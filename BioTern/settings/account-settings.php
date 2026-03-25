<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
if ($currentUserId <= 0) {
    header('Location: auth-login-cover.php');
    exit;
}

function account_settings_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function account_settings_normalize_path(string $path): string
{
    return ltrim(str_replace('\\', '/', trim($path)), '/');
}

function account_settings_table_has_column(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result instanceof mysqli_result && $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

function account_settings_refresh_user(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT id, name, username, email, role, profile_picture, password, is_active
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    return $row;
}

function account_settings_set_flash(string $type, string $message): void
{
    $_SESSION['account_settings_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function account_settings_redirect_back(): void
{
    header('Location: account-settings.php');
    exit;
}

$user = account_settings_refresh_user($conn, $currentUserId);
if (!$user || (int)($user['is_active'] ?? 0) !== 1) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: auth-login-cover.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string)($_POST['action'] ?? '')));

    if ($action === 'save_profile') {
        $name = trim((string)($_POST['name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if ($name === '' || $username === '' || $email === '') {
            account_settings_set_flash('danger', 'Name, username, and email are required.');
            account_settings_redirect_back();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            account_settings_set_flash('danger', 'Please provide a valid email address.');
            account_settings_redirect_back();
        }

        $dupStmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE (username = ? OR email = ?) AND id <> ?
            LIMIT 1
        ");
        if ($dupStmt) {
            $dupStmt->bind_param('ssi', $username, $email, $currentUserId);
            $dupStmt->execute();
            $duplicate = $dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();
            if ($duplicate) {
                account_settings_set_flash('danger', 'That username or email is already used by another account.');
                account_settings_redirect_back();
            }
        }

        $updateStmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email = ? WHERE id = ? LIMIT 1");
        if (!$updateStmt) {
            account_settings_set_flash('danger', 'Unable to save profile at the moment.');
            account_settings_redirect_back();
        }
        $updateStmt->bind_param('sssi', $name, $username, $email, $currentUserId);
        $ok = $updateStmt->execute();
        $error = (string)$updateStmt->error;
        $updateStmt->close();

        if (!$ok) {
            account_settings_set_flash('danger', $error !== '' ? ('Failed to save profile: ' . $error) : 'Failed to save profile.');
            account_settings_redirect_back();
        }

        $_SESSION['name'] = $name;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;

        account_settings_set_flash('success', 'Profile updated successfully.');
        account_settings_redirect_back();
    }

    if ($action === 'upload_avatar') {
        if (!isset($_FILES['profile_picture']) || !is_array($_FILES['profile_picture'])) {
            account_settings_set_flash('danger', 'Please choose an image file.');
            account_settings_redirect_back();
        }

        $file = $_FILES['profile_picture'];
        $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            account_settings_set_flash('danger', 'Upload failed. Please try again.');
            account_settings_redirect_back();
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $original = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime = $finfo ? (string)finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($size <= 0 || $size > (3 * 1024 * 1024)) {
            account_settings_set_flash('danger', 'Image must be less than 3MB.');
            account_settings_redirect_back();
        }
        if (!in_array($ext, $allowedExt, true) || ($mime !== '' && !in_array($mime, $allowedMime, true))) {
            account_settings_set_flash('danger', 'Only JPG, PNG, WEBP, or GIF images are allowed.');
            account_settings_redirect_back();
        }

        $uploadsDir = dirname(__DIR__) . '/uploads/profile_pictures';
        if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0755, true) && !is_dir($uploadsDir)) {
            account_settings_set_flash('danger', 'Could not prepare upload folder.');
            account_settings_redirect_back();
        }

        try {
            $random = bin2hex(random_bytes(6));
        } catch (Throwable $e) {
            $random = (string)mt_rand(100000, 999999);
        }

        $targetFile = 'user_' . $currentUserId . '_' . time() . '_' . $random . '.' . $ext;
        $targetAbs = $uploadsDir . '/' . $targetFile;
        $targetRel = 'uploads/profile_pictures/' . $targetFile;

        if (!move_uploaded_file($tmpPath, $targetAbs)) {
            account_settings_set_flash('danger', 'Failed to save uploaded image.');
            account_settings_redirect_back();
        }

        $oldRel = account_settings_normalize_path((string)($user['profile_picture'] ?? ''));
        $updateStmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ? LIMIT 1");
        if (!$updateStmt) {
            @unlink($targetAbs);
            account_settings_set_flash('danger', 'Unable to save profile image.');
            account_settings_redirect_back();
        }
        $updateStmt->bind_param('si', $targetRel, $currentUserId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        if (!$ok) {
            @unlink($targetAbs);
            account_settings_set_flash('danger', 'Unable to save profile image.');
            account_settings_redirect_back();
        }

        if ($oldRel !== '' && strpos($oldRel, 'uploads/profile_pictures/') === 0) {
            $oldAbs = dirname(__DIR__) . '/' . $oldRel;
            if (is_file($oldAbs)) {
                @unlink($oldAbs);
            }
        }

        $_SESSION['profile_picture'] = $targetRel;
        account_settings_set_flash('success', 'Profile picture updated.');
        account_settings_redirect_back();
    }

    if ($action === 'remove_avatar') {
        $oldRel = account_settings_normalize_path((string)($user['profile_picture'] ?? ''));
        $updateStmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ? LIMIT 1");
        if (!$updateStmt) {
            account_settings_set_flash('danger', 'Unable to remove profile picture.');
            account_settings_redirect_back();
        }
        $updateStmt->bind_param('i', $currentUserId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        if (!$ok) {
            account_settings_set_flash('danger', 'Unable to remove profile picture.');
            account_settings_redirect_back();
        }

        if ($oldRel !== '' && strpos($oldRel, 'uploads/profile_pictures/') === 0) {
            $oldAbs = dirname(__DIR__) . '/' . $oldRel;
            if (is_file($oldAbs)) {
                @unlink($oldAbs);
            }
        }

        $_SESSION['profile_picture'] = '';
        account_settings_set_flash('success', 'Profile picture removed.');
        account_settings_redirect_back();
    }

    if ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            account_settings_set_flash('danger', 'All password fields are required.');
            account_settings_redirect_back();
        }
        if ($newPassword !== $confirmPassword) {
            account_settings_set_flash('danger', 'New password and confirmation do not match.');
            account_settings_redirect_back();
        }
        if (strlen($newPassword) < 8) {
            account_settings_set_flash('danger', 'New password must be at least 8 characters long.');
            account_settings_redirect_back();
        }

        $storedPassword = (string)($user['password'] ?? '');
        $matches = password_verify($currentPassword, $storedPassword);
        if (!$matches && $storedPassword !== '' && hash_equals($storedPassword, $currentPassword)) {
            $matches = true;
        }
        if (!$matches) {
            account_settings_set_flash('danger', 'Current password is incorrect.');
            account_settings_redirect_back();
        }
        if (hash_equals($currentPassword, $newPassword)) {
            account_settings_set_flash('danger', 'New password must be different from current password.');
            account_settings_redirect_back();
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($newHash === false) {
            account_settings_set_flash('danger', 'Unable to secure the new password.');
            account_settings_redirect_back();
        }

        $passwordColumn = account_settings_table_has_column($conn, 'users', 'password_hash') ? 'password_hash' : 'password';
        $updateSql = "UPDATE users SET {$passwordColumn} = ? WHERE id = ? LIMIT 1";
        $updateStmt = $conn->prepare($updateSql);
        if (!$updateStmt) {
            account_settings_set_flash('danger', 'Unable to update password.');
            account_settings_redirect_back();
        }
        $updateStmt->bind_param('si', $newHash, $currentUserId);
        $ok = $updateStmt->execute();
        $updateStmt->close();

        if (!$ok) {
            account_settings_set_flash('danger', 'Unable to update password.');
            account_settings_redirect_back();
        }

        if ($passwordColumn !== 'password' && account_settings_table_has_column($conn, 'users', 'password')) {
            $legacyStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1");
            if ($legacyStmt) {
                $legacyStmt->bind_param('si', $newHash, $currentUserId);
                $legacyStmt->execute();
                $legacyStmt->close();
            }
        }

        account_settings_set_flash('success', 'Password updated successfully.');
        account_settings_redirect_back();
    }

    account_settings_set_flash('warning', 'No valid action was provided.');
    account_settings_redirect_back();
}

$user = account_settings_refresh_user($conn, $currentUserId) ?? $user;

$flash = $_SESSION['account_settings_flash'] ?? null;
unset($_SESSION['account_settings_flash']);

$profileRel = account_settings_normalize_path((string)($user['profile_picture'] ?? ''));
$profileAbs = $profileRel !== '' ? (dirname(__DIR__) . '/' . $profileRel) : '';
$profileUrl = ($profileRel !== '' && is_file($profileAbs))
    ? $profileRel . '?v=' . rawurlencode((string)@filemtime($profileAbs))
    : ('assets/images/avatar/' . (($currentUserId % 5) + 1) . '.png');

$page_title = 'Account Settings';
$page_body_class = 'settings-page';
include dirname(__DIR__) . '/includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Account Settings</h5>
                    <p class="text-muted mb-0">Manage your profile details, photo, and password.</p>
                </div>
            </div>
        </div>

        <?php if (is_array($flash) && !empty($flash['message'])): ?>
            <div class="alert alert-<?php echo account_settings_h((string)($flash['type'] ?? 'info')); ?>" role="alert">
                <?php echo account_settings_h((string)$flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="<?php echo account_settings_h($profileUrl); ?>" alt="Profile picture" class="img-fluid rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                        <h6 class="mb-1"><?php echo account_settings_h((string)($user['name'] ?? '')); ?></h6>
                        <p class="text-muted mb-3"><?php echo account_settings_h((string)($user['email'] ?? '')); ?></p>
                        <span class="badge bg-soft-primary text-primary"><?php echo account_settings_h(ucfirst((string)($user['role'] ?? 'user'))); ?></span>
                        <hr>
                        <form method="post" enctype="multipart/form-data" class="mb-2">
                            <input type="hidden" name="action" value="upload_avatar">
                            <input type="file" name="profile_picture" class="form-control mb-2" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" required>
                            <button type="submit" class="btn btn-primary w-100">Upload Photo</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="remove_avatar">
                            <button type="submit" class="btn btn-light w-100">Remove Photo</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Profile Information</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="action" value="save_profile">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Full Name</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo account_settings_h((string)($user['name'] ?? '')); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="username">Username</label>
                                <input type="text" id="username" name="username" class="form-control" value="<?php echo account_settings_h((string)($user['username'] ?? '')); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo account_settings_h((string)($user['email'] ?? '')); ?>" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Save Profile</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Change Password</h6>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <input type="hidden" name="action" value="change_password">
                            <div class="col-12">
                                <label class="form-label" for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control" minlength="8" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Update Password</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
