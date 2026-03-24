<?php
// BioTern_unified/tools/biometric_auto_import.php
// Automatically imports new biometric logs from attendance.txt into biometric_raw_logs and attendances

$attendanceFile = __DIR__ . '/../../attendance.txt';
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';


$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Repair older biometric rows that were stored using users.id instead of students.id.
$conn->query("
    UPDATE attendances a
    INNER JOIN students s ON s.user_id = a.student_id
    SET a.student_id = s.id
    WHERE a.source = 'biometric'
");

if (!file_exists($attendanceFile)) {
    die('attendance.txt not found.');
}

$json = file_get_contents($attendanceFile);
if ($json === false) {
    die('Failed to read attendance.txt.');
}

if (trim($json) === '' || trim($json) === '[]') {
    die('No new attendance data.');
}

$data = json_decode($json, true);
if (!is_array($data)) {
    die('Invalid attendance.txt format.');
}

// Insert new raw logs

// Always insert new entries into biometric_raw_logs
foreach ($data as $entry) {
    $raw = json_encode($entry);
    if ($raw === '{}' || $raw === '[]' || $raw === '' || $raw === null) continue;
    // Check if already imported
    $stmt = $conn->prepare("SELECT id FROM biometric_raw_logs WHERE raw_data = ?");
    $stmt->bind_param('s', $raw);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) {
        $stmt->close();
        $ins = $conn->prepare("INSERT INTO biometric_raw_logs (raw_data) VALUES (?)");
        $ins->bind_param('s', $raw);
        $ins->execute();
        $ins->close();
    } else {
        $stmt->close();
    }
}

// Process unprocessed logs
$res = $conn->query("SELECT id, raw_data FROM biometric_raw_logs WHERE processed = 0");
if ($res && $res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $log_id = $row['id'];
        $entry = json_decode($row['raw_data'], true);
        $finger_id = $entry['finger_id'] ?? $entry['id'] ?? null;
        $clock_type = $entry['type'] ?? null;
        $datetime = $entry['time'] ?? null;
        if (!$finger_id || !$clock_type || !$datetime) {
            $conn->query("UPDATE biometric_raw_logs SET processed = 1 WHERE id = $log_id");
            continue;
        }
        // Ensure mapping table exists
        $conn->query("CREATE TABLE IF NOT EXISTS fingerprint_user_map (finger_id INT PRIMARY KEY, user_id INT NOT NULL)");
        // Diagnostic: check if table exists
        $check = $conn->query("SHOW TABLES LIKE 'fingerprint_user_map'");
        if (!$check || $check->num_rows == 0) {
            ob_end_clean();
            die('Table fingerprint_user_map does NOT exist in the current database!');
        }
        $mapq = $conn->prepare("SELECT user_id FROM fingerprint_user_map WHERE finger_id = ?");
        if ($mapq === false) {
            die('Database error: failed to prepare fingerprint mapping query.<br>Error: ' . $conn->error . '<br>Query: SELECT user_id FROM fingerprint_user_map WHERE finger_id = ?');
        }
        $mapq->bind_param('i', $finger_id);
        $mapq->execute();
        $mapq->bind_result($user_id);
        $mappedUserId = $mapq->fetch() ? (int)$user_id : null;
        $mapq->close();

        if ($mappedUserId !== null) {
            $studentLookup = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
            if ($studentLookup === false) {
                die('Database error: failed to prepare student lookup.<br>Error: ' . $conn->error . '<br>Query: SELECT id FROM students WHERE user_id = ? LIMIT 1');
            }
            $studentLookup->bind_param('i', $mappedUserId);
            $studentLookup->execute();
            $studentLookup->bind_result($studentId);
            $mappedStudentId = $studentLookup->fetch() ? (int)$studentId : null;
            $studentLookup->close();

            if ($mappedStudentId === null) {
                $conn->query("UPDATE biometric_raw_logs SET processed = 1 WHERE id = $log_id");
                continue;
            }

            $date = substr($datetime, 0, 10);
            $time = substr($datetime, 11, 8);
            $columns = [1 => 'morning_time_in', 2 => 'morning_time_out', 3 => 'afternoon_time_in', 4 => 'afternoon_time_out'];
            if (isset($columns[$clock_type])) {
                $col = $columns[$clock_type];
                // Check for duplicate
                $stmt = $conn->prepare("SELECT id FROM attendances WHERE student_id = ? AND attendance_date = ? AND $col = ?");
                if ($stmt === false) {
                    die('Database error: failed to prepare attendance duplicate check.<br>Error: ' . $conn->error . '<br>Query: SELECT id FROM attendances WHERE student_id = ? AND attendance_date = ? AND ' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . ' = ?');
                }
                $stmt->bind_param('iss', $mappedStudentId, $date, $time);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows == 0) {
                    $stmt->close();
                    // Insert or update attendance
                    $stmt2 = $conn->prepare("SELECT id FROM attendances WHERE student_id = ? AND attendance_date = ?");
                    if ($stmt2 === false) {
                        die('Database error: failed to prepare attendance lookup.<br>Error: ' . $conn->error . '<br>Query: SELECT id FROM attendances WHERE student_id = ? AND attendance_date = ?');
                    }
                    $stmt2->bind_param('is', $mappedStudentId, $date);
                    $stmt2->execute();
                    $stmt2->store_result();
                    if ($stmt2->num_rows > 0) {
                        $stmt2->bind_result($att_id);
                        $stmt2->fetch();
                        $stmt2->close();
                        $upd = $conn->prepare("UPDATE attendances SET $col = ?, updated_at = NOW(), source = 'biometric' WHERE id = ?");
                        if ($upd === false) {
                            die('Database error: failed to prepare attendance update.<br>Error: ' . $conn->error . '<br>Query: UPDATE attendances SET ' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . ' = ?, updated_at = NOW(), source = \'biometric\' WHERE id = ?');
                        }
                        $upd->bind_param('si', $time, $att_id);
                        $upd->execute();
                        $upd->close();
                    } else {
                        $stmt2->close();
                        $ins = $conn->prepare("INSERT INTO attendances (student_id, attendance_date, $col, source, status, created_at, updated_at) VALUES (?, ?, ?, 'biometric', 'pending', NOW(), NOW())");
                        if ($ins === false) {
                            die('Database error: failed to prepare attendance insert.<br>Error: ' . $conn->error . '<br>Query: INSERT INTO attendances (student_id, attendance_date, ' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . ', source, status, created_at, updated_at) VALUES (?, ?, ?, \'biometric\', \'pending\', NOW(), NOW())');
                        }
                        $ins->bind_param('iss', $mappedStudentId, $date, $time);
                        $ins->execute();
                        $ins->close();
                    }
                } else {
                    $stmt->close();
                }
            }
        }
        // Mark as processed
        $conn->query("UPDATE biometric_raw_logs SET processed = 1 WHERE id = $log_id");
    }
    $res->close();
}

echo "Biometric auto import complete.\n";
