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

if (!function_exists('biotern_auth_cookie_options')) {
    function biotern_auth_cookie_options($expires)
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

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
    function biotern_set_auth_cookie($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return;
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + (60 * 60 * 12);
        $payload = $userId . '|' . $issuedAt . '|' . $expiresAt;
        $signature = hash_hmac('sha256', $payload, biotern_auth_cookie_key());
        $token = base64_encode($payload . '|' . $signature);
        setcookie('biotern_auth', $token, biotern_auth_cookie_options($expiresAt));
    }
}

if (!function_exists('biotern_clear_auth_cookie')) {
    function biotern_clear_auth_cookie()
    {
        setcookie('biotern_auth', '', biotern_auth_cookie_options(time() - 3600));
        unset($_COOKIE['biotern_auth']);
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
        if (!($conn instanceof mysqli) || $conn->connect_errno) {
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

        $stmt = $conn->prepare("SELECT id, name, username, email, role, is_active, profile_picture FROM users WHERE id = ? LIMIT 1");
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
    }
}

if (!function_exists('biotern_boot_session')) {
    function biotern_boot_session($conn = null)
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if ((int)($_SESSION['user_id'] ?? 0) <= 0) {
            biotern_restore_session_from_cookie($conn);
        }
    }
}
