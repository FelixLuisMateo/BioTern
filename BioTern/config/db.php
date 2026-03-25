<?php
mysqli_report(MYSQLI_REPORT_OFF);

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

$databaseUrl = biotern_env_pick(['DATABASE_URL', 'MYSQL_URL', 'DB_URL'], '');

$resolvedHost = '';
$resolvedUser = '';
$resolvedPass = '';
$resolvedName = '';
$resolvedPort = '';

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
    $resolvedHost = biotern_env_pick(['DB_HOST', 'MYSQLHOST', 'RAILWAY_MYSQL_HOST'], '127.0.0.1');
}
if ($resolvedUser === '') {
    $resolvedUser = biotern_env_pick(['DB_USER', 'MYSQLUSER', 'RAILWAY_MYSQL_USER'], 'root');
}
if ($resolvedPass === '') {
    $resolvedPass = biotern_env_pick(['DB_PASS', 'MYSQLPASSWORD', 'RAILWAY_MYSQL_PASSWORD'], '');
}
if ($resolvedName === '') {
    $resolvedName = biotern_env_pick(['DB_NAME', 'MYSQLDATABASE', 'RAILWAY_MYSQL_DATABASE'], 'biotern_db');
}
if ($resolvedPort === '') {
    $resolvedPort = biotern_env_pick(['DB_PORT', 'MYSQLPORT', 'RAILWAY_MYSQL_PORT'], '3306');
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

$connectionProfiles = [[
    'host' => $resolvedHost,
    'user' => $resolvedUser,
    'pass' => $resolvedPass,
    'name' => $resolvedName,
    'port' => $resolvedPortInt,
]];

$isLocalRuntime = biotern_is_local_runtime();
$primaryHostLower = strtolower((string)$resolvedHost);
$primaryIsLocalHost = in_array($primaryHostLower, ['127.0.0.1', 'localhost', '::1'], true);
$preferVercel = biotern_env_truthy(['VERCEL', 'BIOTERN_PRIORITIZE_VERCEL']);

if (!$preferVercel) {
    $dbTarget = strtolower(trim(biotern_env_pick(['BIOTERN_DB_TARGET'], '')));
    if ($dbTarget === 'vercel' || $dbTarget === 'remote' || $dbTarget === 'railway') {
        $preferVercel = true;
    }
}

if ($isLocalRuntime && !$primaryIsLocalHost && !$preferVercel) {
    $localPort = (int)biotern_env_pick(['LOCAL_DB_PORT'], '3306');
    if ($localPort <= 0) {
        $localPort = 3306;
    }

    $connectionProfiles[] = [
        'host' => biotern_env_pick(['LOCAL_DB_HOST'], '127.0.0.1'),
        'user' => biotern_env_pick(['LOCAL_DB_USER'], 'root'),
        'pass' => biotern_env_pick(['LOCAL_DB_PASS'], ''),
        'name' => biotern_env_pick(['LOCAL_DB_NAME'], 'biotern_db'),
        'port' => $localPort,
    ];
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
    die('Database connection failed. Please verify DB env variables (DB_HOST/DB_USER/DB_PASS/DB_NAME/DB_PORT or MYSQL*/RAILWAY_MYSQL* vars). Current host=' . $safeHost . ', db=' . $safeDb . '. Error: ' . $lastError);
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



