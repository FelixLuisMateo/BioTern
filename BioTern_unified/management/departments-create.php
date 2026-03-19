<?php
require_once dirname(__DIR__) . '/config/db.php';
$host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : ''; 
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$db_port = defined('DB_PORT') ? (int)DB_PORT : 3306;

$message = '';
$message_type = 'info';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name, $db_port);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

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
    $department_head = trim((string)($_POST['department_head'] ?? ''));
    $contact_email = trim((string)($_POST['contact_email'] ?? ''));

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

            if ($hasColumn('department_head')) {
                $columns[] = 'department_head';
                $placeholders[] = '?';
                $types .= 's';
                $params[] = $department_head;
            }
            if ($hasColumn('contact_email')) {
                $columns[] = 'contact_email';
                $placeholders[] = '?';
                $types .= 's';
                $params[] = $contact_email;
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
if ($hasColumn('department_head')) {
    $selectFields[] = 'department_head';
}
if ($hasColumn('contact_email')) {
    $selectFields[] = 'contact_email';
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
include 'includes/header.php';
?>
<style>
    .create-form-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .create-form-actions .btn {
        width: auto !important;
        min-width: 140px;
        display: inline-flex;
        justify-content: center;
        align-items: center;
    }
</style>
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
                            <input type="text" name="name" class="form-control" placeholder="Information Technology" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Code *</label>
                            <input type="text" name="code" class="form-control" placeholder="DEPT-IT" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Head</label>
                            <input type="text" name="department_head" class="form-control" placeholder="Dr. Juan Santos">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Email</label>
                            <input type="email" name="contact_email" class="form-control" placeholder="it@biotern.com">
                        </div>
                        <div class="create-form-actions">
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
                    <a href="auth-register-creative.php" class="btn btn-sm btn-outline-primary">Open Registration</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Head</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($departments)): ?>
                                <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo (int)$dept['id']; ?></td>
                                        <td><?php echo htmlspecialchars((string)$dept['name']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$dept['code']); ?></td>
                                        <td><?php echo htmlspecialchars((string)($dept['department_head'] ?? '-')); ?></td>
                                        <td><?php echo htmlspecialchars((string)($dept['contact_email'] ?? '-')); ?></td>
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
<?php
require_once dirname(__DIR__) . '/config/db.php';
include 'includes/footer.php';
$conn->close();
?>

