<?php

if (!function_exists('manual_dtr_table_columns')) {
    function manual_dtr_table_columns(mysqli $conn, string $table): array
    {
        $columns = [];
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($safeTable === '') {
            return $columns;
        }
        $result = $conn->query("SHOW COLUMNS FROM {$safeTable}");
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $columns[strtolower((string)($row['Field'] ?? ''))] = true;
            }
            $result->close();
        }
        return $columns;
    }
}

if (!function_exists('manual_dtr_add_column_if_missing')) {
    function manual_dtr_add_column_if_missing(mysqli $conn, string $table, string $column, string $sql): void
    {
        $columns = manual_dtr_table_columns($conn, $table);
        if (!isset($columns[strtolower($column)])) {
            $conn->query($sql);
        }
    }
}

if (!function_exists('manual_dtr_requests_ensure_schema')) {
    function manual_dtr_requests_ensure_schema(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS manual_dtr_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            attendance_id INT NOT NULL,
            attendance_date DATE NULL,
            file_path VARCHAR(255) NOT NULL DEFAULT '',
            file_name VARCHAR(255) NOT NULL DEFAULT '',
            file_type VARCHAR(100) NOT NULL DEFAULT '',
            file_size INT NOT NULL DEFAULT 0,
            reason TEXT NULL,
            uploaded_by INT NULL,
            storage_driver VARCHAR(30) NOT NULL DEFAULT 'filesystem',
            file_blob LONGBLOB NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            deleted_at DATETIME NULL,
            INDEX idx_manual_dtr_student (student_id),
            INDEX idx_manual_dtr_attendance (attendance_id),
            INDEX idx_manual_dtr_deleted (deleted_at)
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS manual_dtr_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            submitted_by INT NULL,
            track VARCHAR(20) NOT NULL DEFAULT 'internal',
            date_from DATE NOT NULL,
            date_to DATE NOT NULL,
            day_count INT NOT NULL DEFAULT 0,
            total_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            reason_category VARCHAR(80) NOT NULL DEFAULT '',
            reason_details VARCHAR(500) NULL,
            proof_file_path VARCHAR(255) NOT NULL DEFAULT '',
            proof_file_name VARCHAR(255) NOT NULL DEFAULT '',
            proof_file_type VARCHAR(100) NOT NULL DEFAULT '',
            proof_file_size INT NOT NULL DEFAULT 0,
            proof_storage_driver VARCHAR(30) NOT NULL DEFAULT 'filesystem',
            proof_file_blob LONGBLOB NULL,
            proof_sha1 CHAR(40) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            submitted_ip VARCHAR(64) NULL,
            submitted_user_agent VARCHAR(255) NULL,
            submitted_at DATETIME NULL,
            reviewed_by INT NULL,
            reviewed_at DATETIME NULL,
            review_note VARCHAR(500) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            INDEX idx_manual_dtr_req_student_status (student_id, status),
            INDEX idx_manual_dtr_req_dates (date_from, date_to),
            INDEX idx_manual_dtr_req_submitted (submitted_at)
        )");

        $conn->query("CREATE TABLE IF NOT EXISTS manual_dtr_request_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            attendance_id INT NULL,
            attendance_date DATE NOT NULL,
            morning_time_in TIME NULL,
            morning_time_out TIME NULL,
            afternoon_time_in TIME NULL,
            afternoon_time_out TIME NULL,
            total_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            conflict_note VARCHAR(500) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            INDEX idx_manual_dtr_entry_request (request_id),
            INDEX idx_manual_dtr_entry_attendance (attendance_id),
            INDEX idx_manual_dtr_entry_date (attendance_date)
        )");

        manual_dtr_add_column_if_missing($conn, 'manual_dtr_attachments', 'request_id', 'ALTER TABLE manual_dtr_attachments ADD COLUMN request_id INT NULL AFTER attendance_id');
        manual_dtr_add_column_if_missing($conn, 'manual_dtr_attachments', 'proof_sha1', 'ALTER TABLE manual_dtr_attachments ADD COLUMN proof_sha1 CHAR(40) NULL AFTER file_blob');
    }
}

if (!function_exists('manual_dtr_client_ip')) {
    function manual_dtr_client_ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = trim((string)($_SERVER[$key] ?? ''));
            if ($value !== '') {
                return substr(trim(explode(',', $value)[0]), 0, 64);
            }
        }
        return '';
    }
}

if (!function_exists('manual_dtr_valid_categories')) {
    function manual_dtr_valid_categories(): array
    {
        return [
            'machine_unavailable' => 'Biometric machine unavailable',
            'fingerprint_not_detected' => 'Fingerprint not detected',
            'power_or_internet_issue' => 'Power or internet issue',
            'admin_instruction' => 'Admin instructed manual entry',
            'external_weekly_dtr' => 'External weekly/monthly DTR',
            'other' => 'Other',
        ];
    }
}

if (!function_exists('manual_dtr_category_label')) {
    function manual_dtr_category_label(string $category): string
    {
        $categories = manual_dtr_valid_categories();
        return $categories[$category] ?? 'Other';
    }
}

if (!function_exists('manual_dtr_request_status_for_entries')) {
    function manual_dtr_request_status_for_entries(mysqli $conn, int $requestId): string
    {
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN LOWER(COALESCE(status, 'pending')) = 'approved' THEN 1 ELSE 0 END) AS approved_count,
                SUM(CASE WHEN LOWER(COALESCE(status, 'pending')) = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                COUNT(*) AS total_count
            FROM manual_dtr_request_entries
            WHERE request_id = ?
        ");
        if (!$stmt) {
            return 'pending';
        }
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        $total = (int)($row['total_count'] ?? 0);
        $approved = (int)($row['approved_count'] ?? 0);
        $rejected = (int)($row['rejected_count'] ?? 0);
        if ($total > 0 && $approved === $total) {
            return 'approved';
        }
        if ($total > 0 && $rejected === $total) {
            return 'rejected';
        }
        if ($approved > 0 || $rejected > 0) {
            return 'partially_reviewed';
        }
        return 'pending';
    }
}

if (!function_exists('manual_dtr_requests_sync_for_attendance_ids')) {
    function manual_dtr_requests_sync_for_attendance_ids(mysqli $conn, array $attendanceIds, string $status, int $reviewerId, string $reviewNote = ''): void
    {
        manual_dtr_requests_ensure_schema($conn);
        $ids = array_values(array_unique(array_filter(array_map('intval', $attendanceIds))));
        if ($ids === []) {
            return;
        }
        $status = strtolower(trim($status));
        $status = in_array($status, ['approved', 'rejected'], true) ? $status : 'pending';
        $idList = implode(',', $ids);
        $safeNote = trim($reviewNote);
        $stmt = $conn->prepare("
            UPDATE manual_dtr_request_entries
            SET status = ?, updated_at = NOW()
            WHERE attendance_id IN ({$idList})
        ");
        if ($stmt) {
            $stmt->bind_param('s', $status);
            $stmt->execute();
            $stmt->close();
        }

        $requestIds = [];
        $res = $conn->query("SELECT DISTINCT request_id FROM manual_dtr_request_entries WHERE attendance_id IN ({$idList})");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $requestIds[] = (int)($row['request_id'] ?? 0);
            }
            $res->close();
        }

        foreach (array_unique(array_filter($requestIds)) as $requestId) {
            $requestStatus = manual_dtr_request_status_for_entries($conn, (int)$requestId);
            $update = $conn->prepare("
                UPDATE manual_dtr_requests
                SET status = ?,
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    review_note = CASE WHEN ? <> '' THEN ? ELSE review_note END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            if ($update) {
                $update->bind_param('sissi', $requestStatus, $reviewerId, $safeNote, $safeNote, $requestId);
                $update->execute();
                $update->close();
            }
        }
    }
}
