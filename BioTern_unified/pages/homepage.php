<?php
require_once dirname(__DIR__) . '/config/db.php';
// Start session early to avoid headers-sent warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include_once dirname(__DIR__) . '/config/db.php';
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
$page_styles = array('assets/css/homepage-dashboard.css');
include 'includes/header.php';
?>
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
                            <i class="feather-calendar me-1"></i> <?php
require_once dirname(__DIR__) . '/config/db.php';
echo date('M d, Y'); ?>
                        </span>
                        <button type="button" id="toggle-dashboard-layout" class="btn btn-sm btn-light-brand">
                            <i class="feather-move me-1"></i> Edit Layout
                        </button>
                        <button type="button" id="reset-dashboard-layout" class="btn btn-sm btn-light-brand">
                            <i class="feather-refresh-cw me-1"></i> Reset Layout
                        </button>
                        <a href="students.php" class="btn btn-sm btn-light-brand">
                            <i class="feather-users me-1"></i> Manage Students
                        </a>
                        <?php if ($dashboard_can_review_applications): ?>
                        <a href="applications-review.php" class="btn btn-sm btn-light-brand">
                            <i class="feather-user-check me-1"></i> Review Applications
                        </a>
                        <?php endif; ?>
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
                    <div class="col-12 dashboard-movable" data-move-key="overview-hero">
                        <div class="card stretch stretch-full overflow-hidden dashboard-hero">
                            <div class="card-body bg-primary text-white p-4 dashboard-move-handle" title="Drag to move this section">
                                <div class="row align-items-center g-3">
                                    <div class="col-lg-8">
                                        <span class="badge bg-light text-primary mb-2">Admin Dashboard</span>
                                        <h4 class="text-reset mb-2">BioTern Operations Snapshot</h4>
                                        <p class="mb-0 text-reset opacity-75">Key numbers are shown first so you can review progress and act quickly.</p>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                            <a href="students-create.php" class="btn btn-light btn-sm">
                                                <i class="feather-user-plus me-1"></i> New Student
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

                    <div class="col-12 dashboard-movable" data-move-key="kpi-strip">
                        <div class="row g-3 align-items-stretch dashboard-move-handle" title="Drag to move this section">
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Pending Attendance</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance_awaiting; ?></h4>
                                        <span class="badge bg-soft-warning text-warning"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance_pending_rate; ?>% pending</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Biometric Pending</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $pending_biometrics; ?></h4>
                                        <span class="badge bg-soft-danger text-danger text-wrap">students without registration</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Active Students</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $active_students; ?></h4>
                                        <span class="badge bg-soft-primary text-primary"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $active_student_rate; ?>% of total students</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Active Internships</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $active_internships; ?></h4>
                                        <span class="badge bg-soft-info text-info">ongoing placements</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Attendance Today</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $today_attendance; ?></h4>
                                        <span class="badge bg-soft-secondary text-dark"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo date('M d, Y'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Attendance Approved</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance_completed; ?></h4>
                                        <span class="badge bg-soft-success text-success"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance_approval_rate; ?>% approval rate</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-8 order-3 order-xxl-3 section-tight dashboard-movable" data-move-key="operations-pulse">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle" title="Drag to move this section">
                                <h5 class="card-title">Operations Pulse</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="fs-12 text-muted">Attendance Approval Progress</span>
                                        <span class="fs-12 fw-semibold text-dark"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance_completed; ?>/<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance_total; ?></span>
                                    </div>
                                    <div class="progress ht-5">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance_approval_rate; ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="fs-12 text-muted">Biometric Enrollment</span>
                                        <span class="fs-12 fw-semibold text-dark"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $biometric_registered; ?>/<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $student_count; ?></span>
                                    </div>
                                    <div class="progress ht-5">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php
require_once dirname(__DIR__) . '/config/db.php';
echo ($student_count > 0) ? round(($biometric_registered / $student_count) * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="fs-12 text-muted">Internship Completion</span>
                                        <span class="fs-12 fw-semibold text-dark"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $completed_internships; ?>/<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $internship_count; ?></span>
                                    </div>
                                    <div class="progress ht-5">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php
require_once dirname(__DIR__) . '/config/db.php';
echo ($internship_count > 0) ? round(($completed_internships / $internship_count) * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer d-flex flex-wrap gap-2">
                                <a href="attendance.php" class="btn btn-sm btn-light-brand"><i class="feather-check-circle me-1"></i> Process Attendance</a>
                                <a href="students.php" class="btn btn-sm btn-light-brand"><i class="feather-users me-1"></i> View Students</a>
                                <a href="demo-biometric.php" class="btn btn-sm btn-light-brand"><i class="feather-activity me-1"></i> Biometric Queue</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-xxl-4 order-4 order-xxl-4 section-tight dashboard-movable" data-move-key="priority-items">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle" title="Drag to move this section">
                                <h5 class="card-title">Priority Items</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between p-3 border border-dashed rounded-3 mb-3">
                                    <div>
                                        <div class="fw-semibold text-dark">Attendance for Review</div>
                                        <div class="fs-12 text-muted">Pending submissions requiring approval</div>
                                    </div>
                                    <span class="badge bg-soft-warning text-warning fs-12"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance_awaiting; ?></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-3 border border-dashed rounded-3 mb-3">
                                    <div>
                                        <div class="fw-semibold text-dark">Students Without Biometrics</div>
                                        <div class="fs-12 text-muted">Needs registration to enable attendance flow</div>
                                    </div>
                                    <span class="badge bg-soft-danger text-danger fs-12"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $pending_biometrics; ?></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-3 border border-dashed rounded-3">
                                    <div>
                                        <div class="fw-semibold text-dark">OJT Not Yet Completed</div>
                                        <div class="fs-12 text-muted">Internships still active or pending</div>
                                    </div>
                                    <span class="badge bg-soft-info text-info fs-12"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo max(0, $internship_count - $completed_internships); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- [Recent Activities & Logs] start (replaces Payment Record) -->
                    <div class="col-xxl-8 order-5 order-xxl-5 section-tight dashboard-movable" data-move-key="recent-activities">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle" title="Drag to move this section">
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
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
if (count($recent_activities) > 0): ?>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($recent_activities as $activity): ?>
                                        <?php
                                            $activityType = (string)($activity['activity_type'] ?? '');
                                            $activityMeta = [
                                                'application_submitted' => [
                                                    'bg' => '#e3f2fd',
                                                    'icon' => 'file-text',
                                                    'badge' => 'bg-soft-primary text-primary',
                                                    'label' => 'Application submitted'
                                                ],
                                                'application_approved' => [
                                                    'bg' => '#e8f5e9',
                                                    'icon' => 'check-circle',
                                                    'badge' => 'bg-soft-success text-success',
                                                    'label' => 'Application approved'
                                                ],
                                                'application_rejected' => [
                                                    'bg' => '#fdecea',
                                                    'icon' => 'x-circle',
                                                    'badge' => 'bg-soft-danger text-danger',
                                                    'label' => 'Application rejected'
                                                ],
                                                'attendance_recorded' => [
                                                    'bg' => '#f3e5f5',
                                                    'icon' => 'clock',
                                                    'badge' => 'bg-soft-warning text-warning',
                                                    'label' => 'Attendance recorded'
                                                ],
                                                'biometric_registered' => [
                                                    'bg' => '#e8f5e9',
                                                    'icon' => 'check-circle',
                                                    'badge' => 'bg-soft-success text-success',
                                                    'label' => 'Biometric registered'
                                                ],
                                            ];
                                            $meta = $activityMeta[$activityType] ?? [
                                                'bg' => '#e3f2fd',
                                                'icon' => 'info',
                                                'badge' => 'bg-soft-info text-info',
                                                'label' => ucfirst(str_replace('_', ' ', $activityType))
                                            ];
                                        ?>
                                        <div class="d-flex align-items-center gap-3 p-3 border-bottom">
                                            <div class="avatar-text avatar-sm rounded-circle" 
                                                style="background-color: <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $meta['bg'];
                                                ?>">
                                                <i class="feather-<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $meta['icon'];
                                                ?>" style="font-size: 14px;"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <a href="javascript:void(0);" class="fw-semibold text-dark d-block">
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($activity['activity']); ?>
                                                </a>
                                                <span class="fs-12 text-muted">
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($activity['activity_date']): ?>
                                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
echo date('M d, Y H:i', strtotime($activity['activity_date'])); ?>
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                                        No date
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                                </span>
                                            </div>
                                            <span class="badge <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $meta['badge'];
                                            ?> fs-10">
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $meta['label']; ?>
                                            </span>
                                        </div>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                        <p class="text-muted text-center py-4">No recent activities found</p>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                </div>
                            </div>
                            <a href="javascript:void(0);" class="card-footer fs-11 fw-bold text-uppercase text-center py-3">View All Activities</a>
                        </div>
                    </div>
                    <!-- [Recent Activities & Logs] end -->

                    <!-- [Admin Quick Actions] (moved next to Recent Activities) -->
                    <?php
require_once dirname(__DIR__) . '/config/db.php';
// Admin Quick Actions: moved to be side-by-side with Recent Activities ?>
                    <div class="col-xxl-4 order-6 order-xxl-6 section-tight dashboard-movable" data-move-key="admin-quick-actions">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle" title="Drag to move this section">
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
require_once dirname(__DIR__) . '/config/db.php';
$qa_total_students = 0;
                                $qa_total_internships = 0;
                                $qa_attendance_today = 0;
                                $qa_biometric_registered = 0;

                                if (isset($conn)) {
                                    $qa_total_students = dashboard_safe_table_count($conn, 'students', '1');
                                    $qa_total_internships = dashboard_safe_table_count($conn, 'internships', '1');

                                    if (dashboard_column_exists($conn, 'attendances', 'date')) {
                                        $qa_attendance_today = dashboard_safe_table_count($conn, 'attendances', 'date = CURDATE()');
                                    } elseif (dashboard_column_exists($conn, 'attendances', 'log_time')) {
                                        $qa_attendance_today = dashboard_safe_table_count($conn, 'attendances', 'DATE(log_time) = CURDATE()');
                                    }

                                    if (dashboard_column_exists($conn, 'students', 'biometric_registered')) {
                                        $qa_biometric_registered = dashboard_safe_table_count($conn, 'students', 'biometric_registered = 1');
                                    }
                                }
                                ?>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <a href="students.php" class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center">
                                            <i class="feather-users me-2"></i> Students
                                            <span class="badge bg-white text-dark ms-3"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $qa_total_students; ?></span>
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
                                            <span class="badge bg-white text-dark ms-3"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $qa_total_internships; ?></span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="attendance.php" class="btn btn-warning btn-lg w-100 d-flex align-items-center justify-content-center">
                                            <i class="feather-calendar me-2"></i> Attendance Today
                                            <span class="badge bg-white text-dark ms-3"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $qa_attendance_today; ?></span>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="demo-biometric.php" class="btn btn-secondary btn-lg w-100 d-flex align-items-center justify-content-center">
                                            <i class="feather-activity me-2"></i> Biometric Demo
                                            <span class="badge bg-white text-dark ms-3"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $qa_biometric_registered; ?></span>
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

                    

                    <!-- [Latest Attendance Records] start -->
                    <div class="col-xxl-8 order-1 order-xxl-1 section-tight dashboard-movable" data-move-key="latest-attendance">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle" title="Drag to move this section">
                                <h5 class="card-title latest-attendance-title">Latest Attendance Records <span class="badge bg-soft-success text-success fs-11">Active Today: <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $today_attendance; ?></span></h5>
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
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if (count($recent_attendance) > 0): ?>
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($recent_attendance as $attendance): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-3">
                                                        <div class="avatar-text avatar-sm bg-soft-primary text-primary">
                                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
echo strtoupper(substr($attendance['first_name'], 0, 1) . substr($attendance['last_name'], 0, 1)); ?>
                                                        </div>
                                                        <a href="students-view.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance['student_id']; ?>">
                                                            <span class="d-block fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($attendance['first_name'] . ' ' . $attendance['last_name']); ?></span>
                                                            <span class="fs-12 d-block fw-normal text-muted"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($attendance['student_num']); ?></span>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
echo date('m/d/Y', strtotime($attendance['attendance_date'])); ?>
                                                </td>
                                                <td>
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $attendance['morning_time_in'] ? date('h:i a', strtotime($attendance['morning_time_in'])) : 'N/A'; ?>
                                                </td>
                                                <td>
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
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
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">No attendance records found</td>
                                            </tr>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer">
                                <ul class="list-unstyled d-flex align-items-center gap-2 mb-0 pagination-common-style" id="latest-attendance-pagination">
                                    <li>
                                        <a href="javascript:void(0);" data-role="prev"><i class="bi bi-arrow-left"></i></a>
                                    </li>
                                    <li><a href="javascript:void(0);" data-role="page" data-page="1" class="active">1</a></li>
                                    <li><a href="javascript:void(0);" data-role="page" data-page="2">2</a></li>
                                    <li>
                                        <a href="javascript:void(0);"><i class="bi bi-dot"></i></a>
                                    </li>
                                    <li><a href="javascript:void(0);" data-role="view-all">View All</a></li>
                                    <li>
                                        <a href="javascript:void(0);" data-role="next"><i class="bi bi-arrow-right"></i></a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <!-- [Latest Attendance Records] end -->
                    <!--! BEGIN: [Biometric Registration Status] !-->
                    <div class="col-xxl-4 order-2 order-xxl-2 section-tight dashboard-movable" data-move-key="biometric-status">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle" title="Drag to move this section">
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
                                                <span class="fs-18 fw-bold mb-1 d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $biometric_registered; ?></span>
                                                <span class="fs-10 fw-semibold text-uppercase d-block">Registered</span>
                                            </div>
                                            <div class="text-dark">
                                                <a href="demo-biometric.php" class="fw-bold mb-2 text-truncate-1-line">Students Registered</a>
                                                <span class="fs-11 fw-normal text-muted text-truncate-1-line"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo ($student_count > 0) ? round(($biometric_registered / $student_count) * 100) : 0; ?>% of total</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-3 border border-dashed rounded-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="wd-50 ht-50 bg-soft-warning text-warning lh-1 d-flex align-items-center justify-content-center flex-column rounded-2">
                                                <span class="fs-18 fw-bold mb-1 d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo ($student_count - $biometric_registered); ?></span>
                                                <span class="fs-10 fw-semibold text-uppercase d-block">Pending</span>
                                            </div>
                                            <div class="text-dark">
                                                <a href="demo-biometric.php" class="fw-bold mb-2 text-truncate-1-line">Awaiting Registration</a>
                                                <span class="fs-11 fw-normal text-muted text-truncate-1-line"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo ($student_count > 0) ? round((($student_count - $biometric_registered) / $student_count) * 100) : 0; ?>% pending</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="progress mb-3 ht-5">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php
require_once dirname(__DIR__) . '/config/db.php';
echo ($student_count > 0) ? round(($biometric_registered / $student_count) * 100) : 0; ?>%"></div>
                                </div>
                                <p class="text-muted text-center fs-12 mb-0">Overall Biometric Registration Progress</p>
                            </div>
                            <a href="demo-biometric.php" class="card-footer fs-11 fw-bold text-uppercase text-center py-4">Manage Biometric</a>
                        </div>
                    </div>
                    <!--! END: [Biometric Registration Status] !-->

                    <!--! BEGIN: [Coordinators List] !-->
                    <div class="col-xxl-4 dashboard-movable" data-move-key="coordinators">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle" title="Drag to move this section">
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
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if (count($coordinators) > 0): ?>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($coordinators as $coordinator): ?>
                                    <div class="hstack justify-content-between border border-dashed rounded-3 p-3 mb-3">
                                        <div class="hstack gap-3">
                                            <div class="avatar-text avatar-lg bg-soft-primary text-primary">
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
echo strtoupper(substr($coordinator['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($coordinator['name']); ?></a>
                                                <div class="fs-11 text-muted"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($coordinator['email']); ?></div>
                                            </div>
                                        </div>
                                        <span class="badge bg-soft-info text-info fs-10">Coordinator</span>
                                    </div>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                    <p class="text-muted text-center">No coordinators found</p>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                            </div>
                            <a href="javascript:void(0);" class="card-footer fs-11 fw-bold text-uppercase text-center py-3">View All Coordinators</a>
                        </div>
                    </div>
                    <!--! END: [Coordinators List] !-->
                    <!--! BEGIN: [Supervisors List] !-->
                    <div class="col-xxl-4 dashboard-movable" data-move-key="supervisors">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle" title="Drag to move this section">
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
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if (count($supervisors) > 0): ?>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($supervisors as $supervisor): ?>
                                    <div class="hstack justify-content-between border border-dashed rounded-3 p-3 mb-3">
                                        <div class="hstack gap-3">
                                            <div class="avatar-text avatar-lg bg-soft-success text-success">
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
echo strtoupper(substr($supervisor['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($supervisor['name']); ?></a>
                                                <div class="fs-11 text-muted"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($supervisor['email']); ?></div>
                                            </div>
                                        </div>
                                        <span class="badge bg-soft-success text-success fs-10">Supervisor</span>
                                    </div>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                    <p class="text-muted text-center">No supervisors found</p>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
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
                <span>Copyright Â©</span>
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
    <script src="assets/js/homepage-movable.js"></script>
    <!--! END: Apps Init !-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                if (typeof ApexCharts === 'undefined') return;
                var seriesData = [<?php
require_once dirname(__DIR__) . '/config/db.php';
echo isset($ojt_status_counts['pending']) ? intval($ojt_status_counts['pending']) : 0; ?>, <?php
require_once dirname(__DIR__) . '/config/db.php';
echo isset($ojt_status_counts['ongoing']) ? intval($ojt_status_counts['ongoing']) : 0; ?>, <?php
require_once dirname(__DIR__) . '/config/db.php';
echo isset($ojt_status_counts['completed']) ? intval($ojt_status_counts['completed']) : 0; ?>, <?php
require_once dirname(__DIR__) . '/config/db.php';
echo isset($ojt_status_counts['cancelled']) ? intval($ojt_status_counts['cancelled']) : 0; ?>];
                var opts = {
                    chart: { type: 'donut', height: 260 },
                    series: seriesData,
                    labels: ['Pending','Ongoing','Completed','Cancelled'],
                    colors: ['#f6c23e', '#36b9cc', '#1cc88a', '#e74a3b'],
                    legend: { position: 'bottom' },
                    responsive: [{ breakpoint: 768, options: { chart: { height: 200 }, legend: { position: 'bottom' } } }]
                };
                var el = document.querySelector('#ojt-overview-pie');
                if (el) {
                    var chart = new ApexCharts(el, opts);
                    chart.render();
                }
            } catch (e) {
                console.error('OJT chart init error', e);
            }
        });
    </script>
    <!--! BEGIN: Theme Customizer  !-->
    <script src="assets/js/theme-customizer-init.min.js"></script>
    <!--! END: Theme Customizer !-->
    <script>
        (function () {
            function collapseSidebarMenus() {
                if (!document.documentElement.classList.contains('minimenu')) return;
                document.querySelectorAll('.nxl-navigation .nxl-item.nxl-hasmenu.open, .nxl-navigation .nxl-item.nxl-hasmenu.nxl-trigger').forEach(function (item) {
                    item.classList.remove('open', 'nxl-trigger');
                });
            }

            function runAfterToggle() {
                collapseSidebarMenus();
                setTimeout(collapseSidebarMenus, 80);
                setTimeout(collapseSidebarMenus, 220);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', collapseSidebarMenus);
            } else {
                collapseSidebarMenus();
            }

            ['menu-mini-button', 'menu-expend-button', 'mobile-collapse'].forEach(function (id) {
                var btn = document.getElementById(id);
                if (btn) btn.addEventListener('click', runAfterToggle);
            });

            var nav = document.querySelector('.nxl-navigation');
            if (window.MutationObserver && nav) {
                var observer = new MutationObserver(function () {
                    if (document.documentElement.classList.contains('minimenu')) {
                        collapseSidebarMenus();
                    }
                });
                observer.observe(nav, { subtree: true, attributes: true, attributeFilter: ['class'] });
            }
        })();
    </script>
</body>

</html>



