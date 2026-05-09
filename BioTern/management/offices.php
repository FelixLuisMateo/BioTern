<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/offices.php';
/** @var mysqli $conn */

require_roles_page(['admin']);
biotern_offices_ensure_schema($conn);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$message_type = 'info';

$courses = [];
$courseRes = $conn->query('SELECT id, name FROM courses ORDER BY name ASC');
if ($courseRes) {
    while ($row = $courseRes->fetch_assoc()) {
        $courses[] = $row;
    }
}

$departments = [];
$deptRes = $conn->query('SELECT id, name FROM departments ORDER BY name ASC');
if ($deptRes) {
    while ($row = $deptRes->fetch_assoc()) {
        $departments[] = $row;
    }
}

$supervisors = [];
$supRes = $conn->query("
    SELECT id, course_id, department_id, TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS name
    FROM supervisors
    WHERE deleted_at IS NULL AND is_active = 1
    ORDER BY name ASC
");
if ($supRes) {
    while ($row = $supRes->fetch_assoc()) {
        $supervisors[] = $row;
    }
}

$editOffice = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmt = $conn->prepare('SELECT * FROM offices WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $editId);
            $stmt->execute();
            $editOffice = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'create');
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $stmt = $conn->prepare('UPDATE offices SET deleted_at = NOW(), is_active = 0 WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $message = 'Office archived.';
            $message_type = 'success';
        }
    } else {
        $name = trim((string)($_POST['name'] ?? ''));
        $code = trim((string)($_POST['code'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $courseId = (int)($_POST['course_id'] ?? 0);
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $supervisorIds = isset($_POST['supervisor_ids']) && is_array($_POST['supervisor_ids']) ? $_POST['supervisor_ids'] : [];
        if ($name === '') {
            $message = 'Office name is required.';
            $message_type = 'danger';
        } else {
            $officeId = $id > 0 ? $id : biotern_offices_find_or_create($conn, $name);
            if ($officeId > 0) {
                $stmt = $conn->prepare('UPDATE offices SET name = ?, code = NULLIF(?, \'\'), description = NULLIF(?, \'\'), course_id = NULLIF(?, 0), department_id = NULLIF(?, 0), is_active = 1, deleted_at = NULL WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('sssiii', $name, $code, $description, $courseId, $departmentId, $officeId);
                    $stmt->execute();
                    $stmt->close();
                }
                $del = $conn->prepare('DELETE FROM supervisor_offices WHERE office_id = ?');
                if ($del) {
                    $del->bind_param('i', $officeId);
                    $del->execute();
                    $del->close();
                }
                $ins = $conn->prepare('INSERT IGNORE INTO supervisor_offices (supervisor_id, office_id) VALUES (?, ?)');
                if ($ins) {
                    foreach ($supervisorIds as $supervisorId) {
                        $supervisorId = (int)$supervisorId;
                        if ($supervisorId > 0) {
                            $ins->bind_param('ii', $supervisorId, $officeId);
                            $ins->execute();
                        }
                    }
                    $ins->close();
                }
                $message = 'Office saved.';
                $message_type = 'success';
                $editOffice = null;
            }
        }
    }
}

$offices = [];
$res = $conn->query("
    SELECT o.*, c.name AS course_name, d.name AS department_name, COALESCE(supervisor_summary.supervisor_count, 0) AS supervisor_count, supervisor_summary.supervisor_names
    FROM offices o
    LEFT JOIN courses c ON c.id = o.course_id
    LEFT JOIN departments d ON d.id = o.department_id
    LEFT JOIN (
        SELECT so.office_id,
               COUNT(DISTINCT so.supervisor_id) AS supervisor_count,
               GROUP_CONCAT(DISTINCT TRIM(CONCAT(s.first_name, ' ', s.last_name)) ORDER BY s.first_name, s.last_name SEPARATOR ', ') AS supervisor_names
        FROM supervisor_offices so
        INNER JOIN supervisors s ON s.id = so.supervisor_id AND s.deleted_at IS NULL
        GROUP BY so.office_id
    ) supervisor_summary ON supervisor_summary.office_id = o.id
    WHERE o.deleted_at IS NULL
    ORDER BY o.name ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $offices[] = $row;
    }
}

$page_title = 'Offices';
include 'includes/header.php';
$selectedSupervisorIds = [];
if ($editOffice) {
    $stmt = $conn->prepare('SELECT supervisor_id FROM supervisor_offices WHERE office_id = ?');
    if ($stmt) {
        $editId = (int)$editOffice['id'];
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $selectedSupervisorIds[] = (int)$row['supervisor_id'];
        }
        $stmt->close();
    }
}
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title"><h5 class="m-b-10">Offices</h5></div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Offices</li>
                </ul>
            </div>
        </div>
        <div class="main-content">
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php endif; ?>
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card stretch stretch-full">
                        <div class="card-header"><h5 class="card-title mb-0"><?php echo $editOffice ? 'Edit Office' : 'Create Office'; ?></h5></div>
                        <div class="card-body">
                            <div class="alert alert-info py-2">Departments are academic groups for coordinators. Offices are work locations assigned to supervisors and OJT students.</div>
                            <form method="post" class="row g-3" id="officeForm">
                                <input type="hidden" name="id" value="<?php echo (int)($editOffice['id'] ?? 0); ?>">
                                <div class="col-12"><label class="form-label">Office Name *</label><input type="text" name="name" class="form-control" value="<?php echo h($editOffice['name'] ?? ''); ?>" placeholder="ComLab 2" required></div>
                                <div class="col-12"><label class="form-label">Code</label><input type="text" name="code" class="form-control" value="<?php echo h($editOffice['code'] ?? ''); ?>" placeholder="COMLAB-2"></div>
                                <div class="col-12">
                                    <label class="form-label">Course</label>
                                    <select name="course_id" id="officeCourse" class="form-select">
                                        <option value="0">Any course</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo (int)$course['id']; ?>" <?php echo (int)($editOffice['course_id'] ?? 0) === (int)$course['id'] ? 'selected' : ''; ?>><?php echo h($course['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Department</label>
                                    <select name="department_id" id="officeDepartment" class="form-select">
                                        <option value="0">Any department</option>
                                        <?php foreach ($departments as $department): ?>
                                            <option value="<?php echo (int)$department['id']; ?>" <?php echo (int)($editOffice['department_id'] ?? 0) === (int)$department['id'] ? 'selected' : ''; ?>><?php echo h($department['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Supervisors</label>
                                    <select name="supervisor_ids[]" id="officeSupervisors" class="form-select" multiple size="6">
                                        <?php foreach ($supervisors as $supervisor): ?>
                                            <option value="<?php echo (int)$supervisor['id']; ?>"
                                                data-course-id="<?php echo (int)($supervisor['course_id'] ?? 0); ?>"
                                                data-department-id="<?php echo (int)($supervisor['department_id'] ?? 0); ?>"
                                                <?php echo in_array((int)$supervisor['id'], $selectedSupervisorIds, true) ? 'selected' : ''; ?>>
                                                <?php echo h($supervisor['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Filtered by course first, then department.</small>
                                </div>
                                <div class="col-12"><label class="form-label">Notes</label><textarea name="description" rows="3" class="form-control"><?php echo h($editOffice['description'] ?? ''); ?></textarea></div>
                                <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Save Office</button><?php if ($editOffice): ?><a class="btn btn-outline-secondary" href="offices.php">Cancel Edit</a><?php endif; ?></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card stretch stretch-full app-data-card app-data-toolbar app-academic-list-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">All Offices</h5>
                            <span class="badge bg-primary text-white px-3 py-1 fw-semibold"><?php echo count($offices); ?> total</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive app-data-table-wrap">
                                <table class="table table-hover mb-0 app-data-table app-academic-list-table">
                                    <thead><tr><th>Office</th><th>Course</th><th>Department</th><th>Supervisors</th><th>Actions</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($offices as $office): ?>
                                        <tr>
                                            <td><span class="app-academic-name"><?php echo h($office['name']); ?></span></td>
                                            <td><span class="app-academic-code-pill"><?php echo h($office['course_name'] ?: 'Any'); ?></span></td>
                                            <td><span class="app-academic-created"><?php echo h($office['department_name'] ?: 'Any'); ?></span></td>
                                            <td><span class="app-academic-created"><?php echo h($office['supervisor_names'] ?: '-'); ?></span></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                <a href="offices.php?edit=<?php echo (int)$office['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <form method="post" data-confirm-message="Delete this office?">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$office['id']; ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$offices): ?><tr><td colspan="5" class="text-center py-4 text-muted">No offices found.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var course = document.getElementById('officeCourse');
    var department = document.getElementById('officeDepartment');
    var supervisors = document.getElementById('officeSupervisors');
    if (!course || !department || !supervisors) return;
    function filterSupervisors() {
        var courseId = course.value || '0';
        var departmentId = department.value || '0';
        Array.prototype.forEach.call(supervisors.options, function (option) {
            var optionCourse = option.getAttribute('data-course-id') || '0';
            var optionDepartment = option.getAttribute('data-department-id') || '0';
            var courseMatches = courseId === '0' || optionCourse === courseId;
            var departmentMatches = departmentId === '0' || optionDepartment === departmentId;
            option.hidden = !(courseMatches && departmentMatches);
            if (option.hidden) option.selected = false;
        });
    }
    course.addEventListener('change', filterSupervisors);
    department.addEventListener('change', filterSupervisors);
    filterSupervisors();
});
</script>
<?php include 'includes/footer.php'; $conn->close(); ?>
