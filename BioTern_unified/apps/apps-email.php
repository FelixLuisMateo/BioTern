<?php
require_once dirname(__DIR__) . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function email_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function email_time_label(?string $value): string
{
    if (!$value) {
        return '-';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return (string)$value;
    }
    return date('M j, Y g:i A', $ts);
}

function email_preview(string $text, int $max = 90): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    if ($text === '') {
        return '[No preview]';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 3) . '...' : $text;
    }
    return strlen($text) > $max ? substr($text, 0, $max - 3) . '...' : $text;
}

function email_build_url(string $mailbox, array $params = []): string
{
    $query = array_merge(['mailbox' => $mailbox], $params);
    $query = array_filter($query, static fn($value) => $value !== null && $value !== '');
    return 'apps-email.php?' . http_build_query($query);
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUserName = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? ''));
if ($currentUserId <= 0) {
    header('Location: index.php');
    exit;
}

$conn->query(
    "CREATE TABLE IF NOT EXISTS app_emails (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sender_user_id BIGINT UNSIGNED NOT NULL,
        recipient_user_id BIGINT UNSIGNED NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body TEXT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        sender_deleted_at TIMESTAMP NULL DEFAULT NULL,
        recipient_deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_email_sender (sender_user_id, created_at),
        INDEX idx_email_recipient (recipient_user_id, is_read, created_at),
        INDEX idx_email_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$flashSuccess = '';
$flashError = '';
if (isset($_SESSION['email_flash']) && is_array($_SESSION['email_flash'])) {
    $flashSuccess = (string)($_SESSION['email_flash']['success'] ?? '');
    $flashError = (string)($_SESSION['email_flash']['error'] ?? '');
    unset($_SESSION['email_flash']);
}

if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['action'] ?? '') === 'send-email') {
    $recipientUserId = (int)($_POST['recipient_user_id'] ?? 0);
    $subject = trim((string)($_POST['subject'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));

    if ($recipientUserId <= 0) {
        $_SESSION['email_flash'] = ['error' => 'Please select a recipient.'];
        header('Location: apps-email.php?mailbox=inbox');
        exit;
    } elseif ($recipientUserId === $currentUserId) {
        $_SESSION['email_flash'] = ['error' => 'You cannot send email to yourself.'];
        header('Location: apps-email.php?mailbox=inbox');
        exit;
    } elseif ($subject === '') {
        $_SESSION['email_flash'] = ['error' => 'Subject is required.'];
        header('Location: apps-email.php?mailbox=inbox');
        exit;
    } else {
        if (function_exists('mb_substr')) {
            $subject = mb_substr($subject, 0, 255, 'UTF-8');
        } else {
            $subject = substr($subject, 0, 255);
        }

        $recipientStmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE id = ?
               AND is_active = 1
               AND (role <> 'student' OR COALESCE(application_status, 'approved') = 'approved')
             LIMIT 1"
        );
        $recipientExists = false;
        if ($recipientStmt) {
            $recipientStmt->bind_param('i', $recipientUserId);
            $recipientStmt->execute();
            $recipientExists = (bool)$recipientStmt->get_result()->fetch_assoc();
            $recipientStmt->close();
        }

        if (!$recipientExists) {
            $_SESSION['email_flash'] = ['error' => 'Recipient not found.'];
            header('Location: apps-email.php?mailbox=inbox');
            exit;
        } else {
            $sendStmt = $conn->prepare('INSERT INTO app_emails (sender_user_id, recipient_user_id, subject, body, is_read, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())');
            if (!$sendStmt) {
                $_SESSION['email_flash'] = ['error' => 'Failed to prepare email send request.'];
                header('Location: apps-email.php?mailbox=inbox');
                exit;
            } else {
                $sendStmt->bind_param('iiss', $currentUserId, $recipientUserId, $subject, $body);
                $ok = $sendStmt->execute();
                $newId = (int)$sendStmt->insert_id;
                $sendStmt->close();

                if ($ok) {
                    $_SESSION['email_flash'] = ['success' => 'Email sent successfully.'];
                    header('Location: apps-email.php?mailbox=sent' . ($newId > 0 ? ('&view=' . $newId) : ''));
                    exit;
                } else {
                    $_SESSION['email_flash'] = ['error' => 'Failed to send email.'];
                    header('Location: apps-email.php?mailbox=inbox');
                    exit;
                }
            }
        }
    }
}

if ((string)($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['action'] ?? '') === 'delete-email') {
    $deleteId = (int)($_POST['email_id'] ?? 0);
    $deleteMailbox = strtolower(trim((string)($_POST['mailbox'] ?? 'inbox')));
    $deleteMailbox = in_array($deleteMailbox, ['inbox', 'sent'], true) ? $deleteMailbox : 'inbox';

    if ($deleteId <= 0) {
        $_SESSION['email_flash'] = ['error' => 'Email not found.'];
        header('Location: ' . email_build_url($deleteMailbox));
        exit;
    }

    if ($deleteMailbox === 'inbox') {
        $deleteStmt = $conn->prepare('UPDATE app_emails SET recipient_deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND recipient_user_id = ? AND recipient_deleted_at IS NULL LIMIT 1');
    } else {
        $deleteStmt = $conn->prepare('UPDATE app_emails SET sender_deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND sender_user_id = ? AND sender_deleted_at IS NULL LIMIT 1');
    }

    if (!$deleteStmt) {
        $_SESSION['email_flash'] = ['error' => 'Unable to delete this email right now.'];
        header('Location: ' . email_build_url($deleteMailbox));
        exit;
    }

    $deleteStmt->bind_param('ii', $deleteId, $currentUserId);
    $deleteStmt->execute();
    $deletedRows = (int)$deleteStmt->affected_rows;
    $deleteStmt->close();

    $_SESSION['email_flash'] = $deletedRows > 0
        ? ['success' => 'Email moved out of your mailbox.']
        : ['error' => 'Unable to delete this email.'];
    header('Location: ' . email_build_url($deleteMailbox));
    exit;
}

$mailbox = strtolower(trim((string)($_GET['mailbox'] ?? 'inbox')));
if (!in_array($mailbox, ['inbox', 'sent'], true)) {
    $mailbox = 'inbox';
}
$viewId = (int)($_GET['view'] ?? 0);
$search = trim((string)($_GET['q'] ?? ''));
$filter = strtolower(trim((string)($_GET['filter'] ?? 'all')));
if (!in_array($filter, ['all', 'unread'], true)) {
    $filter = 'all';
}
$composeOpen = ((string)($_GET['compose'] ?? '') === '1');
$composeRecipientId = (int)($_GET['to'] ?? 0);
$composeSubject = trim((string)($_GET['subject'] ?? ''));
$composeBody = trim((string)($_GET['body'] ?? ''));

$users = [];
$usersStmt = $conn->prepare(
    'SELECT id, COALESCE(NULLIF(name, ""), username, email, CONCAT("User #", id)) AS display_name, email
     FROM users
     WHERE id <> ?
       AND is_active = 1
       AND (role <> \'student\' OR COALESCE(application_status, \'approved\') = \'approved\')
     ORDER BY display_name ASC'
);
if ($usersStmt) {
    $usersStmt->bind_param('i', $currentUserId);
    $usersStmt->execute();
    $res = $usersStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $users[] = [
            'id' => (int)($row['id'] ?? 0),
            'display_name' => (string)($row['display_name'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
        ];
    }
    $usersStmt->close();
}

$inboxCount = 0;
$inboxUnreadCount = 0;
$sentCount = 0;

$inboxCountStmt = $conn->prepare('SELECT COUNT(*) AS c FROM app_emails WHERE recipient_user_id = ? AND recipient_deleted_at IS NULL');
if ($inboxCountStmt) {
    $inboxCountStmt->bind_param('i', $currentUserId);
    $inboxCountStmt->execute();
    $inboxCount = (int)(($inboxCountStmt->get_result()->fetch_assoc())['c'] ?? 0);
    $inboxCountStmt->close();
}

$inboxUnreadStmt = $conn->prepare('SELECT COUNT(*) AS c FROM app_emails WHERE recipient_user_id = ? AND recipient_deleted_at IS NULL AND is_read = 0');
if ($inboxUnreadStmt) {
    $inboxUnreadStmt->bind_param('i', $currentUserId);
    $inboxUnreadStmt->execute();
    $inboxUnreadCount = (int)(($inboxUnreadStmt->get_result()->fetch_assoc())['c'] ?? 0);
    $inboxUnreadStmt->close();
}

$sentCountStmt = $conn->prepare('SELECT COUNT(*) AS c FROM app_emails WHERE sender_user_id = ? AND sender_deleted_at IS NULL');
if ($sentCountStmt) {
    $sentCountStmt->bind_param('i', $currentUserId);
    $sentCountStmt->execute();
    $sentCount = (int)(($sentCountStmt->get_result()->fetch_assoc())['c'] ?? 0);
    $sentCountStmt->close();
}

$list = [];
$listParams = [$currentUserId];
$listTypes = 'i';
if ($mailbox === 'inbox') {
    $listSql = 'SELECT
            e.id,
            e.subject,
            e.body,
            e.is_read,
            e.created_at,
            e.sender_user_id,
            COALESCE(NULLIF(u.name, ""), u.username, u.email, CONCAT("User #", u.id)) AS sender_name,
            u.email AS sender_email
        FROM app_emails e
        LEFT JOIN users u ON u.id = e.sender_user_id
        WHERE e.recipient_user_id = ? AND e.recipient_deleted_at IS NULL';
    if ($filter === 'unread') {
        $listSql .= ' AND e.is_read = 0';
    }
    if ($search !== '') {
        $listSql .= ' AND (
            e.subject LIKE ?
            OR e.body LIKE ?
            OR COALESCE(NULLIF(u.name, ""), u.username, u.email, CONCAT("User #", u.id)) LIKE ?
            OR COALESCE(u.email, "") LIKE ?
        )';
        $searchLike = '%' . $search . '%';
        array_push($listParams, $searchLike, $searchLike, $searchLike, $searchLike);
        $listTypes .= 'ssss';
    }
    $listSql .= '
        ORDER BY e.created_at DESC, e.id DESC
        LIMIT 200';
} else {
    $listSql = 'SELECT
            e.id,
            e.subject,
            e.body,
            e.is_read,
            e.created_at,
            e.recipient_user_id,
            COALESCE(NULLIF(u.name, ""), u.username, u.email, CONCAT("User #", u.id)) AS recipient_name,
            u.email AS recipient_email
        FROM app_emails e
        LEFT JOIN users u ON u.id = e.recipient_user_id
        WHERE e.sender_user_id = ? AND e.sender_deleted_at IS NULL';
    if ($search !== '') {
        $listSql .= ' AND (
            e.subject LIKE ?
            OR e.body LIKE ?
            OR COALESCE(NULLIF(u.name, ""), u.username, u.email, CONCAT("User #", u.id)) LIKE ?
            OR COALESCE(u.email, "") LIKE ?
        )';
        $searchLike = '%' . $search . '%';
        array_push($listParams, $searchLike, $searchLike, $searchLike, $searchLike);
        $listTypes .= 'ssss';
    }
    $listSql .= '
        ORDER BY e.created_at DESC, e.id DESC
        LIMIT 200';
}

$listStmt = $conn->prepare($listSql);
if ($listStmt) {
    $listStmt->bind_param($listTypes, ...$listParams);
    $listStmt->execute();
    $res = $listStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $item = [
            'id' => (int)($row['id'] ?? 0),
            'subject' => (string)($row['subject'] ?? ''),
            'body' => (string)($row['body'] ?? ''),
            'is_read' => (int)($row['is_read'] ?? 0) === 1,
            'created_at' => (string)($row['created_at'] ?? ''),
            'preview' => email_preview((string)($row['body'] ?? '')),
        ];
        if ($mailbox === 'inbox') {
            $item['person_name'] = (string)($row['sender_name'] ?? '-');
            $item['person_email'] = (string)($row['sender_email'] ?? '');
        } else {
            $item['person_name'] = (string)($row['recipient_name'] ?? '-');
            $item['person_email'] = (string)($row['recipient_email'] ?? '');
        }
        $list[] = $item;
    }
    $listStmt->close();
}

$selected = null;
if ($viewId > 0) {
    $viewSql = $mailbox === 'inbox'
        ? 'SELECT
                e.*,
                COALESCE(NULLIF(s.name, ""), s.username, s.email, CONCAT("User #", s.id)) AS sender_name,
                s.email AS sender_email,
                COALESCE(NULLIF(r.name, ""), r.username, r.email, CONCAT("User #", r.id)) AS recipient_name,
                r.email AS recipient_email
            FROM app_emails e
            LEFT JOIN users s ON s.id = e.sender_user_id
            LEFT JOIN users r ON r.id = e.recipient_user_id
            WHERE e.id = ? AND e.recipient_user_id = ? AND e.recipient_deleted_at IS NULL
            LIMIT 1'
        : 'SELECT
                e.*,
                COALESCE(NULLIF(s.name, ""), s.username, s.email, CONCAT("User #", s.id)) AS sender_name,
                s.email AS sender_email,
                COALESCE(NULLIF(r.name, ""), r.username, r.email, CONCAT("User #", r.id)) AS recipient_name,
                r.email AS recipient_email
            FROM app_emails e
            LEFT JOIN users s ON s.id = e.sender_user_id
            LEFT JOIN users r ON r.id = e.recipient_user_id
            WHERE e.id = ? AND e.sender_user_id = ? AND e.sender_deleted_at IS NULL
            LIMIT 1';

    $viewStmt = $conn->prepare($viewSql);
    if ($viewStmt) {
        $viewStmt->bind_param('ii', $viewId, $currentUserId);
        $viewStmt->execute();
        $row = $viewStmt->get_result()->fetch_assoc();
        $viewStmt->close();

        if ($row) {
            $selected = [
                'id' => (int)($row['id'] ?? 0),
                'subject' => (string)($row['subject'] ?? ''),
                'body' => (string)($row['body'] ?? ''),
                'is_read' => (int)($row['is_read'] ?? 0) === 1,
                'created_at' => (string)($row['created_at'] ?? ''),
                'sender_user_id' => (int)($row['sender_user_id'] ?? 0),
                'recipient_user_id' => (int)($row['recipient_user_id'] ?? 0),
                'sender_name' => (string)($row['sender_name'] ?? '-'),
                'sender_email' => (string)($row['sender_email'] ?? ''),
                'recipient_name' => (string)($row['recipient_name'] ?? '-'),
                'recipient_email' => (string)($row['recipient_email'] ?? ''),
            ];

            if ($mailbox === 'inbox' && !$selected['is_read']) {
                $markReadStmt = $conn->prepare('UPDATE app_emails SET is_read = 1, updated_at = NOW() WHERE id = ? AND recipient_user_id = ?');
                if ($markReadStmt) {
                    $markReadStmt->bind_param('ii', $viewId, $currentUserId);
                    $markReadStmt->execute();
                    $markReadStmt->close();
                }
                $selected['is_read'] = true;
            }
        }
    }
}

$page_title = 'BioTern || Email';
include 'includes/header.php';
?>

<style>
    body.apps-email-page .main-content {
        padding-top: 0 !important;
    }

    body.apps-email-page .content-sidebar,
    body.apps-email-page .content-area {
        border-color: #e2e8f0 !important;
    }

    body.apps-email-page .content-sidebar-header,
    body.apps-email-page .content-area-header {
        background: #ffffff !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }

    body.apps-email-page .nxl-content-sidebar-item .nav-link {
        border-radius: 8px;
        margin: 0 0.35rem;
        color: #1f2937;
        font-weight: 600;
    }

    body.apps-email-page .nxl-content-sidebar-item .nav-link.active {
        background: #eef2ff;
        color: #1d4ed8;
    }

    body.apps-email-page .email-list-pane,
    body.apps-email-page .email-detail-pane {
        max-height: calc(100vh - 240px);
        overflow-y: auto;
    }

    body.apps-email-page .email-list-pane {
        border-right: 1px solid #e2e8f0;
        background: #ffffff;
    }

    body.apps-email-page .email-detail-pane {
        background: #ffffff;
    }

    body.apps-email-page .email-row {
        border-bottom: 1px solid #eef2f7;
        transition: background-color 0.16s ease;
    }

    body.apps-email-page .email-row:hover {
        background: #f8fafc;
    }

    body.apps-email-page .email-row.email-row-active {
        background: #eff6ff;
    }

    body.apps-email-page .email-person-name {
        color: #0f172a;
    }

    body.apps-email-page .email-subject {
        color: #0f172a;
    }

    body.apps-email-page .email-body {
        color: #111827;
    }

    body.apps-email-page .email-meta {
        color: #475569;
    }

    body.apps-email-page .email-toolbar-form .form-control,
    body.apps-email-page .email-toolbar-form .form-select {
        min-width: 0;
    }

    body.apps-email-page .email-toolbar-form {
        width: 100%;
    }

    body.apps-email-page .email-toolbar-actions {
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    body.apps-email-page .modal-backdrop.show {
        opacity: 0.62 !important;
    }

    body.apps-email-page #composeMail {
        z-index: 12050;
    }

    body.apps-email-page #composeMail .modal-content {
        border-radius: 12px;
        border: 1px solid rgba(27, 36, 54, 0.18);
        box-shadow: 0 18px 45px rgba(2, 6, 23, 0.45);
    }

    html.app-skin-dark body.apps-email-page #composeMail .modal-content {
        background-color: #0f172a !important;
        border-color: #1b2436 !important;
    }

    html.app-skin-dark body.apps-email-page #composeMail .modal-header,
    html.app-skin-dark body.apps-email-page #composeMail .modal-footer {
        border-color: #1b2436 !important;
    }

    html.app-skin-dark body.apps-email-page #composeMail .form-control,
    html.app-skin-dark body.apps-email-page #composeMail .form-select,
    html.app-skin-dark body.apps-email-page #composeMail textarea {
        background-color: #121a2d !important;
        border-color: #1b2436 !important;
        color: #b1b4c0 !important;
    }

    html.app-skin-dark body.apps-email-page #composeMail .form-control::placeholder,
    html.app-skin-dark body.apps-email-page #composeMail textarea::placeholder {
        color: rgba(177, 180, 192, 0.75) !important;
    }

    html.app-skin-dark body.apps-email-page .content-sidebar,
    html.app-skin-dark body.apps-email-page .content-area {
        border-color: #1b2436 !important;
    }

    html.app-skin-dark body.apps-email-page .content-sidebar-header,
    html.app-skin-dark body.apps-email-page .content-area-header {
        background: #0f172a !important;
        border-bottom-color: #1b2436 !important;
    }

    html.app-skin-dark body.apps-email-page .nxl-content-sidebar-item .nav-link {
        color: #dbe5f5 !important;
    }

    html.app-skin-dark body.apps-email-page .nxl-content-sidebar-item .nav-link.active {
        background: #1c2740 !important;
        color: #8fb4ff !important;
    }

    html.app-skin-dark body.apps-email-page .email-list-pane {
        border-right-color: #1b2436 !important;
        background: #0f172a;
    }

    html.app-skin-dark body.apps-email-page .email-detail-pane {
        background: #0f172a;
    }

    html.app-skin-dark body.apps-email-page .email-row {
        border-bottom-color: #1b2436;
    }

    html.app-skin-dark body.apps-email-page .email-row:hover {
        background: #162238;
    }

    html.app-skin-dark body.apps-email-page .email-row.email-row-active {
        background: #1b2a46;
    }

    html.app-skin-dark body.apps-email-page .email-person-name,
    html.app-skin-dark body.apps-email-page .email-subject,
    html.app-skin-dark body.apps-email-page .email-body {
        color: #e6edf8 !important;
    }

    html.app-skin-dark body.apps-email-page .email-meta,
    html.app-skin-dark body.apps-email-page .text-muted {
        color: #9fb0cc !important;
    }
</style>

<div class="main-content d-flex">
    <div class="content-sidebar content-sidebar-md" data-scrollbar-target="#psScrollbarInit">
        <div class="content-sidebar-header bg-white sticky-top hstack justify-content-between">
            <h4 class="fw-bolder mb-0">Email</h4>
            <a href="javascript:void(0);" class="app-sidebar-close-trigger d-flex">
                <i class="feather-x"></i>
            </a>
        </div>

        <div class="content-sidebar-header">
            <a href="javascript:void(0);" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#composeMail">
                <i class="feather-plus me-2"></i>
                <span>Compose</span>
            </a>
        </div>

        <div class="content-sidebar-body">
            <ul class="nav flex-column nxl-content-sidebar-item">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $mailbox === 'inbox' ? ' active' : ''; ?>" href="apps-email.php?mailbox=inbox">
                        <span class="d-flex align-items-center">
                            <i class="feather-inbox me-3"></i>
                            <span>Inbox</span>
                        </span>
                        <span class="badge bg-soft-primary text-primary"><?php echo (int)$inboxUnreadCount; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $mailbox === 'sent' ? ' active' : ''; ?>" href="apps-email.php?mailbox=sent">
                        <span class="d-flex align-items-center">
                            <i class="feather-send me-3"></i>
                            <span>Sent</span>
                        </span>
                        <span class="badge bg-soft-success text-success"><?php echo (int)$sentCount; ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="content-area" data-scrollbar-target="#psScrollbarInit">
        <div class="content-area-header bg-white sticky-top">
            <div class="page-header-left d-flex align-items-center gap-2">
                <a href="javascript:void(0);" class="app-sidebar-open-trigger me-2">
                    <i class="feather-align-left fs-20"></i>
                </a>
                <h5 class="mb-0 text-capitalize"><?php echo email_esc($mailbox); ?></h5>
            </div>
            <div class="page-header-right ms-auto w-100" style="max-width: 620px;">
                <form method="get" action="apps-email.php" class="d-flex align-items-center justify-content-end email-toolbar-form">
                    <input type="hidden" name="mailbox" value="<?php echo email_esc($mailbox); ?>">
                    <div class="d-flex align-items-center justify-content-end email-toolbar-actions w-100">
                        <?php if ($mailbox === 'inbox'): ?>
                            <select name="filter" class="form-select form-select-sm" style="max-width: 140px;">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Mail</option>
                                <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                            </select>
                        <?php endif; ?>
                        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search mail" value="<?php echo email_esc($search); ?>" style="max-width: 220px;">
                        <button type="submit" class="btn btn-light btn-sm">Apply</button>
                        <?php if ($search !== '' || ($mailbox === 'inbox' && $filter !== 'all')): ?>
                            <a href="<?php echo email_esc(email_build_url($mailbox)); ?>" class="btn btn-link btn-sm text-decoration-none">Reset</a>
                        <?php endif; ?>
                        <span class="text-muted small"><?php echo count($list); ?> message(s)</span>
                    </div>
                </form>
            </div>
        </div>

        <div class="content-area-body p-0">
            <?php if ($flashError !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show rounded-2 shadow-sm mb-3" role="alert" id="emailAlertError">
                    <span class="me-2"><i class="feather-x-circle"></i></span> <?php echo email_esc($flashError); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($flashSuccess !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show rounded-2 shadow-sm mb-3" role="alert" id="emailAlertSuccess">
                    <span class="me-2"><i class="feather-check-circle"></i></span> <?php echo email_esc($flashSuccess); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-0">
                <div class="col-lg-5 email-list-pane">
                    <?php if (empty($list)): ?>
                        <div class="p-4 text-muted text-center">No messages in <?php echo email_esc($mailbox); ?>.</div>
                    <?php else: ?>
                        <?php foreach ($list as $item): ?>
                            <?php $isActive = (int)$item['id'] === $viewId; ?>
                            <a href="apps-email.php?mailbox=<?php echo email_esc($mailbox); ?>&view=<?php echo (int)$item['id']; ?>" class="text-decoration-none text-reset d-block email-row <?php echo $isActive ? 'email-row-active' : ''; ?>">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="text-truncate email-person-name <?php echo (!$item['is_read'] && $mailbox === 'inbox') ? '' : 'fw-semibold'; ?>"><?php echo email_esc($item['person_name']); ?></strong>
                                        <small class="email-meta ms-2"><?php echo email_esc(email_time_label($item['created_at'])); ?></small>
                                    </div>
                                    <div class="fw-semibold text-truncate email-subject"><?php echo email_esc($item['subject']); ?></div>
                                    <div class="email-meta text-truncate"><?php echo email_esc($item['preview']); ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="col-lg-7 email-detail-pane">
                    <?php if ($selected === null): ?>
                        <div class="p-4 text-center text-muted">
                            Select a message to read.
                        </div>
                    <?php else: ?>
                        <div class="p-4">
                            <h5 class="mb-3 email-subject"><?php echo email_esc($selected['subject']); ?></h5>
                            <div class="small email-meta mb-3">
                                <div><strong>From:</strong> <?php echo email_esc($selected['sender_name']); ?><?php echo $selected['sender_email'] !== '' ? ' &lt;' . email_esc($selected['sender_email']) . '&gt;' : ''; ?></div>
                                <div><strong>To:</strong> <?php echo email_esc($selected['recipient_name']); ?><?php echo $selected['recipient_email'] !== '' ? ' &lt;' . email_esc($selected['recipient_email']) . '&gt;' : ''; ?></div>
                                <div><strong>Date:</strong> <?php echo email_esc(email_time_label($selected['created_at'])); ?></div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                <a
                                    href="<?php echo email_esc(email_build_url($mailbox, [
                                        'view' => $selected['id'],
                                        'compose' => 1,
                                        'to' => $mailbox === 'inbox' ? (int)$selected['sender_user_id'] : (int)$selected['recipient_user_id'],
                                        'subject' => (stripos($selected['subject'], 'Re:') === 0 ? $selected['subject'] : ('Re: ' . $selected['subject'])),
                                    ])); ?>"
                                    class="btn btn-outline-primary btn-sm"
                                >
                                    <i class="feather-corner-up-left me-1"></i>Reply
                                </a>
                                <form method="post" action="apps-email.php" onsubmit="return confirm('Remove this email from your <?php echo email_esc($mailbox); ?>?');">
                                    <input type="hidden" name="action" value="delete-email">
                                    <input type="hidden" name="email_id" value="<?php echo (int)$selected['id']; ?>">
                                    <input type="hidden" name="mailbox" value="<?php echo email_esc($mailbox); ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="feather-trash-2 me-1"></i>Delete
                                    </button>
                                </form>
                            </div>
                            <hr>
                            <div class="mb-0 email-body" style="white-space: pre-wrap;"><?php echo nl2br(email_esc($selected['body'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="composeMail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Compose Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="apps-email.php?mailbox=inbox">
                <input type="hidden" name="action" value="send-email">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">From</label>
                        <input type="text" class="form-control" value="<?php echo email_esc($currentUserName !== '' ? $currentUserName : ('User #' . $currentUserId)); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <select name="recipient_user_id" class="form-select" required>
                            <option value="">Select recipient...</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo (int)$u['id']; ?>" <?php echo $composeRecipientId === (int)$u['id'] ? 'selected' : ''; ?>><?php echo email_esc($u['display_name']); ?><?php echo $u['email'] !== '' ? ' (' . email_esc($u['email']) . ')' : ''; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" maxlength="255" required value="<?php echo email_esc($composeSubject); ?>">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Message</label>
                        <textarea name="body" class="form-control" rows="7" placeholder="Write your message..."><?php echo email_esc($composeBody); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="composeSendBtn"><i class="feather-send me-1"></i>Send</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        if (document.body) {
            document.body.classList.add('apps-email-page');
        }

        // Move modal to body for proper z-index
        var composeModal = document.getElementById('composeMail');
        if (composeModal && document.body && composeModal.parentElement !== document.body) {
            document.body.appendChild(composeModal);
        }

        // Auto-hide alerts after 4 seconds
        setTimeout(function () {
            var err = document.getElementById('emailAlertError');
            if (err) err.classList.remove('show');
            var ok = document.getElementById('emailAlertSuccess');
            if (ok) ok.classList.remove('show');
        }, 4000);

        // Ctrl+Enter to send in compose modal
        var composeForm = composeModal ? composeModal.querySelector('form') : null;
        if (composeForm) {
            composeForm.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    var sendBtn = document.getElementById('composeSendBtn');
                    if (sendBtn) sendBtn.click();
                }
            });
        }

        <?php if ($composeOpen): ?>
        if (composeModal && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(composeModal).show();
        }
        <?php endif; ?>
    })();
</script>

<?php
include 'includes/footer.php';
$conn->close();
?>
