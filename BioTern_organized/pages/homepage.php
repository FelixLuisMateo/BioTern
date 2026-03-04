<?php
// Start session early to avoid headers-sent warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include database connection
include_once dirname(__DIR__) . '/config/db.php';
include_once dirname(__DIR__) . '/includes/dashboard_data.php';

// Initialize analytics variables with defaults
$attendance_awaiting = 0;
$attendance_completed = 0;
$attendance_rejected = 0;
$attendance_total = 0;
$student_count = 0;
$internship_count = 0;
$active_students = 0;
$active_internships = 0;
$completed_internships = 0;
$today_attendance = 0;
$pending_biometrics = 0;
$attendance_approval_rate = 0;
$attendance_pending_rate = 0;
$active_student_rate = 0;
$biometric_registered = 0;
$recent_students = array();
$recent_attendance = array();
$coordinators = array();
$supervisors = array();
$recent_activities = array();

if (isset($dashboard_data) && is_array($dashboard_data) && !empty($dashboard_data)) {
    $attendance_awaiting = (int)($dashboard_data['pending_approvals'] ?? 0);
    $attendance_completed = (int)($dashboard_data['approved_attendances'] ?? 0);
    $attendance_rejected = (int)($dashboard_data['rejected_attendances'] ?? 0);
    $attendance_total = $attendance_awaiting + $attendance_completed + $attendance_rejected;
    $student_count = (int)($dashboard_data['total_students'] ?? 0);
    $active_students = (int)($dashboard_data['active_students'] ?? 0);
    $internship_count = (int)($dashboard_data['total_internships'] ?? 0);
    $active_internships = (int)($dashboard_data['active_internships'] ?? 0);
    $completed_internships = (int)($dashboard_data['completed_internships'] ?? 0);
    $biometric_registered = (int)($dashboard_data['biometric_students'] ?? 0);
    $today_attendance = (int)($dashboard_data['today_attendance'] ?? 0);

    if (isset($recent_attendances) && is_array($recent_attendances) && !empty($recent_attendances)) {
        $recent_attendance = $recent_attendances;
    }
}

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

    // Active students based on ongoing internships
    $active_students_query = $conn->query("\n        SELECT COUNT(DISTINCT s.id) AS count\n        FROM students s\n        INNER JOIN internships i ON i.student_id = s.id\n        WHERE s.deleted_at IS NULL\n          AND i.deleted_at IS NULL\n          AND i.status = 'ongoing'\n    ");
    if ($active_students_query) {
        $active_students = (int)$active_students_query->fetch_assoc()['count'];
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

    $active_internships = isset($ojt_status_counts['ongoing']) ? (int)$ojt_status_counts['ongoing'] : 0;
    $completed_internships = isset($ojt_status_counts['completed']) ? (int)$ojt_status_counts['completed'] : 0;
    
    // Biometric registered students
    $biometric_query = $conn->query("SELECT COUNT(*) as count FROM students WHERE biometric_registered = 1");
    if ($biometric_query) {
        $biometric_registered = (int)$biometric_query->fetch_assoc()['count'];
    }

    // Today's attendance volume
    $today = date('Y-m-d');
    $today_attendance_query = $conn->query("SELECT COUNT(*) as count FROM attendances WHERE DATE(attendance_date) = '{$today}'");
    if ($today_attendance_query) {
        $today_attendance = (int)$today_attendance_query->fetch_assoc()['count'];
    }

    $pending_biometrics = max(0, $student_count - $biometric_registered);
    $attendance_approval_rate = ($attendance_total > 0) ? round(($attendance_completed / $attendance_total) * 100) : 0;
    $attendance_pending_rate = ($attendance_total > 0) ? round(($attendance_awaiting / $attendance_total) * 100) : 0;
    $active_student_rate = ($student_count > 0) ? round(($active_students / $student_count) * 100) : 0;
    
    // Get recent students (last 5)
    $recent_students_query = $conn->query("\n        SELECT s.id, s.student_id, s.first_name, s.last_name, s.email, s.status, s.biometric_registered, s.created_at\n        FROM students s\n        WHERE s.deleted_at IS NULL\n        ORDER BY s.created_at DESC\n        LIMIT 5\n    ");
    
    if ($recent_students_query && $recent_students_query->num_rows > 0) {
        while ($row = $recent_students_query->fetch_assoc()) {
            $recent_students[] = $row;
        }
    }
    
    // Get recent attendance records (last 10) with student info
    $recent_attendance_query = $conn->query("\n        SELECT a.id, a.student_id, a.attendance_date, a.morning_time_in, a.morning_time_out, a.status, a.created_at, \n               s.first_name, s.last_name, s.email, s.student_id as student_num\n        FROM attendances a\n        LEFT JOIN students s ON a.student_id = s.id\n        ORDER BY (DATE(a.attendance_date) = CURDATE()) DESC, a.attendance_date DESC, a.created_at DESC\n        LIMIT 10\n    ");
    
    if ($recent_attendance_query && $recent_attendance_query->num_rows > 0) {
        while ($row = $recent_attendance_query->fetch_assoc()) {
            $recent_attendance[] = $row;
        }
    }
    
    // Get coordinators (Active)
    $coordinators_query = $conn->query("\n        SELECT u.id, u.name, u.email, c.department_id, c.phone, c.created_at\n        FROM users u\n        LEFT JOIN coordinators c ON u.id = c.user_id\n        WHERE u.role = 'coordinator' AND u.is_active = 1\n        ORDER BY u.created_at DESC\n        LIMIT 5\n    ");
    
    if ($coordinators_query && $coordinators_query->num_rows > 0) {
        while ($row = $coordinators_query->fetch_assoc()) {
            $coordinators[] = $row;
        }
    }
    
    // Get supervisors (Active)
    $supervisors_query = $conn->query("\n        SELECT\n            s.id AS supervisor_id,\n            s.user_id,\n            COALESCE(NULLIF(u.name, ''), TRIM(CONCAT(s.first_name, ' ', s.last_name))) AS name,\n            COALESCE(NULLIF(u.email, ''), s.email) AS email,\n            s.phone,\n            s.department_id,\n            s.specialization,\n            s.created_at\n        FROM supervisors s\n        LEFT JOIN users u ON u.id = s.user_id\n        WHERE s.is_active = 1\n          AND s.deleted_at IS NULL\n          AND (u.id IS NULL OR u.is_active = 1)\n        ORDER BY s.created_at DESC\n        LIMIT 5\n    ");
    
    if ($supervisors_query && $supervisors_query->num_rows > 0) {
        while ($row = $supervisors_query->fetch_assoc()) {
            $supervisors[] = $row;
        }
    }
    
    // Get recent activities (student registrations, attendance records, etc)
    $activities_query = $conn->query("\n        SELECT \n            CONCAT('Student Created: ', s.first_name, ' ', s.last_name) as activity,\n            s.created_at as activity_date,\n            'student_created' as activity_type,\n            s.id as entity_id\n        FROM students s\n        WHERE s.deleted_at IS NULL\n        UNION ALL\n        SELECT \n            CONCAT('Attendance Recorded for ', s.first_name, ' ', s.last_name) as activity,\n            a.created_at as activity_date,\n            'attendance_recorded' as activity_type,\n            a.id as entity_id\n        FROM attendances a\n        LEFT JOIN students s ON a.student_id = s.id\n        UNION ALL\n        SELECT \n            CONCAT('Biometric Registered: ', s.first_name, ' ', s.last_name) as activity,\n            s.biometric_registered_at as activity_date,\n            'biometric_registered' as activity_type,\n            s.id as entity_id\n        FROM students s\n        WHERE s.biometric_registered = 1 AND s.biometric_registered_at IS NOT NULL\n        ORDER BY activity_date DESC\n        LIMIT 15\n    ");
    
    if ($activities_query && $activities_query->num_rows > 0) {
        while ($row = $activities_query->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }
    
} catch (Exception $e) {
    // Database error - fallback to 0 values
    error_log("Dashboard error: " . $e->getMessage());
}
$page_title = 'BioTern || Dashboard';
$page_styles = ['assets/css/homepage-dashboard.css'];
include 'includes/header.php';?>
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
                        <button type="button" id="toggle-dashboard-layout" class="btn btn-sm btn-light-brand">
                            <i class="feather-move me-1"></i> Edit Layout
                        </button>
                        <button type="button" id="reset-dashboard-layout" class="btn btn-sm btn-light-brand">
                            <i class="feather-refresh-cw me-1"></i> Reset Layout
                        </button>
                        <a href="students.php" class="btn btn-sm btn-light-brand">
                            <i class="feather-users me-1"></i> Manage Students
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
                                        <h4 class="fw-bold text-dark mb-1"><?php echo $attendance_awaiting; ?></h4>
                                        <span class="badge bg-soft-warning text-warning"><?php echo $attendance_pending_rate; ?>% pending</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Biometric Pending</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo $pending_biometrics; ?></h4>
                                        <span class="badge bg-soft-danger text-danger text-wrap">students without registration</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Active Students</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo $active_students; ?></h4>
                                        <span class="badge bg-soft-primary text-primary"><?php echo $active_student_rate; ?>% of total students</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Active Internships</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo $active_internships; ?></h4>
                                        <span class="badge bg-soft-info text-info">ongoing placements</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Attendance Today</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo $today_attendance; ?></h4>
                                        <span class="badge bg-soft-secondary text-dark"><?php echo date('M d, Y'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xxl-2 col-lg-4 col-md-6">
                                <div class="card mb-0 h-100 kpi-card">
                                    <div class="card-body d-flex flex-column justify-content-between gap-2">
                                        <span class="fs-11 text-muted d-block mb-2">Attendance Approved</span>
                                        <h4 class="fw-bold text-dark mb-1"><?php echo $attendance_completed; ?></h4>
                                        <span class="badge bg-soft-success text-success"><?php echo $attendance_approval_rate; ?>% approval rate</span>
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
                                        <span class="fs-12 fw-semibold text-dark"><?php echo $attendance_completed; ?>/<?php echo $attendance_total; ?></span>
                                    </div>
                                    <div class="progress ht-5">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $attendance_approval_rate; ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="fs-12 text-muted">Biometric Enrollment</span>
                                        <span class="fs-12 fw-semibold text-dark"><?php echo $biometric_registered; ?>/<?php echo $student_count; ?></span>
                                    </div>
                                    <div class="progress ht-5">
                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo ($student_count > 0) ? round(($biometric_registered / $student_count) * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="fs-12 text-muted">Internship Completion</span>
                                        <span class="fs-12 fw-semibold text-dark"><?php echo $completed_internships; ?>/<?php echo $internship_count; ?></span>
                                    </div>
                                    <div class="progress ht-5">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo ($internship_count > 0) ? round(($completed_internships / $internship_count) * 100) : 0; ?>%"></div>
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
                                    <span class="badge bg-soft-warning text-warning fs-12"><?php echo $attendance_awaiting; ?></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-3 border border-dashed rounded-3 mb-3">
                                    <div>
                                        <div class="fw-semibold text-dark">Students Without Biometrics</div>
                                        <div class="fs-12 text-muted">Needs registration to enable attendance flow</div>
                                    </div>
                                    <span class="badge bg-soft-danger text-danger fs-12"><?php echo $pending_biometrics; ?></span>
                                </div>
                                <div class="d-flex align-items-center justify-content-between p-3 border border-dashed rounded-3">
                                    <div>
                                        <div class="fw-semibold text-dark">OJT Not Yet Completed</div>
                                        <div class="fs-12 text-muted">Internships still active or pending</div>
                                    </div>
                                    <span class="badge bg-soft-info text-info fs-12"><?php echo max(0, $internship_count - $completed_internships); ?></span>
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

                    <!-- [Admin Quick Actions] (moved next to Recent Activities) -->
                    <?php // Admin Quick Actions: moved to be side-by-side with Recent Activities ?>
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
                                $qa_total_students = 0;
                                $qa_total_internships = 0;
                                $qa_attendance_today = 0;
                                $qa_biometric_registered = 0;
                                if (isset($conn)) {
                                    function _safe_count_mov($conn, $table, $where = '1') {
                                        $safe = 0;
                                        $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
                                        if ($res && $res->num_rows > 0) {
                                            $q = $conn->query("SELECT COUNT(*) AS cnt FROM `" . $conn->real_escape_string($table) . "` WHERE {$where}");
                                            if ($q) {
                                                $r = $q->fetch_assoc();
                                                $safe = (int) ($r['cnt'] ?? 0);
                                            }
                                        }
                                        return $safe;
                                    }
                                    $qa_total_students = _safe_count_mov($conn, 'students', '1');
                                    $qa_total_internships = _safe_count_mov($conn, 'internships', '1');
                                    $qa_attendance_today = 0;
                                    $res = $conn->query("SHOW COLUMNS FROM `attendances` LIKE 'date'");
                                    if ($res && $res->num_rows > 0) {
                                        $qa_attendance_today = _safe_count_mov($conn, 'attendances', 'date = CURDATE()');
                                    } else {
                                        $res2 = $conn->query("SHOW COLUMNS FROM `attendances` LIKE 'log_time'");
                                        if ($res2 && $res2->num_rows > 0) {
                                            $qa_attendance_today = _safe_count_mov($conn, 'attendances', 'DATE(log_time) = CURDATE()');
                                        }
                                    }
                                    $res3 = $conn->query("SHOW COLUMNS FROM `students` LIKE 'biometric_registered'");
                                    if ($res3 && $res3->num_rows > 0) {
                                        $qa_biometric_registered = _safe_count_mov($conn, 'students', 'biometric_registered = 1');
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

                    

                    <!-- [Latest Attendance Records] start -->
                    <div class="col-xxl-8 order-1 order-xxl-1 section-tight dashboard-movable" data-move-key="latest-attendance">
                        <div class="card stretch stretch-full">
                            <div class="card-header dashboard-move-handle" title="Drag to move this section">
                                <h5 class="card-title latest-attendance-title">Latest Attendance Records <span class="badge bg-soft-success text-success fs-11">Active Today: <?php echo $today_attendance; ?></span></h5>
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
    <script src="assets/js/homepage-movable.js"></script>
    <!--! END: Apps Init !-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            try {
                if (typeof ApexCharts === 'undefined') return;
                var seriesData = [<?php echo isset($ojt_status_counts['pending']) ? intval($ojt_status_counts['pending']) : 0; ?>, <?php echo isset($ojt_status_counts['ongoing']) ? intval($ojt_status_counts['ongoing']) : 0; ?>, <?php echo isset($ojt_status_counts['completed']) ? intval($ojt_status_counts['completed']) : 0; ?>, <?php echo isset($ojt_status_counts['cancelled']) ? intval($ojt_status_counts['cancelled']) : 0; ?>];
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

