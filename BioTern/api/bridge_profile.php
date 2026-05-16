<?php
require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET required']);
    exit;
}

function bridge_profile_ensure_table(mysqli $conn): void
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
        auto_import_on_ingest TINYINT(1) NOT NULL DEFAULT 1,
        attendance_window_enabled TINYINT(1) NOT NULL DEFAULT 0,
        attendance_start_time VARCHAR(20) NOT NULL DEFAULT '08:00:00',
        attendance_end_time VARCHAR(20) NOT NULL DEFAULT '20:00:00',
        duplicate_guard_minutes INT NOT NULL DEFAULT 10,
        slot_advance_minimum_minutes INT NOT NULL DEFAULT 10,
        updated_by INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_profile_name (profile_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [
        'selected_bridge_preset' => "ALTER TABLE biometric_bridge_profile ADD COLUMN selected_bridge_preset VARCHAR(100) NOT NULL DEFAULT 'laptop_custom' AFTER profile_name",
        'router_name' => "ALTER TABLE biometric_bridge_profile ADD COLUMN router_name VARCHAR(150) NOT NULL DEFAULT '' AFTER selected_bridge_preset",
        'bridge_name' => "ALTER TABLE biometric_bridge_profile ADD COLUMN bridge_name VARCHAR(150) NOT NULL DEFAULT '' AFTER router_name",
        'auto_import_on_ingest' => "ALTER TABLE biometric_bridge_profile ADD COLUMN auto_import_on_ingest TINYINT(1) NOT NULL DEFAULT 1 AFTER output_path",
        'attendance_window_enabled' => "ALTER TABLE biometric_bridge_profile ADD COLUMN attendance_window_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_import_on_ingest",
        'attendance_start_time' => "ALTER TABLE biometric_bridge_profile ADD COLUMN attendance_start_time VARCHAR(20) NOT NULL DEFAULT '08:00:00' AFTER attendance_window_enabled",
        'attendance_end_time' => "ALTER TABLE biometric_bridge_profile ADD COLUMN attendance_end_time VARCHAR(20) NOT NULL DEFAULT '20:00:00' AFTER attendance_start_time",
        'duplicate_guard_minutes' => "ALTER TABLE biometric_bridge_profile ADD COLUMN duplicate_guard_minutes INT NOT NULL DEFAULT 10 AFTER attendance_end_time",
        'slot_advance_minimum_minutes' => "ALTER TABLE biometric_bridge_profile ADD COLUMN slot_advance_minimum_minutes INT NOT NULL DEFAULT 10 AFTER duplicate_guard_minutes",
    ];
    foreach ($columns as $column => $alterSql) {
        $res = $conn->query("SHOW COLUMNS FROM biometric_bridge_profile LIKE '" . $conn->real_escape_string($column) . "'");
        $exists = ($res instanceof mysqli_result) && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->close();
        }
        if (!$exists) {
            $conn->query($alterSql);
        }
    }
}

function bridge_profile_request_token(): string
{
    $header = trim((string)($_SERVER['HTTP_X_BRIDGE_TOKEN'] ?? ''));
    if ($header !== '') {
        return $header;
    }

    return trim((string)($_GET['bridge_token'] ?? ''));
}

function bridge_profile_token_candidates(array $row): array
{
    $candidates = [];

    $dbBridgeToken = trim((string)($row['bridge_token'] ?? ''));
    if ($dbBridgeToken !== '') {
        $candidates[] = $dbBridgeToken;
    }

    $envToken = getenv('BIOTERN_BRIDGE_TOKEN');
    if (is_string($envToken) && trim($envToken) !== '') {
        $candidates[] = trim($envToken);
    }

    // Fallback: allow using ingest token if worker was configured with that token.
    $dbIngestToken = trim((string)($row['ingest_api_token'] ?? ''));
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

bridge_profile_ensure_table($conn);

$requestedProfileName = trim((string)($_GET['profile_name'] ?? $_SERVER['HTTP_X_BRIDGE_PROFILE'] ?? ''));
$providedToken = bridge_profile_request_token();
$profile = [];
$isAuthorized = false;

if ($requestedProfileName !== '') {
    if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/', $requestedProfileName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid bridge profile name']);
        $conn->close();
        exit;
    }

    $safeProfile = $conn->real_escape_string($requestedProfileName);
    $res = $conn->query("SELECT * FROM biometric_bridge_profile WHERE profile_name = '{$safeProfile}' LIMIT 1");
    if ($res instanceof mysqli_result) {
        $profile = $res->fetch_assoc() ?: [];
        $res->close();
    }

    foreach (bridge_profile_token_candidates($profile) as $candidate) {
        if (hash_equals((string)$candidate, $providedToken)) {
            $isAuthorized = true;
            break;
        }
    }
} else {
    $res = $conn->query("SELECT * FROM biometric_bridge_profile ORDER BY profile_name = 'default' DESC, id ASC");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            foreach (bridge_profile_token_candidates($row) as $candidate) {
                if (hash_equals((string)$candidate, $providedToken)) {
                    $profile = $row;
                    $isAuthorized = true;
                    break 2;
                }
            }
        }
        $res->close();
    }
}

if ($profile === []) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Bridge profile not configured yet.']);
    $conn->close();
    exit;
}

if ($providedToken === '' || !$isAuthorized) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized bridge token']);
    $conn->close();
    exit;
}

echo json_encode([
    'success' => true,
    'profile' => [
        'profile_name' => (string)($profile['profile_name'] ?? ''),
        'bridge_enabled' => !empty($profile['bridge_enabled']),
        'selected_bridge_preset' => (string)($profile['selected_bridge_preset'] ?? ''),
        'cloud_base_url' => (string)($profile['cloud_base_url'] ?? ''),
        'ingest_path' => (string)($profile['ingest_path'] ?? '/api/f20h_ingest.php'),
        'ingest_api_token' => (string)($profile['ingest_api_token'] ?? ''),
        'poll_seconds' => (int)($profile['poll_seconds'] ?? 30),
        'ip_address' => (string)($profile['ip_address'] ?? ''),
        'gateway' => (string)($profile['gateway'] ?? ''),
        'mask' => (string)($profile['mask'] ?? '255.255.255.0'),
        'port' => (int)($profile['port'] ?? 5001),
        'device_number' => (int)($profile['device_number'] ?? 1),
        'communication_password' => (string)($profile['communication_password'] ?? '0'),
        'output_path' => (string)($profile['output_path'] ?? ''),
        'auto_import_on_ingest' => !array_key_exists('auto_import_on_ingest', $profile) || !empty($profile['auto_import_on_ingest']),
        'attendance_window_enabled' => !empty($profile['attendance_window_enabled']),
        'attendance_start_time' => (string)($profile['attendance_start_time'] ?? '08:00:00'),
        'attendance_end_time' => (string)($profile['attendance_end_time'] ?? '20:00:00'),
        'duplicate_guard_minutes' => (int)($profile['duplicate_guard_minutes'] ?? 10),
        'slot_advance_minimum_minutes' => (int)($profile['slot_advance_minimum_minutes'] ?? 10),
        'updated_at' => (string)($profile['updated_at'] ?? ''),
    ],
]);

$conn->close();
