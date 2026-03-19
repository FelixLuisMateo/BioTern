<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/notifications.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login-cover.php');
    exit;
}

biotern_notifications_ensure_table($conn);
$columns = biotern_notification_columns($conn);

$allowedStatuses = ['all', 'unread'];
$allowedCategories = ['all', 'chat', 'assignment', 'attendance', 'account', 'system'];
$allowedRanges = ['all', 'today', '7d', '30d', 'custom'];
$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
$categoryFilter = strtolower(trim((string)($_GET['category'] ?? 'all')));
$rangeFilter = strtolower(trim((string)($_GET['range'] ?? 'all')));
$searchQuery = trim((string)($_GET['q'] ?? ''));
$dateStart = trim((string)($_GET['start'] ?? ''));
$dateEnd = trim((string)($_GET['end'] ?? ''));
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}
if (!in_array($categoryFilter, $allowedCategories, true)) {
    $categoryFilter = 'all';
}
if (!in_array($rangeFilter, $allowedRanges, true)) {
    $rangeFilter = 'all';
}

if ($rangeFilter !== 'custom') {
    $dateStart = '';
    $dateEnd = '';
}

$validDateFormat = '/^\d{4}-\d{2}-\d{2}$/';
if ($dateStart !== '' && preg_match($validDateFormat, $dateStart) !== 1) {
    $dateStart = '';
}
if ($dateEnd !== '' && preg_match($validDateFormat, $dateEnd) !== 1) {
    $dateEnd = '';
}

$rangeStartTs = null;
$rangeEndTs = null;
if ($rangeFilter === 'today') {
    $rangeStartTs = strtotime(date('Y-m-d 00:00:00'));
    $rangeEndTs = strtotime(date('Y-m-d 23:59:59'));
} elseif ($rangeFilter === '7d') {
    $rangeStartTs = strtotime('-6 days 00:00:00');
    $rangeEndTs = strtotime(date('Y-m-d 23:59:59'));
} elseif ($rangeFilter === '30d') {
    $rangeStartTs = strtotime('-29 days 00:00:00');
    $rangeEndTs = strtotime(date('Y-m-d 23:59:59'));
} elseif ($rangeFilter === 'custom' && $dateStart !== '' && $dateEnd !== '') {
    $rangeStartTs = strtotime($dateStart . ' 00:00:00');
    $rangeEndTs = strtotime($dateEnd . ' 23:59:59');
    if ($rangeStartTs === false || $rangeEndTs === false || $rangeStartTs > $rangeEndTs) {
        $rangeFilter = 'all';
        $dateStart = '';
        $dateEnd = '';
        $rangeStartTs = null;
        $rangeEndTs = null;
    }
}

$baseFilterParams = [
    'status' => $statusFilter,
    'category' => $categoryFilter,
    'range' => $rangeFilter,
];
if ($searchQuery !== '') {
    $baseFilterParams['q'] = $searchQuery;
}
if ($rangeFilter === 'custom' && $dateStart !== '' && $dateEnd !== '') {
    $baseFilterParams['start'] = $dateStart;
    $baseFilterParams['end'] = $dateEnd;
}

$notificationsUrl = static function (array $overrides = []) use ($baseFilterParams): string {
    $params = array_merge($baseFilterParams, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    $query = http_build_query($params);
    return 'notifications.php' . ($query !== '' ? ('?' . $query) : '');
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = strtolower(trim((string)($_POST['notifications_action'] ?? '')));
    $notificationId = (int)($_POST['notification_id'] ?? 0);

    if ($action === 'mark_read' && $notificationId > 0) {
        biotern_notifications_mark_read($conn, $userId, $notificationId);
    } elseif ($action === 'mark_all_read') {
        biotern_notifications_mark_all_read($conn, $userId);
    } elseif ($action === 'clear_one' && $notificationId > 0) {
        biotern_notifications_clear($conn, $userId, $notificationId);
    } elseif ($action === 'clear_all') {
        biotern_notifications_clear_all($conn, $userId);
    }

    header('Location: ' . $notificationsUrl(['page' => null]));
    exit;
}

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$where = 'user_id = ?';
if (isset($columns['deleted_at'])) {
    $where .= ' AND deleted_at IS NULL';
}

$select = 'id, title, message, is_read, created_at';
if (isset($columns['type'])) {
    $select .= ', type';
}
if (isset($columns['action_url'])) {
    $select .= ', action_url';
}

$listSql = 'SELECT ' . $select . ' FROM notifications WHERE ' . $where . ' ORDER BY created_at DESC, id DESC';
$allItems = [];
$listStmt = $conn->prepare($listSql);
if ($listStmt) {
    $listStmt->bind_param('i', $userId);
    $listStmt->execute();
    $res = $listStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rawType = (string)($row['type'] ?? '');
        $resolvedType = biotern_notification_normalize_type(
            $rawType,
            (string)($row['title'] ?? ''),
            (string)($row['message'] ?? ''),
            (string)($row['action_url'] ?? '')
        );
        $row['_resolved_type'] = $resolvedType;
        $row['_type_meta'] = biotern_notification_type_meta($resolvedType);
        $row['_time_ago'] = biotern_notification_time_ago((string)($row['created_at'] ?? ''));
        $allItems[] = $row;
    }
    $listStmt->close();
}

$baseScopedItems = array_values(array_filter($allItems, static function (array $item) use ($searchQuery, $rangeStartTs, $rangeEndTs): bool {
    if ($searchQuery !== '') {
        $needle = function_exists('mb_strtolower') ? mb_strtolower($searchQuery, 'UTF-8') : strtolower($searchQuery);
        $haystack = (string)($item['title'] ?? '') . ' ' . (string)($item['message'] ?? '');
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
        if (strpos($haystack, $needle) === false) {
            return false;
        }
    }

    if ($rangeStartTs !== null && $rangeEndTs !== null) {
        $itemTs = strtotime((string)($item['created_at'] ?? ''));
        if ($itemTs === false || $itemTs < $rangeStartTs || $itemTs > $rangeEndTs) {
            return false;
        }
    }

    return true;
}));

$filterCounts = [
    'all' => count($baseScopedItems),
    'unread' => 0,
    'chat' => 0,
    'assignment' => 0,
    'attendance' => 0,
    'account' => 0,
    'system' => 0,
];

foreach ($baseScopedItems as $item) {
    $isUnread = (int)($item['is_read'] ?? 0) === 0;
    if ($isUnread) {
        $filterCounts['unread']++;
    }
    $resolvedType = (string)($item['_resolved_type'] ?? 'system');
    if (isset($filterCounts[$resolvedType])) {
        $filterCounts[$resolvedType]++;
    }
}

$filteredItems = array_values(array_filter($baseScopedItems, static function (array $item) use ($statusFilter, $categoryFilter): bool {
    $isUnread = (int)($item['is_read'] ?? 0) === 0;
    if ($statusFilter === 'unread' && !$isUnread) {
        return false;
    }
    if ($categoryFilter !== 'all' && (string)($item['_resolved_type'] ?? 'system') !== $categoryFilter) {
        return false;
    }
    return true;
}));

$total = count($filteredItems);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$items = array_slice($filteredItems, $offset, $perPage);

$unreadCount = biotern_notifications_count_unread($conn, $userId);

$page_title = 'BioTern || Notifications';
include 'includes/header.php';
?>
<style>
    body.apps-account-page .main-content {
        padding-top: 0 !important;
    }

    body.apps-account-page .content-sidebar,
    body.apps-account-page .content-area {
        border-color: #e2e8f0 !important;
    }

    body.apps-account-page .content-sidebar-header,
    body.apps-account-page .content-area-header {
        background: #ffffff !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }

    body.apps-account-page .nxl-content-sidebar-item .nav-link {
        border-radius: 8px;
        margin: 0 0.35rem;
        color: #1f2937;
        font-weight: 600;
    }

    body.apps-account-page .nxl-content-sidebar-item .nav-link.active {
        background: #eef2ff;
        color: #1d4ed8;
    }

    html.app-skin-dark body.apps-account-page .content-sidebar,
    html.app-skin-dark body.apps-account-page .content-area {
        border-color: #1b2436 !important;
    }

    html.app-skin-dark body.apps-account-page .content-sidebar-header,
    html.app-skin-dark body.apps-account-page .content-area-header {
        background: #0f172a !important;
        border-bottom-color: #1b2436 !important;
    }

    html.app-skin-dark body.apps-account-page .nxl-content-sidebar-item .nav-link {
        color: #dbe5f5 !important;
    }

    html.app-skin-dark body.apps-account-page .nxl-content-sidebar-item .nav-link.active {
        background: #1c2740 !important;
        color: #8fb4ff !important;
    }

    .notifications-filter-bar {
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 4px;
    }

    .notifications-filter-pill {
        border: 1px solid #dbe3ee;
        background: #ffffff;
        color: #0f172a;
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .notifications-filter-pill.active {
        border-color: #1d4ed8;
        color: #1d4ed8;
        background: #eef2ff;
    }

    .notifications-type-pill {
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 8px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .notifications-time {
        color: #64748b;
        font-size: 12px;
    }

    .notifications-advanced-filters {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px;
        background: #f8fafc;
        margin-bottom: 14px;
    }

    html.app-skin-dark .notifications-advanced-filters {
        border-color: #334155;
        background: #0f172a;
    }

    html.app-skin-dark .notifications-filter-pill {
        border-color: #334155;
        background: #0f172a;
        color: #dbe5f5;
    }

    html.app-skin-dark .notifications-filter-pill.active {
        border-color: #8fb4ff;
        color: #8fb4ff;
        background: #1c2740;
    }

    html.app-skin-dark .notifications-time {
        color: #94a3b8;
    }
</style>

<script>
    document.body.classList.add('apps-account-page');
</script>

<div class="main-content d-flex">
    <div class="content-area w-100" data-scrollbar-target="#psScrollbarInit">
        <div class="content-area-header bg-white sticky-top">
            <div class="page-header-left d-flex align-items-center gap-2">
                <div>
                    <h5 class="mb-0">All Notifications</h5>
                    <div class="text-muted small"><?php echo (int)$unreadCount; ?> unread</div>
                </div>
            </div>
            <div class="page-header-right ms-auto">
                <div class="d-flex gap-2">
                    <form method="post" class="d-inline">
                        <input type="hidden" name="notifications_action" value="mark_all_read">
                        <button type="submit" class="btn btn-outline-primary btn-sm">Mark All Read</button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Clear all notifications?');">
                        <input type="hidden" name="notifications_action" value="clear_all">
                        <button type="submit" class="btn btn-outline-danger btn-sm">Clear All</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="content-area-body p-3">
            <div class="card">
                <div class="card-body">
                    <form method="get" class="notifications-advanced-filters">
                        <div class="row g-2 align-items-end">
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label form-label-sm mb-1">Search</label>
                                <input type="text" name="q" class="form-control form-control-sm" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search title or message">
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label form-label-sm mb-1">Date Range</label>
                                <select name="range" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="all"<?php echo $rangeFilter === 'all' ? ' selected' : ''; ?>>All time</option>
                                    <option value="today"<?php echo $rangeFilter === 'today' ? ' selected' : ''; ?>>Today</option>
                                    <option value="7d"<?php echo $rangeFilter === '7d' ? ' selected' : ''; ?>>Last 7 days</option>
                                    <option value="30d"<?php echo $rangeFilter === '30d' ? ' selected' : ''; ?>>Last 30 days</option>
                                    <option value="custom"<?php echo $rangeFilter === 'custom' ? ' selected' : ''; ?>>Custom dates</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label form-label-sm mb-1">Start</label>
                                <input type="date" name="start" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateStart, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label form-label-sm mb-1">End</label>
                                <input type="date" name="end" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dateEnd, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="col-lg-1 col-md-12">
                                <button type="submit" class="btn btn-sm btn-primary w-100">Apply</button>
                            </div>
                        </div>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryFilter, ENT_QUOTES, 'UTF-8'); ?>">
                    </form>

                    <div class="d-flex flex-wrap align-items-center notifications-filter-bar mb-3">
                        <?php
                        $statusAllClass = $statusFilter === 'all' ? ' active' : '';
                        $statusUnreadClass = $statusFilter === 'unread' ? ' active' : '';
                        $categoryAllClass = $categoryFilter === 'all' ? ' active' : '';
                        $categoryChatClass = $categoryFilter === 'chat' ? ' active' : '';
                        $categoryAssignmentClass = $categoryFilter === 'assignment' ? ' active' : '';
                        $categoryAttendanceClass = $categoryFilter === 'attendance' ? ' active' : '';
                        $categoryAccountClass = $categoryFilter === 'account' ? ' active' : '';
                        $categorySystemClass = $categoryFilter === 'system' ? ' active' : '';
                        ?>
                        <a class="notifications-filter-pill<?php echo $statusAllClass; ?>" href="<?php echo htmlspecialchars($notificationsUrl(['status' => 'all', 'page' => null]), ENT_QUOTES, 'UTF-8'); ?>">All (<?php echo (int)($filterCounts['all'] ?? 0); ?>)</a>
                        <a class="notifications-filter-pill<?php echo $statusUnreadClass; ?>" href="<?php echo htmlspecialchars($notificationsUrl(['status' => 'unread', 'page' => null]), ENT_QUOTES, 'UTF-8'); ?>">Unread (<?php echo (int)($filterCounts['unread'] ?? 0); ?>)</a>
                        <a class="notifications-filter-pill<?php echo $categoryAllClass; ?>" href="<?php echo htmlspecialchars($notificationsUrl(['category' => 'all', 'page' => null]), ENT_QUOTES, 'UTF-8'); ?>">All Categories</a>
                        <a class="notifications-filter-pill<?php echo $categoryChatClass; ?>" href="<?php echo htmlspecialchars($notificationsUrl(['category' => 'chat', 'page' => null]), ENT_QUOTES, 'UTF-8'); ?>">Chat (<?php echo (int)($filterCounts['chat'] ?? 0); ?>)</a>
                        <a class="notifications-filter-pill<?php echo $categoryAssignmentClass; ?>" href="<?php echo htmlspecialchars($notificationsUrl(['category' => 'assignment', 'page' => null]), ENT_QUOTES, 'UTF-8'); ?>">Assignment (<?php echo (int)($filterCounts['assignment'] ?? 0); ?>)</a>
                        <a class="notifications-filter-pill<?php echo $categoryAttendanceClass; ?>" href="<?php echo htmlspecialchars($notificationsUrl(['category' => 'attendance', 'page' => null]), ENT_QUOTES, 'UTF-8'); ?>">Attendance (<?php echo (int)($filterCounts['attendance'] ?? 0); ?>)</a>
                        <a class="notifications-filter-pill<?php echo $categoryAccountClass; ?>" href="<?php echo htmlspecialchars($notificationsUrl(['category' => 'account', 'page' => null]), ENT_QUOTES, 'UTF-8'); ?>">Account (<?php echo (int)($filterCounts['account'] ?? 0); ?>)</a>
                        <a class="notifications-filter-pill<?php echo $categorySystemClass; ?>" href="<?php echo htmlspecialchars($notificationsUrl(['category' => 'system', 'page' => null]), ENT_QUOTES, 'UTF-8'); ?>">System (<?php echo (int)($filterCounts['system'] ?? 0); ?>)</a>
                    </div>

                    <?php if (empty($items)): ?>
                        <div class="text-muted">No notifications found.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($items as $item): ?>
                                <?php
                                $isUnread = (int)($item['is_read'] ?? 0) === 0;
                                $typeMeta = is_array($item['_type_meta'] ?? null) ? $item['_type_meta'] : biotern_notification_type_meta('system');
                                $openUrl = biotern_notification_open_url((string)($item['action_url'] ?? ''), (int)($item['id'] ?? 0), 'notifications.php');
                                ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="me-2">
                                            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                                <span class="badge notifications-type-pill <?php echo htmlspecialchars((string)($typeMeta['badge_class'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="<?php echo htmlspecialchars((string)($typeMeta['icon'] ?? 'feather-bell'), ENT_QUOTES, 'UTF-8'); ?>"></i>
                                                    <?php echo htmlspecialchars((string)($typeMeta['label'] ?? 'System'), ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <?php if ($isUnread): ?><span class="badge bg-danger">Unread</span><?php endif; ?>
                                            </div>
                                            <div class="fw-semibold">
                                                <?php echo htmlspecialchars((string)($item['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <div class="small text-muted mt-1"><?php echo htmlspecialchars((string)($item['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php if (!empty($item['action_url'])): ?>
                                                <a class="small" href="<?php echo htmlspecialchars($openUrl, ENT_QUOTES, 'UTF-8'); ?>">Open related page</a>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <small class="notifications-time d-block mb-2" title="<?php echo htmlspecialchars((string)($item['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)($item['_time_ago'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                            <div class="d-flex gap-1 justify-content-end">
                                                <?php if ($isUnread): ?>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="notifications_action" value="mark_read">
                                                        <input type="hidden" name="notification_id" value="<?php echo (int)($item['id'] ?? 0); ?>">
                                                        <button type="submit" class="btn btn-outline-primary btn-sm">Mark Read</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Remove this notification?');">
                                                    <input type="hidden" name="notifications_action" value="clear_one">
                                                    <input type="hidden" name="notification_id" value="<?php echo (int)($item['id'] ?? 0); ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-3" aria-label="Notifications pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars($notificationsUrl(['page' => max(1, $page - 1)]), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                                    </li>
                                    <li class="page-item disabled"><span class="page-link">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span></li>
                                    <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars($notificationsUrl(['page' => min($totalPages, $page + 1)]), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
