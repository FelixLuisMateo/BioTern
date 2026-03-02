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
    <div class="card stretch stretch-full">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Courses</h5>
            <span class="badge bg-primary text-white px-3 py-1" style="font-weight:600;"><?php echo count($courses); ?> total</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <?php if ($hasColumn('course_head')): ?><th>Course Head</th><?php endif; ?>
                            <?php if ($hasColumn('total_ojt_hours')): ?><th>Total OJT Hours</th><?php endif; ?>
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
                                <td>
                                    <a href="courses-edit.php?id=<?php echo (int)$course['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
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
