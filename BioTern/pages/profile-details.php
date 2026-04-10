<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/lib/notifications.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login-cover.php');
    exit;
}

$user = null;
$stmt = $conn->prepare('SELECT id, name, username, email, password, role, is_active, profile_picture, created_at FROM users WHERE id = ? LIMIT 1');
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    header('Location: auth-login-cover.php?logout=1');
    exit;
}

biotern_notifications_ensure_table($conn);

function profile_details_preview(string $value, int $limit = 72): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if ($value === '') {
        return '';
    }
    return strlen($value) > $limit ? substr($value, 0, $limit - 3) . '...' : $value;
}

function profile_details_value(?string $value, string $fallback = 'Not yet available'): string
{
    $value = trim((string)$value);
    return $value !== '' ? $value : $fallback;
}

function profile_details_format_date(?string $value, string $fallback = 'Not yet available'): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('M d, Y', $timestamp) : $fallback;
}

$profile_flash_message = '';
$profile_flash_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile_action = (string)($_POST['action'] ?? '');

    if ($profile_action === 'update_student_profile') {
        $currentRole = strtolower(trim((string)($user['role'] ?? $_SESSION['role'] ?? '')));
        if ($currentRole !== 'student') {
            $profile_flash_message = 'Only student accounts can update student profile details here.';
            $profile_flash_type = 'warning';
        } else {
            $studentRecordId = (int)($_POST['student_record_id'] ?? 0);
            $studentEmail = trim((string)($_POST['student_email'] ?? ''));
            $phone = trim((string)($_POST['phone'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $dateOfBirth = trim((string)($_POST['date_of_birth'] ?? ''));
            $gender = trim((string)($_POST['gender'] ?? ''));
            $emergencyContact = trim((string)($_POST['emergency_contact'] ?? ''));

            if ($dateOfBirth !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth) !== 1) {
                $profile_flash_message = 'Birth date must use a valid date.';
                $profile_flash_type = 'warning';
            } else {
                $updateSql = "UPDATE students
                    SET email = ?, phone = ?, address = ?, date_of_birth = ?, gender = ?, emergency_contact = ?
                    WHERE ";
                if ($studentRecordId > 0) {
                    $updateSql .= "id = ? LIMIT 1";
                } else {
                    $updateSql .= "user_id = ? LIMIT 1";
                }

                $studentUpdateStmt = $conn->prepare($updateSql);
                if (!$studentUpdateStmt) {
                    $profile_flash_message = 'Could not prepare student profile update.';
                    $profile_flash_type = 'danger';
                } else {
                    $dateParam = $dateOfBirth !== '' ? $dateOfBirth : null;
                    $recordTarget = $studentRecordId > 0 ? $studentRecordId : $userId;
                    $studentUpdateStmt->bind_param('ssssssi', $studentEmail, $phone, $address, $dateParam, $gender, $emergencyContact, $recordTarget);
                    if ($studentUpdateStmt->execute()) {
                        $profile_flash_message = 'Student profile details updated successfully.';
                        $profile_flash_type = 'success';
                    } else {
                        $profile_flash_message = 'Failed to update student profile details.';
                        $profile_flash_type = 'danger';
                    }
                    $studentUpdateStmt->close();
                }
            }
        }
    } elseif ($profile_action === 'upload_profile_picture') {
        if (!isset($_FILES['profile_picture']) || !is_array($_FILES['profile_picture'])) {
            $profile_flash_message = 'Please choose an image file.';
            $profile_flash_type = 'warning';
        } else {
            $file = $_FILES['profile_picture'];
            if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $profile_flash_message = 'Upload failed. Please try again.';
                $profile_flash_type = 'danger';
            } else {
                $tmp = (string)($file['tmp_name'] ?? '');
                $size = (int)($file['size'] ?? 0);
                $name = (string)($file['name'] ?? '');
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

                $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
                $mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }

                if ($size <= 0 || $size > (3 * 1024 * 1024)) {
                    $profile_flash_message = 'Image must be less than 3MB.';
                    $profile_flash_type = 'warning';
                } elseif (!in_array($ext, $allowedExt, true) || ($mime !== '' && !in_array($mime, $allowedMime, true))) {
                    $profile_flash_message = 'Only JPG, PNG, WEBP, or GIF images are allowed.';
                    $profile_flash_type = 'warning';
                } else {
                    $uploadDirFs = dirname(__DIR__) . '/assets/images/avatar/uploads';
                    if (!is_dir($uploadDirFs)) {
                        @mkdir($uploadDirFs, 0777, true);
                    }

                    $safeName = 'user_' . $userId . '_' . time() . '.' . $ext;
                    $destFs = $uploadDirFs . '/' . $safeName;
                    $destRel = 'assets/images/avatar/uploads/' . $safeName;

                    if (!@move_uploaded_file($tmp, $destFs)) {
                        $profile_flash_message = 'Failed to save uploaded file.';
                        $profile_flash_type = 'danger';
                    } else {
                        $oldPath = biotern_avatar_normalize_path((string)($user['profile_picture'] ?? ''));
                        biotern_avatar_sync_profile_path($conn, $userId, $destRel);

                        $_SESSION['profile_picture'] = $destRel;
                        $user['profile_picture'] = $destRel;
                        $profile_flash_message = 'Profile picture updated successfully.';
                        $profile_flash_type = 'success';

                        if ($oldPath !== '' && strpos($oldPath, 'assets/images/avatar/uploads/') === 0) {
                            $oldFs = dirname(__DIR__) . '/' . $oldPath;
                            if (is_file($oldFs)) {
                                @unlink($oldFs);
                            }
                        }
                    }
                }
            }
        }
    } elseif ($profile_action === 'change_password') {
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $storedPasswordHash = (string)($user['password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $profile_flash_message = 'Please fill in all password fields.';
            $profile_flash_type = 'warning';
        } elseif (!password_verify($currentPassword, $storedPasswordHash)) {
            $profile_flash_message = 'Current password is incorrect.';
            $profile_flash_type = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $profile_flash_message = 'New password and confirmation do not match.';
            $profile_flash_type = 'warning';
        } elseif (strlen($newPassword) < 8) {
            $profile_flash_message = 'New password must be at least 8 characters.';
            $profile_flash_type = 'warning';
        } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
            $profile_flash_message = 'Use at least one uppercase letter, one lowercase letter, and one number.';
            $profile_flash_type = 'warning';
        } elseif (password_verify($newPassword, $storedPasswordHash)) {
            $profile_flash_message = 'New password must be different from your current password.';
            $profile_flash_type = 'warning';
        } else {
            $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');
            if (!$passwordStmt) {
                $profile_flash_message = 'Could not prepare password update. Please try again.';
                $profile_flash_type = 'danger';
            } else {
                $passwordStmt->bind_param('si', $newPasswordHash, $userId);
                if ($passwordStmt->execute()) {
                    $user['password'] = $newPasswordHash;
                    $profile_flash_message = 'Password changed successfully.';
                    $profile_flash_type = 'success';
                } else {
                    $profile_flash_message = 'Failed to update password. Please try again.';
                    $profile_flash_type = 'danger';
                }
                $passwordStmt->close();
            }
        }
    }
}

$studentProfile = null;
$currentRole = strtolower(trim((string)($user['role'] ?? $_SESSION['role'] ?? '')));
if ($currentRole === 'student') {
    $studentStmt = $conn->prepare("SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.email AS student_email, s.phone, s.address,
        s.date_of_birth, s.gender, s.emergency_contact, s.status AS student_status,
        c.name AS course_name, d.name AS department_name, sec.code AS section_code, sec.name AS section_name
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN departments d ON d.id = s.department_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        WHERE s.user_id = ?
        LIMIT 1");
    if ($studentStmt) {
        $studentStmt->bind_param('i', $userId);
        $studentStmt->execute();
        $studentProfile = $studentStmt->get_result()->fetch_assoc();
        $studentStmt->close();
    }

    if (!$studentProfile) {
        $fallbackEmail = trim((string)($user['email'] ?? ''));
        $fallbackName = trim((string)($user['name'] ?? ''));
        $fallbackStmt = $conn->prepare(
            "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.email AS student_email, s.phone, s.address,
                    s.date_of_birth, s.gender, s.emergency_contact, s.status AS student_status,
                    c.name AS course_name, d.name AS department_name, sec.code AS section_code, sec.name AS section_name
             FROM students s
             LEFT JOIN courses c ON c.id = s.course_id
             LEFT JOIN departments d ON d.id = s.department_id
             LEFT JOIN sections sec ON sec.id = s.section_id
             WHERE ((? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?))
                 OR (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?)))
             ORDER BY
                CASE
                    WHEN (? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?)) THEN 0
                    WHEN (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?)) THEN 1
                    ELSE 2
                END
             LIMIT 1"
        );

        if ($fallbackStmt) {
            $fallbackStmt->bind_param(
                'ssssssss',
                $fallbackEmail,
                $fallbackEmail,
                $fallbackName,
                $fallbackName,
                $fallbackEmail,
                $fallbackEmail,
                $fallbackName,
                $fallbackName
            );
            $fallbackStmt->execute();
            $studentProfile = $fallbackStmt->get_result()->fetch_assoc() ?: null;
            $fallbackStmt->close();
        }
    }
}

$lastLoginAt = null;
$loginStmt = $conn->prepare('SELECT created_at FROM login_logs WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1');
if ($loginStmt) {
    $status = 'success';
    $loginStmt->bind_param('is', $userId, $status);
    $loginStmt->execute();
    $lastLogin = $loginStmt->get_result()->fetch_assoc();
    $lastLoginAt = $lastLogin['created_at'] ?? null;
    $loginStmt->close();
}

$displayName = trim((string)($user['name'] ?? 'BioTern User'));
if ($displayName === '') {
    $displayName = 'BioTern User';
}

$nameParts = preg_split('/\s+/', $displayName) ?: [];
$initials = '';
if (!empty($nameParts[0])) {
    $initials .= strtoupper(substr((string)$nameParts[0], 0, 1));
}
if (!empty($nameParts[1])) {
    $initials .= strtoupper(substr((string)$nameParts[1], 0, 1));
}
if ($initials === '') {
    $initials = 'BT';
}

$profile_picture_src = biotern_avatar_resolve_existing_path((string)($user['profile_picture'] ?? ''));
$profile_avatar_src = biotern_avatar_public_src((string)($user['profile_picture'] ?? ''), $userId);

$memberSinceDisplay = '-';
if (!empty($user['created_at'])) {
    $ts = strtotime((string)$user['created_at']);
    if ($ts !== false) {
        $memberSinceDisplay = date('M d, Y h:i A', $ts);
    }
}

$lastLoginDisplay = 'No login record yet';
if (!empty($lastLoginAt)) {
    $ts = strtotime((string)$lastLoginAt);
    if ($ts !== false) {
        $lastLoginDisplay = date('M d, Y h:i A', $ts);
    }
}

$notificationUnreadCount = biotern_notifications_count_unread($conn, $userId);
$notificationTotalCount = 0;
$notificationTotalStmt = $conn->prepare('SELECT COUNT(*) AS total FROM notifications WHERE user_id = ?');
if ($notificationTotalStmt) {
    $notificationTotalStmt->bind_param('i', $userId);
    $notificationTotalStmt->execute();
    $notificationTotal = $notificationTotalStmt->get_result()->fetch_assoc();
    $notificationTotalCount = (int)($notificationTotal['total'] ?? 0);
    $notificationTotalStmt->close();
}

$loginEventCount = 0;
$loginCountStmt = $conn->prepare('SELECT COUNT(*) AS total FROM login_logs WHERE user_id = ?');
if ($loginCountStmt) {
    $loginCountStmt->bind_param('i', $userId);
    $loginCountStmt->execute();
    $loginCount = $loginCountStmt->get_result()->fetch_assoc();
    $loginEventCount = (int)($loginCount['total'] ?? 0);
    $loginCountStmt->close();
}

$auditEventCount = 0;
$auditCheck = $conn->query("SHOW TABLES LIKE 'audit_logs'");
if ($auditCheck instanceof mysqli_result && $auditCheck->num_rows > 0) {
    $auditCountStmt = $conn->prepare('SELECT COUNT(*) AS total FROM audit_logs WHERE user_id = ?');
    if ($auditCountStmt) {
        $auditCountStmt->bind_param('i', $userId);
        $auditCountStmt->execute();
        $auditCount = $auditCountStmt->get_result()->fetch_assoc();
        $auditEventCount = (int)($auditCount['total'] ?? 0);
        $auditCountStmt->close();
    }
}

$accountSecurityState = ((int)($user['is_active'] ?? 0) === 1) ? 'Protected' : 'Restricted';
$roleWorkspaceLabel = ucfirst((string)($user['role'] ?? 'user')) . ' Workspace';
$contactPhone = trim((string)($studentProfile['phone'] ?? ''));
$contactAddress = trim((string)($studentProfile['address'] ?? ''));
$studentSectionParts = array_filter([
    trim((string)($studentProfile['section_code'] ?? '')),
    trim((string)($studentProfile['section_name'] ?? '')),
]);
$studentSectionDisplay = !empty($studentSectionParts) ? implode(' | ', $studentSectionParts) : trim((string)($studentProfile['section_name'] ?? ''));
$studentStatusRaw = trim((string)($studentProfile['student_status'] ?? ''));
$studentStatusDisplay = match (strtolower($studentStatusRaw)) {
    '1', 'true', 'active', 'approved' => 'Active',
    '0', 'false', 'inactive', 'rejected' => 'Inactive',
    'pending' => 'Pending',
    default => profile_details_value($studentStatusRaw),
};
$studentGenderDisplay = trim((string)($studentProfile['gender'] ?? '')) !== ''
    ? ucwords(strtolower(trim((string)($studentProfile['gender'] ?? ''))))
    : 'Not yet available';

$page_title$page_title = 'BioTern || Profile Details';
$page_body_class = 'apps-account-page';
$page_styles = [
    'assets/css/modules/pages/page-profile-details.css',
];
$page_scripts = [
    'assets/js/modules/pages/profile-details-page.js',
];
include 'includes/header.php';
?>
<div class="main-content d-flex">
    <div class="content-area w-100" data-scrollbar-target="#psScrollbarInit">
        <div class="content-area-header bg-white sticky-top">
            <div class="page-header-left d-flex align-items-center gap-2">
                <h5 class="mb-0">Profile Details</h5>
            </div>
        </div>

        <div class="content-area-body p-3 profile-shell">
            <?php if ($profile_flash_message !== ''): ?>
            <div class="alert alert-<?php echo htmlspecialchars($profile_flash_type, ENT_QUOTES, 'UTF-8'); ?> py-2 mb-3">
                <?php echo htmlspecialchars($profile_flash_message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>

            <div class="profile-nav">
                <a class="profile-nav-link" href="profile-details.php"><i class="feather-user"></i><span>Profile Details</span></a>
                <a class="profile-nav-link" href="profile-details.php#account-settings"><i class="feather-settings"></i><span>Account Settings</span></a>
                <a class="profile-nav-link" href="activity-feed.php"><i class="feather-activity"></i><span>Activity Feed</span></a>
                <a class="profile-nav-link" href="notifications.php"><i class="feather-bell"></i><span>Notifications</span></a>
            </div>

            <div class="profile-hero">
                <div class="profile-persona">
                    <div class="profile-avatar"><?php if ($profile_avatar_src !== ''): ?><img src="<?php echo htmlspecialchars($profile_avatar_src, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile Picture"><?php else: ?><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></div>
                    <div>
                        <h6 class="profile-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h6>
                        <span class="profile-role"><?php echo htmlspecialchars(ucfirst((string)($user['role'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <div class="profile-kpis">
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Account Status</div>
                        <div class="profile-kpi-value"><?php echo ((int)($user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?></div>
                    </div>
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Member Since</div>
                        <div class="profile-kpi-value"><?php echo htmlspecialchars($memberSinceDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Last Login</div>
                        <div class="profile-kpi-value"><?php echo htmlspecialchars($lastLoginDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <div class="profile-kpi">
                        <div class="profile-kpi-label">Username</div>
                        <div class="profile-kpi-value"><?php echo htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </div>

            <div class="profile-summary-grid">
                <div class="profile-summary-card">
                    <div class="profile-summary-label">Unread Notifications</div>
                    <div class="profile-summary-value"><?php echo (int)$notificationUnreadCount; ?></div>
                    <div class="profile-summary-note"><?php echo (int)$notificationTotalCount; ?> total alerts saved in your account.</div>
                </div>
                <div class="profile-summary-card">
                    <div class="profile-summary-label">Activity Events</div>
                    <div class="profile-summary-value"><?php echo (int)($loginEventCount + $auditEventCount + $notificationTotalCount); ?></div>
                    <div class="profile-summary-note"><?php echo (int)$loginEventCount; ?> login records and <?php echo (int)$auditEventCount; ?> tracked changes.</div>
                </div>
                <div class="profile-summary-card">
                    <div class="profile-summary-label">Workspace</div>
                    <div class="profile-summary-value" style="font-size: 18px; line-height: 1.25;"><?php echo htmlspecialchars($roleWorkspaceLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="profile-summary-note">Your profile tools, alerts, and account actions are linked here.</div>
                </div>
                <div class="profile-summary-card">
                    <div class="profile-summary-label">Security</div>
                    <div class="profile-summary-value" style="font-size: 18px; line-height: 1.25;"><?php echo htmlspecialchars($accountSecurityState, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="profile-summary-note">Password reset, profile photo, and account status controls are available below.</div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card profile-panel">
                        <div class="card-header">
                            <h6 class="mb-0"><span>💼</span> My Account</h6>
                        </div>
                        <div class="card-body">
                            <div class="profile-grid">
                                <div class="profile-field">
                                    <div class="profile-field-label">Full Name</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Username</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field full">
                                    <div class="profile-field-label">Email</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Role</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(ucfirst((string)($user['role'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Status</div>
                                    <div class="profile-field-value"><?php echo ((int)($user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Last Login</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars($lastLoginDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Member Since</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars($memberSinceDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card profile-panel mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Quick Access</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a class="btn btn-primary profile-action-btn" href="profile-details.php#account-settings">Open Account Settings</a>
                                <a class="btn btn-outline-primary profile-action-btn" href="notifications.php">Open My Notifications</a>
                                <a class="btn btn-outline-secondary profile-action-btn" href="activity-feed.php">Open My Activity Feed</a>
                                <a class="btn btn-outline-dark profile-action-btn" href="auth-login-cover.php?logout=1">Logout</a>
                            </div>
                        </div>
                    </div>

                    <?php if (is_array($studentProfile)): ?>
                    <div class="card profile-panel mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Student Profile</h6>
                        </div>
                        <div class="card-body">
                            <div class="profile-grid">
                                <div class="profile-field">
                                    <div class="profile-field-label">Student ID</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['student_id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Student Status</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars($studentStatusDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Course</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['course_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Department</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['department_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Section</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value($studentSectionDisplay, 'Not yet assigned'), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Phone</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['phone'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Student Email</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['student_email'] ?? ($user['email'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Birth Date</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_format_date((string)($studentProfile['date_of_birth'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field">
                                    <div class="profile-field-label">Gender</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars($studentGenderDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field full">
                                    <div class="profile-field-label">Emergency Contact</div>
                                    <div class="profile-field-value"><?php echo htmlspecialchars(profile_details_value((string)($studentProfile['emergency_contact'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="profile-field full">
                                    <div class="profile-field-label">Address</div>
                                    <div class="profile-field-value"><?php echo nl2br(htmlspecialchars(profile_details_value((string)($studentProfile['address'] ?? '')), ENT_QUOTES, 'UTF-8')); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-5" id="account-settings">
                    <div class="card profile-panel">
                        <div class="card-header">
                            <h6 class="mb-0">Individual Account Settings</h6>
                        </div>
                        <div class="card-body">
                            <p class="profile-action-note mb-3">Quick actions for your own account.</p>
                            <div class="profile-status-list mb-3">
                                <div class="profile-status-item">
                                    <div class="profile-status-copy">
                                        <strong>Account access</strong>
                                        <span>Your user account is currently <?php echo ((int)($user['is_active'] ?? 0) === 1) ? 'enabled and ready to use.' : 'restricted and may need admin review.'; ?></span>
                                    </div>
                                    <span class="badge <?php echo ((int)($user['is_active'] ?? 0) === 1) ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?> profile-inline-badge"><?php echo ((int)($user['is_active'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?></span>
                                </div>
                                <div class="profile-status-item">
                                    <div class="profile-status-copy">
                                        <strong>Contact channel</strong>
                                        <span><?php echo htmlspecialchars($contactPhone !== '' ? $contactPhone : 'No phone number saved in your linked student record yet.', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <span class="badge bg-soft-primary text-primary profile-inline-badge"><?php echo $contactPhone !== '' ? 'Saved' : 'Missing'; ?></span>
                                </div>
                                <div class="profile-status-item">
                                    <div class="profile-status-copy">
                                        <strong>Address record</strong>
                                        <span><?php echo htmlspecialchars($contactAddress !== '' ? profile_details_preview($contactAddress, 72) : 'Add an address to keep your records complete.', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <span class="badge bg-soft-warning text-warning profile-inline-badge"><?php echo $contactAddress !== '' ? 'On file' : 'Review'; ?></span>
                                </div>
                            </div>
                            <?php if (is_array($studentProfile)): ?>
                            <form method="post" class="mb-3">
                                <input type="hidden" name="action" value="update_student_profile">
                                <input type="hidden" name="student_record_id" value="<?php echo (int)($studentProfile['id'] ?? 0); ?>">
                                <div class="profile-subtitle">Student Details</div>
                                <div class="profile-grid mb-3">
                                    <div class="profile-field">
                                        <div class="profile-field-label">Student Email</div>
                                        <input type="email" name="student_email" class="form-control profile-password-input" value="<?php echo htmlspecialchars((string)($studentProfile['student_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Student email">
                                    </div>
                                    <div class="profile-field">
                                        <div class="profile-field-label">Phone</div>
                                        <input type="text" name="phone" class="form-control profile-password-input" value="<?php echo htmlspecialchars((string)($studentProfile['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Phone number">
                                    </div>
                                    <div class="profile-field">
                                        <div class="profile-field-label">Birth Date</div>
                                        <input type="date" name="date_of_birth" class="form-control profile-password-input" value="<?php echo htmlspecialchars((string)($studentProfile['date_of_birth'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    </div>
                                    <div class="profile-field">
                                        <div class="profile-field-label">Gender</div>
                                        <select name="gender" class="form-control profile-password-input">
                                            <option value="">Select gender</option>
                                            <option value="male" <?php echo strtolower((string)($studentProfile['gender'] ?? '')) === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo strtolower((string)($studentProfile['gender'] ?? '')) === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo strtolower((string)($studentProfile['gender'] ?? '')) === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="profile-field full">
                                        <div class="profile-field-label">Emergency Contact</div>
                                        <input type="text" name="emergency_contact" class="form-control profile-password-input" value="<?php echo htmlspecialchars((string)($studentProfile['emergency_contact'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Emergency contact person and number">
                                    </div>
                                    <div class="profile-field full">
                                        <div class="profile-field-label">Address</div>
                                        <textarea name="address" class="form-control profile-password-input" rows="3" placeholder="Home address"><?php echo htmlspecialchars((string)($studentProfile['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary profile-action-btn w-100 mb-3">Save Student Details</button>
                            </form>

                            <div class="profile-section-divider"></div>
                            <?php endif; ?>
                            <form method="post" enctype="multipart/form-data" class="mb-3">
                                <input type="hidden" name="action" value="upload_profile_picture">
                                <label class="form-label mb-2" style="font-weight: 600; display: flex; align-items: center; gap: 6px;">Update Profile Photo</label>
                                <input type="file" name="profile_picture" class="form-control profile-upload-input mb-3" accept="image/*" required>
                                <button type="submit" class="btn btn-primary profile-action-btn w-100 mb-3">
                                    Upload Photo
                                </button>
                                <div class="profile-upload-status">
                                    Current source: <?php echo htmlspecialchars($profile_picture_src !== '' ? $profile_picture_src : 'Default avatar', ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </form>

                            <div class="profile-section-divider"></div>

                            <form method="post" class="mb-3">
                                <input type="hidden" name="action" value="change_password">
                                <div class="profile-subtitle">Change Password</div>
                                <input type="password" name="current_password" class="form-control profile-password-input mb-2" placeholder="Current password" autocomplete="current-password" required>
                                <input type="password" name="new_password" class="form-control profile-password-input mb-2" placeholder="New password" autocomplete="new-password" minlength="8" required>
                                <input type="password" name="confirm_password" class="form-control profile-password-input mb-2" placeholder="Confirm new password" autocomplete="new-password" minlength="8" required>
                                <div class="profile-password-toggle">
                                    <input type="checkbox" id="toggle-password-visibility" class="profile-password-toggle-input">
                                    <label for="toggle-password-visibility" class="profile-password-toggle-label">
                                        <span class="profile-password-toggle-switch" aria-hidden="true"></span>
                                        <span class="profile-password-toggle-text">Show passwords</span>
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-outline-primary profile-action-btn w-100">Change Password</button>
                                <div class="profile-password-hint">Use at least 8 characters with uppercase, lowercase, and a number.</div>
                            </form>

                            <div class="profile-section-divider"></div>

                            <div class="profile-subtitle">Account Workspace</div>
                            <div class="d-grid gap-2">
                                <a class="btn btn-outline-primary profile-action-btn" href="notifications.php">Review notifications and alerts</a>
                                <a class="btn btn-outline-secondary profile-action-btn" href="activity-feed.php">Review recent account activity</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

