<?php
require_once __DIR__ . '/lib/attendance_rules.php';
require_once __DIR__ . '/lib/ops_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_roles_page(['admin', 'coordinator', 'supervisor']);
// Database Connection
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
    // Allow afternoon time-in as the first entry of the day.
    if ($clock_type === 'afternoon_in') {
        $has_morning_or_break = !empty($record['morning_time_in'])
            || !empty($record['morning_time_out'])
            || !empty($record['break_time_in'])
            || !empty($record['break_time_out']);
        $has_afternoon_in = !empty($record['afternoon_time_in']);
        $has_afternoon_out = !empty($record['afternoon_time_out']);

        if (!$has_morning_or_break && !$has_afternoon_in && !$has_afternoon_out) {
            $new_minutes = attendance_time_to_minutes($clock_time);
            if ($new_minutes === null) {
                return ['ok' => false, 'message' => 'Invalid clock time format.'];
            }
            return ['ok' => true, 'message' => 'OK'];
        }
    }

    // Custom rule: if afternoon in already exists, morning in is no longer allowed.
    if (
        $clock_type === 'morning_in'
        && empty($record['morning_time_in'])
        && !empty($record['afternoon_time_in'])
    ) {
        return ['ok' => false, 'message' => 'Cannot record morning in after afternoon in is already recorded.'];
    }

    return attendance_validate_transition($record, $clock_type, $clock_time);
}

// Handle clock in/out submission
$message = '';
$message_type = '';

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
                        sync_student_active_status($conn, $student_id, $clock_type);
                        $message = ucfirst(str_replace('_', ' ', $clock_type)) . " recorded at " . date('h:i A', strtotime($clock_time));
                        $message_type = "success";
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
                    $message = " — " . ucfirst(str_replace('_', ' ', $clock_type)) . " has already been recorded. Cannot clock in twice.";
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
                            $message = ucfirst(str_replace('_', ' ', $clock_type)) . " recorded at " . date('h:i A', strtotime($clock_time));
                            $message_type = "success";
                        }
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

// Get today's attendance for display
$today = date('Y-m-d');
$attendance_today_query = "SELECT a.*, s.student_id, s.first_name, s.last_name FROM attendances a 
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
?>

<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="BioTern - Biometric Time In/Out Demo">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <title>BioTern || Biometric Demo</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <script>
        (function(){
            try{
                var s = localStorage.getItem('app-skin-dark') || localStorage.getItem('app-skin') || localStorage.getItem('app_skin') || localStorage.getItem('theme');
                if (s && (s.indexOf && s.indexOf('dark') !== -1 || s === 'app-skin-dark')) {
                    document.documentElement.classList.add('app-skin-dark');
                }
            }catch(e){}
        })();
    </script>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <script>try{var s=localStorage.getItem('app-skin')||localStorage.getItem('app_skin')||localStorage.getItem('theme'); if(s&&s.indexOf('dark')!==-1)document.documentElement.classList.add('app-skin-dark');}catch(e){};</script>
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <style>
        .biometric-container {
            max-width: 1080px;
            margin: 0 auto;
            padding: 18px;
        }

        .bio-hero {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            padding: 28px;
            background:
                radial-gradient(circle at 88% -10%, rgba(244, 183, 64, 0.28), transparent 36%),
                radial-gradient(circle at -8% 118%, rgba(10, 178, 229, 0.2), transparent 38%),
                linear-gradient(135deg, #041c3b 0%, #0d2c58 48%, #12456e 100%);
            color: #f7fbff;
            box-shadow: 0 24px 44px rgba(8, 22, 46, 0.34);
            margin-bottom: 1.25rem;
        }

        .bio-hero h2 {
            margin: 0 0 0.4rem;
            font-weight: 800;
            letter-spacing: 0.3px;
            color: #ffffff;
        }

        .bio-hero p {
            margin: 0;
            color: rgba(240, 248, 255, 0.86);
        }

        .bio-hero-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            background: rgba(255, 255, 255, 0.1);
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }

        .bio-layout {
            display: grid;
            grid-template-columns: 290px minmax(0, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .scanner-card,
        .clock-section,
        .record-section {
            border-radius: 18px;
            border: 1px solid rgba(18, 57, 95, 0.12);
            background: #ffffff;
            box-shadow: 0 12px 28px rgba(13, 30, 58, 0.1);
        }

        .scanner-card {
            padding: 1.1rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background:
                linear-gradient(0deg, rgba(14, 66, 117, 0.04), rgba(14, 66, 117, 0.04)),
                #ffffff;
        }

        .fingerprint-image {
            text-align: center;
            margin: 0;
            padding: 1.2rem 0.4rem 0.7rem;
        }

        .fingerprint-image img {
            width: 180px;
            max-width: 100%;
            height: auto;
            filter: contrast(1.05);
        }

        .scan-label {
            margin-top: 0.9rem;
            margin-bottom: 0;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.3px;
            color: #2b5681;
        }

        .scanner-stat {
            border-radius: 12px;
            border: 1px dashed rgba(18, 73, 121, 0.35);
            background: rgba(9, 92, 156, 0.05);
            padding: 0.85rem;
            text-align: center;
            color: #18426a;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .clock-section {
            padding: 1.25rem;
            color: #0b2748;
        }

        .clock-section h3 {
            margin-bottom: 1rem;
            font-weight: 800;
            color: #0b2748;
        }

        .form-group-custom {
            margin-bottom: 0.95rem;
        }

        .form-group-custom label {
            display: block;
            font-weight: 700;
            margin-bottom: 0.45rem;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #2d4f72;
        }

        .form-group-custom input,
        .form-group-custom select {
            width: 100%;
            padding: 0.68rem 0.85rem;
            border: 1px solid #c7d6e5;
            border-radius: 11px;
            background-color: #f8fbff;
            color: #0f3154;
            font-size: 14px;
            transition: 0.2s ease;
        }

        .form-group-custom select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            padding-right: 2.25rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2322486f' d='M6 9L1.5 4.5h9z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.7rem center;
            background-size: 12px 12px;
        }

        .form-group-custom select::-ms-expand {
            display: none;
        }

        .form-group-custom input:focus,
        .form-group-custom select:focus {
            outline: none;
            border-color: #2f86ce;
            box-shadow: 0 0 0 4px rgba(47, 134, 206, 0.16);
            background: #ffffff;
        }

        /* Dark mode form surface + readable controls */
        html.app-skin-dark .clock-section {
            background: #0b1f35;
            border-color: rgba(160, 195, 230, 0.18);
            color: #eaf4ff;
        }

        html.app-skin-dark .scanner-card,
        html.app-skin-dark .record-section {
            background: #0b1f35;
            border-color: rgba(160, 195, 230, 0.18);
            color: #eaf4ff;
        }

        html.app-skin-dark .scan-label,
        html.app-skin-dark .scanner-stat {
            color: #cfe6ff;
        }

        html.app-skin-dark .scanner-stat {
            background: rgba(58, 118, 173, 0.14);
            border-color: rgba(145, 190, 230, 0.45);
        }

        html.app-skin-dark .clock-section h3,
        html.app-skin-dark .form-group-custom label {
            color: #cfe6ff;
        }

        html.app-skin-dark .time-display {
            background: #163655;
            border-color: #4e82b4;
            color: #f6fbff;
        }

        html.app-skin-dark .form-group-custom input,
        html.app-skin-dark .form-group-custom select {
            background: #122d49 !important;
            border-color: #365a7f !important;
            color: #f8fbff !important;
            -webkit-text-fill-color: #f8fbff !important;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23d9edff' d='M6 9L1.5 4.5h9z'/%3E%3C/svg%3E");
        }

        html.app-skin-dark .form-group-custom input:focus,
        html.app-skin-dark .form-group-custom select:focus {
            background: #163655 !important;
            border-color: #6aa7de !important;
            box-shadow: 0 0 0 4px rgba(70, 145, 212, 0.2) !important;
        }

        html.app-skin-dark .form-group-custom select option {
            background: #122d49;
            color: #f8fbff;
        }

        html.app-skin-dark .clock-btn {
            background: #18395a;
            border-color: #3f678f;
            color: #d9edff;
        }

        html.app-skin-dark .clock-btn:hover {
            background: #214a73;
            border-color: #73a8dc;
        }

        /* Force readable text for native date/time controls */
        .form-group-custom input[type="date"],
        .form-group-custom input[type="time"] {
            color: #0f3154 !important;
            -webkit-text-fill-color: #0f3154 !important;
        }

        .form-group-custom input[type="date"]::-webkit-datetime-edit,
        .form-group-custom input[type="date"]::-webkit-datetime-edit-year-field,
        .form-group-custom input[type="date"]::-webkit-datetime-edit-month-field,
        .form-group-custom input[type="date"]::-webkit-datetime-edit-day-field,
        .form-group-custom input[type="date"]::-webkit-datetime-edit-text,
        .form-group-custom input[type="time"]::-webkit-datetime-edit,
        .form-group-custom input[type="time"]::-webkit-datetime-edit-hour-field,
        .form-group-custom input[type="time"]::-webkit-datetime-edit-minute-field,
        .form-group-custom input[type="time"]::-webkit-datetime-edit-ampm-field,
        .form-group-custom input[type="time"]::-webkit-datetime-edit-text {
            color: #0f3154 !important;
            -webkit-text-fill-color: #0f3154 !important;
        }

        html.app-skin-dark .form-group-custom input[type="date"],
        html.app-skin-dark .form-group-custom input[type="time"] {
            color: #f8fbff !important;
            -webkit-text-fill-color: #f8fbff !important;
        }

        html.app-skin-dark .form-group-custom input[type="date"]::-webkit-datetime-edit,
        html.app-skin-dark .form-group-custom input[type="date"]::-webkit-datetime-edit-year-field,
        html.app-skin-dark .form-group-custom input[type="date"]::-webkit-datetime-edit-month-field,
        html.app-skin-dark .form-group-custom input[type="date"]::-webkit-datetime-edit-day-field,
        html.app-skin-dark .form-group-custom input[type="date"]::-webkit-datetime-edit-text,
        html.app-skin-dark .form-group-custom input[type="time"]::-webkit-datetime-edit,
        html.app-skin-dark .form-group-custom input[type="time"]::-webkit-datetime-edit-hour-field,
        html.app-skin-dark .form-group-custom input[type="time"]::-webkit-datetime-edit-minute-field,
        html.app-skin-dark .form-group-custom input[type="time"]::-webkit-datetime-edit-ampm-field,
        html.app-skin-dark .form-group-custom input[type="time"]::-webkit-datetime-edit-text {
            color: #f8fbff !important;
            -webkit-text-fill-color: #f8fbff !important;
        }

        html.app-skin-dark .form-group-custom input[type="date"]::-webkit-calendar-picker-indicator,
        html.app-skin-dark .form-group-custom input[type="time"]::-webkit-calendar-picker-indicator {
            filter: invert(1) brightness(1.2);
            opacity: 0.95;
        }

        html.app-skin-dark .record-section h3 {
            color: #d9edff;
        }

        html.app-skin-dark .record-table th {
            background: #1a3a5a;
            color: #dff0ff;
            border-bottom-color: #32587e;
        }

        html.app-skin-dark .record-table td {
            border-bottom-color: #284a6d;
            color: #cfe6ff;
        }

        html.app-skin-dark .record-table tbody tr:hover {
            background: #13314e;
        }

        html.app-skin-dark .no-records {
            color: #a8c7e6;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.9rem;
        }

        .btn-clock {
            width: 100%;
            padding: 0.82rem 1rem;
            background: linear-gradient(90deg, #1e6fb0, #2384b8 58%, #2f9bc0);
            color: #ffffff;
            border: 0;
            border-radius: 11px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-top: 0.25rem;
        }

        .btn-clock:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(22, 93, 144, 0.28);
            filter: brightness(1.03);
            color: #ffffff;
        }

        .alert-custom {
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.25s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-icon {
            font-size: 20px;
        }

        .clock-type-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.65rem;
            margin: 0.75rem 0 1rem;
        }

        .clock-btn {
            padding: 10px 8px;
            background-color: #f2f7fc;
            border: 1px solid #d2e0ee;
            color: #1a4068;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 11px;
            transition: all 0.3s ease;
        }

        .clock-btn:hover {
            background-color: #e8f2fb;
            border-color: #8db7dd;
        }

        .clock-btn.active {
            background: linear-gradient(135deg, #1f6daa, #3192bf);
            color: #ffffff;
            border-color: transparent;
            box-shadow: 0 8px 16px rgba(25, 91, 140, 0.3);
        }

        /* Ensure active clock button remains visible in dark mode */
        html.app-skin-dark .clock-btn.active {
            background: linear-gradient(135deg, #11405f, #1a5b7e);
            color: #ffffff !important;
            border-color: transparent;
            box-shadow: 0 8px 18px rgba(8, 36, 58, 0.6);
        }

        .record-section {
            padding: 1.2rem;
            margin: 1rem 0;
        }

        .record-section h3 {
            color: #102f51;
            margin-bottom: 0.95rem;
            font-weight: 800;
        }

        .record-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 830px;
        }

        .record-table th {
            background-color: #edf4fb;
            color: #254a6f;
            padding: 11px;
            text-align: left;
            font-weight: 700;
            border-bottom: 1px solid #d8e4f0;
        }

        .record-table td {
            padding: 11px;
            border-bottom: 1px solid #e7eff8;
            vertical-align: middle;
        }

        .record-table tbody tr:hover {
            background-color: #f9fcff;
        }

        .badge-time {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-morning {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .badge-break {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .badge-afternoon {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }

        .no-records {
            text-align: center;
            padding: 40px 20px;
            color: #6b839c;
        }

        .no-records i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .time-display {
            font-size: 28px;
            font-weight: 800;
            text-align: center;
            letter-spacing: 2px;
            padding: 0.85rem 1rem;
            border: 1px dashed #9ec2e2;
            background: linear-gradient(180deg, #f2f8ff 0%, #f8fbff 100%);
            border-radius: 11px;
            margin: 0.35rem 0 1rem;
            color: #0e355a;
        }

        .bio-link-wrap {
            text-align: center;
            margin-top: 1rem;
        }

        .bio-link-wrap .btn {
            border-radius: 11px;
            padding: 0.75rem 1.2rem;
            font-weight: 700;
        }

        @media (max-width: 992px) {
            .bio-layout {
                grid-template-columns: 1fr;
            }

            .scanner-card {
                padding-bottom: 1rem;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .clock-type-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>
    <!--! ================================================================ !-->
    <!--! [Start] Navigation Manu !-->
    <!--! ================================================================ !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="index.php" class="b-brand">
                    <!-- ========   change your logo hear   ============ -->
                    <img src="assets/images/logo-full.png" alt="" class="logo logo-lg" />
                    <img src="assets/images/logo-abbr.png" alt="" class="logo logo-sm" />
                </a>
            </div>
            <div class="navbar-content">
                <ul class="nxl-navbar">
                    <li class="nxl-item nxl-caption">
                        <label>Navigation</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-airplay"></i></span>
                            <span class="nxl-mtext">Dashboards</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="index.php">Overview</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="analytics.php">Analytics</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-cast"></i></span>
                            <span class="nxl-mtext">Reports</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="reports-sales.php">Sales Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="reports-ojt.php">OJT Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="reports-project.php">Project Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="reports-timesheets.php">Timesheets Report</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-send"></i></span>
                            <span class="nxl-mtext">Applications</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="apps-chat.php">Chat</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-email.php">Email</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-tasks.php">Tasks</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-notes.php">Notes</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-storage.php">Storage</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-calendar.php">Calendar</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-users"></i></span>
                            <span class="nxl-mtext">Students</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="students.php">Students List</a></li>
                            <li class="nxl-divider"></li>
                            <li class="nxl-item"><a class="nxl-link" href="attendance.php"><i class="feather-calendar me-2"></i>Attendance Records</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="demo-biometric.php"><i class="feather-activity me-2"></i>Biometric Demo</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-alert-circle"></i></span>
                            <span class="nxl-mtext">Assign OJT Designation</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="ojt.php">OJT List</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="ojt-view.php">OJT View</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="ojt-create.php">OJT Create</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-layout"></i></span>
                            <span class="nxl-mtext">Widgets</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="widgets-lists.php">Lists</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-tables.php">Tables</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-charts.php">Charts</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-statistics.php">Statistics</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-miscellaneous.php">Miscellaneous</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-settings"></i></span>
                            <span class="nxl-mtext">Settings</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="settings-general.php">General</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-seo.php">SEO</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-tags.php">Tags</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-email.php">Email</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-tasks.php">Tasks</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-ojt.php">Leads</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-support.php">Support</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-students.php">Students</a></li>


                            <li class="nxl-item"><a class="nxl-link" href="settings-miscellaneous.php">Miscellaneous</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-power"></i></span>
                            <span class="nxl-mtext">Authentication</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Login</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-login-cover.php">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Register</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-register-creative.php">Creative</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Error-404</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-404-minimal.php">Minimal</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Reset Pass</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-reset-cover.php">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Verify OTP</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-verify-cover.php">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Maintenance</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-maintenance-cover.php">Cover</a></li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-life-buoy"></i></span>
                            <span class="nxl-mtext">Help Center</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="#!">Support</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="help-knowledgebase.php">KnowledgeBase</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="/docs/documentations">Documentations</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!--! ================================================================ !-->
    <!--! [End]  Navigation Manu !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! [Start] Header !-->
    <!--! ================================================================ !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <!--! [Start] Header Left !-->
            <div class="header-left d-flex align-items-center gap-2">
                <!--! [Start] nxl-head-mobile-toggler !-->
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <!--! [Start] nxl-head-mobile-toggler !-->
                <!--! [Start] nxl-navigation-toggle !-->
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
                <!--! [End] nxl-navigation-toggle !-->

            </div>
            <!--! [End] Header Left !-->
            <!--! [Start] Header Right !-->
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">
                    <div class="dropdown nxl-h-item nxl-header-search">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <i class="feather-search"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown">
                            <div class="input-group search-form">
                                <span class="input-group-text">
                                    <i class="feather-search fs-6 text-muted"></i>
                                </span>
                                <input type="text" class="form-control search-input-field" placeholder="Search...." />
                                <span class="input-group-text">
                                    <button type="button" class="btn-close"></button>
                                </span>
                            </div>
                            <div class="dropdown-divider mt-0"></div>
                            <div class="search-items-wrapper">
                                <div class="searching-for px-4 py-2">
                                    <p class="fs-11 fw-medium text-muted">I'm searching for...</p>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Projects</a>
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Leads</a>
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Contacts</a>
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Inbox</a>
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Invoices</a>
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Tasks</a>
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Students</a>
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Notes</a>
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Affiliate</a>
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Storage</a>
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">Calendar</a>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <div class="recent-result px-4 py-2">
                                    <h4 class="fs-13 fw-normal text-gray-600 mb-3">Recnet <span class="badge small bg-gray-200 rounded ms-1 text-dark">3</span></h4>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text rounded">
                                                <i class="feather-airplay"></i>
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">CRM dashboard redesign</a>
                                                <p class="fs-11 text-muted mb-0">Home / project / crm</p>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="badge border rounded text-dark">/<i class="feather-command ms-1 fs-10"></i></a>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text rounded">
                                                <i class="feather-file-plus"></i>
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">Create new document</a>
                                                <p class="fs-11 text-muted mb-0">Home / tasks / docs</p>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="badge border rounded text-dark">N /<i class="feather-command ms-1 fs-10"></i></a>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text rounded">
                                                <i class="feather-user-plus"></i>
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">Invite project colleagues</a>
                                                <p class="fs-11 text-muted mb-0">Home / project / invite</p>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="badge border rounded text-dark">P /<i class="feather-command ms-1 fs-10"></i></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="dropdown-divider my-3"></div>
                                <div class="users-result px-4 py-2">
                                    <h4 class="fs-13 fw-normal text-gray-600 mb-3">Users <span class="badge small bg-gray-200 rounded ms-1 text-dark">5</span></h4>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-image rounded">
                                                <img src="assets/images/avatar/1.png" alt="" class="img-fluid" />
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">Felix Luis Mateo</a>
                                                <p class="fs-11 text-muted mb-0">felixluismateo@example.com</p>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="avatar-text avatar-md">
                                            <i class="feather-chevron-right"></i>
                                        </a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-image rounded">
                                                <img src="assets/images/avatar/2.png" alt="" class="img-fluid" />
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">Green Cute</a>
                                                <p class="fs-11 text-muted mb-0">green.cute@outlook.com</p>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="avatar-text avatar-md">
                                            <i class="feather-chevron-right"></i>
                                        </a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-image rounded">
                                                <img src="assets/images/avatar/3.png" alt="" class="img-fluid" />
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">Malanie Hanvey</a>
                                                <p class="fs-11 text-muted mb-0">malanie.anvey@outlook.com</p>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="avatar-text avatar-md">
                                            <i class="feather-chevron-right"></i>
                                        </a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-image rounded">
                                                <img src="assets/images/avatar/4.png" alt="" class="img-fluid" />
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">Kenneth Hune</a>
                                                <p class="fs-11 text-muted mb-0">kenth.hune@outlook.com</p>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="avatar-text avatar-md">
                                            <i class="feather-chevron-right"></i>
                                        </a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-0">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-image rounded">
                                                <img src="assets/images/avatar/5.png" alt="" class="img-fluid" />
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">Archie Cantones</a>
                                                <p class="fs-11 text-muted mb-0">archie.cones@outlook.com</p>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="avatar-text avatar-md">
                                            <i class="feather-chevron-right"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="dropdown-divider my-3"></div>
                                <div class="file-result px-4 py-2">
                                    <h4 class="fs-13 fw-normal text-gray-600 mb-3">Files <span class="badge small bg-gray-200 rounded ms-1 text-dark">3</span></h4>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-image bg-gray-200 rounded">
                                                <img src="assets/images/file-icons/css.png" alt="" class="img-fluid" />
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">Project Style CSS</a>
                                                <p class="fs-11 text-muted mb-0">05.74 MB</p>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="avatar-text avatar-md">
                                            <i class="feather-download"></i>
                                        </a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-image bg-gray-200 rounded">
                                                <img src="assets/images/file-icons/zip.png" alt="" class="img-fluid" />
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">Dashboard Project Zip</a>
                                                <p class="fs-11 text-muted mb-0">46.83 MB</p>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="avatar-text avatar-md">
                                            <i class="feather-download"></i>
                                        </a>
                                    </div>
                                    <div class="d-flex align-items-center justify-content-between mb-0">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-image bg-gray-200 rounded">
                                                <img src="assets/images/file-icons/pdf.png" alt="" class="img-fluid" />
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">Project Document PDF</a>
                                                <p class="fs-11 text-muted mb-0">12.85 MB</p>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="avatar-text avatar-md">
                                            <i class="feather-download"></i>
                                        </a>
                                    </div>
                                </div>
                                <div class="dropdown-divider mt-3 mb-0"></div>
                                <a href="javascript:void(0);" class="p-3 fs-10 fw-bold text-uppercase text-center d-block">Loar More</a>
                            </div>
                        </div>
                    </div>
                    <div class="nxl-h-item d-none d-sm-flex">
                        <div class="full-screen-switcher">
                            <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
                                <i class="feather-maximize maximize"></i>
                                <i class="feather-minimize minimize"></i>
                            </a>
                        </div>
                    </div>
                    <div class="nxl-h-item dark-light-theme">
                        <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button">
                            <i class="feather-moon"></i>
                        </a>
                        <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display: none">
                            <i class="feather-sun"></i>
                        </a>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <i class="feather-clock"></i>
                            <span class="badge bg-success nxl-h-badge">2</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-timesheets-menu">
                            <div class="d-flex justify-content-between align-items-center timesheets-head">
                                <h6 class="fw-bold text-dark mb-0">Timesheets</h6>
                                <a href="javascript:void(0);" class="fs-11 text-success text-end ms-auto" data-bs-toggle="tooltip" title="Upcomming Timers">
                                    <i class="feather-clock"></i>
                                    <span>3 Upcomming</span>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-column timesheets-body">
                                <i class="feather-clock fs-1 mb-4"></i>
                                <p class="text-muted">No started timers found yes!</p>
                                <a href="javascript:void(0);" class="btn btn-sm btn-primary">Started Timer</a>
                            </div>
                            <div class="text-center timesheets-footer">
                                <a href="javascript:void(0);" class="fs-13 fw-semibold text-dark">Alls Timesheets</a>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                            <i class="feather-bell"></i>
                            <span class="badge bg-danger nxl-h-badge">3</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                            <div class="d-flex justify-content-between align-items-center notifications-head">
                                <h6 class="fw-bold text-dark mb-0">Notifications</h6>
                                <a href="javascript:void(0);" class="fs-11 text-success text-end ms-auto" data-bs-toggle="tooltip" title="Make as Read">
                                    <i class="feather-check"></i>
                                    <span>Make as Read</span>
                                </a>
                            </div>
                            <div class="notifications-item">
                                <img src="assets/images/avatar/2.png" alt="" class="rounded me-3 border" />
                                <div class="notifications-desc">
                                    <a href="javascript:void(0);" class="font-body text-truncate-2-line"> <span class="fw-semibold text-dark">Malanie Hanvey</span> We should talk about that at lunch!</a>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="notifications-date text-muted border-bottom border-bottom-dashed">2 minutes ago</div>
                                        <div class="d-flex align-items-center float-end gap-2">
                                            <a href="javascript:void(0);" class="d-block wd-8 ht-8 rounded-circle bg-gray-300" data-bs-toggle="tooltip" title="Make as Read"></a>
                                            <a href="javascript:void(0);" class="text-danger" data-bs-toggle="tooltip" title="Remove">
                                                <i class="feather-x fs-12"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="notifications-item">
                                <img src="assets/images/avatar/3.png" alt="" class="rounded me-3 border" />
                                <div class="notifications-desc">
                                    <a href="javascript:void(0);" class="font-body text-truncate-2-line"> <span class="fw-semibold text-dark">Valentine Maton</span> You can download the latest invoices now.</a>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="notifications-date text-muted border-bottom border-bottom-dashed">36 minutes ago</div>
                                        <div class="d-flex align-items-center float-end gap-2">
                                            <a href="javascript:void(0);" class="d-block wd-8 ht-8 rounded-circle bg-gray-300" data-bs-toggle="tooltip" title="Make as Read"></a>
                                            <a href="javascript:void(0);" class="text-danger" data-bs-toggle="tooltip" title="Remove">
                                                <i class="feather-x fs-12"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="notifications-item">
                                <img src="assets/images/avatar/4.png" alt="" class="rounded me-3 border" />
                                <div class="notifications-desc">
                                    <a href="javascript:void(0);" class="font-body text-truncate-2-line"> <span class="fw-semibold text-dark">Archie Cantones</span> Don't forget to pickup Jeremy after school!</a>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="notifications-date text-muted border-bottom border-bottom-dashed">53 minutes ago</div>
                                        <div class="d-flex align-items-center float-end gap-2">
                                            <a href="javascript:void(0);" class="d-block wd-8 ht-8 rounded-circle bg-gray-300" data-bs-toggle="tooltip" title="Make as Read"></a>
                                            <a href="javascript:void(0);" class="text-danger" data-bs-toggle="tooltip" title="Remove">
                                                <i class="feather-x fs-12"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center notifications-footer">
                                <a href="javascript:void(0);" class="fs-13 fw-semibold text-dark">Alls Notifications</a>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <img src="assets/images/avatar/1.png" alt="user-image" class="img-fluid user-avtar me-0" />
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="assets/images/avatar/1.png" alt="user-image" class="img-fluid user-avtar" />
                                    <div>
                                        <h6 class="text-dark mb-0">Felix Luis Mateo <span class="badge bg-soft-success text-success ms-1">PRO</span></h6>
                                        <span class="fs-12 fw-medium text-muted">felixluismateo@example.com</span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown">
                                <a href="javascript:void(0);" class="dropdown-item" data-bs-toggle="dropdown">
                                    <span class="hstack">
                                        <i class="wd-10 ht-10 border border-2 border-gray-1 bg-success rounded-circle me-2"></i>
                                        <span>Active</span>
                                    </span>
                                    <i class="feather-chevron-right ms-auto me-0"></i>
                                </a>
                                <div class="dropdown-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="wd-10 ht-10 border border-2 border-gray-1 bg-warning rounded-circle me-2"></i>
                                            <span>Always</span>
                                        </span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="wd-10 ht-10 border border-2 border-gray-1 bg-success rounded-circle me-2"></i>
                                            <span>Active</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>

                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-user"></i>
                                <span>Profile Details</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-activity"></i>
                                <span>Activity Feed</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-dollar-sign"></i>
                                <span>Billing Details</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-bell"></i>
                                <span>Notifications</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-settings"></i>
                                <span>Account Settings</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="auth-login-cover.php" class="dropdown-item">
                                <i class="feather-log-out"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
                </div>
            <!--! [End] Header Right !-->
        </div>
    </header>
    <!--! ================================================================ !-->
    <!--! [End] Header !-->
    <!--! ================================================================ !-->

    <!--! Main Content !-->
    <main class="nxl-container">
        <div class="nxl-content">
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
                                    <input type="date" name="clock_date" id="clock_date" value="<?php echo date('Y-m-d'); ?>" required>
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
                                <label for="clock_type">
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
                                        <tr>
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
                                                        $morning = date('h:i A', strtotime($record['morning_time_in'])) . ' ✓';
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
                                                        $break = date('h:i A', strtotime($record['break_time_in'])) . ' ✓';
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
                                                        $afternoon = date('h:i A', strtotime($record['afternoon_time_in'])) . ' ✓';
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
                            <p>No attendance records for today yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- View Full Attendance -->
                <div class="bio-link-wrap">
                    <a href="attendance.php" class="btn btn-primary">
                        <i class="feather-arrow-right"></i> View Full Attendance Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer" style="margin-top: 50px;">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright ©</span>
                <script>document.write(new Date().getFullYear());</script>
            </p>
            <p><span>By: <a target="_blank" href="">ACT 2A</a></span> • <span>Distributed by: <a target="_blank" href="">Group 5</a></span></p>
        </footer>
    </main>

    <!-- Scripts -->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>

    <script>
        // Update current time every second
        function updateCurrentTime() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('currentTime').textContent = hours + ':' + minutes + ':' + seconds;

            // Update time input
            document.getElementById('clock_time').value = hours + ':' + minutes;
        }

        // Update time every second
        setInterval(updateCurrentTime, 1000);
        updateCurrentTime();

        // Clock type button handlers
        document.querySelectorAll('.clock-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();

                // Remove active class from all buttons
                document.querySelectorAll('.clock-btn').forEach(b => b.classList.remove('active'));

                // Add active class to clicked button
                this.classList.add('active');

                // Set hidden input value
                document.getElementById('clock_type').value = this.getAttribute('data-type');
            });
        });

        // Form validation
        document.getElementById('biometricClockForm').addEventListener('submit', function(e) {
            const student = document.getElementById('student_id').value;
            const clockType = document.getElementById('clock_type').value;

            if (!student) {
                e.preventDefault();
                alert('Please select a student');
                return false;
            }

            if (!clockType) {
                e.preventDefault();
                alert('Please select a clock type');
                return false;
            }
        });
    </script>
    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="assets/vendors/js/dataTables.min.js"></script>
    <script src="assets/vendors/js/dataTables.bs5.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/customers-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!--! BEGIN: Theme Customizer  !-->
    <script src="assets/js/theme-customizer-init.min.js"></script>
    <!--! END: Theme Customizer !-->
</body>

</html>

<?php
$conn->close();
?>

