<?php
require_once __DIR__ . '/biometric_machine_runtime.php';
require_once __DIR__ . '/biometric_auto_import.php';
require_once __DIR__ . '/biometric_ops.php';
require_once __DIR__ . '/biometric_db.php';

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
$machineConfig = loadBiometricMachineConfig();
$syncMode = strtolower(trim((string)($machineConfig['syncMode'] ?? 'direct_ingest')));
if (!in_array($syncMode, ['direct_ingest', 'connector_fallback'], true)) {
    $syncMode = 'direct_ingest';
}

$opsDb = biometric_shared_db();
$syncRunId = 0;
if (!$opsDb->connect_error) {
    $opsDb->set_charset('utf8mb4');
    biometric_ops_ensure_tables($opsDb);
    $syncRunId = biometric_ops_start_sync_run($opsDb, (int)($_SESSION['user_id'] ?? 0), $triggerSource);
}

if ($syncMode === 'connector_fallback') {
    $connector = biometric_machine_run_command('sync');
    if (!$connector['success']) {
        $message = "Machine sync failed.\n" . trim(implode("\n", $connector['output'] ?? []));
        if ($opsDb instanceof mysqli && !$opsDb->connect_error) {
            biometric_ops_finish_sync_run($opsDb, $syncRunId, 'failed', trim(($connector['text'] ?? '') . "\n" . $message), null, 0, 0, 0, 0);
            biometric_ops_log_audit($opsDb, (int)($_SESSION['user_id'] ?? 0), (string)($_SESSION['role'] ?? ''), 'machine_sync_failed', 'machine_sync', null, ['message' => $message, 'sync_mode' => $syncMode]);
            $opsDb->close();
        }
        if ($jsonMode) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'stage' => $connector['stage'] ?? 'run', 'mode' => $syncMode, 'message' => $message]);
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
    $connector = [
        'success' => true,
        'stage' => 'import',
        'output' => ['Connector sync skipped because direct ingest mode is enabled.'],
        'text' => 'Connector sync skipped because direct ingest mode is enabled.',
    ];
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

$message = trim("Machine sync complete.\n" . ($connector['text'] ?? '') . "\n" . $importMessage);
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
    biometric_ops_log_audit($opsDb, (int)($_SESSION['user_id'] ?? 0), (string)($_SESSION['role'] ?? ''), 'machine_sync_success', 'machine_sync', null, array_merge($importStats, ['sync_mode' => $syncMode]));
    $opsDb->close();
}

if ($jsonMode) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Machine sync complete.',
        'mode' => $syncMode,
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
