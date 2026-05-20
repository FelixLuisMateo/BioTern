<?php

require_once dirname(__DIR__) . '/config/db.php';

if (!($conn instanceof mysqli) || $conn->connect_errno) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$candidates = [
    'biometric_data',
    'daily_time_records',
    'hour_logs',
    'upload_settings',
];

$backupDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'dumps';
if (!is_dir($backupDir) && !mkdir($backupDir, 0777, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Unable to create backup directory: {$backupDir}\n");
    exit(1);
}

$backupPath = $backupDir . DIRECTORY_SEPARATOR . 'railway_unused_empty_tables_schema_' . date('Ymd-His') . '.sql';
$backup = [];
$dropped = [];
$skipped = [];
$failed = [];

$backup[] = '-- BioTern unused empty table schema backup';
$backup[] = '-- Database: ' . DB_NAME . ' on ' . DB_HOST . ':' . DB_PORT;
$backup[] = '-- Created at: ' . date('c');
$backup[] = '';

foreach ($candidates as $table) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        $skipped[] = "{$table} (unsafe table name)";
        continue;
    }

    $existsRes = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
    if (!($existsRes instanceof mysqli_result) || $existsRes->num_rows === 0) {
        $skipped[] = "{$table} (missing table)";
        continue;
    }
    $existsRes->close();

    $countRes = $conn->query("SELECT COUNT(*) AS total FROM `{$table}`");
    if (!($countRes instanceof mysqli_result)) {
        $failed[] = "{$table} (count failed: {$conn->error})";
        continue;
    }
    $row = $countRes->fetch_assoc();
    $countRes->close();
    $rows = (int)($row['total'] ?? 0);
    if ($rows !== 0) {
        $skipped[] = "{$table} (not empty: {$rows} rows)";
        continue;
    }

    $createRes = $conn->query("SHOW CREATE TABLE `{$table}`");
    if ($createRes instanceof mysqli_result) {
        $createRow = $createRes->fetch_assoc();
        $createSql = (string)($createRow['Create Table'] ?? '');
        if ($createSql !== '') {
            $backup[] = '-- Table: ' . $table;
            $backup[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
            $backup[] = $createSql . ';';
            $backup[] = '';
        }
        $createRes->close();
    }

    if ($conn->query("DROP TABLE `{$table}`")) {
        $dropped[] = $table;
    } else {
        $failed[] = "{$table} ({$conn->error})";
    }
}

if (!file_put_contents($backupPath, implode(PHP_EOL, $backup) . PHP_EOL)) {
    fwrite(STDERR, "Cleanup ran, but schema backup could not be written: {$backupPath}\n");
    exit(1);
}

echo "Unused empty table cleanup\n";
echo "Schema backup: {$backupPath}\n";
echo "Dropped: " . count($dropped) . "\n";
foreach ($dropped as $table) {
    echo "  + {$table}\n";
}
echo "Skipped: " . count($skipped) . "\n";
foreach ($skipped as $item) {
    echo "  - {$item}\n";
}
echo "Failed: " . count($failed) . "\n";
foreach ($failed as $item) {
    echo "  ! {$item}\n";
}

exit(count($failed) > 0 ? 2 : 0);
