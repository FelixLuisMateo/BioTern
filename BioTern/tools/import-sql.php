<?php
require_once dirname(__DIR__) . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login-cover.php?next=import-sql.php');
    exit;
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Forbidden: Admin access only.';
    exit;
}

if (!function_exists('transfer_csrf_token')) {
    function transfer_csrf_token(): string
    {
        $token = (string)($_SESSION['transfer_sql_csrf'] ?? '');
        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['transfer_sql_csrf'] = $token;
        }
        return $token;
    }
}

if (!function_exists('transfer_sql_normalize')) {
    function transfer_sql_normalize(string $sql): string
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
        $sql = str_replace("\r\n", "\n", $sql);
        $sql = preg_replace('/^\s*CREATE\s+DATABASE\b[^;]*;\s*$/mi', '', $sql);
        $sql = preg_replace('/^\s*USE\s+`?[^`\s;]+`?\s*;\s*$/mi', '', $sql);
        return trim((string)$sql);
    }
}

if (!function_exists('transfer_host_is_local_target')) {
    function transfer_host_is_local_target(string $host): bool
    {
        $normalized = strtolower(trim($host));
        if ($normalized === '') {
            return true;
        }

        if (in_array($normalized, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
            return true;
        }

        if (preg_match('/^127\./', $normalized)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('transfer_sql_inspect')) {
    function transfer_sql_inspect(mysqli $mysqli, string $sql, string $databaseName): array
    {
        preg_match_all('/CREATE\s+DATABASE\b/i', $sql, $mCreateDb);
        preg_match_all('/USE\s+`?([A-Za-z0-9_\-]+)`?/i', $sql, $mUseDb);
        preg_match_all('/CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?/i', $sql, $mCreateTable);
        preg_match_all('/DROP\s+TABLE(?:\s+IF\s+EXISTS)?\s+`?([A-Za-z0-9_]+)`?/i', $sql, $mDropTable);
        preg_match_all('/INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?/i', $sql, $mInsertInto);
        preg_match_all('/ALTER\s+TABLE\s+`?([A-Za-z0-9_]+)`?/i', $sql, $mAlterTable);

        $createTables = array_values(array_unique(array_map('strval', $mCreateTable[1] ?? [])));
        $dropTables = array_values(array_unique(array_map('strval', $mDropTable[1] ?? [])));
        $insertTables = array_values(array_unique(array_map('strval', $mInsertInto[1] ?? [])));
        $alterTables = array_values(array_unique(array_map('strval', $mAlterTable[1] ?? [])));
        $mentionedTables = array_values(array_unique(array_merge($createTables, $dropTables, $insertTables, $alterTables)));

        $safeDb = $mysqli->real_escape_string($databaseName);
        $currentTableCount = 0;
        $res = $mysqli->query("SELECT COUNT(*) AS total_count FROM information_schema.tables WHERE table_schema = '{$safeDb}'");
        if ($res instanceof mysqli_result) {
            $row = $res->fetch_assoc();
            $currentTableCount = (int)($row['total_count'] ?? 0);
            $res->free();
        }

        $createCount = count($createTables);
        $dropCount = count($dropTables);
        $mentionedCount = count($mentionedTables);
        $hasCreateDatabase = !empty($mCreateDb[0]);
        $hasUseDatabase = !empty($mUseDb[0]);

        $looksFullDump = false;
        if ($currentTableCount > 0 && $createCount > 0 && $createCount >= max(3, (int)floor($currentTableCount * 0.6))) {
            $looksFullDump = true;
        }
        if ($hasCreateDatabase && $createCount >= 3) {
            $looksFullDump = true;
        }
        if ($dropCount > 0 && $createCount > 0 && $dropCount >= max(3, (int)floor($createCount * 0.6))) {
            $looksFullDump = true;
        }

        $looksPartialDump = false;
        if ($mentionedCount > 0 && $mentionedCount <= 2 && !$hasCreateDatabase && !$hasUseDatabase) {
            $looksPartialDump = true;
        }
        if ($currentTableCount > 0 && $mentionedCount > 0 && $mentionedCount < max(3, (int)floor($currentTableCount * 0.35))) {
            $looksPartialDump = true;
        }

        $riskLevel = 'medium';
        if ($looksPartialDump) {
            $riskLevel = 'high';
        } elseif ($looksFullDump) {
            $riskLevel = 'low';
        }

        return [
            'create_database_count' => count($mCreateDb[0] ?? []),
            'use_database_count' => count($mUseDb[0] ?? []),
            'create_table_count' => $createCount,
            'drop_table_count' => $dropCount,
            'insert_table_count' => count($insertTables),
            'alter_table_count' => count($alterTables),
            'mentioned_table_count' => $mentionedCount,
            'create_tables' => $createTables,
            'mentioned_tables' => $mentionedTables,
            'current_table_count' => $currentTableCount,
            'looks_full_dump' => $looksFullDump,
            'looks_partial_dump' => $looksPartialDump,
            'risk_level' => $riskLevel,
        ];
    }
}

if (!function_exists('transfer_sql_drop_all_tables')) {
    function transfer_sql_drop_all_tables(mysqli $mysqli, string $databaseName, string &$errorMessage = '', array &$summary = []): bool
    {
        $safeDb = $mysqli->real_escape_string($databaseName);
        $summary['dropped_views'] = 0;
        $summary['dropped_tables'] = 0;
        $summary['remaining_objects'] = 0;

        $objects = [];
        $res = $mysqli->query("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.tables WHERE table_schema = '{$safeDb}' ORDER BY CASE WHEN TABLE_TYPE = 'VIEW' THEN 0 ELSE 1 END, TABLE_NAME ASC");
        if (!$res) {
            $errorMessage = 'Unable to read database objects: ' . $mysqli->error;
            return false;
        }

        while ($row = $res->fetch_assoc()) {
            $objects[] = [
                'name' => (string)($row['TABLE_NAME'] ?? ''),
                'type' => strtoupper((string)($row['TABLE_TYPE'] ?? 'BASE TABLE')),
            ];
        }
        $res->free();

        if (empty($objects)) {
            return true;
        }

        $mysqli->query('SET FOREIGN_KEY_CHECKS = 0');
        $mysqli->query('SET UNIQUE_CHECKS = 0');
        $mysqli->query('SET SQL_NOTES = 0');

        $errors = [];
        foreach ($objects as $object) {
            $name = str_replace('`', '``', (string)($object['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $type = (string)($object['type'] ?? 'BASE TABLE');
            $dropSql = $type === 'VIEW'
                ? "DROP VIEW IF EXISTS `{$name}`"
                : "DROP TABLE IF EXISTS `{$name}`";

            if ($mysqli->query($dropSql)) {
                if ($type === 'VIEW') {
                    $summary['dropped_views'] = (int)($summary['dropped_views'] ?? 0) + 1;
                } else {
                    $summary['dropped_tables'] = (int)($summary['dropped_tables'] ?? 0) + 1;
                }
                continue;
            }

            $errors[] = $type . ' `' . $name . '`: ' . $mysqli->error;
        }

        $mysqli->query('SET SQL_NOTES = 1');
        $mysqli->query('SET UNIQUE_CHECKS = 1');
        $mysqli->query('SET FOREIGN_KEY_CHECKS = 1');

        $remainingResult = $mysqli->query("SELECT COUNT(*) AS remaining_count FROM information_schema.tables WHERE table_schema = '{$safeDb}'");
        if ($remainingResult instanceof mysqli_result) {
            $remainingRow = $remainingResult->fetch_assoc();
            $summary['remaining_objects'] = (int)($remainingRow['remaining_count'] ?? 0);
            $remainingResult->free();
        }

        if (($summary['remaining_objects'] ?? 0) > 0 || !empty($errors)) {
            $messageParts = [];
            if (($summary['remaining_objects'] ?? 0) > 0) {
                $messageParts[] = 'Remaining objects after force drop: ' . (int)$summary['remaining_objects'];
            }
            if (!empty($errors)) {
                $messageParts[] = 'Drop errors: ' . implode(' | ', array_slice($errors, 0, 3));
            }
            $errorMessage = implode('. ', $messageParts);
            return false;
        }

        return true;
    }
}

if (!function_exists('transfer_sql_execute_multi')) {
    function transfer_sql_execute_multi(mysqli $mysqli, string $sql, string &$errorMessage): bool
    {
        if (!$mysqli->multi_query($sql)) {
            $errorMessage = (string)$mysqli->error;
            return false;
        }

        do {
            $result = $mysqli->store_result();
            if ($result instanceof mysqli_result) {
                $result->free();
            }

            if ($mysqli->errno) {
                $errorMessage = (string)$mysqli->error;
                return false;
            }
        } while ($mysqli->more_results() && $mysqli->next_result());

        if ($mysqli->errno) {
            $errorMessage = (string)$mysqli->error;
            return false;
        }

        return true;
    }
}

if (!function_exists('transfer_sql_table_exists')) {
    function transfer_sql_table_exists(mysqli $mysqli, string $tableName): bool
    {
        $escaped = $mysqli->real_escape_string($tableName);
        $res = $mysqli->query("SHOW TABLES LIKE '{$escaped}'");
        if (!$res) {
            return false;
        }
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
}

if (!function_exists('transfer_sql_column_exists')) {
    function transfer_sql_column_exists(mysqli $mysqli, string $tableName, string $columnName): bool
    {
        $safeTable = str_replace('`', '``', $tableName);
        $safeColumn = $mysqli->real_escape_string($columnName);
        $res = $mysqli->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        if (!$res) {
            return false;
        }
        $exists = $res->num_rows > 0;
        $res->free();
        return $exists;
    }
}

if (!function_exists('transfer_sql_apply_missing_columns')) {
    function transfer_sql_apply_missing_columns(mysqli $mysqli, string $tableName, string $createBody, array &$summary): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $createBody) ?: [];
        $safeTable = str_replace('`', '``', $tableName);

        foreach ($lines as $line) {
            $trimmed = trim((string)$line);
            if ($trimmed === '' || strpos($trimmed, '`') !== 0) {
                continue;
            }

            $trimmed = rtrim($trimmed, ',');
            if (!preg_match('/^`([^`]+)`\s+(.+)$/', $trimmed, $matches)) {
                continue;
            }

            $columnName = (string)$matches[1];
            $columnDef = (string)$matches[2];
            if (transfer_sql_column_exists($mysqli, $tableName, $columnName)) {
                continue;
            }

            $safeColumn = str_replace('`', '``', $columnName);
            $alter = "ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$columnDef}";
            if ($mysqli->query($alter)) {
                $summary['added_columns'] = (int)($summary['added_columns'] ?? 0) + 1;
            }
        }
    }
}

if (!function_exists('transfer_sql_prepare_merge_statements')) {
    function transfer_sql_prepare_merge_statements(mysqli $mysqli, string $sql, array &$summary): string
    {
        $summary['existing_tables_seen'] = 0;
        $summary['new_tables_seen'] = 0;
        $summary['added_columns'] = 0;

        $pattern = '/CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s*\((.*?)\)\s*ENGINE\s*=.*?;/is';
        $processed = preg_replace_callback($pattern, function (array $m) use ($mysqli, &$summary) {
            $table = (string)($m[1] ?? '');
            $body = (string)($m[2] ?? '');
            if ($table === '') {
                return (string)$m[0];
            }

            if (transfer_sql_table_exists($mysqli, $table)) {
                $summary['existing_tables_seen'] = (int)($summary['existing_tables_seen'] ?? 0) + 1;
                transfer_sql_apply_missing_columns($mysqli, $table, $body, $summary);
                return '';
            }

            $summary['new_tables_seen'] = (int)($summary['new_tables_seen'] ?? 0) + 1;
            return (string)$m[0];
        }, $sql);

        return is_string($processed) ? $processed : $sql;
    }
}

if (!function_exists('transfer_sql_split_statements')) {
    function transfer_sql_split_statements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $prev = $i > 0 ? $sql[$i - 1] : '';

            if ($ch === "'" && !$inDouble && $prev !== '\\') {
                $inSingle = !$inSingle;
            } elseif ($ch === '"' && !$inSingle && $prev !== '\\') {
                $inDouble = !$inDouble;
            }

            if ($ch === ';' && !$inSingle && !$inDouble) {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
            } else {
                $buffer .= $ch;
            }
        }

        $tail = trim($buffer);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}

if (!function_exists('transfer_sql_execute_merge')) {
    function transfer_sql_execute_merge(mysqli $mysqli, string $sql, array &$summary, string &$errorMessage): bool
    {
        $ignorableCodes = [1050, 1060, 1061, 1062, 1091, 1831];
        $statements = transfer_sql_split_statements($sql);
        $summary['executed_statements'] = 0;
        $summary['ignored_statement_errors'] = 0;

        foreach ($statements as $statement) {
            $stmt = trim($statement);
            if ($stmt === '' || strpos($stmt, '--') === 0 || strpos($stmt, '/*') === 0) {
                continue;
            }

            if (preg_match('/^INSERT\s+INTO\s+/i', $stmt)) {
                $stmt = preg_replace('/^INSERT\s+INTO\s+/i', 'INSERT IGNORE INTO ', $stmt);
            }

            $ok = $mysqli->query($stmt);
            if ($ok) {
                $summary['executed_statements'] = (int)($summary['executed_statements'] ?? 0) + 1;
                continue;
            }

            $code = (int)$mysqli->errno;
            if (in_array($code, $ignorableCodes, true)) {
                $summary['ignored_statement_errors'] = (int)($summary['ignored_statement_errors'] ?? 0) + 1;
                continue;
            }

            $errorMessage = 'Statement failed (' . $code . '): ' . $mysqli->error;
            return false;
        }

        return true;
    }
}

if (!function_exists('transfer_sql_export')) {
    function transfer_sql_export(mysqli $mysqli, string $databaseName): string
    {
        $dump = "-- BioTern Unified SQL Export\n";
        $dump .= '-- Generated: ' . date('Y-m-d H:i:s') . "\n";
        $dump .= '-- Database: `' . $databaseName . "`\n\n";
        $dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tablesResult = $mysqli->query('SHOW TABLES');
        if (!$tablesResult) {
            return $dump;
        }

        $tables = [];
        while ($row = $tablesResult->fetch_row()) {
            if (isset($row[0])) {
                $tables[] = (string)$row[0];
            }
        }
        $tablesResult->free();

        foreach ($tables as $table) {
            $escapedTable = str_replace('`', '``', $table);
            $createResult = $mysqli->query("SHOW CREATE TABLE `{$escapedTable}`");
            if ($createResult) {
                $createRow = $createResult->fetch_assoc();
                $createResult->free();
                $createSql = (string)($createRow['Create Table'] ?? '');
                if ($createSql !== '') {
                    $dump .= "DROP TABLE IF EXISTS `{$escapedTable}`;\n";
                    $dump .= $createSql . ";\n\n";
                }
            }

            $dataResult = $mysqli->query("SELECT * FROM `{$escapedTable}`");
            if ($dataResult instanceof mysqli_result && $dataResult->num_rows > 0) {
                while ($dataRow = $dataResult->fetch_assoc()) {
                    $columns = [];
                    $values = [];
                    foreach ($dataRow as $column => $value) {
                        $columns[] = '`' . str_replace('`', '``', (string)$column) . '`';
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . $mysqli->real_escape_string((string)$value) . "'";
                        }
                    }

                    $dump .= 'INSERT INTO `' . $escapedTable . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
                }
                $dump .= "\n";
                $dataResult->free();
            }
        }

        $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $dump;
    }
}

if (!function_exists('transfer_students_rows')) {
    function transfer_students_rows(mysqli $mysqli): array
    {
        $rows = [];
        $sql = "SELECT id, name, username, email, role, is_active, profile_picture, created_at, updated_at FROM users WHERE role = 'student' ORDER BY id ASC";
        $result = $mysqli->query($sql);
        if (!$result) {
            return $rows;
        }
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $result->free();
        return $rows;
    }
}

if (!function_exists('transfer_send_students_csv')) {
    function transfer_send_students_csv(mysqli $mysqli): void
    {
        $rows = transfer_students_rows($mysqli);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="students-export-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        if ($out !== false) {
            fputcsv($out, ['id', 'name', 'username', 'email', 'role', 'is_active', 'profile_picture', 'created_at', 'updated_at']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    (string)($row['id'] ?? ''),
                    (string)($row['name'] ?? ''),
                    (string)($row['username'] ?? ''),
                    (string)($row['email'] ?? ''),
                    (string)($row['role'] ?? ''),
                    (string)($row['is_active'] ?? ''),
                    (string)($row['profile_picture'] ?? ''),
                    (string)($row['created_at'] ?? ''),
                    (string)($row['updated_at'] ?? ''),
                ]);
            }
            fclose($out);
        }
        exit;
    }
}

if (!function_exists('transfer_send_students_xls')) {
    function transfer_send_students_xls(mysqli $mysqli): void
    {
        $rows = transfer_students_rows($mysqli);
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="students-export-' . date('Ymd-His') . '.xls"');
        echo '<table border="1">';
        echo '<tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Is Active</th><th>Profile Picture</th><th>Created At</th><th>Updated At</th></tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string)($row['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['username'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['role'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['is_active'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['profile_picture'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        exit;
    }
}

if (!function_exists('transfer_send_students_word')) {
    function transfer_send_students_word(mysqli $mysqli): void
    {
        $rows = transfer_students_rows($mysqli);
        header('Content-Type: application/msword; charset=UTF-8');
        header('Content-Disposition: attachment; filename="students-export-' . date('Ymd-His') . '.doc"');
        echo '<html><head><meta charset="utf-8"><title>Students Export</title></head><body>';
        echo '<h2>Students Export</h2>';
        echo '<table border="1" cellpadding="6" cellspacing="0">';
        echo '<tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Is Active</th><th>Profile Picture</th><th>Created At</th><th>Updated At</th></tr>';
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars((string)($row['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['username'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['role'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['is_active'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['profile_picture'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string)($row['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }
}

if (!function_exists('transfer_send_students_template')) {
    function transfer_send_students_template(): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="students-import-template.csv"');
        $out = fopen('php://output', 'w');
        if ($out !== false) {
            fputcsv($out, ['name', 'username', 'email', 'password', 'role', 'is_active', 'profile_picture']);
            fputcsv($out, ['Juan Dela Cruz', 'juandelacruz', 'juan@example.com', 'password123', 'student', '1', '']);
            fclose($out);
        }
        exit;
    }
}

if (!function_exists('transfer_student_username')) {
    function transfer_student_username(string $name, string $email): string
    {
        $base = $email !== '' ? strstr($email, '@', true) : '';
        if ($base === false || $base === null || $base === '') {
            $base = preg_replace('/\s+/', '', strtolower($name));
        }
        $base = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$base);
        if ($base === '') {
            $base = 'student' . time();
        }
        return substr((string)$base, 0, 255);
    }
}

if (!function_exists('transfer_password_hash')) {
    function transfer_password_hash(string $password): string
    {
        $trimmed = trim($password);
        if ($trimmed === '') {
            $trimmed = 'password123';
        }

        $info = password_get_info($trimmed);
        if (($info['algo'] ?? 0) !== 0) {
            return $trimmed;
        }

        $hashed = password_hash($trimmed, PASSWORD_DEFAULT);
        return $hashed !== false ? $hashed : $trimmed;
    }
}

if (!function_exists('transfer_bool_from_text')) {
    function transfer_bool_from_text(string $value): int
    {
        $value = strtolower(trim($value));
        if (in_array($value, ['1', 'true', 'yes', 'y', 'active'], true)) {
            return 1;
        }
        if (in_array($value, ['0', 'false', 'no', 'n', 'inactive'], true)) {
            return 0;
        }
        return 1;
    }
}

// Add PhpSpreadsheet for .xlsx support
$transferVendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($transferVendorAutoload)) {
    require_once $transferVendorAutoload;
}

if (!function_exists('transfer_import_students_csv')) {
    function transfer_import_students_csv(mysqli $mysqli, string $csvContent, string &$message, $isXlsx = false, $xlsxPath = ''): bool
    {
        $rows = [];
        if ($isXlsx && $xlsxPath !== '' && file_exists($xlsxPath)) {
            if (!class_exists('PhpOffice\\PhpSpreadsheet\\IOFactory')) {
                $message = 'XLSX import requires PhpSpreadsheet. Install it in this project or upload CSV instead.';
                return false;
            }
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
                $sheet = $spreadsheet->getActiveSheet();
                $header = [];
                foreach ($sheet->getRowIterator() as $rowIndex => $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);
                    $rowData = [];
                    foreach ($cellIterator as $cell) {
                        $rowData[] = trim((string)$cell->getValue());
                    }
                    if ($rowIndex === 1) {
                        $header = array_map('strtolower', $rowData);
                        continue;
                    }
                    $rows[] = $rowData;
                }
            } catch (Throwable $e) {
                $message = 'Failed to read Excel file: ' . $e->getMessage();
                return false;
            }
        } else {
            $stream = fopen('php://temp', 'r+');
            if ($stream === false) {
                $message = 'Unable to open temporary stream for CSV.';
                return false;
            }
            fwrite($stream, $csvContent);
            rewind($stream);
            $header = fgetcsv($stream);
            if (!is_array($header) || count($header) === 0) {
                fclose($stream);
                $message = 'CSV header is missing.';
                return false;
            }
            $header = array_map('strtolower', $header);
            while (($row = fgetcsv($stream)) !== false) {
                $rows[] = $row;
            }
            fclose($stream);
        }

        $required = ['name', 'email'];
        foreach ($required as $field) {
            if (!in_array($field, $header, true)) {
                $message = 'Import must contain required columns: name and email.';
                return false;
            }
        }
        $idx = array_flip($header);
        $imported = 0;
        $failed = 0;
        $failedRows = [];

        $sql = "INSERT INTO users (name, username, email, password, role, is_active, application_status, profile_picture, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 'approved', ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    username = VALUES(username),
                    password = VALUES(password),
                    role = VALUES(role),
                    is_active = VALUES(is_active),
                    profile_picture = VALUES(profile_picture),
                    updated_at = NOW()";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            $message = 'Failed to prepare import statement: ' . $mysqli->error;
            return false;
        }

        foreach ($rows as $rowNum => $row) {
            if (!is_array($row) || count($row) === 0) {
                continue;
            }
            $name = trim((string)($row[$idx['name']] ?? ''));
            $email = trim((string)($row[$idx['email']] ?? ''));
            if ($name === '' || $email === '') {
                $failed++;
                $failedRows[] = $rowNum + 2; // +2 for header and 0-index
                continue;
            }
            $usernameRaw = trim((string)($row[$idx['username']] ?? ''));
            $passwordRaw = (string)($row[$idx['password']] ?? '');
            $roleRaw = strtolower(trim((string)($row[$idx['role']] ?? 'student')));
            $isActiveRaw = (string)($row[$idx['is_active']] ?? '1');
            $profilePicture = trim((string)($row[$idx['profile_picture']] ?? ''));

            $username = $usernameRaw !== '' ? substr($usernameRaw, 0, 255) : transfer_student_username($name, $email);
            $passwordHash = transfer_password_hash($passwordRaw);
            $allowedRoles = ['admin', 'coordinator', 'supervisor', 'student'];
            $userRole = in_array($roleRaw, $allowedRoles, true) ? $roleRaw : 'student';
            $isActive = transfer_bool_from_text($isActiveRaw);

            $stmt->bind_param('sssssis', $name, $username, $email, $passwordHash, $userRole, $isActive, $profilePicture);
            if ($stmt->execute()) {
                $imported++;
            } else {
                $failed++;
                $failedRows[] = $rowNum + 2;
            }
        }
        $stmt->close();
        $msg = 'Student import completed. Rows imported: ' . $imported . '.';
        if ($failed > 0) {
            $msg .= ' Failed: ' . $failed . ' (rows: ' . implode(', ', $failedRows) . ')';
        }
        $message = $msg;
        return $imported > 0;
    }
}

$action = strtolower(trim((string)($_POST['action'] ?? $_GET['action'] ?? '')));
$download = strtolower(trim((string)($_GET['download'] ?? '')));
$statusType = '';
$statusMessage = '';
$statusDetails = [];
$csrfToken = transfer_csrf_token();
$sqlTextValue = (string)($_POST['sql_text'] ?? '');
$replaceAllChecked = isset($_POST['replace_all']) && (string)$_POST['replace_all'] === '1';
$mergeSchemaChecked = !isset($_POST['merge_schema']) || (string)($_POST['merge_schema'] ?? '1') === '1';
$showStudentsEditLink = false;
$confirmReplaceBackup = isset($_POST['confirm_replace_backup']) && (string)$_POST['confirm_replace_backup'] === '1';
$confirmReplaceFullDump = isset($_POST['confirm_replace_full_dump']) && (string)$_POST['confirm_replace_full_dump'] === '1';
$confirmReplaceUnderstand = isset($_POST['confirm_replace_understand']) && (string)$_POST['confirm_replace_understand'] === '1';
$confirmPartialForce = isset($_POST['confirm_partial_force']) && (string)$_POST['confirm_partial_force'] === '1';
$replaceConfirmText = (string)($_POST['replace_confirm_text'] ?? '');
$sqlInspection = null;
$sqlInspectionRisk = 'medium';
$connectedHost = (string)DB_HOST;
$connectedDatabase = (string)DB_NAME;
$connectedPort = (int)DB_PORT;
$connectedIsLocalTarget = transfer_host_is_local_target($connectedHost);
$allowLocalImport = isset($_POST['allow_local_import']) && (string)$_POST['allow_local_import'] === '1';

if ($download !== '') {
    if ($download === 'students_template') {
        transfer_send_students_template();
    }
    if ($download === 'students_csv') {
        transfer_send_students_csv($conn);
    }
    if ($download === 'students_xls') {
        transfer_send_students_xls($conn);
    }
    if ($download === 'students_word') {
        transfer_send_students_word($conn);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedCsrf)) {
        $statusType = 'danger';
        $statusMessage = 'Your session token is invalid or expired. Refresh the page and try again.';
    } elseif ($action === 'students_import') {
        if (!isset($_FILES['students_file']) || !is_array($_FILES['students_file'])) {
            $statusType = 'danger';
            $statusMessage = 'Choose a CSV or XLSX file to import.';
        } else {
            $uploadError = (int)($_FILES['students_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_OK) {
                $statusType = 'danger';
                $statusMessage = 'Students file upload failed. Error code: ' . $uploadError;
            } else {
                $tmpName = (string)($_FILES['students_file']['tmp_name'] ?? '');
                $originalName = (string)($_FILES['students_file']['name'] ?? '');
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $isValidTempFile = $tmpName !== '' && (is_uploaded_file($tmpName) || is_file($tmpName));

                if (!$isValidTempFile) {
                    $statusType = 'danger';
                    $statusMessage = 'Uploaded students file is invalid.';
                } else {
                    $importMessage = '';
                    $importOk = false;

                    if ($extension === 'xlsx') {
                        $importOk = transfer_import_students_csv($conn, '', $importMessage, true, $tmpName);
                    } else {
                        $content = file_get_contents($tmpName);
                        if ($content === false) {
                            $importMessage = 'Unable to read the uploaded students file.';
                        } else {
                            $importOk = transfer_import_students_csv($conn, (string)$content, $importMessage, false, '');
                        }
                    }

                    $statusType = $importOk ? 'success' : 'danger';
                    $statusMessage = $importMessage !== '' ? $importMessage : ($importOk ? 'Student import completed successfully.' : 'Student import failed.');
                    $showStudentsEditLink = $importOk;
                }
            }
        }
    } elseif ($action === 'sql_import') {
        if ($connectedIsLocalTarget && !$allowLocalImport) {
            $statusType = 'danger';
            $statusMessage = 'Import blocked: current target is local (' . $connectedHost . '). Enable the confirmation checkbox to run locally, or switch DB env vars to Railway before importing.';
        }

        $replaceAllChecked = isset($_POST['replace_all']) && (string)$_POST['replace_all'] === '1';
        $mergeSchemaChecked = !$replaceAllChecked && (!isset($_POST['merge_schema']) || (string)$_POST['merge_schema'] === '1');
        $pastedSql = (string)($_POST['sql_text'] ?? '');
        $sqlContent = '';

        if (isset($_FILES['sql_file']) && is_array($_FILES['sql_file']) && (int)($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int)($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_OK) {
                $statusType = 'danger';
                $statusMessage = 'SQL file upload failed. Error code: ' . $uploadError;
            } else {
                $tmpName = (string)($_FILES['sql_file']['tmp_name'] ?? '');
                $isValidTempFile = $tmpName !== '' && (is_uploaded_file($tmpName) || is_file($tmpName));
                if (!$isValidTempFile) {
                    $statusType = 'danger';
                    $statusMessage = 'Uploaded SQL file is invalid.';
                } else {
                    $fileData = file_get_contents($tmpName);
                    if ($fileData === false) {
                        $statusType = 'danger';
                        $statusMessage = 'Unable to read the uploaded SQL file.';
                    } else {
                        $sqlContent = (string)$fileData;
                    }
                }
            }
        } elseif (trim($pastedSql) !== '') {
            $sqlContent = $pastedSql;
        }

        if ($statusType === '' && trim($sqlContent) === '') {
            $statusType = 'danger';
            $statusMessage = 'Provide SQL by uploading a .sql file or pasting SQL text.';
        }

        if ($statusType === '') {
            $sqlContent = transfer_sql_normalize($sqlContent);
            if ($sqlContent === '') {
                $statusType = 'danger';
                $statusMessage = 'SQL content is empty after normalization.';
            }
        }

        if ($statusType === '') {
            $errorMessage = '';
            $mergeSummary = [];
            $sqlInspection = transfer_sql_inspect($conn, $sqlContent, (string)DB_NAME);
            $sqlInspectionRisk = (string)($sqlInspection['risk_level'] ?? 'medium');
            $statusDetails[] = 'SQL tables mentioned: ' . (int)($sqlInspection['mentioned_table_count'] ?? 0);
            $statusDetails[] = 'CREATE TABLE statements: ' . (int)($sqlInspection['create_table_count'] ?? 0);
            $statusDetails[] = 'Current DB objects: ' . (int)($sqlInspection['current_table_count'] ?? 0);
            $statusDetails[] = 'Import risk assessment: ' . strtoupper($sqlInspectionRisk);
            $statusDetails[] = 'Connected host: ' . $connectedHost . ':' . $connectedPort;
            $statusDetails[] = 'Target database: ' . $connectedDatabase;

            if ($replaceAllChecked) {
                $allReplaceChecksPassed =
                    $confirmReplaceBackup
                    && $confirmReplaceFullDump
                    && $confirmReplaceUnderstand
                    && strtoupper(trim($replaceConfirmText)) === 'REPLACE DATABASE';

                if (!empty($sqlInspection['looks_partial_dump']) && !$confirmPartialForce) {
                    $statusType = 'danger';
                    $statusMessage = 'Safety block: this SQL looks like a partial dump, not a full database backup. Replace mode is blocked to prevent wiping the whole database.';
                } elseif (!$allReplaceChecksPassed) {
                    $statusType = 'danger';
                    $statusMessage = 'Safety checklist incomplete. Replace mode is blocked until every confirmation is checked and the confirmation phrase is entered.';
                }
            }

            if ($statusType === '' && $replaceAllChecked) {
                $dropSummary = [];
                if (!transfer_sql_drop_all_tables($conn, (string)DB_NAME, $errorMessage, $dropSummary)) {
                    $statusType = 'danger';
                    $statusMessage = 'Failed to drop existing tables before import. ' . $errorMessage;
                } elseif (!transfer_sql_execute_multi($conn, $sqlContent, $errorMessage)) {
                    $statusType = 'danger';
                    $statusMessage = 'SQL replace import failed: ' . $errorMessage;
                } else {
                    $statusType = 'success';
                    $statusMessage = 'SQL import completed successfully in replace mode.';
                    $statusDetails[] = 'Views dropped: ' . (int)($dropSummary['dropped_views'] ?? 0);
                    $statusDetails[] = 'Tables dropped: ' . (int)($dropSummary['dropped_tables'] ?? 0);
                }
            } elseif ($statusType === '' && $mergeSchemaChecked) {
                $preparedSql = transfer_sql_prepare_merge_statements($conn, $sqlContent, $mergeSummary);
                if (!transfer_sql_execute_merge($conn, $preparedSql, $mergeSummary, $errorMessage)) {
                    $statusType = 'danger';
                    $statusMessage = 'SQL merge import failed: ' . $errorMessage;
                } else {
                    $statusType = 'success';
                    $statusMessage = 'SQL import completed successfully in merge mode.';
                    $statusDetails[] = 'Existing tables matched: ' . (int)($mergeSummary['existing_tables_seen'] ?? 0);
                    $statusDetails[] = 'New tables created: ' . (int)($mergeSummary['new_tables_seen'] ?? 0);
                    $statusDetails[] = 'Missing columns added: ' . (int)($mergeSummary['added_columns'] ?? 0);
                    $statusDetails[] = 'Statements executed: ' . (int)($mergeSummary['executed_statements'] ?? 0);
                    $statusDetails[] = 'Ignored duplicate/schema conflicts: ' . (int)($mergeSummary['ignored_statement_errors'] ?? 0);
                }
            } elseif ($statusType === '') {
                if (!transfer_sql_execute_multi($conn, $sqlContent, $errorMessage)) {
                    $statusType = 'danger';
                    $statusMessage = 'SQL import failed: ' . $errorMessage;
                } else {
                    $statusType = 'success';
                    $statusMessage = 'SQL import completed successfully.';
                }
            }
        }
    }
}

$page_title = 'Data Transfer';
include dirname(__DIR__) . '/includes/header.php';
?>
<style>
.transfer-shell {
    max-width: 1180px;
    margin: 0 auto;
}
.transfer-hero {
    background: linear-gradient(135deg, #0f4c81 0%, #1f7a8c 48%, #dceff2 100%);
    color: #fff;
    border: 0;
    overflow: hidden;
}
.transfer-hero .card-body {
    padding: 2rem;
}
.transfer-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}
.transfer-kpi {
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 1rem;
    padding: 1rem 1.1rem;
}
.transfer-kpi-label {
    display: block;
    font-size: 0.76rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    opacity: 0.82;
}
.transfer-kpi-value {
    display: block;
    font-size: 1rem;
    font-weight: 700;
    margin-top: 0.25rem;
    word-break: break-word;
}
.transfer-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(290px, 0.95fr);
    gap: 1.5rem;
}
.transfer-stack {
    display: grid;
    gap: 1.5rem;
}
.transfer-panel {
    border: 1px solid #e5ebf2;
    border-radius: 1rem;
    box-shadow: 0 18px 40px rgba(16, 24, 40, 0.06);
}
.transfer-panel .card-body {
    padding: 1.5rem;
}
.transfer-badge {
    display: inline-flex;
    align-items: center;
    background: #edf6ff;
    color: #0f4c81;
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
    font-size: 0.8rem;
    font-weight: 700;
}
.transfer-mode {
    border: 1px solid #e8edf4;
    border-radius: 0.9rem;
    padding: 1rem;
    background: #fff;
    height: 100%;
}
.transfer-step {
    border: 1px solid #edf1f5;
    border-radius: 1rem;
    padding: 1rem;
    background: #fbfcfe;
}
.transfer-step + .transfer-step {
    margin-top: 0.9rem;
}
.transfer-step-number {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    background: #0f4c81;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    margin-bottom: 0.75rem;
}
.transfer-divider {
    display: flex;
    align-items: center;
    gap: 1rem;
    color: #7b8794;
    margin: 1.25rem 0;
}
.transfer-divider::before,
.transfer-divider::after {
    content: "";
    flex: 1;
    height: 1px;
    background: #e8edf3;
}
.transfer-help {
    background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
}
.transfer-note-list,
.transfer-checklist {
    margin: 0;
    padding-left: 1.1rem;
}
.transfer-note-list li,
.transfer-checklist li {
    margin-bottom: 0.55rem;
}
.app-skin-dark .transfer-panel {
    background: #16202b;
    border-color: #253243;
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.35);
}
.app-skin-dark .transfer-panel .text-muted,
.app-skin-dark .transfer-mode .text-muted,
.app-skin-dark .transfer-step .text-muted,
.app-skin-dark .transfer-help .text-muted,
.app-skin-dark .form-text,
.app-skin-dark .transfer-divider,
.app-skin-dark .transfer-checklist,
.app-skin-dark .transfer-note-list {
    color: #aab8c5 !important;
}
.app-skin-dark .transfer-panel h4,
.app-skin-dark .transfer-panel h5,
.app-skin-dark .transfer-panel h6,
.app-skin-dark .transfer-panel label,
.app-skin-dark .transfer-panel .form-label,
.app-skin-dark .transfer-panel .form-check-label {
    color: #ecf3fb;
}
.app-skin-dark .transfer-mode,
.app-skin-dark .transfer-step {
    background: #1b2632;
    border-color: #2a394b;
}
.app-skin-dark .transfer-help {
    background: linear-gradient(180deg, #182330 0%, #131c27 100%);
}
.app-skin-dark .transfer-badge {
    background: rgba(70, 155, 255, 0.14);
    color: #9fd0ff;
}
.app-skin-dark .transfer-divider::before,
.app-skin-dark .transfer-divider::after {
    background: #334354;
}
.app-skin-dark #sql_text,
.app-skin-dark #students_file,
.app-skin-dark #sql_file,
.app-skin-dark .form-control {
    background-color: #0f1720;
    border-color: #334354;
    color: #edf4fb;
}
.app-skin-dark .form-control::placeholder {
    color: #7e92a8;
}
.app-skin-dark .btn-light {
    background: #243244;
    border-color: #334354;
    color: #edf4fb;
}
.app-skin-dark .btn-outline-primary,
.app-skin-dark .btn-outline-secondary {
    color: #cfe7ff;
    border-color: #45617f;
}
.transfer-risk-high {
    border-color: #f4b5b1;
    background: #fff6f5;
}
.transfer-risk-low {
    border-color: #b9ebcd;
    background: #f4fff8;
}
.transfer-risk-medium {
    border-color: #f1deac;
    background: #fffaf0;
}
.app-skin-dark .transfer-risk-high {
    background: #2f1b1c;
    border-color: #7a3d40;
}
.app-skin-dark .transfer-risk-low {
    background: #13271d;
    border-color: #2e6a46;
}
.app-skin-dark .transfer-risk-medium {
    background: #2b2417;
    border-color: #7e6840;
}
@media (max-width: 991.98px) {
    .transfer-grid {
        grid-template-columns: 1fr;
    }
    .transfer-hero .card-body {
        padding: 1.5rem;
    }
}
</style>
<div class="container-xxl py-4">
    <?php if ($statusType !== ''): ?>
        <div class="alert alert-<?php echo $statusType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show mb-4" role="alert">
            <strong><?php echo $statusType === 'success' ? 'Success:' : 'Import error:'; ?></strong>
            <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
            <?php if ($showStudentsEditLink): ?>
                <a href="students-edit.php" class="btn btn-sm btn-success ms-2">Edit Students</a>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <?php if (!empty($statusDetails)): ?>
                <div class="mt-2 small">
                    <?php foreach ($statusDetails as $detail): ?>
                        <div><?php echo htmlspecialchars((string)$detail, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
            <div id="importStatusToast" class="toast text-bg-<?php echo $statusType === 'success' ? 'success' : 'danger'; ?> border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="7000">
                <div class="d-flex">
                    <div class="toast-body">
                        <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="transfer-shell">
        <div class="card transfer-hero mb-4">
            <div class="card-body">
                <span class="transfer-badge">Railway Database Transfer Center</span>
                <h2 class="mt-3 mb-2 text-white">Move your data with a clearer step-by-step workflow</h2>
                <p class="mb-0 text-white-50">This page helps you import SQL into the current database connection, update students from spreadsheet files, and understand which option is safest before you run anything.</p>
                <div class="transfer-kpis">
                    <div class="transfer-kpi">
                        <span class="transfer-kpi-label">Connected Host</span>
                        <span class="transfer-kpi-value"><?php echo htmlspecialchars((string)DB_HOST, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="transfer-kpi">
                        <span class="transfer-kpi-label">Target Database</span>
                        <span class="transfer-kpi-value"><?php echo htmlspecialchars((string)DB_NAME, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="transfer-kpi">
                        <span class="transfer-kpi-label">Port</span>
                        <span class="transfer-kpi-value"><?php echo (int)DB_PORT; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($connectedIsLocalTarget): ?>
            <div class="alert alert-warning border-warning-subtle mb-4" role="alert">
                <strong>Active target is local database:</strong>
                <?php echo htmlspecialchars($connectedHost . ':' . $connectedPort . ' / ' . $connectedDatabase, ENT_QUOTES, 'UTF-8'); ?>.
                Railway will not change until this page is connected to Railway DB credentials.
            </div>
        <?php endif; ?>

        <div class="transfer-grid">
            <div class="transfer-stack">
                <div class="card transfer-panel">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div>
                                <span class="transfer-badge">Main Import</span>
                                <h4 class="mt-3 mb-2">SQL import for Railway or other remote database updates</h4>
                                <p class="text-muted mb-0">Upload a SQL file for full or partial database updates. If you only need a safer incremental update, start with merge mode.</p>
                            </div>
                            <a href="homepage.php" class="btn btn-light">Back to Dashboard</a>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="transfer-mode">
                                    <h6>Merge Mode</h6>
                                    <p class="text-muted mb-0">Recommended for most Railway updates. Existing tables stay intact, missing columns can be added, and duplicate inserts are handled more safely.</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="transfer-mode">
                                    <h6>Replace Mode</h6>
                                    <p class="text-muted mb-0">Use only when your SQL file is a complete backup. This mode can remove current tables before rebuilding them.</p>
                                </div>
                            </div>
                        </div>

                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="sql_import">

                            <?php if ($connectedIsLocalTarget): ?>
                                <div class="alert alert-warning mb-3" role="alert">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input" type="checkbox" value="1" id="allow_local_import" name="allow_local_import" <?php echo $allowLocalImport ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allow_local_import">
                                            I understand this import will run on local database <?php echo htmlspecialchars($connectedHost . ':' . $connectedPort . ' / ' . $connectedDatabase, ENT_QUOTES, 'UTF-8'); ?>.
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="sql_file" class="form-label fw-semibold">1. Upload SQL file</label>
                                <input class="form-control" type="file" id="sql_file" name="sql_file" accept=".sql,application/sql,text/sql">
                                <div class="form-text">Best for exports coming from Vercel, phpMyAdmin, Adminer, or another MySQL host. Use file upload for large dumps whenever possible.</div>
                            </div>

                            <div class="transfer-divider">or use manual SQL input</div>

                            <div class="mb-3">
                                <label for="sql_text" class="form-label fw-semibold">2. Paste SQL directly</label>
                                <textarea class="form-control" id="sql_text" name="sql_text" rows="10" placeholder="Paste SQL statements here..."><?php echo htmlspecialchars($sqlTextValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="form-text">This is better for smaller SQL snippets, isolated fixes, or quick tests.</div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="transfer-step h-100">
                                        <div class="transfer-step-number">A</div>
                                        <label class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" value="1" id="replace_all" name="replace_all" <?php echo $replaceAllChecked ? 'checked' : ''; ?>>
                                            <span class="form-check-label fw-semibold">Replace existing tables before import</span>
                                        </label>
                                        <p class="text-muted mb-0">High risk. Enable only if the SQL dump contains the full schema and all records needed to rebuild the database correctly.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="transfer-step h-100">
                                        <div class="transfer-step-number">B</div>
                                        <label class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" value="1" id="merge_schema" name="merge_schema" <?php echo $mergeSchemaChecked ? 'checked' : ''; ?>>
                                            <span class="form-check-label fw-semibold">Merge schema and keep existing data</span>
                                        </label>
                                        <p class="text-muted mb-0">Best choice for routine updates. It tries to preserve current data while applying incoming schema and insert statements more carefully.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="transfer-step mb-4 <?php echo $sqlInspectionRisk === 'high' ? 'transfer-risk-high' : ($sqlInspectionRisk === 'low' ? 'transfer-risk-low' : 'transfer-risk-medium'); ?>">
                                <div class="transfer-step-number">C</div>
                                <h6>Replace Mode Safety Checklist</h6>
                                <p class="text-muted mb-3">Replace mode will not run unless this checklist is completed. This helps prevent the exact problem where a single-table SQL file wipes the whole database.</p>

                                <?php if (is_array($sqlInspection)): ?>
                                    <div class="small mb-3">
                                        <div><strong>Inspection:</strong> this SQL mentions <?php echo (int)($sqlInspection['mentioned_table_count'] ?? 0); ?> table(s), contains <?php echo (int)($sqlInspection['create_table_count'] ?? 0); ?> `CREATE TABLE` statement(s), and the current database has <?php echo (int)($sqlInspection['current_table_count'] ?? 0); ?> object(s).</div>
                                        <div><strong>Risk:</strong> <?php echo htmlspecialchars(strtoupper((string)($sqlInspection['risk_level'] ?? 'medium')), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div><strong>Assessment:</strong> <?php echo !empty($sqlInspection['looks_full_dump']) ? 'Looks like a fuller database dump.' : (!empty($sqlInspection['looks_partial_dump']) ? 'Looks like a partial export.' : 'Needs manual review before replace mode.'); ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="small text-muted mb-3">The inspection summary appears after you submit an SQL file or pasted SQL. Merge mode is safer until the dump is confirmed.</div>
                                <?php endif; ?>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="confirm_replace_backup" name="confirm_replace_backup" <?php echo $confirmReplaceBackup ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="confirm_replace_backup">I have a backup of the current database before running replace mode.</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="confirm_replace_full_dump" name="confirm_replace_full_dump" <?php echo $confirmReplaceFullDump ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="confirm_replace_full_dump">I confirmed this SQL file is intended to replace the full database, not just one table.</label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" value="1" id="confirm_replace_understand" name="confirm_replace_understand" <?php echo $confirmReplaceUnderstand ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="confirm_replace_understand">I understand replace mode can remove existing tables before importing.</label>
                                </div>

                                <div class="mb-3">
                                    <label for="replace_confirm_text" class="form-label fw-semibold">Type <code>REPLACE DATABASE</code> to unlock replace mode</label>
                                    <input type="text" class="form-control" id="replace_confirm_text" name="replace_confirm_text" value="<?php echo htmlspecialchars($replaceConfirmText, ENT_QUOTES, 'UTF-8'); ?>" placeholder="REPLACE DATABASE">
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="confirm_partial_force" name="confirm_partial_force" <?php echo $confirmPartialForce ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="confirm_partial_force">Emergency override: I still want replace mode even if the SQL inspection says this looks like a partial dump.</label>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary px-4">Run SQL Import</button>
                                <span class="text-muted align-self-center">After importing, read the status message at the top of the page for the exact result.</span>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card transfer-panel">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div>
                                <span class="transfer-badge">Table-Based Fallback</span>
                                <h5 class="mt-3 mb-2">Students Excel / Word Import / Export</h5>
                                <p class="text-muted mb-0">Use this if you only need to update student records or if a full SQL import is not the right tool for the job.</p>
                            </div>
                        </div>

                        <div class="mb-3 d-flex flex-wrap gap-2">
                            <a href="?download=students_template" class="btn btn-outline-secondary">Download Import Template (.csv)</a>
                            <a href="?download=students_csv" class="btn btn-outline-primary">Export Students CSV</a>
                            <a href="?download=students_xls" class="btn btn-outline-primary">Export Students Excel (.xls)</a>
                            <a href="?download=students_word" class="btn btn-outline-primary">Export Students Word (.doc)</a>
                        </div>

                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="students_import">

                            <div class="mb-3">
                                <label for="students_file" class="form-label fw-semibold">Upload students CSV or XLSX file</label>
                                <input class="form-control" type="file" id="students_file" name="students_file" accept=".csv,.txt,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                                <div class="form-text">Required columns: <code>name</code>, <code>email</code>. Optional columns: <code>username</code>, <code>password</code>, <code>role</code>, <code>is_active</code>, <code>profile_picture</code>.</div>
                            </div>

                            <div class="d-flex flex-wrap gap-2">
                                <button type="submit" class="btn btn-primary">Import Students</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="transfer-stack">
                <div class="card transfer-panel transfer-help">
                    <div class="card-body">
                        <span class="transfer-badge">User Guide</span>
                        <h5 class="mt-3 mb-3">What to do first</h5>
                        <div class="transfer-step">
                            <div class="transfer-step-number">1</div>
                            <h6>Confirm the target database</h6>
                            <p class="text-muted mb-0">Check the host, database name, and port shown above. Make sure this is the Railway database you actually want to update.</p>
                        </div>
                        <div class="transfer-step">
                            <div class="transfer-step-number">2</div>
                            <h6>Choose the safer import mode</h6>
                            <p class="text-muted mb-0">Use merge mode first for existing live databases. Only switch to replace mode when you are restoring a complete known-good backup.</p>
                        </div>
                        <div class="transfer-step">
                            <div class="transfer-step-number">3</div>
                            <h6>Read the result after every attempt</h6>
                            <p class="text-muted mb-0">This page now shows both a toast and a message banner. If the import fails, use the shown error to identify what needs fixing in the SQL file.</p>
                        </div>
                    </div>
                </div>

                <div class="card transfer-panel">
                    <div class="card-body">
                        <span class="transfer-badge">Checklist</span>
                        <h5 class="mt-3 mb-3">Before running an import</h5>
                        <ul class="transfer-checklist text-muted">
                            <li>Keep a backup before using replace mode.</li>
                            <li>Prefer a `.sql` file over pasted SQL for large imports.</li>
                            <li>Use merge mode for incremental updates from Vercel to Railway.</li>
                            <li>Do not retry repeatedly without reading the error message first.</li>
                        </ul>
                    </div>
                </div>

                <div class="card transfer-panel">
                    <div class="card-body">
                        <span class="transfer-badge">Recommendations</span>
                        <h5 class="mt-3 mb-3">If the import still fails</h5>
                        <ul class="transfer-note-list text-muted">
                            <li>Export a fresh SQL dump from the source system so the schema and data are consistent.</li>
                            <li>Try merge mode first if the current Railway database already has tables and data.</li>
                            <li>If the issue is limited to user records, use the students CSV or XLSX import instead of a full SQL restore.</li>
                            <li>If the error mentions a missing table or column, compare the source dump with the current Railway schema before retrying.</li>
                        </ul>
                        <div class="mt-3">
                            <a href="import-students-excel.php" class="btn btn-outline-success me-2">Open Excel Student Import</a>
                            <a href="force-drop-db.php" class="btn btn-outline-danger">Open Force Drop Utility</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($statusType !== ''): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') {
        return;
    }
    var toastEl = document.getElementById('importStatusToast');
    if (!toastEl) {
        return;
    }
    var toast = new bootstrap.Toast(toastEl);
    toast.show();
});
</script>
<?php endif; ?>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
