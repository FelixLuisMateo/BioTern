<?php

if (!function_exists('biotern_attendance_settings_defaults')) {
    function biotern_attendance_settings_defaults(): array
    {
        return [
            'credit_mode' => 'actual',
            'biometric_window_enabled' => '0',
            'scheduled_slot_display' => '1',
            'live_timer_uses_schedule_cutoff' => '0',
            'apply_to_external' => '0',
        ];
    }
}

if (!function_exists('biotern_attendance_ensure_settings_table')) {
    function biotern_attendance_ensure_settings_table(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(100) NOT NULL,
                `value` TEXT NULL,
                `description` VARCHAR(255) NULL,
                `category` VARCHAR(100) NOT NULL DEFAULT 'general',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `key` (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if ($result = $conn->query('SHOW COLUMNS FROM system_settings')) {
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = (string)$row['Field'];
            }
            $result->close();

            if (!in_array('description', $columns, true)) {
                $conn->query('ALTER TABLE system_settings ADD COLUMN `description` VARCHAR(255) NULL AFTER `value`');
            }
            if (!in_array('category', $columns, true)) {
                $conn->query("ALTER TABLE system_settings ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'general' AFTER `description`");
            }
        }
    }
}

if (!function_exists('biotern_attendance_settings')) {
    function biotern_attendance_settings(mysqli $conn): array
    {
        static $cache = [];
        $hash = spl_object_id($conn);
        if (isset($cache[$hash])) {
            return $cache[$hash];
        }

        biotern_attendance_ensure_settings_table($conn);
        $settings = biotern_attendance_settings_defaults();
        $category = 'attendance';
        $stmt = $conn->prepare('SELECT `key`, `value` FROM system_settings WHERE category = ?');
        if ($stmt) {
            $stmt->bind_param('s', $category);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $key = (string)($row['key'] ?? '');
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = (string)($row['value'] ?? '');
                }
            }
            $stmt->close();
        }

        $settings['credit_mode'] = $settings['credit_mode'] === 'schedule' ? 'schedule' : 'actual';
        foreach (['biometric_window_enabled', 'scheduled_slot_display', 'live_timer_uses_schedule_cutoff', 'apply_to_external'] as $key) {
            $settings[$key] = $settings[$key] === '1' ? '1' : '0';
        }

        $cache[$hash] = $settings;
        return $settings;
    }
}

if (!function_exists('biotern_attendance_save_setting')) {
    function biotern_attendance_save_setting(mysqli $conn, string $key, string $value, string $description = ''): bool
    {
        biotern_attendance_ensure_settings_table($conn);
        $category = 'attendance';
        $stmt = $conn->prepare(
            'INSERT INTO system_settings (`key`, `value`, `description`, `category`, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `description` = VALUES(`description`), updated_at = NOW()'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssss', $key, $value, $description, $category);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('biotern_attendance_applies_to_track')) {
    function biotern_attendance_applies_to_track(array $settings, string $track): bool
    {
        $track = strtolower(trim($track));
        return $track !== 'external' || (string)($settings['apply_to_external'] ?? '0') === '1';
    }
}

if (!function_exists('biotern_attendance_uses_schedule_credit')) {
    function biotern_attendance_uses_schedule_credit(mysqli $conn, string $track = 'internal'): bool
    {
        $settings = biotern_attendance_settings($conn);
        return biotern_attendance_applies_to_track($settings, $track)
            && (string)($settings['credit_mode'] ?? 'actual') === 'schedule';
    }
}
