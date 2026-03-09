<?php
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
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

    if ($user_id <= 0 || $first_name === '' || $last_name === '' || $email === '') {
        $message = 'Please complete required fields.';
        $message_type = 'danger';
    } else {
        if (isset($_FILES['profile_picture']) && is_array($_FILES['profile_picture']) && (int)$_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
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
<link rel="stylesheet" type="text/css" href="assets/css/management-create-shared-page.css">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Create Coordinator</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="coordinators.php">Coordinators</a></li>
            <li class="breadcrumb-item">Create</li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="card stretch stretch-full">
        <div class="card-header"><h5 class="card-title mb-0">Coordinator Form</h5></div>
        <div class="card-body">
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">User *</label>
                    <select name="user_id" class="form-select" required>
                        <option value="">Select user</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>"><?php echo h(($u['name'] ?? '') . ' (' . ($u['email'] ?? '') . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
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
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>"><?php echo h($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Office Location</label><input type="text" name="office_location" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Profile Picture</label><input type="file" name="profile_picture" class="form-control" accept="image/*"></div>
                <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" rows="2" class="form-control"></textarea></div>
                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="is_active" id="is_active_create" checked><label class="form-check-label" for="is_active_create">Active</label></div>
                <div class="col-12 create-form-actions app-form-actions">
                    <button type="submit" class="btn btn-primary">Save Coordinator</button>
                    <a href="coordinators.php" class="btn btn-outline-secondary">Back to List</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; $conn->close(); ?>

