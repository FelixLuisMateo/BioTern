<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */

$message = '';
$message_type = 'info';

$coordinatorColumns = [];
$coordinatorColumnResult = $conn->query("SHOW COLUMNS FROM coordinators");
if ($coordinatorColumnResult) {
    while ($column = $coordinatorColumnResult->fetch_assoc()) {
        $coordinatorColumns[] = strtolower((string)$column['Field']);
    }
}

$hasCoordinatorColumn = function (string $columnName) use ($coordinatorColumns): bool {
    return in_array(strtolower($columnName), $coordinatorColumns, true);
};

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$departments = [];
$dept_res = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($dept_res) {
    while ($row = $dept_res->fetch_assoc()) {
        $departments[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
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
    $profile_picture_fs = '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($username === '' || $password === '' || $first_name === '' || $last_name === '' || $email === '') {
        $message = 'Please complete required fields.';
        $message_type = 'danger';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
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
                            $profile_picture_fs = $destFs;
                            $profile_picture = 'uploads/profile_pictures/' . $filename;
                        }
                    }
                }
            }
        }

        if ($message !== '' && $message_type === 'danger') {
            // keep message and do not insert
        } else {
            $deptForInsert = $department_id ?? 0;
            $displayName = trim($first_name . ' ' . $last_name);

            $duplicateStmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            if (!$duplicateStmt) {
                $message = 'Failed to prepare user lookup.';
                $message_type = 'danger';
            } else {
                $duplicateStmt->bind_param('ss', $username, $email);
                $duplicateStmt->execute();
                $duplicateUser = $duplicateStmt->get_result()->fetch_assoc();
                $duplicateStmt->close();

                if ($duplicateUser) {
                    $message = 'A user with that username or email already exists.';
                    $message_type = 'warning';
                }
            }
        }

        if ($message !== '' && ($message_type === 'danger' || $message_type === 'warning')) {
            if ($profile_picture_fs !== '' && is_file($profile_picture_fs)) {
                @unlink($profile_picture_fs);
            }
        } else {
            $deptForInsert = $department_id ?? 0;
            $displayName = trim($first_name . ' ' . $last_name);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $insertSql = '';
            if ($hasCoordinatorColumn('office_location')) {
                $insertSql = 'INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, office_location, bio, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?)';
            } elseif ($hasCoordinatorColumn('office')) {
                $insertSql = 'INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, office, bio, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?)';
            } else {
                $insertSql = 'INSERT INTO coordinators (user_id, first_name, last_name, middle_name, email, phone, department_id, bio, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?)';
            }

            $conn->begin_transaction();
            $user_id = 0;

            try {
                $userStmt = $conn->prepare('INSERT INTO users (name, username, email, password, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
                if (!$userStmt) {
                    throw new RuntimeException('Failed to prepare user insert statement.');
                }
                $role = 'coordinator';
                $userStmt->bind_param('sssssi', $displayName, $username, $email, $passwordHash, $role, $is_active);
                if (!$userStmt->execute()) {
                    $err = $userStmt->error;
                    $userStmt->close();
                    throw new RuntimeException('Failed to create linked user: ' . $err);
                }
                $user_id = (int)$userStmt->insert_id;
                $userStmt->close();

                $stmt = $conn->prepare($insertSql);
                if (!$stmt) {
                    throw new RuntimeException('Failed to prepare coordinator insert statement.');
                }
                if ($hasCoordinatorColumn('office_location') || $hasCoordinatorColumn('office')) {
                    $stmt->bind_param('isssssisssi', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $deptForInsert, $office_location, $bio, $profile_picture, $is_active);
                } else {
                    $stmt->bind_param('isssssissi', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $deptForInsert, $bio, $profile_picture, $is_active);
                }
                
                if ($stmt->execute()) {
                    $stmt->close();
                    $conn->commit();
                    header('Location: coordinators.php');
                    exit;
                }
                $errorText = $stmt->error;
                $stmt->close();
                throw new RuntimeException('Failed to create coordinator: ' . $errorText);
            } catch (Throwable $e) {
                $conn->rollback();
                if ($profile_picture_fs !== '' && is_file($profile_picture_fs)) {
                    @unlink($profile_picture_fs);
                }
                if ((int)$e->getCode() === 1062 || stripos($e->getMessage(), 'Duplicate') !== false) {
                    $message = 'Duplicate coordinator record detected (username/email already used).';
                    $message_type = 'warning';
                } else {
                    $message = $e->getMessage();
                    $message_type = 'danger';
                }
            }
        }
    }
}

$page_title = 'Create Coordinator';
$page_styles = ['assets/css/modules/management/management-create-shared.css'];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
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
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" autocomplete="off" autocapitalize="off" spellcheck="false" required>
                </div>
                <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" required></div>
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
                <div class="col-md-4"><label class="form-label">Profile Picture</label><input type="file" name="profile_picture" class="form-control create-form-file-input" accept="image/*"></div>
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
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; $conn->close(); ?>





