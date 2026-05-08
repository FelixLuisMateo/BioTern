<?php
require_once dirname(__DIR__) . '/tools/biometric_db.php';
require_once dirname(__DIR__) . '/tools/biometric_auto_import.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

function f20h_request_ip(): string
{
    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded !== '') {
        $parts = explode(',', $forwarded);
        return trim((string)($parts[0] ?? ''));
    }

    return trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
}

function f20h_request_node(): string
{
    $node = trim((string)($_SERVER['HTTP_X_BRIDGE_NODE'] ?? $_SERVER['HTTP_X_MACHINE_NAME'] ?? ''));
    if ($node === '') {
        $node = trim((string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
    }

    if ($node === '') {
        return 'unknown-node';
    }

    return substr($node, 0, 120);
}

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

    $db->query("ALTER TABLE biometric_ingest_events ADD COLUMN IF NOT EXISTS source_node VARCHAR(120) DEFAULT '' AFTER source_ip");
    $db->query("CREATE INDEX IF NOT EXISTS idx_source_node ON biometric_ingest_events (source_node)");
}

function f20h_log_ingest_event(array $event): void
{
    try {
        $db = biometric_shared_db();
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
        // Keep ingest endpoint resilient even if diagnostic logging fails.
    }
}

function f20h_ingest_token(): string
{
    $headerToken = trim((string) ($_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_X_BIOTERN_TOKEN'] ?? $_SERVER['HTTP_X_BRIDGE_TOKEN'] ?? ''));
    if ($headerToken !== '') {
        return $headerToken;
    }

    $bodyToken = trim((string) ($_POST['api_token'] ?? $_GET['api_token'] ?? ''));
    return $bodyToken;
}

function f20h_token_candidates(array $machineConfig): array
{
    $candidates = [];

    $envToken = getenv('BIOTERN_API_TOKEN');
    if (is_string($envToken) && trim((string)$envToken) !== '') {
        $candidates[] = trim((string)$envToken);
    }

    $machineToken = trim((string)($machineConfig['ingestToken'] ?? ''));
    if ($machineToken !== '') {
        $candidates[] = $machineToken;
    }

    try {
        $db = biometric_shared_db();
        $db->query("CREATE TABLE IF NOT EXISTS biometric_bridge_profile (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            profile_name VARCHAR(100) NOT NULL DEFAULT 'default',
            bridge_enabled TINYINT(1) NOT NULL DEFAULT 1,
            bridge_token VARCHAR(255) NOT NULL DEFAULT '',
            cloud_base_url VARCHAR(255) NOT NULL DEFAULT '',
            ingest_path VARCHAR(255) NOT NULL DEFAULT '/api/f20h_ingest.php',
            ingest_api_token VARCHAR(255) NOT NULL DEFAULT '',
            poll_seconds INT NOT NULL DEFAULT 30,
            ip_address VARCHAR(100) NOT NULL DEFAULT '',
            gateway VARCHAR(100) NOT NULL DEFAULT '',
            mask VARCHAR(100) NOT NULL DEFAULT '255.255.255.0',
            port INT NOT NULL DEFAULT 5001,
            device_number INT NOT NULL DEFAULT 1,
            communication_password VARCHAR(255) NOT NULL DEFAULT '0',
            output_path VARCHAR(255) NOT NULL DEFAULT '',
            updated_by INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_profile_name (profile_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $profileRes = $db->query("SELECT bridge_token, ingest_api_token FROM biometric_bridge_profile WHERE profile_name = 'default' LIMIT 1");
        if ($profileRes instanceof mysqli_result) {
            $profileRow = $profileRes->fetch_assoc() ?: [];
            $profileRes->close();

            $dbBridge = trim((string)($profileRow['bridge_token'] ?? ''));
            $dbIngest = trim((string)($profileRow['ingest_api_token'] ?? ''));
            if ($dbBridge !== '') {
                $candidates[] = $dbBridge;
            }
            if ($dbIngest !== '') {
                $candidates[] = $dbIngest;
            }
        }

        $db->close();
    } catch (Throwable $ignored) {
        // Keep ingest resilient even when profile lookup is unavailable.
    }

    return array_values(array_unique(array_filter($candidates, static function ($value): bool {
        return trim((string)$value) !== '';
    })));
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

function f20h_auto_import_enabled(array $machineConfig): bool
{
    $env = getenv('BIOTERN_AUTO_IMPORT_ON_INGEST');
    if (is_string($env) && trim($env) !== '') {
        $normalized = strtolower(trim($env));
        if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
            return false;
        }
        if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
            return true;
        }
    }

    $isCloudRuntime = (
        getenv('VERCEL') !== false
        || getenv('RAILWAY_ENVIRONMENT') !== false
        || getenv('K_SERVICE') !== false
    );
    if ($isCloudRuntime) {
        return true;
    }

    return !isset($machineConfig['autoImportOnIngest']) || !empty($machineConfig['autoImportOnIngest']);
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
$providedToken = f20h_ingest_token();
$tokenCandidates = f20h_token_candidates($machineConfig);
$tokenRequired = !empty($tokenCandidates);
$authorized = !$tokenRequired;
if ($tokenRequired) {
    foreach ($tokenCandidates as $candidate) {
        if (hash_equals((string)$candidate, $providedToken)) {
            $authorized = true;
            break;
        }
    }
}

if (!$authorized && $providedToken !== '') {
    try {
        $db = biometric_shared_db();
        $sourceNode = f20h_request_node();
        $stmt = $db->prepare("SELECT ingest_token_hash FROM biometric_machines WHERE bridge_node = ? AND deleted_at IS NULL AND status <> 'retired' LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $sourceNode);
            $stmt->execute();
            $machine = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $hash = trim((string)($machine['ingest_token_hash'] ?? ''));
            if ($hash !== '' && password_verify($providedToken, $hash)) {
                $authorized = true;
            }
        }
        $db->close();
    } catch (Throwable $ignored) {
        // Keep the endpoint resilient; invalid tokens still fail below.
    }
}

if (!$authorized) {
    f20h_log_ingest_event([
        'source_ip' => f20h_request_ip(),
        'source_node' => f20h_request_node(),
        'token_status' => 'invalid',
        'http_status' => 401,
        'events_received' => 0,
        'events_accepted' => 0,
        'auto_import' => 0,
        'import_message' => '',
        'note' => 'Unauthorized token',
    ]);
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
    f20h_log_ingest_event([
        'source_ip' => f20h_request_ip(),
        'source_node' => f20h_request_node(),
        'token_status' => ($tokenRequired ? 'valid' : 'disabled'),
        'http_status' => 422,
        'events_received' => count($events),
        'events_accepted' => 0,
        'auto_import' => 0,
        'import_message' => '',
        'note' => 'No valid F20H events found in payload',
    ]);
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No valid F20H events found in payload']);
    exit;
}

try {
    $db = biometric_shared_db();
    $inserted = biometricInsertRawLogEntries($db, $normalized, $machineConfig);
    $sourceNode = f20h_request_node();
    if ($sourceNode !== '') {
        $stmtSeen = $db->prepare("UPDATE biometric_machines SET last_seen_at = NOW(), last_sync_at = NOW() WHERE bridge_node = ? AND deleted_at IS NULL");
        if ($stmtSeen) {
            $stmtSeen->bind_param('s', $sourceNode);
            $stmtSeen->execute();
            $stmtSeen->close();
        }
    }
    $db->close();

    $autoImport = f20h_auto_import_enabled($machineConfig);
    $stats = null;
    if ($autoImport) {
        $stats = run_biometric_auto_import_stats();
    }

    f20h_log_ingest_event([
        'source_ip' => f20h_request_ip(),
        'source_node' => f20h_request_node(),
        'token_status' => ($tokenRequired ? 'valid' : 'disabled'),
        'http_status' => 200,
        'events_received' => count($events),
        'events_accepted' => $inserted,
        'auto_import' => $autoImport ? 1 : 0,
        'import_message' => is_array($stats) ? (string)($stats['message'] ?? '') : '',
        'note' => 'Ingest accepted',
    ]);

    echo json_encode([
        'success' => true,
        'mode' => 'direct_ingest',
        'received' => count($normalized),
        'inserted' => $inserted,
        'auto_import' => $autoImport,
        'stats' => $stats,
    ]);
} catch (Throwable $e) {
    f20h_log_ingest_event([
        'source_ip' => f20h_request_ip(),
        'source_node' => f20h_request_node(),
        'token_status' => ($tokenRequired ? 'valid' : 'disabled'),
        'http_status' => 500,
        'events_received' => count($events),
        'events_accepted' => 0,
        'auto_import' => 0,
        'import_message' => '',
        'note' => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
