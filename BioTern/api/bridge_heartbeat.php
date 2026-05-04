<?php
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

function bridge_heartbeat_ensure_profile_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_profile (
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
}

function bridge_heartbeat_ensure_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_heartbeat (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        node_name VARCHAR(120) NOT NULL DEFAULT '',
        status_text VARCHAR(255) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_node_name (node_name),
        KEY idx_updated_at (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function bridge_heartbeat_request_token(): string
{
    $header = trim((string)($_SERVER['HTTP_X_BRIDGE_TOKEN'] ?? ''));
    if ($header !== '') {
        return $header;
    }

    return trim((string)($_GET['bridge_token'] ?? ''));
}

function bridge_heartbeat_token_candidates(array $profile): array
{
    $candidates = [];

    $dbBridgeToken = trim((string)($profile['bridge_token'] ?? ''));
    if ($dbBridgeToken !== '') {
        $candidates[] = $dbBridgeToken;
    }

    $envBridgeToken = getenv('BIOTERN_BRIDGE_TOKEN');
    if (is_string($envBridgeToken) && trim($envBridgeToken) !== '') {
        $candidates[] = trim($envBridgeToken);
    }

    $dbIngestToken = trim((string)($profile['ingest_api_token'] ?? ''));
    if ($dbIngestToken !== '') {
        $candidates[] = $dbIngestToken;
    }

    $envIngestToken = getenv('BIOTERN_API_TOKEN');
    if (is_string($envIngestToken) && trim($envIngestToken) !== '') {
        $candidates[] = trim($envIngestToken);
    }

    return array_values(array_unique(array_filter($candidates, static function ($value): bool {
        return trim((string)$value) !== '';
    })));
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

bridge_heartbeat_ensure_profile_table($conn);
bridge_heartbeat_ensure_table($conn);

$profileRes = $conn->query("SELECT * FROM biometric_bridge_profile WHERE profile_name = 'default' LIMIT 1");
$profile = [];
if ($profileRes instanceof mysqli_result) {
    $profile = $profileRes->fetch_assoc() ?: [];
    $profileRes->close();
}
if ($profile === []) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Bridge profile not configured yet.']);
    $conn->close();
    exit;
}

$providedToken = bridge_heartbeat_request_token();
$authorized = false;
foreach (bridge_heartbeat_token_candidates($profile) as $candidate) {
    if (hash_equals((string)$candidate, $providedToken)) {
        $authorized = true;
        break;
    }
}
if ($providedToken === '' || !$authorized) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized bridge token']);
    $conn->close();
    exit;
}

$rawBody = file_get_contents('php://input');
$decoded = is_string($rawBody) ? json_decode($rawBody, true) : null;
$nodeName = trim((string)($_SERVER['HTTP_X_BRIDGE_NODE'] ?? ''));
$statusText = 'running';
if (is_array($decoded)) {
    if ($nodeName === '') {
        $nodeName = trim((string)($decoded['node_name'] ?? ''));
    }
    $statusText = trim((string)($decoded['status_text'] ?? $decoded['status'] ?? 'running'));
}
if ($nodeName === '') {
    $nodeName = 'unknown-node';
}
if ($statusText === '') {
    $statusText = 'running';
}
if (strlen($statusText) > 255) {
    $statusText = substr($statusText, 0, 255);
}

$stmt = $conn->prepare('INSERT INTO biometric_bridge_heartbeat (node_name, status_text, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE status_text = VALUES(status_text), updated_at = NOW()');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to prepare heartbeat update.']);
    $conn->close();
    exit;
}
$stmt->bind_param('ss', $nodeName, $statusText);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'node_name' => $nodeName,
    'status_text' => $statusText,
    'last_seen_at' => date('Y-m-d H:i:s'),
]);

$conn->close();