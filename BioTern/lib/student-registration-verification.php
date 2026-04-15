<?php

if (!function_exists('biotern_student_reg_normalize_token')) {
    function biotern_student_reg_normalize_token(string $token): string
    {
        $normalized = strtolower(trim($token));
        if ($normalized === '' || !preg_match('/^[a-f0-9]{64}$/', $normalized)) {
            return '';
        }

        return $normalized;
    }
}

if (!function_exists('biotern_student_reg_generate_token')) {
    function biotern_student_reg_generate_token(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            return hash('sha256', uniqid('student-reg-', true) . '|' . mt_rand());
        }
    }
}

if (!function_exists('biotern_student_reg_ensure_table')) {
    function biotern_student_reg_ensure_table(mysqli $mysqli): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS student_registration_verifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            verify_token CHAR(64) NOT NULL,
            target_email VARCHAR(255) NOT NULL,
            verify_code CHAR(6) NOT NULL,
            pending_post MEDIUMTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_student_reg_verify_token (verify_token),
            KEY idx_student_reg_verify_expires (expires_at),
            KEY idx_student_reg_verify_email (target_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        return (bool)$mysqli->query($sql);
    }
}

if (!function_exists('biotern_student_reg_store_pending')) {
    function biotern_student_reg_store_pending(mysqli $mysqli, string $token, string $email, string $code, array $pendingPost, int $expiresAt): bool
    {
        $normalizedToken = biotern_student_reg_normalize_token($token);
        $safeEmail = trim($email);
        $safeCode = trim($code);

        if ($normalizedToken === '' || $safeEmail === '' || !filter_var($safeEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (!preg_match('/^\d{6}$/', $safeCode)) {
            return false;
        }

        if (!biotern_student_reg_ensure_table($mysqli)) {
            return false;
        }

        $encoded = json_encode($pendingPost, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            return false;
        }

        $expiresAtSql = date('Y-m-d H:i:s', max($expiresAt, time() + 60));

        $stmt = $mysqli->prepare("INSERT INTO student_registration_verifications (verify_token, target_email, verify_code, pending_post, expires_at, consumed_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NULL, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                target_email = VALUES(target_email),
                verify_code = VALUES(verify_code),
                pending_post = VALUES(pending_post),
                expires_at = VALUES(expires_at),
                consumed_at = NULL,
                updated_at = NOW()");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('sssss', $normalizedToken, $safeEmail, $safeCode, $encoded, $expiresAtSql);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            // Opportunistic cleanup to prevent unbounded growth.
            $mysqli->query("DELETE FROM student_registration_verifications WHERE expires_at < (NOW() - INTERVAL 2 DAY)");
        }

        return (bool)$ok;
    }
}

if (!function_exists('biotern_student_reg_load_pending')) {
    function biotern_student_reg_load_pending(mysqli $mysqli, string $token): ?array
    {
        $normalizedToken = biotern_student_reg_normalize_token($token);
        if ($normalizedToken === '') {
            return null;
        }

        if (!biotern_student_reg_ensure_table($mysqli)) {
            return null;
        }

        $stmt = $mysqli->prepare("SELECT verify_token, target_email, verify_code, pending_post, UNIX_TIMESTAMP(expires_at) AS expires_at_ts, consumed_at
            FROM student_registration_verifications
            WHERE verify_token = ?
            LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $normalizedToken);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return null;
        }

        if (!empty($row['consumed_at'])) {
            return null;
        }

        $pending = json_decode((string)($row['pending_post'] ?? ''), true);
        if (!is_array($pending)) {
            return null;
        }

        $expiresAt = (int)($row['expires_at_ts'] ?? 0);
        return [
            'token' => (string)($row['verify_token'] ?? ''),
            'target_email' => trim((string)($row['target_email'] ?? '')),
            'verify_code' => trim((string)($row['verify_code'] ?? '')),
            'pending_post' => $pending,
            'expires_at' => $expiresAt,
        ];
    }
}

if (!function_exists('biotern_student_reg_update_code')) {
    function biotern_student_reg_update_code(mysqli $mysqli, string $token, string $newCode, int $newExpiresAt): bool
    {
        $normalizedToken = biotern_student_reg_normalize_token($token);
        $safeCode = trim($newCode);
        if ($normalizedToken === '' || !preg_match('/^\d{6}$/', $safeCode)) {
            return false;
        }

        if (!biotern_student_reg_ensure_table($mysqli)) {
            return false;
        }

        $expiresAtSql = date('Y-m-d H:i:s', max($newExpiresAt, time() + 60));
        $stmt = $mysqli->prepare("UPDATE student_registration_verifications
            SET verify_code = ?, expires_at = ?, consumed_at = NULL, updated_at = NOW()
            WHERE verify_token = ?
            LIMIT 1");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('sss', $safeCode, $expiresAtSql, $normalizedToken);
        $ok = $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return (bool)$ok && $affected >= 0;
    }
}

if (!function_exists('biotern_student_reg_consume')) {
    function biotern_student_reg_consume(mysqli $mysqli, string $token): void
    {
        $normalizedToken = biotern_student_reg_normalize_token($token);
        if ($normalizedToken === '' || !biotern_student_reg_ensure_table($mysqli)) {
            return;
        }

        $stmt = $mysqli->prepare("UPDATE student_registration_verifications
            SET consumed_at = NOW(), updated_at = NOW()
            WHERE verify_token = ?
            LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $normalizedToken);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('biotern_login_email_verify_ensure_table')) {
    function biotern_login_email_verify_ensure_table(mysqli $mysqli): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS login_email_verifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            verify_token CHAR(64) NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            target_email VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_login_email_verify_token (verify_token),
            KEY idx_login_email_verify_user_id (user_id),
            KEY idx_login_email_verify_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        return (bool)$mysqli->query($sql);
    }
}

if (!function_exists('biotern_login_email_verify_store')) {
    function biotern_login_email_verify_store(mysqli $mysqli, string $token, int $userId, string $email, int $expiresAt): bool
    {
        $normalizedToken = biotern_student_reg_normalize_token($token);
        $safeEmail = trim($email);
        $safeUserId = (int)$userId;

        if ($normalizedToken === '' || $safeUserId <= 0 || $safeEmail === '' || !filter_var($safeEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (!biotern_login_email_verify_ensure_table($mysqli)) {
            return false;
        }

        $expiresAtSql = date('Y-m-d H:i:s', max($expiresAt, time() + 60));
        $stmt = $mysqli->prepare("INSERT INTO login_email_verifications (verify_token, user_id, target_email, expires_at, consumed_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, NULL, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                target_email = VALUES(target_email),
                expires_at = VALUES(expires_at),
                consumed_at = NULL,
                updated_at = NOW()");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('siss', $normalizedToken, $safeUserId, $safeEmail, $expiresAtSql);
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $mysqli->query("DELETE FROM login_email_verifications WHERE expires_at < (NOW() - INTERVAL 2 DAY)");
        }

        return (bool)$ok;
    }
}

if (!function_exists('biotern_login_email_verify_load')) {
    function biotern_login_email_verify_load(mysqli $mysqli, string $token): ?array
    {
        $normalizedToken = biotern_student_reg_normalize_token($token);
        if ($normalizedToken === '') {
            return null;
        }

        if (!biotern_login_email_verify_ensure_table($mysqli)) {
            return null;
        }

        $stmt = $mysqli->prepare("SELECT verify_token, user_id, target_email, UNIX_TIMESTAMP(expires_at) AS expires_at_ts, consumed_at
            FROM login_email_verifications
            WHERE verify_token = ?
            LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $normalizedToken);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !empty($row['consumed_at'])) {
            return null;
        }

        return [
            'token' => (string)($row['verify_token'] ?? ''),
            'user_id' => (int)($row['user_id'] ?? 0),
            'target_email' => trim((string)($row['target_email'] ?? '')),
            'expires_at' => (int)($row['expires_at_ts'] ?? 0),
        ];
    }
}

if (!function_exists('biotern_login_email_verify_consume')) {
    function biotern_login_email_verify_consume(mysqli $mysqli, string $token): void
    {
        $normalizedToken = biotern_student_reg_normalize_token($token);
        if ($normalizedToken === '' || !biotern_login_email_verify_ensure_table($mysqli)) {
            return;
        }

        $stmt = $mysqli->prepare("UPDATE login_email_verifications
            SET consumed_at = NOW(), updated_at = NOW()
            WHERE verify_token = ?
            LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $normalizedToken);
            $stmt->execute();
            $stmt->close();
        }
    }
}
