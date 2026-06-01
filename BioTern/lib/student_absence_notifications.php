<?php
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/section_schedule.php';
require_once __DIR__ . '/student_absence_excuses.php';
require_once __DIR__ . '/attendance_settings.php';

if (!function_exists('biotern_absence_ensure_system_settings_table')) {
    function biotern_absence_ensure_system_settings_table(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(191) NOT NULL UNIQUE,
            `value` TEXT NOT NULL,
            `description` VARCHAR(255) NULL,
            `category` VARCHAR(100) NOT NULL DEFAULT 'general',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = [];
        if ($result = $conn->query('SHOW COLUMNS FROM system_settings')) {
            while ($row = $result->fetch_assoc()) {
                $columns[strtolower((string)($row['Field'] ?? ''))] = true;
            }
            $result->close();
        }
        if (!isset($columns['description'])) {
            $conn->query('ALTER TABLE system_settings ADD COLUMN `description` VARCHAR(255) NULL AFTER `value`');
        }
        if (!isset($columns['category'])) {
            $conn->query("ALTER TABLE system_settings ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'general' AFTER `description`");
        }
    }
}

if (!function_exists('biotern_absence_setting')) {
    function biotern_absence_setting(mysqli $conn, string $key, string $default): string
    {
        biotern_absence_ensure_system_settings_table($conn);
        $stmt = $conn->prepare("SELECT `value` FROM system_settings WHERE `key` = ? LIMIT 1");
        if (!$stmt) {
            return $default;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $value = trim((string)($row['value'] ?? ''));
        return $value !== '' ? $value : $default;
    }
}

if (!function_exists('biotern_absence_store_setting')) {
    function biotern_absence_store_setting(mysqli $conn, string $key, string $value, string $description): bool
    {
        biotern_absence_ensure_system_settings_table($conn);
        $category = 'notifications';
        $stmt = $conn->prepare(
            "INSERT INTO system_settings (`key`, `value`, `description`, `category`, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `description` = VALUES(`description`), `category` = VALUES(`category`), updated_at = NOW()"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssss', $key, $value, $description, $category);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('biotern_absence_notify_threshold')) {
    function biotern_absence_notify_threshold(mysqli $conn): int
    {
        $settings = function_exists('biotern_attendance_settings') ? biotern_attendance_settings($conn) : [];
        $value = (string)($settings['student_absence_notify_days'] ?? biotern_absence_setting($conn, 'student_absence_notify_days', '3'));
        $threshold = (int)$value;
        return max(1, min(30, $threshold));
    }
}

if (!function_exists('biotern_absence_ensure_log_table')) {
    function biotern_absence_ensure_log_table(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS student_absence_notification_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id BIGINT UNSIGNED NOT NULL,
            streak_end_date DATE NOT NULL,
            threshold_days INT NOT NULL,
            absent_dates VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_student_absence_streak (student_id, streak_end_date, threshold_days),
            INDEX idx_student_absence_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('biotern_absence_is_admin')) {
    function biotern_absence_is_admin(): bool
    {
        $role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? $_SESSION['account_role'] ?? '')));
        return $role === 'admin';
    }
}

if (!function_exists('biotern_absence_admin_user_ids')) {
    function biotern_absence_admin_user_ids(mysqli $conn): array
    {
        $ids = [];
        $result = $conn->query("SELECT id FROM users WHERE role = 'admin' AND (is_active = 1 OR is_active IS NULL)");
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            $result->close();
        }
        return array_values(array_unique($ids));
    }
}

if (!function_exists('biotern_absence_recipient_user_ids')) {
    function biotern_absence_recipient_user_ids(mysqli $conn, array $student): array
    {
        $ids = [];

        foreach (['student_supervisor_user_id', 'internship_supervisor_user_id', 'student_coordinator_user_id', 'internship_coordinator_user_id'] as $key) {
            $id = (int)($student[$key] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            $ids = biotern_absence_admin_user_ids($conn);
        }

        return array_values(array_unique(array_filter($ids, static fn($id) => (int)$id > 0)));
    }
}

if (!function_exists('biotern_absence_schedule_is_countable_day')) {
    function biotern_absence_schedule_is_countable_day(array $schedule, string $date): bool
    {
        $dayKey = section_schedule_day_key_from_date($date);
        if ($dayKey === 'sunday') {
            return false;
        }
        if (section_schedule_requires_school_attendance($schedule, $date)) {
            return in_array((string)$dayKey, section_schedule_weekday_order(), true);
        }
        $resolved = section_schedule_for_date($schedule, $date);
        $dayType = section_schedule_normalize_day_type((string)($resolved['day_type'] ?? 'class'), $dayKey);
        if ($dayType === 'no_class') {
            return in_array((string)$dayKey, section_schedule_weekday_order(), true);
        }
        if ($dayKey === 'saturday') {
            return section_schedule_has_configured_day($schedule, $date);
        }
        if (!in_array((string)$dayKey, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], true)) {
            return false;
        }

        $weekly = is_array($schedule['weekly_schedule'] ?? null) ? $schedule['weekly_schedule'] : [];
        return isset($weekly[$dayKey]) && section_schedule_has_configured_day($schedule, $date);
    }
}

if (!function_exists('biotern_absence_attendance_is_present')) {
    function biotern_absence_attendance_is_present(array $row, array $schedule): bool
    {
        if (strtolower(trim((string)($row['status'] ?? ''))) === 'rejected') {
            return false;
        }
        return section_schedule_entry_time($row, $schedule) !== null;
    }
}

if (!function_exists('biotern_absence_recent_attendance')) {
    function biotern_absence_recent_attendance(mysqli $conn, int $studentId, string $startDate, string $endDate): array
    {
        $rows = [];
        $stmt = $conn->prepare(
            "SELECT attendance_date, morning_time_in, afternoon_time_in, status
             FROM attendances
             WHERE student_id = ? AND attendance_date BETWEEN ? AND ?
             ORDER BY attendance_date DESC"
        );
        if (!$stmt) {
            return $rows;
        }
        $stmt->bind_param('iss', $studentId, $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $date = (string)($row['attendance_date'] ?? '');
            if ($date !== '') {
                $rows[$date][] = $row;
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('biotern_absence_approved_excuse_dates')) {
    function biotern_absence_approved_excuse_dates(mysqli $conn, int $studentId, string $startDate, string $endDate): array
    {
        biotern_absence_excuses_ensure_schema($conn);
        $dates = [];
        $stmt = $conn->prepare(
            "SELECT absence_date
             FROM student_absence_excuses
             WHERE student_id = ? AND absence_date BETWEEN ? AND ? AND status = 'approved' AND deleted_at IS NULL"
        );
        if (!$stmt) {
            return $dates;
        }
        $stmt->bind_param('iss', $studentId, $startDate, $endDate);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $date = (string)($row['absence_date'] ?? '');
            if ($date !== '') {
                $dates[$date] = true;
            }
        }
        $stmt->close();
        return $dates;
    }
}

if (!function_exists('biotern_absence_student_streak')) {
    function biotern_absence_student_streak(mysqli $conn, array $student, int $threshold, string $endDate): array
    {
        $startDate = date('Y-m-d', strtotime($endDate . ' -45 days'));
        $attendanceRows = biotern_absence_recent_attendance($conn, (int)$student['id'], $startDate, $endDate);
        $approvedExcuses = biotern_absence_approved_excuse_dates($conn, (int)$student['id'], $startDate, $endDate);
        $schedule = section_schedule_from_row($student);
        $absentDates = [];
        $cursorTs = strtotime($endDate);

        while ($cursorTs !== false && $cursorTs >= strtotime($startDate)) {
            $date = date('Y-m-d', $cursorTs);
            if (!biotern_absence_schedule_is_countable_day($schedule, $date)) {
                $dayKey = section_schedule_day_key_from_date($date);
                $isWeekendException = $dayKey === 'sunday' || $dayKey === 'saturday';
                if (!empty($absentDates) && !$isWeekendException) {
                    break;
                }
                $cursorTs = strtotime('-1 day', $cursorTs);
                continue;
            }
            if (function_exists('biotern_discipline_active_suspension') && biotern_discipline_active_suspension($conn, (int)$student['id'], $date)) {
                break;
            }

            $present = false;
            foreach (($attendanceRows[$date] ?? []) as $row) {
                $row['attendance_date'] = $date;
                if (biotern_absence_attendance_is_present($row, $schedule)) {
                    $present = true;
                    break;
                }
            }

            if ($present) {
                break;
            }
            if (!empty($approvedExcuses[$date])) {
                break;
            }

            $absentDates[] = $date;
            if (count($absentDates) >= $threshold) {
                break;
            }
            $cursorTs = strtotime('-1 day', $cursorTs);
        }

        return array_reverse($absentDates);
    }
}

if (!function_exists('biotern_absence_notify_admins')) {
    function biotern_absence_notify_admins(mysqli $conn, int $threshold = 0): int
    {
        if ($threshold <= 0) {
            $threshold = biotern_absence_notify_threshold($conn);
        }

        biotern_notifications_ensure_table($conn);
        section_schedule_ensure_columns($conn);
        biotern_absence_ensure_log_table($conn);

        $endDate = date('Y-m-d', strtotime('yesterday'));
        $students = [];
        $sql = "SELECT s.id, s.student_id, s.first_name, s.last_name, s.section_id,
                    sup_student.user_id AS student_supervisor_user_id,
                    sup_internship.user_id AS internship_supervisor_user_id,
                    coord_student.user_id AS student_coordinator_user_id,
                    coord_internship.user_id AS internship_coordinator_user_id,
                    sec.attendance_session, sec.schedule_time_in, sec.schedule_time_out, sec.late_after_time, sec.school_year_end_date, sec.weekly_schedule_json
                FROM students s
                INNER JOIN users u ON u.id = s.user_id
                LEFT JOIN sections sec ON sec.id = s.section_id
                LEFT JOIN supervisors sup_student ON sup_student.id = s.supervisor_id OR sup_student.user_id = s.supervisor_id
                LEFT JOIN internships i_active ON i_active.student_id = s.id AND i_active.status = 'ongoing' AND i_active.deleted_at IS NULL
                LEFT JOIN supervisors sup_internship ON sup_internship.id = i_active.supervisor_id OR sup_internship.user_id = i_active.supervisor_id
                LEFT JOIN coordinators coord_student ON coord_student.id = s.coordinator_id OR coord_student.user_id = s.coordinator_id
                LEFT JOIN coordinators coord_internship ON coord_internship.id = i_active.coordinator_id OR coord_internship.user_id = i_active.coordinator_id
                WHERE s.deleted_at IS NULL
                    AND (u.is_active = 1 OR u.is_active IS NULL)
                    AND (s.status IN ('1', 'active') OR s.status IS NULL)
                    AND (
                        s.application_status = 'approved'
                        OR EXISTS (
                            SELECT 1
                            FROM internships i
                            WHERE i.student_id = s.id AND i.status = 'ongoing' AND i.deleted_at IS NULL
                            LIMIT 1
                        )
                    )
                ORDER BY s.last_name ASC, s.first_name ASC";
        $result = $conn->query($sql);
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $students[] = $row;
            }
            $result->close();
        }

        $created = 0;
        foreach ($students as $student) {
            $absentDates = biotern_absence_student_streak($conn, $student, $threshold, $endDate);
            if (count($absentDates) < $threshold) {
                continue;
            }

            $studentId = (int)$student['id'];
            $streakEndDate = (string)end($absentDates);
            $absentDatesCsv = implode(',', $absentDates);
            $logStmt = $conn->prepare(
                "INSERT IGNORE INTO student_absence_notification_log (student_id, streak_end_date, threshold_days, absent_dates, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            if (!$logStmt) {
                continue;
            }
            $logStmt->bind_param('isis', $studentId, $streakEndDate, $threshold, $absentDatesCsv);
            $logStmt->execute();
            $inserted = $logStmt->affected_rows > 0;
            $logStmt->close();
            if (!$inserted) {
                continue;
            }

            $name = trim((string)($student['first_name'] ?? '') . ' ' . (string)($student['last_name'] ?? ''));
            if ($name === '') {
                $name = 'Student #' . $studentId;
            }
            $studentNumber = trim((string)($student['student_id'] ?? ''));
            $title = 'Student absence threshold reached';
            $message = $name . ($studentNumber !== '' ? ' (' . $studentNumber . ')' : '') . ' has been absent for ' . $threshold . ' scheduled day' . ($threshold === 1 ? '' : 's') . ': ' . implode(', ', $absentDates) . '.';
            foreach (biotern_absence_recipient_user_ids($conn, $student) as $recipientId) {
                if (biotern_notify($conn, $recipientId, $title, $message, 'attendance', 'reports-absences.php')) {
                    $created++;
                }
            }
        }

        return $created;
    }
}
