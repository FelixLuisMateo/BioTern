<?php
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_roles_page(['admin', 'coordinator', 'supervisor']);

$conn = new mysqli('localhost', 'root', '', 'biotern_db');
if ($conn->connect_error) {
    die("DB connection failed");
}

if (!table_exists($conn, 'attendance_correction_requests')) {
    die('Run db_updates_operations.sql first.');
}

$query = "
    SELECT r.*, a.attendance_date, s.first_name, s.last_name
    FROM attendance_correction_requests r
    LEFT JOIN attendances a ON a.id = r.attendance_id
    LEFT JOIN students s ON s.id = a.student_id
    ORDER BY r.created_at DESC
    LIMIT 100
";
$res = $conn->query($query);
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

$page_title = 'BioTern || Attendance Corrections';
$page_styles = [
    'assets/css/pages-attendance-page.css',
];
$page_scripts = [
    'assets/js/theme-customizer-init.min.js',
];

include 'includes/header.php';
?>
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Attendance Corrections</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item">Attendance Corrections</li>
                    </ul>
                </div>
            </div>

            <div class="main-content">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Attendance Correction Requests</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Student</th>
                                        <th>Date</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Requested</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr><td colspan="6" class="text-center">No correction requests.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $row): ?>
                                            <tr>
                                                <td><?php echo (int)$row['id']; ?></td>
                                                <td><?php echo htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))); ?></td>
                                                <td><?php echo htmlspecialchars($row['attendance_date'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($row['correction_reason']); ?></td>
                                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

<?php include 'includes/footer.php'; ?>

