<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

function a_count(mysqli $conn, string $sql, string $key = 'count'): int
{
    $result = $conn->query($sql);
    if (!$result instanceof mysqli_result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    $result->close();
    return (int)($row[$key] ?? 0);
}

function a_rows(mysqli $conn, string $sql): array
{
    $rows = [];
    $result = $conn->query($sql);
    if (!$result instanceof mysqli_result) {
        return $rows;
    }
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $result->close();
    return $rows;
}

function a_table_has_column(mysqli $conn, string $table, string $column): bool
{
    $safeTable = str_replace('`', '``', $table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if (!$result instanceof mysqli_result) {
        return false;
    }
    $has = $result->num_rows > 0;
    $result->close();
    return $has;
}

function a_pct(int|float $part, int|float $whole): float
{
    return $whole > 0 ? round(($part / $whole) * 100, 2) : 0.0;
}

function analytics_label_initials(string $value): string
{
    $parts = preg_split('/\s+/', trim($value)) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part !== '') {
            $letters .= strtoupper(substr($part, 0, 1));
        }
        if (strlen($letters) >= 2) {
            break;
        }
    }

    return $letters !== '' ? $letters : 'BT';
}

function a_conic_gradient(array $segments): string
{
    $total = 0.0;
    foreach ($segments as $segment) {
        $total += max(0, (float)($segment['value'] ?? 0));
    }
    if ($total <= 0) {
        return 'conic-gradient(#334155 0deg 360deg)';
    }

    $start = 0.0;
    $parts = [];
    foreach ($segments as $segment) {
        $value = max(0, (float)($segment['value'] ?? 0));
        $color = (string)($segment['color'] ?? '#3454d1');
        $degrees = ($value / $total) * 360;
        $end = $start + $degrees;
        $parts[] = $color . ' ' . round($start, 2) . 'deg ' . round($end, 2) . 'deg';
        $start = $end;
    }

    if ($start < 360) {
        $parts[] = '#334155 ' . round($start, 2) . 'deg 360deg';
    }

    return 'conic-gradient(' . implode(', ', $parts) . ')';
}

function a_max_value(array $values): int
{
    $max = 0;
    foreach ($values as $value) {
        $max = max($max, (int)$value);
    }
    return max($max, 1);
}

$today = date('Y-m-d');
$monthKeys = [];
$monthLabels = [];
for ($i = 11; $i >= 0; $i--) {
    $monthKeys[] = date('Y-m', strtotime("-{$i} months"));
    $monthLabels[] = date('M Y', strtotime("-{$i} months"));
}

$studentTrend = array_fill_keys($monthKeys, 0);
$attendanceTrend = array_fill_keys($monthKeys, 0);
$internshipTrend = array_fill_keys($monthKeys, 0);
$weekLabels = [];
$weekMap = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $weekMap[$date] = 0;
    $weekLabels[] = date('D', strtotime($date));
}

$hasApplicationStatus = a_table_has_column($conn, 'users', 'application_status');
$approvedStudentCondition = $hasApplicationStatus
    ? "COALESCE(u.application_status, 'approved') = 'approved'"
    : '1 = 1';

$studentsTotal = a_count($conn, "SELECT COUNT(*) AS count FROM students s LEFT JOIN users u ON u.id = s.user_id WHERE s.deleted_at IS NULL AND {$approvedStudentCondition}");
$studentsActive = a_count($conn, "SELECT COUNT(DISTINCT s.id) AS count FROM students s LEFT JOIN users u ON u.id = s.user_id INNER JOIN internships i ON i.student_id = s.id WHERE s.deleted_at IS NULL AND i.deleted_at IS NULL AND i.status = 'ongoing' AND {$approvedStudentCondition}");
$studentsBiometric = a_count($conn, "SELECT COUNT(*) AS count FROM students s LEFT JOIN users u ON u.id = s.user_id WHERE s.deleted_at IS NULL AND s.biometric_registered = 1 AND {$approvedStudentCondition}");
$attendanceTotal = a_count($conn, "SELECT COUNT(*) AS count FROM attendances");
$attendanceToday = a_count($conn, "SELECT COUNT(*) AS count FROM attendances WHERE DATE(attendance_date) = '{$today}'");
$internshipsTotal = a_count($conn, "SELECT COUNT(*) AS count FROM internships i INNER JOIN students s ON s.id = i.student_id LEFT JOIN users u ON u.id = s.user_id WHERE i.deleted_at IS NULL AND s.deleted_at IS NULL AND {$approvedStudentCondition}");
$internshipsActive = a_count($conn, "SELECT COUNT(*) AS count FROM internships i INNER JOIN students s ON s.id = i.student_id LEFT JOIN users u ON u.id = s.user_id WHERE i.deleted_at IS NULL AND s.deleted_at IS NULL AND i.status = 'ongoing' AND {$approvedStudentCondition}");
$internshipsCompleted = a_count($conn, "SELECT COUNT(*) AS count FROM internships i INNER JOIN students s ON s.id = i.student_id LEFT JOIN users u ON u.id = s.user_id WHERE i.deleted_at IS NULL AND s.deleted_at IS NULL AND i.status = 'completed' AND {$approvedStudentCondition}");

$attendanceStatus = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0];
foreach (a_rows($conn, "SELECT status, COUNT(*) AS count FROM attendances GROUP BY status") as $row) {
    $status = strtolower(trim((string)($row['status'] ?? '')));
    if ($status === 'pending') $attendanceStatus['Pending'] = (int)$row['count'];
    if ($status === 'approved') $attendanceStatus['Approved'] = (int)$row['count'];
    if ($status === 'rejected') $attendanceStatus['Rejected'] = (int)$row['count'];
}

$internshipStatus = ['Pending' => 0, 'Ongoing' => 0, 'Completed' => 0, 'Cancelled' => 0];
foreach (a_rows($conn, "SELECT i.status, COUNT(*) AS count FROM internships i INNER JOIN students s ON s.id = i.student_id LEFT JOIN users u ON u.id = s.user_id WHERE i.deleted_at IS NULL AND s.deleted_at IS NULL AND {$approvedStudentCondition} GROUP BY i.status") as $row) {
    $status = strtolower(trim((string)($row['status'] ?? '')));
    if ($status === 'pending') $internshipStatus['Pending'] = (int)$row['count'];
    if ($status === 'ongoing') $internshipStatus['Ongoing'] = (int)$row['count'];
    if ($status === 'completed') $internshipStatus['Completed'] = (int)$row['count'];
    if ($status === 'cancelled') $internshipStatus['Cancelled'] = (int)$row['count'];
}

$internshipTypes = ['Internal' => 0, 'External' => 0];
foreach (a_rows($conn, "SELECT i.type, COUNT(*) AS count FROM internships i INNER JOIN students s ON s.id = i.student_id LEFT JOIN users u ON u.id = s.user_id WHERE i.deleted_at IS NULL AND s.deleted_at IS NULL AND {$approvedStudentCondition} GROUP BY i.type") as $row) {
    $type = strtolower(trim((string)($row['type'] ?? '')));
    if ($type === 'internal') $internshipTypes['Internal'] = (int)$row['count'];
    if ($type === 'external') $internshipTypes['External'] = (int)$row['count'];
}

foreach (a_rows($conn, "SELECT DATE_FORMAT(s.created_at, '%Y-%m') AS ym, COUNT(*) AS count FROM students s LEFT JOIN users u ON u.id = s.user_id WHERE s.deleted_at IS NULL AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) AND {$approvedStudentCondition} GROUP BY ym") as $row) {
    if (isset($studentTrend[$row['ym']])) $studentTrend[$row['ym']] = (int)$row['count'];
}
foreach (a_rows($conn, "SELECT DATE_FORMAT(attendance_date, '%Y-%m') AS ym, COUNT(*) AS count FROM attendances WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY ym") as $row) {
    if (isset($attendanceTrend[$row['ym']])) $attendanceTrend[$row['ym']] = (int)$row['count'];
}
foreach (a_rows($conn, "SELECT DATE_FORMAT(i.created_at, '%Y-%m') AS ym, COUNT(*) AS count FROM internships i INNER JOIN students s ON s.id = i.student_id LEFT JOIN users u ON u.id = s.user_id WHERE i.deleted_at IS NULL AND s.deleted_at IS NULL AND i.created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) AND {$approvedStudentCondition} GROUP BY ym") as $row) {
    if (isset($internshipTrend[$row['ym']])) $internshipTrend[$row['ym']] = (int)$row['count'];
}
foreach (a_rows($conn, "SELECT DATE(attendance_date) AS day_key, COUNT(*) AS count FROM attendances WHERE DATE(attendance_date) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY day_key") as $row) {
    if (isset($weekMap[$row['day_key']])) $weekMap[$row['day_key']] = (int)$row['count'];
}

$recentStudents = a_rows($conn, "SELECT s.student_id, s.first_name, s.last_name, s.biometric_registered FROM students s LEFT JOIN users u ON u.id = s.user_id WHERE s.deleted_at IS NULL AND {$approvedStudentCondition} ORDER BY s.created_at DESC LIMIT 5");
$recentInternships = a_rows($conn, "SELECT i.company_name, i.status, i.type, i.completion_percentage, s.first_name, s.last_name FROM internships i LEFT JOIN students s ON s.id = i.student_id LEFT JOIN users u ON u.id = s.user_id WHERE i.deleted_at IS NULL AND s.deleted_at IS NULL AND {$approvedStudentCondition} ORDER BY i.updated_at DESC, i.created_at DESC LIMIT 5");

$attendanceApprovalRate = a_pct($attendanceStatus['Approved'], $attendanceTotal);
$biometricCoverageRate = a_pct($studentsBiometric, $studentsTotal);
$activeInternshipRate = a_pct($internshipsActive, $internshipsTotal);
$growthMax = a_max_value(array_merge(array_values($studentTrend), array_values($attendanceTrend), array_values($internshipTrend)));
$weekMax = a_max_value(array_values($weekMap));
$attendanceChartGradient = a_conic_gradient([
    ['value' => $attendanceStatus['Approved'], 'color' => '#22c55e'],
    ['value' => $attendanceStatus['Pending'], 'color' => '#f59e0b'],
    ['value' => $attendanceStatus['Rejected'], 'color' => '#ef4444'],
]);
$biometricChartGradient = a_conic_gradient([
    ['value' => $studentsBiometric, 'color' => '#3454d1'],
    ['value' => max(0, $studentsTotal - $studentsBiometric), 'color' => '#94a3b8'],
]);
$internshipTypeGradient = a_conic_gradient([
    ['value' => $internshipTypes['Internal'], 'color' => '#0ea5e9'],
    ['value' => $internshipTypes['External'], 'color' => '#8b5cf6'],
]);

$page_title = 'BioTern || Analytics';
$page_body_class = 'page-analytics';
$page_styles = ['assets/css/layout/page_shell.css', 'assets/css/modules/pages/page-analytics.css'];
$page_vendor_scripts = [];
$page_scripts = [];

include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Analytics</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Analytics</li>
                </ul>
            </div>
        </div>

        <section class="analytics-hero">
            <div class="analytics-hero-copy">
                <span class="analytics-kicker">Homepage data, visual-first</span>
                <h1>See movement, balance, and workload across BioTern.</h1>
                <p>This page keeps the same core operational scope as the homepage, but translates it into charts and visual summaries instead of mostly number cards.</p>
            </div>
            <div class="analytics-hero-glance">
                <div class="analytics-glance-card">
                    <span class="analytics-glance-label">Approval Rate</span>
                    <strong><?php echo number_format(a_pct($attendanceStatus['Approved'], $attendanceTotal), 2); ?>%</strong>
                    <small><?php echo number_format($attendanceStatus['Approved']); ?> approved attendance entries</small>
                </div>
            </div>
        </section>
        <section class="analytics-metrics-grid">
            <article class="analytics-metric-card analytics-metric-card--primary">
                <span class="analytics-metric-label">Students</span>
                <strong><?php echo number_format($studentsTotal); ?></strong>
                <p><?php echo number_format($studentsActive); ?> active in ongoing internships</p>
            </article>
            <article class="analytics-metric-card">
                <span class="analytics-metric-label">Attendance</span>
                <strong><?php echo number_format($attendanceTotal); ?></strong>
                <p><?php echo number_format($attendanceToday); ?> logged today</p>
            </article>
            <article class="analytics-metric-card">
                <span class="analytics-metric-label">Internships</span>
                <strong><?php echo number_format($internshipsTotal); ?></strong>
                <p><?php echo number_format($internshipsActive); ?> ongoing, <?php echo number_format($internshipsCompleted); ?> completed</p>
            </article>
            <article class="analytics-metric-card">
                <span class="analytics-metric-label">Biometric</span>
                <strong><?php echo number_format($studentsBiometric); ?></strong>
                <p><?php echo number_format(max(0, $studentsTotal - $studentsBiometric)); ?> still pending</p>
            </article>
        </section>

        <section class="row g-4">
            <div class="col-xxl-8">
                <div class="card analytics-card stretch stretch-full">
                    <div class="card-header"><div><h5 class="card-title mb-1">Platform Growth</h5><p class="analytics-card-subtitle">12-month activity across students, attendance, and internships.</p></div></div>
                    <div class="card-body">
                        <div class="analytics-chart-shell analytics-chart-shell--tall">
                            <div class="analytics-chart-legend">
                                <span><i class="analytics-dot analytics-dot--students"></i>Students</span>
                                <span><i class="analytics-dot analytics-dot--attendance"></i>Attendance</span>
                                <span><i class="analytics-dot analytics-dot--internships"></i>Internships</span>
                            </div>
                            <div class="analytics-bars-chart">
                                <?php foreach ($monthLabels as $index => $label): ?>
                                    <?php
                                    $studentValue = (int)($studentTrend[$monthKeys[$index]] ?? 0);
                                    $attendanceValue = (int)($attendanceTrend[$monthKeys[$index]] ?? 0);
                                    $internshipValue = (int)($internshipTrend[$monthKeys[$index]] ?? 0);
                                    ?>
                                    <div class="analytics-bars-group">
                                        <div class="analytics-bars-stack">
                                            <span class="analytics-bar analytics-bar--students" style="height: <?php echo max(4, round(($studentValue / $growthMax) * 100, 2)); ?>%"></span>
                                            <span class="analytics-bar analytics-bar--attendance" style="height: <?php echo max(4, round(($attendanceValue / $growthMax) * 100, 2)); ?>%"></span>
                                            <span class="analytics-bar analytics-bar--internships" style="height: <?php echo max(4, round(($internshipValue / $growthMax) * 100, 2)); ?>%"></span>
                                        </div>
                                        <div class="analytics-bars-label"><?php echo htmlspecialchars(date('M y', strtotime($monthKeys[$index] . '-01')), ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="analytics-chart-fallback">
                            <?php foreach ($monthLabels as $index => $label): ?>
                                <?php $combined = (int)($studentTrend[$monthKeys[$index]] ?? 0) + (int)($attendanceTrend[$monthKeys[$index]] ?? 0) + (int)($internshipTrend[$monthKeys[$index]] ?? 0); ?>
                                <div class="analytics-fallback-row">
                                    <div class="analytics-fallback-head">
                                        <span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span><?php echo number_format($combined); ?> total activity</span>
                                    </div>
                                    <div class="analytics-fallback-track">
                                        <div class="analytics-fallback-fill" style="width: <?php echo max(6, min(100, round(($combined / max($growthMax, 1)) * 100, 2))); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xxl-4">
                <div class="card analytics-card stretch stretch-full">
                    <div class="card-header"><div><h5 class="card-title mb-1">Attendance Status</h5><p class="analytics-card-subtitle">Current review distribution.</p></div></div>
                    <div class="card-body">
                        <div class="analytics-ring-chart-block">
                            <div class="analytics-ring-chart" style="--ring-fill: <?php echo htmlspecialchars($attendanceChartGradient, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="analytics-ring-center">
                                    <strong><?php echo number_format($attendanceTotal); ?></strong>
                                    <span>Total</span>
                                </div>
                            </div>
                            <div class="analytics-ring-legend">
                                <div class="analytics-ring-legend-item"><i class="analytics-dot analytics-dot--approved"></i><span>Approved</span><strong><?php echo number_format($attendanceStatus['Approved']); ?></strong></div>
                                <div class="analytics-ring-legend-item"><i class="analytics-dot analytics-dot--pending"></i><span>Pending</span><strong><?php echo number_format($attendanceStatus['Pending']); ?></strong></div>
                                <div class="analytics-ring-legend-item"><i class="analytics-dot analytics-dot--rejected"></i><span>Rejected</span><strong><?php echo number_format($attendanceStatus['Rejected']); ?></strong></div>
                            </div>
                        </div>
                        <div class="analytics-chart-fallback">
                            <div class="analytics-fallback-row">
                                <div class="analytics-fallback-head"><span>Approved</span><span><?php echo number_format($attendanceStatus['Approved']); ?></span></div>
                                <div class="analytics-fallback-track"><div class="analytics-fallback-fill analytics-fallback-fill--success" style="width: <?php echo max(6, min(100, $attendanceApprovalRate)); ?>%"></div></div>
                            </div>
                            <div class="analytics-fallback-row">
                                <div class="analytics-fallback-head"><span>Pending</span><span><?php echo number_format($attendanceStatus['Pending']); ?></span></div>
                                <div class="analytics-fallback-track"><div class="analytics-fallback-fill analytics-fallback-fill--warning" style="width: <?php echo max(6, min(100, a_pct($attendanceStatus['Pending'], $attendanceTotal))); ?>%"></div></div>
                            </div>
                            <div class="analytics-fallback-row">
                                <div class="analytics-fallback-head"><span>Rejected</span><span><?php echo number_format($attendanceStatus['Rejected']); ?></span></div>
                                <div class="analytics-fallback-track"><div class="analytics-fallback-fill analytics-fallback-fill--danger" style="width: <?php echo max(6, min(100, a_pct($attendanceStatus['Rejected'], $attendanceTotal))); ?>%"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card analytics-card stretch stretch-full">
                    <div class="card-header"><div><h5 class="card-title mb-1">Internship Status</h5><p class="analytics-card-subtitle">Pipeline health snapshot.</p></div></div>
                    <div class="card-body">
                        <div class="analytics-segmented-card">
                            <div class="analytics-segmented-bar">
                                <?php foreach ($internshipStatus as $label => $value): ?>
                                    <?php
                                    $segmentClass = strtolower($label);
                                    $segmentWidth = max($value > 0 ? 8 : 0, round(a_pct($value, $internshipsTotal), 2));
                                    ?>
                                    <span class="analytics-segment analytics-segment--<?php echo htmlspecialchars($segmentClass, ENT_QUOTES, 'UTF-8'); ?>" style="width: <?php echo $segmentWidth; ?>%"></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="analytics-chart-fallback">
                            <?php foreach ($internshipStatus as $label => $value): ?>
                                <div class="analytics-fallback-row">
                                    <div class="analytics-fallback-head"><span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo number_format($value); ?></span></div>
                                    <div class="analytics-fallback-track"><div class="analytics-fallback-fill analytics-fallback-fill--info" style="width: <?php echo max(6, min(100, a_pct($value, $internshipsTotal))); ?>%"></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card analytics-card stretch stretch-full">
                    <div class="card-header"><div><h5 class="card-title mb-1">Biometric Coverage</h5><p class="analytics-card-subtitle">Registered versus pending students.</p></div></div>
                    <div class="card-body">
                        <div class="analytics-ring-chart-block analytics-ring-chart-block--compact">
                            <div class="analytics-ring-chart analytics-ring-chart--small" style="--ring-fill: <?php echo htmlspecialchars($biometricChartGradient, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="analytics-ring-center">
                                    <strong><?php echo number_format($biometricCoverageRate, 1); ?>%</strong>
                                    <span>Covered</span>
                                </div>
                            </div>
                        </div>
                        <div class="analytics-chart-fallback">
                            <div class="analytics-fallback-row">
                                <div class="analytics-fallback-head"><span>Registered</span><span><?php echo number_format($studentsBiometric); ?></span></div>
                                <div class="analytics-fallback-track"><div class="analytics-fallback-fill" style="width: <?php echo max(6, min(100, $biometricCoverageRate)); ?>%"></div></div>
                            </div>
                            <div class="analytics-fallback-row">
                                <div class="analytics-fallback-head"><span>Pending</span><span><?php echo number_format(max(0, $studentsTotal - $studentsBiometric)); ?></span></div>
                                <div class="analytics-fallback-track"><div class="analytics-fallback-fill analytics-fallback-fill--warning" style="width: <?php echo max(6, min(100, 100 - $biometricCoverageRate)); ?>%"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card analytics-card stretch stretch-full">
                    <div class="card-header"><div><h5 class="card-title mb-1">Internship Type Mix</h5><p class="analytics-card-subtitle">Internal and external balance.</p></div></div>
                    <div class="card-body">
                        <div class="analytics-ring-chart-block analytics-ring-chart-block--compact">
                            <div class="analytics-ring-chart analytics-ring-chart--small" style="--ring-fill: <?php echo htmlspecialchars($internshipTypeGradient, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="analytics-ring-center">
                                    <strong><?php echo number_format($internshipsTotal); ?></strong>
                                    <span>Total</span>
                                </div>
                            </div>
                        </div>
                        <div class="analytics-chart-fallback">
                            <?php foreach ($internshipTypes as $label => $value): ?>
                                <div class="analytics-fallback-row">
                                    <div class="analytics-fallback-head"><span><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo number_format($value); ?></span></div>
                                    <div class="analytics-fallback-track"><div class="analytics-fallback-fill analytics-fallback-fill--violet" style="width: <?php echo max(6, min(100, a_pct($value, $internshipsTotal))); ?>%"></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card analytics-card stretch stretch-full">
                    <div class="card-header"><div><h5 class="card-title mb-1">Last 7 Days Attendance</h5><p class="analytics-card-subtitle">Recent attendance volume.</p></div></div>
                    <div class="card-body">
                        <div class="analytics-chart-shell">
                            <div class="analytics-week-chart">
                                <?php foreach (array_values($weekMap) as $index => $value): ?>
                                    <div class="analytics-week-day">
                                        <div class="analytics-week-bar-wrap">
                                            <span class="analytics-week-bar" style="height: <?php echo max(6, min(100, round(($value / max($weekMax, 1)) * 100, 2))); ?>%"></span>
                                        </div>
                                        <div class="analytics-week-meta">
                                            <strong><?php echo number_format($value); ?></strong>
                                            <span><?php echo htmlspecialchars($weekLabels[$index] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card analytics-card stretch stretch-full">
                    <div class="card-header"><div><h5 class="card-title mb-1">Coverage Progress</h5><p class="analytics-card-subtitle">Operational ratios based on current data.</p></div></div>
                    <div class="card-body">
                        <div class="analytics-progress-list">
                            <div class="analytics-progress-item"><div class="analytics-progress-head"><span>Biometric coverage</span><strong><?php echo number_format($biometricCoverageRate, 2); ?>%</strong></div><div class="progress"><div class="progress-bar bg-primary" style="width: <?php echo max(0, min(100, $biometricCoverageRate)); ?>%"></div></div></div>
                            <div class="analytics-progress-item"><div class="analytics-progress-head"><span>Attendance approval</span><strong><?php echo number_format($attendanceApprovalRate, 2); ?>%</strong></div><div class="progress"><div class="progress-bar bg-success" style="width: <?php echo max(0, min(100, $attendanceApprovalRate)); ?>%"></div></div></div>
                            <div class="analytics-progress-item"><div class="analytics-progress-head"><span>Active internship share</span><strong><?php echo number_format($activeInternshipRate, 2); ?>%</strong></div><div class="progress"><div class="progress-bar bg-info" style="width: <?php echo max(0, min(100, $activeInternshipRate)); ?>%"></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card analytics-card stretch stretch-full">
                    <div class="card-header"><div><h5 class="card-title mb-1">Recent Students</h5><p class="analytics-card-subtitle">Newest student records.</p></div></div>
                    <div class="card-body">
                        <div class="analytics-list">
                            <?php foreach ($recentStudents as $student): ?>
                                <?php $name = trim((string)($student['first_name'] ?? '') . ' ' . (string)($student['last_name'] ?? '')); ?>
                                <div class="analytics-list-item">
                                    <div class="analytics-avatar"><?php echo htmlspecialchars(analytics_label_initials($name), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="analytics-list-copy">
                                        <strong><?php echo htmlspecialchars($name !== '' ? $name : 'Unnamed student', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <small><?php echo htmlspecialchars((string)($student['student_id'] ?? 'No student number'), ENT_QUOTES, 'UTF-8'); ?></small>
                                    </div>
                                    <span class="badge <?php echo !empty($student['biometric_registered']) ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning'; ?>"><?php echo !empty($student['biometric_registered']) ? 'Biometric' : 'Pending'; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card analytics-card stretch stretch-full">
                    <div class="card-header"><div><h5 class="card-title mb-1">Recent Internships</h5><p class="analytics-card-subtitle">Latest updated internship records.</p></div></div>
                    <div class="card-body">
                        <div class="analytics-list">
                            <?php foreach ($recentInternships as $row): ?>
                                <?php $studentName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')); ?>
                                <?php $company = trim((string)($row['company_name'] ?? '')); ?>
                                <div class="analytics-list-item analytics-list-item--stacked">
                                    <div class="analytics-list-copy">
                                        <strong><?php echo htmlspecialchars($company !== '' ? $company : ($studentName !== '' ? $studentName : 'Unnamed internship'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <small><?php echo htmlspecialchars(($studentName !== '' ? $studentName : 'Unknown student') . ' | ' . strtoupper((string)($row['type'] ?? 'n/a')), ENT_QUOTES, 'UTF-8'); ?></small>
                                    </div>
                                    <div class="analytics-inline-meta">
                                        <span class="badge bg-soft-info text-info"><?php echo htmlspecialchars(ucfirst((string)($row['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="analytics-inline-stat"><?php echo number_format((float)($row['completion_percentage'] ?? 0), 2); ?>%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
