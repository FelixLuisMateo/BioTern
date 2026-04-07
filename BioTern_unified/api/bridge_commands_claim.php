<?php
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

function bridge_commands_ensure_profile_table(mysqli $conn): void
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

function bridge_commands_ensure_queue_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_command_queue (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        command_name VARCHAR(80) NOT NULL,
        command_payload LONGTEXT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'queued',
        requested_by INT NOT NULL DEFAULT 0,
        source VARCHAR(80) NOT NULL DEFAULT 'machine_manager',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        claimed_at TIMESTAMP NULL DEFAULT NULL,
        claimed_by VARCHAR(120) NOT NULL DEFAULT '',
        completed_at TIMESTAMP NULL DEFAULT NULL,
        result_text TEXT NULL,
        attempts INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_status_created (status, created_at),
        KEY idx_claimed_by (claimed_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function bridge_commands_request_token(): string
{
    $header = trim((string)($_SERVER['HTTP_X_BRIDGE_TOKEN'] ?? ''));
    if ($header !== '') {
        return $header;
    }

    return trim((string)($_GET['bridge_token'] ?? ''));
}

function bridge_commands_token_candidates(array $profile): array
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

bridge_commands_ensure_profile_table($conn);
bridge_commands_ensure_queue_table($conn);

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

$providedToken = bridge_commands_request_token();
$authorized = false;
foreach (bridge_commands_token_candidates($profile) as $candidate) {
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

$bridgeNode = trim((string)($_SERVER['HTTP_X_BRIDGE_NODE'] ?? ''));
if ($bridgeNode === '') {
    $bridgeNode = 'unknown-node';
}

$conn->query("UPDATE biometric_bridge_command_queue
    SET status = 'failed', completed_at = NOW(), result_text = 'Timed out while waiting for bridge worker claim.'
    WHERE status = 'claimed' AND claimed_at IS NOT NULL AND claimed_at < (NOW() - INTERVAL 15 MINUTE)");

$commandRow = null;
for ($i = 0; $i < 3; $i++) {
    $res = $conn->query("SELECT id, command_name, command_payload, created_at, requested_by, source
        FROM biometric_bridge_command_queue
        WHERE status = 'queued'
        ORDER BY id ASC
        LIMIT 1");

    $row = ($res instanceof mysqli_result) ? ($res->fetch_assoc() ?: null) : null;
    if ($res instanceof mysqli_result) {
        $res->close();
    }

    if (!is_array($row)) {
        break;
    }

    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) {
        break;
    }

    $stmt = $conn->prepare("UPDATE biometric_bridge_command_queue
        SET status = 'claimed', claimed_at = NOW(), claimed_by = ?, attempts = attempts + 1
        WHERE id = ? AND status = 'queued'");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare command claim update.']);
        $conn->close();
        exit;
    }
    $stmt->bind_param('si', $bridgeNode, $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        $commandRow = $row;
        break;
    }
}

echo json_encode([
    'success' => true,
    'command' => is_array($commandRow) ? [
        'id' => (int)($commandRow['id'] ?? 0),
        'command_name' => (string)($commandRow['command_name'] ?? ''),
        'command_payload' => (string)($commandRow['command_payload'] ?? ''),
        'created_at' => (string)($commandRow['created_at'] ?? ''),
        'requested_by' => (int)($commandRow['requested_by'] ?? 0),
        'source' => (string)($commandRow['source'] ?? ''),
    ] : null,
]);

$conn->close();
