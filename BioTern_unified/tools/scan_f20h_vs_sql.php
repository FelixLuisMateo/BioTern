<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require __DIR__ . '/../config/db.php';
require __DIR__ . '/biometric_auto_import.php';

$options = getopt('', [
    'begin::',
    'end::',
    'import-missing::',
    'run-auto-import::',
    'force-insert::',
    'max-preview::',
]);

$begin = trim((string)($options['begin'] ?? ''));
$end = trim((string)($options['end'] ?? ''));
if ($begin === '') {
    $begin = date('Y-m-d 00:00:00', strtotime('-14 days'));
}
if ($end === '') {
    $end = date('Y-m-d H:i:s');
}

$beginTs = strtotime($begin);
$endTs = strtotime($end);
if ($beginTs === false || $endTs === false || $endTs < $beginTs) {
    fwrite(STDERR, "Invalid range. Use --begin='YYYY-MM-DD HH:MM:SS' --end='YYYY-MM-DD HH:MM:SS'\n");
    exit(1);
}

$importMissing = false;
if (isset($options['import-missing'])) {
    $v = strtolower(trim((string)$options['import-missing']));
    $importMissing = in_array($v, ['1', 'true', 'yes', 'on', ''], true);
}

$runAutoImport = true;
if (isset($options['run-auto-import'])) {
    $v = strtolower(trim((string)$options['run-auto-import']));
    $runAutoImport = in_array($v, ['1', 'true', 'yes', 'on', ''], true);
}

$forceInsert = false;
if (isset($options['force-insert'])) {
    $v = strtolower(trim((string)$options['force-insert']));
    $forceInsert = in_array($v, ['1', 'true', 'yes', 'on', ''], true);
}

$maxPreview = isset($options['max-preview']) ? max(1, (int)$options['max-preview']) : 20;

$machineConfigPath = __DIR__ . '/biometric_machine_config.json';
$connectorExePath = __DIR__ . '/device_connector/bin/Release/net9.0-windows/BioTernMachineConnector.exe';
$connectorDllPath = __DIR__ . '/device_connector/bin/Release/net9.0-windows/BioTernMachineConnector.dll';

if (!is_file($machineConfigPath)) {
    fwrite(STDERR, "Missing machine config: {$machineConfigPath}\n");
    exit(1);
}

if (!is_file($connectorExePath) && !is_file($connectorDllPath)) {
    fwrite(STDERR, "Connector binary not found. Build device_connector first.\n");
    exit(1);
}

function scan_bool_opt(array $options, string $key, bool $default): bool
{
    if (!array_key_exists($key, $options)) {
        return $default;
    }
    $v = strtolower(trim((string)$options[$key]));
    return in_array($v, ['1', 'true', 'yes', 'on', ''], true);
}

function scan_event_key(int $fingerId, int $clockType, string $clockTime): string
{
    return $fingerId . '|' . $clockType . '|' . $clockTime;
}

function scan_extract_json_payload(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    $startArray = strpos($raw, '[');
    $startObject = strpos($raw, '{');
    $start = -1;

    if ($startArray !== false && $startObject !== false) {
        $start = min($startArray, $startObject);
    } elseif ($startArray !== false) {
        $start = $startArray;
    } elseif ($startObject !== false) {
        $start = $startObject;
    }

    if ($start < 0) {
        return '';
    }

    $candidate = trim(substr($raw, $start));
    if ($candidate === '') {
        return '';
    }

    if ($candidate[0] === '[') {
        $end = strrpos($candidate, ']');
        if ($end === false) {
            return '';
        }
        return substr($candidate, 0, $end + 1);
    }

    if ($candidate[0] === '{') {
        $end = strrpos($candidate, '}');
        if ($end === false) {
            return '';
        }
        return substr($candidate, 0, $end + 1);
    }

    return '';
}

function scan_connector_output(string $machineConfigPath, string $connectorExePath, string $connectorDllPath, string $begin, string $end): string
{
    $cfg = escapeshellarg($machineConfigPath);
    $b = escapeshellarg($begin);
    $e = escapeshellarg($end);

    if (is_file($connectorExePath)) {
        $exe = escapeshellarg($connectorExePath);
        $cmd = $exe . ' ' . $cfg . ' get-log-range ' . $b . ' ' . $e . ' 2>&1';
        $out = shell_exec($cmd);
        return is_string($out) ? $out : '';
    }

    $dll = escapeshellarg($connectorDllPath);
    $cmd = 'dotnet ' . $dll . ' ' . $cfg . ' get-log-range ' . $b . ' ' . $e . ' 2>&1';
    $out = shell_exec($cmd);
    return is_string($out) ? $out : '';
}

function scan_normalize_machine_events(array $events, int $beginTs, int $endTs): array
{
    $normalized = [];
    $seen = [];

    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }

        $fingerId = isset($event['finger_id']) ? (int)$event['finger_id'] : (isset($event['id']) ? (int)$event['id'] : 0);
        $clockType = isset($event['type']) ? (int)$event['type'] : (isset($event['clock_type']) ? (int)$event['clock_type'] : 0);
        $clockTime = trim((string)($event['time'] ?? $event['record_time'] ?? $event['timestamp'] ?? ''));

        if ($fingerId <= 0 || $clockType <= 0 || $clockTime === '') {
            continue;
        }

        $ts = strtotime($clockTime);
        if ($ts === false || $ts < $beginTs || $ts > $endTs) {
            continue;
        }

        $key = scan_event_key($fingerId, $clockType, $clockTime);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $normalized[] = [
            'id' => $fingerId,
            'finger_id' => $fingerId,
            'type' => $clockType,
            'time' => $clockTime,
            'raw_payload' => $event,
        ];
    }

    return $normalized;
}

function scan_existing_sql_keys(mysqli $conn, int $beginTs, int $endTs): array
{
    $keys = [];
    $res = $conn->query('SELECT id, raw_data FROM biometric_raw_logs');
    if (!($res instanceof mysqli_result)) {
        return $keys;
    }

    while ($row = $res->fetch_assoc()) {
        $decoded = json_decode((string)($row['raw_data'] ?? ''), true);
        if (!is_array($decoded)) {
            continue;
        }

        $fingerId = isset($decoded['finger_id']) ? (int)$decoded['finger_id'] : (isset($decoded['id']) ? (int)$decoded['id'] : 0);
        $clockType = isset($decoded['type']) ? (int)$decoded['type'] : (isset($decoded['clock_type']) ? (int)$decoded['clock_type'] : 0);
        $clockTime = trim((string)($decoded['time'] ?? $decoded['record_time'] ?? ''));

        if ($fingerId <= 0 || $clockType <= 0 || $clockTime === '') {
            continue;
        }

        $ts = strtotime($clockTime);
        if ($ts === false || $ts < $beginTs || $ts > $endTs) {
            continue;
        }

        $keys[scan_event_key($fingerId, $clockType, $clockTime)] = true;
    }

    $res->close();
    return $keys;
}

$rawConnectorOut = scan_connector_output($machineConfigPath, $connectorExePath, $connectorDllPath, $begin, $end);
$jsonPayload = scan_extract_json_payload($rawConnectorOut);
if ($jsonPayload === '') {
    fwrite(STDERR, "Failed to read machine log payload. Connector output:\n" . $rawConnectorOut . "\n");
    exit(1);
}

$decoded = json_decode($jsonPayload, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "Machine payload is not valid JSON array/object.\n");
    exit(1);
}

$eventList = [];
if (array_keys($decoded) === range(0, count($decoded) - 1)) {
    $eventList = $decoded;
} elseif (isset($decoded['events']) && is_array($decoded['events'])) {
    $eventList = $decoded['events'];
} elseif (isset($decoded['logs']) && is_array($decoded['logs'])) {
    $eventList = $decoded['logs'];
} elseif (isset($decoded['data']) && is_array($decoded['data'])) {
    $eventList = $decoded['data'];
} else {
    $eventList = [$decoded];
}

$machineEvents = scan_normalize_machine_events($eventList, $beginTs, $endTs);
$sqlKeys = scan_existing_sql_keys($conn, $beginTs, $endTs);

$missing = [];
foreach ($machineEvents as $event) {
    $k = scan_event_key((int)$event['id'], (int)$event['type'], (string)$event['time']);
    if (!isset($sqlKeys[$k])) {
        $missing[] = $event;
    }
}

$importedRaw = 0;
$importStats = null;
if ($importMissing && count($missing) > 0) {
    if ($forceInsert) {
        $stmtForce = $conn->prepare('INSERT INTO biometric_raw_logs (raw_data, processed) VALUES (?, 0)');
        if (!($stmtForce instanceof mysqli_stmt)) {
            fwrite(STDERR, "Failed to prepare force insert statement.\n");
            exit(1);
        }

        foreach ($missing as $event) {
            $rawPayload = [
                'finger_id' => (int)$event['id'],
                'id' => (int)$event['id'],
                'type' => (int)$event['type'],
                'time' => (string)$event['time'],
                'raw_payload' => $event['raw_payload'] ?? $event,
            ];
            $rawJson = json_encode($rawPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($rawJson) || $rawJson === '') {
                continue;
            }
            $stmtForce->bind_param('s', $rawJson);
            $stmtForce->execute();
            if ($stmtForce->affected_rows > 0) {
                $importedRaw++;
            }
        }

        $stmtForce->close();
    } else {
        $machineConfig = loadBiometricMachineConfig();
        $importedRaw = biometricInsertRawLogEntries($conn, $missing, $machineConfig);
    }

    if ($runAutoImport) {
        $importStats = run_biometric_auto_import_stats();
    }
}

echo 'range_begin=' . $begin . PHP_EOL;
echo 'range_end=' . $end . PHP_EOL;
echo 'machine_events=' . count($machineEvents) . PHP_EOL;
echo 'missing_in_sql=' . count($missing) . PHP_EOL;
echo 'import_missing=' . ($importMissing ? '1' : '0') . PHP_EOL;
echo 'force_insert=' . ($forceInsert ? '1' : '0') . PHP_EOL;
echo 'imported_raw=' . $importedRaw . PHP_EOL;
if (is_array($importStats)) {
    echo 'import_processed_logs=' . (int)($importStats['processed_logs'] ?? 0) . PHP_EOL;
    echo 'import_attendance_changed=' . (int)($importStats['attendance_changed'] ?? 0) . PHP_EOL;
    echo 'import_anomalies_found=' . (int)($importStats['anomalies_found'] ?? 0) . PHP_EOL;
    echo 'import_message=' . (string)($importStats['message'] ?? '') . PHP_EOL;
}

$preview = array_slice($missing, 0, $maxPreview);
foreach ($preview as $row) {
    echo 'missing#id=' . (int)$row['id'] . '|type=' . (int)$row['type'] . '|time=' . (string)$row['time'] . PHP_EOL;
}
