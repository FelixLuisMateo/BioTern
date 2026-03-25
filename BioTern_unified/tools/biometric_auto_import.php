<?php
// BioTern_unified/tools/biometric_auto_import.php
// Imports new machine logs into biometric_raw_logs and reconciles them into attendances.

if (!function_exists('run_biometric_auto_import')) {
    function run_biometric_auto_import(?string $attendanceFile = null): string
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

        $conn->query("
            UPDATE attendances a
            INNER JOIN students s ON s.user_id = a.student_id
            SET a.student_id = s.id
            WHERE a.source = 'biometric'
        ");

        $rawInserted = 0;
        $attendanceChanged = 0;
        $processedLogs = 0;

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

                foreach ($data as $entry) {
                    $raw = json_encode($entry);
                    if (!is_string($raw) || $raw === '{}' || $raw === '[]' || $raw === '') {
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
                    } else {
                        $stmt->close();
                    }
                }
            }
        }

        $fingerprintMap = buildFingerprintStudentMap($conn);
        $columns = [
            1 => 'morning_time_in',
            2 => 'morning_time_out',
            3 => 'afternoon_time_in',
            4 => 'afternoon_time_out',
        ];

        $events = [];
        $res = $conn->query("SELECT id, raw_data FROM biometric_raw_logs ORDER BY id ASC");
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
                if ($studentId <= 0) {
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
                    markRawLogProcessed($conn, $logId);
                    continue;
                }

                $events[] = [
                    'log_id' => $logId,
                    'student_id' => $studentId,
                    'date' => $date,
                    'time' => $time,
                    'clock_type' => $clockType,
                    'hint_column' => $columns[$clockType] ?? null,
                ];
            }
            $res->close();
        }

        $rebuildKeys = [];
        foreach ($events as $event) {
            $rebuildKeys[$event['student_id'] . '|' . $event['date']] = [
                'student_id' => $event['student_id'],
                'date' => $event['date'],
            ];
        }

        foreach ($rebuildKeys as $rebuild) {
            resetBiometricAttendanceDay($conn, (int)$rebuild['student_id'], (string)$rebuild['date']);
        }

        foreach ($events as $event) {
            if (syncAttendanceFromBiometricLog(
                $conn,
                (int)$event['student_id'],
                (string)$event['date'],
                (string)$event['time'],
                (int)$event['clock_type'],
                $event['hint_column']
            )) {
                $attendanceChanged++;
            }

            markRawLogProcessed($conn, (int)$event['log_id']);
            $processedLogs++;
        }

        $conn->close();
        return "Biometric sync complete. Raw inserted: {$rawInserted}, logs processed: {$processedLogs}, attendance rows changed: {$attendanceChanged}";
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
    function syncAttendanceFromBiometricLog(mysqli $conn, int $studentId, string $date, string $time, int $clockType, ?string $hintColumn): bool
    {
        $existing = $conn->prepare("
            SELECT id, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out
            FROM attendances
            WHERE student_id = ? AND attendance_date = ?
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

        if (isDuplicateBiometricPunch($row ?: [], $time, 5)) {
            return false;
        }

        $column = resolveAttendanceColumnForPunch($row ?: [], $clockType, $hintColumn, $time);
        if ($column === null) {
            return false;
        }

        if ($attendanceId > 0) {
            $currentValue = $row[$column] ?? null;
            if ($currentValue !== null && $currentValue !== '' && $currentValue !== '00:00:00') {
                return false;
            }

            $update = $conn->prepare("UPDATE attendances SET $column = ?, source = 'biometric', updated_at = NOW() WHERE id = ?");
            if ($update === false) {
                throw new RuntimeException('Database error: failed to prepare attendance update. Error: ' . $conn->error);
            }
            $update->bind_param('si', $time, $attendanceId);
            $update->execute();
            $affected = $update->affected_rows > 0;
            $update->close();
            return $affected;
        }

        $insert = $conn->prepare("INSERT INTO attendances (student_id, attendance_date, $column, source, status, created_at, updated_at) VALUES (?, ?, ?, 'biometric', 'pending', NOW(), NOW())");
        if ($insert === false) {
            throw new RuntimeException('Database error: failed to prepare attendance insert. Error: ' . $conn->error);
        }
        $insert->bind_param('iss', $studentId, $date, $time);
        $insert->execute();
        $affected = $insert->affected_rows > 0;
        $insert->close();
        return $affected;
    }
}

if (!function_exists('resolveAttendanceColumnForPunch')) {
    function resolveAttendanceColumnForPunch(array $attendanceRow, int $clockType, ?string $hintColumn, string $incomingTime): ?string
    {
        $orderedColumns = ['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out'];

        // The F20H logs currently arrive as generic punches with type=1, so advance through slots in order.
        if ($clockType === 1) {
            $lastTime = lastBiometricPunchTime($attendanceRow);
            if ($lastTime !== null) {
                $minutesSinceLast = minutesBetweenPunches($lastTime, $incomingTime);
                if ($minutesSinceLast !== null && $minutesSinceLast < 60) {
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

if (!function_exists('resetBiometricAttendanceDay')) {
    function resetBiometricAttendanceDay(mysqli $conn, int $studentId, string $date): void
    {
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
