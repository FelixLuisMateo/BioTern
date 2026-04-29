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

function chatlogs_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function chatlogs_is_valid_date(string $value): bool
{
    if ($value === '') {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
}

function chatlogs_preview(string $message, int $maxLen = 120): string
{
    $message = trim($message);
    if ($message === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($message) > $maxLen ? mb_substr($message, 0, $maxLen - 3) . '...' : $message;
    }
    return strlen($message) > $maxLen ? substr($message, 0, $maxLen - 3) . '...' : $message;
}

function chatlogs_initial(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '?';
    }
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($value, 0, 1));
    }
    return strtoupper(substr($value, 0, 1));
}

$columns = [];
$colRes = $conn->query('SHOW COLUMNS FROM messages');
if ($colRes instanceof mysqli_result) {
    while ($row = $colRes->fetch_assoc()) {
        $field = strtolower((string)($row['Field'] ?? ''));
        if ($field !== '') {
            $columns[$field] = true;
        }
    }
    $colRes->free();
}

$senderCol = isset($columns['from_user_id']) ? 'from_user_id' : (isset($columns['sender_id']) ? 'sender_id' : '');
$recipientCol = isset($columns['to_user_id']) ? 'to_user_id' : (isset($columns['recipient_id']) ? 'recipient_id' : '');
$idCol = isset($columns['id']) ? 'id' : '';
$createdCol = isset($columns['created_at']) ? 'created_at' : '';
$mediaCol = isset($columns['media_path']) ? 'media_path' : '';
$isReadCol = isset($columns['is_read']) ? 'is_read' : '';
$readAtCol = isset($columns['read_at']) ? 'read_at' : '';

$schemaReady = ($senderCol !== '' && $recipientCol !== '' && $idCol !== '' && isset($columns['message']));
$schemaError = $schemaReady ? '' : 'The messages table is missing required chat columns.';

$dateFrom = trim((string)($_GET['from'] ?? date('Y-m-d', strtotime('-7 days'))));
$dateTo = trim((string)($_GET['to'] ?? date('Y-m-d')));
$search = trim((string)($_GET['search'] ?? ''));
$export = strtolower(trim((string)($_GET['export'] ?? '')));
$isExportCsv = ($export === 'csv');
$limit = (int)($_GET['limit'] ?? 300);
if ($limit <= 0) {
    $limit = 300;
}
if ($limit > 1000) {
    $limit = 1000;
}

$page = (int)($_GET['page'] ?? 1);
if ($page <= 0) {
    $page = 1;
}

if (!chatlogs_is_valid_date($dateFrom)) {
    $dateFrom = date('Y-m-d', strtotime('-7 days'));
}
if (!chatlogs_is_valid_date($dateTo)) {
    $dateTo = date('Y-m-d');
}
if ($dateFrom > $dateTo) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}

$whereParts = [];
$bindTypes = '';
$bindValues = [];
if ($createdCol !== '') {
    $whereParts[] = 'm.' . $createdCol . ' >= ? AND m.' . $createdCol . ' < DATE_ADD(?, INTERVAL 1 DAY)';
    $bindTypes .= 'ss';
    $bindValues[] = $dateFrom;
    $bindValues[] = $dateTo;
}

if ($search !== '') {
    $whereParts[] = '(m.message LIKE ? OR COALESCE(s.name, s.username, CONCAT("User #", m.' . $senderCol . ')) LIKE ? OR COALESCE(r.name, r.username, CONCAT("User #", m.' . $recipientCol . ')) LIKE ?)';
    $searchLike = '%' . $search . '%';
    $bindTypes .= 'sss';
    $bindValues[] = $searchLike;
    $bindValues[] = $searchLike;
    $bindValues[] = $searchLike;
}
$whereSql = !empty($whereParts) ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

$offset = ($page - 1) * $limit;

$summary = [
    'total_messages' => 0,
    'read_messages' => 0,
    'media_messages' => 0,
    'active_conversations' => 0,
];
$rows = [];
$totalRecords = 0;
$totalPages = 1;

if ($schemaReady) {
    $readExpr = $isReadCol !== '' ? ('COALESCE(m.' . $isReadCol . ', 0)') : '0';
    $mediaExpr = $mediaCol !== '' ? ('(m.' . $mediaCol . ' IS NOT NULL AND m.' . $mediaCol . " <> '')") : '0';

    $summarySql = 'SELECT
            COUNT(*) AS total_messages,
            SUM(CASE WHEN ' . $readExpr . ' = 1 THEN 1 ELSE 0 END) AS read_messages,
            SUM(CASE WHEN ' . $mediaExpr . ' THEN 1 ELSE 0 END) AS media_messages,
            COUNT(DISTINCT CONCAT(LEAST(m.' . $senderCol . ', m.' . $recipientCol . '), ":", GREATEST(m.' . $senderCol . ', m.' . $recipientCol . '))) AS active_conversations
        FROM messages m
        ' . $whereSql;

    $summaryStmt = $conn->prepare($summarySql);
    if ($summaryStmt) {
        if ($bindTypes !== '') {
            $summaryStmt->bind_param($bindTypes, ...$bindValues);
        }
        $summaryStmt->execute();
        $sumRow = $summaryStmt->get_result()->fetch_assoc();
        $summaryStmt->close();
        if ($sumRow) {
            $summary['total_messages'] = (int)($sumRow['total_messages'] ?? 0);
            $summary['read_messages'] = (int)($sumRow['read_messages'] ?? 0);
            $summary['media_messages'] = (int)($sumRow['media_messages'] ?? 0);
            $summary['active_conversations'] = (int)($sumRow['active_conversations'] ?? 0);
        }
    }

    $orderExpr = $createdCol !== '' ? ('m.' . $createdCol . ' DESC, m.' . $idCol . ' DESC') : ('m.' . $idCol . ' DESC');
    $createdExpr = $createdCol !== '' ? ('m.' . $createdCol) : 'NULL';
    $mediaSelect = $mediaCol !== '' ? ('m.' . $mediaCol) : 'NULL';
    $readSelect = $isReadCol !== '' ? ('COALESCE(m.' . $isReadCol . ', 0)') : '0';
    $readAtSelect = $readAtCol !== '' ? ('m.' . $readAtCol) : 'NULL';

    $countSql = 'SELECT COUNT(*) AS total_records
        FROM messages m
        LEFT JOIN users s ON s.id = m.' . $senderCol . '
        LEFT JOIN users r ON r.id = m.' . $recipientCol . '
        ' . $whereSql;
    $countStmt = $conn->prepare($countSql);
    if ($countStmt) {
        if ($bindTypes !== '') {
            $countStmt->bind_param($bindTypes, ...$bindValues);
        }
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        $totalRecords = (int)($countRow['total_records'] ?? 0);
    }
    $totalPages = max(1, (int)ceil($totalRecords / $limit));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    $logsSql = 'SELECT
            m.' . $idCol . ' AS message_id,
            m.' . $senderCol . ' AS sender_id,
            m.' . $recipientCol . ' AS recipient_id,
            m.message,
            ' . $mediaSelect . ' AS media_path,
            ' . $readSelect . ' AS is_read,
            ' . $readAtSelect . ' AS read_at,
            ' . $createdExpr . ' AS created_at,
            COALESCE(s.name, s.username, CONCAT("User #", m.' . $senderCol . ')) AS sender_name,
            COALESCE(r.name, r.username, CONCAT("User #", m.' . $recipientCol . ')) AS recipient_name
        FROM messages m
        LEFT JOIN users s ON s.id = m.' . $senderCol . '
        LEFT JOIN users r ON r.id = m.' . $recipientCol . '
        ' . $whereSql . '
        ORDER BY ' . $orderExpr . '
        LIMIT ? OFFSET ?';

    $logsStmt = $conn->prepare($logsSql);
    if ($logsStmt) {
        $listBindTypes = $bindTypes . 'ii';
        $listBindValues = $bindValues;
        $listBindValues[] = $limit;
        $listBindValues[] = $offset;
        $logsStmt->bind_param($listBindTypes, ...$listBindValues);
        $logsStmt->execute();
        $res = $logsStmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'message_id' => (int)($row['message_id'] ?? 0),
                'sender_name' => (string)($row['sender_name'] ?? '-'),
                'recipient_name' => (string)($row['recipient_name'] ?? '-'),
                'message' => (string)($row['message'] ?? ''),
                'media_path' => (string)($row['media_path'] ?? ''),
                'is_read' => (int)($row['is_read'] ?? 0) === 1,
                'read_at' => (string)($row['read_at'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }
        $logsStmt->close();
    }

    if ($isExportCsv) {
        $exportLimit = 10000;
        $exportSql = 'SELECT
                m.' . $idCol . ' AS message_id,
                COALESCE(s.name, s.username, CONCAT("User #", m.' . $senderCol . ')) AS sender_name,
                COALESCE(r.name, r.username, CONCAT("User #", m.' . $recipientCol . ')) AS recipient_name,
                m.message,
                ' . $mediaSelect . ' AS media_path,
                ' . $readSelect . ' AS is_read,
                ' . $readAtSelect . ' AS read_at,
                ' . $createdExpr . ' AS created_at
            FROM messages m
            LEFT JOIN users s ON s.id = m.' . $senderCol . '
            LEFT JOIN users r ON r.id = m.' . $recipientCol . '
            ' . $whereSql . '
            ORDER BY ' . $orderExpr . '
            LIMIT ?';

        $exportStmt = $conn->prepare($exportSql);
        $exportRows = [];
        if ($exportStmt) {
            $exportBindTypes = $bindTypes . 'i';
            $exportBindValues = $bindValues;
            $exportBindValues[] = $exportLimit;
            $exportStmt->bind_param($exportBindTypes, ...$exportBindValues);
            $exportStmt->execute();
            $exportRes = $exportStmt->get_result();
            while ($er = $exportRes->fetch_assoc()) {
                $exportRows[] = $er;
            }
            $exportStmt->close();
        }

        $safeFrom = preg_replace('/[^0-9\-]/', '', $dateFrom);
        $safeTo = preg_replace('/[^0-9\-]/', '', $dateTo);
        $filename = 'chat-logs-' . ($safeFrom !== '' ? $safeFrom : 'from') . '-to-' . ($safeTo !== '' ? $safeTo : 'to') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        if ($out !== false) {
            fputcsv($out, ['Message ID', 'Created At', 'Sender', 'Recipient', 'Message', 'Has Media', 'Read', 'Read At']);
            foreach ($exportRows as $er) {
                $hasMedia = trim((string)($er['media_path'] ?? '')) !== '' ? 'Yes' : 'No';
                $isRead = (int)($er['is_read'] ?? 0) === 1 ? 'Yes' : 'No';
                fputcsv($out, [
                    (int)($er['message_id'] ?? 0),
                    (string)($er['created_at'] ?? ''),
                    (string)($er['sender_name'] ?? ''),
                    (string)($er['recipient_name'] ?? ''),
                    (string)($er['message'] ?? ''),
                    $hasMedia,
                    $isRead,
                    (string)($er['read_at'] ?? ''),
                ]);
            }
            fclose($out);
        }
        exit;
    }
}

$readRate = $summary['total_messages'] > 0
    ? round(($summary['read_messages'] / $summary['total_messages']) * 100, 1)
    : 0;

$queryBase = [
    'from' => $dateFrom,
    'to' => $dateTo,
    'limit' => $limit,
];
if ($search !== '') {
    $queryBase['search'] = $search;
}
$exportQuery = http_build_query(array_merge($queryBase, ['export' => 'csv']));
$prevUrl = 'reports-chat-logs.php?' . http_build_query(array_merge($queryBase, ['page' => max(1, $page - 1)]));
$nextUrl = 'reports-chat-logs.php?' . http_build_query(array_merge($queryBase, ['page' => min($totalPages, $page + 1)]));

$page_body_class = trim(($page_body_class ?? '') . ' reports-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-chat-logs-page.css', 'assets/css/modules/reports/reports-shell.css']);
$page_scripts = array_merge($page_scripts ?? [], ['assets/js/modules/reports/reports-chat-logs-page.js']);
$page_title = 'BioTern || Chat Logs';
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
<div class="page-header page-header-with-middle">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title logs-page-title">
            <h5 class="m-b-10">Chat Logs</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="index.php">Reports</a></li>
            <li class="breadcrumb-item">Chat Logs</li>
        </ul>
    </div>
    <div class="page-header-middle">
        <p class="page-header-statement">Real-time visibility into conversations, delivery status, and user message activity.</p>
    </div>
    <?php ob_start(); ?>
        <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
        <a href="reports-chat-reports.php" class="btn btn-outline-primary"><i class="feather-alert-triangle me-1"></i>Reported Chats</a>
        <a href="reports-chat-logs.php?<?php echo chatlogs_esc($exportQuery); ?>" class="btn btn-outline-success"><i class="feather-download me-1"></i>Export</a>
        <button type="button" class="btn btn-light-brand" onclick="window.print();"><i class="feather-printer me-1"></i>Print</button>
    <?php
    biotern_render_page_header_actions([
        'menu_id' => 'reportsChatLogsActionsMenu',
        'items_html' => ob_get_clean(),
    ]);
    ?>
</div>

<div class="main-content pb-5">
    <div class="logs-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
        <span class="logs-pill bg-soft-primary text-primary">
            <i class="feather feather-message-circle"></i>
            Last <?php echo (int)$limit; ?> messages
        </span>
    </div>

            <?php if ($schemaError !== ''): ?>
                <div class="alert alert-danger"><?php echo chatlogs_esc($schemaError); ?></div>
            <?php endif; ?>

            <div class="chatlogs-kpi-grid">
                <div class="chatlogs-kpi">
                    <div class="chatlogs-kpi-label">Messages</div>
                    <div class="chatlogs-kpi-value text-primary"><?php echo number_format($summary['total_messages']); ?></div>
                </div>
                <div class="chatlogs-kpi">
                    <div class="chatlogs-kpi-label">Conversations</div>
                    <div class="chatlogs-kpi-value text-info"><?php echo number_format($summary['active_conversations']); ?></div>
                </div>
                <div class="chatlogs-kpi">
                    <div class="chatlogs-kpi-label">Read Messages</div>
                    <div class="chatlogs-kpi-value text-success"><?php echo number_format($summary['read_messages']); ?></div>
                </div>
                <div class="chatlogs-kpi">
                    <div class="chatlogs-kpi-label">Read Rate</div>
                    <div class="chatlogs-kpi-value text-dark"><?php echo number_format($readRate, 1); ?>%</div>
                </div>
            </div>

            <div class="chatlogs-filter">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label mb-1">From</label>
                        <input type="date" name="from" class="form-control" value="<?php echo chatlogs_esc($dateFrom); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-1">To</label>
                        <input type="date" name="to" class="form-control" value="<?php echo chatlogs_esc($dateTo); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label mb-1">Limit</label>
                        <input type="number" min="1" max="1000" name="limit" class="form-control" value="<?php echo (int)$limit; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label mb-1">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Message or user name" value="<?php echo chatlogs_esc($search); ?>">
                    </div>
                    <div class="col-md-12 d-flex gap-2">
                        <input type="hidden" name="page" value="1">
                        <button type="submit" class="btn btn-primary"><i class="feather-filter me-1"></i>Apply</button>
                        <a href="reports-chat-logs.php?<?php echo chatlogs_esc($exportQuery); ?>" class="btn btn-success"><i class="feather-download me-1"></i>Export CSV</a>
                        <a href="reports-chat-logs.php" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>

            <div class="chatlogs-conversations">
                            <?php if (empty($rows)): ?>
                                <div class="chatlogs-convo">
                                    <div class="text-center text-muted py-4">No chat logs found for the selected range.</div>
                                </div>
                            <?php else:
                                // Group rows by conversation pair (order-independent)
                                $conversations = [];
                                foreach ($rows as $row) {
                                    $a = $row['sender_name'];
                                    $b = $row['recipient_name'];
                                    $key = $a < $b ? $a . '|||' . $b : $b . '|||' . $a;
                                    $conversations[$key][] = $row;
                                }
                                // Sort conversations by most recent message first
                                uasort($conversations, function($ca, $cb) {
                                    $ta = strtotime((string)($ca[0]['created_at'] ?? '0'));
                                    $tb = strtotime((string)($cb[0]['created_at'] ?? '0'));
                                    return $tb - $ta;
                                });
                                foreach ($conversations as $key => $msgs):
                                    [$nameA, $nameB] = explode('|||', $key, 2);
                                    $msgCount = count($msgs);
                                    $readCount = count(array_filter($msgs, fn($m) => $m['is_read']));
                                    $firstMsg = end($msgs); // oldest (rows are DESC)
                                    $lastMsg = reset($msgs); // newest
                                    $threadRows = array_reverse($msgs); // oldest -> newest for readable thread timeline
                                    $previewCount = 3;
                                    $collapseThreshold = max(0, count($threadRows) - $previewCount);
                                    $hiddenCount = $collapseThreshold;
                                    $convoId = 'convo-' . substr(md5((string)$key), 0, 10);
                                    $firstTime = $firstMsg['created_at'] !== '' ? date('M d, Y', strtotime($firstMsg['created_at'])) : '-';
                                    $lastTime = $lastMsg['created_at'] !== '' ? date('M d, Y h:i A', strtotime($lastMsg['created_at'])) : '-';
                                    $initA = chatlogs_initial($nameA);
                                    $initB = chatlogs_initial($nameB);
                            ?>
                                <div class="chatlogs-convo">
                                    <div class="chatlogs-convo-header">
                                        <div class="chatlogs-convo-headline">
                                            <div class="chatlogs-convo-participants">
                                                <span class="avatar-badge"><?php echo chatlogs_esc($initA); ?></span>
                                                <?php echo chatlogs_esc($nameA); ?>
                                                <span class="sep"><i class="feather-repeat chatlogs-sep-icon"></i></span>
                                                <span class="avatar-badge"><?php echo chatlogs_esc($initB); ?></span>
                                                <?php echo chatlogs_esc($nameB); ?>
                                            </div>
                                            <div class="chatlogs-convo-meta">
                                                <span><i class="feather-calendar me-1"></i>Since <?php echo chatlogs_esc($firstTime); ?></span>
                                                <span>Last <?php echo chatlogs_esc($lastTime); ?></span>
                                            </div>
                                        </div>
                                        <div class="chatlogs-convo-actions">
                                            <div class="chatlogs-convo-meta">
                                                <span><i class="feather-message-square me-1"></i><?php echo $msgCount; ?> msg<?php echo $msgCount !== 1 ? 's' : ''; ?></span>
                                                <span class="text-success"><i class="feather-check-circle me-1"></i><?php echo $readCount; ?> read</span>
                                            </div>
                                            <?php if ($hiddenCount > 0): ?>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm chatlogs-toggle-btn"
                                                    data-target="<?php echo chatlogs_esc($convoId); ?>"
                                                    data-state="collapsed"
                                                    data-hidden-count="<?php echo (int)$hiddenCount; ?>"
                                                >
                                                    <i class="feather-chevron-down me-1"></i>
                                                    <span>Show <?php echo (int)$hiddenCount; ?> older</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div id="<?php echo chatlogs_esc($convoId); ?>" class="chatlogs-thread">
                                        <?php foreach ($threadRows as $idx => $row):
                                            $isCollapsedRow = ($hiddenCount > 0 && $idx < $collapseThreshold);
                                            $createdAt = trim((string)$row['created_at']);
                                            $timeLabel = $createdAt !== '' ? date('M d, h:i A', strtotime($createdAt)) : '-';
                                            $messageText = trim((string)$row['message']);
                                            $mediaPath = trim((string)$row['media_path']);
                                            $preview = $messageText !== '' ? chatlogs_preview($messageText, 180) : ($mediaPath !== '' ? '[Media attachment]' : '-');
                                            $isOwn = ($row['sender_name'] === $nameA);
                                            $senderClass = $isOwn ? 'chatlogs-sender-own' : 'chatlogs-sender-other';
                                            if ($row['is_read'] && trim((string)$row['read_at']) !== '') {
                                                $statusLabel = 'Seen ' . date('h:i A', strtotime((string)$row['read_at']));
                                                $pillClass = 'bg-soft-success text-success';
                                            } elseif ($row['is_read']) {
                                                $statusLabel = 'Seen';
                                                $pillClass = 'bg-soft-success text-success';
                                            } else {
                                                $statusLabel = 'Delivered';
                                                $pillClass = 'bg-soft-warning text-warning';
                                            }
                                        ?>
                                            <div class="chatlogs-thread-row<?php echo $isCollapsedRow ? ' chatlogs-thread-extra d-none' : ''; ?>">
                                                <div class="chatlogs-thread-time"><?php echo chatlogs_esc($timeLabel); ?></div>
                                                <div class="chatlogs-thread-sender"><span class="<?php echo $senderClass; ?>"><?php echo chatlogs_esc($row['sender_name']); ?></span></div>
                                                <div class="chatlogs-thread-main">
                                                    <span class="chatlogs-msg" title="<?php echo chatlogs_esc($messageText); ?>"><?php echo chatlogs_esc($preview); ?></span>
                                                    <div class="chatlogs-thread-submeta">
                                                        <?php if ($mediaPath !== ''): ?>
                                                            <span class="chatlogs-pill bg-soft-info text-info">Media</span>
                                                        <?php endif; ?>
                                                        <span class="chatlogs-pill <?php echo $pillClass; ?>"><?php echo chatlogs_esc($statusLabel); ?></span>
                                                    </div>
                                                </div>
                                                <div class="chatlogs-thread-actions"></div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
            </div>

            <div class="chatlogs-pagination d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="text-muted small">
                    Showing page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?>
                    (<?php echo number_format($totalRecords); ?> total records)
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <form method="get" class="d-flex align-items-center gap-2 mb-0">
                        <input type="hidden" name="from" value="<?php echo chatlogs_esc($dateFrom); ?>">
                        <input type="hidden" name="to" value="<?php echo chatlogs_esc($dateTo); ?>">
                        <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
                        <?php if ($search !== ''): ?>
                            <input type="hidden" name="search" value="<?php echo chatlogs_esc($search); ?>">
                        <?php endif; ?>
                        <label for="pageJump" class="small text-muted mb-0">Go to page</label>
                        <select id="pageJump" name="page" class="form-select form-select-sm chatlogs-page-jump">
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <option value="<?php echo (int)$p; ?>" <?php echo $p === $page ? 'selected' : ''; ?>>
                                    Page <?php echo (int)$p; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </form>
                    <nav aria-label="Chat logs pagination">
                    <ul class="pagination pagination-sm">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo chatlogs_esc($prevUrl); ?>">Prev</a>
                        </li>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo chatlogs_esc($nextUrl); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
                </div>
            </div>

</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>

