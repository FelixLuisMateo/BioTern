<?php

if (!function_exists('biotern_db_table_exists')) {
    function biotern_db_table_exists(mysqli $conn, string $table): bool
    {
        static $cache = [];
        $table = trim($table);
        if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }

        $key = spl_object_id($conn) . ':' . strtolower($table);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $conn->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        if (!$stmt) {
            return $cache[$key] = false;
        }

        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $cache[$key] = $exists;
    }
}

if (!function_exists('biotern_db_column_exists')) {
    function biotern_db_column_exists(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
            return false;
        }

        $key = spl_object_id($conn) . ':' . strtolower($table) . ':' . strtolower($column);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $conn->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        if (!$stmt) {
            return $cache[$key] = false;
        }

        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $cache[$key] = $exists;
    }
}

if (!function_exists('biotern_db_index_exists')) {
    function biotern_db_index_exists(mysqli $conn, string $table, string $index): bool
    {
        static $cache = [];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $index)) {
            return false;
        }

        $key = spl_object_id($conn) . ':' . strtolower($table) . ':' . strtolower($index);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $conn->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1');
        if (!$stmt) {
            return $cache[$key] = false;
        }

        $stmt->bind_param('ss', $table, $index);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $cache[$key] = $exists;
    }
}

if (!function_exists('biotern_db_add_index_if_missing')) {
    function biotern_db_add_index_if_missing(mysqli $conn, string $table, string $index, array $columns): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $index)) {
            return false;
        }
        if (!biotern_db_table_exists($conn, $table) || biotern_db_index_exists($conn, $table, $index)) {
            return true;
        }

        $safeColumns = [];
        foreach ($columns as $column) {
            $column = (string)$column;
            if (!preg_match('/^[A-Za-z0-9_]+$/', $column) || !biotern_db_column_exists($conn, $table, $column)) {
                return false;
            }
            $safeColumns[] = '`' . $column . '`';
        }

        if (!$safeColumns) {
            return false;
        }

        return (bool)$conn->query('CREATE INDEX `' . $index . '` ON `' . $table . '` (' . implode(', ', $safeColumns) . ')');
    }
}

if (!function_exists('biotern_clamp_limit')) {
    function biotern_clamp_limit($value, int $default = 50, int $max = 200): int
    {
        $limit = (int)$value;
        if ($limit <= 0) {
            $limit = $default;
        }
        return max(1, min($max, $limit));
    }
}

if (!function_exists('biotern_clamp_offset')) {
    function biotern_clamp_offset($value): int
    {
        return max(0, (int)$value);
    }
}

if (!function_exists('biotern_ensure_system_settings_table')) {
    function biotern_ensure_system_settings_table(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(191) NOT NULL UNIQUE,
            `value` TEXT NOT NULL,
            `description` VARCHAR(255) NULL,
            `category` VARCHAR(100) NOT NULL DEFAULT 'general',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_system_settings_category (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!biotern_db_column_exists($conn, 'system_settings', 'description')) {
            $conn->query('ALTER TABLE system_settings ADD COLUMN `description` VARCHAR(255) NULL AFTER `value`');
        }
        if (!biotern_db_column_exists($conn, 'system_settings', 'category')) {
            $conn->query("ALTER TABLE system_settings ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'general' AFTER `description`");
        }
        biotern_db_add_index_if_missing($conn, 'system_settings', 'idx_system_settings_category', ['category']);
    }
}

if (!function_exists('biotern_settings_by_category')) {
    function biotern_settings_by_category(mysqli $conn, string $category, array $defaults = []): array
    {
        if (!isset($GLOBALS['biotern_settings_cache']) || !is_array($GLOBALS['biotern_settings_cache'])) {
            $GLOBALS['biotern_settings_cache'] = [];
        }
        $cache = &$GLOBALS['biotern_settings_cache'];
        $category = trim($category);
        $key = spl_object_id($conn) . ':' . $category;

        if (!array_key_exists($key, $cache)) {
            biotern_ensure_system_settings_table($conn);
            $items = [];
            $stmt = $conn->prepare('SELECT `key`, `value` FROM system_settings WHERE category = ?');
            if ($stmt) {
                $stmt->bind_param('s', $category);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $items[(string)$row['key']] = (string)($row['value'] ?? '');
                }
                $stmt->close();
            }
            $cache[$key] = $items;
        }

        return array_replace($defaults, $cache[$key]);
    }
}

if (!function_exists('biotern_save_setting')) {
    function biotern_save_setting(mysqli $conn, string $key, string $value, string $description, string $category): bool
    {
        biotern_ensure_system_settings_table($conn);
        $stmt = $conn->prepare(
            'INSERT INTO system_settings (`key`, `value`, `description`, `category`, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `description` = VALUES(`description`), `category` = VALUES(`category`), updated_at = NOW()'
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ssss', $key, $value, $description, $category);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok && isset($GLOBALS['biotern_settings_cache']) && is_array($GLOBALS['biotern_settings_cache'])) {
            unset($GLOBALS['biotern_settings_cache'][spl_object_id($conn) . ':' . trim($category)]);
        }

        return $ok;
    }
}

if (!function_exists('biotern_core_performance_indexes')) {
    function biotern_core_performance_indexes(): array
    {
        return [
            ['students', 'idx_students_user_id', ['user_id']],
            ['students', 'idx_students_student_id', ['student_id']],
            ['students', 'idx_students_course_section', ['course_id', 'section_id']],
            ['users', 'idx_users_role_active', ['role', 'is_active']],
            ['users', 'idx_users_email', ['email']],
            ['users', 'idx_users_username', ['username']],
            ['internships', 'idx_internships_student_status', ['student_id', 'status']],
            ['internships', 'idx_internships_updated', ['updated_at', 'id']],
            ['attendances', 'idx_attendances_student_date', ['student_id', 'attendance_date']],
            ['attendances', 'idx_attendances_date_status', ['attendance_date', 'status']],
            ['external_attendance', 'idx_external_attendance_student_date', ['student_id', 'attendance_date']],
            ['notifications', 'idx_notifications_user_read_created', ['user_id', 'is_read', 'created_at']],
            ['notifications', 'idx_notifications_user_deleted_created', ['user_id', 'deleted_at', 'created_at']],
            ['messages', 'idx_messages_thread_created', ['from_user_id', 'to_user_id', 'created_at']],
            ['login_logs', 'idx_login_logs_user_status_created', ['user_id', 'status', 'created_at']],
            ['system_settings', 'idx_system_settings_category', ['category']],
            ['student_applications', 'idx_student_applications_status_submitted', ['status', 'submitted_at']],
            ['ojt_masterlist', 'idx_ojt_masterlist_term_section', ['school_year', 'semester', 'section']],
            ['storage_files', 'idx_storage_files_owner_updated', ['owner_user_id', 'deleted_at', 'updated_at']],
            ['storage_activity_logs', 'idx_storage_activity_user_created', ['user_id', 'created_at']],
            ['biometric_raw_logs', 'idx_biometric_raw_processed_id', ['processed', 'id']],
            ['biometric_event_queue', 'idx_biometric_event_status_date', ['status', 'attendance_date']],
            ['biometric_bridge_command_queue', 'idx_bridge_command_status_created', ['status', 'created_at']],
            ['biometric_ingest_events', 'idx_biometric_ingest_received', ['received_at']],
            ['calendar_events', 'idx_calendar_events_start_end', ['start_at', 'end_at']],
            ['user_notes', 'idx_user_notes_user_state_updated', ['user_id', 'deleted_at', 'is_pinned', 'updated_at']],
        ];
    }
}

if (!function_exists('biotern_apply_core_performance_indexes')) {
    function biotern_apply_core_performance_indexes(mysqli $conn): array
    {
        $summary = ['created' => [], 'skipped' => [], 'failed' => []];
        foreach (biotern_core_performance_indexes() as $definition) {
            [$table, $index, $columns] = $definition;
            if (!biotern_db_table_exists($conn, $table)) {
                $summary['skipped'][] = $table . '.' . $index . ' (missing table)';
                continue;
            }
            if (biotern_db_index_exists($conn, $table, $index)) {
                $summary['skipped'][] = $table . '.' . $index . ' (already exists)';
                continue;
            }
            $missingColumns = [];
            foreach ($columns as $column) {
                if (!biotern_db_column_exists($conn, $table, (string)$column)) {
                    $missingColumns[] = (string)$column;
                }
            }
            if ($missingColumns) {
                $summary['skipped'][] = $table . '.' . $index . ' (missing columns: ' . implode(', ', $missingColumns) . ')';
                continue;
            }
            $ok = biotern_db_add_index_if_missing($conn, $table, $index, $columns);
            $summary[$ok ? 'created' : 'failed'][] = $table . '.' . $index;
        }
        return $summary;
    }
}
