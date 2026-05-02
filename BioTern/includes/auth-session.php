<?php

if (!function_exists('biotern_auth_cookie_key')) {
    function biotern_auth_cookie_key()
    {
        $appKey = getenv('APP_KEY');
        if ($appKey !== false && trim((string)$appKey) !== '') {
            return (string)$appKey;
        }

        $dbPassKey = defined('DB_PASS') ? (string)DB_PASS : '';
        if ($dbPassKey !== '') {
            return $dbPassKey;
        }

        return 'biotern-fallback-auth-key';
    }
}

if (!function_exists('biotern_is_vercel_runtime')) {
    function biotern_is_vercel_runtime(): bool
    {
        $vercel = getenv('VERCEL');
        if ($vercel !== false) {
            $normalized = strtolower(trim((string)$vercel));
            if ($normalized !== '' && in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
        }

        $vercelEnv = getenv('VERCEL_ENV');
        return $vercelEnv !== false && trim((string)$vercelEnv) !== '';
    }
}

if (!function_exists('biotern_auth_session_remember_ttl_seconds')) {
    function biotern_auth_session_remember_ttl_seconds(): int
    {
        return 60 * 60 * 24 * 30;
    }
}

if (!function_exists('biotern_auth_session_ttl_seconds')) {
    function biotern_auth_session_ttl_seconds(bool $remember = false): int
    {
        return $remember ? biotern_auth_session_remember_ttl_seconds() : (60 * 60 * 12);
    }
}

if (!function_exists('biotern_auth_session_cookie_name')) {
    function biotern_auth_session_cookie_name()
    {
        return 'biotern_session';
    }
}

if (!function_exists('biotern_auth_session_db')) {
    function biotern_auth_session_db($conn = null)
    {
        if ($conn instanceof mysqli && !$conn->connect_errno) {
            return $conn;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli && !$GLOBALS['conn']->connect_errno) {
            return $GLOBALS['conn'];
        }

        return null;
    }
}

if (!function_exists('biotern_auth_cookie_options')) {
    function biotern_auth_cookie_options($expires)
    {
        $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwardedProto !== '' && strpos($forwardedProto, ',') !== false) {
            $parts = explode(',', $forwardedProto);
            $forwardedProto = strtolower(trim((string)($parts[0] ?? '')));
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || ($forwardedProto === 'https')
            || biotern_is_vercel_runtime();

        return [
            'expires' => (int)$expires,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('biotern_set_auth_cookie')) {
    function biotern_set_auth_cookie($userId, bool $remember = false)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return;
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + biotern_auth_session_ttl_seconds($remember);
        $payload = $userId . '|' . $issuedAt . '|' . $expiresAt;
        $signature = hash_hmac('sha256', $payload, biotern_auth_cookie_key());
        $token = base64_encode($payload . '|' . $signature);
        if (!headers_sent()) {
            setcookie('biotern_auth', $token, biotern_auth_cookie_options($expiresAt));
        }
        $_COOKIE['biotern_auth'] = $token;
    }
}

if (!function_exists('biotern_login_session_cookie_options')) {
    function biotern_login_session_cookie_options($expires)
    {
        return biotern_auth_cookie_options((int)$expires);
    }
}

if (!function_exists('biotern_set_login_session_cookie')) {
    function biotern_set_login_session_cookie($rawToken, $expiresAt)
    {
        $rawToken = trim((string)$rawToken);
        if ($rawToken === '') {
            return;
        }

        if (!headers_sent()) {
            setcookie(
                biotern_auth_session_cookie_name(),
                $rawToken,
                biotern_login_session_cookie_options((int)$expiresAt)
            );
        }
        $_COOKIE[biotern_auth_session_cookie_name()] = $rawToken;
    }
}

if (!function_exists('biotern_clear_login_session_cookie')) {
    function biotern_clear_login_session_cookie()
    {
        if (!headers_sent()) {
            setcookie(
                biotern_auth_session_cookie_name(),
                '',
                biotern_login_session_cookie_options(time() - 3600)
            );
        }
        unset($_COOKIE[biotern_auth_session_cookie_name()]);
    }
}

if (!function_exists('biotern_clear_auth_cookie')) {
    function biotern_clear_auth_cookie()
    {
        if (!headers_sent()) {
            setcookie('biotern_auth', '', biotern_auth_cookie_options(time() - 3600));
        }
        unset($_COOKIE['biotern_auth']);
        biotern_clear_login_session_cookie();
    }
}

if (!function_exists('biotern_auth_session_prepare_runtime')) {
    function biotern_auth_session_prepare_runtime(): void
    {
        static $prepared = false;
        if ($prepared || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $prepared = true;
        $cookieOptions = biotern_auth_cookie_options(time() + biotern_auth_session_ttl_seconds());

        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_secure', !empty($cookieOptions['secure']) ? '1' : '0');
        @ini_set('session.cookie_samesite', 'Lax');
        @ini_set('session.gc_maxlifetime', (string)biotern_auth_session_ttl_seconds());

        if (biotern_is_vercel_runtime()) {
            $tmpDir = (string)sys_get_temp_dir();
            if ($tmpDir !== '' && @is_dir($tmpDir) && @is_writable($tmpDir)) {
                @session_save_path($tmpDir);
            }
        }
    }
}

if (!function_exists('biotern_login_session_raw_cookie')) {
    function biotern_login_session_raw_cookie()
    {
        return isset($_COOKIE[biotern_auth_session_cookie_name()])
            ? trim((string)$_COOKIE[biotern_auth_session_cookie_name()])
            : '';
    }
}

if (!function_exists('biotern_login_session_token_hash')) {
    function biotern_login_session_token_hash($rawToken)
    {
        $rawToken = trim((string)$rawToken);
        if ($rawToken === '') {
            return '';
        }

        return hash_hmac('sha256', $rawToken, biotern_auth_cookie_key());
    }
}

if (!function_exists('biotern_auth_session_current_hash')) {
    function biotern_auth_session_current_hash()
    {
        $fromSession = trim((string)($_SESSION['auth_session_token_hash'] ?? ''));
        if ($fromSession !== '') {
            return $fromSession;
        }

        $rawCookie = biotern_login_session_raw_cookie();
        if ($rawCookie === '') {
            return '';
        }

        $hash = biotern_login_session_token_hash($rawCookie);
        if ($hash !== '' && session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['auth_session_token_hash'] = $hash;
        }

        return $hash;
    }
}

if (!function_exists('biotern_login_sessions_has_column')) {
    function biotern_login_sessions_has_column(mysqli $conn, string $column): bool
    {
        $safeColumn = $conn->real_escape_string($column);
        $result = $conn->query("SHOW COLUMNS FROM user_login_sessions LIKE '{$safeColumn}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }
}

if (!function_exists('biotern_login_sessions_ensure_table')) {
    function biotern_login_sessions_ensure_table($conn): bool
    {
        $db = biotern_auth_session_db($conn);
        if (!($db instanceof mysqli) || $db->connect_errno) {
            return false;
        }

        static $initialized = false;
        if ($initialized) {
            return true;
        }

        $created = $db->query("CREATE TABLE IF NOT EXISTS user_login_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            session_id VARCHAR(128) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            revoke_reason VARCHAR(50) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_login_sessions_token_hash (token_hash),
            INDEX idx_user_login_sessions_user (user_id),
            INDEX idx_user_login_sessions_lookup (user_id, token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$created) {
            return false;
        }

        $requiredColumns = [
            'user_id' => 'INT NOT NULL',
            'token_hash' => 'CHAR(64) NOT NULL',
            'session_id' => 'VARCHAR(128) NULL',
            'ip_address' => 'VARCHAR(45) NULL',
            'user_agent' => 'VARCHAR(255) NULL',
            'created_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'last_seen_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'expires_at' => 'DATETIME NOT NULL',
            'revoked_at' => 'DATETIME NULL',
            'revoke_reason' => 'VARCHAR(50) NULL',
        ];

        foreach ($requiredColumns as $column => $definition) {
            if (!biotern_login_sessions_has_column($db, $column)) {
                $safeColumn = str_replace('`', '``', $column);
                $db->query("ALTER TABLE user_login_sessions ADD COLUMN `{$safeColumn}` {$definition}");
            }
        }

        $db->query('CREATE INDEX idx_user_login_sessions_user ON user_login_sessions (user_id)');
        $db->query('CREATE INDEX idx_user_login_sessions_lookup ON user_login_sessions (user_id, token_hash)');
        $initialized = true;

        return true;
    }
}

if (!function_exists('biotern_login_session_generate_token')) {
    function biotern_login_session_generate_token(): string
    {
        try {
            return bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $bytes = openssl_random_pseudo_bytes(32);
                if (is_string($bytes) && $bytes !== '') {
                    return bin2hex($bytes);
                }
            }

            return hash('sha256', uniqid('biotern-session-', true) . mt_rand());
        }
    }
}

if (!function_exists('biotern_auth_session_client_ip')) {
    function biotern_auth_session_client_ip(): string
    {
        $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            return substr(trim((string)($parts[0] ?? '')), 0, 45);
        }

        return substr(trim((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
    }
}

if (!function_exists('biotern_login_session_start')) {
    function biotern_login_session_start($conn, int $userId, bool $remember = false): bool
    {
        $db = biotern_auth_session_db($conn);
        $userId = (int)$userId;
        if (!($db instanceof mysqli) || $db->connect_errno || $userId <= 0) {
            return false;
        }

        if (!biotern_login_sessions_ensure_table($db)) {
            return false;
        }

        $rawToken = biotern_login_session_generate_token();
        $tokenHash = biotern_login_session_token_hash($rawToken);
        if ($rawToken === '' || $tokenHash === '') {
            return false;
        }

        $expiresAt = time() + biotern_auth_session_ttl_seconds($remember);
        $sessionId = substr((string)session_id(), 0, 128);
        $ipAddress = biotern_auth_session_client_ip();
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        $stmt = $db->prepare("INSERT INTO user_login_sessions (user_id, token_hash, session_id, ip_address, user_agent, created_at, last_seen_at, expires_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW(), FROM_UNIXTIME(?))");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('issssi', $userId, $tokenHash, $sessionId, $ipAddress, $userAgent, $expiresAt);
        $ok = (bool)$stmt->execute();
        $stmt->close();

        if (!$ok) {
            return false;
        }

        $_SESSION['auth_session_token_hash'] = $tokenHash;
        biotern_set_login_session_cookie($rawToken, $expiresAt);

        $cleanup = $db->prepare("DELETE FROM user_login_sessions
            WHERE user_id = ?
              AND (revoked_at IS NOT NULL OR expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY))");
        if ($cleanup) {
            $cleanup->bind_param('i', $userId);
            $cleanup->execute();
            $cleanup->close();
        }

        return true;
    }
}

if (!function_exists('biotern_login_session_revoke_current')) {
    function biotern_login_session_revoke_current($conn, int $userId = 0, string $reason = 'logout'): void
    {
        $db = biotern_auth_session_db($conn);
        if (!($db instanceof mysqli) || $db->connect_errno || !biotern_login_sessions_ensure_table($db)) {
            biotern_clear_login_session_cookie();
            return;
        }

        $userId = $userId > 0 ? $userId : (int)($_SESSION['user_id'] ?? 0);
        $tokenHash = biotern_auth_session_current_hash();
        if ($userId <= 0 || $tokenHash === '') {
            biotern_clear_login_session_cookie();
            return;
        }

        $reason = substr(trim($reason), 0, 50);
        if ($reason === '') {
            $reason = 'logout';
        }

        $stmt = $db->prepare("UPDATE user_login_sessions
            SET revoked_at = NOW(), revoke_reason = ?, last_seen_at = NOW()
            WHERE user_id = ? AND token_hash = ? AND revoked_at IS NULL
            LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('sis', $reason, $userId, $tokenHash);
            $stmt->execute();
            $stmt->close();
        }

        unset($_SESSION['auth_session_token_hash']);
        biotern_clear_login_session_cookie();
    }
}

if (!function_exists('biotern_login_session_revoke_others')) {
    function biotern_login_session_revoke_others($conn, int $userId, string $currentTokenHash, string $reason = 'logout_other_sessions'): int
    {
        $db = biotern_auth_session_db($conn);
        $userId = (int)$userId;
        $currentTokenHash = trim((string)$currentTokenHash);
        if (!($db instanceof mysqli) || $db->connect_errno || $userId <= 0 || $currentTokenHash === '' || !biotern_login_sessions_ensure_table($db)) {
            return 0;
        }

        $reason = substr(trim($reason), 0, 50);
        if ($reason === '') {
            $reason = 'logout_other_sessions';
        }

        $stmt = $db->prepare("UPDATE user_login_sessions
            SET revoked_at = NOW(), revoke_reason = ?
            WHERE user_id = ?
              AND token_hash <> ?
              AND revoked_at IS NULL
              AND expires_at > NOW()");
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('sis', $reason, $userId, $currentTokenHash);
        $stmt->execute();
        $affected = (int)$stmt->affected_rows;
        $stmt->close();

        return $affected;
    }
}

if (!function_exists('biotern_login_session_revoke_by_id')) {
    function biotern_login_session_revoke_by_id($conn, int $userId, int $sessionRowId, string $currentTokenHash, string $reason = 'logout_selected_session'): bool
    {
        $db = biotern_auth_session_db($conn);
        $userId = (int)$userId;
        $sessionRowId = (int)$sessionRowId;
        $currentTokenHash = trim((string)$currentTokenHash);
        if (!($db instanceof mysqli) || $db->connect_errno || $userId <= 0 || $sessionRowId <= 0 || !biotern_login_sessions_ensure_table($db)) {
            return false;
        }

        $reason = substr(trim($reason), 0, 50);
        if ($reason === '') {
            $reason = 'logout_selected_session';
        }

        if ($currentTokenHash !== '') {
            $stmt = $db->prepare("UPDATE user_login_sessions
                SET revoked_at = NOW(), revoke_reason = ?
                WHERE id = ?
                  AND user_id = ?
                  AND token_hash <> ?
                  AND revoked_at IS NULL
                LIMIT 1");
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param('siis', $reason, $sessionRowId, $userId, $currentTokenHash);
            $stmt->execute();
            $affected = (int)$stmt->affected_rows;
            $stmt->close();
            return $affected > 0;
        }

        $stmt = $db->prepare("UPDATE user_login_sessions
            SET revoked_at = NOW(), revoke_reason = ?
            WHERE id = ?
              AND user_id = ?
              AND revoked_at IS NULL
            LIMIT 1");
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('sii', $reason, $sessionRowId, $userId);
        $stmt->execute();
        $affected = (int)$stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }
}

if (!function_exists('biotern_login_session_recent_for_user')) {
    function biotern_login_session_recent_for_user($conn, int $userId, string $currentTokenHash = '', int $limit = 12): array
    {
        $db = biotern_auth_session_db($conn);
        $userId = (int)$userId;
        if (!($db instanceof mysqli) || $db->connect_errno || $userId <= 0 || !biotern_login_sessions_ensure_table($db)) {
            return [];
        }

        $limit = max(1, min(30, (int)$limit));
        $sql = "SELECT id, token_hash, ip_address, user_agent, created_at, last_seen_at, expires_at, revoked_at, revoke_reason
                FROM user_login_sessions
                WHERE user_id = ?
                ORDER BY last_seen_at DESC, id DESC
                LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $row['is_current'] = $currentTokenHash !== '' && hash_equals($currentTokenHash, (string)($row['token_hash'] ?? ''));
            $rows[] = $row;
        }
        $stmt->close();

        return $rows;
    }
}

if (!function_exists('biotern_destroy_authenticated_session')) {
    function biotern_destroy_authenticated_session(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            biotern_clear_auth_cookie();
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        biotern_clear_auth_cookie();
    }
}

if (!function_exists('biotern_parse_auth_cookie')) {
    function biotern_parse_auth_cookie()
    {
        $raw = isset($_COOKIE['biotern_auth']) ? (string)$_COOKIE['biotern_auth'] : '';
        if ($raw === '') {
            return null;
        }

        $decoded = base64_decode($raw, true);
        if (!is_string($decoded) || $decoded === '') {
            return null;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 4) {
            return null;
        }

        $userId = (int)$parts[0];
        $issuedAt = (int)$parts[1];
        $expiresAt = (int)$parts[2];
        $signature = (string)$parts[3];
        if ($userId <= 0 || $issuedAt <= 0 || $expiresAt <= 0 || $signature === '') {
            return null;
        }

        if ($expiresAt < time()) {
            return null;
        }

        $payload = $userId . '|' . $issuedAt . '|' . $expiresAt;
        $expected = hash_hmac('sha256', $payload, biotern_auth_cookie_key());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        return ['user_id' => $userId];
    }
}

if (!function_exists('biotern_restore_session_from_cookie')) {
    function biotern_restore_session_from_cookie($conn)
    {
        $db = biotern_auth_session_db($conn);
        if (!($db instanceof mysqli) || $db->connect_errno) {
            return;
        }

        $parsed = biotern_parse_auth_cookie();
        if (!is_array($parsed) || !isset($parsed['user_id'])) {
            return;
        }

        $cookieUserId = (int)$parsed['user_id'];
        if ($cookieUserId <= 0) {
            return;
        }

        if (!biotern_login_sessions_ensure_table($db)) {
            return;
        }

        $rawSessionToken = biotern_login_session_raw_cookie();
        $tokenHash = biotern_login_session_token_hash($rawSessionToken);
        if ($tokenHash === '') {
            biotern_clear_auth_cookie();
            return;
        }

        $sessionRowId = 0;
        $sessionStmt = $db->prepare("SELECT id
            FROM user_login_sessions
            WHERE user_id = ?
              AND token_hash = ?
              AND revoked_at IS NULL
              AND expires_at > NOW()
            LIMIT 1");
        if (!$sessionStmt) {
            return;
        }

        $sessionStmt->bind_param('is', $cookieUserId, $tokenHash);
        $sessionStmt->execute();
        $sessionRow = $sessionStmt->get_result()->fetch_assoc();
        $sessionStmt->close();

        if (!$sessionRow || (int)($sessionRow['id'] ?? 0) <= 0) {
            biotern_clear_auth_cookie();
            return;
        }
        $sessionRowId = (int)$sessionRow['id'];

        $stmt = $db->prepare("SELECT id, name, username, email, role, is_active, profile_picture FROM users WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('i', $cookieUserId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['name'] = (string)($user['name'] ?? '');
        $_SESSION['username'] = (string)($user['username'] ?? '');
        $_SESSION['email'] = (string)($user['email'] ?? '');
        $_SESSION['role'] = (string)($user['role'] ?? '');
        $_SESSION['profile_picture'] = (string)($user['profile_picture'] ?? '');
        $_SESSION['logged_in'] = true;
        $_SESSION['auth_session_token_hash'] = $tokenHash;

        $touchStmt = $db->prepare("UPDATE user_login_sessions
            SET last_seen_at = NOW(), session_id = ?
            WHERE id = ?
            LIMIT 1");
        if ($touchStmt) {
            $sessionId = substr((string)session_id(), 0, 128);
            $touchStmt->bind_param('si', $sessionId, $sessionRowId);
            $touchStmt->execute();
            $touchStmt->close();
        }
    }
}

if (!function_exists('biotern_login_session_validate')) {
    function biotern_login_session_validate($conn, int $userId): bool
    {
        $db = biotern_auth_session_db($conn);
        $userId = (int)$userId;
        if ($userId <= 0) {
            return true;
        }

        if (!($db instanceof mysqli) || $db->connect_errno) {
            return true;
        }

        if (!biotern_login_sessions_ensure_table($db)) {
            return true;
        }

        $tokenHash = biotern_auth_session_current_hash();

        // Backward-compatible migration path: if no token hash is present yet,
        // issue one for the active PHP session.
        if ($tokenHash === '') {
            biotern_login_session_start($db, $userId);
            $tokenHash = biotern_auth_session_current_hash();
            if ($tokenHash === '') {
                return true;
            }
        }

        try {
            $stmt = $db->prepare("SELECT id, revoked_at, expires_at
                FROM user_login_sessions
                WHERE user_id = ? AND token_hash = ?
                LIMIT 1");
        } catch (Throwable $e) {
            return true;
        }
        if (!$stmt) {
            return true;
        }

        $stmt->bind_param('is', $userId, $tokenHash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || (int)($row['id'] ?? 0) <= 0) {
            // If this hash does not exist, treat it as a legacy session and create a new row.
            // Revoked sessions still keep a row, so they won't pass through this fallback.
            biotern_login_session_start($db, $userId);
            return biotern_auth_session_current_hash() !== '';
        }

        if (!empty($row['revoked_at'])) {
            return false;
        }

        $expiresAtRaw = trim((string)($row['expires_at'] ?? ''));
        if ($expiresAtRaw !== '') {
            $expiresAtTs = strtotime($expiresAtRaw);
            if ($expiresAtTs !== false && $expiresAtTs <= time()) {
                return false;
            }
        }

        $touchStmt = $db->prepare("UPDATE user_login_sessions
            SET last_seen_at = NOW(), session_id = ?
            WHERE id = ?
            LIMIT 1");
        if ($touchStmt) {
            $sessionId = substr((string)session_id(), 0, 128);
            $rowId = (int)$row['id'];
            $touchStmt->bind_param('si', $sessionId, $rowId);
            $touchStmt->execute();
            $touchStmt->close();
        }

        return true;
    }
}

if (!function_exists('biotern_boot_session')) {
    function biotern_boot_session($conn = null)
    {
        biotern_auth_session_prepare_runtime();

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        $db = biotern_auth_session_db($conn);

        if ((int)($_SESSION['user_id'] ?? 0) <= 0) {
            biotern_restore_session_from_cookie($db);
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId > 0 && !biotern_login_session_validate($db, $userId)) {
            biotern_destroy_authenticated_session();
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                biotern_auth_session_prepare_runtime();
                @session_start();
            }
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }
        }

        if ((int)($_SESSION['user_id'] ?? 0) > 0 && strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''))) === 'admin') {
            $adminActivityLogPath = __DIR__ . '/admin-activity-log.php';
            if (is_file($adminActivityLogPath)) {
                require_once $adminActivityLogPath;
                if (function_exists('biotern_admin_activity_auto_log')) {
                    biotern_admin_activity_auto_log($db);
                }
            }
        }
    }
}
