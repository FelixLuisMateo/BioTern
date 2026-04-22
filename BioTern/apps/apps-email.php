<?php
require_once dirname(__DIR__) . '/config/db.php';

require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

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

function email_role_label(?string $role): string
{
    $role = strtolower(trim((string)$role));
    if ($role === '') {
        return 'User';
    }

    return ucwords(str_replace(['_', '-'], ' ', $role));
}

function email_column_exists(mysqli $conn, string $table, string $column): bool
{
    $tableEscaped = $conn->real_escape_string($table);
    $columnEscaped = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM `{$tableEscaped}` LIKE '{$columnEscaped}'");
    return $result instanceof mysqli_result && $result->num_rows > 0;
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentUserName = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? ''));
$currentUserEmail = trim((string)($_SESSION['email'] ?? ''));
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

if (!email_column_exists($conn, 'app_emails', 'message_uid')) {
    @mysqli_query(
        $conn,
        "ALTER TABLE app_emails
         ADD COLUMN message_uid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE FIRST"
    );
}

$emailRowIdColumn = email_column_exists($conn, 'app_emails', 'message_uid') ? 'message_uid' : 'id';

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

        $recipientSql = "SELECT id
             FROM users
             WHERE id = ?
               AND is_active = 1";
        $recipientSql .= " LIMIT 1";
        $recipientStmt = $conn->prepare($recipientSql);
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
        $deleteStmt = $conn->prepare('UPDATE app_emails SET recipient_deleted_at = NOW(), updated_at = NOW() WHERE ' . $emailRowIdColumn . ' = ? AND recipient_user_id = ? AND recipient_deleted_at IS NULL LIMIT 1');
    } else {
        $deleteStmt = $conn->prepare('UPDATE app_emails SET sender_deleted_at = NOW(), updated_at = NOW() WHERE ' . $emailRowIdColumn . ' = ? AND sender_user_id = ? AND sender_deleted_at IS NULL LIMIT 1');
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
$roleFilter = strtolower(trim((string)($_GET['role_filter'] ?? 'all')));
$sort = strtolower(trim((string)($_GET['sort'] ?? 'newest')));
if (!in_array($filter, ['all', 'unread', 'read'], true)) {
    $filter = 'all';
}
if (!in_array($roleFilter, ['all', 'student', 'coordinator', 'supervisor', 'admin'], true)) {
    $roleFilter = 'all';
}
if (!in_array($sort, ['newest', 'oldest'], true)) {
    $sort = 'newest';
}
$composeOpen = ((string)($_GET['compose'] ?? '') === '1');
$composeRecipientId = (int)($_GET['to'] ?? 0);
$composeSubject = trim((string)($_GET['subject'] ?? ''));
$composeBody = trim((string)($_GET['body'] ?? ''));

$users = [];
$usersSql =
    'SELECT id, role, COALESCE(NULLIF(name, ""), username, email, CONCAT("User #", id)) AS display_name, email
     FROM users
     WHERE id <> ?
       AND is_active = 1';
$usersSql .= ' ORDER BY FIELD(LOWER(COALESCE(role, "")), "admin", "coordinator", "supervisor", "student"), display_name ASC';
$usersStmt = $conn->prepare($usersSql);
if ($usersStmt) {
    $usersStmt->bind_param('i', $currentUserId);
    $usersStmt->execute();
    $res = $usersStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $users[] = [
            'id' => (int)($row['id'] ?? 0),
            'role' => strtolower(trim((string)($row['role'] ?? ''))),
            'role_label' => email_role_label((string)($row['role'] ?? '')),
            'display_name' => (string)($row['display_name'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
        ];
    }
    $usersStmt->close();
}

$mailboxSearchPlaceholder = 'Search mail';
$mailboxComposeLabel = 'Compose';
$mailboxEmptyText = 'No messages in ' . $mailbox . '.';
$mailboxReadPrompt = 'Select a message to read.';
$recipientPlaceholder = 'Select recipient...';
$recipientHelpText = 'Every BioTern account has its own inbox. Choose any active BioTern account.';
$mailboxDescription = $mailbox === 'inbox'
    ? 'Your personal inbox across every active BioTern role.'
    : 'Everything you have sent from this account.';
$mailboxUnreadLabel = $mailbox === 'inbox' ? 'Unread' : 'Recipient Unread';
$mailboxReadLabel = $mailbox === 'inbox' ? 'Read' : 'Recipient Read';
$mailboxStateParams = [
    'q' => $search,
    'filter' => $filter,
    'role_filter' => $roleFilter,
    'sort' => $sort,
];

$page_styles = [
    'assets/css/modules/apps/apps-email-page.css',
    'assets/css/modules/apps/apps-workspace-theme.css',
];
$page_scripts = [
    'assets/js/modules/apps/apps-email-page.js',
];
$page_body_class = trim((string)($page_body_class ?? '') . ' apps-email-page');

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
            e.' . $emailRowIdColumn . ' AS email_row_id,
            e.subject,
            e.body,
            e.is_read,
            e.created_at,
            e.sender_user_id,
            COALESCE(NULLIF(u.name, ""), u.username, u.email, CONCAT("User #", u.id)) AS sender_name,
            u.email AS sender_email,
            LOWER(COALESCE(u.role, "")) AS person_role
        FROM app_emails e
        LEFT JOIN users u ON u.id = e.sender_user_id
        WHERE e.recipient_user_id = ? AND e.recipient_deleted_at IS NULL';
    if ($filter === 'unread') {
        $listSql .= ' AND e.is_read = 0';
    } elseif ($filter === 'read') {
        $listSql .= ' AND e.is_read = 1';
    }
    if ($roleFilter !== 'all') {
        $listSql .= ' AND LOWER(COALESCE(u.role, "")) = ?';
        $listParams[] = $roleFilter;
        $listTypes .= 's';
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
        ORDER BY e.created_at ' . ($sort === 'oldest' ? 'ASC' : 'DESC') . ', e.' . $emailRowIdColumn . ' ' . ($sort === 'oldest' ? 'ASC' : 'DESC') . '
        LIMIT 200';
} else {
    $listSql = 'SELECT
            e.' . $emailRowIdColumn . ' AS email_row_id,
            e.subject,
            e.body,
            e.is_read,
            e.created_at,
            e.recipient_user_id,
            COALESCE(NULLIF(u.name, ""), u.username, u.email, CONCAT("User #", u.id)) AS recipient_name,
            u.email AS recipient_email,
            LOWER(COALESCE(u.role, "")) AS person_role
        FROM app_emails e
        LEFT JOIN users u ON u.id = e.recipient_user_id
        WHERE e.sender_user_id = ? AND e.sender_deleted_at IS NULL';
    if ($roleFilter !== 'all') {
        $listSql .= ' AND LOWER(COALESCE(u.role, "")) = ?';
        $listParams[] = $roleFilter;
        $listTypes .= 's';
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
        ORDER BY e.created_at ' . ($sort === 'oldest' ? 'ASC' : 'DESC') . ', e.' . $emailRowIdColumn . ' ' . ($sort === 'oldest' ? 'ASC' : 'DESC') . '
        LIMIT 200';
}

$listStmt = $conn->prepare($listSql);
if ($listStmt) {
    $listStmt->bind_param($listTypes, ...$listParams);
    $listStmt->execute();
    $res = $listStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $item = [
            'id' => (int)($row['email_row_id'] ?? 0),
            'subject' => (string)($row['subject'] ?? ''),
            'body' => (string)($row['body'] ?? ''),
            'is_read' => (int)($row['is_read'] ?? 0) === 1,
            'created_at' => (string)($row['created_at'] ?? ''),
            'preview' => email_preview((string)($row['body'] ?? '')),
            'person_role' => strtolower(trim((string)($row['person_role'] ?? ''))),
            'person_role_label' => email_role_label((string)($row['person_role'] ?? '')),
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
                e.' . $emailRowIdColumn . ' AS email_row_id,
                e.*,
                COALESCE(NULLIF(s.name, ""), s.username, s.email, CONCAT("User #", s.id)) AS sender_name,
                s.email AS sender_email,
                COALESCE(NULLIF(r.name, ""), r.username, r.email, CONCAT("User #", r.id)) AS recipient_name,
                r.email AS recipient_email
            FROM app_emails e
            LEFT JOIN users s ON s.id = e.sender_user_id
            LEFT JOIN users r ON r.id = e.recipient_user_id
            WHERE e.' . $emailRowIdColumn . ' = ? AND e.recipient_user_id = ? AND e.recipient_deleted_at IS NULL
            LIMIT 1'
        : 'SELECT
                e.' . $emailRowIdColumn . ' AS email_row_id,
                e.*,
                COALESCE(NULLIF(s.name, ""), s.username, s.email, CONCAT("User #", s.id)) AS sender_name,
                s.email AS sender_email,
                COALESCE(NULLIF(r.name, ""), r.username, r.email, CONCAT("User #", r.id)) AS recipient_name,
                r.email AS recipient_email
            FROM app_emails e
            LEFT JOIN users s ON s.id = e.sender_user_id
            LEFT JOIN users r ON r.id = e.recipient_user_id
            WHERE e.' . $emailRowIdColumn . ' = ? AND e.sender_user_id = ? AND e.sender_deleted_at IS NULL
            LIMIT 1';

    $viewStmt = $conn->prepare($viewSql);
    if ($viewStmt) {
        $viewStmt->bind_param('ii', $viewId, $currentUserId);
        $viewStmt->execute();
        $row = $viewStmt->get_result()->fetch_assoc();
        $viewStmt->close();

        if ($row) {
            $selected = [
                'id' => (int)($row['email_row_id'] ?? 0),
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
                $markReadStmt = $conn->prepare('UPDATE app_emails SET is_read = 1, updated_at = NOW() WHERE ' . $emailRowIdColumn . ' = ? AND recipient_user_id = ?');
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
<main class="nxl-container apps-container">
    <div class="nxl-content">
        <div class="page-header email-page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="email-page-header-copy">
                    <ul class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item">Apps</li>
                        <li class="breadcrumb-item">Email</li>
                    </ul>
                </div>
            </div>
            <div class="page-header-right ms-auto">
                <div class="email-page-header-actions">
                    <span class="email-page-header-chip">
                        <i class="feather-inbox"></i>
                        <?php echo email_esc(ucfirst($mailbox)); ?>
                    </span>
                    <span class="email-page-header-chip">
                        <i class="feather-mail"></i>
                        <?php echo (int)count($list); ?> message(s)
                    </span>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeMail">
                        <i class="feather-edit-3 me-2"></i>Compose
                    </button>
                </div>
            </div>
        </div>
<div class="main-content d-flex apps-workspace-main email-workspace-shell" data-app-email-root data-compose-open="<?php echo $composeOpen ? '1' : '0'; ?>">
    <div class="content-sidebar content-sidebar-md" data-scrollbar-target="#psScrollbarInit">
        <div class="content-sidebar-body">
            <ul class="nav flex-column nxl-content-sidebar-item">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $mailbox === 'inbox' ? ' active' : ''; ?>" href="apps-email.php?mailbox=inbox">
                        <span class="d-flex align-items-center">
                            <i class="feather-inbox me-3"></i>
                            <span>Inbox</span>
                        </span>
                        <span class="email-counter-badge"><?php echo (int)$inboxUnreadCount; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $mailbox === 'sent' ? ' active' : ''; ?>" href="apps-email.php?mailbox=sent">
                        <span class="d-flex align-items-center">
                            <i class="feather-send me-3"></i>
                            <span>Sent</span>
                        </span>
                        <span class="email-counter-badge is-secondary"><?php echo (int)$sentCount; ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="content-area" data-scrollbar-target="#psScrollbarInit">
        <div class="content-area-header sticky-top">
            <div class="page-header-left d-flex align-items-center gap-2 email-toolbar-lead">
                <a href="javascript:void(0);" class="app-sidebar-open-trigger me-2">
                    <i class="feather-align-left fs-20"></i>
                </a>
                <div class="email-toolbar-copy">
                    <span class="email-toolbar-kicker"><?php echo email_esc(ucfirst($mailbox)); ?></span>
                    <p class="mb-0"><?php echo email_esc($mailboxReadPrompt); ?></p>
                </div>
            </div>
            <div class="page-header-right ms-auto w-100 email-toolbar-shell">
                <form method="get" action="apps-email.php" class="d-flex align-items-center justify-content-end email-toolbar-form">
                    <input type="hidden" name="mailbox" value="<?php echo email_esc($mailbox); ?>">
                    <div class="email-toolbar-actions w-100">
                        <div class="email-toolbar-group email-toolbar-group--filters">
                            <select name="filter" class="form-select form-select-sm email-filter-select">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Mail</option>
                                <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>><?php echo email_esc($mailboxUnreadLabel); ?></option>
                                <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>><?php echo email_esc($mailboxReadLabel); ?></option>
                            </select>
                            <select name="role_filter" class="form-select form-select-sm email-role-filter-select">
                                <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All Roles</option>
                                <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Students</option>
                                <option value="coordinator" <?php echo $roleFilter === 'coordinator' ? 'selected' : ''; ?>>Coordinators</option>
                                <option value="supervisor" <?php echo $roleFilter === 'supervisor' ? 'selected' : ''; ?>>Supervisors</option>
                                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                            </select>
                            <select name="sort" class="form-select form-select-sm email-sort-select">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            </select>
                        </div>
                        <div class="email-toolbar-group email-toolbar-group--search">
                            <input type="search" name="q" class="form-control form-control-sm email-search-input" placeholder="<?php echo email_esc($mailboxSearchPlaceholder); ?>" value="<?php echo email_esc($search); ?>">
                            <button type="submit" class="btn btn-light btn-sm">Apply</button>
                        </div>
                        <?php if ($search !== '' || $filter !== 'all' || $roleFilter !== 'all' || $sort !== 'newest'): ?>
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
                        <div class="email-empty-state">
                            <div class="email-empty-state__icon"><i class="feather-mail"></i></div>
                            <h3>No messages yet</h3>
                            <p><?php echo email_esc($mailboxEmptyText); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="email-list-scroll">
                        <?php foreach ($list as $item): ?>
                            <?php $isActive = (int)$item['id'] === $viewId; ?>
                            <a href="<?php echo email_esc(email_build_url($mailbox, array_merge($mailboxStateParams, ['view' => (int)$item['id']]))); ?>" class="text-decoration-none text-reset d-block email-row <?php echo $isActive ? 'email-row-active' : ''; ?>">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="text-truncate email-person-name <?php echo (!$item['is_read'] && $mailbox === 'inbox') ? '' : 'fw-semibold'; ?>"><?php echo email_esc($item['person_name']); ?></strong>
                                        <small class="email-meta ms-2"><?php echo email_esc(email_time_label($item['created_at'])); ?></small>
                                    </div>
                                    <div class="email-row-role"><?php echo email_esc($item['person_role_label']); ?></div>
                                    <div class="fw-semibold text-truncate email-subject"><?php echo email_esc($item['subject']); ?></div>
                                    <div class="email-meta text-truncate"><?php echo email_esc($item['preview']); ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-7 email-detail-pane">
                    <?php if ($selected === null): ?>
                        <div class="email-empty-state email-empty-state--detail">
                            <div class="email-empty-state__icon"><i class="feather-inbox"></i></div>
                            <h3>Select a message</h3>
                            <p><?php echo email_esc($mailboxReadPrompt); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="p-4 email-detail-card">
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
                                    ] + $mailboxStateParams)); ?>"
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
                            <div class="mb-0 email-body email-body-content"><?php echo nl2br(email_esc($selected['body'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
</main>

<div class="modal fade" id="composeMail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo email_esc($mailboxComposeLabel); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="apps-email.php?mailbox=inbox">
                <input type="hidden" name="action" value="send-email">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">From</label>
                        <input type="text" class="form-control" value="<?php echo email_esc($currentUserName !== '' ? $currentUserName : ('User #' . $currentUserId)); ?><?php echo $currentUserEmail !== '' ? ' <' . email_esc($currentUserEmail) . '>' : ''; ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <select name="recipient_user_id" class="form-select" required>
                            <option value=""><?php echo email_esc($recipientPlaceholder); ?></option>
                            <?php
                            $usersByRole = [];
                            foreach ($users as $u) {
                                $usersByRole[$u['role_label']][] = $u;
                            }
                            foreach ($usersByRole as $roleLabel => $roleUsers):
                            ?>
                                <optgroup label="<?php echo email_esc($roleLabel); ?>">
                                    <?php foreach ($roleUsers as $u): ?>
                                        <option value="<?php echo (int)$u['id']; ?>" <?php echo $composeRecipientId === (int)$u['id'] ? 'selected' : ''; ?>><?php echo email_esc($u['display_name']); ?><?php echo $u['email'] !== '' ? ' (' . email_esc($u['email']) . ')' : ''; ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?php echo email_esc($recipientHelpText); ?></div>
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

<?php
include 'includes/footer.php';
$conn->close();
?>
