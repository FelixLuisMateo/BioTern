<?php
include 'filter.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));

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

$courseColumns = [];
$columnResult = $conn->query("SHOW COLUMNS FROM courses");
if ($columnResult) {
    while ($column = $columnResult->fetch_assoc()) {
        $courseColumns[] = strtolower((string)$column['Field']);
    }
}

$hasColumn = function ($columnName) use ($courseColumns) {
    return in_array(strtolower($columnName), $courseColumns, true);
};

$flash_message = (string)($_SESSION['courses_flash_message'] ?? '');
$flash_type = (string)($_SESSION['courses_flash_type'] ?? 'success');
unset($_SESSION['courses_flash_message'], $_SESSION['courses_flash_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_course') {
    $course_id = (int)($_POST['course_id'] ?? 0);

    if (!in_array($current_role, ['admin'], true)) {
        $_SESSION['courses_flash_message'] = 'Only admin can delete courses.';
        $_SESSION['courses_flash_type'] = 'danger';
        header('Location: courses.php');
        exit;
    }

    if ($course_id <= 0) {
        $_SESSION['courses_flash_message'] = 'Invalid course id.';
        $_SESSION['courses_flash_type'] = 'danger';
        header('Location: courses.php');
        exit;
    }

    $deleted = false;
    if ($hasColumn('deleted_at')) {
        $stmt_del = $conn->prepare('UPDATE courses SET deleted_at = NOW() WHERE id = ? LIMIT 1');
        if ($stmt_del) {
            $stmt_del->bind_param('i', $course_id);
            $deleted = $stmt_del->execute() && $stmt_del->affected_rows > 0;
            $stmt_del->close();
        }
    } else {
        $stmt_del = $conn->prepare('DELETE FROM courses WHERE id = ? LIMIT 1');
        if ($stmt_del) {
            $deleted = false;
            if ($stmt_del->bind_param('i', $course_id)) {
                $deleted = $stmt_del->execute() && $stmt_del->affected_rows > 0;
            }
            $stmt_del->close();
        }
    }

    $_SESSION['courses_flash_message'] = $deleted ? 'Course deleted successfully.' : 'Unable to delete course (it may be in use).';
    $_SESSION['courses_flash_type'] = $deleted ? 'success' : 'danger';
    header('Location: courses.php');
    exit;
}

$selectFields = ['id', 'name', 'code'];
if ($hasColumn('course_head')) {
    $selectFields[] = 'course_head';
}
if ($hasColumn('description')) {
    $selectFields[] = 'description';
}
if ($hasColumn('total_ojt_hours')) {
    $selectFields[] = 'total_ojt_hours';
}
if ($hasColumn('internal_hours')) {
    $selectFields[] = 'internal_hours';
}
if ($hasColumn('external_hours')) {
    $selectFields[] = 'external_hours';
}
if ($hasColumn('school_year')) {
    $selectFields[] = 'school_year';
}
if ($hasColumn('is_active')) {
    $selectFields[] = 'is_active';
}
if ($hasColumn('created_at')) {
    $selectFields[] = 'created_at';
}

$where = [];
if ($hasColumn('deleted_at')) {
    $where[] = "deleted_at IS NULL";
}

if ($current_role === 'coordinator' && $current_user_id > 0) {
    $has_coord_courses = false;
    $coord_course_tbl_res = $conn->query("SHOW TABLES LIKE 'coordinator_courses'");
    if ($coord_course_tbl_res && $coord_course_tbl_res->num_rows > 0) {
        $has_coord_courses = true;
    }

    if ($has_coord_courses) {
        $where[] = "id IN (SELECT course_id FROM coordinator_courses WHERE coordinator_user_id = " . $current_user_id . ")";
    } elseif ($hasColumn('coordinator_id')) {
        $where[] = "coordinator_id = " . $current_user_id;
    } else {
        $coordCols = [];
        $coordRes = $conn->query("SHOW COLUMNS FROM coordinators");
        if ($coordRes) {
            while ($coordCol = $coordRes->fetch_assoc()) {
                $coordCols[] = strtolower((string)$coordCol['Field']);
            }
        }
        if (in_array('course_id', $coordCols, true) && in_array('user_id', $coordCols, true)) {
            $where[] = "id IN (SELECT course_id FROM coordinators WHERE user_id = " . $current_user_id . ")";
        }
    }
}

$whereClause = count($where) ? (" WHERE " . implode(' AND ', $where)) : "";
$orderBy = $hasColumn('created_at') ? "created_at DESC" : "id DESC";

$courses = [];
$listQuery = "SELECT " . implode(', ', $selectFields) . " FROM courses" . $whereClause . " ORDER BY " . $orderBy . " LIMIT 200";
$listResult = $conn->query($listQuery);
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $courses[] = $row;
    }
}

$colCount = count($selectFields) + 1; // +1 for Actions column

$page_title = 'Courses';
include 'includes/header.php';
?>
<link rel="stylesheet" type="text/css" href="assets/css/management-courses-page.css">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Courses</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item">Courses</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="courses-create.php" class="btn btn-primary">Create Course</a>
    </div>
</div>

<div class="main-content">
    <?php if ($flash_message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?> mb-3"><?php echo htmlspecialchars($flash_message); ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Courses</h5>
            <span class="badge bg-primary text-white px-3 py-1 fw-semibold"><?php echo count($courses); ?> total</span>
        </div>
        <div class="card-body p-0 pb-3">
            <div class="table-responsive">
                <table class="table table-hover mb-0 courses-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <?php if ($hasColumn('course_head')): ?><th>Course Head</th><?php endif; ?>
                            <?php if ($hasColumn('total_ojt_hours')): ?><th>Total OJT Hours</th><?php endif; ?>
                            <?php if ($hasColumn('internal_hours')): ?><th>Internal Hours</th><?php endif; ?>
                            <?php if ($hasColumn('external_hours')): ?><th>External Hours</th><?php endif; ?>
                            <?php if ($hasColumn('school_year')): ?><th>School Year</th><?php endif; ?>
                            <?php if ($hasColumn('is_active')): ?><th>Status</th><?php endif; ?>
                            <?php if ($hasColumn('created_at')): ?><th>Created</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($courses)): ?>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><?php echo (int)$course['id']; ?></td>
                                <td><?php echo htmlspecialchars((string)($course['code'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string)($course['name'] ?? '')); ?></td>
                                <?php if ($hasColumn('course_head')): ?>
                                    <td><?php echo htmlspecialchars((string)($course['course_head'] ?? '-')); ?></td>
                                <?php endif; ?>
                                <?php if ($hasColumn('total_ojt_hours')): ?>
                                    <td><?php echo htmlspecialchars((string)($course['total_ojt_hours'] ?? '-')); ?></td>
                                <?php endif; ?>
                                <?php if ($hasColumn('internal_hours')): ?>
                                    <td><?php echo (int)($course['internal_hours'] ?? 0); ?></td>
                                <?php endif; ?>
                                <?php if ($hasColumn('external_hours')): ?>
                                    <td><?php echo (int)($course['external_hours'] ?? 0); ?></td>
                                <?php endif; ?>
                                <?php if ($hasColumn('school_year')): ?>
                                    <td><?php echo htmlspecialchars((string)($course['school_year'] ?? '-')); ?></td>
                                <?php endif; ?>
                                <?php if ($hasColumn('is_active')): ?>
                                    <td>
                                        <?php if ((string)($course['is_active'] ?? '0') === '1'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($hasColumn('created_at')): ?>
                                    <td><?php echo htmlspecialchars((string)($course['created_at'] ?? '-')); ?></td>
                                <?php endif; ?>
                                <td class="action-cell">
                                    <a href="courses-edit.php?id=<?php echo (int)$course['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <?php if ($current_role === 'admin'): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this course?');">
                                            <input type="hidden" name="action" value="delete_course">
                                            <input type="hidden" name="course_id" value="<?php echo (int)$course['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $colCount; ?>" class="text-center py-4 text-muted">No courses found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
include 'includes/footer.php';

