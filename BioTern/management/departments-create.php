<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */

$message = '';
$message_type = 'info';

$departmentColumns = [];
$columnResult = $conn->query("SHOW COLUMNS FROM departments");
if ($columnResult) {
    while ($column = $columnResult->fetch_assoc()) {
        $departmentColumns[] = strtolower((string)$column['Field']);
    }
}

$hasColumn = function ($columnName) use ($departmentColumns) {
    return in_array(strtolower($columnName), $departmentColumns, true);
};

$hasDeletedAt = $hasColumn('deleted_at');

if (!$hasColumn('location')) {
    @$conn->query("ALTER TABLE departments ADD COLUMN location VARCHAR(255) NULL AFTER code");
    $departmentColumns[] = 'location';
}

function bindDynamicParams(mysqli_stmt $stmt, string $types, array &$params): bool
{
    if ($types === '') {
        return true;
    }

    $refs = [$types];
    foreach ($params as $index => &$value) {
        $refs[] = &$value;
    }

    return (bool)call_user_func_array([$stmt, 'bind_param'], $refs);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $location = trim((string)($_POST['location'] ?? ''));
    $department_head = trim((string)($_POST['department_head'] ?? ''));

    if ($name === '' || $code === '') {
        $message = 'Department name and code are required.';
        $message_type = 'danger';
    } else {
        $checkQuery = "SELECT id FROM departments WHERE code = ?" . ($hasDeletedAt ? " AND deleted_at IS NULL" : "") . " LIMIT 1";
        $check_stmt = $conn->prepare($checkQuery);
        $check_stmt->bind_param("s", $code);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($exists) {
            $message = 'Department code already exists.';
            $message_type = 'warning';
        } else {
            $columns = ['name', 'code'];
            $placeholders = ['?', '?'];
            $types = 'ss';
            $params = [$name, $code];

            if ($hasColumn('location')) {
                $columns[] = 'location';
                $placeholders[] = '?';
                $types .= 's';
                $params[] = $location;
            }
            if ($hasColumn('department_head')) {
                $columns[] = 'department_head';
                $placeholders[] = '?';
                $types .= 's';
                $params[] = $department_head;
            }
            if ($hasColumn('created_at')) {
                $columns[] = 'created_at';
                $placeholders[] = 'NOW()';
            }
            if ($hasColumn('updated_at')) {
                $columns[] = 'updated_at';
                $placeholders[] = 'NOW()';
            }

            $insertSql = "INSERT INTO departments (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $insert_stmt = $conn->prepare($insertSql);
            if (!$insert_stmt) {
                $message = 'Failed to prepare insert statement.';
                $message_type = 'danger';
            } else {
                bindDynamicParams($insert_stmt, $types, $params);
                try {
                    if ($insert_stmt->execute()) {
                        $message = 'Department created successfully.';
                        $message_type = 'success';
                    } else {
                        $message = 'Failed to create department: ' . $insert_stmt->error;
                        $message_type = 'danger';
                    }
                } catch (mysqli_sql_exception $e) {
                    if ((int)$e->getCode() === 1062) {
                        $message = 'Department code already exists. The department may have been saved already.';
                        $message_type = 'warning';
                    } else {
                        $message = 'Failed to create department: ' . $e->getMessage();
                        $message_type = 'danger';
                    }
                }
                $insert_stmt->close();
            }
        }
    }
}

$departments = [];
$selectFields = ['id', 'name', 'code'];
if ($hasColumn('location')) {
    $selectFields[] = 'location';
}
if ($hasColumn('department_head')) {
    $selectFields[] = 'department_head';
}
if ($hasColumn('created_at')) {
    $selectFields[] = 'created_at';
}

$whereClause = $hasDeletedAt ? ' WHERE deleted_at IS NULL' : '';
$orderBy = $hasColumn('created_at') ? 'created_at DESC' : 'id DESC';
$listSql = "SELECT " . implode(', ', $selectFields) . " FROM departments" . $whereClause . " ORDER BY " . $orderBy . " LIMIT 50";
$list_result = $conn->query($listSql);
if ($list_result) {
    while ($row = $list_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

$page_title = 'Create Department';
$page_styles = ['assets/css/modules/management/management-create-shared.css'];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Create Department</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item">Departments</li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="row">
        <div class="col-lg-5">
            <div class="card stretch stretch-full">
                <div class="card-header">
                    <h5 class="card-title mb-0">Department Form</h5>
                </div>
                <div class="card-body">
                    <?php if ($message !== ''): ?>
                        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="">
                        <div class="mb-3">
                            <label class="form-label">Department Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="Information Technology" required value="<?php echo htmlspecialchars((string)($_POST['name'] ?? '')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Code *</label>
                            <input type="text" name="code" class="form-control" placeholder="DEPT-IT" required value="<?php echo htmlspecialchars((string)($_POST['code'] ?? '')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" placeholder="Main Building, 2nd Floor" value="<?php echo htmlspecialchars((string)($_POST['location'] ?? '')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Head</label>
                            <input type="text" name="department_head" class="form-control" placeholder="Dr. Juan Santos" value="<?php echo htmlspecialchars((string)($_POST['department_head'] ?? '')); ?>">
                        </div>
                        <div class="create-form-actions app-form-actions">
                            <button type="submit" class="btn btn-primary">Save Department</button>
                            <a href="departments.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card stretch stretch-full">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Existing Departments</h5>
                    <a href="auth-register.php" class="btn btn-sm btn-outline-primary">Open Registration</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Location</th>
                                    <th>Head</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($departments)): ?>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo (int)$dept['id']; ?></td>
                                        <td><?php echo htmlspecialchars((string)$dept['name']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$dept['code']); ?></td>
                                        <td><?php echo htmlspecialchars((string)($dept['location'] ?? '-')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($dept['department_head'] ?? '-')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No departments found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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





