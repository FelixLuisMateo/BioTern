<?php
require_once __DIR__ . '/lib/ops_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_roles_page(['admin', 'coordinator', 'supervisor']);

$conn = new mysqli('localhost', 'root', '', 'biotern_db');
if ($conn->connect_error) {
    die("DB connection failed");
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Operations Report</title>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <h3 class="mb-3">Attendance Operations Report</h3>
        <div class="row mb-4">
            <div class="col-md-4"><div class="card"><div class="card-body"><strong>Pending Corrections:</strong> <?php echo $pendingCorrections; ?></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body"><strong>Pending Queue Events:</strong> <?php echo $pendingQueue; ?></div></div></div>
            <div class="col-md-4"><div class="card"><div class="card-body"><strong>Failed Queue Events:</strong> <?php echo $failedQueue; ?></div></div></div>
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
    </div>
</body>
</html>

