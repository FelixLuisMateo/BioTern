<?php
// Start session early to avoid headers-sent warnings
include_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
include_once dirname(__DIR__) . '/includes/dashboard_data.php';

if (!function_exists('dashboard_fetch_count')) {
    function dashboard_fetch_count($conn, $sql, $key = 'count')
    {
        if (!isset($conn)) {
            return 0;
        }

        $result = $conn->query($sql);
        if (!$result) {
            return 0;
        }

        $row = $result->fetch_assoc();
        return (int)($row[$key] ?? 0);
    }
}

if (!function_exists('dashboard_fetch_all')) {
    function dashboard_fetch_all($conn, $sql)
    {
        $rows = array();
        if (!isset($conn)) {
            return $rows;
        }

        $result = $conn->query($sql);
        if (!$result || $result->num_rows <= 0) {
            return $rows;
        }

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }
}

if (!function_exists('dashboard_table_exists')) {
    function dashboard_table_exists($conn, $table)
    {
        if (!isset($conn)) {
            return false;
        }

        $escapedTable = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$escapedTable}'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('dashboard_column_exists')) {
    function dashboard_column_exists($conn, $table, $column)
    {
        if (!isset($conn)) {
            return false;
        }

        $escapedTable = $conn->real_escape_string($table);
        $escapedColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'");
        return $result && $result->num_rows > 0;
    }
}

if (!function_exists('dashboard_safe_table_count')) {
    function dashboard_safe_table_count($conn, $table, $where = '1')
    {
        if (!dashboard_table_exists($conn, $table)) {
            return 0;
        }

        $escapedTable = $conn->real_escape_string($table);
        return dashboard_fetch_count($conn, "SELECT COUNT(*) AS cnt FROM `{$escapedTable}` WHERE {$where}", 'cnt');
    }
}

// Initialize dashboard values
$dashboard_stats = array(
    'attendance_awaiting' => 0,
    'attendance_completed' => 0,
    'attendance_rejected' => 0,
    'attendance_total' => 0,
    'student_count' => 0,
    'internship_count' => 0,
    'active_students' => 0,
    'active_internships' => 0,
    'completed_internships' => 0,
    'today_attendance' => 0,
    'biometric_registered' => 0,
    'pending_biometrics' => 0,
    'attendance_approval_rate' => 0,
    'attendance_pending_rate' => 0,
    'active_student_rate' => 0,
);

$ojt_status_counts = array('pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0);
$ojt_type_counts = array('internal' => 0, 'external' => 0);
$avg_completion_percentage = 0.0;

$recent_students = array();
$recent_attendance = array();
$active_students_list = array();
$coordinators = array();
$supervisors = array();
$recent_activities = array();

if (isset($dashboard_data) && is_array($dashboard_data) && !empty($dashboard_data)) {
    $dashboard_stats['attendance_awaiting'] = (int)($dashboard_data['pending_approvals'] ?? 0);
    $dashboard_stats['attendance_completed'] = (int)($dashboard_data['approved_attendances'] ?? 0);
    $dashboard_stats['attendance_rejected'] = (int)($dashboard_data['rejected_attendances'] ?? 0);
    $dashboard_stats['attendance_total'] = $dashboard_stats['attendance_awaiting'] + $dashboard_stats['attendance_completed'] + $dashboard_stats['attendance_rejected'];
    $dashboard_stats['student_count'] = (int)($dashboard_data['total_students'] ?? 0);
    $dashboard_stats['active_students'] = (int)($dashboard_data['active_students'] ?? 0);
    $dashboard_stats['internship_count'] = (int)($dashboard_data['total_internships'] ?? 0);
    $dashboard_stats['active_internships'] = (int)($dashboard_data['active_internships'] ?? 0);
    $dashboard_stats['completed_internships'] = (int)($dashboard_data['completed_internships'] ?? 0);
    $dashboard_stats['biometric_registered'] = (int)($dashboard_data['biometric_students'] ?? 0);
    $dashboard_stats['today_attendance'] = (int)($dashboard_data['today_attendance'] ?? 0);

    if (isset($recent_attendances) && is_array($recent_attendances) && !empty($recent_attendances)) {
        $recent_attendance = $recent_attendances;
    }
}

try {
    // Core counts
    $dashboard_stats['attendance_awaiting'] = dashboard_fetch_count($conn, "SELECT COUNT(*) AS count FROM attendances WHERE status = 'pending'");
    $dashboard_stats['attendance_completed'] = dashboard_fetch_count($conn, "SELECT COUNT(*) AS count FROM attendances WHERE status = 'approved'");
    $dashboard_stats['attendance_rejected'] = dashboard_fetch_count($conn, "SELECT COUNT(*) AS count FROM attendances WHERE status = 'rejected'");
    $dashboard_stats['attendance_total'] = dashboard_fetch_count($conn, "SELECT COUNT(*) AS count FROM attendances");
    $dashboard_stats['student_count'] = dashboard_fetch_count($conn, "SELECT COUNT(*) AS count FROM students WHERE deleted_at IS NULL");

    $dashboard_stats['active_students'] = dashboard_fetch_count(
        $conn,
        "SELECT COUNT(DISTINCT s.id) AS count
         FROM students s
         INNER JOIN internships i ON i.student_id = s.id
         WHERE s.deleted_at IS NULL
           AND i.deleted_at IS NULL
           AND i.status = 'ongoing'"
    );

    // OJT / internship distribution
    $ojt_rows = dashboard_fetch_all($conn, "SELECT status, type, COUNT(*) AS cnt FROM internships WHERE deleted_at IS NULL GROUP BY status, type");
    $dashboard_stats['internship_count'] = 0;
    foreach ($ojt_rows as $ojt_row) {
        $status = $ojt_row['status'] ?? null;
        $type = $ojt_row['type'] ?? null;
        $count = (int)($ojt_row['cnt'] ?? 0);

        if ($status && array_key_exists($status, $ojt_status_counts)) {
            $ojt_status_counts[$status] += $count;
        }

        if ($type && array_key_exists($type, $ojt_type_counts)) {
            $ojt_type_counts[$type] += $count;
        }

        $dashboard_stats['internship_count'] += $count;
    }

    $avg_completion_row = dashboard_fetch_all($conn, "SELECT AVG(completion_percentage) AS avg_completion FROM internships WHERE deleted_at IS NULL");
    if (!empty($avg_completion_row) && $avg_completion_row[0]['avg_completion'] !== null) {
        $avg_completion_percentage = round((float)$avg_completion_row[0]['avg_completion'], 2);
    }

    $dashboard_stats['active_internships'] = (int)($ojt_status_counts['ongoing'] ?? 0);
    $dashboard_stats['completed_internships'] = (int)($ojt_status_counts['completed'] ?? 0);
    $dashboard_stats['biometric_registered'] = dashboard_fetch_count($conn, "SELECT COUNT(*) AS count FROM students WHERE biometric_registered = 1");

    $today = date('Y-m-d');
    $dashboard_stats['today_attendance'] = dashboard_fetch_count($conn, "SELECT COUNT(*) AS count FROM attendances WHERE DATE(attendance_date) = '{$today}'");

    // Lists used by dashboard widgets
    $recent_students = dashboard_fetch_all(
        $conn,
        "SELECT s.id, s.student_id, s.first_name, s.last_name, s.email, s.status, s.biometric_registered, s.created_at
         FROM students s
         WHERE s.deleted_at IS NULL
         ORDER BY s.created_at DESC
         LIMIT 5"
    );

    $recent_attendance = dashboard_fetch_all(
        $conn,
        "SELECT a.id, a.student_id, a.attendance_date, a.morning_time_in, a.morning_time_out, a.status, a.created_at,
                s.first_name, s.last_name, s.email, s.student_id AS student_num
         FROM attendances a
         LEFT JOIN students s ON a.student_id = s.id
         ORDER BY (DATE(a.attendance_date) = CURDATE()) DESC, a.attendance_date DESC, a.created_at DESC
         LIMIT 10"
    );

    $active_students_list = dashboard_fetch_all(
        $conn,
        "SELECT
            s.id,
            s.student_id,
            s.first_name,
            s.last_name,
            s.email,
            s.profile_picture,
            s.biometric_registered,
            i.type AS internship_type,
            i.status AS internship_status,
            i.completion_percentage,
            a.attendance_date,
            a.morning_time_in,
            a.status AS attendance_status
         FROM students s
         INNER JOIN internships i
            ON i.student_id = s.id
           AND i.deleted_at IS NULL
           AND i.status = 'ongoing'
         LEFT JOIN attendances a
            ON a.id = (
                SELECT a2.id
                FROM attendances a2
                WHERE a2.student_id = s.id
                ORDER BY a2.attendance_date DESC, a2.created_at DESC
                LIMIT 1
            )
         WHERE s.deleted_at IS NULL
         ORDER BY COALESCE(a.attendance_date, '1970-01-01') DESC, s.created_at DESC
         LIMIT 6"
    );

    $coordinators = dashboard_fetch_all(
        $conn,
        "SELECT u.id, u.name, u.email, c.department_id, c.phone, c.created_at
         FROM users u
         LEFT JOIN coordinators c ON u.id = c.user_id
         WHERE u.role = 'coordinator' AND u.is_active = 1
         ORDER BY u.created_at DESC
         LIMIT 5"
    );

    $supervisors = dashboard_fetch_all(
        $conn,
        "SELECT
            s.id AS supervisor_id,
            s.user_id,
            COALESCE(NULLIF(u.name, ''), TRIM(CONCAT(s.first_name, ' ', s.last_name))) AS name,
            COALESCE(NULLIF(u.email, ''), s.email) AS email,
            s.phone,
            s.department_id,
            s.specialization,
            s.created_at
         FROM supervisors s
         LEFT JOIN users u ON u.id = s.user_id
         WHERE s.is_active = 1
           AND s.deleted_at IS NULL
           AND (u.id IS NULL OR u.is_active = 1)
         ORDER BY s.created_at DESC
         LIMIT 5"
    );

    $recent_activities = dashboard_fetch_all(
        $conn,
        "SELECT
            CONCAT('Student Application Submitted: ', COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')) AS activity,
            u.application_submitted_at AS activity_date,
            'application_submitted' AS activity_type,
            u.id AS entity_id
         FROM users u
         LEFT JOIN students s ON s.user_id = u.id
         WHERE u.role = 'student'
           AND COALESCE(u.application_status, 'approved') = 'pending'
           AND u.application_submitted_at IS NOT NULL
         UNION ALL
         SELECT
            CONCAT('Student Application Approved: ', COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')) AS activity,
            u.approved_at AS activity_date,
            'application_approved' AS activity_type,
            u.id AS entity_id
         FROM users u
         LEFT JOIN students s ON s.user_id = u.id
         WHERE u.role = 'student'
           AND COALESCE(u.application_status, 'approved') = 'approved'
           AND u.approved_at IS NOT NULL
         UNION ALL
         SELECT
            CONCAT('Student Application Rejected: ', COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')) AS activity,
            u.rejected_at AS activity_date,
            'application_rejected' AS activity_type,
            u.id AS entity_id
         FROM users u
         LEFT JOIN students s ON s.user_id = u.id
         WHERE u.role = 'student'
           AND COALESCE(u.application_status, 'approved') = 'rejected'
           AND u.rejected_at IS NOT NULL
         UNION ALL
         SELECT
            CONCAT('Attendance Recorded for ', s.first_name, ' ', s.last_name) AS activity,
            a.created_at AS activity_date,
            'attendance_recorded' AS activity_type,
            a.id AS entity_id
         FROM attendances a
         LEFT JOIN students s ON a.student_id = s.id
         UNION ALL
         SELECT
            CONCAT('Biometric Registered: ', s.first_name, ' ', s.last_name) AS activity,
            s.biometric_registered_at AS activity_date,
            'biometric_registered' AS activity_type,
            s.id AS entity_id
         FROM students s
         WHERE s.biometric_registered = 1 AND s.biometric_registered_at IS NOT NULL
         UNION ALL
         SELECT
            CONCAT(
                'Login ',
                CASE WHEN l.status = 'success' THEN 'Success' ELSE 'Failed' END,
                ': ',
                COALESCE(NULLIF(u.name, ''), l.identifier, 'Unknown User')
            ) AS activity,
            l.created_at AS activity_date,
            CASE WHEN l.status = 'success' THEN 'login_success' ELSE 'login_failed' END AS activity_type,
            l.id AS entity_id
         FROM login_logs l
         LEFT JOIN users u ON u.id = l.user_id
         ORDER BY activity_date DESC
         LIMIT 15"
    );
} catch (Exception $e) {
    error_log('Dashboard error: ' . $e->getMessage());
}

// Derived metrics
$dashboard_stats['pending_biometrics'] = max(0, $dashboard_stats['student_count'] - $dashboard_stats['biometric_registered']);
$dashboard_stats['attendance_approval_rate'] = ($dashboard_stats['attendance_total'] > 0)
    ? round(($dashboard_stats['attendance_completed'] / $dashboard_stats['attendance_total']) * 100)
    : 0;
$dashboard_stats['attendance_pending_rate'] = ($dashboard_stats['attendance_total'] > 0)
    ? round(($dashboard_stats['attendance_awaiting'] / $dashboard_stats['attendance_total']) * 100)
    : 0;
$dashboard_stats['active_student_rate'] = ($dashboard_stats['student_count'] > 0)
    ? round(($dashboard_stats['active_students'] / $dashboard_stats['student_count']) * 100)
    : 0;

// Keep original variable names for template compatibility
$attendance_awaiting = $dashboard_stats['attendance_awaiting'];
$attendance_completed = $dashboard_stats['attendance_completed'];
$attendance_rejected = $dashboard_stats['attendance_rejected'];
$attendance_total = $dashboard_stats['attendance_total'];
$student_count = $dashboard_stats['student_count'];
$internship_count = $dashboard_stats['internship_count'];
$active_students = $dashboard_stats['active_students'];
$active_internships = $dashboard_stats['active_internships'];
$completed_internships = $dashboard_stats['completed_internships'];
$today_attendance = $dashboard_stats['today_attendance'];
$pending_biometrics = $dashboard_stats['pending_biometrics'];
$attendance_approval_rate = $dashboard_stats['attendance_approval_rate'];
$attendance_pending_rate = $dashboard_stats['attendance_pending_rate'];
$active_student_rate = $dashboard_stats['active_student_rate'];
$biometric_registered = $dashboard_stats['biometric_registered'];
$dashboard_role = strtolower(trim((string)($_SESSION['role'] ?? '')));
$dashboard_can_review_applications = in_array($dashboard_role, ['admin', 'coordinator', 'supervisor'], true);

$page_title = 'BioTern || Dashboard';
$page_body_class = 'dashboard-home';
$page_styles = array('assets/css/modules/pages/page-home-dashboard.css');
$page_vendor_scripts = array(
    'assets/vendors/js/daterangepicker.min.js',
    'assets/vendors/js/apexcharts.min.js',
    'assets/vendors/js/circle-progress.min.js',
);
$page_scripts = array(
    'assets/js/modules/pages/dashboard-init.min.js',
    'assets/js/modules/pages/homepage-movable.js',
    'assets/js/theme-customizer-init.min.js',
);
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
            <div class="page-header dashboard-page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Overview</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item">Overview</li>
                    </ul>
                </div>
            <div class="page-header-right ms-auto">
                <div class="page-header-quick d-none d-md-flex">
                    <span class="badge bg-soft-primary text-primary fs-11">
                        <i class="feather-calendar me-1"></i> <?php echo date('M d, Y'); ?>
                    </span>
                    <button type="button" id="toggle-dashboard-layout" class="btn btn-sm btn-light-brand">
                        <i class="feather-move me-1"></i> Edit Layout
                    </button>
                    <a href="students.php" class="btn btn-sm btn-light-brand">
                        <i class="feather-users me-1"></i> Manage Students
                    </a>
                    <a href="students-edit.php" class="btn btn-sm btn-light-brand">
                        <i class="feather-plus-circle me-1"></i> Add Student
                    </a>
                </div>
                <button class="btn btn-sm btn-primary page-header-actions-toggle" type="button" aria-expanded="false" aria-controls="dashboardPageActions">
                    <i class="feather-grid me-1"></i> Actions
                </button>
                <div class="page-header-actions" id="dashboardPageActions">
                    <div class="dashboard-actions-panel">
                        <div class="dashboard-actions-meta">
                            <span class="text-muted fs-12">Quick Actions</span>
                        </div>
                        <div class="dashboard-actions-quick d-md-none">
                            <span class="badge bg-soft-primary text-primary fs-11">
                                <i class="feather-calendar me-1"></i> <?php echo date('M d, Y'); ?>
                            </span>
                            <button type="button" id="toggle-dashboard-layout-mobile" class="btn btn-sm btn-light-brand">
                                <i class="feather-move me-1"></i> Edit Layout
                            </button>
                            <a href="students.php" class="btn btn-sm btn-light-brand">
                                <i class="feather-users me-1"></i> Manage Students
                            </a>
                            <a href="students-edit.php" class="btn btn-sm btn-light-brand">
                                <i class="feather-plus-circle me-1"></i> Add Student
                            </a>
                        </div>
                        <div class="dashboard-actions-grid">
                            <button type="button" id="reset-dashboard-layout" class="action-tile">
                                <i class="feather-refresh-cw"></i>
                                <span>Reset Layout</span>
                            </button>
                            <?php if ($dashboard_can_review_applications): ?>
                            <a href="applications-review.php" class="action-tile">
                                <i class="feather-user-check"></i>
                                <span>Review Applications</span>
                            </a>
                            <?php endif; ?>
                            <a href="attendance.php" class="action-tile">
                                <i class="feather-calendar"></i>
                                <span>Attendance Center</span>
                            </a>
                            <a href="reports-timesheets.php" class="action-tile">
                                <i class="feather-file-text"></i>
                                <span>Reports</span>
                            </a>
                            <a href="biometric-machine.php" class="action-tile">
                                <i class="feather-activity"></i>
                                <span>F20H Manager</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            <!-- [ page-header ] end -->
            <div class="dashboard-mobile-summary d-md-none">
                <div class="mobile-summary-card">
                    <span class="mobile-summary-label">Active Students</span>
                    <span class="mobile-summary-value"><?php echo $active_students; ?></span>
                    <span class="mobile-summary-meta"><?php echo $active_student_rate; ?>% of <?php echo $student_count; ?></span>
                </div>
                <div class="mobile-summary-card">
                    <span class="mobile-summary-label">Attendance Today</span>
                    <span class="mobile-summary-value"><?php echo $today_attendance; ?></span>
                    <span class="mobile-summary-meta"><?php echo $attendance_pending_rate; ?>% pending</span>
                </div>
                <div class="mobile-summary-card">
                    <span class="mobile-summary-label">Pending Attendance</span>
                    <span class="mobile-summary-value"><?php echo $attendance_awaiting; ?></span>
                    <span class="mobile-summary-meta"><?php echo $attendance_approval_rate; ?>% approved</span>
                </div>
                <div class="mobile-summary-card">
                    <span class="mobile-summary-label">OJT Ongoing</span>
                    <span class="mobile-summary-value"><?php echo $active_internships; ?></span>
                    <span class="mobile-summary-meta">Avg <?php echo $avg_completion_percentage; ?>% completion</span>
                </div>
            </div>
            <div class="dashboard-mobile-stat-board d-md-none" aria-label="Mobile dashboard section switcher">
                <button type="button" class="dashboard-mobile-stat-btn is-active" data-mobile-panel-btn="kpi" aria-pressed="true">
                    <span class="dashboard-mobile-stat-label">Today Glance</span>
                    <strong class="dashboard-mobile-stat-value"><?php echo $today_attendance; ?></strong>
                </button>
                <button type="button" class="dashboard-mobile-stat-btn" data-mobile-panel-btn="students" aria-pressed="false">
                    <span class="dashboard-mobile-stat-label">Students</span>
                    <strong class="dashboard-mobile-stat-value"><?php echo $active_students; ?></strong>
                </button>
                <button type="button" class="dashboard-mobile-stat-btn" data-mobile-panel-btn="activities" aria-pressed="false">
                    <span class="dashboard-mobile-stat-label">Activities</span>
                    <strong class="dashboard-mobile-stat-value"><?php echo count($recent_activities); ?></strong>
                </button>
                <button type="button" class="dashboard-mobile-stat-btn" data-mobile-panel-btn="attendance" aria-pressed="false">
                    <span class="dashboard-mobile-stat-label">Attendance</span>
                    <strong class="dashboard-mobile-stat-value"><?php echo $attendance_total; ?></strong>
                </button>
                <button type="button" class="dashboard-mobile-stat-btn" data-mobile-panel-btn="coordinators" aria-pressed="false">
                    <span class="dashboard-mobile-stat-label">Coordinators</span>
                    <strong class="dashboard-mobile-stat-value"><?php echo count($coordinators); ?></strong>
                </button>
                <button type="button" class="dashboard-mobile-stat-btn" data-mobile-panel-btn="supervisors" aria-pressed="false">
                    <span class="dashboard-mobile-stat-label">Supervisors</span>
                    <strong class="dashboard-mobile-stat-value"><?php echo count($supervisors); ?></strong>
                </button>
            </div>
            <!-- [ Main Content ] start -->
            <div class="main-content dashboard-shell widgets-preloading">
                <div class="row">
                    <!-- [KPI Strip] start -->
                    <div class="col-12 dashboard-movable" data-move-key="kpi-strip" data-mobile-panel="kpi">
                        <div class="card stretch stretch-full kpi-strip">
                            <div class="card-header">
                                <h5 class="card-title">Today at a Glance</h5>
                                <div class="card-header-action">
                                    <div class="card-header-btn"></div>
                                </div>
                            </div>
                            <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="kpi-card kpi-tile">
                                                <div class="kpi-title">Active Students</div>
                                                <div class="kpi-value"><?php echo $active_students; ?></div>
                                                <div class="kpi-subtext"><?php echo $active_student_rate; ?>% of <?php echo $student_count; ?> total</div>
                                                <div class="progress kpi-progress">
                                                    <div class="progress-bar bg-primary" role="progressbar" data-progress-width="<?php echo $active_student_rate; ?>"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="kpi-card kpi-tile">
                                                <div class="kpi-title">Attendance Today</div>
                                                <div class="kpi-value"><?php echo $today_attendance; ?></div>
                                                <div class="kpi-subtext"><?php echo $attendance_pending_rate; ?>% pending approvals</div>
                                                <div class="progress kpi-progress">
                                                    <div class="progress-bar bg-warning" role="progressbar" data-progress-width="<?php echo $attendance_pending_rate; ?>"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="kpi-card kpi-tile">
                                                <div class="kpi-title">Pending Attendance</div>
                                                <div class="kpi-value"><?php echo $attendance_awaiting; ?></div>
                                                <div class="kpi-subtext"><?php echo $attendance_approval_rate; ?>% approved overall</div>
                                                <div class="progress kpi-progress">
                                                    <div class="progress-bar bg-success" role="progressbar" data-progress-width="<?php echo $attendance_approval_rate; ?>"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 col-xl-3">
                                            <div class="kpi-card kpi-tile">
                                                <div class="kpi-title">OJT Ongoing</div>
                                                <div class="kpi-value"><?php echo $active_internships; ?></div>
                                                <div class="kpi-subtext">Avg. completion <?php echo $avg_completion_percentage; ?>%</div>
                                                <div class="progress kpi-progress">
                                                    <div class="progress-bar bg-info" role="progressbar" data-progress-width="<?php echo $avg_completion_percentage; ?>"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                            </div>
                        </div>
                    </div>
                    <!-- [KPI Strip] end -->

                    <!-- [Active Students] start -->
                    <div class="col-xxl-4 section-tight dashboard-movable" data-move-key="active-students" data-mobile-panel="students">
                        <div class="card dash-card stretch stretch-full">
                            <div class="dash-card-header">
                                <h6 class="dash-card-title">Active Students</h6>
                                <div class="dash-card-actions"></div>
                            </div>
                            <div class="dash-card-body">
                                <?php if (count($active_students_list) > 0): ?>
                                    <div class="dash-list">
                                        <?php foreach ($active_students_list as $student): ?>
                                            <?php
                                            $firstName = (string)($student['first_name'] ?? '');
                                            $lastName = (string)($student['last_name'] ?? '');
                                            $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                                            $attendanceDate = $student['attendance_date'] ? date('M d, Y', strtotime($student['attendance_date'])) : 'No attendance yet';
                                            $attendanceTime = $student['morning_time_in'] ? date('h:i a', strtotime($student['morning_time_in'])) : '';
                                            $attendanceStatus = (string)($student['attendance_status'] ?? '');
                                            $internshipType = ucfirst((string)($student['internship_type'] ?? ''));
                                            ?>
                                            <div class="dash-list-item">
                                                <div class="dash-list-main">
                                                    <div class="dash-avatar">
                                                        <?php echo $initials !== '' ? $initials : 'ST'; ?>
                                                    </div>
                                                    <div class="dash-list-text">
                                                        <a href="students-view.php?id=<?php echo (int)$student['id']; ?>" class="dash-list-title">
                                                            <?php echo htmlspecialchars(trim($firstName . ' ' . $lastName)); ?>
                                                        </a>
                                                        <div class="dash-list-sub">
                                                            <?php echo htmlspecialchars((string)($student['student_id'] ?? '')); ?> | <?php echo htmlspecialchars($internshipType); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="dash-list-meta">
                                                    <?php if ($attendanceStatus === 'approved'): ?>
                                                        <span class="badge bg-soft-success text-success">Approved</span>
                                                    <?php elseif ($attendanceStatus === 'pending'): ?>
                                                        <span class="badge bg-soft-warning text-warning">Pending</span>
                                                    <?php elseif ($attendanceStatus === 'rejected'): ?>
                                                        <span class="badge bg-soft-danger text-danger">Rejected</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-soft-secondary text-secondary">No record</span>
                                                    <?php endif; ?>
                                                    <div class="dash-meta-line">
                                                        <?php echo $attendanceDate; ?><?php echo $attendanceTime ? ' | ' . $attendanceTime : ''; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="dash-empty">No active students</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- [Active Students] end -->

                    <!-- [Recent Activities & Logs] start (replaces Payment Record) -->
                    <div class="col-xxl-8 section-tight dashboard-movable" data-move-key="recent-activities" data-mobile-panel="activities">
                        <div class="card dash-card stretch stretch-full">
                            <div class="dash-card-header">
                                <h6 class="dash-card-title">Recent Activities</h6>
                                <div class="dash-card-actions"></div>
                            </div>
                            <div class="dash-card-body">
                                <?php if (count($recent_activities) > 0): ?>
                                    <?php
                                    $activityMeta = [
                                        'application_submitted' => [
                                            'class' => 'activity-icon--application-submitted',
                                            'icon' => 'file-text',
                                        ],
                                        'application_approved' => [
                                            'class' => 'activity-icon--application-approved',
                                            'icon' => 'check-circle',
                                        ],
                                        'application_rejected' => [
                                            'class' => 'activity-icon--application-rejected',
                                            'icon' => 'x-circle',
                                        ],
                                        'attendance_recorded' => [
                                            'class' => 'activity-icon--attendance-recorded',
                                            'icon' => 'clock',
                                        ],
                                        'biometric_registered' => [
                                            'class' => 'activity-icon--biometric-registered',
                                            'icon' => 'check-circle',
                                        ],
                                        'login_success' => [
                                            'class' => 'activity-icon--login-success',
                                            'icon' => 'log-in',
                                        ],
                                        'login_failed' => [
                                            'class' => 'activity-icon--login-failed',
                                            'icon' => 'alert-triangle',
                                        ],
                                    ];
                                    ?>
                                    <div class="dash-list">
                                        <?php foreach ($recent_activities as $activity): ?>
                                        <?php
                                        $activityType = (string)($activity['activity_type'] ?? '');
                                        $meta = $activityMeta[$activityType] ?? [
                                            'class' => 'activity-icon--default',
                                            'icon' => 'info',
                                        ];
                                        ?>
                                        <div class="dash-list-item">
                                            <div class="dash-list-main">
                                                <div class="dash-activity-icon <?php echo $meta['class']; ?>">
                                                    <i class="feather-<?php echo $meta['icon']; ?>"></i>
                                                </div>
                                                <div class="dash-list-text">
                                                    <div class="dash-list-title">
                                                        <?php echo htmlspecialchars($activity['activity'] ?? ''); ?>
                                                    </div>
                                                    <div class="dash-list-sub">
                                                        <?php echo $activity['activity_date'] ? date('M d, Y H:i', strtotime($activity['activity_date'])) : 'No date'; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="dash-empty">No recent activity</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- [Recent Activities & Logs] end -->

                    

                    <!-- [Latest Attendance Records] start -->
                    <div class="col-xxl-8 section-tight dashboard-movable" data-move-key="latest-attendance" data-mobile-panel="attendance">
                        <div class="card dash-card stretch stretch-full">
                            <div class="dash-card-header">
                                <h6 class="dash-card-title">Latest Attendance</h6>
                                <div class="dash-card-actions">
                                    <span class="dash-pill">Today <?php echo $today_attendance; ?></span>
                                </div>
                            </div>
                            <div class="dash-card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0 dash-table" id="latest-attendance-table">
                                        <thead>
                                            <tr class="border-b">
                                                <th scope="row">Students</th>
                                                <th>Attendance Date</th>
                                                <th>Time In</th>
                                                <th>Status</th>
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
                                              </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">No attendance records found</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [Latest Attendance Records] end -->
                    <!--! BEGIN: [Coordinators List] !-->
                    <div class="col-xxl-4 dashboard-movable" data-move-key="coordinators" data-mobile-panel="coordinators">
                        <div class="card dash-card stretch stretch-full">
                            <div class="dash-card-header">
                                <h6 class="dash-card-title">Coordinators</h6>
                                <div class="dash-card-actions"></div>
                            </div>
                            <div class="dash-card-body">
                                <?php if (count($coordinators) > 0): ?>
                                    <div class="dash-list">
                                        <?php foreach ($coordinators as $coordinator): ?>
                                        <div class="dash-list-item">
                                            <div class="dash-list-main">
                                                <div class="dash-avatar dash-avatar--primary">
                                                    <?php echo strtoupper(substr($coordinator['name'], 0, 1)); ?>
                                                </div>
                                                <div class="dash-list-text">
                                                    <div class="dash-list-title"><?php echo htmlspecialchars($coordinator['name']); ?></div>
                                                    <div class="dash-list-sub"><?php echo htmlspecialchars($coordinator['email']); ?></div>
                                                </div>
                                            </div>
                                            <div class="dash-list-meta">
                                                <span class="badge bg-soft-info text-info">Coordinator</span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="dash-empty">No coordinators</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!--! END: [Coordinators List] !-->
                    <!--! BEGIN: [Supervisors List] !-->
                    <div class="col-xxl-4 dashboard-movable" data-move-key="supervisors" data-mobile-panel="supervisors">
                        <div class="card dash-card stretch stretch-full">
                            <div class="dash-card-header">
                                <h6 class="dash-card-title">Supervisors</h6>
                                <div class="dash-card-actions"></div>
                            </div>
                            <div class="dash-card-body">
                                <?php if (count($supervisors) > 0): ?>
                                    <div class="dash-list">
                                        <?php foreach ($supervisors as $supervisor): ?>
                                        <div class="dash-list-item">
                                            <div class="dash-list-main">
                                                <div class="dash-avatar dash-avatar--success">
                                                    <?php echo strtoupper(substr($supervisor['name'], 0, 1)); ?>
                                                </div>
                                                <div class="dash-list-text">
                                                    <div class="dash-list-title"><?php echo htmlspecialchars($supervisor['name']); ?></div>
                                                    <div class="dash-list-sub"><?php echo htmlspecialchars($supervisor['email']); ?></div>
                                                </div>
                                            </div>
                                            <div class="dash-list-meta">
                                                <span class="badge bg-soft-success text-success">Supervisor</span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="dash-empty">No supervisors</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!--! END: [Supervisors List] !-->
                    <!-- Duplicate Recent Activities removed (now shown in the top section) -->
                </div>
            </div>
            <div id="homepage-runtime-config"
                data-ojt-pending="<?php echo isset($ojt_status_counts['pending']) ? intval($ojt_status_counts['pending']) : 0; ?>"
                data-ojt-ongoing="<?php echo isset($ojt_status_counts['ongoing']) ? intval($ojt_status_counts['ongoing']) : 0; ?>"
                data-ojt-completed="<?php echo isset($ojt_status_counts['completed']) ? intval($ojt_status_counts['completed']) : 0; ?>"
                data-ojt-cancelled="<?php echo isset($ojt_status_counts['cancelled']) ? intval($ojt_status_counts['cancelled']) : 0; ?>"
                hidden></div>
            <script>
            (function () {
                function initMobileDashboardPanels() {
                    var body = document.body;
                    if (!body || !window.matchMedia('(max-width: 991.98px)').matches) {
                        return;
                    }

                    var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-mobile-panel-btn]'));
                    var panels = Array.prototype.slice.call(document.querySelectorAll('.dashboard-movable[data-mobile-panel]'));
                    if (!buttons.length || !panels.length) {
                        return;
                    }

                    function activatePanel(panelKey) {
                        body.classList.add('mobile-dashboard-filtered');
                        buttons.forEach(function (btn) {
                            var isActive = btn.getAttribute('data-mobile-panel-btn') === panelKey;
                            btn.classList.toggle('is-active', isActive);
                            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                        });

                        panels.forEach(function (panel) {
                            var isActivePanel = panel.getAttribute('data-mobile-panel') === panelKey;
                            panel.classList.toggle('is-mobile-panel-active', isActivePanel);
                        });
                    }

                    buttons.forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            activatePanel(btn.getAttribute('data-mobile-panel-btn'));
                        });
                    });

                    activatePanel('kpi');
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initMobileDashboardPanels);
                } else {
                    initMobileDashboardPanels();
                }
            })();
            </script>
            <!-- [ Main Content ] end -->
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>





