<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/notifications.php';

biotern_boot_session(isset($conn) ? $conn : null);

if (!function_exists('biotern_notifications_feed_json')) {
    function biotern_notifications_feed_json(int $status, array $payload): void
    {
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    biotern_notifications_feed_json(401, [
        'ok' => false,
        'message' => 'Authentication required.',
    ]);
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    biotern_notifications_feed_json(500, [
        'ok' => false,
        'message' => 'Database connection unavailable.',
    ]);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    biotern_notifications_feed_json(405, [
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
}

biotern_notifications_ensure_table($conn);
$columns = biotern_notification_columns($conn);
$safeLimit = max(1, min(10, (int)($_GET['limit'] ?? 6)));

$items = [];
$latestId = 0;
$readCount = 0;
$hasTitle = isset($columns['title']);
$hasMessage = isset($columns['message']);
$hasType = isset($columns['type']);
$hasData = isset($columns['data']);
$hasActionUrl = isset($columns['action_url']);
$hasDeletedAt = isset($columns['deleted_at']);

if ($hasTitle && $hasMessage) {
    $sql = 'SELECT id, title, message, is_read, created_at';
    if ($hasType) {
        $sql .= ', type';
    }
    if ($hasActionUrl) {
        $sql .= ', action_url';
    }
    $sql .= ' FROM notifications WHERE user_id = ?';
    if ($hasDeletedAt) {
        $sql .= ' AND deleted_at IS NULL';
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $safeLimit;

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            $title = (string)($row['title'] ?? 'Notification');
            $message = (string)($row['message'] ?? '');
            $type = biotern_notification_normalize_type(
                (string)($row['type'] ?? ''),
                $title,
                $message,
                (string)($row['action_url'] ?? '')
            );
            $meta = biotern_notification_type_meta($type);
            $createdAt = (string)($row['created_at'] ?? '');
            $isRead = (int)($row['is_read'] ?? 0);

            if ($id > $latestId) {
                $latestId = $id;
            }
            if ($isRead === 1) {
                $readCount++;
            }

            $items[] = [
                'id' => $id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'type_label' => (string)($meta['label'] ?? 'System'),
                'icon' => (string)($meta['icon'] ?? 'feather-bell'),
                'action_url' => (string)($row['action_url'] ?? ''),
                'open_url' => biotern_notification_open_url((string)($row['action_url'] ?? ''), $id, 'notifications.php', $title, $message, $type),
                'is_read' => $isRead,
                'created_at' => $createdAt,
                'time_ago' => biotern_notification_time_ago($createdAt),
            ];
        }
        $stmt->close();
    }
} elseif ($hasType && $hasData) {
    $sql = 'SELECT id, type, data, is_read, created_at FROM notifications WHERE user_id = ?';
    if ($hasDeletedAt) {
        $sql .= ' AND deleted_at IS NULL';
    }
    $sql .= ' ORDER BY created_at DESC, id DESC LIMIT ' . $safeLimit;

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $id = (int)($row['id'] ?? 0);
            $rawData = (string)($row['data'] ?? '');
            $decoded = json_decode($rawData, true);
            $title = ucfirst((string)($row['type'] ?? 'notification'));
            $message = $rawData;
            $actionUrl = '';
            if (is_array($decoded)) {
                $title = (string)($decoded['title'] ?? $title);
                $message = (string)($decoded['message'] ?? $message);
                $actionUrl = (string)($decoded['action_url'] ?? '');
            }
            $type = biotern_notification_normalize_type((string)($row['type'] ?? ''), $title, $message, $actionUrl);
            $meta = biotern_notification_type_meta($type);
            $createdAt = (string)($row['created_at'] ?? '');
            $isRead = (int)($row['is_read'] ?? 0);

            if ($id > $latestId) {
                $latestId = $id;
            }
            if ($isRead === 1) {
                $readCount++;
            }

            $items[] = [
                'id' => $id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'type_label' => (string)($meta['label'] ?? 'System'),
                'icon' => (string)($meta['icon'] ?? 'feather-bell'),
                'action_url' => $actionUrl,
                'open_url' => biotern_notification_open_url($actionUrl, $id, 'notifications.php', $title, $message, $type),
                'is_read' => $isRead,
                'created_at' => $createdAt,
                'time_ago' => biotern_notification_time_ago($createdAt),
            ];
        }
        $stmt->close();
    }
}

biotern_notifications_feed_json(200, [
    'ok' => true,
    'notifications' => $items,
    'unread_count' => biotern_notifications_count_unread($conn, $userId),
    'read_count' => $readCount,
    'latest_id' => $latestId,
    'notifications_url' => 'notifications.php',
]);
