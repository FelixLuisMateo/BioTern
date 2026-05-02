<?php

if (!function_exists('biotern_masterlist_table_exists')) {
    function biotern_masterlist_table_exists(mysqli $conn, string $table): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('biotern_masterlist_column_exists')) {
    function biotern_masterlist_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('biotern_masterlist_lookup_key')) {
    function biotern_masterlist_lookup_key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
        return $value;
    }
}

if (!function_exists('biotern_masterlist_fetch_for_student')) {
    function biotern_masterlist_fetch_for_student(mysqli $conn, array $student): array
    {
        if (!biotern_masterlist_table_exists($conn, 'ojt_masterlist')) {
            return [];
        }

        $studentNo = trim((string)($student['student_id'] ?? $student['student_no'] ?? ''));
        $studentName = trim((string)(($student['first_name'] ?? '') . ' ' . (!empty($student['middle_name']) ? ($student['middle_name'] . ' ') : '') . ($student['last_name'] ?? '')));
        $lookupKey = biotern_masterlist_lookup_key($studentNo !== '' ? $studentNo : $studentName);

        $where = [];
        $types = '';
        $params = [];

        if ($studentNo !== '' && biotern_masterlist_column_exists($conn, 'ojt_masterlist', 'student_no')) {
            $where[] = 'TRIM(COALESCE(student_no, "")) = ?';
            $types .= 's';
            $params[] = $studentNo;
        }

        if ($lookupKey !== '' && biotern_masterlist_column_exists($conn, 'ojt_masterlist', 'student_lookup_key')) {
            $where[] = 'TRIM(COALESCE(student_lookup_key, "")) = ?';
            $types .= 's';
            $params[] = $lookupKey;
        }

        if ($studentName !== '' && biotern_masterlist_column_exists($conn, 'ojt_masterlist', 'student_name')) {
            $where[] = 'LOWER(TRIM(COALESCE(student_name, ""))) = LOWER(?)';
            $types .= 's';
            $params[] = $studentName;
        }

        if ($where === []) {
            return [];
        }

        $sql = 'SELECT * FROM ojt_masterlist WHERE (' . implode(' OR ', $where) . ') ORDER BY id DESC LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return is_array($row) ? $row : [];
    }
}

if (!function_exists('biotern_masterlist_endorsement_defaults')) {
    function biotern_masterlist_endorsement_defaults(array $masterlist, array $student = []): array
    {
        $representative = trim((string)($masterlist['company_representative'] ?? ''));
        $representativePosition = trim((string)($masterlist['company_representative_position'] ?? ''));
        $supervisor = trim((string)($masterlist['supervisor_name'] ?? ''));
        $supervisorPosition = trim((string)($masterlist['supervisor_position'] ?? ''));

        return [
            'recipient_name' => $representative !== '' ? $representative : $supervisor,
            'recipient_position' => $representativePosition !== '' ? $representativePosition : $supervisorPosition,
            'company_name' => trim((string)($masterlist['company_name'] ?? $masterlist['company'] ?? '')),
            'company_address' => trim((string)($masterlist['company_address'] ?? $masterlist['address'] ?? '')),
            'supervisor_name' => $supervisor,
            'supervisor_position' => $supervisorPosition,
            'student_name' => trim((string)($masterlist['student_name'] ?? '')),
            'student_no' => trim((string)($masterlist['student_no'] ?? ($student['student_id'] ?? ''))),
            'section' => trim((string)($masterlist['section'] ?? '')),
            'status' => trim((string)($masterlist['status'] ?? '')),
            'recipient_title' => 'auto',
        ];
    }
}

