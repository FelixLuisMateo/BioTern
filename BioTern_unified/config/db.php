<?php
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

if (!function_exists('biotern_app_timezone')) {
    function biotern_app_timezone(): string
    {
        static $timezone = null;
        if ($timezone !== null) {
            return $timezone;
        }

        $configured = getenv('APP_TIMEZONE');
        if (!is_string($configured) || trim($configured) === '') {
            $configured = getenv('TZ');
        }
        if (!is_string($configured) || trim($configured) === '') {
            $configured = 'Asia/Manila';
        }

        $configured = trim($configured);
        try {
            new DateTimeZone($configured);
            $timezone = $configured;
        } catch (Throwable $e) {
            $timezone = 'Asia/Manila';
        }

        return $timezone;
    }
}

date_default_timezone_set(biotern_app_timezone());

if (!function_exists('biotern_session_cookie_path')) {
    function biotern_session_cookie_path(): string
    {
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $requestUri = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
        $marker = '/BioTern_unified/';

        $pos = stripos($scriptName, $marker);
        if ($pos === false) {
            $pos = stripos($requestUri, $marker);
            if ($pos !== false) {
                $base = substr($requestUri, 0, $pos) . $marker;
                return rtrim('/' . ltrim((string)$base, '/'), '/') . '/';
            }
        } else {
            $base = substr($scriptName, 0, $pos) . $marker;
            return rtrim('/' . ltrim((string)$base, '/'), '/') . '/';
        }

        $projectDir = '/' . basename(dirname(__DIR__)) . '/';
        return preg_replace('#/+#', '/', $projectDir);
    }
}

if (!function_exists('biotern_configure_session_cookie_params')) {
    function biotern_configure_session_cookie_params(): void
    {
        if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
            return;
        }

        session_set_cookie_params([
            'path' => biotern_session_cookie_path(),
            'secure' => (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

biotern_configure_session_cookie_params();

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

        $url = biotern_env_first(['MYSQL_PUBLIC_URL', 'DATABASE_URL', 'MYSQL_URL', 'DB_URL'], '');
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

if (!function_exists('biotern_unified_is_local_runtime')) {
    function biotern_unified_is_local_runtime(): bool
    {
        $httpHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? ''));
        $remoteAddr = strtolower((string)($_SERVER['REMOTE_ADDR'] ?? ''));

        if ($httpHost !== '' && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/', $httpHost)) {
            return true;
        }
        if (in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
        if (in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('biotern_unified_open_mysqli')) {
    function biotern_unified_open_mysqli(string $host, string $user, string $pass, string $name, int $port): mysqli
    {
        $mysqli = mysqli_init();
        if ($mysqli instanceof mysqli) {
            @mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 8);
            @mysqli_real_connect($mysqli, $host, $user, $pass, $name, $port);
            return $mysqli;
        }

        return @new mysqli($host, $user, $pass, $name, $port);
    }
}

if (!function_exists('biotern_unified_env_truthy')) {
    function biotern_unified_env_truthy(array $keys): bool
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value === false) {
                continue;
            }
            $normalized = strtolower(trim((string)$value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('biotern_unified_host_is_internal')) {
    function biotern_unified_host_is_internal(string $host): bool
    {
        $normalized = strtolower(trim($host));
        if ($normalized === '') {
            return false;
        }

        return (bool)preg_match('/(^|\.)internal$/', $normalized);
    }
}

$envHost = (string)biotern_env_first(
    ['DB_HOST', 'DB_HOST_ONLINE', 'MYSQL_PUBLIC_HOST', 'RAILWAY_TCP_PROXY_DOMAIN', 'MYSQLHOST', 'RAILWAY_MYSQL_HOST'],
    (string)($biotern_mysql_url['host'] ?? '127.0.0.1')
);
$envUser = (string)biotern_env_first(
    ['DB_USER', 'DB_USERNAME', 'DB_USERNAME_ONLINE', 'MYSQLUSER', 'RAILWAY_MYSQL_USER', 'MYSQL_PUBLIC_USER'],
    (string)($biotern_mysql_url['user'] ?? 'root')
);
$envPass = (string)biotern_env_first(
    ['DB_PASS', 'DB_PASSWORD', 'DB_PASSWORD_ONLINE', 'MYSQLPASSWORD', 'RAILWAY_MYSQL_PASSWORD', 'MYSQL_PUBLIC_PASSWORD'],
    (string)($biotern_mysql_url['pass'] ?? '')
);
$envName = (string)biotern_env_first(
    ['DB_NAME', 'DB_DATABASE', 'DB_DATABASE_ONLINE', 'MYSQLDATABASE', 'RAILWAY_MYSQL_DATABASE', 'MYSQL_PUBLIC_DATABASE'],
    (string)($biotern_mysql_url['database'] ?? 'biotern_db')
);
$envPort = (int)biotern_env_first(
    ['DB_PORT', 'DB_PORT_ONLINE', 'MYSQL_PUBLIC_PORT', 'RAILWAY_TCP_PROXY_PORT', 'MYSQLPORT', 'RAILWAY_MYSQL_PORT'],
    (string)($biotern_mysql_url['port'] ?? 3306)
);
if ($envPort <= 0) {
    $envPort = 3306;
}

$isVercelRuntime = biotern_unified_env_truthy(['VERCEL']);
if ($isVercelRuntime) {
    // On Vercel, explicit host-style vars should override stale URL-derived values.
    $explicitHost = (string)biotern_env_first(['MYSQL_PUBLIC_HOST', 'RAILWAY_TCP_PROXY_DOMAIN', 'DB_HOST', 'DB_HOST_ONLINE', 'MYSQLHOST'], '');
    if ($explicitHost !== '' && !biotern_unified_host_is_internal($explicitHost)) {
        $envHost = $explicitHost;
    }

    $explicitUser = (string)biotern_env_first(['DB_USER', 'DB_USER_ONLINE', 'MYSQLUSER', 'MYSQL_PUBLIC_USER'], '');
    if ($explicitUser !== '') {
        $envUser = $explicitUser;
    }

    $explicitPass = (string)biotern_env_first(['DB_PASS', 'DB_PASS_ONLINE', 'MYSQLPASSWORD', 'MYSQL_PUBLIC_PASSWORD'], '');
    if ($explicitPass !== '') {
        $envPass = $explicitPass;
    }

    $explicitName = (string)biotern_env_first(['DB_NAME', 'DB_NAME_ONLINE', 'MYSQLDATABASE', 'MYSQL_PUBLIC_DATABASE'], '');
    if ($explicitName !== '') {
        $envName = $explicitName;
    }

    $explicitPort = (string)biotern_env_first(['MYSQL_PUBLIC_PORT', 'RAILWAY_TCP_PROXY_PORT', 'DB_PORT', 'DB_PORT_ONLINE', 'MYSQLPORT'], '');
    if ($explicitPort !== '') {
        $envPort = (int)$explicitPort;
        if ($envPort <= 0) {
            $envPort = 3306;
        }
    }
}

if ($isVercelRuntime && biotern_unified_host_is_internal($envHost)) {
    // Vercel cannot resolve Railway internal DNS; switch to public URL/host vars when present.
    $publicUrl = (string)biotern_env_first(['MYSQL_PUBLIC_URL', 'RAILWAY_MYSQL_PUBLIC_URL', 'DB_PUBLIC_URL'], '');
    if ($publicUrl !== '') {
        $publicParts = @parse_url($publicUrl);
        if (is_array($publicParts)) {
            if (!empty($publicParts['host'])) {
                $envHost = (string)$publicParts['host'];
            }
            if (!empty($publicParts['user'])) {
                $envUser = (string)$publicParts['user'];
            }
            if (array_key_exists('pass', $publicParts)) {
                $envPass = (string)$publicParts['pass'];
            }
            if (!empty($publicParts['path'])) {
                $parsedDb = ltrim((string)$publicParts['path'], '/');
                if ($parsedDb !== '') {
                    $envName = $parsedDb;
                }
            }
            if (!empty($publicParts['port'])) {
                $envPort = (int)$publicParts['port'];
            }
        }
    }

    if (biotern_unified_host_is_internal($envHost)) {
        $candidateHost = (string)biotern_env_first(['MYSQL_PUBLIC_HOST', 'RAILWAY_TCP_PROXY_DOMAIN', 'DB_HOST', 'DB_HOST_ONLINE', 'MYSQLHOST'], '');
        if ($candidateHost !== '' && !biotern_unified_host_is_internal($candidateHost)) {
            $envHost = $candidateHost;
        }
        $envUser = (string)biotern_env_first(['MYSQLUSER', 'MYSQL_PUBLIC_USER', 'DB_USER'], $envUser ?: 'root');
        $envPass = (string)biotern_env_first(['MYSQLPASSWORD', 'MYSQL_PUBLIC_PASSWORD', 'DB_PASS'], $envPass ?? '');
        $envName = (string)biotern_env_first(['MYSQLDATABASE', 'MYSQL_PUBLIC_DATABASE', 'DB_NAME'], $envName ?: 'biotern_db');
        $envPort = (int)biotern_env_first(['MYSQL_PUBLIC_PORT', 'RAILWAY_TCP_PROXY_PORT', 'MYSQLPORT', 'DB_PORT'], (string)($envPort > 0 ? $envPort : 3306));
        if ($envPort <= 0) {
            $envPort = 3306;
        }
    }
}

$requestedTarget = strtolower(trim((string)biotern_env_first(['BIOTERN_DB_TARGET'], '')));
$forceRemoteTarget = in_array($requestedTarget, ['vercel', 'remote', 'railway', 'cloud'], true)
    || biotern_unified_env_truthy(['VERCEL', 'BIOTERN_PRIORITIZE_VERCEL']);
$forceLocalTarget = in_array($requestedTarget, ['local', 'xampp'], true);

$isLocalRuntime = biotern_unified_is_local_runtime();
$primaryIsLocal = in_array(strtolower($envHost), ['127.0.0.1', 'localhost', '::1'], true);
$primaryIsInternal = biotern_unified_host_is_internal($envHost);
$shouldTryLocalFallback = $isLocalRuntime && !$primaryIsLocal && (!$forceRemoteTarget || $primaryIsInternal);

$localProfile = [
    'host' => (string)biotern_env_first(['LOCAL_DB_HOST'], '127.0.0.1'),
    'user' => (string)biotern_env_first(['LOCAL_DB_USER'], 'root'),
    'pass' => (string)biotern_env_first(['LOCAL_DB_PASS'], ''),
    'name' => (string)biotern_env_first(['LOCAL_DB_NAME'], 'biotern_db'),
    'port' => (int)biotern_env_first(['LOCAL_DB_PORT'], '3306'),
];
if ($localProfile['port'] <= 0) {
    $localProfile['port'] = 3306;
}

$remoteProfile = [
    'host' => $envHost,
    'user' => $envUser,
    'pass' => $envPass,
    'name' => $envName,
    'port' => $envPort,
];

$connectionProfiles = [];
if ($forceLocalTarget || $shouldTryLocalFallback) {
    $connectionProfiles[] = $localProfile;
    $connectionProfiles[] = $remoteProfile;
} else {
    $connectionProfiles[] = $remoteProfile;
    if ($shouldTryLocalFallback) {
        $connectionProfiles[] = $localProfile;
    }
}

$activeProfile = null;
$lastError = 'Unknown database connection error';
$conn = null;

foreach ($connectionProfiles as $profile) {
    $mysqli = biotern_unified_open_mysqli(
        (string)$profile['host'],
        (string)$profile['user'],
        (string)$profile['pass'],
        (string)$profile['name'],
        (int)$profile['port']
    );

    if ($mysqli instanceof mysqli && !$mysqli->connect_errno) {
        $conn = $mysqli;
        $activeProfile = $profile;
        break;
    }

    $lastError = (string)(($mysqli instanceof mysqli && $mysqli->connect_error) ? $mysqli->connect_error : 'Unknown database connection error');
    if ($mysqli instanceof mysqli) {
        $mysqli->close();
    }
}

if (!($conn instanceof mysqli) || !$activeProfile) {
    $safeHost = $envHost !== '' ? $envHost : 'unknown-host';
    $safeDb = $envName !== '' ? $envName : 'unknown-db';
    die('Database connection failed. Please verify DB env variables (MYSQL_PUBLIC_URL, DATABASE_URL, DB_HOST/DB_USER/DB_PASS/DB_NAME/DB_PORT, or MYSQL*/RAILWAY_MYSQL* vars). Current host=' . $safeHost . ', db=' . $safeDb . '. Error: ' . $lastError);
}

if (!defined('DB_HOST')) {
    define('DB_HOST', (string)$activeProfile['host']);
}
if (!defined('DB_USER')) {
    define('DB_USER', (string)$activeProfile['user']);
}
if (!defined('DB_PASS')) {
    define('DB_PASS', (string)$activeProfile['pass']);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', (string)$activeProfile['name']);
}
if (!defined('DB_PORT')) {
    define('DB_PORT', (int)$activeProfile['port']);
}

@ini_set('mysqli.default_host', (string)DB_HOST);
@ini_set('mysqli.default_user', (string)DB_USER);
@ini_set('mysqli.default_pw', (string)DB_PASS);
@ini_set('mysqli.default_port', (string)DB_PORT);

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

try {
    $tz = new DateTimeZone(biotern_app_timezone());
    $offset = $tz->getOffset(new DateTimeImmutable('now', $tz));
    $sign = $offset >= 0 ? '+' : '-';
    $abs = abs($offset);
    $hours = str_pad((string)intdiv($abs, 3600), 2, '0', STR_PAD_LEFT);
    $minutes = str_pad((string)intdiv($abs % 3600, 60), 2, '0', STR_PAD_LEFT);
    $mysqlOffset = $sign . $hours . ':' . $minutes;
    @$conn->query("SET time_zone = '{$mysqlOffset}'");
} catch (Throwable $e) {
    // Keep working with the default DB timezone when session timezone cannot be set.
}

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

if (!function_exists('biotern_db_ensure_index')) {
    function biotern_db_ensure_index(mysqli $mysqli, string $table, string $indexName, string $indexSql): bool
    {
        $safeTable = $mysqli->real_escape_string($table);
        $safeIndex = $mysqli->real_escape_string($indexName);
        $res = $mysqli->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
        if ($res instanceof mysqli_result && $res->num_rows > 0) {
            $res->close();
            return true;
        }
        if ($res instanceof mysqli_result) {
            $res->close();
        }

        try {
            return (bool)$mysqli->query($indexSql);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('biotern_ensure_fingerprint_user_map_table')) {
    function biotern_ensure_fingerprint_user_map_table(mysqli $mysqli): bool
    {
        $ok = (bool)$mysqli->query("
            CREATE TABLE IF NOT EXISTS fingerprint_user_map (
                finger_id INT NOT NULL,
                user_id INT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (finger_id),
                UNIQUE KEY uniq_fingerprint_user_map_user_id (user_id),
                KEY idx_fingerprint_user_map_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        if (!$ok) {
            return false;
        }

        biotern_db_add_column_if_missing($mysqli, 'fingerprint_user_map', 'created_at', 'created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        biotern_db_add_column_if_missing($mysqli, 'fingerprint_user_map', 'updated_at', 'updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        biotern_db_ensure_index(
            $mysqli,
            'fingerprint_user_map',
            'uniq_fingerprint_user_map_user_id',
            "ALTER TABLE `fingerprint_user_map` ADD UNIQUE KEY `uniq_fingerprint_user_map_user_id` (`user_id`)"
        );
        biotern_db_ensure_index(
            $mysqli,
            'fingerprint_user_map',
            'idx_fingerprint_user_map_user_id',
            "ALTER TABLE `fingerprint_user_map` ADD KEY `idx_fingerprint_user_map_user_id` (`user_id`)"
        );

        return true;
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
