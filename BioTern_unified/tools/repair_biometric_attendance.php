<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/biometric_auto_import.php';
require_once __DIR__ . '/rebuild_biometric_date.php';

if (!function_exists('repair_biometric_collect_dates')) {
    function repair_biometric_collect_dates(mysqli $conn): array
    {
        $dates = [];
        $res = $conn->query("SELECT id, raw_data FROM biometric_raw_logs ORDER BY id ASC");
        if (!$res instanceof mysqli_result) {
            return [];
        }

        while ($row = $res->fetch_assoc()) {
            $entry = json_decode((string)($row['raw_data'] ?? ''), true);
            if (!is_array($entry)) {
                continue;
            }

            $datetime = trim((string)($entry['time'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $datetime, $matches)) {
                $dates[$matches[0]] = true;
            }
        }
        $res->close();

        $dateList = array_keys($dates);
        sort($dateList);
        return $dateList;
    }
}

if (!function_exists('repair_biometric_date_audit')) {
    function repair_biometric_date_audit(mysqli $conn, string $date): array
    {
        $machineConfig = loadBiometricMachineConfig();
        $studentScheduleMap = buildStudentAttendanceScheduleMap($conn);
        $fingerprintMap = buildFingerprintStudentMap($conn);

        $rawCount = 0;
        $suspiciousCount = 0;
        $like = '%"time":"' . $date . ' %';
        $stmt = $conn->prepare("SELECT raw_data FROM biometric_raw_logs WHERE raw_data LIKE ? ORDER BY id ASC");
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $entry = json_decode((string)($row['raw_data'] ?? ''), true);
            if (!is_array($entry)) {
                continue;
            }

            $datetime = trim((string)($entry['time'] ?? ''));
            if (substr($datetime, 0, 10) !== $date) {
                continue;
            }

            $time = substr($datetime, 11, 8);
            $fingerId = isset($entry['finger_id']) ? (int)$entry['finger_id'] : (isset($entry['id']) ? (int)$entry['id'] : 0);
            $studentId = (int)($fingerprintMap[$fingerId] ?? 0);
            $schedule = section_schedule_effective_day(
                $studentScheduleMap[$studentId] ?? section_schedule_from_row([]),
                $date,
                biometricMachineSchoolHours($machineConfig)
            );

            $rawCount++;
            if (!biometricMachineIsWithinHardWindow($time, $machineConfig, $schedule)) {
                $suspiciousCount++;
            }
        }
        $stmt->close();

        $attendanceRows = 0;
        $filledSlots = 0;
        $rowRes = $conn->query("
            SELECT morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out
            FROM attendances
            WHERE attendance_date = '" . $conn->real_escape_string($date) . "' AND source = 'biometric'
        ");
        if ($rowRes instanceof mysqli_result) {
            while ($row = $rowRes->fetch_assoc()) {
                $attendanceRows++;
                foreach (['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out'] as $column) {
                    $value = trim((string)($row[$column] ?? ''));
                    if ($value !== '' && $value !== '00:00:00') {
                        $filledSlots++;
                    }
                }
            }
            $rowRes->close();
        }

        return [
            'date' => $date,
            'raw_count' => $rawCount,
            'attendance_rows' => $attendanceRows,
            'filled_slots' => $filledSlots,
            'suspicious_count' => $suspiciousCount,
            'needs_rebuild' => $suspiciousCount > 0 || ($rawCount > 0 && $filledSlots === 0) || $rawCount > max(0, $filledSlots * 2),
        ];
    }
}

if (!function_exists('repair_biometric_attendance')) {
    function repair_biometric_attendance(mysqli $conn, bool $dryRun = false): array
    {
        $dates = repair_biometric_collect_dates($conn);
        $audits = [];
        $rebuilt = [];

        foreach ($dates as $date) {
            $audit = repair_biometric_date_audit($conn, $date);
            $audits[] = $audit;
            if (!$audit['needs_rebuild']) {
                continue;
            }

            if (!$dryRun) {
                $rebuilt[] = rebuild_biometric_attendance_for_date($conn, $date);
            }
        }

        return [
            'audits' => $audits,
            'rebuilt' => $rebuilt,
        ];
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $dryRun = in_array('--dry-run', $argv ?? [], true);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, defined('DB_PORT') ? DB_PORT : 3306);
    if ($conn->connect_error) {
        fwrite(STDERR, $conn->connect_error . PHP_EOL);
        exit(1);
    }

    $result = repair_biometric_attendance($conn, $dryRun);
    foreach ($result['audits'] as $audit) {
        echo $audit['date']
            . ' | raw=' . $audit['raw_count']
            . ' | rows=' . $audit['attendance_rows']
            . ' | slots=' . $audit['filled_slots']
            . ' | suspicious=' . $audit['suspicious_count']
            . ' | rebuild=' . ($audit['needs_rebuild'] ? 'yes' : 'no')
            . PHP_EOL;
    }
    if (!$dryRun) {
        echo 'Rebuilt dates: ' . count($result['rebuilt']) . PHP_EOL;
    }
}
