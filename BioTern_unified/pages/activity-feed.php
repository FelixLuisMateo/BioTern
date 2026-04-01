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

$accountUser = null;
$userStmt = $conn->prepare('SELECT name, role, email, is_active FROM users WHERE id = ? LIMIT 1');
if ($userStmt) {
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $accountUser = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();
}

$activityUserName = trim((string)($accountUser['name'] ?? ($_SESSION['name'] ?? 'BioTern User')));
if ($activityUserName === '') {
    $activityUserName = 'BioTern User';
}

$activityUserRole = ucfirst((string)($accountUser['role'] ?? ($_SESSION['role'] ?? 'user')));
$notificationUnreadCount = biotern_notifications_count_unread($conn, $userId);
$activityRoleKey = strtolower((string)($accountUser['role'] ?? ($_SESSION['role'] ?? '')));
$showAssignmentFeed = $activityRoleKey !== 'student';
$isStudentActivity = $activityRoleKey === 'student';
$studentActivityRecord = null;

if ($isStudentActivity) {
    $studentStmt = $conn->prepare(
        "SELECT s.id, s.student_id, s.first_name, s.last_name, s.email
         FROM students s
         WHERE s.user_id = ?
            OR LOWER(TRIM(COALESCE(s.email, ''))) = LOWER(TRIM(?))
            OR LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(TRIM(?))
         ORDER BY CASE WHEN s.user_id = ? THEN 0 ELSE 1 END, s.id DESC
         LIMIT 1"
    );
    if ($studentStmt) {
        $userEmail = (string)($accountUser['email'] ?? '');
        $studentStmt->bind_param('issi', $userId, $userEmail, $activityUserName, $userId);
        $studentStmt->execute();
        $studentActivityRecord = $studentStmt->get_result()->fetch_assoc();
        $studentStmt->close();
    }
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

if ($isStudentActivity && is_array($studentActivityRecord) && !empty($studentActivityRecord['id']) && activity_feed_table_exists($conn, 'attendances')) {
    $studentDbId = (int)$studentActivityRecord['id'];
    $attendanceStmt = $conn->prepare('SELECT attendance_date, status, total_hours, source, updated_at, created_at FROM attendances WHERE student_id = ? ORDER BY attendance_date DESC, id DESC LIMIT 40');
    if ($attendanceStmt) {
        $attendanceStmt->bind_param('i', $studentDbId);
        $attendanceStmt->execute();
        $attendanceResult = $attendanceStmt->get_result();
        while ($row = $attendanceResult->fetch_assoc()) {
            $statusText = ucfirst(trim((string)($row['status'] ?? 'pending')));
            $sourceText = ucfirst(trim((string)($row['source'] ?? 'manual')));
            $hoursText = number_format((float)($row['total_hours'] ?? 0), 2);
            $events[] = [
                'source' => 'Attendance',
                'title' => 'DTR entry recorded',
                'detail' => 'Attendance for ' . date('M d, Y', strtotime((string)($row['attendance_date'] ?? 'now'))) . ' is ' . $statusText . ' with ' . $hoursText . ' rendered hours via ' . $sourceText . '.',
                'created_at' => (string)($row['updated_at'] ?? $row['created_at'] ?? ''),
                'badge_class' => 'bg-soft-success text-success',
                'type_key' => 'attendance',
            ];
        }
        $attendanceStmt->close();
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

if ($isStudentActivity && activity_feed_table_exists($conn, 'storage_activity_logs')) {
    $storageStmt = $conn->prepare('SELECT action_type, title, details, created_at FROM storage_activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 40');
    if ($storageStmt) {
        $storageStmt->bind_param('i', $userId);
        $storageStmt->execute();
        $storageResult = $storageStmt->get_result();
        while ($row = $storageResult->fetch_assoc()) {
            $actionType = strtolower(trim((string)($row['action_type'] ?? 'update')));
            $title = trim((string)($row['title'] ?? 'Document'));
            $verbMap = [
                'upload' => 'Uploaded document',
                'replace' => 'Updated document',
                'update' => 'Edited document details',
                'delete' => 'Moved document to trash',
                'restore' => 'Restored document',
                'toggle_star' => 'Updated starred document',
                'bulk_delete' => 'Bulk document cleanup',
                'bulk_restore' => 'Bulk document restore',
            ];
            $events[] = [
                'source' => 'Documents',
                'title' => $verbMap[$actionType] ?? 'Document activity',
                'detail' => ($title !== '' ? ($title . ' | ') : '') . trim((string)($row['details'] ?? 'Document change recorded')),
                'created_at' => (string)($row['created_at'] ?? ''),
                'badge_class' => 'bg-soft-secondary text-secondary',
                'type_key' => 'documents',
            ];
        }
        $storageStmt->close();
    }
}

if ($isStudentActivity && activity_feed_table_exists($conn, 'audit_logs')) {
    $auditStudentStmt = $conn->prepare('SELECT action, entity_type, entity_id, before_data, after_data, created_at FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 60');
    if ($auditStudentStmt) {
        $auditStudentStmt->bind_param('i', $userId);
        $auditStudentStmt->execute();
        $auditStudentResult = $auditStudentStmt->get_result();
        while ($row = $auditStudentResult->fetch_assoc()) {
            $entityType = strtolower(trim((string)($row['entity_type'] ?? '')));
            if ($entityType !== '' && strpos($entityType, 'student') === false && strpos($entityType, 'user') === false && strpos($entityType, 'profile') === false && strpos($entityType, 'account') === false) {
                continue;
            }

            $before = activity_feed_parse_json((string)($row['before_data'] ?? ''));
            $after = activity_feed_parse_json((string)($row['after_data'] ?? ''));
            $changes = activity_feed_build_change_details($before, $after);
            $detailParts = [];
            foreach (array_slice($changes, 0, 3) as $change) {
                $label = ucwords(str_replace('_', ' ', (string)$change['key']));
                $detailParts[] = $label . ': ' . $change['after'];
            }
            $detail = !empty($detailParts) ? implode(' | ', $detailParts) : 'Your profile or account information was updated.';
            $events[] = [
                'source' => 'Profile',
                'title' => 'Updated profile details',
                'detail' => $detail,
                'created_at' => (string)($row['created_at'] ?? ''),
                'badge_class' => 'bg-soft-warning text-warning',
                'type_key' => 'profile',
            ];
        }
        $auditStudentStmt->close();
    }
}

if (!$isStudentActivity && activity_feed_table_exists($conn, 'audit_logs')) {
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

if (!$isStudentActivity && activity_feed_table_exists($conn, 'ojt_edit_audit')) {
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
$allowedFeeds = $isStudentActivity
    ? ['all', 'profile', 'attendance', 'documents', 'login', 'notification']
    : ['all', 'student', 'login', 'notification'];
if (!$isStudentActivity && $showAssignmentFeed) {
    array_splice($allowedFeeds, 1, 0, ['assignment']);
}
if (!in_array($feed, $allowedFeeds, true)) {
    $feed = 'all';
}

$countAll = count($events);
$countAssignment = 0;
$countStudent = 0;
$countProfile = 0;
$countAttendance = 0;
$countDocuments = 0;
$countLogin = 0;
$countNotification = 0;

foreach ($events as $event) {
    $typeKey = strtolower(trim((string)($event['type_key'] ?? '')));
    if ($typeKey === 'assignment') {
        $countAssignment++;
    } elseif ($typeKey === 'student') {
        $countStudent++;
    } elseif ($typeKey === 'profile') {
        $countProfile++;
    } elseif ($typeKey === 'attendance') {
        $countAttendance++;
    } elseif ($typeKey === 'documents') {
        $countDocuments++;
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

    body.apps-activity-page .account-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 16px;
        border-bottom: 1px solid #e2e8f0;
        background: #ffffff;
    }

    body.apps-activity-page .account-toolbar a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 14px;
        border-radius: 999px;
        border: 1px solid #dbe3ee;
        background: #f8fafc;
        color: #1d4ed8;
        text-decoration: none;
        font-size: 13px;
        font-weight: 700;
    }

    body.apps-activity-page .activity-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        padding: 16px;
        border-bottom: 1px solid #e2e8f0;
        background: #ffffff;
    }

    body.apps-activity-page .activity-summary-card {
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #f8fafc;
        padding: 16px;
    }

    body.apps-activity-page .activity-summary-label {
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        font-weight: 700;
    }

    body.apps-activity-page .activity-summary-value {
        margin-top: 8px;
        font-size: 24px;
        line-height: 1;
        font-weight: 800;
        color: #0f172a;
    }

    body.apps-activity-page .activity-summary-note {
        margin-top: 8px;
        color: #64748b;
        font-size: 12px;
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

    html.app-skin-dark body.apps-activity-page .account-toolbar,
    html.app-skin-dark body.apps-activity-page .activity-summary-grid {
        background: #0f172a;
        border-bottom-color: #1b2436;
    }

    html.app-skin-dark body.apps-activity-page .account-toolbar a {
        border-color: #334155;
        background: #111c31;
        color: #93c5fd;
    }

    html.app-skin-dark body.apps-activity-page .activity-summary-card {
        border-color: #334155;
        background: #111c31;
    }

    html.app-skin-dark body.apps-activity-page .activity-summary-value {
        color: #e6edf8;
    }

    html.app-skin-dark body.apps-activity-page .activity-summary-note,
    html.app-skin-dark body.apps-activity-page .activity-summary-label {
        color: #9fb0cc;
    }

    @media (max-width: 991.98px) {
        body.apps-activity-page .activity-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        body.apps-activity-page .activity-summary-grid {
            grid-template-columns: 1fr;
        }
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
                <?php if ($showAssignmentFeed): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $feed === 'assignment' ? ' active' : ''; ?>" href="activity-feed.php?feed=assignment">
                        <span class="d-flex align-items-center">
                            <i class="feather-users me-3"></i>
                            <span>Assignments</span>
                        </span>
                        <span class="badge bg-soft-success text-success"><?php echo (int)$countAssignment; ?></span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($isStudentActivity): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $feed === 'profile' ? ' active' : ''; ?>" href="activity-feed.php?feed=profile">
                        <span class="d-flex align-items-center">
                            <i class="feather-edit-2 me-3"></i>
                            <span>Profile & Records</span>
                        </span>
                        <span class="badge bg-soft-warning text-warning"><?php echo (int)$countProfile; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $feed === 'attendance' ? ' active' : ''; ?>" href="activity-feed.php?feed=attendance">
                        <span class="d-flex align-items-center">
                            <i class="feather-clock me-3"></i>
                            <span>Attendance</span>
                        </span>
                        <span class="badge bg-soft-success text-success"><?php echo (int)$countAttendance; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $feed === 'documents' ? ' active' : ''; ?>" href="activity-feed.php?feed=documents">
                        <span class="d-flex align-items-center">
                            <i class="feather-file-text me-3"></i>
                            <span>Documents</span>
                        </span>
                        <span class="badge bg-soft-secondary text-secondary"><?php echo (int)$countDocuments; ?></span>
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center justify-content-between<?php echo $feed === 'student' ? ' active' : ''; ?>" href="activity-feed.php?feed=student">
                        <span class="d-flex align-items-center">
                            <i class="feather-edit-2 me-3"></i>
                            <span>Student Info</span>
                        </span>
                        <span class="badge bg-soft-warning text-warning"><?php echo (int)$countStudent; ?></span>
                    </a>
                </li>
                <?php endif; ?>
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
            <div class="account-toolbar">
                <a href="profile-details.php"><i class="feather-user"></i><span>Profile Details</span></a>
                <a href="profile-details.php#account-settings"><i class="feather-settings"></i><span>Account Settings</span></a>
                <a href="activity-feed.php?feed=<?php echo urlencode($feed); ?>"><i class="feather-activity"></i><span>Activity Feed</span></a>
                <a href="notifications.php"><i class="feather-bell"></i><span>Notifications</span></a>
            </div>

            <div class="activity-summary-grid">
                <div class="activity-summary-card">
                    <div class="activity-summary-label">Account Owner</div>
                    <div class="activity-summary-value" style="font-size: 19px; line-height: 1.25;"><?php echo activity_feed_esc($activityUserName); ?></div>
                    <div class="activity-summary-note"><?php echo activity_feed_esc($activityUserRole); ?> workspace activity is collected here.</div>
                </div>
                <div class="activity-summary-card">
                    <div class="activity-summary-label">Visible Events</div>
                    <div class="activity-summary-value"><?php echo (int)count($filteredEvents); ?></div>
                    <div class="activity-summary-note"><?php echo activity_feed_esc($isStudentActivity ? 'Student-friendly ' . strtolower(ucfirst(str_replace('-', ' ', $feed))) . ' view currently loaded.' : ucfirst(str_replace('-', ' ', $feed)) . ' view currently loaded.'); ?></div>
                </div>
                <div class="activity-summary-card">
                    <div class="activity-summary-label">Unread Alerts</div>
                    <div class="activity-summary-value"><?php echo (int)$notificationUnreadCount; ?></div>
                    <div class="activity-summary-note">Notifications needing review in your account center.</div>
                </div>
                <div class="activity-summary-card">
                    <div class="activity-summary-label"><?php echo $isStudentActivity ? 'Attendance Logs' : 'Login Records'; ?></div>
                    <div class="activity-summary-value"><?php echo (int)($isStudentActivity ? $countAttendance : $countLogin); ?></div>
                    <div class="activity-summary-note"><?php echo activity_feed_esc($isStudentActivity ? 'DTR-related events collected from your attendance history.' : 'Recent access history linked to your account.'); ?></div>
                </div>
            </div>

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
