<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
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

$page_body_class = trim(($page_body_class ?? '') . ' reports-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-ojt-page.css', 'assets/css/modules/reports/reports-shell.css']);
$page_scripts = array_merge($page_scripts ?? [], ['assets/js/modules/reports/reports-progress-bars.js']);
$page_title = 'BioTern || OJT Report';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
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
<td><div class="progress-bar-custom mb-1"><div class="bar report-progress-bar" data-progress="<?php echo (float)($row['completion_percentage'] ?? 0); ?>"></div></div><small class="text-muted"><?php echo number_format($row['completion_percentage'] ?? 0, 1); ?>%</small></td>
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
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>
