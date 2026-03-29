<?php

if (!function_exists('biometric_ops_ensure_tables')) {
    function biometric_ops_ensure_tables(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS biometric_audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                actor_user_id BIGINT UNSIGNED NULL,
                actor_role VARCHAR(50) NULL,
                action VARCHAR(100) NOT NULL,
                target_type VARCHAR(100) NOT NULL,
                target_id VARCHAR(100) NULL,
                details_json LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_biometric_audit_created (created_at),
                INDEX idx_biometric_audit_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS biometric_sync_runs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                initiated_by_user_id BIGINT UNSIGNED NULL,
                trigger_source VARCHAR(50) NOT NULL DEFAULT 'manual',
                status VARCHAR(20) NOT NULL DEFAULT 'running',
                connector_output LONGTEXT NULL,
                import_summary LONGTEXT NULL,
                raw_inserted INT NOT NULL DEFAULT 0,
                processed_logs INT NOT NULL DEFAULT 0,
                attendance_changed INT NOT NULL DEFAULT 0,
                anomalies_found INT NOT NULL DEFAULT 0,
                started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                finished_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_biometric_sync_started (started_at),
                INDEX idx_biometric_sync_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS biometric_anomalies (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                raw_log_id BIGINT UNSIGNED NULL,
                fingerprint_id INT NULL,
                user_id BIGINT UNSIGNED NULL,
                student_id BIGINT UNSIGNED NULL,
                anomaly_type VARCHAR(100) NOT NULL,
                severity VARCHAR(20) NOT NULL DEFAULT 'warning',
                event_time DATETIME NULL,
                message TEXT NOT NULL,
                details_json LONGTEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_biometric_anomaly (raw_log_id, anomaly_type),
                INDEX idx_biometric_anomaly_status (status),
                INDEX idx_biometric_anomaly_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

if (!function_exists('biometric_ops_log_audit')) {
    function biometric_ops_log_audit(mysqli $conn, ?int $actorUserId, ?string $actorRole, string $action, string $targetType, ?string $targetId = null, ?array $details = null): void
    {
        biometric_ops_ensure_tables($conn);

        $detailsJson = $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $stmt = $conn->prepare("
            INSERT INTO biometric_audit_logs (actor_user_id, actor_role, action, target_type, target_id, details_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param(
            'isssss',
            $actorUserId,
            $actorRole,
            $action,
            $targetType,
            $targetId,
            $detailsJson
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('biometric_ops_start_sync_run')) {
    function biometric_ops_start_sync_run(mysqli $conn, ?int $initiatedByUserId, string $triggerSource = 'manual'): int
    {
        biometric_ops_ensure_tables($conn);

        $stmt = $conn->prepare("
            INSERT INTO biometric_sync_runs (initiated_by_user_id, trigger_source, status)
            VALUES (?, ?, 'running')
        ");
        if ($stmt === false) {
            return 0;
        }
        $stmt->bind_param('is', $initiatedByUserId, $triggerSource);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }
}

if (!function_exists('biometric_ops_finish_sync_run')) {
    function biometric_ops_finish_sync_run(mysqli $conn, int $syncRunId, string $status, ?string $connectorOutput, ?string $importSummary, int $rawInserted, int $processedLogs, int $attendanceChanged, int $anomaliesFound): void
    {
        biometric_ops_ensure_tables($conn);

        if ($syncRunId <= 0) {
            return;
        }

        $stmt = $conn->prepare("
            UPDATE biometric_sync_runs
            SET status = ?,
                connector_output = ?,
                import_summary = ?,
                raw_inserted = ?,
                processed_logs = ?,
                attendance_changed = ?,
                anomalies_found = ?,
                finished_at = NOW()
            WHERE id = ?
        ");
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param('sssiiiii', $status, $connectorOutput, $importSummary, $rawInserted, $processedLogs, $attendanceChanged, $anomaliesFound, $syncRunId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('biometric_ops_record_anomaly')) {
    function biometric_ops_record_anomaly(mysqli $conn, ?int $rawLogId, ?int $fingerprintId, ?int $userId, ?int $studentId, string $anomalyType, string $severity, ?string $eventTime, string $message, ?array $details = null): void
    {
        biometric_ops_ensure_tables($conn);

        $detailsJson = $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $stmt = $conn->prepare("
            INSERT INTO biometric_anomalies (raw_log_id, fingerprint_id, user_id, student_id, anomaly_type, severity, event_time, message, details_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                severity = VALUES(severity),
                event_time = VALUES(event_time),
                message = VALUES(message),
                details_json = VALUES(details_json),
                updated_at = CURRENT_TIMESTAMP
        ");
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param('iiiisssss', $rawLogId, $fingerprintId, $userId, $studentId, $anomalyType, $severity, $eventTime, $message, $detailsJson);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('biometric_ops_fetch_latest_sync_run')) {
    function biometric_ops_fetch_latest_sync_run(mysqli $conn): ?array
    {
        biometric_ops_ensure_tables($conn);

        $res = $conn->query("SELECT * FROM biometric_sync_runs ORDER BY id DESC LIMIT 1");
        if (!$res instanceof mysqli_result) {
            return null;
        }
        $row = $res->fetch_assoc() ?: null;
        $res->close();
        return $row;
    }
}

if (!function_exists('biometric_ops_fetch_recent_anomalies')) {
    function biometric_ops_fetch_recent_anomalies(mysqli $conn, int $limit = 10): array
    {
        biometric_ops_ensure_tables($conn);

        $limit = max(1, min($limit, 50));
        $rows = [];
        $res = $conn->query("
            SELECT
                a.*,
                u.name AS mapped_user_name,
                u.username AS mapped_username,
                s.first_name AS student_first_name,
                s.last_name AS student_last_name,
                s.student_id AS student_number
            FROM biometric_anomalies a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN students s ON a.student_id = s.id
            ORDER BY a.created_at DESC, a.id DESC
            LIMIT {$limit}
        ");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->close();
        }
        return $rows;
    }
}

if (!function_exists('biometric_ops_fetch_recent_audit_logs')) {
    function biometric_ops_fetch_recent_audit_logs(mysqli $conn, int $limit = 10): array
    {
        biometric_ops_ensure_tables($conn);

        $limit = max(1, min($limit, 50));
        $rows = [];
        $res = $conn->query("
            SELECT *
            FROM biometric_audit_logs
            ORDER BY created_at DESC, id DESC
            LIMIT {$limit}
        ");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->close();
        }
        return $rows;
    }
}

if (!function_exists('biometric_ops_fetch_open_anomaly_count')) {
    function biometric_ops_fetch_open_anomaly_count(mysqli $conn): int
    {
        biometric_ops_ensure_tables($conn);

        $res = $conn->query("SELECT COUNT(*) AS total FROM biometric_anomalies WHERE status = 'open'");
        if (!$res instanceof mysqli_result) {
            return 0;
        }
        $row = $res->fetch_assoc() ?: ['total' => 0];
        $res->close();
        return (int)($row['total'] ?? 0);
    }
}
