<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/lib/attendance_rules.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
biotern_boot_session(isset($conn) ? $conn : null);
external_attendance_ensure_schema($conn);
attendance_bonus_rules_ensure_schema($conn);
section_schedule_ensure_columns($conn);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($currentUserId <= 0) {
    header('Location: auth/auth-login.php');
    exit;
}

$externalFlash = $_SESSION['external_attendance_flash'] ?? null;
unset($_SESSION['external_attendance_flash']);

function external_attendance_flash_redirect(string $message, string $type, string $target = 'external-attendance.php'): void
{
    $_SESSION['external_attendance_flash'] = ['message' => $message, 'type' => $type];
    header('Location: ' . $target);
    exit;
}

function external_attendance_month_rows(mysqli $conn, int $studentId, string $monthStart, string $monthEnd): array
{
    $rows = [];
    $stmt = $conn->prepare("
        SELECT *
        FROM external_attendance
        WHERE student_id = ? AND attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date DESC, id DESC
    ");
    if (!$stmt) {
        return $rows;
    }
    $stmt->bind_param('iss', $studentId, $monthStart, $monthEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

if ($currentRole === 'student') {
    $student = external_attendance_student_context($conn, $currentUserId);
    if (!$student) {
        external_attendance_flash_redirect('Student record not found.', 'danger');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = strtolower(trim((string)($_POST['external_action'] ?? '')));
        if ($action === 'quick_clock') {
            $clockDate = trim((string)($_POST['clock_date'] ?? ''));
            $clockType = trim((string)($_POST['clock_type'] ?? ''));
            $clockTime = external_attendance_normalize_time((string)($_POST['clock_time'] ?? ''));
            $clockNotes = trim((string)($_POST['notes'] ?? ''));
            $column = attendance_action_to_column($clockType);
            $existing = external_attendance_student_record($conn, (int)$student['id'], $clockDate);

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clockDate)) {
                external_attendance_flash_redirect('Valid attendance date is required.', 'danger');
            }
            if ($column === null || $clockTime === null) {
                external_attendance_flash_redirect('Valid clock type and time are required.', 'danger');
            }

            $photoPath = '';
            if (isset($_FILES['photo']) && (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $upload = external_attendance_store_photo($_FILES['photo'], (int)$student['id'], $clockDate);
                if (!($upload['ok'] ?? false)) {
                    external_attendance_flash_redirect((string)$upload['message'], 'danger');
                }
                $photoPath = (string)$upload['path'];
            } elseif (!$existing || trim((string)($existing['photo_path'] ?? '')) === '') {
                external_attendance_flash_redirect('A verification photo is required the first time you submit for this day.', 'danger');
            }

            $payload = [
                'morning_time_in' => null,
                'morning_time_out' => null,
                'break_time_in' => null,
                'break_time_out' => null,
                'afternoon_time_in' => null,
                'afternoon_time_out' => null,
            ];
            $payload[$column] = $clockTime;

            $saved = external_attendance_upsert_day($conn, $student, $clockDate, $payload, $photoPath, $clockNotes, $currentUserId);
            external_attendance_flash_redirect((string)$saved['message'], !empty($saved['ok']) ? 'success' : 'danger');
        }

        if ($action === 'weekly_manual') {
            $startDate = trim((string)($_POST['attendance_date'] ?? ''));
            $endDate = trim((string)($_POST['attendance_end_date'] ?? ''));
            $notes = trim((string)($_POST['notes'] ?? ''));
            $payload = [
                'morning_time_in' => external_attendance_normalize_time((string)($_POST['morning_time_in'] ?? '')),
                'morning_time_out' => external_attendance_normalize_time((string)($_POST['morning_time_out'] ?? '')),
                'break_time_in' => external_attendance_normalize_time((string)($_POST['break_time_in'] ?? '')),
                'break_time_out' => external_attendance_normalize_time((string)($_POST['break_time_out'] ?? '')),
                'afternoon_time_in' => external_attendance_normalize_time((string)($_POST['afternoon_time_in'] ?? '')),
                'afternoon_time_out' => external_attendance_normalize_time((string)($_POST['afternoon_time_out'] ?? '')),
            ];

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
                external_attendance_flash_redirect('Valid start and end dates are required.', 'danger');
            }
            if ($notes === '') {
                external_attendance_flash_redirect('Notes/details are required for weekly external DTR.', 'danger');
            }
            if (!isset($_FILES['photo']) || (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                external_attendance_flash_redirect('A verification photo is required.', 'danger');
            }

            $validation = external_attendance_validate_record($payload);
            if (!($validation['ok'] ?? false)) {
                external_attendance_flash_redirect((string)$validation['message'], 'danger');
            }

            $startTs = strtotime($startDate);
            $endTs = strtotime($endDate);
            if ($startTs === false || $endTs === false || $endTs < $startTs) {
                external_attendance_flash_redirect('End date must be the same as or later than start date.', 'danger');
            }
            if ((($endTs - $startTs) / 86400) > 30) {
                external_attendance_flash_redirect('Range entry is limited to 31 days per submission.', 'danger');
            }

            $upload = external_attendance_store_photo($_FILES['photo'], (int)$student['id'], $startDate);
            if (!($upload['ok'] ?? false)) {
                external_attendance_flash_redirect((string)$upload['message'], 'danger');
            }

            $savedCount = 0;
            for ($cursor = $startTs; $cursor <= $endTs; $cursor += 86400) {
                $targetDate = date('Y-m-d', $cursor);
                $saved = external_attendance_upsert_day($conn, $student, $targetDate, $payload, (string)$upload['path'], $notes, $currentUserId, true);
                if (!empty($saved['ok'])) {
                    $savedCount++;
                }
            }

            external_attendance_flash_redirect(
                $savedCount > 0
                    ? ('External weekly DTR submitted for ' . $savedCount . ' day(s).')
                    : 'No external attendance dates were saved.',
                $savedCount > 0 ? 'success' : 'danger'
            );
        }
    }

    $selectedMonth = trim((string)($_GET['month'] ?? date('Y-m')));
    if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
        $selectedMonth = date('Y-m');
    }
    $monthStart = $selectedMonth . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    $monthRows = external_attendance_month_rows($conn, (int)$student['id'], $monthStart, $monthEnd);
    $monthHours = 0.0;
    $approvedCount = 0;
    $pendingCount = 0;
    foreach ($monthRows as $row) {
        $monthHours += (float)($row['total_hours'] ?? 0);
        $status = strtolower(trim((string)($row['status'] ?? 'pending')));
        if ($status === 'approved') {
            $approvedCount++;
        } elseif ($status === 'pending') {
            $pendingCount++;
        }
    }

    $page_title = 'BioTern || External DTR';
    $page_styles = ['assets/css/homepage-student.css', 'assets/css/student-dtr.css'];
    include 'includes/header.php';
    ?>
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="main-content">
                <div class="student-home-shell student-dtr-shell">
                    <?php if (is_array($externalFlash) && !empty($externalFlash['message'])): ?>
                        <div class="alert alert-<?php echo htmlspecialchars((string)($externalFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string)$externalFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <section class="card student-home-hero border-0 student-dtr-hero-card mb-4">
                        <div class="card-body">
                            <span class="student-home-eyebrow">External Attendance / DTR</span>
                            <h2><?php echo htmlspecialchars(trim((string)($student['first_name'] . ' ' . $student['last_name'])), ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p>Use the quick clock panel for one punch at a time, or use the weekly manual panel when you need to submit the same external schedule across a date range. Photo verification is required.</p>
                            <div class="student-home-meta">
                                <span><?php echo htmlspecialchars((string)($student['course_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span><?php echo htmlspecialchars(biotern_format_section_code((string)($student['section_code'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span>External target: <?php echo (int)($student['external_total_hours'] ?? 0); ?> hrs</span>
                                <span>Remaining: <?php echo (int)($student['external_total_hours_remaining'] ?? 0); ?> hrs</span>
                            </div>
                        </div>
                    </section>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="dtr-summary-card">
                                <div class="dtr-summary-label">Month Hours</div>
                                <div class="dtr-summary-value"><?php echo number_format($monthHours, 2); ?> hrs</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="dtr-summary-card">
                                <div class="dtr-summary-label">Approved Entries</div>
                                <div class="dtr-summary-value"><?php echo (int)$approvedCount; ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="dtr-summary-card">
                                <div class="dtr-summary-label">Pending Review</div>
                                <div class="dtr-summary-value"><?php echo (int)$pendingCount; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-lg-5">
                            <div class="card stretch stretch-full">
                                <div class="card-header"><h5 class="mb-0">Quick External Clock</h5></div>
                                <div class="card-body">
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="external_action" value="quick_clock">
                                        <div class="mb-3">
                                            <label class="form-label">Date</label>
                                            <input type="date" name="clock_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Clock Action</label>
                                            <select name="clock_type" class="form-select" required>
                                                <option value="morning_in">Morning In</option>
                                                <option value="morning_out">Morning Out</option>
                                                <option value="afternoon_in">Afternoon In</option>
                                                <option value="afternoon_out">Afternoon Out</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Time</label>
                                            <input type="time" name="clock_time" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Verification Photo</label>
                                            <input type="file" name="photo" class="form-control" accept="image/*" capture="environment">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Notes</label>
                                            <textarea name="notes" class="form-control" rows="3" placeholder="Example: client visit, field work, double time note, machine unavailable"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">Save External Clock</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="card stretch stretch-full">
                                <div class="card-header"><h5 class="mb-0">Weekly / Range Manual External DTR</h5></div>
                                <div class="card-body">
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="external_action" value="weekly_manual">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Start Date</label>
                                                <input type="date" name="attendance_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">End Date</label>
                                                <input type="date" name="attendance_end_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Morning In</label>
                                                <input type="time" name="morning_time_in" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Morning Out</label>
                                                <input type="time" name="morning_time_out" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Break In</label>
                                                <input type="time" name="break_time_in" class="form-control">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Break Out</label>
                                                <input type="time" name="break_time_out" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Afternoon In</label>
                                                <input type="time" name="afternoon_time_in" class="form-control">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Afternoon Out</label>
                                                <input type="time" name="afternoon_time_out" class="form-control">
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">Verification Photo</label>
                                                <input type="file" name="photo" class="form-control" accept="image/*" capture="environment" required>
                                            </div>
                                            <div class="col-md-12">
                                                <label class="form-label">Notes / Reason</label>
                                                <textarea name="notes" class="form-control" rows="4" placeholder="Describe the external duty, location, and anything the reviewer should know." required></textarea>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary mt-3">Submit External DTR Range</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card stretch stretch-full">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">External Attendance History</h5>
                            <form method="get" class="d-flex gap-2 align-items-center">
                                <label class="small text-muted" for="externalMonth">Month</label>
                                <input id="externalMonth" type="month" name="month" class="form-control" value="<?php echo htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-light-brand">Load</button>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Morning</th>
                                            <th>Break</th>
                                            <th>Afternoon</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Photo</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($monthRows === []): ?>
                                            <tr><td colspan="8" class="text-center text-muted py-4">No external attendance records yet for this month.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($monthRows as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime((string)$row['attendance_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars(trim((string)($row['morning_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['morning_time_in'])) . ' - ' . (trim((string)($row['morning_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['morning_time_out'])) : '--')) : '--', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars(trim((string)($row['break_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['break_time_in'])) . ' - ' . (trim((string)($row['break_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['break_time_out'])) : '--')) : '--', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars(trim((string)($row['afternoon_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['afternoon_time_in'])) . ' - ' . (trim((string)($row['afternoon_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['afternoon_time_out'])) : '--')) : '--', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo number_format((float)($row['total_hours'] ?? 0), 2); ?> hrs<?php if ((float)($row['multiplier'] ?? 1) > 1): ?> <div class="small text-muted">x<?php echo number_format((float)$row['multiplier'], 2); ?></div><?php endif; ?></td>
                                                    <td><span class="badge bg-soft-<?php echo strtolower((string)($row['status'] ?? 'pending')) === 'approved' ? 'success text-success' : (strtolower((string)($row['status'] ?? 'pending')) === 'rejected' ? 'danger text-danger' : 'warning text-warning'); ?>"><?php echo htmlspecialchars(ucfirst((string)($row['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                    <td><?php if (trim((string)($row['photo_path'] ?? '')) !== ''): ?><a href="<?php echo htmlspecialchars((string)$row['photo_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">View</a><?php else: ?>-<?php endif; ?></td>
                                                    <td><?php echo htmlspecialchars((string)($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php
    include 'includes/footer.php';
    return;
}

$canManage = in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true);
if (!$canManage) {
    external_attendance_flash_redirect('You do not have access to external attendance.', 'danger', 'homepage.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string)($_POST['external_admin_action'] ?? '')));
    if ($action === 'review') {
        $recordId = (int)($_POST['record_id'] ?? 0);
        $status = strtolower(trim((string)($_POST['status'] ?? 'pending')));
        $note = trim((string)($_POST['admin_note'] ?? ''));
        if ($recordId > 0 && in_array($status, ['approved', 'rejected', 'pending'], true)) {
            $stmt = $conn->prepare("
                UPDATE external_attendance
                SET status = ?, reviewed_by = ?, reviewed_at = NOW(),
                    notes = CASE WHEN ? <> '' THEN CONCAT(TRIM(COALESCE(notes, '')), CASE WHEN TRIM(COALESCE(notes, '')) = '' THEN '' ELSE ' | ' END, ?) ELSE notes END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            if ($stmt) {
                $stmt->bind_param('sissi', $status, $currentUserId, $note, $note, $recordId);
                $stmt->execute();
                $stmt->close();

                $studentLookup = $conn->prepare("SELECT student_id FROM external_attendance WHERE id = ? LIMIT 1");
                if ($studentLookup) {
                    $studentLookup->bind_param('i', $recordId);
                    $studentLookup->execute();
                    $row = $studentLookup->get_result()->fetch_assoc();
                    $studentLookup->close();
                    if ($row) {
                        external_attendance_sync_student_hours($conn, (int)$row['student_id']);
                    }
                }
                external_attendance_flash_redirect('External attendance review updated.', 'success');
            }
        }
        external_attendance_flash_redirect('Could not update external attendance review.', 'danger');
    }

    if ($action === 'create_bonus_rule') {
        $title = trim((string)($_POST['title'] ?? ''));
        $multiplier = (float)($_POST['multiplier'] ?? 1);
        $weekdayKey = strtolower(trim((string)($_POST['weekday_key'] ?? '')));
        $sectionId = (int)($_POST['section_id'] ?? 0);
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $appliesTo = strtolower(trim((string)($_POST['applies_to'] ?? 'both')));
        $startDate = trim((string)($_POST['start_date'] ?? ''));
        $endDate = trim((string)($_POST['end_date'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));

        if ($title === '' || $multiplier <= 1) {
            external_attendance_flash_redirect('Rule title and multiplier greater than 1.0 are required.', 'danger');
        }
        if (!in_array($weekdayKey, ['', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], true)) {
            external_attendance_flash_redirect('Invalid weekday value for the bonus rule.', 'danger');
        }
        if (!in_array($appliesTo, ['internal', 'external', 'both'], true)) {
            $appliesTo = 'both';
        }

        $stmt = $conn->prepare("
            INSERT INTO attendance_bonus_rules
                (title, section_id, department_id, applies_to, weekday_key, start_date, end_date, multiplier, is_active, notes, created_at, updated_at)
            VALUES
                (?, NULLIF(?, 0), NULLIF(?, 0), ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?, 1, NULLIF(?, ''), NOW(), NOW())
        ");
        if ($stmt) {
            $stmt->bind_param('siissssds', $title, $sectionId, $departmentId, $appliesTo, $weekdayKey, $startDate, $endDate, $multiplier, $notes);
            $stmt->execute();
            $stmt->close();
            external_attendance_flash_redirect('Attendance bonus rule created.', 'success');
        }
        external_attendance_flash_redirect('Could not create attendance bonus rule.', 'danger');
    }
}

$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'pending')));
if (!in_array($statusFilter, ['all', 'pending', 'approved', 'rejected'], true)) {
    $statusFilter = 'pending';
}
$rows = [];
$sql = "
    SELECT
        ea.*,
        s.student_id AS student_number,
        s.user_id,
        s.first_name,
        s.last_name,
        s.department_id,
        s.section_id,
        COALESCE(NULLIF(u.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
        c.name AS course_name,
        d.name AS department_name,
        sec.code AS section_code
    FROM external_attendance ea
    LEFT JOIN students s ON s.id = ea.student_id
    LEFT JOIN users u ON u.id = s.user_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN departments d ON d.id = s.department_id
    LEFT JOIN sections sec ON sec.id = s.section_id
";
if ($statusFilter !== 'all') {
    $sql .= " WHERE ea.status = ?";
}
$sql .= " ORDER BY ea.attendance_date DESC, ea.id DESC LIMIT 200";
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($statusFilter !== 'all') {
        $stmt->bind_param('s', $statusFilter);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
}

$sections = [];
$resSections = $conn->query("SELECT id, COALESCE(NULLIF(code, ''), name) AS label FROM sections ORDER BY label ASC");
if ($resSections instanceof mysqli_result) {
    while ($row = $resSections->fetch_assoc()) {
        $sections[] = $row;
    }
    $resSections->close();
}
$departments = [];
$resDepartments = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($resDepartments instanceof mysqli_result) {
    while ($row = $resDepartments->fetch_assoc()) {
        $departments[] = $row;
    }
    $resDepartments->close();
}
$bonusRules = [];
$bonusRes = $conn->query("
    SELECT abr.*, sec.code AS section_code, d.name AS department_name
    FROM attendance_bonus_rules abr
    LEFT JOIN sections sec ON sec.id = abr.section_id
    LEFT JOIN departments d ON d.id = abr.department_id
    WHERE abr.is_active = 1
    ORDER BY abr.multiplier DESC, abr.id DESC
    LIMIT 50
");
if ($bonusRes instanceof mysqli_result) {
    while ($row = $bonusRes->fetch_assoc()) {
        $bonusRules[] = $row;
    }
    $bonusRes->close();
}

$page_title = 'BioTern || External Attendance';
$page_styles = ['assets/css/modules/management/management-students.css'];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="main-content">
            <?php if (is_array($externalFlash) && !empty($externalFlash['message'])): ?>
                <div class="alert alert-<?php echo htmlspecialchars((string)($externalFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars((string)$externalFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title"><h5 class="m-b-10">External Attendance DTR</h5></div>
                </div>
                <div class="page-header-right ms-auto">
                    <form method="get" class="d-flex gap-2 align-items-center">
                        <select name="status" class="form-select">
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                        </select>
                        <button type="submit" class="btn btn-light-brand">Filter</button>
                    </form>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-xl-4">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h5 class="mb-0">Recurring Bonus Rule</h5></div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="external_admin_action" value="create_bonus_rule">
                                <div class="mb-3">
                                    <label class="form-label">Rule Title</label>
                                    <input type="text" name="title" class="form-control" placeholder="Example: ComLab2 Saturday Double Time" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Applies To</label>
                                    <select name="applies_to" class="form-select">
                                        <option value="both">Both Internal + External</option>
                                        <option value="external">External Only</option>
                                        <option value="internal">Internal Only</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Multiplier</label>
                                    <input type="number" step="0.01" min="1" name="multiplier" class="form-control" value="2.00" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Weekday</label>
                                    <select name="weekday_key" class="form-select">
                                        <option value="">Any day</option>
                                        <option value="monday">Monday</option>
                                        <option value="tuesday">Tuesday</option>
                                        <option value="wednesday">Wednesday</option>
                                        <option value="thursday">Thursday</option>
                                        <option value="friday">Friday</option>
                                        <option value="saturday">Saturday</option>
                                        <option value="sunday">Sunday</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Section</label>
                                    <select name="section_id" class="form-select">
                                        <option value="0">Any section</option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo (int)$section['id']; ?>"><?php echo htmlspecialchars((string)$section['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <select name="department_id" class="form-select">
                                        <option value="0">Any department</option>
                                        <?php foreach ($departments as $department): ?>
                                            <option value="<?php echo (int)$department['id']; ?>"><?php echo htmlspecialchars((string)$department['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" name="start_date" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">End Date</label>
                                        <input type="date" name="end_date" class="form-control">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="3"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Create Bonus Rule</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h5 class="mb-0">Active Bonus Rules</h5></div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Rule</th>
                                            <th>Scope</th>
                                            <th>When</th>
                                            <th>Multiplier</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($bonusRules === []): ?>
                                            <tr><td colspan="4" class="text-center text-muted py-4">No active recurring bonus rules yet.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($bonusRules as $rule): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars((string)$rule['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars(trim((string)($rule['department_name'] ?: $rule['section_code'] ?: 'Global')), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars((string)($rule['weekday_key'] ?: 'Any day'), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>x<?php echo number_format((float)($rule['multiplier'] ?? 1), 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card stretch stretch-full">
                <div class="card-header"><h5 class="mb-0">External Attendance Review Queue</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Date</th>
                                    <th>Schedule</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Photo</th>
                                    <th>Notes</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($rows === []): ?>
                                    <tr><td colspan="8" class="text-center text-muted py-4">No external attendance records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($rows as $row): ?>
                                        <?php $avatar = biotern_avatar_public_src((string)($row['profile_picture'] ?? ''), (int)($row['user_id'] ?? 0)); ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="avatar-image avatar-md"><img src="<?php echo htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="img-fluid"></div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars(trim((string)($row['first_name'] . ' ' . $row['last_name'])), ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div class="small text-muted"><?php echo htmlspecialchars((string)($row['student_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                        <div class="small text-muted"><?php echo htmlspecialchars((string)($row['course_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime((string)$row['attendance_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <div class="small"><?php echo htmlspecialchars(trim((string)($row['morning_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['morning_time_in'])) . ' - ' . (trim((string)($row['morning_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['morning_time_out'])) : '--')) : 'No morning record', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="small"><?php echo htmlspecialchars(trim((string)($row['afternoon_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['afternoon_time_in'])) . ' - ' . (trim((string)($row['afternoon_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['afternoon_time_out'])) : '--')) : 'No afternoon record', ENT_QUOTES, 'UTF-8'); ?></div>
                                            </td>
                                            <td><?php echo number_format((float)($row['total_hours'] ?? 0), 2); ?> hrs<?php if ((float)($row['multiplier'] ?? 1) > 1): ?><div class="small text-muted"><?php echo htmlspecialchars((string)($row['multiplier_reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst((string)($row['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php if (trim((string)($row['photo_path'] ?? '')) !== ''): ?><a href="<?php echo htmlspecialchars((string)$row['photo_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Open</a><?php else: ?>-<?php endif; ?></td>
                                            <td class="small"><?php echo htmlspecialchars((string)($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <form method="post" class="d-grid gap-2">
                                                    <input type="hidden" name="external_admin_action" value="review">
                                                    <input type="hidden" name="record_id" value="<?php echo (int)$row['id']; ?>">
                                                    <select name="status" class="form-select form-select-sm">
                                                        <option value="approved">Approve</option>
                                                        <option value="pending" <?php echo strtolower((string)($row['status'] ?? 'pending')) === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="rejected" <?php echo strtolower((string)($row['status'] ?? 'pending')) === 'rejected' ? 'selected' : ''; ?>>Reject</option>
                                                    </select>
                                                    <input type="text" name="admin_note" class="form-control form-control-sm" placeholder="Review note">
                                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
