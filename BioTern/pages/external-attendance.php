<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/lib/attendance_rules.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/lib/section_format.php';

biotern_boot_session(isset($conn) ? $conn : null);
external_attendance_ensure_schema($conn);
section_schedule_ensure_columns($conn);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($currentUserId <= 0) {
    header('Location: auth/auth-login.php');
    exit;
}

if ($currentRole === 'student' && !defined('BIOTERN_ALLOW_STUDENT_EXTERNAL_DTR')) {
    header('Location: student-external-dtr.php');
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

function external_attendance_action_locked(array $record, string $clockType): bool
{
    $column = attendance_action_to_column($clockType);
    if ($column === null) {
        return true;
    }
    if (!empty($record[$column])) {
        return true;
    }

    $order = ['morning_in', 'morning_out', 'afternoon_in', 'afternoon_out'];
    $currentIndex = array_search($clockType, $order, true);
    if ($currentIndex === false) {
        return true;
    }

    for ($i = $currentIndex + 1; $i < count($order); $i++) {
        $laterColumn = attendance_action_to_column($order[$i]);
        if ($laterColumn !== null && !empty($record[$laterColumn])) {
            return true;
        }
    }

    $previousAction = attendance_expected_previous($clockType);
    if ($previousAction !== null) {
        $previousColumn = attendance_action_to_column($previousAction);
        if ($previousColumn !== null && empty($record[$previousColumn])) {
            return true;
        }
    }

    return false;
}

if ($currentRole === 'student') {
    $student = external_attendance_student_context($conn, $currentUserId);
    if (!$student) {
        external_attendance_flash_redirect('Student profile not found for external attendance.', 'danger', 'homepage.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = strtolower(trim((string)($_POST['external_action'] ?? '')));
        if ($action === 'quick_clock') {
            $clockDate = trim((string)($_POST['clock_date'] ?? date('Y-m-d')));
            $clockType = strtolower(trim((string)($_POST['clock_type'] ?? '')));
            $clockTime = date('H:i:s');
            $notes = trim((string)($_POST['notes'] ?? ''));

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clockDate)) {
                external_attendance_flash_redirect('A valid clock date is required.', 'danger');
            }

            $existing = external_attendance_student_record($conn, (int)$student['id'], $clockDate) ?: [
                'morning_time_in' => null,
                'morning_time_out' => null,
                'afternoon_time_in' => null,
                'afternoon_time_out' => null,
            ];

            $validation = attendance_validate_transition($existing, $clockType, $clockTime);
            if (!($validation['ok'] ?? false)) {
                external_attendance_flash_redirect((string)($validation['message'] ?? 'Invalid external DTR punch.'), 'warning');
            }

            $column = attendance_action_to_column($clockType);
            if ($column === null) {
                external_attendance_flash_redirect('Invalid external DTR punch.', 'danger');
            }

            $payload = [
                'morning_time_in' => null,
                'morning_time_out' => null,
                'afternoon_time_in' => null,
                'afternoon_time_out' => null,
            ];
            $payload[$column] = $clockTime;

            $save = external_attendance_upsert_day(
                $conn,
                $student,
                $clockDate,
                $payload,
                null,
                $notes,
                $currentUserId,
                true,
                'external-biometric'
            );

            if (!empty($save['ok'])) {
                external_attendance_flash_redirect(
                    ucfirst(str_replace('_', ' ', $clockType)) . ' recorded at ' . date('h:i A', strtotime($clockTime)) . '.',
                    'success'
                );
            }

            external_attendance_flash_redirect((string)($save['message'] ?? 'Could not save the external DTR punch.'), 'danger');
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

    $today = date('Y-m-d');
    $todayRecord = external_attendance_student_record($conn, (int)$student['id'], $today) ?: [
        'morning_time_in' => null,
        'morning_time_out' => null,
        'afternoon_time_in' => null,
        'afternoon_time_out' => null,
    ];
    $clockTypes = [
        'morning_in' => ['Morning In', 'feather-sunrise'],
        'morning_out' => ['Morning Out', 'feather-arrow-up-right'],
        'afternoon_in' => ['Afternoon In', 'feather-sun'],
        'afternoon_out' => ['Afternoon Out', 'feather-sunset'],
    ];
    $page_title = 'BioTern || External DTR';
    $page_styles = [
        'assets/css/homepage-student.css',
        'assets/css/student-dtr.css',
        'assets/css/modules/pages/page-external-biometric.css',
        'assets/css/modules/pages/page-external-attendance-student.css',
    ];
    include 'includes/header.php';
    ?>
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="main-content">
                <div class="student-home-shell student-dtr-shell biometric-container">
                    <?php if (is_array($externalFlash) && !empty($externalFlash['message'])): ?>
                        <div class="alert alert-<?php echo htmlspecialchars((string)($externalFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string)$externalFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <section class="bio-hero">
                        <div class="bio-hero-chip">
                            <i class="feather-shield"></i>
                            <span>External DTR Biometric Demo</span>
                        </div>
                        <h2><?php echo htmlspecialchars(trim((string)($student['first_name'] . ' ' . $student['last_name'])), ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p>Use the same one-tap biometric flow for your external DTR. The system uses your current account automatically and disables punches that are already recorded.</p>
                        <div class="student-home-meta mt-3">
                            <span><?php echo htmlspecialchars((string)($student['course_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><?php echo htmlspecialchars(biotern_format_section_code((string)($student['section_code'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span>External target: <?php echo (int)($student['external_total_hours'] ?? 0); ?> hrs</span>
                            <span>Remaining: <?php echo (int)($student['external_total_hours_remaining'] ?? 0); ?> hrs</span>
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

                    <div class="bio-layout">
                        <aside class="scanner-card">
                            <figure class="fingerprint-image">
                                <div class="display-4 text-primary"><i class="feather-shield"></i></div>
                                <p class="scan-label">ACCOUNT-LINKED BIOMETRIC ACTION</p>
                            </figure>
                            <div class="scanner-stat">
                                External DTR for <?php echo htmlspecialchars(date('F d, Y', strtotime($today)), ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </aside>

                        <section class="clock-section">
                            <h3>Quick External DTR Punch</h3>
                            <div class="time-display mb-3" id="externalCurrentTime"><?php echo date('H:i:s'); ?></div>
                            <form method="post" id="externalBiometricForm">
                                <input type="hidden" name="external_action" value="quick_clock">
                                <input type="hidden" name="clock_date" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>">

                                <div class="form-group-custom">
                                    <label>Clock Type</label>
                                    <div class="clock-type-grid">
                                        <?php foreach ($clockTypes as $type => [$label, $iconClass]): ?>
                                            <?php $isLocked = external_attendance_action_locked($todayRecord, $type); ?>
                                            <button
                                                type="submit"
                                                class="clock-btn external-clock-btn<?php echo $isLocked ? ' is-complete' : ''; ?>"
                                                name="clock_type"
                                                value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
                                                <?php echo $isLocked ? 'disabled aria-disabled="true"' : ''; ?>
                                            >
                                                <i class="<?php echo htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8'); ?>"></i><br><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="form-group-custom">
                                    <label for="externalPunchNotes">Notes</label>
                                    <input type="text" name="notes" id="externalPunchNotes" maxlength="255" placeholder="Optional note for this punch">
                                </div>
                            </form>
                        </section>
                    </div>

                    <section class="record-section mb-4">
                        <div class="card-header border-0 bg-transparent px-4 pt-4">
                            <h5 class="mb-0">Today&apos;s External DTR Status</h5>
                        </div>
                        <div class="card-body pt-3">
                            <div class="row g-3">
                                <?php foreach ($clockTypes as $type => [$label]): ?>
                                    <?php $column = attendance_action_to_column($type); ?>
                                    <div class="col-md-4 col-sm-6">
                                        <div class="dtr-summary-card">
                                            <div class="dtr-summary-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="dtr-summary-value" style="font-size: 1rem;">
                                                <?php
                                                $value = $column !== null ? trim((string)($todayRecord[$column] ?? '')) : '';
                                                echo $value !== '' ? htmlspecialchars(date('h:i A', strtotime($value)), ENT_QUOTES, 'UTF-8') : '--';
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>

                    <section class="record-section mb-4">
                        <div class="card-header border-0 bg-transparent px-4 pt-4">
                            <h5 class="mb-1">Manual External DTR Capture</h5>
                            <p class="text-muted mb-0">Open the dedicated encoder page when you need to enter many days from a physical DTR without crowding this screen.</p>
                        </div>
                        <div class="card-body pt-3">
                            <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
                                <div>
                                    <div class="fw-semibold">Need to encode many dates?</div>
                                    <div class="text-muted small">Use the standalone page for long physical DTR batches like 10 to 20 daily rows.</div>
                                </div>
                                <a href="external-attendance-manual.php" class="btn btn-primary">
                                    <i class="feather-edit-3 me-2"></i>
                                    <span>Open Manual DTR Page</span>
                                </a>
                            </div>
                        </div>
                    </section>

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
                                <table class="table table-hover mb-0 external-history-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Morning</th>
                                            <th>Afternoon</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($monthRows === []): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-4">No external attendance records yet for this month.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($monthRows as $row): ?>
                                                <tr>
                                                    <td data-label="Date"><?php echo htmlspecialchars(date('M d, Y', strtotime((string)$row['attendance_date'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td data-label="Morning"><?php echo htmlspecialchars(trim((string)($row['morning_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['morning_time_in'])) . ' - ' . (trim((string)($row['morning_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['morning_time_out'])) : '--')) : '--', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td data-label="Afternoon"><?php echo htmlspecialchars(trim((string)($row['afternoon_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['afternoon_time_in'])) . ' - ' . (trim((string)($row['afternoon_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['afternoon_time_out'])) : '--')) : '--', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td data-label="Total">
                                                        <?php echo number_format((float)($row['total_hours'] ?? 0), 2); ?> hrs
                                                        <?php if ((float)($row['multiplier'] ?? 1) > 1): ?>
                                                            <div class="small text-muted">x<?php echo number_format((float)$row['multiplier'], 2); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Status"><span class="badge bg-soft-<?php echo strtolower((string)($row['status'] ?? 'pending')) === 'approved' ? 'success text-success' : (strtolower((string)($row['status'] ?? 'pending')) === 'rejected' ? 'danger text-danger' : 'warning text-warning'); ?>"><?php echo htmlspecialchars(ucfirst((string)($row['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                                    <td data-label="Notes"><?php echo htmlspecialchars((string)($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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
    <script>
    (function () {
        var timeNode = document.getElementById('externalCurrentTime');
        var form = document.getElementById('externalBiometricForm');
        if (timeNode) {
            var syncTime = function () {
                var now = new Date();
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var seconds = String(now.getSeconds()).padStart(2, '0');
                timeNode.textContent = hours + ':' + minutes + ':' + seconds;
            };
            syncTime();
            window.setInterval(syncTime, 1000);
        }

        if (form) {
            Array.prototype.forEach.call(form.querySelectorAll('.external-clock-btn'), function (button) {
                button.addEventListener('click', function () {
                    if (button.disabled) {
                        return;
                    }
                    button.disabled = true;
                });
            });
        }
    }());
    </script>
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
        COALESCE(NULLIF(u.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
        c.name AS course_name
    FROM external_attendance ea
    LEFT JOIN students s ON s.id = ea.student_id
    LEFT JOIN users u ON u.id = s.user_id
    LEFT JOIN courses c ON c.id = s.course_id
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

            <div class="page-header page-header-with-middle page-header-condensed-actions">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">External Attendance DTR</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="attendance.php">Internal DTR</a></li>
                        <li class="breadcrumb-item">External DTR</li>
                    </ul>
                </div>
                <div class="page-header-middle">
                    <p class="page-header-statement">Review submitted external DTR entries and update approval statuses in one place.</p>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items-wrapper">
                        <form method="get" class="d-flex gap-2 align-items-center">
                            <select name="status" class="form-select">
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                            </select>
                            <button type="submit" class="btn btn-light-brand">Filter</button>
                        </form>
                        <a href="apps-calendar.php" class="btn btn-primary">
                            <i class="feather-calendar me-2"></i>
                            <span>Open Calendar</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="card stretch stretch-full mb-4">
                <div class="card-body">
                    <div>
                        <div>
                            <h5 class="mb-1">External Attendance Review Queue</h5>
                            <p class="text-muted mb-0">Attendance multipliers are now managed from the calendar event modal instead of this page.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="mb-0">External Attendance Review Queue</h5>
                </div>
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
                                    <th>Source</th>
                                    <th>Photo</th>
                                    <th>Notes</th>
                                    <th>Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($rows === []): ?>
                                    <tr><td colspan="9" class="text-center text-muted py-4">No external attendance records found.</td></tr>
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
                                                <div class="small"><?php echo htmlspecialchars(trim((string)($row['break_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['break_time_in'])) . ' - ' . (trim((string)($row['break_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['break_time_out'])) : '--')) : 'No break record', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="small"><?php echo htmlspecialchars(trim((string)($row['afternoon_time_in'] ?? '')) !== '' ? (date('g:i A', strtotime((string)$row['afternoon_time_in'])) . ' - ' . (trim((string)($row['afternoon_time_out'] ?? '')) !== '' ? date('g:i A', strtotime((string)$row['afternoon_time_out'])) : '--')) : 'No afternoon record', ENT_QUOTES, 'UTF-8'); ?></div>
                                            </td>
                                            <td>
                                                <?php echo number_format((float)($row['total_hours'] ?? 0), 2); ?> hrs
                                                <?php if ((float)($row['multiplier'] ?? 1) > 1): ?>
                                                    <div class="small text-muted"><?php echo htmlspecialchars((string)($row['multiplier_reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars(ucfirst((string)($row['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['source'] ?? 'manual'), ENT_QUOTES, 'UTF-8'); ?></td>
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
