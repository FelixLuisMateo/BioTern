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

$conn->query("CREATE TABLE IF NOT EXISTS coordinator_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coordinator_user_id INT NOT NULL,
    coordinator_id INT NULL,
    course_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_coordinator_course (coordinator_user_id, course_id),
    KEY idx_coordinator_id (coordinator_id),
    KEY idx_course_id (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    die('Invalid coordinator id.');
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

$courses = [];
$courses_res = $conn->query("SELECT id, name FROM courses WHERE deleted_at IS NULL ORDER BY name ASC");
if (!$courses_res) {
    $courses_res = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
}
if ($courses_res) {
    while ($row = $courses_res->fetch_assoc()) {
        $courses[] = $row;
    }
}

$stmt = $conn->prepare('SELECT * FROM coordinators WHERE id = ? AND deleted_at IS NULL LIMIT 1');
$coordinator = null;
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $coordinator = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$coordinator) {
    die('Coordinator not found.');
}

$selected_course_ids = [];
$map_stmt = $conn->prepare('SELECT course_id FROM coordinator_courses WHERE coordinator_user_id = ?');
if ($map_stmt) {
    $current_user_id = (int)$coordinator['user_id'];
    $map_stmt->bind_param('i', $current_user_id);
    $map_stmt->execute();
    $result = $map_stmt->get_result();
    while ($result && $row = $result->fetch_assoc()) {
        $selected_course_ids[] = (int)$row['course_id'];
    }
    $map_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'delete') {
        $del = $conn->prepare('UPDATE coordinators SET deleted_at = NOW() WHERE id = ?');
        if ($del) {
            $del->bind_param('i', $id);
            $del->execute();
            $del->close();
            header('Location: coordinators.php');
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
    $office_location = trim((string)($_POST['office_location'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $profile_picture = trim((string)($_POST['profile_picture'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $posted_course_ids = isset($_POST['course_ids']) && is_array($_POST['course_ids'])
        ? array_values(array_unique(array_filter(array_map('intval', $_POST['course_ids']), fn($v) => $v > 0)))
        : [];

    if ($user_id <= 0 || $first_name === '' || $last_name === '' || $email === '') {
        $message = 'Please complete required fields.';
        $message_type = 'danger';
    } else {
        $up = $conn->prepare('UPDATE coordinators SET user_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, department_id = ?, office_location = ?, bio = ?, profile_picture = ?, is_active = ? WHERE id = ?');
        if ($up) {
            $up->bind_param('isssssisssii', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $department_id, $office_location, $bio, $profile_picture, $is_active, $id);
            if ($up->execute()) {
                $old_user_id = (int)$coordinator['user_id'];
                $delete_map_stmt = $conn->prepare('DELETE FROM coordinator_courses WHERE coordinator_user_id = ?');
                if ($delete_map_stmt) {
                    $delete_map_stmt->bind_param('i', $old_user_id);
                    $delete_map_stmt->execute();
                    $delete_map_stmt->close();
                }

                if (!empty($posted_course_ids)) {
                    $insert_map_stmt = $conn->prepare('INSERT INTO coordinator_courses (coordinator_user_id, coordinator_id, course_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE coordinator_id = VALUES(coordinator_id), updated_at = NOW()');
                    if ($insert_map_stmt) {
                        foreach ($posted_course_ids as $course_id) {
                            $insert_map_stmt->bind_param('iii', $user_id, $id, $course_id);
                            $insert_map_stmt->execute();
                        }
                        $insert_map_stmt->close();
                    }
                }

                header('Location: coordinators.php');
                exit;
            }
            $message = 'Failed to update coordinator: ' . $up->error;
            $message_type = 'danger';
            $up->close();
        }
    }
}

$page_title = 'Edit Coordinator';
include 'includes/header.php';
?>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Edit Coordinator</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="coordinators.php">Coordinators</a></li>
            <li class="breadcrumb-item">Edit</li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="card stretch stretch-full">
        <div class="card-header"><h5 class="card-title mb-0">Coordinator Form</h5></div>
        <div class="card-body">
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php endif; ?>
            <form method="post" class="row g-3">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <div class="col-md-4">
                    <label class="form-label">User *</label>
                    <select name="user_id" class="form-select" required>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>" <?php echo ((int)$coordinator['user_id'] === (int)$u['id']) ? 'selected' : ''; ?>><?php echo h(($u['name'] ?? '') . ' (' . ($u['email'] ?? '') . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?php echo h($coordinator['first_name']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?php echo h($coordinator['last_name']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?php echo h($coordinator['middle_name']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?php echo h($coordinator['email']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?php echo h($coordinator['phone']); ?>"></div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)$coordinator['department_id'] === (int)$d['id']) ? 'selected' : ''; ?>><?php echo h($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Office Location</label><input type="text" name="office_location" class="form-control" value="<?php echo h($coordinator['office_location']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Profile Picture (path)</label><input type="text" name="profile_picture" class="form-control" value="<?php echo h($coordinator['profile_picture']); ?>"></div>
                <div class="col-12">
                    <label class="form-label">Handled Courses</label>
                    <div class="row g-2">
                        <?php foreach ($courses as $c): ?>
                            <?php $checked = in_array((int)$c['id'], $selected_course_ids, true); ?>
                            <div class="col-md-4 col-sm-6">
                                <label class="form-check-label d-flex align-items-center gap-2">
                                    <input class="form-check-input" type="checkbox" name="course_ids[]" value="<?php echo (int)$c['id']; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                    <span><?php echo h($c['name']); ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">Select one or more courses this coordinator can handle.</small>
                </div>
                <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" rows="2" class="form-control"><?php echo h($coordinator['bio']); ?></textarea></div>
                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="is_active" id="is_active_edit" <?php echo ((int)$coordinator['is_active'] === 1) ? 'checked' : ''; ?>><label class="form-check-label" for="is_active_edit">Active</label></div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="coordinators.php" class="btn btn-outline-secondary">Back to List</a>
                </div>
            </form>
            <hr>
            <form method="post" data-confirm-message="Delete this coordinator?">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-outline-danger">Delete Coordinator</button>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; $conn->close(); ?>
