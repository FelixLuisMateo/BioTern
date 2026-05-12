<?php
// Imports F20H machine logs into biometric_raw_logs and reconciles them into attendances.
require_once __DIR__ . '/biometric_ops.php';
require_once __DIR__ . '/biometric_db.php';
require_once dirname(__DIR__) . '/lib/section_schedule.php';
require_once dirname(__DIR__) . '/lib/attendance_settings.php';

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
        $attendanceFile = $attendanceFile ?: (dirname(__DIR__) . '/attendance.txt');
        $machineConfig = loadBiometricMachineConfig();
        $conn = biometric_shared_db();
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
        $autoCleanupQueued = 0;
        $autoCleanupApplied = 0;

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

                $rawInserted += biometricInsertRawLogEntries($conn, $data, $machineConfig);
            }
        }

        $autoCleanupApplied = applyCompletedFingerprintCleanupResults($conn);

        $fingerprintMap = buildFingerprintStudentMap($conn);
        $fingerprintUserMap = buildFingerprintUserMap($conn);
        $attendanceChanged += reconcileBiometricAttendanceOwnership($conn, $fingerprintMap);
        $studentScheduleMap = buildStudentAttendanceScheduleMap($conn);
        $studentRecordingStopMap = buildStudentRecordingStopMap($conn);
        $autoCleanupQueued = queueFingerprintCleanupForCompletedStudents($conn, $fingerprintMap, $studentRecordingStopMap);
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

                if (!empty($studentRecordingStopMap[$studentId]['stop'])) {
                    $anomaliesFound++;
                    biometric_ops_record_anomaly(
                        $conn,
                        $logId,
                        $fingerId,
                        $mappedUserId > 0 ? $mappedUserId : null,
                        $studentId,
                        'attendance_locked_hours_completed',
                        'info',
                        $datetime,
                        'Biometric punch ignored because the student has already completed required OJT hours.',
                        [
                            'track' => (string)($studentRecordingStopMap[$studentId]['track'] ?? 'internal'),
                            'remaining_hours' => (int)($studentRecordingStopMap[$studentId]['remaining_hours'] ?? 0),
                        ]
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

                $attendanceSettings = biotern_attendance_settings($conn);
                $useConfiguredAttendanceWindow = (string)($attendanceSettings['biometric_window_enabled'] ?? '0') === '1';
                if ($useConfiguredAttendanceWindow && !isWithinConfiguredAttendanceWindow($time, $machineConfig)) {
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
            'message' => "Biometric sync complete. Raw inserted: {$rawInserted}, logs processed: {$processedLogs}, attendance rows changed: {$attendanceChanged}, anomalies found: {$anomaliesFound}, cleanup queued: {$autoCleanupQueued}, cleanup applied: {$autoCleanupApplied}",
            'raw_inserted' => $rawInserted,
            'processed_logs' => $processedLogs,
            'attendance_changed' => $attendanceChanged,
            'anomalies_found' => $anomaliesFound,
            'cleanup_queued' => $autoCleanupQueued,
            'cleanup_applied' => $autoCleanupApplied,
        ];
    }
}

if (!function_exists('reconcileBiometricAttendanceOwnership')) {
    function reconcileBiometricAttendanceOwnership(mysqli $conn, array $fingerprintMap): int
    {
        if (empty($fingerprintMap)) {
            return 0;
        }

        $changed = 0;
        $slotColumns = ['morning_time_in', 'morning_time_out', 'afternoon_time_in', 'afternoon_time_out'];
        $rawRes = $conn->query("SELECT id, raw_data FROM biometric_raw_logs WHERE processed = 0 ORDER BY id ASC LIMIT 5000");
        if (!($rawRes instanceof mysqli_result)) {
            return 0;
        }

        $lookup = $conn->prepare("
            SELECT id, student_id, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out
            FROM attendances
            WHERE attendance_date = ?
              AND source = 'biometric'
              AND (
                morning_time_in = ?
                OR morning_time_out = ?
                OR afternoon_time_in = ?
                OR afternoon_time_out = ?
              )
            ORDER BY id ASC
        ");
        $targetLookup = $conn->prepare("
            SELECT id, morning_time_in, morning_time_out, afternoon_time_in, afternoon_time_out
            FROM attendances
            WHERE student_id = ? AND attendance_date = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $insertTarget = $conn->prepare("
            INSERT INTO attendances (student_id, attendance_date, source, status, approved_at, created_at, updated_at)
            VALUES (?, ?, 'biometric', 'approved', NOW(), NOW(), NOW())
        ");
        $moveSlot = $conn->prepare("UPDATE attendances SET student_id = ?, status = 'approved', approved_at = COALESCE(approved_at, NOW()), updated_at = NOW() WHERE id = ?");
        $clearSlotStatements = [];
        $fillSlotStatements = [];
        foreach ($slotColumns as $slotColumn) {
            $clearSlotStatements[$slotColumn] = $conn->prepare("UPDATE attendances SET {$slotColumn} = NULL, updated_at = NOW() WHERE id = ?");
            $fillSlotStatements[$slotColumn] = $conn->prepare("UPDATE attendances SET {$slotColumn} = ?, source = 'biometric', status = 'approved', approved_at = COALESCE(approved_at, NOW()), updated_at = NOW() WHERE id = ?");
        }
        $deleteEmpty = $conn->prepare("
            DELETE FROM attendances
            WHERE id = ?
              AND COALESCE(NULLIF(morning_time_in, '00:00:00'), '') = ''
              AND COALESCE(NULLIF(morning_time_out, '00:00:00'), '') = ''
              AND COALESCE(NULLIF(afternoon_time_in, '00:00:00'), '') = ''
              AND COALESCE(NULLIF(afternoon_time_out, '00:00:00'), '') = ''
        ");

        if (!$lookup || !$targetLookup || !$insertTarget || !$moveSlot || !$deleteEmpty) {
            $rawRes->close();
            return 0;
        }

        while ($raw = $rawRes->fetch_assoc()) {
            $entry = json_decode((string)($raw['raw_data'] ?? ''), true);
            if (!is_array($entry)) {
                continue;
            }

            $fingerId = isset($entry['finger_id']) ? (int)$entry['finger_id'] : (isset($entry['id']) ? (int)$entry['id'] : 0);
            $targetStudentId = (int)($fingerprintMap[$fingerId] ?? 0);
            $datetime = trim((string)($entry['time'] ?? ''));
            if ($fingerId <= 0 || $targetStudentId <= 0 || $datetime === '') {
                continue;
            }

            $date = substr($datetime, 0, 10);
            $time = substr($datetime, 11, 8);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                continue;
            }

            $lookup->bind_param('sssss', $date, $time, $time, $time, $time);
            $lookup->execute();
            $matches = $lookup->get_result();
            if (!($matches instanceof mysqli_result)) {
                continue;
            }

            while ($attendance = $matches->fetch_assoc()) {
                $sourceAttendanceId = (int)($attendance['id'] ?? 0);
                $sourceStudentId = (int)($attendance['student_id'] ?? 0);
                if ($sourceAttendanceId <= 0 || $sourceStudentId === $targetStudentId) {
                    continue;
                }

                $matchedSlots = [];
                foreach ($slotColumns as $slotColumn) {
                    if ((string)($attendance[$slotColumn] ?? '') === $time) {
                        $matchedSlots[] = $slotColumn;
                    }
                }
                if ($matchedSlots === []) {
                    continue;
                }

                $targetLookup->bind_param('is', $targetStudentId, $date);
                $targetLookup->execute();
                $targetRow = $targetLookup->get_result()->fetch_assoc();
                $targetAttendanceId = (int)($targetRow['id'] ?? 0);

                if ($targetAttendanceId <= 0) {
                    $canMoveWholeRow = true;
                    foreach ($slotColumns as $slotColumn) {
                        $slotValue = trim((string)($attendance[$slotColumn] ?? ''));
                        if ($slotValue !== '' && $slotValue !== '00:00:00' && !in_array($slotColumn, $matchedSlots, true)) {
                            $canMoveWholeRow = false;
                            break;
                        }
                    }

                    if ($canMoveWholeRow) {
                        $moveSlot->bind_param('ii', $targetStudentId, $sourceAttendanceId);
                        $moveSlot->execute();
                        if ($moveSlot->affected_rows > 0) {
                            $changed++;
                        }
                        continue;
                    }

                    $insertTarget->bind_param('is', $targetStudentId, $date);
                    $insertTarget->execute();
                    $targetAttendanceId = (int)$conn->insert_id;
                    if ($targetAttendanceId > 0) {
                        $targetRow = [];
                        $changed++;
                    }
                }

                foreach ($matchedSlots as $slotColumn) {
                    $targetSlotValue = trim((string)($targetRow[$slotColumn] ?? ''));
                    $sourceSlotCanClear = false;
                    if ($targetAttendanceId > 0 && ($targetSlotValue === '' || $targetSlotValue === '00:00:00')) {
                        $fill = $fillSlotStatements[$slotColumn] ?? null;
                        if ($fill) {
                            $fill->bind_param('si', $time, $targetAttendanceId);
                            $fill->execute();
                            if ($fill->affected_rows > 0) {
                                $changed++;
                                $sourceSlotCanClear = true;
                            }
                        }
                    } elseif ($targetSlotValue === $time) {
                        $sourceSlotCanClear = true;
                    }

                    $clear = $clearSlotStatements[$slotColumn] ?? null;
                    if ($sourceSlotCanClear && $clear) {
                        $clear->bind_param('i', $sourceAttendanceId);
                        $clear->execute();
                        if ($clear->affected_rows > 0) {
                            $changed++;
                        }
                    }
                }

                $deleteEmpty->bind_param('i', $sourceAttendanceId);
                $deleteEmpty->execute();
            }
            $matches->close();
        }

        $rawRes->close();
        return $changed;
    }
}

if (!function_exists('biometricInsertRawLogEntries')) {
    function biometricInsertRawLogEntries(mysqli $conn, array $entries, array $machineConfig = []): int
    {
        $inserted = 0;
        $incomingSeen = [];
        $duplicateWindowMinutes = biometricMachineConfigInt($machineConfig, 'duplicateGuardMinutes', 10);

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $raw = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($raw) || $raw === '{}' || $raw === '[]' || $raw === '') {
                continue;
            }

            $fingerId = isset($entry['finger_id']) ? (int) $entry['finger_id'] : (isset($entry['id']) ? (int) $entry['id'] : 0);
            $datetime = isset($entry['time']) ? trim((string) $entry['time']) : '';

            if ($fingerId > 0 && $datetime !== '' && isDuplicateRawBiometricEvent($conn, $incomingSeen, $fingerId, $datetime, $duplicateWindowMinutes)) {
                continue;
            }

            $stmt = $conn->prepare("SELECT id, processed FROM biometric_raw_logs WHERE raw_data = ? LIMIT 1");
            if ($stmt === false) {
                throw new RuntimeException('Database error: failed to prepare raw log lookup. Error: ' . $conn->error);
            }
            $stmt->bind_param('s', $raw);
            $stmt->execute();
            $existingRaw = $stmt->get_result()->fetch_assoc() ?: null;

            if (!$existingRaw) {
                $stmt->close();
                $ins = $conn->prepare("INSERT INTO biometric_raw_logs (raw_data, processed) VALUES (?, 0)");
                if ($ins === false) {
                    throw new RuntimeException('Database error: failed to prepare raw log insert. Error: ' . $conn->error);
                }
                $ins->bind_param('s', $raw);
                $ins->execute();
                $ins->close();
                $inserted++;
                rememberAcceptedRawBiometricEvent($incomingSeen, $fingerId, $datetime);
            } else {
                if ($fingerId > 0 && $datetime !== '' && !biometricAttendanceExistsForFingerDate($conn, $fingerId, substr($datetime, 0, 10))) {
                    $rawId = (int)($existingRaw['id'] ?? 0);
                    if ($rawId > 0) {
                        $reset = $conn->prepare("UPDATE biometric_raw_logs SET processed = 0 WHERE id = ?");
                        if ($reset) {
                            $reset->bind_param('i', $rawId);
                            $reset->execute();
                            $reset->close();
                        }
                    }
                }
                $stmt->close();
            }
        }

        return $inserted;
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

if (!function_exists('biometricMachineSchoolHours')) {
    function biometricMachineSchoolHours(array $machineConfig): array
    {
        $start = section_schedule_format_time_input((string)($machineConfig['attendanceStartTime'] ?? '08:00:00'));
        $end = section_schedule_format_time_input((string)($machineConfig['attendanceEndTime'] ?? '19:00:00'));

        return [
            'schedule_time_in' => $start !== '' ? $start : '08:00',
            'schedule_time_out' => $end !== '' ? $end : '19:00',
            'late_after_time' => $start !== '' ? $start : '08:00',
        ];
    }
}

if (!function_exists('biometricMachineTimeWithOffset')) {
    function biometricMachineTimeWithOffset(string $time, int $offsetMinutes): string
    {
        $ts = strtotime($time);
        if ($ts === false) {
            return $time;
        }

        return date('H:i:s', $ts + ($offsetMinutes * 60));
    }
}

if (!function_exists('biometricMachineHardWindow')) {
    function biometricMachineHardWindow(array $machineConfig, array $effectiveSchedule = []): array
    {
        $schoolHours = biometricMachineSchoolHours($machineConfig);
        $scheduleIn = section_schedule_normalize_time_input((string)($schoolHours['schedule_time_in'] ?? '')) ?: '08:00:00';
        $scheduleOut = section_schedule_normalize_time_input((string)($schoolHours['schedule_time_out'] ?? '')) ?: '19:00:00';

        $earlyAllowance = isset($machineConfig['maxEarlyArrivalMinutes']) ? max(0, (int)$machineConfig['maxEarlyArrivalMinutes']) : 120;
        $lateAllowance = isset($machineConfig['maxLateDepartureMinutes']) ? max(0, (int)$machineConfig['maxLateDepartureMinutes']) : 120;

        return [
            'start' => biometricMachineTimeWithOffset($scheduleIn, -$earlyAllowance),
            'end' => biometricMachineTimeWithOffset($scheduleOut, $lateAllowance),
            'early_allowance_minutes' => $earlyAllowance,
            'late_allowance_minutes' => $lateAllowance,
        ];
    }
}

if (!function_exists('biometricMachineIsWithinHardWindow')) {
    function biometricMachineIsWithinHardWindow(string $time, array $machineConfig, array $effectiveSchedule = []): bool
    {
        $time = section_schedule_normalize_time_input($time);
        // Keep every valid punch so early arrivals and late departures are credited.
        return $time !== null;
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

if (!function_exists('buildStudentRecordingStopMap')) {
    function buildStudentRecordingStopMap(mysqli $conn): array
    {
        $map = [];
        $res = $conn->query("SELECT id, assignment_track, internal_total_hours, internal_total_hours_remaining, external_total_hours, external_total_hours_remaining FROM students");
        if ($res && $res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $studentId = (int)($row['id'] ?? 0);
                if ($studentId <= 0) {
                    continue;
                }

                $track = strtolower(trim((string)($row['assignment_track'] ?? 'internal')));
                $totalHours = $track === 'external'
                    ? (int)($row['external_total_hours'] ?? 0)
                    : (int)($row['internal_total_hours'] ?? 0);
                $remainingHours = $track === 'external'
                    ? (int)($row['external_total_hours_remaining'] ?? 0)
                    : (int)($row['internal_total_hours_remaining'] ?? 0);

                $map[$studentId] = [
                    'stop' => $totalHours > 0 && $remainingHours <= 0,
                    'track' => $track,
                    'remaining_hours' => $remainingHours,
                ];
            }
            $res->close();
        }

        return $map;
    }
}

if (!function_exists('ensureBridgeCommandQueueTableForAutoImport')) {
    function ensureBridgeCommandQueueTableForAutoImport(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS biometric_bridge_command_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            command_name VARCHAR(80) NOT NULL,
            command_payload LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'queued',
            requested_by INT NOT NULL DEFAULT 0,
            source VARCHAR(80) NOT NULL DEFAULT 'machine_manager',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            claimed_at TIMESTAMP NULL DEFAULT NULL,
            claimed_by VARCHAR(120) NOT NULL DEFAULT '',
            completed_at TIMESTAMP NULL DEFAULT NULL,
            result_text TEXT NULL,
            attempts INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_status_created (status, created_at),
            KEY idx_claimed_by (claimed_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('ensureFingerprintCleanupLogTable')) {
    function ensureFingerprintCleanupLogTable(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS biometric_fingerprint_cleanup_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            student_id INT NOT NULL,
            finger_id INT NOT NULL,
            command_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(32) NOT NULL DEFAULT 'queued',
            last_message TEXT NULL,
            queued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_student_finger (student_id, finger_id),
            KEY idx_command_id (command_id),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('queueFingerprintCleanupForCompletedStudents')) {
    function queueFingerprintCleanupForCompletedStudents(mysqli $conn, array $fingerprintMap, array $studentRecordingStopMap): int
    {
        ensureBridgeCommandQueueTableForAutoImport($conn);
        ensureFingerprintCleanupLogTable($conn);

        $queuedCount = 0;
        foreach ($fingerprintMap as $fingerId => $studentId) {
            $fingerId = (int)$fingerId;
            $studentId = (int)$studentId;
            if ($fingerId <= 0 || $studentId <= 0) {
                continue;
            }
            if (empty($studentRecordingStopMap[$studentId]['stop'])) {
                continue;
            }

            $existingStmt = $conn->prepare("SELECT status FROM biometric_fingerprint_cleanup_log WHERE student_id = ? AND finger_id = ? LIMIT 1");
            if ($existingStmt) {
                $existingStmt->bind_param('ii', $studentId, $fingerId);
                $existingStmt->execute();
                $existingRow = $existingStmt->get_result()->fetch_assoc() ?: null;
                $existingStmt->close();
                if (is_array($existingRow)) {
                    $existingStatus = strtolower(trim((string)($existingRow['status'] ?? 'queued')));
                    if (in_array($existingStatus, ['queued', 'claimed', 'succeeded'], true)) {
                        continue;
                    }
                }
            }

            $payload = json_encode(['user_id' => $fingerId], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($payload) || $payload === '') {
                continue;
            }

            $queueStmt = $conn->prepare("INSERT INTO biometric_bridge_command_queue (command_name, command_payload, status, requested_by, source) VALUES ('delete_user', ?, 'queued', 0, 'auto_hours_cleanup')");
            if (!$queueStmt) {
                continue;
            }
            $queueStmt->bind_param('s', $payload);
            $queueStmt->execute();
            $commandId = (int)$queueStmt->insert_id;
            $inserted = $queueStmt->affected_rows > 0;
            $queueStmt->close();

            if (!$inserted || $commandId <= 0) {
                continue;
            }

            $message = 'Queued automatic fingerprint cleanup after OJT hour completion.';
            $upsert = $conn->prepare("INSERT INTO biometric_fingerprint_cleanup_log
                (student_id, finger_id, command_id, status, last_message, queued_at, completed_at)
                VALUES (?, ?, ?, 'queued', ?, NOW(), NULL)
                ON DUPLICATE KEY UPDATE
                    command_id = VALUES(command_id),
                    status = 'queued',
                    last_message = VALUES(last_message),
                    queued_at = NOW(),
                    completed_at = NULL");
            if ($upsert) {
                $upsert->bind_param('iiis', $studentId, $fingerId, $commandId, $message);
                $upsert->execute();
                $upsert->close();
            }

            $queuedCount++;
        }

        return $queuedCount;
    }
}

if (!function_exists('applyCompletedFingerprintCleanupResults')) {
    function applyCompletedFingerprintCleanupResults(mysqli $conn): int
    {
        ensureBridgeCommandQueueTableForAutoImport($conn);
        ensureFingerprintCleanupLogTable($conn);
        $conn->query("CREATE TABLE IF NOT EXISTS fingerprint_user_map (finger_id INT PRIMARY KEY, user_id INT NOT NULL)");

        $appliedCount = 0;
        $res = $conn->query("SELECT l.id, l.finger_id, l.command_id, q.status AS command_status, q.result_text
            FROM biometric_fingerprint_cleanup_log l
            LEFT JOIN biometric_bridge_command_queue q ON q.id = l.command_id
            WHERE l.status IN ('queued', 'claimed')");
        if (!($res instanceof mysqli_result)) {
            return 0;
        }

        while ($row = $res->fetch_assoc()) {
            $logId = (int)($row['id'] ?? 0);
            $fingerId = (int)($row['finger_id'] ?? 0);
            $commandStatus = strtolower(trim((string)($row['command_status'] ?? '')));
            $resultText = trim((string)($row['result_text'] ?? ''));

            if ($logId <= 0 || $fingerId <= 0 || $commandStatus === '') {
                continue;
            }

            if ($commandStatus === 'claimed') {
                $claimedStmt = $conn->prepare("UPDATE biometric_fingerprint_cleanup_log SET status = 'claimed', last_message = ? WHERE id = ?");
                if ($claimedStmt) {
                    $claimedMsg = $resultText !== '' ? $resultText : 'Bridge worker claimed fingerprint cleanup command.';
                    $claimedStmt->bind_param('si', $claimedMsg, $logId);
                    $claimedStmt->execute();
                    $claimedStmt->close();
                }
                continue;
            }

            if ($commandStatus === 'succeeded') {
                $deleteStmt = $conn->prepare("DELETE FROM fingerprint_user_map WHERE finger_id = ?");
                if ($deleteStmt) {
                    $deleteStmt->bind_param('i', $fingerId);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                }

                $okStmt = $conn->prepare("UPDATE biometric_fingerprint_cleanup_log SET status = 'succeeded', last_message = ?, completed_at = NOW() WHERE id = ?");
                if ($okStmt) {
                    $okMsg = $resultText !== '' ? $resultText : 'Fingerprint cleanup succeeded and local mapping was removed.';
                    $okStmt->bind_param('si', $okMsg, $logId);
                    $okStmt->execute();
                    $okStmt->close();
                }
                $appliedCount++;
                continue;
            }

            if ($commandStatus === 'failed') {
                $failedStmt = $conn->prepare("UPDATE biometric_fingerprint_cleanup_log SET status = 'failed', last_message = ?, completed_at = NOW() WHERE id = ?");
                if ($failedStmt) {
                    $failMsg = $resultText !== '' ? $resultText : 'Fingerprint cleanup command failed.';
                    $failedStmt->bind_param('si', $failMsg, $logId);
                    $failedStmt->execute();
                    $failedStmt->close();
                }
            }
        }

        $res->close();
        return $appliedCount;
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
                if (biometricAttendanceExistsForFingerDate($conn, $fingerId, $date)) {
                    $res->close();
                    return true;
                }
            }
        }

        $res->close();
        return false;
    }
}

if (!function_exists('biometricAttendanceExistsForFingerDate')) {
    function biometricAttendanceExistsForFingerDate(mysqli $conn, int $fingerId, string $date): bool
    {
        if ($fingerId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return true;
        }

        $stmt = $conn->prepare("
            SELECT a.id
            FROM fingerprint_user_map m
            INNER JOIN students s ON s.user_id = m.user_id
            INNER JOIN attendances a ON a.student_id = s.id AND a.attendance_date = ?
            WHERE m.finger_id = ?
            LIMIT 1
        ");
        if (!$stmt) {
            return true;
        }

        $stmt->bind_param('si', $date, $fingerId);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();

        return $exists;
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
        $effectiveSchedule = section_schedule_effective_day($studentSchedule, $date, biometricMachineSchoolHours($machineConfig));

        if (!biometricMachineIsWithinHardWindow($time, $machineConfig, $effectiveSchedule)) {
            $hardWindow = biometricMachineHardWindow($machineConfig, $effectiveSchedule);
            biometric_ops_record_anomaly(
                $conn,
                $rawLogId,
                $fingerId,
                $userId,
                $studentId,
                'outside_hard_attendance_window',
                'warning',
                $date . ' ' . $time,
                'Biometric punch was skipped because it is too far outside the allowed school attendance safety window.',
                [
                    'attendance_date' => $date,
                    'time' => $time,
                    'hard_window_start' => $hardWindow['start'] ?? null,
                    'hard_window_end' => $hardWindow['end'] ?? null,
                    'window_source' => $effectiveSchedule['window_source'] ?? null,
                ]
            );
            return ['changed' => false, 'anomalies_found' => 1];
        }

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

        $column = resolveAttendanceColumnForPunch($row, $clockType, $hintColumn, $time, $machineConfig, $effectiveSchedule);
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

            $update = $conn->prepare("UPDATE attendances SET $column = ?, source = 'biometric', status = 'approved', approved_at = COALESCE(approved_at, NOW()), updated_at = NOW() WHERE id = ?");
            if ($update === false) {
                throw new RuntimeException('Database error: failed to prepare attendance update. Error: ' . $conn->error);
            }
            $update->bind_param('si', $time, $attendanceId);
            $update->execute();
            $affected = $update->affected_rows > 0;
            $update->close();
            return ['changed' => $affected, 'anomalies_found' => 0];
        }

        $insert = $conn->prepare("INSERT INTO attendances (student_id, attendance_date, $column, source, status, approved_at, created_at, updated_at) VALUES (?, ?, ?, 'biometric', 'approved', NOW(), NOW(), NOW())");
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
            $session = section_schedule_inferred_session($effectiveSchedule);
            $afternoonBoundary = '12:00:00';
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
                $slotAdvanceMinimumMinutes = biometricMachineConfigInt($machineConfig, 'slotAdvanceMinimumMinutes', 10);
                $morningIn = trim((string)($attendanceRow['morning_time_in'] ?? ''));
                $morningOut = trim((string)($attendanceRow['morning_time_out'] ?? ''));
                $afternoonIn = trim((string)($attendanceRow['afternoon_time_in'] ?? ''));
                $afternoonOut = trim((string)($attendanceRow['afternoon_time_out'] ?? ''));

                if (
                    $morningIn === ''
                    && $morningOut === ''
                    && strcmp($incomingTime, $afternoonBoundary) >= 0
                ) {
                    if ($afternoonIn === '') {
                        return 'afternoon_time_in';
                    }
                    if ($afternoonOut === '') {
                        $minutesSinceAfternoonIn = minutesBetweenPunches($afternoonIn, $incomingTime);
                        return ($minutesSinceAfternoonIn !== null && $minutesSinceAfternoonIn < $slotAdvanceMinimumMinutes) ? null : 'afternoon_time_out';
                    }
                    return null;
                }

                if ($morningIn !== '' && $morningOut !== '' && strcmp($incomingTime, $afternoonBoundary) >= 0) {
                    if ($afternoonIn === '') {
                        $minutesSinceMorningOut = minutesBetweenPunches($morningOut, $incomingTime);
                        return ($minutesSinceMorningOut !== null && $minutesSinceMorningOut < $slotAdvanceMinimumMinutes) ? null : 'afternoon_time_in';
                    }
                    if ($afternoonOut === '') {
                        $minutesSinceAfternoonIn = minutesBetweenPunches($afternoonIn, $incomingTime);
                        return ($minutesSinceAfternoonIn !== null && $minutesSinceAfternoonIn < $slotAdvanceMinimumMinutes) ? null : 'afternoon_time_out';
                    }
                    return null;
                }

                if ($morningIn === '') {
                    return 'morning_time_in';
                }
                if ($morningOut === '') {
                    $minutesSinceLast = minutesBetweenPunches($morningIn, $incomingTime);
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

        if ($scheduledIn !== null && $scheduledOut !== null) {
            $startTs = strtotime($scheduledIn);
            $endTs = strtotime($scheduledOut);
            if ($startTs !== false && $endTs !== false && $endTs > $startTs) {
                $midpointTs = (int)round(($startTs + $endTs) / 2);
                $splitTime = date('H:i:s', $midpointTs);
                if (strcmp($splitTime, '11:00:00') < 0) {
                    return '11:00:00';
                }
                if (strcmp($splitTime, '15:00:00') > 0) {
                    return '15:00:00';
                }
                return $splitTime;
            }
        }

        return '13:00:00';
    }
}

if (!function_exists('resolveWholeDayAttendanceExitTime')) {
    function resolveWholeDayAttendanceExitTime(array $schedule): string
    {
        $splitTime = resolveWholeDayAttendanceSplitTime($schedule);
        $scheduledOut = section_schedule_normalize_time_input((string)($schedule['schedule_time_out'] ?? ''));
        if ($scheduledOut !== null) {
            $endTs = strtotime($scheduledOut);
            $splitTs = strtotime($splitTime);
            if ($endTs !== false && $splitTs !== false && $endTs > $splitTs) {
                $exitTs = (int)round($splitTs + (($endTs - $splitTs) * 0.5));
                $exitTime = date('H:i:s', $exitTs);
                if (strcmp($exitTime, '15:00:00') < 0) {
                    return '15:00:00';
                }
                if (strcmp($exitTime, $scheduledOut) > 0) {
                    return substr($scheduledOut, 0, 8);
                }
                return $exitTime;
            }
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
