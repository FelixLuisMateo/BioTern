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

if (!function_exists('transfer_sql_drop_all_tables')) {
    function transfer_sql_drop_all_tables(mysqli $mysqli, string $databaseName): bool
    {
        $safeDb = $mysqli->real_escape_string($databaseName);
        $res = $mysqli->query("SELECT GROUP_CONCAT(CONCAT('`', table_name, '`')) AS tables_list FROM information_schema.tables WHERE table_schema = '{$safeDb}'");
        if (!$res) {
            return false;
        }

        $row = $res->fetch_assoc();
        $res->free();
        $tables = (string)($row['tables_list'] ?? '');
        if ($tables === '') {
            return true;
        }

        if (!$mysqli->query('SET FOREIGN_KEY_CHECKS = 0')) {
            return false;
        }

        $dropOk = $mysqli->query('DROP TABLE ' . $tables);
        $mysqli->query('SET FOREIGN_KEY_CHECKS = 1');

        return (bool)$dropOk;
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

if (!function_exists('transfer_import_students_csv')) {
    function transfer_import_students_csv(mysqli $mysqli, string $csvContent, string &$message): bool
    {
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

        $normalized = [];
        foreach ($header as $column) {
            $normalized[] = strtolower(trim((string)$column));
        }

        $required = ['name', 'email'];
        foreach ($required as $field) {
            if (!in_array($field, $normalized, true)) {
                fclose($stream);
                $message = 'CSV must contain required columns: name and email.';
                return false;
            }
        }

        $idx = array_flip($normalized);
        $imported = 0;

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
            fclose($stream);
            $message = 'Failed to prepare import statement: ' . $mysqli->error;
            return false;
        }

        while (($row = fgetcsv($stream)) !== false) {
            if (!is_array($row) || count($row) === 0) {
                continue;
            }

            $name = trim((string)($row[$idx['name']] ?? ''));
            $email = trim((string)($row[$idx['email']] ?? ''));
            if ($name === '' || $email === '') {
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
            }
        }

        $stmt->close();
        fclose($stream);
        $message = 'Student import completed. Rows processed: ' . $imported . '.';
        return true;
    }
}

$download = strtolower(trim((string)($_GET['download'] ?? '')));
if ($download === 'sql') {
    $dump = transfer_sql_export($conn, (string)DB_NAME);
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="database-export-' . date('Ymd-His') . '.sql"');
    echo $dump;
    exit;
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
if ($download === 'students_template') {
    transfer_send_students_template();
}

$csrfToken = transfer_csrf_token();
$statusType = '';
$statusMessage = '';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedToken)) {
        $statusType = 'danger';
        $statusMessage = 'Invalid request token. Please refresh and try again.';
    } else {
        $action = strtolower(trim((string)($_POST['action'] ?? '')));

        if ($action === 'sql_import') {
            $replaceAll = isset($_POST['replace_all']) && (string)$_POST['replace_all'] === '1';
            $pastedSql = (string)($_POST['sql_text'] ?? '');
            $sqlContent = '';

            if (isset($_FILES['sql_file']) && is_array($_FILES['sql_file']) && (int)($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploadError = (int)($_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $statusType = 'danger';
                    $statusMessage = 'SQL file upload failed. Error code: ' . $uploadError;
                } else {
                    $tmpName = (string)($_FILES['sql_file']['tmp_name'] ?? '');
                    $originalName = strtolower((string)($_FILES['sql_file']['name'] ?? ''));
                    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                        $statusType = 'danger';
                        $statusMessage = 'Uploaded SQL file is invalid.';
                    } elseif (!str_ends_with($originalName, '.sql')) {
                        $statusType = 'danger';
                        $statusMessage = 'Only .sql files are allowed.';
                    } else {
                        $loaded = @file_get_contents($tmpName);
                        if (!is_string($loaded) || $loaded === '') {
                            $statusType = 'danger';
                            $statusMessage = 'Uploaded SQL file is empty or unreadable.';
                        } else {
                            $sqlContent = $loaded;
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
                    $statusMessage = 'SQL content is empty after cleanup.';
                }
            }

            if ($statusType === '') {
                $errorMessage = '';

                if ($replaceAll && !transfer_sql_drop_all_tables($conn, (string)DB_NAME)) {
                    $statusType = 'danger';
                    $statusMessage = 'Could not drop existing tables: ' . $conn->error;
                }

                if ($statusType === '') {
                    if (!transfer_sql_execute_multi($conn, $sqlContent, $errorMessage)) {
                        $statusType = 'danger';
                        $statusMessage = 'Import failed: ' . $errorMessage;
                    } else {
                        $statusType = 'success';
                        $statusMessage = 'SQL import completed successfully.';
                    }
                }
            }
        } elseif ($action === 'students_import') {
            $csvContent = '';
            if (isset($_FILES['students_file']) && is_array($_FILES['students_file'])) {
                $uploadError = (int)($_FILES['students_file']['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadError !== UPLOAD_ERR_OK) {
                    $statusType = 'danger';
                    $statusMessage = 'Students file upload failed. Error code: ' . $uploadError;
                } else {
                    $tmpName = (string)($_FILES['students_file']['tmp_name'] ?? '');
                    $originalName = strtolower((string)($_FILES['students_file']['name'] ?? ''));
                    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                        $statusType = 'danger';
                        $statusMessage = 'Uploaded students file is invalid.';
                    } elseif (!str_ends_with($originalName, '.csv') && !str_ends_with($originalName, '.txt')) {
                        $statusType = 'danger';
                        $statusMessage = 'Students import accepts .csv or .txt files (Excel CSV export).';
                    } else {
                        $loaded = @file_get_contents($tmpName);
                        if (!is_string($loaded) || trim($loaded) === '') {
                            $statusType = 'danger';
                            $statusMessage = 'Students file is empty or unreadable.';
                        } else {
                            $csvContent = $loaded;
                        }
                    }
                }
            } else {
                $statusType = 'danger';
                $statusMessage = 'Please upload a students CSV file.';
            }

            if ($statusType === '') {
                $importMessage = '';
                if (!transfer_import_students_csv($conn, $csvContent, $importMessage)) {
                    $statusType = 'danger';
                    $statusMessage = $importMessage;
                } else {
                    $statusType = 'success';
                    $statusMessage = $importMessage;
                }
            }
        }
    }
}

$assetPrefix = '../';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Data Transfer</title>
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/theme.min.css">
</head>
<body>
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card mb-4">
                    <div class="card-body p-4 p-md-5">
                        <h3 class="mb-2">Data Transfer</h3>
                        <p class="text-muted mb-0">Target DB: <strong><?php echo htmlspecialchars((string)DB_NAME, ENT_QUOTES, 'UTF-8'); ?></strong> @ <?php echo htmlspecialchars((string)DB_HOST, ENT_QUOTES, 'UTF-8'); ?>:<?php echo (int)DB_PORT; ?></p>
                    </div>
                </div>

                <?php if ($statusMessage !== ''): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                        <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="mb-3">SQL Import / Export</h5>
                        <div class="mb-3 d-flex flex-wrap gap-2">
                            <a href="?download=sql" class="btn btn-outline-primary">Export SQL (.sql)</a>
                        </div>

                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="sql_import">

                            <div class="mb-3">
                                <label for="sql_file" class="form-label">Upload SQL file</label>
                                <input class="form-control" type="file" id="sql_file" name="sql_file" accept=".sql,text/sql">
                            </div>

                            <div class="text-center text-muted my-3">or</div>

                            <div class="mb-3">
                                <label for="sql_text" class="form-label">Paste SQL</label>
                                <textarea class="form-control" id="sql_text" name="sql_text" rows="9" placeholder="Paste SQL statements here..."></textarea>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" value="1" id="replace_all" name="replace_all">
                                <label class="form-check-label" for="replace_all">Replace existing tables before import</label>
                            </div>

                            <button type="submit" class="btn btn-primary">Import SQL</button>
                        </form>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body p-4 p-md-5">
                        <h5 class="mb-3">Students Excel / Word Import / Export</h5>

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
                                <label for="students_file" class="form-label">Import Students (Excel CSV)</label>
                                <input class="form-control" type="file" id="students_file" name="students_file" accept=".csv,.txt">
                                <div class="form-text">Use CSV format from Excel. Required columns: <code>name</code>, <code>email</code>. Optional: <code>username</code>, <code>password</code>, <code>role</code>, <code>is_active</code>, <code>profile_picture</code>.</div>
                            </div>

                            <button type="submit" class="btn btn-primary">Import Students CSV</button>
                            <a href="../homepage.php" class="btn btn-light ms-2">Back to Dashboard</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/vendors.min.js"></script>
    <script src="<?php echo htmlspecialchars($assetPrefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/common-init.min.js"></script>
</body>
</html>
