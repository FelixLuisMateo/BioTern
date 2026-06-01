<?php
require_once __DIR__ . '/student_discipline.php';

if (!function_exists('biotern_absence_excuses_ensure_schema')) {
    function biotern_absence_excuses_ensure_schema(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS student_absence_excuses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            student_id BIGINT UNSIGNED NOT NULL,
            absence_date DATE NOT NULL,
            reason VARCHAR(255) NOT NULL DEFAULT '',
            details TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            submitted_by BIGINT UNSIGNED NULL,
            reviewed_by BIGINT UNSIGNED NULL,
            review_notes TEXT NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_absence_excuse_student_date (student_id, absence_date),
            KEY idx_absence_excuse_status (status, absence_date),
            KEY idx_absence_excuse_student (student_id, absence_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('biotern_absence_excuse_normalize_date')) {
    function biotern_absence_excuse_normalize_date(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $dt instanceof DateTimeImmutable && $dt->format('Y-m-d') === $value ? $value : null;
    }
}

if (!function_exists('biotern_absence_excuse_submit')) {
    function biotern_absence_excuse_submit(mysqli $conn, int $studentId, string $absenceDate, string $reason, string $details, int $submittedBy): bool
    {
        biotern_absence_excuses_ensure_schema($conn);
        $absenceDate = biotern_absence_excuse_normalize_date($absenceDate) ?: '';
        $reason = trim($reason);
        $details = trim($details);
        if ($studentId <= 0 || $absenceDate === '' || $reason === '') {
            return false;
        }

        $stmt = $conn->prepare(
            "INSERT INTO student_absence_excuses
                (student_id, absence_date, reason, details, status, submitted_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'pending', NULLIF(?, 0), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                reason = VALUES(reason),
                details = VALUES(details),
                status = 'pending',
                submitted_by = VALUES(submitted_by),
                reviewed_by = NULL,
                review_notes = NULL,
                reviewed_at = NULL,
                updated_at = NOW()"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('isssi', $studentId, $absenceDate, $reason, $details, $submittedBy);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool)$ok;
    }
}

if (!function_exists('biotern_absence_excuse_review')) {
    function biotern_absence_excuse_review(mysqli $conn, int $excuseId, string $status, string $notes, int $reviewedBy, string $role, int $userId): bool
    {
        biotern_absence_excuses_ensure_schema($conn);
        $status = strtolower(trim($status));
        if (!in_array($status, ['approved', 'rejected', 'pending'], true) || $excuseId <= 0) {
            return false;
        }

        $scope = biotern_student_scope_condition($conn, 's', $role, $userId);
        $where = $scope !== '' ? ' AND ' . $scope : '';
        $stmt = $conn->prepare(
            "UPDATE student_absence_excuses e
             INNER JOIN students s ON s.id = e.student_id
             SET e.status = ?, e.review_notes = ?, e.reviewed_by = NULLIF(?, 0), e.reviewed_at = NOW(), e.updated_at = NOW()
             WHERE e.id = ? AND e.deleted_at IS NULL{$where}"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssii', $status, $notes, $reviewedBy, $excuseId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('biotern_absence_excuse_rows_for_student')) {
    function biotern_absence_excuse_rows_for_student(mysqli $conn, int $studentId, int $limit = 30): array
    {
        biotern_absence_excuses_ensure_schema($conn);
        $limit = max(1, min(100, $limit));
        $stmt = $conn->prepare(
            "SELECT e.*, COALESCE(submitter.name, submitter.username, '') AS submitted_by_name, COALESCE(reviewer.name, reviewer.username, '') AS reviewed_by_name
             FROM student_absence_excuses e
             LEFT JOIN users submitter ON submitter.id = e.submitted_by
             LEFT JOIN users reviewer ON reviewer.id = e.reviewed_by
             WHERE e.student_id = ? AND e.deleted_at IS NULL
             ORDER BY e.absence_date DESC, e.id DESC
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
