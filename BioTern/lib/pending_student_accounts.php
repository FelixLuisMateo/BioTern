<?php

if (!function_exists('biotern_pending_accounts_lookup_key')) {
    function biotern_pending_accounts_lookup_key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '', $value);
        return (string)$value;
    }
}

if (!function_exists('biotern_pending_accounts_ensure_table')) {
    function biotern_pending_accounts_ensure_table(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS student_pending_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_type VARCHAR(40) NOT NULL DEFAULT 'unknown',
            source_workbook VARCHAR(255) NOT NULL DEFAULT '',
            source_sheet VARCHAR(120) NOT NULL DEFAULT '',
            source_row_number INT NOT NULL DEFAULT 0,
            student_no VARCHAR(100) NOT NULL DEFAULT '',
            student_lookup_key VARCHAR(190) NOT NULL DEFAULT '',
            student_name VARCHAR(255) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            school_year VARCHAR(30) NOT NULL DEFAULT '',
            semester VARCHAR(50) NOT NULL DEFAULT '',
            assignment_track VARCHAR(30) NOT NULL DEFAULT '',
            section_label VARCHAR(120) NOT NULL DEFAULT '',
            course_id INT NULL,
            section_id INT NULL,
            raw_payload LONGTEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            matched_student_id INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_pending_student_source (source_type, school_year, semester, assignment_track, student_lookup_key),
            KEY idx_pending_status (status),
            KEY idx_pending_student_no (student_no),
            KEY idx_pending_period (school_year, semester)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('biotern_pending_accounts_record')) {
    function biotern_pending_accounts_record(mysqli $conn, array $payload): void
    {
        biotern_pending_accounts_ensure_table($conn);

        $studentNo = trim((string)($payload['student_no'] ?? $payload['student_id'] ?? ''));
        $studentName = trim((string)($payload['student_name'] ?? ''));
        $lookupKey = biotern_pending_accounts_lookup_key($studentNo !== '' ? $studentNo : $studentName);
        if ($lookupKey === '') {
            return;
        }

        $rawJson = json_encode($payload['raw_payload'] ?? $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($rawJson)) {
            $rawJson = '{}';
        }

        $sourceType = trim((string)($payload['source_type'] ?? 'unknown'));
        $sourceWorkbook = trim((string)($payload['source_workbook'] ?? ''));
        $sourceSheet = trim((string)($payload['source_sheet'] ?? ''));
        $sourceRow = (int)($payload['source_row_number'] ?? 0);
        $email = trim((string)($payload['email'] ?? ''));
        $schoolYear = trim((string)($payload['school_year'] ?? ''));
        $semester = trim((string)($payload['semester'] ?? ''));
        $track = trim((string)($payload['assignment_track'] ?? $payload['track'] ?? ''));
        $sectionLabel = trim((string)($payload['section_label'] ?? $payload['section'] ?? ''));
        $courseId = isset($payload['course_id']) && (int)$payload['course_id'] > 0 ? (int)$payload['course_id'] : null;
        $sectionId = isset($payload['section_id']) && (int)$payload['section_id'] > 0 ? (int)$payload['section_id'] : null;
        $status = trim((string)($payload['status'] ?? 'pending'));
        $matchedStudentId = isset($payload['matched_student_id']) && (int)$payload['matched_student_id'] > 0 ? (int)$payload['matched_student_id'] : null;

        $stmt = $conn->prepare("INSERT INTO student_pending_accounts
            (source_type, source_workbook, source_sheet, source_row_number, student_no, student_lookup_key, student_name, email, school_year, semester, assignment_track, section_label, course_id, section_id, raw_payload, status, matched_student_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                source_workbook = VALUES(source_workbook),
                source_sheet = VALUES(source_sheet),
                source_row_number = VALUES(source_row_number),
                student_no = VALUES(student_no),
                student_name = VALUES(student_name),
                email = VALUES(email),
                section_label = VALUES(section_label),
                course_id = VALUES(course_id),
                section_id = VALUES(section_id),
                raw_payload = VALUES(raw_payload),
                status = VALUES(status),
                matched_student_id = VALUES(matched_student_id),
                updated_at = NOW()");
        if (!$stmt) {
            return;
        }

        $stmt->bind_param(
            'sssissssssssiissi',
            $sourceType,
            $sourceWorkbook,
            $sourceSheet,
            $sourceRow,
            $studentNo,
            $lookupKey,
            $studentName,
            $email,
            $schoolYear,
            $semester,
            $track,
            $sectionLabel,
            $courseId,
            $sectionId,
            $rawJson,
            $status,
            $matchedStudentId
        );
        $stmt->execute();
        $stmt->close();
    }
}
