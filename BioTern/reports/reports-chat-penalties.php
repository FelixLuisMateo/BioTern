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
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

function chatpenalties_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function chatpenalties_label(string $action): string
{
    return match (strtolower(trim($action))) {
        'warning' => 'Warning',
        'mute_chat' => 'Muted Chat',
        'restrict_chat' => 'Restricted Chat',
        'suspend_chat' => 'Suspended Chat',
        'delete_message' => 'Deleted Message',
        default => ucwords(str_replace('_', ' ', trim($action))),
    };
}

function chatpenalties_status_label(array $row): string
{
    if ((int)($row['is_active'] ?? 0) !== 1) {
        return 'Revoked';
    }

    $endsAt = trim((string)($row['ends_at'] ?? ''));
    if ($endsAt !== '' && strtotime($endsAt) !== false && strtotime($endsAt) <= time()) {
        return 'Expired';
    }

    return 'Active';
}

function chatpenalties_status_class(string $status): string
{
    return match (strtolower($status)) {
        'active' => 'success',
        'expired' => 'warning',
        'revoked' => 'secondary',
        default => 'secondary',
    };
}

function chatpenalties_format_datetime($value): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('M d, Y h:i A', $timestamp);
}

function chatpenalties_ensure_table(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS chat_user_penalties (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT UNSIGNED NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(40) NOT NULL DEFAULT 'warning',
            reason VARCHAR(255) NULL DEFAULT NULL,
            moderator_note VARCHAR(255) NULL DEFAULT NULL,
            starts_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            ends_at TIMESTAMP NULL DEFAULT NULL,
            created_by_user_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_chat_penalty_report (report_id),
            INDEX idx_chat_penalty_user_active (user_id, is_active),
            INDEX idx_chat_penalty_action (action),
            INDEX idx_chat_penalty_ends (ends_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

chatpenalties_ensure_table($conn);
$conn->query(
    "CREATE TABLE IF NOT EXISTS message_reports (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        message_id BIGINT UNSIGNED NOT NULL,
        reporter_user_id BIGINT UNSIGNED NOT NULL,
        reported_user_id BIGINT UNSIGNED NOT NULL,
        reason VARCHAR(255) NOT NULL DEFAULT 'Inappropriate message',
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        resolution_action VARCHAR(40) NOT NULL DEFAULT 'none',
        punishment_until TIMESTAMP NULL DEFAULT NULL,
        moderator_note VARCHAR(255) NULL DEFAULT NULL,
        reviewed_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
        reviewed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_reporter_message (message_id, reporter_user_id),
        INDEX idx_reported_user (reported_user_id),
        INDEX idx_report_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$flashSuccess = '';
$flashError = '';
if (isset($_SESSION['chatpenalties_flash']) && is_array($_SESSION['chatpenalties_flash'])) {
    $flashSuccess = (string)($_SESSION['chatpenalties_flash']['success'] ?? '');
    $flashError = (string)($_SESSION['chatpenalties_flash']['error'] ?? '');
    unset($_SESSION['chatpenalties_flash']);
}

if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['action'] ?? '') === 'revoke') {
    $penaltyId = (int)($_POST['penalty_id'] ?? 0);
    if ($penaltyId <= 0) {
        $_SESSION['chatpenalties_flash'] = ['error' => 'Invalid penalty selected.'];
    } else {
        $stmt = $conn->prepare('UPDATE chat_user_penalties SET is_active = 0, updated_at = NOW() WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $penaltyId);
            $ok = $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            $_SESSION['chatpenalties_flash'] = ($ok && $affected > 0)
                ? ['success' => 'Chat punishment revoked.']
                : ['error' => 'Unable to revoke this punishment.'];
        } else {
            $_SESSION['chatpenalties_flash'] = ['error' => 'Failed to prepare revoke action.'];
        }
    }

    $query = $_GET ? ('?' . http_build_query($_GET)) : '';
    header('Location: reports-chat-penalties.php' . $query);
    exit;
}

$status = strtolower(trim((string)($_GET['status'] ?? 'active')));
if (!in_array($status, ['all', 'active', 'inactive'], true)) {
    $status = 'active';
}
$actionFilter = strtolower(trim((string)($_GET['penalty'] ?? 'all')));
$allowedActions = ['all', 'warning', 'mute_chat', 'restrict_chat', 'suspend_chat', 'delete_message'];
if (!in_array($actionFilter, $allowedActions, true)) {
    $actionFilter = 'all';
}
$search = trim((string)($_GET['search'] ?? ''));

$where = [];
$types = '';
$params = [];

if ($status === 'active') {
    $where[] = 'cup.is_active = 1 AND (cup.ends_at IS NULL OR cup.ends_at > NOW())';
} elseif ($status === 'inactive') {
    $where[] = '(cup.is_active = 0 OR (cup.ends_at IS NOT NULL AND cup.ends_at <= NOW()))';
}

if ($actionFilter !== 'all') {
    $where[] = 'cup.action = ?';
    $types .= 's';
    $params[] = $actionFilter;
}

if ($search !== '') {
    $where[] = '(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR cup.reason LIKE ? OR cup.moderator_note LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$sql = 'SELECT
        cup.*,
        COALESCE(u.name, u.username, u.email, CONCAT("User #", cup.user_id)) AS punished_user,
        u.email AS punished_email,
        COALESCE(m.name, m.username, m.email, "-") AS moderator_name,
        mr.message_id,
        mr.status AS report_status
    FROM chat_user_penalties cup
    LEFT JOIN users u ON u.id = cup.user_id
    LEFT JOIN users m ON m.id = cup.created_by_user_id
    LEFT JOIN message_reports mr ON mr.id = cup.report_id
    ' . $whereSql . '
    ORDER BY cup.is_active DESC, cup.created_at DESC, cup.id DESC
    LIMIT 500';

$rows = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
}

$summary = [
    'active' => 0,
    'muted' => 0,
    'restricted' => 0,
    'suspended' => 0,
];
$summaryRes = $conn->query("SELECT action, COUNT(*) AS total FROM chat_user_penalties WHERE is_active = 1 AND (ends_at IS NULL OR ends_at > NOW()) GROUP BY action");
if ($summaryRes instanceof mysqli_result) {
    while ($row = $summaryRes->fetch_assoc()) {
        $action = strtolower((string)($row['action'] ?? ''));
        $count = (int)($row['total'] ?? 0);
        $summary['active'] += $count;
        if ($action === 'mute_chat') {
            $summary['muted'] += $count;
        } elseif ($action === 'restrict_chat') {
            $summary['restricted'] += $count;
        } elseif ($action === 'suspend_chat') {
            $summary['suspended'] += $count;
        }
    }
}

$page_body_class = trim(($page_body_class ?? '') . ' reports-page chat-penalties-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-shell.css', 'assets/css/modules/reports/reports-login-logs-page.css', 'assets/css/modules/reports/reports-chat-penalties-page.css']);
$page_title = 'BioTern || Chat Penalties';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
<div class="page-header page-header-with-middle">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title logs-page-title">
            <h5 class="m-b-10">Chat Penalties</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="reports-chat-reports.php">Reported Chats</a></li>
            <li class="breadcrumb-item">Chat Penalties</li>
        </ul>
    </div>
    <div class="page-header-middle">
        <p class="page-header-statement">Review and revoke active chat punishments from resolved reports.</p>
    </div>
    <?php ob_start(); ?>
        <a href="reports-chat-reports.php" class="btn btn-outline-primary"><i class="feather-flag me-1"></i>Reported Chats</a>
        <a href="reports-chat-logs.php" class="btn btn-outline-secondary"><i class="feather-message-circle me-1"></i>Chat Logs</a>
    <?php
    biotern_render_page_header_actions([
        'menu_id' => 'reportsChatPenaltiesActionsMenu',
        'items_html' => ob_get_clean(),
    ]);
    ?>
</div>

<div class="main-content pb-5">
    <?php if ($flashSuccess !== ''): ?>
        <div class="alert alert-success"><?php echo chatpenalties_esc($flashSuccess); ?></div>
    <?php endif; ?>
    <?php if ($flashError !== ''): ?>
        <div class="alert alert-danger"><?php echo chatpenalties_esc($flashError); ?></div>
    <?php endif; ?>

    <div class="logs-summary-grid">
        <div class="logs-kpi total">
            <div class="logs-kpi-label">Active Penalties</div>
            <div class="logs-kpi-value"><?php echo (int)$summary['active']; ?></div>
        </div>
        <div class="logs-kpi failed">
            <div class="logs-kpi-label">Muted</div>
            <div class="logs-kpi-value"><?php echo (int)$summary['muted']; ?></div>
        </div>
        <div class="logs-kpi success">
            <div class="logs-kpi-label">Restricted / Suspended</div>
            <div class="logs-kpi-value"><?php echo (int)($summary['restricted'] + $summary['suspended']); ?></div>
        </div>
    </div>

    <div class="logs-filter-wrap">
        <form method="get" class="row g-2 align-items-end chat-penalties-auto-filter">
            <div class="col-sm-6 col-md-3">
                <label class="form-label mb-1">Status</label>
                <select class="form-select" name="status">
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Expired / Revoked</option>
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label mb-1">Punishment</label>
                <select class="form-select" name="penalty">
                    <option value="all" <?php echo $actionFilter === 'all' ? 'selected' : ''; ?>>All</option>
                    <?php foreach (array_slice($allowedActions, 1) as $action): ?>
                        <option value="<?php echo chatpenalties_esc($action); ?>" <?php echo $actionFilter === $action ? 'selected' : ''; ?>><?php echo chatpenalties_esc(chatpenalties_label($action)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-8 col-md-4">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control" name="search" value="<?php echo chatpenalties_esc($search); ?>" placeholder="User, reason, note...">
            </div>
            <div class="col-auto">
                <a href="reports-chat-penalties.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="logs-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Punishment</th>
                        <th>Reason / Note</th>
                        <th>Duration</th>
                        <th>Moderator</th>
                        <th>Status</th>
                        <th class="text-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $rowStatus = chatpenalties_status_label($row);
                            $statusClass = chatpenalties_status_class($rowStatus);
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo chatpenalties_esc($row['punished_user'] ?? '-'); ?></div>
                                    <small class="text-muted"><?php echo chatpenalties_esc($row['punished_email'] ?? 'No email'); ?></small>
                                </td>
                                <td>
                                    <span class="logs-pill bg-soft-primary text-primary"><?php echo chatpenalties_esc(chatpenalties_label((string)($row['action'] ?? ''))); ?></span>
                                    <?php if ((int)($row['report_id'] ?? 0) > 0): ?>
                                        <div><small class="text-muted">Report #<?php echo (int)$row['report_id']; ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo chatpenalties_esc(trim((string)($row['reason'] ?? '')) !== '' ? (string)$row['reason'] : '-'); ?></div>
                                    <?php if (trim((string)($row['moderator_note'] ?? '')) !== ''): ?>
                                        <small class="text-muted"><?php echo chatpenalties_esc((string)$row['moderator_note']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><small class="text-muted">Start</small> <?php echo chatpenalties_esc(chatpenalties_format_datetime($row['starts_at'] ?? '')); ?></div>
                                    <div><small class="text-muted">End</small> <?php echo chatpenalties_esc(chatpenalties_format_datetime($row['ends_at'] ?? '')); ?></div>
                                </td>
                                <td><?php echo chatpenalties_esc($row['moderator_name'] ?? '-'); ?></td>
                                <td><span class="logs-pill bg-soft-<?php echo $statusClass; ?> text-<?php echo $statusClass; ?>"><?php echo chatpenalties_esc($rowStatus); ?></span></td>
                                <td class="text-nowrap">
                                    <?php if ($rowStatus === 'Active'): ?>
                                        <form method="post" onsubmit="return confirm('Remove this chat punishment?');">
                                            <input type="hidden" name="action" value="revoke">
                                            <input type="hidden" name="penalty_id" value="<?php echo (int)$row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Revoke</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">No action</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted py-5">No chat penalties found.</td></tr>
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
    var form = document.querySelector('.chat-penalties-auto-filter');
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
