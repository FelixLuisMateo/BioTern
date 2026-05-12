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
      AND LOWER(COALESCE(i.status, 'ongoing')) = 'ongoing'
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
if ($selectedOffice) {
    $selectedOffice['student_count'] = $officeCounts[(int)$selectedOffice['id']] ?? 0;
}
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
        <div class="page-header" data-phc-condensed="1">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title"><h5 class="m-b-10">Offices</h5></div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Academic</li>
                    <li class="breadcrumb-item">Offices</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto companies-page-header-actions">
                <?php if ($canManageOffices): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#officeEditorModal">
                        <i class="feather-plus me-2"></i>
                        <span>Add Office</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="main-content">
            <?php if ($message !== ''): ?><div class="alert alert-<?php echo h($message_type); ?>"><?php echo h($message); ?></div><?php endif; ?>
            <div class="companies-layout">
                <aside class="companies-list-panel">
                    <div class="companies-list-panel-head">
                        <h6 class="mb-1">All Offices</h6>
                        <p class="mb-0"><?php echo count($offices); ?> result(s)</p>
                    </div>
                    <?php if ($offices === []): ?>
                        <div class="companies-empty-state companies-list-empty-state">
                            <div class="companies-empty-icon"><i class="feather-map-pin"></i></div>
                            <h6>No offices found</h6>
                            <p class="mb-0">Create offices for internal OJT deployment.</p>
                        </div>
                    <?php else: ?>
                        <div class="companies-list-scroll">
                            <?php foreach ($offices as $office): ?>
                                <?php
                                $isActive = $selectedOffice && (int)$selectedOffice['id'] === (int)$office['id'];
                                $officeHref = 'offices.php?office=' . (int)$office['id'];
                                ?>
                                <a href="<?php echo h($officeHref); ?>" class="companies-list-item<?php echo $isActive ? ' is-active' : ''; ?>">
                                    <div class="companies-list-item-main">
                                        <div class="companies-list-thumb">
                                            <span><?php echo h(strtoupper(substr((string)$office['name'], 0, 2))); ?></span>
                                        </div>
                                        <div class="companies-list-item-copy">
                                            <h6 class="mb-1"><?php echo h($office['name']); ?></h6>
                                            <p class="mb-0"><?php echo h($office['supervisor_names'] ?: 'No supervisor assigned yet'); ?></p>
                                        </div>
                                        <span class="companies-list-count"><?php echo (int)($office['student_count'] ?? 0); ?></span>
                                    </div>
                                    <div class="companies-list-meta">
                                        <span><?php echo (int)($office['student_count'] ?? 0); ?> ongoing</span>
                                        <span><?php echo h($office['department_name'] ?: ($office['course_name'] ?: 'Any department')); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </aside>

                <section class="companies-detail-panel">
                    <?php if (!$selectedOffice): ?>
                        <div class="companies-empty-state">
                            <div class="companies-empty-icon"><i class="feather-map-pin"></i></div>
                            <h6>Select an office</h6>
                            <p class="mb-0">Choose an office from the list to view supervisor information and internal OJT students.</p>
                        </div>
                    <?php else: ?>
                        <div class="companies-detail-head">
                            <div class="companies-detail-primary">
                                <div class="companies-detail-thumb">
                                    <span><?php echo h(strtoupper(substr((string)$selectedOffice['name'], 0, 2))); ?></span>
                                </div>
                                <div>
                                    <h4 class="companies-detail-title mb-1"><?php echo h((string)$selectedOffice['name']); ?></h4>
                                    <p class="companies-detail-subtitle mb-0"><?php echo h(trim((string)($selectedOffice['description'] ?? '')) !== '' ? (string)$selectedOffice['description'] : 'Internal OJT office'); ?></p>
                                </div>
                            </div>
                            <div class="companies-detail-actions">
                                <?php if ($canManageOffices): ?>
                                    <a href="offices.php?edit=<?php echo (int)$selectedOffice['id']; ?>&office=<?php echo (int)$selectedOffice['id']; ?>" class="btn btn-outline-primary">
                                        <i class="feather-edit-2 me-2"></i>
                                        <span>Edit Office</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-3 companies-detail-grid">
                            <div class="col-12 col-lg-4">
                                <article class="companies-info-card h-100">
                                    <h6 class="mb-3">Office Information</h6>
                                    <dl class="companies-info-list mb-0">
                                        <div>
                                            <dt>Office</dt>
                                            <dd><?php echo h((string)$selectedOffice['name']); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Code</dt>
                                            <dd><?php echo h(trim((string)($selectedOffice['code'] ?? '')) !== '' ? (string)$selectedOffice['code'] : 'Not provided'); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Course</dt>
                                            <dd><?php echo h((string)($selectedOffice['course_name'] ?: 'Any course')); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Department</dt>
                                            <dd><?php echo h((string)($selectedOffice['department_name'] ?: 'Any department')); ?></dd>
                                        </div>
                                    </dl>
                                    <hr>
                                    <h6 class="mb-3">Supervisor Information</h6>
                                    <dl class="companies-info-list mb-0">
                                        <div>
                                            <dt>Supervisor</dt>
                                            <dd><?php echo h((string)($selectedOffice['supervisor_names'] ?: 'Not assigned')); ?></dd>
                                        </div>
                                        <div>
                                            <dt>Assigned Students</dt>
                                            <dd><?php echo (int)($selectedOffice['student_count'] ?? 0); ?> ongoing internal student(s)</dd>
                                        </div>
                                    </dl>
                                </article>
                            </div>
                            <div class="col-12 col-lg-8">
                                <article class="companies-intern-card h-100">
                                    <div class="companies-intern-card-head">
                                        <div>
                                            <h6 class="mb-1">Internal Students</h6>
                                            <p class="mb-0">Students whose latest ongoing internal OJT record is assigned to this office.</p>
                                        </div>
                                        <span class="companies-intern-count"><?php echo count($selectedOfficeInterns); ?> student(s)</span>
                                    </div>

                                    <?php if ($selectedOfficeInterns === []): ?>
                                        <div class="companies-empty-state companies-intern-empty-state">
                                            <div class="companies-empty-icon"><i class="feather-users"></i></div>
                                            <h6>No ongoing internal students linked yet</h6>
                                            <p class="mb-0">Assign this office from the student or OJT edit flow to populate this list.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="companies-intern-list">
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
                                                                <span class="companies-status-pill is-primary">Ongoing</span>
                                                            </div>
                                                            <p class="mb-0">
                                                                <?php echo h((string)($intern['course_name'] ?? '-')); ?>
                                                                <span class="companies-dot">|</span>
                                                                <?php echo h((string)($intern['section_label'] ?? '-')); ?>
                                                                <span class="companies-dot">|</span>
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
                                    <?php endif; ?>
                                </article>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
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
