<?php
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

$message = '';
$message_type = 'info';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
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
        $check_stmt = $conn->prepare("SELECT id FROM departments WHERE code = ? LIMIT 1");
        $check_stmt->bind_param("s", $code);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if ($exists) {
            $message = 'Department code already exists.';
            $message_type = 'warning';
        } else {
            $insert_stmt = $conn->prepare("
                INSERT INTO departments (name, code, department_head, contact_email, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $insert_stmt->bind_param("ssss", $name, $code, $department_head, $contact_email);
            if ($insert_stmt->execute()) {
                $message = 'Department created successfully.';
                $message_type = 'success';
            } else {
                $message = 'Failed to create department: ' . $insert_stmt->error;
                $message_type = 'danger';
            }
            $insert_stmt->close();
        }
    }
}

$departments = [];
$list_result = $conn->query("
    SELECT id, name, code, department_head, contact_email, created_at
    FROM departments
    WHERE deleted_at IS NULL
    ORDER BY id DESC
    LIMIT 50
");
if ($list_result) {
    while ($row = $list_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

$page_title = 'Create Department';
include 'includes/header.php';
?>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Create Department</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
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
                        <button type="submit" class="btn btn-primary">Save Department</button>
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
include 'includes/footer.php';
$conn->close();
?>
