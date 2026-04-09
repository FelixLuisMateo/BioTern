<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/avatar.php';

if (!($conn instanceof mysqli)) {
    fwrite(STDERR, "Database connection is not available.\n");
    exit(1);
}

$argvList = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argvList, true);
$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

$createSql = "CREATE TABLE IF NOT EXISTS user_profile_pictures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    image_mime VARCHAR(64) NOT NULL,
    image_data LONGBLOB NOT NULL,
    image_size INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_profile_picture (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!$conn->query($createSql)) {
    fwrite(STDERR, "Failed creating table user_profile_pictures: " . $conn->error . "\n");
    exit(1);
}

$sql = "SELECT id, profile_picture FROM users
        WHERE profile_picture IS NOT NULL
          AND TRIM(profile_picture) <> ''
          AND LOWER(TRIM(profile_picture)) NOT IN ('db-avatar','db_avatar')";
$res = $conn->query($sql);
if (!$res) {
    fwrite(STDERR, "Failed fetching users: " . $conn->error . "\n");
    exit(1);
}

$insertStmt = $conn->prepare(
    "INSERT INTO user_profile_pictures (user_id, image_mime, image_data, image_size, created_at, updated_at)
     VALUES (?, ?, ?, ?, NOW(), NOW())
     ON DUPLICATE KEY UPDATE image_mime = VALUES(image_mime), image_data = VALUES(image_data), image_size = VALUES(image_size), updated_at = NOW()"
);
$updateUserStmt = $conn->prepare("UPDATE users SET profile_picture = 'db-avatar' WHERE id = ? LIMIT 1");

if (!$insertStmt || !$updateUserStmt) {
    fwrite(STDERR, "Failed preparing statements: " . $conn->error . "\n");
    exit(1);
}

$total = 0;
$migratable = 0;
$migrated = 0;
$skippedMissing = 0;
$skippedMime = 0;
$skippedRead = 0;
$errors = 0;
$errorDetails = [];

while ($row = $res->fetch_assoc()) {
    $total++;
    $userId = (int)($row['id'] ?? 0);
    $raw = (string)($row['profile_picture'] ?? '');
    if ($userId <= 0 || $raw === '') {
        continue;
    }

    $resolved = biotern_avatar_resolve_existing_path($raw);
    if ($resolved === '') {
        $skippedMissing++;
        continue;
    }

    $abs = dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $resolved), '/');
    if (!is_file($abs)) {
        $skippedMissing++;
        continue;
    }

    $binary = @file_get_contents($abs);
    if (!is_string($binary) || $binary === '') {
        $skippedRead++;
        continue;
    }

    $imgInfo = function_exists('getimagesizefromstring') ? @getimagesizefromstring($binary) : false;
    $mime = strtolower((string)($imgInfo['mime'] ?? ''));
    if ($mime === '' && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $abs);
            finfo_close($finfo);
            $mime = strtolower((string)$detected);
        }
    }

    if (!in_array($mime, $allowedMime, true)) {
        $skippedMime++;
        continue;
    }

    $migratable++;
    if (!$apply) {
        continue;
    }

    $size = strlen($binary);
    $blob = '';
    $insertStmt->bind_param('isbi', $userId, $mime, $blob, $size);
    $insertStmt->send_long_data(2, $binary);
    if (!$insertStmt->execute()) {
        $errors++;
        $errorDetails[] = 'user_id=' . $userId . ' insert failed: ' . $insertStmt->error;
        continue;
    }

    $updateUserStmt->bind_param('i', $userId);
    if (!$updateUserStmt->execute()) {
        $errors++;
        $errorDetails[] = 'user_id=' . $userId . ' update failed: ' . $updateUserStmt->error;
        continue;
    }

    $migrated++;
}

$res->close();
$insertStmt->close();
$updateUserStmt->close();

$mode = $apply ? 'apply' : 'dry-run';
echo "Profile Picture Migration ($mode)\n";
echo "Total candidate users: $total\n";
echo "Migratable users: $migratable\n";
echo "Migrated users: $migrated\n";
echo "Skipped missing file: $skippedMissing\n";
echo "Skipped unsupported mime: $skippedMime\n";
echo "Skipped unreadable file: $skippedRead\n";
echo "Errors: $errors\n";
if ($errors > 0 && !empty($errorDetails)) {
    echo "Error details:\n";
    foreach ($errorDetails as $line) {
        echo "- $line\n";
    }
}

exit(0);
