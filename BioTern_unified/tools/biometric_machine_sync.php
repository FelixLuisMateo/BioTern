<?php
require_once __DIR__ . '/biometric_machine_runtime.php';
require_once __DIR__ . '/biometric_auto_import.php';

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

$connector = biometric_machine_run_command('sync');
if (!$connector['success']) {
    $message = "Machine sync failed.\n" . trim(implode("\n", $connector['output'] ?? []));
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

try {
    $importMessage = run_biometric_auto_import();
} catch (Throwable $e) {
    $message = "Attendance reconciliation failed.\n" . $e->getMessage();
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

if ($jsonMode) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Machine sync complete.',
        'connector_output' => $connector['output'] ?? [],
        'import_output' => [$importMessage],
    ]);
    exit;
}

if ($allowRedirect) {
    $_SESSION['attendance_sync_flash'] = ['type' => 'success', 'message' => $message];
    header('Location: ' . $redirect);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo $message . "\n";
