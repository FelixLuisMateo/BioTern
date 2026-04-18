<?php

if (!function_exists('attendance_bonus_rules_ensure_schema')) {
    function attendance_bonus_rules_ensure_schema(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS attendance_bonus_rules (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(150) NOT NULL,
                section_id INT UNSIGNED NULL,
                department_id INT UNSIGNED NULL,
                applies_to VARCHAR(20) NOT NULL DEFAULT 'both',
                weekday_key VARCHAR(16) NULL,
                start_date DATE NULL,
                end_date DATE NULL,
                multiplier DECIMAL(6,2) NOT NULL DEFAULT 1.00,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                notes VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_bonus_scope (section_id, department_id, applies_to, is_active),
                KEY idx_bonus_window (start_date, end_date, weekday_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }
}

if (!function_exists('attendance_bonus_rules_weekday_key')) {
    function attendance_bonus_rules_weekday_key(string $dateValue): string
    {
        $timestamp = strtotime($dateValue);
        if ($timestamp === false) {
            return '';
        }

        return strtolower((string)date('l', $timestamp));
    }
}

if (!function_exists('attendance_bonus_rules_for_context')) {
    function attendance_bonus_rules_for_context(
        mysqli $conn,
        string $dateValue,
        string $track,
        int $sectionId = 0,
        int $departmentId = 0
    ): array {
        attendance_bonus_rules_ensure_schema($conn);

        $dateValue = substr(trim($dateValue), 0, 10);
        if ($dateValue === '') {
            return [];
        }

        $track = strtolower(trim($track));
        if (!in_array($track, ['internal', 'external'], true)) {
            $track = 'both';
        }

        $rules = [];
        $weekdayKey = attendance_bonus_rules_weekday_key($dateValue);
        $sql = "
            SELECT id, title, section_id, department_id, applies_to, weekday_key, start_date, end_date, multiplier, notes
            FROM attendance_bonus_rules
            WHERE is_active = 1
              AND multiplier > 0
              AND (applies_to = 'both' OR applies_to = ?)
              AND (weekday_key IS NULL OR weekday_key = '' OR weekday_key = ?)
              AND (start_date IS NULL OR start_date <= ?)
              AND (end_date IS NULL OR end_date >= ?)
              AND (
                    (section_id IS NULL AND department_id IS NULL)
                 OR (section_id IS NOT NULL AND section_id = ?)
                 OR (department_id IS NOT NULL AND department_id = ?)
              )
            ORDER BY multiplier DESC, id DESC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('ssssii', $track, $weekdayKey, $dateValue, $dateValue, $sectionId, $departmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rules[] = $row;
        }
        $stmt->close();

        return $rules;
    }
}
