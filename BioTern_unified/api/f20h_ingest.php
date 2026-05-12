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
        $headerToken = trim((string)($_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_X_BIOTERN_TOKEN'] ?? $_SERVER['HTTP_X_BRIDGE_TOKEN'] ?? ''));
        if ($headerToken !== '') {
            return $headerToken;
        }

        return trim((string)($_POST['api_token'] ?? $_GET['api_token'] ?? ''));
    }
}

if (!function_exists('f20h_request_ip')) {
    function f20h_request_ip(): string
    {
        $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            $parts = explode(',', $forwarded);
            return trim((string)($parts[0] ?? ''));
        }

        return trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    }
}

if (!function_exists('f20h_request_node')) {
    function f20h_request_node(): string
    {
        $node = trim((string)($_SERVER['HTTP_X_BRIDGE_NODE'] ?? $_SERVER['HTTP_X_MACHINE_NAME'] ?? ''));
        if ($node === '') {
            $node = trim((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
        }

        return $node !== '' ? substr($node, 0, 120) : 'unknown-node';
    }
}

if (!function_exists('f20h_table_has_column')) {
    function f20h_table_has_column(mysqli $db, string $table, string $column): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }
        $safeColumn = $db->real_escape_string($column);
        $res = $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$safeColumn}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('f20h_table_has_index')) {
    function f20h_table_has_index(mysqli $db, string $table, string $index): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }
        $safeIndex = $db->real_escape_string($index);
        $res = $db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$safeIndex}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

if (!function_exists('f20h_ensure_ingest_events_table')) {
    function f20h_ensure_ingest_events_table(mysqli $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS biometric_ingest_events (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            source_ip VARCHAR(64) DEFAULT '',
            source_node VARCHAR(120) DEFAULT '',
            token_status VARCHAR(32) DEFAULT 'unknown',
            http_status INT NOT NULL DEFAULT 0,
            events_received INT NOT NULL DEFAULT 0,
            events_accepted INT NOT NULL DEFAULT 0,
            auto_import TINYINT(1) NOT NULL DEFAULT 0,
            import_message TEXT NULL,
            note VARCHAR(255) DEFAULT '',
            PRIMARY KEY (id),
            KEY idx_received_at (received_at),
            KEY idx_source_node (source_node),
            KEY idx_token_status (token_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!f20h_table_has_column($db, 'biometric_ingest_events', 'source_node')) {
            $db->query("ALTER TABLE biometric_ingest_events ADD COLUMN source_node VARCHAR(120) DEFAULT '' AFTER source_ip");
        }
        if (!f20h_table_has_index($db, 'biometric_ingest_events', 'idx_source_node')) {
            $db->query("CREATE INDEX idx_source_node ON biometric_ingest_events (source_node)");
        }
    }
}

if (!function_exists('f20h_log_ingest_event')) {
    function f20h_log_ingest_event(array $event): void
    {
        try {
            $db = new mysqli(
                defined('DB_HOST') ? DB_HOST : 'localhost',
                defined('DB_USER') ? DB_USER : 'root',
                defined('DB_PASS') ? DB_PASS : '',
                defined('DB_NAME') ? DB_NAME : 'biotern_db',
                defined('DB_PORT') ? (int)DB_PORT : 3306
            );
            if ($db->connect_error) {
                return;
            }
            $db->set_charset('utf8mb4');
            f20h_ensure_ingest_events_table($db);

            $stmt = $db->prepare('INSERT INTO biometric_ingest_events (source_ip, source_node, token_status, http_status, events_received, events_accepted, auto_import, import_message, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $sourceIp = (string)($event['source_ip'] ?? '');
                $sourceNode = (string)($event['source_node'] ?? 'unknown-node');
                $tokenStatus = (string)($event['token_status'] ?? 'unknown');
                $httpStatus = (int)($event['http_status'] ?? 0);
                $eventsReceived = (int)($event['events_received'] ?? 0);
                $eventsAccepted = (int)($event['events_accepted'] ?? 0);
                $autoImport = !empty($event['auto_import']) ? 1 : 0;
                $importMessage = (string)($event['import_message'] ?? '');
                $note = (string)($event['note'] ?? '');

                $stmt->bind_param('sssiiiiss', $sourceIp, $sourceNode, $tokenStatus, $httpStatus, $eventsReceived, $eventsAccepted, $autoImport, $importMessage, $note);
                $stmt->execute();
                $stmt->close();
            }
            $db->close();
        } catch (Throwable $ignored) {
            // Ingest should not fail only because heartbeat logging failed.
        }
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

if (!function_exists('f20h_token_candidates')) {
    function f20h_token_candidates(array $machineConfig): array
    {
        $candidates = [];

        $expectedToken = f20h_expected_token($machineConfig);
        if ($expectedToken !== '') {
            $candidates[] = $expectedToken;
        }

        $envBridgeToken = getenv('BIOTERN_BRIDGE_TOKEN');
        if (is_string($envBridgeToken) && trim($envBridgeToken) !== '') {
            $candidates[] = trim($envBridgeToken);
        }

        try {
            $db = new mysqli(
                defined('DB_HOST') ? DB_HOST : 'localhost',
                defined('DB_USER') ? DB_USER : 'root',
                defined('DB_PASS') ? DB_PASS : '',
                defined('DB_NAME') ? DB_NAME : 'biotern_db',
                defined('DB_PORT') ? (int)DB_PORT : 3306
            );
            if (!$db->connect_error) {
                $db->set_charset('utf8mb4');
                $profileRes = $db->query("SELECT bridge_token, ingest_api_token FROM biometric_bridge_profile WHERE profile_name = 'default' LIMIT 1");
                if ($profileRes instanceof mysqli_result) {
                    $profile = $profileRes->fetch_assoc() ?: [];
                    $profileRes->close();

                    $bridgeToken = trim((string)($profile['bridge_token'] ?? ''));
                    $ingestToken = trim((string)($profile['ingest_api_token'] ?? ''));
                    if ($bridgeToken !== '') {
                        $candidates[] = $bridgeToken;
                    }
                    if ($ingestToken !== '') {
                        $candidates[] = $ingestToken;
                    }
                }
                $db->close();
            }
        } catch (Throwable $ignored) {
            // Keep ingest available even if the bridge profile table is not ready yet.
        }

        return array_values(array_unique(array_filter($candidates, static function ($value): bool {
            return trim((string)$value) !== '';
        })));
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
$providedToken = f20h_ingest_token();
$tokenCandidates = f20h_token_candidates($machineConfig);
$isAuthorized = $tokenCandidates === [];
foreach ($tokenCandidates as $candidate) {
    if (hash_equals((string)$candidate, $providedToken)) {
        $isAuthorized = true;
        break;
    }
}
if (!$isAuthorized) {
    f20h_log_ingest_event([
        'source_ip' => f20h_request_ip(),
        'source_node' => f20h_request_node(),
        'token_status' => 'invalid',
        'http_status' => 401,
        'events_received' => 0,
        'events_accepted' => 0,
        'note' => 'Unauthorized token',
    ]);
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
    f20h_log_ingest_event([
        'source_ip' => f20h_request_ip(),
        'source_node' => f20h_request_node(),
        'token_status' => 'valid',
        'http_status' => 422,
        'events_received' => 0,
        'events_accepted' => 0,
        'note' => 'No valid F20H events found in payload',
    ]);
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
    f20h_log_ingest_event([
        'source_ip' => f20h_request_ip(),
        'source_node' => f20h_request_node(),
        'token_status' => 'valid',
        'http_status' => 200,
        'events_received' => count($normalized),
        'events_accepted' => $inserted,
        'auto_import' => 1,
        'import_message' => is_array($stats) ? json_encode($stats, JSON_UNESCAPED_SLASHES) : '',
        'note' => 'F20H bridge ingest accepted',
    ]);
} catch (Throwable $e) {
    f20h_log_ingest_event([
        'source_ip' => f20h_request_ip(),
        'source_node' => f20h_request_node(),
        'token_status' => 'valid',
        'http_status' => 500,
        'events_received' => count($normalized),
        'events_accepted' => $inserted,
        'note' => substr($e->getMessage(), 0, 255),
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
