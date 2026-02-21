@php
// Data for this view is provided by `StudentController@edit`.
// Backwards-compatible: check for flashed session messages.
$success_message = session('success_message') ?? '';
$error_message = session('error_message') ?? '';

// Provide helper functions used in the template (guarded to avoid redeclare)
if (!function_exists('formatDate')) {
    function formatDate($date) {
        if ($date) {
            return date('Y-m-d', strtotime($date));
        }
        return '';
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($date) {
        if ($date) {
            return date('M d, Y h:i A', strtotime($date));
        }
        return 'N/A';
    }
}
@endphp

<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <title>BioTern || Edit Student - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('frontend/assets/images/favicon.ico') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/vendors/css/select2-theme.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/vendors/css/datepicker.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('frontend/assets/css/theme.min.css') }}">

    <style>
        /* Select2 styling adjustments - avoid overriding position computed by plugin */
        .select2-container--default .select2-selection--single {
            height: 40px;
            padding: 6px 12px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            border: 1px solid #d3d3d3;
            background-color: #fff;
            color: #333;
        }

        .select2-container--default.select2-container--open .select2-selection--single {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 0;
            align-items: center;
            display: flex;
        }

        /* Let Select2 calculate dropdown position. Only ensure visuals and stacking. */
        .select2-container--default .select2-dropdown {
            border: 1px solid #dddddd;
            border-radius: 0 0 4px 4px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
            max-height: 200px;
            overflow-y: auto;
        }
        .select2-container--open {
            z-index: 1065;
        }

        .select2-container {
            width: 100% !important;
            max-width: 100%;
        }

        .form-control.select2-hidden-accessible {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        /* Small tweak for arrow height and layout */
        .select2-container--default .select2-selection--single {
            position: relative;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 28px;
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Clear (Ã—) button styling */
        .select2-selection__clear {
            position: absolute;
            right: 36px;
            top: 50%;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 3px;
            border: 1px solid #e0e0e0;
            background: #fff;
            color: #333;
            box-shadow: none;
            font-size: 12px;
        }

        /* Truncate selected text so it doesn't overflow under icons */
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            display: inline-block;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: calc(100% - 80px);
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
        @media (min-width: 992px) {
            html.minimenu .nxl-header {
                left: 100px !important;
                width: calc(100% - 100px) !important;
            }
            html.minimenu .nxl-container {
                margin-left: 100px !important;
                width: calc(100% - 100px);
            }
            html.minimenu .page-header {
                left: 100px !important;
            }
        }
        /* Keep edit form content below fixed top header */
        .nxl-header + .nxl-container .nxl-content {
            padding-top: 84px;
        }
        @media (max-width: 991.98px) {
            .nxl-header + .nxl-container .nxl-content {
                padding-top: 72px;
            }
        }
        /* Prevent anchored sections from hiding under header */
        #upload-profile-picture,
        #upload-tools,
        #editStudentForm {
            scroll-margin-top: 96px;
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
            background-color: #0f172a !important;
            border-color: #120a36 !important;
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

        html.app-skin-light select.form-control,
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
    <!--! Navigation !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="{{ route('dashboard') }}" class="b-brand">
                    <img src="{{ asset('frontend/assets/images/logo-full.png') }}" alt="" class="logo logo-lg">
                    <img src="{{ asset('frontend/assets/images/logo-abbr.png') }}" alt="" class="logo logo-sm">
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
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('dashboard') }}">CRM</a></li>
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
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/students') }}">Students</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/students/view') }}">Students View</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ url('/students/create') }}">Students Create</a></li>
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

    <!--! Header !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-2">
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
                                <input type="text" class="form-control search-input-field" id="global_search" name="global_search" placeholder="Search...." aria-label="Search">
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
                            <img src="{{ asset('frontend/assets/images/avatar/1.png') }}" alt="user-image" class="img-fluid user-avtar me-0">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="{{ asset('frontend/assets/images/avatar/1.png') }}" alt="user-image" class="img-fluid user-avtar">
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
                            <a href="{{ url('/auth-login-cover') }}" class="dropdown-item">
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
                        <h5 class="m-b-10">Edit Student</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ url('/students') }}">Students</a></li>
                        <li class="breadcrumb-item"><a href="{{ url('/students/view') }}?id=<?php echo $student['id']; ?>">View</a></li>
                        <li class="breadcrumb-item">Edit</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            <a href="{{ url('/students/view') }}?id=<?php echo $student['id']; ?>" class="btn btn-light-brand">
                                <i class="feather-arrow-left me-2"></i>
                                <span>Back to View</span>
                            </a>
                            <a href="{{ url('/students') }}" class="btn btn-primary">
                                <i class="feather-list me-2"></i>
                                <span>Back to List</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h5 class="card-title mb-0">Edit Student Information</h5>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Alert Messages -->
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="feather-check-circle me-2"></i>
                                        <?php echo $success_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="feather-alert-circle me-2"></i>
                                        <?php echo $error_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <!-- Quick Upload Tools -->
                                <div class="row g-3 mb-4" id="upload-tools">
                                    <div class="col-lg-6" id="upload-profile-picture">
                                        <div class="border rounded p-3 h-100">
                                            <h6 class="fw-bold mb-3"><i class="feather-image me-2"></i>Upload Profile Picture</h6>
                                            <form method="POST" action="<?php echo route('students.update', ['id' => $student['id'] ?? ($student['id'] ?? null)]); ?>" enctype="multipart/form-data">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="upload_profile_picture">
                                                <div class="mb-3">
                                                    <label for="upload_profile_picture_file" class="form-label visually-hidden">Profile picture</label>
                                                    <input type="file" class="form-control" id="upload_profile_picture_file" name="profile_picture" accept="image/*" required aria-describedby="uploadProfileHelp">
                                                    <small id="uploadProfileHelp" class="form-text text-muted">JPG, JPEG, PNG, GIF (Max 5MB)</small>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="feather-upload me-2"></i>Upload Picture
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Form -->
                                <form method="POST" action="<?php echo route('students.update', ['id' => $student['id'] ?? ($student['id'] ?? null)]); ?>" id="editStudentForm" enctype="multipart/form-data">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_profile">
                                    <!-- Personal Information Section -->
                                    <div class="mb-5">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-user me-2"></i>Personal Information
                                        </h6>

                                        <div class="row">
                                            <div class="col-md-4 mb-4">
                                                <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                                                      <input type="text" class="form-control" id="first_name" name="first_name"
                                                          value="<?php echo htmlspecialchars($student['first_name']); ?>" required autocomplete="given-name">
                                            </div>
                                            <div class="col-md-4 mb-4">
                                                <label for="middle_name" class="form-label fw-semibold">Middle Name</label>
                                                      <input type="text" class="form-control" id="middle_name" name="middle_name"
                                                          value="<?php echo htmlspecialchars($student['middle_name'] ?? ''); ?>" autocomplete="additional-name">
                                            </div>
                                            <div class="col-md-4 mb-4">
                                                <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                                                      <input type="text" class="form-control" id="last_name" name="last_name"
                                                          value="<?php echo htmlspecialchars($student['last_name']); ?>" required autocomplete="family-name">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                                      <input type="email" class="form-control" id="email" name="email"
                                                          value="<?php echo htmlspecialchars($student['email']); ?>" required autocomplete="email">
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="phone" class="form-label fw-semibold">Phone Number</label>
                                                      <input type="tel" class="form-control" id="phone" name="phone"
                                                          value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" autocomplete="tel">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="date_of_birth" class="form-label fw-semibold">Date of Birth</label>
                                                      <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                                          value="<?php echo formatDate($student['date_of_birth']); ?>" autocomplete="bday">
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="gender" class="form-label fw-semibold">Gender</label>
                                                <select class="form-control" id="gender" name="gender">
                                                    <option value="">-- Select Gender --</option>
                                                    <option value="male" <?php echo $student['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                                    <option value="female" <?php echo $student['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                                    <option value="other" <?php echo $student['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="address" class="form-label fw-semibold">Home Address</label>
                                            <textarea class="form-control" id="address" name="address" rows="3" autocomplete="street-address"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-4">
                                            <label for="emergency_contact" class="form-label fw-semibold">Emergency Contact Number</label>
                                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact"
                                                   value="<?php echo htmlspecialchars($student['emergency_contact'] ?? ''); ?>"
                                                   placeholder="Phone number" autocomplete="tel">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="student_id" class="form-label fw-semibold">Student ID</label>
                                                      <input type="text" class="form-control" id="student_id" name="student_id"
                                                          value="<?php echo htmlspecialchars($student['student_id'] ?? ''); ?>" autocomplete="username" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?>>
                                                <small class="form-text text-muted">Can be edited by admins, coordinators, and supervisors</small>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="profile_picture" class="form-label fw-semibold">Profile Picture</label>
                                                <div class="mb-2">
                                                    <?php if (!empty($student['profile_picture'])): ?>
                                                        <?php $pp = $student['profile_picture']; $ppPath = public_path($pp); ?>
                                                        <div class="mb-2">
                                                            <?php if (file_exists($ppPath) && is_file($ppPath)): ?>
                                                                <img src="<?php echo asset($pp) . '?v=' . filemtime($ppPath); ?>" alt="Profile" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                                            <?php else: ?>
                                                                <div class="alert alert-warning mb-2 py-2 px-3">Profile picture not found on disk</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-info mb-2 py-2 px-3">No profile picture uploaded</div>
                                                    <?php endif; ?>
                                                </div>
                                                <input type="file" class="form-control" id="profile_picture" name="profile_picture"
                                                       accept="image/*">
                                                <small class="form-text text-muted">JPG, PNG, GIF (Max 5MB)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <!-- Internship Information Section -->
                                    <div class="mb-5">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-briefcase me-2"></i>Internship Information
                                        </h6>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="supervisor_id" class="form-label fw-semibold">Supervisor</label>
                                                <select class="form-control" id="supervisor_id" name="supervisor_id">
                                                    <option value=""></option>
                                                    <?php foreach ($supervisors as $supervisor): ?>
                                                        <option value="<?php echo (int)$supervisor['id']; ?>"
                                                            <?php echo ((int)($student['supervisor_id'] ?? 0) === (int)$supervisor['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($supervisor['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="coordinator_id" class="form-label fw-semibold">Coordinator</label>
                                                <select class="form-control" id="coordinator_id" name="coordinator_id">
                                                    <option value=""></option>
                                                    <?php foreach ($coordinators as $coordinator): ?>
                                                        <option value="<?php echo (int)$coordinator['id']; ?>"
                                                            <?php echo ((int)($student['coordinator_id'] ?? 0) === (int)$coordinator['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($coordinator['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Academic Information Section -->
                                    <div class="mb-5">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-book me-2"></i>Academic Information
                                        </h6>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="course_id" class="form-label fw-semibold">Course</label>
                                                <select class="form-control" id="course_id" name="course_id">
                                                    <option value="0">-- Select Course --</option>
                                                    <?php foreach ($courses as $course): ?>
                                                        <option value="<?php echo $course['id']; ?>"
                                                            <?php echo $student['course_id'] == $course['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($course['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="status" class="form-label fw-semibold">Status</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="1" <?php echo $student['status'] == 1 ? 'selected' : ''; ?>>Active</option>
                                                    <option value="0" <?php echo $student['status'] == 0 ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="assignment_track" class="form-label fw-semibold">Assignment Track</label>
                                                <select class="form-control" id="assignment_track" name="assignment_track" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?>>
                                                    <option value="internal" <?php echo (($student['assignment_track'] ?? 'internal') === 'internal') ? 'selected' : ''; ?>>Internal</option>
                                                    <option value="external" <?php echo (($student['assignment_track'] ?? '') === 'external') ? 'selected' : ''; ?>>External</option>
                                                </select>
                                                <small class="form-text text-muted">Rule: External is allowed only when Internal Hours Remaining is 0.</small>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-3 mb-4">
                                                <label for="internal_total_hours" class="form-label fw-semibold">Internal Total Hours</label>
                                                <input type="number" class="form-control" id="internal_total_hours" name="internal_total_hours" min="0" step="1" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['internal_total_hours'] ?? '')); ?>">
                                            </div>
                                            <div class="col-md-3 mb-4">
                                                <label for="internal_total_hours_remaining" class="form-label fw-semibold">Internal Hours Remaining</label>
                                                <input type="number" class="form-control" id="internal_total_hours_remaining" name="internal_total_hours_remaining" min="0" step="1" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['internal_total_hours_remaining'] ?? '')); ?>">
                                            </div>
                                            <div class="col-md-3 mb-4">
                                                <label for="external_total_hours" class="form-label fw-semibold">External Total Hours</label>
                                                <input type="number" class="form-control" id="external_total_hours" name="external_total_hours" min="0" step="1" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['external_total_hours'] ?? '')); ?>">
                                            </div>
                                            <div class="col-md-3 mb-4">
                                                <label for="external_total_hours_remaining" class="form-label fw-semibold">External Hours Remaining</label>
                                                <input type="number" class="form-control" id="external_total_hours_remaining" name="external_total_hours_remaining" min="0" step="1" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['external_total_hours_remaining'] ?? '')); ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-label fw-semibold text-muted">Biometric Status</div>
                                                <div class="form-text">
                                                    <?php if ($student['biometric_registered']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="feather-check me-1"></i>Registered
                                                        </span>
                                                        <small class="d-block mt-2 text-muted">
                                                            Registered on: <?php echo formatDateTime($student['biometric_registered_at']); ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="feather-alert-circle me-1"></i>Not Registered
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-label fw-semibold text-muted">Registration Date</div>
                                                <div class="form-text">
                                                    <small class="text-muted">
                                                        Registered: <?php echo formatDateTime($student['created_at']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <!-- Form Actions -->
                                    <div class="d-flex gap-2 justify-content-between pt-4">
                                        <a href="{{ url('/generate_resume') }}?id=<?php echo $student['id']; ?>" class="btn btn-success" target="_blank">
                                            <i class="feather-file-text me-2"></i>Generate Resume
                                        </a>
                                        <div class="d-flex gap-2">
                                            <a href="{{ url('/students/view') }}?id=<?php echo $student['id']; ?>" class="btn btn-light-brand">
                                                <i class="feather-x me-2"></i>Cancel
                                            </a>
                                            <button type="reset" class="btn btn-outline-secondary">
                                                <i class="feather-refresh-cw me-2"></i>Reset
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="feather-save me-2"></i>Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </form>
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
            <p><span>By: <a target="_blank" href="">ACT 2A</a> </span><span>Distributed by: <a target="_blank" href="">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </footer>
    </main>

    <!-- Scripts -->
    <script src="{{ asset('frontend/assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/vendors/js/select2-active.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/vendors/js/datepicker.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/js/common-init.min.js') }}"></script>
    <script src="{{ asset('frontend/assets/js/theme-customizer-init.min.js') }}"></script>

    <script>
        // Initialize form elements
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize select2 for dropdowns
            $('#course_id, #status, #gender, #assignment_track').each(function() {
                $(this).select2({
                    allowClear: false,
                    width: 'resolve',
                    dropdownAutoWidth: false,
                    dropdownParent: $(this).parent(),
                    theme: 'default'
                });
            });

            $('#supervisor_id').select2({
                placeholder: 'Search supervisor',
                allowClear: true,
                minimumResultsForSearch: 0,
                width: 'resolve',
                dropdownAutoWidth: false,
                theme: 'default'
            });

            $('#coordinator_id').select2({
                placeholder: 'Search coordinator',
                allowClear: true,
                minimumResultsForSearch: 0,
                width: 'resolve',
                dropdownAutoWidth: false,
                theme: 'default'
            });

            // Initialize datepicker for date fields
            $('#date_of_birth').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true
            });

            // Form validation
            document.getElementById('editStudentForm').addEventListener('submit', function(e) {
                var firstName = document.getElementById('first_name').value.trim();
                var lastName = document.getElementById('last_name').value.trim();
                var email = document.getElementById('email').value.trim();

                if (!firstName || !lastName || !email) {
                    e.preventDefault();
                    alert('Please fill in all required fields!');
                    return false;
                }

                // Email validation
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address!');
                    return false;
                }

                return true;
            });
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>

</html>

<!-- Connection closed in controller; nothing to close in view -->


