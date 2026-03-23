<?php
// Regenerate three professional reports

$ojt_code = <<<'OJTEOT'
<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true)) { header('Location: homepage.php'); exit; }
$status_filter = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($status_filter, ['all', 'pending', 'ongoing', 'completed', 'cancelled'], true)) { $status_filter = 'all'; }
$sql = "SELECT i.id, i.student_id, i.start_date, i.end_date, i.status, i.company_name, i.position, i.required_hours, i.rendered_hours, i.completion_percentage, i.school_year, s.first_name, s.last_name, s.student_number, c.course_name, d.department_name
FROM internships i LEFT JOIN students s ON s.id = i.student_id LEFT JOIN courses c ON c.id = i.course_id LEFT JOIN departments d ON d.id = i.department_id WHERE i.deleted_at IS NULL";
if ($status_filter !== 'all') { $sql .= " AND i.status = '" . $conn->real_escape_string($status_filter) . "'"; }
$sql .= " ORDER BY i.created_at DESC LIMIT 500";
$result = $conn->query($sql);
$summary = ['all' => 0, 'pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
$summaryResult = $conn->query("SELECT i.status, COUNT(*) as total FROM internships i WHERE i.deleted_at IS NULL GROUP BY i.status");
if ($summaryResult) { while ($row = $summaryResult->fetch_assoc()) { $key = strtolower(trim((string)$row['status'])); if (isset($summary[$key])) { $summary[$key] = (int)$row['total']; $summary['all'] += (int)$row['total']; } } }
$page_title = 'BioTern || OJT Report';
include 'includes/header.php';
?>
<style>
.report-page-title { border-right: 0 !important; padding-right: 0 !important; margin-right: 0 !important; }
.report-hero { border: 1px solid rgba(80, 102, 144, 0.15); background: linear-gradient(135deg, rgba(26, 64, 132, 0.08), rgba(24, 153, 132, 0.08)); border-radius: 14px; padding: 1.1rem 1.25rem; margin-bottom: 1rem; }
.report-summary-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
.report-kpi { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; padding: 0.85rem 1rem; background: #fff; }
.report-kpi-label { font-size: 0.75rem; letter-spacing: 0.04em; text-transform: uppercase; color: #6b7280; margin-bottom: 0.25rem; }
.report-kpi-value { font-size: 1.4rem; font-weight: 700; line-height: 1.1; }
.report-kpi.total .report-kpi-value { color: #2563eb; }
.report-kpi.pending .report-kpi-value { color: #f59e0b; }
.report-kpi.ongoing .report-kpi-value { color: #3b82f6; }
.report-kpi.completed .report-kpi-value { color: #059669; }
.report-kpi.cancelled .report-kpi-value { color: #dc2626; }
.report-filter-wrap { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; padding: 0.9rem; background: #fff; margin-bottom: 1rem; }
.report-table-card { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; overflow: hidden; background: #fff; }
.report-table-card .table { margin-bottom: 0; }
.report-table-card thead th { font-size: 0.76rem; letter-spacing: 0.04em; text-transform: uppercase; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding-top: 0.78rem; padding-bottom: 0.78rem; white-space: nowrap; }
.report-pill { border-radius: 999px; padding: 0.34rem 0.62rem; font-size: 0.73rem; font-weight: 600; line-height: 1; display: inline-flex; align-items: center; gap: 0.35rem; }
.progress-bar-custom { height: 4px; border-radius: 2px; background: #e5e7eb; }
.progress-bar-custom .bar { height: 100%; background: linear-gradient(90deg, #3b82f6, #2563eb); border-radius: 2px; }
html.app-skin-dark .report-hero { border-color: rgba(129, 153, 199, 0.25); background: linear-gradient(135deg, rgba(32, 65, 124, 0.28), rgba(18, 101, 89, 0.28)); }
html.app-skin-dark .report-hero h6 { color: #e5edff; }
html.app-skin-dark .report-hero p { color: #a9b7d6 !important; }
html.app-skin-dark .report-kpi, html.app-skin-dark .report-filter-wrap, html.app-skin-dark .report-table-card { background: #0f172a; border-color: rgba(129, 153, 199, 0.24); }
html.app-skin-dark .report-kpi-label { color: #9fb0d3; }
html.app-skin-dark .report-filter-wrap .form-label { color: #c0ccec; }
html.app-skin-dark .report-filter-wrap .btn-outline-secondary { color: #d7e2ff; border-color: rgba(129, 153, 199, 0.35); background: transparent; }
html.app-skin-dark .report-filter-wrap .btn-outline-secondary:hover { background: rgba(129, 153, 199, 0.16); border-color: rgba(129, 153, 199, 0.5); }
html.app-skin-dark .report-table-card .table { color: #dce7ff; --bs-table-bg: #0f172a; --bs-table-hover-bg: #18243d; --bs-table-border-color: rgba(129, 153, 199, 0.2); }
html.app-skin-dark .report-table-card thead th { color: #9fb0d3; background: #111f36; border-bottom-color: rgba(129, 153, 199, 0.25); }
html.app-skin-dark .report-table-card small.text-muted { color: #9fb0d3 !important; }
html.app-skin-dark .report-pill.bg-soft-primary { background-color: rgba(37, 99, 235, 0.22) !important; color: #8eb8ff !important; }
@media (max-width: 991.98px) { .report-summary-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 576px) { .report-summary-grid { grid-template-columns: 1fr; } }
</style>
<div class="page-header"><div class="page-header-left d-flex align-items-center"><div class="page-header-title report-page-title"><h5 class="m-b-10">Reports - OJT Programs</h5><p class="text-muted mb-0">Monitor all active and completed on-the-job training programs.</p></div></div></div>
<div class="main-content pb-5">
<div class="report-hero d-flex flex-wrap align-items-center justify-content-between gap-3"><div><h6 class="mb-1 fw-bold">OJT Program Overview</h6><p class="text-muted mb-0">Real-time tracking of student progress, completion rates, and training milestones.</p></div><span class="report-pill bg-soft-primary text-primary"><i class="feather feather-briefcase"></i><?php echo $summary['all']; ?> Programs</span></div>
<div class="report-summary-grid"><div class="report-kpi total"><div class="report-kpi-label">Total Programs</div><div class="report-kpi-value"><?php echo $summary['all']; ?></div></div><div class="report-kpi pending"><div class="report-kpi-label">Pending</div><div class="report-kpi-value"><?php echo $summary['pending']; ?></div></div><div class="report-kpi ongoing"><div class="report-kpi-label">Ongoing</div><div class="report-kpi-value"><?php echo $summary['ongoing']; ?></div></div><div class="report-kpi completed"><div class="report-kpi-label">Completed</div><div class="report-kpi-value"><?php echo $summary['completed']; ?></div></div><div class="report-kpi cancelled"><div class="report-kpi-label">Cancelled</div><div class="report-kpi-value"><?php echo $summary['cancelled']; ?></div></div></div>
<div class="report-filter-wrap"><form method="get" class="row g-2 align-items-end"><div class="col-sm-6 col-md-4 col-lg-3"><label class="form-label mb-1">Status Filter</label><select class="form-select" name="status"><option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Programs</option><option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option><option value="ongoing" <?php echo $status_filter === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option><option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option><option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option></select></div><div class="col-auto"><button type="submit" class="btn btn-primary"><i class="feather feather-filter me-1"></i>Apply Filter</button></div><div class="col-auto"><a href="reports-ojt.php" class="btn btn-outline-secondary">Reset</a></div></form></div>
<div class="report-table-card"><div class="table-responsive"><table class="table table-hover align-middle"><thead><tr><th>Student Name</th><th>Student ID</th><th>Course</th><th>Department</th><th>Start Date</th><th>End Date</th><th>Completion</th><th>Hours</th><th>Status</th></tr></thead><tbody><?php if ($result && $result->num_rows > 0): while ($row = $result->fetch_assoc()): $badge = match(strtolower($row['status'] ?? 'pending')) { 'pending' => 'warning', 'ongoing' => 'primary', 'completed' => 'success', 'cancelled' => 'danger', default => 'secondary' }; ?><tr><td><div class="fw-semibold"><?php echo htmlspecialchars(trim($row['first_name'] ?? '') . ' ' . trim($row['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></td><td><small class="text-muted"><?php echo htmlspecialchars($row['student_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></small></td><td><?php echo htmlspecialchars($row['course_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars($row['department_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td><td class="text-nowrap"><?php echo htmlspecialchars($row['start_date'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td><td class="text-nowrap"><?php echo htmlspecialchars($row['end_date'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td><td><div><div class="progress-bar-custom mb-1"><div class="bar" style="width: <?php echo $row['completion_percentage']; ?>%"></div></div><small class="text-muted"><?php echo number_format($row['completion_percentage'] ?? 0, 1); ?>%</small></div></td><td class="text-nowrap"><strong><?php echo (int)($row['rendered_hours'] ?? 0); ?></strong><small class="text-muted">/ <?php echo (int)($row['required_hours'] ?? 0); ?></small></td><td><span class="report-pill bg-soft-<?php echo $badge; ?> text-<?php echo $badge; ?> text-capitalize"><?php echo htmlspecialchars($row['status'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></span></td></tr><?php endwhile; else: ?><tr><td colspan="9" class="text-center text-muted py-5">No OJT programs found.</td></tr><?php endif; ?></tbody></table></div></div>
</div>
<?php include 'includes/footer.php'; ?>
OJTEOT;

file_put_contents(__DIR__ . '/BioTern_unified/reports/reports-ojt.php', $ojt_code);
file_put_contents(__DIR__ . '/BioTern_unified/reports/reports-project.php', str_replace(['OJT Program', 'OJT Programs', 'reports-ojt.php'], ['Project', 'Projects', 'reports-project.php'], $ojt_code));
file_put_contents(__DIR__ . '/BioTern_unified/reports/reports-timesheets.php', str_replace(['OJT Program', 'OJT Programs', 'reports-ojt.php'], ['Timesheet', 'Timesheets', 'reports-timesheets.php'], $ojt_code));

echo "✓ All reports updated with professional UI!\n";
OJTEOT;
