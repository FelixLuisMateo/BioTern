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

// Handle attendance actions (approve/reject/delete)
$action_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $action = trim($_POST['action']);
    $attendance_id = intval($_POST['id']);
    $current_user_id = \Illuminate\Support\Facades\Auth::id();

    if ($attendance_id > 0) {
        if ($action === 'approve') {
            $q = "UPDATE attendances SET status = 'approved', approved_by = " . ($current_user_id ? intval($current_user_id) : 'NULL') . ", approved_at = NOW(), updated_at = NOW() WHERE id = " . $attendance_id;
            if ($conn->query($q)) {
                $action_message = 'Attendance approved successfully.';
            }
        } elseif ($action === 'reject') {
            $remarks = isset($_POST['remarks']) ? $conn->real_escape_string(trim($_POST['remarks'])) : '';
            $q = "UPDATE attendances SET status = 'rejected', remarks = '" . $remarks . "', approved_by = " . ($current_user_id ? intval($current_user_id) : 'NULL') . ", approved_at = NOW(), updated_at = NOW() WHERE id = " . $attendance_id;
            if ($conn->query($q)) {
                $action_message = 'Attendance rejected successfully.';
            }
        } elseif ($action === 'delete') {
            $q = "DELETE FROM attendances WHERE id = " . $attendance_id;
            if ($conn->query($q)) {
                $action_message = 'Attendance deleted successfully.';
            }
        }
    }
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
    LEFT JOIN internships i ON i.id = (
        SELECT id
        FROM internships
        WHERE student_id = s.id AND status = 'ongoing'
        ORDER BY id DESC
        LIMIT 1
    )
    LEFT JOIN departments d ON i.department_id = d.id
    LEFT JOIN users u ON a.approved_by = u.id
    " . (count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "") . "
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

// Remove duplicate attendance rows (same student + date), keeping the latest record
if (count($attendances) > 1) {
    $seen = [];
    $unique = [];
    foreach ($attendances as $attendance) {
        $studentId = isset($attendance['student_id']) ? $attendance['student_id'] : null;
        $attendanceDate = isset($attendance['attendance_date']) ? $attendance['attendance_date'] : null;
        if ($studentId === null || $attendanceDate === null) {
            $unique[] = $attendance;
            continue;
        }
        $dedupeKey = $studentId . '|' . $attendanceDate;
        if (!isset($seen[$dedupeKey])) {
            $seen[$dedupeKey] = true;
            $unique[] = $attendance;
        }
    }
    $attendances = $unique;
}

// If requested via AJAX, return only the table rows HTML so frontend can replace tbody
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (!empty($attendances)) {
        foreach ($attendances as $attendance) {
            echo '<tr class="single-item">';
            echo '<td><div class="item-checkbox ms-1"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input checkbox" id="checkBox_' . $attendance['id'] . '"><label class="custom-control-label" for="checkBox_' . $attendance['id'] . '"></label></div></div></td>';
            echo '<td><a href="' . url('/students/view') . '?id=' . $attendance['student_id'] . '" class="hstack gap-3"><div class="avatar-image avatar-md"><div class="avatar-text avatar-md bg-light-primary rounded">' . strtoupper(substr($attendance['first_name'] ?? 'N', 0, 1) . substr($attendance['last_name'] ?? 'A', 0, 1)) . '</div></div><div><div class="fw-bold">' . htmlspecialchars(($attendance['first_name'] ?? '') . ' ' . ($attendance['last_name'] ?? '')) . '</div><small class="text-muted">' . htmlspecialchars($attendance['student_number'] ?? '') . '</small></div></a></td>';
            echo '<td><span class="badge bg-soft-primary text-primary">' . date('Y-m-d', strtotime($attendance['attendance_date'])) . '</span></td>';
            echo '<td><span class="badge bg-soft-success text-success">' . formatTime($attendance['morning_time_in']) . '</span></td>';
            echo '<td><span class="badge bg-soft-success text-success">' . formatTime($attendance['morning_time_out']) . '</span></td>';
            echo '<td><span class="badge bg-soft-info text-info">' . formatTime($attendance['break_time_in']) . '</span></td>';
            echo '<td><span class="badge bg-soft-info text-info">' . formatTime($attendance['break_time_out']) . '</span></td>';
            echo '<td><span class="badge bg-soft-warning text-warning">' . formatTime($attendance['afternoon_time_in']) . '</span></td>';
            echo '<td><span class="badge bg-soft-warning text-warning">' . formatTime($attendance['afternoon_time_out']) . '</span></td>';
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
    if (empty($time) || $time === '00:00:00' || $time === '0000-00-00 00:00:00') {
        return '-';
    }

    $parsed = strtotime($time);
    if ($parsed === false) {
        return '-';
    }

    return date('h:i A', $parsed);
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
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('frontend/assets/images/favicon.ico') }}">
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/css/bootstrap.min.css') }}">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/vendors/css/dataTables.bs5.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/vendors/css/select2-theme.min.css') }}">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/css/theme.min.css') }}">
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
    </style>
</head>

<body>
    <!--! ================================================================ !-->
    <!--! [Start] Navigation Manu !-->
    <!--! ================================================================ !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="{{ route('dashboard') }}" class="b-brand">
                    <!-- ========   change your logo hear   ============ -->
                    <img src="{{ asset('frontend/assets/images/logo-full.png') }}" alt="" class="logo logo-lg" />
                    <img src="{{ asset('frontend/assets/images/logo-abbr.png') }}" alt="" class="logo logo-sm" />
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
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('dashboard') }}">Overview</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/analytics') }}">Analytics</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-cast"></i></span>
                            <span class="nxl-mtext">Reports</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/reports-sales') }}">Sales Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/reports-ojt') }}">OJT Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/reports-project') }}">Project Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/reports-timesheets') }}">Timesheets Report</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-send"></i></span>
                            <span class="nxl-mtext">Applications</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/apps-chat') }}">Chat</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/apps-email') }}">Email</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/apps-tasks') }}">Tasks</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/apps-notes') }}">Notes</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/apps-storage') }}">Storage</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/apps-calendar') }}">Calendar</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-users"></i></span>
                            <span class="nxl-mtext">Students</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/students') }}">Students List</a></li>
                            <li class="nxl-divider"></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/attendance') }}"><i class="feather-calendar me-2"></i>Attendance Records</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/demo-biometric') }}"><i class="feather-activity me-2"></i>Biometric Demo</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-alert-circle"></i></span>
                            <span class="nxl-mtext">Assign OJT Designation</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/ojt') }}">OJT List</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/ojt-view') }}">OJT View</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/ojt-create') }}">OJT Create</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-layout"></i></span>
                            <span class="nxl-mtext">Widgets</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/widgets-lists') }}">Lists</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/widgets-tables') }}">Tables</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/widgets-charts') }}">Charts</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/widgets-statistics') }}">Statistics</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/widgets-miscellaneous') }}">Miscellaneous</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-settings"></i></span>
                            <span class="nxl-mtext">Settings</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/settings-general') }}">General</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/settings-seo') }}">SEO</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/settings-tags') }}">Tags</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/settings-email') }}">Email</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/settings-tasks') }}">Tasks</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/settings-ojt') }}">Leads</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/settings-support') }}">Support</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/settings-students') }}">Students</a></li>


                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/settings-miscellaneous') }}">Miscellaneous</a></li>
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
                                    <li class="nxl-item"><a class="nxl-link" href="{{ url('/auth-login-cover') }}">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Register</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="{{ url('/auth-register-creative') }}">Creative</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Error-404</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="{{ url('/auth-404-minimal') }}">Minimal</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Reset Pass</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="{{ url('/auth-reset-cover') }}">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Verify OTP</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="{{ url('/auth-verify-cover') }}">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Maintenance</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="{{ url('/auth-maintenance-cover') }}">Cover</a></li>
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
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/help-knowledgebase') }}">KnowledgeBase</a></li>
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
    <!--! [Start] Main Content !-->
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
                                                <img src="{{ asset('frontend/assets/images/avatar/1.png') }}" alt="" class="img-fluid" />
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
                                                <img src="{{ asset('frontend/assets/images/avatar/2.png') }}" alt="" class="img-fluid" />
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
                                                <img src="{{ asset('frontend/assets/images/avatar/3.png') }}" alt="" class="img-fluid" />
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
                                                <img src="{{ asset('frontend/assets/images/avatar/4.png') }}" alt="" class="img-fluid" />
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
                                                <img src="{{ asset('frontend/assets/images/avatar/5.png') }}" alt="" class="img-fluid" />
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
                                                <img src="{{ asset('frontend/assets/images/file-icons/css.png') }}" alt="" class="img-fluid" />
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
                                                <img src="{{ asset('frontend/assets/images/file-icons/zip.png') }}" alt="" class="img-fluid" />
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
                                                <img src="{{ asset('frontend/assets/images/file-icons/pdf.png') }}" alt="" class="img-fluid" />
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
                                <img src="{{ asset('frontend/assets/images/avatar/2.png') }}" alt="" class="rounded me-3 border" />
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
                                <img src="{{ asset('frontend/assets/images/avatar/3.png') }}" alt="" class="rounded me-3 border" />
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
                                <img src="{{ asset('frontend/assets/images/avatar/4.png') }}" alt="" class="rounded me-3 border" />
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
                            <img src="{{ asset('frontend/assets/images/avatar/1.png') }}" alt="user-image" class="img-fluid user-avtar me-0" />
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="{{ asset('frontend/assets/images/avatar/1.png') }}" alt="user-image" class="img-fluid user-avtar" />
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
                            <a href="{{ url('/auth-login-cover') }}" class="dropdown-item">
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
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- [ page-header ] start -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Student Attendance DTR</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ url('/students') }}">Students</a></li>
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
           <div class="row mb-1 px-3">
                <div class="col-12">
                    <form method="GET" class="row g-2 align-items-end filter-form">
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-date">Date</label>
                            <input id="filter-date" type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($_GET['date'] ?? ''); ?>">
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-course">Course</label>
                            <select id="filter-course" name="course_id" class="form-control">
                                <option value="0">-- All Courses --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" <?php echo $filter_course == $course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-department">Department</label>
                            <select id="filter-department" name="department_id" class="form-control">
                                <option value="0">-- All Departments --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $filter_department == $dept['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-supervisor">Supervisor</label>
                            <select id="filter-supervisor" name="supervisor" class="form-control">
                                <option value="">-- Any Supervisor --</option>
                                <?php foreach ($supervisors as $sup): ?>
                                    <option value="<?php echo htmlspecialchars($sup); ?>" <?php echo $filter_supervisor == $sup ? 'selected' : ''; ?>><?php echo htmlspecialchars($sup); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label" for="filter-coordinator">Coordinator</label>
                            <select id="filter-coordinator" name="coordinator" class="form-control">
                                <option value="">-- Any Coordinator --</option>
                                <?php foreach ($coordinators as $coor): ?>
                                    <option value="<?php echo htmlspecialchars($coor); ?>" <?php echo $filter_coordinator == $coor ? 'selected' : ''; ?>><?php echo htmlspecialchars($coor); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-2">
                            <label class="form-label d-block invisible">Actions</label>
                            <div class="filter-actions">
                                <button type="submit" class="btn btn-primary">Filter</button>
                                <a href="{{ url('/attendance') }}" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="main-content">
                <?php if (!empty($action_message)): ?>
                    <div class="alert alert-success mx-3 mt-2" role="alert">
                        <?php echo htmlspecialchars($action_message); ?>
                    </div>
                <?php endif; ?>
                <div class="row">
                    <div class="col-lg-12">
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
                                                            <a href="<?php echo url('/students/view') . '?id=' . $attendance['student_id']; ?>" class="hstack gap-3">
                                                                <div class="avatar-image avatar-md">
                                                                    <div class="avatar-text avatar-md bg-light-primary rounded">
                                                                        <?php echo strtoupper(substr($attendance['first_name'] ?? 'N', 0, 1) . substr($attendance['last_name'] ?? 'A', 0, 1)); ?>
                                                                    </div>
                                                                </div>
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
            <p><span>By: <a target="_blank" href="">ACT 2A</a> </span><span>Distributed by: <a target="_blank" href="">Group 5</a></span></p>
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
    <script src="{{ asset('frontend/assets/vendors/js/vendors.min.js') }}"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <style>
        /* Ensure header dropdowns are visible above other elements */
        .page-header .dropdown-menu,
        .page-header-right .dropdown-menu {
            z-index: 99999 !important;
        }
        /* Allow dropdowns to overflow parent containers */
        .page-header, .page-header-right { overflow: visible !important; }

        /* Dark mode select and Select2 styling */
        select.form-control,
        select.form-select,
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            color: #333;
            background-color: #ffffff;
        }

        /* Dark mode support for Select2 - using app-skin-dark class */
        html.app-skin-dark .select2-container--default .select2-selection--single,
        html.app-skin-dark .select2-container--default .select2-selection--multiple {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f0f0f0 !important;
        }

        /* Dark mode dropdown menu */
        html.app-skin-dark .select2-container--default.select2-container--open .select2-dropdown {
            background-color: #2d3748 !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark .select2-results__option {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
        }

        html.app-skin-dark .select2-results__option--highlighted[aria-selected] {
            background-color: #667eea !important;
            color: #ffffff !important;
        }

        html.app-skin-light select.form-control,
        html.app-skin-dark select.form-select {
            color: #f0f0f0 !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark select.form-control option,
        html.app-skin-dark select.form-select option {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
        }
 /* Filter row alignment */
        .filter-form .form-label {
            margin-bottom: 0.35rem;
        }


        /* Calendar input design */
        .filter-form input[type="date"].form-control {
            min-height: 42px;
            border-radius: 8px;
            padding-right: 2.25rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-form input[type="date"].form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.2);
        }

        html.app-skin-light .filter-form input[type="date"].form-control {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-calendar-picker-indicator {
            filter: invert(1) brightness(1.2);
            opacity: 0.9;
            cursor: pointer;
        }

        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-datetime-edit,
        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-datetime-edit-text,
        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-datetime-edit-month-field,
        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-datetime-edit-day-field,
        html.app-skin-dark .filter-form input[type="date"].form-control::-webkit-datetime-edit-year-field {
            color: #f0f0f0;
        }

        .filter-form .select2-container .select2-selection--single {
            min-height: 42px;
            display: flex;
            align-items: center;
        }

        .filter-form .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px;
        }

        .filter-form .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }

        .filter-form .filter-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.55rem;
        }

        .filter-form .filter-actions .btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>

    <script src="{{ asset('frontend/assets/vendors/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/vendors/js/dataTables.bs5.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/vendors/js/select2-active.min.js') }}"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="{{ asset('frontend/assets/js/common-init.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/js/customers-init.min.js') }}"></script>
    <!--! END: Apps Init !-->
    <!--! BEGIN: Theme Customizer  !-->
    <script src="{{ asset('frontend/assets/js/theme-customizer-init.min.js') }}"></script>
    <!--! END: Theme Customizer !-->

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
                }).fail(function() {
                    // fallback to full reload on error
                    window.location.href = window.location.pathname + (qs ? ('?' + qs) : '');
                });
            });

            // Handle Check All
            $('#checkAllAttendance').on('change', function() {
                $('.checkbox').prop('checked', this.checked);
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

        const attendanceActionUrl = "{{ url('/attendance') }}";
        const csrfToken = "{{ csrf_token() }}";

        function submitAttendanceAction(action, id, remarks) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = attendanceActionUrl;

            var token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = csrfToken;

            var input1 = document.createElement('input');
            input1.type = 'hidden';
            input1.name = 'action';
            input1.value = action;

            var input2 = document.createElement('input');
            input2.type = 'hidden';
            input2.name = 'id';
            input2.value = id;

            form.appendChild(token);
            form.appendChild(input1);
            form.appendChild(input2);

            if (remarks !== undefined && remarks !== null) {
                var input3 = document.createElement('input');
                input3.type = 'hidden';
                input3.name = 'remarks';
                input3.value = remarks;
                form.appendChild(input3);
            }

            document.body.appendChild(form);
            form.submit();
        }

        // Simple modal-based confirmation helper
        function showConfirmModal(options) {
            // options: { title, message, showRemarks:false, onConfirm: function(remarks){}}
            var modalEl = document.getElementById('confirmModal');
            var modalTitle = modalEl.querySelector('.modal-title');
            var modalBody = modalEl.querySelector('.modal-body .confirm-message');
            var remarksWrap = modalEl.querySelector('.modal-body .confirm-remarks-wrap');
            var remarksInput = modalEl.querySelector('#confirmRemarks');
            var okBtn = modalEl.querySelector('#confirmModalOk');

            modalTitle.textContent = options.title || 'Confirm';
            modalBody.textContent = options.message || '';
            if (options.showRemarks) {
                remarksWrap.style.display = 'block';
                remarksInput.value = options.defaultRemarks || '';
            } else {
                remarksWrap.style.display = 'none';
                remarksInput.value = '';
            }

            // remove previous handler
            okBtn.replaceWith(okBtn.cloneNode(true));
            okBtn = modalEl.querySelector('#confirmModalOk');

            okBtn.addEventListener('click', function() {
                var remarks = remarksInput.value.trim();
                var m = bootstrap.Modal.getInstance(modalEl);
                if (m) m.hide();
                if (typeof options.onConfirm === 'function') options.onConfirm(remarks);
            });

            var bsModal = new bootstrap.Modal(modalEl, { backdrop: 'static' });
            bsModal.show();
        }

        // Approve attendance function using modal
        function approveAttendance(id) {
            showConfirmModal({
                title: 'Approve Attendance',
                message: 'Are you sure you want to approve this attendance?',
                showRemarks: false,
                onConfirm: function() { submitAttendanceAction('approve', id); }
            });
        }

        // Reject attendance function using modal with remarks
        function rejectAttendance(id) {
            showConfirmModal({
                title: 'Reject Attendance',
                message: 'Provide a reason for rejection (required):',
                showRemarks: true,
                onConfirm: function(remarks) {
                    if (!remarks) {
                        // reopen modal to require remarks
                        setTimeout(function() {
                            showConfirmModal({
                                title: 'Reject Attendance',
                                message: 'Rejection reason is required.',
                                showRemarks: true,
                                onConfirm: function(r) { if (r) submitAttendanceAction('reject', id, r); }
                            });
                        }, 250);
                        return;
                    }
                    submitAttendanceAction('reject', id, remarks);
                }
            });
        }

        // Edit attendance function
        function editAttendance(id) {
            // Hook for future inline edit modal
            alert('Edit action is not connected yet for Attendance ID: ' + id);
        }

        // Print attendance function
        function printAttendance(id) {
            window.print();
        }

        // Send notification function
        function sendNotification(id) {
            alert('Notification action is not connected yet for Attendance ID: ' + id);
        }

        // Delete attendance function using modal
        function deleteAttendance(id) {
            showConfirmModal({
                title: 'Delete Attendance',
                message: 'Are you sure you want to delete this attendance record? This action cannot be undone.',
                showRemarks: false,
                onConfirm: function() { submitAttendanceAction('delete', id); }
            });
        }
    </script>
    
    <!-- Confirmation Modal (used for approve/reject/delete) -->
    <div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="confirm-message"></p>
                    <div class="confirm-remarks-wrap" style="display:none; margin-top:10px;">
                        <label for="confirmRemarks" class="form-label">Remarks</label>
                        <textarea id="confirmRemarks" class="form-control" rows="3" placeholder="Enter remarks here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="confirmModalCancel" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="confirmModalOk" class="btn btn-primary">OK</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>



