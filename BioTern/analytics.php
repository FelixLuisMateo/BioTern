<?php
// Include database connection
include_once 'config/db.php';

// Initialize analytics variables with defaults
$bounce_rate = 0;
$page_views = 0;
$site_impressions = 0;
$conversion_rate = 0;
$active_students = 0;
$total_students = 0;

try {
    // Calculate bounce rate based on attendance rejection ratio
    $total_att = $conn->query("SELECT COUNT(*) as count FROM attendances");
    $total_attendances = $total_att ? (int)$total_att->fetch_assoc()['count'] : 0;
    
    $rejected_att = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'rejected'");
    $rejected_attendances = $rejected_att ? (int)$rejected_att->fetch_assoc()['count'] : 0;
    $bounce_rate = ($total_attendances > 0) ? round(($rejected_attendances / $total_attendances) * 100, 2) : 0;
    
    // Calculate page views based on active internships
    $total_int = $conn->query("SELECT COUNT(*) as count FROM internships");
    $total_internships = $total_int ? (int)$total_int->fetch_assoc()['count'] : 0;
    
    $active_int = $conn->query("SELECT COUNT(*) as count FROM internships WHERE status = 'ongoing'");
    $active_internships = $active_int ? (int)$active_int->fetch_assoc()['count'] : 0;
    $page_views = ($total_internships > 0) ? round(($active_internships / $total_internships) * 100, 2) : 0;
    
    // Calculate site impressions based on biometric registration
    $total_std = $conn->query("SELECT COUNT(*) as count FROM students WHERE deleted_at IS NULL");
    $total_students = $total_std ? (int)$total_std->fetch_assoc()['count'] : 0;
    
    $biometric_std = $conn->query("SELECT COUNT(*) as count FROM students WHERE biometric_registered = 1 AND deleted_at IS NULL");
    $biometric_students = $biometric_std ? (int)$biometric_std->fetch_assoc()['count'] : 0;
    $site_impressions = ($total_students > 0) ? round(($biometric_students / $total_students) * 100, 2) : 0;
    
    // Calculate conversion rate based on approved attendances
    $approved_att = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE status = 'approved'");
    $approved_attendances = $approved_att ? (int)$approved_att->fetch_assoc()['count'] : 0;
    $conversion_rate = ($total_attendances > 0) ? round(($approved_attendances / $total_attendances) * 100, 2) : 0;
    
    // Additional analytics for Goal Progress and other widgets
    // New students in last 30 days
    $new_std_q = $conn->query("SELECT COUNT(*) as count FROM students WHERE deleted_at IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $new_students_30 = $new_std_q ? (int)$new_std_q->fetch_assoc()['count'] : 0;

    // Coordinators and supervisors counts
    $coord_q = $conn->query("SELECT COUNT(*) as count FROM coordinators WHERE deleted_at IS NULL AND is_active = 1");
    $coordinators_count = $coord_q ? (int)$coord_q->fetch_assoc()['count'] : 0;
    $sup_q = $conn->query("SELECT COUNT(*) as count FROM supervisors WHERE deleted_at IS NULL AND is_active = 1");
    $supervisors_count = $sup_q ? (int)$sup_q->fetch_assoc()['count'] : 0;

    // Internship hours totals for OJT Goal
    $hours_q = $conn->query("SELECT COALESCE(SUM(required_hours),0) as total_required, COALESCE(SUM(rendered_hours),0) as total_rendered FROM internships WHERE deleted_at IS NULL");
    $total_required_hours = 0;
    $total_rendered_hours = 0;
    if ($hours_q) {
        $hrow = $hours_q->fetch_assoc();
        if ($hrow) {
            $total_required_hours = (int)$hrow['total_required'];
            $total_rendered_hours = (int)$hrow['total_rendered'];
        }
    }

    // Ensure totals exist to avoid division by zero elsewhere
    $total_students = isset($total_students) ? (int)$total_students : 0;
    $total_attendances = isset($total_attendances) ? (int)$total_attendances : 0;

    // Goal widgets: define goals and current values from DB
    $marketing_goal = 1250;
    $marketing_current = isset($new_students_30) ? (int)$new_students_30 : 0;
    $teams_goal = 1250;
    $teams_current = (isset($coordinators_count) ? (int)$coordinators_count : 0) + (isset($supervisors_count) ? (int)$supervisors_count : 0);
    $ojt_goal_hours = max(1, (int)$total_required_hours);
    $ojt_current_hours = (int)$total_rendered_hours;
    $revenue_goal = 12500;
    $revenue_current = (int)$total_students;
    // compute simple percentages for client-side progress if needed
    $marketing_progress = $marketing_goal > 0 ? round(min(100, ($marketing_current / $marketing_goal) * 100), 2) : 0;
    $teams_progress = $teams_goal > 0 ? round(min(100, ($teams_current / $teams_goal) * 100), 2) : 0;
    $ojt_progress = $ojt_goal_hours > 0 ? round(min(100, ($ojt_current_hours / $ojt_goal_hours) * 100), 2) : 0;
    $revenue_progress = $revenue_goal > 0 ? round(min(100, ($revenue_current / $revenue_goal) * 100), 2) : 0;
} catch (Exception $e) {
    // Database error - fallback to 0 values
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
    <title>BioTern || Analytics</title>
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
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/daterangepicker.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/jquery-jvectormap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/jquery.time-to.min.css">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
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
    <!--! END: Custom CSS-->
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
</head>

<body>
    <!--! Navigation (central include) -->
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
                                <a href="javascript:void(0);" class="fs-13 fw-semibold text-dark">Alls Notifications</a>
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
                        <h5 class="m-b-10">Dashboard</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item">Analytics</li>
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
                                <a class="btn btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
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
                    <!-- [KPI Cards] start -->
                    <div class="col-12">
                        <div class="row g-3 mb-4">
                            <div class="col-md-2 col-sm-6">
                                <div class="card p-3 text-center">
                                    <div class="fs-5 fw-bold"><?php echo number_format($total_students); ?></div>
                                    <div class="fs-12 text-muted">Total Students</div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="card p-3 text-center">
                                    <div class="fs-5 fw-bold"><?php echo number_format(isset($active_internships)?$active_internships:$active_internships= (isset($active_internships)?$active_internships: (isset($active_internships)?$active_internships:0))); ?></div>
                                    <div class="fs-12 text-muted">Active Internships</div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="card p-3 text-center">
                                    <div class="fs-5 fw-bold"><?php echo number_format($total_attendances); ?></div>
                                    <div class="fs-12 text-muted">Total Attendances</div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="card p-3 text-center">
                                    <div class="fs-5 fw-bold"><?php echo number_format($approved_attendances); ?></div>
                                    <div class="fs-12 text-muted">Approved Attendances</div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="card p-3 text-center">
                                    <div class="fs-5 fw-bold"><?php echo number_format($rejected_attendances); ?></div>
                                    <div class="fs-12 text-muted">Rejected Attendances</div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="card p-3 text-center">
                                    <div class="fs-5 fw-bold"><?php echo number_format(isset($biometric_students)?$biometric_students:0); ?></div>
                                    <div class="fs-12 text-muted">Biometric Registered</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [KPI Cards] end -->
                    <!-- [Mini Card] start -->
                    <div class="col-12">
                        <div class="card stretch stretch-full">
                            <div class="card-body">
                                <div class="hstack justify-content-between mb-4 pb-">
                                    <div>
                                        <h5 class="mb-1">Email Reports</h5>
                                        <span class="fs-12 text-muted">Email Campaign Reports</span>
                                    </div>
                                    <a href="javascript:void(0);" class="btn btn-light-brand">View Alls</a>
                                </div>
                                <div class="row">
                                <?php
                                // Build student-centric metrics for the Email Reports area
                                $erp_total_students = isset($total_students) ? (int)$total_students : 0;
                                // students with non-empty email
                                $q_email = $conn->query("SELECT COUNT(*) as cnt FROM students WHERE deleted_at IS NULL AND COALESCE(email, '') <> ''");
                                $erp_with_email = $q_email ? (int)$q_email->fetch_assoc()['cnt'] : 0;
                                // biometric registered students (already computed above as $biometric_students)
                                $erp_biometric = isset($biometric_students) ? (int)$biometric_students : 0;
                                // new students in last 30 days (already computed as $new_students_30)
                                $erp_new30 = isset($new_students_30) ? (int)$new_students_30 : 0;
                                // students attended today (distinct students in attendances for today)
                                $q_att_today = $conn->query("SELECT COUNT(DISTINCT student_id) as cnt FROM attendances WHERE DATE(attendance_date) = CURDATE()");
                                if (! $q_att_today) {
                                    // fallback to common column names
                                    $q_att_today = $conn->query("SELECT COUNT(DISTINCT student_id) as cnt FROM attendances WHERE DATE(log_time) = CURDATE()");
                                }
                                $erp_att_today = $q_att_today ? (int)$q_att_today->fetch_assoc()['cnt'] : 0;

                                // compute percentages relative to total students
                                $pct_with_email = $erp_total_students > 0 ? round(($erp_with_email / $erp_total_students) * 100, 2) : 0;
                                $pct_biometric = $erp_total_students > 0 ? round(($erp_biometric / $erp_total_students) * 100, 2) : 0;
                                $pct_new30 = $erp_total_students > 0 ? round(($erp_new30 / $erp_total_students) * 100, 2) : 0;
                                $pct_att_today = $erp_total_students > 0 ? round(($erp_att_today / $erp_total_students) * 100, 2) : 0;
                                ?>
                                <div class="row">
                                    <div class="col-xxl-2 col-lg-4 col-md-6">
                                        <div class="card stretch stretch-full border border-dashed border-gray-5">
                                            <div class="card-body rounded-3 text-center">
                                                <i class="bi bi-people fs-3 text-primary"></i>
                                                <div class="fs-4 fw-bolder text-dark mt-3 mb-1"><?php echo number_format($erp_total_students); ?></div>
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">Total Students</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-lg-4 col-md-6">
                                        <div class="card stretch stretch-full border border-dashed border-gray-5">
                                            <div class="card-body rounded-3 text-center">
                                                <i class="bi bi-envelope fs-3 text-warning"></i>
                                                <div class="fs-4 fw-bolder text-dark mt-3 mb-1"><?php echo number_format($erp_with_email); ?></div>
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">With Email (<?php echo $pct_with_email; ?>%)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-lg-4 col-md-6">
                                        <div class="card stretch stretch-full border border-dashed border-gray-5">
                                            <div class="card-body rounded-3 text-center">
                                                <i class="bi bi-person-check fs-3 text-success"></i>
                                                <div class="fs-4 fw-bolder text-dark mt-3 mb-1"><?php echo number_format($erp_biometric); ?></div>
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">Biometric Registered (<?php echo $pct_biometric; ?>%)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-lg-4 col-md-6">
                                        <div class="card stretch stretch-full border border-dashed border-gray-5">
                                            <div class="card-body rounded-3 text-center">
                                                <i class="bi bi-person-plus fs-3 text-indigo"></i>
                                                <div class="fs-4 fw-bolder text-dark mt-3 mb-1"><?php echo number_format($erp_new30); ?></div>
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">New (30d) (<?php echo $pct_new30; ?>%)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-lg-4 col-md-6">
                                        <div class="card stretch stretch-full border border-dashed border-gray-5">
                                            <div class="card-body rounded-3 text-center">
                                                <i class="bi bi-calendar-check fs-3 text-teal"></i>
                                                <div class="fs-4 fw-bolder text-dark mt-3 mb-1"><?php echo number_format($erp_att_today); ?></div>
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">Attended Today (<?php echo $pct_att_today; ?>%)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-lg-4 col-md-6">
                                        <div class="card stretch stretch-full border border-dashed border-gray-5">
                                            <div class="card-body rounded-3 text-center">
                                                <i class="bi bi-briefcase fs-3 text-danger"></i>
                                                <div class="fs-4 fw-bolder text-dark mt-3 mb-1"><?php echo number_format(isset($active_internships)?$active_internships:0); ?></div>
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">Active Internships</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Mini Card] end -->
                    <!-- [Visitors Overview] start -->
                    <div class="col-xxl-8">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Visitors Overview</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Collapse/Expand">
                                            <a href="#" class="avatar-text avatar-xs bg-gray-300" data-bs-toggle="collapse"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="#" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="#" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="#" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a href="#" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
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
                                <div id="visitors-overview-statistics-chart"></div>
                            </div>
                        </div>
                    </div>
                    <!-- [Visitors Overview] end -->
                    <!-- [Browser States] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Browser States</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Collapse/Expand">
                                            <a href="#" class="avatar-text avatar-xs bg-gray-300" data-bs-toggle="collapse"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="#" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="#" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="#" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a href="#" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
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
                                        <?php
                                        // Show internships status distribution instead of browser placeholders
                                        $status_counts = ['pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
                                        $total_interns = 0;
                                        $stq = $conn->query("SELECT status, COUNT(*) as cnt FROM internships WHERE deleted_at IS NULL GROUP BY status");
                                        if ($stq) {
                                            while ($r = $stq->fetch_assoc()) {
                                                $s = $r['status'] ?? 'other';
                                                $c = (int)($r['cnt'] ?? 0);
                                                if (array_key_exists($s, $status_counts)) $status_counts[$s] = $c;
                                                $total_interns += $c;
                                            }
                                        }
                                        $colors = ['pending' => 'bg-warning', 'ongoing' => 'bg-success', 'completed' => 'bg-primary', 'cancelled' => 'bg-danger'];
                                        foreach ($status_counts as $k => $v) {
                                            $pct = $total_interns > 0 ? round(($v / $total_interns) * 100, 2) : 0;
                                            $label = ucfirst($k);
                                            $barClass = $colors[$k] ?? 'bg-dark';
                                            echo "<tr>\n";
                                            echo "<td><a href=\"ojt.php\"><span>{$label}</span></a></td>\n";
                                            echo "<td><span class=\"text-end d-flex align-items-center m-0\"><span class=\"me-3\">{$pct}%</span><span class=\"progress w-100 ht-5\"><span class=\"progress-bar {$barClass}\" style=\"width: {$pct}%\"></span></span></span></td>\n";
                                            echo "</tr>\n";
                                        }
                                        ?>
                                    </table>
                                </div>
                                <div class="p-3">
                                    <div id="internship-pie-chart" style="height:240px;"></div>
                                    <div class="d-flex justify-content-around mt-3">
                                        <div class="text-center">
                                            <div class="fs-5 fw-bold"><?php echo number_format($status_counts['completed'] ?? 0); ?></div>
                                            <div class="fs-12 text-muted">Completed</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="fs-5 fw-bold"><?php echo number_format($status_counts['ongoing'] ?? 0); ?></div>
                                            <div class="fs-12 text-muted">Ongoing</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="fs-5 fw-bold"><?php echo number_format($status_counts['pending'] ?? 0); ?></div>
                                            <div class="fs-12 text-muted">Pending</div>
                                        </div>
                                        <div class="text-center">
                                            <div class="fs-5 fw-bold"><?php echo number_format($status_counts['cancelled'] ?? 0); ?></div>
                                            <div class="fs-12 text-muted">Cancelled</div>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                // Prepare pie chart data for client-side
                                $pie_labels = array_map('ucfirst', array_keys($status_counts));
                                $pie_values = array_values($status_counts);
                                ?>
                                <script>
                                document.addEventListener('DOMContentLoaded', function(){
                                    var options = {
                                        chart: { type: 'pie', height: 240 },
                                        series: <?php echo json_encode($pie_values); ?>,
                                        labels: <?php echo json_encode($pie_labels); ?>,
                                        colors: ['#ffc107','#28a745','#007bff','#dc3545'],
                                        legend: { position: 'bottom' }
                                    };
                                    var chart = new ApexCharts(document.querySelector('#internship-pie-chart'), options);
                                    chart.render();
                                });
                                </script>
                            </div>
                            <a href="javascript:void(0);" class="card-footer fs-11 fw-bold text-uppercase text-center">Explore Details</a>
                        </div>
                    </div>
                    <!-- [Browser States] end -->
                    <!-- [Mini Card] start -->
                    <div class="col-xxl-3 col-md-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between p-4 mb-4">
                                    <div>
                                        <div class="fw-bold mb-2 text-dark text-truncate-1-line">Attendance Rejection Rate</div>
                                        <div class="fs-11 text-muted">Based on attendance records</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fs-24 fw-bold mb-2 text-dark"><span class="counter"><?php echo $bounce_rate; ?></span>%</div>
                                        <div class="fs-11 text-danger">(Rejected)</div>
                                    </div>
                                </div>
                                <div id="bounce-rate"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-md-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between p-4 mb-4">
                                    <div>
                                        <div class="fw-bold mb-2 text-dark text-truncate-1-line">Active Internships Rate</div>
                                        <div class="fs-11 text-muted">Ongoing vs Total internships</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fs-24 fw-bold mb-2 text-dark"><span class="counter"><?php echo $page_views; ?></span>%</div>
                                        <div class="fs-11 text-success">(Active)</div>
                                    </div>
                                </div>
                                <div id="page-views"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-md-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between p-4 mb-4">
                                    <div>
                                        <div class="fw-bold mb-2 text-dark text-truncate-1-line">Biometric Registration Rate</div>
                                        <div class="fs-11 text-muted">Registered vs Total students</div>
                                    </div>
                                    <div class="tx-right">
                                        <div class="fs-24 fw-bold mb-2 text-dark"><span class="counter"><?php echo $site_impressions; ?></span>%</div>
                                        <div class="fs-11 text-success">(Registered)</div>
                                    </div>
                                </div>
                                <div id="site-impressions"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-md-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between p-4 mb-4">
                                    <div>
                                        <div class="fw-bold mb-2 text-dark text-truncate-1-line">Attendance Approval Rate</div>
                                        <div class="fs-11 text-muted">Approved vs Total records</div>
                                    </div>
                                    <div class="tx-right">
                                        <div class="fs-24 fw-bold mb-2 text-dark"><span class="counter"><?php echo $conversion_rate; ?></span>%</div>
                                        <div class="fs-11 text-success">(Approved)</div>
                                    </div>
                                </div>
                                <div id="conversions-rate"></div>
                            </div>
                        </div>
                    </div>
                    <!-- [Mini Card] end -->
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
                                            <h2 class="fs-13 tx-spacing-1">Marketing Goal</h2>
                                            <div class="fs-11 text-muted text-truncate-1-line"><?php echo $marketing_current; ?>/<?php echo $marketing_goal; ?> Users (<?php echo $marketing_progress; ?>%)</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="px-4 py-3 text-center border border-dashed rounded-3">
                                            <div class="mx-auto mb-4">
                                                <div class="goal-progress-2"></div>
                                            </div>
                                            <h2 class="fs-13 tx-spacing-1">Teams Goal</h2>
                                            <div class="fs-11 text-muted text-truncate-1-line"><?php echo $teams_current; ?>/<?php echo $teams_goal; ?> Members (<?php echo $teams_progress; ?>%)</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="px-4 py-3 text-center border border-dashed rounded-3">
                                            <div class="mx-auto mb-4">
                                                <div class="goal-progress-3"></div>
                                            </div>
                                            <h2 class="fs-13 tx-spacing-1">OJT Goal</h2>
                                            <div class="fs-11 text-muted text-truncate-1-line"><?php echo $ojt_current_hours; ?>/<?php echo $ojt_goal_hours; ?> hrs (<?php echo $ojt_progress; ?>%)</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="px-4 py-3 text-center border border-dashed rounded-3">
                                            <div class="mx-auto mb-4">
                                                <div class="goal-progress-4"></div>
                                            </div>
                                            <h2 class="fs-13 tx-spacing-1">Revenue Goal</h2>
                                            <div class="fs-11 text-muted text-truncate-1-line"><?php echo $revenue_current; ?>/<?php echo $revenue_goal; ?> (<?php echo $revenue_progress; ?>%)</div>
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
                    <!-- [Marketing Campaign] start -->
                    <div class="col-xxl-8">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Marketing Campaign</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn">
                                        <div data-bs-toggle="tooltip" title="Collapse/Expand">
                                            <a href="#" class="avatar-text avatar-xs bg-gray-300" data-bs-toggle="collapse"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Delete">
                                            <a href="#" class="avatar-text avatar-xs bg-danger" data-bs-toggle="remove"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Refresh">
                                            <a href="#" class="avatar-text avatar-xs bg-warning" data-bs-toggle="refresh"> </a>
                                        </div>
                                        <div data-bs-toggle="tooltip" title="Maximize/Minimize">
                                            <a href="#" class="avatar-text avatar-xs bg-success" data-bs-toggle="expand"> </a>
                                        </div>
                                    </div>
                                    <div class="dropdown">
                                        <a href="#" class="avatar-text avatar-sm" data-bs-toggle="dropdown" data-bs-offset="25, 25">
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
                                <div id="campaign-alytics-bar-chart"></div>
                            </div>
                            <div class="card-footer">
                                <div class="row g-4">
                                    <?php
                                    // Use previously calculated analytics percentages when available
                                    $reach_count = isset($total_students) ? (int)$total_students : 0;
                                    $opened_pct = isset($site_impressions) ? $site_impressions : 0; // biometric registration %
                                    $clicked_pct = isset($page_views) ? $page_views : 0; // active internships %
                                    $conversion_pct = isset($conversion_rate) ? $conversion_rate : 0; // attendance approval %
                                    // Normalize widths to 0-100
                                    $w_opened = max(0, min(100, round($opened_pct)));
                                    $w_clicked = max(0, min(100, round($clicked_pct)));
                                    $w_conversion = max(0, min(100, round($conversion_pct)));
                                    ?>
                                    <div class="col-lg-3">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">Reach</div>
                                            <h6 class="fw-bold text-dark"><?php echo number_format($reach_count); ?></h6>
                                            <div class="progress mt-2 ht-3">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo ($reach_count>0? min(100, round(($reach_count/ max(1,$reach_count))*100)):0); ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">Opened</div>
                                            <h6 class="fw-bold text-dark"><?php echo $opened_pct; ?>%</h6>
                                            <div class="progress mt-2 ht-3">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $w_opened; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">Clicked</div>
                                            <h6 class="fw-bold text-dark"><?php echo $clicked_pct; ?>%</h6>
                                            <div class="progress mt-2 ht-3">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $w_clicked; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">Conversion</div>
                                            <h6 class="fw-bold text-dark"><?php echo $conversion_pct; ?>%</h6>
                                            <div class="progress mt-2 ht-3">
                                                <div class="progress-bar bg-dark" role="progressbar" style="width: <?php echo $w_conversion; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Marketing Campaign] end -->
                    <!-- [Project Remainders] start -->
                    <div class="col-xxl-8">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Project Remainders</h5>
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
                                            <tr>
                                                <th scope="col">Name</th>
                                                <th scope="col">Status</th>
                                                <th scope="col">Remaining</th>
                                                <th scope="col">Stage</th>
                                                <th scope="col" class="text-end">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <div class="hstack gap-2">
                                                        <span class="wd-10 ht-10 bg-gray-400 rounded-circle d-inline-block me-2 lh-base"></span>
                                                        <div class="border-3 border-start rounded ps-3">
                                                            <a href="javascript:void(0);" class="mb-2 d-block">
                                                                <span>Overview Home Redesign</span>
                                                            </a>
                                                            <p class="fs-12 text-muted mb-0">Management of project under "BioTern" brand</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-soft-primary text-primary">In Prograss</span>
                                                </td>
                                                <td>
                                                    <div data-time-countdown="countdown_1"></div>
                                                </td>
                                                <td>
                                                    <div class="hstack gap-1">
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-warning rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-warning rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-gray-300 rounded-pill"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="javascript:void(0);" class="avatar-text avatar-md ms-auto">
                                                        <i class="feather-arrow-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="hstack gap-2">
                                                        <span class="wd-10 ht-10 bg-gray-400 rounded-circle d-inline-block me-2 lh-base"></span>
                                                        <div class="border-3 border-start rounded ps-3">
                                                            <a href="javascript:void(0);" class="mb-2 d-block">
                                                                <span>BioTern Overview Admin Project</span>
                                                            </a>
                                                            <p class="fs-12 text-muted mb-0">BioTern Overview Home Project</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-soft-info text-info">Updading</span>
                                                </td>
                                                <td>
                                                    <div data-time-countdown="countdown_2"></div>
                                                </td>
                                                <td>
                                                    <div class="hstack gap-1">
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-warning rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-warning rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-gray-300 rounded-pill"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="javascript:void(0);" class="avatar-text avatar-md ms-auto">
                                                        <i class="feather-arrow-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="hstack gap-2">
                                                        <span class="wd-10 ht-10 bg-gray-400 rounded-circle d-inline-block me-2 lh-base"></span>
                                                        <div class="border-3 border-start rounded ps-3">
                                                            <a href="javascript:void(0);" class="mb-2 d-block">
                                                                <span>Website Redesign for Nike</span>
                                                            </a>
                                                            <p class="fs-12 text-muted mb-0">Website Redesign for Nike</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-soft-danger text-danger">Upcoming</span>
                                                </td>
                                                <td>
                                                    <div data-time-countdown="countdown_3"></div>
                                                </td>
                                                <td>
                                                    <div class="hstack gap-1">
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-warning rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-warning rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-gray-300 rounded-pill"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="javascript:void(0);" class="avatar-text avatar-md ms-auto">
                                                        <i class="feather-arrow-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="hstack gap-2">
                                                        <span class="wd-10 ht-10 bg-gray-400 rounded-circle d-inline-block me-2 lh-base"></span>
                                                        <div class="border-3 border-start rounded ps-3">
                                                            <a href="javascript:void(0);" class="mb-2 d-block">
                                                                <span>BioTern Overview Home Project</span>
                                                            </a>
                                                            <p class="fs-12 text-muted mb-0">BioTern Overview Home Project</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-soft-teal text-teal">Submitted</span>
                                                </td>
                                                <td>
                                                    <div data-time-countdown="countdown_4"></div>
                                                </td>
                                                <td>
                                                    <div class="hstack gap-1">
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-warning rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-warning rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-gray-300 rounded-pill"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="javascript:void(0);" class="avatar-text avatar-md ms-auto">
                                                        <i class="feather-arrow-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>
                                                    <div class="hstack gap-2">
                                                        <span class="wd-10 ht-10 bg-gray-400 rounded-circle d-inline-block me-2 lh-base"></span>
                                                        <div class="border-3 border-start rounded ps-3">
                                                            <a href="javascript:void(0);" class="mb-2 d-block">
                                                                <span>Update User Flows with UX Feedback</span>
                                                            </a>
                                                            <p class="fs-12 text-muted mb-0">Update User Flows with UX Feedback</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-soft-warning text-warning">Working</span>
                                                </td>
                                                <td>
                                                    <div data-time-countdown="countdown_5"></div>
                                                </td>
                                                <td>
                                                    <div class="hstack gap-1">
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-success rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-warning rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-warning rounded-pill opacity-75"></div>
                                                        <div class="wd-15 ht-4 bg-gray-300 rounded-pill"></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="javascript:void(0);" class="avatar-text avatar-md ms-auto">
                                                        <i class="feather-arrow-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-footer">
                                    <ul class="list-unstyled d-flex align-items-center gap-2 mb-0 pagination-common-style">
                                        <li>
                                            <a href="javascript:void(0);"><i class="bi bi-arrow-left"></i></a>
                                        </li>
                                        <li><a href="javascript:void(0);" class="active">1</a></li>
                                        <li><a href="javascript:void(0);">2</a></li>
                                        <li>
                                            <a href="javascript:void(0);"><i class="bi bi-dot"></i></a>
                                        </li>
                                        <li><a href="javascript:void(0);">8</a></li>
                                        <li><a href="javascript:void(0);">9</a></li>
                                        <li>
                                            <a href="javascript:void(0);"><i class="bi bi-arrow-right"></i></a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Project Remainders] end -->
                    <!-- [Social Statistics] start -->
                    <div class="col-xxl-4">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h5 class="card-title">Social Statistics</h5>
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
                                <?php
                                // Build simple DB-driven social stats summary
                                $total_students = isset($total_students) ? (int)$total_students : 0;
                                $biometric_students = isset($biometric_students) ? (int)$biometric_students : 0;
                                $total_internships = isset($total_internships) ? (int)$total_internships : 0;
                                $active_internships = isset($active_internships) ? (int)$active_internships : 0;
                                $biometric_pct = $total_students > 0 ? round(($biometric_students / $total_students) * 100, 2) : 0;
                                $internship_active_pct = $total_internships > 0 ? round(($active_internships / $total_internships) * 100, 2) : 0;
                                ?>
                                <div class="row g-3 text-center">
                                    <div class="col-6">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">Students</div>
                                            <h6 class="fw-bold text-dark"><?php echo number_format($total_students); ?></h6>
                                            <div class="fs-11 text-muted"><?php echo $biometric_pct; ?>% Biometric</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">OJT Internships</div>
                                            <h6 class="fw-bold text-dark"><?php echo number_format($total_internships); ?></h6>
                                            <div class="fs-11 text-muted"><?php echo $internship_active_pct; ?>% Active</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <a href="javascript:void(0);" class="card-footer fs-11 fw-bold text-uppercase text-center">Explore Details</a>
                        </div>
                    </div>
                    <!-- [Social Statistics] end -->
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
    <script src="assets/vendors/js/daterangepicker.min.js"></script>
    <script src="assets/vendors/js/apexcharts.min.js"></script>
    <script src="assets/vendors/js/jquery.time-to.min.js "></script>
    <script src="assets/vendors/js/circle-progress.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/analytics-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!--! BEGIN: Theme Customizer  !-->
    <script src="assets/js/theme-customizer-init.min.js"></script>
    <!--! END: Theme Customizer !-->
</body>

</html>

