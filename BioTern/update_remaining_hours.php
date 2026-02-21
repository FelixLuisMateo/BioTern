<?php
header('Content-Type: application/json');

$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        throw new Exception('Connection failed');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_connection_failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$remaining_hours = isset($_POST['remaining_hours']) ? intval($_POST['remaining_hours']) : null;

if ($student_id <= 0 || $remaining_hours === null || $remaining_hours < 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

$stmt = $conn->prepare("SELECT assignment_track FROM students WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'student_not_found']);
    exit;
}

$track = strtolower((string)($row['assignment_track'] ?? 'internal'));

if ($track === 'external') {
    $upd = $conn->prepare("UPDATE students SET external_total_hours_remaining = ?, updated_at = NOW() WHERE id = ?");
    $upd->bind_param("ii", $remaining_hours, $student_id);
} else {
    $upd = $conn->prepare("UPDATE students SET internal_total_hours_remaining = ?, updated_at = NOW() WHERE id = ?");
    $upd->bind_param("ii", $remaining_hours, $student_id);
}

$ok = $upd->execute();
$upd->close();

echo json_encode([
    'ok' => (bool)$ok,
    'track' => $track,
    'remaining_hours' => $remaining_hours
]);

$conn->close();
?>
