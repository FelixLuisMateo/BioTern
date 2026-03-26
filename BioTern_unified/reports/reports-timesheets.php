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

$dateFrom = trim((string)($_GET['from'] ?? date('Y-m-d', strtotime('-30 days'))));
$dateTo = trim((string)($_GET['to'] ?? date('Y-m-d')));
if (strtotime($dateFrom) === false) $dateFrom = date('Y-m-d', strtotime('-30 days'));
if (strtotime($dateTo) === false) $dateTo = date('Y-m-d');
if ($dateFrom > $dateTo) { list($dateFrom, $dateTo) = array($dateTo, $dateFrom); }

$sql = "SELECT s.id, s.first_name, s.last_name, 
        COALESCE(SUM(h.hours), 0) as total_hours, 
        COUNT(h.id) as entries, 
        MAX(h.date) as last_entry,
        COALESCE(d.name, 'No Department') as department_name, 
        COALESCE(d.id, 0) as dept_id
        FROM students s
        LEFT JOIN departments d ON d.id = s.department_id
        LEFT JOIN hour_logs h ON h.student_id = s.id AND h.deleted_at IS NULL AND h.date >= ? AND h.date <= ?
        GROUP BY s.id, d.id
        ORDER BY d.name, total_hours DESC
        LIMIT 500";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param('ss', $dateFrom, $dateTo);
$stmt->execute();
$result = $stmt->get_result();
$timesheet_records = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $timesheet_records[] = $row;
    }
}
$stmt->close();

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
.page-header h5 { border-right: none !important; margin-right: 0 !important; padding-right: 0 !important; }
.page-header .page-header-left { border-right: none !important; border-left: none !important; }
.page-header .page-header-title { border-right: none !important; border-left: none !important; }
.breadcrumb-wrapper { margin: 0 0 12px 0; }
.breadcrumb { padding: 0; margin-bottom: 0; background: transparent; font-size: 13px; }
.breadcrumb-item { color: #64748b; }
.breadcrumb-item a { color: #64748b; text-decoration: none; transition: color 0.3s ease; }
.breadcrumb-item a:hover { color: #3454d1; }
.breadcrumb-item.active { color: #64748b; font-weight: 500; }
.breadcrumb-item + .breadcrumb-item::before { color: #94a3b8; }
.report-hero { border: 1px solid rgba(80, 102, 144, 0.15); background: linear-gradient(135deg, rgba(26, 64, 132, 0.08), rgba(24, 153, 132, 0.08)); border-radius: 14px; padding: 1.1rem 1.25rem; margin-bottom: 1rem; }
.report-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.75rem; margin-bottom: 1rem; }
.report-kpi { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; padding: 0.85rem 1rem; background: #fff; text-align: center; }
.report-kpi-label { font-size: 0.75rem; letter-spacing: 0.04em; text-transform: uppercase; color: #6b7280; margin-bottom: 0.4rem; }
.report-kpi-value { font-size: 1.5rem; font-weight: 700; color: #2563eb; }
.report-filter-wrap { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; padding: 0.9rem; background: #fff; margin-bottom: 1rem; }
.timesheet-by-dept { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; margin-bottom: 1rem; overflow: hidden; background: #fff; }
.report-table-card { border: 1px solid rgba(80, 102, 144, 0.14); border-radius: 12px; overflow: hidden; background: #fff; }
html.app-skin-dark .timesheet-by-dept { background: #0f172a; border-color: rgba(129, 153, 199, 0.24); }
.timesheet-dept-header { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; padding: 0.75rem 1rem; font-weight: 600; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
html.app-skin-dark .timesheet-dept-header { background: linear-gradient(135deg, #1e40af, #1e3a8a); }
.timesheet-dept-toggle { background: transparent; border: none; color: white; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; }
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
<div class="page-header"><nav class="breadcrumb-wrapper"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="index.php">Reports</a></li><li class="breadcrumb-item active">Timesheets</li></ol></nav></div>
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

<div class="report-filter-wrap">
<form method="get" class="row g-2 align-items-end">
<div class="col-md-3">
<label class="form-label mb-1">From Date</label>
<input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($dateFrom); ?>">
</div>
<div class="col-md-3">
<label class="form-label mb-1">To Date</label>
<input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($dateTo); ?>">
</div>
<div class="col-auto">
<button type="submit" class="btn btn-primary"><i class="feather feather-filter me-1"></i>Apply</button>
<a href="reports-timesheets.php" class="btn btn-outline-secondary">Reset</a>
</div>
</form>
</div>

<div class="report-table-card">
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr><th>Student</th><th>ID</th><th>Total Hours</th><th>Entries</th><th>Last Entry</th></tr>
</thead>
<tbody>
<?php if ($timesheet_records):
$byDept = [];
foreach ($timesheet_records as $r) {
    $deptName = trim((string)($r['department_name'] ?? 'No Department'));
    if (!isset($byDept[$deptName])) $byDept[$deptName] = [];
    $byDept[$deptName][] = $r;
}

foreach ($byDept as $deptName => $students):
    $deptId = 'ts-dept-' . substr(md5($deptName), 0, 8);
    $studentCount = count($students);
    $previewCount = 5;
    $hiddenCount = max(0, $studentCount - $previewCount);
?>
    <tr>
        <td colspan="5" class="p-0">
            <div class="timesheet-by-dept" id="<?php echo htmlspecialchars($deptId); ?>">
                <div class="timesheet-dept-header" onclick="document.getElementById('tsToggle-<?php echo htmlspecialchars($deptId); ?>').click()">
                    <span><i class="feather feather-folder me-2"></i><?php echo htmlspecialchars($deptName); ?> (<?php echo $studentCount; ?>)</span>
                    <button type="button" class="timesheet-dept-toggle" id="tsToggle-<?php echo htmlspecialchars($deptId); ?>" data-state="expanded" onclick="event.stopPropagation();">
                        <i class="feather-chevron-up"></i>
                    </button>
                </div>
                <div class="timesheet-dept-content">
                    <?php foreach ($students as $idx => $row):
                        $isHidden = ($idx >= $previewCount);
                    ?>
                    <div class="timesheet-student-row<?php echo $isHidden ? ' ts-extra d-none' : ''; ?>" style="padding: 0.65rem 1rem; border-bottom: 1px solid rgba(80, 102, 144, 0.14); display: flex; justify-content: space-between;">
                        <div style="flex: 1;">
                            <strong><?php echo htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')); ?></strong>
                        </div>
                        <div style="text-align: right;">
                            <strong><?php echo number_format((float)$row['total_hours'], 2); ?> hrs</strong><br>
                            <small class="text-muted"><?php echo (int)$row['entries']; ?> entries</small>
                        </div>
                        <div style="text-align: right; margin-left: 1.5rem;">
                            <?php $lastE = trim((string)($row['last_entry'] ?? '')); echo $lastE && $lastE !== '0000-00-00' ? date('M d, Y', strtotime($lastE)) : '-'; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($hiddenCount > 0): ?>
                    <div style="padding: 0.65rem 1rem; text-align: center; font-size: 0.85rem;">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-target="<?php echo htmlspecialchars($deptId); ?>" onclick="toggleDeptRows(this)">
                            <i class="feather-chevron-down me-1"></i>Show <?php echo $hiddenCount; ?> more
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </td>
    </tr>
<?php endforeach;
 else:
?>
<tr><td colspan="5" class="text-center py-5 text-muted">No timesheet records found</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<script>
function toggleDeptRows(btn) {
    var deptId = btn.getAttribute('data-target');
    var container = document.getElementById(deptId);
    if (!container) return;
    var extraRows = container.querySelectorAll('.ts-extra');
    extraRows.forEach(function(r) { r.classList.toggle('d-none'); });
    var icon = btn.querySelector('i');
    var text = btn.querySelector('span') || btn;
    if (btn.classList.contains('showing-all')) {
        btn.classList.remove('showing-all');
        if (icon) icon.className = 'feather-chevron-down me-1';
        btn.innerHTML = '<i class="feather-chevron-down me-1"></i>Show more';
    } else {
        btn.classList.add('showing-all');
        if (icon) icon.className = 'feather-chevron-up me-1';
        btn.innerHTML = '<i class="feather-chevron-up me-1"></i>Show less';
    }
}
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.timesheet-dept-toggle');
    if (btn) {
        var deptId = btn.id.replace('tsToggle-', '');
        var container = document.getElementById(deptId);
        if (!container) return;
        var isExpanded = btn.getAttribute('data-state') === 'expanded';
        var content = container.querySelector('.timesheet-dept-content');
        if (content) content.style.display = isExpanded ? 'none' : 'block';
        btn.setAttribute('data-state', isExpanded ? 'collapsed' : 'expanded');
        var icon = btn.querySelector('i');
        if (icon) icon.className = isExpanded ? 'feather-chevron-down' : 'feather-chevron-up';
    }
});
</script>
</div>
<?php include 'includes/footer.php'; ?>
