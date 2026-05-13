<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}
$isAdmin = ($role === 'admin');

function fleet_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fleet_redirect(): void
{
    header('Location: biometric-fleet.php');
    exit;
}

function fleet_ensure_schema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_heartbeat (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        node_name VARCHAR(120) NOT NULL DEFAULT '',
        status_text VARCHAR(255) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_node_name (node_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS biometric_ingest_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        source_ip VARCHAR(64) NOT NULL DEFAULT '',
        source_node VARCHAR(120) NOT NULL DEFAULT '',
        token_status VARCHAR(40) NOT NULL DEFAULT '',
        http_status INT NOT NULL DEFAULT 0,
        events_received INT NOT NULL DEFAULT 0,
        events_accepted INT NOT NULL DEFAULT 0,
        note VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (id),
        KEY idx_source_node (source_node),
        KEY idx_received_at (received_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("CREATE TABLE IF NOT EXISTS biometric_machines (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        machine_code VARCHAR(80) NOT NULL,
        machine_name VARCHAR(160) NOT NULL,
        floor_label VARCHAR(80) NOT NULL DEFAULT '',
        machine_model VARCHAR(120) NOT NULL DEFAULT '',
        firmware_version VARCHAR(120) NOT NULL DEFAULT '',
        ip_address VARCHAR(80) NOT NULL DEFAULT '',
        port INT NOT NULL DEFAULT 5001,
        device_number INT NOT NULL DEFAULT 1,
        bridge_node VARCHAR(120) NOT NULL DEFAULT '',
        ingest_token_hash VARCHAR(255) NOT NULL DEFAULT '',
        sync_mode VARCHAR(30) NOT NULL DEFAULT 'bridge',
        status VARCHAR(30) NOT NULL DEFAULT 'not_configured',
        last_seen_at DATETIME NULL,
        last_sync_at DATETIME NULL,
        notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_machine_code (machine_code),
        KEY idx_machine_status (status),
        KEY idx_bridge_node (bridge_node)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $columns = [];
    $res = $conn->query("SHOW COLUMNS FROM biometric_machines");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $columns[strtolower((string)($row['Field'] ?? ''))] = true;
        }
        $res->close();
    }
    if (!isset($columns['ingest_token_hash'])) {
        $conn->query("ALTER TABLE biometric_machines ADD COLUMN ingest_token_hash VARCHAR(255) NOT NULL DEFAULT '' AFTER bridge_node");
    }
    if (!isset($columns['last_seen_at'])) {
        $conn->query("ALTER TABLE biometric_machines ADD COLUMN last_seen_at DATETIME NULL AFTER status");
    }
    if (!isset($columns['last_sync_at'])) {
        $conn->query("ALTER TABLE biometric_machines ADD COLUMN last_sync_at DATETIME NULL AFTER last_seen_at");
    }
}

fleet_ensure_schema($conn);
$fleetTokenNotice = '';

function fleet_generate_ingest_token(): string
{
    try {
        return 'btm_' . bin2hex(random_bytes(24));
    } catch (Throwable $e) {
        return 'btm_' . hash('sha256', uniqid('machine-token-', true));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $action = strtolower(trim((string)($_POST['fleet_action'] ?? '')));
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare("UPDATE biometric_machines SET deleted_at = NOW(), status = 'retired' WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        fleet_redirect();
    }

    if ($action === 'save') {
        $machineCode = strtoupper(trim((string)($_POST['machine_code'] ?? '')));
        $machineName = trim((string)($_POST['machine_name'] ?? ''));
        $floorLabel = trim((string)($_POST['floor_label'] ?? ''));
        $machineModel = trim((string)($_POST['machine_model'] ?? ''));
        $firmwareVersion = trim((string)($_POST['firmware_version'] ?? ''));
        $ipAddress = trim((string)($_POST['ip_address'] ?? ''));
        $port = max(1, (int)($_POST['port'] ?? 5001));
        $deviceNumber = max(1, (int)($_POST['device_number'] ?? 1));
        $bridgeNode = trim((string)($_POST['bridge_node'] ?? ''));
        $syncMode = strtolower(trim((string)($_POST['sync_mode'] ?? 'bridge')));
        $status = strtolower(trim((string)($_POST['status'] ?? 'not_configured')));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $allowedStatuses = ['active', 'not_working', 'not_syncing', 'not_configured', 'maintenance', 'retired'];
        $status = in_array($status, $allowedStatuses, true) ? $status : 'not_configured';
        $syncMode = in_array($syncMode, ['bridge', 'api', 'manual_import'], true) ? $syncMode : 'bridge';
        $rotateToken = !empty($_POST['rotate_ingest_token']);
        $newToken = $rotateToken ? fleet_generate_ingest_token() : '';
        $newTokenHash = $newToken !== '' ? password_hash($newToken, PASSWORD_DEFAULT) : '';

        if ($machineCode !== '' && $machineName !== '') {
            if ($id > 0) {
                $tokenSql = $newTokenHash !== '' ? ", ingest_token_hash = ?" : "";
                $stmt = $conn->prepare("UPDATE biometric_machines SET machine_code = ?, machine_name = ?, floor_label = ?, machine_model = ?, firmware_version = ?, ip_address = ?, port = ?, device_number = ?, bridge_node = ?, sync_mode = ?, status = ?, notes = ?{$tokenSql} WHERE id = ? LIMIT 1");
                if ($stmt) {
                    if ($newTokenHash !== '') {
                        $stmt->bind_param('ssssssiisssssi', $machineCode, $machineName, $floorLabel, $machineModel, $firmwareVersion, $ipAddress, $port, $deviceNumber, $bridgeNode, $syncMode, $status, $notes, $newTokenHash, $id);
                    } else {
                        $stmt->bind_param('ssssssiissssi', $machineCode, $machineName, $floorLabel, $machineModel, $firmwareVersion, $ipAddress, $port, $deviceNumber, $bridgeNode, $syncMode, $status, $notes, $id);
                    }
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                if ($newTokenHash === '') {
                    $newToken = fleet_generate_ingest_token();
                    $newTokenHash = password_hash($newToken, PASSWORD_DEFAULT);
                }
                $stmt = $conn->prepare("INSERT INTO biometric_machines (machine_code, machine_name, floor_label, machine_model, firmware_version, ip_address, port, device_number, bridge_node, sync_mode, status, notes, ingest_token_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('ssssssiisssss', $machineCode, $machineName, $floorLabel, $machineModel, $firmwareVersion, $ipAddress, $port, $deviceNumber, $bridgeNode, $syncMode, $status, $notes, $newTokenHash);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            if ($newToken !== '') {
                $_SESSION['fleet_token_notice'] = 'New ingest token for ' . $machineCode . ': ' . $newToken . ' Save it in that floor bridge only; BioTern stores only the hash.';
            }
        }
        fleet_redirect();
    }
}

$fleetTokenNotice = (string)($_SESSION['fleet_token_notice'] ?? '');
unset($_SESSION['fleet_token_notice']);

$machines = [];
$res = $conn->query("SELECT bm.*,
        hb.updated_at AS heartbeat_at,
        hb.status_text AS heartbeat_text,
        (SELECT MAX(received_at) FROM biometric_ingest_events bie WHERE bie.source_node = bm.bridge_node) AS ingest_at
    FROM biometric_machines bm
    LEFT JOIN biometric_bridge_heartbeat hb ON hb.node_name = bm.bridge_node
    WHERE bm.deleted_at IS NULL
    ORDER BY FIELD(bm.status, 'active', 'not_syncing', 'not_working', 'not_configured', 'maintenance', 'retired'), bm.floor_label ASC, bm.machine_name ASC");
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $machines[] = $row;
    }
    $res->close();
}

$counts = ['active' => 0, 'not_working' => 0, 'not_syncing' => 0, 'not_configured' => 0];
foreach ($machines as $machine) {
    $status = (string)($machine['status'] ?? 'not_configured');
    if (isset($counts[$status])) {
        $counts[$status]++;
    }
}

$page_title = 'Biometric Machine Fleet';
include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title"><h5 class="m-b-10">Biometric Machine Fleet</h5></div>
                <ul class="breadcrumb"><li class="breadcrumb-item"><a href="homepage.php">Home</a></li><li class="breadcrumb-item">Machine Fleet</li></ul>
            </div>
            <div class="page-header-right ms-auto d-flex gap-2">
                <a href="biometric-machine.php" class="btn btn-outline-secondary">F20H Manager</a>
                <a href="biometric-machine-bridge2.php" class="btn btn-outline-secondary">Bridge 2 Manager</a>
            </div>
        </div>
        <div class="main-content">
            <?php if ($fleetTokenNotice !== ''): ?>
                <div class="alert alert-warning">
                    <?php echo fleet_h($fleetTokenNotice); ?>
                </div>
            <?php endif; ?>
            <div class="row g-3 mb-3">
                <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted fs-12">Active</div><div class="h4 mb-0"><?php echo (int)$counts['active']; ?></div></div></div></div>
                <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted fs-12">Not Syncing</div><div class="h4 mb-0"><?php echo (int)$counts['not_syncing']; ?></div></div></div></div>
                <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted fs-12">Not Working</div><div class="h4 mb-0"><?php echo (int)$counts['not_working']; ?></div></div></div></div>
                <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted fs-12">Not Configured</div><div class="h4 mb-0"><?php echo (int)$counts['not_configured']; ?></div></div></div></div>
            </div>
            <div class="row g-3">
                <?php if ($isAdmin): ?>
                <div class="col-xl-4">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h5 class="card-title mb-0">Register Machine</h5></div>
                        <div class="card-body">
                            <form method="post" class="row g-3" id="fleetForm">
                                <input type="hidden" name="fleet_action" value="save">
                                <input type="hidden" name="id" id="fleet_id" value="0">
                                <div class="col-sm-6"><label class="form-label">Code</label><input name="machine_code" id="fleet_machine_code" class="form-control" placeholder="F1-A" required></div>
                                <div class="col-sm-6"><label class="form-label">Floor</label><input name="floor_label" id="fleet_floor_label" class="form-control" placeholder="1st Floor"></div>
                                <div class="col-12"><label class="form-label">Machine Name</label><input name="machine_name" id="fleet_machine_name" class="form-control" placeholder="Main lobby machine" required></div>
                                <div class="col-sm-6"><label class="form-label">Model</label><input name="machine_model" id="fleet_machine_model" class="form-control" placeholder="F20H / ZKTeco"></div>
                                <div class="col-sm-6"><label class="form-label">Firmware</label><input name="firmware_version" id="fleet_firmware_version" class="form-control"></div>
                                <div class="col-sm-6"><label class="form-label">IP Address</label><input name="ip_address" id="fleet_ip_address" class="form-control"></div>
                                <div class="col-sm-3"><label class="form-label">Port</label><input type="number" name="port" id="fleet_port" class="form-control" value="5001"></div>
                                <div class="col-sm-3"><label class="form-label">Device #</label><input type="number" name="device_number" id="fleet_device_number" class="form-control" value="1"></div>
                                <div class="col-12"><label class="form-label">Bridge Node</label><input name="bridge_node" id="fleet_bridge_node" class="form-control" placeholder="floor-1-bridge"></div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="rotate_ingest_token" id="fleet_rotate_ingest_token">
                                        <label class="form-check-label" for="fleet_rotate_ingest_token">Generate new ingest token</label>
                                    </div>
                                    <small class="text-muted">New machines get a token automatically. Existing tokens are shown only once when regenerated.</small>
                                </div>
                                <div class="col-sm-6"><label class="form-label">Sync Mode</label><select name="sync_mode" id="fleet_sync_mode" class="form-select"><option value="bridge">Bridge</option><option value="api">API</option><option value="manual_import">Manual Import</option></select></div>
                                <div class="col-sm-6"><label class="form-label">Status</label><select name="status" id="fleet_status" class="form-select"><option value="active">Active</option><option value="not_syncing">Not Syncing</option><option value="not_working">Not Working</option><option value="not_configured">Not Configured</option><option value="maintenance">Maintenance</option><option value="retired">Retired</option></select></div>
                                <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" id="fleet_notes" class="form-control" rows="3"></textarea></div>
                                <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Save Machine</button><button class="btn btn-outline-secondary" type="button" id="fleetResetButton">New</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="<?php echo $isAdmin ? 'col-xl-8' : 'col-12'; ?>">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h5 class="card-title mb-0">Configured Machines</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead><tr><th>Machine</th><th>Network</th><th>Status</th><th>Last Activity</th><th>Config</th><?php if ($isAdmin): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead>
                                    <tbody>
                                    <?php if ($machines === []): ?><tr><td colspan="<?php echo $isAdmin ? 6 : 5; ?>" class="text-center text-muted py-5">No machines registered yet.</td></tr><?php endif; ?>
                                    <?php foreach ($machines as $machine): ?>
                                        <?php
                                        $status = (string)($machine['status'] ?? 'not_configured');
                                        $statusClass = match ($status) {
                                            'active' => 'success',
                                            'not_syncing', 'maintenance' => 'warning',
                                            'not_working' => 'danger',
                                            default => 'secondary',
                                        };
                                        $lastActivity = max(strtotime((string)($machine['heartbeat_at'] ?? '')) ?: 0, strtotime((string)($machine['ingest_at'] ?? '')) ?: 0, strtotime((string)($machine['last_sync_at'] ?? '')) ?: 0);
                                        $payload = json_encode($machine, JSON_UNESCAPED_SLASHES);
                                        ?>
                                        <tr>
                                            <td><div class="fw-bold"><?php echo fleet_h($machine['machine_name']); ?> <span class="badge bg-soft-primary text-primary"><?php echo fleet_h($machine['machine_code']); ?></span></div><small class="text-muted"><?php echo fleet_h(($machine['floor_label'] ?: 'No floor') . ' | ' . ($machine['machine_model'] ?: 'Unknown model')); ?></small></td>
                                            <td><?php echo fleet_h($machine['ip_address'] ?: '-'); ?>:<?php echo (int)$machine['port']; ?><div class="fs-12 text-muted">Node: <?php echo fleet_h($machine['bridge_node'] ?: '-'); ?></div></td>
                                            <td><span class="badge bg-soft-<?php echo fleet_h($statusClass); ?> text-<?php echo fleet_h($statusClass); ?>"><?php echo fleet_h(ucwords(str_replace('_', ' ', $status))); ?></span></td>
                                            <td><?php echo $lastActivity > 0 ? fleet_h(date('Y-m-d H:i:s', $lastActivity)) : '<span class="text-muted">No activity yet</span>'; ?><div class="fs-12 text-muted"><?php echo fleet_h($machine['heartbeat_text'] ?: 'No heartbeat text'); ?></div></td>
                                            <td><span class="badge bg-soft-info text-info"><?php echo fleet_h(ucwords(str_replace('_', ' ', (string)$machine['sync_mode']))); ?></span><div class="fs-12 text-muted">Device #<?php echo (int)$machine['device_number']; ?></div></td>
                                            <?php if ($isAdmin): ?>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-fleet-edit='<?php echo fleet_h((string)$payload); ?>'>Edit</button>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this machine profile?');">
                                                    <input type="hidden" name="fleet_action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$machine['id']; ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                </form>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php if ($isAdmin): ?>
<script>
(function () {
    function setValue(id, value) {
        var input = document.getElementById(id);
        if (input) input.value = value || '';
    }
    document.querySelectorAll('[data-fleet-edit]').forEach(function (button) {
        button.addEventListener('click', function () {
            var data = JSON.parse(button.getAttribute('data-fleet-edit') || '{}');
            ['id','machine_code','machine_name','floor_label','machine_model','firmware_version','ip_address','port','device_number','bridge_node','sync_mode','status','notes'].forEach(function (key) {
                setValue('fleet_' + key, data[key]);
            });
            document.getElementById('fleetForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
    var reset = document.getElementById('fleetResetButton');
    var form = document.getElementById('fleetForm');
    if (reset && form) {
        reset.addEventListener('click', function () {
            form.reset();
            setValue('fleet_id', '0');
            setValue('fleet_port', '5001');
            setValue('fleet_device_number', '1');
            var rotate = document.getElementById('fleet_rotate_ingest_token');
            if (rotate) rotate.checked = false;
        });
    }
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
