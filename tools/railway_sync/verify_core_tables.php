<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$options = getopt('', ['url:']);
$url = isset($options['url']) ? trim((string)$options['url']) : '';
if ($url === '') {
    fwrite(STDERR, "Usage: php verify_core_tables.php --url=mysql://user:pass@host:port/database\n");
    exit(1);
}

$parts = parse_url($url);
if (!is_array($parts)) {
    fwrite(STDERR, "Invalid URL\n");
    exit(1);
}

$host = (string)($parts['host'] ?? '');
$port = isset($parts['port']) ? (int)$parts['port'] : 3306;
$user = isset($parts['user']) ? urldecode((string)$parts['user']) : '';
$pass = isset($parts['pass']) ? urldecode((string)$parts['pass']) : '';
$db = isset($parts['path']) ? ltrim((string)$parts['path'], '/') : '';

if ($host === '' || $user === '' || $db === '') {
    fwrite(STDERR, "URL must include host, user, and database\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);
$m = @new mysqli($host, $user, $pass, $db, $port);
if ($m->connect_errno) {
    fwrite(STDERR, "Connection failed: " . $m->connect_error . "\n");
    exit(1);
}

$tables = ['users', 'students', 'attendances', 'biometric_raw_logs', 'ojt_masterlist', 'ojt_partner_companies'];
foreach ($tables as $table) {
    $safe = '`' . str_replace('`', '``', $table) . '`';
    $res = $m->query("SELECT COUNT(*) AS total FROM $safe");
    if (!$res) {
        echo $table . ': ERROR - ' . $m->error . PHP_EOL;
        continue;
    }
    $row = $res->fetch_assoc();
    $res->free();
    echo $table . ': ' . (int)($row['total'] ?? 0) . PHP_EOL;
}

$m->close();
