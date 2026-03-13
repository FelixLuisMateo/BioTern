<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$current_user_email = trim((string)($_SESSION['email'] ?? 'admin@biotern.local'));
$current_user_role_badge = trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
$current_profile_rel = ltrim(str_replace('\\', '/', trim((string)($_SESSION['profile_picture'] ?? ''))), '/');
$current_profile_img = 'assets/images/avatar/' . (($current_user_id > 0 ? ($current_user_id % 5) : 0) + 1) . '.png';
if ($current_profile_rel !== '' && file_exists(dirname(__DIR__) . '/' . $current_profile_rel)) {
    $current_profile_img = $current_profile_rel;
}

// Include database connection
include_once dirname(__DIR__) . '/config/db.php';

if (!function_exists('format_pct')) {
    function format_pct($value)
    {
        return number_format((float)$value, 2) . '%';
    }
}

// Initialize analytics variables with defaults
$bounce_rate = 0;
$page_views = 0;
$site_impressions = 0;
$conversion_rate = 0;
$active_students = 0;
$total_students = 0;

$total_attendances = 0;
$rejected_attendances = 0;
$approved_attendances = 0;
$pending_attendances = 0;
$total_internships = 0;
$active_internships = 0;
$completed_internships = 0;
$internal_internships = 0;
$external_internships = 0;
$biometric_students = 0;
$students_with_email = 0;
$new_students_30 = 0;
$coordinators_count = 0;
$supervisors_count = 0;
$total_required_hours = 0;
$total_rendered_hours = 0;

$attendance_pending_rate = 0;
$completed_internships_rate = 0;

$status_counts = ['pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
$total_interns = 0;

$unread_notifications = 0;
$latest_notifications = [];

$engagement_labels = [];
$engagement_values = [];

$visitors_labels = [];
$visitors_students = [];
$visitors_attendances = [];
$visitors_internships = [];

$campaign_labels = [];
$campaign_internal = [];
$campaign_external = [];

$sparkline_bounce = [];
$sparkline_active = [];
$sparkline_biometric = [];
$sparkline_approval = [];

$project_remainders = [];

// Build a stable last-12-month keyset for campaign chart aggregation.
$campaign_keys = [];
for ($i = 11; $i >= 0; $i--) {
    $month_ts = strtotime("-{$i} months");
    $campaign_keys[] = date('Y-m', $month_ts);
    $campaign_labels[] = date('M y', $month_ts);
}

// Provide baseline labels for visitors chart so frontend render stays stable.
$visitors_labels = $campaign_labels;
if (empty($visitors_students)) {
    $visitors_students = array_fill(0, count($visitors_labels), 0);
}
if (empty($visitors_attendances)) {
    $visitors_attendances = array_fill(0, count($visitors_labels), 0);
}
if (empty($visitors_internships)) {
    $visitors_internships = array_fill(0, count($visitors_labels), 0);
}

try {

    $campaign_internal_map = array_fill_keys($campaign_keys, 0);
    $campaign_external_map = array_fill_keys($campaign_keys, 0);
    $start_12m = date('Y-m-01', strtotime('-11 months'));

    $q_campaign = $conn->query("SELECT DATE_FORMAT(start_date, '%Y-%m') AS ym, type, COUNT(*) AS cnt FROM internships WHERE deleted_at IS NULL AND start_date >= '{$start_12m}' GROUP BY ym, type");
    if ($q_campaign) {
        while ($row = $q_campaign->fetch_assoc()) {
            $ym = $row['ym'];
            $type = $row['type'];
            $cnt = (int)$row['cnt'];
            if ($type === 'internal' && isset($campaign_internal_map[$ym])) {
                $campaign_internal_map[$ym] = $cnt;
            }
            if ($type === 'external' && isset($campaign_external_map[$ym])) {
                $campaign_external_map[$ym] = $cnt;
            }
        }
    }
    foreach ($campaign_keys as $key) {
        $campaign_internal[] = $campaign_internal_map[$key];
        $campaign_external[] = $campaign_external_map[$key];
    }

    // Sparkline series (last 9 days)
    $day_keys = [];
    for ($i = 8; $i >= 0; $i--) {
        $day_keys[] = date('Y-m-d', strtotime("-{$i} days"));
    }

    $att_daily = [];
    $rejected_daily = [];
    $approved_daily = [];
    $q_daily_att = $conn->query("SELECT attendance_date AS dt, COUNT(*) AS total_cnt, SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_cnt, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_cnt FROM attendances WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 8 DAY) GROUP BY attendance_date");
    if ($q_daily_att) {
        while ($row = $q_daily_att->fetch_assoc()) {
            $dt = $row['dt'];
            $att_daily[$dt] = (int)$row['total_cnt'];
            $rejected_daily[$dt] = (int)$row['rejected_cnt'];
            $approved_daily[$dt] = (int)$row['approved_cnt'];
        }
    }

    $new_students_daily = [];
    $q_students_daily = $conn->query("SELECT DATE(created_at) AS dt, COUNT(*) AS cnt FROM students WHERE deleted_at IS NULL AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 8 DAY) GROUP BY DATE(created_at)");
    if ($q_students_daily) {
        while ($row = $q_students_daily->fetch_assoc()) {
            $new_students_daily[$row['dt']] = (int)$row['cnt'];
        }
    }

    $biometric_daily = [];
    $q_bio_daily = $conn->query("SELECT DATE(COALESCE(biometric_registered_at, created_at)) AS dt, COUNT(*) AS cnt FROM students WHERE deleted_at IS NULL AND biometric_registered = 1 AND DATE(COALESCE(biometric_registered_at, created_at)) >= DATE_SUB(CURDATE(), INTERVAL 8 DAY) GROUP BY DATE(COALESCE(biometric_registered_at, created_at))");
    if ($q_bio_daily) {
        while ($row = $q_bio_daily->fetch_assoc()) {
            $biometric_daily[$row['dt']] = (int)$row['cnt'];
        }
    }

    $intern_total_daily = [];
    $intern_ongoing_daily = [];
    $q_intern_daily = $conn->query("SELECT DATE(created_at) AS dt, COUNT(*) AS total_cnt, SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_cnt FROM internships WHERE deleted_at IS NULL AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 8 DAY) GROUP BY DATE(created_at)");
    if ($q_intern_daily) {
        while ($row = $q_intern_daily->fetch_assoc()) {
            $dt = $row['dt'];
            $intern_total_daily[$dt] = (int)$row['total_cnt'];
            $intern_ongoing_daily[$dt] = (int)$row['ongoing_cnt'];
        }
    }

    foreach ($day_keys as $dt) {
        $day_total_att = $att_daily[$dt] ?? 0;
        $day_rejected = $rejected_daily[$dt] ?? 0;
        $day_approved = $approved_daily[$dt] ?? 0;
        $day_new_students = $new_students_daily[$dt] ?? 0;
        $day_biometric = $biometric_daily[$dt] ?? 0;
        $day_total_intern = $intern_total_daily[$dt] ?? 0;
        $day_ongoing_intern = $intern_ongoing_daily[$dt] ?? 0;

        $sparkline_bounce[] = $day_total_att > 0 ? round(($day_rejected / $day_total_att) * 100, 2) : 0;
        $sparkline_approval[] = $day_total_att > 0 ? round(($day_approved / $day_total_att) * 100, 2) : 0;
        $sparkline_biometric[] = $day_new_students > 0 ? round(($day_biometric / $day_new_students) * 100, 2) : 0;
        $sparkline_active[] = $day_total_intern > 0 ? round(($day_ongoing_intern / $day_total_intern) * 100, 2) : 0;
    }

    // Project remainders table data (latest internships)
    $q_projects = $conn->query("SELECT i.id, i.student_id, i.company_name, i.status, i.required_hours, i.rendered_hours, i.completion_percentage, s.first_name, s.last_name FROM internships i LEFT JOIN students s ON s.id = i.student_id WHERE i.deleted_at IS NULL ORDER BY i.updated_at DESC LIMIT 5");
    if ($q_projects) {
        while ($row = $q_projects->fetch_assoc()) {
            $required = (int)($row['required_hours'] ?? 0);
            $rendered = (int)($row['rendered_hours'] ?? 0);
            $remaining = max(0, $required - $rendered);
            $completion = (float)($row['completion_percentage'] ?? 0);
            if ($required > 0 && $completion <= 0) {
                $completion = round(($rendered / $required) * 100, 2);
            }
            $project_remainders[] = [
                'id' => (int)$row['id'],
                'name' => !empty($row['company_name']) ? $row['company_name'] : trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'status' => (string)($row['status'] ?? 'pending'),
                'required_hours' => $required,
                'rendered_hours' => $rendered,
                'remaining_hours' => $remaining,
                'completion' => max(0, min(100, $completion))
            ];
        }
    }

    // Internship status distribution for table and pie chart
    $stq = $conn->query("SELECT status, COUNT(*) as cnt FROM internships WHERE deleted_at IS NULL GROUP BY status");
    if ($stq) {
        while ($r = $stq->fetch_assoc()) {
            $s = $r['status'] ?? 'other';
            $c = (int)($r['cnt'] ?? 0);
            if (array_key_exists($s, $status_counts)) {
                $status_counts[$s] = $c;
            }
            $total_interns += $c;
        }
    }

    // Notifications summary for header dropdown
    $q_unread = $conn->query("SELECT COUNT(*) AS cnt FROM notifications WHERE is_read = 0 AND deleted_at IS NULL");
    $unread_notifications = $q_unread ? (int)$q_unread->fetch_assoc()['cnt'] : 0;

    $q_latest_notifications = $conn->query("SELECT n.title, n.message, n.created_at, n.is_read, u.name AS user_name FROM notifications n LEFT JOIN users u ON u.id = n.user_id WHERE n.deleted_at IS NULL ORDER BY n.created_at DESC LIMIT 5");
    if ($q_latest_notifications) {
        while ($row = $q_latest_notifications->fetch_assoc()) {
            $latest_notifications[] = [
                'title' => (string)($row['title'] ?? 'Notification'),
                'message' => (string)($row['message'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'is_read' => (int)($row['is_read'] ?? 0),
                'user_name' => (string)($row['user_name'] ?? 'System')
            ];
        }
    }

    // Summary chart data: operational percentages
    $engagement_labels = ['With Email', 'Biometric', 'Approved Attendance', 'Active Internship'];
    $engagement_values = [
        round($total_students > 0 ? ($students_with_email / $total_students) * 100 : 0, 2),
        round($site_impressions, 2),
        round($conversion_rate, 2),
        round($page_views, 2)
    ];
} catch (Exception $e) {
    // Database error - fallback to 0 values
}
?>
<?php
$page_title = 'BioTern || Analytics';
$page_body_class = 'page-analytics';
$page_styles = array(
    'assets/vendors/css/daterangepicker.min.css',
    'assets/vendors/css/jquery-jvectormap.min.css',
    'assets/vendors/css/jquery.time-to.min.css',
    'assets/css/homepage-dashboard.css',
);
$page_vendor_scripts = array(
    'assets/vendors/js/daterangepicker.min.js',
    'assets/vendors/js/apexcharts.min.js',
    'assets/vendors/js/jquery.time-to.min.js',
    'assets/vendors/js/circle-progress.min.js',
);
$page_scripts = array(
    'assets/js/analytics-page-runtime.js',
    'assets/js/theme-customizer-init.min.js',
);
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<style>
    .analytics-hero .card-body {
        background: linear-gradient(95deg, rgba(var(--bs-primary-rgb), 0.95), rgba(31, 90, 166, 0.84));
    }

    .analytics-chart-panel {
        border: 1px dashed rgba(0, 0, 0, 0.08);
        border-radius: 12px;
        padding: 10px;
    }
</style>

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
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-soft-primary text-primary fs-11">
                            <i class="feather-calendar me-1"></i> <?php echo date('M d, Y'); ?>
                        </span>
                        <div id="reportrange" class="reportrange-picker d-flex align-items-center btn btn-sm btn-light-brand">
                            <span class="reportrange-picker-field"></span>
                        </div>
                        <a href="reports-timesheets.php" class="btn btn-sm btn-light-brand">
                            <i class="feather-file-text me-1"></i> Reports
                        </a>
                        <a href="attendance.php" class="btn btn-sm btn-primary">
                            <i class="feather-check-square me-1"></i> Review Attendance
                        </a>
                    </div>
                </div>
            </div>
            <!-- [ page-header ] end -->
            <!-- [ Main Content ] start -->
                <div class="main-content dashboard-shell">
                <div class="row">
                    <div class="col-12">
                        <div class="card stretch stretch-full overflow-hidden dashboard-hero analytics-hero mb-3">
                            <div class="card-body text-white p-4">
                                <div class="row align-items-center g-3">
                                    <div class="col-lg-8">
                                        <span class="badge bg-light text-primary mb-2">Analytics Overview</span>
                                        <h4 class="text-reset mb-2">BioTern Performance Dashboard</h4>
                                        <p class="mb-0 text-reset opacity-75">Live operational metrics, trends, and completion rates in one place.</p>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                            <a href="students.php" class="btn btn-light btn-sm">
                                                <i class="feather-users me-1"></i> Students
                                            </a>
                                            <a href="ojt.php" class="btn btn-outline-light btn-sm">
                                                <i class="feather-briefcase me-1"></i> OJT Management
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [KPI Cards] start -->
                    <div class="col-12">
                        <div class="row g-3 mb-4 align-items-stretch">
                            <div class="col-md-2 col-sm-6">
                                <div class="card h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Total Students</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo number_format($total_students); ?></h4>
                                        <span class="badge bg-soft-primary text-primary"><?php echo number_format($students_with_email); ?> with email</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="card h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Active Internships</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo number_format($active_internships); ?></h4>
                                        <span class="badge bg-soft-info text-info"><?php echo format_pct($page_views); ?> ongoing ratio</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="card h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Total Attendances</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo number_format($total_attendances); ?></h4>
                                        <span class="badge bg-soft-warning text-warning"><?php echo format_pct($attendance_pending_rate); ?> pending</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="card h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Approved Attendance</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo number_format($approved_attendances); ?></h4>
                                        <span class="badge bg-soft-success text-success"><?php echo format_pct($conversion_rate); ?> approval rate</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="card h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Rejected Attendance</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo number_format($rejected_attendances); ?></h4>
                                        <span class="badge bg-soft-danger text-danger"><?php echo format_pct($bounce_rate); ?> rejection rate</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-sm-6">
                                <div class="card h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Biometric Registered</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo number_format(isset($biometric_students)?$biometric_students:0); ?></h4>
                                        <span class="badge bg-soft-primary text-primary"><?php echo format_pct($site_impressions); ?> enrollment</span>
                                    </div>
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
                                        <h5 class="mb-1">Student Engagement Snapshot</h5>
                                        <span class="fs-12 text-muted">Live breakdown from current database records</span>
                                    </div>
                                    <a href="javascript:void(0);" class="btn btn-light-brand">Live Overview</a>
                                </div>
                                <?php
                                // Build student-centric metrics for the Email Reports area
                                $erp_total_students = isset($total_students) ? (int)$total_students : 0;
                                // students with non-empty email
                                $erp_with_email = isset($students_with_email) ? (int)$students_with_email : 0;
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
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">With Email (<?php echo format_pct($pct_with_email); ?>)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-lg-4 col-md-6">
                                        <div class="card stretch stretch-full border border-dashed border-gray-5">
                                            <div class="card-body rounded-3 text-center">
                                                <i class="bi bi-person-check fs-3 text-success"></i>
                                                <div class="fs-4 fw-bolder text-dark mt-3 mb-1"><?php echo number_format($erp_biometric); ?></div>
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">Biometric Registered (<?php echo format_pct($pct_biometric); ?>)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-lg-4 col-md-6">
                                        <div class="card stretch stretch-full border border-dashed border-gray-5">
                                            <div class="card-body rounded-3 text-center">
                                                <i class="bi bi-person-plus fs-3 text-indigo"></i>
                                                <div class="fs-4 fw-bolder text-dark mt-3 mb-1"><?php echo number_format($erp_new30); ?></div>
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">New (30d) (<?php echo format_pct($pct_new30); ?>)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-lg-4 col-md-6">
                                        <div class="card stretch stretch-full border border-dashed border-gray-5">
                                            <div class="card-body rounded-3 text-center">
                                                <i class="bi bi-calendar-check fs-3 text-teal"></i>
                                                <div class="fs-4 fw-bolder text-dark mt-3 mb-1"><?php echo number_format($erp_att_today); ?></div>
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">Attended Today (<?php echo format_pct($pct_att_today); ?>)</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-xxl-2 col-lg-4 col-md-6">
                                        <div class="card stretch stretch-full border border-dashed border-gray-5">
                                            <div class="card-body rounded-3 text-center">
                                                <i class="bi bi-briefcase fs-3 text-danger"></i>
                                                <div class="fs-4 fw-bolder text-dark mt-3 mb-1"><?php echo number_format(isset($active_internships)?$active_internships:0); ?></div>
                                                <p class="fs-12 fw-medium text-muted text-spacing-1 mb-0 text-truncate-1-line">Active Internships (<?php echo format_pct($page_views); ?>)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Mini Card] end -->
                    <!-- [Visitors Overview] start -->
                    <div class="col-xxl-8 section-tight">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle">
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
                                <div class="analytics-chart-panel">
                                    <div id="visitors-overview-statistics-chart"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Visitors Overview] end -->
                    <!-- [Internship Status] start -->
                    <div class="col-xxl-4 section-tight">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle">
                                <h5 class="card-title">Internship Status</h5>
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
                                        $colors = ['pending' => 'bg-warning', 'ongoing' => 'bg-success', 'completed' => 'bg-primary', 'cancelled' => 'bg-danger'];
                                        foreach ($status_counts as $k => $v) {
                                            $pct = $total_interns > 0 ? round(($v / $total_interns) * 100, 2) : 0;
                                            $label = ucfirst($k);
                                            $barClass = $colors[$k] ?? 'bg-dark';
                                            echo "<tr>\n";
                                            echo "<td><a href=\"ojt.php\"><span>{$label}</span></a></td>\n";
                                            echo "<td><span class=\"text-end d-flex align-items-center m-0\"><span class=\"me-3\">" . format_pct($pct) . "</span><span class=\"progress w-100 ht-5\"><span class=\"progress-bar {$barClass}\" style=\"width: {$pct}%\"></span></span></span></td>\n";
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
                    <!-- [Internship Status] end -->
                    <!-- [Mini Card] start -->
                    <div class="col-xxl-3 col-md-6 section-tight">
                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between p-4 mb-4">
                                    <div>
                                        <div class="fw-bold mb-2 text-dark text-truncate-1-line">Attendance Rejection Rate</div>
                                        <div class="fs-11 text-muted">Based on attendance records</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fs-24 fw-bold mb-2 text-dark"><span class="counter"><?php echo $bounce_rate; ?></span>%</div>
                                        <div class="fs-11 text-danger"><?php echo number_format($rejected_attendances); ?> of <?php echo number_format($total_attendances); ?></div>
                                    </div>
                                </div>
                                <div id="bounce-rate"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-md-6 section-tight">
                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between p-4 mb-4">
                                    <div>
                                        <div class="fw-bold mb-2 text-dark text-truncate-1-line">Active Internships Rate</div>
                                        <div class="fs-11 text-muted">Ongoing vs Total internships</div>
                                    </div>
                                    <div class="text-end">
                                        <div class="fs-24 fw-bold mb-2 text-dark"><span class="counter"><?php echo $page_views; ?></span>%</div>
                                        <div class="fs-11 text-success"><?php echo number_format($active_internships); ?> active of <?php echo number_format($total_internships); ?></div>
                                    </div>
                                </div>
                                <div id="page-views"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-md-6 section-tight">
                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between p-4 mb-4">
                                    <div>
                                        <div class="fw-bold mb-2 text-dark text-truncate-1-line">Biometric Registration Rate</div>
                                        <div class="fs-11 text-muted">Registered vs Total students</div>
                                    </div>
                                    <div class="tx-right">
                                        <div class="fs-24 fw-bold mb-2 text-dark"><span class="counter"><?php echo $site_impressions; ?></span>%</div>
                                        <div class="fs-11 text-success"><?php echo number_format($biometric_students); ?> registered of <?php echo number_format($total_students); ?></div>
                                    </div>
                                </div>
                                <div id="site-impressions"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xxl-3 col-md-6 section-tight">
                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="d-flex justify-content-between p-4 mb-4">
                                    <div>
                                        <div class="fw-bold mb-2 text-dark text-truncate-1-line">Attendance Approval Rate</div>
                                        <div class="fs-11 text-muted">Approved vs Total records</div>
                                    </div>
                                    <div class="tx-right">
                                        <div class="fs-24 fw-bold mb-2 text-dark"><span class="counter"><?php echo $conversion_rate; ?></span>%</div>
                                        <div class="fs-11 text-success"><?php echo number_format($approved_attendances); ?> approved, <?php echo number_format($pending_attendances); ?> pending</div>
                                    </div>
                                </div>
                                <div id="conversions-rate"></div>
                            </div>
                        </div>
                    </div>
                    <!-- [Mini Card] end -->
                    <!-- [Goal Progress] start -->
                    <div class="col-xxl-4 section-tight">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle">
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
                                            <div class="fs-11 text-muted text-truncate-1-line"><?php echo $marketing_current; ?>/<?php echo $marketing_goal; ?> Users (<?php echo format_pct($marketing_progress); ?>)</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="px-4 py-3 text-center border border-dashed rounded-3">
                                            <div class="mx-auto mb-4">
                                                <div class="goal-progress-2"></div>
                                            </div>
                                            <h2 class="fs-13 tx-spacing-1">Teams Goal</h2>
                                            <div class="fs-11 text-muted text-truncate-1-line"><?php echo $teams_current; ?>/<?php echo $teams_goal; ?> Members (<?php echo format_pct($teams_progress); ?>)</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="px-4 py-3 text-center border border-dashed rounded-3">
                                            <div class="mx-auto mb-4">
                                                <div class="goal-progress-3"></div>
                                            </div>
                                            <h2 class="fs-13 tx-spacing-1">OJT Goal</h2>
                                            <div class="fs-11 text-muted text-truncate-1-line"><?php echo $ojt_current_hours; ?>/<?php echo $ojt_goal_hours; ?> hrs (<?php echo format_pct($ojt_progress); ?>)</div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="px-4 py-3 text-center border border-dashed rounded-3">
                                            <div class="mx-auto mb-4">
                                                <div class="goal-progress-4"></div>
                                            </div>
                                            <h2 class="fs-13 tx-spacing-1">Revenue Goal</h2>
                                            <div class="fs-11 text-muted text-truncate-1-line"><?php echo $revenue_current; ?>/<?php echo $revenue_goal; ?> (<?php echo format_pct($revenue_progress); ?>)</div>
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
                    <div class="col-xxl-8 section-tight">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle">
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
                                <div class="analytics-chart-panel m-2">
                                    <div id="campaign-alytics-bar-chart"></div>
                                </div>
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
                                            <h6 class="fw-bold text-dark"><?php echo format_pct($opened_pct); ?></h6>
                                            <div class="progress mt-2 ht-3">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $w_opened; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">Clicked</div>
                                            <h6 class="fw-bold text-dark"><?php echo format_pct($clicked_pct); ?></h6>
                                            <div class="progress mt-2 ht-3">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $w_clicked; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-3">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">Conversion</div>
                                            <h6 class="fw-bold text-dark"><?php echo format_pct($conversion_pct); ?></h6>
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
                    <div class="col-xxl-8 section-tight">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle">
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
                                            <?php
                                            $status_class_map = [
                                                'pending' => 'bg-soft-warning text-warning',
                                                'ongoing' => 'bg-soft-primary text-primary',
                                                'completed' => 'bg-soft-success text-success',
                                                'cancelled' => 'bg-soft-danger text-danger'
                                            ];
                                            if (!empty($project_remainders)):
                                                foreach ($project_remainders as $project):
                                                    $status = strtolower($project['status']);
                                                    $badge_class = $status_class_map[$status] ?? 'bg-soft-secondary text-secondary';
                                                    $stage_steps = (int)round(($project['completion'] / 100) * 6);
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="hstack gap-2">
                                                        <span class="wd-10 ht-10 bg-gray-400 rounded-circle d-inline-block me-2 lh-base"></span>
                                                        <div class="border-3 border-start rounded ps-3">
                                                            <a href="ojt-view.php?id=<?php echo (int)$project['id']; ?>" class="mb-2 d-block">
                                                                <span><?php echo htmlspecialchars($project['name'] ?: ('Internship #' . $project['id'])); ?></span>
                                                            </a>
                                                            <p class="fs-12 text-muted mb-0">Rendered <?php echo (int)$project['rendered_hours']; ?> / <?php echo (int)$project['required_hours']; ?> hours</p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                                                </td>
                                                <td>
                                                    <span class="fs-12 fw-semibold"><?php echo (int)$project['remaining_hours']; ?> hrs</span>
                                                </td>
                                                <td>
                                                    <div class="hstack gap-1">
                                                        <?php for ($i = 1; $i <= 6; $i++): ?>
                                                            <div class="wd-15 ht-4 rounded-pill <?php echo $i <= $stage_steps ? 'bg-success opacity-75' : 'bg-gray-300'; ?>"></div>
                                                        <?php endfor; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="ojt-view.php?id=<?php echo (int)$project['id']; ?>" class="avatar-text avatar-md ms-auto">
                                                        <i class="feather-arrow-right"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php
                                                endforeach;
                                            else:
                                            ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">No internship records found.</td>
                                            </tr>
                                            <?php endif; ?>
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
                    <div class="col-xxl-4 section-tight">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle">
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
                                $internal_pct = $total_internships > 0 ? round(($internal_internships / $total_internships) * 100, 2) : 0;
                                $external_pct = $total_internships > 0 ? round(($external_internships / $total_internships) * 100, 2) : 0;
                                ?>
                                <div class="row g-3 text-center">
                                    <div class="col-6">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">Students</div>
                                            <h6 class="fw-bold text-dark"><?php echo number_format($total_students); ?></h6>
                                            <div class="fs-11 text-muted"><?php echo format_pct($biometric_pct); ?> Biometric</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">OJT Internships</div>
                                            <h6 class="fw-bold text-dark"><?php echo number_format($total_internships); ?></h6>
                                            <div class="fs-11 text-muted"><?php echo format_pct($internship_active_pct); ?> Active</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">Completed OJT</div>
                                            <h6 class="fw-bold text-dark"><?php echo number_format($completed_internships); ?></h6>
                                            <div class="fs-11 text-muted"><?php echo format_pct($completed_internships_rate); ?> Completion</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border border-dashed rounded">
                                            <div class="fs-12 text-muted mb-1">Internal vs External</div>
                                            <h6 class="fw-bold text-dark"><?php echo number_format($internal_internships); ?> / <?php echo number_format($external_internships); ?></h6>
                                            <div class="fs-11 text-muted"><?php echo format_pct($internal_pct); ?> / <?php echo format_pct($external_pct); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="analytics-chart-panel">
                                            <div id="social-overview-chart" style="height:260px;"></div>
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

    <div
        id="analytics-data"
        class="d-none"
        data-sparkline-bounce='<?php echo htmlspecialchars(json_encode($sparkline_bounce), ENT_QUOTES, 'UTF-8'); ?>'
        data-sparkline-active='<?php echo htmlspecialchars(json_encode($sparkline_active), ENT_QUOTES, 'UTF-8'); ?>'
        data-sparkline-biometric='<?php echo htmlspecialchars(json_encode($sparkline_biometric), ENT_QUOTES, 'UTF-8'); ?>'
        data-sparkline-approval='<?php echo htmlspecialchars(json_encode($sparkline_approval), ENT_QUOTES, 'UTF-8'); ?>'
        data-visitors-labels='<?php echo htmlspecialchars(json_encode($visitors_labels), ENT_QUOTES, 'UTF-8'); ?>'
        data-visitors-students='<?php echo htmlspecialchars(json_encode($visitors_students), ENT_QUOTES, 'UTF-8'); ?>'
        data-visitors-attendances='<?php echo htmlspecialchars(json_encode($visitors_attendances), ENT_QUOTES, 'UTF-8'); ?>'
        data-visitors-internships='<?php echo htmlspecialchars(json_encode($visitors_internships), ENT_QUOTES, 'UTF-8'); ?>'
        data-campaign-labels='<?php echo htmlspecialchars(json_encode($campaign_labels), ENT_QUOTES, 'UTF-8'); ?>'
        data-campaign-internal='<?php echo htmlspecialchars(json_encode($campaign_internal), ENT_QUOTES, 'UTF-8'); ?>'
        data-campaign-external='<?php echo htmlspecialchars(json_encode($campaign_external), ENT_QUOTES, 'UTF-8'); ?>'
        data-social-overview-labels='<?php echo htmlspecialchars(json_encode($engagement_labels), ENT_QUOTES, 'UTF-8'); ?>'
        data-social-overview-values='<?php echo htmlspecialchars(json_encode($engagement_values), ENT_QUOTES, 'UTF-8'); ?>'
        data-goal-progress='<?php echo htmlspecialchars(json_encode([
            'marketing' => $marketing_progress,
            'teams' => $teams_progress,
            'ojt' => $ojt_progress,
            'revenue' => $revenue_progress,
        ]), ENT_QUOTES, 'UTF-8'); ?>'>
    </div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>







