<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/offices.php';
/** @var mysqli $conn */

require_roles_page(['admin']);

$message = '';
$message_type = 'info';

biotern_offices_ensure_schema($conn);

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

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    die('Invalid supervisor id.');
}

$departments = [];
$dept_res = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($dept_res) {
    while ($row = $dept_res->fetch_assoc()) {
        $departments[] = $row;
    }
}
$courses = [];
$courseRes = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
if ($courseRes) {
    while ($row = $courseRes->fetch_assoc()) {
        $courses[] = $row;
    }
}
$offices = biotern_offices_all($conn);

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

$linkedUser = null;
$linkedUserStmt = $conn->prepare('SELECT id, name, username, email FROM users WHERE id = ? LIMIT 1');
if ($linkedUserStmt) {
    $linkedUserId = (int)($supervisor['user_id'] ?? 0);
    $linkedUserStmt->bind_param('i', $linkedUserId);
    $linkedUserStmt->execute();
    $linkedUser = $linkedUserStmt->get_result()->fetch_assoc() ?: null;
    $linkedUserStmt->close();
}
$selectedOfficeIds = biotern_supervisor_office_ids($conn, $id);

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

    $user_id = (int)($supervisor['user_id'] ?? 0);
    $first_name = trim((string)($_POST['first_name'] ?? ''));
    $last_name = trim((string)($_POST['last_name'] ?? ''));
    $middle_name = trim((string)($_POST['middle_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $course_id_raw = trim((string)($_POST['course_id'] ?? ''));
    $course_id = $course_id_raw !== '' ? (int)$course_id_raw : null;
    $department_id_raw = trim((string)($_POST['department_id'] ?? ''));
    $department_id = $department_id_raw !== '' ? (int)$department_id_raw : null;
    $specialization = trim((string)($_POST['specialization'] ?? ''));
    $office_location = trim((string)($_POST['office_location'] ?? ''));
    $office_ids = isset($_POST['office_ids']) && is_array($_POST['office_ids']) ? $_POST['office_ids'] : [];
    $bio = trim((string)($_POST['bio'] ?? ''));
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($user_id <= 0 || $first_name === '' || $last_name === '' || $email === '') {
        $message = 'Please complete required fields.';
        $message_type = 'danger';
    } else {
        if ($hasSupervisorColumn('office_location')) {
            $up = $conn->prepare('UPDATE supervisors SET user_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, course_id = ?, department_id = ?, specialization = ?, office_location = ?, bio = ?, is_active = ? WHERE id = ?');
        } elseif ($hasSupervisorColumn('office')) {
            $up = $conn->prepare('UPDATE supervisors SET user_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, department_id = ?, specialization = ?, office = ?, bio = ?, is_active = ? WHERE id = ?');
        } else {
            $up = $conn->prepare('UPDATE supervisors SET user_id = ?, first_name = ?, last_name = ?, middle_name = ?, email = ?, phone = ?, department_id = ?, specialization = ?, bio = ?, is_active = ? WHERE id = ?');
        }
        if ($up) {
            if ($hasSupervisorColumn('office_location') || $hasSupervisorColumn('office')) {
                $up->bind_param('isssssiisssii', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $course_id, $department_id, $specialization, $office_location, $bio, $is_active, $id);
            } else {
                $up->bind_param('isssssissii', $user_id, $first_name, $last_name, $middle_name, $email, $phone, $department_id, $specialization, $bio, $is_active, $id);
            }
            if ($up->execute()) {
                biotern_supervisor_sync_offices($conn, $id, $office_ids, $office_location);
                header('Location: supervisors.php');
                exit;
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
<main class="nxl-container">
    <div class="nxl-content">
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

<div class="main-content">
    <div class="card stretch stretch-full">
        <div class="card-header"><h5 class="card-title mb-0">Supervisor Form</h5></div>
        <div class="card-body">
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php endif; ?>
            <form method="post" class="row g-3">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <div class="col-md-4">
                    <label class="form-label">Username</label>
                    <input type="hidden" name="user_id" value="<?php echo (int)($supervisor['user_id'] ?? 0); ?>">
                    <input type="text" class="form-control" value="<?php echo h((string)($linkedUser['username'] ?? 'Linked user missing')); ?>" readonly>
                    <small class="text-muted">Username is unique and cannot be changed here.</small>
                </div>
                <div class="col-md-4"><label class="form-label">First Name *</label><input type="text" name="first_name" class="form-control" value="<?php echo h($supervisor['first_name']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Last Name *</label><input type="text" name="last_name" class="form-control" value="<?php echo h($supervisor['last_name']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?php echo h($supervisor['middle_name']); ?>"></div>
                <div class="col-md-4"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" value="<?php echo h($supervisor['email']); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?php echo h($supervisor['phone']); ?>"></div>
                <div class="col-md-4">
                    <label class="form-label">Course</label>
                    <select name="course_id" id="supervisorCourse" class="form-select">
                        <option value="">Any course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo (int)$course['id']; ?>" <?php echo ((int)($supervisor['course_id'] ?? 0) === (int)$course['id']) ? 'selected' : ''; ?>><?php echo h($course['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department_id" id="supervisorDepartment" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)$supervisor['department_id'] === (int)$d['id']) ? 'selected' : ''; ?>><?php echo h($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4"><label class="form-label">Specialization</label><input type="text" name="specialization" class="form-control" value="<?php echo h($supervisor['specialization']); ?>"></div>
                <div class="col-md-4">
                    <label class="form-label">Office Assignments</label>
                    <select name="office_ids[]" id="supervisorOffices" class="form-select" multiple size="4">
                        <?php foreach ($offices as $office): ?>
                            <option value="<?php echo (int)$office['id']; ?>" data-course-id="<?php echo (int)($office['course_id'] ?? 0); ?>" data-department-id="<?php echo (int)($office['department_id'] ?? 0); ?>" <?php echo in_array((int)$office['id'], $selectedOfficeIds, true) ? 'selected' : ''; ?>><?php echo h($office['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">A supervisor can manage more than one office.</small>
                </div>
                <div class="col-md-4"><label class="form-label">Add Office</label><input type="text" name="office_location" class="form-control" placeholder="Example: ComLab 2"></div>
                <div class="col-12"><label class="form-label">Bio</label><textarea name="bio" rows="2" class="form-control"><?php echo h($supervisor['bio']); ?></textarea></div>
                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" name="is_active" id="is_active_edit" <?php echo ((int)$supervisor['is_active'] === 1) ? 'checked' : ''; ?>><label class="form-check-label" for="is_active_edit">Active</label></div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="supervisors.php" class="btn btn-outline-secondary">Back to List</a>
                </div>
            </form>
            <hr>
            <form method="post" data-confirm-message="Delete this supervisor?">
                <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-outline-danger">Delete Supervisor</button>
            </form>
        </div>
    </div>
</div>
</div> <!-- .nxl-content -->
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var course = document.getElementById('supervisorCourse');
    var department = document.getElementById('supervisorDepartment');
    var offices = document.getElementById('supervisorOffices');
    if (!course || !department || !offices) return;
    function filterOffices() {
        var courseId = course.value || '0';
        var departmentId = department.value || '0';
        Array.prototype.forEach.call(offices.options, function (option) {
            var officeCourse = option.getAttribute('data-course-id') || '0';
            var officeDepartment = option.getAttribute('data-department-id') || '0';
            var courseMatches = courseId === '0' || officeCourse === '0' || officeCourse === courseId;
            var departmentMatches = departmentId === '0' || officeDepartment === '0' || officeDepartment === departmentId;
            option.hidden = !(courseMatches && departmentMatches);
        });
    }
    course.addEventListener('change', filterOffices);
    department.addEventListener('change', filterOffices);
    filterOffices();
});
</script>
<?php include 'includes/footer.php'; $conn->close(); ?>




