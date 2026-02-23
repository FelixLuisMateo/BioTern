<?php
// Database Connection
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch Attendance Statistics
$stats_query = "
    SELECT 
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        COUNT(*) as total_count
    FROM attendances
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
// Prepare filter inputs (defaults: today's date)
$filter_date = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : '';
$start_date = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : '';
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_department = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$filter_supervisor = isset($_GET['supervisor']) ? trim($_GET['supervisor']) : '';
$filter_coordinator = isset($_GET['coordinator']) ? trim($_GET['coordinator']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';

// default to today when no date filters provided
if (empty($filter_date) && empty($start_date) && empty($end_date) && empty($filter_status)) {
    $filter_date = date('Y-m-d');
}

// Fetch dropdown lists
$courses = [];
$courses_res = $conn->query("SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name ASC");
if ($courses_res && $courses_res->num_rows) {
    while ($r = $courses_res->fetch_assoc()) $courses[] = $r;
}

$departments = [];
$dept_res = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($dept_res && $dept_res->num_rows) {
    while ($r = $dept_res->fetch_assoc()) $departments[] = $r;
}

$supervisors = [];
$sup_res = $conn->query("SELECT DISTINCT supervisor_name FROM students WHERE supervisor_name IS NOT NULL AND supervisor_name <> '' ORDER BY supervisor_name ASC");
if ($sup_res && $sup_res->num_rows) {
    while ($r = $sup_res->fetch_assoc()) $supervisors[] = $r['supervisor_name'];
}

$coordinators = [];
$coor_res = $conn->query("SELECT DISTINCT coordinator_name FROM students WHERE coordinator_name IS NOT NULL AND coordinator_name <> '' ORDER BY coordinator_name ASC");
if ($coor_res && $coor_res->num_rows) {
    while ($r = $coor_res->fetch_assoc()) $coordinators[] = $r['coordinator_name'];
}

// Build attendance query filtered by provided inputs. Default shows today's records.
// Build WHERE clauses depending on provided filters
$where = [];
if (!empty($start_date) && !empty($end_date)) {
    $where[] = "a.attendance_date BETWEEN '" . $conn->real_escape_string($start_date) . "' AND '" . $conn->real_escape_string($end_date) . "'";
} elseif (!empty($filter_date)) {
    $where[] = "a.attendance_date = '" . $conn->real_escape_string($filter_date) . "'";
}
if (!empty($filter_status)) {
    $allowed = ['approved','pending','rejected'];
    if (in_array($filter_status, $allowed)) {
        $where[] = "a.status = '" . $conn->real_escape_string($filter_status) . "'";
    }
}
if ($filter_course > 0) {
    $where[] = "s.course_id = " . intval($filter_course);
}
if ($filter_department > 0) {
    // join internships table to filter by department assignment
    $where[] = "i.department_id = " . intval($filter_department);
}
if (!empty($filter_supervisor)) {
    $where[] = "(s.supervisor_name LIKE '%" . $conn->real_escape_string($filter_supervisor) . "%' OR i.supervisor_id IN (SELECT id FROM users WHERE name LIKE '%" . $conn->real_escape_string($filter_supervisor) . "%'))";
}
if (!empty($filter_coordinator)) {
    $where[] = "(s.coordinator_name LIKE '%" . $conn->real_escape_string($filter_coordinator) . "%' OR i.coordinator_id IN (SELECT id FROM users WHERE name LIKE '%" . $conn->real_escape_string($filter_coordinator) . "%'))";
}

$attendance_query = "
    SELECT 
        a.id,
        a.attendance_date,
        a.morning_time_in,
        a.morning_time_out,
        a.break_time_in,
        a.break_time_out,
        a.afternoon_time_in,
        a.afternoon_time_out,
        a.status,
        a.approved_by,
        a.approved_at,
        a.remarks,
        s.id as student_id,
        s.profile_picture,
        s.student_id as student_number,
        s.first_name,
        s.last_name,
        s.email,
        s.supervisor_name,
        s.coordinator_name,
        c.name as course_name,
        d.name as department_name,
        u.name as approver_name
    FROM attendances a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN users u ON a.approved_by = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.attendance_date DESC, s.last_name ASC
    LIMIT 100
";

$attendance_result = $conn->query($attendance_query);
$attendances = [];
if ($attendance_result && $attendance_result->num_rows > 0) {
    while ($row = $attendance_result->fetch_assoc()) {
        $attendances[] = $row;
    }
}

// If requested via AJAX, return only the table rows HTML so frontend can replace tbody
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (!empty($attendances)) {
        foreach ($attendances as $attendance) {
            echo '<tr class="single-item">';
            echo '<td><div class="item-checkbox ms-1"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input checkbox" id="checkBox_' . $attendance['id'] . '"><label class="custom-control-label" for="checkBox_' . $attendance['id'] . '"></label></div></div></td>';
            // build avatar (use uploaded profile picture when available)
            $avatar_html = '<a href="students-view.php?id=' . $attendance['student_id'] . '" class="hstack gap-3">';
            if (!empty($attendance['profile_picture']) && file_exists(__DIR__ . '/' . $attendance['profile_picture'])) {
                $v = filemtime(__DIR__ . '/' . $attendance['profile_picture']);
                $avatar_html .= '<div class="avatar-image avatar-md"><img src="' . htmlspecialchars($attendance['profile_picture']) . '?v=' . $v . '" alt="" class="img-fluid"></div>';
            } else {
                $initials = strtoupper(substr($attendance['first_name'] ?? 'N', 0, 1) . substr($attendance['last_name'] ?? 'A', 0, 1));
                $avatar_html .= '<div class="avatar-image avatar-md"><div class="avatar-text avatar-md bg-light-primary rounded">' . $initials . '</div></div>';
            }
            $avatar_html .= '<div><div class="fw-bold">' . htmlspecialchars(($attendance['first_name'] ?? '') . ' ' . ($attendance['last_name'] ?? '')) . '</div><small class="text-muted">' . htmlspecialchars($attendance['student_number'] ?? '') . '</small></div></a>';
            echo '<td>' . $avatar_html . '</td>';
            echo '<td><span class="badge bg-soft-primary text-primary">' . date('Y-m-d', strtotime($attendance['attendance_date'])) . '</span></td>';
            echo '<td><span class="badge bg-soft-success text-success">' . ( $attendance['morning_time_in'] ? date('h:i A', strtotime($attendance['morning_time_in'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-success text-success">' . ( $attendance['morning_time_out'] ? date('h:i A', strtotime($attendance['morning_time_out'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-info text-info">' . ( $attendance['break_time_in'] ? date('h:i A', strtotime($attendance['break_time_in'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-info text-info">' . ( $attendance['break_time_out'] ? date('h:i A', strtotime($attendance['break_time_out'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-warning text-warning">' . ( $attendance['afternoon_time_in'] ? date('h:i A', strtotime($attendance['afternoon_time_in'])) : '-' ) . '</span></td>';
            echo '<td><span class="badge bg-soft-warning text-warning">' . ( $attendance['afternoon_time_out'] ? date('h:i A', strtotime($attendance['afternoon_time_out'])) : '-' ) . '</span></td>';
            $total_hours = calculateTotalHours($attendance['morning_time_in'], $attendance['morning_time_out'], $attendance['afternoon_time_in'], $attendance['afternoon_time_out']);
            echo '<td><span class="badge bg-soft-secondary text-secondary">' . $total_hours . 'h</span></td>';
            // attendance status
            $att_status = getAttendanceStatus($attendance['morning_time_in']);
            if ($att_status === 'present') {
                $status_html = '<span class="badge bg-soft-success text-success">Present</span>';
            } elseif ($att_status === 'late') {
                $status_html = '<span class="badge bg-soft-warning text-warning">Late</span>';
            } else {
                $status_html = '<span class="badge bg-soft-danger text-danger">Absent</span>';
            }
            echo '<td>' . $status_html . '</td>';
            echo '<td>' . getStatusBadge($attendance['status']) . '</td>';
            // actions (keep minimal for AJAX)
            echo '<td><div class="hstack gap-2 justify-content-end"><a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View Details" onclick="viewDetails(' . $attendance['id'] . ')"><i class="feather feather-eye"></i></a><div class="dropdown"><a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21"><i class="feather feather-more-horizontal"></i></a><ul class="dropdown-menu"><li><a class="dropdown-item" href="javascript:void(0)" onclick="approveAttendance(' . $attendance['id'] . ')"><i class="feather feather-check-circle me-3"></i><span>Approve</span></a></li><li><a class="dropdown-item" href="javascript:void(0)" onclick="rejectAttendance(' . $attendance['id'] . ')"><i class="feather feather-x-circle me-3"></i><span>Reject</span></a></li><li><a class="dropdown-item" href="javascript:void(0)" onclick="editAttendance(' . $attendance['id'] . ')"><i class="feather feather-edit-3 me-3"></i><span>Edit</span></a></li><li><a class="dropdown-item printBTN" href="javascript:void(0)" onclick="printAttendance(' . $attendance['id'] . ')"><i class="feather feather-printer me-3"></i><span>Print</span></a></li><li><a class="dropdown-item" href="javascript:void(0)" onclick="sendNotification(' . $attendance['id'] . ')"><i class="feather feather-mail me-3"></i><span>Send Notification</span></a></li><li class="dropdown-divider"></li><li><a class="dropdown-item" href="javascript:void(0)" onclick="deleteAttendance(' . $attendance['id'] . ')"><i class="feather feather-trash-2 me-3"></i><span>Delete</span></a></li></ul></div></div></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="13" class="text-center py-5"><p class="text-muted">No attendance records found</p></td></tr>';
    }
    exit;
}

// Helper function to format time
function formatTime($time) {
    if ($time) {
        return date('h:i A', strtotime($time));
    }
    return '-';
}

// Helper function to get status badge
function getStatusBadge($status) {
    switch($status) {
        case 'approved':
            return '<span class="badge bg-soft-success text-success">Approved</span>';
        case 'rejected':
            return '<span class="badge bg-soft-danger text-danger">Rejected</span>';
        case 'pending':
            return '<span class="badge bg-soft-warning text-warning">Pending</span>';
        default:
            return '<span class="badge bg-soft-secondary text-secondary">Unknown</span>';
    }
}

// Helper function to calculate total hours
function calculateTotalHours($morning_in, $morning_out, $afternoon_in, $afternoon_out) {
    $total = 0;
    
    if ($morning_in && $morning_out) {
        $morning_time = strtotime($morning_out) - strtotime($morning_in);
        $total += $morning_time / 3600;
    }
    
    if ($afternoon_in && $afternoon_out) {
        $afternoon_time = strtotime($afternoon_out) - strtotime($afternoon_in);
        $total += $afternoon_time / 3600;
    }
    
    return round($total, 2);
}

// Determine attendance status based on morning_time_in
function getAttendanceStatus($morning_time_in) {
    if (!$morning_time_in) {
        return 'absent';
    }
    
    $time = strtotime($morning_time_in);
    $expected_time = strtotime('08:00 AM');
    
    if ($time <= $expected_time) {
        return 'present';
    } else {
        return 'late';
    }
}
?>

<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>BioTern || Student Attendance</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <!--! END: Favicon-->
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
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/dataTables.bs5.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <!--! END: Custom CSS-->
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
    <style>
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
    <!--! ================================================================ !-->
    <!--! [Start] Navigation Manu !-->
    <!--! ================================================================ !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="index.php" class="b-brand">
                    <!-- ========   change your logo hear   ============ -->
                    <img src="assets/images/logo-full.png" alt="" class="logo logo-lg">
                    <img src="assets/images/logo-abbr.png" alt="" class="logo logo-sm">
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
                            <span class="nxl-mtext">Home</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
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
                            <li class="nxl-item"><a class="nxl-link" href="students.php">Students</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-view.php">Students View</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-create.php">Students Create</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="attendance.php">Attendance DTR</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="demo-biometric.php">Demo Biometric</a></li>
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
                            <li class="nxl-item"><a class="nxl-link" href="settings-finance.php">Finance</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-gateways.php">Gateways</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-students.php">Students</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-localization.php">Localization</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-recaptcha.php">reCAPTCHA</a></li>
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
                                    <a href="javascript:void(0);" class="font-body text-truncate-2-line"> <span class="fw-semibold text-dark">Malanie Hanvey</span> We should talk about that at lunch!<[...]
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
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- [ page-header ] start -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Student Attendance DTR</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item">Attendance DTR</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex d-md-none">
                            <a href="javascript:void(0)" class="page-header-right-close-toggle">
                                <i class="feather-arrow-left me-2"></i>
                                <span>Back</span>
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            <a href="javascript:void(0);" class="btn btn-icon btn-light-brand" data-bs-toggle="collapse" data-bs-target="#collapseAttendanceStats">
                                <i class="feather-bar-chart"></i>
                            </a>
                            <div class="dropdown">
                                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-filter"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="period" data-value="today">
                                        <i class="feather-calendar me-3"></i>
                                        <span>Today</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="period" data-value="week">
                                        <i class="feather-calendar me-3"></i>
                                        <span>This Week</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="period" data-value="month">
                                        <i class="feather-calendar me-3"></i>
                                        <span>This Month</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="status" data-value="approved">
                                        <i class="feather-check-circle me-3"></i>
                                        <span>Approved</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="status" data-value="pending">
                                        <i class="feather-clock me-3"></i>
                                        <span>Pending</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item attendance-filter" data-type="status" data-value="rejected">
                                        <i class="feather-x-circle me-3"></i>
                                        <span>Rejected</span>
                                    </a>
                                </div>
                            </div>
                            <div class="dropdown">
                                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-paperclip"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="bi bi-filetype-pdf me-3"></i>
                                        <span>PDF</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="bi bi-filetype-csv me-3"></i>
                                        <span>CSV</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="bi bi-filetype-xml me-3"></i>
                                        <span>XML</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="bi bi-printer me-3"></i>
                                        <span>Print</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-md-none d-flex align-items-center">
                        <a href="javascript:void(0)" class="page-header-right-open-toggle">
                            <i class="feather-align-right fs-20"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!--! attendance statistics database !-->
            <div id="collapseAttendanceStats" class="accordion-collapse collapse page-header-collapse">
                <div class="accordion-body pb-2">
                    <div class="row">
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-check-circle"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Total Approved</span>
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['approved_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-success text-success">
                                            <i class="feather-arrow-up fs-10 me-1"></i>
                                            <span><?php echo $stats['total_count'] > 0 ? round(($stats['approved_count'] / $stats['total_count']) * 100, 1) : 0; ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-clock"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Pending Approval</span>
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['pending_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-warning text-warning">
                                            <i class="feather-arrow-up fs-10 me-1"></i>
                                            <span><?php echo $stats['total_count'] > 0 ? round(($stats['pending_count'] / $stats['total_count']) * 100, 1) : 0; ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-x-circle"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Rejected</span>
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['rejected_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-danger text-danger">
                                            <i class="feather-arrow-down fs-10 me-1"></i>
                                            <span><?php echo $stats['total_count'] > 0 ? round(($stats['rejected_count'] / $stats['total_count']) * 100, 1) : 0; ?>%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-alert-circle"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Total Records</span>
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['total_count'] ?? 0; ?></span>
                                            </a>
                                        </div>
                                        <div class="badge bg-soft-info text-info">
                                            <i class="feather-info fs-10 me-1"></i>
                                            <span>100%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--! end of attendance statistics database !-->

            <!-- [ page-header ] end -->
            <!-- [ Main Content ] start -->
            <!-- Filters -->
            <div class="row mb-3 px-3">
                <div class="col-12">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-sm-2">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date); ?>">
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label">Course</label>
                            <select name="course_id" class="form-control">
                                <option value="0">-- All Courses --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo $filter_course == $course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-control">
                                <option value="0">-- All Departments --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $filter_department == $dept['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label">Supervisor</label>
                            <select name="supervisor" class="form-control">
                                <option value="">-- Any Supervisor --</option>
                                <?php foreach ($supervisors as $sup): ?>
                                    <option value="<?php echo htmlspecialchars($sup); ?>" <?php echo $filter_supervisor == $sup ? 'selected' : ''; ?>><?php echo htmlspecialchars($sup); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label">Coordinator</label>
                            <select name="coordinator" class="form-control">
                                <option value="">-- Any Coordinator --</option>
                                <?php foreach ($coordinators as $coor): ?>
                                    <option value="<?php echo htmlspecialchars($coor); ?>" <?php echo $filter_coordinator == $coor ? 'selected' : ''; ?>><?php echo htmlspecialchars($coor); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2 d-flex gap-1" style="align-items: flex-end;">
                            <button type="submit" class="btn btn-primary btn-sm px-3 py-1" style="font-size: 0.85rem;">Filter</button>
                            <a href="students.php" class="btn btn-outline-secondary btn-sm px-3 py-1" style="font-size: 0.85rem;">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            <div class="main-content">
                <div class="row">
                    <div class="col-lg-12">
                        <!-- Bulk Actions Toolbar -->
                        <div class="card stretch stretch-full mb-3" id="bulkActionsToolbar" style="display: none;">
                            <div class="card-body p-2">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="text-muted"><strong id="selectedCount">0</strong> record(s) selected</span>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-success" onclick="performBulkAction('approve')" title="Approve selected records">
                                            <i class="feather feather-check-circle me-1"></i> Approve All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning" onclick="performBulkAction('reject')" title="Reject selected records">
                                            <i class="feather feather-x-circle me-1"></i> Reject All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="performBulkAction('delete')" title="Delete selected records">
                                            <i class="feather feather-trash-2 me-1"></i> Delete All
                                        </button>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-secondary ms-auto" onclick="clearSelection()">
                                        <i class="feather feather-x me-1"></i> Clear
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="attendanceList">
                                        <thead>
                                            <tr>
                                                <th class="wd-30">
                                                    <div class="btn-group mb-1">
                                                        <div class="custom-control custom-checkbox ms-1">
                                                            <input type="checkbox" class="custom-control-input" id="checkAllAttendance">
                                                            <label class="custom-control-label" for="checkAllAttendance"></label>
                                                        </div>
                                                    </div>
                                                </th>
                                                <th>Student Name</th>
                                                <th>Attendance Date</th>
                                                <th>Morning In</th>
                                                <th>Morning Out</th>
                                                <th>Break In</th>
                                                <th>Break Out</th>
                                                <th>Afternoon In</th>
                                                <th>Afternoon Out</th>
                                                <th>Total Hours</th>
                                                <th>Status</th>
                                                <th>Approval Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($attendances)): ?>
                                                <?php foreach ($attendances as $index => $attendance): ?>
                                                    <tr class="single-item">
                                                        <td>
                                                            <div class="item-checkbox ms-1">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input checkbox" id="checkBox_<?php echo $attendance['id']; ?>">
                                                                    <label class="custom-control-label" for="checkBox_<?php echo $attendance['id']; ?>"></label>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <a href="students-view.php?id=<?php echo $attendance['student_id']; ?>" class="hstack gap-3">
                                                                <?php
                                                                $pp = $attendance['profile_picture'] ?? '';
                                                                if ($pp && file_exists(__DIR__ . '/' . $pp)) {
                                                                    $v = filemtime(__DIR__ . '/' . $pp);
                                                                    echo '<div class="avatar-image avatar-md"><img src="' . htmlspecialchars($pp) . '?v=' . $v . '" alt="" class="img-fluid"></div>';
                                                                } else {
                                                                    echo '<div class="avatar-image avatar-md"><div class="avatar-text avatar-md bg-light-primary rounded">' . strtoupper(substr($attendance['first_name'] ?? 'N', 0, 1) . substr($attendance['last_name'] ?? 'A', 0, 1)) . '</div></div>';
                                                                }
                                                                ?>
                                                                <div>
                                                                    <span class="text-truncate-1-line fw-bold"><?php echo ($attendance['first_name'] ?? 'N/A') . ' ' . ($attendance['last_name'] ?? 'N/A'); ?></span>
                                                                    <span class="fs-12 text-muted d-block"><?php echo $attendance['student_number'] ?? 'N/A'; ?></span>
                                                                </div>
                                                            </a>
                                                        </td>
                                                        <td><span class="badge bg-soft-primary text-primary"><?php echo date('Y-m-d', strtotime($attendance['attendance_date'])); ?></span></td>
                                                        <td><span class="badge bg-soft-success text-success"><?php echo formatTime($attendance['morning_time_in']); ?></span></td>
                                                        <td><span class="badge bg-soft-success text-success"><?php echo formatTime($attendance['morning_time_out']); ?></span></td>
                                                        <td><span class="badge bg-soft-info text-info"><?php echo formatTime($attendance['break_time_in']); ?></span></td>
                                                        <td><span class="badge bg-soft-info text-info"><?php echo formatTime($attendance['break_time_out']); ?></span></td>
                                                        <td><span class="badge bg-soft-warning text-warning"><?php echo formatTime($attendance['afternoon_time_in']); ?></span></td>
                                                        <td><span class="badge bg-soft-warning text-warning"><?php echo formatTime($attendance['afternoon_time_out']); ?></span></td>
                                                        <td>
                                                            <span class="badge bg-soft-secondary text-secondary">
                                                                <?php echo calculateTotalHours($attendance['morning_time_in'], $attendance['morning_time_out'], $attendance['afternoon_time_in'], $attendance['afternoon_time_out']); ?>h
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                $att_status = getAttendanceStatus($attendance['morning_time_in']);
                                                                if ($att_status === 'present') {
                                                                    echo '<span class="badge bg-soft-success text-success">Present</span>';
                                                                } elseif ($att_status === 'late') {
                                                                    echo '<span class="badge bg-soft-warning text-warning">Late</span>';
                                                                } else {
                                                                    echo '<span class="badge bg-soft-danger text-danger">Absent</span>';
                                                                }
                                                            ?>
                                                        </td>
                                                        <td><?php echo getStatusBadge($attendance['status']); ?></td>
                                                        <td>
                                                            <div class="hstack gap-2 justify-content-end">
                                                                <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View Details" onclick="viewDetails(<?php echo $attendance['id']; ?>)">
                                                                    <i class="feather feather-eye"></i>
                                                                </a>
                                                                <div class="dropdown">
                                                                    <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                                                        <i class="feather feather-more-horizontal"></i>
                                                                    </a>
                                                                    <ul class="dropdown-menu">
                                                                        <li>
                                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="approveAttendance(<?php echo $attendance['id']; ?>)">
                                                                                <i class="feather feather-check-circle me-3"></i>
                                                                                <span>Approve</span>
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="rejectAttendance(<?php echo $attendance['id']; ?>)">
                                                                                <i class="feather feather-x-circle me-3"></i>
                                                                                <span>Reject</span>
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="editAttendance(<?php echo $attendance['id']; ?>)">
                                                                                <i class="feather feather-edit-3 me-3"></i>
                                                                                <span>Edit</span>
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item printBTN" href="javascript:void(0)" onclick="printAttendance(<?php echo $attendance['id']; ?>)">
                                                                                <i class="feather feather-printer me-3"></i>
                                                                                <span>Print</span>
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="sendNotification(<?php echo $attendance['id']; ?>)">
                                                                                <i class="feather feather-mail me-3"></i>
                                                                                <span>Send Notification</span>
                                                                            </a>
                                                                        </li>
                                                                        <li class="dropdown-divider"></li>
                                                                        <li>
                                                                            <a class="dropdown-item" href="javascript:void(0)" onclick="deleteAttendance(<?php echo $attendance['id']; ?>)">
                                                                                <i class="feather feather-trash-2 me-3"></i>
                                                                                <span>Delete</span>
                                                                            </a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="11" class="text-center py-5">
                                                        <p class="text-muted">No attendance records found</p>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ Main Content ] end -->
        </div>
        <!-- [ Footer ] start -->
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
        <!-- [ Footer ] end -->
    </main>

    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->

    <!--! ================================================================ !-->
    <!--! BEGIN: Downloading Toast !-->
    <!--! ================================================================ !-->
    <div class="position-fixed" style="right: 5px; bottom: 5px; z-index: 999999">
        <div id="toast" class="toast bg-black hide" data-bs-delay="3000" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header px-3 bg-transparent d-flex align-items-center justify-content-between border-bottom border-light border-opacity-10">
                <div class="text-white mb-0 mr-auto">Downloading...</div>
                <a href="javascript:void(0)" class="ms-2 mb-1 close fw-normal" data-bs-dismiss="toast" aria-label="Close">
                    <span class="text-white">&times;</span>
                </a>
            </div>
            <div class="toast-body p-3 text-white">
                <h6 class="fs-13 text-white">Attendance.zip</h6>
                <span class="text-light fs-11">4.2mb of 5.5mb</span>
            </div>
            <div class="toast-footer p-3 pt-0 border-top border-light border-opacity-10">
                <div class="progress mt-3" style="height: 5px">
                    <div class="progress-bar progress-bar-striped progress-bar-animated w-75 bg-dark" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>
    </div>
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <style>
        /* Ensure header dropdowns are visible above other elements */
        .page-header .dropdown-menu,
        .page-header-right .dropdown-menu {
            z-index: 99999 !important;
        }
        /* Allow dropdowns to overflow parent containers */
        .page-header, .page-header-right { overflow: visible !important; }
        
        /* Fix dropdown visibility in table */
        .table-responsive {
            overflow: visible !important;
        }
        
        .table td {
            position: relative;
            overflow: visible !important;
        }
        
        .dropdown {
            position: static !important;
        }
        
        .dropdown-menu {
            position: absolute !important;
            z-index: 10000 !important;
        }
        
        /* Ensure table doesn't clip dropdowns */
        .table-hover tbody tr {
            position: static !important;
            overflow: visible !important;
        }
    </style>

    <script src="assets/vendors/js/dataTables.min.js"></script>
    <script src="assets/vendors/js/dataTables.bs5.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <!--! END: Vendors JS !-->
    
    <style>
        /* Fix dropdown visibility in scrollable table */
        .table-responsive .dropdown-menu {
            position: absolute !important;
            z-index: 10000 !important;
            margin-top: 5px;
        }
    </style>
    
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/customers-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!-- Theme Customizer removed -->

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#attendanceList').DataTable({
                "pageLength": 10,
                "ordering": true,
                "searching": true,
                "bLengthChange": true,
                "info": true,
                "paging": true
            });

            // Initialize Select2
            // Initialize Select2 for filter selects
            $('select[name="course_id"], select[name="department_id"], select[name="supervisor"], select[name="coordinator"]').select2({
                width: 'resolve',
                theme: 'bootstrap-5'
            });

            // Header quick-filters (Today / This Week / This Month / status)
            // Use delegated binding so dynamically shown menu items are caught
            $(document).on('click', '.attendance-filter', function(e) {
                e.preventDefault();
                var type = $(this).data('type');
                var value = $(this).data('value');
                var params = new URLSearchParams(window.location.search);

                // Remove pagination or unrelated params
                params.delete('page');

                if (type === 'period') {
                    var today = new Date();
                    var yyyy = today.getFullYear();
                    var mm = String(today.getMonth() + 1).padStart(2, '0');
                    var dd = String(today.getDate()).padStart(2, '0');
                    if (value === 'today') {
                        params.set('date', yyyy + '-' + mm + '-' + dd);
                        params.delete('start_date');
                        params.delete('end_date');
                        params.delete('status');
                    } else if (value === 'week') {
                        // start of week (Monday)
                        var curr = new Date();
                        var first = new Date(curr.setDate(curr.getDate() - (curr.getDay() || 7) + 1));
                        var last = new Date();
                        var s_yyyy = first.getFullYear();
                        var s_mm = String(first.getMonth() + 1).padStart(2, '0');
                        var s_dd = String(first.getDate()).padStart(2, '0');
                        var e_yyyy = last.getFullYear();
                        var e_mm = String(last.getMonth() + 1).padStart(2, '0');
                        var e_dd = String(last.getDate()).padStart(2, '0');
                        params.set('start_date', s_yyyy + '-' + s_mm + '-' + s_dd);
                        params.set('end_date', e_yyyy + '-' + e_mm + '-' + e_dd);
                        params.delete('date');
                        params.delete('status');
                    } else if (value === 'month') {
                        var now = new Date();
                        var s_yyyy = now.getFullYear();
                        var s_mm = String(now.getMonth() + 1).padStart(2, '0');
                        params.set('start_date', s_yyyy + '-' + s_mm + '-01');
                        // last day of month
                        var lastDay = new Date(now.getFullYear(), now.getMonth()+1, 0);
                        var e_yyyy = lastDay.getFullYear();
                        var e_mm = String(lastDay.getMonth() + 1).padStart(2, '0');
                        var e_dd = String(lastDay.getDate()).padStart(2, '0');
                        params.set('end_date', e_yyyy + '-' + e_mm + '-' + e_dd);
                        params.delete('date');
                        params.delete('status');
                    }
                } else if (type === 'status') {
                    params.set('status', value);
                    // clear specific date range so status can apply broadly
                    params.delete('date');
                    params.delete('start_date');
                    params.delete('end_date');
                }

                // navigate via AJAX: fetch rows and replace table body without full reload
                var qs = params.toString();
                var fetchUrl = window.location.pathname + (qs ? ('?' + qs) : '') + (qs ? '&ajax=1' : '?ajax=1');
                // request rows
                $.get(fetchUrl, function(html) {
                    // destroy and reinit DataTable while replacing rows
                    if ($.fn.DataTable.isDataTable('#attendanceList')) {
                        $('#attendanceList').DataTable().clear().destroy();
                    }
                    $('#attendanceList tbody').html(html);
                    // re-init DataTable
                    $('#attendanceList').DataTable({
                        "pageLength": 10,
                        "ordering": true,
                        "searching": true
                    });
                    // re-init tooltips
                    $('[data-bs-toggle="tooltip"]').each(function() { new bootstrap.Tooltip(this); });
                    // re-init dropdowns
                    var dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
                    dropdownElements.forEach(function(element) {
                        new bootstrap.Dropdown(element);
                    });
                }).fail(function() {
                    // fallback to full reload on error
                    window.location.href = window.location.pathname + (qs ? ('?' + qs) : '');
                });
            });

            // Handle Check All
            $('#checkAllAttendance').on('change', function() {
                $('.checkbox').prop('checked', this.checked);
                updateBulkActionsToolbar();
            });

            // Handle individual checkbox changes
            $(document).on('change', '.checkbox', function() {
                updateBulkActionsToolbar();
            });

            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').each(function() {
                new bootstrap.Tooltip(this);
            });
        });

        // View Details function
        function viewDetails(id) {
            alert('View Details for Attendance ID: ' + id);
            // You can implement a modal or redirect to detail page
        }

        // Update bulk actions toolbar visibility and count
        function updateBulkActionsToolbar() {
            var selectedCount = $('.checkbox:checked').length;
            $('#selectedCount').text(selectedCount);
            
            if (selectedCount > 0) {
                $('#bulkActionsToolbar').slideDown(200);
            } else {
                $('#bulkActionsToolbar').slideUp(200);
                $('#checkAllAttendance').prop('checked', false);
            }
        }

        // Clear selection
        function clearSelection() {
            $('.checkbox').prop('checked', false);
            $('#checkAllAttendance').prop('checked', false);
            updateBulkActionsToolbar();
        }

        // Helper function to get selected IDs
        function getSelectedIds() {
            var ids = [];
            $('.checkbox:checked').each(function() {
                var id = $(this).attr('id').replace('checkBox_', '');
                ids.push(id);
            });
            return ids;
        }

        // Show toast notification
        function showToast(message, type = 'success') {
            // Remove existing toasts
            $('.toast-notification').remove();
            
            var toastHtml = '<div class="toast-notification alert alert-' + type + ' alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 99999; max-width: 400px;">' +
                message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                '</div>';
            
            $('body').append(toastHtml);
            
            setTimeout(function() {
                $('.toast-notification').fadeOut(function() {
                    $(this).remove();
                });
            }, 4000);
        }

        // Refresh table after action
        function refreshAttendanceTable() {
            var currentUrl = window.location.href;
            $.get(currentUrl, function(html) {
                if ($.fn.DataTable.isDataTable('#attendanceList')) {
                    $('#attendanceList').DataTable().destroy();
                }
                var newTbody = $(html).find('#attendanceList tbody').html();
                $('#attendanceList tbody').html(newTbody);
                $('#attendanceList').DataTable({
                    "pageLength": 10,
                    "ordering": true,
                    "searching": true
                });
                // Reinitialize tooltips
                $('[data-bs-toggle="tooltip"]').each(function() {
                    new bootstrap.Tooltip(this);
                });
                // Reinitialize dropdowns - Bootstrap 5
                var dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
                dropdownElements.forEach(function(element) {
                    new bootstrap.Dropdown(element);
                });
                $('#checkAllAttendance').prop('checked', false);
                updateBulkActionsToolbar();
            });
        }

        // Approve attendance function (single or bulk via AJAX)
        function approveAttendance(id) {
            var ids = id ? [id] : getSelectedIds();
            
            if (ids.length === 0) {
                showToast('Please select at least one attendance record to approve', 'warning');
                return;
            }

            if (confirm('Are you sure you want to approve ' + ids.length + ' attendance record(s)?')) {
                $.ajax({
                    type: 'POST',
                    url: 'process_attendance.php',
                    data: {
                        action: 'approve',
                        id: ids
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'success');
                            refreshAttendanceTable();
                        } else {
                            showToast(response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Error processing request', 'danger');
                    }
                });
            }
        }

        // Reject attendance function (single or bulk via AJAX)
        function rejectAttendance(id) {
            var ids = id ? [id] : getSelectedIds();
            
            if (ids.length === 0) {
                showToast('Please select at least one attendance record to reject', 'warning');
                return;
            }

            var remarks = prompt('Enter rejection reason:');
            if (remarks !== null && remarks.trim() !== '') {
                $.ajax({
                    type: 'POST',
                    url: 'process_attendance.php',
                    data: {
                        action: 'reject',
                        id: ids,
                        remarks: remarks
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'success');
                            refreshAttendanceTable();
                        } else {
                            showToast(response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Error processing request', 'danger');
                    }
                });
            }
        }

        // Edit attendance function (redirects to edit page)
        function editAttendance(id) {
            window.location.href = 'edit_attendance.php?id=' + id;
        }

        // Print attendance function
        function printAttendance(id) {
            window.open('print_attendance.php?id=' + id, 'Print', 'height=600,width=800');
        }

        // Send notification function
        function sendNotification(id) {
            alert('Sending notification for Attendance ID: ' + id);
            // Implement your notification logic here
        }

        // Delete attendance function (single or bulk via AJAX)
        function deleteAttendance(id) {
            var ids = id ? [id] : getSelectedIds();
            
            if (ids.length === 0) {
                showToast('Please select at least one attendance record to delete', 'warning');
                return;
            }

            if (confirm('Are you sure you want to delete ' + ids.length + ' attendance record(s)? This action cannot be undone.')) {
                $.ajax({
                    type: 'POST',
                    url: 'process_attendance.php',
                    data: {
                        action: 'delete',
                        id: ids
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'success');
                            refreshAttendanceTable();
                        } else {
                            showToast(response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Error processing request', 'danger');
                    }
                });
            }
        }

        // Bulk action handler
        function performBulkAction(action) {
            var ids = getSelectedIds();
            
            if (ids.length === 0) {
                showToast('Please select at least one attendance record', 'warning');
                return;
            }

            if (action === 'approve') {
                approveAttendance(null);
            } else if (action === 'reject') {
                rejectAttendance(null);
            } else if (action === 'delete') {
                deleteAttendance(null);
            }
        }

        // Edit status inline via AJAX
        function changeStatus(id, newStatus) {
            if (confirm('Change status to ' + newStatus + '?')) {
                $.ajax({
                    type: 'POST',
                    url: 'process_attendance.php',
                    data: {
                        action: 'edit_status',
                        id: [id],
                        status: newStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'success');
                            refreshAttendanceTable();
                        } else {
                            showToast(response.message, 'danger');
                        }
                    },
                    error: function() {
                        showToast('Error processing request', 'danger');
                    }
                });
            }
        }

