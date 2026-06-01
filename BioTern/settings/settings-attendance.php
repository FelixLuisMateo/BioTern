<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/attendance_settings.php';
require_once dirname(__DIR__) . '/lib/attendance_bonus_rules.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';

biotern_boot_session(isset($conn) ? $conn : null);
attendance_bonus_rules_ensure_schema($conn);

$current_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['account_role'] ?? '')));
$current_user_id = function_exists('get_current_user_id_or_zero') ? get_current_user_id_or_zero() : (int)($_SESSION['user_id'] ?? 0);
if (!in_array($current_role, ['admin', 'coordinator'], true)) {
    header('Location: ../homepage.php');
    exit;
}

function as_h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$field_meta = [
    'credit_mode' => 'Controls whether attendance hours use real punches or are limited to the scheduled class window.',
    'biometric_window_enabled' => 'When enabled, biometric imports can reject punches outside the machine attendance window.',
    'scheduled_slot_display' => 'Shows scheduled class placeholders inside empty time slots.',
    'live_timer_uses_schedule_cutoff' => 'When enabled, live student countdown stops at the scheduled end time.',
    'apply_to_external' => 'Allows these attendance rules to apply to external attendance flows later.',
    'missing_schedule_notifications_enabled' => 'Notifies admins and assigned coordinators when attendance exists but a section schedule is missing.',
    'student_absence_notify_days' => 'Scheduled absence streak length before assigned staff are notified.',
    'discipline_default_suspension_days' => 'Default number of calendar days used to prefill student suspension records.',
];

$save_success = '';
$save_error = '';

function attendance_settings_date(?string $value): ?string
{
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $value ? $value : null;
}

function attendance_settings_section_options(mysqli $conn, string $role, int $userId): array
{
    $where = [];
    if ($role === 'coordinator') {
        $courseIds = function_exists('coordinator_course_ids') ? coordinator_course_ids($conn, $userId) : [];
        if ($courseIds === []) {
            return [];
        }
        $where[] = 's.course_id IN (' . implode(',', array_map('intval', $courseIds)) . ')';
    }
    $sql = "SELECT s.id, s.code, s.name, c.name AS course_name
            FROM sections s
            LEFT JOIN courses c ON c.id = s.course_id";
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY c.name ASC, s.code ASC, s.name ASC";
    $rows = [];
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->close();
    }
    return $rows;
}

function attendance_settings_can_use_section(mysqli $conn, int $sectionId, string $role, int $userId): bool
{
    if ($sectionId <= 0) {
        return $role === 'admin';
    }
    if ($role === 'admin') {
        return true;
    }
    $courseIds = function_exists('coordinator_course_ids') ? coordinator_course_ids($conn, $userId) : [];
    if ($courseIds === []) {
        return false;
    }
    $stmt = $conn->prepare('SELECT id FROM sections WHERE id = ? AND course_id IN (' . implode(',', array_map('intval', $courseIds)) . ') LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $sectionId);
    $stmt->execute();
    $ok = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authorityAction = strtolower(trim((string)($_POST['authority_action'] ?? 'save_settings')));
    if ($authorityAction === 'delete_x2_rule') {
        $ruleId = (int)($_POST['rule_id'] ?? 0);
        $scopeSql = '';
        if ($current_role === 'coordinator') {
            $courseIds = function_exists('coordinator_course_ids') ? coordinator_course_ids($conn, $current_user_id) : [];
            if ($courseIds === []) {
                $scopeSql = ' AND 1 = 0';
            } else {
                $scopeSql = ' AND section_id IN (SELECT id FROM sections WHERE course_id IN (' . implode(',', array_map('intval', $courseIds)) . '))';
            }
        }
        $stmt = $conn->prepare("UPDATE attendance_bonus_rules SET is_active = 0, updated_at = NOW() WHERE id = ? AND title LIKE 'Authority x2 Saturday%'{$scopeSql}");
        if ($stmt) {
            $stmt->bind_param('i', $ruleId);
            $stmt->execute();
            $save_success = $stmt->affected_rows > 0 ? 'x2 Saturday date rule disabled.' : '';
            $save_error = $stmt->affected_rows > 0 ? '' : 'Unable to disable that x2 rule.';
            $stmt->close();
        }
    }

    $posted = [
        'credit_mode' => (string)($_POST['credit_mode'] ?? 'actual'),
        'biometric_window_enabled' => isset($_POST['biometric_window_enabled']) ? '1' : '0',
        'scheduled_slot_display' => isset($_POST['scheduled_slot_display']) ? '1' : '0',
        'live_timer_uses_schedule_cutoff' => isset($_POST['live_timer_uses_schedule_cutoff']) ? '1' : '0',
        'apply_to_external' => isset($_POST['apply_to_external']) ? '1' : '0',
        'missing_schedule_notifications_enabled' => isset($_POST['missing_schedule_notifications_enabled']) ? '1' : '0',
        'student_absence_notify_days' => (string)max(1, min(30, (int)($_POST['student_absence_notify_days'] ?? 3))),
        'discipline_default_suspension_days' => (string)max(1, min(180, (int)($_POST['discipline_default_suspension_days'] ?? 7))),
    ];

    if ($authorityAction === 'delete_x2_rule') {
        // Deleting a date rule does not require rewriting all settings.
    } elseif (!in_array($posted['credit_mode'], ['actual', 'schedule'], true)) {
        $save_error = 'Attendance credit mode is invalid.';
    } else {
        $ok = true;
        foreach ($posted as $key => $value) {
            if (!biotern_attendance_save_setting($conn, $key, $value, $field_meta[$key] ?? '')) {
                $ok = false;
                break;
            }
        }
        $save_success = $ok ? 'Attendance settings saved successfully.' : '';
        $save_error = $ok ? '' : 'Unable to save one or more attendance settings right now.';

        $x2Start = attendance_settings_date((string)($_POST['x2_start_date'] ?? ''));
        $x2End = attendance_settings_date((string)($_POST['x2_end_date'] ?? ''));
        $x2SectionId = (int)($_POST['x2_section_id'] ?? 0);
        if ($ok && $x2Start !== null) {
            if ($x2End !== null && $x2End < $x2Start) {
                [$x2Start, $x2End] = [$x2End, $x2Start];
            }
            if (!attendance_settings_can_use_section($conn, $x2SectionId, $current_role, $current_user_id)) {
                $save_success = '';
                $save_error = 'You can only create x2 rules for sections under your authority.';
            } else {
                $x2Title = 'Authority x2 Saturday date rule';
                $appliesTo = 'internal';
                $weekday = 'saturday';
                $multiplier = 2.0;
                $notes = 'Created from Attendance Settings.';
                $sectionParam = $x2SectionId > 0 ? $x2SectionId : null;
                $stmt = $conn->prepare(
                    "INSERT INTO attendance_bonus_rules
                        (title, section_id, department_id, applies_to, weekday_key, start_date, end_date, multiplier, is_active, notes, created_at, updated_at)
                     VALUES (?, ?, NULL, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())"
                );
                if ($stmt) {
                    $stmt->bind_param('sissssds', $x2Title, $sectionParam, $appliesTo, $weekday, $x2Start, $x2End, $multiplier, $notes);
                    $ruleOk = $stmt->execute();
                    $stmt->close();
                    $save_success = $ruleOk ? trim($save_success . ' x2 Saturday date rule added.') : '';
                    $save_error = $ruleOk ? $save_error : 'Attendance settings saved, but the x2 rule could not be added.';
                }
            }
        }
    }
}

$settings = biotern_attendance_settings($conn);
$sectionOptions = attendance_settings_section_options($conn, $current_role, $current_user_id);
$x2RulesWhere = ["abr.is_active = 1", "abr.title LIKE 'Authority x2 Saturday%'"];
if ($current_role === 'coordinator') {
    $courseIds = function_exists('coordinator_course_ids') ? coordinator_course_ids($conn, $current_user_id) : [];
    $x2RulesWhere[] = $courseIds === []
        ? '1 = 0'
        : 'abr.section_id IN (SELECT id FROM sections WHERE course_id IN (' . implode(',', array_map('intval', $courseIds)) . '))';
}
$x2Rules = [];
$x2Res = $conn->query("
    SELECT abr.*, sec.code AS section_code, sec.name AS section_name
    FROM attendance_bonus_rules abr
    LEFT JOIN sections sec ON sec.id = abr.section_id
    WHERE " . implode(' AND ', $x2RulesWhere) . "
    ORDER BY abr.start_date DESC, abr.id DESC
    LIMIT 25
");
if ($x2Res instanceof mysqli_result) {
    while ($row = $x2Res->fetch_assoc()) {
        $x2Rules[] = $row;
    }
    $x2Res->close();
}

$page_title = 'Attendance Settings';
$page_body_class = 'settings-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/settings/settings-shell.css',
    'assets/css/modules/settings/page-settings-suite.css',
];
require_once dirname(__DIR__) . '/includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Attendance Settings</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="settings-general.php">Settings</a></li>
                    <li class="breadcrumb-item">Attendance</li>
                </ul>
            </div>
        </div>

        <div class="main-content settings-shell">
            <div class="settings-layout">
                <section class="settings-main">
                    <?php if ($save_success !== ''): ?>
                        <div class="alert alert-success settings-alert" role="alert"><?= as_h($save_success) ?></div>
                    <?php endif; ?>
                    <?php if ($save_error !== ''): ?>
                        <div class="alert alert-danger settings-alert" role="alert"><?= as_h($save_error) ?></div>
                    <?php endif; ?>

                    <form method="post" class="settings-form-card">
                        <div class="settings-form-header">
                            <div>
                                <h4>Attendance Rules</h4>
                                <p>Default behavior counts the real biometric time. Turn the old schedule limits back on only when you need them.</p>
                            </div>
                            <div class="settings-badge">Saved to system_settings</div>
                        </div>

                        <div class="settings-grid">
                            <div class="settings-field-card">
                                <label class="form-label" for="credit_mode">Hours Credit Mode</label>
                                <select class="form-select" id="credit_mode" name="credit_mode">
                                    <option value="actual"<?= $settings['credit_mode'] === 'actual' ? ' selected' : '' ?>>Actual clock time</option>
                                    <option value="schedule"<?= $settings['credit_mode'] === 'schedule' ? ' selected' : '' ?>>Limit to class schedule</option>
                                </select>
                                <small class="form-text">Actual mode credits 7:30 AM when the student really clocks in at 7:30 AM.</small>
                            </div>

                            <div class="settings-field-card settings-toggle">
                                <div class="settings-toggle-copy">
                                    <label class="form-label mb-0" for="biometric_window_enabled">Use Biometric Import Window</label>
                                    <small class="form-text mt-0">Reject punches outside the configured machine window when this is on.</small>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" id="biometric_window_enabled" name="biometric_window_enabled" type="checkbox" value="1"<?= $settings['biometric_window_enabled'] === '1' ? ' checked' : '' ?>>
                                </div>
                            </div>

                            <div class="settings-field-card settings-toggle">
                                <div class="settings-toggle-copy">
                                    <label class="form-label mb-0" for="scheduled_slot_display">Show Class Time in Empty Slots</label>
                                    <small class="form-text mt-0">Example: Morning In shows 8:00 AM Class when the class starts at 8:00 AM.</small>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" id="scheduled_slot_display" name="scheduled_slot_display" type="checkbox" value="1"<?= $settings['scheduled_slot_display'] === '1' ? ' checked' : '' ?>>
                                </div>
                            </div>

                            <div class="settings-field-card settings-toggle">
                                <div class="settings-toggle-copy">
                                    <label class="form-label mb-0" for="live_timer_uses_schedule_cutoff">Stop Live Timer at Schedule End</label>
                                    <small class="form-text mt-0">Off by default so the student view keeps ticking while the student is still clocked in.</small>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" id="live_timer_uses_schedule_cutoff" name="live_timer_uses_schedule_cutoff" type="checkbox" value="1"<?= $settings['live_timer_uses_schedule_cutoff'] === '1' ? ' checked' : '' ?>>
                                </div>
                            </div>

                            <div class="settings-field-card settings-toggle">
                                <div class="settings-toggle-copy">
                                    <label class="form-label mb-0" for="apply_to_external">Allow External Attendance Support</label>
                                    <small class="form-text mt-0">Keeps the setting ready for external attendance without changing current external pages unless enabled.</small>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" id="apply_to_external" name="apply_to_external" type="checkbox" value="1"<?= $settings['apply_to_external'] === '1' ? ' checked' : '' ?>>
                                </div>
                            </div>

                            <div class="settings-field-card settings-toggle">
                                <div class="settings-toggle-copy">
                                    <label class="form-label mb-0" for="missing_schedule_notifications_enabled">Notify Missing Section Schedules</label>
                                    <small class="form-text mt-0">Alerts admins/coordinators when biometric rows exist but a section day has no schedule set.</small>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" id="missing_schedule_notifications_enabled" name="missing_schedule_notifications_enabled" type="checkbox" value="1"<?= $settings['missing_schedule_notifications_enabled'] === '1' ? ' checked' : '' ?>>
                                </div>
                            </div>

                            <div class="settings-field-card">
                                <label class="form-label" for="student_absence_notify_days">Absence Notification Days</label>
                                <input class="form-control" id="student_absence_notify_days" name="student_absence_notify_days" type="number" min="1" max="30" step="1" value="<?= as_h((string)$settings['student_absence_notify_days']) ?>">
                                <small class="form-text">Assigned staff are notified when a student reaches this many required absent days.</small>
                            </div>

                            <div class="settings-field-card">
                                <label class="form-label" for="discipline_default_suspension_days">Default Suspension Days</label>
                                <input class="form-control" id="discipline_default_suspension_days" name="discipline_default_suspension_days" type="number" min="1" max="180" step="1" value="<?= as_h((string)$settings['discipline_default_suspension_days']) ?>">
                                <small class="form-text">Prefills the suspension end date in the student discipline tab.</small>
                            </div>

                            <div class="settings-field-card">
                                <label class="form-label" for="x2_start_date">x2 Saturday Start Date</label>
                                <input class="form-control" id="x2_start_date" name="x2_start_date" type="date">
                                <small class="form-text">Optional authority date rule. Only Saturdays inside the range receive 2x credit.</small>
                            </div>

                            <div class="settings-field-card">
                                <label class="form-label" for="x2_end_date">x2 Saturday End Date</label>
                                <input class="form-control" id="x2_end_date" name="x2_end_date" type="date">
                                <small class="form-text">Leave blank for one Saturday or an open-ended authority rule.</small>
                            </div>

                            <div class="settings-field-card">
                                <label class="form-label" for="x2_section_id">x2 Section Scope</label>
                                <select class="form-select" id="x2_section_id" name="x2_section_id">
                                    <?php if ($current_role === 'admin'): ?>
                                        <option value="0">All sections</option>
                                    <?php endif; ?>
                                    <?php foreach ($sectionOptions as $sectionOption): ?>
                                        <?php
                                        $sectionLabel = trim((string)($sectionOption['code'] ?? ''));
                                        if ($sectionLabel === '') {
                                            $sectionLabel = trim((string)($sectionOption['name'] ?? 'Section ' . (int)$sectionOption['id']));
                                        }
                                        $courseLabel = trim((string)($sectionOption['course_name'] ?? ''));
                                        ?>
                                        <option value="<?= (int)$sectionOption['id'] ?>"><?= as_h($sectionLabel . ($courseLabel !== '' ? ' - ' . $courseLabel : '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text"><?= $current_role === 'admin' ? 'Admins may apply the rule globally or to one section.' : 'Coordinators can only choose sections in their assigned courses.' ?></small>
                            </div>
                        </div>

                        <?php if ($x2Rules !== []): ?>
                            <div class="table-responsive mt-4">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>x2 Date Rule</th>
                                            <th>Scope</th>
                                            <th>Dates</th>
                                            <th>Multiplier</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($x2Rules as $rule): ?>
                                            <?php
                                            $scopeLabel = (int)($rule['section_id'] ?? 0) > 0
                                                ? trim((string)($rule['section_code'] ?? '') . ' ' . (string)($rule['section_name'] ?? ''))
                                                : 'All sections';
                                            ?>
                                            <tr>
                                                <td><?= as_h((string)$rule['title']) ?></td>
                                                <td><?= as_h(trim($scopeLabel) !== '' ? $scopeLabel : 'Section #' . (int)$rule['section_id']) ?></td>
                                                <td><?= as_h(((string)($rule['start_date'] ?? '') ?: '-') . ' to ' . ((string)($rule['end_date'] ?? '') ?: 'open')) ?></td>
                                                <td><?= as_h(number_format((float)($rule['multiplier'] ?? 2), 2)) ?>x</td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit" name="authority_action" value="delete_x2_rule" formaction="settings-attendance.php" formmethod="post" onclick="this.form.rule_id.value='<?= (int)$rule['id'] ?>'">Disable</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <div class="settings-actions">
                            <input type="hidden" name="authority_action" value="save_settings">
                            <input type="hidden" name="rule_id" value="0">
                            <a class="btn btn-outline-light" href="../homepage.php">Cancel</a>
                            <button class="btn btn-primary" type="submit">
                                <i class="feather-save me-2"></i>
                                Save Attendance Settings
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
</main>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
