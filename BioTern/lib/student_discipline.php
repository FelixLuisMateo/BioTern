<?php
require_once __DIR__ . '/notifications.php';

if (!function_exists('biotern_discipline_ensure_schema')) {
    function biotern_discipline_ensure_schema(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS student_disciplinary_records (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id BIGINT UNSIGNED NOT NULL,
            action_type VARCHAR(40) NOT NULL DEFAULT 'note',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            reason VARCHAR(255) NOT NULL DEFAULT '',
            details TEXT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            reset_scope VARCHAR(20) NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            KEY idx_student_discipline_student (student_id, status, start_date, end_date),
            KEY idx_student_discipline_action (action_type, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('biotern_discipline_normalize_date')) {
    function biotern_discipline_normalize_date(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $value ? $value : null;
    }
}

if (!function_exists('biotern_discipline_active_suspension')) {
    function biotern_discipline_active_suspension(mysqli $conn, int $studentId, ?string $date = null): ?array
    {
        if ($studentId <= 0) {
            return null;
        }
        biotern_discipline_ensure_schema($conn);
        $date = biotern_discipline_normalize_date($date) ?: date('Y-m-d');
        $stmt = $conn->prepare(
            "SELECT *
             FROM student_disciplinary_records
             WHERE student_id = ?
               AND action_type = 'suspension'
               AND status = 'active'
               AND deleted_at IS NULL
               AND (start_date IS NULL OR start_date <= ?)
               AND (end_date IS NULL OR end_date >= ?)
             ORDER BY id DESC
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('iss', $studentId, $date, $date);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('biotern_discipline_student_records')) {
    function biotern_discipline_student_records(mysqli $conn, int $studentId, int $limit = 20): array
    {
        if ($studentId <= 0) {
            return [];
        }
        biotern_discipline_ensure_schema($conn);
        $limit = max(1, min(100, $limit));
        $stmt = $conn->prepare(
            "SELECT r.*, COALESCE(u.name, u.username, '') AS created_by_name
             FROM student_disciplinary_records r
             LEFT JOIN users u ON u.id = r.created_by
             WHERE r.student_id = ? AND r.deleted_at IS NULL
             ORDER BY r.created_at DESC, r.id DESC
             LIMIT ?"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $studentId, $limit);
        $stmt->execute();
        $rows = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('biotern_discipline_reset_student_time')) {
    function biotern_discipline_reset_student_time(mysqli $conn, int $studentId, string $scope, ?string $startDate, string $reason): void
    {
        $scope = in_array($scope, ['internal', 'external', 'all'], true) ? $scope : 'internal';
        $startDate = biotern_discipline_normalize_date($startDate);
        $reason = trim($reason) !== '' ? trim($reason) : 'Disciplinary time reset';

        if ($scope === 'internal' || $scope === 'all') {
            $sql = "UPDATE attendances
                    SET total_hours = 0,
                        remarks = TRIM(CONCAT(COALESCE(remarks, ''), CASE WHEN COALESCE(remarks, '') = '' THEN '' ELSE ' | ' END, ?)),
                        updated_at = NOW()
                    WHERE student_id = ?";
            if ($startDate !== null) {
                $sql .= " AND attendance_date >= ?";
            }
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if ($startDate !== null) {
                    $stmt->bind_param('sis', $reason, $studentId, $startDate);
                } else {
                    $stmt->bind_param('si', $reason, $studentId);
                }
                $stmt->execute();
                $stmt->close();
            }
        }

        if (($scope === 'external' || $scope === 'all') && function_exists('table_exists') && table_exists($conn, 'external_attendance')) {
            $sql = "UPDATE external_attendance
                    SET total_hours = 0,
                        notes = TRIM(CONCAT(COALESCE(notes, ''), CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE ' | ' END, ?)),
                        updated_at = NOW()
                    WHERE student_id = ?";
            if ($startDate !== null) {
                $sql .= " AND attendance_date >= ?";
            }
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if ($startDate !== null) {
                    $stmt->bind_param('sis', $reason, $studentId, $startDate);
                } else {
                    $stmt->bind_param('si', $reason, $studentId);
                }
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

if (!function_exists('biotern_discipline_create_record')) {
    function biotern_discipline_create_record(mysqli $conn, int $studentId, string $actionType, string $reason, ?string $startDate, ?string $endDate, string $details, int $createdBy, string $resetScope = ''): bool
    {
        biotern_discipline_ensure_schema($conn);
        $actionType = in_array($actionType, ['note', 'warning', 'suspension', 'reset_time'], true) ? $actionType : 'note';
        $reason = trim($reason);
        $details = trim($details);
        $startDate = biotern_discipline_normalize_date($startDate);
        $endDate = biotern_discipline_normalize_date($endDate);
        $resetScope = in_array($resetScope, ['internal', 'external', 'all'], true) ? $resetScope : null;
        if ($studentId <= 0 || $reason === '') {
            return false;
        }
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $stmt = $conn->prepare(
            "INSERT INTO student_disciplinary_records
                (student_id, action_type, status, reason, details, start_date, end_date, reset_scope, created_by, created_at, updated_at)
             VALUES (?, ?, 'active', ?, ?, ?, ?, ?, NULLIF(?, 0), NOW(), NOW())"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('issssssi', $studentId, $actionType, $reason, $details, $startDate, $endDate, $resetScope, $createdBy);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok && $actionType === 'reset_time') {
            biotern_discipline_reset_student_time($conn, $studentId, (string)$resetScope, $startDate, $reason);
        }

        return (bool)$ok;
    }
}
