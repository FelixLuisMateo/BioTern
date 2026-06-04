<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
/** @var mysqli $conn */

require_roles_page(['admin']);

$message = '';
$message_type = 'info';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function edit_post_value(string $key, string $fallback = ''): string
{
    return htmlspecialchars((string)($_POST[$key] ?? $fallback), ENT_QUOTES, 'UTF-8');
}

function edit_post_course_ids(): array
{
    $courseId = (int)($_POST['course_id'] ?? 0);
    return $courseId > 0 ? [$courseId] : [];
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    die('Invalid coordinator id.');
}

$departments = [];
$dept_res = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($dept_res) {
    while ($row = $dept_res->fetch_assoc()) {
        $departments[] = $row;
    }
}

$courses = [];
$course_res = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
if ($course_res) {
    while ($row = $course_res->fetch_assoc()) {
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

$linkedUser = null;
$linkedUserStmt = $conn->prepare('SELECT id, name, username, email FROM users WHERE id = ? LIMIT 1');
if ($linkedUserStmt) {
    $linkedUserId = (int)($coordinator['user_id'] ?? 0);
    $linkedUserStmt->bind_param('i', $linkedUserId);
    $linkedUserStmt->execute();
    $linkedUser = $linkedUserStmt->get_result()->fetch_assoc() ?: null;
    $linkedUserStmt->close();
}

$assignedCourseIds = function_exists('coordinator_course_ids')
    ? coordinator_course_ids($conn, (int)($coordinator['user_id'] ?? 0))
    : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $originalUserId = (int)($coordinator['user_id'] ?? 0);
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

    $user_id = (int)($coordinator['user_id'] ?? 0);
    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name = trim((string)($_POST['last_name'] ?? ''));
    $middle_name = trim((string)($_POST['middle_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $department_id_raw = trim((string)($_POST['department_id'] ?? ''));
    $department_id = $department_id_raw !== '' ? (int)$department_id_raw : null;
    $office_location = trim((string)($_POST['office_location'] ?? ''));
    $bio = trim((string)($_POST['bio'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $selectedCourseIds = edit_post_course_ids();

    if ($user_id <= 0 || $first_name === '' || $last_name === '' || $email === '') {
        $message = 'Please complete required fields.';
        $message_type = 'danger';
    } elseif (!empty($courses) && empty($selectedCourseIds)) {
        $message = 'Please select exactly one course this coordinator can manage.';
        $message_type = 'danger';
    } else {
        $conn->begin_transaction();
        try {
            $up = $conn->prepare('UPDATE coordinators SET user_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, department_id = ?, office_location = ?, bio = ?, is_active = ? WHERE id = ?');
            if (!$up) {
                throw new RuntimeException('Failed to prepare coordinator update statement.');
            }

            $up->bind_param('isssssissii', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $department_id, $office_location, $bio, $is_active, $id);
            if (!$up->execute()) {
                $errorText = $up->error;
                $up->close();
                throw new RuntimeException('Failed to update coordinator: ' . $errorText);
            }
            $up->close();

            $displayName = trim($first_name . ' ' . $last_name);
            $userUpdate = $conn->prepare('UPDATE users SET name = ?, email = ?, is_active = ?, updated_at = NOW() WHERE id = ? LIMIT 1');
            if (!$userUpdate) {
                throw new RuntimeException('Failed to prepare linked user update statement.');
            }
            $userUpdate->bind_param('ssii', $displayName, $email, $is_active, $user_id);
            if (!$userUpdate->execute()) {
                $errorText = $userUpdate->error;
                $userUpdate->close();
                throw new RuntimeException('Failed to update linked user: ' . $errorText);
            }
            $userUpdate->close();

            if ($originalUserId > 0 && $originalUserId !== $user_id && !sync_coordinator_courses($conn, $originalUserId, [])) {
                throw new RuntimeException('Failed to clear the previous coordinator course assignments.');
            }

            if (!empty($courses) && !sync_coordinator_courses($conn, $user_id, $selectedCourseIds)) {
                throw new RuntimeException('Failed to save coordinator course assignments.');
            }

            $conn->commit();
            header('Location: coordinators.php');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = 'danger';
        }
    }
}

$page_title = 'Edit Coordinator';
$page_styles = [
    'assets/css/modules/management/management-coordinators.css',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title"><h5 class="m-b-10">Edit Coordinator</h5></div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
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
                    <label class="form-label">Username</label>
                    <input type="hidden" name="user_id" value="<?php echo (int)($coordinator['user_id'] ?? 0); ?>">
                    <input type="text" class="form-control" value="<?php echo h((string)($linkedUser['username'] ?? 'Linked user missing')); ?>" readonly>
                    <small class="text-muted">Username is unique and cannot be changed here.</small>
                </div>
                <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?php echo edit_post_value('first_name', $coordinator['first_name']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?php echo edit_post_value('last_name', $coordinator['last_name']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?php echo edit_post_value('middle_name', $coordinator['middle_name']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?php echo edit_post_value('email', $coordinator['email']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?php echo edit_post_value('phone', $coordinator['phone']); ?>"></div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="" <?php echo empty($_POST['department_id']) && empty($coordinator['department_id']) ? 'selected' : ''; ?>>None</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo ((string)($_POST['department_id'] ?? $coordinator['department_id']) === (string)$d['id']) ? 'selected' : ''; ?>><?php echo h($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Course to Manage *</label>
                        <?php
                        $selectedCourses = array_key_exists('course_id', $_POST) ? edit_post_course_ids() : array_slice($assignedCourseIds, 0, 1);
                        ?>
                    <select name="course_id" class="form-select" required>
                        <option value="">Select course</option>
                        <?php foreach ($courses as $course):
                            $courseId = (int)$course['id'];
                        ?>
                            <option value="<?php echo $courseId; ?>" <?php echo in_array($courseId, $selectedCourses, true) ? 'selected' : ''; ?>>
                                <?php echo h($course['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="app-coordinator-course-help d-block">Each course can have only one coordinator. Saving this will move the course from any previous coordinator.</small>
                </div>
                <div class="col-md-4"><label class="form-label">Office Location</label><input type="text" name="office_location" class="form-control" value="<?php echo edit_post_value('office_location', $coordinator['office_location']); ?>"></div>
                <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" rows="2" class="form-control"><?php echo edit_post_value('bio', $coordinator['bio']); ?></textarea></div>
                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="is_active" id="is_active_edit" <?php echo isset($_POST['is_active']) ? 'checked' : (((int)$coordinator['is_active'] === 1) ? 'checked' : ''); ?>><label class="form-check-label" for="is_active_edit">Active</label></div>
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
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; $conn->close(); ?>




