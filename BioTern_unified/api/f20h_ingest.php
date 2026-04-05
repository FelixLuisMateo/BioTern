<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/tools/biometric_auto_import.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

if (!function_exists('f20h_ingest_token')) {
    function f20h_ingest_token(): string
    {
        $headerToken = trim((string)($_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_X_BIOTERN_TOKEN'] ?? ''));
        if ($headerToken !== '') {
            return $headerToken;
        }

        return trim((string)($_POST['api_token'] ?? $_GET['api_token'] ?? ''));
    }
}

if (!function_exists('f20h_expected_token')) {
    function f20h_expected_token(array $machineConfig): string
    {
        $envToken = getenv('BIOTERN_API_TOKEN');
        if (is_string($envToken) && trim($envToken) !== '') {
            return trim($envToken);
        }

        return trim((string)($machineConfig['ingestToken'] ?? ''));
    }
}

if (!function_exists('f20h_read_payload')) {
    function f20h_read_payload(): array
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (isset($_POST['events'])) {
            $eventsRaw = (string)$_POST['events'];
            $decoded = json_decode($eventsRaw, true);
            if (is_array($decoded)) {
                return ['events' => $decoded];
            }
        }

        return [];
    }
}

if (!function_exists('f20h_extract_events')) {
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
}

if (!function_exists('f20h_normalize_datetime')) {
    function f20h_normalize_datetime($value): string
    {
        if (is_numeric($value)) {
            $ts = (int)$value;
            if ($ts > 0) {
                return date('Y-m-d H:i:s', $ts);
            }
        }

        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }

        $ts = strtotime($text);
        if ($ts === false) {
            return '';
        }

        return date('Y-m-d H:i:s', $ts);
    }
}

if (!function_exists('f20h_normalize_event')) {
    function f20h_normalize_event(array $event): ?array
    {
        $fingerId = isset($event['finger_id']) ? (int)$event['finger_id'] : (isset($event['id']) ? (int)$event['id'] : 0);
        $clockType = isset($event['type']) ? (int)$event['type'] : (isset($event['clock_type']) ? (int)$event['clock_type'] : 0);
        $time = f20h_normalize_datetime($event['time'] ?? $event['record_time'] ?? $event['timestamp'] ?? '');

        if ($fingerId <= 0 || $clockType <= 0 || $time === '') {
            return null;
        }

        return [
            'finger_id' => $fingerId,
            'id' => $fingerId,
            'type' => $clockType,
            'time' => $time,
        ];
    }
}

$machineConfig = loadBiometricMachineConfig();
$expectedToken = f20h_expected_token($machineConfig);
$providedToken = f20h_ingest_token();
if ($expectedToken !== '' && !hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized token']);
    exit;
}

$payload = f20h_read_payload();
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

$conn = new mysqli(
    defined('DB_HOST') ? DB_HOST : 'localhost',
    defined('DB_USER') ? DB_USER : 'root',
    defined('DB_PASS') ? DB_PASS : '',
    defined('DB_NAME') ? DB_NAME : 'biotern_db',
    defined('DB_PORT') ? (int)DB_PORT : 3306
);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}
$conn->set_charset('utf8mb4');

$inserted = 0;
$incomingSeen = [];
$duplicateWindowMinutes = biometricMachineConfigInt($machineConfig, 'duplicateGuardMinutes', 10);

try {
    foreach ($normalized as $entry) {
        $fingerId = (int)$entry['finger_id'];
        $datetime = (string)$entry['time'];

        if (isDuplicateRawBiometricEvent($conn, $incomingSeen, $fingerId, $datetime, $duplicateWindowMinutes)) {
            continue;
        }

        $raw = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($raw) || $raw === '') {
            continue;
        }

        $stmt = $conn->prepare('SELECT id FROM biometric_raw_logs WHERE raw_data = ?');
        if ($stmt === false) {
            throw new RuntimeException('Database error: failed to prepare raw log lookup. Error: ' . $conn->error);
        }
        $stmt->bind_param('s', $raw);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $stmt->close();
            $ins = $conn->prepare('INSERT INTO biometric_raw_logs (raw_data, processed) VALUES (?, 0)');
            if ($ins === false) {
                throw new RuntimeException('Database error: failed to prepare raw log insert. Error: ' . $conn->error);
            }
            $ins->bind_param('s', $raw);
            $ins->execute();
            $ins->close();
            $inserted++;
            rememberAcceptedRawBiometricEvent($incomingSeen, $fingerId, $datetime);
        } else {
            $stmt->close();
        }
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'biotern_ingest_');
    if ($tmpPath === false) {
        throw new RuntimeException('Failed to allocate temporary ingest file.');
    }
    file_put_contents($tmpPath, '[]');
    $stats = run_biometric_auto_import_stats($tmpPath);
    @unlink($tmpPath);

    echo json_encode([
        'success' => true,
        'mode' => 'direct_ingest',
        'received' => count($normalized),
        'inserted' => $inserted,
        'stats' => $stats,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
