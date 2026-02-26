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

$deptColumns = [];
$columnResult = $conn->query("SHOW COLUMNS FROM departments");
if ($columnResult) {
    while ($column = $columnResult->fetch_assoc()) {
        $deptColumns[] = strtolower((string)$column['Field']);
    }
}

$hasColumn = function ($columnName) use ($deptColumns) {
    return in_array(strtolower($columnName), $deptColumns, true);
};

$selectFields = ['id', 'name', 'code'];
if ($hasColumn('department_head')) {
    $selectFields[] = 'department_head';
}
if ($hasColumn('contact_email')) {
    $selectFields[] = 'contact_email';
}
if ($hasColumn('is_active')) {
    $selectFields[] = 'is_active';
}
if ($hasColumn('created_at')) {
    $selectFields[] = 'created_at';
}

$whereClause = $hasColumn('deleted_at') ? " WHERE deleted_at IS NULL" : "";
$orderBy = $hasColumn('created_at') ? "created_at DESC" : "id DESC";

$departments = [];
$listQuery = "SELECT " . implode(', ', $selectFields) . " FROM departments" . $whereClause . " ORDER BY " . $orderBy . " LIMIT 200";
$listResult = $conn->query($listQuery);
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $departments[] = $row;
    }
}

ob_start();
?>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Departments</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item">Departments</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="departments-create.php" class="btn btn-primary">Create Department</a>
    </div>
</div>

<div class="main-content">
    <div class="card stretch stretch-full">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">All Departments</h5>
            <span class="badge bg-light text-dark"><?php echo count($departments); ?> total</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <?php if ($hasColumn('department_head')): ?><th>Department Head</th><?php endif; ?>
                            <?php if ($hasColumn('contact_email')): ?><th>Contact Email</th><?php endif; ?>
                            <?php if ($hasColumn('is_active')): ?><th>Status</th><?php endif; ?>
                            <?php if ($hasColumn('created_at')): ?><th>Created</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($departments)): ?>
                        <?php foreach ($departments as $dept): ?>
                            <tr>
                                <td><?php echo (int)$dept['id']; ?></td>
                                <td><?php echo htmlspecialchars((string)($dept['code'] ?? '')); ?></td>
                                <td><?php echo htmlspecialchars((string)($dept['name'] ?? '')); ?></td>
                                <?php if ($hasColumn('department_head')): ?>
                                    <td><?php echo htmlspecialchars((string)($dept['department_head'] ?? '-')); ?></td>
                                <?php endif; ?>
                                <?php if ($hasColumn('contact_email')): ?>
                                    <td><?php echo htmlspecialchars((string)($dept['contact_email'] ?? '-')); ?></td>
                                <?php endif; ?>
                                <?php if ($hasColumn('is_active')): ?>
                                    <td>
                                        <?php if ((string)($dept['is_active'] ?? '0') === '1'): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($hasColumn('created_at')): ?>
                                    <td><?php echo htmlspecialchars((string)($dept['created_at'] ?? '-')); ?></td>
                                <?php endif; ?>
                                <td>
                                    <a href="departments-edit.php?id=<?php echo (int)$dept['id']; ?>" class="btn btn-sm btn-light-brand">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo 4 + ($hasColumn('department_head') ? 1 : 0) + ($hasColumn('contact_email') ? 1 : 0) + ($hasColumn('is_active') ? 1 : 0) + ($hasColumn('created_at') ? 1 : 0); ?>" class="text-center py-4 text-muted">No departments found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
$template_page_content = ob_get_clean();
include 'template.php';
$conn->close();
?>
