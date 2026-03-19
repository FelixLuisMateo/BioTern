<?php
require_once dirname(__DIR__) . '/config/db.php';

date_default_timezone_set('Asia/Manila');

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
$conn->query("SET time_zone = '+08:00'");

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

    $manilaTz = new DateTimeZone('Asia/Manila');
    $raw = str_replace('T', ' ', $raw);

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
        $raw .= ' 00:00:00';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $raw) === 1) {
        $raw .= ':00';
    }

    try {
        if (preg_match('/(Z|[+\-]\d{2}:?\d{2})$/', $raw) === 1) {
            $dt = new DateTimeImmutable($raw);
            return $dt->setTimezone($manilaTz)->format('Y-m-d H:i:s');
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $raw, $manilaTz);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->format('Y-m-d H:i:s');
        }

        $fallback = strtotime($raw);
        if ($fallback === false) {
            return null;
        }

        return (new DateTimeImmutable('@' . $fallback))
            ->setTimezone($manilaTz)
            ->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function build_ph_celebrations(int $year): array
{
    $manilaTz = new DateTimeZone('Asia/Manila');
    $items = [
        ['New Year\'s Day', "$year-01-01"],
        ['EDSA People Power Revolution', "$year-02-25"],
        ['Araw ng Kagitingan', "$year-04-09"],
        ['Labor Day', "$year-05-01"],
        ['Independence Day', "$year-06-12"],
        ['Ninoy Aquino Day', "$year-08-21"],
        ['All Saints\' Day', "$year-11-01"],
        ['All Souls\' Day', "$year-11-02"],
        ['Bonifacio Day', "$year-11-30"],
        ['Christmas Eve', "$year-12-24"],
        ['Christmas Day', "$year-12-25"],
        ['Rizal Day', "$year-12-30"],
        ['New Year\'s Eve', "$year-12-31"],
    ];

    $lastAugustDay = new DateTimeImmutable("$year-08-31", $manilaTz);
    while ((int)$lastAugustDay->format('N') !== 1) {
        $lastAugustDay = $lastAugustDay->modify('-1 day');
    }
    $items[] = ['National Heroes Day', $lastAugustDay->format('Y-m-d')];

    return array_map(static function (array $item): array {
        $date = $item[1];
        return [
            'title' => 'PH Celebration: ' . $item[0],
            'location' => 'Philippines',
            'description' => 'Philippines celebration event',
            'start_at' => $date . ' 00:00:00',
            'end_at' => $date . ' 23:59:59',
            'color' => '#f59e0b',
            'is_all_day' => 1,
        ];
    }, $items);
}

function easter_sunday_manila(int $year): DateTimeImmutable
{
    $base = new DateTimeImmutable("$year-03-21", new DateTimeZone('Asia/Manila'));
    $offset = easter_days($year);
    return $base->modify('+' . $offset . ' days');
}

function build_ph_holidays(int $year): array
{
    $easterSunday = easter_sunday_manila($year);
    $maundyThursday = $easterSunday->modify('-3 days')->format('Y-m-d');
    $goodFriday = $easterSunday->modify('-2 days')->format('Y-m-d');
    $blackSaturday = $easterSunday->modify('-1 day')->format('Y-m-d');

    $items = [
        ['New Year\'s Day', "$year-01-01"],
        ['Maundy Thursday', $maundyThursday],
        ['Good Friday', $goodFriday],
        ['Black Saturday', $blackSaturday],
        ['Araw ng Kagitingan', "$year-04-09"],
        ['Labor Day', "$year-05-01"],
        ['Independence Day', "$year-06-12"],
        ['National Heroes Day', (function () use ($year) {
            $lastAugustDay = new DateTimeImmutable("$year-08-31", new DateTimeZone('Asia/Manila'));
            while ((int)$lastAugustDay->format('N') !== 1) {
                $lastAugustDay = $lastAugustDay->modify('-1 day');
            }
            return $lastAugustDay->format('Y-m-d');
        })()],
        ['Bonifacio Day', "$year-11-30"],
        ['Christmas Day', "$year-12-25"],
        ['Rizal Day', "$year-12-30"],
    ];

    return array_map(static function (array $item): array {
        $date = $item[1];
        return [
            'title' => 'PH Holiday: ' . $item[0],
            'location' => 'Philippines',
            'description' => 'Philippines holiday',
            'start_at' => $date . ' 00:00:00',
            'end_at' => $date . ' 23:59:59',
            'color' => '#ef4444',
            'is_all_day' => 1,
        ];
    }, $items);
}

function build_ph_celebrations_and_holidays(int $year): array
{
    $merged = array_merge(build_ph_celebrations($year), build_ph_holidays($year));
    $unique = [];
    $seen = [];

    foreach ($merged as $event) {
        $key = strtolower((string)$event['title']) . '|' . (string)$event['start_at'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $unique[] = $event;
    }

    return $unique;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    // Check if fetching a single event by ID
    $eventId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($eventId > 0) {
        $stmt = $conn->prepare("SELECT id, title, location, description, start_at, end_at, color, is_all_day
            FROM calendar_events
            WHERE id = ? AND user_id = ? AND deleted_at IS NULL
            LIMIT 1");
        if (!$stmt) {
            respond_json(500, ['success' => false, 'message' => 'Failed to prepare event query']);
        }
        $stmt->bind_param('ii', $eventId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();
        $stmt->close();

        if (!$event) {
            respond_json(404, ['success' => false, 'message' => 'Event not found']);
        }

        respond_json(200, ['success' => true, 'event' => [
            'id' => (int)$event['id'],
            'title' => (string)$event['title'],
            'location' => (string)($event['location'] ?? ''),
            'description' => (string)($event['description'] ?? ''),
            'start_at' => (string)$event['start_at'],
            'end_at' => (string)$event['end_at'],
            'color' => (string)($event['color'] ?? '#0d6efd'),
            'is_all_day' => (int)($event['is_all_day'] ?? 0),
        ]]);
    }

    // Otherwise, fetch events by date range or all events
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

if ($action === 'seed_celebrations' || $action === 'import_celebrations') {
    $year = (int)($data['year'] ?? date('Y'));
    if ($year < 2000 || $year > 2100) {
        respond_json(400, ['success' => false, 'message' => 'Invalid year']);
    }

    $events = build_ph_celebrations_and_holidays($year);
    $inserted = 0;

    $checkStmt = $conn->prepare("SELECT id
        FROM calendar_events
        WHERE user_id = ? AND title = ? AND start_at = ? AND end_at = ? AND deleted_at IS NULL
        LIMIT 1");
    if (!$checkStmt) {
        respond_json(500, ['success' => false, 'message' => 'Failed to prepare duplicate check']);
    }

    $insertStmt = $conn->prepare("INSERT INTO calendar_events
        (user_id, title, location, description, start_at, end_at, color, is_all_day)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$insertStmt) {
        $checkStmt->close();
        respond_json(500, ['success' => false, 'message' => 'Failed to prepare celebration insert']);
    }

    foreach ($events as $event) {
        $title = (string)$event['title'];
        $location = (string)$event['location'];
        $description = (string)$event['description'];
        $startAt = (string)$event['start_at'];
        $endAt = (string)$event['end_at'];
        $color = (string)$event['color'];
        $isAllDay = (int)$event['is_all_day'];

        $checkStmt->bind_param('isss', $userId, $title, $startAt, $endAt);
        $checkStmt->execute();
        $existingResult = $checkStmt->get_result();
        $alreadyExists = $existingResult && $existingResult->num_rows > 0;

        if ($alreadyExists) {
            continue;
        }

        $insertStmt->bind_param('issssssi', $userId, $title, $location, $description, $startAt, $endAt, $color, $isAllDay);
        if ($insertStmt->execute()) {
            $inserted++;
        }
    }

    $checkStmt->close();
    $insertStmt->close();

    respond_json(200, [
        'success' => true,
        'inserted_count' => $inserted,
        'total_templates' => count($events),
        'year' => $year,
    ]);
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
