<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function ash($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function anorm(string $p): string { return ltrim(str_replace('\\', '/', trim($p)), '/'); }
function ainitials(string $v): string {
    $parts = preg_split('/\s+/', trim($v)) ?: []; $letters = '';
    foreach ($parts as $part) { if ($part !== '') { $letters .= strtoupper(substr($part, 0, 1)); } if (strlen($letters) >= 2) break; }
    return $letters !== '' ? $letters : 'BT';
}
function aflash(string $type, string $message): void { $_SESSION['account_settings_flash'] = ['type' => $type, 'message' => $message]; }
function aredirect(): void { header('Location: account-settings.php'); exit; }
function acol(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) return false; $stmt->bind_param('ss', $table, $column); $stmt->execute(); $ok = $stmt->get_result()->num_rows > 0; $stmt->close(); return $ok;
}
function auser(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT id, name, username, email, role, profile_picture, password, is_active, created_at FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) return null; $stmt->bind_param('i', $id); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close(); return $row ?: null;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) { header('Location: auth-login.php'); exit; }
$user = auser($conn, $userId);
if (!$user || (int)($user['is_active'] ?? 0) !== 1) { header('Location: auth-login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim((string)($_POST['action'] ?? '')));
    if ($action === 'save_profile') {
        $name = trim((string)($_POST['name'] ?? '')); $username = trim((string)($_POST['username'] ?? '')); $email = trim((string)($_POST['email'] ?? ''));
        if ($name === '' || $username === '' || $email === '') { aflash('danger', 'Name, username, and email are required.'); aredirect(); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { aflash('danger', 'Please provide a valid email address.'); aredirect(); }
        $dup = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1");
        if ($dup) { $dup->bind_param('ssi', $username, $email, $userId); $dup->execute(); if ($dup->get_result()->fetch_assoc()) { $dup->close(); aflash('danger', 'That username or email is already used by another account.'); aredirect(); } $dup->close(); }
        $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email = ? WHERE id = ? LIMIT 1");
        if (!$stmt) { aflash('danger', 'Unable to save profile.'); aredirect(); }
        $stmt->bind_param('sssi', $name, $username, $email, $userId); $ok = $stmt->execute(); $err = (string)$stmt->error; $stmt->close();
        if (!$ok) { aflash('danger', $err !== '' ? 'Failed to save profile: ' . $err : 'Failed to save profile.'); aredirect(); }
        $_SESSION['name'] = $name; $_SESSION['username'] = $username; $_SESSION['email'] = $email; aflash('success', 'Profile updated successfully.'); aredirect();
    }
    if ($action === 'upload_avatar') {
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $maxImageBytes = 3145728;

        $binaryUpload = null;
        $tmpUpload = '';
        $uploadExt = '';

        $croppedData = trim((string)($_POST['profile_picture_cropped'] ?? ''));
        if ($croppedData !== '') {
            if (!preg_match('#^data:(image/(?:png|jpeg|jpg|webp|gif));base64,([a-zA-Z0-9+/=\r\n]+)$#', $croppedData, $parts)) {
                aflash('danger', 'Invalid cropped image format. Please crop again.');
                aredirect();
            }

            $mime = strtolower((string)$parts[1]);
            if ($mime === 'image/jpg') {
                $mime = 'image/jpeg';
            }
            if (!in_array($mime, $allowedMime, true)) {
                aflash('danger', 'Only JPG, PNG, WEBP, or GIF images are allowed.');
                aredirect();
            }

            $rawBase64 = preg_replace('/\s+/', '', (string)$parts[2]);
            $decoded = base64_decode($rawBase64, true);
            if ($decoded === false || $decoded === '') {
                aflash('danger', 'Could not read cropped image data.');
                aredirect();
            }
            if (strlen($decoded) > $maxImageBytes) {
                aflash('danger', 'Cropped image must be less than 3MB.');
                aredirect();
            }

            $imgInfo = function_exists('getimagesizefromstring') ? @getimagesizefromstring($decoded) : false;
            $detectedMime = strtolower((string)($imgInfo['mime'] ?? ''));
            if (!$imgInfo || !in_array($detectedMime, $allowedMime, true)) {
                aflash('danger', 'Cropped image is not a supported photo type.');
                aredirect();
            }

            $binaryUpload = $decoded;
            $uploadExt = (string)($mimeToExt[$detectedMime] ?? 'png');
        } else {
            $file = $_FILES['profile_picture'] ?? null;
            if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                aflash('danger', 'Upload failed. Please try again.');
                aredirect();
            }

            $tmpUpload = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $name = (string)($file['name'] ?? '');
            $uploadExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
            $mime = $finfo ? (string)finfo_file($finfo, $tmpUpload) : '';
            if ($finfo) {
                finfo_close($finfo);
            }

            if ($size <= 0 || $size > $maxImageBytes) {
                aflash('danger', 'Image must be less than 3MB.');
                aredirect();
            }
            if ($uploadExt === '' || !in_array($uploadExt, $allowedExt, true) || ($mime !== '' && !in_array(strtolower($mime), $allowedMime, true))) {
                aflash('danger', 'Only JPG, PNG, WEBP, or GIF images are allowed.');
                aredirect();
            }
        }

        $dir = dirname(__DIR__) . '/uploads/profile_pictures'; if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) { aflash('danger', 'Could not prepare upload folder.'); aredirect(); }
        try { $rand = bin2hex(random_bytes(6)); } catch (Throwable $e) { $rand = (string)mt_rand(100000, 999999); }
        $targetFile = 'user_' . $userId . '_' . time() . '_' . $rand . '.' . $uploadExt; $targetAbs = $dir . '/' . $targetFile; $targetRel = 'uploads/profile_pictures/' . $targetFile;
        if ($binaryUpload !== null) {
            if (@file_put_contents($targetAbs, $binaryUpload) === false) { aflash('danger', 'Failed to save cropped image.'); aredirect(); }
        } else {
            if (!move_uploaded_file($tmpUpload, $targetAbs)) { aflash('danger', 'Failed to save uploaded image.'); aredirect(); }
        }
        $oldRel = anorm((string)($user['profile_picture'] ?? '')); $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ? LIMIT 1");
        if (!$stmt) { @unlink($targetAbs); aflash('danger', 'Unable to save profile image.'); aredirect(); }
        $stmt->bind_param('si', $targetRel, $userId); $ok = $stmt->execute(); $stmt->close();
        if (!$ok) { @unlink($targetAbs); aflash('danger', 'Unable to save profile image.'); aredirect(); }
        if ($oldRel !== '' && strpos($oldRel, 'uploads/profile_pictures/') === 0) { $oldAbs = dirname(__DIR__) . '/' . $oldRel; if (is_file($oldAbs)) @unlink($oldAbs); }
        $_SESSION['profile_picture'] = $targetRel; aflash('success', 'Profile picture updated.'); aredirect();
    }
    if ($action === 'remove_avatar') {
        $oldRel = anorm((string)($user['profile_picture'] ?? '')); $stmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ? LIMIT 1");
        if (!$stmt) { aflash('danger', 'Unable to remove profile picture.'); aredirect(); }
        $stmt->bind_param('i', $userId); $ok = $stmt->execute(); $stmt->close();
        if (!$ok) { aflash('danger', 'Unable to remove profile picture.'); aredirect(); }
        if ($oldRel !== '' && strpos($oldRel, 'uploads/profile_pictures/') === 0) { $oldAbs = dirname(__DIR__) . '/' . $oldRel; if (is_file($oldAbs)) @unlink($oldAbs); }
        $_SESSION['profile_picture'] = ''; aflash('success', 'Profile picture removed.'); aredirect();
    }
    if ($action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? ''); $newPassword = (string)($_POST['new_password'] ?? ''); $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') { aflash('danger', 'All password fields are required.'); aredirect(); }
        if ($newPassword !== $confirmPassword) { aflash('danger', 'New password and confirmation do not match.'); aredirect(); }
        if (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) { aflash('danger', 'Use at least 8 characters, including uppercase, lowercase, and a number.'); aredirect(); }
        $storedPassword = (string)($user['password'] ?? ''); $matches = password_verify($currentPassword, $storedPassword);
        if (!$matches && $storedPassword !== '' && hash_equals($storedPassword, $currentPassword)) $matches = true;
        if (!$matches) { aflash('danger', 'Current password is incorrect.'); aredirect(); }
        if (hash_equals($currentPassword, $newPassword)) { aflash('danger', 'New password must be different from current password.'); aredirect(); }
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT); if ($newHash === false) { aflash('danger', 'Unable to secure the new password.'); aredirect(); }
        $col = acol($conn, 'users', 'password_hash') ? 'password_hash' : 'password'; $stmt = $conn->prepare("UPDATE users SET {$col} = ? WHERE id = ? LIMIT 1");
        if (!$stmt) { aflash('danger', 'Unable to update password.'); aredirect(); }
        $stmt->bind_param('si', $newHash, $userId); $ok = $stmt->execute(); $stmt->close(); if (!$ok) { aflash('danger', 'Unable to update password.'); aredirect(); }
        if ($col !== 'password' && acol($conn, 'users', 'password')) { $legacy = $conn->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1"); if ($legacy) { $legacy->bind_param('si', $newHash, $userId); $legacy->execute(); $legacy->close(); } }
        aflash('success', 'Password updated successfully.'); aredirect();
    }
    aflash('warning', 'No valid action was provided.'); aredirect();
}

$user = auser($conn, $userId) ?? $user; $flash = $_SESSION['account_settings_flash'] ?? null; unset($_SESSION['account_settings_flash']);
$profileRel = anorm((string)($user['profile_picture'] ?? '')); $profileAbs = $profileRel !== '' ? dirname(__DIR__) . '/' . $profileRel : '';
$profileUrl = ($profileRel !== '' && is_file($profileAbs)) ? $profileRel . '?v=' . rawurlencode((string)@filemtime($profileAbs)) : ('assets/images/avatar/' . (($userId % 5) + 1) . '.png');
$displayName = trim((string)($user['name'] ?? 'BioTern User')); if ($displayName === '') $displayName = 'BioTern User';
$memberSince = '-'; if (!empty($user['created_at'])) { $ts = strtotime((string)$user['created_at']); if ($ts !== false) $memberSince = date('M d, Y h:i A', $ts); }
$lastLogin = 'No login record yet'; $loginStmt = $conn->prepare("SELECT created_at FROM login_logs WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1");
if ($loginStmt) { $status = 'success'; $loginStmt->bind_param('is', $userId, $status); $loginStmt->execute(); $loginRow = $loginStmt->get_result()->fetch_assoc(); $loginStmt->close(); if (!empty($loginRow['created_at'])) { $ts = strtotime((string)$loginRow['created_at']); if ($ts !== false) $lastLogin = date('M d, Y h:i A', $ts); } }
$studentProfile = null; $role = strtolower(trim((string)($user['role'] ?? $_SESSION['role'] ?? '')));
if ($role === 'student') {
    $studentStmt = $conn->prepare("SELECT s.student_id, s.email AS student_email, s.phone, s.address, c.name AS course_name, d.name AS department_name, sec.name AS section_name FROM students s LEFT JOIN courses c ON c.id = s.course_id LEFT JOIN departments d ON d.id = s.department_id LEFT JOIN sections sec ON sec.id = s.section_id WHERE s.user_id = ? LIMIT 1");
    if ($studentStmt) { $studentStmt->bind_param('i', $userId); $studentStmt->execute(); $studentProfile = $studentStmt->get_result()->fetch_assoc(); $studentStmt->close(); }
}

$page_title = 'BioTern || Account Settings';
$page_body_class = 'settings-page account-settings-page';
$page_styles = ['assets/css/layout/page_shell.css', 'assets/css/modules/settings/settings-shell.css', 'assets/css/modules/settings/page-account-settings.css'];
$page_scripts = ['assets/js/modules/settings/page-account-settings.js'];
include dirname(__DIR__) . '/includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header dashboard-page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title"><h5 class="m-b-10">Profile & Settings</h5></div>
                <ul class="breadcrumb"><li class="breadcrumb-item"><a href="homepage.php">Home</a></li><li class="breadcrumb-item">Account Settings</li></ul>
            </div>
        </div>
        <section class="settings-hub account-settings-shell">
            <?php if (is_array($flash) && !empty($flash['message'])): ?><div class="alert alert-<?php echo ash((string)($flash['type'] ?? 'info')); ?> mb-4" role="alert"><?php echo ash((string)$flash['message']); ?></div><?php endif; ?>
            <div class="row g-4">
                <div class="col-12">
                    <div class="settings-stack">
                        <section class="settings-hero account-profile-hero" id="overview">
                            <div class="account-identity">
                                <div class="account-identity-avatar"><img src="<?php echo ash($profileUrl); ?>" alt="Profile picture"></div>
                                <div class="account-identity-copy">
                                    <h3><?php echo ash($displayName); ?></h3>
                                    <p>Manage your account identity, security settings, and profile image from one place.</p>
                                    <span class="account-role-chip"><i class="feather-user-check"></i><?php echo ash(ucfirst((string)($user['role'] ?? 'user'))); ?></span>
                                </div>
                            </div>
                            <div class="settings-kpi-grid">
                                <div class="settings-kpi"><span class="settings-kpi-label">Account Status</span><span class="settings-kpi-value"><?php echo ((int)($user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?></span></div>
                                <div class="settings-kpi"><span class="settings-kpi-label">Username</span><span class="settings-kpi-value"><?php echo ash((string)($user['username'] ?? '')); ?></span></div>
                                <div class="settings-kpi"><span class="settings-kpi-label">Member Since</span><span class="settings-kpi-value"><?php echo ash($memberSince); ?></span></div>
                                <div class="settings-kpi"><span class="settings-kpi-label">Last Login</span><span class="settings-kpi-value"><?php echo ash($lastLogin); ?></span></div>
                            </div>
                        </section>

                        <section class="card settings-panel-card">
                            <div class="card-header"><h6 class="settings-section-title">Account overview</h6><p class="settings-section-subtitle">Current identity and contact information.</p></div>
                            <div class="card-body">
                                <div class="account-profile-grid">
                                    <div class="account-profile-field"><span>Full Name</span><strong><?php echo ash((string)($user['name'] ?? '')); ?></strong></div>
                                    <div class="account-profile-field"><span>Username</span><strong><?php echo ash((string)($user['username'] ?? '')); ?></strong></div>
                                    <div class="account-profile-field full"><span>Email</span><strong><?php echo ash((string)($user['email'] ?? '')); ?></strong></div>
                                    <div class="account-profile-field"><span>Role</span><strong><?php echo ash(ucfirst((string)($user['role'] ?? 'user'))); ?></strong></div>
                                    <div class="account-profile-field"><span>Initials</span><strong><?php echo ash(ainitials($displayName)); ?></strong></div>
                                </div>
                            </div>
                        </section>

                        <section class="card settings-panel-card" id="profile-form">
                            <div class="card-header"><h6 class="settings-section-title">Edit profile</h6><p class="settings-section-subtitle">Update the details that appear throughout the app.</p></div>
                            <div class="card-body">
                                <form method="post">
                                    <input type="hidden" name="action" value="save_profile">
                                    <div class="account-form-grid">
                                        <div><label class="form-label" for="name">Full Name</label><input type="text" id="name" name="name" class="form-control" value="<?php echo ash((string)($user['name'] ?? '')); ?>" required></div>
                                        <div><label class="form-label" for="username">Username</label><input type="text" id="username" name="username" class="form-control" value="<?php echo ash((string)($user['username'] ?? '')); ?>" required></div>
                                        <div class="full"><label class="form-label" for="email">Email Address</label><input type="email" id="email" name="email" class="form-control" value="<?php echo ash((string)($user['email'] ?? '')); ?>" required></div>
                                    </div>
                                    <div class="account-form-actions"><button type="submit" class="btn btn-primary">Save Profile</button><a href="homepage.php" class="btn btn-light">Back to Dashboard</a></div>
                                </form>
                            </div>
                        </section>

                        <section class="card settings-panel-card" id="security">
                            <div class="card-header"><h6 class="settings-section-title">Security and avatar</h6><p class="settings-section-subtitle">Replace your profile image and keep your password current.</p></div>
                            <div class="card-body">
                                <div class="row g-4">
                                    <div class="col-lg-5">
                                        <div class="account-avatar-panel">
                                            <div class="account-current-avatar"><img src="<?php echo ash($profileUrl); ?>" alt="Current avatar"><div><strong>Current profile image</strong><span><?php echo ash($profileRel !== '' ? $profileRel : 'Default BioTern avatar'); ?></span></div></div>
                                            <form method="post" enctype="multipart/form-data" data-avatar-upload-form>
                                                <input type="hidden" name="action" value="upload_avatar">
                                                <input type="hidden" name="profile_picture_cropped" value="" data-avatar-cropped-input>
                                                <label class="form-label" for="profile_picture">Upload a new image</label>
                                                <input type="file" id="profile_picture" name="profile_picture" class="form-control mb-3" accept=".jpg,.jpeg,.png,.webp,.gif,image/*" required data-avatar-file-input>
                                                <div class="avatar-crop-editor d-none" data-avatar-crop-editor>
                                                    <label class="form-label mb-2">Crop before upload</label>
                                                    <div class="avatar-crop-canvas-wrap">
                                                        <canvas width="320" height="320" data-avatar-crop-canvas></canvas>
                                                    </div>
                                                    <div class="mt-2">
                                                        <label class="form-label mb-1" for="avatar_crop_zoom">Zoom</label>
                                                        <input type="range" id="avatar_crop_zoom" min="100" max="400" step="1" value="100" class="form-range" data-avatar-crop-zoom>
                                                    </div>
                                                    <div class="account-form-actions mt-2">
                                                        <button type="button" class="btn btn-light" data-avatar-crop-reset>Reset</button>
                                                        <button type="button" class="btn btn-outline-primary" data-avatar-crop-apply>Apply Crop</button>
                                                    </div>
                                                    <p class="account-note mb-0 mt-2" data-avatar-crop-status>Drag the image to position the crop area.</p>
                                                </div>
                                                <div class="account-form-actions"><button type="submit" class="btn btn-primary">Upload Photo</button></div>
                                            </form>
                                            <form method="post"><input type="hidden" name="action" value="remove_avatar"><button type="submit" class="btn btn-outline-secondary">Remove Photo</button></form>
                                            <p class="account-note mb-0">Accepted formats: JPG, PNG, WEBP, and GIF up to 3MB.</p>
                                        </div>
                                    </div>
                                    <div class="col-lg-7">
                                        <form method="post">
                                            <input type="hidden" name="action" value="change_password">
                                            <div class="account-form-grid">
                                                <div class="full"><label class="form-label" for="current_password">Current Password</label><input type="password" id="current_password" name="current_password" class="form-control" data-account-password-field required></div>
                                                <div><label class="form-label" for="new_password">New Password</label><input type="password" id="new_password" name="new_password" class="form-control" minlength="8" data-account-password-field required></div>
                                                <div><label class="form-label" for="confirm_password">Confirm Password</label><input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="8" data-account-password-field required></div>
                                            </div>
                                            <label class="account-password-toggle mt-3"><input type="checkbox" data-account-password-toggle><span data-account-password-toggle-label>Show passwords</span></label>
                                            <div class="account-form-actions mt-3"><button type="submit" class="btn btn-outline-primary">Update Password</button></div>
                                            <p class="account-note mb-0 mt-3">Use at least 8 characters, including uppercase, lowercase, and a number.</p>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <?php if (is_array($studentProfile)): ?>
                            <section class="card settings-panel-card" id="student-record">
                                <div class="card-header"><h6 class="settings-section-title">Linked student record</h6><p class="settings-section-subtitle">Student information tied to this account.</p></div>
                                <div class="card-body">
                                    <div class="account-profile-grid">
                                        <div class="account-profile-field"><span>Student ID</span><strong><?php echo ash((string)($studentProfile['student_id'] ?? '')); ?></strong></div>
                                        <div class="account-profile-field"><span>Course</span><strong><?php echo ash((string)($studentProfile['course_name'] ?? '')); ?></strong></div>
                                        <div class="account-profile-field"><span>Department</span><strong><?php echo ash((string)($studentProfile['department_name'] ?? '')); ?></strong></div>
                                        <div class="account-profile-field"><span>Section</span><strong><?php echo ash((string)($studentProfile['section_name'] ?? '')); ?></strong></div>
                                        <div class="account-profile-field"><span>Student Email</span><strong><?php echo ash((string)($studentProfile['student_email'] ?? '')); ?></strong></div>
                                        <div class="account-profile-field"><span>Phone</span><strong><?php echo ash((string)($studentProfile['phone'] ?? '')); ?></strong></div>
                                        <div class="account-profile-field full"><span>Address</span><strong><?php echo nl2br(ash((string)($studentProfile['address'] ?? ''))); ?></strong></div>
                                    </div>
                                </div>
                            </section>
                        <?php endif; ?>

                        <section class="card settings-panel-card">
                            <div class="card-header"><h6 class="settings-section-title">Quick links</h6><p class="settings-section-subtitle">Shortcuts that belong with your account tools.</p></div>
                            <div class="card-body">
                                <div class="settings-utility-links">
                                    <a class="settings-utility-link" href="notifications.php"><span>Open notifications inbox</span><span><i class="feather-arrow-right"></i></span></a>
                                    <a class="settings-utility-link" href="theme-customizer.php"><span>Appearance</span><span><i class="feather-arrow-right"></i></span></a>
                                    <a class="settings-utility-link" href="auth-login.php?logout=1"><span>Logout</span><span><i class="feather-log-out"></i></span></a>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
