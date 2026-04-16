<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
/** @var mysqli $conn */

function get_table_columns(mysqli $conn, string $table): array {
    $columns = [];
    $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $columns[] = strtolower((string)$row['Field']);
        }
    }
    return $columns;
}

function has_col(array $cols, string $name): bool {
    return in_array(strtolower($name), $cols, true);
}

function format_section_code(string $code): string {
    $value = trim($code);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\s*-\s*/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', (string)$value);
    $value = trim((string)$value);

    if (preg_match('/^(\d+[A-Za-z]*)\s+([A-Za-z][A-Za-z0-9]*)$/', $value, $matches)) {
        return strtoupper((string)$matches[2]) . ' ' . strtoupper((string)$matches[1]);
    }

    if (preg_match('/^([A-Za-z][A-Za-z0-9]*)\s+(\d+[A-Za-z]*)$/', $value, $matches)) {
        return strtoupper((string)$matches[1]) . ' ' . strtoupper((string)$matches[2]);
    }

    if (preg_match('/^([A-Za-z]+)([0-9]+[A-Za-z]*)$/', $value, $matches)) {
        return strtoupper($matches[1]) . ' ' . strtoupper($matches[2]);
    }

    return $value;
}

$sectionCols = get_table_columns($conn, 'sections');
$courseCols = get_table_columns($conn, 'courses');
$deptCols = get_table_columns($conn, 'departments');

$hasSectionDeletedAt = has_col($sectionCols, 'deleted_at');
$hasSectionDepartment = has_col($sectionCols, 'department_id');
$hasSectionIsActive = has_col($sectionCols, 'is_active');
$hasSectionStatus = has_col($sectionCols, 'status');
$hasSectionCreatedAt = has_col($sectionCols, 'created_at');
$hasSectionUpdatedAt = has_col($sectionCols, 'updated_at');

$hasCourseDeletedAt = has_col($courseCols, 'deleted_at');
$hasCourseDepartment = has_col($courseCols, 'department_id');

$hasDeptDeletedAt = has_col($deptCols, 'deleted_at');

$flash = null;

$filter_q = trim((string)($_GET['q'] ?? ''));
$filter_course = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
$filter_department = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$filter_section = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$filter_status = strtolower(trim((string)($_GET['status'] ?? '')));
if (!in_array($filter_status, ['', 'active', 'inactive'], true)) {
    $filter_status = '';
}

$courses = [];
$courseSql = "SELECT id, code, name FROM courses" . ($hasCourseDeletedAt ? " WHERE deleted_at IS NULL" : "") . " ORDER BY name ASC";
$courseRes = $conn->query($courseSql);
if ($courseRes) {
    while ($row = $courseRes->fetch_assoc()) {
        $courses[] = $row;
    }
}

$departments = [];
if ($hasSectionDepartment) {
    $deptSql = "SELECT id, code, name FROM departments" . ($hasDeptDeletedAt ? " WHERE deleted_at IS NULL" : "") . " ORDER BY name ASC";
    $deptListRes = $conn->query($deptSql);
    if ($deptListRes) {
        while ($row = $deptListRes->fetch_assoc()) {
            $departments[] = $row;
        }
    }
}

$sectionOptions = [];
$sectionOptionSql = "SELECT id, code, name, course_id";
if ($hasSectionDepartment) {
    $sectionOptionSql .= ", department_id";
}
$sectionOptionSql .= " FROM sections";
$optWhere = [];
if ($hasSectionDeletedAt) {
    $optWhere[] = "deleted_at IS NULL";
}
if ($filter_course > 0) {
    $optWhere[] = "course_id = " . $filter_course;
}
if ($hasSectionDepartment && $filter_department > 0) {
    $optWhere[] = "department_id = " . $filter_department;
}
if (!empty($optWhere)) {
    $sectionOptionSql .= " WHERE " . implode(' AND ', $optWhere);
}
$sectionOptionSql .= " ORDER BY code ASC, name ASC LIMIT 500";
$sectionOptRes = $conn->query($sectionOptionSql);
if ($sectionOptRes) {
    while ($row = $sectionOptRes->fetch_assoc()) {
        $sectionOptions[] = $row;
    }
}

$sectionFields = [
    's.id',
    's.code',
    's.name',
    's.course_id',
    'c.name AS course_name'
];
if ($hasSectionDepartment) {
    $sectionFields[] = 's.department_id';
    $sectionFields[] = 'd.name AS department_name';
}
if ($hasSectionStatus) {
    $sectionFields[] = 's.status';
} elseif ($hasSectionIsActive) {
    $sectionFields[] = 's.is_active';
}
if ($hasSectionCreatedAt) {
    $sectionFields[] = 's.created_at';
}

$where = [];
if ($hasSectionDeletedAt) {
    $where[] = "s.deleted_at IS NULL";
}
if ($filter_course > 0) {
    $where[] = "s.course_id = " . $filter_course;
}
if ($hasSectionDepartment && $filter_department > 0) {
    $where[] = "s.department_id = " . $filter_department;
}
if ($filter_section > 0) {
    $where[] = "s.id = " . $filter_section;
}
if ($filter_status !== '' && ($hasSectionStatus || $hasSectionIsActive)) {
    $statusCol = $hasSectionStatus ? 's.status' : 's.is_active';
    $where[] = $statusCol . " = " . ($filter_status === 'active' ? "1" : "0");
}
if ($filter_q !== '') {
    $esc = $conn->real_escape_string($filter_q);
    $qWhere = "(s.code LIKE '%{$esc}%' OR s.name LIKE '%{$esc}%' OR c.name LIKE '%{$esc}%'";
    if ($hasSectionDepartment) {
        $qWhere .= " OR d.name LIKE '%{$esc}%'";
    }
    $qWhere .= ")";
    $where[] = $qWhere;
}

$sectionSql = "SELECT " . implode(', ', $sectionFields) . " FROM sections s ";
$sectionSql .= "LEFT JOIN courses c ON s.course_id = c.id ";
if ($hasSectionDepartment) {
    $sectionSql .= "LEFT JOIN departments d ON s.department_id = d.id ";
}
if (!empty($where)) {
    $sectionSql .= "WHERE " . implode(' AND ', $where) . " ";
}
$sectionSql .= "ORDER BY s.id DESC LIMIT 300";

$sections = [];
$sectionRes = $conn->query($sectionSql);
if ($sectionRes) {
    while ($row = $sectionRes->fetch_assoc()) {
        $sections[] = $row;
    }
}

$page_title = 'Sections';
$page_styles = [
    'assets/css/modules/management/management-filters.css',
    'assets/css/modules/management/management-sections.css'
];
$page_scripts = [
    'assets/js/modules/management/management-sections-runtime.js'
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Sections</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item">Sections</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto d-flex gap-2">
        <a href="sections-create.php" class="btn btn-primary">
            <i class="feather-plus me-2"></i>
            <span>Create Section</span>
        </a>
    </div>
</div>

<div class="main-content">
    <?php if (!empty($flash)): ?>
        <div class="alert alert-info"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="filter-form row g-2 align-items-end" id="sectionsFilterForm">
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" id="filter-q" name="q" class="form-control" placeholder="Code / Name / Course" value="<?php echo htmlspecialchars($filter_q); ?>">
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <label class="form-label">Course</label>
                    <select id="filter-course" name="course_id" class="form-control">
                        <option value="0">-- All Courses --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo (int)$course['id']; ?>" <?php echo ($filter_course === (int)$course['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)($course['code'] ?: $course['name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <label class="form-label">Department</label>
                    <select id="filter-department" name="department_id" class="form-control" <?php echo !$hasSectionDepartment ? 'disabled' : ''; ?>>
                        <option value="0">-- All Departments --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo (int)$dept['id']; ?>" <?php echo ($filter_department === (int)$dept['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)($dept['code'] ?: $dept['name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <label class="form-label">Section</label>
                    <select id="filter-section" name="section_id" class="form-control">
                        <option value="0">-- All Sections --</option>
                        <?php foreach ($sectionOptions as $secOpt): ?>
                            <?php $secLabel = biotern_format_section_label((string)($secOpt['code'] ?? ''), (string)($secOpt['name'] ?? '')); ?>
                            <option value="<?php echo (int)$secOpt['id']; ?>" <?php echo ($filter_section === (int)$secOpt['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($secLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-xl-2 col-lg-4 col-md-6">
                    <label class="form-label">Status</label>
                    <select id="filter-status" name="status" class="form-control">
                        <option value="" <?php echo $filter_status === '' ? 'selected' : ''; ?>>-- Any Status --</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filter_status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-xl-1 col-lg-2 col-md-4 d-flex gap-2">
                    <a href="sections.php" class="btn btn-outline-secondary btn-sm px-3 py-1 w-100 app-fs-085">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card stretch stretch-full app-data-card app-data-toolbar app-academic-list-card app-mobile-inline-list-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Sections</h5>
            <span class="badge bg-primary text-white px-3 py-1 text-center fw-semibold app-minw-72"><?php echo count($sections); ?> total</span>
        </div>
        <div class="card-body p-0 pb-3">
            <div class="table-responsive app-data-table-wrap">
                <table class="table table-hover mb-0 app-data-table app-academic-list-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Course</th>
                            <?php if ($hasSectionDepartment): ?><th>Department</th><?php endif; ?>
                            <?php if ($hasSectionStatus || $hasSectionIsActive): ?><th>Status</th><?php endif; ?>
                            <?php if ($hasSectionCreatedAt): ?><th>Created</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($sections)): ?>
                        <?php foreach ($sections as $sec): ?>
                            <tr>
                                <td><span class="app-academic-id-pill"><?php echo (int)$sec['id']; ?></span></td>
                                <td><span class="app-academic-code-pill"><?php echo htmlspecialchars(format_section_code((string)($sec['code'] ?? ''))); ?></span></td>
                                <td>
                                    <div class="app-academic-name-cell">
                                        <span class="app-academic-name"><?php echo htmlspecialchars((string)($sec['name'] ?? '')); ?></span>
                                        <span class="app-academic-meta"><?php echo htmlspecialchars((string)($sec['course_name'] ?? '-')); ?></span>
                                    </div>
                                </td>
                                <td><span class="app-academic-head"><?php echo htmlspecialchars((string)($sec['course_name'] ?? '-')); ?></span></td>
                                <?php if ($hasSectionDepartment): ?><td><span class="app-academic-created"><?php echo htmlspecialchars((string)($sec['department_name'] ?? '-')); ?></span></td><?php endif; ?>
                                <?php if ($hasSectionStatus || $hasSectionIsActive): ?>
                                    <td>
                                        <?php
                                        $activeFlag = $hasSectionStatus
                                            ? (string)($sec['status'] ?? '0')
                                            : (string)($sec['is_active'] ?? '0');
                                        ?>
                                        <?php if ($activeFlag === '1'): ?>
                                            <span class="app-academic-status-pill is-active">Active</span>
                                        <?php else: ?>
                                            <span class="app-academic-status-pill is-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($hasSectionCreatedAt): ?><td><span class="app-academic-created"><?php echo htmlspecialchars((string)($sec['created_at'] ?? '-')); ?></span></td><?php endif; ?>
                                <td class="action-cell">
                                    <a href="sections-edit.php?id=<?php echo (int)$sec['id']; ?>" class="btn btn-sm btn-outline-primary app-academic-edit-btn">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo 5 + ($hasSectionDepartment ? 1 : 0) + (($hasSectionStatus || $hasSectionIsActive) ? 1 : 0) + ($hasSectionCreatedAt ? 1 : 0); ?>" class="text-center py-4 text-muted">
                                No sections found.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="app-mobile-list app-ojt-mobile-list">
                <?php if (!empty($sections)): ?>
                    <?php foreach ($sections as $sec): ?>
                        <details class="app-mobile-item app-ojt-mobile-item">
                            <summary class="app-mobile-summary app-ojt-mobile-summary">
                                <div class="app-mobile-summary-main app-ojt-mobile-summary-main">
                                    <div class="app-mobile-summary-text app-ojt-mobile-summary-text">
                                        <span class="app-mobile-name app-ojt-mobile-name"><?php echo htmlspecialchars((string)($sec['name'] ?? '')); ?></span>
                                        <span class="app-mobile-subtext app-ojt-mobile-subtext">Code: <?php echo htmlspecialchars(format_section_code((string)($sec['code'] ?? '-'))); ?></span>
                                    </div>
                                </div>
                                <?php
                                $summaryActive = $hasSectionStatus
                                    ? (string)($sec['status'] ?? '0') === '1'
                                    : ((string)($sec['is_active'] ?? '0') === '1');
                                ?>
                                <span class="app-ojt-mobile-status-dot <?php echo $summaryActive ? 'status-active' : 'status-review'; ?>" aria-hidden="true"></span>
                            </summary>
                            <div class="app-mobile-details app-ojt-mobile-details">
                                <div class="app-mobile-row app-ojt-mobile-row">
                                    <span class="app-mobile-label app-ojt-mobile-label">ID</span>
                                    <span class="app-mobile-value app-ojt-mobile-value"><?php echo (int)$sec['id']; ?></span>
                                </div>
                                <div class="app-mobile-row app-ojt-mobile-row">
                                    <span class="app-mobile-label app-ojt-mobile-label">Course</span>
                                    <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($sec['course_name'] ?? '-')); ?></span>
                                </div>
                                <?php if ($hasSectionDepartment): ?>
                                    <div class="app-mobile-row app-ojt-mobile-row">
                                        <span class="app-mobile-label app-ojt-mobile-label">Department</span>
                                        <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($sec['department_name'] ?? '-')); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasSectionStatus || $hasSectionIsActive): ?>
                                    <?php
                                    $activeFlag = $hasSectionStatus
                                        ? (string)($sec['status'] ?? '0')
                                        : (string)($sec['is_active'] ?? '0');
                                    ?>
                                    <div class="app-mobile-row app-ojt-mobile-row">
                                        <span class="app-mobile-label app-ojt-mobile-label">Status</span>
                                        <span class="app-mobile-value app-ojt-mobile-value">
                                            <?php if ($activeFlag === '1'): ?>
                                                <span class="app-academic-status-pill is-active">Active</span>
                                            <?php else: ?>
                                                <span class="app-academic-status-pill is-inactive">Inactive</span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasSectionCreatedAt): ?>
                                    <div class="app-mobile-row app-ojt-mobile-row">
                                        <span class="app-mobile-label app-ojt-mobile-label">Created</span>
                                        <span class="app-mobile-value app-ojt-mobile-value"><?php echo htmlspecialchars((string)($sec['created_at'] ?? '-')); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="app-mobile-row app-mobile-row-stack app-ojt-mobile-row app-ojt-mobile-row-stack">
                                    <span class="app-mobile-label app-ojt-mobile-label">Actions</span>
                                    <div class="app-ojt-mobile-actions">
                                        <a href="sections-edit.php?id=<?php echo (int)$sec['id']; ?>" class="btn btn-sm btn-outline-primary app-academic-edit-btn">Edit</a>
                                    </div>
                                </div>
                            </div>
                        </details>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="app-data-empty">No sections found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>







