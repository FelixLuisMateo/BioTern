<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/notifications.php';

function notifications_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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

$select = 'id, title, message, is_read, created_at';
if (isset($columns['type'])) {
    $select .= ', type';
}
if (isset($columns['action_url'])) {
    $select .= ', action_url';
}

$where = 'user_id = ?';
if (isset($columns['deleted_at'])) {
    $where .= ' AND deleted_at IS NULL';
}

$allItems = [];
$stmt = $conn->prepare('SELECT ' . $select . ' FROM notifications WHERE ' . $where . ' ORDER BY created_at DESC, id DESC');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $resolvedType = biotern_notification_normalize_type(
            (string)($row['type'] ?? ''),
            (string)($row['title'] ?? ''),
            (string)($row['message'] ?? ''),
            (string)($row['action_url'] ?? '')
        );
        $row['_resolved_type'] = $resolvedType;
        $row['_type_meta'] = biotern_notification_type_meta($resolvedType);
        $row['_time_ago'] = biotern_notification_time_ago((string)($row['created_at'] ?? ''));
        $allItems[] = $row;
    }
    $stmt->close();
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
    if ((int)($item['is_read'] ?? 0) === 0) {
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

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = count($filteredItems);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$items = array_slice($filteredItems, ($page - 1) * $perPage, $perPage);
$unreadCount = biotern_notifications_count_unread($conn, $userId);

$page_title = 'BioTern || Notifications';
$page_body_class = 'settings-page notifications-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/settings/settings-shell.css',
    'assets/css/modules/settings/page-notifications.css',
];
include dirname(__DIR__) . '/includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header dashboard-page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Notifications</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Notifications</li>
                </ul>
            </div>
        </div>

        <section class="settings-hub">
            <div class="settings-stack">
                <section class="card settings-panel-card">
                    <div class="card-body pt-4">
                                <div class="notifications-toolbar">
                                    <div class="notifications-toolbar-copy">
                                        <strong>Notification inbox</strong>
                                        <span><?php echo (int)$unreadCount; ?> unread right now across your account.</span>
                                    </div>
                                    <div class="notifications-toolbar-actions">
                                        <form method="post">
                                            <input type="hidden" name="notifications_action" value="mark_all_read">
                                            <button type="submit" class="btn btn-outline-primary btn-sm">Mark All Read</button>
                                        </form>
                                        <form method="post">
                                            <input type="hidden" name="notifications_action" value="clear_all">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Clear All</button>
                                        </form>
                                    </div>
                                </div>

                                <form method="get" class="notifications-filters-card">
                                    <div class="row g-3">
                                        <div class="col-lg-4">
                                            <label class="form-label">Search</label>
                                            <input type="text" name="q" class="form-control" value="<?php echo notifications_h($searchQuery); ?>" placeholder="Search title or message">
                                        </div>
                                        <div class="col-lg-3">
                                            <label class="form-label">Date Range</label>
                                            <select name="range" class="form-select">
                                                <option value="all"<?php echo $rangeFilter === 'all' ? ' selected' : ''; ?>>All time</option>
                                                <option value="today"<?php echo $rangeFilter === 'today' ? ' selected' : ''; ?>>Today</option>
                                                <option value="7d"<?php echo $rangeFilter === '7d' ? ' selected' : ''; ?>>Last 7 days</option>
                                                <option value="30d"<?php echo $rangeFilter === '30d' ? ' selected' : ''; ?>>Last 30 days</option>
                                                <option value="custom"<?php echo $rangeFilter === 'custom' ? ' selected' : ''; ?>>Custom dates</option>
                                            </select>
                                        </div>
                                        <div class="col-lg-2">
                                            <label class="form-label">Start</label>
                                            <input type="date" name="start" class="form-control" value="<?php echo notifications_h($dateStart); ?>">
                                        </div>
                                        <div class="col-lg-2">
                                            <label class="form-label">End</label>
                                            <input type="date" name="end" class="form-control" value="<?php echo notifications_h($dateEnd); ?>">
                                        </div>
                                        <div class="col-lg-1 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary w-100">Apply</button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="status" value="<?php echo notifications_h($statusFilter); ?>">
                                    <input type="hidden" name="category" value="<?php echo notifications_h($categoryFilter); ?>">
                                </form>

                                <div class="notifications-filter-pills">
                                    <?php
                                    $pillMap = [
                                        ['status' => 'all', 'category' => $categoryFilter, 'label' => 'All', 'count' => $filterCounts['all']],
                                        ['status' => 'unread', 'category' => $categoryFilter, 'label' => 'Unread', 'count' => $filterCounts['unread']],
                                        ['status' => $statusFilter, 'category' => 'all', 'label' => 'All Categories', 'count' => null],
                                        ['status' => $statusFilter, 'category' => 'attendance', 'label' => 'Attendance', 'count' => $filterCounts['attendance']],
                                        ['status' => $statusFilter, 'category' => 'account', 'label' => 'Account', 'count' => $filterCounts['account']],
                                        ['status' => $statusFilter, 'category' => 'system', 'label' => 'System', 'count' => $filterCounts['system']],
                                    ];
                                    foreach ($pillMap as $pill):
                                        $isActive = $pill['status'] === $statusFilter && $pill['category'] === $categoryFilter;
                                        $countSuffix = $pill['count'] !== null ? ' (' . (int)$pill['count'] . ')' : '';
                                    ?>
                                        <a class="notifications-filter-pill<?php echo $isActive ? ' is-active' : ''; ?>" href="<?php echo notifications_h($notificationsUrl(['status' => $pill['status'], 'category' => $pill['category'], 'page' => null])); ?>">
                                            <?php echo notifications_h($pill['label'] . $countSuffix); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>

                                <?php if (empty($items)): ?>
                                    <div class="notifications-empty">No notifications matched your current filters.</div>
                                <?php else: ?>
                                    <div class="notifications-list">
                                        <?php foreach ($items as $item): ?>
                                            <?php
                                            $isUnread = (int)($item['is_read'] ?? 0) === 0;
                                            $typeMeta = is_array($item['_type_meta'] ?? null) ? $item['_type_meta'] : biotern_notification_type_meta('system');
                                            $openUrl = biotern_notification_open_url((string)($item['action_url'] ?? ''), (int)($item['id'] ?? 0), 'notifications.php');
                                            ?>
                                            <article class="notification-card<?php echo $isUnread ? ' is-unread' : ''; ?>">
                                                <div class="notification-icon"><i class="<?php echo notifications_h((string)($typeMeta['icon'] ?? 'feather-bell')); ?>"></i></div>
                                                <div>
                                                    <div class="notification-meta">
                                                        <span class="badge <?php echo notifications_h((string)($typeMeta['badge_class'] ?? 'bg-soft-primary text-primary')); ?>">
                                                            <?php echo notifications_h((string)($typeMeta['label'] ?? 'System')); ?>
                                                        </span>
                                                        <?php if ($isUnread): ?>
                                                            <span class="badge bg-soft-danger text-danger">Unread</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <h6 class="notification-title"><?php echo notifications_h((string)($item['title'] ?? 'Notification')); ?></h6>
                                                    <p class="notification-message"><?php echo notifications_h((string)($item['message'] ?? '')); ?></p>
                                                    <div class="notification-footer">
                                                        <span class="notification-time"><?php echo notifications_h((string)($item['_time_ago'] ?? '')); ?></span>
                                                        <?php if (!empty($item['action_url'])): ?>
                                                            <a href="<?php echo notifications_h($openUrl); ?>" class="btn btn-link btn-sm p-0">Open related page</a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="notification-actions">
                                                    <?php if ($isUnread): ?>
                                                        <form method="post">
                                                            <input type="hidden" name="notifications_action" value="mark_read">
                                                            <input type="hidden" name="notification_id" value="<?php echo (int)($item['id'] ?? 0); ?>">
                                                            <button type="submit" class="btn btn-outline-primary btn-sm">Mark Read</button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <form method="post">
                                                        <input type="hidden" name="notifications_action" value="clear_one">
                                                        <input type="hidden" name="notification_id" value="<?php echo (int)($item['id'] ?? 0); ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm">Remove</button>
                                                    </form>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if ($totalPages > 1): ?>
                                        <nav class="mt-4" aria-label="Notifications pagination">
                                            <ul class="pagination mb-0">
                                                <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>">
                                                    <a class="page-link" href="<?php echo notifications_h($notificationsUrl(['page' => max(1, $page - 1)])); ?>">Previous</a>
                                                </li>
                                                <li class="page-item disabled"><span class="page-link">Page <?php echo (int)$page; ?> of <?php echo (int)$totalPages; ?></span></li>
                                                <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>">
                                                    <a class="page-link" href="<?php echo notifications_h($notificationsUrl(['page' => min($totalPages, $page + 1)])); ?>">Next</a>
                                                </li>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                <?php endif; ?>
                    </div>
                </section>
            </div>
        </section>
    </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
