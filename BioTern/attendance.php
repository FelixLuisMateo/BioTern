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

// Fetch Attendance Records with Student Info
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
        c.name as course_name,
        u.name as approver_name
    FROM attendances a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN users u ON a.approved_by = u.id
    ORDER BY a.attendance_date DESC
    LIMIT 100
";
$attendance_result = $conn->query($attendance_query);
$attendances = [];
if ($attendance_result->num_rows > 0) {
    while ($row = $attendance_result->fetch_assoc()) {
        $attendances[] = $row;
    }
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
</head>

<body>
    <!--! ================================================================ !-->
    <!--! [Start] Navigation Manu !-->
    <!--! ================================================================ !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="index.html" class="b-brand">
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
                            <span class="nxl-mtext">Dashboards</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="index.html">CRM</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="analytics.html">Analytics</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-cast"></i></span>
                            <span class="nxl-mtext">Reports</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="reports-sales.html">Sales Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="reports-leads.html">Leads Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="reports-project.html">Project Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="reports-timesheets.html">Timesheets Report</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-send"></i></span>
                            <span class="nxl-mtext">Applications</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="apps-chat.html">Chat</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-email.html">Email</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-tasks.html">Tasks</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-notes.html">Notes</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-storage.html">Storage</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-calendar.html">Calendar</a></li>
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
                            <li class="nxl-item"><a class="nxl-link" href="students-create.html">Students Create</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="attendance.php">Attendance DTR</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="demo-biometric.php">Demo Biometric</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-alert-circle"></i></span>
                            <span class="nxl-mtext">Leads</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="leads.html">Leads</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="leads-view.html">Leads View</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="leads-create.html">Leads Create</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-layout"></i></span>
                            <span class="nxl-mtext">Widgets</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="widgets-lists.html">Lists</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-tables.html">Tables</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-charts.html">Charts</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-statistics.html">Statistics</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-miscellaneous.html">Miscellaneous</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-settings"></i></span>
                            <span class="nxl-mtext">Settings</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="settings-general.html">General</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-seo.html">SEO</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-tags.html">Tags</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-email.html">Email</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-tasks.html">Tasks</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-leads.html">Leads</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-support.html">Support</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-finance.html">Finance</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-gateways.html">Gateways</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-students.php">Students</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-localization.html">Localization</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-recaptcha.html">reCAPTCHA</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-miscellaneous.html">Miscellaneous</a></li>
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
                                    <li class="nxl-item"><a class="nxl-link" href="auth-login-cover.html">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Register</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-register-creative.html">Creative</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Error-404</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-404-minimal.html">Minimal</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Reset Pass</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-reset-cover.html">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Verify OTP</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-verify-cover.html">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Maintenance</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-maintenance-cover.html">Cover</a></li>
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
                            <li class="nxl-item"><a class="nxl-link" href="help-knowledgebase.html">KnowledgeBase</a></li>
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
                <!--! [End] nxl-navigation-toggle !-->
                <!--! [Start] nxl-lavel-mega-menu-toggle !-->
                <div class="nxl-lavel-mega-menu-toggle d-flex d-lg-none">
                    <a href="javascript:void(0);" id="nxl-lavel-mega-menu-open">
                        <i class="feather-align-left"></i>
                    </a>
                </div>
                <!--! [End] nxl-lavel-mega-menu-toggle !-->
                <!--! [Start] nxl-lavel-mega-menu !-->
                <div class="nxl-drp-link nxl-lavel-mega-menu">
                    <div class="nxl-lavel-mega-menu-toggle d-flex d-lg-none">
                        <a href="javascript:void(0)" id="nxl-lavel-mega-menu-hide">
                            <i class="feather-arrow-left me-2"></i>
                            <span>Back</span>
                        </a>
                    </div>
                </div>
                <!--! [End] nxl-lavel-mega-menu !-->
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
                            <a href="./auth-login-minimal.html" class="dropdown-item">
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
                        <li class="breadcrumb-item"><a href="index.html">Home</a></li>
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
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-calendar me-3"></i>
                                        <span>Today</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-calendar me-3"></i>
                                        <span>This Week</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-calendar me-3"></i>
                                        <span>This Month</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-check-circle me-3"></i>
                                        <span>Approved</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-clock me-3"></i>
                                        <span>Pending</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
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
            <div class="main-content">
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
                                                            <a href="students-view.php?id=<?php echo $attendance['student_id']; ?>" class="hstack gap-3">
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
            <p><span>By: <a target="_blank" href="" target="_blank">ACT 2A</a></span> • <span>Distributed by: <a target="_blank" href="" target="_blank">Group 5</a></span></p>
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
            $('[data-select2-selector]').select2();

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

        // Approve attendance function
        function approveAttendance(id) {
            if (confirm('Are you sure you want to approve this attendance?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process_attendance.php';
                
                var input1 = document.createElement('input');
                input1.type = 'hidden';
                input1.name = 'action';
                input1.value = 'approve';
                
                var input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'id';
                input2.value = id;
                
                form.appendChild(input1);
                form.appendChild(input2);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Reject attendance function
        function rejectAttendance(id) {
            var remarks = prompt('Enter rejection reason:');
            if (remarks !== null && remarks.trim() !== '') {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process_attendance.php';
                
                var input1 = document.createElement('input');
                input1.type = 'hidden';
                input1.name = 'action';
                input1.value = 'reject';
                
                var input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'id';
                input2.value = id;
                
                var input3 = document.createElement('input');
                input3.type = 'hidden';
                input3.name = 'remarks';
                input3.value = remarks;
                
                form.appendChild(input1);
                form.appendChild(input2);
                form.appendChild(input3);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Edit attendance function
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

        // Delete attendance function
        function deleteAttendance(id) {
            if (confirm('Are you sure you want to delete this attendance record? This action cannot be undone.')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process_attendance.php';
                
                var input1 = document.createElement('input');
                input1.type = 'hidden';
                input1.name = 'action';
                input1.value = 'delete';
                
                var input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'id';
                input2.value = id;
                
                form.appendChild(input1);
                form.appendChild(input2);
                document.body.appendChild(form);