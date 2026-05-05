<?php

if (!function_exists('biotern_announcements_ensure_tables')) {
    function biotern_announcements_ensure_tables(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS announcements (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            body LONGTEXT NOT NULL,
            media_path VARCHAR(255) NULL,
            media_type VARCHAR(20) NOT NULL DEFAULT 'image',
            media_mime VARCHAR(100) NULL,
            media_name VARCHAR(255) NULL,
            media_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            media_blob LONGBLOB NULL,
            popup_size VARCHAR(20) NOT NULL DEFAULT 'medium',
            accent_color VARCHAR(20) NOT NULL DEFAULT '#3454d1',
            button_label VARCHAR(80) NOT NULL DEFAULT 'Got It',
            show_title TINYINT(1) NOT NULL DEFAULT 1,
            show_author TINYINT(1) NOT NULL DEFAULT 0,
            display_mode VARCHAR(20) NOT NULL DEFAULT 'popup',
            target_role VARCHAR(30) NOT NULL DEFAULT 'all',
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_announcements_active (is_active, target_role),
            INDEX idx_announcements_dates (starts_at, ends_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = [];
        if ($result = $conn->query('SHOW COLUMNS FROM announcements')) {
            while ($row = $result->fetch_assoc()) {
                $columns[strtolower((string)($row['Field'] ?? ''))] = true;
            }
            $result->close();
        }
        if (!isset($columns['media_path'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN media_path VARCHAR(255) NULL AFTER body");
        }
        if (!isset($columns['media_type'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN media_type VARCHAR(20) NOT NULL DEFAULT 'image' AFTER media_path");
        }
        if (!isset($columns['media_mime'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN media_mime VARCHAR(100) NULL AFTER media_type");
        }
        if (!isset($columns['media_name'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN media_name VARCHAR(255) NULL AFTER media_mime");
        }
        if (!isset($columns['media_size'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN media_size BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER media_name");
        }
        if (!isset($columns['media_blob'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN media_blob LONGBLOB NULL AFTER media_size");
        }
        if (!isset($columns['popup_size'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN popup_size VARCHAR(20) NOT NULL DEFAULT 'medium' AFTER media_type");
        }
        if (!isset($columns['accent_color'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN accent_color VARCHAR(20) NOT NULL DEFAULT '#3454d1' AFTER popup_size");
        }
        if (!isset($columns['button_label'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN button_label VARCHAR(80) NOT NULL DEFAULT 'Got It' AFTER accent_color");
        }
        if (!isset($columns['show_title'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN show_title TINYINT(1) NOT NULL DEFAULT 1 AFTER button_label");
        }
        if (!isset($columns['show_author'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN show_author TINYINT(1) NOT NULL DEFAULT 0 AFTER show_title");
        }
        if (!isset($columns['display_mode'])) {
            $conn->query("ALTER TABLE announcements ADD COLUMN display_mode VARCHAR(20) NOT NULL DEFAULT 'popup' AFTER show_author");
        }

        $conn->query("CREATE TABLE IF NOT EXISTS announcement_reads (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            announcement_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            dismissed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_announcement_user (announcement_id, user_id),
            INDEX idx_announcement_reads_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('biotern_announcements_can_manage')) {
    function biotern_announcements_can_manage(string $role): bool
    {
        return in_array(strtolower(trim($role)), ['admin', 'coordinator', 'supervisor'], true);
    }
}

if (!function_exists('biotern_announcements_normalize_target')) {
    function biotern_announcements_normalize_target(string $target): string
    {
        $target = strtolower(trim($target));
        $allowed = ['all', 'student', 'admin', 'coordinator', 'supervisor'];
        return in_array($target, $allowed, true) ? $target : 'all';
    }
}

if (!function_exists('biotern_announcements_normalize_display_mode')) {
    function biotern_announcements_normalize_display_mode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return in_array($mode, ['popup', 'notification', 'both'], true) ? $mode : 'popup';
    }
}

if (!function_exists('biotern_announcements_display_mode_label')) {
    function biotern_announcements_display_mode_label(string $mode): string
    {
        return match (biotern_announcements_normalize_display_mode($mode)) {
            'notification' => 'Notification Only',
            'both' => 'Popup + Notification',
            default => 'Popup Only',
        };
    }
}

if (!function_exists('biotern_announcements_target_label')) {
    function biotern_announcements_target_label(string $target): string
    {
        return match (biotern_announcements_normalize_target($target)) {
            'student' => 'Students',
            'admin' => 'Admins',
            'coordinator' => 'Coordinators',
            'supervisor' => 'Supervisors',
            default => 'Everyone',
        };
    }
}

if (!function_exists('biotern_announcements_datetime_or_null')) {
    function biotern_announcements_datetime_or_null(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}

if (!function_exists('biotern_announcements_normalize_size')) {
    function biotern_announcements_normalize_size(string $size): string
    {
        $size = strtolower(trim($size));
        return in_array($size, ['small', 'medium', 'wide', 'full'], true) ? $size : 'medium';
    }
}

if (!function_exists('biotern_announcements_normalize_color')) {
    function biotern_announcements_normalize_color(string $color): string
    {
        $color = trim($color);
        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#3454d1';
    }
}

if (!function_exists('biotern_announcements_normalize_media_type')) {
    function biotern_announcements_normalize_media_type(string $type): string
    {
        $type = strtolower(trim($type));
        return in_array($type, ['image', 'video'], true) ? $type : 'image';
    }
}

if (!function_exists('biotern_announcements_pending_for_user')) {
    function biotern_announcements_pending_for_user(mysqli $conn, int $userId, string $role, int $limit = 3): array
    {
        if ($userId <= 0) {
            return [];
        }

        biotern_announcements_ensure_tables($conn);
        $role = strtolower(trim($role));
        $safeLimit = max(1, min(10, $limit));

        $sql = "SELECT a.id, a.title, a.body, a.media_path, a.media_type, a.popup_size, a.accent_color, a.button_label, a.show_title, a.show_author, a.target_role, a.created_at,
                COALESCE(NULLIF(u.name, ''), NULLIF(u.username, ''), NULLIF(u.email, ''), 'BioTern Admin') AS author_name
            FROM announcements a
            LEFT JOIN users u ON u.id = a.created_by
            LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
            WHERE a.is_active = 1
                AND COALESCE(a.display_mode, 'popup') IN ('popup', 'both')
                AND ar.id IS NULL
                AND (a.target_role = 'all' OR a.target_role = ?)
                AND (a.starts_at IS NULL OR a.starts_at <= NOW())
                AND (a.ends_at IS NULL OR a.ends_at >= NOW())
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT " . $safeLimit;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('is', $userId, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();

        return $items;
    }
}

if (!function_exists('biotern_announcements_dismiss')) {
    function biotern_announcements_dismiss(mysqli $conn, int $announcementId, int $userId): bool
    {
        if ($announcementId <= 0 || $userId <= 0) {
            return false;
        }

        biotern_announcements_ensure_tables($conn);
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO announcement_reads (announcement_id, user_id, dismissed_at)
             VALUES (?, ?, NOW())"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $announcementId, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}
