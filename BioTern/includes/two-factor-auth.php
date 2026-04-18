<?php

if (!function_exists('biotern_two_factor_db')) {
    function biotern_two_factor_db($conn = null)
    {
        if (function_exists('biotern_auth_session_db')) {
            $db = biotern_auth_session_db($conn);
            if ($db instanceof mysqli && !$db->connect_errno) {
                return $db;
            }
        }

        if ($conn instanceof mysqli && !$conn->connect_errno) {
            return $conn;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli && !$GLOBALS['conn']->connect_errno) {
            return $GLOBALS['conn'];
        }

        return null;
    }
}

if (!function_exists('biotern_two_factor_pending_key')) {
    function biotern_two_factor_pending_key(): string
    {
        return 'biotern_two_factor_pending_login';
    }
}

if (!function_exists('biotern_two_factor_pending_ttl_seconds')) {
    function biotern_two_factor_pending_ttl_seconds(): int
    {
        return 900;
    }
}

if (!function_exists('biotern_two_factor_code_ttl_seconds')) {
    function biotern_two_factor_code_ttl_seconds(): int
    {
        return 600;
    }
}

if (!function_exists('biotern_two_factor_pending_cookie_name')) {
    function biotern_two_factor_pending_cookie_name(): string
    {
        return 'biotern_two_factor_pending';
    }
}

if (!function_exists('biotern_two_factor_cookie_key')) {
    function biotern_two_factor_cookie_key(): string
    {
        if (function_exists('biotern_auth_cookie_key')) {
            return (string)biotern_auth_cookie_key();
        }

        return 'biotern-fallback-auth-key';
    }
}

if (!function_exists('biotern_two_factor_cookie_options')) {
    function biotern_two_factor_cookie_options(int $expires): array
    {
        if (function_exists('biotern_auth_cookie_options')) {
            return biotern_auth_cookie_options($expires);
        }

        $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwardedProto !== '' && strpos($forwardedProto, ',') !== false) {
            $parts = explode(',', $forwardedProto);
            $forwardedProto = strtolower(trim((string)($parts[0] ?? '')));
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || ($forwardedProto === 'https');

        return [
            'expires' => (int)$expires,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('biotern_two_factor_set_pending_cookie')) {
    function biotern_two_factor_set_pending_cookie(int $userId, int $createdAt, string $identifier, string $next): void
    {
        $userId = (int)$userId;
        $createdAt = (int)$createdAt;
        if ($userId <= 0 || $createdAt <= 0) {
            return;
        }

        $identifier = substr(trim($identifier), 0, 191);
        $next = basename(trim($next));
        if ($next !== '' && preg_match('/^[A-Za-z0-9_-]+\.php$/', $next) !== 1) {
            $next = '';
        }

        $payload = $userId . '|' . $createdAt . '|' . base64_encode($next) . '|' . base64_encode($identifier);
        $signature = hash_hmac('sha256', $payload, biotern_two_factor_cookie_key());
        $token = base64_encode($payload . '|' . $signature);

        if (!headers_sent()) {
            setcookie(
                biotern_two_factor_pending_cookie_name(),
                $token,
                biotern_two_factor_cookie_options($createdAt + biotern_two_factor_pending_ttl_seconds())
            );
        }

        $_COOKIE[biotern_two_factor_pending_cookie_name()] = $token;
    }
}

if (!function_exists('biotern_two_factor_clear_pending_cookie')) {
    function biotern_two_factor_clear_pending_cookie(): void
    {
        if (!headers_sent()) {
            setcookie(
                biotern_two_factor_pending_cookie_name(),
                '',
                biotern_two_factor_cookie_options(time() - 3600)
            );
        }

        unset($_COOKIE[biotern_two_factor_pending_cookie_name()]);
    }
}

if (!function_exists('biotern_two_factor_table_has_column')) {
    function biotern_two_factor_table_has_column(mysqli $conn, string $table, string $column): bool
    {
        $safeTable = str_replace('`', '``', trim($table));
        $safeColumn = $conn->real_escape_string($column);
        if ($safeTable === '') {
            return false;
        }

        $result = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('biotern_two_factor_ensure_settings_table')) {
    function biotern_two_factor_ensure_settings_table(mysqli $conn): bool
    {
        $created = $conn->query("CREATE TABLE IF NOT EXISTS user_security_settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_security_settings_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$created) {
            return false;
        }

        $requiredColumns = [
            'user_id' => 'INT NOT NULL',
            'two_factor_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];

        foreach ($requiredColumns as $column => $definition) {
            if (!biotern_two_factor_table_has_column($conn, 'user_security_settings', $column)) {
                $safeColumn = str_replace('`', '``', $column);
                $conn->query("ALTER TABLE user_security_settings ADD COLUMN `{$safeColumn}` {$definition}");
            }
        }

        $conn->query('CREATE UNIQUE INDEX uq_user_security_settings_user ON user_security_settings (user_id)');

        return true;
    }
}

if (!function_exists('biotern_two_factor_ensure_codes_table')) {
    function biotern_two_factor_ensure_codes_table(mysqli $conn): bool
    {
        $created = $conn->query("CREATE TABLE IF NOT EXISTS user_two_factor_codes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            purpose VARCHAR(30) NOT NULL DEFAULT 'login',
            code_hash CHAR(64) NOT NULL,
            attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            PRIMARY KEY (id),
            INDEX idx_user_two_factor_lookup (user_id, purpose, consumed_at, expires_at),
            INDEX idx_user_two_factor_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$created) {
            return false;
        }

        $requiredColumns = [
            'user_id' => 'INT NOT NULL',
            'purpose' => "VARCHAR(30) NOT NULL DEFAULT 'login'",
            'code_hash' => 'CHAR(64) NOT NULL',
            'attempt_count' => 'SMALLINT UNSIGNED NOT NULL DEFAULT 0',
            'ip_address' => 'VARCHAR(45) NULL',
            'user_agent' => 'VARCHAR(255) NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'expires_at' => 'DATETIME NOT NULL',
            'consumed_at' => 'DATETIME NULL',
        ];

        foreach ($requiredColumns as $column => $definition) {
            if (!biotern_two_factor_table_has_column($conn, 'user_two_factor_codes', $column)) {
                $safeColumn = str_replace('`', '``', $column);
                $conn->query("ALTER TABLE user_two_factor_codes ADD COLUMN `{$safeColumn}` {$definition}");
            }
        }

        $conn->query('CREATE INDEX idx_user_two_factor_lookup ON user_two_factor_codes (user_id, purpose, consumed_at, expires_at)');
        $conn->query('CREATE INDEX idx_user_two_factor_created ON user_two_factor_codes (created_at)');

        return true;
    }
}

if (!function_exists('biotern_two_factor_ensure_schema')) {
    function biotern_two_factor_ensure_schema($conn): bool
    {
        $db = biotern_two_factor_db($conn);
        if (!($db instanceof mysqli) || $db->connect_errno) {
            return false;
        }

        static $initialized = false;
        if ($initialized) {
            return true;
        }

        $ok = biotern_two_factor_ensure_settings_table($db) && biotern_two_factor_ensure_codes_table($db);
        if ($ok) {
            $initialized = true;
        }

        return $ok;
    }
}

if (!function_exists('biotern_two_factor_is_enabled')) {
    function biotern_two_factor_is_enabled($conn, int $userId): bool
    {
        $db = biotern_two_factor_db($conn);
        $userId = (int)$userId;
        if (!($db instanceof mysqli) || $db->connect_errno || $userId <= 0 || !biotern_two_factor_ensure_schema($db)) {
            return false;
        }

        $stmt = $db->prepare('SELECT two_factor_enabled FROM user_security_settings WHERE user_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (int)($row['two_factor_enabled'] ?? 0) === 1;
    }
}

if (!function_exists('biotern_two_factor_set_enabled')) {
    function biotern_two_factor_set_enabled($conn, int $userId, bool $enabled): bool
    {
        $db = biotern_two_factor_db($conn);
        $userId = (int)$userId;
        if (!($db instanceof mysqli) || $db->connect_errno || $userId <= 0 || !biotern_two_factor_ensure_schema($db)) {
            return false;
        }

        $flag = $enabled ? 1 : 0;
        $stmt = $db->prepare("INSERT INTO user_security_settings (user_id, two_factor_enabled, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE two_factor_enabled = VALUES(two_factor_enabled), updated_at = NOW()");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $userId, $flag);
        $ok = (bool)$stmt->execute();
        $stmt->close();

        if (!$ok) {
            return false;
        }

        if (!$enabled) {
            $consumeStmt = $db->prepare("UPDATE user_two_factor_codes
                SET consumed_at = NOW()
                WHERE user_id = ?
                  AND purpose = 'login'
                  AND consumed_at IS NULL");
            if ($consumeStmt) {
                $consumeStmt->bind_param('i', $userId);
                $consumeStmt->execute();
                $consumeStmt->close();
            }

            $pending = biotern_two_factor_get_pending_login();
            if (is_array($pending) && (int)($pending['user_id'] ?? 0) === $userId) {
                biotern_two_factor_clear_pending_login();
            }
        }

        return true;
    }
}

if (!function_exists('biotern_two_factor_client_ip')) {
    function biotern_two_factor_client_ip(): string
    {
        if (function_exists('biotern_auth_session_client_ip')) {
            return biotern_auth_session_client_ip();
        }

        $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            return substr(trim((string)($parts[0] ?? '')), 0, 45);
        }

        return substr(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
    }
}

if (!function_exists('biotern_two_factor_user_agent')) {
    function biotern_two_factor_user_agent(): string
    {
        return substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);
    }
}

if (!function_exists('biotern_two_factor_mask_email')) {
    function biotern_two_factor_mask_email(string $email): string
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'No verified email';
        }

        $parts = explode('@', $email, 2);
        $local = $parts[0] ?? '';
        $domain = $parts[1] ?? '';

        if (strlen($local) <= 2) {
            $maskedLocal = substr($local, 0, 1) . '*';
        } else {
            $maskedLocal = substr($local, 0, 1) . str_repeat('*', max(strlen($local) - 2, 1)) . substr($local, -1);
        }

        return $maskedLocal . '@' . $domain;
    }
}

if (!function_exists('biotern_two_factor_generate_code')) {
    function biotern_two_factor_generate_code(): string
    {
        try {
            return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } catch (Throwable $e) {
            return str_pad((string)mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        }
    }
}

if (!function_exists('biotern_two_factor_code_hash')) {
    function biotern_two_factor_code_hash(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        $key = function_exists('biotern_auth_cookie_key')
            ? (string)biotern_auth_cookie_key()
            : 'biotern-fallback-auth-key';

        return hash_hmac('sha256', $code, $key);
    }
}

if (!function_exists('biotern_two_factor_prepare_pending_login')) {
    function biotern_two_factor_prepare_pending_login(int $userId, string $identifier = '', string $next = ''): void
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return;
        }

        $identifier = substr(trim($identifier), 0, 191);
        $next = basename(trim($next));
        if ($next !== '' && preg_match('/^[A-Za-z0-9_-]+\.php$/', $next) !== 1) {
            $next = '';
        }

        $createdAt = time();

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[biotern_two_factor_pending_key()] = [
                'user_id' => $userId,
                'identifier' => $identifier,
                'next' => $next,
                'created_at' => $createdAt,
            ];
        }

        biotern_two_factor_set_pending_cookie($userId, $createdAt, $identifier, $next);
    }
}

if (!function_exists('biotern_two_factor_parse_pending_cookie')) {
    function biotern_two_factor_parse_pending_cookie(): ?array
    {
        $rawToken = trim((string)($_COOKIE[biotern_two_factor_pending_cookie_name()] ?? ''));
        if ($rawToken === '') {
            return null;
        }

        $decoded = base64_decode($rawToken, true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 5) {
            return null;
        }

        $userId = (int)($parts[0] ?? 0);
        $createdAt = (int)($parts[1] ?? 0);
        $nextRaw = base64_decode((string)($parts[2] ?? ''), true);
        $identifierRaw = base64_decode((string)($parts[3] ?? ''), true);
        $signature = (string)($parts[4] ?? '');

        if ($userId <= 0 || $createdAt <= 0 || $signature === '') {
            return null;
        }

        $payload = (string)($parts[0] ?? '') . '|' . (string)($parts[1] ?? '') . '|' . (string)($parts[2] ?? '') . '|' . (string)($parts[3] ?? '');
        $expected = hash_hmac('sha256', $payload, biotern_two_factor_cookie_key());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $age = time() - $createdAt;
        if ($age < 0 || $age > biotern_two_factor_pending_ttl_seconds()) {
            return null;
        }

        $next = basename(trim(is_string($nextRaw) ? $nextRaw : ''));
        if ($next !== '' && preg_match('/^[A-Za-z0-9_-]+\.php$/', $next) !== 1) {
            $next = '';
        }

        return [
            'user_id' => $userId,
            'identifier' => substr(trim(is_string($identifierRaw) ? $identifierRaw : ''), 0, 191),
            'next' => $next,
            'created_at' => $createdAt,
        ];
    }
}

if (!function_exists('biotern_two_factor_pending_payload_is_valid')) {
    function biotern_two_factor_pending_payload_is_valid(array $payload): bool
    {
        $userId = (int)($payload['user_id'] ?? 0);
        $createdAt = (int)($payload['created_at'] ?? 0);
        $age = time() - $createdAt;
        return $userId > 0 && $createdAt > 0 && $age >= 0 && $age <= biotern_two_factor_pending_ttl_seconds();
    }
}

if (!function_exists('biotern_two_factor_normalize_pending_payload')) {
    function biotern_two_factor_normalize_pending_payload(array $payload): array
    {
        $next = basename((string)($payload['next'] ?? ''));
        if ($next !== '' && preg_match('/^[A-Za-z0-9_-]+\.php$/', $next) !== 1) {
            $next = '';
        }

        return [
            'user_id' => (int)($payload['user_id'] ?? 0),
            'identifier' => substr(trim((string)($payload['identifier'] ?? '')), 0, 191),
            'next' => $next,
            'created_at' => (int)($payload['created_at'] ?? 0),
        ];
    }
}

if (!function_exists('biotern_two_factor_get_pending_login')) {
    function biotern_two_factor_get_pending_login(): ?array
    {
        $sessionPayload = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $key = biotern_two_factor_pending_key();
            if (isset($_SESSION[$key]) && is_array($_SESSION[$key])) {
                $sessionPayload = biotern_two_factor_normalize_pending_payload($_SESSION[$key]);
            }
        }

        if (is_array($sessionPayload)) {
            if (biotern_two_factor_pending_payload_is_valid($sessionPayload)) {
                biotern_two_factor_set_pending_cookie(
                    (int)$sessionPayload['user_id'],
                    (int)$sessionPayload['created_at'],
                    (string)$sessionPayload['identifier'],
                    (string)$sessionPayload['next']
                );
                return $sessionPayload;
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                unset($_SESSION[biotern_two_factor_pending_key()]);
            }
        }

        $cookiePayload = biotern_two_factor_parse_pending_cookie();
        if (!is_array($cookiePayload) || !biotern_two_factor_pending_payload_is_valid($cookiePayload)) {
            biotern_two_factor_clear_pending_cookie();
            return null;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[biotern_two_factor_pending_key()] = $cookiePayload;
        }

        return $cookiePayload;
    }
}

if (!function_exists('biotern_two_factor_clear_pending_login')) {
    function biotern_two_factor_clear_pending_login(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION[biotern_two_factor_pending_key()]);
        }

        biotern_two_factor_clear_pending_cookie();
    }
}

if (!function_exists('biotern_two_factor_issue_login_code')) {
    function biotern_two_factor_issue_login_code($conn, int $userId, string $email, ?string &$errorRef = null): array
    {
        $errorRef = null;

        $db = biotern_two_factor_db($conn);
        $userId = (int)$userId;
        $email = trim($email);

        if (!($db instanceof mysqli) || $db->connect_errno || $userId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'A valid email address is required for two-factor authentication.', 'reference' => ''];
        }

        if (!biotern_two_factor_ensure_schema($db)) {
            return ['ok' => false, 'error' => 'Two-factor authentication is unavailable right now.', 'reference' => ''];
        }

        $cleanupStmt = $db->prepare("UPDATE user_two_factor_codes
            SET consumed_at = NOW()
            WHERE user_id = ?
              AND purpose = 'login'
              AND consumed_at IS NULL");
        if ($cleanupStmt) {
            $cleanupStmt->bind_param('i', $userId);
            $cleanupStmt->execute();
            $cleanupStmt->close();
        }

        $code = biotern_two_factor_generate_code();
        $codeHash = biotern_two_factor_code_hash($code);
        if ($codeHash === '') {
            return ['ok' => false, 'error' => 'Unable to create a verification code right now.', 'reference' => ''];
        }

        $expiresAt = time() + biotern_two_factor_code_ttl_seconds();
        $ipAddress = biotern_two_factor_client_ip();
        $userAgent = biotern_two_factor_user_agent();

        $insertStmt = $db->prepare("INSERT INTO user_two_factor_codes (user_id, purpose, code_hash, attempt_count, ip_address, user_agent, created_at, expires_at)
            VALUES (?, 'login', ?, 0, ?, ?, NOW(), FROM_UNIXTIME(?))");
        if (!$insertStmt) {
            return ['ok' => false, 'error' => 'Unable to store the verification code.', 'reference' => ''];
        }

        $insertStmt->bind_param('isssi', $userId, $codeHash, $ipAddress, $userAgent, $expiresAt);
        $stored = (bool)$insertStmt->execute();
        $codeRowId = (int)$db->insert_id;
        $insertStmt->close();

        if (!$stored || $codeRowId <= 0) {
            return ['ok' => false, 'error' => 'Unable to store the verification code.', 'reference' => ''];
        }

        if (!function_exists('biotern_send_mail')) {
            require_once dirname(__DIR__) . '/lib/mailer.php';
        }

        $minutes = max(1, (int)ceil(biotern_two_factor_code_ttl_seconds() / 60));
        $subject = 'Your BioTern login verification code';
        $textBody = "Your BioTern verification code is: {$code}\n\nThis code expires in {$minutes} minute(s). If this was not you, please change your password immediately.";
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $safeMasked = htmlspecialchars(biotern_two_factor_mask_email($email), ENT_QUOTES, 'UTF-8');

        $htmlBody = '
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0b1220;padding:24px 0;font-family:Segoe UI,Arial,sans-serif;">
            <tr>
                <td align="center">
                    <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#111a2e;border:1px solid #1f2a44;border-radius:16px;overflow:hidden;">
                        <tr>
                            <td style="padding:20px 24px;background:linear-gradient(135deg,#162447,#111a2e);color:#ffffff;">
                                <div style="font-size:18px;font-weight:700;">BioTern</div>
                                <div style="font-size:13px;color:#a3b3cc;">Two-factor authentication</div>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px;color:#e5e7eb;">
                                <div style="font-size:17px;font-weight:700;margin-bottom:8px;">Your login verification code</div>
                                <div style="font-size:14px;color:#94a3b8;margin-bottom:18px;">
                                    Use this code to finish signing in. It was requested for <strong style="color:#e5e7eb;">' . $safeMasked . '</strong>.
                                </div>
                                <div style="text-align:center;margin:22px 0;">
                                    <span style="display:inline-block;padding:12px 20px;border-radius:12px;background:#1e293b;border:1px solid #475569;color:#ffffff;font-size:28px;letter-spacing:0.3em;font-weight:700;">' . $safeCode . '</span>
                                </div>
                                <div style="font-size:13px;color:#94a3b8;">This code expires in ' . $minutes . ' minute(s).</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>';

        $mailRef = null;
        $sent = biotern_send_mail($db, $email, $subject, $textBody, $htmlBody, $mailRef);
        if (!$sent) {
            $invalidateStmt = $db->prepare('UPDATE user_two_factor_codes SET consumed_at = NOW() WHERE id = ? LIMIT 1');
            if ($invalidateStmt) {
                $invalidateStmt->bind_param('i', $codeRowId);
                $invalidateStmt->execute();
                $invalidateStmt->close();
            }

            $errorRef = (string)$mailRef;
            return [
                'ok' => false,
                'error' => 'Unable to send the two-factor code right now.',
                'reference' => (string)$mailRef,
            ];
        }

        $purgeStmt = $db->prepare("DELETE FROM user_two_factor_codes
            WHERE user_id = ?
              AND (consumed_at IS NOT NULL OR expires_at < DATE_SUB(NOW(), INTERVAL 3 DAY))");
        if ($purgeStmt) {
            $purgeStmt->bind_param('i', $userId);
            $purgeStmt->execute();
            $purgeStmt->close();
        }

        return [
            'ok' => true,
            'error' => '',
            'reference' => '',
            'masked_email' => biotern_two_factor_mask_email($email),
            'expires_at' => $expiresAt,
        ];
    }
}

if (!function_exists('biotern_two_factor_verify_login_code')) {
    function biotern_two_factor_verify_login_code($conn, int $userId, string $code): array
    {
        $db = biotern_two_factor_db($conn);
        $userId = (int)$userId;
        $code = preg_replace('/\D+/', '', (string)$code);

        if (!($db instanceof mysqli) || $db->connect_errno || $userId <= 0 || !biotern_two_factor_ensure_schema($db)) {
            return ['ok' => false, 'reason' => 'system', 'message' => 'Two-factor authentication is unavailable right now.'];
        }

        if (!preg_match('/^[0-9]{6}$/', $code)) {
            return ['ok' => false, 'reason' => 'format', 'message' => 'Please enter the 6-digit verification code.'];
        }

        $stmt = $db->prepare("SELECT id, code_hash, attempt_count, expires_at
            FROM user_two_factor_codes
            WHERE user_id = ?
              AND purpose = 'login'
              AND consumed_at IS NULL
            ORDER BY id DESC
            LIMIT 1");
        if (!$stmt) {
            return ['ok' => false, 'reason' => 'system', 'message' => 'Two-factor authentication is unavailable right now.'];
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || (int)($row['id'] ?? 0) <= 0) {
            return ['ok' => false, 'reason' => 'missing', 'message' => 'No active verification code found. Please request a new code.'];
        }

        $rowId = (int)$row['id'];
        $attemptCount = (int)($row['attempt_count'] ?? 0);
        $expiresAtTs = strtotime((string)($row['expires_at'] ?? ''));

        if ($expiresAtTs !== false && $expiresAtTs <= time()) {
            $expireStmt = $db->prepare('UPDATE user_two_factor_codes SET consumed_at = NOW() WHERE id = ? AND consumed_at IS NULL LIMIT 1');
            if ($expireStmt) {
                $expireStmt->bind_param('i', $rowId);
                $expireStmt->execute();
                $expireStmt->close();
            }

            return ['ok' => false, 'reason' => 'expired', 'message' => 'Verification code expired. Please resend a new code.'];
        }

        if ($attemptCount >= 5) {
            $lockStmt = $db->prepare('UPDATE user_two_factor_codes SET consumed_at = NOW() WHERE id = ? AND consumed_at IS NULL LIMIT 1');
            if ($lockStmt) {
                $lockStmt->bind_param('i', $rowId);
                $lockStmt->execute();
                $lockStmt->close();
            }

            return ['ok' => false, 'reason' => 'locked', 'message' => 'Too many invalid attempts. Please request a new code.'];
        }

        $storedHash = (string)($row['code_hash'] ?? '');
        $providedHash = biotern_two_factor_code_hash($code);
        if ($storedHash === '' || $providedHash === '' || !hash_equals($storedHash, $providedHash)) {
            $newAttempts = $attemptCount + 1;
            if ($newAttempts >= 5) {
                $failStmt = $db->prepare('UPDATE user_two_factor_codes SET attempt_count = ?, consumed_at = NOW() WHERE id = ? LIMIT 1');
                if ($failStmt) {
                    $failStmt->bind_param('ii', $newAttempts, $rowId);
                    $failStmt->execute();
                    $failStmt->close();
                }

                return ['ok' => false, 'reason' => 'locked', 'message' => 'Too many invalid attempts. Please request a new code.'];
            }

            $failStmt = $db->prepare('UPDATE user_two_factor_codes SET attempt_count = ? WHERE id = ? LIMIT 1');
            if ($failStmt) {
                $failStmt->bind_param('ii', $newAttempts, $rowId);
                $failStmt->execute();
                $failStmt->close();
            }

            return ['ok' => false, 'reason' => 'invalid', 'message' => 'Invalid verification code. Please try again.'];
        }

        $consumeStmt = $db->prepare('UPDATE user_two_factor_codes SET consumed_at = NOW() WHERE id = ? AND consumed_at IS NULL LIMIT 1');
        if (!$consumeStmt) {
            return ['ok' => false, 'reason' => 'system', 'message' => 'Unable to finalize verification. Please try again.'];
        }

        $consumeStmt->bind_param('i', $rowId);
        $consumeStmt->execute();
        $affected = (int)$consumeStmt->affected_rows;
        $consumeStmt->close();

        if ($affected <= 0) {
            return ['ok' => false, 'reason' => 'stale', 'message' => 'That verification code is no longer valid. Please request a new code.'];
        }

        return ['ok' => true, 'reason' => 'verified', 'message' => 'Code verified.'];
    }
}
