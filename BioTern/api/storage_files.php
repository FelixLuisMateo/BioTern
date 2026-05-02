<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';

date_default_timezone_set('Asia/Manila');

biotern_boot_session(isset($conn) ? $conn : null);

$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$userRole = strtolower(trim((string)($_SESSION['role'] ?? '')));

if ($userId <= 0) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!($conn instanceof mysqli) || $conn->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$endpointBase = 'storage_files.php';

$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+08:00'");
$conn->query("CREATE TABLE IF NOT EXISTS storage_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    owner_user_id INT NOT NULL,
    uploader_name VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    storage_scope VARCHAR(20) NOT NULL DEFAULT 'personal',
    category VARCHAR(40) NOT NULL DEFAULT 'other',
    mime_type VARCHAR(191) NOT NULL DEFAULT 'application/octet-stream',
    file_extension VARCHAR(20) NOT NULL DEFAULT '',
    file_type VARCHAR(20) NOT NULL DEFAULT 'file',
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    storage_path VARCHAR(500) NOT NULL,
    notes TEXT NULL,
    is_starred TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_storage_files_visibility (owner_user_id, storage_scope, deleted_at, updated_at),
    KEY idx_storage_files_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS storage_file_versions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    storage_file_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(191) NOT NULL DEFAULT 'application/octet-stream',
    file_extension VARCHAR(20) NOT NULL DEFAULT '',
    file_type VARCHAR(20) NOT NULL DEFAULT 'file',
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    storage_path VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_storage_versions_file (storage_file_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS storage_activity_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    action_type VARCHAR(40) NOT NULL,
    storage_file_id INT UNSIGNED DEFAULT NULL,
    title VARCHAR(255) DEFAULT NULL,
    details VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_storage_activity_logs_user (user_id, created_at),
    KEY idx_storage_activity_logs_file (storage_file_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS storage_file_blobs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    storage_file_id INT UNSIGNED DEFAULT NULL,
    storage_version_id INT UNSIGNED DEFAULT NULL,
    mime_type VARCHAR(191) NOT NULL DEFAULT 'application/octet-stream',
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    blob_data LONGBLOB NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_storage_blob_file (storage_file_id, storage_version_id),
    KEY idx_storage_blob_file (storage_file_id),
    KEY idx_storage_blob_version (storage_version_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function storage_ensure_column(mysqli $conn, string $table, string $column, string $definition): void
{
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') {
        return;
    }

    $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    if ($result instanceof mysqli_result && $result->num_rows > 0) {
        $result->free();
        return;
    }
    if ($result instanceof mysqli_result) {
        $result->free();
    }

    $conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
}

storage_ensure_column($conn, 'storage_files', 'shared_audience', "VARCHAR(20) NOT NULL DEFAULT 'all' AFTER `storage_scope`");
storage_ensure_column($conn, 'storage_files', 'shared_target_user_id', "INT NULL DEFAULT NULL AFTER `shared_audience`");

function storage_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function storage_can_manage_shared(string $role): bool
{
    return false;
}

function storage_clean_scope(?string $value, bool $canManageShared): string
{
    return 'personal';
}

function storage_clean_audience(?string $value): string
{
    $allowed = ['all', 'student', 'supervisor', 'user'];
    $value = strtolower(trim((string)$value));
    return in_array($value, $allowed, true) ? $value : 'all';
}

function storage_find_user(mysqli $conn, int $targetUserId): ?array
{
    if ($targetUserId <= 0) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT id, name, username, role
         FROM users
         WHERE id = ?
           AND is_active = 1
           AND (role <> 'student' OR COALESCE(application_status, 'approved') = 'approved')
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function storage_clean_target_user_id(mysqli $conn, $value): ?int
{
    $targetUserId = (int)$value;
    if ($targetUserId <= 0) {
        return null;
    }

    $target = storage_find_user($conn, $targetUserId);
    return $target ? (int)$target['id'] : null;
}

function storage_clean_category(?string $value): string
{
    $allowed = ['requirements', 'generated', 'internship', 'images', 'reports', 'other'];
    $value = strtolower(trim((string)$value));
    return in_array($value, $allowed, true) ? $value : 'other';
}

function storage_category_for_role(string $category, string $role): string
{
    $role = strtolower(trim($role));
    if ($category === 'requirements' && $role !== 'student') {
        return 'reports';
    }
    return $category;
}

function storage_clean_title(?string $value, string $fallback = 'Untitled file'): string
{
    $value = trim((string)$value);
    if ($value === '') {
        $value = $fallback;
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 255, 'UTF-8');
    }

    return substr($value, 0, 255);
}

function storage_clean_notes(?string $value): string
{
    return trim((string)$value);
}

function storage_private_root(): string
{
    $path = dirname(__DIR__) . '/storage_private/files';
    if (!is_dir($path)) {
        @mkdir($path, 0777, true);
    }
    return $path;
}

function storage_should_use_database_storage(): bool
{
    $driver = strtolower(trim((string)getenv('BIOTERN_STORAGE_DRIVER')));
    if (in_array($driver, ['file', 'files', 'filesystem', 'disk', 'local'], true)) {
        return false;
    }

    return true;
}

function storage_is_database_path(string $path): bool
{
    return str_starts_with($path, 'db:file:') || str_starts_with($path, 'db:version:') || $path === 'db:pending';
}

function storage_unlink_local(?string $path): void
{
    $path = (string)$path;
    if ($path !== '' && !storage_is_database_path($path) && is_file($path)) {
        @unlink($path);
    }
}

function storage_random_name(string $extension = ''): string
{
    try {
        $name = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $name = sha1(uniqid((string)mt_rand(), true));
    }

    return $extension !== '' ? ($name . '.' . $extension) : $name;
}

function storage_allowed_extensions(): array
{
    return [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'zip', 'rar', 'ppt', 'pptx',
    ];
}

function storage_detect_file_type(string $extension, string $mimeType): string
{
    $extension = strtolower($extension);
    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || str_starts_with($mimeType, 'image/')) {
        return 'image';
    }
    if ($extension === 'pdf' || $mimeType === 'application/pdf') {
        return 'pdf';
    }
    if (in_array($extension, ['xls', 'xlsx', 'csv'], true)) {
        return 'spreadsheet';
    }
    if (in_array($extension, ['doc', 'docx', 'txt', 'ppt', 'pptx'], true)) {
        return 'document';
    }
    if (in_array($extension, ['zip', 'rar'], true)) {
        return 'archive';
    }
    return 'file';
}

function storage_can_access(array $row, int $userId, string $role): bool
{
    return (int)($row['owner_user_id'] ?? 0) === $userId;
}

function storage_can_delete(array $row, int $userId, string $role): bool
{
    return (int)($row['owner_user_id'] ?? 0) === $userId;
}

function storage_can_edit(array $row, int $userId, string $role): bool
{
    return storage_can_delete($row, $userId, $role);
}

function storage_fetch_one(mysqli $conn, int $fileId): ?array
{
    $stmt = $conn->prepare(
        'SELECT sf.id, sf.owner_user_id, sf.uploader_name, sf.title, sf.original_name, sf.stored_name, sf.storage_scope, sf.shared_audience, sf.shared_target_user_id, tu.name AS shared_target_user_name, sf.category, sf.mime_type, sf.file_extension, sf.file_type, sf.file_size, sf.storage_path, sf.notes, sf.is_starred, sf.created_at, sf.updated_at, sf.deleted_at,
            (SELECT COUNT(*) FROM storage_file_versions v WHERE v.storage_file_id = sf.id) AS version_count
         FROM storage_files sf
         LEFT JOIN users tu ON tu.id = sf.shared_target_user_id
         WHERE sf.id = ?
         LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function storage_map(array $row, int $userId, string $role, string $endpointBase): array
{
    $id = (int)($row['id'] ?? 0);
    return [
        'id' => $id,
        'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
        'uploader_name' => (string)($row['uploader_name'] ?? ''),
        'title' => (string)($row['title'] ?? ''),
        'original_name' => (string)($row['original_name'] ?? ''),
        'scope' => 'personal',
        'shared_audience' => storage_clean_audience((string)($row['shared_audience'] ?? 'all')),
        'shared_target_user_id' => !empty($row['shared_target_user_id']) ? (int)$row['shared_target_user_id'] : null,
        'shared_target_user_name' => (string)($row['shared_target_user_name'] ?? ''),
        'category' => storage_clean_category((string)($row['category'] ?? 'other')),
        'mime_type' => (string)($row['mime_type'] ?? 'application/octet-stream'),
        'file_extension' => (string)($row['file_extension'] ?? ''),
        'file_type' => (string)($row['file_type'] ?? 'file'),
        'file_size' => (int)($row['file_size'] ?? 0),
        'notes' => (string)($row['notes'] ?? ''),
        'is_starred' => (int)($row['is_starred'] ?? 0),
        'is_deleted' => !empty($row['deleted_at']) ? 1 : 0,
        'version_count' => (int)($row['version_count'] ?? 0),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'is_owner' => ((int)($row['owner_user_id'] ?? 0) === $userId) ? 1 : 0,
        'can_edit' => storage_can_edit($row, $userId, $role) ? 1 : 0,
        'can_delete' => storage_can_delete($row, $userId, $role) ? 1 : 0,
        'can_restore' => (!empty($row['deleted_at']) && storage_can_edit($row, $userId, $role)) ? 1 : 0,
        'view_url' => $endpointBase . '?action=view&id=' . $id,
        'download_url' => $endpointBase . '?action=download&id=' . $id,
    ];
}

function storage_store_uploaded_file(array $fileInfo, int $userId, string $scope): array
{
    $size = (int)($fileInfo['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Empty files cannot be uploaded');
    }
    if ($size > 15 * 1024 * 1024) {
        throw new RuntimeException('Please keep uploads under 15 MB');
    }

    $originalName = (string)($fileInfo['name'] ?? 'upload');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, storage_allowed_extensions(), true)) {
        throw new RuntimeException('That file type is not supported yet');
    }

    $tmpName = (string)($fileInfo['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Invalid upload payload');
    }

    $mimeType = (string)($fileInfo['type'] ?? 'application/octet-stream');
    $fileType = storage_detect_file_type($extension, $mimeType);
    $storedName = storage_random_name($extension);

    if (storage_should_use_database_storage()) {
        $blob = file_get_contents($tmpName);
        if (!is_string($blob) || $blob === '') {
            throw new RuntimeException('Unable to read the uploaded file');
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_extension' => $extension,
            'file_type' => $fileType,
            'file_size' => $size,
            'storage_path' => 'db:pending',
            'blob_data' => $blob,
        ];
    }

    $baseDir = storage_private_root();
    $subDir = $scope === 'shared' ? 'shared' : ('user_' . $userId);
    $targetDir = $baseDir . '/' . $subDir;
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }

    $targetPath = $targetDir . '/' . $storedName;
    if (!@move_uploaded_file($tmpName, $targetPath)) {
        $blob = file_get_contents($tmpName);
        if (!is_string($blob) || $blob === '') {
            throw new RuntimeException('Unable to save the uploaded file');
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_extension' => $extension,
            'file_type' => $fileType,
            'file_size' => $size,
            'storage_path' => 'db:pending',
            'blob_data' => $blob,
        ];
    }

    return [
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'mime_type' => $mimeType,
        'file_extension' => $extension,
        'file_type' => $fileType,
        'file_size' => $size,
        'storage_path' => $targetPath,
    ];
}

function storage_save_file_blob(mysqli $conn, int $fileId, string $blob, string $mimeType, int $fileSize): void
{
    if ($fileId <= 0 || $blob === '') {
        throw new RuntimeException('Unable to save file content');
    }

    $delete = $conn->prepare('DELETE FROM storage_file_blobs WHERE storage_file_id = ? AND storage_version_id IS NULL');
    if ($delete) {
        $delete->bind_param('i', $fileId);
        $delete->execute();
        $delete->close();
    }

    $stmt = $conn->prepare('INSERT INTO storage_file_blobs (storage_file_id, storage_version_id, mime_type, file_size, blob_data) VALUES (?, NULL, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Unable to save file content');
    }
    $empty = '';
    $stmt->bind_param('isib', $fileId, $mimeType, $fileSize, $empty);
    $stmt->send_long_data(3, $blob);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to save file content');
    }
    $stmt->close();
}

function storage_save_version_blob(mysqli $conn, int $fileId, int $versionId, string $blob, string $mimeType, int $fileSize): void
{
    if ($fileId <= 0 || $versionId <= 0 || $blob === '') {
        return;
    }

    $stmt = $conn->prepare('INSERT INTO storage_file_blobs (storage_file_id, storage_version_id, mime_type, file_size, blob_data) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $empty = '';
    $stmt->bind_param('iisib', $fileId, $versionId, $mimeType, $fileSize, $empty);
    $stmt->send_long_data(4, $blob);
    $stmt->execute();
    $stmt->close();
}

function storage_copy_file_blob_to_version(mysqli $conn, int $fileId, int $versionId): void
{
    $stmt = $conn->prepare('SELECT blob_data, mime_type, file_size FROM storage_file_blobs WHERE storage_file_id = ? AND storage_version_id IS NULL LIMIT 1');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $fileId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || !is_string($row['blob_data'] ?? null)) {
        return;
    }

    storage_save_version_blob(
        $conn,
        $fileId,
        $versionId,
        (string)$row['blob_data'],
        (string)($row['mime_type'] ?? 'application/octet-stream'),
        (int)($row['file_size'] ?? 0)
    );
}

function storage_get_blob(mysqli $conn, int $fileId, ?int $versionId = null): ?array
{
    if ($versionId !== null && $versionId > 0) {
        $stmt = $conn->prepare('SELECT blob_data, mime_type, file_size FROM storage_file_blobs WHERE storage_version_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $versionId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row && is_string($row['blob_data'] ?? null)) {
                return $row;
            }
        }
        return null;
    }

    $stmt = $conn->prepare('SELECT blob_data, mime_type, file_size FROM storage_file_blobs WHERE storage_file_id = ? AND storage_version_id IS NULL LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $fileId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ($row && is_string($row['blob_data'] ?? null)) ? $row : null;
}

function storage_mark_file_as_database_backed(mysqli $conn, int $fileId): void
{
    $path = 'db:file:' . $fileId;
    $stmt = $conn->prepare('UPDATE storage_files SET storage_path = ? WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('si', $path, $fileId);
        $stmt->execute();
        $stmt->close();
    }
}

function storage_mark_version_as_database_backed(mysqli $conn, int $versionId): void
{
    $path = 'db:version:' . $versionId;
    $stmt = $conn->prepare('UPDATE storage_file_versions SET storage_path = ? WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('si', $path, $versionId);
        $stmt->execute();
        $stmt->close();
    }
}

function storage_stream_file_payload(mysqli $conn, string $path, int $fileId, ?int $versionId, string $mimeType, string $name, string $disposition): void
{
    if ($path !== '' && !storage_is_database_path($path) && is_file($path)) {
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string)filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    $blob = storage_get_blob($conn, $fileId, $versionId);
    if (!$blob) {
        http_response_code(404);
        exit('File is missing');
    }

    $payload = (string)$blob['blob_data'];
    header('Content-Type: ' . ($mimeType !== '' ? $mimeType : (string)($blob['mime_type'] ?? 'application/octet-stream')));
    header('Content-Length: ' . (string)strlen($payload));
    header('Content-Disposition: ' . $disposition . '; filename="' . $name . '"');
    header('X-Content-Type-Options: nosniff');
    echo $payload;
    exit;
}

function storage_log_activity(mysqli $conn, int $userId, string $actionType, ?int $fileId, ?string $title, ?string $details = null): void
{
    $stmt = $conn->prepare('INSERT INTO storage_activity_logs (user_id, action_type, storage_file_id, title, details) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isiss', $userId, $actionType, $fileId, $title, $details);
    $stmt->execute();
    $stmt->close();
}

function storage_required_documents(array $files, bool $isStudent): array
{
    if (!$isStudent) {
        return [];
    }

    $checks = [
        ['label' => 'Resume', 'hint' => 'Upload your latest resume.', 'match' => 'resume'],
        ['label' => 'Application Letter', 'hint' => 'Keep your application letter in Storage.', 'match' => 'application'],
        ['label' => 'Endorsement / MOA', 'hint' => 'Upload your endorsement or MOA copy.', 'match' => 'moa'],
        ['label' => 'Requirement Files', 'hint' => 'Store your requirement documents here.', 'match' => 'requirements'],
    ];

    return array_map(static function (array $item) use ($files): array {
        $needle = strtolower($item['match']);
        $isComplete = false;
        foreach ($files as $file) {
            $haystack = strtolower(($file['title'] ?? '') . ' ' . ($file['original_name'] ?? '') . ' ' . ($file['category'] ?? ''));
            if (str_contains($haystack, $needle)) {
                $isComplete = true;
                break;
            }
        }
        return [
            'label' => $item['label'],
            'hint' => $item['hint'],
            'is_complete' => $isComplete ? 1 : 0,
        ];
    }, $checks);
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = strtolower(trim((string)($_GET['action'] ?? $_POST['action'] ?? '')));

if ($method === 'GET' && $action === 'download_version') {
    $versionId = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare('SELECT v.id, v.original_name, v.mime_type, v.storage_path, v.file_size, v.storage_file_id, sf.owner_user_id, sf.storage_scope, sf.shared_audience, sf.shared_target_user_id FROM storage_file_versions v INNER JOIN storage_files sf ON sf.id = v.storage_file_id WHERE v.id = ? LIMIT 1');
    if (!$stmt) {
        http_response_code(500);
        exit('Version unavailable');
    }
    $stmt->bind_param('i', $versionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $version = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if (!$version || !storage_can_access($version, $userId, $userRole)) {
        http_response_code(404);
        exit('Version not found');
    }
    $path = (string)($version['storage_path'] ?? '');
    $name = str_replace('"', '', (string)($version['original_name'] ?? 'download'));
    storage_stream_file_payload($conn, $path, (int)($version['storage_file_id'] ?? 0), $versionId, (string)($version['mime_type'] ?? 'application/octet-stream'), $name, 'attachment');
}

if ($method === 'GET' && in_array($action, ['download', 'view'], true)) {
    $fileId = (int)($_GET['id'] ?? 0);
    $file = storage_fetch_one($conn, $fileId);
    if (!$file || !empty($file['deleted_at']) || !storage_can_access($file, $userId, $userRole)) {
        http_response_code(404);
        exit('File not found');
    }

    $disposition = $action === 'view' ? 'inline' : 'attachment';
    $mimeType = (string)($file['mime_type'] ?? 'application/octet-stream');
    $name = str_replace('"', '', (string)($file['original_name'] ?? 'download'));
    storage_stream_file_payload($conn, (string)($file['storage_path'] ?? ''), (int)$file['id'], null, $mimeType, $name, $disposition);
}

if ($method === 'GET' && $action === 'history') {
    $fileId = (int)($_GET['id'] ?? 0);
    $file = storage_fetch_one($conn, $fileId);
    if (!$file || !storage_can_access($file, $userId, $userRole)) {
        storage_json(404, ['success' => false, 'message' => 'File not found']);
    }

    $stmt = $conn->prepare('SELECT id, original_name, file_size, created_at FROM storage_file_versions WHERE storage_file_id = ? ORDER BY created_at DESC, id DESC');
    if (!$stmt) {
        storage_json(500, ['success' => false, 'message' => 'Unable to load version history']);
    }
    $stmt->bind_param('i', $fileId);
    $stmt->execute();
    $result = $stmt->get_result();
    $versions = [];
    while ($row = $result->fetch_assoc()) {
        $versions[] = [
            'id' => (int)($row['id'] ?? 0),
            'original_name' => (string)($row['original_name'] ?? ''),
            'file_size' => (int)($row['file_size'] ?? 0),
            'created_at' => (string)($row['created_at'] ?? ''),
            'download_url' => $endpointBase . '?action=download_version&id=' . (int)($row['id'] ?? 0),
        ];
    }
    $stmt->close();

    storage_json(200, ['success' => true, 'versions' => $versions]);
}

if ($method === 'GET') {
    $stmt = $conn->prepare(
        'SELECT sf.id, sf.owner_user_id, sf.uploader_name, sf.title, sf.original_name, sf.stored_name, sf.storage_scope, sf.shared_audience, sf.shared_target_user_id, tu.name AS shared_target_user_name, sf.category, sf.mime_type, sf.file_extension, sf.file_type, sf.file_size, sf.storage_path, sf.notes, sf.is_starred, sf.created_at, sf.updated_at, sf.deleted_at,
            (SELECT COUNT(*) FROM storage_file_versions v WHERE v.storage_file_id = sf.id) AS version_count
         FROM storage_files sf
         LEFT JOIN users tu ON tu.id = sf.shared_target_user_id
         WHERE sf.owner_user_id = ?
         ORDER BY sf.deleted_at IS NULL DESC, sf.is_starred DESC, sf.updated_at DESC, sf.id DESC'
    );
    if (!$stmt) {
        storage_json(500, ['success' => false, 'message' => 'Unable to load storage files']);
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $files = [];
    while ($row = $result->fetch_assoc()) {
        if (!storage_can_access($row, $userId, $userRole)) {
            continue;
        }
        if (!empty($row['deleted_at']) && !storage_can_edit($row, $userId, $userRole)) {
            continue;
        }
        $files[] = storage_map($row, $userId, $userRole, $endpointBase);
    }
    $stmt->close();

    $activityStmt = $conn->prepare('SELECT action_type, title, details, created_at FROM storage_activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 4');
    $activity = [];
    if ($activityStmt) {
        $activityStmt->bind_param('i', $userId);
        $activityStmt->execute();
        $activityResult = $activityStmt->get_result();
        while ($row = $activityResult->fetch_assoc()) {
            $activity[] = [
                'action_type' => (string)($row['action_type'] ?? ''),
                'title' => (string)($row['title'] ?? ''),
                'details' => (string)($row['details'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }
        $activityStmt->close();
    }

    storage_json(200, [
        'success' => true,
        'files' => $files,
        'activity' => $activity,
        'required_documents' => storage_required_documents($files, $userRole === 'student'),
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

$action = strtolower(trim((string)($data['action'] ?? $action)));
if ($action === '') {
    storage_json(400, ['success' => false, 'message' => 'Missing action']);
}

if ($action === 'upload') {
    if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
        storage_json(422, ['success' => false, 'message' => 'Please choose a file to upload']);
    }
    if ((int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        storage_json(422, ['success' => false, 'message' => 'Upload failed. Please try another file.']);
    }

    $scope = storage_clean_scope((string)($_POST['scope'] ?? 'personal'), storage_can_manage_shared($userRole));
    $sharedAudience = 'all';
    $sharedTargetUserId = null;
    $category = storage_category_for_role(storage_clean_category((string)($_POST['category'] ?? 'other')), $userRole);
    $notes = storage_clean_notes((string)($_POST['notes'] ?? ''));

    try {
        $storedFile = storage_store_uploaded_file($_FILES['file'], $userId, $scope);
    } catch (Throwable $e) {
        storage_json(422, ['success' => false, 'message' => $e->getMessage()]);
    }

    $title = storage_clean_title((string)($_POST['title'] ?? ''), pathinfo($storedFile['original_name'], PATHINFO_FILENAME));

    $stmt = $conn->prepare(
        'INSERT INTO storage_files (owner_user_id, uploader_name, title, original_name, stored_name, storage_scope, shared_audience, shared_target_user_id, category, mime_type, file_extension, file_type, file_size, storage_path, notes, is_starred)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
    );
    if (!$stmt) {
        storage_unlink_local($storedFile['storage_path']);
        storage_json(500, ['success' => false, 'message' => 'Unable to save file record']);
    }

    $stmt->bind_param(
        'issssssissssiss',
        $userId,
        $userName,
        $title,
        $storedFile['original_name'],
        $storedFile['stored_name'],
        $scope,
        $sharedAudience,
        $sharedTargetUserId,
        $category,
        $storedFile['mime_type'],
        $storedFile['file_extension'],
        $storedFile['file_type'],
        $storedFile['file_size'],
        $storedFile['storage_path'],
        $notes
    );

    if (!$stmt->execute()) {
        $stmt->close();
        storage_unlink_local($storedFile['storage_path']);
        storage_json(500, ['success' => false, 'message' => 'Unable to save file record']);
    }

    $fileId = (int)$stmt->insert_id;
    $stmt->close();
    if (!empty($storedFile['blob_data']) && is_string($storedFile['blob_data'])) {
        try {
            storage_save_file_blob($conn, $fileId, $storedFile['blob_data'], $storedFile['mime_type'], (int)$storedFile['file_size']);
            storage_mark_file_as_database_backed($conn, $fileId);
        } catch (Throwable $e) {
            $cleanup = $conn->prepare('DELETE FROM storage_files WHERE id = ? LIMIT 1');
            if ($cleanup) {
                $cleanup->bind_param('i', $fileId);
                $cleanup->execute();
                $cleanup->close();
            }
            storage_json(500, ['success' => false, 'message' => 'Unable to save uploaded file content']);
        }
    }
    $uploadDetails = 'Uploaded to ' . $scope . ' storage';
    storage_log_activity($conn, $userId, 'upload', $fileId, $title, $uploadDetails);
    $file = storage_fetch_one($conn, $fileId);
    storage_json(200, ['success' => true, 'file' => $file ? storage_map($file, $userId, $userRole, $endpointBase) : null]);
}

if ($action === 'update') {
    $fileId = (int)($data['id'] ?? $_POST['id'] ?? 0);
    $file = storage_fetch_one($conn, $fileId);
    if (!$file || !storage_can_edit($file, $userId, $userRole)) {
        storage_json(403, ['success' => false, 'message' => 'You cannot edit this file']);
    }

    $scope = storage_clean_scope((string)($data['scope'] ?? $_POST['scope'] ?? $file['storage_scope']), storage_can_manage_shared($userRole));
    $sharedAudience = 'all';
    $sharedTargetUserId = null;
    $category = storage_category_for_role(storage_clean_category((string)($data['category'] ?? $_POST['category'] ?? $file['category'])), $userRole);
    $notes = storage_clean_notes((string)($data['notes'] ?? $_POST['notes'] ?? $file['notes']));
    $title = storage_clean_title((string)($data['title'] ?? $_POST['title'] ?? $file['title']), pathinfo((string)$file['original_name'], PATHINFO_FILENAME));

    $hasReplacement = !empty($_FILES['file']) && is_array($_FILES['file']) && (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    $replacement = null;

    if ($hasReplacement) {
        try {
            $replacement = storage_store_uploaded_file($_FILES['file'], $userId, $scope);
        } catch (Throwable $e) {
            storage_json(422, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    if ($hasReplacement && $replacement) {
        $versionStmt = $conn->prepare(
            'INSERT INTO storage_file_versions (storage_file_id, original_name, stored_name, mime_type, file_extension, file_type, file_size, storage_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if ($versionStmt) {
            $versionStmt->bind_param(
                'isssssis',
                $fileId,
                $file['original_name'],
                $file['stored_name'],
                $file['mime_type'],
                $file['file_extension'],
                $file['file_type'],
                $file['file_size'],
                $file['storage_path']
            );
            $versionStmt->execute();
            $versionId = (int)$versionStmt->insert_id;
            $versionStmt->close();
            if ($versionId > 0 && storage_is_database_path((string)($file['storage_path'] ?? ''))) {
                storage_copy_file_blob_to_version($conn, $fileId, $versionId);
                storage_mark_version_as_database_backed($conn, $versionId);
            }
        }

        $stmt = $conn->prepare(
            'UPDATE storage_files
             SET title = ?, category = ?, notes = ?, storage_scope = ?, shared_audience = ?, shared_target_user_id = ?, original_name = ?, stored_name = ?, mime_type = ?, file_extension = ?, file_type = ?, file_size = ?, storage_path = ?, updated_at = NOW()
             WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            storage_unlink_local($replacement['storage_path']);
            storage_json(500, ['success' => false, 'message' => 'Unable to update this file']);
        }

        $stmt->bind_param(
            'sssssisssssisi',
            $title,
            $category,
            $notes,
            $scope,
            $sharedAudience,
            $sharedTargetUserId,
            $replacement['original_name'],
            $replacement['stored_name'],
            $replacement['mime_type'],
            $replacement['file_extension'],
            $replacement['file_type'],
            $replacement['file_size'],
            $replacement['storage_path'],
            $fileId
        );

        if (!$stmt->execute()) {
            $stmt->close();
            storage_unlink_local($replacement['storage_path']);
            storage_json(500, ['success' => false, 'message' => 'Unable to update this file']);
        }
        $stmt->close();
        if (!empty($replacement['blob_data']) && is_string($replacement['blob_data'])) {
            try {
                storage_save_file_blob($conn, $fileId, $replacement['blob_data'], $replacement['mime_type'], (int)$replacement['file_size']);
                storage_mark_file_as_database_backed($conn, $fileId);
            } catch (Throwable $e) {
                storage_json(500, ['success' => false, 'message' => 'Unable to save replacement file content']);
            }
        }
        $replaceDetails = 'Replaced uploaded file version';
        storage_log_activity($conn, $userId, 'replace', $fileId, $title, $replaceDetails);
    } else {
        $stmt = $conn->prepare(
            'UPDATE storage_files SET title = ?, category = ?, notes = ?, storage_scope = ?, shared_audience = ?, shared_target_user_id = ?, updated_at = NOW() WHERE id = ? LIMIT 1'
        );
        if (!$stmt) {
            storage_json(500, ['success' => false, 'message' => 'Unable to update this file']);
        }
        $stmt->bind_param('sssssii', $title, $category, $notes, $scope, $sharedAudience, $sharedTargetUserId, $fileId);
        if (!$stmt->execute()) {
            $stmt->close();
            storage_json(500, ['success' => false, 'message' => 'Unable to update this file']);
        }
        $stmt->close();
        $updateDetails = 'Updated file details';
        storage_log_activity($conn, $userId, 'update', $fileId, $title, $updateDetails);
    }

    $updated = storage_fetch_one($conn, $fileId);
    storage_json(200, ['success' => true, 'file' => $updated ? storage_map($updated, $userId, $userRole, $endpointBase) : null]);
}

if ($action === 'toggle_star') {
    $fileId = (int)($data['id'] ?? 0);
    $file = storage_fetch_one($conn, $fileId);
    if (!$file || !storage_can_edit($file, $userId, $userRole) || !empty($file['deleted_at'])) {
        storage_json(403, ['success' => false, 'message' => 'You cannot update this file']);
    }

    $nextValue = !empty($file['is_starred']) ? 0 : 1;
    $stmt = $conn->prepare('UPDATE storage_files SET is_starred = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
    if (!$stmt) {
        storage_json(500, ['success' => false, 'message' => 'Unable to update this file']);
    }
    $stmt->bind_param('ii', $nextValue, $fileId);
    if (!$stmt->execute()) {
        $stmt->close();
        storage_json(500, ['success' => false, 'message' => 'Unable to update this file']);
    }
    $stmt->close();
    storage_log_activity($conn, $userId, 'toggle_star', $fileId, (string)($file['title'] ?? ''), $nextValue ? 'Marked as starred' : 'Removed star');

    $updated = storage_fetch_one($conn, $fileId);
    storage_json(200, ['success' => true, 'file' => $updated ? storage_map($updated, $userId, $userRole, $endpointBase) : null]);
}

if ($action === 'delete') {
    $fileId = (int)($data['id'] ?? 0);
    $file = storage_fetch_one($conn, $fileId);
    if (!$file || !storage_can_delete($file, $userId, $userRole)) {
        storage_json(403, ['success' => false, 'message' => 'You cannot delete this file']);
    }

    $stmt = $conn->prepare('UPDATE storage_files SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1');
    if (!$stmt) {
        storage_json(500, ['success' => false, 'message' => 'Unable to delete this file']);
    }
    $stmt->bind_param('i', $fileId);
    if (!$stmt->execute()) {
        $stmt->close();
        storage_json(500, ['success' => false, 'message' => 'Unable to delete this file']);
    }
    $stmt->close();
    storage_log_activity($conn, $userId, 'delete', $fileId, (string)($file['title'] ?? ''), 'Moved file to trash');

    storage_json(200, ['success' => true]);
}

if ($action === 'restore') {
    $fileId = (int)($data['id'] ?? 0);
    $file = storage_fetch_one($conn, $fileId);
    if (!$file || !storage_can_edit($file, $userId, $userRole) || empty($file['deleted_at'])) {
        storage_json(403, ['success' => false, 'message' => 'You cannot restore this file']);
    }

    $stmt = $conn->prepare('UPDATE storage_files SET deleted_at = NULL, updated_at = NOW() WHERE id = ? LIMIT 1');
    if (!$stmt) {
        storage_json(500, ['success' => false, 'message' => 'Unable to restore this file']);
    }
    $stmt->bind_param('i', $fileId);
    if (!$stmt->execute()) {
        $stmt->close();
        storage_json(500, ['success' => false, 'message' => 'Unable to restore this file']);
    }
    $stmt->close();
    storage_log_activity($conn, $userId, 'restore', $fileId, (string)($file['title'] ?? ''), 'Restored file from trash');

    $updated = storage_fetch_one($conn, $fileId);
    storage_json(200, ['success' => true, 'file' => $updated ? storage_map($updated, $userId, $userRole, $endpointBase) : null]);
}

if ($action === 'bulk_delete' || $action === 'bulk_restore') {
    $ids = $data['ids'] ?? [];
    if (!is_array($ids) || !$ids) {
        storage_json(422, ['success' => false, 'message' => 'No files selected']);
    }

    $processed = 0;
    foreach ($ids as $rawId) {
        $fileId = (int)$rawId;
        if ($fileId <= 0) {
            continue;
        }
        $file = storage_fetch_one($conn, $fileId);
        if (!$file) {
            continue;
        }

        if ($action === 'bulk_delete') {
            if (!$file['deleted_at'] && storage_can_delete($file, $userId, $userRole)) {
                $stmt = $conn->prepare('UPDATE storage_files SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $fileId);
                    if ($stmt->execute()) {
                        $processed++;
                        storage_log_activity($conn, $userId, 'bulk_delete', $fileId, (string)($file['title'] ?? ''), 'Moved file to trash in bulk action');
                    }
                    $stmt->close();
                }
            }
        } else {
            if (!empty($file['deleted_at']) && storage_can_edit($file, $userId, $userRole)) {
                $stmt = $conn->prepare('UPDATE storage_files SET deleted_at = NULL, updated_at = NOW() WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $fileId);
                    if ($stmt->execute()) {
                        $processed++;
                        storage_log_activity($conn, $userId, 'bulk_restore', $fileId, (string)($file['title'] ?? ''), 'Restored file in bulk action');
                    }
                    $stmt->close();
                }
            }
        }
    }

    storage_json(200, ['success' => true, 'processed' => $processed]);
}

storage_json(400, ['success' => false, 'message' => 'Unsupported action']);
