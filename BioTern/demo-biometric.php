<?php
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

// Handle clock in/out submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = intval($_POST['student_id']);
    $clock_date = $_POST['clock_date'];
    $clock_time = $_POST['clock_time'];
    $clock_type = $_POST['clock_type']; // morning_time_in, morning_time_out, break_time_in, break_time_out, afternoon_time_in, afternoon_time_out

    // Map clock types to correct column names
    $clock_type_map = array(
        'morning_in' => 'morning_time_in',
        'morning_out' => 'morning_time_out',
        'break_in' => 'break_time_in',
        'break_out' => 'break_time_out',
        'afternoon_in' => 'afternoon_time_in',
        'afternoon_out' => 'afternoon_time_out'
    );

    $db_column = $clock_type_map[$clock_type] ?? null;

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
            $date_check = $conn->query("SELECT id, $db_column FROM attendances WHERE student_id = $student_id AND attendance_date = '$clock_date'");
            
            if ($date_check->num_rows == 0) {
                // Create new attendance record
                $insert_query = "INSERT INTO attendances (student_id, attendance_date, $db_column, status, created_at, updated_at) 
                                VALUES ($student_id, '$clock_date', '$clock_time', 'pending', NOW(), NOW())";
                if ($conn->query($insert_query)) {
                    sync_student_active_status($conn, $student_id, $clock_type);
                    $message = "" . ucfirst(str_replace('_', ' ', $clock_type)) . " recorded at " . date('h:i A', strtotime($clock_time));
                    $message_type = "success";
                } else {
                    $message = "Error recording time: " . $conn->error;
                    $message_type = "danger";
                }
            } else {
                // Attendance record exists. Check if this specific time field is already filled
                $record = $date_check->fetch_assoc();
                
                // If the time field is already set, prevent duplicate clock-in
                if (!empty($record[$db_column])) {
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
                            $computed_hours = calculate_attendance_hours($att_row);
                            $upd_total = $conn->prepare("UPDATE attendances SET total_hours = ?, updated_at = NOW() WHERE id = ?");
                            $upd_total->bind_param("di", $computed_hours, $att_row['id']);
                            $upd_total->execute();
                            $upd_total->close();
                        }

                        // Sync student/internship accumulated progress so it does not reset daily.
                        sync_student_hours($conn, $student_id);
                        sync_student_active_status($conn, $student_id, $clock_type);

                        $message = " — " . ucfirst(str_replace('_', ' ', $clock_type)) . " recorded at " . date('h:i A', strtotime($clock_time));
                        $message_type = "success";
                    } else {
                        $message = "Error recording time: " . $conn->error;
                        $message_type = "danger";
                    }
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
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <style>
        .biometric-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .fingerprint-image {
            text-align: center;
            margin: 30px 0;
        }

        .fingerprint-image img {
            max-width: 300px;
            height: auto;
            filter: grayscale(100%);
        }

        .clock-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 30px;
            color: white;
            margin: 20px 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .clock-section h2 {
            margin-bottom: 25px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .form-group-custom {
            margin-bottom: 20px;
        }

        .form-group-custom label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group-custom input,
        .form-group-custom select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .form-group-custom input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-group-custom input:focus,
        .form-group-custom select:focus {
            outline: none;
            background-color: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 0 15px rgba(255, 255, 255, 0.2);
        }

        .form-group-custom select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='white' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
            cursor: pointer;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .btn-clock {
            width: 100%;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.3);
            color: white;
            border: 2px solid white;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        .btn-clock:hover {
            background-color: white;
            color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-clock:active {
            transform: translateY(0);
        }

        .alert-custom {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
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
            gap: 10px;
            margin: 20px 0;
        }

        @media (max-width: 768px) {
            .clock-type-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .clock-btn {
            padding: 12px;
            background-color: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.4);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .clock-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
            border-color: white;
        }

        .clock-btn.active {
            background-color: white;
            color: #667eea;
            border-color: white;
        }

        .record-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin: 30px 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .record-section h3 {
            color: #333;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .record-table {
            width: 100%;
            border-collapse: collapse;
        }

        .record-table th {
            background-color: #f8f9fa;
            color: #333;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        .record-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }

        .record-table tbody tr:hover {
            background-color: #f8f9fa;
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
            color: #999;
        }

        .no-records i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .time-display {
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.15);
            border-radius: 8px;
            margin: 20px 0;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main.nxl-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        div.nxl-content {
            flex: 1;
        }
        footer.footer {
            margin-top: auto;
        }
        
        /* Dark mode select and Select2 styling */
        select.form-control,
        select.form-select,
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            color: #333 !important;
            background-color: #ffffff !important;
        }
        
        /* Dark mode support for Select2 - using app-skin-dark class */
        html.app-skin-dark .select2-container--default .select2-selection--single,
        html.app-skin-dark .select2-container--default .select2-selection--multiple {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark .select2-container--default.select2-container--focus .select2-selection--single,
        html.app-skin-dark .select2-container--default.select2-container--focus .select2-selection--multiple {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
            border-color: #667eea !important;
        }
        
        html.app-skin-dark .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f0f0f0 !important;
        }
        
        html.app-skin-dark .select2-container--default .select2-selection__placeholder {
            color: #a0aec0 !important;
        }
        
        /* Dark mode dropdown menu */
        html.app-skin-dark .select2-container--default.select2-container--open .select2-dropdown {
            background-color: #2d3748 !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark .select2-results {
            background-color: #2d3748 !important;
        }
        
        html.app-skin-dark .select2-results__option {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
        }
        
        html.app-skin-dark .select2-results__option--highlighted[aria-selected] {
            background-color: #667eea !important;
            color: #ffffff !important;
        }
        
        html.app-skin-dark .select2-container--default {
            background-color: #2d3748 !important;
        }
        
        html.app-skin-dark select.form-control,
        html.app-skin-dark select.form-select {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark select.form-control option,
        html.app-skin-dark select.form-select option {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
        }
    </style>
</head>

<body>
    <?php include_once 'includes/navigation.php'; ?>
    <!--! ================================================================ !-->
    <!--! [Start] Header !-->
    <!--! ================================================================ !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <!--! [Start] Header Left !-->
            <div class="header-left d-flex align-items-center gap-4">
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
                                <input type="text" class="form-control search-input-field" placeholder="Search....">
                                <span class="input-group-text">
                                    <button type="button" class="btn-close"></button>
                                </span>
                            </div>
                            <div class="dropdown-divider mt-0"></div>
                            <!--! search coding for database !-->
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
                                <img src="assets/images/avatar/2.png" alt="" class="rounded me-3 border">
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
                            <div class="text-center notifications-footer">
                                <a href="javascript:void(0);" class="fs-13 fw-semibold text-dark">All Notifications</a>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <img src="assets/images/avatar/1.png" alt="user-image" class="img-fluid user-avtar me-0">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="assets/images/avatar/1.png" alt="user-image" class="img-fluid user-avtar">
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
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-user"></i>
                                <span>Profile Details</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-activity"></i>
                                <span>Activity Feed</span>
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
                            <a href="./auth-login-cover.php" class="dropdown-item">
                                <i class="feather-log-out"></i>
                                <span>Logout</span>
                            </a>
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
                <!-- Page Header -->
                <div class="page-header" style="margin-bottom: 30px;">
                    <h2 style="color: #333; font-weight: 700; margin-bottom: 5px;">
                        <i class="feather-clock"></i> Biometric Time In/Out Demo
                        <br>
                        <p style="color: #666; margin: 0;">Simulate clock in and out events for attendance tracking</p>
                    </h2>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert-custom alert-<?php echo $message_type; ?>">
                        <span class="alert-icon">
                            <?php echo $message_type === 'success' ? ' — ' : ' — '; ?>
                        </span>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Fingerprint Image -->
                <div class="fingerprint-image">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 250'%3E%3Ccircle cx='100' cy='120' r='80' fill='none' stroke='%23ccc' stroke-width='2'/%3E%3Ccircle cx='100' cy='120' r='70' fill='none' stroke='%23ddd' stroke-width='1'/%3E%3Ccircle cx='100' cy='120' r='60' fill='none' stroke='%23eee' stroke-width='1'/%3E%3Cpath d='M 100 50 Q 120 70 140 100 T 150 150' fill='none' stroke='%23999' stroke-width='1.5'/%3E%3Cpath d='M 100 50 Q 80 70 60 100 T 50 150' fill='none' stroke='%23999' stroke-width='1.5'/%3E%3Cpath d='M 100 50 Q 100 75 100 100 L 100 150' fill='none' stroke='%23aaa' stroke-width='2'/%3E%3C/svg%3E" alt="Fingerprint">
                    <p style="color: #999; font-size: 12px; margin-top: 10px;">SIMULATE FINGERPRINT SCAN</p>
                </div>

                <!-- Clock Form Section -->
                <div class="clock-section">
                    <h2>
                        <i class="feather-log-in"></i> Record Time Entry
                    </h2>

                    <!-- Current Time Display -->
                    <div class="time-display" id="currentTime">
                        <?php echo date('H:i:s'); ?>
                    </div>

                    <form method="POST" action="">
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
                                                        $morning = date('h:i A', strtotime($record['morning_time_in'])) . ' - ';
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
                                                        $break = date('h:i A', strtotime($record['break_time_in'])) . ' - ';
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
                                                        $afternoon = date('h:i A', strtotime($record['afternoon_time_in'])) . ' - ';
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
                <div style="text-align: center; margin-top: 30px;">
                    <a href="attendance.php" class="btn btn-primary" style="padding: 12px 30px; border-radius: 8px; text-decoration: none; display: inline-block;">
                        <i class="feather-arrow-right"></i> View Full Attendance Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright ©</span>
                <script>
                    document.write(new Date().getFullYear());
                </script>
            </p>
            <p><span>By: <a target="_blank" href="" target="_blank">ACT 2A</a> </span><span>Distributed by: <a target="_blank" href="" target="_blank">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
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
        document.querySelector('form').addEventListener('submit', function(e) {
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
</body>

</html>

<?php
$conn->close();
?>

