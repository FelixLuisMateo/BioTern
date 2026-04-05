<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/section_schedule.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_roles_page(['admin', 'coordinator', 'supervisor']);

$report_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$report_user_id = (int)($_SESSION['user_id'] ?? 0);
$report_is_supervisor = ($report_role === 'supervisor');
$report_supervisor_profile_id = 0;
if ($report_is_supervisor && $report_user_id > 0) {
    $scopeStmt = $conn->prepare('SELECT id FROM supervisors WHERE user_id = ? LIMIT 1');
    if ($scopeStmt instanceof mysqli_stmt) {
        $scopeStmt->bind_param('i', $report_user_id);
        $scopeStmt->execute();
        $scopeRow = $scopeStmt->get_result()->fetch_assoc();
        $report_supervisor_profile_id = (int)($scopeRow['id'] ?? 0);
        $scopeStmt->close();
    }
}

function report_machine_config_path(): string
{
    return dirname(__DIR__) . '/tools/biometric_machine_config.json';
}

function report_load_machine_config(): array
{
    $configPath = report_machine_config_path();
    if (!file_exists($configPath)) {
        return [];
    }

    $json = file_get_contents($configPath);
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function report_school_hours_config(?array $machineConfig = null): array
{
    $machineConfig = is_array($machineConfig) ? $machineConfig : report_load_machine_config();
    $start = section_schedule_format_time_input((string)($machineConfig['attendanceStartTime'] ?? '08:00:00'));
    $end = section_schedule_format_time_input((string)($machineConfig['attendanceEndTime'] ?? '19:00:00'));

    return [
        'schedule_time_in' => $start !== '' ? $start : '08:00',
        'schedule_time_out' => $end !== '' ? $end : '19:00',
        'late_after_time' => $start !== '' ? $start : '08:00',
    ];
}

function report_effective_schedule(array $attendance, array $machineConfig): array
{
    return section_schedule_effective_day(
        section_schedule_from_row($attendance),
        (string)($attendance['attendance_date'] ?? ''),
        report_school_hours_config($machineConfig)
    );
}

function report_collect_punch_values(array $attendance): array
{
    $values = [];
    foreach (['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out'] as $column) {
        $value = trim((string)($attendance[$column] ?? ''));
        if ($value === '' || $value === '00:00:00') {
            continue;
        }
        $values[] = $value;
    }

    usort($values, static function (string $left, string $right): int {
        return strcmp($left, $right);
    });

    return $values;
}

function report_parse_time($value): ?int
{
    $value = trim((string)$value);
    if ($value === '' || $value === '00:00:00') {
        return null;
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? null : $timestamp;
}

function report_schedule_bounds(array $attendance, array $machineConfig): array
{
    $schedule = report_effective_schedule($attendance, $machineConfig);

    return [
        'schedule' => $schedule,
        'official_start' => section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? '')) ?: '08:00:00',
        'official_end' => section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? '')) ?: '19:00:00',
        'late_after' => section_schedule_normalize_time_input((string)($schedule['late_after_time'] ?? ''))
            ?: (section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? '')) ?: '08:00:00'),
    ];
}

function report_clamped_duration_seconds(?int $startTs, ?int $endTs, string $windowStart, string $windowEnd): int
{
    if ($startTs === null || $endTs === null || $endTs <= $startTs) {
        return 0;
    }

    $windowStartTs = strtotime($windowStart);
    $windowEndTs = strtotime($windowEnd);
    if ($windowStartTs === false || $windowEndTs === false) {
        return max(0, $endTs - $startTs);
    }

    $clampedStart = max($startTs, $windowStartTs);
    $clampedEnd = min($endTs, $windowEndTs);
    return max(0, $clampedEnd - $clampedStart);
}

function report_credited_seconds(array $attendance, array $bounds): int
{
    $officialStart = (string)($bounds['official_start'] ?? '08:00:00');
    $officialEnd = (string)($bounds['official_end'] ?? '19:00:00');
    $totalSeconds = 0;

    foreach ([['morning_time_in', 'morning_time_out'], ['afternoon_time_in', 'afternoon_time_out']] as $pair) {
        $startTs = report_parse_time($attendance[$pair[0]] ?? null);
        $endTs = report_parse_time($attendance[$pair[1]] ?? null);
        $totalSeconds += report_clamped_duration_seconds($startTs, $endTs, $officialStart, $officialEnd);
    }

    $breakInTs = report_parse_time($attendance['break_time_in'] ?? null);
    $breakOutTs = report_parse_time($attendance['break_time_out'] ?? null);
    $totalSeconds -= report_clamped_duration_seconds($breakInTs, $breakOutTs, $officialStart, $officialEnd);

    return max(0, $totalSeconds);
}

function report_attendance_metrics(array $attendance, array $machineConfig): array
{
    $bounds = report_schedule_bounds($attendance, $machineConfig);
    $schedule = $bounds['schedule'];
    $punches = report_collect_punch_values($attendance);
    $firstPunch = $punches[0] ?? null;
    $lastPunch = $punches !== [] ? $punches[count($punches) - 1] : null;
    $officialStart = (string)$bounds['official_start'];
    $officialEnd = (string)$bounds['official_end'];
    $lateAfter = (string)$bounds['late_after'];

    $earlyHours = 0.0;
    $overtimeHours = 0.0;
    if ($firstPunch !== null && strcmp($firstPunch, $officialStart) < 0) {
        $earlyHours = round(max(0, strtotime($officialStart) - strtotime($firstPunch)) / 3600, 2);
    }
    if ($lastPunch !== null && strcmp($lastPunch, $officialEnd) > 0) {
        $overtimeHours = round(max(0, strtotime($lastPunch) - strtotime($officialEnd)) / 3600, 2);
    }

    $arrivalStatus = 'absent';
    if ($firstPunch !== null) {
        if (strcmp($firstPunch, $officialStart) < 0) {
            $arrivalStatus = 'early';
        } elseif (strcmp($firstPunch, $lateAfter) > 0) {
            $arrivalStatus = 'late';
        } else {
            $arrivalStatus = 'present';
        }
    }

    return [
        'schedule' => $schedule,
        'official_start' => $officialStart,
        'official_end' => $officialEnd,
        'first_punch' => $firstPunch,
        'last_punch' => $lastPunch,
        'early_hours' => $earlyHours,
        'overtime_hours' => $overtimeHours,
        'credited_hours' => round(report_credited_seconds($attendance, $bounds) / 3600, 2),
        'arrival_status' => $arrivalStatus,
    ];
}

function report_format_date(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '0000-00-00') {
        return '-';
    }
    $timestamp = strtotime($raw);
    return $timestamp ? date('M d, Y', $timestamp) : $raw;
}

function report_format_time(?string $value): string
{
    $raw = trim((string)$value);
    if ($raw === '' || $raw === '00:00:00') {
        return '-';
    }
    $timestamp = strtotime($raw);
    return $timestamp ? date('h:i A', $timestamp) : $raw;
}

function report_format_hours(float $hours): string
{
    return number_format($hours, 2) . 'h';
}

$machineConfig = report_load_machine_config();
$filter_start = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['start_date']) ? (string)$_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$filter_end = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_GET['end_date']) ? (string)$_GET['end_date'] : date('Y-m-d');
$filter_mode = isset($_GET['mode']) ? strtolower(trim((string)$_GET['mode'])) : 'all';
if (!in_array($filter_mode, ['all', 'early', 'overtime'], true)) {
    $filter_mode = 'all';
}

$where = [
    "a.attendance_date BETWEEN '" . $conn->real_escape_string($filter_start) . "' AND '" . $conn->real_escape_string($filter_end) . "'",
    "(a.status IS NULL OR a.status <> 'rejected')",
];
if ($report_is_supervisor && $report_user_id > 0) {
    $scopeParts = ["(i.supervisor_id = " . (int)$report_user_id . " OR s.supervisor_id = " . (int)$report_user_id . ")"];
    if ($report_supervisor_profile_id > 0 && $report_supervisor_profile_id !== $report_user_id) {
        $scopeParts[] = "(i.supervisor_id = " . (int)$report_supervisor_profile_id . " OR s.supervisor_id = " . (int)$report_supervisor_profile_id . ")";
    }
    $where[] = '(' . implode(' OR ', $scopeParts) . ')';
}

$query = "
    SELECT
        a.id,
        a.attendance_date,
        a.morning_time_in,
        a.morning_time_out,
        a.break_time_in,
        a.break_time_out,
        a.afternoon_time_in,
        a.afternoon_time_out,
        a.total_hours,
        a.source,
        a.status,
        s.id AS student_id,
        s.student_id AS student_number,
        s.first_name,
        s.last_name,
        sec.attendance_session,
        sec.schedule_time_in,
        sec.schedule_time_out,
        sec.late_after_time,
        sec.weekly_schedule_json
    FROM attendances a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.attendance_date DESC, a.id DESC, s.last_name ASC
";

$rows = [];
$res = $conn->query($query);
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $metrics = report_attendance_metrics($row, $machineConfig);
        $hasEarly = (float)$metrics['early_hours'] > 0;
        $hasOvertime = (float)$metrics['overtime_hours'] > 0;
        if ((!$hasEarly && !$hasOvertime) || ($filter_mode === 'early' && !$hasEarly) || ($filter_mode === 'overtime' && !$hasOvertime)) {
            continue;
        }
        $row['metrics'] = $metrics;
        $rows[] = $row;
    }
    $res->free();
}

$earlyCount = 0;
$overtimeCount = 0;
$totalEarlyHours = 0.0;
$totalOvertimeHours = 0.0;
foreach ($rows as $row) {
    $metrics = $row['metrics'];
    if ((float)$metrics['early_hours'] > 0) {
        $earlyCount++;
        $totalEarlyHours += (float)$metrics['early_hours'];
    }
    if ((float)$metrics['overtime_hours'] > 0) {
        $overtimeCount++;
        $totalOvertimeHours += (float)$metrics['overtime_hours'];
    }
}

$page_title = 'BioTern || Attendance Exceptions Report';
include 'includes/header.php';
?>
<style>
    .attendance-exceptions-hero,.attendance-exceptions-card{border:1px solid rgba(80,102,144,.14);background:#fff;border-radius:14px}
    .attendance-exceptions-hero{padding:1.1rem 1.25rem;margin-bottom:1rem}
    .attendance-exceptions-summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:.8rem;margin-bottom:1rem}
    .attendance-exceptions-kpi{border:1px solid rgba(80,102,144,.14);border-radius:12px;padding:1rem;background:#fff}
    .attendance-exceptions-kpi-label,.attendance-exceptions-toolbar .form-label{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:.35rem}
    .attendance-exceptions-kpi-value{font-size:1.55rem;font-weight:700;line-height:1.1}
    .attendance-exceptions-toolbar{display:flex;flex-wrap:wrap;gap:.75rem;align-items:end;margin-bottom:1rem}
    .attendance-exceptions-note{font-size:.85rem;color:#64748b}
    .attendance-exceptions-card .table{margin-bottom:0}
    .attendance-exceptions-card thead th{font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;white-space:nowrap;background:#f8fafc}
    .attendance-exceptions-badge{border-radius:999px;padding:.35rem .7rem;font-size:.75rem;font-weight:600;display:inline-flex;align-items:center;gap:.35rem}
    html.app-skin-dark .attendance-exceptions-hero,html.app-skin-dark .attendance-exceptions-card,html.app-skin-dark .attendance-exceptions-kpi{background:#0f172a;border-color:rgba(129,153,199,.24);color:#dce7ff}
    html.app-skin-dark .attendance-exceptions-kpi-label,html.app-skin-dark .attendance-exceptions-note{color:#9fb0d3}
    html.app-skin-dark .attendance-exceptions-card thead th{background:#111f36;color:#9fb0d3;border-bottom-color:rgba(129,153,199,.24)}
    html.app-skin-dark .attendance-exceptions-card .table{--bs-table-bg:#0f172a;--bs-table-hover-bg:#18243d;--bs-table-border-color:rgba(129,153,199,.18)}
</style>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Attendance Exceptions</h5><ul class="breadcrumb"><li class="breadcrumb-item"><a href="reports-ojt.php">Reports</a></li><li class="breadcrumb-item">Attendance Exceptions</li></ul></div>
    </div>
</div>
<div class="main-content pb-5">
    <div class="attendance-exceptions-hero d-flex flex-wrap justify-content-between gap-3">
        <div><h6 class="fw-bold mb-1">Early Birds and Late Birds</h6><p class="attendance-exceptions-note mb-0">Actual punch times stay visible for audit, while credited attendance hours stay clamped to the official schedule.</p></div>
        <span class="attendance-exceptions-badge bg-soft-primary text-primary"><i class="feather feather-activity"></i><?php echo htmlspecialchars(report_format_date($filter_start) . ' - ' . report_format_date($filter_end), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <form method="get" class="attendance-exceptions-toolbar">
        <div><label class="form-label" for="start_date">Start Date</label><input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filter_start, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div><label class="form-label" for="end_date">End Date</label><input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filter_end, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div><label class="form-label" for="mode">Mode</label><select class="form-select" id="mode" name="mode"><option value="all"<?php echo $filter_mode === 'all' ? ' selected' : ''; ?>>All Exceptions</option><option value="early"<?php echo $filter_mode === 'early' ? ' selected' : ''; ?>>Early Only</option><option value="overtime"<?php echo $filter_mode === 'overtime' ? ' selected' : ''; ?>>Overtime Only</option></select></div>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-primary">Apply</button><a href="reports-attendance-exceptions.php" class="btn btn-outline-secondary">Reset</a></div>
    </form>
    <div class="attendance-exceptions-summary">
        <div class="attendance-exceptions-kpi"><div class="attendance-exceptions-kpi-label">Exception Rows</div><div class="attendance-exceptions-kpi-value"><?php echo count($rows); ?></div></div>
        <div class="attendance-exceptions-kpi"><div class="attendance-exceptions-kpi-label">Early Arrivals</div><div class="attendance-exceptions-kpi-value"><?php echo $earlyCount; ?></div><div class="attendance-exceptions-note mt-1"><?php echo htmlspecialchars(report_format_hours($totalEarlyHours), ENT_QUOTES, 'UTF-8'); ?> total early time</div></div>
        <div class="attendance-exceptions-kpi"><div class="attendance-exceptions-kpi-label">Late Overtime</div><div class="attendance-exceptions-kpi-value"><?php echo $overtimeCount; ?></div><div class="attendance-exceptions-note mt-1"><?php echo htmlspecialchars(report_format_hours($totalOvertimeHours), ENT_QUOTES, 'UTF-8'); ?> total overtime</div></div>
    </div>
    <div class="attendance-exceptions-card"><div class="table-responsive"><table class="table table-hover align-middle">
        <thead><tr><th>Student</th><th>Date</th><th>First Punch</th><th>Official Start</th><th>Last Punch</th><th>Official End</th><th>Early</th><th>Overtime</th><th>Credited</th><th>Source</th></tr></thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="10" class="text-center py-5 text-muted">No early-arrival or overtime exceptions found for this range.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php $metrics = $row['metrics']; ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars(trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong><div class="text-muted small"><?php echo htmlspecialchars((string)($row['student_number'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div></td>
                        <td><?php echo htmlspecialchars(report_format_date((string)($row['attendance_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(report_format_time((string)($metrics['first_punch'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(report_format_time((string)($metrics['official_start'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(report_format_time((string)($metrics['last_punch'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(report_format_time((string)($metrics['official_end'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(report_format_hours((float)($metrics['early_hours'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(report_format_hours((float)($metrics['overtime_hours'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars(report_format_hours((float)($metrics['credited_hours'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string)($row['source'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table></div></div>
</div>
<?php include 'includes/footer.php'; ?>
