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
            $message = "Student ID not found!";
            $message_type = "danger";
        } else {
            // Escape values for security
            $clock_date = $conn->real_escape_string($clock_date);
            $clock_time = $conn->real_escape_string($clock_time);

            // Check if attendance record exists for this date
            $date_check = $conn->query("SELECT id FROM attendances WHERE student_id = $student_id AND attendance_date = '$clock_date'");

            if ($date_check->num_rows == 0) {
                // Create new attendance record
                $insert_query = "INSERT INTO attendances (student_id, attendance_date, $db_column, status, created_at, updated_at)
                                VALUES ($student_id, '$clock_date', '$clock_time', 'pending', NOW(), NOW())";
                if ($conn->query($insert_query)) {
                    $message = "✓ " . ucfirst(str_replace('_', ' ', $clock_type)) . " recorded at " . date('h:i A', strtotime($clock_time));
                    $message_type = "success";
                } else {
                    $message = "Error recording time: " . $conn->error;
                    $message_type = "danger";
                }
            } else {
                // Update existing attendance record
                $update_query = "UPDATE attendances SET $db_column = '$clock_time', updated_at = NOW()
                                WHERE student_id = $student_id AND attendance_date = '$clock_date'";
                if ($conn->query($update_query)) {
                    $message = "✓ " . ucfirst(str_replace('_', ' ', $clock_type)) . " updated at " . date('h:i A', strtotime($clock_time));
                    $message_type = "success";
                } else {
                    $message = "Error updating time: " . $conn->error;
                    $message_type = "danger";
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
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('frontend/assets/images/favicon.ico') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/css/theme.min.css') }}">
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
                    <!--! [Start] nxl-lavel-mega-menu-wrapper !-->
                    <div class="nxl-lavel-mega-menu-wrapper d-flex gap-3">
                        <!--! [Start] nxl-lavel-menu !-->
                        <div class="dropdown nxl-h-item nxl-lavel-menu">
                            <a href="javascript:void(0);" class="avatar-text avatar-md bg-primary text-white" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                                <i class="feather-plus"></i>
                            </a>
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
                                        <a href="{{ url('/apps-chat') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Chat</span>
                                        </a>
                                        <a href="{{ url('/apps-email') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Email</span>
                                        </a>
                                        <a href="{{ url('/apps-tasks') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Tasks</span>
                                        </a>
                                        <a href="{{ url('/apps-notes') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Notes</span>
                                        </a>
                                        <a href="{{ url('/apps-storage') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Storage</span>
                                        </a>
                                        <a href="{{ url('/apps-calendar') }}" class="dropdown-item">
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
                                        <a href="{{ url('/reports-sales') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Sales Report</span>
                                        </a>
                                        <a href="{{ url('/reports-ojt') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>OJT Report</span>
                                        </a>
                                        <a href="{{ url('/reports-project') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Project Report</span>
                                        </a>
                                        <a href="{{ url('/reports-timesheets') }}" class="dropdown-item">
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
                                        <a href="{{ url('/proposal') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Proposal</span>
                                        </a>
                                        <a href="{{ url('/proposal-view') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Proposal View</span>
                                        </a>
                                        <a href="{{ url('/proposal-edit') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Proposal Edit</span>
                                        </a>
                                        <a href="{{ url('/proposal-create') }}" class="dropdown-item">
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
                                        <a href="{{ url('/payment') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Payment</span>
                                        </a>
                                        <a href="{{ url('/invoice-view') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Invoice View</span>
                                        </a>
                                        <a href="{{ url('/invoice-create') }}" class="dropdown-item">
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
                                        <a href="{{ url('/students') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Students</span>
                                        </a>
                                        <a href="{{ url('/students-view') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Students View</span>
                                        </a>
                                        <a href="{{ url('/students-create') }}" class="dropdown-item">
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
                                        <a href="{{ url('/ojt') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Leads</span>
                                        </a>
                                        <a href="{{ url('/ojt-view') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>OJT View</span>
                                        </a>
                                        <a href="{{ url('/ojt-create') }}" class="dropdown-item">
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
                                        <a href="{{ url('/projects') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Projects</span>
                                        </a>
                                        <a href="{{ url('/projects-view') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Projects View</span>
                                        </a>
                                        <a href="{{ url('/projects-create') }}" class="dropdown-item">
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
                                        <a href="{{ url('/widgets-lists') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Lists</span>
                                        </a>
                                        <a href="{{ url('/widgets-tables') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Tables</span>
                                        </a>
                                        <a href="{{ url('/widgets-charts') }}" class="dropdown-item">
                                            <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                            <span>Charts</span>
                                        </a>
                                        <a href="{{ url('/widgets-statistics') }}" class="dropdown-item">
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
                                                <a href="{{ url('/auth-login-cover') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="{{ url('/auth-login-cover') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="{{ url('/auth-login-creative') }}" class="dropdown-item">
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
                                                <a href="{{ url('/auth-register-cover') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="{{ url('/auth-register-minimal') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="{{ url('/register_submit') }}" class="dropdown-item">
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
                                                <a href="{{ url('/auth-404-cover') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="{{ url('/auth-404-minimal') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="{{ url('/auth-404-creative') }}" class="dropdown-item">
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
                                                <a href="{{ url('/auth-reset-cover') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="{{ url('/auth-reset-minimal') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="{{ url('/auth-reset-creative') }}" class="dropdown-item">
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
                                                <a href="{{ url('/auth-verify-cover') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="{{ url('/auth-verify-minimal') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="{{ url('/auth-verify-creative') }}" class="dropdown-item">
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
                                                <a href="{{ url('/auth-maintenance-cover') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Cover</span>
                                                </a>
                                                <a href="{{ url('/auth-maintenance-minimal') }}" class="dropdown-item">
                                                    <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                    <span>Minimal</span>
                                                </a>
                                                <a href="{{ url('/auth-maintenance-creative') }}" class="dropdown-item">
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
                            <a href="javascript:void(0);" class="btn btn-light-brand" data-bs-toggle="dropdown" data-bs-auto-close="outside"> Mega Menu </a>
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
                                                <img src="{{ asset('frontend/assets/images/banner/mockup.png') }}" alt="" class="img-fluid rounded-3" />
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
                                                    <a href="{{ url('/apps-chat') }}" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Chat</span>
                                                    </a>
                                                    <a href="{{ url('/apps-email') }}" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Email</span>
                                                    </a>
                                                    <a href="{{ url('/apps-tasks') }}" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Tasks</span>
                                                    </a>
                                                    <a href="{{ url('/apps-notes') }}" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Notes</span>
                                                    </a>
                                                    <a href="{{ url('/apps-storage') }}" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Storage</span>
                                                    </a>
                                                    <a href="{{ url('/apps-calendar') }}" class="dropdown-item">
                                                        <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                        <span>Calendar</span>
                                                    </a>
                                                </div>
                                                <div class="col-lg-6">
                                                    <div class="nxl-mega-menu-image">
                                                        <img src="{{ asset('frontend/assets/images/general/full-avatar.png') }}" alt="" class="img-fluid full-user-avtar" />
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
                                                            <img src="{{ asset('frontend/assets/images/brand/app-store.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">App Store</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/spotify.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Spotify</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/figma.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Figma</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/shopify.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Shopify</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/paypal.png') }}" alt="" class="img-fluid" />
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
                                                            <img src="{{ asset('frontend/assets/images/brand/gmail.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Gmail</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/dropbox.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Dropbox</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/google-drive.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Google Drive</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/github.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Github</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/gitlab.png') }}" alt="" class="img-fluid" />
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
                                                            <img src="{{ asset('frontend/assets/images/brand/facebook.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Facebook</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/pinterest.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Pinterest</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/instagram.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Instagram</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/twitter.png') }}" alt="" class="img-fluid" />
                                                        </div>
                                                        <div class="menu-item-title">Twitter</div>
                                                        <div class="menu-item-arrow">
                                                            <i class="feather-arrow-right"></i>
                                                        </div>
                                                    </a>
                                                    <a href="javascript:void(0);" class="dropdown-item">
                                                        <div class="menu-item-icon">
                                                            <img src="{{ asset('frontend/assets/images/brand/youtube.png') }}" alt="" class="img-fluid" />
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
                                                            <a href="javascript:void(0);" class="dropdown-item">CRM</a>
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
                                                        <img src="{{ asset('frontend/assets/images/banner/1.jpg') }}" alt="" class="img-fluid" />
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
                                                            <a href="{{ url('/auth-login-cover') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Login</span>
                                                            </a>
                                                            <a href="{{ url('/auth-register-cover') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Register</span>
                                                            </a>
                                                            <a href="{{ url('/auth-404-cover') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Error-404</span>
                                                            </a>
                                                            <a href="{{ url('/auth-reset-cover') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Reset Pass</span>
                                                            </a>
                                                            <a href="{{ url('/auth-verify-cover') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Verify OTP</span>
                                                            </a>
                                                            <a href="{{ url('/auth-maintenance-cover') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Maintenance</span>
                                                            </a>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <h6 class="dropdown-item-title">Minimal</h6>
                                                            <a href="{{ url('/auth-login-cover') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Login</span>
                                                            </a>
                                                            <a href="{{ url('/auth-register-minimal') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Register</span>
                                                            </a>
                                                            <a href="{{ url('/auth-404-minimal') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Error-404</span>
                                                            </a>
                                                            <a href="{{ url('/auth-reset-minimal') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Reset Pass</span>
                                                            </a>
                                                            <a href="{{ url('/auth-verify-minimal') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Verify OTP</span>
                                                            </a>
                                                            <a href="{{ url('/auth-maintenance-minimal') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Maintenance</span>
                                                            </a>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <h6 class="dropdown-item-title">Creative</h6>
                                                            <a href="{{ url('/auth-login-creative') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Login</span>
                                                            </a>
                                                            <a href="{{ url('/register_submit') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Register</span>
                                                            </a>
                                                            <a href="{{ url('/auth-404-creative') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Error-404</span>
                                                            </a>
                                                            <a href="{{ url('/auth-reset-creative') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Reset Pass</span>
                                                            </a>
                                                            <a href="{{ url('/auth-verify-creative') }}" class="dropdown-item">
                                                                <i class="wd-5 ht-5 bg-gray-500 rounded-circle me-3"></i>
                                                                <span>Verify OTP</span>
                                                            </a>
                                                            <a href="{{ url('/auth-maintenance-creative') }}" class="dropdown-item">
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
                                                                    <img src="{{ asset('frontend/assets/images/banner/6.jpg') }}" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">Shopify eCommerce Store</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the first slide.</p>
                                                                </div>
                                                            </div>
                                                            <div class="carousel-item">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="{{ asset('frontend/assets/images/banner/5.jpg') }}" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">iOS Apps Development</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the second slide.</p>
                                                                </div>
                                                            </div>
                                                            <div class="carousel-item">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="{{ asset('frontend/assets/images/banner/4.jpg') }}" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">Figma Dashboard Design</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                </div>
                                                            </div>
                                                            <div class="carousel-item">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="{{ asset('frontend/assets/images/banner/3.jpg') }}" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">React Dashboard Design</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                </div>
                                                            </div>
                                                            <div class="carousel-item">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="{{ asset('frontend/assets/images/banner/2.jpg') }}" alt="" class="img-fluid d-block w-100" />
                                                                </div>
                                                                <div class="carousel-caption">
                                                                    <h5 class="carousel-caption-title text-truncate-1-line">Standup Team Meeting</h5>
                                                                    <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                </div>
                                                            </div>
                                                            <div class="carousel-item">
                                                                <div class="nxl-mega-menu-image">
                                                                    <img src="{{ asset('frontend/assets/images/banner/1.jpg') }}" alt="" class="img-fluid d-block w-100" />
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
                                                                            <img src="{{ asset('frontend/assets/images/banner/1.jpg') }}" alt="" class="img-fluid rounded-3" />
                                                                        </div>
                                                                        <div class="mt-3 mt-lg-0 ms-lg-3 item-text">
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Shopify eCommerce Store</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Sint nam ullam iure eum sed rerum libero quis doloremque maiores veritatis?</p>
                                                                            <div class="hstack gap-2 mt-3">
                                                                                <div class="avatar-image avatar-sm">
                                                                                    <img src="{{ asset('frontend/assets/images/avatar/1.png') }}" alt="" class="img-fluid" />
                                                                                </div>
                                                                                <a href="javascript:void(0);" class="fs-12">Felix Luis Mateo</a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-6">
                                                                    <div class="d-lg-flex align-items-center gap-3">
                                                                        <div class="wd-150 rounded-3">
                                                                            <img src="{{ asset('frontend/assets/images/banner/2.jpg') }}" alt="" class="img-fluid rounded-3" />
                                                                        </div>
                                                                        <div class="mt-3 mt-lg-0 ms-lg-3 item-text">
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">iOS Apps Development</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Sint nam ullam iure eum sed rerum libero quis doloremque maiores veritatis?</p>
                                                                            <div class="hstack gap-2 mt-3">
                                                                                <div class="avatar-image avatar-sm">
                                                                                    <img src="{{ asset('frontend/assets/images/avatar/2.png') }}" alt="" class="img-fluid" />
                                                                                </div>
                                                                                <a href="javascript:void(0);" class="fs-12">Green Cute</a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-6">
                                                                    <div class="d-lg-flex align-items-center gap-3">
                                                                        <div class="wd-150 rounded-3">
                                                                            <img src="{{ asset('frontend/assets/images/banner/3.jpg') }}" alt="" class="img-fluid rounded-3" />
                                                                        </div>
                                                                        <div class="mt-3 mt-lg-0 ms-lg-3 item-text">
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">Figma Dashboard Design</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Sint nam ullam iure eum sed rerum libero quis doloremque maiores veritatis?</p>
                                                                            <div class="hstack gap-2 mt-3">
                                                                                <div class="avatar-image avatar-sm">
                                                                                    <img src="{{ asset('frontend/assets/images/avatar/3.png') }}" alt="" class="img-fluid" />
                                                                                </div>
                                                                                <a href="javascript:void(0);" class="fs-12">Malanie Hanvey</a>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-6">
                                                                    <div class="d-lg-flex align-items-center gap-3">
                                                                        <div class="wd-150 rounded-3">
                                                                            <img src="{{ asset('frontend/assets/images/banner/4.jpg') }}" alt="" class="img-fluid rounded-3" />
                                                                        </div>
                                                                        <div class="mt-3 mt-lg-0 ms-lg-3 item-text">
                                                                            <a href="javascript:void(0);">
                                                                                <h6 class="menu-item-heading text-truncate-1-line">React Dashboard Design</h6>
                                                                            </a>
                                                                            <p class="fs-12 fw-normal text-muted mb-0 text-truncate-2-line">Lorem ipsum dolor sit amet, consectetur adipisicing elit. Sint nam ullam iure eum sed rerum libero quis doloremque maiores veritatis?</p>
                                                                            <div class="hstack gap-2 mt-3">
                                                                                <div class="avatar-image avatar-sm">
                                                                                    <img src="{{ asset('frontend/assets/images/avatar/4.png') }}" alt="" class="img-fluid" />
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
                                                                            <img src="{{ asset('frontend/assets/images/banner/6.jpg') }}" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">Shopify eCommerce Store</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the first slide.</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="carousel-item">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="{{ asset('frontend/assets/images/banner/5.jpg') }}" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">iOS Apps Development</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the second slide.</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="carousel-item">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="{{ asset('frontend/assets/images/banner/4.jpg') }}" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">Figma Dashboard Design</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="carousel-item">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="{{ asset('frontend/assets/images/banner/3.jpg') }}" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">React Dashboard Design</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="carousel-item">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="{{ asset('frontend/assets/images/banner/2.jpg') }}" alt="" class="img-fluid d-block w-100" />
                                                                        </div>
                                                                        <div class="carousel-caption">
                                                                            <h5 class="carousel-caption-title text-truncate-1-line">Standup Team Meeting</h5>
                                                                            <p class="carousel-caption-desc">Some representative placeholder content for the third slide.</p>
                                                                        </div>
                                                                    </div>
                                                                    <div class="carousel-item">
                                                                        <div class="nxl-mega-menu-image">
                                                                            <img src="{{ asset('frontend/assets/images/banner/1.jpg') }}" alt="" class="img-fluid d-block w-100" />
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
                                                                <img src="{{ asset('frontend/assets/images/banner/1.jpg') }}" alt="" class="img-fluid" />
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
                                                                            <img src="{{ asset('frontend/assets/images/banner/1.jpg') }}" alt="" class="img-fluid rounded-3 border border-3" />
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
                                                                            <img src="{{ asset('frontend/assets/images/banner/2.jpg') }}" alt="" class="img-fluid rounded-3 border border-3" />
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
                                                                            <img src="{{ asset('frontend/assets/images/banner/3.jpg') }}" alt="" class="img-fluid rounded-3 border border-3" />
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
                                                                            <img src="{{ asset('frontend/assets/images/banner/4.jpg') }}" alt="" class="img-fluid rounded-3 border border-3" />
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
                                                                            <img src="{{ asset('frontend/assets/images/banner/5.jpg') }}" alt="" class="img-fluid rounded-3 border border-3" />
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
                                                                            <img src="{{ asset('frontend/assets/images/banner/6.jpg') }}" alt="" class="img-fluid rounded-3 border border-3" />
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
                    <div class="dropdown nxl-h-item nxl-header-language d-none d-sm-flex">
                        <a href="javascript:void(0);" class="nxl-head-link me-0 nxl-language-link" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <img src="{{ asset('frontend/assets/vendors/img/flags/4x3/us.svg') }}" alt="" class="img-fluid wd-20" />
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
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/sa.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>Arabic</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/bd.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>Bengali</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/ch.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>Chinese</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/nl.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>Dutch</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select active">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/us.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>English</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/fr.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>French</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/de.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>German</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/in.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>Hindi</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/asses/tvendors/img/flags/1x1/ru.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>Russian</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/es.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>Spanish</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/tr.svg') }}" alt="" class="img-fluid" /></div>
                                            <span>Turkish</span>
                                        </a>
                                    </div>
                                    <div class="col-sm-4 col-6 language_select">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2">
                                            <div class="avatar-image avatar-sm"><img src="{{ asset('frontend/assets/vendors/img/flags/1x1/pk.svg') }}" alt="" class="img-fluid" /></div>
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
                    </h2>
                    <p style="color: #666; margin: 0;">Simulate clock in and out events for attendance tracking</p>
                </div>

                <!-- Alert Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert-custom alert-<?php echo $message_type; ?>">
                        <span class="alert-icon">
                            <?php echo $message_type === 'success' ? '✓' : '✕'; ?>
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
                        <?php echo csrf_field(); ?>
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
                <div style="text-align: center; margin-top: 30px;">
                    <a href="{{ url('/attendance') }}" class="btn btn-primary" style="padding: 12px 30px; border-radius: 8px; text-decoration: none; display: inline-block;">
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
    <script src="{{ asset('frontend/assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/js/common-init.min.js') }}"></script>

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
    <script src="{{ asset('frontend/assets/vendors/js/vendors.min.js') }}"></script>
    <!-- vendors.min.js {always must need to be top} -->
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
</body>

</html>

<?php
$conn->close();
?>

