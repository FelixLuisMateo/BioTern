<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_roles_page(['admin', 'coordinator', 'supervisor']);

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    http_response_code(500);
    die('Database connection is not available.');
}
/** @var mysqli $db */
$db = $conn;

if (!table_exists($db, 'attendance_correction_requests')) {
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
$res = $db->query($query);
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}

$page_title = 'BioTern || Attendance Corrections';
$page_styles = [
    'assets/css/modules/pages/page-attendance.css',
];
$page_scripts = [
    'assets/js/theme-customizer-init.min.js',
];

include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Attendance Corrections</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item">Attendance Corrections</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="d-flex d-md-none align-items-center">
                        <button type="button" class="btn btn-light-brand page-header-actions-toggle" data-bs-toggle="collapse" data-bs-target="#attendanceCorrectionsActionsCollapse" aria-expanded="false" aria-controls="attendanceCorrectionsActionsCollapse">
                            <i class="feather-align-right me-2"></i>
                            <span>Actions</span>
                        </button>
                    </div>
                    <div class="page-header-right-items collapse d-md-flex" id="attendanceCorrectionsActionsCollapse">
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            <a href="attendance.php" class="btn btn-light-brand">
                                <i class="feather-calendar me-1"></i>
                                <span>Attendance DTR</span>
                            </a>
                            <a href="homepage.php" class="btn btn-outline-secondary">
                                <i class="feather-home me-1"></i>
                                <span>Dashboard</span>
                            </a>
                            <button type="button" class="btn btn-light" data-action="print-page">
                                <i class="feather-printer me-1"></i>
                                <span>Print</span>
                            </button>
                        </div>
                    </div>
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

</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>



