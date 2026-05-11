<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';

biotern_boot_session(isset($conn) ? $conn : null);
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login.php?next=backup-database.php');
    exit;
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Forbidden: Admin access only.';
    exit;
}

if (!function_exists('backup_clean_download_output')) {
    function backup_clean_download_output(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

if (!function_exists('backup_sql_export')) {
    function backup_sql_export(mysqli $mysqli, string $databaseName): string
    {
        $safeDatabase = str_replace('`', '``', $databaseName);
        $dump = "-- BioTern Full Database Backup\n";
        $dump .= '-- Generated: ' . date('Y-m-d H:i:s') . "\n";
        $dump .= '-- Host: ' . (defined('DB_HOST') ? DB_HOST : '') . ':' . (defined('DB_PORT') ? DB_PORT : '') . "\n";
        $dump .= '-- Database: `' . $databaseName . "`\n\n";
        $dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $dump .= "SET time_zone = \"+00:00\";\n";
        $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        $dump .= "CREATE DATABASE IF NOT EXISTS `{$safeDatabase}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;\n";
        $dump .= "USE `{$safeDatabase}`;\n\n";

        $tablesResult = $mysqli->query('SHOW FULL TABLES');
        if (!$tablesResult) {
            return $dump . "SET FOREIGN_KEY_CHECKS = 1;\n";
        }

        $tables = [];
        $views = [];
        while ($row = $tablesResult->fetch_row()) {
            $name = isset($row[0]) ? (string)$row[0] : '';
            $type = strtolower((string)($row[1] ?? ''));
            if ($name === '') {
                continue;
            }
            if ($type === 'view') {
                $views[] = $name;
            } else {
                $tables[] = $name;
            }
        }
        $tablesResult->free();

        foreach ($tables as $table) {
            $escapedTable = str_replace('`', '``', $table);
            $createResult = $mysqli->query("SHOW CREATE TABLE `{$escapedTable}`");
            if ($createResult instanceof mysqli_result) {
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
                        $values[] = $value === null ? 'NULL' : "'" . $mysqli->real_escape_string((string)$value) . "'";
                    }
                    $dump .= 'INSERT INTO `' . $escapedTable . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
                }
                $dump .= "\n";
                $dataResult->free();
            }
        }

        foreach ($views as $view) {
            $escapedView = str_replace('`', '``', $view);
            $createResult = $mysqli->query("SHOW CREATE VIEW `{$escapedView}`");
            if ($createResult instanceof mysqli_result) {
                $createRow = $createResult->fetch_assoc();
                $createResult->free();
                $createSql = (string)($createRow['Create View'] ?? '');
                if ($createSql !== '') {
                    $dump .= "DROP VIEW IF EXISTS `{$escapedView}`;\n";
                    $dump .= $createSql . ";\n\n";
                }
            }
        }

        $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $dump;
    }
}

if (strtolower(trim((string)($_GET['download'] ?? ''))) === 'sql') {
    $databaseName = defined('DB_NAME') ? (string)DB_NAME : 'biotern_db';
    $dump = backup_sql_export($conn, $databaseName);
    $fileName = 'biotern_db_backup_' . date('Ymd-His') . '.sql';
    backup_clean_download_output();
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . strlen($dump));
    header('X-Content-Type-Options: nosniff');
    echo $dump;
    exit;
}

$databaseName = defined('DB_NAME') ? (string)DB_NAME : 'biotern_db';
$databaseHost = defined('DB_HOST') ? (string)DB_HOST : 'localhost';
$databasePort = defined('DB_PORT') ? (int)DB_PORT : 3306;
$tableCount = 0;
$rowEstimate = 0;
$tableResult = $conn->query('SHOW TABLE STATUS');
if ($tableResult instanceof mysqli_result) {
    while ($row = $tableResult->fetch_assoc()) {
        $tableCount++;
        $rowEstimate += (int)($row['Rows'] ?? 0);
    }
    $tableResult->free();
}

$page_title = 'BioTern || Backup Database';
$page_body_class = 'page-transfer-tools page-database-backup';
$page_styles = [
    'assets/css/modules/pages/page-transfer-tools.css',
];
include dirname(__DIR__) . '/includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Backup Database</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                <li class="breadcrumb-item">Backup Database</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="import-sql.php" class="btn btn-outline-secondary">
                        <i class="feather-repeat me-2"></i>
                        <span>Data Transfer</span>
                    </a>
                    <a href="backup-database.php?download=sql" class="btn btn-primary">
                        <i class="feather-download-cloud me-2"></i>
                        <span>Download Backup</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-xxl py-4 transfer-page-wrap">
        <div class="card transfer-hero-card mb-4">
            <div class="card-body p-4 p-md-5">
                <span class="transfer-hero-badge">Database Backup</span>
                <h2 class="mt-3 mb-2 text-white">Download a full SQL backup</h2>
                <p class="mb-0 text-white-50">This exports the database currently connected to BioTern, including table structure and table data.</p>
                <div class="transfer-hero-actions mt-4">
                    <a href="backup-database.php?download=sql" class="btn btn-primary">
                        <i class="feather-download-cloud me-2"></i>
                        Download SQL Backup
                    </a>
                    <a href="import-sql.php" class="btn btn-light">Open Data Transfer</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold mb-2">Database</div>
                        <h5 class="mb-0"><?php echo htmlspecialchars($databaseName, ENT_QUOTES, 'UTF-8'); ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold mb-2">Connection</div>
                        <h5 class="mb-0"><?php echo htmlspecialchars($databaseHost . ':' . $databasePort, ENT_QUOTES, 'UTF-8'); ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small text-uppercase fw-semibold mb-2">Tables</div>
                        <h5 class="mb-0"><?php echo number_format($tableCount); ?> tables</h5>
                        <div class="text-muted small mt-1"><?php echo number_format($rowEstimate); ?> estimated rows</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info mb-0">
            Keep the downloaded <code>.sql</code> file somewhere safe before importing or replacing database data.
        </div>
    </div>
</div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
