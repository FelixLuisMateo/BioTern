<?php
$host = '127.0.0.1';
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
$hasDeptDeletedAt = has_col($deptCols, 'deleted_at');

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
    $deptRes = $conn->query($deptSql);
    if ($deptRes) {
        while ($row = $deptRes->fetch_assoc()) {
            $departments[] = $row;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $name = trim((string)($_POST['name'] ?? ''));
    $course_id = (int)($_POST['course_id'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    $status_text = strtolower(trim((string)($_POST['status'] ?? 'active')));
    $status_flag = ($status_text === 'inactive') ? 0 : 1;

    if ($code === '' || $name === '' || $course_id <= 0) {
        $message = 'Section code, name, and course are required.';
        $message_type = 'danger';
    } elseif ($hasSectionDepartment && $department_id <= 0) {
        $message = 'Department is required.';
        $message_type = 'danger';
    } else {
        $dupSql = "SELECT id FROM sections WHERE code = ?";
        if ($hasSectionDeletedAt) {
            $dupSql .= " AND deleted_at IS NULL";
        }
        $dupSql .= " LIMIT 1";
        $dupStmt = $conn->prepare($dupSql);
        $exists = false;
        if ($dupStmt) {
            $dupStmt->bind_param('s', $code);
            $dupStmt->execute();
            $exists = (bool)$dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();
        }

        if ($exists) {
            $message = 'Section code already exists.';
            $message_type = 'warning';
        } else {
            $columns = ['code', 'name', 'course_id'];
            $values = ["'" . $conn->real_escape_string($code) . "'", "'" . $conn->real_escape_string($name) . "'", (string)$course_id];

            if ($hasSectionDepartment) {
                $columns[] = 'department_id';
                $values[] = (string)$department_id;
            }

            if ($hasSectionStatus) {
                $columns[] = 'status';
                $values[] = (string)$status_flag;
            } elseif ($hasSectionIsActive) {
                $columns[] = 'is_active';
                $values[] = (string)$status_flag;
            }

            if ($hasSectionCreatedAt) {
                $columns[] = 'created_at';
                $values[] = 'NOW()';
            }
            if ($hasSectionUpdatedAt) {
                $columns[] = 'updated_at';
                $values[] = 'NOW()';
            }

            $insertSql = "INSERT INTO sections (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
            if ($conn->query($insertSql)) {
                header('Location: sections.php');
                exit;
            }
            $message = 'Failed to create section: ' . $conn->error;
            $message_type = 'danger';
        }
    }
}

$page_title = 'Create Section';
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
            <h5 class="m-b-10">Create Section</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="sections.php">Sections</a></li>
            <li class="breadcrumb-item">Create</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="sections.php" class="btn btn-outline-secondary">Back to List</a>
    </div>
</div>

<div class="main-content">
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title mb-0">Section Form</h5>
        </div>
        <div class="card-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <form method="post" action="">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Section Code *</label>
                        <input type="text" name="code" class="form-control" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Section Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int)$course['id']; ?>">
                                    <?php echo htmlspecialchars((string)($course['code'] ?: $course['name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($hasSectionDepartment): ?>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo (int)$dept['id']; ?>">
                                        <?php echo htmlspecialchars((string)($dept['code'] ?: $dept['name'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 create-form-actions">
                    <button type="submit" class="btn btn-primary">Create Section</button>
                    <a href="sections.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
include 'includes/footer.php';
$conn->close();
?>
