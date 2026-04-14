<?php
mysqli_report(MYSQLI_REPORT_OFF);

$biotern_app_timezone = getenv('BIOTERN_APP_TIMEZONE');
if (!is_string($biotern_app_timezone) || trim($biotern_app_timezone) === '') {
    $biotern_app_timezone = 'Asia/Manila';
}
@date_default_timezone_set($biotern_app_timezone);

$biotern_project_root = dirname(__DIR__);
$biotern_include_candidates = [
    $biotern_project_root,
    $biotern_project_root . DIRECTORY_SEPARATOR . 'includes',
    $biotern_project_root . DIRECTORY_SEPARATOR . 'config',
];

$biotern_include_paths = [];
foreach ($biotern_include_candidates as $candidate) {
    if (is_dir($candidate)) {
        $real_candidate = realpath($candidate);
        $biotern_include_paths[] = $real_candidate !== false ? $real_candidate : $candidate;
    }
}

$existing_include_path = get_include_path();
if ($existing_include_path !== false && $existing_include_path !== '') {
    $biotern_include_paths[] = $existing_include_path;
}

set_include_path(implode(PATH_SEPARATOR, array_values(array_unique($biotern_include_paths))));

if (!function_exists('biotern_env_pick')) {
    function biotern_env_pick(array $keys, string $default = ''): string
    {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                return (string)$value;
            }
        }
        return $default;
    }
}

$databaseUrl = biotern_env_pick(['MYSQL_PUBLIC_URL', 'DATABASE_URL', 'MYSQL_URL', 'DB_URL'], '');

$resolvedHost = '';
$resolvedUser = '';
$resolvedPass = '';
$resolvedName = '';
$resolvedPort = '';

$requestedTarget = strtolower(trim(biotern_env_pick(['BIOTERN_DB_TARGET'], '')));
$vercelEnvRaw = getenv('VERCEL');
$isVercelRuntime = false;
if ($vercelEnvRaw !== false) {
    $vercelEnvNormalized = strtolower(trim((string)$vercelEnvRaw));
    $isVercelRuntime = in_array($vercelEnvNormalized, ['1', 'true', 'yes', 'on'], true);
}
$hasRailwayMysqlEnv = biotern_env_pick(['MYSQL_PUBLIC_HOST', 'MYSQLHOST', 'RAILWAY_MYSQL_HOST', 'RAILWAY_TCP_PROXY_DOMAIN'], '') !== '';
if ($requestedTarget === '' && $isVercelRuntime && $hasRailwayMysqlEnv) {
    // In Vercel deployments with Railway MySQL vars present, prefer remote MySQL by default.
    $requestedTarget = 'railway';
}
if ($requestedTarget === '' && $isVercelRuntime) {
    // On Vercel, always treat DB target as remote to avoid prioritizing stale DB_HOST values.
    $requestedTarget = 'railway';
}
$targetRemoteFirst = in_array($requestedTarget, ['vercel', 'remote', 'railway', 'cloud'], true);

if ($databaseUrl !== '') {
    $parsed = parse_url($databaseUrl);
    if (is_array($parsed)) {
        $resolvedHost = isset($parsed['host']) ? (string)$parsed['host'] : '';
        $resolvedUser = isset($parsed['user']) ? (string)$parsed['user'] : '';
        $resolvedPass = isset($parsed['pass']) ? (string)$parsed['pass'] : '';
        $resolvedName = isset($parsed['path']) ? ltrim((string)$parsed['path'], '/') : '';
        $resolvedPort = isset($parsed['port']) ? (string)$parsed['port'] : '';
    }
}

if ($resolvedHost === '') {
    $hostKeys = $targetRemoteFirst
        ? ['MYSQL_PUBLIC_HOST', 'RAILWAY_TCP_PROXY_DOMAIN', 'MYSQLHOST', 'RAILWAY_MYSQL_HOST', 'DB_HOST_ONLINE', 'DB_HOST']
        : ['DB_HOST', 'MYSQL_PUBLIC_HOST', 'RAILWAY_TCP_PROXY_DOMAIN', 'MYSQLHOST', 'RAILWAY_MYSQL_HOST', 'DB_HOST_ONLINE'];
    $resolvedHost = biotern_env_pick($hostKeys, '127.0.0.1');
}
if ($resolvedUser === '') {
    $userKeys = $targetRemoteFirst
        ? ['MYSQLUSER', 'RAILWAY_MYSQL_USER', 'MYSQL_PUBLIC_USER', 'DB_USER_ONLINE', 'DB_USER']
        : ['DB_USER', 'MYSQLUSER', 'RAILWAY_MYSQL_USER', 'MYSQL_PUBLIC_USER', 'DB_USER_ONLINE'];
    $resolvedUser = biotern_env_pick($userKeys, 'root');
}
if ($resolvedPass === '') {
    $passKeys = $targetRemoteFirst
        ? ['MYSQLPASSWORD', 'RAILWAY_MYSQL_PASSWORD', 'MYSQL_PUBLIC_PASSWORD', 'DB_PASS_ONLINE', 'DB_PASS']
        : ['DB_PASS', 'MYSQLPASSWORD', 'RAILWAY_MYSQL_PASSWORD', 'MYSQL_PUBLIC_PASSWORD', 'DB_PASS_ONLINE'];
    $resolvedPass = biotern_env_pick($passKeys, '');
}
if ($resolvedName === '') {
    $nameKeys = $targetRemoteFirst
        ? ['MYSQLDATABASE', 'RAILWAY_MYSQL_DATABASE', 'MYSQL_PUBLIC_DATABASE', 'DB_NAME_ONLINE', 'DB_NAME']
        : ['DB_NAME', 'MYSQLDATABASE', 'RAILWAY_MYSQL_DATABASE', 'MYSQL_PUBLIC_DATABASE', 'DB_NAME_ONLINE'];
    $resolvedName = biotern_env_pick($nameKeys, 'biotern_db');
}
if ($resolvedPort === '') {
    $portKeys = $targetRemoteFirst
        ? ['MYSQL_PUBLIC_PORT', 'RAILWAY_TCP_PROXY_PORT', 'MYSQLPORT', 'RAILWAY_MYSQL_PORT', 'DB_PORT_ONLINE', 'DB_PORT']
        : ['DB_PORT', 'MYSQL_PUBLIC_PORT', 'RAILWAY_TCP_PROXY_PORT', 'MYSQLPORT', 'RAILWAY_MYSQL_PORT', 'DB_PORT_ONLINE'];
    $resolvedPort = biotern_env_pick($portKeys, '3306');
}

$resolvedPortInt = (int)$resolvedPort;
if ($resolvedPortInt <= 0) {
    $resolvedPortInt = 3306;
}

if (!function_exists('biotern_is_local_runtime')) {
    function biotern_is_local_runtime(): bool
    {
        $httpHost = isset($_SERVER['HTTP_HOST']) ? strtolower((string)$_SERVER['HTTP_HOST']) : '';
        $serverName = isset($_SERVER['SERVER_NAME']) ? strtolower((string)$_SERVER['SERVER_NAME']) : '';
        $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? strtolower((string)$_SERVER['REMOTE_ADDR']) : '';

        if ($httpHost !== '' && preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/', $httpHost)) {
            return true;
        }
        if ($serverName !== '' && in_array($serverName, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }
        if ($remoteAddr !== '' && in_array($remoteAddr, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('biotern_open_mysqli')) {
    function biotern_open_mysqli(string $host, string $user, string $pass, string $name, int $port): mysqli
    {
        $mysqli = mysqli_init();
        if ($mysqli instanceof mysqli) {
            $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
            @mysqli_real_connect($mysqli, $host, $user, $pass, $name, $port);
            return $mysqli;
        }

        return @new mysqli($host, $user, $pass, $name, $port);
    }
}

if (!function_exists('biotern_env_truthy')) {
    function biotern_env_truthy(array $keys): bool
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

if (!function_exists('biotern_add_profile')) {
    function biotern_add_profile(array &$profiles, array &$seen, string $host, string $user, string $pass, string $name, $port): void
    {
        $host = trim($host);
        $user = trim($user);
        $name = trim($name);
        $port = (int)$port;

        if ($host === '' || $user === '' || $name === '') {
            return;
        }
        if ($port <= 0) {
            $port = 3306;
        }

        $signature = implode('|', [$host, $port, $user, $name, $pass]);
        if (isset($seen[$signature])) {
            return;
        }

        $seen[$signature] = true;
        $profiles[] = [
            'host' => $host,
            'user' => $user,
            'pass' => $pass,
            'name' => $name,
            'port' => $port,
        ];
    }
}

if (!function_exists('biotern_add_url_profile')) {
    function biotern_add_url_profile(array &$profiles, array &$seen, string $databaseUrl): void
    {
        if (trim($databaseUrl) === '') {
            return;
        }

        $parsed = parse_url($databaseUrl);
        if (!is_array($parsed)) {
            return;
        }

        biotern_add_profile(
            $profiles,
            $seen,
            isset($parsed['host']) ? (string)$parsed['host'] : '',
            isset($parsed['user']) ? (string)$parsed['user'] : '',
            array_key_exists('pass', $parsed) ? (string)$parsed['pass'] : '',
            isset($parsed['path']) ? ltrim((string)$parsed['path'], '/') : '',
            isset($parsed['port']) ? (int)$parsed['port'] : 3306
        );
    }
}

if (!function_exists('biotern_host_is_internal')) {
    function biotern_host_is_internal(string $host): bool
    {
        $normalized = strtolower(trim($host));
        if ($normalized === '') {
            return false;
        }

        return (bool)preg_match('/(^|\.)internal$/', $normalized);
    }
}

if ($isVercelRuntime) {
    // On Vercel, explicit host-style vars should override stale URL-derived values.
    $explicitHost = biotern_env_pick(['MYSQL_PUBLIC_HOST', 'RAILWAY_TCP_PROXY_DOMAIN', 'DB_HOST', 'DB_HOST_ONLINE', 'MYSQLHOST'], '');
    if ($explicitHost !== '' && !biotern_host_is_internal($explicitHost)) {
        $resolvedHost = $explicitHost;
    }

    $explicitUser = biotern_env_pick(['DB_USER', 'DB_USER_ONLINE', 'MYSQLUSER', 'MYSQL_PUBLIC_USER'], '');
    if ($explicitUser !== '') {
        $resolvedUser = $explicitUser;
    }

    $explicitPass = biotern_env_pick(['DB_PASS', 'DB_PASS_ONLINE', 'MYSQLPASSWORD', 'MYSQL_PUBLIC_PASSWORD'], '');
    if ($explicitPass !== '') {
        $resolvedPass = $explicitPass;
    }

    $explicitName = biotern_env_pick(['DB_NAME', 'DB_NAME_ONLINE', 'MYSQLDATABASE', 'MYSQL_PUBLIC_DATABASE'], '');
    if ($explicitName !== '') {
        $resolvedName = $explicitName;
    }

    $explicitPort = biotern_env_pick(['MYSQL_PUBLIC_PORT', 'RAILWAY_TCP_PROXY_PORT', 'DB_PORT', 'DB_PORT_ONLINE', 'MYSQLPORT'], '');
    if ($explicitPort !== '') {
        $resolvedPort = $explicitPort;
    }
}

if ($isVercelRuntime && biotern_host_is_internal($resolvedHost)) {
    // Vercel cannot reach Railway internal DNS; prefer public Railway URL/host if available.
    $publicUrl = biotern_env_pick(['MYSQL_PUBLIC_URL', 'RAILWAY_MYSQL_PUBLIC_URL', 'DB_PUBLIC_URL'], '');
    if ($publicUrl !== '') {
        $publicParsed = parse_url($publicUrl);
        if (is_array($publicParsed)) {
            if (!empty($publicParsed['host'])) {
                $resolvedHost = (string)$publicParsed['host'];
            }
            if (!empty($publicParsed['user'])) {
                $resolvedUser = (string)$publicParsed['user'];
            }
            if (array_key_exists('pass', $publicParsed)) {
                $resolvedPass = (string)$publicParsed['pass'];
            }
            if (!empty($publicParsed['path'])) {
                $resolvedName = ltrim((string)$publicParsed['path'], '/');
            }
            if (!empty($publicParsed['port'])) {
                $resolvedPort = (string)$publicParsed['port'];
            }
        }
    }

    if (biotern_host_is_internal($resolvedHost)) {
        $publicHost = biotern_env_pick(['MYSQL_PUBLIC_HOST', 'RAILWAY_TCP_PROXY_DOMAIN', 'DB_HOST', 'DB_HOST_ONLINE', 'MYSQLHOST'], '');
        if ($publicHost !== '' && !biotern_host_is_internal($publicHost)) {
            $resolvedHost = $publicHost;
        }
        if ($resolvedUser === '') {
            $resolvedUser = biotern_env_pick(['MYSQLUSER', 'MYSQL_PUBLIC_USER', 'DB_USER'], 'root');
        }
        if ($resolvedPass === '') {
            $resolvedPass = biotern_env_pick(['MYSQLPASSWORD', 'MYSQL_PUBLIC_PASSWORD', 'DB_PASS'], '');
        }
        if ($resolvedName === '') {
            $resolvedName = biotern_env_pick(['MYSQLDATABASE', 'MYSQL_PUBLIC_DATABASE', 'DB_NAME'], 'biotern_db');
        }
        if ($resolvedPort === '') {
            $resolvedPort = biotern_env_pick(['MYSQL_PUBLIC_PORT', 'RAILWAY_TCP_PROXY_PORT', 'MYSQLPORT', 'DB_PORT'], '3306');
        }
    }
}

$connectionProfiles = [];
$connectionProfileSeen = [];

biotern_add_profile(
    $connectionProfiles,
    $connectionProfileSeen,
    $resolvedHost,
    $resolvedUser,
    $resolvedPass,
    $resolvedName,
    $resolvedPortInt
);

if ($targetRemoteFirst || $databaseUrl !== '') {
    biotern_add_url_profile($connectionProfiles, $connectionProfileSeen, biotern_env_pick(['MYSQL_PUBLIC_URL'], ''));
    biotern_add_url_profile($connectionProfiles, $connectionProfileSeen, biotern_env_pick(['DATABASE_URL', 'MYSQL_URL', 'DB_URL'], ''));

    biotern_add_profile(
        $connectionProfiles,
        $connectionProfileSeen,
        biotern_env_pick(['MYSQL_PUBLIC_HOST', 'RAILWAY_TCP_PROXY_DOMAIN', 'MYSQLHOST', 'RAILWAY_MYSQL_HOST'], ''),
        biotern_env_pick(['MYSQLUSER', 'RAILWAY_MYSQL_USER', 'MYSQL_PUBLIC_USER'], ''),
        biotern_env_pick(['MYSQLPASSWORD', 'RAILWAY_MYSQL_PASSWORD', 'MYSQL_PUBLIC_PASSWORD'], ''),
        biotern_env_pick(['MYSQLDATABASE', 'RAILWAY_MYSQL_DATABASE', 'MYSQL_PUBLIC_DATABASE'], ''),
        biotern_env_pick(['MYSQL_PUBLIC_PORT', 'RAILWAY_TCP_PROXY_PORT', 'MYSQLPORT', 'RAILWAY_MYSQL_PORT'], '3306')
    );

    biotern_add_profile(
        $connectionProfiles,
        $connectionProfileSeen,
        biotern_env_pick(['DB_HOST_ONLINE'], ''),
        biotern_env_pick(['DB_USER_ONLINE'], ''),
        biotern_env_pick(['DB_PASS_ONLINE'], ''),
        biotern_env_pick(['DB_NAME_ONLINE'], ''),
        biotern_env_pick(['DB_PORT_ONLINE'], '3306')
    );
}

biotern_add_profile(
    $connectionProfiles,
    $connectionProfileSeen,
    biotern_env_pick(['DB_HOST'], ''),
    biotern_env_pick(['DB_USER'], ''),
    biotern_env_pick(['DB_PASS'], ''),
    biotern_env_pick(['DB_NAME'], ''),
    biotern_env_pick(['DB_PORT'], '3306')
);

$isLocalRuntime = biotern_is_local_runtime();
$primaryHostLower = strtolower((string)$resolvedHost);
$primaryIsLocalHost = in_array($primaryHostLower, ['127.0.0.1', 'localhost', '::1'], true);
$primaryIsInternalHost = biotern_host_is_internal($resolvedHost);
$dbTarget = $requestedTarget;
$preferVercel = biotern_env_truthy(['VERCEL', 'BIOTERN_PRIORITIZE_VERCEL']);

$hasRemoteEnvConfig = (
    biotern_env_pick(['MYSQL_PUBLIC_URL', 'DATABASE_URL', 'MYSQL_URL', 'DB_URL'], '') !== ''
    || biotern_env_pick(['MYSQLHOST', 'RAILWAY_MYSQL_HOST', 'MYSQL_PUBLIC_HOST', 'DB_HOST_ONLINE'], '') !== ''
);

$forceRemoteTarget = in_array($dbTarget, ['vercel', 'remote', 'railway', 'cloud'], true)
    || biotern_env_truthy(['VERCEL']);

$forceLocalTarget = in_array($dbTarget, ['local', 'xampp'], true);

// If remote DB config is present (or explicitly requested), do not silently fall back to local.
$disableLocalFallback = (!$forceLocalTarget) && ($forceRemoteTarget || $hasRemoteEnvConfig);
if ($isLocalRuntime && $primaryIsInternalHost && !$forceLocalTarget) {
    // Railway internal DNS is not reachable from localhost; keep local fallback available.
    $disableLocalFallback = false;
}

if (!$preferVercel) {
    if ($dbTarget === 'vercel' || $dbTarget === 'remote' || $dbTarget === 'railway') {
        $preferVercel = true;
    }
}

if ($isLocalRuntime && !$primaryIsLocalHost && !$preferVercel && !$disableLocalFallback) {
    $localPort = (int)biotern_env_pick(['LOCAL_DB_PORT'], '3306');
    if ($localPort <= 0) {
        $localPort = 3306;
    }

    biotern_add_profile(
        $connectionProfiles,
        $connectionProfileSeen,
        biotern_env_pick(['LOCAL_DB_HOST'], '127.0.0.1'),
        biotern_env_pick(['LOCAL_DB_USER'], 'root'),
        biotern_env_pick(['LOCAL_DB_PASS'], ''),
        biotern_env_pick(['LOCAL_DB_NAME'], 'biotern_db'),
        $localPort
    );
}

$activeProfile = null;
$lastError = 'Unknown MySQL connection error.';
$profileCount = count($connectionProfiles);

/** @var mysqli $conn */
$conn = biotern_open_mysqli(
    (string)$connectionProfiles[0]['host'],
    (string)$connectionProfiles[0]['user'],
    (string)$connectionProfiles[0]['pass'],
    (string)$connectionProfiles[0]['name'],
    (int)$connectionProfiles[0]['port']
);

if (!$conn->connect_errno) {
    $activeProfile = $connectionProfiles[0];
} else {
    $lastError = (string)$conn->connect_error;
    $conn->close();

    for ($profileIndex = 1; $profileIndex < $profileCount; $profileIndex++) {
        $profile = $connectionProfiles[$profileIndex];
        $conn = biotern_open_mysqli(
            (string)$profile['host'],
            (string)$profile['user'],
            (string)$profile['pass'],
            (string)$profile['name'],
            (int)$profile['port']
        );

        if (!$conn->connect_errno) {
            $activeProfile = $profile;
            break;
        }

        $lastError = (string)$conn->connect_error;
        $conn->close();
    }
}

if (!$activeProfile) {
    $safeHost = $resolvedHost !== '' ? $resolvedHost : 'unknown-host';
    $safeDb = $resolvedName !== '' ? $resolvedName : 'unknown-db';
    $safePort = $resolvedPortInt > 0 ? (string)$resolvedPortInt : 'unknown-port';
    die('Database connection failed. Please verify DB env variables (MYSQL_PUBLIC_URL, DATABASE_URL, DB_HOST/DB_USER/DB_PASS/DB_NAME/DB_PORT, or MYSQL*/RAILWAY_MYSQL* vars). Current host=' . $safeHost . ', port=' . $safePort . ', db=' . $safeDb . '. Error: ' . $lastError);
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

$conn->set_charset('utf8mb4');

// Keep SQL NOW()/CURRENT_TIMESTAMP aligned with PH (UTC+08) unless overridden.
$biotern_mysql_tz = biotern_env_pick(['BIOTERN_DB_TIMEZONE', 'DB_TIMEZONE'], '+08:00');
if ($biotern_mysql_tz === '') {
    $biotern_mysql_tz = '+08:00';
}
@mysqli_query($conn, "SET time_zone = '" . mysqli_real_escape_string($conn, $biotern_mysql_tz) . "'");



