<?php
require_once dirname(__DIR__) . '/tools/biometric_db.php';
require_once dirname(__DIR__) . '/tools/biometric_auto_import.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

function f20h_ingest_token(): string
{
    $headerToken = trim((string) ($_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_X_BIOTERN_TOKEN'] ?? ''));
    if ($headerToken !== '') {
        return $headerToken;
    }

    $bodyToken = trim((string) ($_POST['api_token'] ?? $_GET['api_token'] ?? ''));
    return $bodyToken;
}

function f20h_expected_token(array $machineConfig): string
{
    $envToken = getenv('BIOTERN_API_TOKEN');
    if ($envToken !== false && trim((string) $envToken) !== '') {
        return trim((string) $envToken);
    }

    return trim((string) ($machineConfig['ingestToken'] ?? ''));
}

function f20h_decode_payload(): array
{
    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);
    return is_array($decoded) ? $decoded : [];
}

function f20h_extract_events(array $payload): array
{
    if (isset($payload['events']) && is_array($payload['events'])) {
        return $payload['events'];
    }
    if (isset($payload['logs']) && is_array($payload['logs'])) {
        return $payload['logs'];
    }
    if (isset($payload['data']) && is_array($payload['data'])) {
        return $payload['data'];
    }

    $isList = array_keys($payload) === range(0, count($payload) - 1);
    return $isList ? $payload : [$payload];
}

function f20h_normalize_event(array $event): ?array
{
    $fingerId = isset($event['finger_id']) ? (int) $event['finger_id'] : (isset($event['id']) ? (int) $event['id'] : 0);
    $clockType = isset($event['type']) ? (int) $event['type'] : (isset($event['clock_type']) ? (int) $event['clock_type'] : 0);
    $time = trim((string) ($event['time'] ?? $event['record_time'] ?? $event['timestamp'] ?? ''));

    if ($fingerId <= 0 || $clockType <= 0 || $time === '') {
        return null;
    }

    return [
        'finger_id' => $fingerId,
        'id' => $fingerId,
        'type' => $clockType,
        'time' => $time,
        'raw_payload' => $event,
    ];
}

$machineConfig = loadBiometricMachineConfig();
$expectedToken = f20h_expected_token($machineConfig);
$providedToken = f20h_ingest_token();
if ($expectedToken !== '' && !hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized token']);
    exit;
}

$payload = f20h_decode_payload();
$events = f20h_extract_events($payload);
$normalized = [];
foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }

    $mapped = f20h_normalize_event($event);
    if ($mapped !== null) {
        $normalized[] = $mapped;
    }
}

if ($normalized === []) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No valid F20H events found in payload']);
    exit;
}

try {
    $db = biometric_shared_db();
    $inserted = biometricInsertRawLogEntries($db, $normalized, $machineConfig);
    $db->close();

    $autoImport = !isset($machineConfig['autoImportOnIngest']) || !empty($machineConfig['autoImportOnIngest']);
    $stats = null;
    if ($autoImport) {
        $stats = run_biometric_auto_import_stats();
    }

    echo json_encode([
        'success' => true,
        'mode' => 'direct_ingest',
        'received' => count($normalized),
        'inserted' => $inserted,
        'auto_import' => $autoImport,
        'stats' => $stats,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
