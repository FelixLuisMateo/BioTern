<?php
require_once dirname(__DIR__) . '/config/db.php';
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : ''; 
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';

$message = '';
$message_type = 'info';

$conn = new mysqli($host, $db_user, $db_password, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function supervisor_office_value(array $row): string
{
    foreach (['office', 'specialization', 'office_location'] as $field) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }
    return '';
}

$supervisorColumns = [];
$supervisorColumnResult = $conn->query("SHOW COLUMNS FROM supervisors");
if ($supervisorColumnResult) {
    while ($column = $supervisorColumnResult->fetch_assoc()) {
        $supervisorColumns[] = strtolower((string)$column['Field']);
    }
}

$hasSupervisorColumn = function (string $columnName) use ($supervisorColumns): bool {
    return in_array(strtolower($columnName), $supervisorColumns, true);
};

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    die('Invalid supervisor id.');
}

$users = [];
$users_res = $conn->query("SELECT id, name, email FROM users ORDER BY name ASC");
if ($users_res) {
    while ($row = $users_res->fetch_assoc()) {
        $users[] = $row;
    }
}

$departments = [];
$dept_res = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($dept_res) {
    while ($row = $dept_res->fetch_assoc()) {
        $departments[] = $row;
    }
}

$stmt = $conn->prepare('SELECT * FROM supervisors WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$supervisor = null;
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $supervisor = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$supervisor) {
    die('Supervisor not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'delete') {
        $del = $conn->prepare('UPDATE supervisors SET deleted_at = NOW() WHERE id = ?');
        if ($del) {
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
            header('Location: supervisors.php');
            exit;
        }
    }

    $user_id = (int)($_POST['user_id'] ?? 0);
    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name = trim((string)($_POST['last_name'] ?? ''));
    $middle_name = trim((string)($_POST['middle_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $department_id_raw = trim((string)($_POST['department_id'] ?? ''));
    $department_id = $department_id_raw !== '' ? (int)$department_id_raw : null;
    $office = trim((string)($_POST['office'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $profile_picture = trim((string)($_POST['profile_picture'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if ($new_password !== '' || $confirm_password !== '') {
        if (strlen($new_password) < 8) {
            $message = 'New password must be at least 8 characters.';
            $message_type = 'danger';
        } elseif ($new_password !== $confirm_password) {
            $message = 'New password confirmation does not match.';
            $message_type = 'danger';
        }
    }

    if ($message === '' && isset($_FILES['profile_picture_upload']) && is_array($_FILES['profile_picture_upload']) && (int)$_FILES['profile_picture_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_picture_upload'];
        if ((int)$file['error'] !== UPLOAD_ERR_OK) {
            $message = 'Profile picture upload failed.';
            $message_type = 'danger';
        } else {
            $tmp = (string)$file['tmp_name'];
            $orig = (string)$file['name'];
            $size = (int)$file['size'];
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($ext, $allowed, true)) {
                $message = 'Invalid image type. Allowed: jpg, jpeg, png, gif, webp.';
                $message_type = 'danger';
            } elseif ($size > 5 * 1024 * 1024) {
                $message = 'Image size must be 5MB or less.';
                $message_type = 'danger';
            } elseif (@getimagesize($tmp) === false) {
                $message = 'Uploaded file is not a valid image.';
                $message_type = 'danger';
            } else {
                $uploadDirFs = dirname(__DIR__) . '/uploads/profile_pictures';
                if (!is_dir($uploadDirFs) && !@mkdir($uploadDirFs, 0775, true)) {
                    $message = 'Failed to create upload directory.';
                    $message_type = 'danger';
                } else {
                    $filename = 'supervisor_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $destFs = $uploadDirFs . '/' . $filename;
                    if (!@move_uploaded_file($tmp, $destFs)) {
                        $message = 'Failed to save uploaded profile picture.';
                        $message_type = 'danger';
                    } else {
                        $profile_picture = 'uploads/profile_pictures/' . $filename;
                    }
                }
            }
        }
    }

    if ($user_id <= 0 || $first_name === '' || $last_name === '' || $email === '') {
        $message = 'Please complete required fields.';
        $message_type = 'danger';
    } elseif ($message === '') {
        $deptForUpdate = $department_id ?? 0;
        $profileFieldValue = $office;
        if ($hasSupervisorColumn('office')) {
            $updateSql = 'UPDATE supervisors SET user_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, department_id = NULLIF(?, 0), office = ?, bio = ?, profile_picture = ?, is_active = ? WHERE id = ?';
        } elseif ($hasSupervisorColumn('specialization')) {
            $updateSql = 'UPDATE supervisors SET user_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, department_id = NULLIF(?, 0), specialization = ?, bio = ?, profile_picture = ?, is_active = ? WHERE id = ?';
        } elseif ($hasSupervisorColumn('office_location')) {
            $updateSql = 'UPDATE supervisors SET user_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, department_id = NULLIF(?, 0), office_location = ?, bio = ?, profile_picture = ?, is_active = ? WHERE id = ?';
        } else {
            $updateSql = 'UPDATE supervisors SET user_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, department_id = NULLIF(?, 0), bio = ?, profile_picture = ?, is_active = ? WHERE id = ?';
        }

        $up = $conn->prepare($updateSql);
        if ($up) {
            if ($hasSupervisorColumn('office') || $hasSupervisorColumn('specialization') || $hasSupervisorColumn('office_location')) {
                $up->bind_param('isssssisssii', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $deptForUpdate, $profileFieldValue, $bio, $profile_picture, $is_active, $id);
            } else {
                $up->bind_param('isssssissii', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $deptForUpdate, $bio, $profile_picture, $is_active, $id);
            }
            if ($up->execute()) {
                $up->close();
                if ($new_password !== '') {
                    $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $passwordUpdate = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                    if ($passwordUpdate) {
                        $passwordUpdate->bind_param('si', $password_hash, $user_id);
                        if (!$passwordUpdate->execute()) {
                            $message = 'Supervisor updated, but failed to update password: ' . $passwordUpdate->error;
                            $message_type = 'warning';
                        }
                        $passwordUpdate->close();
                    }
                }
                if ($message === '') {
                header('Location: supervisors.php');
                exit;
                }
            }
            $message = 'Failed to update supervisor: ' . $up->error;
            $message_type = 'danger';
            $up->close();
        }
    }
}

$page_title = 'Edit Supervisor';
include 'includes/header.php';
?>
<style>
    .supervisors-form-shell .page-subtitle {
        font-size: 12px;
        color: #6c7a92;
        margin: 0;
        line-height: 1.45;
        max-width: 72ch;
    }
    .supervisors-form-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.15rem;
        margin-bottom: 1rem;
        border: 1px solid #dfe8f5;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }
    .supervisors-form-toolbar-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .edit-form-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }
    .edit-form-actions .btn {
        width: auto !important;
        min-width: 140px;
        display: inline-flex;
        justify-content: center;
        align-items: center;
    }
    .supervisor-preview {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.8rem 1rem;
        border: 1px dashed rgba(140, 160, 190, 0.32);
        border-radius: 12px;
        margin-bottom: 1rem;
    }
    .supervisor-preview img {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        object-fit: cover;
    }
    html.app-skin-dark input[type="file"].form-control {
        color: #ffffff !important;
        background-color: #0f172a !important;
        border-color: #4a5568 !important;
    }
    html.app-skin-dark input[type="file"].form-control::file-selector-button {
        color: #ffffff !important;
        background: #1e293b !important;
        border: 1px solid #4a5568 !important;
    }
    html.app-skin-dark .supervisors-form-toolbar,
    html.app-skin-dark .supervisors-form-shell .card.stretch.stretch-full {
        border-color: #253252;
        background: #111a2e;
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
    }
    html.app-skin-dark .supervisors-form-shell .page-subtitle {
        color: #99abc8;
    }
    html.app-skin-dark .supervisor-preview {
        border-color: rgba(140, 160, 190, 0.28);
        background: rgba(15, 26, 46, 0.28);
    }
    @media (max-width: 991.98px) {
        .supervisors-form-toolbar {
            flex-direction: column;
            align-items: flex-start;
        }
        .supervisors-form-toolbar-actions {
            width: 100%;
        }
        .supervisors-form-toolbar-actions .btn,
        .edit-form-actions .btn {
            width: 100%;
        }
    }
</style>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Edit Supervisor</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="supervisors.php">Supervisors</a></li>
            <li class="breadcrumb-item">Edit</li>
        </ul>
    </div>
</div>

<div class="main-content supervisors-form-shell">
    <div class="supervisors-form-toolbar">
        <p class="page-subtitle">Update supervisor profile details, linked account ownership, department assignment, and active access status.</p>
        <div class="supervisors-form-toolbar-actions">
            <a href="supervisors.php" class="btn btn-outline-secondary">Back to List</a>
        </div>
    </div>
    <div class="card stretch stretch-full">
        <div class="card-header"><h5 class="card-title mb-0">Supervisor Form</h5></div>
        <div class="card-body">
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php endif; ?>
            <div class="supervisor-preview">
                <img src="<?php echo h(!empty($supervisor['profile_picture']) ? $supervisor['profile_picture'] : 'assets/images/avatar/1.png'); ?>" alt="Supervisor profile" onerror="this.src='assets/images/avatar/1.png';">
                <div>
                    <div class="fw-semibold"><?php echo h(trim(($supervisor['first_name'] ?? '') . ' ' . ($supervisor['last_name'] ?? ''))); ?></div>
                    <div class="text-muted small"><?php echo h($supervisor['email'] ?? '-'); ?></div>
                </div>
            </div>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <div class="col-md-4">
                    <label class="form-label">User *</label>
                    <select name="user_id" class="form-select" required>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)$supervisor['user_id'] === (int)$u['id']) ? 'selected' : ''; ?>><?php echo h(($u['name'] ?? '') . ' (' . ($u['email'] ?? '') . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?php echo h($supervisor['first_name']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?php echo h($supervisor['last_name']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?php echo h($supervisor['middle_name']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?php echo h($supervisor['email']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?php echo h($supervisor['phone']); ?>"></div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)$supervisor['department_id'] === (int)$d['id']) ? 'selected' : ''; ?>><?php echo h($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Office</label><input type="text" name="office" class="form-control" value="<?php echo h($_POST['office'] ?? supervisor_office_value($supervisor)); ?>"></div>
                <div class="col-md-4"><label class="form-label">Profile Picture</label><input type="file" name="profile_picture_upload" class="form-control" accept="image/*"></div>
                <div class="col-md-4"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current password"></div>
                <div class="col-md-4"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" placeholder="Repeat new password"></div>
                <div class="col-12"><label class="form-label">Profile Picture Path</label><input type="text" name="profile_picture" class="form-control" value="<?php echo h($supervisor['profile_picture']); ?>"></div>
                <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" rows="2" class="form-control"><?php echo h($supervisor['bio']); ?></textarea></div>
                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="is_active" id="is_active_edit" <?php echo ((int)$supervisor['is_active'] === 1) ? 'checked' : ''; ?>><label class="form-check-label" for="is_active_edit">Active</label></div>
                <div class="col-12 edit-form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="supervisors.php" class="btn btn-outline-secondary">Back to List</a>
                </div>
            </form>
            <hr>
            <form method="post" onsubmit="return confirm('Delete this supervisor?');">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-outline-danger">Delete Supervisor</button>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; $conn->close(); ?>

