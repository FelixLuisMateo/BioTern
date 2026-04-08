<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */

$message = '';
$message_type = 'info';

if (isset($_GET['saved']) && (string)$_GET['saved'] === '1') {
    $message = 'Course created successfully.';
    $message_type = 'success';
}

if (isset($_GET['duplicate']) && (string)$_GET['duplicate'] === '1') {
    $message = 'Course code already exists. The course may have been saved already.';
    $message_type = 'warning';
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

$hasCourseHead = $hasColumn('course_head');
$hasDeletedAt = $hasColumn('deleted_at');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $course_head = trim((string)($_POST['course_head'] ?? ''));

    if ($name === '' || $code === '') {
        $message = 'Course name and code are required.';
        $message_type = 'danger';
    } elseif ($hasCourseHead && $course_head === '') {
        $message = 'Course head is required for this database schema.';
        $message_type = 'danger';
    } else {
        $checkQuery = "SELECT id FROM courses WHERE code = ?" . ($hasDeletedAt ? " AND deleted_at IS NULL" : "") . " LIMIT 1";
        $checkStmt = $conn->prepare($checkQuery);
        if ($checkStmt) {
            $checkStmt->bind_param("s", $code);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if ($exists) {
                $message = 'Course code already exists.';
                $message_type = 'warning';
            } else {
                if ($hasCourseHead) {
                    $insertQuery = "INSERT INTO courses (name, code, course_head, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
                    $insertStmt = $conn->prepare($insertQuery);
                    if ($insertStmt) {
                        $insertStmt->bind_param("sss", $name, $code, $course_head);
                    }
                } else {
                    $insertQuery = "INSERT INTO courses (name, code, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
                    $insertStmt = $conn->prepare($insertQuery);
                    if ($insertStmt) {
                        $insertStmt->bind_param("ss", $name, $code);
                    }
                }

                if (!isset($insertStmt) || !$insertStmt) {
                    $message = 'Failed to prepare insert statement.';
                    $message_type = 'danger';
                } else {
                    try {
                        if ($insertStmt->execute()) {
                            $insertStmt->close();
                            header('Location: courses-create.php?saved=1');
                            exit;
                        }
                        $message = 'Failed to create course: ' . $insertStmt->error;
                        $message_type = 'danger';
                    } catch (mysqli_sql_exception $e) {
                        if ((int)$e->getCode() === 1062) {
                            $insertStmt->close();
                            header('Location: courses-create.php?duplicate=1');
                            exit;
                        }
                        $message = 'Failed to create course: ' . $e->getMessage();
                        $message_type = 'danger';
                    }
                }

                if (isset($insertStmt) && $insertStmt) {
                    $insertStmt->close();
                }
            }
        } else {
            $message = 'Failed to prepare duplicate-check statement.';
            $message_type = 'danger';
        }
    }
}

$selectFields = ['id', 'name', 'code'];
if ($hasColumn('course_head')) {
    $selectFields[] = 'course_head';
}
if ($hasColumn('created_at')) {
    $selectFields[] = 'created_at';
}

$whereClause = $hasDeletedAt ? " WHERE deleted_at IS NULL" : "";
$orderBy = $hasColumn('created_at') ? "created_at DESC" : "id DESC";

$courses = [];
$listQuery = "SELECT " . implode(', ', $selectFields) . " FROM courses" . $whereClause . " ORDER BY " . $orderBy . " LIMIT 50";
$listResult = $conn->query($listQuery);
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $courses[] = $row;
    }
}

$page_title = 'Create Course';
$page_styles = ['assets/css/modules/management/management-create-shared.css'];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Create Course</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="courses.php">Courses</a></li>
            <li class="breadcrumb-item">Create</li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="row">
        <div class="col-lg-5">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title mb-0">Course Form</h5>
                </div>
                <div class="card-body">
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="">
                        <div class="mb-3">
                            <label class="form-label">Course Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="Bachelor of Science in Accountancy" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Course Code *</label>
                            <input type="text" name="code" class="form-control" placeholder="ACT" required>
                        </div>
                        <?php if ($hasCourseHead): ?>
                            <div class="mb-3">
                                <label class="form-label">Course Head *</label>
                                <input type="text" name="course_head" class="form-control" placeholder="Prof. Juan Dela Cruz" required>
                            </div>
                        <?php endif; ?>
                        <div class="create-form-actions app-form-actions">
                            <button type="submit" class="btn btn-primary">Save Course</button>
                            <a href="courses.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card stretch stretch-full">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recent Courses</h5>
                    <a href="auth-register.php" class="btn btn-sm btn-outline-primary">Open Registration</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <?php if ($hasCourseHead): ?><th>Course Head</th><?php endif; ?>
                                    <?php if ($hasColumn('created_at')): ?><th>Created</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($courses)): ?>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td><?php echo (int)$course['id']; ?></td>
                                        <td><?php echo htmlspecialchars((string)$course['code']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$course['name']); ?></td>
                                        <?php if ($hasCourseHead): ?>
                                            <td><?php echo htmlspecialchars((string)($course['course_head'] ?? '-')); ?></td>
                                        <?php endif; ?>
                                        <?php if ($hasColumn('created_at')): ?>
                                            <td><?php echo htmlspecialchars((string)($course['created_at'] ?? '-')); ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No courses found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="courses.php" class="btn btn-outline-secondary">View All</a>
                </div>
            </div>
        </div>
    </div>
</div>
</div> <!-- .nxl-content -->
</main>
<?php
include 'includes/footer.php';
$conn->close();
?>





