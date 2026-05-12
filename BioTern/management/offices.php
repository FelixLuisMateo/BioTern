<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/offices.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
/** @var mysqli $conn */

require_roles_page(['admin', 'coordinator', 'supervisor']);
biotern_offices_ensure_schema($conn);
$officeRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$canManageOffices = in_array($officeRole, ['admin', 'coordinator'], true);

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
    if (!$canManageOffices) {
        $message = 'You do not have permission to modify offices.';
        $message_type = 'danger';
    } elseif ($action === 'delete' && $id > 0) {
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

$selectedOfficeId = (int)($_GET['office'] ?? ($offices[0]['id'] ?? 0));
$selectedOffice = null;
foreach ($offices as $office) {
    if ((int)$office['id'] === $selectedOfficeId) {
        $selectedOffice = $office;
        break;
    }
}

$officeInternsById = [];
$officeCounts = [];
$internSql = "
    SELECT
        o.id AS office_id,
        s.id AS student_record_id,
        s.user_id,
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        COALESCE(NULLIF(u.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
        COALESCE(c.name, '') AS course_name,
        COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '') AS section_code,
        COALESCE(sec.name, '') AS section_name,
        COALESCE(i.status, 'ongoing') AS internship_status,
        COALESCE(i.start_date, '') AS start_date,
        COALESCE(i.required_hours, s.internal_total_hours, 0) AS required_hours,
        COALESCE(i.rendered_hours, GREATEST(COALESCE(s.internal_total_hours, 0) - COALESCE(s.internal_total_hours_remaining, 0), 0), 0) AS rendered_hours
    FROM internships i
    INNER JOIN offices o ON o.id = i.office_id AND o.deleted_at IS NULL
    INNER JOIN students s ON s.id = i.student_id AND s.deleted_at IS NULL
    LEFT JOIN users u ON u.id = s.user_id
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    INNER JOIN (
        SELECT student_id, MAX(id) AS latest_id
        FROM internships
        WHERE deleted_at IS NULL
          AND LOWER(COALESCE(type, 'internal')) = 'internal'
        GROUP BY student_id
    ) latest ON latest.latest_id = i.id
    WHERE i.deleted_at IS NULL
      AND LOWER(COALESCE(i.type, 'internal')) = 'internal'
    ORDER BY s.last_name ASC, s.first_name ASC
";
$internRes = $conn->query($internSql);
if ($internRes instanceof mysqli_result) {
    while ($row = $internRes->fetch_assoc()) {
        $officeId = (int)($row['office_id'] ?? 0);
        if ($officeId <= 0) {
            continue;
        }
        $displayName = trim(implode(' ', array_filter([
            (string)($row['first_name'] ?? ''),
            (string)($row['middle_name'] ?? ''),
            (string)($row['last_name'] ?? ''),
        ])));
        $row['display_name'] = $displayName !== '' ? $displayName : 'Unnamed Student';
        $row['section_label'] = biotern_format_section_label((string)($row['section_code'] ?? ''), (string)($row['section_name'] ?? ''));
        $row['profile_url'] = biotern_avatar_public_src((string)($row['profile_picture'] ?? ''), (int)($row['user_id'] ?? 0));
        $required = max(0, (float)($row['required_hours'] ?? 0));
        $rendered = max(0, (float)($row['rendered_hours'] ?? 0));
        $row['progress_pct'] = $required > 0 ? min(100, max(0, (int)round(($rendered / $required) * 100))) : 0;
        $officeInternsById[$officeId][] = $row;
        $officeCounts[$officeId] = ($officeCounts[$officeId] ?? 0) + 1;
    }
    $internRes->close();
}

foreach ($offices as &$office) {
    $office['student_count'] = $officeCounts[(int)$office['id']] ?? 0;
}
unset($office);
$selectedOfficeInterns = $selectedOffice ? ($officeInternsById[(int)$selectedOffice['id']] ?? []) : [];

$page_title = 'Offices';
$page_body_class = 'companies-page offices-page';
$page_styles = [
    'assets/css/modules/management/management-companies.css',
];
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
                <?php if ($canManageOffices): ?>
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
                <?php endif; ?>
                <div class="<?php echo $canManageOffices ? 'col-lg-8' : 'col-12'; ?>">
                    <div class="card stretch stretch-full app-data-card app-data-toolbar app-academic-list-card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">All Offices</h5>
                            <span class="badge bg-primary text-white px-3 py-1 fw-semibold"><?php echo count($offices); ?> total</span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive app-data-table-wrap">
                                <table class="table table-hover mb-0 app-data-table app-academic-list-table">
                                    <thead><tr><th>Office</th><th>Course</th><th>Department</th><th>Supervisors</th><th>Internal Students</th><th>Actions</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($offices as $office): ?>
                                        <tr>
                                            <td><a class="app-academic-name" href="offices.php?office=<?php echo (int)$office['id']; ?>"><?php echo h($office['name']); ?></a></td>
                                            <td><span class="app-academic-code-pill"><?php echo h($office['course_name'] ?: 'Any'); ?></span></td>
                                            <td><span class="app-academic-created"><?php echo h($office['department_name'] ?: 'Any'); ?></span></td>
                                            <td><span class="app-academic-created"><?php echo h($office['supervisor_names'] ?: '-'); ?></span></td>
                                            <td><span class="badge bg-primary text-white"><?php echo (int)($office['student_count'] ?? 0); ?></span></td>
                                            <td>
                                                <div class="d-flex gap-2">
                                                <?php if ($canManageOffices): ?>
                                                <a href="offices.php?edit=<?php echo (int)$office['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <form method="post" data-confirm-message="Delete this office?">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$office['id']; ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                                </form>
                                                <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (!$offices): ?><tr><td colspan="6" class="text-center py-4 text-muted">No offices found.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mt-3 companies-detail-card">
                <div class="card-body">
                    <?php if ($selectedOffice): ?>
                        <div class="companies-detail-header">
                            <div>
                                <h4 class="mb-1"><?php echo h((string)$selectedOffice['name']); ?></h4>
                                <p class="companies-detail-subtitle mb-0"><?php echo h(trim((string)($selectedOffice['description'] ?? '')) !== '' ? (string)$selectedOffice['description'] : 'Internal OJT office'); ?></p>
                            </div>
                            <span class="companies-intern-count"><?php echo count($selectedOfficeInterns); ?> internal student(s)</span>
                        </div>
                        <div class="companies-intern-list mt-3">
                            <?php if (!$selectedOfficeInterns): ?>
                                <div class="companies-empty-state companies-intern-empty-state">
                                    <h6>No internal students linked yet</h6>
                                    <p class="mb-0">Assign an internal internship office from the student or OJT edit flow to populate this list.</p>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($selectedOfficeInterns as $intern): ?>
                                <article class="companies-intern-item">
                                    <div class="companies-intern-primary">
                                        <div class="companies-intern-avatar">
                                            <?php if (!empty($intern['profile_url'])): ?>
                                                <img src="<?php echo h((string)$intern['profile_url']); ?>" alt="<?php echo h((string)$intern['display_name']); ?>">
                                            <?php else: ?>
                                                <span><?php echo h(strtoupper(substr((string)($intern['first_name'] ?? 'S'), 0, 1))); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="companies-intern-copy">
                                            <div class="companies-intern-name-row">
                                                <h6 class="mb-0"><?php echo h((string)$intern['display_name']); ?></h6>
                                                <span class="companies-status-pill is-primary"><?php echo h(ucfirst((string)($intern['internship_status'] ?? 'Ongoing'))); ?></span>
                                            </div>
                                            <p class="mb-0">
                                                <?php echo h((string)($intern['course_name'] ?? '-')); ?>
                                                <span>|</span>
                                                <?php echo h((string)($intern['section_label'] ?? '-')); ?>
                                                <span>|</span>
                                                Internal
                                            </p>
                                        </div>
                                    </div>
                                    <div class="companies-intern-meta">
                                        <div class="companies-intern-chip-row">
                                            <span class="companies-meta-chip"><?php echo h(trim((string)($intern['start_date'] ?? '')) !== '' ? date('M d, Y', strtotime((string)$intern['start_date'])) : 'Start date pending'); ?></span>
                                        </div>
                                        <div class="companies-progress-row">
                                            <div class="companies-progress-track">
                                                <span style="width: <?php echo (int)($intern['progress_pct'] ?? 0); ?>%"></span>
                                            </div>
                                            <div class="companies-progress-copy">
                                                <span><?php echo (int)($intern['rendered_hours'] ?? 0); ?> / <?php echo (int)($intern['required_hours'] ?? 0); ?> hrs</span>
                                                <strong><?php echo (int)($intern['progress_pct'] ?? 0); ?>%</strong>
                                            </div>
                                        </div>
                                        <div class="companies-intern-actions">
                                            <a href="students-view.php?id=<?php echo (int)($intern['student_record_id'] ?? 0); ?>" class="btn btn-sm btn-outline-primary">Student</a>
                                            <a href="ojt-view.php?id=<?php echo (int)($intern['student_record_id'] ?? 0); ?>" class="btn btn-sm btn-outline-secondary">OJT</a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="companies-empty-state">
                            <h6>No office selected</h6>
                            <p class="mb-0">Create or choose an office to view internal OJT students.</p>
                        </div>
                    <?php endif; ?>
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
