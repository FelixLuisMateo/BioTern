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

// Get student ID from URL parameter
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id == 0) {
    die("Invalid student ID");
}

// Fetch Student Details
$student_query = "
    SELECT 
        s.id,
        s.student_id,
        s.profile_picture,
        s.first_name,
        s.last_name,
        s.middle_name,
        s.email,
        s.phone,
        s.date_of_birth,
        s.gender,
        s.address,
        s.emergency_contact,
        s.status,
        s.biometric_registered,
        s.biometric_registered_at,
        s.created_at,
        s.total_hours,
        s.supervisor_name,
        s.coordinator_name,
        c.name as course_name,
        c.id as course_id,
        i.id as internship_id,
        i.supervisor_id,
        i.coordinator_id,
        i.rendered_hours,
        i.required_hours,
        i.status as internship_status
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
    WHERE s.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Student not found");
}

$student = $result->fetch_assoc();

// Check if student is active today (has any attendance record for today)
$today = date('Y-m-d');
$active_today_query = "
    SELECT COUNT(*) as count 
    FROM attendances 
    WHERE student_id = ? AND attendance_date = ?
";
$stmt_active = $conn->prepare($active_today_query);
$stmt_active->bind_param("is", $student_id, $today);
$stmt_active->execute();
$active_result = $stmt_active->get_result();
$active_row = $active_result->fetch_assoc();
$is_active_today = $active_row['count'] > 0 ? true : false;

// Check if student is currently clocked in (has morning_time_in but no morning_time_out)
$clocked_in_query = "
    SELECT 
        id,
        morning_time_in,
        morning_time_out,
        afternoon_time_in,
        afternoon_time_out
    FROM attendances 
    WHERE student_id = ? AND attendance_date = ?
    LIMIT 1
";
$stmt_clock = $conn->prepare($clocked_in_query);
$stmt_clock->bind_param("is", $student_id, $today);
$stmt_clock->execute();
$clock_result = $stmt_clock->get_result();
$attendance_record = $clock_result->fetch_assoc();

// Determine if student is currently clocked in
$is_clocked_in = false;
if ($attendance_record) {
    $morning_in = $attendance_record['morning_time_in'];
    $morning_out = $attendance_record['morning_time_out'];
    $afternoon_in = $attendance_record['afternoon_time_in'];
    $afternoon_out = $attendance_record['afternoon_time_out'];
    
    // Student is clocked in if:
    // - Morning clock in exists but no clock out, OR
    // - Afternoon clock in exists but no afternoon clock out
    if (($morning_in && !$morning_out) || ($afternoon_in && !$afternoon_out)) {
        $is_clocked_in = true;
    }
}

// Calculate hours remaining and completion percentage
$hours_rendered = $student['rendered_hours'] ?? 0;
$total_hours = $student['total_hours'] ?? 600;
$hours_remaining = max(0, $total_hours - $hours_rendered);
$completion_percentage = ($hours_rendered / $total_hours) * 100;

// Fetch Attendance Records for activity
$activity_query = "
    SELECT 
        att.id,
        att.attendance_date as date,
        att.morning_time_in,
        att.morning_time_out,
        att.break_time_in,
        att.break_time_out,
        att.afternoon_time_in,
        att.afternoon_time_out,
        att.total_hours,
        att.status,
        att.created_at
    FROM attendances att
    WHERE att.student_id = ?
    ORDER BY att.attendance_date DESC
    LIMIT 10
";

$stmt_activity = $conn->prepare($activity_query);
$stmt_activity->bind_param("i", $student_id);
$stmt_activity->execute();
$activity_result = $stmt_activity->get_result();
$activities = [];
while ($row = $activity_result->fetch_assoc()) {
    $activities[] = $row;
}

// Helper functions
function formatDate($date) {
    if ($date) {
        return date('M d, Y', strtotime($date));
    }
    return 'N/A';
}

function formatDateTime($date) {
    if ($date) {
        return date('M d, Y h:i A', strtotime($date));
    }
    return 'N/A';
}

function getStatusBadge($status) {
    if ($status == 1 || $status == 'ongoing') {
        return '<span class="badge bg-soft-success text-success">Active</span>';
    } elseif ($status == 'approved') {
        return '<span class="badge bg-soft-success text-success">Approved</span>';
    } elseif ($status == 'pending') {
        return '<span class="badge bg-soft-warning text-warning">Pending</span>';
    } elseif ($status == 'rejected') {
        return '<span class="badge bg-soft-danger text-danger">Rejected</span>';
    } else {
        return '<span class="badge bg-soft-danger text-danger">Inactive</span>';
    }
}

function getActivityTypeClass($status) {
    $status = strtolower($status);
    if ($status == 'approved') {
        return 'feed-item-success';
    } elseif ($status == 'pending') {
        return 'feed-item-warning';
    } elseif ($status == 'rejected') {
        return 'feed-item-danger';
    }
    return 'feed-item-info';
}

function formatTimeRange($time_in, $time_out) {
    if ($time_in && $time_out) {
        return date('h:i A', strtotime($time_in)) . ' - ' . date('h:i A', strtotime($time_out));
    }
    return '-';
}

function calculateTotalHours($morning_in, $morning_out, $break_in, $break_out, $afternoon_in, $afternoon_out) {
    $total = 0;
    
    // Morning hours
    if ($morning_in && $morning_out) {
        $morning_time = strtotime($morning_out) - strtotime($morning_in);
        $total += $morning_time / 3600;
    }
    
    // Afternoon hours
    if ($afternoon_in && $afternoon_out) {
        $afternoon_time = strtotime($afternoon_out) - strtotime($afternoon_in);
        $total += $afternoon_time / 3600;
    }
    
    // Subtract break time
    if ($break_in && $break_out) {
        $break_time = strtotime($break_out) - strtotime($break_in);
        $total -= $break_time / 3600;
    }
    
    return round(max(0, $total), 2);
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
    <title>BioTern || Student Profile - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
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
            color: #333;
            background-color: #ffffff;
        }
        
        /* Dark mode support for Select2 */
        body.dark .select2-container--default .select2-selection--single,
        body.dark .select2-container--default .select2-selection--multiple,
        body[data-bs-theme="dark"] .select2-container--default .select2-selection--single,
        body[data-bs-theme="dark"] .select2-container--default .select2-selection--multiple {
            color: #f0f0f0;
            background-color: #2d3748;
            border-color: #4a5568 !important;
        }
        
        body.dark .select2-container--default .select2-selection--single .select2-selection__rendered,
        body[data-bs-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f0f0f0;
        }
        
        /* Dark mode dropdown menu */
        body.dark .select2-container--default.select2-container--open .select2-dropdown,
        body[data-bs-theme="dark"] .select2-container--default.select2-container--open .select2-dropdown {
            background-color: #2d3748;
            border-color: #4a5568;
        }
        
        body.dark .select2-results__option,
        body[data-bs-theme="dark"] .select2-results__option {
            color: #f0f0f0;
            background-color: #2d3748;
        }
        
        body.dark .select2-results__option--highlighted[aria-selected],
        body[data-bs-theme="dark"] .select2-results__option--highlighted[aria-selected] {
            background-color: #667eea;
            color: #ffffff;
        }
        
        body.dark .select2-container--default select.form-control,
        body.dark select.form-control,
        body.dark select.form-select,
        body[data-bs-theme="dark"] select.form-control,
        body[data-bs-theme="dark"] select.form-select {
            color: #f0f0f0;
            background-color: #2d3748;
            border-color: #4a5568;
        }
        
        body.dark select.form-control option,
        body.dark select.form-select option,
        body[data-bs-theme="dark"] select.form-control option,
        body[data-bs-theme="dark"] select.form-select option {
            color: #f0f0f0;
            background-color: #2d3748;
        }
    </style>
</head>

<body>
    <!--! Navigation !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="index.html" class="b-brand">
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
                            <li class="nxl-item"><a class="nxl-link" href="settings-students.php">Students</a></li>
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
                                    <li class="nxl-item"><a class="nxl-link" href="register_submit.php">Creative</a></li>
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

    <!--! Header !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
            </div>
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
                                        <h6 class="text-dark mb-0">Felix Luis Mateo</h6>
                                        <span class="fs-12 fw-medium text-muted">felixluismateo@example.com</span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
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
        </div>
    </header>

    <!--! Main Content !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Student Profile</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item">View</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            <a href="javascript:void(0);" class="btn btn-icon btn-light-brand successAlertMessage">
                                <i class="feather-star"></i>
                            </a>
                            <a href="javascript:void(0);" class="btn btn-icon btn-light-brand">
                                <i class="feather-eye me-2"></i>
                                <span>Follow</span>
                            </a>
                            <a href="students.php" class="btn btn-primary">
                                <i class="feather-arrow-left me-2"></i>
                                <span>Back to List</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="row">
                    <!-- Student Card Left Side -->
                    <div class="col-xxl-4 col-xl-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body">
                                <div class="mb-4 text-center">
                                    <div class="wd-150 ht-150 mx-auto mb-3 position-relative">
                                        <div class="avatar-image wd-150 ht-150 border border-5 border-gray-3">
                                            <?php if (!empty($student['profile_picture']) && file_exists(__DIR__ . '/' . $student['profile_picture'])): ?>
                                                <img src="<?php echo htmlspecialchars($student['profile_picture']) . '?v=' . filemtime(__DIR__ . '/' . $student['profile_picture']); ?>" alt="Profile" class="img-fluid">
                                            <?php else: ?>
                                                <img src="assets/images/avatar/<?php echo ($student['id'] % 5) + 1; ?>.png" alt="" class="img-fluid">
                                            <?php endif; ?>
                                        </div>
                                        <div class="wd-10 ht-10 text-success rounded-circle position-absolute translate-middle" style="top: 76%; right: 10px">
                                            <i class="bi bi-patch-check-fill"></i>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <a href="javascript:void(0);" class="fs-14 fw-bold d-block"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></a>
                                        <a href="javascript:void(0);" class="fs-12 fw-normal text-muted d-block"><?php echo htmlspecialchars($student['email']); ?></a>
                                    </div>
                                    <div class="fs-12 fw-normal text-muted text-center d-flex flex-wrap gap-3 mb-4">
                                        <div class="flex-fill py-3 px-4 rounded-1 d-none d-sm-block border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder" id="hoursRemaining">
                                                <?php 
                                                $hours = floor($hours_remaining);
                                                $mins = floor(($hours_remaining - $hours) * 60);
                                                echo $hours . 'h:' . str_pad($mins, 2, '0', STR_PAD_LEFT) . 'm:00s';
                                                ?>
                                            </h6>
                                            <p class="fs-12 text-muted mb-0">Hours Remaining</p>
                                        </div>
                                        <div class="flex-fill py-3 px-4 rounded-1 d-none d-sm-block border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder"><?php echo intval($hours_rendered); ?>/<?php echo intval($total_hours); ?></h6>
                                            <p class="fs-12 text-muted mb-0">Hours Total</p>
                                        </div>
                                        <div class="flex-fill py-3 px-4 rounded-1 d-none d-sm-block border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder"><?php echo number_format($completion_percentage, 2); ?>%</h6>
                                            <p class="fs-12 text-muted mb-0">Completion</p>
                                        </div>
                                    </div>
                                    <?php if ($is_active_today): ?>
                                        <div class="alert alert-soft-success-message p-2 mb-3" role="alert">
                                            <i class="feather-check-circle me-2"></i>
                                            <span class="fs-12">Student is active today</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-soft-warning-message p-2 mb-3" role="alert">
                                            <i class="feather-alert-circle me-2"></i>
                                            <span class="fs-12">Student is not active today</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <ul class="list-unstyled mb-4">
                                    <li class="hstack justify-content-between mb-4">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-map-pin"></i>Location</span>
                                        <a href="javascript:void(0);" class="float-end"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></a>
                                    </li>
                                    <li class="hstack justify-content-between mb-4">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-phone"></i>Mobile Phone</span>
                                        <a href="javascript:void(0);" class="float-end"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></a>
                                    </li>
                                    <li class="hstack justify-content-between mb-0">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-mail"></i>Email</span>
                                        <a href="javascript:void(0);" class="float-end"><?php echo htmlspecialchars($student['email']); ?></a>
                                    </li>
                                </ul>
                                <div class="d-flex gap-2 text-center pt-4">
                                    <a href="javascript:void(0);" class="w-50 btn btn-light-brand">
                                        <i class="feather-trash-2 me-2"></i>
                                        <span>Delete</span>
                                    </a>
                                    <a href="students-edit.php?id=<?php echo $student['id']; ?>" class="w-50 btn btn-primary">
                                        <i class="feather-edit me-2"></i>
                                        <span>Edit Profile</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Information Right Side -->
                    <div class="col-xxl-8 col-xl-6">
                        <div class="card border-top-0">
                            <div class="card-header p-0">
                                <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="myTab" role="tablist">
                                    <li class="nav-item flex-fill border-top" role="presentation">
                                        <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab" data-bs-target="#overviewTab" role="tab">Overview</a>
                                    </li>
                                    <li class="nav-item flex-fill border-top" role="presentation">
                                        <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#activityTab" role="tab">Attendance</a>
                                    </li>
                                    <li class="nav-item flex-fill border-top" role="presentation">
                                        <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#evaluationTab" role="tab">Evaluation</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="tab-content">
                                <!-- Overview Tab -->
                                <div class="tab-pane fade show active p-4" id="overviewTab" role="tabpanel">
                                    <div class="profile-details mb-5">
                                        <div class="mb-4 d-flex align-items-center justify-content-between">
                                            <h5 class="fw-bold mb-0">Profile Details:</h5>
                                            <a href="students-edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-light-brand">Edit Profile</a>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">First Name:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['first_name']); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Middle Name:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['middle_name'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Last Name:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['last_name']); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Student ID:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['student_id']); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Course:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Email Address:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['email']); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Date of Birth:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo formatDate($student['date_of_birth']); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Mobile Number:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Supervisor:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['supervisor_name'] ?? 'Not Assigned'); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Coordinator:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['coordinator_name'] ?? 'Not Assigned'); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Home Address:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Date Registered:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo formatDate($student['created_at']); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Date Fingerprint Registered:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo formatDate($student['biometric_registered_at']); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Gender:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars(ucfirst($student['gender'] ?? 'N/A')); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Emergency Contact:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo htmlspecialchars($student['emergency_contact'] ?? 'N/A'); ?></div>
                                        </div>
                                        <div class="row g-0 mb-4">
                                            <div class="col-sm-6 text-muted">Status:</div>
                                            <div class="col-sm-6 fw-semibold"><?php echo getStatusBadge($student['internship_status']); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Attendance Tab -->
                                <div class="tab-pane fade" id="activityTab" role="tabpanel">
                                    <div class="recent-activity p-4 pb-0">
                                        <div class="mb-4 pb-2 d-flex justify-content-between">
                                            <h5 class="fw-bold">Recent Attendance Records:</h5>
                                            <a href="javascript:void(0);" class="btn btn-sm btn-light-brand">View All</a>
                                        </div>
                                        <ul class="list-unstyled activity-feed">
                                            <?php if (count($activities) > 0): ?>
                                                <?php foreach ($activities as $activity): ?>
                                                    <?php 
                                                    $total_hours = !empty($activity['total_hours']) ? $activity['total_hours'] : calculateTotalHours(
                                                        $activity['morning_time_in'],
                                                        $activity['morning_time_out'],
                                                        $activity['break_time_in'],
                                                        $activity['break_time_out'],
                                                        $activity['afternoon_time_in'],
                                                        $activity['afternoon_time_out']
                                                    );
                                                    ?>
                                                    <li class="d-flex justify-content-between feed-item <?php echo getActivityTypeClass($activity['status']); ?>">
                                                        <div>
                                                            <span class="text-truncate-1-line lead_date">
                                                                Attendance for <?php echo date('M d, Y', strtotime($activity['date'])); ?>
                                                                <span class="date">[<?php echo formatDateTime($activity['created_at']); ?>]</span>
                                                            </span>
                                                            <span class="text">
                                                                Morning: <a href="javascript:void(0);" class="fw-bold text-primary"><?php echo formatTimeRange($activity['morning_time_in'], $activity['morning_time_out']); ?></a>
                                                                &nbsp;|&nbsp;
                                                                Afternoon: <a href="javascript:void(0);" class="fw-bold text-primary"><?php echo formatTimeRange($activity['afternoon_time_in'], $activity['afternoon_time_out']); ?></a>
                                                                &nbsp;|&nbsp;
                                                                Total: <strong><?php echo $total_hours; ?> hrs</strong>
                                                            </span>
                                                        </div>
                                                        <div class="ms-3 d-flex gap-2 align-items-center">
                                                            <?php echo getStatusBadge($activity['status']); ?>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li class="text-center py-4">
                                                    <p class="text-muted">No attendance records found</p>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Evaluation Tab -->
                                <div class="tab-pane fade" id="evaluationTab" role="tabpanel">
                                    <div class="p-4">
                                        <div class="mb-4 d-flex align-items-center justify-content-between">
                                            <h5 class="fw-bold mb-0">Supervisor Evaluation:</h5>
                                            <span class="badge bg-soft-info text-info">Not Yet Evaluated</span>
                                        </div>
                                        <div class="alert alert-dismissible alert-soft-info-message p-4 mb-4" role="alert">
                                            <div class="d-flex">
                                                <div class="me-3 d-none d-md-block">
                                                    <i class="feather-info fs-1"></i>
                                                </div>
                                                <div>
                                                    <p class="fw-bold mb-1">No evaluation submitted yet</p>
                                                    <p class="fs-12 text-muted mb-0">The supervisor evaluation form is pending. Once submitted, the details will appear here.</p>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        </div>
                                        <div class="text-center py-5">
                                            <i class="feather-inbox fs-1 text-muted mb-3 d-block"></i>
                                            <p class="text-muted">Waiting for supervisor evaluation</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
            <p><span>By: <a target="_blank" href="">ACT 2A</a></span> • <span>Distributed by: <a target="_blank" href="">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </footer>
    </main>

    <!-- Scripts -->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/theme-customizer-init.min.js"></script>

    <script>
        // Timer with localStorage persistence
        function initializeTimer() {
            const studentId = <?php echo $student['id']; ?>;
            const isActiveToday = <?php echo $is_active_today ? 'true' : 'false'; ?>;
            const isClockedIn = <?php echo $is_clocked_in ? 'true' : 'false'; ?>;
            const hoursRemaining = <?php echo $hours_remaining; ?>;
            const timerElement = document.getElementById('hoursRemaining');
            const storageKey = `student_timer_${studentId}`;
            const statusKey = `student_active_${studentId}`;
            
            // Check if student is active today
            if (!isActiveToday) {
                timerElement.textContent = 'Student not active today';
                return;
            }
            
            // Check if student is clocked in
            if (!isClockedIn) {
                timerElement.textContent = 'Student not clocked in';
                return;
            }
            
            // Get stored remaining seconds or initialize
            let storedData = localStorage.getItem(storageKey);
            let remainingSeconds;
            
            if (storedData) {
                const data = JSON.parse(storedData);
                const storedTime = data.timestamp;
                const storedSeconds = data.seconds;
                const currentTime = new Date().getTime();
                const elapsed = (currentTime - storedTime) / 1000;
                remainingSeconds = Math.max(0, storedSeconds - elapsed);
            } else {
                // Initialize with current hours remaining
                remainingSeconds = hoursRemaining * 3600;
                saveTimerState(remainingSeconds, storageKey);
            }
            
            // Update display and save state
            function updateTimer() {
                if (remainingSeconds > 0) {
                    remainingSeconds--;
                    
                    const hours = Math.floor(remainingSeconds / 3600);
                    const minutes = Math.floor((remainingSeconds % 3600) / 60);
                    const seconds = remainingSeconds % 60;
                    
                    timerElement.textContent = 
                        hours + 'h:' + 
                        (minutes < 10 ? '0' : '') + minutes + 'm:' + 
                        (seconds < 10 ? '0' : '') + seconds + 's';
                    
                    // Save state every 10 seconds to avoid excessive storage writes
                    if (remainingSeconds % 10 === 0) {
                        saveTimerState(remainingSeconds, storageKey);
                    }
                } else {
                    timerElement.textContent = '0h:00m:00s';
                    localStorage.removeItem(storageKey);
                }
            }
            
            // Check if student is still clocked in (every 30 seconds)
            function checkClockInStatus() {
                fetch('get_clock_status.php?student_id=' + studentId)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.is_clocked_in) {
                            // Student has clocked out, stop timer
                            timerElement.textContent = 'Student clocked out';
                            clearInterval(timerInterval);
                        }
                    })
                    .catch(error => {
                        // Silently fail on network errors
                        console.log('Clock status check failed');
                    });
            }
            
            // Save timer state to localStorage
            function saveTimerState(seconds, key) {
                const data = {
                    timestamp: new Date().getTime(),
                    seconds: seconds
                };
                localStorage.setItem(key, JSON.stringify(data));
            }
            
            // Clear expired timers (older than 24 hours)
            function clearExpiredTimers() {
                const currentTime = new Date().getTime();
                for (let key in localStorage) {
                    if (key.startsWith('student_timer_')) {
                        try {
                            const data = JSON.parse(localStorage.getItem(key));
                            const age = currentTime - data.timestamp;
                            if (age > 86400000) { // 24 hours in milliseconds
                                localStorage.removeItem(key);
                            }
                        } catch (e) {
                            localStorage.removeItem(key);
                        }
                    }
                }
            }
            
            // Initial display
            updateTimer();
            
            // Update every second
            const timerInterval = setInterval(updateTimer, 1000);
            
            // Check clock in status every 30 seconds
            const clockCheckInterval = setInterval(checkClockInStatus, 30000);
            
            // Clear on page unload
            window.addEventListener('beforeunload', function() {
                saveTimerState(remainingSeconds, storageKey);
                clearExpiredTimers();
            });
            
            // Clear expired timers on init
            clearExpiredTimers();
        }
        
        // Initialize timer when DOM is ready
        document.addEventListener('DOMContentLoaded', initializeTimer);
    </script>
</body>

</html>

<?php
$conn->close();
?>