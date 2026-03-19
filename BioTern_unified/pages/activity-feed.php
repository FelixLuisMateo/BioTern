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

function activity_feed_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function activity_feed_timestamp(string $value): int
{
    $ts = strtotime($value);
    return $ts === false ? 0 : $ts;
}

function activity_feed_parse_json(?string $raw): array
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function activity_feed_preview(string $value, int $limit = 70): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    if ($value === '') {
        return '-';
    }
    if (strlen($value) <= $limit) {
        return $value;
    }
    return substr($value, 0, $limit - 3) . '...';
}

function activity_feed_format_time(string $raw): string
{
    $ts = strtotime($raw);
    if ($ts === false) {
        return $raw;
    }
    return date('M d, Y h:i A', $ts);
}

function activity_feed_is_assignment_change(array $keys, string $text): bool
{
    $haystack = strtolower($text . ' ' . implode(' ', $keys));
    return (strpos($haystack, 'assign') !== false)
        || (strpos($haystack, 'supervisor') !== false)
        || (strpos($haystack, 'coordinator') !== false)
        || (strpos($haystack, 'internship') !== false)
        || (strpos($haystack, 'track') !== false);
}

function activity_feed_build_change_details(array $before, array $after): array
{
    $keys = array_unique(array_merge(array_keys($before), array_keys($after)));
    $changes = [];

    foreach ($keys as $key) {
        $beforeVal = array_key_exists($key, $before) ? (string)$before[$key] : '';
        $afterVal = array_key_exists($key, $after) ? (string)$after[$key] : '';
        if ($beforeVal === $afterVal) {
            continue;
        }
        $changes[] = [
            'key' => (string)$key,
            'before' => activity_feed_preview($beforeVal),
            'after' => activity_feed_preview($afterVal),
        ];
    }

    return $changes;
}

$events = [];

if (activity_feed_table_exists($conn, 'login_logs')) {
    $stmt = $conn->prepare('SELECT status, reason, ip_address, created_at FROM login_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 40');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $status = strtolower(trim((string)($row['status'] ?? '')));
            $reason = trim((string)($row['reason'] ?? ''));
            $title = $status === 'success' ? 'Logged in successfully' : 'Login attempt failed';
            $detail = $reason !== '' ? ('Reason: ' . $reason) : 'Login event recorded';
            if (!empty($row['ip_address'])) {
                $detail .= ' | IP: ' . (string)$row['ip_address'];
            }
            $events[] = [
                'source' => 'Login',
                'title' => $title,
                'detail' => $detail,
                'created_at' => (string)($row['created_at'] ?? ''),
                'badge_class' => 'bg-soft-primary text-primary',
                'type_key' => 'login',
            ];
        }
        $stmt->close();
    }
}

$notifications = biotern_notifications_fetch($conn, $userId, 40);
foreach ($notifications as $item) {
    $events[] = [
        'source' => 'Notification',
        'title' => (string)($item['title'] ?? 'Notification'),
        'detail' => (string)($item['message'] ?? ''),
        'created_at' => (string)($item['created_at'] ?? ''),
        'badge_class' => 'bg-soft-info text-info',
        'type_key' => 'notification',
    ];
}

if (activity_feed_table_exists($conn, 'audit_logs')) {
    $stmt = $conn->prepare('SELECT action, entity_type, entity_id, before_data, after_data, created_at FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 60');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $action = trim((string)($row['action'] ?? 'updated'));
            $entityType = trim((string)($row['entity_type'] ?? 'record'));
            $entityId = (int)($row['entity_id'] ?? 0);

            $before = activity_feed_parse_json((string)($row['before_data'] ?? ''));
            $after = activity_feed_parse_json((string)($row['after_data'] ?? ''));
            $changes = activity_feed_build_change_details($before, $after);

            $title = 'System activity';
            $detail = ucfirst($action) . ' ' . $entityType;

            if (!empty($changes)) {
                $firstChanges = array_slice($changes, 0, 3);
                $parts = [];
                foreach ($firstChanges as $change) {
                    $parts[] = $change['key'] . ': ' . $change['before'] . ' -> ' . $change['after'];
                }
                $detail = implode(' | ', $parts);

                $keys = array_map(static function (array $item): string {
                    return (string)$item['key'];
                }, $changes);

                if (activity_feed_is_assignment_change($keys, $entityType . ' ' . $action)) {
                    $title = 'Updated assignment details';
                } elseif (stripos($entityType, 'student') !== false) {
                    $title = 'Updated student information';
                }
            }

            if ($entityId > 0) {
                $detail .= ' | Record #' . $entityId;
            }

            $events[] = [
                'source' => 'Audit',
                'title' => $title,
                'detail' => $detail,
                'created_at' => (string)($row['created_at'] ?? ''),
                'badge_class' => 'bg-soft-warning text-warning',
                'type_key' => activity_feed_is_assignment_change([], $title) ? 'assignment' : ((stripos($title, 'student') !== false) ? 'student' : 'all'),
            ];
        }
        $stmt->close();
    }
}

if (activity_feed_table_exists($conn, 'ojt_edit_audit')) {
    $stmt = $conn->prepare('SELECT student_id, reason, changes_text, created_at FROM ojt_edit_audit WHERE editor_user_id = ? ORDER BY created_at DESC LIMIT 80');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $changesText = trim((string)($row['changes_text'] ?? ''));
            $reason = trim((string)($row['reason'] ?? ''));
            $studentId = (int)($row['student_id'] ?? 0);

            $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $changesText ?: ''))));
            $isAssignment = activity_feed_is_assignment_change($lines, $changesText . ' ' . $reason);

            $title = $isAssignment ? 'Updated assignment details' : 'Updated student information';
            $detail = $reason !== '' ? ('Reason: ' . $reason) : 'Manual update submitted';
            if (!empty($lines)) {
                $detail .= ' | ' . implode(' | ', array_slice($lines, 0, 3));
            }
            if ($studentId > 0) {
                $detail .= ' | Student #' . $studentId;
            }

            $events[] = [
                'source' => 'Edit Log',
                'title' => $title,
                'detail' => $detail,
                'created_at' => (string)($row['created_at'] ?? ''),
                'badge_class' => 'bg-soft-success text-success',
                'type_key' => $isAssignment ? 'assignment' : 'student',
            ];
        }
        $stmt->close();
    }
}

usort($events, static function (array $a, array $b): int {
    return activity_feed_timestamp((string)($b['created_at'] ?? '')) <=> activity_feed_timestamp((string)($a['created_at'] ?? ''));
});

$events = array_slice($events, 0, 180);

$uidCounter = 0;
foreach ($events as &$evt) {
    $uidCounter++;
    $evt['uid'] = $uidCounter;
}
unset($evt);

$feed = strtolower(trim((string)($_GET['feed'] ?? 'all')));
$allowedFeeds = ['all', 'assignment', 'student', 'login', 'notification'];
if (!in_array($feed, $allowedFeeds, true)) {
    $feed = 'all';
}

$countAll = count($events);
$countAssignment = 0;
$countStudent = 0;
$countLogin = 0;
$countNotification = 0;

foreach ($events as $event) {
    $typeKey = strtolower(trim((string)($event['type_key'] ?? '')));
    if ($typeKey === 'assignment') {
        $countAssignment++;
    } elseif ($typeKey === 'student') {
        $countStudent++;
    } elseif ($typeKey === 'login') {
        $countLogin++;
    } elseif ($typeKey === 'notification') {
        $countNotification++;
    }
}

$filteredEvents = array_values(array_filter($events, static function (array $event) use ($feed): bool {
    if ($feed === 'all') {
        return true;
    }
    return strtolower(trim((string)($event['type_key'] ?? ''))) === $feed;
}));

$viewId = (int)($_GET['view'] ?? 0);
$selected = null;
foreach ($filteredEvents as $event) {
    if ((int)($event['uid'] ?? 0) === $viewId) {
        $selected = $event;
        break;
    }
}
if ($selected === null && !empty($filteredEvents)) {
    $selected = $filteredEvents[0];
}

function activity_feed_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function activity_feed_title_preview(string $text, int $max = 72): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    if ($text === '') {
        return '-';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 3) . '...' : $text;
    }
    return strlen($text) > $max ? substr($text, 0, $max - 3) . '...' : $text;
}

function activity_feed_detail_preview(string $text, int $max = 110): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    if ($text === '') {
        return '[No detail]';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 3) . '...' : $text;
    }
    return strlen($text) > $max ? substr($text, 0, $max - 3) . '...' : $text;
}

$page_title = 'BioTern || Activity Feed';
include 'includes/header.php';
?>
<style>
    body.apps-activity-page .main-content {
        padding-top: 0 !important;
    }

    body.apps-activity-page .content-sidebar,
    body.apps-activity-page .content-area {
        border-color: #e2e8f0 !important;
    }

    body.apps-activity-page .content-sidebar-header,
    body.apps-activity-page .content-area-header {
        background: #ffffff !important;
        border-bottom: 1px solid #e2e8f0 !important;
    }

    body.apps-activity-page .nxl-content-sidebar-item .nav-link {
        border-radius: 8px;
        margin: 0 0.35rem;
        color: #1f2937;
        font-weight: 600;
    }

    body.apps-activity-page .nxl-content-sidebar-item .nav-link.active {
        background: #eef2ff;
        color: #1d4ed8;
    }

    body.apps-activity-page .activity-list-pane,
    body.apps-activity-page .activity-detail-pane {
        max-height: calc(100vh - 240px);
        overflow-y: auto;
    }

    body.apps-activity-page .activity-list-pane {
        border-right: 1px solid #e2e8f0;
        background: #ffffff;
    }

    body.apps-activity-page .activity-detail-pane {
        background: #ffffff;
    }

    body.apps-activity-page .activity-row {
        border-bottom: 1px solid #eef2f7;
        transition: background-color 0.16s ease;
    }

    body.apps-activity-page .activity-row:hover {
        background: #f8fafc;
    }

    body.apps-activity-page .activity-row.activity-row-active {
        background: #eff6ff;
    }

    body.apps-activity-page .activity-title {
        color: #0f172a;
    }

    body.apps-activity-page .activity-meta {
        color: #475569;
    }

    body.apps-activity-page .activity-body {
        color: #111827;
        white-space: pre-wrap;
        line-height: 1.5;
    }

    html.app-skin-dark body.apps-activity-page .content-sidebar,
    html.app-skin-dark body.apps-activity-page .content-area {
        border-color: #1b2436 !important;
    }

    html.app-skin-dark body.apps-activity-page .content-sidebar-header,
    html.app-skin-dark body.apps-activity-page .content-area-header {
        background: #0f172a !important;
        border-bottom-color: #1b2436 !important;
    }

    html.app-skin-dark body.apps-activity-page .nxl-content-sidebar-item .nav-link {
        color: #dbe5f5 !important;
    }

    html.app-skin-dark body.apps-activity-page .nxl-content-sidebar-item .nav-link.active {
        background: #1c2740 !important;
        color: #8fb4ff !important;
    }

    html.app-skin-dark body.apps-activity-page .activity-list-pane {
        border-right-color: #1b2436 !important;
        background: #0f172a;
    }

    html.app-skin-dark body.apps-activity-page .activity-detail-pane {
        background: #0f172a;
    }

    html.app-skin-dark body.apps-activity-page .activity-row {
        border-bottom-color: #1b2436;
    }

    html.app-skin-dark body.apps-activity-page .activity-row:hover {
        background: #162238;
    }

    html.app-skin-dark body.apps-activity-page .activity-row.activity-row-active {
        background: #1b2a46;
    }

    html.app-skin-dark body.apps-activity-page .activity-title,
    html.app-skin-dark body.apps-activity-page .activity-body {
        color: #e6edf8 !important;
    }

    html.app-skin-dark body.apps-activity-page .activity-meta,
    html.app-skin-dark body.apps-activity-page .text-muted {
        color: #9fb0cc !important;
    }
</style>

<script>
    document.body.classList.add('apps-activity-page');
</script>

<div class="main-content d-flex">
    <div class="content-sidebar content-sidebar-md" data-scrollbar-target="#psScrollbarInit">
        <div class="content-sidebar-header bg-white sticky-top hstack justify-content-between">
            <h4 class="fw-bolder mb-0">Activity</h4>
            <a href="javascript:void(0);" class="app-sidebar-close-trigger d-flex">
                <i class="feather-x"></i>
            </a>
        </div>

        <div class="content-sidebar-header">
            <a href="profile-details.php" class="btn btn-primary w-100">
                <i class="feather-user me-2"></i>
                <span>Profile Details</span>
            </a>
        </div>

        <div class="content-sidebar-body">
            <ul class="nav flex-column nxl-content-sidebar-item">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $feed === 'all' ? ' active' : ''; ?>" href="activity-feed.php?feed=all">
                        <span class="d-flex align-items-center">
                            <i class="feather-activity me-3"></i>
                            <span>All Activity</span>
                        </span>
                        <span class="badge bg-soft-primary text-primary"><?php echo (int)$countAll; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $feed === 'assignment' ? ' active' : ''; ?>" href="activity-feed.php?feed=assignment">
                        <span class="d-flex align-items-center">
                            <i class="feather-users me-3"></i>
                            <span>Assignments</span>
                        </span>
                        <span class="badge bg-soft-success text-success"><?php echo (int)$countAssignment; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $feed === 'student' ? ' active' : ''; ?>" href="activity-feed.php?feed=student">
                        <span class="d-flex align-items-center">
                            <i class="feather-edit-2 me-3"></i>
                            <span>Student Info</span>
                        </span>
                        <span class="badge bg-soft-warning text-warning"><?php echo (int)$countStudent; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $feed === 'login' ? ' active' : ''; ?>" href="activity-feed.php?feed=login">
                        <span class="d-flex align-items-center">
                            <i class="feather-log-in me-3"></i>
                            <span>Login</span>
                        </span>
                        <span class="badge bg-soft-info text-info"><?php echo (int)$countLogin; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $feed === 'notification' ? ' active' : ''; ?>" href="activity-feed.php?feed=notification">
                        <span class="d-flex align-items-center">
                            <i class="feather-bell me-3"></i>
                            <span>Notifications</span>
                        </span>
                        <span class="badge bg-soft-danger text-danger"><?php echo (int)$countNotification; ?></span>
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
                <h5 class="mb-0 text-capitalize"><?php echo activity_feed_esc(str_replace('-', ' ', $feed)); ?></h5>
            </div>
            <div class="page-header-right ms-auto">
                <span class="text-muted small"><?php echo (int)count($filteredEvents); ?> item(s)</span>
            </div>
        </div>

        <div class="content-area-body p-0">
            <div class="row g-0">
                <div class="col-lg-5 activity-list-pane">
                    <?php if (empty($filteredEvents)): ?>
                        <div class="p-4 text-muted text-center">No activity records in this view.</div>
                    <?php else: ?>
                        <?php foreach ($filteredEvents as $item): ?>
                            <?php $isActive = $selected !== null && (int)$selected['uid'] === (int)$item['uid']; ?>
                            <a href="activity-feed.php?feed=<?php echo activity_feed_esc($feed); ?>&view=<?php echo (int)$item['uid']; ?>" class="text-decoration-none text-reset d-block activity-row <?php echo $isActive ? 'activity-row-active' : ''; ?>">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <strong class="text-truncate activity-title"><?php echo activity_feed_esc(activity_feed_title_preview((string)($item['title'] ?? 'Activity'))); ?></strong>
                                        <small class="activity-meta ms-2"><?php echo activity_feed_esc(activity_feed_format_time((string)($item['created_at'] ?? ''))); ?></small>
                                    </div>
                                    <div class="activity-meta text-truncate"><?php echo activity_feed_esc(activity_feed_detail_preview((string)($item['detail'] ?? ''))); ?></div>
                                    <span class="badge <?php echo activity_feed_esc((string)($item['badge_class'] ?? 'bg-soft-primary text-primary')); ?> mt-2"><?php echo activity_feed_esc((string)($item['source'] ?? 'Activity')); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="col-lg-7 activity-detail-pane">
                    <?php if ($selected === null): ?>
                        <div class="p-4 text-center text-muted">Select an activity to view details.</div>
                    <?php else: ?>
                        <div class="p-4">
                            <h5 class="mb-3 activity-title"><?php echo activity_feed_esc((string)($selected['title'] ?? 'Activity')); ?></h5>
                            <div class="small activity-meta mb-3">
                                <div><strong>Source:</strong> <?php echo activity_feed_esc((string)($selected['source'] ?? '-')); ?></div>
                                <div><strong>Date:</strong> <?php echo activity_feed_esc(activity_feed_format_time((string)($selected['created_at'] ?? ''))); ?></div>
                            </div>
                            <hr>
                            <div class="mb-0 activity-body"><?php echo nl2br(activity_feed_esc((string)($selected['detail'] ?? ''))); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
