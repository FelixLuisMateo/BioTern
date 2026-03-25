<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/tools/biometric_machine_runtime.php';
require_once dirname(__DIR__) . '/tools/biometric_auto_import.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}
$isAdmin = ($role === 'admin');

$flashType = 'info';
$flashMessage = '';
$userListRaw = '';
$userDetailsRaw = trim((string)($_POST['user_json'] ?? ''));
$userListDecoded = null;
$userDetailsDecoded = null;
$selectedUserId = (int)($_POST['user_id'] ?? 0);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['machine_action'] ?? ''));

    try {
        $adminOnlyActions = [
            'save_user_json',
            'save_config',
            'save_network',
            'save_connector_config',
            'clear_records',
            'clear_users',
            'clear_admin',
            'restart',
            'save_device_identity',
        ];
        if (in_array($action, $adminOnlyActions, true) && !$isAdmin) {
            throw new RuntimeException('Only admins can perform that machine action.');
        }

        switch ($action) {
            case 'sync':
                $connector = biometric_machine_run_command('sync');
                if (!$connector['success']) {
                    throw new RuntimeException(trim(implode("\n", $connector['output'] ?? [])));
                }
                $flashMessage = trim(($connector['text'] ?? '') . "\n" . run_biometric_auto_import());
                $flashType = 'success';
                break;

            case 'list_users':
                machine_load_user_list_into_state($userListRaw, $userListDecoded);
                $flashMessage = 'Machine user list loaded.';
                $flashType = 'success';
                break;

            case 'get_user':
                if ($selectedUserId <= 0) {
                    throw new RuntimeException('Enter a valid user ID.');
                }
                machine_load_user_details_into_state($selectedUserId, $userDetailsRaw, $userDetailsDecoded);
                $flashMessage = 'Machine user record loaded.';
                $flashType = 'success';
                break;

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
                $flashMessage = 'Machine user updated.';
                $flashType = 'success';
                break;

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
                machine_load_user_details_into_state($selectedUserId, $userDetailsRaw, $userDetailsDecoded);
                machine_load_user_list_into_state($userListRaw, $userListDecoded);
                $flashMessage = 'Machine user name updated.';
                $flashType = 'success';
                break;

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
                machine_load_user_list_into_state($userListRaw, $userListDecoded);
                $flashMessage = 'Machine user renamed on the F20H.';
                $flashType = 'success';
                break;

            case 'delete_user':
                if ($selectedUserId <= 0) {
                    throw new RuntimeException('Enter a valid user ID to delete.');
                }
                $result = biometric_machine_run_command('delete-user', [(string)$selectedUserId]);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $flashMessage = 'Machine user deleted.';
                $flashType = 'success';
                $userDetailsRaw = '';
                $userDetailsDecoded = null;
                machine_load_user_list_into_state($userListRaw, $userListDecoded);
                break;

            case 'get_device_info':
                $result = biometric_machine_run_command('get-device-info');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $deviceInfoRaw = $result['text'] ?? '';
                $flashMessage = 'Device info loaded.';
                $flashType = 'success';
                break;

            case 'get_config':
                $result = biometric_machine_run_command('get-config');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $configRaw = $result['text'] ?? '';
                $flashMessage = 'Device config loaded.';
                $flashType = 'success';
                break;

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
                $flashMessage = 'Device config updated.';
                $flashType = 'success';
                break;

            case 'get_network':
                $result = biometric_machine_run_command('get-network');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $networkRaw = $result['text'] ?? '';
                $flashMessage = 'Network settings loaded.';
                $flashType = 'success';
                break;

            case 'save_network':
                $ip = trim((string)($_POST['ip_address'] ?? ''));
                $gateway = trim((string)($_POST['gateway'] ?? ''));
                $mask = trim((string)($_POST['mask'] ?? ''));
                $port = trim((string)($_POST['port'] ?? ''));
                $result = biometric_machine_run_command('set-network', [$ip, $gateway, $mask, $port]);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $flashMessage = 'Network settings updated.';
                $flashType = 'success';
                break;

            case 'get_time':
                $result = biometric_machine_run_command('get-time');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $timeRaw = $result['text'] ?? '';
                $flashMessage = 'Machine time loaded.';
                $flashType = 'success';
                break;

            case 'set_time':
                $timeValue = trim((string)($_POST['time_value'] ?? ''));
                $result = biometric_machine_run_command('set-time', [$timeValue]);
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $flashMessage = 'Machine time updated.';
                $flashType = 'success';
                break;

            case 'save_connector_config':
                $machineConfigJson = trim((string)($_POST['connector_config_json'] ?? ''));
                if ($machineConfigJson === '') {
                    throw new RuntimeException('Connector config cannot be empty.');
                }
                if (json_decode($machineConfigJson, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Connector config must be valid JSON.');
                }
                file_put_contents($machineConfigPath, $machineConfigJson . PHP_EOL);
                $flashMessage = 'Connector config updated.';
                $flashType = 'success';
                break;

            case 'clear_records':
                $result = biometric_machine_run_command('clear-records');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $flashMessage = 'Machine attendance records cleared.';
                $flashType = 'warning';
                break;

            case 'clear_users':
                $result = biometric_machine_run_command('clear-users');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $flashMessage = 'All users on the machine were cleared.';
                $flashType = 'warning';
                break;

            case 'clear_admin':
                $result = biometric_machine_run_command('clear-admin');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $flashMessage = 'Machine admin records cleared.';
                $flashType = 'warning';
                break;

            case 'restart':
                $result = biometric_machine_run_command('restart');
                if (!$result['success']) {
                    throw new RuntimeException(trim(implode("\n", $result['output'] ?? [])));
                }
                $flashMessage = 'Restart command sent to the machine.';
                $flashType = 'success';
                break;

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
                $flashMessage = 'Machine identity settings updated.';
                $flashType = 'success';
                break;
        }
    } catch (Throwable $e) {
        $flashType = 'danger';
        $flashMessage = $e->getMessage();
    }
}

$loadedUserRows = machine_extract_rows($userListDecoded);
$connectorConfig = json_decode($machineConfigJson, true);
$connectorIp = is_array($connectorConfig) ? (string)($connectorConfig['ipAddress'] ?? '') : '';
$connectorPort = is_array($connectorConfig) ? (string)($connectorConfig['port'] ?? '') : '';
$connectorDeviceNo = is_array($connectorConfig) ? (string)($connectorConfig['deviceNumber'] ?? '') : '';

$page_title = 'BioTern || F20H Machine Manager';
include __DIR__ . '/../includes/header.php';
?>
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
                        <div class="text-muted">Use “Read All Users” to refresh this page view.</div>
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
        </div>

        <div class="row g-3">
            <div class="col-xl-4">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Machine Sync</h6></div>
                    <div class="card-body">
                        <p class="text-muted">Pull new logs from the F20H, then reconcile them into BioTern attendance.</p>
                        <form method="post">
                            <input type="hidden" name="machine_action" value="sync">
                            <button type="submit" class="btn btn-primary w-100">Sync Now</button>
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
                                <button type="submit" class="btn btn-outline-secondary w-100">Read Device Config</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="machine_action" value="list_users">
                                <button type="submit" class="btn btn-outline-secondary w-100">Read All Users</button>
                            </form>
                            <a href="attendance.php" class="btn btn-outline-secondary w-100">Open Attendance DTR</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Users on Machine</h6></div>
                    <div class="card-body">
                        <form method="post" class="row g-2 align-items-end mb-3">
                            <input type="hidden" name="machine_action" value="get_user">
                            <div class="col-sm-6">
                                <label class="form-label">User ID on F20H</label>
                                <input type="number" name="user_id" class="form-control" value="<?php echo machine_h($selectedUserId); ?>" min="1">
                            </div>
                            <div class="col-sm-6">
                                <button type="submit" class="btn btn-primary w-100">Load User Record</button>
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
                                                <td style="min-width: 240px;">
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
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="machine_action" value="get_user">
                                                            <input type="hidden" name="user_id" value="<?php echo machine_h($rowUserId); ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">Load</button>
                                                        </form>
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

            <div class="col-xl-6">
                <div class="card stretch stretch-full">
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

                        <form method="post" class="mt-2" onsubmit="return confirm('Delete this user from the F20H machine?');">
                            <input type="hidden" name="machine_action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?php echo machine_h($selectedUserId); ?>">
                            <button type="submit" class="btn btn-danger">Delete User</button>
                        </form>

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
                                            class="form-control"
                                            rows="4"
                                            style="resize: vertical; min-height: 120px;"
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
                    <div class="card-header"><h6 class="card-title mb-0">Network and Time</h6></div>
                    <div class="card-body">
                        <form method="post" class="row g-2 mb-4">
                            <input type="hidden" name="machine_action" value="save_network">
                            <div class="col-sm-6">
                                <label class="form-label">IP Address</label>
                                <input type="text" name="ip_address" class="form-control" placeholder="192.168.100.201">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Gateway</label>
                                <input type="text" name="gateway" class="form-control" placeholder="192.168.100.1">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Mask</label>
                                <input type="text" name="mask" class="form-control" placeholder="255.255.255.0">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Port</label>
                                <input type="number" name="port" class="form-control" placeholder="5001">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-outline-primary">Save Network Settings</button>
                            </div>
                        </form>

                        <form method="post" class="row g-2">
                            <input type="hidden" name="machine_action" value="set_time">
                            <div class="col-sm-8">
                                <label class="form-label">Device Time</label>
                                <input type="text" name="time_value" class="form-control" placeholder="2026-03-25 08:00:00">
                            </div>
                            <div class="col-sm-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-secondary w-100">Set Time</button>
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

            <?php if ($isAdmin): ?>
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
                                <form method="post" onsubmit="return confirm('Restart the F20H now?');">
                                    <input type="hidden" name="machine_action" value="restart">
                                    <button type="submit" class="btn btn-outline-primary">Restart Machine</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Clear only attendance records from the F20H?');">
                                    <input type="hidden" name="machine_action" value="clear_records">
                                    <button type="submit" class="btn btn-outline-warning">Clear Records</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Clear admin data on the F20H?');">
                                    <input type="hidden" name="machine_action" value="clear_admin">
                                    <button type="submit" class="btn btn-outline-warning">Clear Admin</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Clear all users from the F20H? This removes the machine user list.');">
                                    <input type="hidden" name="machine_action" value="clear_users">
                                    <button type="submit" class="btn btn-outline-danger">Clear All Users</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
