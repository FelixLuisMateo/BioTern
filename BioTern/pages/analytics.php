<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
require_once dirname(__DIR__) . '/includes/dashboard_data.php';

$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}

function analytics_table_exists(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function analytics_column_exists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = str_replace('`', '``', $table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

function analytics_count(mysqli $conn, string $sql, string $key = 'total'): int
{
    $result = $conn->query($sql);
    if (!$result instanceof mysqli_result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    $result->free();
    return (int)($row[$key] ?? 0);
}

function analytics_rows(mysqli $conn, string $sql): array
{
    $rows = [];
    $result = $conn->query($sql);
    if (!$result instanceof mysqli_result) {
        return $rows;
    }
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->free();
    return $rows;
}

function analytics_pct(int|float $part, int|float $whole): float
{
    if ((float)$whole <= 0) {
        return 0.0;
    }
    return round((((float)$part / (float)$whole) * 100), 2);
}

function analytics_date_label(string $date): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }
    return date('M d, Y', $timestamp);
}

function analytics_name_from_row(array $row): string
{
    $name = trim((string)(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
    if ($name !== '') {
        return $name;
    }

    $name = trim((string)($row['name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    return 'Unknown';
}

function analytics_format_hours(float $hours): string
{
    return number_format(max(0, $hours), 2) . 'h';
}

$stats = [
    'students_total' => (int)($dashboard_data['total_students'] ?? 0),
    'students_active' => (int)($dashboard_data['active_students'] ?? 0),
    'students_biometric' => (int)($dashboard_data['biometric_students'] ?? 0),
    'internships_total' => (int)($dashboard_data['total_internships'] ?? 0),
    'internships_active' => (int)($dashboard_data['active_internships'] ?? 0),
    'internships_completed' => (int)($dashboard_data['completed_internships'] ?? 0),
    'attendance_pending' => (int)($dashboard_data['pending_approvals'] ?? 0),
    'attendance_approved' => (int)($dashboard_data['approved_attendances'] ?? 0),
    'attendance_rejected' => (int)($dashboard_data['rejected_attendances'] ?? 0),
    'attendance_today' => (int)($dashboard_data['today_attendance'] ?? 0),
];

$stats['students_biometric_pending'] = max(0, $stats['students_total'] - $stats['students_biometric']);
$stats['attendance_total'] = $stats['attendance_pending'] + $stats['attendance_approved'] + $stats['attendance_rejected'];
$stats['attendance_approval_rate'] = analytics_pct($stats['attendance_approved'], $stats['attendance_total']);
$stats['attendance_pending_rate'] = analytics_pct($stats['attendance_pending'], $stats['attendance_total']);
$stats['attendance_rejected_rate'] = analytics_pct($stats['attendance_rejected'], $stats['attendance_total']);
$stats['internship_active_rate'] = analytics_pct($stats['internships_active'], $stats['internships_total']);
$stats['biometric_coverage_rate'] = analytics_pct($stats['students_biometric'], $stats['students_total']);
$stats['internship_avg_completion'] = 0.0;
$stats['internship_hours_completion_rate'] = 0.0;
$stats['attendance_source_biometric_rate'] = 0.0;
$stats['student_user_active_rate'] = 0.0;
$stats['student_user_approved_rate'] = 0.0;
$stats['application_approval_rate'] = 0.0;
$stats['login_success_rate'] = 0.0;

$applicationStats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];

$loginStats = [
    'total' => 0,
    'success' => 0,
    'failed' => 0,
];

$internshipStage = [
    'pending' => 0,
    'ongoing' => 0,
    'completed' => 0,
    'cancelled' => 0,
];

$hoursStats = [
    'required' => 0.0,
    'rendered' => 0.0,
    'approved_attendance_hours' => 0.0,
    'approved_attendance_avg' => 0.0,
    'biometric_logs' => 0,
    'manual_logs' => 0,
    'uploaded_logs' => 0,
];

$studentUserStats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];

$internshipType = [
    'internal' => 0,
    'external' => 0,
    'other' => 0,
];

if (isset($conn) && $conn instanceof mysqli && analytics_table_exists($conn, 'internships')) {
    $typeColumn = analytics_column_exists($conn, 'internships', 'type') ? 'type' : '';
    if ($typeColumn === '' && analytics_column_exists($conn, 'internships', 'assignment_track')) {
        $typeColumn = 'assignment_track';
    }

    if ($typeColumn !== '') {
        $whereDeleted = analytics_column_exists($conn, 'internships', 'deleted_at') ? ' WHERE deleted_at IS NULL' : '';
        $typeRows = analytics_rows(
            $conn,
            "SELECT LOWER(TRIM(COALESCE(`{$typeColumn}`, 'unknown'))) AS internship_type, COUNT(*) AS total
             FROM internships{$whereDeleted}
             GROUP BY LOWER(TRIM(COALESCE(`{$typeColumn}`, 'unknown')))"
        );

        foreach ($typeRows as $row) {
            $key = (string)($row['internship_type'] ?? 'other');
            $count = (int)($row['total'] ?? 0);
            if ($key === 'internal') {
                $internshipType['internal'] += $count;
            } elseif ($key === 'external') {
                $internshipType['external'] += $count;
            } else {
                $internshipType['other'] += $count;
            }
        }
    }
}

if (isset($conn) && $conn instanceof mysqli && analytics_table_exists($conn, 'internships')) {
    $whereDeleted = analytics_column_exists($conn, 'internships', 'deleted_at') ? ' WHERE deleted_at IS NULL' : '';

    $stageRows = analytics_rows(
        $conn,
        "SELECT LOWER(TRIM(COALESCE(status, 'pending'))) AS stage_key, COUNT(*) AS total
         FROM internships{$whereDeleted}
         GROUP BY LOWER(TRIM(COALESCE(status, 'pending')))"
    );
    foreach ($stageRows as $row) {
        $stageKey = (string)($row['stage_key'] ?? '');
        if (array_key_exists($stageKey, $internshipStage)) {
            $internshipStage[$stageKey] += (int)($row['total'] ?? 0);
        }
    }

    if (analytics_column_exists($conn, 'internships', 'required_hours') && analytics_column_exists($conn, 'internships', 'rendered_hours')) {
        $hoursSummary = analytics_rows(
            $conn,
            "SELECT
                COALESCE(SUM(COALESCE(required_hours, 0)), 0) AS required_hours,
                COALESCE(SUM(COALESCE(rendered_hours, 0)), 0) AS rendered_hours,
                COALESCE(AVG(COALESCE(completion_percentage, 0)), 0) AS avg_completion
             FROM internships{$whereDeleted}"
        );
        if (!empty($hoursSummary)) {
            $hoursStats['required'] = (float)($hoursSummary[0]['required_hours'] ?? 0);
            $hoursStats['rendered'] = (float)($hoursSummary[0]['rendered_hours'] ?? 0);
            $stats['internship_avg_completion'] = (float)($hoursSummary[0]['avg_completion'] ?? 0);
        } else {
            $stats['internship_avg_completion'] = 0.0;
        }
    } else {
        $stats['internship_avg_completion'] = 0.0;
    }
}

if (isset($conn) && $conn instanceof mysqli && analytics_table_exists($conn, 'attendances')) {
    $attendanceSummary = analytics_rows(
        $conn,
        "SELECT
            COALESCE(SUM(CASE WHEN status = 'approved' THEN COALESCE(total_hours, 0) ELSE 0 END), 0) AS approved_hours,
            COALESCE(AVG(CASE WHEN status = 'approved' THEN COALESCE(total_hours, 0) END), 0) AS avg_approved_hours,
            COALESCE(SUM(CASE WHEN source = 'biometric' THEN 1 ELSE 0 END), 0) AS biometric_total,
            COALESCE(SUM(CASE WHEN source = 'manual' THEN 1 ELSE 0 END), 0) AS manual_total,
            COALESCE(SUM(CASE WHEN source = 'uploaded' THEN 1 ELSE 0 END), 0) AS uploaded_total
         FROM attendances"
    );
    if (!empty($attendanceSummary)) {
        $hoursStats['approved_attendance_hours'] = (float)($attendanceSummary[0]['approved_hours'] ?? 0);
        $hoursStats['approved_attendance_avg'] = (float)($attendanceSummary[0]['avg_approved_hours'] ?? 0);
        $hoursStats['biometric_logs'] = (int)($attendanceSummary[0]['biometric_total'] ?? 0);
        $hoursStats['manual_logs'] = (int)($attendanceSummary[0]['manual_total'] ?? 0);
        $hoursStats['uploaded_logs'] = (int)($attendanceSummary[0]['uploaded_total'] ?? 0);
    }
}

if (isset($conn) && $conn instanceof mysqli && analytics_table_exists($conn, 'users')) {
    $userWhere = " WHERE LOWER(TRIM(role)) = 'student'";
    $userRows = analytics_rows(
        $conn,
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN COALESCE(is_active, 0) = 1 THEN 1 ELSE 0 END) AS active_total,
            SUM(CASE WHEN COALESCE(is_active, 0) <> 1 THEN 1 ELSE 0 END) AS inactive_total,
            SUM(CASE WHEN COALESCE(application_status, 'approved') = 'pending' THEN 1 ELSE 0 END) AS pending_total,
            SUM(CASE WHEN COALESCE(application_status, 'approved') = 'approved' THEN 1 ELSE 0 END) AS approved_total,
            SUM(CASE WHEN COALESCE(application_status, 'approved') = 'rejected' THEN 1 ELSE 0 END) AS rejected_total
         FROM users{$userWhere}"
    );
    if (!empty($userRows)) {
        $studentUserStats['total'] = (int)($userRows[0]['total'] ?? 0);
        $studentUserStats['active'] = (int)($userRows[0]['active_total'] ?? 0);
        $studentUserStats['inactive'] = (int)($userRows[0]['inactive_total'] ?? 0);
        $studentUserStats['pending'] = (int)($userRows[0]['pending_total'] ?? 0);
        $studentUserStats['approved'] = (int)($userRows[0]['approved_total'] ?? 0);
        $studentUserStats['rejected'] = (int)($userRows[0]['rejected_total'] ?? 0);
    }
}

if (isset($conn) && $conn instanceof mysqli && analytics_table_exists($conn, 'student_applications')) {
    $appRows = analytics_rows(
        $conn,
        "SELECT LOWER(TRIM(COALESCE(status, 'pending'))) AS status_key, COUNT(*) AS total
         FROM student_applications
         GROUP BY LOWER(TRIM(COALESCE(status, 'pending')))"
    );
    foreach ($appRows as $row) {
        $appKey = (string)($row['status_key'] ?? '');
        $appCount = (int)($row['total'] ?? 0);
        if (array_key_exists($appKey, $applicationStats)) {
            $applicationStats[$appKey] += $appCount;
            $applicationStats['total'] += $appCount;
        }
    }
}

if (isset($conn) && $conn instanceof mysqli && analytics_table_exists($conn, 'login_logs')) {
    $loginRows = analytics_rows(
        $conn,
        "SELECT LOWER(TRIM(COALESCE(status, 'failed'))) AS status_key, COUNT(*) AS total
         FROM login_logs
         GROUP BY LOWER(TRIM(COALESCE(status, 'failed')))"
    );
    foreach ($loginRows as $row) {
        $statusKey = (string)($row['status_key'] ?? 'failed');
        $count = (int)($row['total'] ?? 0);
        if ($statusKey === 'success') {
            $loginStats['success'] += $count;
        } else {
            $loginStats['failed'] += $count;
        }
        $loginStats['total'] += $count;
    }
}

$stats['internship_hours_completion_rate'] = analytics_pct($hoursStats['rendered'], max(1.0, $hoursStats['required']));
$stats['attendance_source_biometric_rate'] = analytics_pct($hoursStats['biometric_logs'], max(1, $stats['attendance_total']));
$stats['student_user_active_rate'] = analytics_pct($studentUserStats['active'], max(1, $studentUserStats['total']));
$stats['student_user_approved_rate'] = analytics_pct($studentUserStats['approved'], max(1, $studentUserStats['total']));
$stats['application_approval_rate'] = analytics_pct($applicationStats['approved'], max(1, $applicationStats['total']));
$stats['login_success_rate'] = analytics_pct($loginStats['success'], max(1, $loginStats['total']));

$recentStudents = [];
if (isset($conn) && $conn instanceof mysqli && analytics_table_exists($conn, 'students')) {
    $whereDeleted = analytics_column_exists($conn, 'students', 'deleted_at') ? ' WHERE s.deleted_at IS NULL' : '';
    $recentStudents = analytics_rows(
        $conn,
        "SELECT s.id, s.student_id, s.first_name, s.last_name, s.created_at
         FROM students s
         {$whereDeleted}
         ORDER BY s.created_at DESC, s.id DESC
         LIMIT 8"
    );
}

$recentInternships = [];
if (isset($conn) && $conn instanceof mysqli && analytics_table_exists($conn, 'internships')) {
    $whereDeleted = analytics_column_exists($conn, 'internships', 'deleted_at') ? ' WHERE i.deleted_at IS NULL' : '';
    $recentInternships = analytics_rows(
        $conn,
        "SELECT i.id, i.student_id, i.status, i.type, i.company_name, i.required_hours, i.completion_percentage, i.updated_at,
                s.first_name, s.last_name, s.student_id AS student_code
         FROM internships i
         LEFT JOIN students s ON s.id = i.student_id
         {$whereDeleted}
         ORDER BY i.updated_at DESC, i.id DESC
         LIMIT 8"
    );
}

$last7Days = [];
$maxLast7 = 0;
$dateBuckets = [];
for ($i = 6; $i >= 0; $i--) {
    $dateKey = date('Y-m-d', strtotime("-{$i} days"));
    $dateBuckets[$dateKey] = [
        'date' => $dateKey,
        'label' => date('D', strtotime($dateKey)),
        'total' => 0,
    ];
}

if (isset($conn) && $conn instanceof mysqli && analytics_table_exists($conn, 'attendances') && analytics_column_exists($conn, 'attendances', 'attendance_date')) {
    $windowRows = analytics_rows(
        $conn,
        "SELECT DATE(attendance_date) AS day_key, COUNT(*) AS total
         FROM attendances
         WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(attendance_date)"
    );
    foreach ($windowRows as $row) {
        $key = (string)($row['day_key'] ?? '');
        if ($key !== '' && isset($dateBuckets[$key])) {
            $dateBuckets[$key]['total'] = (int)($row['total'] ?? 0);
        }
    }
}

foreach ($dateBuckets as $bucket) {
    $last7Days[] = $bucket;
    if ((int)$bucket['total'] > $maxLast7) {
        $maxLast7 = (int)$bucket['total'];
    }
}
$maxLast7 = max(1, $maxLast7);

$page_title = 'BioTern || Analytics';
$page_body_class = 'analytics-page';
$page_styles = ['assets/css/modules/pages/page-analytics.css'];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header page-header-with-middle">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Analytics</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Analytics</li>
                </ul>
            </div>
            <div class="page-header-middle">
                <p class="page-header-statement">Operational snapshot for students, internships, and attendance.</p>
            </div>
            <?php ob_start(); ?>
                <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
                <a href="students.php" class="btn btn-outline-secondary"><i class="feather-users me-1"></i>Students</a>
            <?php
            biotern_render_page_header_actions([
                'menu_id' => 'analyticsActionsMenu',
                'items_html' => ob_get_clean(),
            ]);
            ?>
        </div>

        <div class="main-content pb-5 analytics-main-content">
            <section class="analytics-kpi-grid">
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Total Students</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['students_total']); ?></strong>
                    <small class="analytics-kpi-meta"><?php echo number_format($stats['students_active']); ?> active</small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Student Accounts Active</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['student_user_active_rate'], 2); ?>%</strong>
                    <small class="analytics-kpi-meta"><?php echo number_format($studentUserStats['active']); ?> of <?php echo number_format($studentUserStats['total']); ?></small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Biometric Coverage</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['biometric_coverage_rate'], 2); ?>%</strong>
                    <small class="analytics-kpi-meta"><?php echo number_format($stats['students_biometric_pending']); ?> pending registration</small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Student Approval Rate</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['student_user_approved_rate'], 2); ?>%</strong>
                    <small class="analytics-kpi-meta"><?php echo number_format($studentUserStats['pending']); ?> pending, <?php echo number_format($studentUserStats['rejected']); ?> rejected</small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Total Internships</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['internships_total']); ?></strong>
                    <small class="analytics-kpi-meta"><?php echo number_format($stats['internships_active']); ?> ongoing</small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Internship Completion (Avg)</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['internship_avg_completion'], 2); ?>%</strong>
                    <small class="analytics-kpi-meta">Across current internship records</small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Rendered vs Required Hours</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['internship_hours_completion_rate'], 2); ?>%</strong>
                    <small class="analytics-kpi-meta"><?php echo analytics_format_hours($hoursStats['rendered']); ?> of <?php echo analytics_format_hours($hoursStats['required']); ?></small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Attendance Approval</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['attendance_approval_rate'], 2); ?>%</strong>
                    <small class="analytics-kpi-meta"><?php echo number_format($stats['attendance_pending']); ?> pending reviews</small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Attendance Rejected</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['attendance_rejected_rate'], 2); ?>%</strong>
                    <small class="analytics-kpi-meta"><?php echo number_format($stats['attendance_rejected']); ?> rejected records</small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Biometric Source Share</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['attendance_source_biometric_rate'], 2); ?>%</strong>
                    <small class="analytics-kpi-meta"><?php echo number_format($hoursStats['biometric_logs']); ?> biometric logs</small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Application Approval</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['application_approval_rate'], 2); ?>%</strong>
                    <small class="analytics-kpi-meta"><?php echo number_format($applicationStats['pending']); ?> pending, <?php echo number_format($applicationStats['rejected']); ?> rejected</small>
                </article>
                <article class="analytics-kpi-card">
                    <span class="analytics-kpi-label">Login Success Rate</span>
                    <strong class="analytics-kpi-value"><?php echo number_format($stats['login_success_rate'], 2); ?>%</strong>
                    <small class="analytics-kpi-meta"><?php echo number_format($loginStats['success']); ?> success of <?php echo number_format($loginStats['total']); ?></small>
                </article>
            </section>

            <section class="analytics-panels-grid">
                <article class="analytics-panel-card">
                    <header class="analytics-panel-head">
                        <h6 class="analytics-panel-title">Last 7 Days Attendance</h6>
                    </header>
                    <div class="analytics-week-bars">
                        <?php foreach ($last7Days as $item): ?>
                            <?php $height = (int)round(((int)$item['total'] / $maxLast7) * 100); ?>
                            <div class="analytics-week-item">
                                <span class="analytics-week-total"><?php echo (int)$item['total']; ?></span>
                                <div class="analytics-week-track">
                                    <span class="analytics-week-fill" style="height: <?php echo max(4, $height); ?>%;"></span>
                                </div>
                                <span class="analytics-week-label"><?php echo htmlspecialchars((string)$item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="analytics-panel-card">
                    <header class="analytics-panel-head">
                        <h6 class="analytics-panel-title">Internship Type Mix</h6>
                    </header>
                    <div class="analytics-mix-list">
                        <?php
                        $mixTotal = max(1, $internshipType['internal'] + $internshipType['external'] + $internshipType['other']);
                        $mixRows = [
                            ['label' => 'Internal', 'value' => $internshipType['internal']],
                            ['label' => 'External', 'value' => $internshipType['external']],
                            ['label' => 'Other', 'value' => $internshipType['other']],
                        ];
                        ?>
                        <?php foreach ($mixRows as $mix): ?>
                            <?php $ratio = analytics_pct((int)$mix['value'], $mixTotal); ?>
                            <div class="analytics-mix-row">
                                <div class="analytics-mix-head">
                                    <span><?php echo htmlspecialchars((string)$mix['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span><?php echo number_format((int)$mix['value']); ?> (<?php echo number_format($ratio, 2); ?>%)</span>
                                </div>
                                <div class="analytics-mix-track">
                                    <span class="analytics-mix-fill" style="width: <?php echo max(0, min(100, $ratio)); ?>%;"></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </section>

            <section class="analytics-panels-grid">
                <article class="analytics-panel-card">
                    <header class="analytics-panel-head">
                        <h6 class="analytics-panel-title">Core Ratios</h6>
                    </header>
                    <div class="analytics-ratio-list">
                        <div class="analytics-ratio-item">
                            <div class="analytics-ratio-head"><span>Attendance Approved</span><strong><?php echo number_format($stats['attendance_approval_rate'], 2); ?>%</strong></div>
                            <div class="analytics-ratio-track"><span class="analytics-ratio-fill" style="width: <?php echo max(0, min(100, $stats['attendance_approval_rate'])); ?>%;"></span></div>
                        </div>
                        <div class="analytics-ratio-item">
                            <div class="analytics-ratio-head"><span>Attendance Pending</span><strong><?php echo number_format($stats['attendance_pending_rate'], 2); ?>%</strong></div>
                            <div class="analytics-ratio-track"><span class="analytics-ratio-fill" style="width: <?php echo max(0, min(100, $stats['attendance_pending_rate'])); ?>%;"></span></div>
                        </div>
                        <div class="analytics-ratio-item">
                            <div class="analytics-ratio-head"><span>Internship Hours Completion</span><strong><?php echo number_format($stats['internship_hours_completion_rate'], 2); ?>%</strong></div>
                            <div class="analytics-ratio-track"><span class="analytics-ratio-fill" style="width: <?php echo max(0, min(100, $stats['internship_hours_completion_rate'])); ?>%;"></span></div>
                        </div>
                        <div class="analytics-ratio-item">
                            <div class="analytics-ratio-head"><span>Student Application Approved</span><strong><?php echo number_format($stats['application_approval_rate'], 2); ?>%</strong></div>
                            <div class="analytics-ratio-track"><span class="analytics-ratio-fill" style="width: <?php echo max(0, min(100, $stats['application_approval_rate'])); ?>%;"></span></div>
                        </div>
                        <div class="analytics-ratio-item">
                            <div class="analytics-ratio-head"><span>Login Success</span><strong><?php echo number_format($stats['login_success_rate'], 2); ?>%</strong></div>
                            <div class="analytics-ratio-track"><span class="analytics-ratio-fill" style="width: <?php echo max(0, min(100, $stats['login_success_rate'])); ?>%;"></span></div>
                        </div>
                    </div>
                </article>

                <article class="analytics-panel-card">
                    <header class="analytics-panel-head">
                        <h6 class="analytics-panel-title">Status Breakdown</h6>
                    </header>
                    <div class="table-responsive">
                        <table class="table table-sm analytics-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Total</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Internships Ongoing</td>
                                    <td><?php echo number_format($internshipStage['ongoing']); ?></td>
                                    <td><?php echo number_format($internshipStage['pending']); ?> pending, <?php echo number_format($internshipStage['completed']); ?> completed</td>
                                </tr>
                                <tr>
                                    <td>Attendance Source</td>
                                    <td><?php echo number_format($hoursStats['biometric_logs']); ?></td>
                                    <td><?php echo number_format($hoursStats['manual_logs']); ?> manual, <?php echo number_format($hoursStats['uploaded_logs']); ?> uploaded</td>
                                </tr>
                                <tr>
                                    <td>Application Pipeline</td>
                                    <td><?php echo number_format($applicationStats['total']); ?></td>
                                    <td><?php echo number_format($applicationStats['pending']); ?> pending, <?php echo number_format($applicationStats['approved']); ?> approved, <?php echo number_format($applicationStats['rejected']); ?> rejected</td>
                                </tr>
                                <tr>
                                    <td>Approved Attendance Hours</td>
                                    <td><?php echo analytics_format_hours($hoursStats['approved_attendance_hours']); ?></td>
                                    <td>Average <?php echo analytics_format_hours($hoursStats['approved_attendance_avg']); ?> per approved record</td>
                                </tr>
                                <tr>
                                    <td>Student Account Health</td>
                                    <td><?php echo number_format($studentUserStats['active']); ?></td>
                                    <td><?php echo number_format($studentUserStats['inactive']); ?> inactive out of <?php echo number_format($studentUserStats['total']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="analytics-panels-grid">
                <article class="analytics-panel-card">
                    <header class="analytics-panel-head">
                        <h6 class="analytics-panel-title">Recent Students</h6>
                    </header>
                    <div class="table-responsive">
                        <table class="table table-sm analytics-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>ID Number</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentStudents)): ?>
                                    <?php foreach ($recentStudents as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(analytics_name_from_row($student), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($student['student_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(analytics_date_label((string)($student['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-muted text-center py-3">No student records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="analytics-panel-card">
                    <header class="analytics-panel-head">
                        <h6 class="analytics-panel-title">Recent Internship Updates</h6>
                    </header>
                    <div class="table-responsive">
                        <table class="table table-sm analytics-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentInternships)): ?>
                                    <?php foreach ($recentInternships as $internship): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(analytics_name_from_row($internship), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($internship['company_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-capitalize"><?php echo htmlspecialchars((string)($internship['status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(analytics_date_label((string)($internship['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-muted text-center py-3">No internship records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
