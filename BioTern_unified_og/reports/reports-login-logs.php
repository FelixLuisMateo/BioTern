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

$conn->query("CREATE TABLE IF NOT EXISTS login_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    identifier VARCHAR(191) NULL,
    role VARCHAR(50) NULL,
    status VARCHAR(20) NOT NULL,
    reason VARCHAR(100) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_logs_user_id (user_id),
    INDEX idx_login_logs_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$status = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($status, ['all', 'success', 'failed'], true)) {
    $status = 'all';
}

function formatDisplayDateTime($rawValue)
{
    $value = trim((string)$rawValue);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '-';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('M d, Y h:i A', $timestamp);
}

$sql = "
    SELECT l.id, l.identifier, l.role, l.status, l.reason, l.ip_address, l.created_at,
           u.username, u.email
    FROM login_logs l
    LEFT JOIN users u ON u.id = l.user_id
";
if ($status !== 'all') {
    $sql .= " WHERE l.status = '" . $conn->real_escape_string($status) . "'";
}
$sql .= " ORDER BY l.created_at DESC, l.id DESC LIMIT 500";
$rows = $conn->query($sql);

$summary = [
    'all' => 0,
    'success' => 0,
    'failed' => 0,
];
$summaryResult = $conn->query("SELECT status, COUNT(*) AS total FROM login_logs GROUP BY status");
if ($summaryResult) {
    while ($sumRow = $summaryResult->fetch_assoc()) {
        $key = strtolower(trim((string)($sumRow['status'] ?? '')));
        $count = (int)($sumRow['total'] ?? 0);
        if (isset($summary[$key])) {
            $summary[$key] = $count;
            $summary['all'] += $count;
        }
    }
}

$page_title = 'BioTern || Login Logs';
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Reports - Login Logs</h5>
                    <p class="text-muted mb-0">Track successful and failed sign-in attempts.</p>
                </div>
            </div>
        </div>

        <div class="main-content pb-5">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-soft-primary text-primary">Total: <?php echo (int)$summary['all']; ?></span>
                        <span class="badge bg-soft-success text-success">Success: <?php echo (int)$summary['success']; ?></span>
                        <span class="badge bg-soft-danger text-danger">Failed: <?php echo (int)$summary['failed']; ?></span>
                    </div>

                    <form method="get" class="row g-2 align-items-end mb-4">
                        <div class="col-auto">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                                <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-nowrap">Time</th>
                                    <th>User</th>
                                    <th>Identifier</th>
                                    <th>Role</th>
                                    <th class="text-nowrap">Status</th>
                                    <th>Reason</th>
                                    <th class="text-nowrap">IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($rows && $rows->num_rows > 0): ?>
                                    <?php while ($row = $rows->fetch_assoc()): ?>
                                        <?php $badge = strtolower((string)$row['status']) === 'success' ? 'success' : 'danger'; ?>
                                        <tr>
                                            <td class="text-nowrap"><?php echo htmlspecialchars(formatDisplayDateTime($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars((string)($row['username'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars((string)($row['email'] ?? 'No email'), ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars((string)($row['identifier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($row['role'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-nowrap"><span class="badge bg-soft-<?php echo $badge; ?> text-<?php echo $badge; ?> text-capitalize"><?php echo htmlspecialchars((string)($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><?php echo htmlspecialchars((string)($row['reason'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-nowrap"><?php echo htmlspecialchars((string)($row['ip_address'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No login logs yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php include 'includes/footer.php'; ?>
