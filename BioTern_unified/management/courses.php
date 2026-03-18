<?php
require_once dirname(__DIR__) . '/config/db.php';
$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : '';
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$db_port = defined('DB_PORT') ? (int)DB_PORT : 3306;

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name, $db_port);
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
if ($hasColumn('is_active')) {
    $selectFields[] = 'is_active';
}
if ($hasColumn('created_at')) {
    $selectFields[] = 'created_at';
}

$whereClause = $hasColumn('deleted_at') ? " WHERE deleted_at IS NULL" : "";
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
<style>
    /* Prevent last-row action button border from getting visually clipped. */
    .courses-table tbody tr:last-child td {
        padding-bottom: 14px;
    }

    .courses-table .action-cell .btn {
        line-height: 1.2;
    }
</style>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Courses</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item">Courses</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="courses-create.php" class="btn btn-primary">Create Course</a>
    </div>
</div>

<div class="main-content">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Courses</h5>
            <span class="badge bg-primary text-white px-3 py-1" style="font-weight:600;"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo count($courses); ?> total</span>
        </div>
        <div class="card-body p-0 pb-3">
            <div class="table-responsive">
                <table class="table table-hover mb-0 courses-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('course_head')): ?><th>Course Head</th><?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('total_ojt_hours')): ?><th>Total OJT Hours</th><?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('is_active')): ?><th>Status</th><?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('created_at')): ?><th>Created</th><?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
require_once dirname(__DIR__) . '/config/db.php';
if (!empty($courses)): ?>
                        <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($courses as $course): ?>
                            <tr>
                                <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$course['id']; ?></td>
                                <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($course['code'] ?? '')); ?></td>
                                <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($course['name'] ?? '')); ?></td>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('course_head')): ?>
                                    <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($course['course_head'] ?? '-')); ?></td>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('total_ojt_hours')): ?>
                                    <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($course['total_ojt_hours'] ?? '-')); ?></td>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('is_active')): ?>
                                    <td>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
if ((string)($course['is_active'] ?? '0') === '1'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                    </td>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('created_at')): ?>
                                    <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($course['created_at'] ?? '-')); ?></td>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                <td class="action-cell">
                                    <a href="courses-edit.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$course['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                        <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                    <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                        <tr>
                            <td colspan="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $colCount; ?>" class="text-center py-4 text-muted">No courses found.</td>
                        </tr>
                    <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
require_once dirname(__DIR__) . '/config/db.php';
include 'includes/footer.php';


