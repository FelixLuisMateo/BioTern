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
        . "1) Extract this folder on a Windows PC connected to the same LAN as your F20H.\r\n"
        . "2) Open the BioTern website and save Bridge Profile first (token, URL, F20H IP).\r\n"
        . "3) Run install-bridge-worker-task.ps1 with your SiteBaseUrl and BridgeToken.\r\n"
        . "4) Verify with manage-bridge-worker-task.ps1 -Action status.\r\n"
        . "5) Keep that bridge PC signed in for user-logon mode task execution.\r\n";
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
    $baseUrl = 'https://biotern-ccst.vercel.app';
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

        <div class="card bio-console-hero mb-3">
            <div class="card-body">
                <div class="bio-console-hero-grid">
                    <div>
                        <span class="bio-console-eyebrow">Deployment Guide</span>
                        <h3>Set up a new bridge PC in minutes</h3>
                        <p>Use this page as your installation manual when switching to a new laptop/computer or router. Download the kit, run one install command, and verify status.</p>
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
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Step 2: Install Bridge Auto-Start</h6></div>
                    <div class="card-body">
                        <label class="form-label">Run once on bridge PC</label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="bridgeManualInstallCmd" value="<?php echo bridge_manual_h($installCommand); ?>" readonly>
                            <button type="button" class="btn btn-outline-secondary" data-copy-target="bridgeManualInstallCmd">Copy</button>
                        </div>
                        <small class="text-muted">This installs and starts BioTernBridgeWorker as a scheduled background task.</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Step 3: Verify and Maintain</h6></div>
                    <div class="card-body">
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
                    <div class="card-header"><h6 class="card-title mb-0">Step 4: Full Checklist</h6></div>
                    <div class="card-body">
                        <ol class="mb-0">
                            <li>Open website and save Bridge Profile first (URL/token/F20H network).</li>
                            <li>Extract Bridge Kit on new Windows PC.</li>
                            <li>Run install command from this page once.</li>
                            <li>Run status command and confirm task is Running.</li>
                            <li>Go to F20H Machine Manager and click Read All Users / Process Ingest Queue.</li>
                            <li>If changing to a new domain (e.g. ClarkCollege.edu.ph), re-run install using new SiteBaseUrl.</li>
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
