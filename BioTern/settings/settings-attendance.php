<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/attendance_settings.php';

biotern_boot_session(isset($conn) ? $conn : null);

$current_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['account_role'] ?? '')));
if ($current_role !== 'admin') {
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
];

$save_success = '';
$save_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = [
        'credit_mode' => (string)($_POST['credit_mode'] ?? 'actual'),
        'biometric_window_enabled' => isset($_POST['biometric_window_enabled']) ? '1' : '0',
        'scheduled_slot_display' => isset($_POST['scheduled_slot_display']) ? '1' : '0',
        'live_timer_uses_schedule_cutoff' => isset($_POST['live_timer_uses_schedule_cutoff']) ? '1' : '0',
        'apply_to_external' => isset($_POST['apply_to_external']) ? '1' : '0',
    ];

    if (!in_array($posted['credit_mode'], ['actual', 'schedule'], true)) {
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
    }
}

$settings = biotern_attendance_settings($conn);

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
                        </div>

                        <div class="settings-actions">
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
