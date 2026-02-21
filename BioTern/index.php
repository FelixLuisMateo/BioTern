<?php
// Start session early to avoid headers-sent warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database connection
include_once 'config/db.php';

// Initialize analytics variables with defaults
$attendance_awaiting = 0;
$attendance_completed = 0;
$attendance_rejected = 0;
$attendance_total = 0;
$student_count = 0;
$internship_count = 0;
$biometric_registered = 0;
$recent_students = array();
$recent_attendance = array();
$coordinators = array();
$supervisors = array();
$recent_activities = array();

try {
    // Attendance statistics for Payment Record section
    $pending_query = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'pending'");
    if ($pending_query) {
        $attendance_awaiting = (int)$pending_query->fetch_assoc()['count'];
    }
    
    $approved_query = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'approved'");
    if ($approved_query) {
        $attendance_completed = (int)$approved_query->fetch_assoc()['count'];
    }
    
    $rejected_query = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'rejected'");
    if ($rejected_query) {
        $attendance_rejected = (int)$rejected_query->fetch_assoc()['count'];
    }
    
    // Total attendance
    $total_query = $conn->query("SELECT COUNT(*) as count FROM attendances");
    if ($total_query) {
        $attendance_total = (int)$total_query->fetch_assoc()['count'];
    }
    
    // Student count
    $students_query = $conn->query("SELECT COUNT(*) as count FROM students WHERE deleted_at IS NULL");
    if ($students_query) {
        $student_count = (int)$students_query->fetch_assoc()['count'];
    }
    
    // OJT / Internships overview counts (safe queries)
    $ojt_status_counts = array('pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0);
    $ojt_type_counts = array('internal' => 0, 'external' => 0);
    $avg_completion_percentage = 0.0;
    $internship_count = 0;

    $ojt_query = $conn->query("SELECT status, type, COUNT(*) as cnt FROM internships WHERE deleted_at IS NULL GROUP BY status, type");
    if ($ojt_query && $ojt_query->num_rows > 0) {
        while ($r = $ojt_query->fetch_assoc()) {
            $status = isset($r['status']) ? $r['status'] : null;
            $type = isset($r['type']) ? $r['type'] : null;
            $cnt = isset($r['cnt']) ? (int)$r['cnt'] : 0;
            if ($status && array_key_exists($status, $ojt_status_counts)) {
                $ojt_status_counts[$status] += $cnt;
            }
            if ($type && array_key_exists($type, $ojt_type_counts)) {
                $ojt_type_counts[$type] += $cnt;
            }
            $internship_count += $cnt;
        }
    }

    $avg_query = $conn->query("SELECT AVG(completion_percentage) as avg_completion FROM internships WHERE deleted_at IS NULL");
    if ($avg_query) {
        $avg_row = $avg_query->fetch_assoc();
        if ($avg_row && $avg_row['avg_completion'] !== null) {
            $avg_completion_percentage = round((float)$avg_row['avg_completion'], 2);
        }
    }
    
    // Biometric registered students
    $biometric_query = $conn->query("SELECT COUNT(*) as count FROM students WHERE biometric_registered = 1");
    if ($biometric_query) {
        $biometric_registered = (int)$biometric_query->fetch_assoc()['count'];
    }
    
    // Get recent students (last 5)
    $recent_students_query = $conn->query("
        SELECT s.id, s.student_id, s.first_name, s.last_name, s.email, s.status, s.biometric_registered, s.created_at
        FROM students s
        WHERE s.deleted_at IS NULL
        ORDER BY s.created_at DESC
        LIMIT 5
    ");
    
    if ($recent_students_query && $recent_students_query->num_rows > 0) {
        while ($row = $recent_students_query->fetch_assoc()) {
            $recent_students[] = $row;
        }
    }
    
    // Get recent attendance records (last 10) with student info
    $recent_attendance_query = $conn->query("
        SELECT a.id, a.student_id, a.attendance_date, a.morning_time_in, a.morning_time_out, a.status, a.created_at, 
               s.first_name, s.last_name, s.email, s.student_id as student_num
        FROM attendances a
        LEFT JOIN students s ON a.student_id = s.id
        ORDER BY a.attendance_date DESC, a.created_at DESC
        LIMIT 10
    ");
    
    if ($recent_attendance_query && $recent_attendance_query->num_rows > 0) {
        while ($row = $recent_attendance_query->fetch_assoc()) {
            $recent_attendance[] = $row;
        }
    }
    
    // Get coordinators (Active)
    $coordinators_query = $conn->query("
        SELECT u.id, u.name, u.email, c.department_id, c.phone, c.created_at
        FROM users u
        LEFT JOIN coordinators c ON u.id = c.user_id
        WHERE u.role = 'coordinator' AND u.is_active = 1
        ORDER BY u.created_at DESC
        LIMIT 5
    ");
    
    if ($coordinators_query && $coordinators_query->num_rows > 0) {
        while ($row = $coordinators_query->fetch_assoc()) {
            $coordinators[] = $row;
        }
    }
    
    // Get supervisors (Active)
    $supervisors_query = $conn->query("
        SELECT u.id, u.name, u.email, s.phone, s.department, s.created_at
        FROM users u
        LEFT JOIN supervisors s ON u.id = s.user_id
        WHERE u.role = 'supervisor' AND u.is_active = 1
        ORDER BY u.created_at DESC
        LIMIT 5
    ");
    
    if ($supervisors_query && $supervisors_query->num_rows > 0) {
        while ($row = $supervisors_query->fetch_assoc()) {
            $supervisors[] = $row;
        }
    }
    
    // Get recent activities (student registrations, attendance records, etc)
    $activities_query = $conn->query("
        SELECT 
            CONCAT('Student Created: ', s.first_name, ' ', s.last_name) as activity,
            s.created_at as activity_date,
            'student_created' as activity_type,
            s.id as entity_id
        FROM students s
        WHERE s.deleted_at IS NULL
        UNION ALL
        SELECT 
            CONCAT('Attendance Recorded for ', s.first_name, ' ', s.last_name) as activity,
            a.created_at as activity_date,
            'attendance_recorded' as activity_type,
            a.id as entity_id
        FROM attendances a
        LEFT JOIN students s ON a.student_id = s.id
        UNION ALL
        SELECT 
            CONCAT('Biometric Registered: ', s.first_name, ' ', s.last_name) as activity,
            s.biometric_registered_at as activity_date,
            'biometric_registered' as activity_type,
            s.id as entity_id
        FROM students s
        WHERE s.biometric_registered = 1 AND s.biometric_registered_at IS NOT NULL
        ORDER BY activity_date DESC
        LIMIT 15
    ");
    
    if ($activities_query && $activities_query->num_rows > 0) {
        while ($row = $activities_query->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }
    
} catch (Exception $e) {
    // Database error - fallback to 0 values
    error_log("Dashboard error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="x-ua-compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="" />
    <meta name="keyword" content="" />
    <meta name="author" content="flexilecode" />    
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>BioTern || Dashboard</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico" />
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css" />
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css" />
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/daterangepicker.min.css" />
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css" />
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
                            <li class="nxl-item"><a class="nxl-link" href="students.php">Students List</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-view.php">Students View</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-create.php">Students Create</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-edit.php">Students Edit</a></li>
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
            
                <!--! [End] nxl-lavel-mega-menu-toggle !-->
                <!--! [Start] nxl-lavel-mega-menu !-->
                <div class="nxl-drp-link nxl-lavel-mega-menu">
                    <!--! [Start] nxl-lavel-mega-menu-wrapper !-->
                    <div class="nxl-lavel-mega-menu-wrapper d-flex gap-3">
                        <!--! [Start] nxl-lavel-menu !-->
                        <div class="dropdown nxl-h-item nxl-lavel-menu">                            </a>
                            <div class="dropdown-menu nxl-h-dropdown">
                                <div class="dropdown nxl-level-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="feather-send"></i>
                                            <span>Applications</span>
                                        </span>
                                        <i class="feather-chevron-right ms-auto me-0"></i>
                                    </a>
                                    <div class="dropdown-menu nxl-h-dropdown">
                                        <a href="apps-chat.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Chat</span>
                                        </a>
                                        <a href="apps-email.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Email</span>
                                        </a>
                                        <a href="apps-tasks.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Tasks</span>
                                        </a>
                                        <a href="apps-notes.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Notes</span>
                                        </a>
                                        <a href="apps-storage.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Storage</span>
                                        </a>
                                        <a href="apps-calendar.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Calendar</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <div class="dropdown nxl-level-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="feather-cast"></i>
                                            <span>Reports</span>
                                        </span>
                                        <i class="feather-chevron-right ms-auto me-0"></i>
                                    </a>
                                    <div class="dropdown-menu nxl-h-dropdown">
                                        <a href="reports-sales.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Sales Report</span>
                                        </a>
                                        <a href="reports-ojt.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>OJT Report</span>
                                        </a>
                                        <a href="reports-project.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Project Report</span>
                                        </a>
                                        <a href="reports-timesheets.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Timesheets Report</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="dropdown nxl-level-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="feather-at-sign"></i>
                                            <span>Proposal</span>
                                        </span>
                                        <i class="feather-chevron-right ms-auto me-0"></i>
                                    </a>
                                    <div class="dropdown-menu nxl-h-dropdown">
                                        <a href="proposal.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Proposal</span>
                                        </a>
                                        <a href="proposal-view.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Proposal View</span>
                                        </a>
                                        <a href="proposal-edit.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Proposal Edit</span>
                                        </a>
                                        <a href="proposal-create.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Proposal Create</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="dropdown nxl-level-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="feather-dollar-sign"></i>
                                            <span>Payment</span>
                                        </span>
                                        <i class="feather-chevron-right ms-auto me-0"></i>
                                    </a>
                                    <div class="dropdown-menu nxl-h-dropdown">
                                        <a href="payment.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Payment</span>
                                        </a>
                                        <a href="invoice-view.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Invoice View</span>
                                        </a>
                                        <a href="invoice-create.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Invoice Create</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="dropdown nxl-level-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="feather-users"></i>
                                            <span>Students</span>
                                        </span>
                                        <i class="feather-chevron-right ms-auto me-0"></i>
                                    </a>
                                    <div class="dropdown-menu nxl-h-dropdown">
                                        <a href="students.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Students</span>
                                        </a>
                                        <a href="students-view.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Students View</span>
                                        </a>
                                        <a href="students-create.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Students Create</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="dropdown nxl-level-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="feather-alert-circle"></i>
                                            <span>Leads</span>
                                        </span>
                                        <i class="feather-chevron-right ms-auto me-0"></i>
                                    </a>
                                    <div class="dropdown-menu nxl-h-dropdown">
                                        <a href="ojt.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Leads</span>
                                        </a>
                                        <a href="ojt-view.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>OJT View</span>
                                        </a>
                                        <a href="ojt-create.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>OJT Create</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="dropdown nxl-level-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="feather-briefcase"></i>
                                            <span>Projects</span>
                                        </span>
                                        <i class="feather-chevron-right ms-auto me-0"></i>
                                    </a>
                                    <div class="dropdown-menu nxl-h-dropdown">
                                        <a href="projects.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Projects</span>
                                        </a>
                                        <a href="projects-view.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Projects View</span>
                                        </a>
                                        <a href="projects-create.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Projects Create</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="dropdown nxl-level-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="feather-layout"></i>
                                            <span>Widgets</span>
                                        </span>
                                        <i class="feather-chevron-right ms-auto me-0"></i>
                                    </a>
                                    <div class="dropdown-menu nxl-h-dropdown">
                                        <a href="widgets-lists.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Lists</span>
                                        </a>
                                        <a href="widgets-tables.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Tables</span>
                                        </a>
                                        <a href="widgets-charts.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Charts</span>
                                        </a>
                                        <a href="widgets-statistics.php" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Statistics</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="dropdown nxl-level-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="feather-power"></i>
                                            <span>Authentication</span>
                                        </span>
                                        <i class="feather-chevron-right ms-auto me-0"></i>
                                    </a>
                                    <div class="dropdown-menu nxl-h-dropdown">
                                        <div class="dropdown nxl-level-menu">
                                            <a href="javascript:void(0);" class="dropdown-item">
                                                <span class="hstack">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Login</span>
                                                </span>
                                                <i class="feather-chevron-right ms-auto me-0"></i>
                                            </a>
                                            <div class="dropdown-menu nxl-h-dropdown">
                                                <a href="./auth-login-cover.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="./auth-login-cover.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="./auth-login-creative.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Creative</span>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="dropdown nxl-level-menu">
                                            <a href="javascript:void(0);" class="dropdown-item">
                                                <span class="hstack">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Register</span>
                                                </span>
                                                <i class="feather-chevron-right ms-auto me-0"></i>
                                            </a>
                                            <div class="dropdown-menu nxl-h-dropdown">
                                                <a href="./auth-register-cover.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="./auth-register-minimal.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="./register_submit.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Creative</span>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="dropdown nxl-level-menu">
                                            <a href="javascript:void(0);" class="dropdown-item">
                                                <span class="hstack">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Error-404</span>
                                                </span>
                                                <i class="feather-chevron-right ms-auto me-0"></i>
                                            </a>
                                            <div class="dropdown-menu nxl-h-dropdown">
                                                <a href="./auth-404-cover.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="./auth-404-minimal.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="./auth-404-creative.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Creative</span>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="dropdown nxl-level-menu">
                                            <a href="javascript:void(0);" class="dropdown-item">
                                                <span class="hstack">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Reset Pass</span>
                                                </span>
                                                <i class="feather-chevron-right ms-auto me-0"></i>
                                            </a>
                                            <div class="dropdown-menu nxl-h-dropdown">
                                                <a href="./auth-reset-cover.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="./auth-reset-minimal.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="./auth-reset-creative.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Creative</span>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="dropdown nxl-level-menu">
                                            <a href="javascript:void(0);" class="dropdown-item">
                                                <span class="hstack">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Verify OTP</span>
                                                </span>
                                                <i class="feather-chevron-right ms-auto me-0"></i>
                                            </a>
                                            <div class="dropdown-menu nxl-h-dropdown">
                                                <a href="./auth-verify-cover.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="./auth-verify-minimal.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="./auth-verify-creative.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Creative</span>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="dropdown nxl-level-menu">
                                            <a href="javascript:void(0);" class="dropdown-item">
                                                <span class="hstack">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Maintenance</span>
                                                </span>
                                                <i class="feather-chevron-right ms-auto me-0"></i>
                                            </a>
                                            <div class="dropdown-menu nxl-h-dropdown">
                                                <a href="./auth-maintenance-cover.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="./auth-maintenance-minimal.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="./auth-maintenance-creative.php" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Creative</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="feather-plus"></i>
                                    <span>Add New Items</span>
                                </a>
                            </div>
                        </div>
                        <!--! [End] nxl-lavel-menu !-->
                        <!--! [Start] nxl-h-item nxl-mega-menu !-->
                        <div class="dropdown nxl-h-item nxl-mega-menu">
                            <div class="dropdown-menu nxl-h-dropdown" id="mega-menu-dropdown">
                                <div class="d-lg-flex align-items-start">
                                    <!--! [Start] nxl-mega-menu-tabs !-->
                                    <div class="nav flex-column nxl-mega-menu-tabs" role="tablist" aria-orientation="vertical">
                                        <button class="nav-link active nxl-mega-menu-sm" data-bs-toggle="pill" data-bs-target="#v-pills-general" type="button" role="tab">
                                            <span class="menu-icon">
                                                <i class="feather-airplay"></i>
                                            </span>
                                            <span class="menu-title">General</span>
                                            <span class="menu-arrow">
                                                <i class="feather-chevron-right"></i>
                                            </span>
                                        </button>
                                        <button class="nav-link nxl-mega-menu-md" data-bs-toggle="pill" data-bs-target="#v-pills-applications" type="button" role="tab">
                                            <span class="menu-icon">
                                                <i class="feather-send"></i>
                                            </span>
                                            <span class="menu-title">Applications</span>
                                            <span class="menu-arrow">
                                                <i class="feather-chevron-right"></i>
                                            </span>
                                        </button>
                                        <button class="nav-link nxl-mega-menu-lg" data-bs-toggle="pill" data-bs-target="#v-pills-integrations" type="button" role="tab">
                                            <span class="menu-icon">
                                                <i class="feather-link-2"></i>
                                            </span>
                                            <span class="menu-title">Integrations</span>
                                            <span class="menu-arrow">
                                                <i class="feather-chevron-right"></i>
                                            </span>
                                        </button>
                                        <button class="nav-link nxl-mega-menu-xl" data-bs-toggle="pill" data-bs-target="#v-pills-components" type="button" role="tab">
                                            <span class="menu-icon">
                                                <i class="feather-layers"></i>
                                            </span>
                                            <span class="menu-title">Components</span>
                                            <span class="menu-arrow">
                                                <i class="feather-chevron-right"></i>
                                            </span>
                                        </button>
                                        <button class="nav-link nxl-mega-menu-xxl" data-bs-toggle="pill" data-bs-target="#v-pills-authentication" type="button" role="tab">
                                            <span class="menu-icon">
                                                <i class="feather-cpu"></i>
                                            </span>
                                            <span class="menu-title">Authentication</span>
                                            <span class="menu-arrow">
                                                <i class="feather-chevron-right"></i>
                                            </span>
                                        </button>
                                        <button class="nav-link nxl-mega-menu-full" data-bs-toggle="pill" data-bs-target="#v-pills-miscellaneous" type="button" role="tab">
                                            <span class="menu-icon">
                                                <i class="feather-bluetooth"></i>
                                            </span>
                                            <span class="menu-title">Miscellaneous</span>
                                            <span class="menu-arrow">
                                                <i class="feather-chevron-right"></i>
                                            </span>
                                        </button>
                                    </div>
                                    <!--! [End] nxl-mega-menu-tabs !-->
                                    <!--! [Start] nxl-mega-menu-tabs-content !-->
                                    <div class="tab-content nxl-mega-menu-tabs-content">
                                        <!--! [Start] v-pills-general !-->
                                        <div class="tab-pane fade show active" id="v-pills-general" role="tabpanel">
                                            <div class="mb-4 rounded-3 border">
                                                <img src="assets/images/banner/mockup.png" alt="" class="img-fluid rounded-3" />
                                            </div>
                                            <h6 class="fw-bolder">Duralux - Admin Dashboard UiKit</h6>
                                            <p class="fs-12 fw-normal text-muted text-truncate-3-line">Get started Duralux with Duralux up and running. Duralux bootstrap template docs helps you to get started with simple html codes.</p>
                                            <a href="javascript:void(0);" class="fs-13 fw-bold text-primary">Get Started &rarr;</a>
                                        </div>
                                        <!--! [End] v-pills-general !-->
                                        <!--! [Start] v-pills-applications !-->
                                        <div class="tab-pane fade" id="v-pills-applications" role="tabpanel">
                                            <div class="row g-4">
                                                <div class="col-lg-6">
                                                    <h6 class="dropdown-item-title">Applications</h6>
                                                    <a href="apps-chat.php" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Chat</span>
                                                    </a>
                                                    <a href="apps-email.php" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Email</span>
                                                    </a>
                                                    <a href="apps-tasks.php" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Tasks</span>
                                                    </a>
                                                    <a href="apps-notes.php" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Notes</span>
                                                    </a>
                                                    <a href="apps-storage.php" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Storage</span>
                                                    </a>
                                                    <a href="apps-calendar.php" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Calendar</span>
                                                    </a>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="nxl-mega-menu-image">
                                                        <img src="assets/images/general/full-avatar.png" alt="" class="img-fluid full-user-avtar" />
                                                    </div>
                                                </div>
                                            </div>
                                            <hr class="border-top-dashed" />
                                            <div class="d-lg-flex align-items-center justify-content-between">
                                                <div>
                                                    <h6 class="menu-item-heading text-truncate-1-line">Need more application?</h6>
                                                    <p class="fs-12 text-muted mb-0 text-truncate-3-line">We are ready to build custom applications.</p>
                                                </div>
                                                <div class="mt-2 mt-lg-0">
                                                    <a href="mailto:flexilecode@gmail.com" class="fs-13 fw-bold text-primary">Contact Us &rarr;</a>
                                                </div>
                                            </div>
                                        </div>
                                        <!--! [End] v-pills-applications !-->
                                        <!--! [Start] v-pills-integrations !-->
                                        <div class="tab-pane fade" id="v-pills-integrations" role="tabpanel">
                                            <div class="row g-lg-4 nxl-mega-menu-integrations">
                                                <div class="col-lg-12 d-lg-flex align-items-center justify-content-between mb-4 mb-lg-0">
                                                    <div>
                                                        <h6 class="fw-bolder text-dark">Integrations</h6>
                                                        <p class="fs-12 text-muted mb-0">Connect amazing apps on your bucket.</p>
                                                    </div>
                                                    <div class="mt-2 mt-lg-0">
                                                        <a href="javascript:void(0);" class="fs-13 text-primary">Add New &rarr;</a>
                                                    </div>
                                                </div>
                                                <div class="col-lg-4">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/app-store.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">App Store</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/spotify.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Spotify</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/figma.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Figma</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/shopify.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Shopify</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/paypal.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Paypal</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="col-lg-4">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/gmail.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Gmail</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/dropbox.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Dropbox</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/google-drive.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Google Drive</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/github.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Github</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/gitlab.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Gitlab</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                </div>
                                                <div class="col-lg-4">
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/facebook.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Facebook</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/pinterest.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Pinterest</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/instagram.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Instagram</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/twitter.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Twitter</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="assets/images/brand/youtube.png" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Youtube</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>
                                            <hr class="border-top-dashed" />
                                            <p class="fs-13 text-muted mb-0">Need help? Contact our <a href="javascript:void(0);" class="fst-italic">support center</a></p>
                                        </div>
                                        <!--! [End] v-pills-integrations !-->
                                        <!--! [Start] v-pills-components !-->
                                        <div class="tab-pane fade" id="v-pills-components" role="tabpanel">
                                            <div class="row g-4 align-items-center">
                                                <div class="col-xl-8">
                                                    <div class="row g-4">
                                                        <div class="col-lg-4">
                                                            <h6 class="dropdown-item-title">Navigation</h6>
                                                            <a href="javascript:void(0);" class="dropdown-item">Overview</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Analytics</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Sales</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Leads</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Projects</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Timesheets</a>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <h6 class="dropdown-item-title">Pages</h6>
                                                            <a href="javascript:void(0);" class="dropdown-item">OJT </a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Payments</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Projects</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Proposals</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Students</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Documentations</a>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <h6 class="dropdown-item-title">Authentication</h6>
                                                            <a href="javascript:void(0);" class="dropdown-item">Login</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Regiser</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Error-404</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Reset Pass</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Verify OTP</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Maintenance</a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-xl-4">
                                                    <div class="nxl-mega-menu-image">
                                                        <img src="assets/images/banner/1.jpg" alt="" class="img-fluid" />
                                                    </div>
                                                    <div class="mt-4">
                                                        <a href="mailto:flexilecode@gmail.com" class="fs-13 fw-bold">View all resources on Duralux &rarr;</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!--! [End] v-pills-components !-->
                                        <!--! [Start] v-pills-authentication !-->
                                        <div class="tab-pane fade" id="v-pills-authentication" role="tabpanel">
                                            <div class="row g-4 align-items-center nxl-mega-menu-authentication">
                                                <div class="col-xl-8">
                                                    <div class="row g-4">
                                                        <div class="col-lg-4">
                                                            <h6 class="dropdown-item-title">Cover</h6>
                                                            <a href="./auth-login-cover.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Login</span>
                                                            </a>
                                                            <a href="./auth-register-cover.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Register</span>
                                                            </a>
                                                            <a href="./auth-404-cover.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Error-404</span>
                                                            </a>
                                                            <a href="./auth-reset-cover.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Reset Pass</span>
                                                            </a>
                                                            <a href="./auth-verify-cover.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Verify OTP</span>
                                                            </a>
                                                            <a href="./auth-maintenance-cover.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Maintenance</span>
                                                            </a>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <h6 class="dropdown-item-title">Minimal</h6>
                                                            <a href="./auth-login-cover.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Login</span>
                                                            </a>
                                                            <a href="./auth-register-minimal.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Register</span>
                                                            </a>
                                                            <a href="./auth-404-minimal.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Error-404</span>
                                                            </a>
                                                            <a href="./auth-reset-minimal.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Reset Pass</span>
                                                            </a>
                                                            <a href="./auth-verify-minimal.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Verify OTP</span>
                                                            </a>
                                                            <a href="./auth-maintenance-minimal.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Maintenance</span>
                                                            </a>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <h6 class="dropdown-item-title">Creative</h6>
                                                            <a href="./auth-login-creative.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Login</span>
                                                            </a>
                                                            <a href="./register_submit.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Register</span>
                                                            </a>
                                                            <a href="./auth-404-creative.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Error-404</span>
                                                            </a>
                                                            <a href="./auth-reset-creative.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Reset Pass</span>
                                                            </a>
                                                            <a href="./auth-verify-creative.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Verify OTP</span>
                                                            </a>
                                                            <a href="./auth-maintenance-creative.php" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Maintenance</span>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-xl-4">
                                                    <div id="carouselResourcesCaptions" class="carousel slide" data-bs-ride="carousel">
                                                        <div class="carousel-indicators">
                                                            <button type="button" data-bs-target="#carouselResourcesCaptions" data-bs-slide-to="0" class="active" aria-current="true"></button>
                                                            <button type="button" data-bs-target="#carouselResourcesCaptions" data-bs-slide-to="1"></button>
                                                            <button type="button" data-bs-target="#carouselResourcesCaptions" data-bs-slide-to="2"></button>
                                                            <button type="button" data-bs-target="#carouselResourcesCaptions" data-bs-slide-to="3"></button>
                                                            <button type="button" data-bs-target="#carouselResourcesCaptions" data-bs-slide-to="4"></button>
                                                            <button type="button" data-bs-target="#carouselResourcesCaptions" data-bs-slide-to="5"></button>
                                                        </div>
                                                        <div class="carousel-inner rounded-3">
                                                            <div class="carousel-item active">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="assets/images/banner/6.jpg" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">Shopify eCommerce Store</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the first slide.</p>
                                                                </div>
                                                            </div>
                                                            <div class="carousel-item">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="assets/images/banner/5.jpg" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">iOS Apps Development</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the second slide.</p>
                                                                </div>
                                                            </div>
                                                            <div class="carousel-item">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="assets/images/banner/4.jpg" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">Figma Dashboard Design</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                </div>
                                                            </div>
                                                            <div class="carousel-item">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="assets/images/banner/3.jpg" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">React Dashboard Design</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                </div>
                                                            </div>
                                                            <div class="carousel-item">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="assets/images/banner/2.jpg" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">Standup Team Meeting</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                </div>
                                                            </div>
                                                            <div class="carousel-item">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="assets/images/banner/1.jpg" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">Zoom Team Meeting</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <button class="carousel-control-prev" type="button" data-bs-target="#carouselResourcesCaptions" data-bs-slide="prev">
                                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                            <span class="visually-hidden">Previous</span>
                                                        </button>
                                                        <button class="carousel-control-next" type="button" data-bs-target="#carouselResourcesCaptions" data-bs-slide="next">
                                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                            <span class="visually-hidden">Next</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!--! [End] v-pills-authentication !-->
                                        <!--! [Start] v-pills-miscellaneous !-->
                                        <div class="tab-pane fade nxl-mega-menu-miscellaneous" id="v-pills-miscellaneous" role="tabpanel">
                                            <!-- Nav tabs -->
                                            <ul class="nav nav-tabs flex-column flex-lg-row nxl-mega-menu-miscellaneous-tabs" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#v-pills-projects" type="button" role="tab">
                                                        <span class="menu-icon">
                                                            <i class="feather-cast"></i>
                                                        </span>
                                                        <span class="menu-title">Projects</span>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#v-pills-services" type="button" role="tab">
                                                        <span class="menu-icon">
                                                            <i class="feather-check-square"></i>
                                                        </span>
                                                        <span class="menu-title">Services</span>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#v-pills-features" type="button" role="tab">
                                                        <span class="menu-icon">
                                                            <i class="feather-airplay"></i>
                                                        </span>
                                                        <span class="menu-title">Features</span>
                                                    </button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#v-pills-blogs" type="button" role="tab">
                                                        <span class="menu-icon">
                                                            <i class="feather-bold"></i>
                                                        </span>
                                                        <span class="menu-title">Blogs</span>
                                                    </button>
                                                </li>
                                            </ul>
                                            <!-- Tab panes -->
                                            <div class="tab-content nxl-mega-menu-miscellaneous-content">
                                                <div class="tab-pane fade active show" id="v-pills-projects" role="tabpanel">
                                                    <div class="row g-4">
                                                        <div class="col-xxl-2 d-lg-none d-xxl-block">
                                                            <h6 class="dropdown-item-title">Categories</h6>
                                                            <a href="javascript:void(0);" class="dropdown-item">Support</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Services</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Applicatios</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">eCommerce</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Development</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Miscellaneous</a>
                                                        </div>
                                                        <div class="col-xxl-10">
                                                            <div class="row g-4">
                                                                <div class="col-xl-6">
                                                                    <div class="d-lg-flex align-items-center gap-3">
                                                                        <div class="wd-150 rounded-3">
                                                                            <img src="assets/images/banner/1.jpg" alt="" class="img-fluid rounded-3" />
                                                                        </div>
                                                                        <div class="mt-3 mt-lg-0 ms-lg-3 item-text">
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Shopify eCommerce Store</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Sint nam ullam iure eum sed rerum libero quis doloremque maiores veritatis?</p>
                                                                            <div class="hstack gap-2 mt-3">
                                                                                <div class="avatar-image avatar-sm">
                                                                                    <img src="assets/images/avatar/1.png" alt="" class="img-fluid" />
                                                                                </div>
                                                                                <a href="javascript:void(0);" class="fs-12">Felix Luis Mateo</a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-6">
                                                                    <div class="d-lg-flex align-items-center gap-3">
                                                                        <div class="wd-150 rounded-3">
                                                                            <img src="assets/images/banner/2.jpg" alt="" class="img-fluid rounded-3" />
                                                                        </div>
                                                                        <div class="mt-3 mt-lg-0 ms-lg-3 item-text">
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">iOS Apps Development</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Sint nam ullam iure eum sed rerum libero quis doloremque maiores veritatis?</p>
                                                                            <div class="hstack gap-2 mt-3">
                                                                                <div class="avatar-image avatar-sm">
                                                                                    <img src="assets/images/avatar/2.png" alt="" class="img-fluid" />
                                                                                </div>
                                                                                <a href="javascript:void(0);" class="fs-12">Green Cute</a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-6">
                                                                    <div class="d-lg-flex align-items-center gap-3">
                                                                        <div class="wd-150 rounded-3">
                                                                            <img src="assets/images/banner/3.jpg" alt="" class="img-fluid rounded-3" />
                                                                        </div>
                                                                        <div class="mt-3 mt-lg-0 ms-lg-3 item-text">
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Figma Dashboard Design</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Sint nam ullam iure eum sed rerum libero quis doloremque maiores veritatis?</p>
                                                                            <div class="hstack gap-2 mt-3">
                                                                                <div class="avatar-image avatar-sm">
                                                                                    <img src="assets/images/avatar/3.png" alt="" class="img-fluid" />
                                                                                </div>
                                                                                <a href="javascript:void(0);" class="fs-12">Malanie Hanvey</a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-6">
                                                                    <div class="d-lg-flex align-items-center gap-3">
                                                                        <div class="wd-150 rounded-3">
                                                                            <img src="assets/images/banner/4.jpg" alt="" class="img-fluid rounded-3" />
                                                                        </div>
                                                                        <div class="mt-3 mt-lg-0 ms-lg-3 item-text">
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">React Dashboard Design</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Sint nam ullam iure eum sed rerum libero quis doloremque maiores veritatis?</p>
                                                                            <div class="hstack gap-2 mt-3">
                                                                                <div class="avatar-image avatar-sm">
                                                                                    <img src="assets/images/avatar/4.png" alt="" class="img-fluid" />
                                                                                </div>
                                                                                <a href="javascript:void(0);" class="fs-12">Kenneth Hune</a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="tab-pane fade" id="v-pills-services" role="tabpanel">
                                                    <div class="row g-4 nxl-mega-menu-miscellaneous-services">
                                                        <div class="col-xl-8">
                                                            <div class="row g-4">
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg rounded bg-primary text-white">
                                                                            <i class="feather-bar-chart-2 mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Analytics Services</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg rounded bg-danger text-white">
                                                                            <i class="feather-feather mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Content Writing</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg rounded bg-warning text-white">
                                                                            <i class="feather-bell mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">SEO (Search Engine Optimization)</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg rounded bg-success text-white">
                                                                            <i class="feather-shield mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Security Services</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg rounded bg-teal text-white">
                                                                            <i class="feather-shopping-cart mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">eCommerce Services</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg rounded bg-dark text-white">
                                                                            <i class="feather-life-buoy mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Support Services</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-12">
                                                                    <div class="p-3 bg-soft-dark text-dark rounded d-lg-flex align-items-center justify-content-between">
                                                                        <div class="fs-13">
                                                                            <i class="feather-star me-2"></i>
                                                                            <span>View all services on Duralux.</span>
                                                                        </div>
                                                                        <div class="mt-2 mt-lg-0">
                                                                            <a href="javascript:void(0);" class="fs-13 text-primary">Learn More &rarr;</a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-xl-4">
                                                            <div id="carouselServicesCaptions" class="carousel slide" data-bs-ride="carousel">
                                                                <div class="carousel-indicators">
                                                                    <button type="button" data-bs-target="#carouselServicesCaptions" data-bs-slide-to="0" class="active" aria-current="true"></button>
                                                                    <button type="button" data-bs-target="#carouselServicesCaptions" data-bs-slide-to="1"></button>
                                                                    <button type="button" data-bs-target="#carouselServicesCaptions" data-bs-slide-to="2"></button>
                                                                    <button type="button" data-bs-target="#carouselServicesCaptions" data-bs-slide-to="3"></button>
                                                                    <button type="button" data-bs-target="#carouselServicesCaptions" data-bs-slide-to="4"></button>
                                                                    <button type="button" data-bs-target="#carouselServicesCaptions" data-bs-slide-to="5"></button>
                                                                </div>
                                                                <div class="carousel-inner rounded-3">
                                                                    <div class="carousel-item active">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="assets/images/banner/6.jpg" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">Shopify eCommerce Store</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the first slide.</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="carousel-item">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="assets/images/banner/5.jpg" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">iOS Apps Development</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the second slide.</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="carousel-item">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="assets/images/banner/4.jpg" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">Figma Dashboard Design</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="carousel-item">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="assets/images/banner/3.jpg" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">React Dashboard Design</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="carousel-item">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="assets/images/banner/2.jpg" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">Standup Team Meeting</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="carousel-item">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="assets/images/banner/1.jpg" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">Zoom Team Meeting</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <button class="carousel-control-prev" type="button" data-bs-target="#carouselServicesCaptions" data-bs-slide="prev">
                                                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                                    <span class="visually-hidden">Previous</span>
                                                                </button>
                                                                <button class="carousel-control-next" type="button" data-bs-target="#carouselServicesCaptions" data-bs-slide="next">
                                                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                                    <span class="visually-hidden">Next</span>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="tab-pane fade" id="v-pills-features" role="tabpanel">
                                                    <div class="row g-4 nxl-mega-menu-miscellaneous-features">
                                                        <div class="col-xl-8">
                                                            <div class="row g-4">
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg bg-soft-primary text-primary border-soft-primary rounded">
                                                                            <i class="feather-bell mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Notifications</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg bg-soft-danger text-danger border-soft-danger rounded">
                                                                            <i class="feather-bar-chart-2 mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Analytics</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg bg-soft-success text-success border-soft-success rounded">
                                                                            <i class="feather-link-2 mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Ingetrations</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg bg-soft-indigo text-indigo border-soft-indigo rounded">
                                                                            <i class="feather-book mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Documentations</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg bg-soft-warning text-warning border-soft-warning rounded">
                                                                            <i class="feather-shield mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Security</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-6">
                                                                    <div class="d-flex align-items-start gap-3">
                                                                        <div class="avatar-text avatar-lg bg-soft-teal text-teal border-soft-teal rounded">
                                                                            <i class="feather-life-buoy mx-auto"></i>
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Support</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet consectetur adipisicing elit Unde numquam rem dignissimos. elit Unde numquam rem dignissimos.</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-xxl-3 offset-xxl-1 col-xl-4">
                                                            <div class="nxl-mega-menu-image">
                                                                <img src="assets/images/banner/1.jpg" alt="" class="img-fluid" />
                                                            </div>
                                                            <div class="mt-4">
                                                                <a href="mailto:flexilecode@gmail.com" class="fs-13 fw-bold">View all features on Duralux &rarr;</a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="tab-pane fade" id="v-pills-blogs" role="tabpanel">
                                                    <div class="row g-4">
                                                        <div class="col-xxl-2 d-lg-none d-xxl-block">
                                                            <h6 class="dropdown-item-title">Categories</h6>
                                                            <a href="javascript:void(0);" class="dropdown-item">Support</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Services</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Applicatios</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">eCommerce</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Development</a>
                                                            <a href="javascript:void(0);" class="dropdown-item">Miscellaneous</a>
                                                        </div>
                                                        <div class="col-xxl-10">
                                                            <div class="row g-4">
                                                                <div class="col-xxl-4 col-lg-6">
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <div class="wd-100 rounded-3">
                                                                            <img src="assets/images/banner/1.jpg" alt="" class="img-fluid rounded-3 border border-3" />
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Lorem ipsum dolor sit</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum, dolor sit amet consectetur adipisicing elit. Eius dolor quo commodi nisi animi error minus quia aliquam.</p>
                                                                            <span class="fs-11 text-gray-500">26 March, 2023</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xxl-4 col-lg-6">
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <div class="wd-100 rounded-3">
                                                                            <img src="assets/images/banner/2.jpg" alt="" class="img-fluid rounded-3 border border-3" />
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Lorem ipsum dolor sit</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum, dolor sit amet consectetur adipisicing elit. Eius dolor quo commodi nisi animi error minus quia aliquam.</p>
                                                                            <span class="fs-11 text-gray-500">26 March, 2023</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xxl-4 col-lg-6">
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <div class="wd-100 rounded-3">
                                                                            <img src="assets/images/banner/3.jpg" alt="" class="img-fluid rounded-3 border border-3" />
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Lorem ipsum dolor sit</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum, dolor sit amet consectetur adipisicing elit. Eius dolor quo commodi nisi animi error minus quia aliquam.</p>
                                                                            <span class="fs-11 text-gray-500">26 March, 2023</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xxl-4 col-lg-6">
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <div class="wd-100 rounded-3">
                                                                            <img src="assets/images/banner/4.jpg" alt="" class="img-fluid rounded-3 border border-3" />
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Lorem ipsum dolor sit</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum, dolor sit amet consectetur adipisicing elit. Eius dolor quo commodi nisi animi error minus quia aliquam.</p>
                                                                            <span class="fs-11 text-gray-500">26 March, 2023</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xxl-4 col-lg-6">
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <div class="wd-100 rounded-3">
                                                                            <img src="assets/images/banner/5.jpg" alt="" class="img-fluid rounded-3 border border-3" />
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Lorem ipsum dolor sit</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum, dolor sit amet consectetur adipisicing elit. Eius dolor quo commodi nisi animi error minus quia aliquam.</p>
                                                                            <span class="fs-11 text-gray-500">26 March, 2023</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xxl-4 col-lg-6">
                                                                    <div class="d-flex align-items-center gap-3">
                                                                        <div class="wd-100 rounded-3">
                                                                            <img src="assets/images/banner/6.jpg" alt="" class="img-fluid rounded-3 border border-3" />
                                                                        </div>
                                                                        <div>
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Lorem ipsum dolor sit</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum, dolor sit amet consectetur adipisicing elit. Eius dolor quo commodi nisi animi error minus quia aliquam.</p>
                                                                            <span class="fs-11 text-gray-500">26 March, 2023</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-lg-12">
                                                                    <div class="p-3 bg-soft-dark text-dark rounded d-flex align-items-center justify-content-between gap-4">
                                                                        <div class="fs-13 text-truncate-1-line">
                                                                            <i class="feather-star me-2"></i>
                                                                            <strong>Version 2.3.2 is out!</strong>
                                                                            <span>Learn more about our news and schedule reporting.</span>
                                                                        </div>
                                                                        <div class="wd-100 text-end">
                                                                            <a href="javascript:void(0);" class="fs-13 text-primary">Learn More &rarr;</a>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!--! [End] v-pills-miscellaneous !-->
                                    </div>
                                    <!--! [End] nxl-mega-menu-tabs-content !-->
                                </div>
                            </div>
                        </div>
                        <!--! [End] nxl-h-item nxl-mega-menu !-->
                    </div>
                    <!--! [End] nxl-lavel-mega-menu-wrapper !-->
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
                    <div class="dropdown nxl-h-item nxl-header-language d-none d-sm-flex">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-language-dropdown">
                            <div class="dropdown-divider mt-0"></div>
                            <div class="language-items-wrapper">
                                <div class="select-language px-4 py-2 hstack justify-content-between gap-4">
                                    <div class="lh-lg">
                                        <h6 class="mb-0">Select Language</h6>
                                        <p class="fs-11 text-muted mb-0">12 languages avaiable!</p>
                                    </div>
                                    <a href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Add Language">
                                        <i class="feather-plus"></i>
                                    </a>
                                </div>
                                <div class="dropdown-divider"></div>
                                <div class="row px-4 pt-3">
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/sa.svg" alt="" class="img-fluid" /></div>
                                            <span>Arabic</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/bd.svg" alt="" class="img-fluid" /></div>
                                            <span>Bengali</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/ch.svg" alt="" class="img-fluid" /></div>
                                            <span>Chinese</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/nl.svg" alt="" class="img-fluid" /></div>
                                            <span>Dutch</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select active">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/us.svg" alt="" class="img-fluid" /></div>
                                            <span>English</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/fr.svg" alt="" class="img-fluid" /></div>
                                            <span>French</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/de.svg" alt="" class="img-fluid" /></div>
                                            <span>German</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/in.svg" alt="" class="img-fluid" /></div>
                                            <span>Hindi</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/ru.svg" alt="" class="img-fluid" /></div>
                                            <span>Russian</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/es.svg" alt="" class="img-fluid" /></div>
                                            <span>Spanish</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/tr.svg" alt="" class="img-fluid" /></div>
                                            <span>Turkish</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="assets/vendors/img/flags/1x1/pk.svg" alt="" class="img-fluid" /></div>
                                            <span>Urdo</span>
                                        </a>
                                    </div>
                                </div>
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
                            <a href="./auth-login-cover.php" class="dropdown-item">
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
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- [ page-header ] start -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Overview</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item">Overview</li>
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
                            <div id="reportrange" class="reportrange-picker d-flex align-items-center">
                                <span class="reportrange-picker-field"></span>
                            </div>
                            <div class="dropdown filter-dropdown">
                                <a class="btn btn-md btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-filter me-2"></i>
                                    <span>Filter</span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <div class="dropdown-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="Role" checked="checked" />
                                            <label class="custom-control-label c-pointer" for="Role">Role</label>
                                        </div>
                                    </div>
                                    <div class="dropdown-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="Team" checked="checked" />
                                            <label class="custom-control-label c-pointer" for="Team">Team</label>
                                        </div>
                                    </div>
                                    <div class="dropdown-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="Email" checked="checked" />
                                            <label class="custom-control-label c-pointer" for="Email">Email</label>
                                        </div>
                                    </div>
                                    <div class="dropdown-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="Member" checked="checked" />
                                            <label class="custom-control-label c-pointer" for="Member">Member</label>
                                        </div>
                                    </div>
                                    <div class="dropdown-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="Recommendation" checked="checked" />
                                            <label class="custom-control-label c-pointer" for="Recommendation">Recommendation</label>
                                        </div>
                                    </div>
                                    <div class="dropdown-divider"></div>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-plus me-3"></i>
                                        <span>Create New</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-filter me-3"></i>
                                        <span>Manage Filter</span>
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
            <!-- [ page-header ] end -->
            <!-- [ Main Content ] start -->
            <div class="main-content">
                <div class="row">
                    <!-- [Attendance Awaiting Approval] start -->
                    <div class="col-xxl-3 col-md-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-4">
                                    <div class="d-flex gap-4 align-items-center">
                                        <div class="avatar-text avatar-lg bg-gray-200">
                                            <i class="feather-clock"></i>
                                        </div>
                                        <div>
                                            <div class="fs-4 fw-bold text-dark"><span class="counter"><?php echo $attendance_awaiting; ?></span>/<span class="counter"><?php echo $attendance_total; ?></span></div>
                                            <h3 class="fs-13 fw-semibold text-truncate-1-line">Attendance Awaiting Approval</h3>
                                        </div>
                                    </div>
                                    <a href="attendance.php" class="">
                                        <i class="feather-more-vertical"></i>
                                    </a>
                                </div>
                                <div class="pt-4">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <a href="attendance.php" class="fs-12 fw-medium text-muted text-truncate-1-line">Pending Records </a>
                                        <div class="w-100 text-end">
                                            <span class="fs-12 text-dark"><?php echo $attendance_awaiting; ?> Pending</span>
                                            <span class="fs-11 text-muted"><?php echo ($attendance_total > 0) ? round(($attendance_awaiting / $attendance_total) * 100) : 0; ?>%</span>
                                        </div>
                                    </div>
                                    <div class="progress mt-2 ht-3">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo ($attendance_total > 0) ? round(($attendance_awaiting / $attendance_total) * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Attendance Awaiting Approval] end -->
                    <!-- [Attendance Approved] start -->
                    <div class="col-xxl-3 col-md-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-4">
                                    <div class="d-flex gap-4 align-items-center">
                                        <div class="avatar-text avatar-lg bg-gray-200">
                                            <i class="feather-check-circle"></i>
                                        </div>
                                        <div>
                                            <div class="fs-4 fw-bold text-dark"><span class="counter"><?php echo $attendance_completed; ?></span>/<span class="counter"><?php echo $attendance_total; ?></span></div>
                                            <h3 class="fs-13 fw-semibold text-truncate-1-line">Attendance Approved</h3>
                                        </div>
                                    </div>
                                    <a href="attendance.php" class="">
                                        <i class="feather-more-vertical"></i>
                                    </a>
                                </div>
                                <div class="pt-4">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <a href="attendance.php" class="fs-12 fw-medium text-muted text-truncate-1-line">Approved Records </a>
                                        <div class="w-100 text-end">
                                            <span class="fs-12 text-dark"><?php echo $attendance_completed; ?> Approved</span>
                                            <span class="fs-11 text-muted"><?php echo ($attendance_total > 0) ? round(($attendance_completed / $attendance_total) * 100) : 0; ?>%</span>
                                        </div>
                                    </div>
                                    <div class="progress mt-2 ht-3">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo ($attendance_total > 0) ? round(($attendance_completed / $attendance_total) * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Attendance Approved] end -->
                    <!-- [Active Internships] start -->
                    <div class="col-xxl-3 col-md-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-4">
                                    <div class="d-flex gap-4 align-items-center">
                                        <div class="avatar-text avatar-lg bg-gray-200">
                                            <i class="feather-users"></i>
                                        </div>
                                        <div>
                                            <div class="fs-4 fw-bold text-dark"><span class="counter"><?php echo $internship_count; ?></span></div>
                                            <h3 class="fs-13 fw-semibold text-truncate-1-line">Active Internships</h3>
                                        </div>
                                    </div>
                                    <a href="students.php" class="">
                                        <i class="feather-more-vertical"></i>
                                    </a>
                                </div>
                                <div class="pt-4">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <a href="students.php" class="fs-12 fw-medium text-muted text-truncate-1-line">Ongoing Internships </a>
                                        <div class="w-100 text-end">
                                            <span class="fs-12 text-dark"><?php echo $internship_count; ?> Active</span>
                                            <span class="fs-11 text-muted">See List</span>
                                        </div>
                                    </div>
                                    <div class="progress mt-2 ht-3">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: 100%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Active Internships] end -->
                    <!-- [Biometric Registration] start -->
                    <div class="col-xxl-3 col-md-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between mb-4">
                                    <div class="d-flex gap-4 align-items-center">
                                        <div class="avatar-text avatar-lg bg-gray-200">
                                            <i class="feather-activity"></i>
                                        </div>
                                        <div>
                                            <div class="fs-4 fw-bold text-dark"><span class="counter"><?php echo $biometric_registered; ?></span>/<span class="counter"><?php echo $student_count; ?></span></div>
                                            <h3 class="fs-13 fw-semibold text-truncate-1-line">Biometric Registered</h3>
                                        </div>
                                    </div>
                                    <a href="demo-biometric.php" class="">
                                        <i class="feather-more-vertical"></i>
                                    </a>
                                </div>
                                <div class="pt-4">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <a href="demo-biometric.php" class="fs-12 fw-medium text-muted text-truncate-1-line"> Biometric Rate </a>
                                        <div class="w-100 text-end">
                                            <span class="fs-12 text-dark"><?php echo $biometric_registered; ?> Students</span>
                                            <span class="fs-11 text-muted"><?php echo ($student_count > 0) ? round(($biometric_registered / $student_count) * 100) : 0; ?>%</span>
                                        </div>
                                    </div>
                                    <div class="progress mt-2 ht-3">
                                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo ($student_count > 0) ? round(($biometric_registered / $student_count) * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Biometric Registration] end -->
                    <!-- [Recent Activities & Logs] start (replaces Payment Record) -->
                    <div class="col-xxl-8">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Recent Activities & Logs</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
                                            <div data-bs-toggle="tooltip" title="Options">
                                                <i class="feather-more-vertical"></i>
                                            </div>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-sliders"></i>Filter</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-download"></i>Export</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-settings"></i>Settings</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body custom-card-action p-0">
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php if (count($recent_activities) > 0): ?>
                                        <?php foreach ($recent_activities as $activity): ?>
                                        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
                                            <div class="avatar-text avatar-sm rounded-circle" 
                                                style="background-color: <?php 
                                                    echo ($activity['activity_type'] === 'student_created') ? '#e3f2fd' : 
                                                         (($activity['activity_type'] === 'attendance_recorded') ? '#f3e5f5' : '#e8f5e9');
                                                ?>">
                                                <i class="feather-<?php 
                                                    echo ($activity['activity_type'] === 'student_created') ? 'user-plus' : 
                                                         (($activity['activity_type'] === 'attendance_recorded') ? 'clock' : 'check-circle');
                                                ?>" style="font-size: 14px;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <a href="javascript:void(0);" class="fw-semibold text-dark d-block">
                                                    <?php echo htmlspecialchars($activity['activity']); ?>
                                                </a>
                                                <span class="fs-12 text-muted">
                                                    <?php if ($activity['activity_date']): ?>
                                                        <?php echo date('M d, Y H:i', strtotime($activity['activity_date'])); ?>
                                                    <?php else: ?>
                                                        No date
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <span class="badge <?php 
                                                echo ($activity['activity_type'] === 'student_created') ? 'bg-soft-info text-info' : 
                                                     (($activity['activity_type'] === 'attendance_recorded') ? 'bg-soft-warning text-warning' : 'bg-soft-success text-success');
                                            ?> fs-10">
                                                <?php echo str_replace('_', ' ', ucfirst($activity['activity_type'])); ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center py-4">No recent activities found</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="javascript:void(0);" class="card-footer fs-11 fw-bold text-uppercase text-center py-3">View All Activities</a>
                        </div>
                    </div>
                    <!-- [Recent Activities & Logs] end -->
                    <!-- [Total Students] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full overflow-hidden">
                            <div class="bg-primary text-white">
                                <div class="p-4">
                                    <span class="badge bg-light text-primary text-dark float-end"><?php echo $student_count; ?></span>
                                    <div class="text-start">
                                        <h4 class="text-reset"><?php echo $student_count; ?></h4>
                                        <p class="text-reset m-0">Total Students Enrolled</p>
                                    </div>
                                </div>
                                <div id="total-sales-color-graph"></div>
                            </div>
                            <div class="card-body">
                                <?php if (count($recent_students) > 0): ?>
                                    <?php foreach (array_slice($recent_students, 0, 3) as $student): ?>
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="hstack gap-3">
                                            <div class="avatar-text avatar-lg bg-soft-primary text-primary">
                                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <a href="students-view.php?id=<?php echo $student['id']; ?>" class="d-block fw-semibold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></a>
                                                <span class="fs-12 text-muted"><?php echo htmlspecialchars($student['student_id']); ?></span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($student['biometric_registered']): ?>
                                            <span class="badge bg-soft-success text-success fs-10">Biometric ✓</span>
                                            <?php else: ?>
                                            <span class="badge bg-soft-warning text-warning fs-10">Pending Bio</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <hr class="border-dashed my-3" />
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">No recent students found</p>
                                <?php endif; ?>
                            </div>
                            <a href="students.php" class="card-footer fs-11 fw-bold text-uppercase text-center py-4">View All Students</a>
                        </div>
                    </div>
                    <!-- [Total Sales] end !-->
                    <!-- [Mini] start -->
                    <div class="col-lg-4">
                        <div class="card mb-4 stretch stretch-full">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div class="d-flex gap-3 align-items-center">
                                    <div class="avatar-text">
                                        <i class="feather feather-star"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">Tasks Completed</div>
                                        <div class="fs-12 text-muted">22/35 completed</div>
                                    </div>
                                </div>
                                <div class="fs-4 fw-bold text-dark">22/35</div>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-between gap-4">
                                <div id="task-completed-area-chart"></div>
                                <div class="fs-12 text-muted text-nowrap">
                                    <span class="fw-semibold text-primary">28% more</span><br />
                                    <span>from last week</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card mb-4 stretch stretch-full">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div class="d-flex gap-3 align-items-center">
                                    <div class="avatar-text">
                                        <i class="feather feather-file-text"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">New Tasks</div>
                                        <div class="fs-12 text-muted">0/20 tasks</div>
                                    </div>
                                </div>
                                <div class="fs-4 fw-bold text-dark">5/20</div>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-between gap-4">
                                <div id="new-tasks-area-chart"></div>
                                <div class="fs-12 text-muted text-nowrap">
                                    <span class="fw-semibold text-success">34% more</span><br />
                                    <span>from last week</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card mb-4 stretch stretch-full">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <div class="d-flex gap-3 align-items-center">
                                    <div class="avatar-text">
                                        <i class="feather feather-airplay"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">Project Done</div>
                                        <div class="fs-12 text-muted">20/30 project</div>
                                    </div>
                                </div>
                                <div class="fs-4 fw-bold text-dark">20/30</div>
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-between gap-4">
                                <div id="project-done-area-chart"></div>
                                <div class="fs-12 text-muted text-nowrap">
                                    <span class="fw-semibold text-danger">42% more</span><br />
                                    <span>from last week</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Mini] end !-->
                    <!-- [OJT Overview] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">OJT Overview</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
                                            <div data-bs-toggle="tooltip" title="Options">
                                                <i class="feather-more-vertical"></i>
                                            </div>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-at-sign"></i>New</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-calendar"></i>Event</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-bell"></i>Snoozed</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-trash-2"></i>Deleted</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-settings"></i>Settings</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-life-buoy"></i>Tips & Tricks</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body custom-card-action">
                                <div id="leads-overview-donut"></div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="p-2 hstack gap-2 rounded border border-dashed border-gray-5">
                                            <div class="flex-grow-1">
                                                <div class="fs-12 text-muted">Pending</div>
                                                <h6 class="fw-bold text-dark"><?php echo isset($ojt_status_counts['pending']) ? intval($ojt_status_counts['pending']) : 0; ?></h6>
                                            </div>
                                            <div class="text-nowrap"><span class="badge bg-soft-warning text-warning">Status</span></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 hstack gap-2 rounded border border-dashed border-gray-5">
                                            <div class="flex-grow-1">
                                                <div class="fs-12 text-muted">Ongoing</div>
                                                <h6 class="fw-bold text-dark"><?php echo isset($ojt_status_counts['ongoing']) ? intval($ojt_status_counts['ongoing']) : 0; ?></h6>
                                            </div>
                                            <div class="text-nowrap"><span class="badge bg-soft-info text-info">Status</span></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 hstack gap-2 rounded border border-dashed border-gray-5">
                                            <div class="flex-grow-1">
                                                <div class="fs-12 text-muted">Completed</div>
                                                <h6 class="fw-bold text-dark"><?php echo isset($ojt_status_counts['completed']) ? intval($ojt_status_counts['completed']) : 0; ?></h6>
                                            </div>
                                            <div class="text-nowrap"><span class="badge bg-soft-success text-success">Status</span></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 hstack gap-2 rounded border border-dashed border-gray-5">
                                            <div class="flex-grow-1">
                                                <div class="fs-12 text-muted">Cancelled</div>
                                                <h6 class="fw-bold text-dark"><?php echo isset($ojt_status_counts['cancelled']) ? intval($ojt_status_counts['cancelled']) : 0; ?></h6>
                                            </div>
                                            <div class="text-nowrap"><span class="badge bg-soft-danger text-danger">Status</span></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 hstack gap-2 rounded border border-dashed border-gray-5">
                                            <div class="flex-grow-1">
                                                <div class="fs-12 text-muted">Internal</div>
                                                <h6 class="fw-bold text-dark"><?php echo isset($ojt_type_counts['internal']) ? intval($ojt_type_counts['internal']) : 0; ?></h6>
                                            </div>
                                            <div class="text-nowrap"><span class="badge bg-soft-primary text-primary">Type</span></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 hstack gap-2 rounded border border-dashed border-gray-5">
                                            <div class="flex-grow-1">
                                                <div class="fs-12 text-muted">External</div>
                                                <h6 class="fw-bold text-dark"><?php echo isset($ojt_type_counts['external']) ? intval($ojt_type_counts['external']) : 0; ?></h6>
                                            </div>
                                            <div class="text-nowrap"><span class="badge bg-soft-secondary text-dark">Type</span></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 hstack gap-2 rounded border border-dashed border-gray-5">
                                            <div class="flex-grow-1">
                                                <div class="fs-12 text-muted">Total Internships</div>
                                                <h6 class="fw-bold text-dark"><?php echo intval($internship_count); ?></h6>
                                            </div>
                                            <div class="text-nowrap"><span class="badge bg-dark text-white">Total</span></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 hstack gap-2 rounded border border-dashed border-gray-5">
                                            <div class="flex-grow-1">
                                                <div class="fs-12 text-muted">Avg Completion</div>
                                                <h6 class="fw-bold text-dark"><?php echo htmlspecialchars($avg_completion_percentage); ?>%</h6>
                                            </div>
                                            <div class="text-nowrap"><span class="badge bg-soft-info text-info">Metric</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [OJT Overview] end -->
                    <!-- [Latest Attendance Records] start -->
                    <div class="col-xxl-8">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Latest Attendance Records</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
                                            <div data-bs-toggle="tooltip" title="Options">
                                                <i class="feather-more-vertical"></i>
                                            </div>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-at-sign"></i>New</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-calendar"></i>Event</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-bell"></i>Snoozed</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-trash-2"></i>Deleted</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-settings"></i>Settings</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-life-buoy"></i>Tips & Tricks</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body custom-card-action p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr class="border-b">
                                                <th scope="row">Students</th>
                                                <th>Attendance Date</th>
                                                <th>Time In</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($recent_attendance) > 0): ?>
                                                <?php foreach ($recent_attendance as $attendance): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="avatar-text avatar-sm bg-soft-primary text-primary">
                                                            <?php echo strtoupper(substr($attendance['first_name'], 0, 1) . substr($attendance['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <a href="students-view.php?id=<?php echo $attendance['student_id']; ?>">
                                                            <span class="d-block fw-semibold"><?php echo htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']); ?></span>
                                                            <span class="fs-12 d-block fw-normal text-muted"><?php echo htmlspecialchars($attendance['student_num']); ?></span>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo date('m/d/Y', strtotime($attendance['attendance_date'])); ?>
                                                </td>
                                                <td>
                                                    <?php echo $attendance['morning_time_in'] ? date('h:i a', strtotime($attendance['morning_time_in'])) : 'N/A'; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status = $attendance['status'];
                                                    if ($status === 'approved') {
                                                        echo '<span class="badge bg-soft-success text-success">Approved</span>';
                                                    } elseif ($status === 'pending') {
                                                        echo '<span class="badge bg-soft-warning text-warning">Pending</span>';
                                                    } else {
                                                        echo '<span class="badge bg-soft-danger text-danger">Rejected</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td class="text-end">
                                                    <a href="attendance.php"><i class="feather-more-vertical"></i></a>
                                                </td>
                                            </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">No attendance records found</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer">
                                <ul class="list-unstyled d-flex align-items-center gap-2 mb-0 pagination-common-style">
                                    <li>
                                        <a href="attendance.php"><i class="bi bi-arrow-left"></i></a>
                                    </li>
                                    <li><a href="attendance.php" class="active">1</a></li>
                                    <li><a href="attendance.php">2</a></li>
                                    <li>
                                        <a href="javascript:void(0);"><i class="bi bi-dot"></i></a>
                                    </li>
                                    <li><a href="attendance.php">View All</a></li>
                                    <li>
                                        <a href="attendance.php"><i class="bi bi-arrow-right"></i></a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <!-- [Latest Attendance Records] end -->
                    <!--! BEGIN: [Biometric Registration Status] !-->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Biometric Registration Status</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
                                            <div data-bs-toggle="tooltip" title="Options">
                                                <i class="feather-more-vertical"></i>
                                            </div>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-at-sign"></i>New</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-calendar"></i>Event</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-bell"></i>Snoozed</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-trash-2"></i>Deleted</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-settings"></i>Settings</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-life-buoy"></i>Tips & Tricks</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="p-3 border border-dashed rounded-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="wd-50 ht-50 bg-soft-success text-success lh-1 d-flex align-items-center justify-content-center flex-column rounded-2">
                                                <span class="fs-18 fw-bold mb-1 d-block"><?php echo $biometric_registered; ?></span>
                                                <span class="fs-10 fw-semibold text-uppercase d-block">Registered</span>
                                            </div>
                                            <div class="text-dark">
                                                <a href="demo-biometric.php" class="fw-bold mb-2 text-truncate-1-line">Students Registered</a>
                                                <span class="fs-11 fw-normal text-muted text-truncate-1-line"><?php echo ($student_count > 0) ? round(($biometric_registered / $student_count) * 100) : 0; ?>% of total</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3 border border-dashed rounded-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="wd-50 ht-50 bg-soft-warning text-warning lh-1 d-flex align-items-center justify-content-center flex-column rounded-2">
                                                <span class="fs-18 fw-bold mb-1 d-block"><?php echo ($student_count - $biometric_registered); ?></span>
                                                <span class="fs-10 fw-semibold text-uppercase d-block">Pending</span>
                                            </div>
                                            <div class="text-dark">
                                                <a href="demo-biometric.php" class="fw-bold mb-2 text-truncate-1-line">Awaiting Registration</a>
                                                <span class="fs-11 fw-normal text-muted text-truncate-1-line"><?php echo ($student_count > 0) ? round((($student_count - $biometric_registered) / $student_count) * 100) : 0; ?>% pending</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mb-3 ht-5">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo ($student_count > 0) ? round(($biometric_registered / $student_count) * 100) : 0; ?>%"></div>
                                </div>
                                <p class="text-muted text-center fs-12 mb-0">Overall Biometric Registration Progress</p>
                            </div>
                            <a href="demo-biometric.php" class="card-footer fs-11 fw-bold text-uppercase text-center py-4">Manage Biometric</a>
                        </div>
                    </div>
                    <!--! END: [Biometric Registration Status] !-->
                    <!--! BEGIN: [Project Status] !-->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Project Status</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
                                            <div data-bs-toggle="tooltip" title="Options">
                                                <i class="feather-more-vertical"></i>
                                            </div>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-at-sign"></i>New</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-calendar"></i>Event</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-bell"></i>Snoozed</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-trash-2"></i>Deleted</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-settings"></i>Settings</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-life-buoy"></i>Tips & Tricks</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body custom-card-action">
                                <div class="mb-3">
                                    <div class="mb-4 pb-1 d-flex">
                                        <div class="d-flex w-50 align-items-center me-3">
                                            <img src="assets/images/brand/app-store.png" alt="laravel-logo" class="me-3" width="35" />
                                            <div>
                                                <a href="javascript:void(0);" class="text-truncate-1-line">Apps Development</a>
                                                <div class="fs-11 text-muted">Applications</div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-grow-1 align-items-center">
                                            <div class="progress w-100 me-3 ht-5">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: 54%" aria-valuenow="54" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <span class="text-muted">54%</span>
                                        </div>
                                    </div>
                                    <hr class="border-dashed my-3" />
                                    <div class="mb-4 pb-1 d-flex">
                                        <div class="d-flex w-50 align-items-center me-3">
                                            <img src="assets/images/brand/figma.png" alt="figma-logo" class="me-3" width="35" />
                                            <div>
                                                <a href="javascript:void(0);" class="text-truncate-1-line">Dashboard Design</a>
                                                <div class="fs-11 text-muted">App UI Kit</div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-grow-1 align-items-center">
                                            <div class="progress w-100 me-3 ht-5">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: 86%" aria-valuenow="86" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <span class="text-muted">86%</span>
                                        </div>
                                    </div>
                                    <hr class="border-dashed my-3" />
                                    <div class="mb-4 pb-1 d-flex">
                                        <div class="d-flex w-50 align-items-center me-3">
                                            <img src="assets/images/brand/facebook.png" alt="vue-logo" class="me-3" width="35" />
                                            <div>
                                                <a href="javascript:void(0);" class="text-truncate-1-line">Facebook Marketing</a>
                                                <div class="fs-11 text-muted">Marketing</div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-grow-1 align-items-center">
                                            <div class="progress w-100 me-3 ht-5">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: 90%" aria-valuenow="90" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <span class="text-muted">90%</span>
                                        </div>
                                    </div>
                                    <hr class="border-dashed my-3" />
                                    <div class="mb-4 pb-1 d-flex">
                                        <div class="d-flex w-50 align-items-center me-3">
                                            <img src="assets/images/brand/github.png" alt="react-logo" class="me-3" width="35" />
                                            <div>
                                                <a href="javascript:void(0);" class="text-truncate-1-line">React Dashboard Github</a>
                                                <div class="fs-11 text-muted">Dashboard</div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-grow-1 align-items-center">
                                            <div class="progress w-100 me-3 ht-5">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: 37%" aria-valuenow="37" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <span class="text-muted">37%</span>
                                        </div>
                                    </div>
                                    <hr class="border-dashed my-3" />
                                    <div class="d-flex">
                                        <div class="d-flex w-50 align-items-center me-3">
                                            <img src="assets/images/brand/paypal.png" alt="sketch-logo" class="me-3" width="35" />
                                            <div>
                                                <a href="javascript:void(0);" class="text-truncate-1-line">Paypal Payment Gateway</a>
                                                <div class="fs-11 text-muted">Payment</div>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-grow-1 align-items-center">
                                            <div class="progress w-100 me-3 ht-5">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: 29%" aria-valuenow="29" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <span class="text-muted">29%</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <a href="javascript:void(0);" class="card-footer fs-11 fw-bold text-uppercase text-center">Upcomming Projects</a>
                        </div>
                    </div>
                    <!--! END: [Project Status] !-->
                    <!--! BEGIN: [Admin Quick Actions] !-->
                    <?php // Admin Quick Actions: always visible for this deployment ?>
                    <div class="col-xxl-6">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Admin Quick Actions</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php
                                $qa_total_students = 0;
                                $qa_total_internships = 0;
                                $qa_attendance_today = 0;
                                $qa_biometric_registered = 0;
                                if (isset($conn)) {
                                    function _safe_count($conn, $table, $where = '1') {
                                        $safe = 0;
                                        $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
                                        if ($res && $res->num_rows > 0) {
                                            $q = $conn->query("SELECT COUNT(*) AS cnt FROM `" . $conn->real_escape_string($table) . "` WHERE {$where}");
                                            if ($q) {
                                                $r = $q->fetch_assoc();
                                                $safe = (int)
                                                ($r['cnt'] ?? 0);
                                            }
                                        }
                                        return $safe;
                                    }
                                    $qa_total_students = _safe_count($conn, 'students', '1');
                                    $qa_total_internships = _safe_count($conn, 'internships', '1');
                                    // try common attendance date columns safely
                                    $qa_attendance_today = 0;
                                    $res = $conn->query("SHOW COLUMNS FROM `attendances` LIKE 'date'");
                                    if ($res && $res->num_rows > 0) {
                                        $qa_attendance_today = _safe_count($conn, 'attendances', 'date = CURDATE()');
                                    } else {
                                        $res2 = $conn->query("SHOW COLUMNS FROM `attendances` LIKE 'log_time'");
                                        if ($res2 && $res2->num_rows > 0) {
                                            $qa_attendance_today = _safe_count($conn, 'attendances', 'DATE(log_time) = CURDATE()');
                                        }
                                    }
                                    $res3 = $conn->query("SHOW COLUMNS FROM `students` LIKE 'biometric_registered'");
                                    if ($res3 && $res3->num_rows > 0) {
                                        $qa_biometric_registered = _safe_count($conn, 'students', 'biometric_registered = 1');
                                    }
                                }
                                ?>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <a href="students.php" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center">
                                            <i class="feather-users me-2"></i> Students
                                            <span class="badge bg-white text-dark ms-3"><?php echo $qa_total_students; ?></span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="students-edit.php" class="btn btn-success btn-lg w-100 d-flex align-items-center justify-content-center">
                                            <i class="feather-plus-circle me-2"></i> Add Student
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="ojt.php" class="btn btn-info btn-lg w-100 d-flex align-items-center justify-content-center">
                                            <i class="feather-briefcase me-2"></i> OJT List
                                            <span class="badge bg-white text-dark ms-3"><?php echo $qa_total_internships; ?></span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="attendance.php" class="btn btn-warning btn-lg w-100 d-flex align-items-center justify-content-center">
                                            <i class="feather-calendar me-2"></i> Attendance Today
                                            <span class="badge bg-white text-dark ms-3"><?php echo $qa_attendance_today; ?></span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="demo-biometric.php" class="btn btn-secondary btn-lg w-100 d-flex align-items-center justify-content-center">
                                            <i class="feather-activity me-2"></i> Biometric Demo
                                            <span class="badge bg-white text-dark ms-3"><?php echo $qa_biometric_registered; ?></span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="reports-timesheets.php" class="btn btn-dark btn-lg w-100 d-flex align-items-center justify-content-center">
                                            <i class="feather-file-text me-2"></i> Reports
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <a href="javascript:void(0);" class="card-footer fs-11 fw-bold text-uppercase text-center">Admin quick actions</a>
                        </div>
                    </div>
                    
                    <!--! END: [Admin Quick Actions] !-->
                    <!--! BEGIN: [Coordinators List] !-->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Coordinators</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body custom-card-action">
                                <?php if (count($coordinators) > 0): ?>
                                    <?php foreach ($coordinators as $coordinator): ?>
                                    <div class="hstack justify-content-between border border-dashed rounded-3 p-3 mb-3">
                                        <div class="hstack gap-3">
                                            <div class="avatar-text avatar-lg bg-soft-primary text-primary">
                                                <?php echo strtoupper(substr($coordinator['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="fw-semibold"><?php echo htmlspecialchars($coordinator['name']); ?></a>
                                                <div class="fs-11 text-muted"><?php echo htmlspecialchars($coordinator['email']); ?></div>
                                            </div>
                                        </div>
                                        <span class="badge bg-soft-info text-info fs-10">Coordinator</span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">No coordinators found</p>
                                <?php endif; ?>
                            </div>
                            <a href="javascript:void(0);" class="card-footer fs-11 fw-bold text-uppercase text-center py-3">View All Coordinators</a>
                        </div>
                    </div>
                    <!--! END: [Coordinators List] !-->
                    <!--! BEGIN: [Supervisors List] !-->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Supervisors</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="javascript:void(0);" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body custom-card-action">
                                <?php if (count($supervisors) > 0): ?>
                                    <?php foreach ($supervisors as $supervisor): ?>
                                    <div class="hstack justify-content-between border border-dashed rounded-3 p-3 mb-3">
                                        <div class="hstack gap-3">
                                            <div class="avatar-text avatar-lg bg-soft-success text-success">
                                                <?php echo strtoupper(substr($supervisor['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="fw-semibold"><?php echo htmlspecialchars($supervisor['name']); ?></a>
                                                <div class="fs-11 text-muted"><?php echo htmlspecialchars($supervisor['email']); ?></div>
                                            </div>
                                        </div>
                                        <span class="badge bg-soft-success text-success fs-10">Supervisor</span>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">No supervisors found</p>
                                <?php endif; ?>
                            </div>
                            <a href="javascript:void(0);" class="card-footer fs-11 fw-bold text-uppercase text-center py-3">View All Supervisors</a>
                        </div>
                    </div>
                    <!--! END: [Supervisors List] !-->
                    <!-- Duplicate Recent Activities removed (now shown in the top section) -->
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
            <p><span>By: <a target="_blank" href="" target="_blank">ACT 2A</a></span> <span>Distributed by: <a target="_blank" href="" target="_blank">Group 5</a></span></p>
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
    <!--! ================================================================ !-->
    <!--! BEGIN: Theme Customizer !-->
    <!--! ================================================================ !-->
    <div class="theme-customizer">
        <div class="customizer-handle">
            <a href="javascript:void(0);" class="cutomizer-open-trigger bg-primary">
                <i class="feather-settings"></i>
            </a>
        </div>
        <div class="customizer-sidebar-wrapper">
            <div class="customizer-sidebar-header px-4 ht-80 border-bottom d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Theme Settings</h5>
                <a href="javascript:void(0);" class="cutomizer-close-trigger d-flex">
                    <i class="feather-x"></i>
                </a>
            </div>
            <div class="customizer-sidebar-body position-relative p-4" data-scrollbar-target="#psScrollbarInit">
                <!--! BEGIN: [Navigation] !-->
                <div class="position-relative px-3 pb-3 pt-4 mt-3 mb-5 border border-gray-2 theme-options-set">
                    <label class="py-1 px-2 fs-8 fw-bold text-uppercase text-muted text-spacing-2 bg-white border border-gray-2 position-absolute rounded-2 options-label" style="top: -12px">Navigation</label>
                    <div class="row g-2 theme-options-items app-navigation" id="appNavigationList">
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-navigation-light" name="app-navigation" value="1" data-app-navigation="app-navigation-light" checked />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-navigation-light">Light</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-navigation-dark" name="app-navigation" value="2" data-app-navigation="app-navigation-dark" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-navigation-dark">Dark</label>
                        </div>
                    </div>
                </div>
                <!--! END: [Navigation] !-->
                <!--! BEGIN: [Header] !-->
                <div class="position-relative px-3 pb-3 pt-4 mt-3 mb-5 border border-gray-2 theme-options-set mt-5">
                    <label class="py-1 px-2 fs-8 fw-bold text-uppercase text-muted text-spacing-2 bg-white border border-gray-2 position-absolute rounded-2 options-label" style="top: -12px">Header</label>
                    <div class="row g-2 theme-options-items app-header" id="appHeaderList">
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-header-light" name="app-header" value="1" data-app-header="app-header-light" checked />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-header-light">Light</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-header-dark" name="app-header" value="2" data-app-header="app-header-dark" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-header-dark">Dark</label>
                        </div>
                    </div>
                </div>
                <!--! END: [Header] !-->
                <!--! BEGIN: [Skins] !-->
                <div class="position-relative px-3 pb-3 pt-4 mt-3 mb-5 border border-gray-2 theme-options-set">
                    <label class="py-1 px-2 fs-8 fw-bold text-uppercase text-muted text-spacing-2 bg-white border border-gray-2 position-absolute rounded-2 options-label" style="top: -12px">Skins</label>
                    <div class="row g-2 theme-options-items app-skin" id="appSkinList">
                        <div class="col-6 text-center position-relative single-option light-button active">
                            <input type="radio" class="btn-check" id="app-skin-light" name="app-skin" value="1" data-app-skin="app-skin-light" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-skin-light">Light</label>
                        </div>
                        <div class="col-6 text-center position-relative single-option dark-button">
                            <input type="radio" class="btn-check" id="app-skin-dark" name="app-skin" value="2" data-app-skin="app-skin-dark" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-skin-dark">Dark</label>
                        </div>
                    </div>
                </div>
                <!--! END: [Skins] !-->
                <!--! BEGIN: [Typography] !-->
                <div class="position-relative px-3 pb-3 pt-4 mt-3 mb-0 border border-gray-2 theme-options-set">
                    <label class="py-1 px-2 fs-8 fw-bold text-uppercase text-muted text-spacing-2 bg-white border border-gray-2 position-absolute rounded-2 options-label" style="top: -12px">Typography</label>
                    <div class="row g-2 theme-options-items font-family" id="fontFamilyList">
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-lato" name="font-family" value="1" data-font-family="app-font-family-lato" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-lato">Lato</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-rubik" name="font-family" value="2" data-font-family="app-font-family-rubik" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-rubik">Rubik</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-inter" name="font-family" value="3" data-font-family="app-font-family-inter" checked />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-inter">Inter</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-cinzel" name="font-family" value="4" data-font-family="app-font-family-cinzel" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-cinzel">Cinzel</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-nunito" name="font-family" value="6" data-font-family="app-font-family-nunito" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-nunito">Nunito</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-roboto" name="font-family" value="7" data-font-family="app-font-family-roboto" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-roboto">Roboto</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-ubuntu" name="font-family" value="8" data-font-family="app-font-family-ubuntu" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-ubuntu">Ubuntu</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-poppins" name="font-family" value="9" data-font-family="app-font-family-poppins" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-poppins">Poppins</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-raleway" name="font-family" value="10" data-font-family="app-font-family-raleway" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-raleway">Raleway</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-system-ui" name="font-family" value="11" data-font-family="app-font-family-system-ui" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-system-ui">System UI</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-noto-sans" name="font-family" value="12" data-font-family="app-font-family-noto-sans" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-noto-sans">Noto Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-fira-sans" name="font-family" value="13" data-font-family="app-font-family-fira-sans" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-fira-sans">Fira Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-work-sans" name="font-family" value="14" data-font-family="app-font-family-work-sans" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-work-sans">Work Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-open-sans" name="font-family" value="15" data-font-family="app-font-family-open-sans" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-open-sans">Open Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-maven-pro" name="font-family" value="16" data-font-family="app-font-family-maven-pro" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-maven-pro">Maven Pro</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-quicksand" name="font-family" value="17" data-font-family="app-font-family-quicksand" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-quicksand">Quicksand</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-montserrat" name="font-family" value="18" data-font-family="app-font-family-montserrat" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-montserrat">Montserrat</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-josefin-sans" name="font-family" value="19" data-font-family="app-font-family-josefin-sans" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-josefin-sans">Josefin Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-ibm-plex-sans" name="font-family" value="20" data-font-family="app-font-family-ibm-plex-sans" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-ibm-plex-sans">IBM Plex Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-source-sans-pro" name="font-family" value="5" data-font-family="app-font-family-source-sans-pro" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-source-sans-pro">Source Sans Pro</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-montserrat-alt" name="font-family" value="21" data-font-family="app-font-family-montserrat-alt" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-montserrat-alt">Montserrat Alt</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-roboto-slab" name="font-family" value="22" data-font-family="app-font-family-roboto-slab" />
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-roboto-slab">Roboto Slab</label>
                        </div>
                    </div>
                </div>
                <!--! END: [Typography] !-->
            </div>
            <div class="customizer-sidebar-footer px-4 ht-60 border-top d-flex align-items-center gap-2">
                <div class="flex-fill w-50">
                    <a href="javascript:void(0);" class="btn btn-danger" data-style="reset-all-common-style">Reset</a>
                </div>
                <div class="flex-fill w-50">
                    <a href="https://www.themewagon.com/themes/Duralux-admin" target="_blank" class="btn btn-primary">Download</a>
                </div>
            </div>
        </div>
    </div>
    <!--! ================================================================ !-->
    <!--! [End] Theme Customizer !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="assets/vendors/js/daterangepicker.min.js"></script>
    <script src="assets/vendors/js/apexcharts.min.js"></script>
    <script src="assets/vendors/js/circle-progress.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/dashboard-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!--! BEGIN: Theme Customizer  !-->
    <script src="assets/js/theme-customizer-init.min.js"></script>
    <!--! END: Theme Customizer !-->
</body>

</html>

