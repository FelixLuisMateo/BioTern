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
    <title>BioTern || Widgets Miscellaneous</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/daterangepicker.min.css">
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
                        <h5 class="m-b-10">Widgets</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item">Miscellaneous</li>
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
                            <div class="dropdown filter-dropdown">
                                <a class="btn btn-md btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-filter me-2"></i>
                                    <span>Filter</span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <div class="dropdown-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="Role" checked="checked">
                                            <label class="custom-control-label c-pointer" for="Role">Role</label>
                                        </div>
                                    </div>
                                    <div class="dropdown-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="Team" checked="checked">
                                            <label class="custom-control-label c-pointer" for="Team">Team</label>
                                        </div>
                                    </div>
                                    <div class="dropdown-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="Email" checked="checked">
                                            <label class="custom-control-label c-pointer" for="Email">Email</label>
                                        </div>
                                    </div>
                                    <div class="dropdown-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="Member" checked="checked">
                                            <label class="custom-control-label c-pointer" for="Member">Member</label>
                                        </div>
                                    </div>
                                    <div class="dropdown-item">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="Recommendation" checked="checked">
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
                            <a href="javascript:void(0);" class="btn btn-md btn-primary">
                                <i class="feather-plus me-2"></i>
                                <span>Add widget</span>
                            </a>
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
                    <!-- [Mini Cards] start -->
                    <div class="col-12">
                        <div class="card stretch stretch-full">
                            <div class="card-body">
                                <div class="hstack justify-content-between mb-4 pb-">
                                    <div>
                                        <h5 class="mb-1">Projects</h5>
                                        <span class="fs-12 text-muted">Recent project progress</span>
                                    </div>
                                    <a href="javascript:void(0);" class="btn btn-light-brand">View Alls</a>
                                </div>
                                <div class="row g-4">
                                    <div class="col-xxl-3 col-md-6">
                                        <div class="card-body border border-dashed border-gray-5 rounded-3 position-relative">
                                            <div class="hstack justify-content-between gap-4">
                                                <div>
                                                    <h6 class="fs-14 text-truncate-1-line">NFT Mobile Apps Developemnt</h6>
                                                    <div class="fs-12 text-muted"><span class="text-dark fw-medium">Deadiline:</span> 20 days left</div>
                                                </div>
                                                <div class="project-progress-1"></div>
                                            </div>
                                            <div class="badge bg-gray-200 text-dark project-mini-card-badge">Updates</div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-md-6">
                                        <div class="card-body border border-dashed border-gray-5 rounded-3 position-relative">
                                            <div class="hstack justify-content-between gap-4">
                                                <div>
                                                    <h6 class="fs-14 text-truncate-1-line">NFT Mobile Apps Developemnt</h6>
                                                    <div class="fs-12 text-muted"><span class="text-dark fw-medium">Deadiline:</span> 20 days left</div>
                                                </div>
                                                <div class="project-progress-2"></div>
                                            </div>
                                            <div class="badge bg-gray-200 text-dark project-mini-card-badge">Updates</div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-md-6">
                                        <div class="card-body border border-dashed border-gray-5 rounded-3 position-relative">
                                            <div class="hstack justify-content-between gap-4">
                                                <div>
                                                    <h6 class="fs-14 text-truncate-1-line">NFT Mobile Apps Developemnt</h6>
                                                    <div class="fs-12 text-muted"><span class="text-dark fw-medium">Deadiline:</span> 20 days left</div>
                                                </div>
                                                <div class="project-progress-3"></div>
                                            </div>
                                            <div class="badge bg-gray-200 text-dark project-mini-card-badge">Updates</div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-md-6">
                                        <div class="card-body border border-dashed border-gray-5 rounded-3 position-relative">
                                            <div class="hstack justify-content-between gap-4">
                                                <div>
                                                    <h6 class="fs-14 text-truncate-1-line">NFT Mobile Apps Developemnt</h6>
                                                    <div class="fs-12 text-muted"><span class="text-dark fw-medium">Deadiline:</span> 20 days left</div>
                                                </div>
                                                <div class="project-progress-4"></div>
                                            </div>
                                            <div class="badge bg-gray-200 text-dark project-mini-card-badge">Updates</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Mini Cards] end -->
                    <!-- [Mini Cards] start -->
                    <div class="col-lg-12">
                        <div class="card stretch stretch-full">
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-xxl-3 col-lg-6">
                                        <div class="border border-dashed border-gray-5 p-4 rounded-3 gap-4 text-center">
                                            <div class="sales-progress-1"></div>
                                            <div class="mt-4">
                                                <p class="fs-12 text-muted mb-1">Clossing date: <span class="fs-11 fw-medium text-dark">22 March, 2023</span></p>
                                                <a href="javascript:void(0);" class="fw-bold text-truncate-1-line">Web developement deal with alex</a>
                                                <div class="hstack gap-3 mt-3 justify-content-center">
                                                    <div class="avatar-image avatar-sm">
                                                        <img src="assets/images/avatar/1.png" alt="" class="img-fluid">
                                                    </div>
                                                    <a href="javascript:void(0);">Felix Luis Mateo</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-lg-6">
                                        <div class="border border-dashed border-gray-5 p-4 rounded-3 gap-4 text-center">
                                            <div class="sales-progress-2"></div>
                                            <div class="mt-4">
                                                <p class="fs-12 text-muted mb-1">Clossing date: <span class="fs-11 fw-medium text-dark">23 March, 2023</span></p>
                                                <a href="javascript:void(0);" class="fw-bold text-truncate-1-line">Web developement deal with alex</a>
                                                <div class="hstack gap-3 mt-3 justify-content-center">
                                                    <div class="avatar-image avatar-sm">
                                                        <img src="assets/images/avatar/2.png" alt="" class="img-fluid">
                                                    </div>
                                                    <a href="javascript:void(0);">Green Cute</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-lg-6">
                                        <div class="border border-dashed border-gray-5 p-4 rounded-3 gap-4 text-center">
                                            <div class="sales-progress-3"></div>
                                            <div class="mt-4">
                                                <p class="fs-12 text-muted mb-1">Clossing date: <span class="fs-11 fw-medium text-dark">24 March, 2023</span></p>
                                                <a href="javascript:void(0);" class="fw-bold text-truncate-1-line">Web developement deal with alex</a>
                                                <div class="hstack gap-3 mt-3 justify-content-center">
                                                    <div class="avatar-image avatar-sm">
                                                        <img src="assets/images/avatar/3.png" alt="" class="img-fluid">
                                                    </div>
                                                    <a href="javascript:void(0);">Holmes Cherryman</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-3 col-lg-6">
                                        <div class="border border-dashed border-gray-5 p-4 rounded-3 gap-4 text-center">
                                            <div class="sales-progress-4"></div>
                                            <div class="mt-4">
                                                <p class="fs-12 text-muted mb-1">Clossing date: <span class="fs-11 fw-medium text-dark">25 March, 2023</span></p>
                                                <a href="javascript:void(0);" class="fw-bold text-truncate-1-line">Web developement deal with alex</a>
                                                <div class="hstack gap-3 mt-3 justify-content-center">
                                                    <div class="avatar-image avatar-sm">
                                                        <img src="assets/images/avatar/4.png" alt="" class="img-fluid">
                                                    </div>
                                                    <a href="javascript:void(0);">Malanie Hanvey</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Mini Cards] end -->
                    <!-- [Tasks Progress] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Tasks Progress</h5>
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
                                <div class="hstack justify-content-between border border-dashed rounded-3 p-3 mb-3">
                                    <div class="hstack gap-3">
                                        <div class="avatar-image">
                                            <img src="assets/images/avatar/1.png" alt="" class="img-fluid">
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);">Felix Luis Mateo</a>
                                            <div class="fs-11 text-muted">Frontend Developer</div>
                                        </div>
                                    </div>
                                    <div class="team-progress-1"></div>
                                </div>
                                <div class="hstack justify-content-between border border-dashed rounded-3 p-3 mb-3">
                                    <div class="hstack gap-3">
                                        <div class="avatar-image">
                                            <img src="assets/images/avatar/2.png" alt="" class="img-fluid">
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);">Archie Cantones</a>
                                            <div class="fs-11 text-muted">UI/UX Designer</div>
                                        </div>
                                    </div>
                                    <div class="team-progress-2"></div>
                                </div>
                                <div class="hstack justify-content-between border border-dashed rounded-3 p-3 mb-3">
                                    <div class="hstack gap-3">
                                        <div class="avatar-image">
                                            <img src="assets/images/avatar/3.png" alt="" class="img-fluid">
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);">Malanie Hanvey</a>
                                            <div class="fs-11 text-muted">Backend Developer</div>
                                        </div>
                                    </div>
                                    <div class="team-progress-3"></div>
                                </div>
                                <div class="hstack justify-content-between border border-dashed rounded-3 p-3 mb-0">
                                    <div class="hstack gap-3">
                                        <div class="avatar-image">
                                            <img src="assets/images/avatar/4.png" alt="" class="img-fluid">
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);">Kenneth Hune</a>
                                            <div class="fs-11 text-muted">Digital Marketer</div>
                                        </div>
                                    </div>
                                    <div class="team-progress-4"></div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="javascript:void(0);" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                    </div>
                    <!-- [Tasks Progress] end -->
                    <!-- [Goal Progress] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Goal Progress</h5>
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
                                <div class="row g-4">
                                    <div class="col-sm-6">
                                        <div class="px-4 py-3 text-center border border-dashed rounded-3">
                                            <div class="mx-auto mb-4">
                                                <div class="goal-progress-1"></div>
                                            </div>
                                            <h2 class="fs-13 tx-spacing-1">Marketing Gaol</h2>
                                            <div class="fs-11 text-muted">$550/$1250 USD</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="px-4 py-3 text-center border border-dashed rounded-3">
                                            <div class="mx-auto mb-4">
                                                <div class="goal-progress-2"></div>
                                            </div>
                                            <h2 class="fs-13 tx-spacing-1">Teams Goal</h2>
                                            <div class="fs-11 text-muted">$550/$1250 USD</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="px-4 py-3 text-center border border-dashed rounded-3">
                                            <div class="mx-auto mb-4">
                                                <div class="goal-progress-3"></div>
                                            </div>
                                            <h2 class="fs-13 tx-spacing-1">OJT Goal</h2>
                                            <div class="fs-11 text-muted">$850/$950 USD</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="px-4 py-3 text-center border border-dashed rounded-3">
                                            <div class="mx-auto mb-4">
                                                <div class="goal-progress-4"></div>
                                            </div>
                                            <h2 class="fs-13 tx-spacing-1">Revenue Goal</h2>
                                            <div class="fs-11 text-muted">$5,655/$12,500 USD</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="javascript:void(0);" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                    </div>
                    <!-- [Goal Progress] end -->
                    <!-- [Revenue Forecast] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Revenue Forecast</h5>
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
                                <div class="text-center mb-4">
                                    <div class="goal-progress"></div>
                                </div>
                                <hr class="border-top-dashed">
                                <div class="d-flex justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-text">
                                            <img src="assets/images/icons/1.png" alt="" class="img-fluid">
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="fw-bold">Monthly Subscription</a>
                                            <div class="fs-11 text-muted mt-1">Ricky Hunt, Sandra Trepp</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="badge bg-soft-100 text-dark">+ 85K</div>
                                    </div>
                                </div>
                                <hr class="border-top-dashed">
                                <div class="d-flex justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-text">
                                            <img src="assets/images/icons/2.png" alt="" class="img-fluid">
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="fw-bold">Monthly Contributors</a>
                                            <div class="fs-11 text-muted mt-1">Ricky Hunt, Sandra Trepp</div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="badge bg-soft-100 text-dark">+ 96K</div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="javascript:void(0);" class="btn btn-primary">Generate Report</a>
                            </div>
                        </div>
                    </div>
                    <!-- [Revenue Forecast] end -->
                    <!-- [Time Progress 1] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header justify-content-center">
                                <div class="times-progress-chart"></div>
                            </div>
                            <div class="card-body">
                                <div class="hstack gap-3 justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="wd-7 ht-7 bg-primary rounded-circle"></div>
                                        <div class="ps-3 border-start border-3 border-primary rounded">
                                            <a href="javascript:void(0);" class="fw-semibold text-truncate-1-line">React Apps</a>
                                            <a href="javascript:void(0);" class="fs-12 fw-medium text-muted">3/5 Tasks</a>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-bold">01/h: 34/m : 24/s</a>
                                </div>
                                <hr class="border-dashed my-3">
                                <div class="hstack gap-3 justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="wd-7 ht-7 bg-success rounded-circle"></div>
                                        <div class="ps-3 border-start border-3 border-success rounded">
                                            <a href="javascript:void(0);" class="fw-semibold text-truncate-1-line">Vuejs Apps</a>
                                            <a href="javascript:void(0);" class="fs-12 fw-medium text-muted">4/8 Tasks</a>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-bold">02/h: 26/m : 35/s</a>
                                </div>
                                <hr class="border-dashed my-3">
                                <div class="hstack gap-3 justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="wd-7 ht-7 bg-danger rounded-circle"></div>
                                        <div class="ps-3 border-start border-3 border-danger rounded">
                                            <a href="javascript:void(0);" class="fw-semibold text-truncate-1-line">Overview Admin</a>
                                            <a href="javascript:void(0);" class="fs-12 fw-medium text-muted">13/15 Tasks</a>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-bold">01/h: 33/m : 42/s</a>
                                </div>
                            </div>
                            <div class="card-footer hstack justify-content-around">
                                <div class="text-center">
                                    <a href="javascript:void(0);" class="fs-16 fw-bold">05/H : 33/M</a>
                                    <div class="fs-11 text-muted">Billable Hours</div>
                                </div>
                                <span class="vr"></span>
                                <div class="text-center">
                                    <a href="javascript:void(0);" class="fs-16 fw-bold">02/H : 14/M</a>
                                    <div class="fs-11 text-muted">Unbillable Hours</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Time Progress 1] end -->
                    <!-- [Time Progress 2] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header justify-content-center">
                                <div class="tasks-progress-chart"></div>
                            </div>
                            <div class="card-body">
                                <div class="hstack gap-3 justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="wd-7 ht-7 bg-danger rounded-circle"></div>
                                        <div class="ps-3 border-start border-3 border-danger rounded">
                                            <a href="javascript:void(0);" class="fw-semibold text-truncate-1-line">Overview Admin</a>
                                            <a href="javascript:void(0);" class="fs-12 fw-medium text-muted">13/15 Tasks</a>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-bold">01/h: 33/m : 42/s</a>
                                </div>
                                <hr class="border-dashed my-3">
                                <div class="hstack gap-3 justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="wd-7 ht-7 bg-primary rounded-circle"></div>
                                        <div class="ps-3 border-start border-3 border-primary rounded">
                                            <a href="javascript:void(0);" class="fw-semibold text-truncate-1-line">React Apps</a>
                                            <a href="javascript:void(0);" class="fs-12 fw-medium text-muted">3/5 Tasks</a>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-bold">01/h: 34/m : 24/s</a>
                                </div>
                                <hr class="border-dashed my-3">
                                <div class="hstack gap-3 justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="wd-7 ht-7 bg-success rounded-circle"></div>
                                        <div class="ps-3 border-start border-3 border-success rounded">
                                            <a href="javascript:void(0);" class="fw-semibold text-truncate-1-line">Vuejs Apps</a>
                                            <a href="javascript:void(0);" class="fs-12 fw-medium text-muted">4/8 Tasks</a>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-bold">02/h: 26/m : 35/s</a>
                                </div>
                            </div>
                            <div class="card-footer hstack justify-content-around">
                                <div class="text-center">
                                    <a href="javascript:void(0);" class="fs-16 fw-bold">15/30</a>
                                    <div class="fs-11 text-muted">Tasks Completed</div>
                                </div>
                                <span class="vr"></span>
                                <div class="text-center">
                                    <a href="javascript:void(0);" class="fs-16 fw-bold">00/50</a>
                                    <div class="fs-11 text-muted">Tasks Upcomming</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Time Progress 2] end -->
                    <!-- [Time Progress 3] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header justify-content-center">
                                <div class="projects-progress-chart"></div>
                            </div>
                            <div class="card-body">
                                <div class="hstack gap-3 justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="wd-7 ht-7 bg-danger rounded-circle"></div>
                                        <div class="ps-3 border-start border-3 border-danger rounded">
                                            <a href="javascript:void(0);" class="fw-semibold text-truncate-1-line">Overview Admin</a>
                                            <a href="javascript:void(0);" class="fs-12 fw-medium text-muted">13/15 Tasks</a>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-bold">01/h: 33/m : 42/s</a>
                                </div>
                                <hr class="border-dashed my-3">
                                <div class="hstack gap-3 justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="wd-7 ht-7 bg-success rounded-circle"></div>
                                        <div class="ps-3 border-start border-3 border-success rounded">
                                            <a href="javascript:void(0);" class="fw-semibold text-truncate-1-line">Vuejs Apps</a>
                                            <a href="javascript:void(0);" class="fs-12 fw-medium text-muted">4/8 Tasks</a>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-bold">02/h: 26/m : 35/s</a>
                                </div>
                                <hr class="border-dashed my-3">
                                <div class="hstack gap-3 justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="wd-7 ht-7 bg-primary rounded-circle"></div>
                                        <div class="ps-3 border-start border-3 border-primary rounded">
                                            <a href="javascript:void(0);" class="fw-semibold text-truncate-1-line">React Apps</a>
                                            <a href="javascript:void(0);" class="fs-12 fw-medium text-muted">3/5 Tasks</a>
                                        </div>
                                    </div>
                                    <a href="javascript:void(0);" class="fw-bold">01/h: 34/m : 24/s</a>
                                </div>
                            </div>
                            <div class="card-footer hstack justify-content-around">
                                <div class="text-center">
                                    <a href="javascript:void(0);" class="fs-16 fw-bold">13/20</a>
                                    <div class="fs-11 text-muted">Projects Completed</div>
                                </div>
                                <span class="vr"></span>
                                <div class="text-center">
                                    <a href="javascript:void(0);" class="fs-16 fw-bold">00/25</a>
                                    <div class="fs-11 text-muted">Projects Upcomming</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Time Progress 3] end -->
                    <!-- [Selling Status] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full overflow-hidden">
                            <div class="bg-primary text-white">
                                <div class="p-4 d-flex justify-content-between align-items-center">
                                    <h5 class="text-reset">Selling Status</h5>
                                    <div class="dropdown">
                                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown">
                                            <div data-bs-toggle="tooltip" title="Options">
                                                <i class="feather-more-vertical"></i>
                                            </div>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end rounded-top">
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-at-sign"></i>New</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-calendar"></i>Event</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-bell"></i>Snoozed</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-trash-2"></i>Deleted</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-settings"></i>Settings</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-life-buoy"></i>Tips &amp; Tricks</a>
                                        </div>
                                    </div>
                                </div>
                                <div id="selling-status-area-chart"></div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-text avatar-lg bg-soft-primary text-primary border-soft-primary rounded">
                                            <i class="feather-airplay"></i>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="fw-bold mb-2">Weekly Bestseller</a>
                                            <p class="fs-12 text-muted mb-0">10+ weekly bestseller</p>
                                        </div>
                                    </div>
                                    <div class="img-group lh-0 ms-2 justify-content-start">
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Janette Dalton">
                                            <img src="assets/images/avatar/2.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Michael Ksen">
                                            <img src="assets/images/avatar/3.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Socrates Itumay">
                                            <img src="assets/images/avatar/4.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                            <img src="assets/images/avatar/5.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                            <img src="assets/images/avatar/6.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Explorer More">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                    </div>
                                </div>
                                <hr class="border-top-dashed">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-text avatar-lg bg-soft-success text-success border-soft-success rounded">
                                            <i class="feather-award"></i>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="fw-bold mb-2">Feature Sellers</a>
                                            <p class="fs-12 text-muted mb-0">10+ feature sellers</p>
                                        </div>
                                    </div>
                                    <div class="img-group lh-0 ms-2 justify-content-start">
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Janette Dalton">
                                            <img src="assets/images/avatar/2.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Michael Ksen">
                                            <img src="assets/images/avatar/3.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Socrates Itumay">
                                            <img src="assets/images/avatar/4.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                            <img src="assets/images/avatar/5.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                            <img src="assets/images/avatar/6.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Explorer More">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                    </div>
                                </div>
                                <hr class="border-top-dashed">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-text avatar-lg bg-soft-danger text-danger border-soft-danger rounded">
                                            <i class="feather-bar-chart-2"></i>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="fw-bold mb-2">Average Bestseller</a>
                                            <p class="fs-12 text-muted mb-0">10+ average bestseller</p>
                                        </div>
                                    </div>
                                    <div class="img-group lh-0 ms-2 justify-content-start">
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Janette Dalton">
                                            <img src="assets/images/avatar/2.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Michael Ksen">
                                            <img src="assets/images/avatar/3.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Socrates Itumay">
                                            <img src="assets/images/avatar/4.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                            <img src="assets/images/avatar/5.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                            <img src="assets/images/avatar/6.png" class="img-fluid" alt="image">
                                        </a>
                                        <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Explorer More">
                                            <i class="feather-more-horizontal"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Selling Status] end !-->
                    <!-- [Conversion Status] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full overflow-hidden">
                            <div class="bg-success text-white">
                                <div class="p-4 d-flex justify-content-between align-items-center">
                                    <h5 class="text-reset">Conversion Status</h5>
                                    <div class="dropdown">
                                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown">
                                            <div data-bs-toggle="tooltip" title="Options">
                                                <i class="feather-more-vertical"></i>
                                            </div>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end rounded-top">
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-at-sign"></i>New</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-calendar"></i>Event</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-bell"></i>Snoozed</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-trash-2"></i>Deleted</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-settings"></i>Settings</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-life-buoy"></i>Tips &amp; Tricks</a>
                                        </div>
                                    </div>
                                </div>
                                <div id="conversion-statistic-bar-chart"></div>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 bg-soft-primary text-primary text-center rounded">
                                            <i class="feather-airplay fs-3"></i>
                                            <h6 class="fs-13 text-reset mt-2">Weekly Sales</h6>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 bg-soft-warning text-warning text-center rounded">
                                            <i class="feather-layers fs-3"></i>
                                            <h6 class="fs-13 text-reset mt-2">Sales Progress</h6>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 bg-soft-danger text-danger text-center rounded">
                                            <i class="feather-briefcase fs-3"></i>
                                            <h6 class="fs-13 text-reset mt-2">New Projects</h6>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 bg-soft-success text-success text-center rounded">
                                            <i class="feather-shopping-cart fs-3"></i>
                                            <h6 class="fs-13 text-reset mt-2">Items Orders</h6>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Conversion Status] end !-->
                    <!-- [Traffic Source] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full overflow-hidden">
                            <div class="bg-danger text-white">
                                <div class="p-4 d-flex justify-content-between align-items-center">
                                    <h5 class="text-reset">Traffic Source</h5>
                                    <div class="dropdown">
                                        <a href="javascript:void(0);" class="avatar-text avatar-sm" data-bs-toggle="dropdown">
                                            <div data-bs-toggle="tooltip" title="Options">
                                                <i class="feather-more-vertical"></i>
                                            </div>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-end rounded-top">
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-at-sign"></i>New</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-calendar"></i>Event</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-bell"></i>Snoozed</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-trash-2"></i>Deleted</a>
                                            <div class="dropdown-divider"></div>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-settings"></i>Settings</a>
                                            <a href="javascript:void(0);" class="dropdown-item"><i class="feather-life-buoy"></i>Tips &amp; Tricks</a>
                                        </div>
                                    </div>
                                </div>
                                <div id="traffic-source-area-chart"></div>
                            </div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center border border-dashed border-soft-primary rounded position-relative">
                                            <div class="avatar-text avatar-md bg-soft-primary text-primary border-soft-primary position-absolute top-0 start-50 translate-middle">
                                                <i class="feather-airplay"></i>
                                            </div>
                                            <div>
                                                <div class="fs-12 text-muted mb-2">Organic Traffics</div>
                                                <h3>8,865</h3>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center border border-dashed border-soft-warning rounded position-relative">
                                            <div class="avatar-text avatar-md bg-soft-warning text-warning border-soft-warning position-absolute top-0 start-50 translate-middle">
                                                <i class="feather-layers"></i>
                                            </div>
                                            <div>
                                                <div class="fs-12 text-muted mb-2">Referral Traffics</div>
                                                <h3>6,579</h3>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center border border-dashed border-soft-danger rounded position-relative">
                                            <div class="avatar-text avatar-md bg-soft-danger text-danger border-soft-danger position-absolute top-0 start-50 translate-middle">
                                                <i class="feather-link-2"></i>
                                            </div>
                                            <div>
                                                <div class="fs-12 text-muted mb-2">Affiliates Traffics</div>
                                                <h3>5,865</h3>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center border border-dashed border-soft-success rounded position-relative">
                                            <div class="avatar-text avatar-md bg-soft-success text-success border-soft-success position-absolute top-0 start-50 translate-middle">
                                                <i class="feather-bookmark"></i>
                                            </div>
                                            <div>
                                                <div class="fs-12 text-muted mb-2">Others Traffics</div>
                                                <h3>2,354</h3>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Traffic Source] end !-->
                    <!-- [Total Sales] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full overflow-hidden">
                            <div class="bg-primary text-white">
                                <div class="p-4">
                                    <span class="badge bg-soft-primary text-primary text-dark float-end">12%</span>
                                    <div class="text-start">
                                        <h4 class="text-reset">3,569</h4>
                                        <p class="text-reset m-0">Total Sales</p>
                                    </div>
                                </div>
                                <div id="total-sales-color-graph"></div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="avatar-image avatar-lg rounded">
                                            <img class="img-fluid" src="assets/images/gallery/1.png" alt="">
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="d-block">Headphones JBL</a>
                                            <span class="fs-12 text-muted">Electronics </span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">$205</div>
                                        <div class="fs-12 text-end">5 sold</div>
                                    </div>
                                </div>
                                <hr class="border-dashed my-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="avatar-image avatar-lg rounded">
                                            <img class="img-fluid" src="assets/images/gallery/2.png" alt="">
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="d-block">Smart watch</a>
                                            <span class="fs-12 text-muted">Electronics </span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">$350</div>
                                        <div class="fs-12 text-end">6 sold</div>
                                    </div>
                                </div>
                                <hr class="border-dashed my-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="hstack gap-3">
                                        <div class="avatar-image avatar-lg rounded">
                                            <img class="img-fluid" src="assets/images/gallery/3.png" alt="">
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);" class="d-block">Hear Bud 202</a>
                                            <span class="fs-12 text-muted">Electronics </span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">$550</div>
                                        <div class="fs-12 text-end">7 sold</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Total Sales] end !-->
                    <!-- [Total Comment] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full overflow-hidden">
                            <div class="bg-danger text-white">
                                <div class="p-4">
                                    <span class="badge bg-soft-primary text-primary text-dark float-end">15%</span>
                                    <div class="text-start">
                                        <h4 class="text-reset">1,254</h4>
                                        <p class="text-reset m-0">Total Comment</p>
                                    </div>
                                </div>
                                <div id="total-comment-color-graph"></div>
                            </div>
                            <div class="card-body">
                                <div class="p-3 border border-dashed rounded-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="wd-50 ht-50 bg-soft-primary text-primary lh-1 d-flex align-items-center justify-content-center flex-column rounded-2 schedule-date">
                                                <span class="fs-18 fw-bold mb-1 d-block">20</span>
                                                <span class="fs-10 fw-semibold text-uppercase d-block">Dec</span>
                                            </div>
                                            <div class="text-dark">
                                                <a href="javascript:void(0);" class="mb-2 text-truncate-1-line">React Dashboard Design</a>
                                                <span class="fs-11 fw-normal text-muted text-truncate-1-line">11:30am - 12:30pm</span>
                                            </div>
                                        </div>
                                        <div class="img-group lh-0 ms-3 justify-content-start d-none d-sm-flex">
                                            <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Janette Dalton">
                                                <img src="assets/images/avatar/2.png" class="img-fluid" alt="image">
                                            </a>
                                            <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Michael Ksen">
                                                <img src="assets/images/avatar/3.png" class="img-fluid" alt="image">
                                            </a>
                                            <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Socrates Itumay">
                                                <img src="assets/images/avatar/4.png" class="img-fluid" alt="image">
                                            </a>
                                            <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                                <img src="assets/images/avatar/6.png" class="img-fluid" alt="image">
                                            </a>
                                            <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Explorer More">
                                                <i class="feather-more-horizontal"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3 border border-dashed rounded-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="wd-50 ht-50 bg-soft-success text-success lh-1 d-flex align-items-center justify-content-center flex-column rounded-2 schedule-date">
                                                <span class="fs-18 fw-bold mb-1 d-block">17</span>
                                                <span class="fs-10 fw-semibold text-uppercase d-block">Dec</span>
                                            </div>
                                            <div class="text-dark">
                                                <a href="javascript:void(0);" class="mb-2 text-truncate-1-line">Standup Team Meeting</a>
                                                <span class="fs-11 fw-normal text-muted text-truncate-1-line">8:00am - 9:00am</span>
                                            </div>
                                        </div>
                                        <div class="img-group lh-0 ms-3 justify-content-start d-none d-sm-flex">
                                            <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Janette Dalton">
                                                <img src="assets/images/avatar/2.png" class="img-fluid" alt="image">
                                            </a>
                                            <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Michael Ksen">
                                                <img src="assets/images/avatar/3.png" class="img-fluid" alt="image">
                                            </a>
                                            <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Socrates Itumay">
                                                <img src="assets/images/avatar/4.png" class="img-fluid" alt="image">
                                            </a>
                                            <a href="javascript:void(0)" class="avatar-image avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Marianne Audrey">
                                                <img src="assets/images/avatar/5.png" class="img-fluid" alt="image">
                                            </a>
                                            <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="tooltip" data-bs-trigger="hover" title="Explorer More">
                                                <i class="feather-more-horizontal"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <a href="javascript:void(0);" class="fs-13">Read More &rarr;</a>
                            </div>
                        </div>
                    </div>
                    <!-- [Total Comment] end !-->
                    <!-- [Income Status] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full overflow-hidden">
                            <div class="bg-success text-white">
                                <div class="p-4">
                                    <span class="badge bg-soft-primary text-primary text-dark float-end">20%</span>
                                    <div class="text-start">
                                        <h4 class="text-reset">$9.657</h4>
                                        <p class="text-reset m-0">Income Status</p>
                                    </div>
                                </div>
                                <div id="total-income-color-graph"></div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-text avatar-lg bg-soft-primary text-primary border-soft-primary rounded me-3">
                                            <i class="feather-airplay"></i>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);">Weekly Bestseller</a>
                                            <p class="fs-12 text-muted mb-0">Mark, Rowling, Esther</p>
                                        </div>
                                    </div>
                                    <div class="mt-2 mt-md-0 text-md-end mg-l-60 ms-md-0">
                                        <a href="javascript:void(0);" class="fw-bold d-block">$99,685</a>
                                        <span class="fs-12 text-muted">698 Sales</span>
                                    </div>
                                </div>
                                <hr class="border-dashed my-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-text avatar-lg bg-soft-success text-success border-soft-success rounded me-3">
                                            <i class="feather-award"></i>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);">Feature Sellers</a>
                                            <p class="fs-12 text-muted mb-0">Randy, Steve, Mike</p>
                                        </div>
                                    </div>
                                    <div class="mt-2 mt-md-0 text-md-end mg-l-60 ms-md-0">
                                        <a href="javascript:void(0);" class="fw-bold d-block">$95,685 </a>
                                        <span class="fs-12 text-muted">457 Sales</span>
                                    </div>
                                </div>
                                <hr class="border-dashed my-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-text avatar-lg bg-soft-danger text-danger border-soft-danger rounded me-3">
                                            <i class="feather-user-check"></i>
                                        </div>
                                        <div>
                                            <a href="javascript:void(0);">Popular Authors</a>
                                            <p class="fs-12 text-muted mb-0">John, Pat, Jimmy</p>
                                        </div>
                                    </div>
                                    <div class="mt-2 mt-md-0 text-md-end mg-l-60 ms-md-0">
                                        <a href="javascript:void(0);" class="fw-bold d-block">$90,759 </a>
                                        <span class="fs-12 text-muted">447 Sales</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Income Status] end !-->
                    <!-- [Earnings] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="p-4 d-flex align-items-start justify-content-between">
                                <div>
                                    <div class="fs-12 text-success fw-semibold mb-2">Earnings</div>
                                    <h4 class="text-success mb-2">(+) $55,236 USD</h4>
                                    <div class="fs-12 text-muted text-truncate-1-line">Earnings is 69% more than last month.</div>
                                </div>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="btn btn-sm btn-light-brand dropdown-toggle" data-bs-toggle="dropdown">2023</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="javascript:void(0);" class="dropdown-item active">2023</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2022</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2021</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2020</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2019</a>
                                        <div class="dropdown-divider"></div>
                                        <a href="javascript:void(0);" class="dropdown-item">All Times</a>
                                    </div>
                                </div>
                            </div>
                            <div id="earnings-card-area-chart"></div>
                            <div class="card-body bg-soft-success">
                                <div class="row g-4">
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Avarage Sale</div>
                                            <div class="fs-5 fw-bold text-dark">$850</div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Comissions</div>
                                            <div class="fs-5 fw-bold text-dark">$34,500</div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Revenue</div>
                                            <div class="fs-5 fw-bold text-dark">$68,000</div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Expenses</div>
                                            <div class="fs-5 fw-bold text-dark">$230,600</div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Earnings] end !-->
                    <!-- [Expenses] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="p-4 d-flex align-items-start justify-content-between">
                                <div>
                                    <div class="fs-12 text-danger fw-semibold mb-2">Expenses</div>
                                    <h4 class="text-danger mb-2">(-) $16,845 USD</h4>
                                    <div class="fs-12 text-muted text-truncate-1-line">Expenses is 47% more than last month.</div>
                                </div>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="btn btn-sm btn-light-brand dropdown-toggle" data-bs-toggle="dropdown">2023</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="javascript:void(0);" class="dropdown-item active">2023</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2022</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2021</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2020</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2019</a>
                                        <div class="dropdown-divider"></div>
                                        <a href="javascript:void(0);" class="dropdown-item">All Times</a>
                                    </div>
                                </div>
                            </div>
                            <div id="expense-card-area-chart"></div>
                            <div class="card-body bg-soft-danger">
                                <div class="row g-4">
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Avarage Sale</div>
                                            <div class="fs-5 fw-bold text-dark">$850</div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Comissions</div>
                                            <div class="fs-5 fw-bold text-dark">$34,500</div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Revenue</div>
                                            <div class="fs-5 fw-bold text-dark">$68,000</div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Expenses</div>
                                            <div class="fs-5 fw-bold text-dark">$230,600</div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Expenses] end !-->
                    <!-- [Revenue] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="p-4 d-flex align-items-start justify-content-between">
                                <div>
                                    <div class="fs-12 text-primary fw-semibold mb-2">Revenue</div>
                                    <h4 class="text-primary mb-2">(+) $96,753 USD</h4>
                                    <div class="fs-12 text-muted text-truncate-1-line">Earnings is 74% more than last month.</div>
                                </div>
                                <div class="dropdown">
                                    <a href="javascript:void(0);" class="btn btn-sm btn-light-brand dropdown-toggle" data-bs-toggle="dropdown">2023</a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a href="javascript:void(0);" class="dropdown-item active">2023</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2022</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2021</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2020</a>
                                        <a href="javascript:void(0);" class="dropdown-item">2019</a>
                                        <div class="dropdown-divider"></div>
                                        <a href="javascript:void(0);" class="dropdown-item">All Times</a>
                                    </div>
                                </div>
                            </div>
                            <div id="revenue-card-area-chart"></div>
                            <div class="card-body bg-soft-primary">
                                <div class="row g-4">
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Avarage Sale</div>
                                            <div class="fs-5 fw-bold text-dark">$850</div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Comissions</div>
                                            <div class="fs-5 fw-bold text-dark">$34,500</div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Revenue</div>
                                            <div class="fs-5 fw-bold text-dark">$68,000</div>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="javascript:void(0);" class="d-block p-4 text-center rounded border border-dashed">
                                            <div class="fs-12 text-muted">Expenses</div>
                                            <div class="fs-5 fw-bold text-dark">$230,600</div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Revenue] end !-->
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

    <!--! ================================================================ !-->
 
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
                <h6 class="fs-13 text-white">Project.zip</h6>
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
    <!--! END: Downloading Toast !-->
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
                            <input type="radio" class="btn-check" id="app-navigation-light" name="app-navigation" value="1" data-app-navigation="app-navigation-light" checked>
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-navigation-light">Light</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-navigation-dark" name="app-navigation" value="2" data-app-navigation="app-navigation-dark">
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
                            <input type="radio" class="btn-check" id="app-header-light" name="app-header" value="1" data-app-header="app-header-light" checked>
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-header-light">Light</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-header-dark" name="app-header" value="2" data-app-header="app-header-dark">
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
                            <input type="radio" class="btn-check" id="app-skin-light" name="app-skin" value="1" data-app-skin="app-skin-light">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-skin-light">Light</label>
                        </div>
                        <div class="col-6 text-center position-relative single-option dark-button">
                            <input type="radio" class="btn-check" id="app-skin-dark" name="app-skin" value="2" data-app-skin="app-skin-dark">
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
                            <input type="radio" class="btn-check" id="app-font-family-lato" name="font-family" value="1" data-font-family="app-font-family-lato">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-lato">Lato</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-rubik" name="font-family" value="2" data-font-family="app-font-family-rubik">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-rubik">Rubik</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-inter" name="font-family" value="3" data-font-family="app-font-family-inter" checked>
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-inter">Inter</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-cinzel" name="font-family" value="4" data-font-family="app-font-family-cinzel">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-cinzel">Cinzel</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-nunito" name="font-family" value="6" data-font-family="app-font-family-nunito">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-nunito">Nunito</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-roboto" name="font-family" value="7" data-font-family="app-font-family-roboto">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-roboto">Roboto</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-ubuntu" name="font-family" value="8" data-font-family="app-font-family-ubuntu">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-ubuntu">Ubuntu</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-poppins" name="font-family" value="9" data-font-family="app-font-family-poppins">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-poppins">Poppins</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-raleway" name="font-family" value="10" data-font-family="app-font-family-raleway">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-raleway">Raleway</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-system-ui" name="font-family" value="11" data-font-family="app-font-family-system-ui">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-system-ui">System UI</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-noto-sans" name="font-family" value="12" data-font-family="app-font-family-noto-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-noto-sans">Noto Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-fira-sans" name="font-family" value="13" data-font-family="app-font-family-fira-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-fira-sans">Fira Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-work-sans" name="font-family" value="14" data-font-family="app-font-family-work-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-work-sans">Work Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-open-sans" name="font-family" value="15" data-font-family="app-font-family-open-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-open-sans">Open Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-maven-pro" name="font-family" value="16" data-font-family="app-font-family-maven-pro">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-maven-pro">Maven Pro</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-quicksand" name="font-family" value="17" data-font-family="app-font-family-quicksand">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-quicksand">Quicksand</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-montserrat" name="font-family" value="18" data-font-family="app-font-family-montserrat">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-montserrat">Montserrat</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-josefin-sans" name="font-family" value="19" data-font-family="app-font-family-josefin-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-josefin-sans">Josefin Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-ibm-plex-sans" name="font-family" value="20" data-font-family="app-font-family-ibm-plex-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-ibm-plex-sans">IBM Plex Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-source-sans-pro" name="font-family" value="5" data-font-family="app-font-family-source-sans-pro">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-source-sans-pro">Source Sans Pro</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-montserrat-alt" name="font-family" value="21" data-font-family="app-font-family-montserrat-alt">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-montserrat-alt">Montserrat Alt</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-roboto-slab" name="font-family" value="22" data-font-family="app-font-family-roboto-slab">
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
                    <a href="https://www.themewagon.com/themes/BioTern-admin" target="_blank" class="btn btn-primary">Download</a>
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
    <script src="assets/vendors/js/circle-progress.min.js"></script>
    <script src="assets/vendors/js/apexcharts.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/widgets-miscellaneous-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!-- Theme Customizer removed -->
</body>

</html>

