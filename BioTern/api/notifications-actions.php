<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/notifications.php';

biotern_boot_session(isset($conn) ? $conn : null);

if (!function_exists('biotern_notifications_actions_json')) {
    function biotern_notifications_actions_json(int $status, array $payload): void
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

if (!function_exists('biotern_notifications_actions_remaining_count')) {
    function biotern_notifications_actions_remaining_count(mysqli $conn, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $columns = biotern_notification_columns($conn);
        $where = 'user_id = ?';
        if (isset($columns['deleted_at'])) {
            $where .= ' AND deleted_at IS NULL';
        }

        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE ' . $where);
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    biotern_notifications_actions_json(401, [
        'ok' => false,
        'message' => 'Authentication required.',
    ]);
}

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    biotern_notifications_actions_json(500, [
        'ok' => false,
        'message' => 'Database connection unavailable.',
    ]);
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    biotern_notifications_actions_json(405, [
        'ok' => false,
        'message' => 'Method not allowed.',
    ]);
}

biotern_notifications_ensure_table($conn);
$columns = biotern_notification_columns($conn);
$action = strtolower(trim((string)($_POST['action'] ?? '')));
$removedCount = 0;

if ($action === 'remove_one_read') {
    $notificationId = (int)($_POST['notification_id'] ?? 0);
    if ($notificationId <= 0) {
        biotern_notifications_actions_json(422, [
            'ok' => false,
            'message' => 'Invalid notification ID.',
        ]);
    }

    $where = 'id = ? AND user_id = ? AND is_read = 1';
    if (isset($columns['deleted_at'])) {
        $where .= ' AND deleted_at IS NULL';
    }

    $checkStmt = $conn->prepare('SELECT id FROM notifications WHERE ' . $where . ' LIMIT 1');
    if (!$checkStmt) {
        biotern_notifications_actions_json(500, [
            'ok' => false,
            'message' => 'Unable to process notification action.',
        ]);
    }

    $checkStmt->bind_param('ii', $notificationId, $userId);
    $checkStmt->execute();
    $row = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$row) {
        biotern_notifications_actions_json(200, [
            'ok' => true,
            'removed_count' => 0,
            'unread_count' => biotern_notifications_count_unread($conn, $userId),
            'remaining_count' => biotern_notifications_actions_remaining_count($conn, $userId),
            'message' => 'Notification was already removed or is not marked as read.',
        ]);
    }

    $removed = biotern_notifications_clear($conn, $userId, $notificationId);
    $removedCount = $removed ? 1 : 0;
} elseif ($action === 'remove_all_read') {
    $removedCount = biotern_notifications_clear_read($conn, $userId);
} else {
    biotern_notifications_actions_json(422, [
        'ok' => false,
        'message' => 'Invalid action.',
    ]);
}

biotern_notifications_actions_json(200, [
    'ok' => true,
    'removed_count' => max(0, (int)$removedCount),
    'unread_count' => biotern_notifications_count_unread($conn, $userId),
    'remaining_count' => biotern_notifications_actions_remaining_count($conn, $userId),
]);
