<?php
// attendance_import.php
// Reads attendance.txt, maps fingerprint id to user id, and inserts attendance into the database.

$attendanceFile = __DIR__ . '/attendance.txt';
$mapping = [
    1 => 4, // fingerprint id 1 → user id 4
    // Add more mappings as needed
];

$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (!file_exists($attendanceFile)) {
    die('attendance.txt not found.');
}

$json = file_get_contents($attendanceFile);
$data = json_decode($json, true);
if (!is_array($data)) {
    die('Invalid attendance.txt format.');
}

foreach ($data as $entry) {
    $finger_id = $entry['id'] ?? null;
    $clock_type = $entry['type'] ?? null;
    $datetime = $entry['time'] ?? null;
    if (!$finger_id || !$clock_type || !$datetime) continue;
    if (!isset($mapping[$finger_id])) continue; // unmapped fingerprint
    $user_id = $mapping[$finger_id];

    $date = substr($datetime, 0, 10);
    $time = substr($datetime, 11, 8);

    // Determine which column to update based on type
    // 1 = morning_time_in, 2 = morning_time_out, 3 = afternoon_time_in, 4 = afternoon_time_out
    $columns = [1 => 'morning_time_in', 2 => 'morning_time_out', 3 => 'afternoon_time_in', 4 => 'afternoon_time_out'];
    if (!isset($columns[$clock_type])) continue;
    $col = $columns[$clock_type];

    // Check for duplicate
    $stmt = $conn->prepare("SELECT id FROM attendances WHERE student_id = ? AND attendance_date = ? AND $col = ?");
    $stmt->bind_param('iss', $user_id, $date, $time);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        continue; // already exists
    }
    $stmt->close();

    // Insert or update attendance
    // Check if attendance record exists for this date
    $stmt = $conn->prepare("SELECT id FROM attendances WHERE student_id = ? AND attendance_date = ?");
    $stmt->bind_param('is', $user_id, $date);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        // Update existing record
        $stmt->bind_result($att_id);
        $stmt->fetch();
        $stmt->close();
        $upd = $conn->prepare("UPDATE attendances SET $col = ?, updated_at = NOW(), source = 'biometric' WHERE id = ?");
        $upd->bind_param('si', $time, $att_id);
        $upd->execute();
        $upd->close();
    } else {
        $stmt->close();
        $ins = $conn->prepare("INSERT INTO attendances (student_id, attendance_date, $col, source, status, created_at, updated_at) VALUES (?, ?, ?, 'biometric', 'pending', NOW(), NOW())");
        $ins->bind_param('iss', $user_id, $date, $time);
        $ins->execute();
        $ins->close();
    }
}

$conn->close();
echo "Attendance import complete.\n";
