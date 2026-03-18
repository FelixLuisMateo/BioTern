<?php
require_once dirname(__DIR__) . '/config/db.php';
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : '';
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$db_port = defined('DB_PORT') ? (int)DB_PORT : 3306;

$message = '';
$message_type = 'info';

$conn = new mysqli($host, $db_user, $db_password, $db_name, $db_port);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function is_valid_username(string $username): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9._-]{4,30}$/', $username);
}

function has_disallowed_username_term(string $username): bool
{
    $check = strtolower($username);
    $blocked_terms = [
        'admin', 'root', 'system', 'support', 'moderator', 'owner',
        'fuck', 'shit', 'bitch', 'asshole', 'nigger', 'porn', 'sex'
    ];

    foreach ($blocked_terms as $term) {
        if (strpos($check, $term) !== false) {
            return true;
        }
    }

    return false;
}

$departments = [];
$dept_res = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($dept_res) {
    while ($row = $dept_res->fetch_assoc()) {
        $departments[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_email = trim((string)($_POST['user_email'] ?? ''));
    $username_input = trim((string)($_POST['username'] ?? ''));
    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name = trim((string)($_POST['last_name'] ?? ''));
    $middle_name = trim((string)($_POST['middle_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $department_id_raw = trim((string)($_POST['department_id'] ?? ''));
    $department_id = $department_id_raw !== '' ? (int)$department_id_raw : null;
    $office_location = trim((string)($_POST['office_location'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $profile_picture = '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($first_name === '' || $last_name === '' || $email === '') {
        $message = 'Please complete required fields.';
        $message_type = 'danger';
    } else {
        $user_id = 0;
        $lookup_email = $user_email !== '' ? $user_email : $email;
        if ($lookup_email !== '') {
            $user_stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            if ($user_stmt) {
                $user_stmt->bind_param('s', $lookup_email);
                if ($user_stmt->execute()) {
                    $user_stmt->bind_result($found_id);
                    if ($user_stmt->fetch()) {
                        $user_id = (int)$found_id;
                    }
                }
                $user_stmt->close();
            }
        }

        if ($user_id === 0 && $lookup_email !== '') {
            if ($username_input === '') {
                $message = 'Username is required when creating a new user account.';
                $message_type = 'danger';
            } elseif (!is_valid_username($username_input)) {
                $message = 'Username must be 4-30 characters and use only letters, numbers, dot, underscore, or hyphen.';
                $message_type = 'danger';
            } elseif (has_disallowed_username_term($username_input)) {
                $message = 'Please choose a more appropriate username.';
                $message_type = 'danger';
            }

            if ($message === '') {
                $check_stmt = $conn->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
                if ($check_stmt) {
                    $check_stmt->bind_param('s', $username_input);
                    $check_stmt->execute();
                    $check_stmt->store_result();
                    if ($check_stmt->num_rows > 0) {
                        $message = 'Username already exists. Please choose another one.';
                        $message_type = 'danger';
                    }
                    $check_stmt->close();
                }
            }

            if ($message === '') {
                $name = trim($first_name . ' ' . $last_name);
                $random_password = bin2hex(random_bytes(6));
                $password_hash = password_hash($random_password, PASSWORD_BCRYPT);
                $user_ins = $conn->prepare("INSERT INTO users (name, username, email, password, role, is_active, application_status, created_at) VALUES (?, ?, ?, ?, 'coordinator', 1, 'approved', NOW())");
                if ($user_ins) {
                    $user_ins->bind_param('ssss', $name, $username_input, $lookup_email, $password_hash);
                    if ($user_ins->execute()) {
                        $user_id = (int)$user_ins->insert_id;
                    }
                    $user_ins->close();
                }
            }
        }

        if ($user_id <= 0) {
            $message = 'Unable to link coordinator to users table. Please use a valid email.';
            $message_type = 'danger';
        }

        if ($message === '' && isset($_FILES['profile_picture']) && is_array($_FILES['profile_picture']) && (int)$_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['profile_picture'];
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
                        $filename = 'coordinator_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
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

        if ($message !== '' && $message_type === 'danger') {
            // keep message and do not insert
        } else {
        $stmt = $conn->prepare('INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, office_location, bio, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('isssssisssi', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $department_id, $office_location, $bio, $profile_picture, $is_active);
            if ($stmt->execute()) {
                header('Location: coordinators.php');
                exit;
            }
            $message = 'Failed to create coordinator: ' . $stmt->error;
            $message_type = 'danger';
            $stmt->close();
        }
        }
    }
}

$page_title = 'Create Coordinator';
include 'includes/header.php';
?>
<style>
    .create-form-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .create-form-actions .btn {
        width: auto !important;
        min-width: 140px;
        display: inline-flex;
        justify-content: center;
        align-items: center;
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
</style>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Create Coordinator</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="coordinators.php">Coordinators</a></li>
            <li class="breadcrumb-item">Create</li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="card stretch stretch-full">
        <div class="card-header"><h5 class="card-title mb-0">Coordinator Form</h5></div>
        <div class="card-body">
            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($message !== ''): ?><div class="alert alert-<?php
require_once dirname(__DIR__) . '/config/db.php';
echo h($message_type); ?>"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo h($message); ?></div><?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">User Email</label>
                    <input type="email" name="user_email" class="form-control" placeholder="">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control">
                </div>
                <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">None</option>
                        <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($departments as $d): ?>
                            <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$d['id']; ?>"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo h($d['name']); ?></option>
                        <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Office Location</label><input type="text" name="office_location" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Profile Picture</label><input type="file" name="profile_picture" class="form-control" accept="image/*"></div>
                <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" rows="2" class="form-control"></textarea></div>
                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="is_active" id="is_active_create" checked><label class="form-check-label" for="is_active_create">Active</label></div>
                <div class="col-12 create-form-actions">
                    <button type="submit" class="btn btn-primary">Save Coordinator</button>
                    <a href="coordinators.php" class="btn btn-outline-secondary">Back to List</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
require_once dirname(__DIR__) . '/config/db.php';
include 'includes/footer.php'; $conn->close(); ?>


