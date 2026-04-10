<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

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

$page_body_class = trim(($page_body_class ?? '') . ' reports-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-timesheets-page.css', 'assets/css/modules/reports/reports-shell.css']);
$page_title = 'BioTern || Timesheets Report';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
<div class="page-header page-header-with-middle">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title report-page-title">
            <h5 class="m-b-10">Timesheets</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="reports-ojt.php">Reports</a></li>
            <li class="breadcrumb-item">Timesheets</li>
        </ul>
    </div>
    <div class="page-header-middle">
        <p class="page-header-statement">Aggregate view of student hour log submissions and attendance workload trends.</p>
    </div>
    <div class="page-header-right ms-auto">
        <div class="d-md-none d-flex align-items-center">
            <button type="button" class="btn btn-light-brand page-header-actions-toggle" data-bs-toggle="collapse" data-bs-target="#reportsTimesheetsActionsCollapse" aria-expanded="false" aria-controls="reportsTimesheetsActionsCollapse">
                <i class="feather-more-horizontal"></i>
            </button>
        </div>
        <div class="page-header-right-items collapse d-md-flex" id="reportsTimesheetsActionsCollapse">
            <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
                <a href="reports-ojt.php" class="btn btn-outline-primary"><i class="feather-briefcase me-1"></i>OJT Report</a>
                <button type="button" class="btn btn-light-brand" onclick="window.print();"><i class="feather-printer me-1"></i>Print</button>
            </div>
        </div>
    </div>
</div>
<div class="main-content pb-5">
<div class="report-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
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
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>

