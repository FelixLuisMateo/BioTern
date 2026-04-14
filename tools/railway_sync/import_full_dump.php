<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

$options = getopt('', ['url:', 'dump:', 'host:', 'port:', 'user:', 'password:', 'database:']);
$url = isset($options['url']) ? trim((string)$options['url']) : '';
$dumpPath = isset($options['dump']) ? trim((string)$options['dump']) : '';
$host = isset($options['host']) ? (string)$options['host'] : '';
$port = isset($options['port']) ? (int)$options['port'] : 3306;
$user = isset($options['user']) ? (string)$options['user'] : '';
$password = isset($options['password']) ? (string)$options['password'] : '';
$database = isset($options['database']) ? (string)$options['database'] : '';

if ($url !== '') {
    $parts = parse_url($url);
    if (!is_array($parts)) {
        fwrite(STDERR, "Invalid --url. Expected mysql://user:pass@host:port/database\n");
        exit(1);
    }

    $host = (string)($parts['host'] ?? $host);
    $port = isset($parts['port']) ? (int)$parts['port'] : $port;
    $user = isset($parts['user']) ? urldecode((string)$parts['user']) : $user;
    $password = isset($parts['pass']) ? urldecode((string)$parts['pass']) : $password;
    if (isset($parts['path']) && is_string($parts['path'])) {
        $database = ltrim($parts['path'], '/');
    }
}

if ($host === '' || $user === '' || $database === '' || $port <= 0) {
    fwrite(STDERR, "Usage: php tools/railway_sync/import_full_dump.php --url=mysql://user:pass@host:port/database [--dump=biotern_db.sql]\n");
    fwrite(STDERR, "   or: php tools/railway_sync/import_full_dump.php --host=... --port=... --user=... --password=... --database=... [--dump=biotern_db.sql]\n");
    exit(1);
}

if ($dumpPath === '') {
    $dumpPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'biotern_db.sql';
}

if (!is_file($dumpPath)) {
    fwrite(STDERR, "Dump file not found: {$dumpPath}\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @new mysqli($host, $user, $password, $database, $port);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Connection failed: {$mysqli->connect_error}\n");
    exit(1);
}

if (!$mysqli->set_charset('utf8mb4')) {
    fwrite(STDERR, "Warning: failed to set utf8mb4: {$mysqli->error}\n");
}

/**
 * Normalize SQL dump encoding to UTF-8 and strip BOM.
 */
function biotern_normalize_sql_dump(string $sql): string
{
    if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
        $sql = substr($sql, 3);
    }

    if (strpos($sql, "\0") !== false) {
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($sql, 'UTF-8', 'UTF-16LE');
            if (is_string($converted) && $converted !== '') {
                $sql = $converted;
            }
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-16LE', 'UTF-8//IGNORE', $sql);
            if (is_string($converted) && $converted !== '') {
                $sql = $converted;
            }
        }
    }

    if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
        $sql = substr($sql, 3);
    }

    return $sql;
}

$sql = file_get_contents($dumpPath);
if (!is_string($sql) || trim($sql) === '') {
    fwrite(STDERR, "Dump file is empty: {$dumpPath}\n");
    $mysqli->close();
    exit(1);
}

$sql = biotern_normalize_sql_dump($sql);

echo "Importing dump from {$dumpPath} to {$host}:{$port}/{$database} ...\n";

if (!$mysqli->multi_query($sql)) {
    fwrite(STDERR, "Import failed: {$mysqli->error}\n");
    $mysqli->close();
    exit(1);
}

do {
    if ($result = $mysqli->store_result()) {
        $result->free();
    }
} while ($mysqli->more_results() && $mysqli->next_result());

if ($mysqli->errno) {
    fwrite(STDERR, "Import failed: {$mysqli->error}\n");
    $mysqli->close();
    exit(1);
}

$verifyTables = ['users', 'students', 'attendances', 'biometric_raw_logs'];
foreach ($verifyTables as $table) {
    $safeTable = '`' . str_replace('`', '``', $table) . '`';
    $res = $mysqli->query("SELECT COUNT(*) AS total FROM {$safeTable}");
    if (!$res) {
        echo "{$table}: ERROR - {$mysqli->error}" . PHP_EOL;
        continue;
    }
    $row = $res->fetch_assoc();
    $res->free();
    echo "{$table}: " . (int)($row['total'] ?? 0) . PHP_EOL;
}

echo "Full dump import completed successfully.\n";
$mysqli->close();
