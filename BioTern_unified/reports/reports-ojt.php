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

$status_filter = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($status_filter, ['all', 'pending', 'ongoing', 'completed', 'cancelled'], true)) {
    $status_filter = 'all';
}

$where_clause = "WHERE i.deleted_at IS NULL";
if ($status_filter !== 'all') {
    $where_clause .= " AND i.status = " . $conn->quote($status_filter);
}

$sql = "SELECT i.id, i.student_id, i.start_date, i.end_date, i.status, 
        i.company_name, i.position, i.required_hours, i.rendered_hours, 
        i.completion_percentage, s.first_name, s.last_name, s.student_number, 
        c.course_name, d.department_name
        FROM internships i
        LEFT JOIN students s ON s.id = i.student_id
        LEFT JOIN courses c ON c.id = i.course_id
        LEFT JOIN departments d ON d.id = i.department_id
        " . $where_clause . "
        ORDER BY i.created_at DESC LIMIT 500";

$result = $conn->query($sql);
$ojt_records = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $ojt_records[] = $row;
    }
}

$summary_sql = "SELECT i.status, COUNT(*) as total 
                FROM internships i
                WHERE i.deleted_at IS NULL
                GROUP BY i.status";
$summary_result = $conn->query($summary_sql);
$status_summary = ['pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
if ($summary_result && $summary_result->num_rows > 0) {
    while ($row = $summary_result->fetch_assoc()) {
        $status_summary[$row['status']] = (int)$row['total'];
    }
}

$total_ojt = array_sum($status_summary);

$page_title = 'BioTern || OJT Report';
include 'includes/header.php';
?>
<style>
.report-page-title { border-right: 0 !important; padding-right: 0 !important; }
.report-hero { border: 1px solid rgba(80, 102, 144, 0.15); background: linear-gradient(135deg, rgba(26, 64, 132, 0.08), rgba(24, 153, 132, 0.08)); border-radius: 14px; padding: 1.1rem 1.25rem; margin-bottom: 1rem; }
.report-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
.report-kpi { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; padding: 0.85rem 1rem; background: #fff; text-align: center; }
.report-kpi-label { font-size: 0.75rem; letter-spacing: 0.04em; text-transform: uppercase; color: #6b7280; margin-bottom: 0.4rem; }
.report-kpi-value { font-size: 1.5rem; font-weight: 700; }
.report-kpi.total .report-kpi-value { color: #2563eb; }
.report-kpi.pending .report-kpi-value { color: #f59e0b; }
.report-kpi.ongoing .report-kpi-value { color: #3b82f6; }
.report-kpi.completed .report-kpi-value { color: #059669; }
.report-kpi.cancelled .report-kpi-value { color: #dc2626; }
.report-filter-wrap { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; padding: 0.9rem; background: #fff; margin-bottom: 1rem; }
.report-table-card { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; overflow: hidden; background: #fff; }
.report-table-card .table { margin-bottom: 0; }
.report-table-card thead th { font-size: 0.75rem; letter-spacing: 0.04em; text-transform: uppercase; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0.75rem; }
.report-pill { border-radius: 999px; padding: 0.4rem 0.75rem; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem; }
.progress-bar-custom { height: 4px; background: #e5e7eb; border-radius: 2px; overflow: hidden; }
.progress-bar-custom .bar { height: 100%; background: linear-gradient(90deg, #3b82f6, #2563eb); }
html.app-skin-dark .report-hero { border-color: rgba(129, 153, 199, 0.25); background: linear-gradient(135deg, rgba(32, 65, 124, 0.28), rgba(18, 101, 89, 0.28)); }
html.app-skin-dark .report-hero h6 { color: #e5edff; }
html.app-skin-dark .report-kpi, html.app-skin-dark .report-filter-wrap, html.app-skin-dark .report-table-card { background: #0f172a; border-color: rgba(129, 153, 199, 0.24); color: #dce7ff; }
html.app-skin-dark .report-kpi-label { color: #9fb0d3; }
html.app-skin-dark .report-table-card thead th { background: #111f36; color: #9fb0d3; border-bottom-color: rgba(129, 153, 199, 0.25); }
html.app-skin-dark .report-table-card .table { --bs-table-bg: #0f172a; --bs-table-hover-bg: #18243d; --bs-table-border-color: rgba(129, 153, 199, 0.2); }
@media (max-width: 768px) { .report-summary-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
<div class="page-header"><div class="page-header-left d-flex align-items-center"><div class="page-header-title report-page-title"><h5 class="m-b-10">Reports - OJT Programs</h5><p class="text-muted mb-0">Monitor all active and completed on-the-job training programs.</p></div></div></div>
<div class="main-content pb-5">
<div class="report-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
<div>
<h6 class="mb-1 fw-bold">OJT Program Overview</h6>
<p class="text-muted mb-0">Real-time tracking of student progress, completion rates, and training milestones.</p>
</div>
<span class="report-pill bg-soft-primary text-primary"><i class="feather feather-briefcase"></i><?php echo $total_ojt; ?> Programs</span>
</div>

<div class="report-summary-grid">
<div class="report-kpi total"><div class="report-kpi-label">Total</div><div class="report-kpi-value"><?php echo $total_ojt; ?></div></div>
<div class="report-kpi pending"><div class="report-kpi-label">Pending</div><div class="report-kpi-value"><?php echo $status_summary['pending']; ?></div></div>
<div class="report-kpi ongoing"><div class="report-kpi-label">Ongoing</div><div class="report-kpi-value"><?php echo $status_summary['ongoing']; ?></div></div>
<div class="report-kpi completed"><div class="report-kpi-label">Completed</div><div class="report-kpi-value"><?php echo $status_summary['completed']; ?></div></div>
<div class="report-kpi cancelled"><div class="report-kpi-label">Cancelled</div><div class="report-kpi-value"><?php echo $status_summary['cancelled']; ?></div></div>
</div>

<div class="report-filter-wrap">
<form method="get" class="row g-2 align-items-end">
<div class="col-sm-6 col-md-4">
<label class="form-label mb-1">Filter by Status</label>
<select class="form-select" name="status">
<option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Programs</option>
<option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
<option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
<option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
<option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
</select>
</div>
<div class="col-auto">
<button type="submit" class="btn btn-primary"><i class="feather feather-filter me-1"></i>Filter</button>
</div>
<div class="col-auto">
<a href="reports-ojt.php" class="btn btn-outline-secondary">Reset</a>
</div>
</form>
</div>

<div class="report-table-card">
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr><th>Student</th><th>Course</th><th>Dept</th><th>Start</th><th>End</th><th>Progress</th><th>Hours</th><th>Status</th></tr>
</thead>
<tbody>
<?php if ($result && $result->num_rows > 0): 
$result->data_seek(0);
while ($row = $result->fetch_assoc()): 
$badge_class = match(strtolower($row['status'] ?? 'pending')) { 
    'pending'=>'warning', 'ongoing'=>'primary', 'completed'=>'success', 'cancelled'=>'danger', default=>'secondary' 
}; 
?>
<tr>
<td><strong><?php echo htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($row['student_number'] ?? ''); ?></small></td>
<td><?php echo htmlspecialchars($row['course_name'] ?? '-'); ?></td>
<td><?php echo htmlspecialchars($row['department_name'] ?? '-'); ?></td>
<td><?php echo htmlspecialchars($row['start_date'] ?? '-'); ?></td>
<td><?php echo htmlspecialchars($row['end_date'] ?? '-'); ?></td>
<td><div class="progress-bar-custom mb-1"><div class="bar" style="width:<?php echo $row['completion_percentage']; ?>%"></div></div><small class="text-muted"><?php echo number_format($row['completion_percentage'] ?? 0, 1); ?>%</small></td>
<td><strong><?php echo $row['rendered_hours']; ?></strong> / <?php echo $row['required_hours']; ?></td>
<td><span class="report-pill bg-soft-<?php echo $badge_class; ?> text-<?php echo $badge_class; ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
</tr>
<?php endwhile; else: ?>
<tr><td colspan="8" class="text-center py-5 text-muted">No programs found</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
