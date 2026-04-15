<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/tools/biometric_machine_runtime.php';
require_once dirname(__DIR__) . '/tools/biometric_auto_import.php';
require_once dirname(__DIR__) . '/tools/biometric_ops.php';
require_once dirname(__DIR__) . '/tools/cleanup_biometric_duplicates.php';
require_once dirname(__DIR__) . '/tools/rebuild_biometric_date.php';
require_once dirname(__DIR__) . '/tools/repair_biometric_attendance.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    header('Location: homepage.php');
    exit;
}
$isAdmin = ($role === 'admin');
$cloudRuntime = false;

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
biotern_ensure_fingerprint_user_map_table($conn);
$fingerprintIdentityMap = machine_fetch_fingerprint_identity_map($conn);
$machineUserIndex = [];
$mappingValidation = [
    'machine_unmapped' => [],
    'mapped_missing_on_machine' => [],
    'name_mismatches' => [],
    'orphan_mappings' => [],
];

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

    file_put_contents($machineConfigPath, $json . PHP_EOL);
}

function machine_open_restart_bridge_shell(string $workspaceRoot): void
{
    if (stripos(PHP_OS_FAMILY, 'Windows') !== 0) {
        throw new RuntimeException('Bridge worker restart shell launcher is only available on Windows hosts.');
    }

    $workspaceCandidates = [
        $workspaceRoot,
        dirname($workspaceRoot) . DIRECTORY_SEPARATOR . 'BioTern',
    ];

    $resolvedWorkspaceRoot = '';
    $restartScript = '';
    foreach ($workspaceCandidates as $candidateRoot) {
        $candidateScript = $candidateRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'restart-bridge-worker.ps1';
        if (file_exists($candidateScript)) {
            $resolvedWorkspaceRoot = $candidateRoot;
            $restartScript = $candidateScript;
            break;
        }
    }

    if (!file_exists($restartScript)) {
        throw new RuntimeException('Restart script not found. Expected in tools/restart-bridge-worker.ps1 (BioTern_unified or BioTern).');
    }

    $workspaceArg = str_replace('"', '""', $resolvedWorkspaceRoot !== '' ? $resolvedWorkspaceRoot : $workspaceRoot);
    $scriptArg = str_replace('"', '""', $restartScript);

    $launchCmd = 'start "BioTern Bridge Restart" powershell.exe -NoExit -ExecutionPolicy Bypass -Command "& \""' . $scriptArg . '"\" -WorkspaceRoot \""' . $workspaceArg . '"\""';
    pclose(popen($launchCmd, 'r'));
}

function machine_open_bridge_log_tail_shell(string $workspaceRoot): void
{
    if (stripos(PHP_OS_FAMILY, 'Windows') !== 0) {
        throw new RuntimeException('Bridge log tail launcher is only available on Windows hosts.');
    }

    $workspaceCandidates = [
        $workspaceRoot,
        dirname($workspaceRoot) . DIRECTORY_SEPARATOR . 'BioTern',
    ];

    $logPath = '';
    foreach ($workspaceCandidates as $candidateRoot) {
        $candidateLog = $candidateRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'bridge-worker.log';
        if (file_exists($candidateLog)) {
            $logPath = $candidateLog;
            break;
        }
    }

    if ($logPath === '') {
        $logPath = $workspaceRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'bridge-worker.log';
    }

    $logArg = str_replace('"', '""', $logPath);
    $launchCmd = 'start "BioTern Bridge Logs" powershell.exe -NoExit -ExecutionPolicy Bypass -Command "Get-Content -Path \""' . $logArg . '"\" -Tail 80 -Wait"';
    pclose(popen($launchCmd, 'r'));
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

function machine_repair_summary_text(array $result): string
{
    $audits = isset($result['audits']) && is_array($result['audits']) ? $result['audits'] : [];
    $rebuilt = isset($result['rebuilt']) && is_array($result['rebuilt']) ? $result['rebuilt'] : [];
    $flaggedDates = [];
    foreach ($audits as $audit) {
        if (!empty($audit['needs_rebuild']) && !empty($audit['date'])) {
            $flaggedDates[] = (string)$audit['date'];
        }
    }

    $summary = 'Audited ' . count($audits) . ' biometric date(s).';
    if ($rebuilt !== []) {
        $summary .= ' Rebuilt ' . count($rebuilt) . ' date(s).';
    } elseif ($flaggedDates !== []) {
        $summary .= ' ' . count($flaggedDates) . ' date(s) need repair.';
    } else {
        $summary .= ' No suspicious biometric dates were found.';
    }

    if ($flaggedDates !== []) {
        $summary .= ' Dates: ' . implode(', ', array_slice($flaggedDates, 0, 10));
        if (count($flaggedDates) > 10) {
            $summary .= '...';
        }
    }

    return $summary;
}

function machine_repair_rows(array $result): array
{
    $rows = [];
    $audits = isset($result['audits']) && is_array($result['audits']) ? $result['audits'] : [];
    $rebuiltByDate = [];
    foreach ((isset($result['rebuilt']) && is_array($result['rebuilt']) ? $result['rebuilt'] : []) as $rebuilt) {
        $date = (string)($rebuilt['date'] ?? '');
        if ($date !== '') {
            $rebuiltByDate[$date] = $rebuilt;
        }
    }

    foreach ($audits as $audit) {
        $date = (string)($audit['date'] ?? '');
        if ($date === '') {
            continue;
        }
        $rebuilt = $rebuiltByDate[$date] ?? [];
        $rows[] = [
            'date' => $date,
            'raw_count' => (int)($audit['raw_count'] ?? 0),
            'attendance_rows' => (int)($audit['attendance_rows'] ?? 0),
            'filled_slots' => (int)($audit['filled_slots'] ?? 0),
            'suspicious_count' => (int)($audit['suspicious_count'] ?? 0),
            'needs_rebuild' => !empty($audit['needs_rebuild']),
            'rebuilt' => $rebuilt !== [],
            'changes' => (int)($rebuilt['attendance_changes_applied'] ?? 0),
        ];
    }

    return $rows;
}

function machine_close_resolved_anomalies(mysqli $conn): int
{
    $machineConfig = loadBiometricMachineConfig();
    $studentScheduleMap = buildStudentAttendanceScheduleMap($conn);
    $fingerprintMap = buildFingerprintStudentMap($conn);

    $closed = 0;
    $res = $conn->query("
        SELECT id, fingerprint_id, student_id, event_time, anomaly_type, status
        FROM biometric_anomalies
        WHERE status = 'open'
          AND anomaly_type = 'outside_hard_attendance_window'
        ORDER BY id DESC
    ");
    if (!$res instanceof mysqli_result) {
        return 0;
    }

    while ($row = $res->fetch_assoc()) {
        $eventTime = (string)($row['event_time'] ?? '');
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2})$/', $eventTime, $matches)) {
            continue;
        }

        $date = $matches[1];
        $time = $matches[2];
        $studentId = (int)($row['student_id'] ?? 0);
        if ($studentId <= 0) {
            $fingerId = (int)($row['fingerprint_id'] ?? 0);
            $studentId = (int)($fingerprintMap[$fingerId] ?? 0);
        }

        $schedule = section_schedule_effective_day(
            $studentScheduleMap[$studentId] ?? section_schedule_from_row([]),
            $date,
            biometricMachineSchoolHours($machineConfig)
        );

        if (biometricMachineIsWithinHardWindow($time, $machineConfig, $schedule)) {
            if (biometric_ops_close_anomaly($conn, (int)($row['id'] ?? 0))) {
                $closed++;
            }
        }
    }
    $res->close();

    return $closed;
}

function machine_fetch_fingerprint_identity_map(mysqli $conn): array
{
    biotern_ensure_fingerprint_user_map_table($conn);
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

function machine_build_machine_user_index($decoded): array
{
    $index = [];
    foreach (machine_extract_rows($decoded) as $row) {
        $machineId = (int)trim(machine_row_value($row, ['id', 'ID', 'user_id', 'userId', 'EnrollNumber']));
        if ($machineId <= 0) {
            continue;
        }

        $index[$machineId] = [
            'machine_id' => $machineId,
            'name' => trim(machine_row_value($row, ['name', 'Name'])),
            'card_no' => trim(machine_row_value($row, ['cardno', 'cardNo', 'CardNo'])),
            'privilege' => trim(machine_row_value($row, ['privilege', 'privalege', 'Privilege'])),
            'raw' => $row,
        ];
    }

    ksort($index);
    return $index;
}

function machine_expected_mapping_name(array $identity): string
{
    $studentName = trim((string)($identity['first_name'] ?? '') . ' ' . (string)($identity['last_name'] ?? ''));
    if ($studentName !== '') {
        return $studentName;
    }

    $userName = trim((string)($identity['mapped_user_name'] ?? ''));
    if ($userName !== '') {
        return $userName;
    }

    return trim((string)($identity['mapped_username'] ?? ''));
}

function machine_build_mapping_validation(array $machineUsers, array $identityMap): array
{
    $machineUnmapped = [];
    $mappedMissingOnMachine = [];
    $nameMismatches = [];
    $orphanMappings = [];

    foreach ($machineUsers as $machineId => $machineUser) {
        if (!isset($identityMap[$machineId])) {
            $machineUnmapped[] = $machineUser;
            continue;
        }

        $identity = $identityMap[$machineId];
        $expectedName = trim(machine_expected_mapping_name($identity));
        $machineName = trim((string)($machineUser['name'] ?? ''));
        if ($expectedName !== '' && $machineName !== '' && mb_strtolower($expectedName) !== mb_strtolower($machineName)) {
            $nameMismatches[] = [
                'machine_id' => $machineId,
                'machine_name' => $machineName,
                'expected_name' => $expectedName,
                'student_number' => trim((string)($identity['student_number'] ?? '')),
                'mapped_user_id' => (int)($identity['user_id'] ?? 0),
            ];
        }
    }

    foreach ($identityMap as $fingerId => $identity) {
        if (!isset($machineUsers[$fingerId])) {
            $mappedMissingOnMachine[] = [
                'finger_id' => $fingerId,
                'label' => machine_identity_label($identity),
                'mapped_user_id' => (int)($identity['user_id'] ?? 0),
            ];
        }

        if (trim((string)($identity['student_number'] ?? '')) === '') {
            $orphanMappings[] = [
                'finger_id' => $fingerId,
                'label' => machine_identity_label($identity),
                'mapped_user_id' => (int)($identity['user_id'] ?? 0),
            ];
        }
    }

    return [
        'machine_unmapped' => $machineUnmapped,
        'mapped_missing_on_machine' => $mappedMissingOnMachine,
        'name_mismatches' => $nameMismatches,
        'orphan_mappings' => $orphanMappings,
    ];
}

if (isset($_SESSION['machine_manager_flash']) && is_array($_SESSION['machine_manager_flash'])) {
    $flashType = (string)($_SESSION['machine_manager_flash']['type'] ?? 'info');
    $flashMessage = (string)($_SESSION['machine_manager_flash']['message'] ?? '');
    unset($_SESSION['machine_manager_flash']);
}
$lastRepairResult = [];
if (isset($_SESSION['machine_manager_last_repair']) && is_array($_SESSION['machine_manager_last_repair'])) {
    $lastRepairResult = $_SESSION['machine_manager_last_repair'];
}

if ((int)($_GET['load_users'] ?? 0) === 1) {
    try {
        machine_load_user_list_into_state($userListRaw, $userListDecoded);
        $machineUserIndex = machine_build_machine_user_index($userListDecoded);
        $mappingValidation = machine_build_mapping_validation($machineUserIndex, $fingerprintIdentityMap);
    } catch (Throwable $e) {
        if ($flashMessage === '') {
            $flashType = 'danger';
            $flashMessage = $e->getMessage();
        }
    }
}

if ($selectedUserId > 0 && (int)($_GET['load_user'] ?? 0) === 1) {
    try {
        machine_load_user_details_into_state($selectedUserId, $userDetailsRaw, $userDetailsDecoded);
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
            'open_restart_bridge_shell',
            'open_bridge_log_tail_shell',
            'cleanup_duplicates_rebuild',
            'clear_records',
            'clear_users',
            'clear_admin',
            'restart',
            'save_device_identity',
        ];
        if (in_array($action, $adminOnlyActions, true) && !$isAdmin) {
            throw new RuntimeException('Only admins can perform that machine action.');
        }

        if (machine_is_cloud_runtime() && in_array($action, ['open_restart_bridge_shell', 'open_bridge_log_tail_shell'], true)) {
            throw new RuntimeException('Bridge shell launcher actions are available only on the local Windows bridge computer.');
        }

        switch ($action) {
            case 'open_restart_bridge_shell':
                machine_open_restart_bridge_shell(dirname(__DIR__));
                $_SESSION['machine_manager_flash'] = [
                    'type' => 'success',
                    'message' => 'Opened PowerShell for bridge worker restart.',
                ];
                machine_redirect_after_post([]);

            case 'open_bridge_log_tail_shell':
                machine_open_bridge_log_tail_shell(dirname(__DIR__));
                $_SESSION['machine_manager_flash'] = [
                    'type' => 'success',
                    'message' => 'Opened PowerShell bridge log tail.',
                ];
                machine_redirect_after_post([]);

            case 'sync':
                $connector = biometric_machine_run_command('sync');
                if (!$connector['success']) {
                    throw new RuntimeException(trim(implode("\n", $connector['output'] ?? [])));
                }
                $_SESSION['machine_manager_flash'] = [
                    'type' => 'success',
                    'message' => trim(($connector['text'] ?? '') . "\n" . run_biometric_auto_import()),
                ];
                machine_redirect_after_post(['load_users' => 1]);

            case 'cleanup_duplicates_rebuild':
                $cleanup = cleanup_biometric_duplicate_logs($conn);
                $rebuilt = [];
                foreach (($cleanup['affected_dates'] ?? []) as $date) {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
                        continue;
                    }
                    $rebuilt[] = rebuild_biometric_attendance_for_date($conn, (string)$date);
                }

                $rebuildSummary = [];
                foreach ($rebuilt as $row) {
                    $rebuildSummary[] = $row['date'] . ' (' . $row['raw_events_replayed'] . ' replayed)';
                }

                $_SESSION['machine_manager_flash'] = [
                    'type' => 'success',
                    'message' => trim(
                        "Duplicate biometric cleanup complete.\n" .
                        'Window: ' . (int)($cleanup['window_minutes'] ?? 0) . " minutes\n" .
                        'Deleted duplicate raw logs: ' . (int)($cleanup['deleted_count'] ?? 0) . "\n" .
                        'Affected dates rebuilt: ' . ($rebuildSummary !== [] ? implode(', ', $rebuildSummary) : 'none')
                    ),
                ];
                biometric_ops_log_audit(
                    $conn,
                    (int)($_SESSION['user_id'] ?? 0),
                    $role,
                    'machine_cleanup_duplicates_rebuild',
                    'machine_sync',
                    null,
                    ['cleanup' => $cleanup, 'rebuilt' => $rebuilt]
                );
                machine_redirect_after_post(['load_users' => 1]);

            case 'list_users':
                machine_load_user_list_into_state($userListRaw, $userListDecoded);
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine user list loaded.'];
                machine_redirect_after_post(['load_users' => 1]);

            case 'get_user':
                if ($selectedUserId <= 0) {
                    throw new RuntimeException('Enter a valid user ID.');
                }
                machine_load_user_details_into_state($selectedUserId, $userDetailsRaw, $userDetailsDecoded);
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Machine user record loaded.'];
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
                file_put_contents($machineConfigPath, $machineConfigJson . PHP_EOL);
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
                $submittedOutputPath = trim((string)($_POST['connector_output_path'] ?? ''));
                $existingOutputPath = trim((string)($existingConfig['outputPath'] ?? ''));
                $existingConfig['outputPath'] = $submittedOutputPath !== ''
                    ? $submittedOutputPath
                    : ($existingOutputPath !== '' ? $existingOutputPath : 'C:\\BioTern\\attendance.txt');
                $existingConfig['attendanceWindowEnabled'] = isset($_POST['attendance_window_enabled']);
                $existingConfig['attendanceStartTime'] = trim((string)($_POST['attendance_start_time'] ?? '08:00:00'));
                $existingConfig['attendanceEndTime'] = trim((string)($_POST['attendance_end_time'] ?? '19:00:00'));
                $existingConfig['duplicateGuardMinutes'] = max(1, (int)($_POST['duplicate_guard_minutes'] ?? 10));
                $existingConfig['slotAdvanceMinimumMinutes'] = max(1, (int)($_POST['slot_advance_minimum_minutes'] ?? 10));
                $existingConfig['maxEarlyArrivalMinutes'] = max(0, (int)($_POST['max_early_arrival_minutes'] ?? 120));
                $existingConfig['maxLateDepartureMinutes'] = max(0, (int)($_POST['max_late_departure_minutes'] ?? 120));
                $selectedRouterPreset = trim((string)($_POST['router_preset'] ?? 'custom'));
                $allowedRouterPresets = ['router_1', 'router_2', 'custom'];
                $existingConfig['selectedRouterPreset'] = in_array($selectedRouterPreset, $allowedRouterPresets, true) ? $selectedRouterPreset : 'custom';

                if ($existingConfig['ipAddress'] === '') {
                    throw new RuntimeException('Connector IP address is required.');
                }
                machine_connector_write_config($machineConfigPath, $existingConfig);
                $_SESSION['machine_manager_flash'] = ['type' => 'success', 'message' => 'Quick connector settings updated.'];
                machine_redirect_after_post([]);

            case 'repair_attendance_dry_run':
                $repairDryRun = repair_biometric_attendance($conn, true);
                $_SESSION['machine_manager_last_repair'] = $repairDryRun;
                $_SESSION['machine_manager_flash'] = [
                    'type' => 'info',
                    'message' => machine_repair_summary_text($repairDryRun),
                ];
                machine_redirect_after_post([]);

            case 'repair_attendance_apply':
                $repairResult = repair_biometric_attendance($conn, false);
                $_SESSION['machine_manager_last_repair'] = $repairResult;
                biometric_ops_log_audit(
                    $conn,
                    (int)($_SESSION['user_id'] ?? 0),
                    $role,
                    'repair_biometric_attendance',
                    'biometric_attendance',
                    null,
                    ['repair_result' => $repairResult]
                );
                $_SESSION['machine_manager_flash'] = [
                    'type' => 'success',
                    'message' => machine_repair_summary_text($repairResult),
                ];
                machine_redirect_after_post([]);

            case 'close_resolved_anomalies':
                $closedCount = machine_close_resolved_anomalies($conn);
                biometric_ops_log_audit(
                    $conn,
                    (int)($_SESSION['user_id'] ?? 0),
                    $role,
                    'close_resolved_anomalies',
                    'biometric_anomalies',
                    null,
                    ['closed_count' => $closedCount]
                );
                $_SESSION['machine_manager_flash'] = [
                    'type' => 'success',
                    'message' => $closedCount > 0
                        ? ('Closed ' . $closedCount . ' resolved anomaly record(s).')
                        : 'No resolved anomalies needed closing.',
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
$connectorConfig = json_decode($machineConfigJson, true);
$connectorIp = is_array($connectorConfig) ? (string)($connectorConfig['ipAddress'] ?? '') : '';
$connectorPort = is_array($connectorConfig) ? (string)($connectorConfig['port'] ?? '') : '';
$connectorDeviceNo = is_array($connectorConfig) ? (string)($connectorConfig['deviceNumber'] ?? '') : '';
$connectorGateway = is_array($connectorConfig) ? (string)($connectorConfig['gateway'] ?? '') : '';
$connectorMask = is_array($connectorConfig) ? (string)($connectorConfig['mask'] ?? '255.255.255.0') : '255.255.255.0';
$connectorPassword = is_array($connectorConfig) ? (string)($connectorConfig['communicationPassword'] ?? '0') : '0';
$connectorOutputPath = is_array($connectorConfig) ? (string)($connectorConfig['outputPath'] ?? '') : '';
$connectorWindowEnabled = is_array($connectorConfig) ? !empty($connectorConfig['attendanceWindowEnabled']) : false;
$connectorStartTime = is_array($connectorConfig) ? (string)($connectorConfig['attendanceStartTime'] ?? '08:00:00') : '08:00:00';
$connectorEndTime = is_array($connectorConfig) ? (string)($connectorConfig['attendanceEndTime'] ?? '19:00:00') : '19:00:00';
$duplicateGuardMinutes = is_array($connectorConfig) ? (int)($connectorConfig['duplicateGuardMinutes'] ?? 10) : 10;
$slotAdvanceMinimumMinutes = is_array($connectorConfig) ? (int)($connectorConfig['slotAdvanceMinimumMinutes'] ?? 10) : 10;
$maxEarlyArrivalMinutes = is_array($connectorConfig) ? (int)($connectorConfig['maxEarlyArrivalMinutes'] ?? 120) : 120;
$maxLateDepartureMinutes = is_array($connectorConfig) ? (int)($connectorConfig['maxLateDepartureMinutes'] ?? 120) : 120;
$selectedRouterPreset = is_array($connectorConfig) ? (string)($connectorConfig['selectedRouterPreset'] ?? 'custom') : 'custom';
if (!in_array($selectedRouterPreset, ['router_1', 'router_2', 'custom'], true)) {
    $selectedRouterPreset = 'custom';
}

if ($selectedRouterPreset === 'custom') {
    if ($connectorIp === '192.168.100.201' && $connectorGateway === '192.168.100.1') {
        $selectedRouterPreset = 'router_1';
    } elseif ($connectorIp === '192.168.110.201' && $connectorGateway === '192.168.110.1') {
        $selectedRouterPreset = 'router_2';
    }
}
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
include __DIR__ . '/../includes/header.php';
?>
        <style>
            .quick-router-panel {
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 0.9rem;
                padding: 1rem;
            }
            .machine-users-card .table td,
            .machine-users-card .table th {
                vertical-align: middle;
            }
            .machine-users-card .table td:last-child {
                min-width: 160px;
            }
            .machine-inline-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                justify-content: flex-end;
            }
            .machine-inline-actions form {
                margin: 0;
            }
            .machine-users-card code,
            .machine-raw-logs-card code {
                white-space: pre-wrap;
                word-break: break-word;
            }
            html.app-skin-dark .quick-router-panel {
                background: rgba(15, 23, 42, 0.72);
                border-color: rgba(148, 163, 184, 0.22);
                color: #e5eefc;
            }
            html.app-skin-dark .quick-router-panel .text-muted,
            html.app-skin-dark .quick-router-panel small {
                color: rgba(226, 232, 240, 0.78) !important;
            }
        </style>
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
                            ? 'Cloud mode: direct F20H user reads are disabled. Use your bridge computer and direct ingest.'
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
                            <?php if ($isAdmin && !machine_is_cloud_runtime()): ?>
                                <form method="post">
                                    <input type="hidden" name="machine_action" value="open_restart_bridge_shell">
                                    <button type="submit" class="btn btn-outline-dark w-100">Open PowerShell: Restart Bridge Worker</button>
                                </form>
                                <form method="post">
                                    <input type="hidden" name="machine_action" value="open_bridge_log_tail_shell">
                                    <button type="submit" class="btn btn-outline-secondary w-100">Open PowerShell: Bridge Log Tail</button>
                                </form>
                            <?php elseif ($isAdmin && machine_is_cloud_runtime()): ?>
                                <div class="alert alert-info mb-2">Windows shell launchers are hidden in cloud runtime. Run restart/log tail from your local bridge PC.</div>
                            <?php endif; ?>
                            <?php if ($isAdmin): ?>
                                <form method="post" onsubmit="return confirm('Clean duplicate biometric raw logs and rebuild all affected attendance dates?');">
                                    <input type="hidden" name="machine_action" value="cleanup_duplicates_rebuild">
                                    <button type="submit" class="btn btn-outline-warning w-100">Clean Duplicates + Rebuild</button>
                                </form>
                            <?php endif; ?>
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
                                <button type="submit" class="btn btn-outline-secondary w-100" <?php echo $cloudRuntime ? 'disabled title="Unavailable in cloud runtime"' : ''; ?>><?php echo $cloudRuntime ? 'Read All Users (Local Only)' : 'Read All Users'; ?></button>
                            </form>
                            <a href="attendance.php" class="btn btn-outline-secondary w-100">Open Attendance DTR</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card stretch stretch-full">
                    <div class="card-header"><h6 class="card-title mb-0">Fingerprint Mapping Validation</h6></div>
                    <div class="card-body">
                        <p class="text-muted mb-3">Compare the current F20H user list against the local `fingerprint_user_map` before syncing attendance.</p>
                        <?php if (empty($machineUserIndex)): ?>
                            <div class="alert alert-soft-primary mb-0">Load the machine user list first to validate mappings.</div>
                        <?php else: ?>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted fs-12 mb-1">Machine IDs Without Local Mapping</div>
                                        <div class="fs-4 fw-bold"><?php echo count($mappingValidation['machine_unmapped']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted fs-12 mb-1">Mapped IDs Missing On Machine</div>
                                        <div class="fs-4 fw-bold"><?php echo count($mappingValidation['mapped_missing_on_machine']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted fs-12 mb-1">Name Mismatches</div>
                                        <div class="fs-4 fw-bold"><?php echo count($mappingValidation['name_mismatches']); ?></div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted fs-12 mb-1">Orphan Local Mappings</div>
                                        <div class="fs-4 fw-bold"><?php echo count($mappingValidation['orphan_mappings']); ?></div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($mappingValidation['machine_unmapped'])): ?>
                                <div class="mt-4">
                                    <div class="fw-semibold mb-2">Machine IDs Without Local Mapping</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead><tr><th>Machine ID</th><th>Name</th><th>Card</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($mappingValidation['machine_unmapped'] as $row): ?>
                                                <tr>
                                                    <td><?php echo machine_h($row['machine_id']); ?></td>
                                                    <td><?php echo machine_h($row['name'] !== '' ? $row['name'] : '(blank)'); ?></td>
                                                    <td><?php echo machine_h($row['card_no'] !== '' ? ($isAdmin ? $row['card_no'] : machine_mask_card_number($row['card_no'])) : '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($mappingValidation['mapped_missing_on_machine'])): ?>
                                <div class="mt-4">
                                    <div class="fw-semibold mb-2">Mapped Fingerprints Missing On Machine</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead><tr><th>Fingerprint ID</th><th>Mapped User</th><th>User ID</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($mappingValidation['mapped_missing_on_machine'] as $row): ?>
                                                <tr>
                                                    <td><?php echo machine_h($row['finger_id']); ?></td>
                                                    <td><?php echo machine_h($row['label']); ?></td>
                                                    <td><?php echo machine_h($row['mapped_user_id']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($mappingValidation['name_mismatches'])): ?>
                                <div class="mt-4">
                                    <div class="fw-semibold mb-2">Name Mismatches</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead><tr><th>Fingerprint ID</th><th>Machine Name</th><th>Expected Name</th><th>Student #</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($mappingValidation['name_mismatches'] as $row): ?>
                                                <tr>
                                                    <td><?php echo machine_h($row['machine_id']); ?></td>
                                                    <td><?php echo machine_h($row['machine_name']); ?></td>
                                                    <td><?php echo machine_h($row['expected_name']); ?></td>
                                                    <td><?php echo machine_h($row['student_number'] !== '' ? $row['student_number'] : '-'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($mappingValidation['orphan_mappings'])): ?>
                                <div class="mt-4">
                                    <div class="fw-semibold mb-2">Orphan Local Mappings</div>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle">
                                            <thead><tr><th>Fingerprint ID</th><th>Mapped Record</th><th>User ID</th></tr></thead>
                                            <tbody>
                                            <?php foreach ($mappingValidation['orphan_mappings'] as $row): ?>
                                                <tr>
                                                    <td><?php echo machine_h($row['finger_id']); ?></td>
                                                    <td><?php echo machine_h($row['label']); ?></td>
                                                    <td><?php echo machine_h($row['mapped_user_id']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (
                                empty($mappingValidation['machine_unmapped']) &&
                                empty($mappingValidation['mapped_missing_on_machine']) &&
                                empty($mappingValidation['name_mismatches']) &&
                                empty($mappingValidation['orphan_mappings'])
                            ): ?>
                                <div class="alert alert-soft-success mt-4 mb-0">Machine user list and local fingerprint mapping are aligned.</div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card stretch stretch-full machine-users-card">
                    <div class="card-header"><h6 class="card-title mb-0">Users on Machine</h6></div>
                    <div class="card-body">
                        <?php if ($cloudRuntime): ?>
                            <div class="alert alert-warning">
                                Direct machine user commands are disabled on cloud runtime. Run the bridge worker on your computer in Router 2, then process logs via ingest.
                            </div>
                        <?php endif; ?>
                        <form method="post" class="row g-2 align-items-end mb-3">
                            <input type="hidden" name="machine_action" value="get_user">
                            <div class="col-sm-6">
                                <label class="form-label">User ID on F20H</label>
                                <input type="number" name="user_id" class="form-control" value="<?php echo machine_h($selectedUserId); ?>" min="1">
                            </div>
                            <div class="col-sm-6">
                                <button type="submit" class="btn btn-primary w-100" <?php echo $cloudRuntime ? 'disabled title="Unavailable in cloud runtime"' : ''; ?>>Load User Record</button>
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
                                                        <div class="machine-inline-actions">
                                                            <form method="post" class="d-inline">
                                                                <input type="hidden" name="machine_action" value="get_user">
                                                                <input type="hidden" name="user_id" value="<?php echo machine_h($rowUserId); ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-primary">Load</button>
                                                            </form>
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this fingerprint record from the F20H machine? This removes the machine user entry.');">
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

                        <form method="post" class="mt-2" onsubmit="return confirm('Delete this fingerprint record from the F20H machine? This removes the machine user entry.');">
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
                        <div class="quick-router-panel mb-4">
                            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                <div>
                                    <div class="fw-semibold">Quick Router Switch</div>
                                    <div class="text-muted fs-12">Choose a router preset, adjust any values you want, then save. BioTern will use these settings the next time you sync.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" id="copyConnectorToMachineBtn">Copy to Network Form</button>
                            </div>
                            <form method="post" class="row g-2 mt-1">
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
                                    <label class="form-label">Attendance File</label>
                                    <input type="text" name="connector_output_path" class="form-control" value="<?php echo machine_h($connectorOutputPath !== '' ? $connectorOutputPath : 'C:\\BioTern\\attendance.txt'); ?>" placeholder="C:\xampp\htdocs\BioTern\attendance.txt">
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
                                <div class="col-sm-6">
                                    <label class="form-label">Window Start</label>
                                    <input type="text" name="attendance_start_time" class="form-control" value="<?php echo machine_h($connectorStartTime); ?>" placeholder="08:00:00">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Window End</label>
                                    <input type="text" name="attendance_end_time" class="form-control" value="<?php echo machine_h($connectorEndTime); ?>" placeholder="19:00:00">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Max Early Arrival</label>
                                    <input type="number" name="max_early_arrival_minutes" class="form-control" value="<?php echo machine_h((string)$maxEarlyArrivalMinutes); ?>" min="0" max="360">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Max Late Departure</label>
                                    <input type="number" name="max_late_departure_minutes" class="form-control" value="<?php echo machine_h((string)$maxLateDepartureMinutes); ?>" min="0" max="360">
                                </div>
                                <div class="col-12 d-flex flex-wrap gap-2">
                                    <button type="submit" class="btn btn-primary">Save Quick Router Settings</button>
                                    <small class="text-muted align-self-center">Recommended baseline: 08:00:00 to 19:00:00, 10-minute duplicate guard, 120-minute early/late safety window.</small>
                                </div>
                            </form>
                        </div>

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
                        </div>
                    </div>
                    <div class="card-body p-0">
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
                                                <td style="min-width: 320px;"><code><?php echo machine_h((string)($rawLog['raw_data'] ?? '')); ?></code></td>
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
                    <div class="card-header"><h6 class="card-title mb-0">Sync Attempts</h6></div>
                    <div class="card-body p-0">
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

            <?php if ($isAdmin): ?>
                <div class="col-xl-6">
                    <div class="card stretch stretch-full">
                        <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-start">
                            <div>
                                <h6 class="card-title mb-0">Recent Anomalies</h6>
                            </div>
                            <form method="post" class="m-0">
                                <input type="hidden" name="machine_action" value="close_resolved_anomalies">
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Close Resolved</button>
                            </form>
                        </div>
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

            <div class="col-12">
                <div class="card stretch stretch-full">
                    <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-start">
                        <div>
                            <h6 class="card-title mb-0">Attendance Repair</h6>
                            <div class="text-muted fs-12 mt-1">Audit raw biometric dates, detect suspicious days, and rebuild attendance safely from raw logs when needed.</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold"><?php echo machine_h((string)$openAnomalyCount); ?> open anomalies</div>
                            <small class="text-muted">Use dry run first, then apply repair if suspicious dates are reported.</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-8">
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold mb-2">Recommended routine</div>
                                    <div class="text-muted fs-12">
                                        1. Sync the machine.
                                        2. Check anomalies and raw logs.
                                        3. Run Dry Run Repair.
                                        4. If suspicious dates are found, run Apply Repair.
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="machine_action" value="repair_attendance_dry_run">
                                        <button type="submit" class="btn btn-outline-primary">Dry Run Repair</button>
                                    </form>
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="machine_action" value="repair_attendance_apply">
                                        <button type="submit" class="btn btn-primary">Apply Repair</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php $lastRepairRows = machine_repair_rows($lastRepairResult); ?>
                        <?php if ($lastRepairRows !== []): ?>
                            <div class="table-responsive mt-3 border rounded">
                                <table class="table table-sm mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Raw</th>
                                            <th>Rows</th>
                                            <th>Slots</th>
                                            <th>Suspicious</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lastRepairRows as $repairRow): ?>
                                            <tr>
                                                <td><?php echo machine_h($repairRow['date']); ?></td>
                                                <td><?php echo machine_h((string)$repairRow['raw_count']); ?></td>
                                                <td><?php echo machine_h((string)$repairRow['attendance_rows']); ?></td>
                                                <td><?php echo machine_h((string)$repairRow['filled_slots']); ?></td>
                                                <td><?php echo machine_h((string)$repairRow['suspicious_count']); ?></td>
                                                <td>
                                                    <?php if ($repairRow['rebuilt']): ?>
                                                        <span class="badge bg-soft-success text-success">Rebuilt</span>
                                                        <div class="fs-12 text-muted mt-1"><?php echo machine_h((string)$repairRow['changes']); ?> change(s)</div>
                                                    <?php elseif ($repairRow['needs_rebuild']): ?>
                                                        <span class="badge bg-soft-warning text-warning">Needs Repair</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-soft-secondary text-secondary">Clean</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    const presetSelect = document.getElementById('routerPresetSelect');
    const copyButton = document.getElementById('copyConnectorToMachineBtn');
    const connectorFields = {
        ip: document.getElementById('connectorIpField'),
        gateway: document.getElementById('connectorGatewayField'),
        mask: document.getElementById('connectorMaskField'),
        port: document.getElementById('connectorPortField')
    };
    const machineFields = {
        ip: document.getElementById('machineIpField'),
        gateway: document.getElementById('machineGatewayField'),
        mask: document.getElementById('machineMaskField'),
        port: document.getElementById('machinePortField')
    };

    function applyPreset() {
        if (!presetSelect) {
            return;
        }

        const option = presetSelect.options[presetSelect.selectedIndex];
        if (!option) {
            return;
        }

        if (connectorFields.ip) {
            connectorFields.ip.value = option.dataset.ip || '';
        }
        if (connectorFields.gateway) {
            connectorFields.gateway.value = option.dataset.gateway || '';
        }
        if (connectorFields.mask) {
            connectorFields.mask.value = option.dataset.mask || '';
        }
        if (connectorFields.port) {
            connectorFields.port.value = option.dataset.port || '5001';
        }
    }

    function copyConnectorToMachine() {
        if (machineFields.ip && connectorFields.ip) {
            machineFields.ip.value = connectorFields.ip.value;
        }
        if (machineFields.gateway && connectorFields.gateway) {
            machineFields.gateway.value = connectorFields.gateway.value;
        }
        if (machineFields.mask && connectorFields.mask) {
            machineFields.mask.value = connectorFields.mask.value;
        }
        if (machineFields.port && connectorFields.port) {
            machineFields.port.value = connectorFields.port.value;
        }
    }

    if (presetSelect) {
        presetSelect.addEventListener('change', applyPreset);
    }

    if (copyButton) {
        copyButton.addEventListener('click', copyConnectorToMachine);
    }

});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
