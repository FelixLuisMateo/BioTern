<?php
require_once dirname(__DIR__) . '/config/db.php';

date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
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

function easter_sunday_manila(int $year): DateTimeImmutable
{
    $base = new DateTimeImmutable("$year-03-21", new DateTimeZone('Asia/Manila'));
    $offset = easter_days($year);
    return $base->modify('+' . $offset . ' days');
}

function calendar_avatar_url(?string $profilePath, int $fallbackSeed = 1): string
{
    $clean = ltrim(str_replace('\\', '/', trim((string)$profilePath)), '/');
    if ($clean !== '') {
        $rootPath = dirname(__DIR__) . '/' . $clean;
        if (is_file($rootPath)) {
            $mtime = @filemtime($rootPath);
            return $clean . ($mtime ? ('?v=' . $mtime) : '');
        }
    }

    $fallbackIndex = (($fallbackSeed % 5) + 1);
    return 'assets/images/avatar/' . $fallbackIndex . '.png';
}

function calendar_last_monday_of_august(int $year): string
{
    $lastAugustDay = new DateTimeImmutable("$year-08-31", new DateTimeZone('Asia/Manila'));
    while ((int)$lastAugustDay->format('N') !== 1) {
        $lastAugustDay = $lastAugustDay->modify('-1 day');
    }

    return $lastAugustDay->format('Y-m-d');
}

function map_calendar_event(array $event): array
{
    $startAt = (string)($event['start_at'] ?? '');
    return [
        'id' => isset($event['id']) ? (int)$event['id'] : 0,
        'title' => (string)($event['title'] ?? ''),
        'location' => (string)($event['location'] ?? ''),
        'description' => (string)($event['description'] ?? ''),
        'start_at' => $startAt,
        'end_at' => (string)($event['end_at'] ?? $startAt),
        'date' => substr($startAt, 0, 10),
        'color' => (string)($event['color'] ?? '#0d6efd'),
        'is_all_day' => (int)($event['is_all_day'] ?? 0),
        'type' => (string)($event['type'] ?? 'custom'),
        'category' => (string)($event['category'] ?? 'Saved Event'),
        'person' => isset($event['person']) && is_array($event['person']) ? $event['person'] : null,
    ];
}

function build_ph_events(int $year): array
{
    $easterSunday = easter_sunday_manila($year);
    $items = [
        ['title' => "New Year's Day", 'date' => "$year-01-01", 'category' => 'Regular Holiday'],
        ['title' => 'EDSA People Power Revolution Anniversary', 'date' => "$year-02-25", 'category' => 'Special Working Holiday'],
        ['title' => 'Maundy Thursday', 'date' => $easterSunday->modify('-3 days')->format('Y-m-d'), 'category' => 'Regular Holiday'],
        ['title' => 'Good Friday', 'date' => $easterSunday->modify('-2 days')->format('Y-m-d'), 'category' => 'Regular Holiday'],
        ['title' => 'Black Saturday', 'date' => $easterSunday->modify('-1 days')->format('Y-m-d'), 'category' => 'Special Non-Working Holiday'],
        ['title' => 'Araw ng Kagitingan', 'date' => "$year-04-09", 'category' => 'Regular Holiday'],
        ['title' => 'Labor Day', 'date' => "$year-05-01", 'category' => 'Regular Holiday'],
        ['title' => 'Independence Day', 'date' => "$year-06-12", 'category' => 'Regular Holiday'],
        ['title' => 'National Heroes Day', 'date' => calendar_last_monday_of_august($year), 'category' => 'Regular Holiday'],
        ['title' => 'Ninoy Aquino Day', 'date' => "$year-08-21", 'category' => 'Special Non-Working Holiday'],
        ['title' => "All Saints' Day", 'date' => "$year-11-01", 'category' => 'Special Non-Working Holiday'],
        ['title' => 'All Souls Day', 'date' => "$year-11-02", 'category' => 'Special Non-Working Holiday'],
        ['title' => 'Bonifacio Day', 'date' => "$year-11-30", 'category' => 'Regular Holiday'],
        ['title' => 'Feast of the Immaculate Conception', 'date' => "$year-12-08", 'category' => 'Special Non-Working Holiday'],
        ['title' => 'Christmas Eve', 'date' => "$year-12-24", 'category' => 'Special Non-Working Holiday'],
        ['title' => 'Christmas Day', 'date' => "$year-12-25", 'category' => 'Regular Holiday'],
        ['title' => 'Rizal Day', 'date' => "$year-12-30", 'category' => 'Regular Holiday'],
        ['title' => "New Year's Eve", 'date' => "$year-12-31", 'category' => 'Special Non-Working Holiday'],
    ];

    if ($year === 2026) {
        $items[] = ['title' => 'Chinese New Year', 'date' => '2026-02-17', 'category' => 'Special Non-Working Holiday'];
    }

    $events = [];
    foreach ($items as $item) {
        $events[] = map_calendar_event([
            'title' => $item['title'],
            'location' => 'Philippines',
            'description' => 'Philippine holiday or observance',
            'start_at' => $item['date'] . ' 00:00:00',
            'end_at' => $item['date'] . ' 23:59:59',
            'color' => '#f97316',
            'is_all_day' => 1,
            'type' => 'holiday',
            'category' => $item['category'],
        ]);
    }

    return $events;
}

function build_ojt_birthday_events(mysqli $conn, int $year): array
{
    $sql = "
        SELECT
            s.id,
            s.first_name,
            s.last_name,
            s.date_of_birth,
            COALESCE(NULLIF(u.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
            c.name AS course_name,
            COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '-') AS section_name
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.date_of_birth IS NOT NULL
          AND s.date_of_birth <> '0000-00-00'
        ORDER BY MONTH(s.date_of_birth), DAY(s.date_of_birth), s.last_name, s.first_name
    ";

    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $timestamp = strtotime((string)($row['date_of_birth'] ?? ''));
        if ($timestamp === false) {
            continue;
        }

        $month = (int)date('n', $timestamp);
        $day = (int)date('j', $timestamp);
        if ($month === 2 && $day === 29 && !checkdate($month, $day, $year)) {
            $day = 28;
        }

        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $fullName = trim((string)($row['first_name'] ?? '') . ' ' . (string)($row['last_name'] ?? ''));
        if ($fullName === '') {
            continue;
        }

        $events[] = map_calendar_event([
            'title' => $fullName . "'s Birthday",
            'location' => 'BioTern OJT',
            'description' => 'Birthday reminder for an OJT in BioTern',
            'start_at' => $date . ' 00:00:00',
            'end_at' => $date . ' 23:59:59',
            'color' => '#10b981',
            'is_all_day' => 1,
            'type' => 'birthday',
            'category' => 'OJT Birthday',
            'person' => [
                'id' => (int)($row['id'] ?? 0),
                'name' => $fullName,
                'course' => (string)($row['course_name'] ?? ''),
                'section' => (string)($row['section_name'] ?? ''),
                'avatar' => calendar_avatar_url($row['profile_picture'] ?? '', (int)($row['id'] ?? 0)),
                'profile_url' => 'students-view.php?id=' . (int)($row['id'] ?? 0),
            ],
        ]);
    }

    return $events;
}

function filter_derived_events(array $events, ?string $from, ?string $to): array
{
    if ($from === null || $to === null) {
        return $events;
    }

    return array_values(array_filter($events, static function (array $event) use ($from, $to): bool {
        $startAt = (string)($event['start_at'] ?? '');
        $endAt = (string)($event['end_at'] ?? $startAt);
        return $startAt <= $to && $endAt >= $from;
    }));
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    // Check if fetching a single event by ID
    $eventId = !empty($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($eventId > 0) {
        $stmt = $conn->prepare("SELECT id, title, location, description, start_at, end_at, color, is_all_day
            FROM calendar_events
            WHERE id = ? AND deleted_at IS NULL
            LIMIT 1");
        if (!$stmt) {
            respond_json(500, ['success' => false, 'message' => 'Failed to prepare event query']);
        }
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();
        $stmt->close();

        if (!$event) {
            respond_json(404, ['success' => false, 'message' => 'Event not found']);
        }

        respond_json(200, ['success' => true, 'event' => map_calendar_event([
            'id' => (int)$event['id'],
            'title' => (string)$event['title'],
            'location' => (string)($event['location'] ?? ''),
            'description' => (string)($event['description'] ?? ''),
            'start_at' => (string)$event['start_at'],
            'end_at' => (string)$event['end_at'],
            'color' => (string)($event['color'] ?? '#0d6efd'),
            'is_all_day' => (int)($event['is_all_day'] ?? 0),
            'type' => 'custom',
            'category' => 'Saved Event',
        ])]);
    }

    // Otherwise, fetch events by date range or all events
    $from = normalize_datetime((string)($_GET['from'] ?? ''));
    $to = normalize_datetime((string)($_GET['to'] ?? ''));

    if ($from !== null && $to !== null) {
        $stmt = $conn->prepare("SELECT id, title, location, description, start_at, end_at, color, is_all_day
            FROM calendar_events
            WHERE deleted_at IS NULL
              AND start_at <= ?
              AND end_at >= ?
            ORDER BY start_at ASC");
        if (!$stmt) {
            respond_json(500, ['success' => false, 'message' => 'Failed to prepare list query']);
        }
        $stmt->bind_param('ss', $to, $from);
    } else {
        $stmt = $conn->prepare("SELECT id, title, location, description, start_at, end_at, color, is_all_day
            FROM calendar_events
            WHERE deleted_at IS NULL
            ORDER BY start_at ASC
            LIMIT 1000");
        if (!$stmt) {
            respond_json(500, ['success' => false, 'message' => 'Failed to prepare list query']);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = map_calendar_event([
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'location' => (string)($row['location'] ?? ''),
            'description' => (string)($row['description'] ?? ''),
            'start_at' => (string)$row['start_at'],
            'end_at' => (string)$row['end_at'],
            'color' => (string)($row['color'] ?? '#0d6efd'),
            'is_all_day' => (int)($row['is_all_day'] ?? 0),
            'type' => 'custom',
            'category' => 'Saved Event',
        ]);
    }
    $stmt->close();

    $rangeStart = $from !== null ? new DateTimeImmutable(substr($from, 0, 10)) : new DateTimeImmutable(date('Y-01-01'));
    $rangeEnd = $to !== null ? new DateTimeImmutable(substr($to, 0, 10)) : new DateTimeImmutable(date('Y-12-31'));
    $years = [];
    $cursor = new DateTimeImmutable($rangeStart->format('Y-01-01'));
    $lastYear = (int)$rangeEnd->format('Y');
    while ((int)$cursor->format('Y') <= $lastYear) {
        $years[] = (int)$cursor->format('Y');
        $cursor = $cursor->modify('+1 year');
    }

    foreach ($years as $year) {
        $events = array_merge($events, filter_derived_events(build_ph_events($year), $from, $to));
        $events = array_merge($events, filter_derived_events(build_ojt_birthday_events($conn, $year), $from, $to));
    }

    usort($events, static function (array $left, array $right): int {
        $dateCompare = strcmp((string)($left['start_at'] ?? ''), (string)($right['start_at'] ?? ''));
        if ($dateCompare !== 0) {
            return $dateCompare;
        }
        return strcasecmp((string)($left['title'] ?? ''), (string)($right['title'] ?? ''));
    });

    respond_json(200, ['success' => true, 'events' => $events]);
}

if ($method !== 'POST') {
    respond_json(405, ['success' => false, 'message' => 'Method not allowed']);
}

if (in_array($userRole, ['student', 'supervisor'], true)) {
    respond_json(403, ['success' => false, 'message' => 'Students and supervisors cannot modify calendar events']);
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

    $events = build_ph_events($year);
    $inserted = 0;

    $checkStmt = $conn->prepare("SELECT id
        FROM calendar_events
        WHERE title = ? AND start_at = ? AND end_at = ? AND deleted_at IS NULL
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

        $checkStmt->bind_param('sss', $title, $startAt, $endAt);
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
        WHERE id = ? AND deleted_at IS NULL
        LIMIT 1");
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Failed to prepare update query']);
    }
    $stmt->bind_param('ssssssii', $title, $location, $description, $startAt, $endAt, $color, $isAllDay, $eventId);
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
        WHERE id = ? AND deleted_at IS NULL
        LIMIT 1");
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Failed to prepare delete query']);
    }
    $stmt->bind_param('i', $eventId);
    if (!$stmt->execute()) {
        $stmt->close();
        respond_json(500, ['success' => false, 'message' => 'Failed to delete event']);
    }
    $stmt->close();

    respond_json(200, ['success' => true]);
}

respond_json(400, ['success' => false, 'message' => 'Unsupported action']);
