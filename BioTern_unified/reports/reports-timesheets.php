<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}

$sql = "SELECT s.id, s.first_name, s.last_name, s.student_number, 
        COALESCE(SUM(h.hours), 0) as total_hours, 
        COUNT(h.id) as entries, 
        MAX(h.date) as last_entry
        FROM students s
        LEFT JOIN hour_logs h ON h.student_id = s.id AND h.deleted_at IS NULL
        GROUP BY s.id
        ORDER BY total_hours DESC
        LIMIT 500";

$result = $conn->query($sql);
$timesheet_records = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $timesheet_records[] = $row;
    }
}

$total_hours_sql = "SELECT COALESCE(SUM(hours), 0) as grand_total FROM hour_logs WHERE deleted_at IS NULL";
$total_result = $conn->query($total_hours_sql);
$grand_total_hours = 0;
if ($total_result && $total_row = $total_result->fetch_assoc()) {
    $grand_total_hours = (int)$total_row['grand_total'];
}

$page_title = 'BioTern || Timesheets Report';
include 'includes/header.php';
?>
<style>
.report-page-title { border-right: 0 !important; padding-right: 0 !important; }
.report-hero { border: 1px solid rgba(80, 102, 144, 0.15); background: linear-gradient(135deg, rgba(26, 64, 132, 0.08), rgba(24, 153, 132, 0.08)); border-radius: 14px; padding: 1.1rem 1.25rem; margin-bottom: 1rem; }
.report-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
.report-kpi { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; padding: 0.85rem 1rem; background: #fff; text-align: center; }
.report-kpi-label { font-size: 0.75rem; letter-spacing: 0.04em; text-transform: uppercase; color: #6b7280; margin-bottom: 0.4rem; }
.report-kpi-value { font-size: 1.5rem; font-weight: 700; color: #2563eb; }
.report-filter-wrap { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; padding: 0.9rem; background: #fff; margin-bottom: 1rem; }
.report-table-card { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; overflow: hidden; background: #fff; }
.report-table-card .table { margin-bottom: 0; }
.report-table-card thead th { font-size: 0.75rem; letter-spacing: 0.04em; text-transform: uppercase; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0.75rem; }
.report-pill { border-radius: 999px; padding: 0.4rem 0.75rem; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem; }
html.app-skin-dark .report-hero { border-color: rgba(129, 153, 199, 0.25); background: linear-gradient(135deg, rgba(32, 65, 124, 0.28), rgba(18, 101, 89, 0.28)); }
html.app-skin-dark .report-hero h6 { color: #e5edff; }
html.app-skin-dark .report-kpi, html.app-skin-dark .report-filter-wrap, html.app-skin-dark .report-table-card { background: #0f172a; border-color: rgba(129, 153, 199, 0.24); color: #dce7ff; }
html.app-skin-dark .report-kpi-label { color: #9fb0d3; }
html.app-skin-dark .report-table-card thead th { background: #111f36; color: #9fb0d3; border-bottom-color: rgba(129, 153, 199, 0.25); }
html.app-skin-dark .report-table-card .table { --bs-table-bg: #0f172a; --bs-table-hover-bg: #18243d; --bs-table-border-color: rgba(129, 153, 199, 0.2); }
@media (max-width: 768px) { .report-summary-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
<div class="page-header"><div class="page-header-left d-flex align-items-center"><div class="page-header-title report-page-title"><h5 class="m-b-10">Reports - Timesheets</h5><p class="text-muted mb-0">Monitor student hour submissions and timesheet entries.</p></div></div></div>
<div class="main-content pb-5">
<div class="report-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
<div>
<h6 class="mb-1 fw-bold">Timesheet Summary</h6>
<p class="text-muted mb-0">Aggregate view of all student hour log submissions.</p>
</div>
<span class="report-pill bg-soft-primary text-primary"><i class="feather feather-clock"></i><?php echo $grand_total_hours; ?> Total Hours</span>
</div>

<div class="report-summary-grid">
<div class="report-kpi"><div class="report-kpi-label">Total Hours</div><div class="report-kpi-value"><?php echo $grand_total_hours; ?></div></div>
<div class="report-kpi"><div class="report-kpi-label">Students</div><div class="report-kpi-value"><?php echo count($timesheet_records); ?></div></div>
<div class="report-kpi"><div class="report-kpi-label">Total Entries</div><div class="report-kpi-value"><?php echo array_sum(array_column($timesheet_records, 'entries')); ?></div></div>
</div>

<div class="report-table-card">
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr><th>Student</th><th>ID</th><th>Total Hours</th><th>Entries</th><th>Last Entry</th></tr>
</thead>
<tbody>
<?php if ($timesheet_records): 
foreach ($timesheet_records as $row): 
?>
<tr>
<td><strong><?php echo htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></strong></td>
<td><?php echo htmlspecialchars($row['student_number'] ?? '-'); ?></td>
<td><strong><?php echo number_format((float)$row['total_hours'], 2); ?></strong> hrs</td>
<td><?php echo (int)$row['entries']; ?></td>
<td><?php echo $row['last_entry'] ? date('M d, Y', strtotime($row['last_entry'])) : '-'; ?></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="5" class="text-center py-5 text-muted">No timesheet records found</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
