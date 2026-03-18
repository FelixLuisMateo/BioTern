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
$hasSectionUpdatedAt = has_col($sectionCols, 'updated_at');

$hasCourseDeletedAt = has_col($courseCols, 'deleted_at');
$hasDeptDeletedAt = has_col($deptCols, 'deleted_at');

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    die('Invalid section id.');
}

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

$selectFields = ['id', 'name', 'code', 'course_id'];
if ($hasSectionDepartment) {
    $selectFields[] = 'department_id';
}
if ($hasSectionStatus) {
    $selectFields[] = 'status';
} elseif ($hasSectionIsActive) {
    $selectFields[] = 'is_active';
}

$whereSql = "id = ?";
if ($hasSectionDeletedAt) {
    $whereSql .= " AND deleted_at IS NULL";
}
$stmt = $conn->prepare("SELECT " . implode(', ', $selectFields) . " FROM sections WHERE " . $whereSql . " LIMIT 1");
$section = null;
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $section = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$section) {
    die('Section not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = strtoupper(trim((string)($_POST['name'] ?? '')));
    $code = strtoupper(trim((string)($_POST['code'] ?? '')));
    $course_id = (int)($_POST['course_id'] ?? 0);
    $department_id = (int)($_POST['department_id'] ?? 0);
    $status_text = strtolower(trim((string)($_POST['status'] ?? 'active')));
    $status_flag = ($status_text === 'inactive') ? 0 : 1;

    if (strpos($code, '-') !== false) {
        $parts = explode('-', $code, 2);
        $codePart = strtoupper(trim((string)($parts[0] ?? '')));
        $namePart = strtoupper(trim((string)($parts[1] ?? '')));
        if ($codePart !== '') {
            $code = $codePart;
        }
        if ($name === '' || $name === strtoupper(trim((string)($_POST['code'] ?? '')))) {
            $name = $namePart;
        }
    }
    if ($code !== '' && strpos($name, $code . '-') === 0) {
        $name = trim(substr($name, strlen($code) + 1));
    }

    if ($name === '' || $code === '' || $course_id <= 0) {
        $message = 'Section name, code, and course are required.';
        $message_type = 'danger';
    } elseif ($hasSectionDepartment && $department_id <= 0) {
        $message = 'Department is required.';
        $message_type = 'danger';
    } else {
        $dupSql = "SELECT id FROM sections WHERE code = ? AND name = ? AND id <> ?";
        if ($hasSectionDeletedAt) {
            $dupSql .= " AND deleted_at IS NULL";
        }
        $dupSql .= " LIMIT 1";
        $dupStmt = $conn->prepare($dupSql);
        $exists = false;
        if ($dupStmt) {
            $dupStmt->bind_param('ssi', $code, $name, $id);
            $dupStmt->execute();
            $exists = (bool)$dupStmt->get_result()->fetch_assoc();
            $dupStmt->close();
        }

        if ($exists) {
            $message = 'Section code + name already exists.';
            $message_type = 'warning';
        } else {
            $set = [
                "name = ?",
                "code = ?",
                "course_id = ?"
            ];
            $types = "ssi";
            $params = [$name, $code, $course_id];

            if ($hasSectionDepartment) {
                $set[] = "department_id = ?";
                $types .= "i";
                $params[] = $department_id;
            }

            if ($hasSectionStatus) {
                $set[] = "status = ?";
                $types .= "i";
                $params[] = $status_flag;
            } elseif ($hasSectionIsActive) {
                $set[] = "is_active = ?";
                $types .= "i";
                $params[] = $status_flag;
            }

            if ($hasSectionUpdatedAt) {
                $set[] = "updated_at = NOW()";
            }

            $updateSql = "UPDATE sections SET " . implode(', ', $set) . " WHERE id = ? LIMIT 1";
            $types .= "i";
            $params[] = $id;

            $updateStmt = $conn->prepare($updateSql);
            if (!$updateStmt) {
                $message = 'Failed to prepare update statement.';
                $message_type = 'danger';
            } else {
                $bindArgs = [];
                $bindArgs[] = &$types;
                foreach ($params as $k => $v) {
                    $bindArgs[] = &$params[$k];
                }
                call_user_func_array([$updateStmt, 'bind_param'], $bindArgs);
                if ($updateStmt->execute()) {
                    $message = 'Section updated successfully.';
                    $message_type = 'success';
                    $section['name'] = $name;
                    $section['code'] = $code;
                    $section['course_id'] = $course_id;
                    if ($hasSectionDepartment) {
                        $section['department_id'] = $department_id;
                    }
                    if ($hasSectionStatus) {
                        $section['status'] = $status_flag;
                    } elseif ($hasSectionIsActive) {
                        $section['is_active'] = $status_flag;
                    }
                } else {
                    $message = 'Failed to update section: ' . $updateStmt->error;
                    $message_type = 'danger';
                }
                $updateStmt->close();
            }
        }
    }
}

$activeValue = $hasSectionStatus
    ? (string)($section['status'] ?? '1')
    : (string)($section['is_active'] ?? '1');

$page_title = 'Edit Section';
include 'includes/header.php';
?>
<div class="page-header">
    <div class="page-header-left d-flex align-items-center">
        <div class="page-header-title">
            <h5 class="m-b-10">Edit Section</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
            <li class="breadcrumb-item"><a href="sections.php">Sections</a></li>
            <li class="breadcrumb-item">Edit</li>
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
            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($message !== ''): ?>
                <div class="alert alert-<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($message_type); ?>" role="alert">
                    <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($message); ?>
                </div>
            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
            <form method="post" action="">
                <input type="hidden" name="id" value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$section['id']; ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Section Code *</label>
                        <input type="text" name="code" class="form-control" value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)$section['code']); ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Section Name *</label>
                        <input type="text" name="name" class="form-control" value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)$section['name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Course *</label>
                        <select name="course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($courses as $course): ?>
                                <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$course['id']; ?>" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo ((int)$section['course_id'] === (int)$course['id']) ? 'selected' : ''; ?>>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($course['code'] ?: $course['name'])); ?>
                                </option>
                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                        </select>
                    </div>
                    <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($hasSectionDepartment): ?>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($departments as $dept): ?>
                                    <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$dept['id']; ?>" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo ((int)($section['department_id'] ?? 0) === (int)$dept['id']) ? 'selected' : ''; ?>>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($dept['code'] ?: $dept['name'])); ?>
                                    </option>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                            </select>
                        </div>
                    <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $activeValue === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $activeValue === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Save Section</button>
                    <a href="sections.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
require_once dirname(__DIR__) . '/config/db.php';
include 'includes/footer.php';
$conn->close();
?>


