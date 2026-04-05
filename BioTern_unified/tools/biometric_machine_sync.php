<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/biometric_machine_runtime.php';
require_once __DIR__ . '/biometric_auto_import.php';
require_once __DIR__ . '/biometric_ops.php';

if (!function_exists('biometric_machine_sync_is_cloud_runtime')) {
    function biometric_machine_sync_is_cloud_runtime(): bool
    {
        return getenv('VERCEL') !== false
            || getenv('RAILWAY_ENVIRONMENT') !== false
            || getenv('RAILWAY_STATIC_URL') !== false
            || getenv('K_SERVICE') !== false;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$redirect = trim((string)($_GET['redirect'] ?? ''));
$allowRedirect = $redirect !== '' && preg_match('/^[A-Za-z0-9._-]+\.php$/', $redirect);
$jsonMode = strtolower(trim((string)($_GET['format'] ?? ''))) === 'json';
$triggerSource = $jsonMode ? 'auto' : 'manual';

$opsDb = new mysqli(
    defined('DB_HOST') ? DB_HOST : 'localhost',
    defined('DB_USER') ? DB_USER : 'root',
    defined('DB_PASS') ? DB_PASS : '',
    defined('DB_NAME') ? DB_NAME : 'biotern_db',
    defined('DB_PORT') ? (int)DB_PORT : 3306
);
$syncRunId = 0;
if (!$opsDb->connect_error) {
    $opsDb->set_charset('utf8mb4');
    biometric_ops_ensure_tables($opsDb);
    $syncRunId = biometric_ops_start_sync_run($opsDb, (int)($_SESSION['user_id'] ?? 0), $triggerSource);
}

$cloudMode = biometric_machine_sync_is_cloud_runtime();
$executionMode = $cloudMode ? 'cloud-direct-ingest' : 'local-connector';
$connector = [
    'success' => true,
    'stage' => $cloudMode ? 'cloud-skip' : 'run',
    'output' => [],
    'code' => 0,
    'text' => '',
];

if (!$cloudMode) {
    $connector = biometric_machine_run_command('sync');
    if (!$connector['success']) {
        $message = "Machine sync failed.\n" . trim(implode("\n", $connector['output'] ?? []));
        if ($opsDb instanceof mysqli && !$opsDb->connect_error) {
            biometric_ops_finish_sync_run($opsDb, $syncRunId, 'failed', trim(($connector['text'] ?? '') . "\n" . $message), null, 0, 0, 0, 0);
            biometric_ops_log_audit($opsDb, (int)($_SESSION['user_id'] ?? 0), (string)($_SESSION['role'] ?? ''), 'machine_sync_failed', 'machine_sync', null, ['message' => $message]);
            $opsDb->close();
        }
        if ($jsonMode) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'stage' => $connector['stage'] ?? 'run', 'message' => $message]);
            exit;
        }
        if ($allowRedirect) {
            $_SESSION['attendance_sync_flash'] = ['type' => 'danger', 'message' => $message];
            header('Location: ' . $redirect);
            exit;
        }
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        echo $message . "\n";
        exit;
    }
} else {
    $connector['text'] = 'Connector step skipped in cloud mode; processing direct-ingest queue only.';
}

try {
    $importStats = run_biometric_auto_import_stats();
    $importMessage = (string)($importStats['message'] ?? 'Biometric import completed.');
} catch (Throwable $e) {
    $message = "Attendance reconciliation failed.\n" . $e->getMessage();
    if ($opsDb instanceof mysqli && !$opsDb->connect_error) {
        biometric_ops_finish_sync_run($opsDb, $syncRunId, 'failed', (string)($connector['text'] ?? ''), $message, 0, 0, 0, 0);
        biometric_ops_log_audit($opsDb, (int)($_SESSION['user_id'] ?? 0), (string)($_SESSION['role'] ?? ''), 'machine_import_failed', 'machine_sync', null, ['message' => $message]);
        $opsDb->close();
    }
    if ($jsonMode) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'stage' => 'import', 'message' => $message]);
        exit;
    }
    if ($allowRedirect) {
        $_SESSION['attendance_sync_flash'] = ['type' => 'danger', 'message' => $message];
        header('Location: ' . $redirect);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo $message . "\n";
    exit;
}

$message = trim("Machine sync complete ({$executionMode}).\n" . ($connector['text'] ?? '') . "\n" . $importMessage);
if ($opsDb instanceof mysqli && !$opsDb->connect_error) {
    biometric_ops_finish_sync_run(
        $opsDb,
        $syncRunId,
        'success',
        (string)($connector['text'] ?? ''),
        $importMessage,
        (int)($importStats['raw_inserted'] ?? 0),
        (int)($importStats['processed_logs'] ?? 0),
        (int)($importStats['attendance_changed'] ?? 0),
        (int)($importStats['anomalies_found'] ?? 0)
    );
    biometric_ops_log_audit($opsDb, (int)($_SESSION['user_id'] ?? 0), (string)($_SESSION['role'] ?? ''), 'machine_sync_success', 'machine_sync', null, $importStats);
    $opsDb->close();
}

if ($jsonMode) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'mode' => $executionMode,
        'message' => 'Machine sync complete.',
        'connector_output' => $connector['output'] ?? [],
        'import_output' => [$importMessage],
        'stats' => $importStats,
    ]);
    exit;
}

if ($allowRedirect) {
    $_SESSION['attendance_sync_flash'] = ['type' => 'success', 'message' => 'Machine sync complete.'];
    header('Location: ' . $redirect);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo $message . "\n";
