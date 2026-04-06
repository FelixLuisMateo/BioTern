<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once __DIR__ . '/biometric_auto_import.php';

if (!function_exists('rebuild_biometric_parse_time')) {
    function rebuild_biometric_parse_time($value): ?int
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? null : $timestamp;
    }
}

if (!function_exists('rebuild_biometric_total_hours')) {
    function rebuild_biometric_total_hours(array $row): float
    {
        $totalSeconds = 0;
        $pairs = [
            ['morning_time_in', 'morning_time_out'],
            ['afternoon_time_in', 'afternoon_time_out'],
        ];

        foreach ($pairs as $pair) {
            $start = rebuild_biometric_parse_time($row[$pair[0]] ?? null);
            $end = rebuild_biometric_parse_time($row[$pair[1]] ?? null);
            if ($start === null || $end === null || $end <= $start) {
                continue;
            }

            $totalSeconds += ($end - $start);
        }

        $breakIn = rebuild_biometric_parse_time($row['break_time_in'] ?? null);
        $breakOut = rebuild_biometric_parse_time($row['break_time_out'] ?? null);
        if ($breakIn !== null && $breakOut !== null && $breakOut > $breakIn) {
            $totalSeconds -= ($breakOut - $breakIn);
        }

        if ($totalSeconds < 0) {
            $totalSeconds = 0;
        }

        return round($totalSeconds / 3600, 2);
    }
}

if (!function_exists('rebuild_biometric_attendance_for_date')) {
    function rebuild_biometric_attendance_for_date(mysqli $conn, string $targetDate): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
            throw new InvalidArgumentException('Invalid rebuild date.');
        }

        $machineConfig = loadBiometricMachineConfig();
        $fingerprintMap = buildFingerprintStudentMap($conn);
        $fingerprintUserMap = buildFingerprintUserMap($conn);
        $studentScheduleMap = buildStudentAttendanceScheduleMap($conn);
        $clockTypeColumns = [
            1 => 'morning_time_in',
            2 => 'morning_time_out',
            3 => 'afternoon_time_in',
            4 => 'afternoon_time_out',
        ];

        $stmt = $conn->prepare("SELECT id, raw_data FROM biometric_raw_logs WHERE raw_data LIKE ? ORDER BY id ASC");
        $like = '%"time":"' . $targetDate . ' %';
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $res = $stmt->get_result();

        $events = [];
        while ($row = $res->fetch_assoc()) {
            $entry = json_decode((string)$row['raw_data'], true);
            if (!is_array($entry)) {
                continue;
            }

            $fingerId = isset($entry['finger_id']) ? (int)$entry['finger_id'] : (isset($entry['id']) ? (int)$entry['id'] : 0);
            $clockType = isset($entry['type']) ? (int)$entry['type'] : 0;
            $datetime = trim((string)($entry['time'] ?? ''));
            if ($fingerId <= 0 || $clockType <= 0 || $datetime === '') {
                continue;
            }

            $date = substr($datetime, 0, 10);
            $time = substr($datetime, 11, 8);
            if ($date !== $targetDate) {
                continue;
            }
            if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                continue;
            }

            $studentId = (int)($fingerprintMap[$fingerId] ?? 0);
            if ($studentId <= 0) {
                continue;
            }

            $events[] = [
                'log_id' => (int)$row['id'],
                'student_id' => $studentId,
                'finger_id' => $fingerId,
                'user_id' => (int)($fingerprintUserMap[$fingerId] ?? 0),
                'date' => $date,
                'time' => $time,
                'clock_type' => $clockType,
                'hint_column' => $clockTypeColumns[$clockType] ?? null,
            ];
        }
        $stmt->close();

        $delete = $conn->prepare("DELETE FROM attendances WHERE attendance_date = ? AND source = 'biometric'");
        $delete->bind_param('s', $targetDate);
        $delete->execute();
        $deletedRows = $delete->affected_rows;
        $delete->close();

        $changed = 0;
        foreach ($events as $event) {
            $syncResult = syncAttendanceFromBiometricLog(
                $conn,
                (int)$event['student_id'],
                (string)$event['date'],
                (string)$event['time'],
                (int)$event['clock_type'],
                $event['hint_column'],
                (int)$event['log_id'],
                (int)$event['finger_id'],
                (int)$event['user_id'],
                $machineConfig,
                $studentScheduleMap[(int)$event['student_id']] ?? section_schedule_from_row([])
            );
            if (!empty($syncResult['changed'])) {
                $changed++;
            }
        }

        $sel = $conn->prepare("
            SELECT id, morning_time_in, morning_time_out, break_time_in, break_time_out, afternoon_time_in, afternoon_time_out
            FROM attendances
            WHERE attendance_date = ? AND source = 'biometric'
        ");
        $sel->bind_param('s', $targetDate);
        $sel->execute();
        $rows = $sel->get_result()->fetch_all(MYSQLI_ASSOC);
        $sel->close();

        $upd = $conn->prepare("UPDATE attendances SET total_hours = ?, updated_at = NOW() WHERE id = ?");
        foreach ($rows as $row) {
            $attendanceId = (int)($row['id'] ?? 0);
            if ($attendanceId <= 0) {
                continue;
            }
            $hours = rebuild_biometric_total_hours($row);
            $upd->bind_param('di', $hours, $attendanceId);
            $upd->execute();
        }
        $upd->close();

        return [
            'date' => $targetDate,
            'deleted_rows' => $deletedRows,
            'raw_events_replayed' => count($events),
            'attendance_changes_applied' => $changed,
        ];
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $targetDate = isset($argv[1]) ? trim((string)$argv[1]) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate)) {
        fwrite(STDERR, "Usage: php BioTern_unified/tools/rebuild_biometric_date.php YYYY-MM-DD\n");
        exit(1);
    }

    $result = rebuild_biometric_attendance_for_date($conn, $targetDate);
    echo "Rebuilt biometric attendance for {$result['date']}\n";
    echo "Deleted rows: {$result['deleted_rows']}\n";
    echo "Raw events replayed: {$result['raw_events_replayed']}\n";
    echo "Attendance changes applied: {$result['attendance_changes_applied']}\n";
}
