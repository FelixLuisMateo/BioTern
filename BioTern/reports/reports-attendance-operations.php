<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
require_roles_page(['admin', 'coordinator', 'supervisor']);

$rows = [];
if (table_exists($conn, 'attendance_operational_report')) {
    $res = $conn->query("SELECT * FROM attendance_operational_report ORDER BY attendance_date DESC LIMIT 30");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
}

$pendingCorrections = 0;
if (table_exists($conn, 'attendance_correction_requests')) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM attendance_correction_requests WHERE status = 'pending'");
    if ($r) {
        $pendingCorrections = (int)$r->fetch_assoc()['c'];
    }
}

$pendingQueue = 0;
$failedQueue = 0;
if (table_exists($conn, 'biometric_event_queue')) {
    $r = $conn->query("SELECT SUM(status='pending') AS p, SUM(status='failed') AS f FROM biometric_event_queue");
    if ($r) {
        $x = $r->fetch_assoc();
        $pendingQueue = (int)($x['p'] ?? 0);
        $failedQueue = (int)($x['f'] ?? 0);
    }
}

$page_body_class = trim(($page_body_class ?? '') . ' reports-page reports-attendance-operations-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-attendance-operations-page.css', 'assets/css/modules/reports/reports-shell.css']);
$page_title = 'BioTern || Attendance Operations Report';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
    <div class="page-header page-header-with-middle">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Attendance Operations</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="reports-ojt.php">Reports</a></li>
                <li class="breadcrumb-item">Attendance Operations</li>
            </ul>
        </div>
        <div class="page-header-middle">
            <p class="page-header-statement">Operational visibility for attendance corrections and biometric queue health.</p>
        </div>
        <div class="page-header-right ms-auto">
            <div class="d-md-none d-flex align-items-center">
                <button type="button" class="btn btn-light-brand page-header-actions-toggle" data-bs-toggle="collapse" data-bs-target="#reportsAttendanceOpsActionsCollapse" aria-expanded="false" aria-controls="reportsAttendanceOpsActionsCollapse">
                    <i class="feather-more-horizontal"></i>
                </button>
            </div>
            <div class="page-header-right-items collapse d-md-flex" id="reportsAttendanceOpsActionsCollapse">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
                    <a href="attendance-corrections.php" class="btn btn-outline-primary"><i class="feather-edit-2 me-1"></i>Corrections</a>
                    <button type="button" class="btn btn-light-brand" onclick="window.print();"><i class="feather-printer me-1"></i>Print</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <strong>Pending Corrections:</strong> <?php echo $pendingCorrections; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <strong>Pending Queue Events:</strong> <?php echo $pendingQueue; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <strong>Failed Queue Events:</strong> <?php echo $failedQueue; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Daily Operational Summary (Last 30 days)</div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Rejected</th>
                        <th>Zero Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="6" class="text-center">No data. Run `db_updates_operations.sql` if needed.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['attendance_date']); ?></td>
                                <td><?php echo (int)$row['total_records']; ?></td>
                                <td><?php echo (int)$row['approved_records']; ?></td>
                                <td><?php echo (int)$row['pending_records']; ?></td>
                                <td><?php echo (int)$row['rejected_records']; ?></td>
                                <td><?php echo (int)$row['zero_hour_records']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>

