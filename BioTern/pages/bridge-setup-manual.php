<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}

function bridge_manual_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bridge_manual_get_profile(mysqli $conn): array
{
    $defaults = [
        'bridge_token' => '',
        'cloud_base_url' => '',
        'ip_address' => '',
        'gateway' => '',
        'mask' => '255.255.255.0',
        'port' => '5001',
        'device_number' => '1',
        'poll_seconds' => '5',
    ];

    $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_profile (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        profile_name VARCHAR(100) NOT NULL DEFAULT 'default',
        selected_bridge_preset VARCHAR(100) NOT NULL DEFAULT 'laptop_custom',
        router_name VARCHAR(150) NOT NULL DEFAULT '',
        bridge_name VARCHAR(150) NOT NULL DEFAULT '',
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
        UNIQUE KEY uq_profile_name (profile_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $res = $conn->query("SELECT bridge_token, cloud_base_url, ip_address, gateway, mask, port, device_number, poll_seconds FROM biometric_bridge_profile WHERE profile_name = 'default' LIMIT 1");
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc() ?: [];
        $res->close();
        if ($row !== []) {
            return array_merge($defaults, $row);
        }
    }

    return $defaults;
}

function bridge_manual_send_file(string $absolutePath, string $downloadName): void
{
    if (!is_file($absolutePath)) {
        http_response_code(404);
        echo 'Requested file not found: ' . bridge_manual_h($downloadName);
        exit;
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
    header('Content-Length: ' . (string)filesize($absolutePath));
    readfile($absolutePath);
    exit;
}

function bridge_manual_send_kit_zip(string $workspaceRoot): void
{
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo 'ZipArchive is not available on this server. Please download files individually.';
        exit;
    }

    $zipPath = tempnam(sys_get_temp_dir(), 'biotern_bridge_');
    if ($zipPath === false) {
        throw new RuntimeException('Unable to allocate temporary zip path.');
    }

    $finalZipPath = $zipPath . '.zip';
    @unlink($finalZipPath);

    $zip = new ZipArchive();
    if ($zip->open($finalZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($zipPath);
        throw new RuntimeException('Unable to create bridge kit zip archive.');
    }

    $files = [
        'tools/bridge-worker.ps1',
        'tools/bridge-worker-autostart.ps1',
        'tools/install-bridge-worker-task.ps1',
        'tools/manage-bridge-worker-task.ps1',
        'tools/restart-bridge-worker.ps1',
        'tools/bridge-profile-cache.json',
        'tools/device_connector/bin/Release/net9.0-windows/BioTernMachineConnector.exe',
        'tools/device_connector/bin/Release/net9.0-windows/BioTernMachineConnector.dll',
        'tools/device_connector/bin/Release/net9.0-windows/BioTernMachineConnector.deps.json',
        'tools/device_connector/bin/Release/net9.0-windows/BioTernMachineConnector.runtimeconfig.json',
        'tools/device_connector/bin/Release/net9.0-windows/DevCtrl.dll',
    ];

    foreach ($files as $relative) {
        $abs = $workspaceRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($abs)) {
            $zip->addFile($abs, 'BioTernBridgeKit/' . $relative);
        }
    }

    $readme = "BioTern Bridge Kit\r\n"
        . "=================\r\n\r\n"
        . "DETAILED SETUP\r\n"
        . "--------------\r\n"
        . "1) Extract this ZIP on a Windows PC connected to the same LAN as your F20H.\r\n"
        . "2) Open the extracted BioTernBridgeKit folder. Confirm these exist:\r\n"
        . "   - tools\\install-bridge-worker-task.ps1\r\n"
        . "   - tools\\manage-bridge-worker-task.ps1\r\n"
        . "   - tools\\bridge-worker.ps1\r\n"
        . "   - tools\\bridge-worker-autostart.ps1\r\n"
        . "   - tools\\device_connector\\bin\\Release\\net9.0-windows\\BioTernMachineConnector.exe\r\n"
        . "3) In website Machine Manager, save Bridge Profile first (token, URL, F20H IP, gateway).\r\n"
        . "4) In the extracted folder, open PowerShell and run the install command from Bridge Setup Manual page.\r\n"
        . "5) Check status: powershell -NoProfile -ExecutionPolicy Bypass -File .\\tools\\manage-bridge-worker-task.ps1 -Action status -TaskName BioTernBridgeWorker\r\n"
        . "6) Confirm State = Running.\r\n"
        . "7) Confirm BridgeHealth = ONLINE or LIKELY ONLINE and BridgeLogAgeSeconds is small (recent).\r\n"
        . "8) Optional: reboot or sign out/in, then run the status command again to confirm auto-start.\r\n"
        . "9) If status is Running, open website and click Read All Users / Process Ingest Queue.\r\n"
        . "10) Keep bridge account signed in for user-logon mode task execution.\r\n\r\n"
        . "TROUBLESHOOTING\r\n"
        . "---------------\r\n"
        . "- If scripts are blocked: run PowerShell as current user and set process policy only:\r\n"
        . "  Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force\r\n"
        . "- If machine not reachable: verify F20H IP/gateway and laptop network are on same router/subnet.\r\n"
        . "- If domain changes (e.g. ClarkCollege.edu.ph): re-run install with new SiteBaseUrl.\r\n";
    $zip->addFromString('BioTernBridgeKit/README-SETUP.txt', $readme);
    $zip->close();

    @unlink($zipPath);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="BioTernBridgeKit.zip"');
    header('Content-Length: ' . (string)filesize($finalZipPath));
    readfile($finalZipPath);
    @unlink($finalZipPath);
    exit;
}

function bridge_manual_is_local_windows_runtime(): bool
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
    $isLocalHost = $host === ''
        || preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/', $host);

    return stripos(PHP_OS_FAMILY, 'Windows') === 0 && (bool)$isLocalHost;
}

function bridge_manual_run_powershell(array $arguments, int $timeoutSeconds = 90): array
{
    if (!bridge_manual_is_local_windows_runtime()) {
        throw new RuntimeException('One-click bridge repair can only run from the local Windows/XAMPP bridge computer.');
    }

    $escaped = array_map('escapeshellarg', $arguments);
    $command = 'powershell.exe ' . implode(' ', $escaped);
    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__));
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start PowerShell.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $startedAt = time();
    while (true) {
        $stdout .= (string)stream_get_contents($pipes[1]);
        $stderr .= (string)stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (empty($status['running'])) {
            break;
        }
        if ((time() - $startedAt) > $timeoutSeconds) {
            proc_terminate($process);
            throw new RuntimeException('PowerShell command timed out while repairing bridge task.');
        }
        usleep(150000);
    }

    $stdout .= (string)stream_get_contents($pipes[1]);
    $stderr .= (string)stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exit_code' => $exitCode,
        'output' => trim($stdout . ($stderr !== '' ? "\n" . $stderr : '')),
    ];
}

$workspaceRoot = dirname(__DIR__);
$download = trim((string)($_GET['download'] ?? ''));
if ($download !== '') {
    switch ($download) {
        case 'bridge-kit':
            bridge_manual_send_kit_zip($workspaceRoot);
            break;
        case 'install-script':
            bridge_manual_send_file($workspaceRoot . '/tools/install-bridge-worker-task.ps1', 'install-bridge-worker-task.ps1');
            break;
        case 'manage-script':
            bridge_manual_send_file($workspaceRoot . '/tools/manage-bridge-worker-task.ps1', 'manage-bridge-worker-task.ps1');
            break;
        case 'worker-script':
            bridge_manual_send_file($workspaceRoot . '/tools/bridge-worker.ps1', 'bridge-worker.ps1');
            break;
        case 'autostart-script':
            bridge_manual_send_file($workspaceRoot . '/tools/bridge-worker-autostart.ps1', 'bridge-worker-autostart.ps1');
            break;
        default:
            http_response_code(400);
            echo 'Unknown download option.';
            exit;
    }
}

$profile = bridge_manual_get_profile($conn);
$baseUrl = trim((string)($profile['cloud_base_url'] ?? ''));
if ($baseUrl === '') {
    $appUrl = getenv('APP_URL');
    if (is_string($appUrl) && trim($appUrl) !== '') {
        $baseUrl = rtrim(trim($appUrl), '/');
    }
}
if ($baseUrl === '') {
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '') {
        $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $host;
    }
}
if ($baseUrl === '') {
    $baseUrl = 'https://biotern.vercel.app';
}
if (stripos($baseUrl, 'http://') === 0) {
    $baseUrl = 'https://' . substr($baseUrl, 7);
}

$bridgeToken = trim((string)($profile['bridge_token'] ?? ''));
if ($bridgeToken === '') {
    $bridgeToken = 'YOUR_BRIDGE_TOKEN';
}

$installCommand = 'powershell -NoProfile -ExecutionPolicy Bypass -File ".\\tools\\install-bridge-worker-task.ps1"'
    . ' -SiteBaseUrl "' . str_replace('"', '\\"', $baseUrl) . '"'
    . ' -BridgeToken "' . str_replace('"', '\\"', $bridgeToken) . '"'
    . ' -TaskName "BioTernBridgeWorker"'
    . ' -PreferLocalConnectorNetwork 0';

$statusCommand = 'powershell -NoProfile -ExecutionPolicy Bypass -File ".\\tools\\manage-bridge-worker-task.ps1" -Action status -TaskName "BioTernBridgeWorker"';
$restartCommand = 'powershell -NoProfile -ExecutionPolicy Bypass -File ".\\tools\\manage-bridge-worker-task.ps1" -Action restart -TaskName "BioTernBridgeWorker"';
$execPolicyCommand = 'Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass -Force';
$openFolderHint = 'Open the extracted folder that contains the tools folder, then open PowerShell in that folder.';
$bridgeManualFlash = $_SESSION['bridge_manual_flash'] ?? null;
unset($_SESSION['bridge_manual_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manualAction = trim((string)($_POST['bridge_manual_action'] ?? ''));
    try {
        if ($manualAction === 'repair_bridge_now') {
            if ($bridgeToken === '' || $bridgeToken === 'YOUR_BRIDGE_TOKEN') {
                throw new RuntimeException('Bridge token is missing. Save the Bridge Profile first, then try repair again.');
            }

            $result = bridge_manual_run_powershell([
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $workspaceRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'install-bridge-worker-task.ps1',
                '-SiteBaseUrl',
                $baseUrl,
                '-BridgeToken',
                $bridgeToken,
                '-TaskName',
                'BioTernBridgeWorker',
                '-PreferLocalConnectorNetwork',
                '0',
            ]);

            if ((int)$result['exit_code'] !== 0) {
                throw new RuntimeException($result['output'] !== '' ? $result['output'] : 'Bridge repair command failed.');
            }

            $_SESSION['bridge_manual_flash'] = [
                'type' => 'success',
                'message' => 'Bridge auto-start task repaired and started. ' . trim((string)$result['output']),
            ];
        } elseif ($manualAction === 'restart_bridge_now') {
            $result = bridge_manual_run_powershell([
                '-NoProfile',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $workspaceRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'manage-bridge-worker-task.ps1',
                '-Action',
                'restart',
                '-TaskName',
                'BioTernBridgeWorker',
            ]);

            if ((int)$result['exit_code'] !== 0) {
                throw new RuntimeException($result['output'] !== '' ? $result['output'] : 'Bridge restart command failed.');
            }

            $_SESSION['bridge_manual_flash'] = [
                'type' => 'success',
                'message' => 'Bridge worker restarted. ' . trim((string)$result['output']),
            ];
        }
    } catch (Throwable $e) {
        $_SESSION['bridge_manual_flash'] = [
            'type' => 'danger',
            'message' => $e->getMessage(),
        ];
    }

    header('Location: bridge-setup-manual.php');
    exit;
}

$page_title = 'BioTern || Bridge Setup Manual';
$page_body_class = 'page-bridge-setup-manual';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/pages/page-biometric-console.css',
    'assets/css/modules/pages/page-biometric-machine.css',
];
$page_scripts = [];
include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Bridge Setup Manual</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Bridge Setup Manual</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto">
                <a href="biometric-machine.php" class="btn btn-outline-secondary">
                    <i class="feather-cpu me-2"></i>
                    <span>Open F20H Machine Manager</span>
                </a>
            </div>
        </div>

        <?php if (is_array($bridgeManualFlash)): ?>
            <div class="alert alert-<?php echo bridge_manual_h((string)($bridgeManualFlash['type'] ?? 'info')); ?> alert-dismissible fade show" role="alert">
                <?php echo nl2br(bridge_manual_h((string)($bridgeManualFlash['message'] ?? ''))); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card bio-console-hero mb-3">
            <div class="card-body">
                <div class="bio-console-hero-grid">
                    <div>
                        <span class="bio-console-eyebrow">Deployment Guide</span>
                        <h3>Set up a new bridge PC in minutes</h3>
                        <p>Use this page as your installation manual when switching to a new laptop/computer or router. Download the kit, run one install command, and verify the scheduled auto-start task is running.</p>
                        <div class="bio-console-pill-list">
                            <span class="bio-console-pill">Cloud URL: <?php echo bridge_manual_h($baseUrl); ?></span>
                            <span class="bio-console-pill">Bridge Token: <?php echo bridge_manual_h($bridgeToken); ?></span>
                            <span class="bio-console-pill">F20H IP: <?php echo bridge_manual_h((string)($profile['ip_address'] ?? '-')); ?></span>
                        </div>
                    </div>
                    <div class="bio-console-hero-side machine-hero-status">
                        <h6>Requirements</h6>
                        <p class="mb-2">Windows PC in same LAN as F20H, internet access, and permission to run PowerShell scripts.</p>
                        <small>Tip: user-logon task mode works even without admin rights; keep the bridge account signed in.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Zero-Miss Walkthrough (Follow Exactly)</h6></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Open <strong>F20H Machine Manager</strong> and save Bridge Profile first: Bridge Token, Cloud URL, F20H IP, Gateway, Mask, Port, Device Number.</li>
                            <li>Click <strong>Download Full Bridge Kit (ZIP)</strong> on this page.</li>
                            <li>Go to your Downloads folder, right click ZIP, choose <strong>Extract All</strong>.</li>
                            <li>Open extracted folder then open <strong>BioTernBridgeKit</strong>.</li>
                            <li>Inside that folder, make sure there is a <strong>tools</strong> folder and connector EXE file.</li>
                            <li>Choose installation method below: Method A (right click script) or Method B (PowerShell command).</li>
                            <li>After install, run <strong>Status</strong> command and confirm task state is <strong>Running</strong>.</li>
                            <li>Go back to F20H Machine Manager and click <strong>Read All Users</strong> and <strong>Process Ingest Queue</strong>.</li>
                            <li>If task is not running, use <strong>Restart</strong> command then check status again.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Step 1: Download Needed Files</h6></div>
                    <div class="card-body">
                        <p class="text-muted">Download everything for a new bridge PC. Full kit is recommended.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="bridge-setup-manual.php?download=bridge-kit" class="btn btn-primary">Download Full Bridge Kit (ZIP)</a>
                            <a href="bridge-setup-manual.php?download=install-script" class="btn btn-outline-secondary">Install Script</a>
                            <a href="bridge-setup-manual.php?download=manage-script" class="btn btn-outline-secondary">Manage Script</a>
                            <a href="bridge-setup-manual.php?download=worker-script" class="btn btn-outline-secondary">Worker Script</a>
                            <a href="bridge-setup-manual.php?download=autostart-script" class="btn btn-outline-secondary">Auto-Start Script</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Step 2: After Extracting ZIP (Important)</h6></div>
                    <div class="card-body">
                        <ol class="mb-3">
                            <li>Right click the ZIP and choose <strong>Extract All</strong>.</li>
                            <li>Open the extracted folder and then open the inner <strong>BioTernBridgeKit</strong> folder.</li>
                            <li>Confirm these files exist before running commands:</li>
                        </ol>
                        <div class="border rounded p-3 bg-light mb-3">
                            <div>tools\install-bridge-worker-task.ps1</div>
                            <div>tools\manage-bridge-worker-task.ps1</div>
                            <div>tools\bridge-worker.ps1</div>
                            <div>tools\bridge-worker-autostart.ps1</div>
                            <div>tools\device_connector\bin\Release\net9.0-windows\BioTernMachineConnector.exe</div>
                        </div>
                        <p class="text-muted mb-0"><?php echo bridge_manual_h($openFolderHint); ?></p>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Step 3: Install Bridge Auto-Start</h6></div>
                    <div class="card-body">
                        <div class="alert alert-light border mb-3">
                            <div class="fw-semibold mb-1">Method A: Right click script (quick)</div>
                            <ol class="mb-0">
                                <li>Open extracted <strong>BioTernBridgeKit</strong> folder.</li>
                                <li>Open <strong>tools</strong> folder.</li>
                                <li>Right click <strong>install-bridge-worker-task.ps1</strong>.</li>
                                <li>Click <strong>Run with PowerShell</strong>.</li>
                                <li>If a security prompt appears, allow/Run anyway.</li>
                                <li>If it closes too quickly or fails, use Method B below.</li>
                            </ol>
                        </div>

                        <div class="alert alert-light border mb-3">
                            <div class="fw-semibold mb-1">Method B: PowerShell command (recommended)</div>
                            <ol class="mb-0">
                                <li>Inside extracted <strong>BioTernBridgeKit</strong> folder, hold <strong>Shift</strong> and right click empty space.</li>
                                <li>Choose <strong>Open PowerShell window here</strong> (or open terminal in current folder).</li>
                                <li>If scripts are blocked, run the execution-policy command below first.</li>
                                <li>Run install command from this page.</li>
                                <li>Run status command and verify task is Running.</li>
                            </ol>
                        </div>

                        <label class="form-label">Run this first if scripts are blocked</label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="bridgeManualExecPolicyCmd" value="<?php echo bridge_manual_h($execPolicyCommand); ?>" readonly>
                            <button type="button" class="btn btn-outline-secondary" data-copy-target="bridgeManualExecPolicyCmd">Copy</button>
                        </div>
                        <small class="text-muted d-block mb-3">Run only in current PowerShell window. It does not permanently change policy.</small>

                        <label class="form-label">Run once on bridge PC</label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="bridgeManualInstallCmd" value="<?php echo bridge_manual_h($installCommand); ?>" readonly>
                            <button type="button" class="btn btn-outline-secondary" data-copy-target="bridgeManualInstallCmd">Copy</button>
                        </div>
                        <small class="text-muted d-block">This installs and starts BioTernBridgeWorker as a scheduled background task that auto-starts the PowerShell bridge worker at startup/logon, runs forever, and auto-recovers if it stops.</small>
                        <small class="text-muted">If right-click method does not pass parameters correctly on your PC, use this command method.</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Step 4: Verify and Maintain</h6></div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <form method="post" data-confirm="Repair and start the BioTern bridge task on this Windows computer?">
                                <input type="hidden" name="bridge_manual_action" value="repair_bridge_now">
                                <button type="submit" class="btn btn-primary">Repair Bridge Now</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="bridge_manual_action" value="restart_bridge_now">
                                <button type="submit" class="btn btn-outline-primary">Restart Bridge Now</button>
                            </form>
                        </div>
                        <small class="text-muted d-block mb-3">These buttons work only when this page is opened from the local Windows/XAMPP bridge computer.</small>

                        <label class="form-label">Check status anytime</label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="bridgeManualStatusCmd" value="<?php echo bridge_manual_h($statusCommand); ?>" readonly>
                            <button type="button" class="btn btn-outline-secondary" data-copy-target="bridgeManualStatusCmd">Copy</button>
                        </div>
                        <label class="form-label">Restart worker if needed</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="bridgeManualRestartCmd" value="<?php echo bridge_manual_h($restartCommand); ?>" readonly>
                            <button type="button" class="btn btn-outline-secondary" data-copy-target="bridgeManualRestartCmd">Copy</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Step 5: Full Checklist</h6></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Open website and save Bridge Profile first (URL/token/F20H network).</li>
                            <li>Extract Bridge Kit on new Windows PC.</li>
                            <li>Open extracted folder, confirm required files are present.</li>
                            <li>Open PowerShell in the extracted folder (Shift + right click &gt; Open PowerShell window here).</li>
                            <li>If PowerShell blocks scripts, run temporary execution policy command.</li>
                            <li>Run install command from this page once (installs the scheduled task).</li>
                            <li>Run status command and confirm task is Running.</li>
                            <li>Confirm BridgeHealth shows ONLINE or LIKELY ONLINE in the status output.</li>
                            <li>Confirm BridgeLogAgeSeconds is small (recent log activity).</li>
                            <li>Optional verification: reboot or sign out/in, then run status again to confirm it auto-starts.</li>
                            <li>Go to F20H Machine Manager and click Read All Users / Process Ingest Queue.</li>
                            <li>If changing to a new domain (e.g. ClarkCollege.edu.ph), re-run install using new SiteBaseUrl.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Troubleshooting (If You Get Stuck)</h6></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>If command says script is blocked: run the execution policy command above, then retry install.</li>
                            <li>If status is not Running: run Restart command, then check status again.</li>
                            <li>If bridge says device connection failed: verify F20H IP/gateway in Machine Manager matches current router.</li>
                            <li>If using a new website domain: update Bridge Profile cloud URL, then re-run install command.</li>
                            <li>If still failing: open F20H Machine Manager and check Bridge Worker Status detail text for exact error.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
(function () {
    var buttons = document.querySelectorAll('[data-copy-target]');
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-copy-target');
            var input = document.getElementById(targetId);
            if (!input) {
                return;
            }
            input.select();
            input.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1200);
            } catch (err) {
                btn.textContent = 'Copy failed';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1400);
            }
        });
    });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
