<?php
// Runs a single LAN sync against the biometric machine, then imports logs into attendances.

$connectorProject = __DIR__ . '/device_connector/BioTernMachineConnector.csproj';
$connectorDll = __DIR__ . '/device_connector/bin/Release/net9.0-windows/BioTernMachineConnector.dll';
$connectorExe = __DIR__ . '/device_connector/bin/Release/net9.0-windows/BioTernMachineConnector.exe';
$configPath = __DIR__ . '/biometric_machine_config.json';
$importScript = __DIR__ . '/biometric_auto_import.php';

require_once $importScript;

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

if (!file_exists($connectorProject)) {
    http_response_code(500);
    exit('Connector project not found.');
}

if (!file_exists($configPath)) {
    http_response_code(500);
    exit('Machine config not found.');
}

if (!file_exists($importScript)) {
    http_response_code(500);
    exit('Attendance import script not found.');
}

$dotnet = 'dotnet';
$dotnetHome = dirname(__DIR__, 2) . '/.dotnet_cli';

if (!file_exists($connectorDll)) {
    $buildCommand = sprintf(
        'set DOTNET_CLI_HOME=%s && %s build %s -c Release 2>&1',
        escapeshellarg($dotnetHome),
        escapeshellarg($dotnet),
        escapeshellarg($connectorProject)
    );
    $buildOutput = [];
    $buildCode = 0;
    exec($buildCommand, $buildOutput, $buildCode);

    if ($buildCode !== 0 || !file_exists($connectorDll)) {
        if ($jsonMode) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'stage' => 'build', 'message' => "Connector build failed.\n" . implode("\n", $buildOutput)]);
            exit;
        }
        if ($allowRedirect) {
            $_SESSION['attendance_sync_flash'] = [
                'type' => 'danger',
                'message' => "Connector build failed.\n" . implode("\n", $buildOutput),
            ];
            header('Location: ' . $redirect);
            exit;
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo "Build output:\n";
        echo implode("\n", $buildOutput) . "\n\n";
        http_response_code(500);
        echo "Connector build failed.\n";
        exit;
    }
}

if (file_exists($connectorExe)) {
    $connectorCommand = sprintf(
        '%s %s 2>&1',
        escapeshellarg($connectorExe),
        escapeshellarg($configPath)
    );
} else {
    $connectorCommand = sprintf(
        'set DOTNET_CLI_HOME=%s && %s %s %s 2>&1',
        escapeshellarg($dotnetHome),
        escapeshellarg($dotnet),
        escapeshellarg($connectorDll),
        escapeshellarg($configPath)
    );
}

$connectorOutput = [];
$connectorCode = 0;
exec($connectorCommand, $connectorOutput, $connectorCode);

if ($connectorCode !== 0) {
    if ($jsonMode) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'stage' => 'connect', 'message' => "Machine sync failed.\n" . implode("\n", $connectorOutput)]);
        exit;
    }
    if ($allowRedirect) {
        $_SESSION['attendance_sync_flash'] = [
            'type' => 'danger',
            'message' => "Machine sync failed.\n" . implode("\n", $connectorOutput),
        ];
        header('Location: ' . $redirect);
        exit;
    }
    http_response_code(500);
    echo "Machine sync failed.\n";
    exit;
}

$importOutput = [];
$importCode = 0;
try {
    $importOutput[] = run_biometric_auto_import();
} catch (Throwable $e) {
    $importCode = 1;
    $importOutput[] = $e->getMessage();
}

if ($importCode !== 0) {
    if ($jsonMode) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'stage' => 'import', 'message' => "Attendance reconciliation failed.\n" . implode("\n", $importOutput)]);
        exit;
    }
    if ($allowRedirect) {
        $_SESSION['attendance_sync_flash'] = [
            'type' => 'danger',
            'message' => "Attendance reconciliation failed.\n" . implode("\n", $importOutput),
        ];
        header('Location: ' . $redirect);
        exit;
    }
    http_response_code(500);
    echo "\nAttendance reconciliation failed.\n";
    exit;
}

if ($jsonMode) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Machine sync complete.',
        'connector_output' => $connectorOutput,
        'import_output' => $importOutput,
    ]);
    exit;
}

if ($allowRedirect) {
    $_SESSION['attendance_sync_flash'] = [
        'type' => 'success',
        'message' => trim("Machine sync complete.\n" . implode("\n", array_filter([
            implode("\n", $connectorOutput),
            implode("\n", $importOutput),
        ]))),
    ];
    header('Location: ' . $redirect);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
if (!empty($buildOutput)) {
    echo "Build output:\n";
    echo implode("\n", $buildOutput) . "\n\n";
}
echo "Connector output:\n";
echo implode("\n", $connectorOutput) . "\n\n";
echo "Import output:\n";
echo implode("\n", $importOutput) . "\n";
echo "\nMachine sync complete.\n";
