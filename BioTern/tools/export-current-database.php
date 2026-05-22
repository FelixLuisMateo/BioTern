<?php

require_once dirname(__DIR__) . '/config/db.php';

if (!($conn instanceof mysqli) || $conn->connect_errno) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$dumpDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'dumps';
if (!is_dir($dumpDir) && !mkdir($dumpDir, 0777, true) && !is_dir($dumpDir)) {
    fwrite(STDERR, "Unable to create dump directory: {$dumpDir}\n");
    exit(1);
}

$prefix = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($argv[1] ?? 'database_export'));
$dumpPath = $dumpDir . DIRECTORY_SEPARATOR . $prefix . '_' . date('Ymd-His') . '.sql';
$handle = fopen($dumpPath, 'wb');
if (!is_resource($handle)) {
    fwrite(STDERR, "Unable to write dump file: {$dumpPath}\n");
    exit(1);
}

function export_write($handle, string $value = ''): void
{
    fwrite($handle, $value . PHP_EOL);
}

function export_sql_value(mysqli $conn, $value, object $field): string
{
    if ($value === null) {
        return 'NULL';
    }

    $numericTypes = [
        MYSQLI_TYPE_TINY,
        MYSQLI_TYPE_SHORT,
        MYSQLI_TYPE_LONG,
        MYSQLI_TYPE_INT24,
        MYSQLI_TYPE_LONGLONG,
        MYSQLI_TYPE_FLOAT,
        MYSQLI_TYPE_DOUBLE,
        MYSQLI_TYPE_DECIMAL,
        MYSQLI_TYPE_NEWDECIMAL,
    ];

    if (in_array((int)$field->type, $numericTypes, true) && is_numeric($value)) {
        return (string)$value;
    }

    $isBinary = ((int)$field->charsetnr === 63) && is_string($value);
    if ($isBinary) {
        return $value === '' ? "''" : '0x' . bin2hex($value);
    }

    return "'" . $conn->real_escape_string((string)$value) . "'";
}

$dbName = DB_NAME;
export_write($handle, '-- BioTern database export');
export_write($handle, '-- Source: ' . DB_HOST . ':' . DB_PORT . '/' . $dbName);
export_write($handle, '-- Created at: ' . date('c'));
export_write($handle);
export_write($handle, 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";');
export_write($handle, 'SET time_zone = "+08:00";');
export_write($handle, 'SET FOREIGN_KEY_CHECKS = 0;');
export_write($handle);
export_write($handle, 'CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
export_write($handle, 'USE `' . str_replace('`', '``', $dbName) . '`;');
export_write($handle);

$tableRes = $conn->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
if (!($tableRes instanceof mysqli_result)) {
    fclose($handle);
    fwrite(STDERR, "Unable to list tables: {$conn->error}\n");
    exit(1);
}

$tables = [];
while ($row = $tableRes->fetch_array(MYSQLI_NUM)) {
    $table = (string)($row[0] ?? '');
    if ($table !== '' && preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        $tables[] = $table;
    }
}
$tableRes->close();

foreach ($tables as $table) {
    export_write($handle, '-- --------------------------------------------------------');
    export_write($handle, '-- Table structure for `' . $table . '`');
    export_write($handle);
    export_write($handle, 'DROP TABLE IF EXISTS `' . $table . '`;');

    $createRes = $conn->query('SHOW CREATE TABLE `' . $table . '`');
    if (!($createRes instanceof mysqli_result)) {
        export_write($handle, '-- Unable to export structure: ' . $conn->error);
        continue;
    }
    $createRow = $createRes->fetch_assoc();
    export_write($handle, (string)($createRow['Create Table'] ?? '') . ';');
    export_write($handle);
    $createRes->close();

    $dataRes = $conn->query('SELECT * FROM `' . $table . '`', MYSQLI_USE_RESULT);
    if (!($dataRes instanceof mysqli_result)) {
        export_write($handle, '-- Unable to export data: ' . $conn->error);
        continue;
    }

    $fields = $dataRes->fetch_fields();
    $columnNames = array_map(static fn(object $field): string => '`' . str_replace('`', '``', $field->name) . '`', $fields);
    $columnsSql = implode(', ', $columnNames);
    $batch = [];
    $rowCount = 0;
    $batchSize = 100;

    while ($row = $dataRes->fetch_assoc()) {
        $values = [];
        foreach ($fields as $field) {
            $values[] = export_sql_value($conn, $row[$field->name] ?? null, $field);
        }
        $batch[] = '(' . implode(', ', $values) . ')';
        $rowCount++;

        if (count($batch) >= $batchSize) {
            export_write($handle, 'INSERT INTO `' . $table . '` (' . $columnsSql . ') VALUES');
            export_write($handle, implode(',' . PHP_EOL, $batch) . ';');
            $batch = [];
        }
    }

    if ($batch) {
        export_write($handle, 'INSERT INTO `' . $table . '` (' . $columnsSql . ') VALUES');
        export_write($handle, implode(',' . PHP_EOL, $batch) . ';');
    }

    $dataRes->close();
    export_write($handle);
    export_write($handle, '-- Exported rows: ' . $rowCount);
    export_write($handle);
}

export_write($handle, 'SET FOREIGN_KEY_CHECKS = 1;');
fclose($handle);

echo $dumpPath . PHP_EOL;
