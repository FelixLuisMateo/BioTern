<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/lib/attendance_rules.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_roles_page(['admin', 'coordinator', 'supervisor', 'student']);

function parse_time_seconds($time_value) {
    if (empty($time_value)) {
        return null;
    }
    $ts = strtotime($time_value);
    return $ts === false ? null : $ts;
}

function calculate_attendance_hours(array $row) {
    $total_seconds = 0;

    $morning_in = parse_time_seconds($row['morning_time_in'] ?? null);
    $morning_out = parse_time_seconds($row['morning_time_out'] ?? null);
    if ($morning_in !== null && $morning_out !== null && $morning_out > $morning_in) {
        $total_seconds += ($morning_out - $morning_in);
    }

    $afternoon_in = parse_time_seconds($row['afternoon_time_in'] ?? null);
    $afternoon_out = parse_time_seconds($row['afternoon_time_out'] ?? null);
    if ($afternoon_in !== null && $afternoon_out !== null && $afternoon_out > $afternoon_in) {
        $total_seconds += ($afternoon_out - $afternoon_in);
    }

    $break_in = parse_time_seconds($row['break_time_in'] ?? null);
    $break_out = parse_time_seconds($row['break_time_out'] ?? null);
    if ($break_in !== null && $break_out !== null && $break_out > $break_in) {
        $total_seconds -= ($break_out - $break_in);
    }

    if ($total_seconds < 0) {
        $total_seconds = 0;
    }

    return round($total_seconds / 3600, 2);
}

function sync_student_hours(mysqli $conn, int $student_id) {
    // Recompute total rendered hours from non-rejected attendance records.
    $sum_stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_hours), 0) AS rendered
        FROM attendances
        WHERE student_id = ? AND (status IS NULL OR status <> 'rejected')
    ");
    $sum_stmt->bind_param("i", $student_id);
    $sum_stmt->execute();
    $sum_result = $sum_stmt->get_result()->fetch_assoc();
    $rendered = isset($sum_result['rendered']) ? (float)$sum_result['rendered'] : 0.0;
    $sum_stmt->close();

    // Update ongoing internship rendered/completion.
    $intern_stmt = $conn->prepare("
        SELECT id, required_hours
        FROM internships
        WHERE student_id = ? AND status = 'ongoing'
        ORDER BY id DESC
        LIMIT 1
    ");
    $intern_stmt->bind_param("i", $student_id);
    $intern_stmt->execute();
    $intern = $intern_stmt->get_result()->fetch_assoc();
    $intern_stmt->close();

    if ($intern) {
        $required = max(0, (int)$intern['required_hours']);
        $percentage = $required > 0 ? round(($rendered / $required) * 100, 2) : 0.0;
        if ($percentage > 100) {
            $percentage = 100.0;
        }
        $upd_intern = $conn->prepare("
            UPDATE internships
            SET rendered_hours = ?, completion_percentage = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $upd_intern->bind_param("ddi", $rendered, $percentage, $intern['id']);
        $upd_intern->execute();
        $upd_intern->close();
    }

    // Update students remaining based on current assignment track.
    $student_stmt = $conn->prepare("
        SELECT assignment_track, internal_total_hours, external_total_hours
        FROM students
        WHERE id = ?
        LIMIT 1
    ");
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    $student_stmt->close();

    if ($student) {
        $track = strtolower((string)($student['assignment_track'] ?? 'internal'));
        $internal_total = max(0, (int)($student['internal_total_hours'] ?? 0));
        $external_total = max(0, (int)($student['external_total_hours'] ?? 0));
        $rounded_rendered = (int)floor($rendered);

        if ($track === 'external') {
            $external_remaining = max(0, $external_total - $rounded_rendered);
            $upd_student = $conn->prepare("
                UPDATE students
                SET external_total_hours_remaining = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $upd_student->bind_param("ii", $external_remaining, $student_id);
            $upd_student->execute();
            $upd_student->close();
        } else {
            $internal_remaining = max(0, $internal_total - $rounded_rendered);
            $upd_student = $conn->prepare("
                UPDATE students
                SET internal_total_hours_remaining = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $upd_student->bind_param("ii", $internal_remaining, $student_id);
            $upd_student->execute();
            $upd_student->close();
        }
    }
}

function sync_student_active_status(mysqli $conn, int $student_id, string $clock_type) {
    $is_clock_in = (substr($clock_type, -3) === '_in');
    $new_status = $is_clock_in ? 1 : 0;
    $upd = $conn->prepare("UPDATE students SET status = ?, updated_at = NOW() WHERE id = ?");
    if (!$upd) return;
    $upd->bind_param("ii", $new_status, $student_id);
    $upd->execute();
    $upd->close();
}

function validate_demo_biometric_transition(array $record, string $clock_type, string $clock_time): array {
    // Enforce same-session out rules.
    if ($clock_type === 'morning_out' && empty($record['morning_time_in'])) {
        return ['ok' => false, 'message' => 'Cannot record morning out without morning in.'];
    }
    if ($clock_type === 'afternoon_out' && empty($record['afternoon_time_in'])) {
        return ['ok' => false, 'message' => 'Cannot record afternoon out without afternoon in.'];
    }

    // Demo mode is permissive for testing:
    // allow any clock field to be set directly (only block duplicates/invalid time).
    $target_column = attendance_action_to_column($clock_type);
    if ($target_column === null) {
        return ['ok' => false, 'message' => 'Invalid clock type.'];
    }
    if (!empty($record[$target_column])) {
        return ['ok' => false, 'message' => ucfirst(str_replace('_', ' ', $clock_type)) . ' already recorded.'];
    }
    if (attendance_time_to_minutes($clock_time) === null) {
        return ['ok' => false, 'message' => 'Invalid clock time format.'];
    }
    return ['ok' => true, 'message' => 'OK'];
}

// Handle clock in/out submission
$message = '';
$message_type = '';
$selected_date = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = intval($_POST['student_id']);
    $clock_date = $_POST['clock_date'];
    $clock_time = $_POST['clock_time'];
    $clock_type = $_POST['clock_type']; // morning_time_in, morning_time_out, break_time_in, break_time_out, afternoon_time_in, afternoon_time_out

    $db_column = attendance_action_to_column($clock_type);

    // Validate inputs
    if (empty($student_id) || empty($clock_date) || empty($clock_time) || empty($clock_type)) {
        $message = "All fields are required!";
        $message_type = "danger";
    } elseif (!$db_column) {
        $message = "Invalid clock type!";
        $message_type = "danger";
    } else {
        // Check if student exists
        $student_check = $conn->query("SELECT id FROM students WHERE id = $student_id");
        if ($student_check->num_rows == 0) {
            // Redirect to a friendly 404 page when student ID is not found
            header('Location: idnotfound-404.php?source=demo-biometric&id=' . urlencode($student_id));
            exit;
        } else {
            // Escape values for security
            $clock_date = $conn->real_escape_string($clock_date);
            $clock_time = $conn->real_escape_string($clock_time);

            // Check if attendance record exists for this date
            $date_check = $conn->query("SELECT id, morning_time_in, morning_time_out, break_time_in, break_time_out, afternoon_time_in, afternoon_time_out FROM attendances WHERE student_id = $student_id AND attendance_date = '$clock_date'");
            
            if ($date_check->num_rows == 0) {
                $empty_record = array(
                    'morning_time_in' => null,
                    'morning_time_out' => null,
                    'break_time_in' => null,
                    'break_time_out' => null,
                    'afternoon_time_in' => null,
                    'afternoon_time_out' => null
                );
                $validation = validate_demo_biometric_transition($empty_record, $clock_type, $clock_time);
                if (!$validation['ok']) {
                    $message = $validation['message'];
                    $message_type = "warning";
                } else {
                    $insert_query = "INSERT INTO attendances (student_id, attendance_date, $db_column, status, created_at, updated_at) 
                                    VALUES ($student_id, '$clock_date', '$clock_time', 'pending', NOW(), NOW())";
                    if ($conn->query($insert_query)) {
                        sync_student_hours($conn, $student_id);
                        sync_student_active_status($conn, $student_id, $clock_type);
                        $message = ucfirst(str_replace('_', ' ', $clock_type)) . " recorded for " . $clock_date . " at " . date('h:i A', strtotime($clock_time));
                        $message_type = "success";
                        $selected_date = $clock_date;
                    } else {
                        $message = "Error recording time: " . $conn->error;
                        $message_type = "danger";
                    }
                }
            } else {
                // Attendance record exists. Check if this specific time field is already filled
                $record = $date_check->fetch_assoc();
                $validation = validate_demo_biometric_transition($record, $clock_type, $clock_time);
                
                // Prevent morning clock-in when afternoon attendance already exists.
                if (!$validation['ok']) {
                    $message = $validation['message'];
                    $message_type = "warning";
                // If the time field is already set, prevent duplicate clock-in
                } elseif (!empty($record[$db_column])) {
                    $message = " â€” " . ucfirst(str_replace('_', ' ', $clock_type)) . " has already been recorded. Cannot clock in twice.";
                    $message_type = "warning";
                } else {
                    // Update existing attendance record with this new time
                    $update_query = "UPDATE attendances SET $db_column = '$clock_time', updated_at = NOW() 
                                    WHERE student_id = $student_id AND attendance_date = '$clock_date'";
                    if ($conn->query($update_query)) {
                        // Recompute and persist attendance total_hours for this date.
                        $att_stmt = $conn->prepare("
                            SELECT id, morning_time_in, morning_time_out, break_time_in, break_time_out, afternoon_time_in, afternoon_time_out
                            FROM attendances
                            WHERE student_id = ? AND attendance_date = ?
                            LIMIT 1
                        ");
                        $att_stmt->bind_param("is", $student_id, $clock_date);
                        $att_stmt->execute();
                        $att_row = $att_stmt->get_result()->fetch_assoc();
                        $att_stmt->close();

                        if ($att_row) {
                            $full_validation = attendance_validate_full_record($att_row);
                            if (!$full_validation['ok']) {
                                $message = $full_validation['message'];
                                $message_type = "warning";
                            } else {
                                $computed_hours = calculate_attendance_hours($att_row);
                                $upd_total = $conn->prepare("UPDATE attendances SET total_hours = ?, updated_at = NOW() WHERE id = ?");
                                $upd_total->bind_param("di", $computed_hours, $att_row['id']);
                                $upd_total->execute();
                                $upd_total->close();
                            }
                        }

                        // Sync student/internship accumulated progress so it does not reset daily.
                        sync_student_hours($conn, $student_id);
                        sync_student_active_status($conn, $student_id, $clock_type);

                        if ($message_type !== "warning") {
                            $message = ucfirst(str_replace('_', ' ', $clock_type)) . " recorded for " . $clock_date . " at " . date('h:i A', strtotime($clock_time));
                            $message_type = "success";
                        }
                        $selected_date = $clock_date;
                    } else {
                        $message = "Error recording time: " . $conn->error;
                        $message_type = "danger";
                    }
                    if (table_exists($conn, 'biometric_event_queue')) {
                        $q_stmt = $conn->prepare("
                            INSERT INTO biometric_event_queue (student_id, attendance_date, clock_type, clock_time, event_source, status, retries, created_at, updated_at)
                            VALUES (?, ?, ?, ?, 'demo-biometric', 'pending', 0, NOW(), NOW())
                        ");
                        if ($q_stmt) {
                            $q_stmt->bind_param("isss", $student_id, $clock_date, $clock_type, $clock_time);
                            $q_stmt->execute();
                            $q_stmt->close();
                        }
                    }
                    insert_audit_log(
                        $conn,
                        get_current_user_id_or_zero(),
                        'clock_' . $clock_type,
                        'attendance',
                        null,
                        [],
                        ['student_id' => $student_id, 'clock_date' => $clock_date, 'clock_time' => $clock_time, 'message' => $message],
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    );
                }
            }
        }
    }
}

// Fetch students for dropdown
$students_query = "SELECT s.id, s.student_id, s.first_name, s.last_name FROM students s ORDER BY s.first_name";
$students_result = $conn->query($students_query);
$students = [];
if ($students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Get selected-date attendance for display (defaults to today)
$today = $selected_date;
$attendance_today_query = "SELECT a.*, s.id AS student_db_id, s.student_id, s.first_name, s.last_name FROM attendances a 
                           LEFT JOIN students s ON a.student_id = s.id 
                           WHERE a.attendance_date = '$today' 
                           ORDER BY a.created_at DESC LIMIT 10";
$attendance_today = $conn->query($attendance_today_query);
$today_records = [];
if ($attendance_today->num_rows > 0) {
    while ($row = $attendance_today->fetch_assoc()) {
        $today_records[] = $row;
    }
}

$today_total_records = count($today_records);
$today_approved = 0;
$today_pending = 0;
$today_rejected = 0;
foreach ($today_records as $tr) {
    if (($tr['status'] ?? '') === 'approved') {
        $today_approved++;
    } elseif (($tr['status'] ?? '') === 'rejected') {
        $today_rejected++;
    } else {
        $today_pending++;
    }
}

$page_title = 'BioTern || Biometric Demo';
$page_styles = [
    'assets/css/modules/pages/page-demo-biometric.css',
];
$page_scripts = [
    'assets/js/modules/pages/demo-biometric-runtime.js',
    'assets/js/theme-customizer-init.min.js',
];

include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Biometric Demo</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="attendance.php">Attendance</a></li>
                        <li class="breadcrumb-item">Biometric Demo</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" aria-expanded="false" aria-controls="demoBiometricActionsMenu">
                        <i class="feather-grid me-1"></i>
                        <span>Actions</span>
                    </button>
                    <div class="page-header-actions" id="demoBiometricActionsMenu">
                        <div class="dashboard-actions-panel">
                            <div class="dashboard-actions-meta">
                                <span class="text-muted fs-12">Quick Actions</span>
                            </div>
                            <div class="dashboard-actions-grid page-header-right-items-wrapper">
                            <a href="attendance.php" class="btn btn-light-brand">
                                <i class="feather-calendar me-1"></i>
                                <span>Attendance DTR</span>
                            </a>
                            <a href="students.php" class="btn btn-outline-secondary">
                                <i class="feather-users me-1"></i>
                                <span>Students</span>
                            </a>
                            <button type="button" class="btn btn-light" data-action="print-page">
                                <i class="feather-printer me-1"></i>
                                <span>Print</span>
                            </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="main-content">
                <div class="biometric-container">
                <div class="bio-hero">
                    <span class="bio-hero-chip"><i class="feather-activity"></i> Live Scanner Simulator</span>
                    <h2><i class="feather-clock me-2"></i>Biometric Time In/Out Demo</h2>
                    <p>Simulate scan-based clock events and verify same-day attendance updates in real time.</p>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert-custom alert-<?php echo $message_type; ?>">
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <div class="bio-layout">
                    <div class="scanner-card">
                        <div class="fingerprint-image">
                            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 250'%3E%3Ccircle cx='100' cy='120' r='80' fill='none' stroke='%2395b6d4' stroke-width='2'/%3E%3Ccircle cx='100' cy='120' r='70' fill='none' stroke='%23aac6df' stroke-width='1.2'/%3E%3Ccircle cx='100' cy='120' r='60' fill='none' stroke='%23bed4e7' stroke-width='1'/%3E%3Cpath d='M 100 50 Q 120 70 140 100 T 150 150' fill='none' stroke='%235b7da2' stroke-width='1.6'/%3E%3Cpath d='M 100 50 Q 80 70 60 100 T 50 150' fill='none' stroke='%235b7da2' stroke-width='1.6'/%3E%3Cpath d='M 100 50 Q 100 75 100 100 L 100 150' fill='none' stroke='%236e8fb1' stroke-width='2'/%3E%3C/svg%3E" alt="Fingerprint">
                            <p class="scan-label">SIMULATE FINGERPRINT SCAN</p>
                        </div>
                        <div class="scanner-stat">
                            <i class="feather-shield me-1"></i> Smart Scan Integrity Mode
                        </div>
                    </div>

                    <!-- Clock Form Section -->
                    <div class="clock-section">
                        <h3><i class="feather-log-in me-2"></i>Record Time Entry</h3>

                        <!-- Current Time Display -->
                        <div class="time-display" id="currentTime">
                            <?php echo date('H:i:s'); ?>
                        </div>

                        <form method="POST" action="" id="biometricClockForm">
                            
                            <!-- Student Selection -->
                            <div class="form-group-custom">
                                <label for="student_id">
                                    <i class="feather-user"></i> Select Student
                                </label>
                                <select name="student_id" id="student_id" required>
                                    <option value="">-- Choose a Student --</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo $student['student_id'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-row">
                                <!-- Date Input -->
                                <div class="form-group-custom">
                                    <label for="clock_date">
                                        <i class="feather-calendar"></i> Date
                                    </label>
                                    <input type="date" name="clock_date" id="clock_date" value="<?php echo htmlspecialchars($selected_date); ?>" required>
                                </div>

                                <!-- Time Input -->
                                <div class="form-group-custom">
                                    <label for="clock_time">
                                        <i class="feather-clock"></i> Time
                                    </label>
                                    <input type="time" name="clock_time" id="clock_time" value="<?php echo date('H:i'); ?>" required>
                                </div>
                            </div>

                            <!-- Clock Type Selection -->
                            <div class="form-group-custom">
                                <label>
                                    <i class="feather-target"></i> Clock Type
                                </label>
                                <div class="clock-type-grid">
                                    <button type="button" class="clock-btn" data-type="morning_in">
                                        <i class="feather-sunrise"></i><br>Morning In
                                    </button>
                                    <button type="button" class="clock-btn" data-type="morning_out">
                                        <i class="feather-arrow-up-right"></i><br>Morning Out
                                    </button>
                                    <button type="button" class="clock-btn" data-type="break_in">
                                        <i class="feather-pause"></i><br>Break In
                                    </button>
                                    <button type="button" class="clock-btn" data-type="break_out">
                                        <i class="feather-play"></i><br>Break Out
                                    </button>
                                    <button type="button" class="clock-btn" data-type="afternoon_in">
                                        <i class="feather-sun"></i><br>Afternoon In
                                    </button>
                                    <button type="button" class="clock-btn" data-type="afternoon_out">
                                        <i class="feather-sunset"></i><br>Afternoon Out
                                    </button>
                                </div>
                                <input type="hidden" name="clock_type" id="clock_type" required>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn-clock">
                                <i class="feather-check-circle"></i> Record Time Entry
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Today's Records Section -->
                <div class="record-section">
                    <h3>
                        <i class="feather-list"></i> Today's Records (<?php echo date('M d, Y'); ?>)
                    </h3>

                    <?php if (count($today_records) > 0): ?>
                        <div style="overflow-x: auto;">
                            <table class="record-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Student ID</th>
                                        <th>Morning</th>
                                        <th>Break</th>
                                        <th>Afternoon</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_records as $record): ?>
                                        <?php $row_student_id = intval($record['student_db_id'] ?? 0); ?>
                                        <tr <?php if ($row_student_id > 0): ?>onclick="window.location.href='students-dtr.php?id=<?php echo $row_student_id; ?>'" style="cursor: pointer;"<?php endif; ?>>
                                            <td>
                                                <strong><?php echo ($record['first_name'] ?? 'N/A') . ' ' . ($record['last_name'] ?? 'N/A'); ?></strong>
                                            </td>
                                            <td><?php echo $record['student_id'] ?? 'N/A'; ?></td>
                                            <td>
                                                <?php
                                                    $morning = '';
                                                    if ($record['morning_time_in'] && $record['morning_time_out']) {
                                                        $morning = date('h:i A', strtotime($record['morning_time_in'])) . ' - ' . date('h:i A', strtotime($record['morning_time_out']));
                                                    } elseif ($record['morning_time_in']) {
                                                        $morning = date('h:i A', strtotime($record['morning_time_in'])) . ' âœ“';
                                                    }
                                                    echo $morning ? '<span class="badge-time badge-morning">' . $morning . '</span>' : '-';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $break = '';
                                                    if ($record['break_time_in'] && $record['break_time_out']) {
                                                        $break = date('h:i A', strtotime($record['break_time_in'])) . ' - ' . date('h:i A', strtotime($record['break_time_out']));
                                                    } elseif ($record['break_time_in']) {
                                                        $break = date('h:i A', strtotime($record['break_time_in'])) . ' âœ“';
                                                    }
                                                    echo $break ? '<span class="badge-time badge-break">' . $break . '</span>' : '-';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $afternoon = '';
                                                    if ($record['afternoon_time_in'] && $record['afternoon_time_out']) {
                                                        $afternoon = date('h:i A', strtotime($record['afternoon_time_in'])) . ' - ' . date('h:i A', strtotime($record['afternoon_time_out']));
                                                    } elseif ($record['afternoon_time_in']) {
                                                        $afternoon = date('h:i A', strtotime($record['afternoon_time_in'])) . ' âœ“';
                                                    }
                                                    echo $afternoon ? '<span class="badge-time badge-afternoon">' . $afternoon . '</span>' : '-';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                    $status_badge = '';
                                                    if ($record['status'] === 'approved') {
                                                        $status_badge = '<span class="badge bg-success">Approved</span>';
                                                    } elseif ($record['status'] === 'rejected') {
                                                        $status_badge = '<span class="badge bg-danger">Rejected</span>';
                                                    } else {
                                                        $status_badge = '<span class="badge bg-warning">Pending</span>';
                                                    }
                                                    echo $status_badge;
                                                ?>
                                            </td>
                                            <td style="font-size: 12px; color: #999;">
                                                <?php echo date('h:i A', strtotime($record['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-records">
                            <p><i class="feather-inbox"></i></p>
                            <p>No attendance records for <?php echo htmlspecialchars($selected_date); ?> yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- View Full Attendance -->
                <div class="bio-link-wrap">
                    <a href="attendance.php?date=<?php echo urlencode($selected_date); ?>" class="btn btn-primary">
                        <i class="feather-arrow-right"></i> View Full Attendance Report
                    </a>
                </div>
                </div>
            </div>

</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>

<?php
$conn->close();
?>






