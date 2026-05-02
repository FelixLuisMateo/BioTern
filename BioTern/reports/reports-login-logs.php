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

function report_login_logs_has_column(mysqli $conn, string $column): bool
{
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM login_logs LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

function report_login_logs_has_table(mysqli $conn, string $table): bool
{
    $safeTable = $conn->real_escape_string($table);
    $result = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    return $result && $result->num_rows > 0;
}

function report_login_logs_table_has_column(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result && $result->num_rows > 0;
}

$requiredLoginLogColumns = [
    'user_id' => "INT NULL",
    'identifier' => "VARCHAR(191) NULL",
    'role' => "VARCHAR(50) NULL",
    'status' => "VARCHAR(20) NOT NULL DEFAULT 'failed'",
    'reason' => "VARCHAR(100) NULL",
    'ip_address' => "VARCHAR(45) NULL",
    'user_agent' => "VARCHAR(255) NULL",
    'created_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP",
];

foreach ($requiredLoginLogColumns as $column => $definition) {
    if (!report_login_logs_has_column($conn, $column)) {
        $safeColumn = str_replace('`', '``', $column);
        $conn->query("ALTER TABLE login_logs ADD COLUMN `{$safeColumn}` {$definition}");
    }
}

$conn->query("CREATE INDEX idx_login_logs_user_id ON login_logs (user_id)");
$conn->query("CREATE INDEX idx_login_logs_status_created ON login_logs (status, created_at)");

$status = strtolower(trim((string)($_GET['status'] ?? 'all')));
if (!in_array($status, ['all', 'success', 'failed'], true)) {
    $status = 'all';
}
$page = (int)($_GET['page'] ?? 1);
if ($page <= 0) {
    $page = 1;
}
$limit = 10;
$offset = ($page - 1) * $limit;

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

function formatDisplayDeviceLocation($rawIp): string
{
    $ip = trim((string)$rawIp);
    if ($ip === '') {
        return '-';
    }

    if ($ip === '::1' || $ip === '127.0.0.1' || strcasecmp($ip, '0:0:0:0:0:0:0:1') === 0 || strcasecmp($ip, '0000:0000:0000:0000:0000:0000:0000:0001') === 0) {
        return 'Local device';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return 'Private network device';
    }

    return 'Public IP location not captured';
}

$sql = "
    SELECT l.id, l.user_id, l.identifier, l.role, l.status, l.reason, l.ip_address, l.created_at,
           u.username, u.email
    FROM login_logs l
    LEFT JOIN users u ON u.id = l.user_id
";
$countSql = "SELECT COUNT(*) AS total_records FROM login_logs l";
if ($status !== 'all') {
    $statusWhere = " WHERE LOWER(TRIM(l.status)) = '" . $conn->real_escape_string($status) . "'";
    $sql .= $statusWhere;
    $countSql .= $statusWhere;
}
$totalRecords = 0;
$countResult = $conn->query($countSql);
if ($countResult instanceof mysqli_result) {
    $countRow = $countResult->fetch_assoc();
    $totalRecords = (int)($countRow['total_records'] ?? 0);
    $countResult->free();
}
$totalPages = max(1, (int)ceil($totalRecords / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}
$sql .= " ORDER BY l.created_at DESC, l.id DESC LIMIT {$limit} OFFSET {$offset}";
$resultRows = $conn->query($sql);
$rows = [];
if ($resultRows instanceof mysqli_result) {
    while ($row = $resultRows->fetch_assoc()) {
        $rows[] = $row;
    }
}
$queryBase = [];
if ($status !== 'all') {
    $queryBase['status'] = $status;
}
$prevUrl = 'reports-login-logs.php?' . http_build_query(array_merge($queryBase, ['page' => max(1, $page - 1)]));
$nextUrl = 'reports-login-logs.php?' . http_build_query(array_merge($queryBase, ['page' => min($totalPages, $page + 1)]));

$summary = [
    'all' => 0,
    'success' => 0,
    'failed' => 0,
];
$summaryResult = $conn->query("SELECT LOWER(TRIM(status)) AS status, COUNT(*) AS total FROM login_logs GROUP BY LOWER(TRIM(status))");
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

$page_body_class = trim(($page_body_class ?? '') . ' reports-page login-logs-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-login-logs-page.css', 'assets/css/modules/reports/reports-shell.css']);
$page_title = 'BioTern || Login Logs';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
<div class="page-header page-header-with-middle">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title logs-page-title">
            <h5 class="m-b-10">Login Logs</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="index.php">Reports</a></li>
            <li class="breadcrumb-item">Login Logs</li>
        </ul>
    </div>
    <div class="page-header-middle">
        <p class="page-header-statement">Real-time visibility into sign-in outcomes, suspicious access, and account activity health.</p>
    </div>
    <?php ob_start(); ?>
        <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
        <a href="reports-chat-logs.php" class="btn btn-outline-primary"><i class="feather-message-circle me-1"></i>Chat Logs</a>
        <button type="button" class="btn btn-light-brand" onclick="window.print();"><i class="feather-printer me-1"></i>Print</button>
    <?php
    biotern_render_page_header_actions([
        'menu_id' => 'reportsLoginLogsActionsMenu',
        'items_html' => ob_get_clean(),
    ]);
    ?>
</div>

<div class="main-content pb-5">
    <div class="logs-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
        <span class="logs-pill bg-soft-primary text-primary">
            <i class="feather feather-clock"></i>
            <?php echo number_format($totalRecords); ?> events found
        </span>
        <span class="logs-pill bg-soft-info text-info">
            Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?>
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
                <form method="get" class="row g-2 align-items-end login-logs-auto-filter">
                    <div class="col-sm-6 col-md-4 col-lg-3">
                        <label class="form-label mb-1">Status Filter</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Success</option>
                            <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                        <input type="hidden" name="page" value="1">
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
                                    <th>Device Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rows)): ?>
                                    <?php foreach ($rows as $row): ?>
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
                                            <td><span class="logs-address" title="<?php echo htmlspecialchars(formatDisplayDeviceLocation($row['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(formatDisplayDeviceLocation($row['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" class="text-center text-muted py-5">No login logs yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                    </table>
                </div>
            </div>

            <div class="logs-pagination d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="text-muted small">
                    Showing page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?>
                    (<?php echo number_format($totalRecords); ?> total records, 10 per page)
                </div>
                <nav aria-label="Login logs pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8'); ?>">Prev</a>
                        </li>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
    </div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.login-logs-auto-filter');
    if (!form) {
        return;
    }

    form.querySelectorAll('select').forEach(function (select) {
        select.addEventListener('change', function () {
            form.requestSubmit();
        });
    });
});
</script>
