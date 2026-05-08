<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$options = getopt('', ['url:', 'apply::']);
$url = isset($options['url']) ? trim((string)$options['url']) : '';
$apply = array_key_exists('apply', $options);

if ($url === '') {
    fwrite(STDERR, "Usage: php tools/railway_sync/ensure_attendance_compat.php --url=mysql://user:pass@host:port/database [--apply]\n");
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
$m->set_charset('utf8mb4');

function table_exists(mysqli $m, string $table): bool
{
    $safe = $m->real_escape_string($table);
    $res = $m->query("SHOW TABLES LIKE '{$safe}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    return $exists;
}

function column_exists(mysqli $m, string $table, string $column): bool
{
    $safeTable = str_replace('`', '``', $table);
    $safeColumn = $m->real_escape_string($column);
    $res = $m->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    return $exists;
}

function index_exists(mysqli $m, string $table, string $index): bool
{
    $safeTable = str_replace('`', '``', $table);
    $safeIndex = $m->real_escape_string($index);
    $res = $m->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    return $exists;
}

function run_step(mysqli $m, bool $apply, array &$summary, string $label, string $sql): void
{
    if (!$apply) {
        $summary[] = ['status' => 'needed', 'label' => $label];
        return;
    }
    if ($m->query($sql)) {
        $summary[] = ['status' => 'applied', 'label' => $label];
        return;
    }
    $summary[] = ['status' => 'error', 'label' => $label, 'message' => $m->error];
}

$summary = [];

if (!table_exists($m, 'system_settings')) {
    run_step(
        $m,
        $apply,
        $summary,
        'create system_settings',
        "CREATE TABLE system_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(255) NOT NULL,
            `value` LONGTEXT NULL,
            `description` VARCHAR(255) NULL,
            `category` VARCHAR(100) NOT NULL DEFAULT 'general',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `key` (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
} else {
    $requiredColumns = [
        'description' => "ALTER TABLE system_settings ADD COLUMN `description` VARCHAR(255) NULL AFTER `value`",
        'category' => "ALTER TABLE system_settings ADD COLUMN `category` VARCHAR(100) NOT NULL DEFAULT 'general' AFTER `description`",
        'created_at' => "ALTER TABLE system_settings ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "ALTER TABLE system_settings ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    ];

    foreach ($requiredColumns as $column => $sql) {
        if (!column_exists($m, 'system_settings', $column)) {
            run_step($m, $apply, $summary, "add system_settings.{$column}", $sql);
        }
    }

    if (!index_exists($m, 'system_settings', 'key') && !index_exists($m, 'system_settings', 'system_settings_key_unique')) {
        run_step($m, $apply, $summary, 'add unique key on system_settings.key', "ALTER TABLE system_settings ADD UNIQUE KEY `key` (`key`)");
    }
}

$attendanceDefaults = [
    'credit_mode' => ['actual', 'Controls whether attendance hours use real punches or are limited to the scheduled class window.'],
    'biometric_window_enabled' => ['0', 'When enabled, biometric imports can reject punches outside the machine attendance window.'],
    'scheduled_slot_display' => ['1', 'Shows scheduled class placeholders inside empty time slots.'],
    'live_timer_uses_schedule_cutoff' => ['0', 'When enabled, live student countdown stops at the scheduled end time.'],
    'apply_to_external' => ['0', 'Allows these attendance rules to apply to external attendance flows later.'],
];

if (table_exists($m, 'system_settings')) {
    foreach ($attendanceDefaults as $key => [$value, $description]) {
        $stmt = $m->prepare("SELECT id FROM system_settings WHERE `key` = ? LIMIT 1");
        if (!$stmt) {
            $summary[] = ['status' => 'error', 'label' => "check setting {$key}", 'message' => $m->error];
            continue;
        }
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $summary[] = ['status' => 'ok', 'label' => "setting {$key} exists"];
            continue;
        }

        if (!$apply) {
            $summary[] = ['status' => 'needed', 'label' => "insert default attendance setting {$key}"];
            continue;
        }

        $category = 'attendance';
        $insert = $m->prepare("INSERT INTO system_settings (`key`, `value`, `description`, `category`, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        if (!$insert) {
            $summary[] = ['status' => 'error', 'label' => "prepare insert {$key}", 'message' => $m->error];
            continue;
        }
        $insert->bind_param('ssss', $key, $value, $description, $category);
        if ($insert->execute()) {
            $summary[] = ['status' => 'applied', 'label' => "insert default attendance setting {$key}"];
        } else {
            $summary[] = ['status' => 'error', 'label' => "insert default attendance setting {$key}", 'message' => $insert->error];
        }
        $insert->close();
    }
}

$importantTables = ['students', 'sections', 'attendances', 'external_attendance', 'biometric_raw_logs', 'system_settings'];
foreach ($importantTables as $table) {
    $summary[] = [
        'status' => table_exists($m, $table) ? 'ok' : 'missing',
        'label' => "table {$table}",
    ];
}

$requiredAttendanceColumns = [
    'attendances' => ['student_id', 'attendance_date', 'morning_time_in', 'morning_time_out', 'break_time_in', 'break_time_out', 'afternoon_time_in', 'afternoon_time_out', 'total_hours', 'source', 'status'],
    'sections' => ['attendance_session', 'schedule_time_in', 'schedule_time_out', 'late_after_time', 'weekly_schedule_json'],
    'external_attendance' => ['student_id', 'attendance_date', 'morning_time_in', 'morning_time_out', 'break_time_in', 'break_time_out', 'afternoon_time_in', 'afternoon_time_out', 'total_hours', 'multiplier'],
];
foreach ($requiredAttendanceColumns as $table => $columns) {
    if (!table_exists($m, $table)) {
        continue;
    }
    foreach ($columns as $column) {
        $summary[] = [
            'status' => column_exists($m, $table, $column) ? 'ok' : 'missing',
            'label' => "column {$table}.{$column}",
        ];
    }
}

$counts = ['ok' => 0, 'needed' => 0, 'applied' => 0, 'missing' => 0, 'error' => 0];
foreach ($summary as $item) {
    $status = (string)$item['status'];
    $counts[$status] = ($counts[$status] ?? 0) + 1;
}

echo ($apply ? "APPLY" : "CHECK") . " SUMMARY\n";
foreach ($counts as $status => $count) {
    echo strtoupper($status) . ": " . $count . "\n";
}
foreach ($summary as $item) {
    echo '[' . strtoupper((string)$item['status']) . '] ' . (string)$item['label'];
    if (!empty($item['message'])) {
        echo ' - ' . (string)$item['message'];
    }
    echo "\n";
}

$m->close();
exit(($counts['error'] ?? 0) > 0 || (!$apply && (($counts['needed'] ?? 0) > 0 || ($counts['missing'] ?? 0) > 0)) ? 2 : 0);
