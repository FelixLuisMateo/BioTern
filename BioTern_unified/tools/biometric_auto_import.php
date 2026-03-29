<?php
// BioTern_unified/tools/biometric_auto_import.php
// Imports new machine logs into biometric_raw_logs and reconciles them into attendances.
require_once __DIR__ . '/biometric_ops.php';
require_once dirname(__DIR__) . '/lib/section_schedule.php';

if (!function_exists('run_biometric_auto_import')) {
    function run_biometric_auto_import(?string $attendanceFile = null): string
    {
        $stats = run_biometric_auto_import_stats($attendanceFile);
        return (string)$stats['message'];
    }
}

if (!function_exists('run_biometric_auto_import_stats')) {
    function run_biometric_auto_import_stats(?string $attendanceFile = null): array
    {
        $attendanceFile = $attendanceFile ?: (__DIR__ . '/../../attendance.txt');
        $machineConfig = loadBiometricMachineConfig();
        $host = 'localhost';
        $db_user = 'root';
        $db_password = '';
        $db_name = 'biotern_db';

        $conn = new mysqli($host, $db_user, $db_password, $db_name);
        if ($conn->connect_error) {
            throw new RuntimeException('Connection failed: ' . $conn->connect_error);
        }
        $conn->set_charset('utf8mb4');
        section_schedule_ensure_columns($conn);

        $conn->query("
            UPDATE attendances a
            LEFT JOIN students s_by_id ON s_by_id.id = a.student_id
            INNER JOIN students s_by_user ON s_by_user.user_id = a.student_id
            SET a.student_id = s_by_user.id
            WHERE a.source = 'biometric'
              AND s_by_id.id IS NULL
        ");

        $rawInserted = 0;
        $attendanceChanged = 0;
        $processedLogs = 0;
        $anomaliesFound = 0;

        if (file_exists($attendanceFile)) {
            $json = file_get_contents($attendanceFile);
            if ($json === false) {
                throw new RuntimeException('Failed to read attendance.txt.');
            }

            $trimmedJson = trim($json);
            if ($trimmedJson !== '' && $trimmedJson !== '[]') {
                $data = json_decode($json, true);
                if (!is_array($data)) {
                    throw new RuntimeException('Invalid attendance.txt format.');
                }

                $incomingSeen = [];
                foreach ($data as $entry) {
                    $raw = json_encode($entry);
                    if (!is_string($raw) || $raw === '{}' || $raw === '[]' || $raw === '') {
                        continue;
                    }

                    $fingerId = isset($entry['finger_id']) ? (int)$entry['finger_id'] : (isset($entry['id']) ? (int)$entry['id'] : 0);
                    $datetime = isset($entry['time']) ? trim((string)$entry['time']) : '';
                    $duplicateWindowMinutes = biometricMachineConfigInt($machineConfig, 'duplicateGuardMinutes', 10);

                    if ($fingerId > 0 && $datetime !== '' && isDuplicateRawBiometricEvent($conn, $incomingSeen, $fingerId, $datetime, $duplicateWindowMinutes)) {
                        continue;
                    }

                    $stmt = $conn->prepare("SELECT id FROM biometric_raw_logs WHERE raw_data = ?");
                    if ($stmt === false) {
                        throw new RuntimeException('Database error: failed to prepare raw log lookup. Error: ' . $conn->error);
                    }
                    $stmt->bind_param('s', $raw);
                    $stmt->execute();
                    $stmt->store_result();

                    if ($stmt->num_rows === 0) {
                        $stmt->close();
                        $ins = $conn->prepare("INSERT INTO biometric_raw_logs (raw_data, processed) VALUES (?, 0)");
                        if ($ins === false) {
                            throw new RuntimeException('Database error: failed to prepare raw log insert. Error: ' . $conn->error);
                        }
                        $ins->bind_param('s', $raw);
                        $ins->execute();
                        $ins->close();
                        $rawInserted++;
                        rememberAcceptedRawBiometricEvent($incomingSeen, $fingerId, $datetime);
                    } else {
                        $stmt->close();
                    }
                }
            }
        }

        $fingerprintMap = buildFingerprintStudentMap($conn);
        $fingerprintUserMap = buildFingerprintUserMap($conn);
        $studentScheduleMap = buildStudentAttendanceScheduleMap($conn);
        $dailyPunchCounts = [];
        $columns = [
            1 => 'morning_time_in',
            2 => 'morning_time_out',
            3 => 'afternoon_time_in',
            4 => 'afternoon_time_out',
        ];

        $events = [];
        $res = $conn->query("SELECT id, raw_data FROM biometric_raw_logs WHERE processed = 0 ORDER BY id ASC");
        if ($res && $res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $logId = (int)$row['id'];
                $entry = json_decode((string)$row['raw_data'], true);
                if (!is_array($entry)) {
                    markRawLogProcessed($conn, $logId);
                    continue;
                }

                $fingerId = isset($entry['finger_id']) ? (int)$entry['finger_id'] : (isset($entry['id']) ? (int)$entry['id'] : 0);
                $clockType = isset($entry['type']) ? (int)$entry['type'] : 0;
                $datetime = isset($entry['time']) ? trim((string)$entry['time']) : '';

                if ($fingerId <= 0 || $clockType <= 0 || $datetime === '' || !isset($columns[$clockType])) {
                    markRawLogProcessed($conn, $logId);
                    continue;
                }

                $studentId = $fingerprintMap[$fingerId] ?? 0;
                $mappedUserId = $fingerprintUserMap[$fingerId] ?? 0;
                if ($studentId <= 0) {
                    $anomaliesFound++;
                    biometric_ops_record_anomaly(
                        $conn,
                        $logId,
                        $fingerId,
                        $mappedUserId > 0 ? $mappedUserId : null,
                        null,
                        $mappedUserId > 0 ? 'mapped_user_missing_student' : 'unmapped_fingerprint',
                        'warning',
                        $datetime !== '' ? $datetime : null,
                        $mappedUserId > 0
                            ? 'Fingerprint is mapped to a user that has no linked student profile.'
                            : 'Fingerprint scan was received with no BioTern mapping.',
                        ['raw_data' => $entry]
                    );
                    markRawLogProcessed($conn, $logId);
                    continue;
                }

                $date = substr($datetime, 0, 10);
                $time = substr($datetime, 11, 8);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                    markRawLogProcessed($conn, $logId);
                    continue;
                }

                if (!isWithinConfiguredAttendanceWindow($time, $machineConfig)) {
                    $anomaliesFound++;
                    biometric_ops_record_anomaly(
                        $conn,
                        $logId,
                        $fingerId,
                        $mappedUserId > 0 ? $mappedUserId : null,
                        $studentId,
                        'outside_attendance_window',
                        'warning',
                        $datetime,
                        'Biometric punch is outside the configured attendance window.',
                        ['raw_data' => $entry]
                    );
                    markRawLogProcessed($conn, $logId);
                    continue;
                }

                $dailyKey = $studentId . '|' . $date;
                $dailyPunchCounts[$dailyKey] = ($dailyPunchCounts[$dailyKey] ?? 0) + 1;
                if ($dailyPunchCounts[$dailyKey] > 6) {
                    $anomaliesFound++;
                    biometric_ops_record_anomaly(
                        $conn,
                        $logId,
                        $fingerId,
                        $mappedUserId > 0 ? $mappedUserId : null,
                        $studentId,
                        'excessive_daily_punches',
                        'warning',
                        $datetime,
                        'Student has more biometric punches than expected for a single day.',
                        ['raw_data' => $entry, 'daily_count' => $dailyPunchCounts[$dailyKey]]
                    );
                }

                $events[] = [
                    'log_id' => $logId,
                    'student_id' => $studentId,
                    'finger_id' => $fingerId,
                    'user_id' => $mappedUserId,
                    'date' => $date,
                    'time' => $time,
                    'clock_type' => $clockType,
                    'hint_column' => $columns[$clockType] ?? null,
                ];
            }
            $res->close();
        }

        foreach ($events as $event) {
            $syncResult = syncAttendanceFromBiometricLog(
                $conn,
                (int)$event['student_id'],
                (string)$event['date'],
                (string)$event['time'],
                (int)$event['clock_type'],
                $event['hint_column'],
                (int)$event['log_id'],
                (int)($event['finger_id'] ?? 0),
                (int)($event['user_id'] ?? 0),
                $machineConfig,
                $studentScheduleMap[(int)$event['student_id']] ?? section_schedule_from_row([])
            );
            if (($syncResult['changed'] ?? false) === true) {
                $attendanceChanged++;
            }
            $anomaliesFound += (int)($syncResult['anomalies_found'] ?? 0);

            markRawLogProcessed($conn, (int)$event['log_id']);
            $processedLogs++;
        }

        $conn->close();
        return [
            'message' => "Biometric sync complete. Raw inserted: {$rawInserted}, logs processed: {$processedLogs}, attendance rows changed: {$attendanceChanged}, anomalies found: {$anomaliesFound}",
            'raw_inserted' => $rawInserted,
            'processed_logs' => $processedLogs,
            'attendance_changed' => $attendanceChanged,
            'anomalies_found' => $anomaliesFound,
        ];
    }
}

if (!function_exists('loadBiometricMachineConfig')) {
    function loadBiometricMachineConfig(): array
    {
        $configPath = __DIR__ . '/biometric_machine_config.json';
        if (!file_exists($configPath)) {
            return [];
        }

        $json = file_get_contents($configPath);
        if (!is_string($json) || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('isWithinConfiguredAttendanceWindow')) {
    function isWithinConfiguredAttendanceWindow(string $time, array $machineConfig): bool
    {
        $enabled = !empty($machineConfig['attendanceWindowEnabled']);
        if (!$enabled) {
            return true;
        }

        $start = trim((string)($machineConfig['attendanceStartTime'] ?? '08:00:00'));
        $end = trim((string)($machineConfig['attendanceEndTime'] ?? '20:00:00'));

        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $end)) {
            return true;
        }

        if ($start <= $end) {
            return $time >= $start && $time <= $end;
        }

        return $time >= $start || $time <= $end;
    }
}

if (!function_exists('buildFingerprintStudentMap')) {
    function buildFingerprintStudentMap(mysqli $conn): array
    {
        $conn->query("CREATE TABLE IF NOT EXISTS fingerprint_user_map (finger_id INT PRIMARY KEY, user_id INT NOT NULL)");

        $map = [];
        $sql = "
            SELECT m.finger_id, s.id AS student_id
            FROM fingerprint_user_map m
            INNER JOIN students s ON s.user_id = m.user_id
        ";
        $res = $conn->query($sql);
        if ($res && $res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $fingerId = (int)($row['finger_id'] ?? 0);
                $studentId = (int)($row['student_id'] ?? 0);
                if ($fingerId > 0 && $studentId > 0) {
                    $map[$fingerId] = $studentId;
                }
            }
            $res->close();
        }

        return $map;
    }
}

if (!function_exists('buildFingerprintUserMap')) {
    function buildFingerprintUserMap(mysqli $conn): array
    {
        $conn->query("CREATE TABLE IF NOT EXISTS fingerprint_user_map (finger_id INT PRIMARY KEY, user_id INT NOT NULL)");

        $map = [];
        $res = $conn->query("SELECT finger_id, user_id FROM fingerprint_user_map");
        if ($res && $res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $fingerId = (int)($row['finger_id'] ?? 0);
                $userId = (int)($row['user_id'] ?? 0);
                if ($fingerId > 0 && $userId > 0) {
                    $map[$fingerId] = $userId;
                }
            }
            $res->close();
        }

        return $map;
    }
}

if (!function_exists('buildStudentAttendanceScheduleMap')) {
    function buildStudentAttendanceScheduleMap(mysqli $conn): array
    {
        $map = [];
        $res = $conn->query("
            SELECT
                s.id AS student_id,
                sec.attendance_session,
                sec.schedule_time_in,
                sec.schedule_time_out,
                sec.late_after_time,
                sec.weekly_schedule_json
            FROM students s
            LEFT JOIN sections sec ON s.section_id = sec.id
        ");
        if ($res && $res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $studentId = (int)($row['student_id'] ?? 0);
                if ($studentId > 0) {
                    $map[$studentId] = section_schedule_from_row($row);
                }
            }
            $res->close();
        }

        return $map;
    }
}

if (!function_exists('rememberAcceptedRawBiometricEvent')) {
    function rememberAcceptedRawBiometricEvent(array &$incomingSeen, int $fingerId, string $datetime): void
    {
        if ($fingerId <= 0 || $datetime === '') {
            return;
        }

        $date = substr($datetime, 0, 10);
        $time = substr($datetime, 11, 8);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return;
        }

        $incomingSeen[$fingerId . '|' . $date][] = $time;
    }
}

if (!function_exists('isDuplicateRawBiometricEvent')) {
    function isDuplicateRawBiometricEvent(mysqli $conn, array $incomingSeen, int $fingerId, string $datetime, int $windowMinutes): bool
    {
        if ($fingerId <= 0 || $datetime === '') {
            return false;
        }

        $date = substr($datetime, 0, 10);
        $time = substr($datetime, 11, 8);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return false;
        }

        $seenKey = $fingerId . '|' . $date;
        foreach ($incomingSeen[$seenKey] ?? [] as $existingTime) {
            $minutesApart = minutesBetweenPunches($existingTime, $time);
            if ($minutesApart !== null && $minutesApart <= $windowMinutes) {
                return true;
            }
        }

        $likeDate = '%' . $conn->real_escape_string('"time":"' . $date . ' ') . '%';
        $res = $conn->query("SELECT raw_data FROM biometric_raw_logs WHERE raw_data LIKE '{$likeDate}' ORDER BY id DESC LIMIT 300");
        if (!($res instanceof mysqli_result)) {
            return false;
        }

        while ($row = $res->fetch_assoc()) {
            $entry = json_decode((string)($row['raw_data'] ?? ''), true);
            if (!is_array($entry)) {
                continue;
            }

            $existingFingerId = isset($entry['finger_id']) ? (int)$entry['finger_id'] : (isset($entry['id']) ? (int)$entry['id'] : 0);
            if ($existingFingerId !== $fingerId) {
                continue;
            }

            $existingDatetime = trim((string)($entry['time'] ?? ''));
            $existingDate = substr($existingDatetime, 0, 10);
            $existingTime = substr($existingDatetime, 11, 8);
            if ($existingDate !== $date || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $existingTime)) {
                continue;
            }

            $minutesApart = minutesBetweenPunches($existingTime, $time);
            if ($minutesApart !== null && $minutesApart <= $windowMinutes) {
                $res->close();
                return true;
            }
        }

        $res->close();
        return false;
    }
}

if (!function_exists('markRawLogProcessed')) {
    function markRawLogProcessed(mysqli $conn, int $logId): void
    {
        $stmt = $conn->prepare("UPDATE biometric_raw_logs SET processed = 1 WHERE id = ?");
        if ($stmt === false) {
            throw new RuntimeException('Database error: failed to prepare raw log update. Error: ' . $conn->error);
        }
        $stmt->bind_param('i', $logId);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('syncAttendanceFromBiometricLog')) {
    function syncAttendanceFromBiometricLog(mysqli $conn, int $studentId, string $date, string $time, int $clockType, ?string $hintColumn, ?int $rawLogId = null, ?int $fingerId = null, ?int $userId = null, array $machineConfig = [], array $studentSchedule = []): array
    {
        consolidateDuplicateBiometricAttendanceRows($conn, $studentId, $date);

        $existing = $conn->prepare("
            SELECT id, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out
            FROM attendances
            WHERE student_id = ? AND attendance_date = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($existing === false) {
            throw new RuntimeException('Database error: failed to prepare attendance lookup. Error: ' . $conn->error);
        }
        $existing->bind_param('is', $studentId, $date);
        $existing->execute();
        $row = $existing->get_result()->fetch_assoc();
        $existing->close();

        $attendanceId = (int)($row['id'] ?? 0);
        if (!is_array($row)) {
            $row = [];
        }
        $row['attendance_date'] = $date;

        $duplicateWindowMinutes = biometricMachineConfigInt($machineConfig, 'duplicateGuardMinutes', 10);
        if (isDuplicateBiometricPunch($row, $time, $duplicateWindowMinutes)) {
            biometric_ops_record_anomaly(
                $conn,
                $rawLogId,
                $fingerId,
                $userId,
                $studentId,
                'duplicate_punch_within_' . $duplicateWindowMinutes . '_minutes',
                'warning',
                $date . ' ' . $time,
                'Biometric punch was ignored because it was too close to an existing punch.',
                ['attendance_date' => $date, 'time' => $time, 'window_minutes' => $duplicateWindowMinutes]
            );
            return ['changed' => false, 'anomalies_found' => 1];
        }

        $column = resolveAttendanceColumnForPunch($row, $clockType, $hintColumn, $time, $machineConfig, $studentSchedule);
        if ($column === null) {
            biometric_ops_record_anomaly(
                $conn,
                $rawLogId,
                $fingerId,
                $userId,
                $studentId,
                'unassigned_biometric_punch',
                'warning',
                $date . ' ' . $time,
                'Biometric punch could not be assigned to an attendance slot.',
                ['attendance_date' => $date, 'time' => $time]
            );
            return ['changed' => false, 'anomalies_found' => 1];
        }

        if ($attendanceId > 0) {
            $currentValue = $row[$column] ?? null;
            if ($currentValue !== null && $currentValue !== '' && $currentValue !== '00:00:00') {
                biometric_ops_record_anomaly(
                    $conn,
                    $rawLogId,
                    $fingerId,
                    $userId,
                    $studentId,
                    'slot_already_filled',
                    'warning',
                    $date . ' ' . $time,
                    'Biometric punch matched an attendance slot that was already filled.',
                    ['attendance_date' => $date, 'time' => $time, 'column' => $column]
                );
                return ['changed' => false, 'anomalies_found' => 1];
            }

            $update = $conn->prepare("UPDATE attendances SET $column = ?, source = 'biometric', updated_at = NOW() WHERE id = ?");
            if ($update === false) {
                throw new RuntimeException('Database error: failed to prepare attendance update. Error: ' . $conn->error);
            }
            $update->bind_param('si', $time, $attendanceId);
            $update->execute();
            $affected = $update->affected_rows > 0;
            $update->close();
            return ['changed' => $affected, 'anomalies_found' => 0];
        }

        $insert = $conn->prepare("INSERT INTO attendances (student_id, attendance_date, $column, source, status, created_at, updated_at) VALUES (?, ?, ?, 'biometric', 'pending', NOW(), NOW())");
        if ($insert === false) {
            throw new RuntimeException('Database error: failed to prepare attendance insert. Error: ' . $conn->error);
        }
        $insert->bind_param('iss', $studentId, $date, $time);
        $insert->execute();
        $affected = $insert->affected_rows > 0;
        $insert->close();
        return ['changed' => $affected, 'anomalies_found' => 0];
    }
}

if (!function_exists('resolveAttendanceColumnForPunch')) {
    function resolveAttendanceColumnForPunch(array $attendanceRow, int $clockType, ?string $hintColumn, string $incomingTime, array $machineConfig = [], array $studentSchedule = []): ?string
    {
        $orderedColumns = ['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out'];
        $effectiveSchedule = section_schedule_for_date($studentSchedule, (string)($attendanceRow['attendance_date'] ?? ''));

        // The F20H logs currently arrive as generic punches with type=1, so advance through slots in order.
        if ($clockType === 1) {
            $session = section_schedule_normalize_session((string)($effectiveSchedule['attendance_session'] ?? 'whole_day'));
            if (section_schedule_prefers_afternoon_entry($effectiveSchedule)) {
                if (trim((string)($attendanceRow['afternoon_time_in'] ?? '')) === '') {
                    return 'afternoon_time_in';
                }
                if (trim((string)($attendanceRow['afternoon_time_out'] ?? '')) === '') {
                    $minutesSinceLast = minutesBetweenPunches((string)($attendanceRow['afternoon_time_in'] ?? ''), $incomingTime);
                    $slotAdvanceMinimumMinutes = biometricMachineConfigInt($machineConfig, 'slotAdvanceMinimumMinutes', 10);
                    return ($minutesSinceLast !== null && $minutesSinceLast < $slotAdvanceMinimumMinutes) ? null : 'afternoon_time_out';
                }
                return null;
            }

            if ($session === 'morning_only') {
                if (trim((string)($attendanceRow['morning_time_in'] ?? '')) === '') {
                    return 'morning_time_in';
                }
                if (trim((string)($attendanceRow['morning_time_out'] ?? '')) === '') {
                    $minutesSinceLast = minutesBetweenPunches((string)($attendanceRow['morning_time_in'] ?? ''), $incomingTime);
                    $slotAdvanceMinimumMinutes = biometricMachineConfigInt($machineConfig, 'slotAdvanceMinimumMinutes', 10);
                    return ($minutesSinceLast !== null && $minutesSinceLast < $slotAdvanceMinimumMinutes) ? null : 'morning_time_out';
                }
                return null;
            }

            if ($session === 'whole_day') {
                $slotAdvanceMinimumMinutes = biometricMachineConfigInt($machineConfig, 'slotAdvanceMinimumMinutes', 10);
                $middayBoundary = resolveWholeDayAttendanceSplitTime($effectiveSchedule);
                $lateDayBoundary = resolveWholeDayAttendanceExitTime($effectiveSchedule);

                $morningIn = trim((string)($attendanceRow['morning_time_in'] ?? ''));
                $morningOut = trim((string)($attendanceRow['morning_time_out'] ?? ''));
                $afternoonIn = trim((string)($attendanceRow['afternoon_time_in'] ?? ''));
                $afternoonOut = trim((string)($attendanceRow['afternoon_time_out'] ?? ''));

                if ($morningIn === '' && $afternoonIn === '') {
                    return strcmp($incomingTime, $middayBoundary) >= 0 ? 'afternoon_time_in' : 'morning_time_in';
                }

                if ($morningIn !== '' && $morningOut === '') {
                    $minutesSinceMorningIn = minutesBetweenPunches($morningIn, $incomingTime);
                    if ($minutesSinceMorningIn !== null && $minutesSinceMorningIn < $slotAdvanceMinimumMinutes) {
                        return null;
                    }

                    if (strcmp($incomingTime, $lateDayBoundary) >= 0 && $afternoonOut === '') {
                        return 'afternoon_time_out';
                    }

                    return 'morning_time_out';
                }

                if ($afternoonIn !== '') {
                    $minutesSinceAfternoonIn = minutesBetweenPunches($afternoonIn, $incomingTime);
                    if ($minutesSinceAfternoonIn !== null && $minutesSinceAfternoonIn < $slotAdvanceMinimumMinutes) {
                        return null;
                    }

                    if ($afternoonOut === '') {
                        return 'afternoon_time_out';
                    }

                    return null;
                }

                if ($afternoonIn === '') {
                    if (strcmp($incomingTime, $lateDayBoundary) >= 0 && $afternoonOut === '') {
                        return 'afternoon_time_out';
                    }
                    return 'afternoon_time_in';
                }
            }

            $lastTime = lastBiometricPunchTime($attendanceRow);
            if ($lastTime !== null) {
                $minutesSinceLast = minutesBetweenPunches($lastTime, $incomingTime);
                $slotAdvanceMinimumMinutes = biometricMachineConfigInt($machineConfig, 'slotAdvanceMinimumMinutes', 10);
                if ($minutesSinceLast !== null && $minutesSinceLast < $slotAdvanceMinimumMinutes) {
                    return null;
                }
            }

            foreach ($orderedColumns as $column) {
                $value = trim((string)($attendanceRow[$column] ?? ''));
                if ($value === '' || $value === '00:00:00') {
                    return $column;
                }
            }

            return null;
        }

        if ($hintColumn !== null) {
            return $hintColumn;
        }

        return null;
    }
}

if (!function_exists('resolveSequentialAttendanceColumn')) {
    function resolveSequentialAttendanceColumn(array $attendanceRow, array $columns, string $incomingTime, int $slotAdvanceMinimumMinutes): ?string
    {
        $previousValue = null;
        foreach ($columns as $column) {
            $value = trim((string)($attendanceRow[$column] ?? ''));
            if ($value === '' || $value === '00:00:00') {
                if ($previousValue !== null) {
                    $minutesSinceLast = minutesBetweenPunches($previousValue, $incomingTime);
                    if ($minutesSinceLast !== null && $minutesSinceLast < $slotAdvanceMinimumMinutes) {
                        return null;
                    }
                }
                return $column;
            }
            $previousValue = $value;
        }

        return null;
    }
}

if (!function_exists('resolveWholeDayAttendanceSplitTime')) {
    function resolveWholeDayAttendanceSplitTime(array $schedule): string
    {
        $scheduledIn = section_schedule_normalize_time_input((string)($schedule['schedule_time_in'] ?? ''));
        $scheduledOut = section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? ''));

        if ($scheduledIn !== null && strcmp($scheduledIn, '12:00:00') >= 0) {
            return '12:00:00';
        }

        if ($scheduledOut !== null && strcmp($scheduledOut, '12:00:00') <= 0) {
            return '12:00:00';
        }

        return '12:00:00';
    }
}

if (!function_exists('resolveWholeDayAttendanceExitTime')) {
    function resolveWholeDayAttendanceExitTime(array $schedule): string
    {
        $scheduledOut = section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? ''));
        if ($scheduledOut !== null && strcmp($scheduledOut, '15:00:00') >= 0) {
            return substr($scheduledOut, 0, 8);
        }

        return '15:00:00';
    }
}

if (!function_exists('isDuplicateBiometricPunch')) {
    function isDuplicateBiometricPunch(array $attendanceRow, string $time, int $windowMinutes = 5): bool
    {
        $currentTs = strtotime($time);
        if ($currentTs === false) {
            return true;
        }

        foreach (['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out'] as $column) {
            $value = trim((string)($attendanceRow[$column] ?? ''));
            if ($value === '' || $value === '00:00:00') {
                continue;
            }

            $existingTs = strtotime($value);
            if ($existingTs === false) {
                continue;
            }

            $deltaMinutes = abs($currentTs - $existingTs) / 60;
            if ($deltaMinutes <= $windowMinutes) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('minutesBetweenPunches')) {
    function minutesBetweenPunches(string $from, string $to): ?float
    {
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if ($fromTs === false || $toTs === false) {
            return null;
        }

        return abs($toTs - $fromTs) / 60;
    }
}

if (!function_exists('biometricMachineConfigInt')) {
    function biometricMachineConfigInt(array $machineConfig, string $key, int $default): int
    {
        $value = isset($machineConfig[$key]) ? (int)$machineConfig[$key] : $default;
        return max(1, $value);
    }
}

if (!function_exists('lastBiometricPunchTime')) {
    function lastBiometricPunchTime(array $attendanceRow): ?string
    {
        $orderedColumns = ['afternoon_time_out', 'afternoon_time_in', 'morning_time_out', 'morning_time_in'];
        foreach ($orderedColumns as $column) {
            $value = trim((string)($attendanceRow[$column] ?? ''));
            if ($value !== '' && $value !== '00:00:00') {
                return $value;
            }
        }

        return null;
    }
}

if (!function_exists('consolidateDuplicateBiometricAttendanceRows')) {
    function consolidateDuplicateBiometricAttendanceRows(mysqli $conn, int $studentId, string $date): void
    {
        $stmt = $conn->prepare("
            SELECT id, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out, status, remarks
            FROM attendances
            WHERE student_id = ? AND attendance_date = ? AND source = 'biometric'
            ORDER BY id ASC
        ");
        if ($stmt === false) {
            throw new RuntimeException('Database error: failed to prepare biometric duplicate lookup. Error: ' . $conn->error);
        }
        $stmt->bind_param('is', $studentId, $date);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (count($rows) <= 1) {
            return;
        }

        $keepRow = $rows[0];
        foreach ($rows as $row) {
            if (shouldPreferBiometricAttendanceRow($row, $keepRow)) {
                $keepRow = $row;
            }
        }

        $keepId = (int)($keepRow['id'] ?? 0);
        if ($keepId <= 0) {
            return;
        }

        $mergedMorningIn = chooseBiometricAttendanceValue($rows, 'morning_time_in', 'min');
        $mergedMorningOut = chooseBiometricAttendanceValue($rows, 'morning_time_out', 'max');
        $mergedAfternoonIn = chooseBiometricAttendanceValue($rows, 'afternoon_time_in', 'min');
        $mergedAfternoonOut = chooseBiometricAttendanceValue($rows, 'afternoon_time_out', 'max');
        $mergedStatus = trim((string)($keepRow['status'] ?? 'pending'));
        $mergedRemarks = (string)($keepRow['remarks'] ?? '');

        $update = $conn->prepare("
            UPDATE attendances
            SET morning_time_in = ?,
                morning_time_out = ?,
                afternoon_time_in = ?,
                afternoon_time_out = ?,
                status = ?,
                remarks = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        if ($update === false) {
            throw new RuntimeException('Database error: failed to prepare biometric duplicate merge update. Error: ' . $conn->error);
        }
        $update->bind_param(
            'ssssssi',
            $mergedMorningIn,
            $mergedMorningOut,
            $mergedAfternoonIn,
            $mergedAfternoonOut,
            $mergedStatus,
            $mergedRemarks,
            $keepId
        );
        $update->execute();
        $update->close();

        $delete = $conn->prepare("DELETE FROM attendances WHERE id = ?");
        if ($delete === false) {
            throw new RuntimeException('Database error: failed to prepare biometric duplicate delete. Error: ' . $conn->error);
        }

        for ($i = 1; $i < count($rows); $i++) {
            $deleteId = (int)($rows[$i]['id'] ?? 0);
            if ($deleteId <= 0 || $deleteId === $keepId) {
                continue;
            }
            $delete->bind_param('i', $deleteId);
            $delete->execute();
        }

        $delete->close();
    }
}

if (!function_exists('shouldPreferBiometricAttendanceRow')) {
    function shouldPreferBiometricAttendanceRow(array $candidate, array $current): bool
    {
        $candidateScore = biometricAttendanceFilledSlotScore($candidate);
        $currentScore = biometricAttendanceFilledSlotScore($current);
        if ($candidateScore !== $currentScore) {
            return $candidateScore > $currentScore;
        }

        return (int)($candidate['id'] ?? 0) < (int)($current['id'] ?? 0);
    }
}

if (!function_exists('biometricAttendanceFilledSlotScore')) {
    function biometricAttendanceFilledSlotScore(array $row): int
    {
        $score = 0;
        foreach (['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out'] as $column) {
            $value = trim((string)($row[$column] ?? ''));
            if ($value !== '' && $value !== '00:00:00') {
                $score++;
            }
        }

        return $score;
    }
}

if (!function_exists('chooseBiometricAttendanceValue')) {
    function chooseBiometricAttendanceValue(array $rows, string $column, string $mode = 'min'): ?string
    {
        $values = [];
        foreach ($rows as $row) {
            $value = trim((string)($row[$column] ?? ''));
            if ($value !== '' && $value !== '00:00:00') {
                $values[] = $value;
            }
        }

        if ($values === []) {
            return null;
        }

        usort($values, static function (string $left, string $right): int {
            return strcmp($left, $right);
        });

        return $mode === 'max' ? end($values) : $values[0];
    }
}

if (!function_exists('resetBiometricAttendanceDay')) {
    function resetBiometricAttendanceDay(mysqli $conn, int $studentId, string $date): void
    {
        consolidateDuplicateBiometricAttendanceRows($conn, $studentId, $date);

        $stmt = $conn->prepare("
            UPDATE attendances
            SET morning_time_in = NULL,
                morning_time_out = NULL,
                afternoon_time_in = NULL,
                afternoon_time_out = NULL,
                updated_at = NOW()
            WHERE student_id = ? AND attendance_date = ? AND source = 'biometric'
        ");
        if ($stmt === false) {
            throw new RuntimeException('Database error: failed to prepare biometric day reset. Error: ' . $conn->error);
        }
        $stmt->bind_param('is', $studentId, $date);
        $stmt->execute();
        $stmt->close();
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        echo run_biometric_auto_import() . "\n";
    } catch (Throwable $e) {
        http_response_code(500);
        echo $e->getMessage() . "\n";
    }
}
