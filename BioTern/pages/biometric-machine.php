<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/tools/biometric_machine_runtime.php';
require_once dirname(__DIR__) . '/tools/biometric_auto_import.php';
require_once dirname(__DIR__) . '/tools/biometric_ops.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}
$isAdmin = ($role === 'admin');

function machine_redirect_after_post(array $params = []): void
{
    $target = 'biometric-machine.php';
    if ($params !== []) {
        $target .= '?' . http_build_query($params);
    }
    header('Location: ' . $target);
    exit;
}

$flashType = 'info';
$flashMessage = '';
$userListRaw = '';
$userDetailsRaw = '';
$userListDecoded = null;
$userDetailsDecoded = null;
$selectedUserId = (int)($_GET['selected_user_id'] ?? $_POST['user_id'] ?? 0);
$deviceInfoRaw = '';
$configRaw = '';
$ringSetRaw = '';
$networkRaw = '';
$timeRaw = '';
$machineConfigPath = dirname(__DIR__) . '/tools/biometric_machine_config.json';
$machineConfigJson = file_exists($machineConfigPath) ? trim((string)file_get_contents($machineConfigPath)) : '';

function machine_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function machine_connector_write_config(string $machineConfigPath, array $config): void
{
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('Failed to encode connector config.');
    }

    machine_write_local_config_file($machineConfigPath, $json . PHP_EOL);
}

function machine_is_cloud_runtime(): bool
{
    return getenv('VERCEL') !== false
        || getenv('RAILWAY_ENVIRONMENT') !== false
        || getenv('RAILWAY_STATIC_URL') !== false
        || getenv('K_SERVICE') !== false;
}

function machine_write_local_config_file(string $path, string $contents): void
{
    if (machine_is_cloud_runtime()) {
        throw new RuntimeException('Connector profile changes are disabled on cloud runtime because local files are read-only. Update connector settings on the always-on teacher PC bridge worker.');
    }

    $dir = dirname($path);
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('Config directory is not writable: ' . $dir);
    }

    $written = @file_put_contents($path, $contents);
    if ($written === false) {
        throw new RuntimeException('Failed to write connector config file.');
    }
}

function machine_make_bridge_token(): string
{
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return bin2hex(pack('N2', time(), mt_rand(100000, 999999)));
    }
}

function machine_bridge_default_cloud_base_url(): string
{
    $appUrl = getenv('APP_URL');
    if (is_string($appUrl) && trim($appUrl) !== '') {
        $normalized = rtrim(trim($appUrl), '/');
        if (stripos($normalized, 'http://') === 0) {
            $normalized = 'https://' . substr($normalized, 7);
        }
        return $normalized;
    }

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        return 'https://' . $host;
    }

    return '';
}

function machine_probe_bridge_profile_endpoint(string $baseUrl, string $bridgeToken): array
{
    $base = rtrim(trim($baseUrl), '/');
    if ($base === '' || $bridgeToken === '') {
        return ['ok' => false, 'status' => 0, 'url' => '', 'message' => 'Cloud base URL and bridge token are required.'];
    }

    $url = $base . '/bridge_profile.php?bridge_token=' . rawurlencode($bridgeToken);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $status = 0;
    $responseHeaders = [];
    if (function_exists('http_get_last_response_headers')) {
        $headers = http_get_last_response_headers();
        if (is_array($headers)) {
            $responseHeaders = $headers;
        }
    }

    if ($responseHeaders !== []) {
        foreach ($responseHeaders as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string)$line, $m)) {
                $status = (int)$m[1];
                break;
            }
        }
    }

    $decoded = is_string($body) ? json_decode($body, true) : null;
    if ($status === 200 && is_array($decoded) && !empty($decoded['success'])) {
        return ['ok' => true, 'status' => $status, 'url' => $url, 'message' => 'Bridge profile endpoint is reachable and token is valid.'];
    }

    $apiMessage = is_array($decoded) ? trim((string)($decoded['message'] ?? '')) : '';
    if ($apiMessage === '') {
        $apiMessage = 'Endpoint check failed with HTTP ' . $status . '.';
    }

    return ['ok' => false, 'status' => $status, 'url' => $url, 'message' => $apiMessage];
}

function machine_ensure_ingest_events_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS biometric_ingest_events (
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

    $conn->query("ALTER TABLE biometric_ingest_events ADD COLUMN IF NOT EXISTS source_node VARCHAR(120) DEFAULT '' AFTER source_ip");
    $conn->query("CREATE INDEX IF NOT EXISTS idx_source_node ON biometric_ingest_events (source_node)");
}

function machine_fetch_ingest_health(mysqli $conn): array
{
    machine_ensure_ingest_events_table($conn);

    $summary = [
        'last_received_at' => '',
        'last_source_ip' => '',
        'last_source_node' => 'unknown-node',
        'last_token_status' => 'unknown',
        'last_http_status' => 0,
        'total_today' => 0,
        'accepted_today' => 0,
    ];
    $recent = [];

    $summaryRes = $conn->query("SELECT
        MAX(received_at) AS last_received_at,
        SUBSTRING_INDEX(GROUP_CONCAT(source_ip ORDER BY id DESC SEPARATOR ','), ',', 1) AS last_source_ip,
        SUBSTRING_INDEX(GROUP_CONCAT(source_node ORDER BY id DESC SEPARATOR ','), ',', 1) AS last_source_node,
        SUBSTRING_INDEX(GROUP_CONCAT(token_status ORDER BY id DESC SEPARATOR ','), ',', 1) AS last_token_status,
        SUBSTRING_INDEX(GROUP_CONCAT(http_status ORDER BY id DESC SEPARATOR ','), ',', 1) AS last_http_status,
        SUM(CASE WHEN DATE(received_at) = CURRENT_DATE THEN 1 ELSE 0 END) AS total_today,
        SUM(CASE WHEN DATE(received_at) = CURRENT_DATE THEN events_accepted ELSE 0 END) AS accepted_today
    FROM biometric_ingest_events");
    if ($summaryRes instanceof mysqli_result) {
        $row = $summaryRes->fetch_assoc() ?: [];
        $summary['last_received_at'] = (string)($row['last_received_at'] ?? '');
        $summary['last_source_ip'] = (string)($row['last_source_ip'] ?? '');
        $summary['last_source_node'] = (string)($row['last_source_node'] ?? 'unknown-node');
        $summary['last_token_status'] = (string)($row['last_token_status'] ?? 'unknown');
        $summary['last_http_status'] = (int)($row['last_http_status'] ?? 0);
        $summary['total_today'] = (int)($row['total_today'] ?? 0);
        $summary['accepted_today'] = (int)($row['accepted_today'] ?? 0);
        $summaryRes->close();
    }

    $recentRes = $conn->query("SELECT received_at, source_ip, source_node, token_status, http_status, events_received, events_accepted, note
        FROM biometric_ingest_events
        ORDER BY id DESC
        LIMIT 8");
    if ($recentRes instanceof mysqli_result) {
        while ($row = $recentRes->fetch_assoc()) {
            $recent[] = $row;
        }
        $recentRes->close();
    }

    return ['summary' => $summary, 'recent' => $recent];
}

function machine_ensure_bridge_profile_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_profile (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        profile_name VARCHAR(100) NOT NULL DEFAULT 'default',
        selected_bridge_preset VARCHAR(100) NOT NULL DEFAULT 'laptop_custom',
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

    $hasPresetColumn = false;
    $colRes = $conn->query("SHOW COLUMNS FROM biometric_bridge_profile LIKE 'selected_bridge_preset'");
    if ($colRes instanceof mysqli_result) {
        $hasPresetColumn = $colRes->num_rows > 0;
        $colRes->close();
    }

    if (!$hasPresetColumn) {
        $conn->query("ALTER TABLE biometric_bridge_profile ADD COLUMN selected_bridge_preset VARCHAR(100) NOT NULL DEFAULT 'laptop_custom' AFTER profile_name");
    }
}

function machine_fetch_bridge_profile(mysqli $conn): array
{
    machine_ensure_bridge_profile_table($conn);

    $defaults = [
        'profile_name' => 'default',
        'selected_bridge_preset' => 'laptop_custom',
        'bridge_enabled' => 1,
        'bridge_token' => '',
        'cloud_base_url' => '',
        'ingest_path' => '/api/f20h_ingest.php',
        'ingest_api_token' => '',
        'poll_seconds' => 30,
        'ip_address' => '',
        'gateway' => '',
        'mask' => '255.255.255.0',
        'port' => 5001,
        'device_number' => 1,
        'communication_password' => '0',
        'output_path' => '',
        'updated_at' => '',
    ];

    $res = $conn->query("SELECT * FROM biometric_bridge_profile WHERE profile_name = 'default' LIMIT 1");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc() ?: [];
        $res->close();
        if ($row !== []) {
            return array_merge($defaults, $row);
        }
    }

    return $defaults;
}

function machine_save_bridge_profile(mysqli $conn, array $profile, int $updatedBy): void
{
    machine_ensure_bridge_profile_table($conn);

    $hasPresetColumn = false;
    $colRes = $conn->query("SHOW COLUMNS FROM biometric_bridge_profile LIKE 'selected_bridge_preset'");
    if ($colRes instanceof mysqli_result) {
        $hasPresetColumn = $colRes->num_rows > 0;
        $colRes->close();
    }

    $selectedBridgePreset = trim((string)($profile['selected_bridge_preset'] ?? 'laptop_custom'));
    $bridgeEnabled = !empty($profile['bridge_enabled']) ? 1 : 0;
    $bridgeToken = (string)($profile['bridge_token'] ?? '');
    $cloudBaseUrl = (string)($profile['cloud_base_url'] ?? '');
    $ingestPath = (string)($profile['ingest_path'] ?? '/api/f20h_ingest.php');
    $ingestApiToken = (string)($profile['ingest_api_token'] ?? '');
    $pollSeconds = max(10, (int)($profile['poll_seconds'] ?? 30));
    $ipAddress = (string)($profile['ip_address'] ?? '');
    $gateway = (string)($profile['gateway'] ?? '');
    $mask = (string)($profile['mask'] ?? '255.255.255.0');
    $port = max(1, (int)($profile['port'] ?? 5001));
    $deviceNumber = max(1, (int)($profile['device_number'] ?? 1));
    $communicationPassword = (string)($profile['communication_password'] ?? '0');
    $outputPath = (string)($profile['output_path'] ?? '');

    $stmt = null;
    $lastPrepareError = '';

    if ($hasPresetColumn) {
        $stmt = $conn->prepare("INSERT INTO biometric_bridge_profile
            (profile_name, selected_bridge_preset, bridge_enabled, bridge_token, cloud_base_url, ingest_path, ingest_api_token, poll_seconds, ip_address, gateway, mask, port, device_number, communication_password, output_path, updated_by)
            VALUES ('default', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                selected_bridge_preset = VALUES(selected_bridge_preset),
                bridge_enabled = VALUES(bridge_enabled),
                bridge_token = VALUES(bridge_token),
                cloud_base_url = VALUES(cloud_base_url),
                ingest_path = VALUES(ingest_path),
                ingest_api_token = VALUES(ingest_api_token),
                poll_seconds = VALUES(poll_seconds),
                ip_address = VALUES(ip_address),
                gateway = VALUES(gateway),
                mask = VALUES(mask),
                port = VALUES(port),
                device_number = VALUES(device_number),
                communication_password = VALUES(communication_password),
                output_path = VALUES(output_path),
                updated_by = VALUES(updated_by)");

        if ($stmt) {
            $stmt->bind_param(
                'sissssisssiissi',
                $selectedBridgePreset,
                $bridgeEnabled,
                $bridgeToken,
                $cloudBaseUrl,
                $ingestPath,
                $ingestApiToken,
                $pollSeconds,
                $ipAddress,
                $gateway,
                $mask,
                $port,
                $deviceNumber,
                $communicationPassword,
                $outputPath,
                $updatedBy
            );
        } else {
            $lastPrepareError = (string)$conn->error;
        }
    }

    if (!$stmt) {
        $stmt = $conn->prepare("INSERT INTO biometric_bridge_profile
            (profile_name, bridge_enabled, bridge_token, cloud_base_url, ingest_path, ingest_api_token, poll_seconds, ip_address, gateway, mask, port, device_number, communication_password, output_path, updated_by)
            VALUES ('default', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                bridge_enabled = VALUES(bridge_enabled),
                bridge_token = VALUES(bridge_token),
                cloud_base_url = VALUES(cloud_base_url),
                ingest_path = VALUES(ingest_path),
                ingest_api_token = VALUES(ingest_api_token),
                poll_seconds = VALUES(poll_seconds),
                ip_address = VALUES(ip_address),
                gateway = VALUES(gateway),
                mask = VALUES(mask),
                port = VALUES(port),
                device_number = VALUES(device_number),
                communication_password = VALUES(communication_password),
                output_path = VALUES(output_path),
                updated_by = VALUES(updated_by)");

        if ($stmt) {
            $stmt->bind_param(
                'issssisssiissi',
                $bridgeEnabled,
                $bridgeToken,
                $cloudBaseUrl,
                $ingestPath,
                $ingestApiToken,
                $pollSeconds,
                $ipAddress,
                $gateway,
                $mask,
                $port,
                $deviceNumber,
                $communicationPassword,
                $outputPath,
                $updatedBy
            );
        } else {
            $lastPrepareError = trim($lastPrepareError . ' | ' . (string)$conn->error, ' |');
            throw new RuntimeException('Failed to prepare bridge profile save query. DB error: ' . $lastPrepareError);
        }
    }

    if (!$stmt->execute()) {
        $executeError = (string)$stmt->error;
        $stmt->close();
        throw new RuntimeException('Failed to execute bridge profile save query. DB error: ' . $executeError);
    }
    $stmt->close();
}

function machine_ensure_bridge_user_cache_table(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_user_cache (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        source_node VARCHAR(120) NOT NULL DEFAULT '',
        users_json LONGTEXT NOT NULL,
        users_count INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_created_at (created_at),
        KEY idx_source_node (source_node)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function machine_load_user_list_from_bridge_cache(mysqli $conn, &$userListRaw, &$userListDecoded): array
{
    machine_ensure_bridge_user_cache_table($conn);

    $res = $conn->query("SELECT source_node, users_json, users_count, created_at FROM biometric_bridge_user_cache ORDER BY id DESC LIMIT 1");
    $row = ($res instanceof mysqli_result) ? ($res->fetch_assoc() ?: []) : [];
    if ($res instanceof mysqli_result) {
        $res->close();
    }

    if ($row === []) {
        throw new RuntimeException('No bridge user cache available yet. Start the bridge worker first, then click Read All Users again.');
    }

    $json = trim((string)($row['users_json'] ?? ''));
    if ($json === '') {
        throw new RuntimeException('Bridge user cache is empty.');
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Bridge user cache payload is invalid JSON.');
    }

    // Normalize to the same shape expected by machine_extract_rows().
    if (array_keys($decoded) !== range(0, count($decoded) - 1) && isset($decoded['users']) && is_array($decoded['users'])) {
        $decoded = $decoded['users'];
        $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $userListDecoded = $decoded;
    $userListRaw = is_string($json) ? $json : json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return [
        'source_node' => (string)($row['source_node'] ?? ''),
        'users_count' => (int)($row['users_count'] ?? count(machine_extract_rows($userListDecoded))),
        'created_at' => (string)($row['created_at'] ?? ''),
    ];
}

function machine_render_pairs(array $data): string
{
    global $isAdmin;
    $html = '';
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (!$isAdmin && in_array(strtolower((string)$key), ['cardno', 'card_no', 'card'], true)) {
            $value = machine_mask_card_number((string)$value);
        }
        $html .= '<div class="col-md-6 col-xl-4"><div class="border rounded p-3 h-100">';
        $html .= '<div class="text-muted fs-12 mb-1">' . machine_h($key) . '</div>';
        $html .= '<div class="fw-semibold text-break">' . machine_h((string)$value) . '</div>';
        $html .= '</div></div>';
    }
    return $html;
}

function machine_extract_rows($decoded): array
{
    if (!is_array($decoded)) {
        return [];
    }

    $isList = array_keys($decoded) === range(0, count($decoded) - 1);
    if ($isList) {
        return array_values(array_filter($decoded, 'is_array'));
    }

    if (isset($decoded['data']) && is_array($decoded['data'])) {
        return machine_extract_rows($decoded['data']);
    }

    return [];
}

function machine_row_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return is_array($row[$key])
                ? json_encode($row[$key], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                : (string)$row[$key];
        }
    }

    return '';
}

function machine_user_label(array $row): string
{
    global $isAdmin;
    $name = trim(machine_row_value($row, ['name', 'Name']));
    $userId = trim(machine_row_value($row, ['id', 'ID', 'user_id', 'userId']));
    $cardNo = trim(machine_row_value($row, ['cardno', 'cardNo', 'CardNo']));

    $parts = [];
    if ($name !== '') {
        $parts[] = $name;
    }
    if ($userId !== '') {
        $parts[] = 'ID ' . $userId;
    }
    if ($cardNo !== '') {
        $parts[] = 'Card ' . ($isAdmin ? $cardNo : machine_mask_card_number($cardNo));
    }

    return $parts !== [] ? implode(' | ', $parts) : 'Machine user';
}

function machine_mask_card_number(string $value): string
{
    $digits = preg_replace('/\s+/', '', trim($value));
    if ($digits === '') {
        return '-';
    }
    if (strlen($digits) <= 4) {
        return str_repeat('*', max(strlen($digits) - 1, 0)) . substr($digits, -1);
    }
    return str_repeat('*', max(strlen($digits) - 4, 0)) . substr($digits, -4);
}

function machine_person_label(array $anomaly): string
{
    $studentName = trim((string)($anomaly['student_first_name'] ?? '') . ' ' . (string)($anomaly['student_last_name'] ?? ''));
    if ($studentName !== '') {
        $studentNumber = trim((string)($anomaly['student_number'] ?? ''));
        return $studentNumber !== '' ? ($studentName . ' (' . $studentNumber . ')') : $studentName;
    }

    $userName = trim((string)($anomaly['mapped_user_name'] ?? ''));
    if ($userName !== '') {
        return $userName;
    }

    $username = trim((string)($anomaly['mapped_username'] ?? ''));
    if ($username !== '') {
        return $username;
    }

    return 'Unknown user';
}

function machine_decode_raw_log_entry(string $rawData): array
{
    $decoded = json_decode($rawData, true);
    return is_array($decoded) ? $decoded : [];
}

function machine_fetch_fingerprint_identity_map(mysqli $conn): array
{
    $map = [];
    $res = $conn->query("
        SELECT
            m.finger_id,
            m.user_id,
            u.name AS mapped_user_name,
            u.username AS mapped_username,
            s.first_name,
            s.last_name,
            s.student_id AS student_number
        FROM fingerprint_user_map m
        LEFT JOIN users u ON m.user_id = u.id
        LEFT JOIN students s ON s.user_id = m.user_id
    ");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $map[(int)($row['finger_id'] ?? 0)] = $row;
        }
        $res->close();
    }

    return $map;
}

function machine_identity_label(array $identity): string
{
    $studentName = trim((string)($identity['first_name'] ?? '') . ' ' . (string)($identity['last_name'] ?? ''));
    if ($studentName !== '') {
        $studentNumber = trim((string)($identity['student_number'] ?? ''));
        return $studentNumber !== '' ? ($studentName . ' (' . $studentNumber . ')') : $studentName;
    }

    $userName = trim((string)($identity['mapped_user_name'] ?? ''));
    if ($userName !== '') {
        return $userName;
    }

    $username = trim((string)($identity['mapped_username'] ?? ''));
    if ($username !== '') {
        return $username;
    }

    return 'Unknown user';
}

function machine_load_user_list_into_state(&$userListRaw, &$userListDecoded): void
{
    $result = biometric_machine_run_command('get-user-list');
    if (!$result['success']) {
        throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
    }

    $userListRaw = biometric_machine_clean_output((string)($result['text'] ?? ''));
    $userListDecoded = biometric_machine_decode_data($userListRaw);
}

function machine_load_user_details_into_state(int $selectedUserId, &$userDetailsRaw, &$userDetailsDecoded): void
{
    $result = biometric_machine_run_command('get-user', [(string)$selectedUserId]);
    if (!$result['success']) {
        throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
    }

    $userDetailsRaw = biometric_machine_clean_output((string)($result['text'] ?? ''));
    $userDetailsDecoded = biometric_machine_decode_data($userDetailsRaw);
}

if (isset($_SESSION['machine_manager_flash']) && is_array($_SESSION['machine_manager_flash'])) {
    $flashType = (string)($_SESSION['machine_manager_flash']['type'] ?? 'info');
    $flashMessage = (string)($_SESSION['machine_manager_flash']['message'] ?? '');
    unset($_SESSION['machine_manager_flash']);
}

if ((int)($_GET['load_users'] ?? 0) === 1) {
    try {
        if (machine_is_cloud_runtime()) {
            machine_load_user_list_from_bridge_cache($conn, $userListRaw, $userListDecoded);
        } else {
            machine_load_user_list_into_state($userListRaw, $userListDecoded);
        }
    } catch (Throwable $e) {
        if ($flashMessage === '') {
            $flashType = 'danger';
            $flashMessage = $e->getMessage();
        }
    }
}

if ($selectedUserId > 0 && (int)($_GET['load_user'] ?? 0) === 1) {
    try {
        if (machine_is_cloud_runtime()) {
            machine_load_user_list_from_bridge_cache($conn, $userListRaw, $userListDecoded);
            $rows = machine_extract_rows($userListDecoded);
            $matched = null;
            foreach ($rows as $row) {
                $rowUserId = (int)trim(machine_row_value($row, ['id', 'ID', 'user_id', 'userId', 'EnrollNumber']));
                if ($rowUserId === $selectedUserId) {
                    $matched = $row;
                    break;
                }
            }

            if (!is_array($matched)) {
                throw new RuntimeException('User ID not found in latest bridge cache. Click Read All Users first to refresh cache.');
            }

            $userDetailsDecoded = $matched;
            $userDetailsRaw = (string)json_encode($matched, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            machine_load_user_details_into_state($selectedUserId, $userDetailsRaw, $userDetailsDecoded);
        }
    } catch (Throwable $e) {
        if ($flashMessage === '') {
            $flashType = 'danger';
            $flashMessage = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['machine_action'] ?? ''));

    try {
        $adminOnlyActions = [
            'save_user_json',
            'save_config',
            'save_network',
            'save_connector_config',
            'save_bridge_profile',
            'quick_fill_bridge_router_2',
            'clear_records',
            'clear_users',
            'clear_admin',
            'restart',
            'save_device_identity',
        ];
        if (in_array($action, $adminOnlyActions, true) && !$isAdmin) {
            throw new RuntimeException('Only admins can perform that machine action.');
        }

        $connectorBoundActions = [
            'save_user_json',
            'save_user_name',
            'save_list_user_name',
            'delete_user',
            'delete_fingerprint',
            'get_device_info',
            'get_config',
            'save_config',
            'get_network',
            'save_network',
            'get_time',
            'set_time',
            'clear_records',
            'clear_users',
            'clear_admin',
            'restart',
            'save_device_identity',
        ];
        if (machine_is_cloud_runtime() && in_array($action, $connectorBoundActions, true)) {
            throw new RuntimeException('Direct machine commands are disabled in cloud runtime. Use F20H direct ingest by posting events to /api/f20h_ingest.php, then run Sync Now to reconcile logs into attendance.');
        }

        switch ($action) {
            case 'sync':
                $existingConfig = json_decode($machineConfigJson, true);
                if (!is_array($existingConfig)) {
                    $existingConfig = [];
                }

                $syncMode = strtolower(trim((string)($existingConfig['syncMode'] ?? 'direct_ingest')));
                if (!in_array($syncMode, ['direct_ingest', 'connector_fallback'], true)) {
                    $syncMode = 'direct_ingest';
                }

                $cloudRuntime = machine_is_cloud_runtime();
                $connectorText = '';

                if ($syncMode === 'connector_fallback' && !$cloudRuntime) {
                    $connector = biometric_machine_run_command('sync');
                    if (!$connector['success']) {
                        throw new RuntimeException(trim(implode("\n", $connector['output'] ?? [])));
                    }
                    $connectorText = trim((string)($connector['text'] ?? ''));
                } else {
                    $connectorText = 'Connector step skipped. Processing direct-ingest queue from database.';
                }

                $importMessage = run_biometric_auto_import();
                $_SESSION['machine_manager_flash'] = [
                    'type' => 'success',
                    'message' => trim($connectorText . "\n" . $importMessage),
                ];
                machine_redirect_after_post(['load_users' => 1]);

            case 'list_users':
                if (machine_is_cloud_runtime()) {
                    $cacheMeta = machine_load_user_list_from_bridge_cache($conn, $userListRaw, $userListDecoded);
                    $_SESSION['machine_manager_flash'] = [
                        'type' => 'success',
                        'message' => 'Bridge user cache loaded from '
                            . (($cacheMeta['source_node'] ?? '') !== '' ? ($cacheMeta['source_node'] . ' ') : '')
                            . '(' . (int)($cacheMeta['users_count'] ?? 0) . ' users, updated ' . (string)($cacheMeta['created_at'] ?? '-') . ').',
                    ];
                } else {
                    machine_load_user_list_into_state($userListRaw, $userListDecoded);
                    $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine user list loaded.'];
                }
                machine_redirect_after_post(['load_users' => 1]);

            case 'get_user':
                if ($selectedUserId <= 0) {
                    throw new RuntimeException('Enter a valid user ID.');
                }
                if (machine_is_cloud_runtime()) {
                    $cacheMeta = machine_load_user_list_from_bridge_cache($conn, $userListRaw, $userListDecoded);
                    $rows = machine_extract_rows($userListDecoded);
                    $matched = null;
                    foreach ($rows as $row) {
                        $rowUserId = (int)trim(machine_row_value($row, ['id', 'ID', 'user_id', 'userId', 'EnrollNumber']));
                        if ($rowUserId === $selectedUserId) {
                            $matched = $row;
                            break;
                        }
                    }

                    if (!is_array($matched)) {
                        throw new RuntimeException('User ID not found in latest bridge cache. Click Read All Users first to refresh cache.');
                    }

                    $userDetailsDecoded = $matched;
                    $userDetailsRaw = (string)json_encode($matched, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $_SESSION['machine_manager_flash'] = [
                        'type' => 'success',
                        'message' => 'Machine user record loaded from bridge cache (' . (string)($cacheMeta['created_at'] ?? '-') . ').',
                    ];
                } else {
                    machine_load_user_details_into_state($selectedUserId, $userDetailsRaw, $userDetailsDecoded);
                    $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine user record loaded.'];
                }
                machine_redirect_after_post(['selected_user_id' => $selectedUserId, 'load_users' => 1, 'load_user' => 1]);

            case 'save_user_json':
                if ($userDetailsRaw === '') {
                    throw new RuntimeException('User JSON cannot be empty.');
                }
                $tmp = tempnam(sys_get_temp_dir(), 'biotern_user_');
                file_put_contents($tmp, $userDetailsRaw);
                $result = biometric_machine_run_command('set-user', [$tmp]);
                @unlink($tmp);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine user updated.'];
                biometric_ops_log_audit($conn, (int)($_SESSION['user_id'] ?? 0), $role, 'machine_user_updated_raw', 'machine_user', (string)$selectedUserId, ['user_id' => $selectedUserId]);
                machine_redirect_after_post(['selected_user_id' => $selectedUserId, 'load_users' => 1, 'load_user' => 1]);

            case 'save_user_name':
                $newName = trim((string)($_POST['user_name'] ?? ''));
                if ($newName === '' || $userDetailsRaw === '') {
                    throw new RuntimeException('Load a user and enter a name first.');
                }
                $patchedJson = biometric_machine_patch_user_name($userDetailsRaw, $newName);
                $tmp = tempnam(sys_get_temp_dir(), 'biotern_user_');
                file_put_contents($tmp, $patchedJson);
                $result = biometric_machine_run_command('set-user', [$tmp]);
                @unlink($tmp);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine user name updated.'];
                biometric_ops_log_audit($conn, (int)($_SESSION['user_id'] ?? 0), $role, 'machine_user_renamed', 'machine_user', (string)$selectedUserId, ['user_id' => $selectedUserId, 'name' => $newName]);
                machine_redirect_after_post(['selected_user_id' => $selectedUserId, 'load_users' => 1, 'load_user' => 1]);

            case 'save_list_user_name':
                $newName = trim((string)($_POST['inline_user_name'] ?? ''));
                $inlineUserId = (int)($_POST['inline_user_id'] ?? 0);
                if ($newName === '' || $inlineUserId <= 0) {
                    throw new RuntimeException('Choose a machine user and enter a new name first.');
                }
                machine_load_user_details_into_state($inlineUserId, $userDetailsRaw, $userDetailsDecoded);
                if ($userDetailsRaw === '') {
                    throw new RuntimeException('Failed to load the full machine user record.');
                }
                $patchedJson = biometric_machine_patch_user_name($userDetailsRaw, $newName);
                $tmp = tempnam(sys_get_temp_dir(), 'biotern_user_');
                file_put_contents($tmp, $patchedJson);
                $result = biometric_machine_run_command('set-user', [$tmp]);
                @unlink($tmp);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine user renamed on the F20H.'];
                biometric_ops_log_audit($conn, (int)($_SESSION['user_id'] ?? 0), $role, 'machine_user_renamed', 'machine_user', (string)$inlineUserId, ['user_id' => $inlineUserId, 'name' => $newName]);
                machine_redirect_after_post(['selected_user_id' => $inlineUserId, 'load_users' => 1, 'load_user' => 1]);

            case 'delete_user':
                if ($selectedUserId <= 0) {
                    throw new RuntimeException('Enter a valid user ID to delete.');
                }
                $result = biometric_machine_run_command('delete-user', [(string)$selectedUserId]);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine user deleted.'];
                biometric_ops_log_audit($conn, (int)($_SESSION['user_id'] ?? 0), $role, 'machine_user_deleted', 'machine_user', (string)$selectedUserId, ['user_id' => $selectedUserId]);
                machine_redirect_after_post(['load_users' => 1]);

            case 'delete_fingerprint':
                if ($selectedUserId <= 0) {
                    throw new RuntimeException('Enter a valid F20H user ID to delete.');
                }
                $result = biometric_machine_run_command('delete-user', [(string)$selectedUserId]);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'warning', 'message' => 'Fingerprint record removed from the F20H machine user list.'];
                biometric_ops_log_audit($conn, (int)($_SESSION['user_id'] ?? 0), $role, 'machine_fingerprint_deleted', 'machine_user', (string)$selectedUserId, ['user_id' => $selectedUserId]);
                machine_redirect_after_post(['load_users' => 1]);

            case 'get_device_info':
                $result = biometric_machine_run_command('get-device-info');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Device info loaded.'];
                machine_redirect_after_post([]);

            case 'get_config':
                $result = biometric_machine_run_command('get-config');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Device config loaded.'];
                machine_redirect_after_post([]);

            case 'save_config':
                $configRaw = trim((string)($_POST['config_json'] ?? ''));
                if ($configRaw === '') {
                    throw new RuntimeException('Config JSON cannot be empty.');
                }
                $tmp = tempnam(sys_get_temp_dir(), 'biotern_cfg_');
                file_put_contents($tmp, $configRaw);
                $result = biometric_machine_run_command('set-config', [$tmp]);
                @unlink($tmp);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Device config updated.'];
                machine_redirect_after_post([]);

            case 'get_network':
                $result = biometric_machine_run_command('get-network');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Network settings loaded.'];
                machine_redirect_after_post([]);

            case 'save_network':
                $ip = trim((string)($_POST['ip_address'] ?? ''));
                $gateway = trim((string)($_POST['gateway'] ?? ''));
                $mask = trim((string)($_POST['mask'] ?? ''));
                $port = trim((string)($_POST['port'] ?? ''));
                $result = biometric_machine_run_command('set-network', [$ip, $gateway, $mask, $port]);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Network settings updated.'];
                machine_redirect_after_post([]);

            case 'get_time':
                $result = biometric_machine_run_command('get-time');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine time loaded.'];
                machine_redirect_after_post([]);

            case 'set_time':
                $timeValue = trim((string)($_POST['time_value'] ?? ''));
                $result = biometric_machine_run_command('set-time', [$timeValue]);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine time updated.'];
                machine_redirect_after_post([]);

            case 'save_connector_config':
                $machineConfigJson = trim((string)($_POST['connector_config_json'] ?? ''));
                if ($machineConfigJson === '') {
                    throw new RuntimeException('Connector config cannot be empty.');
                }
                if (json_decode($machineConfigJson, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Connector config must be valid JSON.');
                }
                machine_write_local_config_file($machineConfigPath, $machineConfigJson . PHP_EOL);
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Connector config updated.'];
                machine_redirect_after_post([]);

            case 'save_connector_profile':
                $existingConfig = json_decode($machineConfigJson, true);
                if (!is_array($existingConfig)) {
                    $existingConfig = [];
                }

                $existingConfig['ipAddress'] = trim((string)($_POST['connector_ip'] ?? ''));
                $existingConfig['gateway'] = trim((string)($_POST['connector_gateway'] ?? ''));
                $existingConfig['mask'] = trim((string)($_POST['connector_mask'] ?? '255.255.255.0'));
                $existingConfig['port'] = max(1, (int)($_POST['connector_port'] ?? 5001));
                $existingConfig['deviceNumber'] = max(1, (int)($_POST['connector_device_number'] ?? 1));
                $existingConfig['communicationPassword'] = trim((string)($_POST['connector_password'] ?? '0'));
                $selectedSyncMode = strtolower(trim((string)($_POST['sync_mode'] ?? 'direct_ingest')));
                $existingConfig['syncMode'] = in_array($selectedSyncMode, ['direct_ingest', 'connector_fallback'], true)
                    ? $selectedSyncMode
                    : 'direct_ingest';
                $existingConfig['outputPath'] = trim((string)($_POST['connector_output_path'] ?? ''));
                $existingConfig['autoImportOnIngest'] = !isset($_POST['disable_auto_import_on_ingest']);
                $existingConfig['attendanceWindowEnabled'] = isset($_POST['attendance_window_enabled']);
                $existingConfig['attendanceStartTime'] = trim((string)($_POST['attendance_start_time'] ?? '08:00:00'));
                $existingConfig['attendanceEndTime'] = trim((string)($_POST['attendance_end_time'] ?? '20:00:00'));
                $existingConfig['duplicateGuardMinutes'] = max(1, (int)($_POST['duplicate_guard_minutes'] ?? 10));
                $existingConfig['slotAdvanceMinimumMinutes'] = max(1, (int)($_POST['slot_advance_minimum_minutes'] ?? 10));
                $selectedRouterPreset = trim((string)($_POST['router_preset'] ?? 'custom'));
                $allowedRouterPresets = ['router_1', 'router_2', 'custom'];
                $existingConfig['selectedRouterPreset'] = in_array($selectedRouterPreset, $allowedRouterPresets, true) ? $selectedRouterPreset : 'custom';

                if ($existingConfig['ipAddress'] === '') {
                    throw new RuntimeException('Connector IP address is required.');
                }
                if ($existingConfig['outputPath'] === '') {
                    throw new RuntimeException('Connector output path is required.');
                }

                machine_connector_write_config($machineConfigPath, $existingConfig);
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Quick connector settings updated.'];
                machine_redirect_after_post([]);

            case 'save_bridge_profile':
                $bridgeEnabled = isset($_POST['bridge_enabled']);
                $selectedBridgePreset = trim((string)($_POST['bridge_preset_preview'] ?? 'laptop_custom'));
                $allowedBridgePresets = ['laptop_router_1', 'laptop_router_2', 'computer_router_2', 'laptop_custom'];
                if (!in_array($selectedBridgePreset, $allowedBridgePresets, true)) {
                    $selectedBridgePreset = 'laptop_custom';
                }
                $bridgeToken = trim((string)($_POST['bridge_token'] ?? ''));
                $cloudBaseUrl = rtrim(trim((string)($_POST['cloud_base_url'] ?? '')), '/');
                $ingestPath = trim((string)($_POST['ingest_path'] ?? '/api/f20h_ingest.php'));
                $ingestApiToken = trim((string)($_POST['ingest_api_token'] ?? ''));
                $pollSeconds = max(10, (int)($_POST['poll_seconds'] ?? 30));
                $bridgeIp = trim((string)($_POST['bridge_ip'] ?? ''));
                $bridgeGateway = trim((string)($_POST['bridge_gateway'] ?? ''));
                $bridgeMask = trim((string)($_POST['bridge_mask'] ?? '255.255.255.0'));
                $bridgePort = max(1, (int)($_POST['bridge_port'] ?? 5001));
                $bridgeDeviceNo = max(1, (int)($_POST['bridge_device_number'] ?? 1));
                $bridgePassword = trim((string)($_POST['bridge_password'] ?? '0'));
                $bridgeOutputPath = trim((string)($_POST['bridge_output_path'] ?? 'C:\\BioTern\\attendance.txt'));

                if ($bridgeToken === '') {
                    $bridgeToken = machine_make_bridge_token();
                }
                if ($cloudBaseUrl === '') {
                    $appUrl = getenv('APP_URL');
                    if (is_string($appUrl) && trim($appUrl) !== '') {
                        $cloudBaseUrl = rtrim(trim($appUrl), '/');
                    } else {
                        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
                        if ($host !== '') {
                            $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
                            $cloudBaseUrl = $scheme . '://' . $host;
                        }
                    }
                }
                if (stripos($cloudBaseUrl, 'http://') === 0) {
                    $cloudBaseUrl = 'https://' . substr($cloudBaseUrl, 7);
                }
                if ($ingestApiToken === '') {
                    $envApiToken = getenv('BIOTERN_API_TOKEN');
                    if (is_string($envApiToken) && trim($envApiToken) !== '') {
                        $ingestApiToken = trim($envApiToken);
                    }
                }
                if ($bridgeIp === '') {
                    throw new RuntimeException('Bridge F20H IP address is required.');
                }
                if ($cloudBaseUrl === '') {
                    throw new RuntimeException('Cloud base URL is required.');
                }
                if ($ingestApiToken === '') {
                    throw new RuntimeException('Ingest API token is required.');
                }

                machine_save_bridge_profile($conn, [
                    'selected_bridge_preset' => $selectedBridgePreset,
                    'bridge_enabled' => $bridgeEnabled ? 1 : 0,
                    'bridge_token' => $bridgeToken,
                    'cloud_base_url' => $cloudBaseUrl,
                    'ingest_path' => $ingestPath,
                    'ingest_api_token' => $ingestApiToken,
                    'poll_seconds' => $pollSeconds,
                    'ip_address' => $bridgeIp,
                    'gateway' => $bridgeGateway,
                    'mask' => $bridgeMask,
                    'port' => $bridgePort,
                    'device_number' => $bridgeDeviceNo,
                    'communication_password' => $bridgePassword,
                    'output_path' => $bridgeOutputPath,
                ], (int)($_SESSION['user_id'] ?? 0));

                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Laptop bridge profile saved. Token: ' . $bridgeToken];
                machine_redirect_after_post([]);

            case 'quick_fill_bridge_router_2':
                $bridgeToken = trim((string)($_POST['bridge_token'] ?? ''));
                if ($bridgeToken === '') {
                    $bridgeToken = machine_make_bridge_token();
                }

                $ingestApiToken = trim((string)($_POST['ingest_api_token'] ?? ''));
                if ($ingestApiToken === '') {
                    $envApiToken = getenv('BIOTERN_API_TOKEN');
                    if (is_string($envApiToken) && trim($envApiToken) !== '') {
                        $ingestApiToken = trim($envApiToken);
                    }
                }
                if ($ingestApiToken === '') {
                    $ingestApiToken = $bridgeToken;
                }

                $cloudBaseUrl = machine_bridge_default_cloud_base_url();
                if ($cloudBaseUrl === '') {
                    throw new RuntimeException('Unable to infer cloud base URL. Set APP_URL or open from the live domain.');
                }

                machine_save_bridge_profile($conn, [
                    'selected_bridge_preset' => 'computer_router_2',
                    'bridge_enabled' => 1,
                    'bridge_token' => $bridgeToken,
                    'cloud_base_url' => $cloudBaseUrl,
                    'ingest_path' => '/api/f20h_ingest.php',
                    'ingest_api_token' => $ingestApiToken,
                    'poll_seconds' => 30,
                    'ip_address' => '192.168.110.201',
                    'gateway' => '192.168.110.1',
                    'mask' => '255.255.255.0',
                    'port' => 5001,
                    'device_number' => 1,
                    'communication_password' => '0',
                    'output_path' => 'C:\\BioTern\\attendance.txt',
                ], (int)($_SESSION['user_id'] ?? 0));

                $_SESSION['machine_manager_flash'] = [
                    'type' => 'success',
                    'message' => 'Computer Bridge (Router 2) defaults were applied and saved to shared bridge profile. Bridge token: ' . $bridgeToken,
                ];
                machine_redirect_after_post([]);

            case 'test_bridge_profile':
                $savedProfile = machine_fetch_bridge_profile($conn);
                $probeBaseUrl = trim((string)($savedProfile['cloud_base_url'] ?? ''));
                $probeToken = trim((string)($savedProfile['bridge_token'] ?? ''));
                $probe = machine_probe_bridge_profile_endpoint($probeBaseUrl, $probeToken);
                if (empty($probe['ok'])) {
                    $fallbackToken = trim((string)($savedProfile['ingest_api_token'] ?? ''));
                    if ($fallbackToken !== '' && !hash_equals($fallbackToken, $probeToken)) {
                        $fallbackProbe = machine_probe_bridge_profile_endpoint($probeBaseUrl, $fallbackToken);
                        if (!empty($fallbackProbe['ok'])) {
                            $probe = $fallbackProbe;
                            $probe['message'] = 'Bridge profile endpoint accepted ingest token fallback. Save Bridge Profile to sync bridge token.';
                        }
                    }
                }

                $message = ($probe['ok'] ? 'Bridge test passed. ' : 'Bridge test failed. ') . ($probe['message'] ?? '');
                if (empty($probe['ok']) && (int)($probe['status'] ?? 0) === 401) {
                    $message .= ' HTTP 401 means token mismatch between saved bridge token and live API expectation. Click Fill Shared Bridge (Router 2), then Save Bridge Profile, then test again.';
                }
                $_SESSION['machine_manager_flash'] = [
                    'type' => $probe['ok'] ? 'success' : 'warning',
                    'message' => $message
                        . (($probe['url'] ?? '') !== '' ? (' Endpoint: ' . $probe['url']) : ''),
                ];
                machine_redirect_after_post([]);

            case 'clear_records':
                $result = biometric_machine_run_command('clear-records');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'warning', 'message' => 'Machine attendance records cleared.'];
                machine_redirect_after_post(['load_users' => 1]);

            case 'clear_users':
                $result = biometric_machine_run_command('clear-users');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'warning', 'message' => 'All users on the machine were cleared.'];
                machine_redirect_after_post([]);

            case 'clear_admin':
                $result = biometric_machine_run_command('clear-admin');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'warning', 'message' => 'Machine admin records cleared.'];
                machine_redirect_after_post([]);

            case 'restart':
                $result = biometric_machine_run_command('restart');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Restart command sent to the machine.'];
                machine_redirect_after_post([]);

            case 'save_device_identity':
                $deviceNo = trim((string)($_POST['device_number'] ?? ''));
                $password = trim((string)($_POST['communication_password'] ?? ''));
                if ($deviceNo !== '') {
                    $deviceNoResult = biometric_machine_run_command('set-device-no', [$deviceNo]);
                    if (!$deviceNoResult['success']) {
                        throw new RuntimeException(trim(implode("\n", $deviceNoResult['output'] ?? [])));
                    }
                }
                if ($password !== '') {
                    $passwordResult = biometric_machine_run_command('set-password', [$password]);
                    if (!$passwordResult['success']) {
                        throw new RuntimeException(trim(implode("\n", $passwordResult['output'] ?? [])));
                    }
                }
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine identity settings updated.'];
                machine_redirect_after_post([]);
        }
    } catch (Throwable $e) {
        $_SESSION['machine_manager_flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
        $errorParams = [];
        if ($selectedUserId > 0) {
            $errorParams['selected_user_id'] = $selectedUserId;
            $errorParams['load_user'] = 1;
        }
        $errorParams['load_users'] = 1;
        machine_redirect_after_post($errorParams);
    }
}

$loadedUserRows = machine_extract_rows($userListDecoded);
biometric_ops_ensure_tables($conn);
$latestSyncRun = biometric_ops_fetch_latest_sync_run($conn);
$recentAnomalies = biometric_ops_fetch_recent_anomalies($conn, 6);
$recentAuditLogs = biometric_ops_fetch_recent_audit_logs($conn, 6);
$openAnomalyCount = biometric_ops_fetch_open_anomaly_count($conn);
$fingerprintIdentityMap = machine_fetch_fingerprint_identity_map($conn);
$rawLogsPerPage = 50;
$rawLogPage = max(1, (int)($_GET['raw_page'] ?? 1));
$rawLogTotal = 0;
$rawLogProcessedTotal = 0;
$rawLogRows = [];
$rawLogSummary = $conn->query("
    SELECT
        COUNT(*) AS total_logs,
        SUM(CASE WHEN processed = 1 THEN 1 ELSE 0 END) AS processed_logs,
        MAX(imported_at) AS latest_imported_at
    FROM biometric_raw_logs
");
if ($rawLogSummary instanceof mysqli_result) {
    $summaryRow = $rawLogSummary->fetch_assoc() ?: [];
    $rawLogTotal = (int)($summaryRow['total_logs'] ?? 0);
    $rawLogProcessedTotal = (int)($summaryRow['processed_logs'] ?? 0);
    $latestRawImportAt = (string)($summaryRow['latest_imported_at'] ?? '');
    $rawLogSummary->close();
} else {
    $latestRawImportAt = '';
}
$rawLogPages = max(1, (int)ceil($rawLogTotal / $rawLogsPerPage));
if ($rawLogPage > $rawLogPages) {
    $rawLogPage = $rawLogPages;
}
$rawLogOffset = ($rawLogPage - 1) * $rawLogsPerPage;
$rawLogResult = $conn->query("
    SELECT id, raw_data, imported_at, processed
    FROM biometric_raw_logs
    ORDER BY id DESC
    LIMIT {$rawLogsPerPage} OFFSET {$rawLogOffset}
");
if ($rawLogResult instanceof mysqli_result) {
    while ($row = $rawLogResult->fetch_assoc()) {
        $rawLogRows[] = $row;
    }
    $rawLogResult->close();
}
$syncAttemptRows = [];
$syncAttemptResult = $conn->query("
    SELECT id, trigger_source, status, raw_inserted, processed_logs, attendance_changed, anomalies_found, started_at, finished_at
    FROM biometric_sync_runs
    ORDER BY id DESC
    LIMIT 20
");
if ($syncAttemptResult instanceof mysqli_result) {
    while ($row = $syncAttemptResult->fetch_assoc()) {
        $syncAttemptRows[] = $row;
    }
    $syncAttemptResult->close();
}
$ingestHealth = machine_fetch_ingest_health($conn);
$ingestSummary = $ingestHealth['summary'] ?? [];
$recentIngestEvents = $ingestHealth['recent'] ?? [];
$bridgeProfile = machine_fetch_bridge_profile($conn);
$connectorConfig = json_decode($machineConfigJson, true);
$connectorIp = is_array($connectorConfig) ? (string)($connectorConfig['ipAddress'] ?? '') : '';
$connectorPort = is_array($connectorConfig) ? (string)($connectorConfig['port'] ?? '') : '';
$connectorDeviceNo = is_array($connectorConfig) ? (string)($connectorConfig['deviceNumber'] ?? '') : '';
$connectorGateway = is_array($connectorConfig) ? (string)($connectorConfig['gateway'] ?? '') : '';
$connectorMask = is_array($connectorConfig) ? (string)($connectorConfig['mask'] ?? '255.255.255.0') : '255.255.255.0';
$connectorPassword = is_array($connectorConfig) ? (string)($connectorConfig['communicationPassword'] ?? '0') : '0';
$syncMode = is_array($connectorConfig) ? strtolower(trim((string)($connectorConfig['syncMode'] ?? 'direct_ingest'))) : 'direct_ingest';
if (!in_array($syncMode, ['direct_ingest', 'connector_fallback'], true)) {
    $syncMode = 'direct_ingest';
}
$cloudRuntime = machine_is_cloud_runtime();
$connectorOutputPath = is_array($connectorConfig) ? (string)($connectorConfig['outputPath'] ?? '') : '';
$autoImportOnIngest = !is_array($connectorConfig) || !array_key_exists('autoImportOnIngest', $connectorConfig) || !empty($connectorConfig['autoImportOnIngest']);
$connectorWindowEnabled = is_array($connectorConfig) ? !empty($connectorConfig['attendanceWindowEnabled']) : false;
$connectorStartTime = is_array($connectorConfig) ? (string)($connectorConfig['attendanceStartTime'] ?? '08:00:00') : '08:00:00';
$connectorEndTime = is_array($connectorConfig) ? (string)($connectorConfig['attendanceEndTime'] ?? '20:00:00') : '20:00:00';
$duplicateGuardMinutes = is_array($connectorConfig) ? (int)($connectorConfig['duplicateGuardMinutes'] ?? 10) : 10;
$slotAdvanceMinimumMinutes = is_array($connectorConfig) ? (int)($connectorConfig['slotAdvanceMinimumMinutes'] ?? 10) : 10;
$selectedRouterPreset = is_array($connectorConfig) ? (string)($connectorConfig['selectedRouterPreset'] ?? 'custom') : 'custom';
$bridgeEnabled = !empty($bridgeProfile['bridge_enabled']);
$bridgeToken = (string)($bridgeProfile['bridge_token'] ?? '');
$bridgeCloudBaseUrl = (string)($bridgeProfile['cloud_base_url'] ?? '');
$bridgeIngestPath = (string)($bridgeProfile['ingest_path'] ?? '/api/f20h_ingest.php');
$bridgeIngestApiToken = (string)($bridgeProfile['ingest_api_token'] ?? '');
$bridgePollSeconds = (int)($bridgeProfile['poll_seconds'] ?? 30);
$bridgeIpAddress = (string)($bridgeProfile['ip_address'] ?? $connectorIp);
$bridgeGateway = (string)($bridgeProfile['gateway'] ?? $connectorGateway);
$bridgeMask = (string)($bridgeProfile['mask'] ?? $connectorMask);
$bridgePort = (int)($bridgeProfile['port'] ?? ($connectorPort !== '' ? (int)$connectorPort : 5001));
$bridgeDeviceNumber = (int)($bridgeProfile['device_number'] ?? ($connectorDeviceNo !== '' ? (int)$connectorDeviceNo : 1));
$bridgePassword = (string)($bridgeProfile['communication_password'] ?? $connectorPassword);
$bridgeOutputPath = (string)($bridgeProfile['output_path'] ?? ($connectorOutputPath !== '' ? $connectorOutputPath : 'C:\\BioTern\\attendance.txt'));

if ($bridgeToken === '') {
    $bridgeTokenEnv = getenv('BIOTERN_BRIDGE_TOKEN');
    if (is_string($bridgeTokenEnv) && trim($bridgeTokenEnv) !== '') {
        $bridgeToken = trim($bridgeTokenEnv);
    }
}

if ($bridgeCloudBaseUrl === '') {
    $appUrl = getenv('APP_URL');
    if (is_string($appUrl) && trim($appUrl) !== '') {
        $bridgeCloudBaseUrl = rtrim(trim($appUrl), '/');
    } else {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $bridgeCloudBaseUrl = 'https://' . $host;
        }
    }
}
if (stripos($bridgeCloudBaseUrl, 'http://') === 0) {
    $bridgeCloudBaseUrl = 'https://' . substr($bridgeCloudBaseUrl, 7);
}

if ($bridgeIngestApiToken === '') {
    $apiTokenEnv = getenv('BIOTERN_API_TOKEN');
    if (is_string($apiTokenEnv) && trim($apiTokenEnv) !== '') {
        $bridgeIngestApiToken = trim($apiTokenEnv);
    }
}

$bridgeWorkerCommand = 'powershell -NoProfile -ExecutionPolicy Bypass -File ".\\tools\\bridge-worker.ps1"'
    . ' -SiteBaseUrl "' . str_replace('"', '\\"', $bridgeCloudBaseUrl !== '' ? $bridgeCloudBaseUrl : 'https://your-app.vercel.app') . '"'
    . ' -BridgeToken "' . str_replace('"', '\\"', $bridgeToken !== '' ? $bridgeToken : 'YOUR_BRIDGE_TOKEN') . '"'
    . ' -WorkspaceRoot "."';

$selectedBridgePreset = 'laptop_custom';
$savedBridgePreset = trim((string)($bridgeProfile['selected_bridge_preset'] ?? ''));
if (in_array($savedBridgePreset, ['laptop_router_1', 'laptop_router_2', 'computer_router_2', 'laptop_custom'], true)) {
    $selectedBridgePreset = $savedBridgePreset;
} elseif ($bridgeIpAddress === '192.168.100.201' && $bridgeGateway === '192.168.100.1') {
    $selectedBridgePreset = 'laptop_router_1';
} elseif ($bridgeIpAddress === '192.168.110.201' && $bridgeGateway === '192.168.110.1') {
    $selectedBridgePreset = 'computer_router_2';
}

$quickBridgeOptions = [
    'laptop_router_1' => [
        'label' => 'Laptop Bridge - Router 1',
        'ip' => '192.168.100.201',
        'gateway' => '192.168.100.1',
        'mask' => '255.255.255.0',
        'port' => '5001',
        'device_number' => '1',
        'poll_seconds' => (string)$bridgePollSeconds,
        'cloud_base_url' => $bridgeCloudBaseUrl,
        'ingest_path' => $bridgeIngestPath,
        'ingest_api_token' => $bridgeIngestApiToken,
        'output_path' => $bridgeOutputPath,
    ],
    'laptop_router_2' => [
        'label' => 'Laptop Bridge - Router 2',
        'ip' => '192.168.110.201',
        'gateway' => '192.168.110.1',
        'mask' => '255.255.255.0',
        'port' => '5001',
        'device_number' => '1',
        'poll_seconds' => (string)$bridgePollSeconds,
        'cloud_base_url' => $bridgeCloudBaseUrl,
        'ingest_path' => $bridgeIngestPath,
        'ingest_api_token' => $bridgeIngestApiToken,
        'output_path' => $bridgeOutputPath,
    ],
    'computer_router_2' => [
        'label' => 'Computer Bridge - WiFi Router 2',
        'ip' => '192.168.110.201',
        'gateway' => '192.168.110.1',
        'mask' => '255.255.255.0',
        'port' => '5001',
        'device_number' => '1',
        'poll_seconds' => (string)$bridgePollSeconds,
        'cloud_base_url' => $bridgeCloudBaseUrl,
        'ingest_path' => $bridgeIngestPath,
        'ingest_api_token' => $bridgeIngestApiToken,
        'output_path' => $bridgeOutputPath,
    ],
    'laptop_custom' => [
        'label' => 'Laptop Bridge - Custom',
        'ip' => $bridgeIpAddress,
        'gateway' => $bridgeGateway,
        'mask' => $bridgeMask,
        'port' => (string)$bridgePort,
        'device_number' => (string)$bridgeDeviceNumber,
        'poll_seconds' => (string)$bridgePollSeconds,
        'cloud_base_url' => $bridgeCloudBaseUrl,
        'ingest_path' => $bridgeIngestPath,
        'ingest_api_token' => $bridgeIngestApiToken,
        'output_path' => $bridgeOutputPath,
    ],
];
$quickRouterOptions = [
    'router_1' => [
        'label' => 'Router 1',
        'ip' => '192.168.100.201',
        'gateway' => '192.168.100.1',
        'mask' => '255.255.255.0',
        'port' => '5001',
    ],
    'router_2' => [
        'label' => 'Router 2',
        'ip' => '192.168.110.201',
        'gateway' => '192.168.110.1',
        'mask' => '255.255.255.0',
        'port' => '5001',
    ],
    'custom' => [
        'label' => 'Custom',
        'ip' => $connectorIp,
        'gateway' => $connectorGateway,
        'mask' => $connectorMask,
        'port' => $connectorPort !== '' ? $connectorPort : '5001',
    ],
];
$page_title = 'BioTern || F20H Machine Manager';
$page_body_class = 'page-biometric-machine';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/pages/page-biometric-machine.css',
];
$page_scripts = [
    'assets/js/modules/pages/biometric-management-runtime.js',
];
include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">F20H Machine Manager</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">F20H Machine Manager</li>
                </ul>
            </div>
        </div>

        <?php if ($flashMessage !== ''): ?>
            <div class="alert alert-<?php echo machine_h($flashType); ?> alert-dismissible fade show" role="alert">
                <?php echo nl2br(machine_h($flashMessage)); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="alert alert-info">
            Fingerprint templates stay on the F20H. BioTern only manages machine user records, mappings, and attendance events. Card numbers are masked for non-admin views.
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Connector Target</div>
                        <div class="fw-bold"><?php echo machine_h($connectorIp !== '' ? $connectorIp : 'Not set'); ?></div>
                        <div class="text-muted">Port: <?php echo machine_h($connectorPort !== '' ? $connectorPort : '-'); ?> | Device: <?php echo machine_h($connectorDeviceNo !== '' ? $connectorDeviceNo : '-'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Loaded Machine Users</div>
                        <div class="fs-3 fw-bold"><?php echo count($loadedUserRows); ?></div>
                        <div class="text-muted"><?php echo $cloudRuntime
                            ? 'Cloud mode: Read All Users uses bridge cache uploaded by your bridge computer.'
                            : 'Use “Read All Users” to refresh this page view.'; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Selected Machine User</div>
                        <div class="fs-3 fw-bold"><?php echo machine_h($selectedUserId > 0 ? (string)$selectedUserId : '-'); ?></div>
                        <div class="text-muted">Load a record, then edit its name or raw JSON below.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Last Sync Status</div>
                        <div class="fs-4 fw-bold"><?php echo machine_h($latestSyncRun['status'] ?? 'No runs yet'); ?></div>
                        <div class="text-muted"><?php echo machine_h((string)($latestSyncRun['finished_at'] ?? $latestSyncRun['started_at'] ?? '')); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Open Anomalies</div>
                        <div class="fs-3 fw-bold"><?php echo $openAnomalyCount; ?></div>
                        <div class="text-muted">Duplicate punches, unmapped scans, and suspicious attendance cases.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Last Sync Totals</div>
                        <div class="fw-bold">
                            <?php echo machine_h('Raw ' . (string)($latestSyncRun['raw_inserted'] ?? 0) . ' | Logs ' . (string)($latestSyncRun['processed_logs'] ?? 0)); ?>
                        </div>
                        <div class="text-muted">
                            <?php echo machine_h('Attendance changed: ' . (string)($latestSyncRun['attendance_changed'] ?? 0) . ' | Anomalies: ' . (string)($latestSyncRun['anomalies_found'] ?? 0)); ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Last Ingest Event</div>
                        <div class="fw-bold"><?php echo machine_h((string)($ingestSummary['last_received_at'] ?? 'No ingest yet')); ?></div>
                        <div class="text-muted">Node: <?php echo machine_h((string)($ingestSummary['last_source_node'] ?? 'unknown-node')); ?> | IP: <?php echo machine_h((string)($ingestSummary['last_source_ip'] ?? '-')); ?></div>
                        <div class="text-muted">Token: <?php echo machine_h((string)($ingestSummary['last_token_status'] ?? 'unknown')); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Ingest Today</div>
                        <div class="fs-3 fw-bold"><?php echo machine_h((string)($ingestSummary['total_today'] ?? 0)); ?></div>
                        <div class="text-muted">Accepted events: <?php echo machine_h((string)($ingestSummary['accepted_today'] ?? 0)); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stretch stretch-full">
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-1">Last Ingest HTTP Status</div>
                        <div class="fs-3 fw-bold"><?php echo machine_h((string)($ingestSummary['last_http_status'] ?? 0)); ?></div>
                        <div class="text-muted">200 means accepted; 401 token mismatch; 422 invalid payload.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-4">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Machine Sync</h6></div>
                    <div class="card-body">
                        <p class="text-muted"><?php echo $cloudRuntime || $syncMode === 'direct_ingest'
                                ? 'Process queued direct-ingest logs from Railway and reconcile them into attendance.'
                                : 'Pull new logs from the F20H, then reconcile them into BioTern attendance.'; ?></p>
                        <form method="post">
                            <input type="hidden" name="machine_action" value="sync">
                            <button type="submit" class="btn btn-primary w-100"><?php echo $cloudRuntime || $syncMode === 'direct_ingest' ? 'Process Ingest Queue' : 'Sync Now'; ?></button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Machine Status</h6></div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <form method="post">
                                <input type="hidden" name="machine_action" value="get_device_info">
                                <button type="submit" class="btn btn-outline-primary w-100">Read Device Info</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="machine_action" value="get_network">
                                <button type="submit" class="btn btn-outline-primary w-100">Read Network Settings</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="machine_action" value="get_time">
                                <button type="submit" class="btn btn-outline-primary w-100">Read Device Time</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Machine Config</h6></div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <form method="post">
                                <input type="hidden" name="machine_action" value="get_config">
                                <button type="submit" class="btn btn-outline-secondary w-100" <?php echo $cloudRuntime ? 'disabled title="Unavailable in cloud runtime"' : ''; ?>>Read Device Config</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="machine_action" value="list_users">
                                <button type="submit" class="btn btn-outline-secondary w-100" title="<?php echo $cloudRuntime ? 'Loads latest user list from bridge cache' : 'Reads directly from machine'; ?>"><?php echo $cloudRuntime ? 'Read All Users (Bridge Cache)' : 'Read All Users'; ?></button>
                            </form>
                            <a href="attendance.php" class="btn btn-outline-secondary w-100">Open Attendance DTR</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card stretch stretch-full machine-users-card">
                    <div class="card-header"><h6 class="card-title mb-0">Users on Machine</h6></div>
                    <div class="card-body">
                        <?php if ($cloudRuntime): ?>
                            <div class="alert alert-warning">
                                Direct LAN reads are not available in Vercel runtime. Use Read All Users (Bridge Cache) after bridge worker sync from Router 2.
                            </div>
                        <?php endif; ?>
                        <form method="post" class="row g-2 align-items-end mb-3">
                            <input type="hidden" name="machine_action" value="get_user">
                            <div class="col-sm-6">
                                <label class="form-label">User ID on F20H</label>
                                <input type="number" name="user_id" class="form-control" value="<?php echo machine_h($selectedUserId); ?>" min="1">
                            </div>
                            <div class="col-sm-6">
                                <button type="submit" class="btn btn-primary w-100" title="<?php echo $cloudRuntime ? 'Loads selected user from bridge cache' : 'Loads selected user from machine'; ?>">Load User Record</button>
                            </div>
                        </form>

                        <?php $rows = machine_extract_rows($userListDecoded); ?>
                        <?php if (!empty($rows)): ?>
                            <div class="table-responsive mb-3">
                                <table class="table table-sm table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Machine ID</th>
                                            <th>Name</th>
                                            <th>Card No</th>
                                            <th>Privilege</th>
                                            <th>Rename on F20H</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rows as $row): ?>
                                            <tr>
                                                <?php $rowUserId = machine_row_value($row, ['id', 'ID', 'user_id', 'userId', 'EnrollNumber']); ?>
                                                <?php $rowName = machine_row_value($row, ['name', 'Name']); ?>
                                                <?php $rowCardNo = machine_row_value($row, ['cardno', 'cardNo', 'CardNo']); ?>
                                                <?php $rowPrivilege = machine_row_value($row, ['privilege', 'privalege', 'Privilege']); ?>
                                                <td><?php echo machine_h($rowUserId !== '' ? $rowUserId : '-'); ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?php echo machine_h($rowName !== '' ? $rowName : '(blank)'); ?></div>
                                                    <small class="text-muted"><?php echo machine_h(machine_user_label($row)); ?></small>
                                                </td>
                                                <td><?php echo machine_h($rowCardNo !== '' ? ($isAdmin ? $rowCardNo : machine_mask_card_number($rowCardNo)) : '-'); ?></td>
                                                <td><?php echo machine_h($rowPrivilege !== '' ? $rowPrivilege : '-'); ?></td>
                                                <td class="machine-rename-cell">
                                                    <?php if ($rowUserId !== ''): ?>
                                                        <form method="post" class="d-flex gap-2">
                                                            <input type="hidden" name="machine_action" value="save_list_user_name">
                                                            <input type="hidden" name="inline_user_id" value="<?php echo machine_h($rowUserId); ?>">
                                                            <input type="text" name="inline_user_name" class="form-control form-control-sm" value="<?php echo machine_h($rowName); ?>" placeholder="Type new name">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <span class="text-muted fs-12">Unavailable</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <?php if ($rowUserId !== ''): ?>
                                                        <div class="machine-inline-actions">
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="machine_action" value="get_user">
                                                                <input type="hidden" name="user_id" value="<?php echo machine_h($rowUserId); ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-primary">Load</button>
                                                            </form>
                                                            <form method="post" class="d-inline" data-confirm="Delete this fingerprint record from the F20H machine? This removes the machine user entry.">
                                                                <input type="hidden" name="machine_action" value="delete_fingerprint">
                                                                <input type="hidden" name="user_id" value="<?php echo machine_h($rowUserId); ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete FP</button>
                                                            </form>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted fs-12">No ID</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if ($isAdmin): ?>
                            <label class="form-label">Raw User List</label>
                            <textarea class="form-control" rows="10" readonly><?php echo machine_h($userListRaw); ?></textarea>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header"><h6 class="card-title mb-0">Selected User Editor</h6></div>
                    <div class="card-body">
                        <form method="post" class="row g-2 mb-3">
                            <div class="col-sm-8">
                                <label class="form-label">Quick Name Update</label>
                                <input type="text" name="user_name" class="form-control" value="<?php echo machine_h((string)($_POST['user_name'] ?? machine_row_value(is_array($userDetailsDecoded) ? $userDetailsDecoded : [], ['name', 'Name', 'username', 'userName', 'UserName']))); ?>">
                            </div>
                            <div class="col-sm-4 d-flex align-items-end">
                                <input type="hidden" name="machine_action" value="save_user_name">
                                <input type="hidden" name="user_id" value="<?php echo machine_h($selectedUserId); ?>">
                                <input type="hidden" name="user_json" value="<?php echo machine_h($userDetailsRaw); ?>">
                                <button type="submit" class="btn btn-outline-primary w-100">Save Name</button>
                            </div>
                        </form>

                        <form method="post" class="mt-2" data-confirm="Delete this fingerprint record from the F20H machine? This removes the machine user entry.">
                            <input type="hidden" name="machine_action" value="delete_fingerprint">
                            <input type="hidden" name="user_id" value="<?php echo machine_h($selectedUserId); ?>">
                            <button type="submit" class="btn btn-danger">Delete Fingerprint</button>
                        </form>
                        <div class="text-muted fs-12 mt-2">This device SDK removes the whole F20H machine user record, which also removes the enrolled fingerprint from the device.</div>

                        <?php if ($isAdmin || is_array($userDetailsDecoded)): ?>
                            <div class="mt-3">
                                <button
                                    class="btn btn-sm btn-outline-secondary"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#machineUserAdvanced"
                                    aria-expanded="false"
                                    aria-controls="machineUserAdvanced"
                                >
                                    Toggle Advanced Record
                                </button>
                            </div>
                            <div class="collapse mt-3" id="machineUserAdvanced">
                                <?php if ($isAdmin): ?>
                                    <form method="post">
                                        <input type="hidden" name="machine_action" value="save_user_json">
                                        <input type="hidden" name="user_id" value="<?php echo machine_h($selectedUserId); ?>">
                                        <label class="form-label">Raw User JSON</label>
                                        <textarea
                                            name="user_json"
                                            class="form-control machine-user-json-field"
                                            rows="4"
                                        ><?php echo machine_h($userDetailsRaw); ?></textarea>
                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            <button type="submit" class="btn btn-primary">Save Raw User</button>
                                        </div>
                                    </form>
                                <?php endif; ?>

                                <?php if (is_array($userDetailsDecoded)): ?>
                                    <div class="row g-2 mt-3">
                                        <?php echo machine_render_pairs($userDetailsDecoded); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Laptop Bridge Profile</h6></div>
                    <div class="card-body">
                        <div class="machine-config-pane h-100">
                            <div class="text-muted fs-12 mb-3">Use this profile for your laptop bridge worker now. It is saved in Railway and used by Vercel endpoints.</div>
                            <?php if (!$isAdmin): ?>
                                <div class="alert alert-info mb-0">Only admins can change bridge profile settings.</div>
                            <?php else: ?>
                                <form method="post" class="row g-2 machine-compact-form">
                                    <input type="hidden" name="machine_action" value="save_bridge_profile">
                                    <div class="col-sm-4">
                                        <label class="form-label">Laptop Bridge Preset</label>
                                        <select class="form-select" id="bridgePresetSelect" name="bridge_preset_preview">
                                            <?php foreach ($quickBridgeOptions as $presetKey => $preset): ?>
                                                <option
                                                    value="<?php echo machine_h($presetKey); ?>"
                                                    data-cloud-base-url="<?php echo machine_h((string)$preset['cloud_base_url']); ?>"
                                                    data-ingest-path="<?php echo machine_h((string)$preset['ingest_path']); ?>"
                                                    data-ingest-api-token="<?php echo machine_h((string)$preset['ingest_api_token']); ?>"
                                                    data-poll-seconds="<?php echo machine_h((string)$preset['poll_seconds']); ?>"
                                                    data-ip="<?php echo machine_h((string)$preset['ip']); ?>"
                                                    data-gateway="<?php echo machine_h((string)$preset['gateway']); ?>"
                                                    data-mask="<?php echo machine_h((string)$preset['mask']); ?>"
                                                    data-port="<?php echo machine_h((string)$preset['port']); ?>"
                                                    data-device-number="<?php echo machine_h((string)$preset['device_number']); ?>"
                                                    data-output-path="<?php echo machine_h((string)$preset['output_path']); ?>"
                                                    <?php echo $selectedBridgePreset === $presetKey ? 'selected' : ''; ?>
                                                >
                                                    <?php echo machine_h((string)$preset['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 d-flex align-items-end">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="bridge_enabled" id="bridgeEnabled" <?php echo $bridgeEnabled ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="bridgeEnabled">Laptop Bridge Enabled</label>
                                        </div>
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">Bridge Token</label>
                                        <input type="text" name="bridge_token" id="bridgeTokenField" class="form-control" value="<?php echo machine_h($bridgeToken); ?>" placeholder="shared token for bridge_profile.php">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">Cloud Base URL</label>
                                        <input type="text" name="cloud_base_url" id="bridgeCloudBaseUrlField" class="form-control" value="<?php echo machine_h($bridgeCloudBaseUrl); ?>" placeholder="https://your-app.vercel.app">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">Ingest Path</label>
                                        <input type="text" name="ingest_path" id="bridgeIngestPathField" class="form-control" value="<?php echo machine_h($bridgeIngestPath); ?>" placeholder="/api/f20h_ingest.php">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">Ingest API Token</label>
                                        <input type="text" name="ingest_api_token" id="bridgeIngestApiTokenField" class="form-control" value="<?php echo machine_h($bridgeIngestApiToken); ?>" placeholder="BIOTERN_API_TOKEN">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">Poll Seconds</label>
                                        <input type="number" name="poll_seconds" id="bridgePollSecondsField" class="form-control" value="<?php echo machine_h((string)$bridgePollSeconds); ?>" min="10" max="300">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">F20H IP</label>
                                        <input type="text" name="bridge_ip" id="bridgeIpField" class="form-control" value="<?php echo machine_h($bridgeIpAddress); ?>" placeholder="192.168.110.201">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">Gateway</label>
                                        <input type="text" name="bridge_gateway" id="bridgeGatewayField" class="form-control" value="<?php echo machine_h($bridgeGateway); ?>" placeholder="192.168.110.1">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">Mask</label>
                                        <input type="text" name="bridge_mask" id="bridgeMaskField" class="form-control" value="<?php echo machine_h($bridgeMask); ?>" placeholder="255.255.255.0">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">Port</label>
                                        <input type="number" name="bridge_port" id="bridgePortField" class="form-control" value="<?php echo machine_h((string)$bridgePort); ?>" min="1">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label">Device Number</label>
                                        <input type="number" name="bridge_device_number" id="bridgeDeviceNumberField" class="form-control" value="<?php echo machine_h((string)$bridgeDeviceNumber); ?>" min="1">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">Communication Password</label>
                                        <input type="text" name="bridge_password" id="bridgePasswordField" class="form-control" value="<?php echo machine_h($bridgePassword); ?>" placeholder="0">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">Local Output Path (bridge PC)</label>
                                        <input type="text" name="bridge_output_path" id="bridgeOutputPathField" class="form-control" value="<?php echo machine_h($bridgeOutputPath); ?>" placeholder="C:\\BioTern\\attendance.txt">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Start Bridge Worker Command (run on bridge computer)</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="bridgeWorkerCommandField" value="<?php echo machine_h($bridgeWorkerCommand); ?>" readonly>
                                            <button type="button" class="btn btn-outline-secondary" id="copyBridgeWorkerCmdBtn">Copy</button>
                                        </div>
                                        <small class="text-muted">Open PowerShell in your BioTern folder, paste this command, then press Enter.</small>
                                    </div>
                                    <div class="col-12 d-flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-secondary btn-sm">Save Laptop Bridge Profile</button>
                                        <button type="submit" class="btn btn-outline-primary btn-sm" formaction="" formmethod="post" name="machine_action" value="quick_fill_bridge_router_2">Fill Shared Bridge (Router 2)</button>
                                        <button type="submit" class="btn btn-outline-info btn-sm" formaction="" formmethod="post" name="machine_action" value="test_bridge_profile">Test Shared Bridge</button>
                                        <small class="text-muted align-self-center">Laptop worker fetches this profile from /bridge_profile.php using the bridge token.</small>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <h6 class="card-title mb-0">Quick Router Switch</h6>
                        <button type="button" class="btn btn-sm btn-secondary" id="copyConnectorToMachineBtn">Copy to Network Form</button>
                    </div>
                    <div class="card-body">
                        <div class="machine-config-pane h-100">
                            <div class="text-muted fs-12 mb-2">Choose a router preset, adjust values, then save.</div>
                            <form method="post" class="row g-2 mt-1 machine-compact-form">
                                <input type="hidden" name="machine_action" value="save_connector_profile">
                                <div class="col-sm-6">
                                    <label class="form-label">Router Preset</label>
                                    <select class="form-select" id="routerPresetSelect" name="router_preset">
                                        <?php foreach ($quickRouterOptions as $presetKey => $preset): ?>
                                            <option
                                                value="<?php echo machine_h($presetKey); ?>"
                                                data-ip="<?php echo machine_h((string)$preset['ip']); ?>"
                                                data-gateway="<?php echo machine_h((string)$preset['gateway']); ?>"
                                                data-mask="<?php echo machine_h((string)$preset['mask']); ?>"
                                                data-port="<?php echo machine_h((string)$preset['port']); ?>"
                                                <?php echo $selectedRouterPreset === $presetKey ? 'selected' : ''; ?>
                                            >
                                                <?php echo machine_h((string)$preset['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Sync Mode</label>
                                    <select class="form-select" name="sync_mode">
                                        <option value="direct_ingest" <?php echo $syncMode === 'direct_ingest' ? 'selected' : ''; ?>>Direct machine ingest</option>
                                        <option value="connector_fallback" <?php echo $syncMode === 'connector_fallback' ? 'selected' : ''; ?>>Connector fallback worker</option>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Attendance File</label>
                                    <input type="text" name="connector_output_path" class="form-control" value="<?php echo machine_h($connectorOutputPath); ?>" placeholder="C:\xampp\htdocs\BioTern\attendance.txt">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Connector IP</label>
                                    <input type="text" name="connector_ip" id="connectorIpField" class="form-control" value="<?php echo machine_h($connectorIp); ?>" placeholder="192.168.110.201">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Gateway</label>
                                    <input type="text" name="connector_gateway" id="connectorGatewayField" class="form-control" value="<?php echo machine_h($connectorGateway); ?>" placeholder="192.168.110.1">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Mask</label>
                                    <input type="text" name="connector_mask" id="connectorMaskField" class="form-control" value="<?php echo machine_h($connectorMask); ?>" placeholder="255.255.255.0">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Port</label>
                                    <input type="number" name="connector_port" id="connectorPortField" class="form-control" value="<?php echo machine_h($connectorPort !== '' ? $connectorPort : '5001'); ?>" min="1">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Device Number</label>
                                    <input type="number" name="connector_device_number" class="form-control" value="<?php echo machine_h($connectorDeviceNo !== '' ? $connectorDeviceNo : '1'); ?>" min="1">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Communication Password</label>
                                    <input type="text" name="connector_password" class="form-control" value="<?php echo machine_h($connectorPassword); ?>" placeholder="0">
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label">Duplicate Guard (minutes)</label>
                                    <input type="number" name="duplicate_guard_minutes" class="form-control" value="<?php echo machine_h((string)$duplicateGuardMinutes); ?>" min="1" max="60">
                                </div>
                                <div class="col-sm-4">
                                    <label class="form-label">Slot Advance Minimum</label>
                                    <input type="number" name="slot_advance_minimum_minutes" class="form-control" value="<?php echo machine_h((string)$slotAdvanceMinimumMinutes); ?>" min="1" max="240">
                                </div>
                                <div class="col-sm-4 d-flex align-items-end">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="attendance_window_enabled" id="attendanceWindowEnabled" <?php echo $connectorWindowEnabled ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="attendanceWindowEnabled">Use Attendance Window</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="disable_auto_import_on_ingest" id="disableAutoImportOnIngest" <?php echo !$autoImportOnIngest ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="disableAutoImportOnIngest">Disable automatic attendance import after direct ingest</label>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Window Start</label>
                                    <input type="text" name="attendance_start_time" class="form-control" value="<?php echo machine_h($connectorStartTime); ?>" placeholder="08:00:00">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Window End</label>
                                    <input type="text" name="attendance_end_time" class="form-control" value="<?php echo machine_h($connectorEndTime); ?>" placeholder="20:00:00">
                                </div>
                                <div class="col-12 d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-secondary btn-sm">Save Quick Router Settings</button>
                                    <small class="text-muted align-self-center">Use direct ingest for Vercel deployments. Use connector fallback when a Windows worker pulls from F20H first.</small>
                                </div>
                                <div class="col-12">
                                    <div class="machine-endpoint-box border rounded p-3">
                                        <div class="fw-semibold mb-1">Dedicated F20H Ingest Endpoint</div>
                                        <div class="text-muted fs-12 mb-2">Point the machine or bridge to this endpoint for raw F20H payload delivery. The same BIOTERN_API_TOKEN header/token used by the biometric API is accepted here.</div>
                                        <div class="d-flex flex-column gap-1">
                                            <code>/api/f20h_ingest.php</code>
                                            <small class="text-muted">Vercel fallback route: <code>/f20h_ingest.php</code></small>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="card-title mb-0">Direct Device Controls</h6>
                        <button class="btn btn-sm machine-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#machineNetworkDirectControls" aria-expanded="false" aria-controls="machineNetworkDirectControls">Toggle Device Controls</button>
                    </div>
                    <div class="card-body">
                        <div class="text-muted fs-12 mb-2">Optional controls for direct machine networking and time adjustments.</div>
                        <div class="collapse" id="machineNetworkDirectControls">
                            <form method="post" class="row g-2 mb-4">
                                <input type="hidden" name="machine_action" value="save_network">
                                <div class="col-sm-6">
                                    <label class="form-label">IP Address</label>
                                    <input type="text" name="ip_address" id="machineIpField" class="form-control" value="<?php echo machine_h($connectorIp); ?>" placeholder="192.168.100.201">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Gateway</label>
                                    <input type="text" name="gateway" id="machineGatewayField" class="form-control" value="<?php echo machine_h($connectorGateway); ?>" placeholder="192.168.100.1">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Mask</label>
                                    <input type="text" name="mask" id="machineMaskField" class="form-control" value="<?php echo machine_h($connectorMask); ?>" placeholder="255.255.255.0">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Port</label>
                                    <input type="number" name="port" id="machinePortField" class="form-control" value="<?php echo machine_h($connectorPort !== '' ? $connectorPort : '5001'); ?>" placeholder="5001">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-secondary">Save Network Settings</button>
                                </div>
                            </form>

                            <form method="post" class="row g-2">
                                <input type="hidden" name="machine_action" value="set_time">
                                <div class="col-sm-8">
                                    <label class="form-label">Device Time</label>
                                    <input type="text" name="time_value" class="form-control" placeholder="2026-03-25 08:00:00">
                                </div>
                                <div class="col-sm-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-secondary w-100">Set Time</button>
                                </div>
                            </form>

                            <div class="mt-3">
                                <label class="form-label">Last Network Readback</label>
                                <textarea class="form-control" rows="4" readonly><?php echo machine_h($networkRaw); ?></textarea>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Last Time Readback</label>
                                <textarea class="form-control" rows="2" readonly><?php echo machine_h($timeRaw); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card stretch stretch-full machine-raw-logs-card">
                    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <h6 class="card-title mb-0">Raw Machine Logs</h6>
                            <div class="text-muted fs-12 mt-1">Every log BioTern has already pulled from the F20H. Use this to monitor volume before clearing the machine records.</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold"><?php echo machine_h((string)$rawLogTotal); ?> total logs</div>
                            <small class="text-muted"><?php echo machine_h((string)$rawLogProcessedTotal); ?> processed<?php echo $latestRawImportAt !== '' ? ' | Last import ' . machine_h($latestRawImportAt) : ''; ?></small>
                            <div class="mt-2">
                                <button class="btn btn-sm btn-outline-secondary machine-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#machineRawLogsCollapse" aria-expanded="false" aria-controls="machineRawLogsCollapse">Toggle Logs</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0 collapse" id="machineRawLogsCollapse">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Raw ID</th>
                                        <th>Fingerprint</th>
                                        <th>Matched Person</th>
                                        <th>Time</th>
                                        <th>Type</th>
                                        <th>Processed</th>
                                        <th>Imported</th>
                                        <th>Raw JSON</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rawLogRows)): ?>
                                        <tr><td colspan="8" class="text-center text-muted py-3">No raw machine logs stored yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($rawLogRows as $rawLog): ?>
                                            <?php $rawEntry = machine_decode_raw_log_entry((string)($rawLog['raw_data'] ?? '')); ?>
                                            <?php $rawFingerId = (int)($rawEntry['finger_id'] ?? $rawEntry['id'] ?? 0); ?>
                                            <?php $rawIdentity = $fingerprintIdentityMap[$rawFingerId] ?? []; ?>
                                            <tr>
                                                <td><?php echo machine_h((string)($rawLog['id'] ?? '-')); ?></td>
                                                <td><?php echo machine_h($rawFingerId > 0 ? (string)$rawFingerId : '-'); ?></td>
                                                <td><?php echo machine_h($rawIdentity !== [] ? machine_identity_label($rawIdentity) : 'Unmapped fingerprint'); ?></td>
                                                <td><?php echo machine_h((string)($rawEntry['time'] ?? '-')); ?></td>
                                                <td><?php echo machine_h((string)($rawEntry['type'] ?? '-')); ?></td>
                                                <td><?php echo machine_h(!empty($rawLog['processed']) ? 'Yes' : 'No'); ?></td>
                                                <td><?php echo machine_h((string)($rawLog['imported_at'] ?? '-')); ?></td>
                                                <td class="machine-raw-json-cell"><code><?php echo machine_h((string)($rawLog['raw_data'] ?? '')); ?></code></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3 border-top">
                            <small class="text-muted">Page <?php echo machine_h((string)$rawLogPage); ?> of <?php echo machine_h((string)$rawLogPages); ?></small>
                            <div class="d-flex gap-2">
                                <?php if ($rawLogPage > 1): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="biometric-machine.php?raw_page=<?php echo $rawLogPage - 1; ?>">Previous</a>
                                <?php endif; ?>
                                <?php if ($rawLogPage < $rawLogPages): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="biometric-machine.php?raw_page=<?php echo $rawLogPage + 1; ?>">Next</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2">
                        <h6 class="card-title mb-0">Sync Attempts</h6>
                        <button class="btn btn-sm btn-outline-secondary machine-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#machineSyncAttemptsCollapse" aria-expanded="false" aria-controls="machineSyncAttemptsCollapse">Toggle</button>
                    </div>
                    <div class="card-body p-0 collapse" id="machineSyncAttemptsCollapse">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Started</th>
                                        <th>Imported</th>
                                        <th>Anomalies</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($syncAttemptRows)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-3">No sync attempts yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($syncAttemptRows as $syncAttempt): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?php echo machine_h((string)($syncAttempt['status'] ?? '-')); ?></div>
                                                    <small class="text-muted"><?php echo machine_h((string)($syncAttempt['trigger_source'] ?? 'manual')); ?></small>
                                                </td>
                                                <td>
                                                    <div><?php echo machine_h((string)($syncAttempt['started_at'] ?? '-')); ?></div>
                                                    <small class="text-muted"><?php echo machine_h((string)($syncAttempt['finished_at'] ?? '')); ?></small>
                                                </td>
                                                <td><?php echo machine_h((string)($syncAttempt['processed_logs'] ?? 0)); ?> logs</td>
                                                <td><?php echo machine_h((string)($syncAttempt['anomalies_found'] ?? 0)); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="px-3 py-3 border-top text-muted fs-12">If these counts keep growing, run a sync, confirm the raw logs are stored here, then you can use "Clear Records" on the F20H to avoid filling the device.</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2">
                        <h6 class="card-title mb-0">Ingest Health Monitor</h6>
                        <button class="btn btn-sm btn-outline-secondary machine-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#machineIngestHealthCollapse" aria-expanded="false" aria-controls="machineIngestHealthCollapse">Toggle</button>
                    </div>
                    <div class="card-body p-0 collapse" id="machineIngestHealthCollapse">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Received</th>
                                        <th>Bridge Node</th>
                                        <th>Source IP</th>
                                        <th>Token</th>
                                        <th>HTTP</th>
                                        <th>Events</th>
                                        <th>Accepted</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentIngestEvents)): ?>
                                        <tr><td colspan="8" class="text-center text-muted py-3">No ingest requests captured yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($recentIngestEvents as $event): ?>
                                            <tr>
                                                <td><?php echo machine_h((string)($event['received_at'] ?? '-')); ?></td>
                                                <td><?php echo machine_h((string)($event['source_node'] ?? 'unknown-node')); ?></td>
                                                <td><?php echo machine_h((string)($event['source_ip'] ?? '-')); ?></td>
                                                <td><?php echo machine_h((string)($event['token_status'] ?? 'unknown')); ?></td>
                                                <td><?php echo machine_h((string)($event['http_status'] ?? 0)); ?></td>
                                                <td><?php echo machine_h((string)($event['events_received'] ?? 0)); ?></td>
                                                <td><?php echo machine_h((string)($event['events_accepted'] ?? 0)); ?></td>
                                                <td><?php echo machine_h((string)($event['note'] ?? '')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($isAdmin): ?>
                <div class="col-12 d-flex justify-content-end">
                    <button class="btn btn-sm btn-outline-secondary machine-section-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#machineAdminAdvancedPanels" aria-expanded="false" aria-controls="machineAdminAdvancedPanels">Toggle Admin Advanced Panels</button>
                </div>
                <div class="col-12 collapse" id="machineAdminAdvancedPanels">
                    <div class="row g-3">
                <div class="col-xl-6">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h6 class="card-title mb-0">Recent Anomalies</h6></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Fingerprint</th>
                                            <th>Matched Person</th>
                                            <th>Severity</th>
                                            <th>Message</th>
                                            <th>When</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentAnomalies)): ?>
                                            <tr><td colspan="6" class="text-center text-muted py-3">No recent anomalies.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recentAnomalies as $anomaly): ?>
                                                <tr>
                                                    <td><?php echo machine_h((string)$anomaly['anomaly_type']); ?></td>
                                                    <td><?php echo machine_h((string)($anomaly['fingerprint_id'] ?? '-')); ?></td>
                                                    <td>
                                                        <div class="fw-semibold"><?php echo machine_h(machine_person_label($anomaly)); ?></div>
                                                        <small class="text-muted">
                                                            <?php echo machine_h((string)($anomaly['mapped_user_name'] ?? '') !== '' ? ('User #' . (string)($anomaly['user_id'] ?? '-')) : ((string)($anomaly['student_id'] ?? '') !== '' ? ('Student row #' . (string)$anomaly['student_id']) : 'No BioTern match')); ?>
                                                        </small>
                                                    </td>
                                                    <td><?php echo machine_h((string)$anomaly['severity']); ?></td>
                                                    <td><?php echo machine_h((string)$anomaly['message']); ?></td>
                                                    <td><?php echo machine_h((string)($anomaly['event_time'] ?: $anomaly['created_at'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h6 class="card-title mb-0">Recent Audit Log</h6></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Actor</th>
                                            <th>Target</th>
                                            <th>When</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recentAuditLogs)): ?>
                                            <tr><td colspan="4" class="text-center text-muted py-3">No recent audit entries.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recentAuditLogs as $auditLog): ?>
                                                <tr>
                                                    <td><?php echo machine_h((string)$auditLog['action']); ?></td>
                                                    <td><?php echo machine_h((string)($auditLog['actor_role'] ?: 'system')); ?></td>
                                                    <td><?php echo machine_h((string)($auditLog['target_type'] . ($auditLog['target_id'] ? ' #' . $auditLog['target_id'] : ''))); ?></td>
                                                    <td><?php echo machine_h((string)$auditLog['created_at']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h6 class="card-title mb-0">Device Info and Raw Config</h6></div>
                        <div class="card-body">
                            <label class="form-label">Device Info</label>
                            <textarea class="form-control mb-3" rows="6" readonly><?php echo machine_h($deviceInfoRaw); ?></textarea>

                            <form method="post">
                                <input type="hidden" name="machine_action" value="save_config">
                                <label class="form-label">Config JSON</label>
                                <textarea name="config_json" class="form-control" rows="10"><?php echo machine_h($configRaw); ?></textarea>
                                <button type="submit" class="btn btn-primary mt-3">Save Config</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h6 class="card-title mb-0">Connector Defaults</h6></div>
                        <div class="card-body">
                            <p class="text-muted">These are the localhost-side connection settings BioTern uses when it talks to the F20H over LAN.</p>
                            <form method="post">
                                <input type="hidden" name="machine_action" value="save_connector_config">
                                <label class="form-label">Connector JSON</label>
                                <textarea name="connector_config_json" class="form-control" rows="10"><?php echo machine_h($machineConfigJson); ?></textarea>
                                <button type="submit" class="btn btn-outline-primary mt-3">Save Connector Config</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h6 class="card-title mb-0">Advanced Machine Controls</h6></div>
                        <div class="card-body">
                            <form method="post" class="row g-2 mb-4">
                                <input type="hidden" name="machine_action" value="save_device_identity">
                                <div class="col-sm-6">
                                    <label class="form-label">Device Number</label>
                                    <input type="number" name="device_number" class="form-control" min="1" placeholder="1">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Communication Password</label>
                                    <input type="text" name="communication_password" class="form-control" placeholder="0">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-outline-secondary">Save Device Identity</button>
                                </div>
                            </form>

                            <div class="d-flex flex-wrap gap-2">
                                <form method="post" data-confirm="Restart the F20H now?">
                                    <input type="hidden" name="machine_action" value="restart">
                                    <button type="submit" class="btn btn-outline-primary">Restart Machine</button>
                                </form>
                                <form method="post" data-confirm="Clear only attendance records from the F20H?">
                                    <input type="hidden" name="machine_action" value="clear_records">
                                    <button type="submit" class="btn btn-outline-warning">Clear Records</button>
                                </form>
                                <form method="post" data-confirm="Clear admin data on the F20H?">
                                    <input type="hidden" name="machine_action" value="clear_admin">
                                    <button type="submit" class="btn btn-outline-warning">Clear Admin</button>
                                </form>
                                <form method="post" data-confirm="Clear all users from the F20H? This removes the machine user list.">
                                    <input type="hidden" name="machine_action" value="clear_users">
                                    <button type="submit" class="btn btn-outline-danger">Clear All Users</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
</div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
