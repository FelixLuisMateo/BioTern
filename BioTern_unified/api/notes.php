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
$conn->query("CREATE TABLE IF NOT EXISTS user_notes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content MEDIUMTEXT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'internship',
    note_type VARCHAR(20) NOT NULL DEFAULT 'text',
    accent_color VARCHAR(20) NOT NULL DEFAULT '#2563eb',
    is_pinned TINYINT(1) NOT NULL DEFAULT 0,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_user_notes_user (user_id, deleted_at, updated_at),
    KEY idx_user_notes_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if (function_exists('biotern_db_add_column_if_missing')) {
    biotern_db_add_column_if_missing($conn, 'user_notes', 'note_type', "note_type VARCHAR(20) NOT NULL DEFAULT 'text' AFTER category");
    biotern_db_add_column_if_missing($conn, 'user_notes', 'accent_color', "accent_color VARCHAR(20) NOT NULL DEFAULT '#2563eb' AFTER note_type");
}

function respond_json(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function notes_allowed_category(string $category): string
{
    $allowed = ['internship', 'meeting', 'requirement', 'reminder', 'personal'];
    $category = strtolower(trim($category));
    return in_array($category, $allowed, true) ? $category : 'internship';
}

function notes_allowed_type(string $value): string
{
    $allowed = ['text', 'checklist'];
    $value = strtolower(trim($value));
    return in_array($value, $allowed, true) ? $value : 'text';
}

function notes_clean_title(?string $title): string
{
    $title = trim((string)$title);
    if ($title === '') {
        return 'Untitled note';
    }
    return mb_substr($title, 0, 255, 'UTF-8');
}

function notes_clean_body(?string $value): string
{
    return trim((string)$value);
}

function notes_clean_color(?string $value): string
{
    $value = trim((string)$value);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1) {
        return strtolower($value);
    }
    return '#2563eb';
}

function notes_map(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'title' => (string)($row['title'] ?? ''),
        'content' => (string)($row['content'] ?? ''),
        'category' => notes_allowed_category((string)($row['category'] ?? 'internship')),
        'note_type' => notes_allowed_type((string)($row['note_type'] ?? 'text')),
        'accent_color' => notes_clean_color((string)($row['accent_color'] ?? '#2563eb')),
        'is_pinned' => (int)($row['is_pinned'] ?? 0),
        'is_archived' => (int)($row['is_archived'] ?? 0),
        'is_deleted' => !empty($row['deleted_at']) ? 1 : 0,
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'deleted_at' => (string)($row['deleted_at'] ?? ''),
    ];
}

function notes_fetch_one(mysqli $conn, int $userId, int $noteId): ?array
{
    $stmt = $conn->prepare('SELECT id, title, content, category, note_type, accent_color, is_pinned, is_archived, created_at, updated_at, deleted_at FROM user_notes WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $noteId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? notes_map($row) : null;
}

function notes_template_payload(string $templateKey): array
{
    $templates = [
        'blank' => [
            'title' => 'Untitled note',
            'content' => '',
            'category' => 'internship',
            'note_type' => 'text',
            'accent_color' => '#2563eb',
        ],
        'meeting' => [
            'title' => 'Meeting Notes',
            'content' => "Agenda:\n\nAttendees:\n\nKey points:\n\nNext steps:",
            'category' => 'meeting',
            'note_type' => 'text',
            'accent_color' => '#8b5cf6',
        ],
        'requirements' => [
            'title' => 'Requirements Checklist',
            'content' => json_encode([
                ['text' => 'Prepare needed document', 'done' => false],
                ['text' => 'Review submission details', 'done' => false],
                ['text' => 'Confirm deadline', 'done' => false],
            ], JSON_UNESCAPED_UNICODE),
            'category' => 'requirement',
            'note_type' => 'checklist',
            'accent_color' => '#f59e0b',
        ],
        'internship-log' => [
            'title' => 'Internship Log',
            'content' => "Date:\n\nWhat happened today:\n\nWhat I learned:\n\nFollow-up:",
            'category' => 'internship',
            'note_type' => 'text',
            'accent_color' => '#10b981',
        ],
    ];

    return $templates[$templateKey] ?? $templates['blank'];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $stmt = $conn->prepare('SELECT id, title, content, category, note_type, accent_color, is_pinned, is_archived, created_at, updated_at, deleted_at FROM user_notes WHERE user_id = ? ORDER BY deleted_at IS NULL DESC, is_pinned DESC, updated_at DESC, id DESC');
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Unable to load notes']);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = [];
    while ($row = $result->fetch_assoc()) {
        $notes[] = notes_map($row);
    }
    $stmt->close();

    respond_json(200, [
        'success' => true,
        'notes' => $notes,
    ]);
}

$raw = file_get_contents('php://input');
$data = [];
if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

$action = strtolower(trim((string)($data['action'] ?? '')));
if ($action === '') {
    respond_json(400, ['success' => false, 'message' => 'Missing action']);
}

if ($action === 'create') {
    $template = notes_template_payload(strtolower(trim((string)($data['template'] ?? 'blank'))));
    $title = notes_clean_title($data['title'] ?? $template['title']);
    $content = notes_clean_body($data['content'] ?? $template['content']);
    $category = notes_allowed_category((string)($data['category'] ?? $template['category']));
    $noteType = notes_allowed_type((string)($data['note_type'] ?? $template['note_type']));
    $accentColor = notes_clean_color((string)($data['accent_color'] ?? $template['accent_color']));

    $stmt = $conn->prepare('INSERT INTO user_notes (user_id, title, content, category, note_type, accent_color, is_pinned, is_archived) VALUES (?, ?, ?, ?, ?, ?, 0, 0)');
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Unable to create note']);
    }

    $stmt->bind_param('isssss', $userId, $title, $content, $category, $noteType, $accentColor);
    if (!$stmt->execute()) {
        $stmt->close();
        respond_json(500, ['success' => false, 'message' => 'Unable to create note']);
    }

    $noteId = (int)$stmt->insert_id;
    $stmt->close();
    $note = notes_fetch_one($conn, $userId, $noteId);

    respond_json(200, ['success' => true, 'note' => $note]);
}

if ($action === 'update') {
    $noteId = (int)($data['id'] ?? 0);
    if ($noteId <= 0) {
        respond_json(422, ['success' => false, 'message' => 'Invalid note']);
    }

    $title = notes_clean_title($data['title'] ?? '');
    $content = notes_clean_body($data['content'] ?? '');
    $category = notes_allowed_category((string)($data['category'] ?? 'internship'));
    $noteType = notes_allowed_type((string)($data['note_type'] ?? 'text'));
    $accentColor = notes_clean_color((string)($data['accent_color'] ?? '#2563eb'));
    $isPinned = !empty($data['is_pinned']) ? 1 : 0;
    $isArchived = !empty($data['is_archived']) ? 1 : 0;

    $stmt = $conn->prepare('UPDATE user_notes SET title = ?, content = ?, category = ?, note_type = ?, accent_color = ?, is_pinned = ?, is_archived = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Unable to update note']);
    }

    $stmt->bind_param('sssssiiii', $title, $content, $category, $noteType, $accentColor, $isPinned, $isArchived, $noteId, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        respond_json(500, ['success' => false, 'message' => 'Unable to update note']);
    }
    $stmt->close();

    $note = notes_fetch_one($conn, $userId, $noteId);
    if (!$note) {
        respond_json(404, ['success' => false, 'message' => 'Note not found']);
    }

    respond_json(200, ['success' => true, 'note' => $note]);
}

if ($action === 'delete') {
    $noteId = (int)($data['id'] ?? 0);
    if ($noteId <= 0) {
        respond_json(422, ['success' => false, 'message' => 'Invalid note']);
    }

    $stmt = $conn->prepare('UPDATE user_notes SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1');
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Unable to delete note']);
    }

    $stmt->bind_param('ii', $noteId, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        respond_json(500, ['success' => false, 'message' => 'Unable to delete note']);
    }
    $stmt->close();

    respond_json(200, ['success' => true]);
}

if ($action === 'restore') {
    $noteId = (int)($data['id'] ?? 0);
    if ($noteId <= 0) {
        respond_json(422, ['success' => false, 'message' => 'Invalid note']);
    }

    $stmt = $conn->prepare('UPDATE user_notes SET deleted_at = NULL, updated_at = NOW() WHERE id = ? AND user_id = ? LIMIT 1');
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Unable to restore note']);
    }

    $stmt->bind_param('ii', $noteId, $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        respond_json(500, ['success' => false, 'message' => 'Unable to restore note']);
    }
    $stmt->close();

    $note = notes_fetch_one($conn, $userId, $noteId);
    respond_json(200, ['success' => true, 'note' => $note]);
}

if ($action === 'duplicate') {
    $noteId = (int)($data['id'] ?? 0);
    if ($noteId <= 0) {
        respond_json(422, ['success' => false, 'message' => 'Invalid note']);
    }

    $original = notes_fetch_one($conn, $userId, $noteId);
    if (!$original || !empty($original['is_deleted'])) {
        respond_json(404, ['success' => false, 'message' => 'Note not found']);
    }

    $duplicateTitle = notes_clean_title($original['title'] . ' Copy');
    $stmt = $conn->prepare('INSERT INTO user_notes (user_id, title, content, category, note_type, accent_color, is_pinned, is_archived) VALUES (?, ?, ?, ?, ?, ?, 0, 0)');
    if (!$stmt) {
        respond_json(500, ['success' => false, 'message' => 'Unable to duplicate note']);
    }

    $content = (string)$original['content'];
    $category = (string)$original['category'];
    $noteType = (string)$original['note_type'];
    $accentColor = (string)$original['accent_color'];
    $stmt->bind_param('isssss', $userId, $duplicateTitle, $content, $category, $noteType, $accentColor);
    if (!$stmt->execute()) {
        $stmt->close();
        respond_json(500, ['success' => false, 'message' => 'Unable to duplicate note']);
    }

    $newId = (int)$stmt->insert_id;
    $stmt->close();
    $note = notes_fetch_one($conn, $userId, $newId);
    respond_json(200, ['success' => true, 'note' => $note]);
}

respond_json(400, ['success' => false, 'message' => 'Unsupported action']);
