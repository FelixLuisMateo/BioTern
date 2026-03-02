<?php
// Database Connection
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get student ID from request
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($student_id == 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid student ID']);
    exit;
}

$today = date('Y-m-d');

// Check if student is currently clocked in
$query = $conn->prepare("
    SELECT 
        morning_time_in,
        morning_time_out,
        afternoon_time_in,
        afternoon_time_out
    FROM attendances 
    WHERE student_id = ? AND attendance_date = ?
    LIMIT 1
");

$query->bind_param("is", $student_id, $today);
$query->execute();
$result = $query->get_result();
$record = $result->fetch_assoc();

$is_clocked_in = false;

if ($record) {
    $morning_in = $record['morning_time_in'];
    $morning_out = $record['morning_time_out'];
    $afternoon_in = $record['afternoon_time_in'];
    $afternoon_out = $record['afternoon_time_out'];
    
    // Student is clocked in if:
    // - Morning clock in exists but no clock out, OR
    // - Afternoon clock in exists but no afternoon clock out
    if (($morning_in && !$morning_out) || ($afternoon_in && !$afternoon_out)) {
        $is_clocked_in = true;
    }
}

header('Content-Type: application/json');
echo json_encode(['is_clocked_in' => $is_clocked_in]);

$conn->close();
?>
