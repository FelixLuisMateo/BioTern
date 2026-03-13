<?php
if (!function_exists('biotern_notification_columns')) {
    function biotern_notification_columns(mysqli $conn): array {
        $columns = [];
        $res = $conn->query("SHOW COLUMNS FROM notifications");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $field = strtolower((string)($row['Field'] ?? ''));
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }
        }
        return $columns;
    }
}

if (!function_exists('biotern_notifications_ensure_table')) {
    function biotern_notifications_ensure_table(mysqli $conn): bool {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            message LONGTEXT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_notifications_user_read (user_id, is_read),
            INDEX idx_notifications_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

        return (bool)$conn->query($sql);
    }
}

if (!function_exists('biotern_notify')) {
    function biotern_notify(mysqli $conn, int $userId, string $title, string $message, string $type = 'system', ?string $actionUrl = null): bool {
        if ($userId <= 0 || trim($title) === '' || trim($message) === '') {
            return false;
        }

        biotern_notifications_ensure_table($conn);
        $columns = biotern_notification_columns($conn);

        $hasType = isset($columns['type']);
        $hasActionUrl = isset($columns['action_url']);

        if ($hasType && $hasActionUrl) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, action_url, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('issss', $userId, $title, $message, $type, $actionUrl);
        } else {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('iss', $userId, $title, $message);
        }

        if (!$stmt) {
            return false;
        }

        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('biotern_notifications_count_unread')) {
    function biotern_notifications_count_unread(mysqli $conn, int $userId): int {
        if ($userId <= 0) return 0;
        biotern_notifications_ensure_table($conn);

        $stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
        if (!$stmt) return 0;

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['unread_count'] ?? 0);
    }
}

if (!function_exists('biotern_notifications_fetch')) {
    function biotern_notifications_fetch(mysqli $conn, int $userId, int $limit = 6): array {
        if ($userId <= 0) return [];
        biotern_notifications_ensure_table($conn);

        $columns = biotern_notification_columns($conn);
        $hasType = isset($columns['type']);
        $hasActionUrl = isset($columns['action_url']);

        $select = "id, title, message, is_read, created_at";
        if ($hasType) {
            $select .= ", type";
        }
        if ($hasActionUrl) {
            $select .= ", action_url";
        }

        $safeLimit = max(1, min(50, $limit));
        $where = "user_id = ?";
        if (isset($columns['deleted_at'])) {
            $where .= " AND deleted_at IS NULL";
        }

        $sql = "SELECT " . $select . "
            FROM notifications
            WHERE " . $where . "
            ORDER BY created_at DESC, id DESC
            LIMIT " . $safeLimit;

        $stmt = $conn->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'title' => (string)($row['title'] ?? 'Notification'),
                'message' => (string)($row['message'] ?? ''),
                'type' => (string)($row['type'] ?? 'system'),
                'action_url' => (string)($row['action_url'] ?? ''),
                'is_read' => (int)($row['is_read'] ?? 0),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }

        $stmt->close();
        return $items;
    }
}

if (!function_exists('biotern_notifications_mark_read')) {
    function biotern_notifications_mark_read(mysqli $conn, int $userId, int $notificationId): bool {
        if ($userId <= 0 || $notificationId <= 0) return false;

        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        if (!$stmt) return false;

        $stmt->bind_param('ii', $notificationId, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('biotern_notifications_mark_all_read')) {
    function biotern_notifications_mark_all_read(mysqli $conn, int $userId): bool {
        if ($userId <= 0) return false;

        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        if (!$stmt) return false;

        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}


