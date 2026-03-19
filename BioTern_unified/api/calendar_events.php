<?php
require_once dirname(__DIR__) . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!($conn instanceof mysqli) || $conn->connect_errno) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$conn->set_charset('utf8mb4');

$conn->query("CREATE TABLE IF NOT EXISTS calendar_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    location VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    color VARCHAR(20) DEFAULT '#0d6efd',
    is_all_day TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_calendar_events_user_range (user_id, start_at, end_at),
    KEY idx_calendar_events_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function respond_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function normalize_datetime(?string $raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }

    $ts = strtotime($raw);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $ts);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $from = normalize_datetime((string)($_GET['from'] ?? ''));
    $to = normalize_datetime((string)($_GET['to'] ?? ''));

    if ($from !== null && $to !== null) {
        $stmt = $conn->prepare("SELECT id, title, location, description, start_at, end_at, color, is_all_day
            FROM calendar_events
            WHERE user_id = ?
              AND deleted_at IS NULL
              AND start_at <= ?
              AND end_at >= ?
            ORDER BY start_at ASC");
        if (!$stmt) {
            respond_json(500, ['success' => false, 'message' => 'Failed to prepare list query']);
        }
        $stmt->bind_param('iss', $userId, $to, $from);
    } else {
        $stmt = $conn->prepare("SELECT id, title, location, description, start_at, end_at, color, is_all_day
            FROM calendar_events
            WHERE user_id = ?
              AND deleted_at IS NULL
            ORDER BY start_at ASC
            LIMIT 1000");
        if (!$stmt) {
            respond_json(500, ['success' => false, 'message' => 'Failed to prepare list query']);
        }
        $stmt->bind_param('i', $userId);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'location' => (string)($row['location'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'start_at' => (string)$row['start_at'],
            'end_at' => (string)$row['end_at'],
            'color' => (string)($row['color'] ?? '#0d6efd'),
            'is_all_day' => (int)($row['is_all_day'] ?? 0),
        ];
    }
    $stmt->close();

    respond_json(200, ['success' => true, 'events' => $events]);
}

if ($method !== 'POST') {
    respond_json(405, ['success' => false, 'message' => 'Method not allowed']);
}

$rawBody = file_get_contents('php://input');
$data = json_decode((string)$rawBody, true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = strtolower(trim((string)($data['action'] ?? '')));
if ($action === '') {
    respond_json(400, ['success' => false, 'message' => 'Missing action']);
}

if ($action === 'create') {
    $title = trim((string)($data['title'] ?? ''));
    $location = trim((string)($data['location'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $color = trim((string)($data['color'] ?? '#0d6efd'));
    $isAllDay = !empty($data['is_all_day']) ? 1 : 0;
    $startAt = normalize_datetime((string)($data['start_at'] ?? ''));
    $endAt = normalize_datetime((string)($data['end_at'] ?? ''));

    if ($title === '' || $startAt === null || $endAt === null) {
        respond_json(400, ['success' => false, 'message' => 'Title, start and end are required']);
    }
    if (strtotime($endAt) < strtotime($startAt)) {
        respond_json(400, ['success' => false, 'message' => 'End time must be after start time']);
    }

    $stmt = $conn->prepare("INSERT INTO calendar_events
        (user_id, title, location, description, start_at, end_at, color, is_all_day)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Failed to prepare create query']);
    }
    $stmt->bind_param('issssssi', $userId, $title, $location, $description, $startAt, $endAt, $color, $isAllDay);
    if (!$stmt->execute()) {
        $stmt->close();
        respond_json(500, ['success' => false, 'message' => 'Failed to create event']);
    }
    $eventId = (int)$stmt->insert_id;
    $stmt->close();

    respond_json(200, ['success' => true, 'id' => $eventId]);
}

if ($action === 'update') {
    $eventId = (int)($data['id'] ?? 0);
    $title = trim((string)($data['title'] ?? ''));
    $location = trim((string)($data['location'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $color = trim((string)($data['color'] ?? '#0d6efd'));
    $isAllDay = !empty($data['is_all_day']) ? 1 : 0;
    $startAt = normalize_datetime((string)($data['start_at'] ?? ''));
    $endAt = normalize_datetime((string)($data['end_at'] ?? ''));

    if ($eventId <= 0 || $title === '' || $startAt === null || $endAt === null) {
        respond_json(400, ['success' => false, 'message' => 'Invalid update payload']);
    }
    if (strtotime($endAt) < strtotime($startAt)) {
        respond_json(400, ['success' => false, 'message' => 'End time must be after start time']);
    }

    $stmt = $conn->prepare("UPDATE calendar_events
        SET title = ?, location = ?, description = ?, start_at = ?, end_at = ?, color = ?, is_all_day = ?
        WHERE id = ? AND user_id = ? AND deleted_at IS NULL
        LIMIT 1");
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Failed to prepare update query']);
    }
    $stmt->bind_param('ssssssiii', $title, $location, $description, $startAt, $endAt, $color, $isAllDay, $eventId, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        respond_json(500, ['success' => false, 'message' => 'Failed to update event']);
    }
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected < 0) {
        respond_json(500, ['success' => false, 'message' => 'Event update failed']);
    }

    respond_json(200, ['success' => true]);
}

if ($action === 'delete') {
    $eventId = (int)($data['id'] ?? 0);
    if ($eventId <= 0) {
        respond_json(400, ['success' => false, 'message' => 'Missing event id']);
    }

    $stmt = $conn->prepare("UPDATE calendar_events
        SET deleted_at = NOW()
        WHERE id = ? AND user_id = ? AND deleted_at IS NULL
        LIMIT 1");
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Failed to prepare delete query']);
    }
    $stmt->bind_param('ii', $eventId, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        respond_json(500, ['success' => false, 'message' => 'Failed to delete event']);
    }
    $stmt->close();

    respond_json(200, ['success' => true]);
}

respond_json(400, ['success' => false, 'message' => 'Unsupported action']);
