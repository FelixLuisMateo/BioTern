<?php
// Database configuration
if (!function_exists('biotern_env_first')) {
    function biotern_env_first(array $keys, $default = null)
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== null && $value !== '') {
                return $value;
            }
        }
        return $default;
    }
}

if (!function_exists('biotern_mysql_url_parts')) {
    function biotern_mysql_url_parts(): array
    {
        static $parts = null;
        if ($parts !== null) {
            return $parts;
        }

        $parts = [
            'host' => null,
            'port' => null,
            'database' => null,
            'user' => null,
            'pass' => null,
        ];

        $url = biotern_env_first(['MYSQL_URL'], '');
        if (!is_string($url) || trim($url) === '') {
            return $parts;
        }

        $parsed = @parse_url($url);
        if (!is_array($parsed)) {
            return $parts;
        }

        if (!empty($parsed['host'])) {
            $parts['host'] = (string)$parsed['host'];
        }
        if (!empty($parsed['port'])) {
            $parts['port'] = (int)$parsed['port'];
        }
        if (!empty($parsed['user'])) {
            $parts['user'] = (string)$parsed['user'];
        }
        if (array_key_exists('pass', $parsed)) {
            $parts['pass'] = (string)$parsed['pass'];
        }
        if (!empty($parsed['path'])) {
            $db = ltrim((string)$parsed['path'], '/');
            if ($db !== '') {
                $parts['database'] = $db;
            }
        }

        return $parts;
    }
}

$biotern_mysql_url = biotern_mysql_url_parts();

if (!defined('DB_HOST')) {
    define('DB_HOST', (string)biotern_env_first(['DB_HOST', 'DB_HOST_ONLINE'], $biotern_mysql_url['host'] ?? '127.0.0.1'));
}
if (!defined('DB_USER')) {
    define('DB_USER', (string)biotern_env_first(['DB_USER', 'DB_USERNAME', 'DB_USERNAME_ONLINE'], $biotern_mysql_url['user'] ?? 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', (string)biotern_env_first(['DB_PASS', 'DB_PASSWORD', 'DB_PASSWORD_ONLINE'], $biotern_mysql_url['pass'] ?? ''));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', (string)biotern_env_first(['DB_NAME', 'DB_DATABASE', 'DB_DATABASE_ONLINE'], $biotern_mysql_url['database'] ?? 'biotern_db'));
}
if (!defined('DB_PORT')) {
    $dbPort = biotern_env_first(['DB_PORT', 'DB_PORT_ONLINE'], $biotern_mysql_url['port'] ?? 3306);
    define('DB_PORT', (int)$dbPort);
}

@ini_set('mysqli.default_host', (string)DB_HOST);
@ini_set('mysqli.default_user', (string)DB_USER);
@ini_set('mysqli.default_pw', (string)DB_PASS);
@ini_set('mysqli.default_port', (string)DB_PORT);

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

if (!function_exists('biotern_db_has_column')) {
    function biotern_db_has_column(mysqli $mysqli, string $table, string $column): bool
    {
        $safeTable = str_replace('`', '``', $table);
        $safeColumn = $mysqli->real_escape_string($column);
        $res = $mysqli->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        return ($res instanceof mysqli_result) && $res->num_rows > 0;
    }
}

if (!function_exists('biotern_db_add_column_if_missing')) {
    function biotern_db_add_column_if_missing(mysqli $mysqli, string $table, string $column, string $columnDefinition): bool
    {
        if (biotern_db_has_column($mysqli, $table, $column)) {
            return true;
        }

        $safeTable = str_replace('`', '``', $table);
        $sql = "ALTER TABLE `{$safeTable}` ADD COLUMN {$columnDefinition}";

        try {
            return (bool)$mysqli->query($sql);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('biotern_auth_cookie_name')) {
    function biotern_auth_cookie_name(): string
    {
        return 'biotern_auth';
    }
}

if (!function_exists('biotern_auth_cookie_secure')) {
    function biotern_auth_cookie_secure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $proto === 'https';
    }
}

if (!function_exists('biotern_auth_ensure_tokens_table')) {
    function biotern_auth_ensure_tokens_table(mysqli $mysqli): void
    {
        $mysqli->query("CREATE TABLE IF NOT EXISTS user_auth_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            selector VARCHAR(24) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            user_agent VARCHAR(255) NULL,
            ip_address VARCHAR(45) NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_auth_selector (selector),
            KEY idx_user_auth_user (user_id),
            KEY idx_user_auth_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('biotern_auth_set_cookie')) {
    function biotern_auth_set_cookie(string $value, int $expiresAt): void
    {
        setcookie(biotern_auth_cookie_name(), $value, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => biotern_auth_cookie_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('biotern_auth_clear_persistent_login')) {
    function biotern_auth_clear_persistent_login(?mysqli $mysqli = null): void
    {
        $cookieName = biotern_auth_cookie_name();
        $cookieVal = (string)($_COOKIE[$cookieName] ?? '');

        if ($mysqli instanceof mysqli && $cookieVal !== '' && strpos($cookieVal, ':') !== false) {
            [$selector] = explode(':', $cookieVal, 2);
            $selector = trim($selector);
            if ($selector !== '') {
                $stmt = $mysqli->prepare('DELETE FROM user_auth_tokens WHERE selector = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('s', $selector);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        biotern_auth_set_cookie('', time() - 3600);
        unset($_COOKIE[$cookieName]);
    }
}

if (!function_exists('biotern_auth_issue_persistent_login')) {
    function biotern_auth_issue_persistent_login(mysqli $mysqli, int $userId, int $days = 30): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            biotern_auth_ensure_tokens_table($mysqli);
            $selector = bin2hex(random_bytes(9));
            $validator = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            return false;
        }

        $tokenHash = hash('sha256', $validator);
        $expiresAtTs = time() + max(1, $days) * 86400;
        $expiresAt = date('Y-m-d H:i:s', $expiresAtTs);
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

        $stmt = $mysqli->prepare('INSERT INTO user_auth_tokens (user_id, selector, token_hash, user_agent, ip_address, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('isssss', $userId, $selector, $tokenHash, $userAgent, $ip, $expiresAt);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return false;
        }

        biotern_auth_set_cookie($selector . ':' . $validator, $expiresAtTs);
        return true;
    }
}

if (!function_exists('biotern_auth_restore_session_from_cookie')) {
    function biotern_auth_restore_session_from_cookie(mysqli $mysqli): bool
    {
        if (!empty($_SESSION['user_id'])) {
            return true;
        }

        $cookieVal = (string)($_COOKIE[biotern_auth_cookie_name()] ?? '');
        if ($cookieVal === '' || strpos($cookieVal, ':') === false) {
            return false;
        }

        [$selector, $validator] = explode(':', $cookieVal, 2);
        $selector = trim($selector);
        $validator = trim($validator);
        if ($selector === '' || $validator === '') {
            biotern_auth_clear_persistent_login($mysqli);
            return false;
        }

        biotern_auth_ensure_tokens_table($mysqli);
        $stmt = $mysqli->prepare('SELECT t.user_id, t.token_hash, t.expires_at, u.name, u.username, u.email, u.role, u.is_active, u.profile_picture FROM user_auth_tokens t INNER JOIN users u ON u.id = t.user_id WHERE t.selector = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $selector);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            biotern_auth_clear_persistent_login($mysqli);
            return false;
        }

        $isExpired = strtotime((string)$row['expires_at']) < time();
        $tokenMatches = hash_equals((string)$row['token_hash'], hash('sha256', $validator));
        $isActive = (int)($row['is_active'] ?? 0) === 1;

        if ($isExpired || !$tokenMatches || !$isActive) {
            $deleteStmt = $mysqli->prepare('DELETE FROM user_auth_tokens WHERE selector = ? LIMIT 1');
            if ($deleteStmt) {
                $deleteStmt->bind_param('s', $selector);
                $deleteStmt->execute();
                $deleteStmt->close();
            }
            biotern_auth_clear_persistent_login($mysqli);
            return false;
        }

        $_SESSION['user_id'] = (int)$row['user_id'];
        $_SESSION['name'] = (string)($row['name'] ?? '');
        $_SESSION['username'] = (string)($row['username'] ?? '');
        $_SESSION['email'] = (string)($row['email'] ?? '');
        $_SESSION['role'] = (string)($row['role'] ?? '');
        $_SESSION['profile_picture'] = (string)($row['profile_picture'] ?? '');
        $_SESSION['logged_in'] = true;

        $deleteStmt = $mysqli->prepare('DELETE FROM user_auth_tokens WHERE selector = ? LIMIT 1');
        if ($deleteStmt) {
            $deleteStmt->bind_param('s', $selector);
            $deleteStmt->execute();
            $deleteStmt->close();
        }

        biotern_auth_issue_persistent_login($mysqli, (int)$row['user_id']);
        return true;
    }
}
