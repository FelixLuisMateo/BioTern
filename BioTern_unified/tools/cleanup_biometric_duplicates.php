<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/biometric_auto_import.php';

if (!function_exists('cleanup_biometric_parse_event')) {
    function cleanup_biometric_parse_event(array $row): ?array
    {
        $entry = json_decode((string)($row['raw_data'] ?? ''), true);
        if (!is_array($entry)) {
            return null;
        }

        $fingerId = isset($entry['finger_id']) ? (int)$entry['finger_id'] : (isset($entry['id']) ? (int)$entry['id'] : 0);
        $datetime = trim((string)($entry['time'] ?? ''));
        $clockType = isset($entry['type']) ? (int)$entry['type'] : 0;
        if ($fingerId <= 0 || $datetime === '' || $clockType <= 0) {
            return null;
        }

        $date = substr($datetime, 0, 10);
        $time = substr($datetime, 11, 8);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return null;
        }

        return [
            'id' => (int)($row['id'] ?? 0),
            'finger_id' => $fingerId,
            'date' => $date,
            'time' => $time,
            'clock_type' => $clockType,
            'raw_data' => (string)($row['raw_data'] ?? ''),
        ];
    }
}

if (!function_exists('cleanup_biometric_compare')) {
    function cleanup_biometric_compare(array $left, array $right): int
    {
        $leftKey = $left['date'] . ' ' . $left['time'];
        $rightKey = $right['date'] . ' ' . $right['time'];
        $cmp = strcmp($leftKey, $rightKey);
        if ($cmp !== 0) {
            return $cmp;
        }

        return ($left['id'] ?? 0) <=> ($right['id'] ?? 0);
    }
}

if (!function_exists('cleanup_biometric_collect_duplicates')) {
    function cleanup_biometric_collect_duplicates(mysqli $conn, int $windowMinutes): array
    {
        $rows = [];
        $res = $conn->query("SELECT id, raw_data FROM biometric_raw_logs ORDER BY id ASC");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $event = cleanup_biometric_parse_event($row);
                if ($event !== null) {
                    $rows[] = $event;
                }
            }
            $res->close();
        }

        usort($rows, 'cleanup_biometric_compare');

        $lastAccepted = [];
        $duplicateIds = [];
        $affectedDates = [];

        foreach ($rows as $event) {
            $groupKey = $event['finger_id'] . '|' . $event['date'];
            $last = $lastAccepted[$groupKey] ?? null;
            if ($last !== null) {
                $minutesApart = minutesBetweenPunches((string)$last['time'], (string)$event['time']);
                if ($minutesApart !== null && $minutesApart <= $windowMinutes) {
                    $duplicateIds[] = (int)$event['id'];
                    $affectedDates[$event['date']] = true;
                    continue;
                }
            }

            $lastAccepted[$groupKey] = $event;
        }

        return [
            'duplicate_ids' => $duplicateIds,
            'affected_dates' => array_keys($affectedDates),
        ];
    }
}

if (!function_exists('cleanup_biometric_duplicate_logs')) {
    function cleanup_biometric_duplicate_logs(mysqli $conn, ?int $windowMinutes = null, bool $dryRun = false): array
    {
        $machineConfig = loadBiometricMachineConfig();
        $windowMinutes = $windowMinutes !== null ? max(1, $windowMinutes) : biometricMachineConfigInt($machineConfig, 'duplicateGuardMinutes', 10);

        $result = cleanup_biometric_collect_duplicates($conn, $windowMinutes);
        $duplicateIds = $result['duplicate_ids'];
        $affectedDates = $result['affected_dates'];

        if ($dryRun) {
            return [
                'window_minutes' => $windowMinutes,
                'duplicate_count' => count($duplicateIds),
                'deleted_count' => 0,
                'affected_dates' => $affectedDates,
            ];
        }

        $deleted = 0;
        if (!empty($duplicateIds)) {
            $delete = $conn->prepare("DELETE FROM biometric_raw_logs WHERE id = ?");
            if ($delete) {
                foreach ($duplicateIds as $id) {
                    $delete->bind_param('i', $id);
                    $delete->execute();
                    if ($delete->affected_rows > 0) {
                        $deleted++;
                    }
                }
                $delete->close();
            }
        }

        return [
            'window_minutes' => $windowMinutes,
            'duplicate_count' => count($duplicateIds),
            'deleted_count' => $deleted,
            'affected_dates' => $affectedDates,
        ];
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $dryRun = in_array('--dry-run', $argv, true);
    $result = cleanup_biometric_duplicate_logs($conn, null, $dryRun);
    if ($dryRun) {
        echo "Dry run only\n";
    }
    echo "Window minutes: {$result['window_minutes']}\n";
    echo ($dryRun ? 'Duplicate raw logs found: ' : 'Duplicate raw logs deleted: ') . ($dryRun ? $result['duplicate_count'] : $result['deleted_count']) . "\n";
    echo "Affected dates: " . implode(', ', $result['affected_dates']) . "\n";
}
