<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

function chatreports_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function chatreports_is_valid_date(string $value): bool
{
    if ($value === '') {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
}

function chatreports_preview(string $message, int $maxLen = 120): string
{
    $message = trim($message);
    if ($message === '') {
        return '[No message text]';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($message) > $maxLen ? mb_substr($message, 0, $maxLen - 3) . '...' : $message;
    }
    return strlen($message) > $maxLen ? substr($message, 0, $maxLen - 3) . '...' : $message;
}

function chatreports_allowed_statuses(bool $includeAll = false): array
{
    $statuses = ['open', 'under_review', 'resolved', 'dismissed'];
    return $includeAll ? array_merge(['all'], $statuses) : $statuses;
}

function chatreports_normalize_status(string $status, bool $allowAll = false): string
{
    $status = strtolower(trim($status));
    $allowed = chatreports_allowed_statuses($allowAll);
    return in_array($status, $allowed, true) ? $status : ($allowAll ? 'all' : 'open');
}

function chatreports_status_label(string $status): string
{
    return match ($status) {
        'under_review' => 'Under Review',
        'resolved' => 'Resolved',
        'dismissed' => 'Dismissed',
        default => 'Open',
    };
}

function chatreports_redirect_with_filters(string $from, string $to, int $limit, string $status): void
{
    $query = http_build_query([
        'from' => $from,
        'to' => $to,
        'limit' => $limit,
        'status' => $status,
    ]);
    header('Location: reports-chat-reports.php' . ($query !== '' ? ('?' . $query) : ''));
    exit;
}

function chatreports_has_table(mysqli $conn, string $tableName): bool
{
    $safe = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    if ($res instanceof mysqli_result) {
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
    return false;
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS message_reports (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        message_id BIGINT UNSIGNED NOT NULL,
        reporter_user_id BIGINT UNSIGNED NOT NULL,
        reported_user_id BIGINT UNSIGNED NOT NULL,
        reason VARCHAR(255) NOT NULL DEFAULT 'Inappropriate message',
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        moderator_note VARCHAR(255) NULL DEFAULT NULL,
        reviewed_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL,
        reviewed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_reporter_message (message_id, reporter_user_id),
        INDEX idx_reported_user (reported_user_id),
        INDEX idx_report_status (status),
        INDEX idx_report_reviewed_at (reviewed_at),
        INDEX idx_report_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$reportColumns = [];
$reportColRes = $conn->query('SHOW COLUMNS FROM message_reports');
if ($reportColRes instanceof mysqli_result) {
    while ($row = $reportColRes->fetch_assoc()) {
        $field = strtolower((string)($row['Field'] ?? ''));
        if ($field !== '') {
            $reportColumns[$field] = true;
        }
    }
    $reportColRes->free();
}

if (!isset($reportColumns['status'])) {
    $conn->query("ALTER TABLE message_reports ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'open' AFTER reason");
}
if (!isset($reportColumns['moderator_note'])) {
    $conn->query("ALTER TABLE message_reports ADD COLUMN moderator_note VARCHAR(255) NULL DEFAULT NULL AFTER status");
}
if (!isset($reportColumns['reviewed_by_user_id'])) {
    $conn->query("ALTER TABLE message_reports ADD COLUMN reviewed_by_user_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER moderator_note");
}
if (!isset($reportColumns['reviewed_at'])) {
    $conn->query("ALTER TABLE message_reports ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER reviewed_by_user_id");
}

$messagesColumns = [];
$messagesColRes = $conn->query('SHOW COLUMNS FROM messages');
if ($messagesColRes instanceof mysqli_result) {
    while ($row = $messagesColRes->fetch_assoc()) {
        $field = strtolower((string)($row['Field'] ?? ''));
        if ($field !== '') {
            $messagesColumns[$field] = true;
        }
    }
    $messagesColRes->free();
}

$messagesReady = isset($messagesColumns['id']) && isset($messagesColumns['message']);
$messageIdCol = isset($messagesColumns['id']) ? 'id' : '';
$messageSenderCol = isset($messagesColumns['from_user_id']) ? 'from_user_id' : (isset($messagesColumns['sender_id']) ? 'sender_id' : '');
$messageRecipientCol = isset($messagesColumns['to_user_id']) ? 'to_user_id' : (isset($messagesColumns['recipient_id']) ? 'recipient_id' : '');
$messageDeletedCol = isset($messagesColumns['deleted_at']) ? 'deleted_at' : '';
$messageMediaCol = isset($messagesColumns['media_path']) ? 'media_path' : '';
$messageCreatedCol = isset($messagesColumns['created_at']) ? 'created_at' : '';

$from = trim((string)($_GET['from'] ?? date('Y-m-d', strtotime('-14 days'))));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
$statusFilter = chatreports_normalize_status((string)($_GET['status'] ?? 'all'), true);
$limit = (int)($_GET['limit'] ?? 300);
if ($limit <= 0) {
    $limit = 300;
}
if ($limit > 1000) {
    $limit = 1000;
}

if (!chatreports_is_valid_date($from)) {
    $from = date('Y-m-d', strtotime('-14 days'));
}
if (!chatreports_is_valid_date($to)) {
    $to = date('Y-m-d');
}
if ($from > $to) {
    $tmp = $from;
    $from = $to;
    $to = $tmp;
}

$actionSuccess = '';
$actionError = '';
if (isset($_SESSION['chatreports_flash']) && is_array($_SESSION['chatreports_flash'])) {
    $actionSuccess = (string)($_SESSION['chatreports_flash']['success'] ?? '');
    $actionError = (string)($_SESSION['chatreports_flash']['error'] ?? '');
    unset($_SESSION['chatreports_flash']);
}

if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['action'] ?? '') === 'update-report-status') {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $newStatus = chatreports_normalize_status((string)($_POST['new_status'] ?? 'open'));
    $moderatorNote = trim((string)($_POST['moderator_note'] ?? ''));
    if (function_exists('mb_substr')) {
        $moderatorNote = mb_substr($moderatorNote, 0, 255, 'UTF-8');
    } else {
        $moderatorNote = substr($moderatorNote, 0, 255);
    }

    $redirectFrom = trim((string)($_POST['from'] ?? $from));
    $redirectTo = trim((string)($_POST['to'] ?? $to));
    $redirectLimit = (int)($_POST['limit'] ?? $limit);
    $redirectStatus = chatreports_normalize_status((string)($_POST['status_filter'] ?? $statusFilter), true);

    if (!chatreports_is_valid_date($redirectFrom)) {
        $redirectFrom = $from;
    }
    if (!chatreports_is_valid_date($redirectTo)) {
        $redirectTo = $to;
    }
    if ($redirectLimit <= 0) {
        $redirectLimit = $limit;
    }
    if ($redirectLimit > 1000) {
        $redirectLimit = 1000;
    }

    if ($reportId <= 0) {
        $_SESSION['chatreports_flash'] = ['error' => 'Invalid report selected.'];
        chatreports_redirect_with_filters($redirectFrom, $redirectTo, $redirectLimit, $redirectStatus);
    }

    if ($newStatus === 'open') {
        $updateSql = 'UPDATE message_reports
            SET status = ?, moderator_note = ?, reviewed_by_user_id = NULL, reviewed_at = NULL, updated_at = NOW()
            WHERE id = ?
            LIMIT 1';
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt) {
            $updateStmt->bind_param('ssi', $newStatus, $moderatorNote, $reportId);
        }
    } else {
        $updateSql = 'UPDATE message_reports
            SET status = ?, moderator_note = ?, reviewed_by_user_id = ?, reviewed_at = NOW(), updated_at = NOW()
            WHERE id = ?
            LIMIT 1';
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt) {
            $updateStmt->bind_param('ssii', $newStatus, $moderatorNote, $currentUserId, $reportId);
        }
    }

    if (empty($updateStmt)) {
        $_SESSION['chatreports_flash'] = ['error' => 'Failed to update report status.'];
    } else {
        $ok = $updateStmt->execute();
        $updateStmt->close();
        if ($ok) {
            $_SESSION['chatreports_flash'] = ['success' => 'Report status updated to ' . chatreports_status_label($newStatus) . '.'];
        } else {
            $_SESSION['chatreports_flash'] = ['error' => 'Failed to update report status.'];
        }
    }

    chatreports_redirect_with_filters($redirectFrom, $redirectTo, $redirectLimit, $redirectStatus);
}

$summary = [
    'total_reports' => 0,
    'reporters' => 0,
    'reported_users' => 0,
    'last_7_days' => 0,
];
$rows = [];
$schemaError = '';

if (!chatreports_has_table($conn, 'message_reports')) {
    $schemaError = 'The message_reports table is not available.';
} elseif (!$messagesReady) {
    $schemaError = 'The messages table is missing required columns.';
}

if ($schemaError === '') {
    $summarySql = 'SELECT
            COUNT(*) AS total_reports,
            COUNT(DISTINCT reporter_user_id) AS reporters,
            COUNT(DISTINCT reported_user_id) AS reported_users,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS last_7_days
        FROM message_reports';
    $summaryStmt = $conn->prepare($summarySql);
    if ($summaryStmt) {
        $summaryStmt->execute();
        $sum = $summaryStmt->get_result()->fetch_assoc();
        $summaryStmt->close();
        if ($sum) {
            $summary['total_reports'] = (int)($sum['total_reports'] ?? 0);
            $summary['reporters'] = (int)($sum['reporters'] ?? 0);
            $summary['reported_users'] = (int)($sum['reported_users'] ?? 0);
            $summary['last_7_days'] = (int)($sum['last_7_days'] ?? 0);
        }
    }

    $messageSelect = 'm.message AS message_text';
    if ($messageMediaCol !== '') {
        $messageSelect .= ', m.' . $messageMediaCol . ' AS media_path';
    } else {
        $messageSelect .= ', NULL AS media_path';
    }
    if ($messageCreatedCol !== '') {
        $messageSelect .= ', m.' . $messageCreatedCol . ' AS message_created_at';
    } else {
        $messageSelect .= ', NULL AS message_created_at';
    }

    $messageJoin = 'LEFT JOIN messages m ON m.' . $messageIdCol . ' = mr.message_id';
    if ($messageDeletedCol !== '') {
        $messageJoin .= ' AND m.' . $messageDeletedCol . ' IS NULL';
    }

    $whereSql = 'WHERE mr.created_at >= ? AND mr.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $bindTypes = 'ss';
    $bindValues = [$from, $to];
    if ($statusFilter !== 'all') {
        $whereSql .= ' AND mr.status = ?';
        $bindTypes .= 's';
        $bindValues[] = $statusFilter;
    }

    $listSql = 'SELECT
            mr.id,
            mr.message_id,
            mr.reporter_user_id,
            mr.reported_user_id,
            mr.reason,
            COALESCE(NULLIF(mr.status, \'\'), \'open\') AS report_status,
            mr.moderator_note,
            mr.reviewed_at,
            mr.created_at,
            ' . $messageSelect . ',
            COALESCE(rep.name, rep.username, rep.email, CONCAT("User #", mr.reporter_user_id)) AS reporter_name,
            COALESCE(trg.name, trg.username, trg.email, CONCAT("User #", mr.reported_user_id)) AS reported_name,
            COALESCE(rev.name, rev.username, rev.email, "-") AS reviewer_name,
            CASE
                WHEN m.' . $messageIdCol . ' IS NULL THEN 0
                ELSE 1
            END AS message_exists
        FROM message_reports mr
        LEFT JOIN users rep ON rep.id = mr.reporter_user_id
        LEFT JOIN users trg ON trg.id = mr.reported_user_id
        LEFT JOIN users rev ON rev.id = mr.reviewed_by_user_id
        ' . $messageJoin . '
        ' . $whereSql . '
        ORDER BY mr.created_at DESC, mr.id DESC
        LIMIT ' . $limit;

    $listStmt = $conn->prepare($listSql);
    if ($listStmt) {
        $listStmt->bind_param($bindTypes, ...$bindValues);
        $listStmt->execute();
        $res = $listStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'message_id' => (int)($row['message_id'] ?? 0),
                'reporter_user_id' => (int)($row['reporter_user_id'] ?? 0),
                'reported_user_id' => (int)($row['reported_user_id'] ?? 0),
                'reporter_name' => (string)($row['reporter_name'] ?? '-'),
                'reported_name' => (string)($row['reported_name'] ?? '-'),
                'reason' => (string)($row['reason'] ?? ''),
                'report_status' => chatreports_normalize_status((string)($row['report_status'] ?? 'open')),
                'moderator_note' => (string)($row['moderator_note'] ?? ''),
                'reviewed_at' => (string)($row['reviewed_at'] ?? ''),
                'reviewer_name' => (string)($row['reviewer_name'] ?? '-'),
                'message_text' => (string)($row['message_text'] ?? ''),
                'media_path' => (string)($row['media_path'] ?? ''),
                'message_created_at' => (string)($row['message_created_at'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'message_exists' => (int)($row['message_exists'] ?? 0) === 1,
            ];
        }
        $listStmt->close();
    }
}

$page_body_class = trim(($page_body_class ?? '') . ' reports-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-chat-reports-page.css', 'assets/css/modules/reports/reports-shell.css']);
$page_title = 'BioTern || Reported Chats';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
<div class="main-content">
    <div class="page-header page-header-with-middle">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Reports - Reported Chats</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="reports-ojt.php">Reports</a></li>
                <li class="breadcrumb-item">Reported Chats</li>
            </ul>
        </div>
        <div class="page-header-middle">
            <p class="page-header-statement">Review flagged conversations and moderate reported chat activity in one stream.</p>
        </div>
        <div class="page-header-right ms-auto">
            <div class="d-md-none d-flex align-items-center">
                <button type="button" class="btn btn-light-brand page-header-actions-toggle" data-bs-toggle="collapse" data-bs-target="#reportsChatReportsActionsCollapse" aria-expanded="false" aria-controls="reportsChatReportsActionsCollapse">
                    <i class="feather-more-horizontal"></i>
                </button>
            </div>
            <div class="page-header-right-items collapse d-md-flex" id="reportsChatReportsActionsCollapse">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
                    <a href="reports-chat-logs.php" class="btn btn-outline-primary"><i class="feather-message-circle me-1"></i>Chat Logs</a>
                    <button type="button" class="btn btn-light-brand" onclick="window.print();"><i class="feather-printer me-1"></i>Print</button>
                </div>
            </div>
        </div>
    </div>

    <div class="chatreports-summary-grid">
        <div class="chatreports-kpi">
            <div class="chatreports-kpi-label">Total Reports</div>
            <div class="chatreports-kpi-value"><?php echo (int)$summary['total_reports']; ?></div>
        </div>
        <div class="chatreports-kpi">
            <div class="chatreports-kpi-label">Unique Reporters</div>
            <div class="chatreports-kpi-value"><?php echo (int)$summary['reporters']; ?></div>
        </div>
        <div class="chatreports-kpi">
            <div class="chatreports-kpi-label">Users Reported</div>
            <div class="chatreports-kpi-value"><?php echo (int)$summary['reported_users']; ?></div>
        </div>
        <div class="chatreports-kpi">
            <div class="chatreports-kpi-label">Last 7 Days</div>
            <div class="chatreports-kpi-value"><?php echo (int)$summary['last_7_days']; ?></div>
        </div>
    </div>

    <form method="get" class="chatreports-filter-wrap row g-2 align-items-end">
        <div class="col-sm-4 col-md-3">
            <label class="form-label mb-1">From</label>
            <input type="date" name="from" class="form-control" value="<?php echo chatreports_esc($from); ?>">
        </div>
        <div class="col-sm-4 col-md-3">
            <label class="form-label mb-1">To</label>
            <input type="date" name="to" class="form-control" value="<?php echo chatreports_esc($to); ?>">
        </div>
        <div class="col-sm-4 col-md-2">
            <label class="form-label mb-1">Status</label>
            <select name="status" class="form-select">
                <?php foreach (chatreports_allowed_statuses(true) as $statusOpt): ?>
                    <option value="<?php echo chatreports_esc($statusOpt); ?>"<?php echo $statusFilter === $statusOpt ? ' selected' : ''; ?>><?php echo chatreports_esc(chatreports_status_label($statusOpt)); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-4 col-md-2">
            <label class="form-label mb-1">Limit</label>
            <input type="number" name="limit" class="form-control" min="50" max="1000" step="50" value="<?php echo (int)$limit; ?>">
        </div>
        <div class="col-sm-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a href="reports-chat-reports.php" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <?php if ($actionSuccess !== ''): ?>
        <div class="alert alert-success"><?php echo chatreports_esc($actionSuccess); ?></div>
    <?php endif; ?>
    <?php if ($actionError !== ''): ?>
        <div class="alert alert-danger"><?php echo chatreports_esc($actionError); ?></div>
    <?php endif; ?>

    <?php if ($schemaError !== ''): ?>
        <div class="alert alert-warning"><?php echo chatreports_esc($schemaError); ?></div>
    <?php endif; ?>

    <div class="chatreports-table-card card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Reporter</th>
                            <th>Reported User</th>
                            <th>Message</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Review</th>
                            <th>Message Time</th>
                            <th>Reported At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No reported chats found for this range.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $item): ?>
                                <tr>
                                    <td><?php echo (int)$item['id']; ?></td>
                                    <td><?php echo chatreports_esc($item['reporter_name']); ?></td>
                                    <td><?php echo chatreports_esc($item['reported_name']); ?></td>
                                    <td class="chatreports-message">
                                        <?php
                                        $preview = chatreports_preview((string)$item['message_text']);
                                        if (!$item['message_exists']) {
                                            $preview = '[Message unavailable]';
                                        } elseif (!empty($item['media_path']) && trim((string)$item['message_text']) === '') {
                                            $preview = '[Media message]';
                                        }
                                        echo chatreports_esc($preview);
                                        ?>
                                    </td>
                                    <td><?php echo chatreports_esc((string)$item['reason']); ?></td>
                                    <td>
                                        <?php $status = (string)$item['report_status']; ?>
                                        <span class="chatreports-status-badge chatreports-status-<?php echo chatreports_esc($status); ?>"><?php echo chatreports_esc(chatreports_status_label($status)); ?></span>
                                    </td>
                                    <td>
                                        <?php if ((string)$item['reviewed_at'] !== ''): ?>
                                            <div><?php echo chatreports_esc((string)$item['reviewer_name']); ?></div>
                                            <small class="text-muted"><?php echo chatreports_esc((string)$item['reviewed_at']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Not reviewed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo chatreports_esc((string)($item['message_created_at'] ?: '-')); ?></td>
                                    <td><?php echo chatreports_esc((string)$item['created_at']); ?></td>
                                    <td>
                                        <form method="post" class="chatreports-action-form">
                                            <input type="hidden" name="action" value="update-report-status">
                                            <input type="hidden" name="report_id" value="<?php echo (int)$item['id']; ?>">
                                            <input type="hidden" name="from" value="<?php echo chatreports_esc($from); ?>">
                                            <input type="hidden" name="to" value="<?php echo chatreports_esc($to); ?>">
                                            <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
                                            <input type="hidden" name="status_filter" value="<?php echo chatreports_esc($statusFilter); ?>">
                                            <select name="new_status" class="form-select form-select-sm">
                                                <?php foreach (chatreports_allowed_statuses() as $statusOpt): ?>
                                                    <option value="<?php echo chatreports_esc($statusOpt); ?>"<?php echo $status === $statusOpt ? ' selected' : ''; ?>><?php echo chatreports_esc(chatreports_status_label($statusOpt)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <textarea name="moderator_note" class="form-control form-control-sm" maxlength="255" placeholder="Moderator note (optional)"><?php echo chatreports_esc((string)$item['moderator_note']); ?></textarea>
                                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        </form>
                                    </td>
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

<?php
include 'includes/footer.php';
$conn->close();
?>

