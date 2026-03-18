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

// set title for header include
$page_title = 'Departments';

	include 'includes/header.php';
?>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Departments</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
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
            <span class="badge bg-primary text-white px-3 py-1" style="font-weight:600;"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo count($departments); ?> total</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Code</th>
                            <th>Name</th>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('department_head')): ?><th>Department Head</th><?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('contact_email')): ?><th>Contact Email</th><?php
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
if (!empty($departments)): ?>
                        <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($departments as $dept): ?>
                            <tr>
                                <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$dept['id']; ?></td>
                                <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($dept['code'] ?? '')); ?></td>
                                <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($dept['name'] ?? '')); ?></td>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('department_head')): ?>
                                    <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($dept['department_head'] ?? '-')); ?></td>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('contact_email')): ?>
                                    <td><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($dept['contact_email'] ?? '-')); ?></td>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasColumn('is_active')): ?>
                                    <td>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
if ((string)($dept['is_active'] ?? '0') === '1'): ?>
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
echo htmlspecialchars((string)($dept['created_at'] ?? '-')); ?></td>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                <td>
                                    <a href="departments-edit.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$dept['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        Edit
                                    </a>
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
echo 4 + ($hasColumn('department_head') ? 1 : 0) + ($hasColumn('contact_email') ? 1 : 0) + ($hasColumn('is_active') ? 1 : 0) + ($hasColumn('created_at') ? 1 : 0); ?>" class="text-center py-4 text-muted">No departments found.</td>
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


