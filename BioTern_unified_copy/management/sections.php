<?php
$host = '127.0.0.1';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

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
include 'includes/header.php';
?>
<link rel="stylesheet" type="text/css" href="assets/css/management-sections-page.css">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Sections</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item">Sections</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto d-flex gap-2">
        <a href="sections-create.php" class="btn btn-primary">Create Section</a>
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
                            <?php $secLabel = trim((string)($secOpt['code'] ?? '')) !== '' ? (string)$secOpt['code'] : (string)($secOpt['name'] ?? ''); ?>
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

    <div class="card stretch stretch-full">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Sections</h5>
            <span class="badge bg-primary text-white px-3 py-1 text-center fw-semibold app-minw-72"><?php echo count($sections); ?> total</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
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
                                <td><?php echo (int)$sec['id']; ?></td>
                                <td><?php echo htmlspecialchars((string)($sec['code'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string)($sec['name'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string)($sec['course_name'] ?? '-')); ?></td>
                                <?php if ($hasSectionDepartment): ?><td><?php echo htmlspecialchars((string)($sec['department_name'] ?? '-')); ?></td><?php endif; ?>
                                <?php if ($hasSectionStatus || $hasSectionIsActive): ?>
                                    <td>
                                        <?php
                                        $activeFlag = $hasSectionStatus
                                            ? (string)($sec['status'] ?? '0')
                                            : (string)($sec['is_active'] ?? '0');
                                        ?>
                                        <?php if ($activeFlag === '1'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($hasSectionCreatedAt): ?><td><?php echo htmlspecialchars((string)($sec['created_at'] ?? '-')); ?></td><?php endif; ?>
                                <td>
                                    <a href="sections-edit.php?id=<?php echo (int)$sec['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
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
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script src="assets/js/management-sections-runtime.js"></script>

