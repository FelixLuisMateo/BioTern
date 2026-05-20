<?php

require_once dirname(__DIR__) . '/config/db.php';

if (!($conn instanceof mysqli) || $conn->connect_errno) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$tables = [];
$tableRes = $conn->query("
    SELECT
        TABLE_NAME,
        COALESCE(TABLE_ROWS, 0) AS estimated_rows,
        ROUND((COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0)) / 1024 / 1024, 2) AS size_mb,
        CREATE_TIME,
        UPDATE_TIME
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    ORDER BY TABLE_NAME ASC
");

if (!$tableRes) {
    fwrite(STDERR, "Unable to inspect tables: {$conn->error}\n");
    exit(1);
}

while ($row = $tableRes->fetch_assoc()) {
    $table = (string)$row['TABLE_NAME'];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        continue;
    }

    $count = null;
    $countRes = $conn->query("SELECT COUNT(*) AS total FROM `{$table}`");
    if ($countRes instanceof mysqli_result) {
        $countRow = $countRes->fetch_assoc();
        $count = (int)($countRow['total'] ?? 0);
        $countRes->close();
    }

    $columns = [];
    $colRes = $conn->query("SHOW COLUMNS FROM `{$table}`");
    if ($colRes instanceof mysqli_result) {
        while ($col = $colRes->fetch_assoc()) {
            $columns[] = (string)($col['Field'] ?? '');
        }
        $colRes->close();
    }

    $deletedCount = null;
    if (in_array('deleted_at', $columns, true)) {
        $deletedRes = $conn->query("SELECT COUNT(*) AS total FROM `{$table}` WHERE deleted_at IS NOT NULL");
        if ($deletedRes instanceof mysqli_result) {
            $deletedRow = $deletedRes->fetch_assoc();
            $deletedCount = (int)($deletedRow['total'] ?? 0);
            $deletedRes->close();
        }
    }

    $tables[] = [
        'name' => $table,
        'rows' => $count,
        'size_mb' => (float)($row['size_mb'] ?? 0),
        'create_time' => (string)($row['CREATE_TIME'] ?? ''),
        'update_time' => (string)($row['UPDATE_TIME'] ?? ''),
        'deleted_count' => $deletedCount,
        'column_count' => count($columns),
    ];
}

$empty = array_values(array_filter($tables, static fn(array $table): bool => (int)$table['rows'] === 0));
$withDeletedRows = array_values(array_filter($tables, static fn(array $table): bool => $table['deleted_count'] !== null && (int)$table['deleted_count'] > 0));
$largest = $tables;
usort($largest, static fn(array $a, array $b): int => ($b['size_mb'] <=> $a['size_mb']) ?: ($b['rows'] <=> $a['rows']));
$largest = array_slice($largest, 0, 15);

echo "Database: " . DB_NAME . " on " . DB_HOST . ":" . DB_PORT . PHP_EOL;
echo "Tables: " . count($tables) . PHP_EOL;
echo "Empty tables: " . count($empty) . PHP_EOL;
echo "Tables with soft-deleted rows: " . count($withDeletedRows) . PHP_EOL . PHP_EOL;

echo "Largest tables:" . PHP_EOL;
foreach ($largest as $table) {
    echo sprintf(
        "  %-38s rows=%-8s size=%7.2f MB updated=%s\n",
        $table['name'],
        (string)$table['rows'],
        $table['size_mb'],
        $table['update_time'] !== '' ? $table['update_time'] : '-'
    );
}

echo PHP_EOL . "Empty tables:" . PHP_EOL;
foreach ($empty as $table) {
    echo sprintf("  %-38s columns=%-3d created=%s\n", $table['name'], $table['column_count'], $table['create_time'] !== '' ? $table['create_time'] : '-');
}

echo PHP_EOL . "Soft-delete cleanup candidates:" . PHP_EOL;
foreach ($withDeletedRows as $table) {
    echo sprintf(
        "  %-38s deleted_rows=%-8d total_rows=%s\n",
        $table['name'],
        (int)$table['deleted_count'],
        (string)$table['rows']
    );
}

echo PHP_EOL . "No tables were changed. Review candidates before deleting or truncating anything." . PHP_EOL;
