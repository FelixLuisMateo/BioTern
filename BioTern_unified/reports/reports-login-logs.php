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

function formatDisplayIpAddress($rawIp)
{
    $ip = trim((string)$rawIp);
    if ($ip === '') {
        return '-';
    }

    // Normalize localhost representations to IPv4 loopback for readability.
    if ($ip === '::1' || strcasecmp($ip, '0:0:0:0:0:0:0:1') === 0 || strcasecmp($ip, '0000:0000:0000:0000:0000:0000:0000:0001') === 0) {
        return '127.0.0.1';
    }

    // Expand compressed IPv6 (for example ::1) so the full address is visible.
    if (strpos($ip, ':') !== false) {
        $binary = @inet_pton($ip);
        if ($binary !== false && strlen($binary) === 16) {
            $parts = unpack('n8', $binary);
            if (is_array($parts) && count($parts) === 8) {
                $expanded = [];
                foreach ($parts as $part) {
                    $expanded[] = sprintf('%04x', (int)$part);
                }
                $expandedIp = implode(':', $expanded);
                if (strcasecmp($expandedIp, '0000:0000:0000:0000:0000:0000:0000:0001') === 0) {
                    return '127.0.0.1';
                }
                return $expandedIp;
            }
        }
    }

    return $ip;
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
<style>
    .logs-page-title {
        border-right: 0 !important;
        padding-right: 0 !important;
        margin-right: 0 !important;
    }

    .logs-hero {
        border: 1px solid rgba(80, 102, 144, 0.15);
        background: linear-gradient(135deg, rgba(26, 64, 132, 0.08), rgba(24, 153, 132, 0.08));
        border-radius: 14px;
        padding: 1.1rem 1.25rem;
        margin-bottom: 1rem;
    }

    .logs-summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .logs-kpi {
        border: 1px solid rgba(80, 102, 144, 0.14);
        border-radius: 12px;
        padding: 0.85rem 1rem;
        background: #fff;
    }

    .logs-kpi-label {
        font-size: 0.75rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 0.25rem;
    }

    .logs-kpi-value {
        font-size: 1.4rem;
        font-weight: 700;
        line-height: 1.1;
    }

    .logs-kpi.total .logs-kpi-value { color: #2563eb; }
    .logs-kpi.success .logs-kpi-value { color: #059669; }
    .logs-kpi.failed .logs-kpi-value { color: #dc2626; }

    .logs-filter-wrap {
        border: 1px solid rgba(80, 102, 144, 0.14);
        border-radius: 12px;
        padding: 0.9rem;
        background: #fff;
        margin-bottom: 1rem;
    }

    .logs-table-card {
        border: 1px solid rgba(80, 102, 144, 0.14);
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
    }

    .logs-table-card .table {
        margin-bottom: 0;
    }

    .logs-table-card thead th {
        font-size: 0.76rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding-top: 0.78rem;
        padding-bottom: 0.78rem;
        white-space: nowrap;
    }

    .logs-identifier {
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: inline-block;
        vertical-align: middle;
    }

    .logs-pill {
        border-radius: 999px;
        padding: 0.34rem 0.62rem;
        font-size: 0.73rem;
        font-weight: 600;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    html.app-skin-dark .logs-hero {
        border-color: rgba(129, 153, 199, 0.25);
        background: linear-gradient(135deg, rgba(32, 65, 124, 0.28), rgba(18, 101, 89, 0.28));
    }

    html.app-skin-dark .logs-hero h6 {
        color: #e5edff;
    }

    html.app-skin-dark .logs-hero p {
        color: #a9b7d6 !important;
    }

    html.app-skin-dark .logs-kpi,
    html.app-skin-dark .logs-filter-wrap,
    html.app-skin-dark .logs-table-card {
        background: #0f172a;
        border-color: rgba(129, 153, 199, 0.24);
    }

    html.app-skin-dark .logs-kpi-label {
        color: #9fb0d3;
    }

    html.app-skin-dark .logs-filter-wrap .form-label {
        color: #c0ccec;
    }

    html.app-skin-dark .logs-filter-wrap .btn-outline-secondary {
        color: #d7e2ff;
        border-color: rgba(129, 153, 199, 0.35);
        background: transparent;
    }

    html.app-skin-dark .logs-filter-wrap .btn-outline-secondary:hover {
        background: rgba(129, 153, 199, 0.16);
        border-color: rgba(129, 153, 199, 0.5);
    }

    html.app-skin-dark .logs-table-card .table {
        color: #dce7ff;
        --bs-table-bg: #0f172a;
        --bs-table-hover-bg: #18243d;
        --bs-table-hover-color: #f3f7ff;
        --bs-table-border-color: rgba(129, 153, 199, 0.2);
    }

    html.app-skin-dark .logs-table-card thead th {
        color: #9fb0d3;
        background: #111f36;
        border-bottom-color: rgba(129, 153, 199, 0.25);
    }

    html.app-skin-dark .logs-table-card small.text-muted,
    html.app-skin-dark .logs-table-card .text-muted {
        color: #9fb0d3 !important;
    }

    html.app-skin-dark .logs-pill.bg-soft-primary {
        background-color: rgba(37, 99, 235, 0.22) !important;
        color: #8eb8ff !important;
    }

    @media (max-width: 991.98px) {
        .logs-summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title logs-page-title">
            <h5 class="m-b-10">Reports - Login Logs</h5>
            <p class="text-muted mb-0">Track successful and failed sign-in attempts.</p>
        </div>
    </div>
</div>

<div class="main-content pb-5">
    <div class="logs-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h6 class="mb-1 fw-bold">Authentication Activity Overview</h6>
            <p class="text-muted mb-0">Real-time visibility into sign-in outcomes, suspicious access, and operational account health.</p>
        </div>
        <span class="logs-pill bg-soft-primary text-primary">
            <i class="feather feather-clock"></i>
            Last 500 events
        </span>
    </div>

            <div class="logs-summary-grid">
                <div class="logs-kpi total">
                    <div class="logs-kpi-label">Total Attempts</div>
                    <div class="logs-kpi-value"><?php echo (int)$summary['all']; ?></div>
                </div>
                <div class="logs-kpi success">
                    <div class="logs-kpi-label">Successful Logins</div>
                    <div class="logs-kpi-value"><?php echo (int)$summary['success']; ?></div>
                </div>
                <div class="logs-kpi failed">
                    <div class="logs-kpi-label">Failed Attempts</div>
                    <div class="logs-kpi-value"><?php echo (int)$summary['failed']; ?></div>
                </div>
            </div>

            <div class="logs-filter-wrap">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <label class="form-label mb-1">Status Filter</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather feather-filter me-1"></i>Apply
                        </button>
                    </div>
                    <div class="col-auto">
                        <a href="reports-login-logs.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <div class="logs-table-card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
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
                                            <td><span class="logs-identifier" title="<?php echo htmlspecialchars((string)($row['identifier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)($row['identifier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><?php echo htmlspecialchars((string)($row['role'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-nowrap"><span class="logs-pill bg-soft-<?php echo $badge; ?> text-<?php echo $badge; ?> text-capitalize"><?php echo htmlspecialchars((string)($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                            <td><?php echo htmlspecialchars((string)($row['reason'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-nowrap"><?php echo htmlspecialchars(formatDisplayIpAddress($row['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted py-5">No login logs yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                    </table>
                </div>
            </div>
    </div>
<?php include 'includes/footer.php'; ?>
