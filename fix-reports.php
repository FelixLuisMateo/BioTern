<?php
// This script will regenerate the three report pages

// OJT Report
$ojt_report = <<<'EOT'
<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}
$status_filter = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($status_filter, ['all', 'pending', 'ongoing', 'completed', 'cancelled'], true)) {
    $status_filter = 'all';
}
$sql = "SELECT i.id, i.student_id, i.start_date, i.end_date, i.status, i.company_name, i.position,
        i.required_hours, i.rendered_hours, i.completion_percentage, i.school_year,
        s.first_name, s.last_name, s.student_number, c.course_name, d.department_name
        FROM internships i
        LEFT JOIN students s ON s.id = i.student_id
        LEFT JOIN courses c ON c.id = i.course_id
        LEFT JOIN departments d ON d.id = i.department_id
        WHERE i.deleted_at IS NULL";
if ($status_filter !== 'all') {
    $sql .= " AND i.status = '" . $conn->real_escape_string($status_filter) . "'";
}
$sql .= " ORDER BY i.created_at DESC LIMIT 500";
$result = $conn->query($sql);
$summary = ['all' => 0, 'pending' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
$summaryResult = $conn->query("SELECT i.status, COUNT(*) as total FROM internships i WHERE i.deleted_at IS NULL GROUP BY i.status");
if ($summaryResult) {
    while ($row = $summaryResult->fetch_assoc()) {
        $key = strtolower(trim((string)$row['status']));
        if (isset($summary[$key])) {
            $summary[$key] = (int)$row['total'];
            $summary['all'] += (int)$row['total'];
        }
    }
}
$page_title = 'BioTern || OJT Report';
include 'includes/header.php';
?>
<div class="page-header"><div class="page-header-left"><h5>Reports - OJT Programs</h5><p class="text-muted">Monitor active and completed OJT programs.</p></div></div>
<div class="main-content pb-5">
<div class="row mb-3">
<div class="col-md-2"><div class="card text-center"><div class="card-body"><div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase">Total</div><div style="font-size:1.6rem;font-weight:700;color:#2563eb"><?php echo $summary['all']; ?></div></div></div></div>
<div class="col-md-2"><div class="card text-center"><div class="card-body"><div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase">Pending</div><div style="font-size:1.6rem;font-weight:700;color:#f59e0b"><?php echo $summary['pending']; ?></div></div></div></div>
<div class="col-md-2"><div class="card text-center"><div class="card-body"><div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase">Ongoing</div><div style="font-size:1.6rem;font-weight:700;color:#3b82f6"><?php echo $summary['ongoing']; ?></div></div></div></div>
<div class="col-md-2"><div class="card text-center"><div class="card-body"><div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase">Completed</div><div style="font-size:1.6rem;font-weight:700;color:#059669"><?php echo $summary['completed']; ?></div></div></div></div>
<div class="col-md-2"><div class="card text-center"><div class="card-body"><div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase">Cancelled</div><div style="font-size:1.6rem;font-weight:700;color:#dc2626"><?php echo $summary['cancelled']; ?></div></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead style="background:#f8fafc"><tr style="font-size:0.7rem;text-transform:uppercase;color:#64748b"><th>Student</th><th>Course</th><th>Department</th><th>Start</th><th>End</th><th>Progress</th><th>Hours</th><th>Status</th></tr></thead><tbody>
<?php if ($result && $result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
<tr><td><div class="fw-semibold"><?php echo htmlspecialchars(trim($row['first_name'] ?? '') . ' ' . trim($row['last_name'] ?? '')); ?></div><small class="text-muted"><?php echo htmlspecialchars($row['student_number'] ?? '-'); ?></small></td><td><?php echo htmlspecialchars($row['course_name'] ?? '-'); ?></td><td><?php echo htmlspecialchars($row['department_name'] ?? '-'); ?></td><td><?php echo htmlspecialchars($row['start_date'] ?? '-'); ?></td><td><?php echo htmlspecialchars($row['end_date'] ?? '-'); ?></td><td><div class="progress" style="height:6px"><div class="progress-bar" style="width:<?php echo $row['completion_percentage']; ?>%"></div></div><small><?php echo number_format($row['completion_percentage'] ?? 0, 1); ?>%</small></td><td><strong><?php echo (int)($row['rendered_hours'] ?? 0); ?></strong> / <?php echo (int)($row['required_hours'] ?? 0); ?></td><td><span class="badge bg-primary"><?php echo htmlspecialchars($row['status'] ?? '-'); ?></span></td></tr>
<?php endwhile; else: ?><tr><td colspan="8" class="text-center text-muted py-4">No programs found.</td></tr><?php endif; ?>
</tbody></table></div></div>
</div>
<?php include 'includes/footer.php'; ?>
EOT;

// Project Report
$project_report = <<<'EOT'
<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}
$sql = "SELECT i.id, i.student_id, i.start_date, i.end_date, i.position, i.company_name,
        i.required_hours, i.rendered_hours, i.completion_percentage, i.status,
        s.first_name, s.last_name, s.student_number, c.course_name, COUNT(DISTINCT h.id) as hour_entries
        FROM internships i
        LEFT JOIN students s ON s.id = i.student_id
        LEFT JOIN courses c ON c.id = i.course_id
        LEFT JOIN hour_logs h ON h.student_id = i.student_id
        WHERE i.deleted_at IS NULL
        GROUP BY i.id
        ORDER BY i.created_at DESC LIMIT 500";
$result = $conn->query($sql);
$total_projects = $conn->query("SELECT COUNT(*) as cnt FROM internships WHERE deleted_at IS NULL")->fetch_assoc()['cnt'] ?? 0;
$completed = $conn->query("SELECT COUNT(*) as cnt FROM internships WHERE deleted_at IS NULL AND status = 'completed'")->fetch_assoc()['cnt'] ?? 0;
$ongoing = $conn->query("SELECT COUNT(*) as cnt FROM internships WHERE deleted_at IS NULL AND status = 'ongoing'")->fetch_assoc()['cnt'] ?? 0;
$page_title = 'BioTern || Project Report';
include 'includes/header.php';
?>
<div class="page-header"><div class="page-header-left"><h5>Reports - Projects</h5><p class="text-muted">Track project assignments and progress.</p></div></div>
<div class="main-content pb-5">
<div class="row mb-3">
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase">Total Projects</div><div style="font-size:1.6rem;font-weight:700;color:#2563eb"><?php echo $total_projects; ?></div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase">Ongoing</div><div style="font-size:1.6rem;font-weight:700;color:#3b82f6"><?php echo $ongoing; ?></div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase">Completed</div><div style="font-size:1.6rem;font-weight:700;color:#059669"><?php echo $completed; ?></div></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead style="background:#f8fafc"><tr style="font-size:0.7rem;text-transform:uppercase;color:#64748b"><th>Student</th><th>Position</th><th>Company</th><th>Start Date</th><th>End Date</th><th>Progress</th><th>Hours Logged</th><th>Status</th></tr></thead><tbody>
<?php if ($result && $result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
<tr><td><div class="fw-semibold"><?php echo htmlspecialchars(trim($row['first_name'] ?? '') . ' ' . trim($row['last_name'] ?? '')); ?></div><small class="text-muted"><?php echo htmlspecialchars($row['student_number'] ?? '-'); ?></small></td><td><?php echo htmlspecialchars($row['position'] ?? '-'); ?></td><td><?php echo htmlspecialchars($row['company_name'] ?? 'Internal'); ?></td><td><?php echo htmlspecialchars($row['start_date'] ?? '-'); ?></td><td><?php echo htmlspecialchars($row['end_date'] ?? '-'); ?></td><td><div class="progress" style="height:6px"><div class="progress-bar" style="width:<?php echo $row['completion_percentage']; ?>%"></div></div><small><?php echo number_format($row['completion_percentage'] ?? 0, 1); ?>%</small></td><td><?php echo (int)($row['hour_entries'] ?? 0); ?> entries</td><td><span class="badge bg-primary"><?php echo htmlspecialchars($row['status'] ?? '-'); ?></span></td></tr>
<?php endwhile; else: ?><tr><td colspan="8" class="text-center text-muted py-4">No projects found.</td></tr><?php endif; ?>
</tbody></table></div></div>
</div>
<?php include 'includes/footer.php'; ?>
EOT;

// Timesheets Report
$timesheets_report = <<<'EOT'
<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? date('Y-m-d');
$sql = "SELECT s.id, s.first_name, s.last_name, s.student_number,
        SUM(h.hours) as total_hours, COUNT(h.id) as entries, MAX(h.date) as last_entry
        FROM hour_logs h
        RIGHT JOIN students s ON s.id = h.student_id
        WHERE h.date IS NULL OR (h.date BETWEEN '" . $conn->real_escape_string($date_from) . "' AND '" . $conn->real_escape_string($date_to) . "')
        GROUP BY s.id
        ORDER BY total_hours DESC LIMIT 500";
$result = $conn->query($sql);
$total_hours = $conn->query("SELECT SUM(hours) as total FROM hour_logs WHERE date BETWEEN '" . $conn->real_escape_string($date_from) . "' AND '" . $conn->real_escape_string($date_to) . "'")->fetch_assoc()['total'] ?? 0;
$students_logged = $conn->query("SELECT COUNT(DISTINCT student_id) as cnt FROM hour_logs WHERE date BETWEEN '" . $conn->real_escape_string($date_from) . "' AND '" . $conn->real_escape_string($date_to) . "'")->fetch_assoc()['cnt'] ?? 0;
$page_title = 'BioTern || Timesheets Report';
include 'includes/header.php';
?>
<div class="page-header"><div class="page-header-left"><h5>Reports - Timesheets</h5><p class="text-muted">Monitor student hours and timesheet submissions.</p></div></div>
<div class="main-content pb-5">
<div class="row mb-3">
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase">Total Hours</div><div style="font-size:1.6rem;font-weight:700;color:#2563eb"><?php echo number_format($total_hours, 2); ?></div></div></div></div>
<div class="col-md-3"><div class="card text-center"><div class="card-body"><div style="font-size:0.7rem;color:#6b7280;text-transform:uppercase">Students With Entries</div><div style="font-size:1.6rem;font-weight:700;color:#3b82f6"><?php echo $students_logged; ?></div></div></div></div>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead style="background:#f8fafc"><tr style="font-size:0.7rem;text-transform:uppercase;color:#64748b"><th>Student</th><th>Total Hours</th><th>Entries</th><th>Last Entry</th></tr></thead><tbody>
<?php if ($result && $result->num_rows > 0): while ($row = $result->fetch_assoc()): ?>
<tr><td><div class="fw-semibold"><?php echo htmlspecialchars(trim($row['first_name'] ?? '') . ' ' . trim($row['last_name'] ?? '')); ?></div><small class="text-muted"><?php echo htmlspecialchars($row['student_number'] ?? '-'); ?></small></td><td><strong><?php echo number_format($row['total_hours'] ?? 0, 2); ?></strong> hrs</td><td><?php echo (int)($row['entries'] ?? 0); ?></td><td><?php echo htmlspecialchars($row['last_entry'] ?? '-'); ?></td></tr>
<?php endwhile; else: ?><tr><td colspan="4" class="text-center text-muted py-4">No timesheet entries found.</td></tr><?php endif; ?>
</tbody></table></div></div>
</div>
<?php include 'includes/footer.php'; ?>
EOT;

file_put_contents(__DIR__ . '/BioTern_unified/reports/reports-ojt.php', $ojt_report);
file_put_contents(__DIR__ . '/BioTern_unified/reports/reports-project.php', $project_report);
file_put_contents(__DIR__ . '/BioTern_unified/reports/reports-timesheets.php', $timesheets_report);

echo "All three report files have been regenerated successfully!\n";
echo "✓ reports-ojt.php\n";
echo "✓ reports-project.php\n";
echo "✓ reports-timesheets.php\n";
