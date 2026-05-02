<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
require_once dirname(__DIR__) . '/includes/admin-activity-log.php';

$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($currentRole !== 'admin') {
    header('Location: homepage.php');
    exit;
}

biotern_admin_activity_table_ready($conn);

function adminLogsFormatDateTime($rawValue): string
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

function adminLogsDisplayIp($rawIp): string
{
    $ip = trim((string)$rawIp);
    if ($ip === '') {
        return '-';
    }

    if ($ip === '::1' || strcasecmp($ip, '0:0:0:0:0:0:0:1') === 0) {
        return '127.0.0.1';
    }

    return $ip;
}

function adminLogsBadgeClass(string $action): string
{
    $action = strtolower(trim($action));
    if (in_array($action, ['create', 'add', 'import', 'approve', 'restore'], true)) {
        return 'success';
    }
    if (in_array($action, ['edit', 'update', 'view'], true)) {
        return 'primary';
    }
    if (in_array($action, ['delete', 'reject'], true)) {
        return 'danger';
    }
    if (in_array($action, ['export', 'archive'], true)) {
        return 'warning';
    }

    return 'secondary';
}

function adminLogsDetailsSummary(?string $json): string
{
    $json = trim((string)$json);
    if ($json === '') {
        return '-';
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return substr($json, 0, 160);
    }

    $bits = [];
    foreach (['query', 'form', 'files'] as $key) {
        if (!empty($decoded[$key]) && is_array($decoded[$key])) {
            $bits[] = ucfirst($key) . ': ' . count($decoded[$key]) . ' item(s)';
        }
    }

    return $bits ? implode(' | ', $bits) : '-';
}

$action = strtolower(trim((string)($_GET['action'] ?? 'all')));
$allowedActions = ['all', 'create', 'edit', 'update', 'delete', 'import', 'export'];
if (!in_array($action, $allowedActions, true)) {
    $action = 'all';
}

$adminId = (int)($_GET['admin_id'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));

$where = [];
$types = '';
$params = [];

if ($action !== 'all') {
    if ($action === 'edit') {
        $where[] = 'action IN ("edit", "update")';
    } else {
        $where[] = 'action = ?';
        $types .= 's';
        $params[] = $action;
    }
}

if ($adminId > 0) {
    $where[] = 'admin_user_id = ?';
    $types .= 'i';
    $params[] = $adminId;
}

if ($search !== '') {
    $where[] = '(admin_name LIKE ? OR admin_username LIKE ? OR admin_email LIKE ? OR target_type LIKE ? OR target_id LIKE ? OR target_name LIKE ? OR activity_comment LIKE ? OR page LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssssssss';
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$logs = [];
$sql = 'SELECT * FROM admin_activity_logs' . $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT 500';
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
}

$summary = [
    'all' => 0,
    'create' => 0,
    'edit' => 0,
    'delete' => 0,
    'import' => 0,
    'export' => 0,
];
$summaryRes = $conn->query('SELECT action, COUNT(*) AS total FROM admin_activity_logs GROUP BY action');
if ($summaryRes instanceof mysqli_result) {
    while ($row = $summaryRes->fetch_assoc()) {
        $key = strtolower(trim((string)($row['action'] ?? '')));
        $count = (int)($row['total'] ?? 0);
        $summary['all'] += $count;
        if ($key === 'update') {
            $summary['edit'] += $count;
        } elseif (isset($summary[$key])) {
            $summary[$key] += $count;
        }
    }
}

$admins = [];
$adminRes = $conn->query('SELECT DISTINCT admin_user_id, admin_name, admin_username FROM admin_activity_logs WHERE admin_user_id IS NOT NULL ORDER BY admin_name ASC, admin_username ASC');
if ($adminRes instanceof mysqli_result) {
    while ($row = $adminRes->fetch_assoc()) {
        $admins[] = $row;
    }
}

$page_body_class = trim(($page_body_class ?? '') . ' reports-page admin-logs-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-login-logs-page.css', 'assets/css/modules/reports/reports-shell.css', 'assets/css/modules/reports/reports-admin-logs-page.css']);
$page_title = 'BioTern || Admin Logs';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
<div class="page-header page-header-with-middle">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title logs-page-title">
            <h5 class="m-b-10">Admin Logs</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="index.php">Reports</a></li>
            <li class="breadcrumb-item">Admin Logs</li>
        </ul>
    </div>
    <div class="page-header-middle">
        <p class="page-header-statement">Audit trail of admin create, edit, delete, import, and export activity.</p>
    </div>
    <?php ob_start(); ?>
        <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
        <a href="reports-login-logs.php" class="btn btn-outline-primary"><i class="feather-log-in me-1"></i>Login Logs</a>
        <button type="button" class="btn btn-light-brand" onclick="window.print();"><i class="feather-printer me-1"></i>Print</button>
    <?php
    biotern_render_page_header_actions([
        'menu_id' => 'reportsAdminLogsActionsMenu',
        'items_html' => ob_get_clean(),
    ]);
    ?>
</div>

<div class="main-content pb-5">
    <div class="logs-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
        <span class="logs-pill bg-soft-primary text-primary">
            <i class="feather feather-shield"></i>
            Last 500 admin events
        </span>
    </div>

    <div class="logs-summary-grid">
        <div class="logs-kpi total">
            <div class="logs-kpi-label">Total Events</div>
            <div class="logs-kpi-value"><?php echo (int)$summary['all']; ?></div>
        </div>
        <div class="logs-kpi success">
            <div class="logs-kpi-label">Creates / Imports</div>
            <div class="logs-kpi-value"><?php echo (int)($summary['create'] + $summary['import']); ?></div>
        </div>
        <div class="logs-kpi failed">
            <div class="logs-kpi-label">Deletes</div>
            <div class="logs-kpi-value"><?php echo (int)$summary['delete']; ?></div>
        </div>
    </div>

    <div class="logs-filter-wrap">
        <form method="get" class="row g-2 align-items-end admin-logs-auto-filter">
            <div class="col-sm-6 col-md-3">
                <label class="form-label mb-1">Action</label>
                <select class="form-select" name="action">
                    <option value="all" <?php echo $action === 'all' ? 'selected' : ''; ?>>All Actions</option>
                    <option value="create" <?php echo $action === 'create' ? 'selected' : ''; ?>>Create</option>
                    <option value="edit" <?php echo $action === 'edit' ? 'selected' : ''; ?>>Edit / Update</option>
                    <option value="delete" <?php echo $action === 'delete' ? 'selected' : ''; ?>>Delete</option>
                    <option value="import" <?php echo $action === 'import' ? 'selected' : ''; ?>>Import</option>
                    <option value="export" <?php echo $action === 'export' ? 'selected' : ''; ?>>Export</option>
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label mb-1">Admin</label>
                <select class="form-select" name="admin_id">
                    <option value="0">All Admins</option>
                    <?php foreach ($admins as $admin): ?>
                        <?php
                        $rowAdminId = (int)($admin['admin_user_id'] ?? 0);
                        $adminLabel = trim((string)($admin['admin_name'] ?? ''));
                        if ($adminLabel === '') {
                            $adminLabel = trim((string)($admin['admin_username'] ?? 'Admin'));
                        }
                        ?>
                        <option value="<?php echo $rowAdminId; ?>" <?php echo $adminId === $rowAdminId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($adminLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-8 col-md-4">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Admin, activity, page, target...">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">
                    <i class="feather feather-filter me-1"></i>Apply
                </button>
            </div>
            <div class="col-auto">
                <a href="reports-admin-logs.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="logs-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="text-nowrap">Time</th>
                        <th>Admin</th>
                        <th>Activity</th>
                        <th class="text-nowrap">Action</th>
                        <th>Target</th>
                        <th>Page</th>
                        <th class="text-nowrap">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $row): ?>
                            <?php
                            $rowAction = strtolower((string)($row['action'] ?? ''));
                            $badge = adminLogsBadgeClass($rowAction);
                            $adminName = trim((string)($row['admin_name'] ?? ''));
                            $adminUsername = trim((string)($row['admin_username'] ?? ''));
                            $adminEmail = trim((string)($row['admin_email'] ?? ''));
                            $activityComment = trim((string)($row['activity_comment'] ?? ''));
                            if ($activityComment === '') {
                                $activityComment = adminLogsDetailsSummary($row['details_json'] ?? '');
                            }
                            $targetName = trim((string)($row['target_name'] ?? ''));
                            $targetId = trim((string)($row['target_id'] ?? ''));
                            ?>
                            <tr>
                                <td class="text-nowrap"><?php echo htmlspecialchars(adminLogsFormatDateTime($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($adminName !== '' ? $adminName : ($adminUsername !== '' ? $adminUsername : 'Admin'), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($adminEmail !== '' ? $adminEmail : ($adminUsername !== '' ? $adminUsername : 'No username'), ENT_QUOTES, 'UTF-8'); ?></small>
                                </td>
                                <td>
                                    <div class="logs-activity-comment"><?php echo htmlspecialchars($activityComment !== '' ? $activityComment : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                </td>
                                <td class="text-nowrap">
                                    <span class="logs-pill bg-soft-<?php echo $badge; ?> text-<?php echo $badge; ?>">
                                        <?php echo htmlspecialchars((string)($row['action_label'] ?? ucwords($rowAction)), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-capitalize"><?php echo htmlspecialchars((string)($row['target_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($targetName !== '' ? $targetName : ($targetId !== '' ? $targetId : '-'), ENT_QUOTES, 'UTF-8'); ?></small>
                                </td>
                                <td><span class="logs-identifier"><?php echo htmlspecialchars((string)($row['page'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td class="text-nowrap"><?php echo htmlspecialchars(adminLogsDisplayIp($row['ip_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No admin activity logs yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
</main>
<?php include 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('.admin-logs-auto-filter');
    if (!form) {
        return;
    }

    var search = form.querySelector('input[name="search"]');
    var timer = null;

    form.querySelectorAll('select').forEach(function (select) {
        select.addEventListener('change', function () {
            form.requestSubmit();
        });
    });

    if (search) {
        search.addEventListener('input', function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                form.requestSubmit();
            }, 450);
        });
    }
});
</script>
