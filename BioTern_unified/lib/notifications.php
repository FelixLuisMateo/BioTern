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

        $ok = (bool)$conn->query($sql);
        if (!$ok) {
            return false;
        }

        $columns = biotern_notification_columns($conn);
        if (!isset($columns['type'])) {
            @$conn->query("ALTER TABLE notifications ADD COLUMN type VARCHAR(80) NULL AFTER message");
        }
        if (!isset($columns['action_url'])) {
            @$conn->query("ALTER TABLE notifications ADD COLUMN action_url VARCHAR(500) NULL AFTER type");
        }

        return true;
    }
}

if (!function_exists('biotern_notification_normalize_type')) {
    function biotern_notification_normalize_type(string $type, string $title = '', string $message = '', string $actionUrl = ''): string {
        $candidate = strtolower(trim($type));
        if ($candidate !== '') {
            if ($candidate === 'msg' || $candidate === 'chat_message') {
                return 'chat';
            }
            if ($candidate === 'assignment_update' || $candidate === 'assign') {
                return 'assignment';
            }
            if ($candidate === 'attendance_log' || $candidate === 'dtr') {
                return 'attendance';
            }
            if ($candidate === 'profile' || $candidate === 'account_update') {
                return 'account';
            }
            return $candidate;
        }

        $haystack = strtolower(trim($title . ' ' . $message . ' ' . $actionUrl));
        if ($haystack === '') {
            return 'system';
        }

        if (strpos($haystack, 'chat') !== false || strpos($haystack, 'message') !== false || strpos($haystack, 'apps-chat.php') !== false) {
            return 'chat';
        }
        if (strpos($haystack, 'assignment') !== false || strpos($haystack, 'assigned') !== false || strpos($haystack, 'supervisor') !== false || strpos($haystack, 'coordinator') !== false) {
            return 'assignment';
        }
        if (strpos($haystack, 'attendance') !== false || strpos($haystack, 'time in') !== false || strpos($haystack, 'time out') !== false || strpos($haystack, 'dtr') !== false) {
            return 'attendance';
        }
        if (strpos($haystack, 'account') !== false || strpos($haystack, 'profile') !== false || strpos($haystack, 'password') !== false || strpos($haystack, 'login') !== false) {
            return 'account';
        }

        return 'system';
    }
}

if (!function_exists('biotern_notification_type_meta')) {
    function biotern_notification_type_meta(string $type): array {
        $type = biotern_notification_normalize_type($type);

        $map = [
            'chat' => [
                'label' => 'Chat',
                'icon' => 'feather-message-square',
                'badge_class' => 'bg-info-subtle text-info-emphasis',
            ],
            'assignment' => [
                'label' => 'Assignment',
                'icon' => 'feather-briefcase',
                'badge_class' => 'bg-primary-subtle text-primary-emphasis',
            ],
            'attendance' => [
                'label' => 'Attendance',
                'icon' => 'feather-clock',
                'badge_class' => 'bg-success-subtle text-success-emphasis',
            ],
            'account' => [
                'label' => 'Account',
                'icon' => 'feather-user',
                'badge_class' => 'bg-warning-subtle text-warning-emphasis',
            ],
            'system' => [
                'label' => 'System',
                'icon' => 'feather-bell',
                'badge_class' => 'bg-secondary-subtle text-secondary-emphasis',
            ],
        ];

        return $map[$type] ?? $map['system'];
    }
}

if (!function_exists('biotern_notification_time_ago')) {
    function biotern_notification_time_ago(string $dateTime): string {
        $dateTime = trim($dateTime);
        if ($dateTime === '') {
            return 'Just now';
        }

        $ts = strtotime($dateTime);
        if ($ts === false) {
            return $dateTime;
        }

        $diff = time() - $ts;
        if ($diff < 0) {
            $diff = 0;
        }

        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $mins = (int)floor($diff / 60);
            return $mins . ' min' . ($mins === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 86400) {
            $hours = (int)floor($diff / 3600);
            return $hours . ' hr' . ($hours === 1 ? '' : 's') . ' ago';
        }
        if ($diff < 172800) {
            return 'Yesterday';
        }
        if ($diff < 604800) {
            $days = (int)floor($diff / 86400);
            return $days . ' days ago';
        }

        return date('M d, Y', $ts);
    }
}

if (!function_exists('biotern_notification_open_url')) {
    function biotern_notification_open_url(string $url, int $notificationId = 0, string $fallback = 'homepage.php'): string {
        $target = trim($url);
        if ($target === '') {
            $target = $fallback;
        }

        if (preg_match('~^(?:[a-z][a-z0-9+.-]*:)?//~i', $target)) {
            $target = $fallback;
        }

        if ($notificationId <= 0) {
            return $target;
        }

        $fragment = '';
        $hashPos = strpos($target, '#');
        if ($hashPos !== false) {
            $fragment = substr($target, $hashPos);
            $target = substr($target, 0, $hashPos);
        }

        $separator = strpos($target, '?') === false ? '?' : '&';
        return $target . $separator . 'notif_read=' . $notificationId . $fragment;
    }
}

if (!function_exists('biotern_chat_notification_sender')) {
    function biotern_chat_notification_sender(string $title): string {
        $title = trim($title);
        if ($title === '') {
            return 'Someone';
        }

        if (preg_match('/^\d+\s+new\s+chat\s+messages\s+from\s+(.+)$/i', $title, $m) === 1) {
            $sender = trim((string)($m[1] ?? ''));
            return $sender !== '' ? $sender : 'Someone';
        }
        if (preg_match('/^new\s+chat\s+message\s+from\s+(.+)$/i', $title, $m) === 1) {
            $sender = trim((string)($m[1] ?? ''));
            return $sender !== '' ? $sender : 'Someone';
        }

        return 'Someone';
    }
}

if (!function_exists('biotern_chat_notification_count')) {
    function biotern_chat_notification_count(string $title): int {
        $title = trim($title);
        if ($title === '') {
            return 1;
        }

        if (preg_match('/^(\d+)\s+new\s+chat\s+messages\s+from\s+/i', $title, $m) === 1) {
            return max(1, (int)($m[1] ?? 1));
        }

        return 1;
    }
}

if (!function_exists('biotern_notification_group_count')) {
    function biotern_notification_group_count(string $title): int {
        $title = trim($title);
        if ($title === '') {
            return 1;
        }

        if (preg_match('/^(\d+)\s+new\s+/i', $title, $m) === 1) {
            return max(1, (int)($m[1] ?? 1));
        }

        return 1;
    }
}

if (!function_exists('biotern_notification_group_title')) {
    function biotern_notification_group_title(string $type, int $count, string $fallbackTitle = 'New update'): string {
        $normalized = biotern_notification_normalize_type($type);
        $count = max(1, $count);

        if ($normalized === 'assignment') {
            return $count === 1 ? 'New assignment update' : ($count . ' new assignment updates');
        }
        if ($normalized === 'attendance') {
            return $count === 1 ? 'New attendance update' : ($count . ' new attendance updates');
        }

        return trim($fallbackTitle) !== '' ? trim($fallbackTitle) : 'New update';
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
        $normalizedType = biotern_notification_normalize_type($type, $title, $message, (string)$actionUrl);

        $groupableTypes = ['chat', 'assignment', 'attendance'];
        $canGroup = $hasType && in_array($normalizedType, $groupableTypes, true);

        if ($canGroup) {
            $actionUrlValue = trim((string)$actionUrl);
            $matchByActionUrl = $hasActionUrl && $actionUrlValue !== '';

            $where = 'user_id = ? AND is_read = 0 AND type = ?';
            if ($matchByActionUrl) {
                $where .= ' AND action_url = ?';
            }
            if (isset($columns['deleted_at'])) {
                $where .= ' AND deleted_at IS NULL';
            }

            $existingId = 0;
            $existingTitle = '';
            $existingStmt = $conn->prepare('SELECT id, title FROM notifications WHERE ' . $where . ' ORDER BY created_at DESC, id DESC LIMIT 1');
            if ($existingStmt) {
                if ($matchByActionUrl) {
                    $existingStmt->bind_param('iss', $userId, $normalizedType, $actionUrlValue);
                } else {
                    $existingStmt->bind_param('is', $userId, $normalizedType);
                }
                $existingStmt->execute();
                $existing = $existingStmt->get_result()->fetch_assoc();
                $existingStmt->close();
                if (is_array($existing)) {
                    $existingId = (int)($existing['id'] ?? 0);
                    $existingTitle = (string)($existing['title'] ?? '');
                }
            }

            if ($existingId > 0) {
                if ($normalizedType === 'chat') {
                    $sender = biotern_chat_notification_sender($title);
                    $nextCount = biotern_chat_notification_count($existingTitle) + 1;
                    $nextTitle = $nextCount . ' new chat messages from ' . $sender;
                } else {
                    $nextCount = biotern_notification_group_count($existingTitle) + 1;
                    $nextTitle = biotern_notification_group_title($normalizedType, $nextCount, $title);
                }
                $nextMessage = trim($message) !== '' ? trim($message) : 'You have new chat messages.';
                if ($normalizedType !== 'chat' && trim($message) === '') {
                    $nextMessage = 'You have new ' . $normalizedType . ' updates.';
                }

                $updateStmt = $conn->prepare('UPDATE notifications SET title = ?, message = ?, created_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ?');
                if ($updateStmt) {
                    $updateStmt->bind_param('ssii', $nextTitle, $nextMessage, $existingId, $userId);
                    $updated = $updateStmt->execute();
                    $updateStmt->close();
                    if ($updated) {
                        return true;
                    }
                }
            }
        }

        if ($hasType && $hasActionUrl) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, action_url, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, NOW())");
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('issss', $userId, $title, $message, $normalizedType, $actionUrl);
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

        $columns = biotern_notification_columns($conn);
        $where = 'user_id = ? AND is_read = 0';
        if (isset($columns['deleted_at'])) {
            $where .= ' AND deleted_at IS NULL';
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notifications WHERE " . $where);
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

        $columns = biotern_notification_columns($conn);
        $where = 'id = ? AND user_id = ?';
        if (isset($columns['deleted_at'])) {
            $where .= ' AND deleted_at IS NULL';
        }

        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE " . $where);
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

        $columns = biotern_notification_columns($conn);
        $where = 'user_id = ? AND is_read = 0';
        if (isset($columns['deleted_at'])) {
            $where .= ' AND deleted_at IS NULL';
        }

        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE " . $where);
        if (!$stmt) return false;

        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('biotern_notifications_clear')) {
    function biotern_notifications_clear(mysqli $conn, int $userId, int $notificationId): bool {
        if ($userId <= 0 || $notificationId <= 0) return false;

        biotern_notifications_ensure_table($conn);
        $columns = biotern_notification_columns($conn);

        if (isset($columns['deleted_at'])) {
            $stmt = $conn->prepare("UPDATE notifications SET deleted_at = NOW() WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
        } else {
            $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        }

        if (!$stmt) return false;

        $stmt->bind_param('ii', $notificationId, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('biotern_notifications_clear_all')) {
    function biotern_notifications_clear_all(mysqli $conn, int $userId): bool {
        if ($userId <= 0) return false;

        biotern_notifications_ensure_table($conn);
        $columns = biotern_notification_columns($conn);

        if (isset($columns['deleted_at'])) {
            $stmt = $conn->prepare("UPDATE notifications SET deleted_at = NOW() WHERE user_id = ? AND deleted_at IS NULL");
        } else {
            $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
        }

        if (!$stmt) return false;

        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}
