<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET required']);
    exit;
}

$node = trim((string)($_SERVER['HTTP_X_BRIDGE_NODE'] ?? ''));
$providedToken = trim((string)($_SERVER['HTTP_X_BRIDGE_TOKEN'] ?? $_GET['bridge_token'] ?? ''));

$profile = [];
$profileRes = $conn->query("SELECT bridge_token, ingest_api_token, bridge_enabled, cloud_base_url, ingest_path, updated_at FROM biometric_bridge_profile WHERE profile_name = 'default' LIMIT 1");
if ($profileRes instanceof mysqli_result) {
    $profile = $profileRes->fetch_assoc() ?: [];
    $profileRes->close();
}

$candidates = [];
foreach ([(string)($profile['bridge_token'] ?? ''), (string)($profile['ingest_api_token'] ?? ''), (string)(getenv('BIOTERN_BRIDGE_TOKEN') ?: ''), (string)(getenv('BIOTERN_API_TOKEN') ?: '')] as $value) {
    $value = trim($value);
    if ($value !== '') {
        $candidates[] = $value;
    }
}
$candidates = array_values(array_unique($candidates));

$authorized = false;
if (!empty($candidates) && $providedToken !== '') {
    foreach ($candidates as $candidate) {
        if (hash_equals($candidate, $providedToken)) {
            $authorized = true;
            break;
        }
    }
}

if (!$authorized) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized bridge token',
        'token_required' => !empty($candidates),
    ]);
    exit;
}

$queueStats = ['queued' => 0, 'claimed' => 0, 'failed' => 0, 'succeeded' => 0];
$queueRes = $conn->query("SELECT status, COUNT(*) AS total FROM biometric_bridge_command_queue GROUP BY status");
if ($queueRes instanceof mysqli_result) {
    while ($row = $queueRes->fetch_assoc()) {
        $status = strtolower(trim((string)($row['status'] ?? '')));
        $total = (int)($row['total'] ?? 0);
        if (isset($queueStats[$status])) {
            $queueStats[$status] = $total;
        }
    }
    $queueRes->close();
}

$lastIngest = null;
$ingestRes = $conn->query("SELECT received_at, source_node, token_status, http_status, events_received, events_accepted, note FROM biometric_ingest_events ORDER BY id DESC LIMIT 1");
if ($ingestRes instanceof mysqli_result) {
    $lastIngest = $ingestRes->fetch_assoc() ?: null;
    $ingestRes->close();
}

echo json_encode([
    'success' => true,
    'node' => $node,
    'bridge_enabled' => !empty($profile['bridge_enabled']),
    'cloud_base_url' => (string)($profile['cloud_base_url'] ?? ''),
    'ingest_path' => (string)($profile['ingest_path'] ?? '/api/f20h_ingest.php'),
    'profile_updated_at' => (string)($profile['updated_at'] ?? ''),
    'queue' => $queueStats,
    'last_ingest' => $lastIngest,
]);
