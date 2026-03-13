<?php
require_once dirname(__DIR__) . '/config/db.php';
$host = '127.0.0.1';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : ''; 
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';

$message = defined('DB_PASS') ? DB_PASS : ''; 
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
$courseCodeById = [];
$courseSql = "SELECT id, code, name FROM courses" . ($hasCourseDeletedAt ? " WHERE deleted_at IS NULL" : "") . " ORDER BY name ASC";
$courseRes = $conn->query($courseSql);
if ($courseRes) {
    while ($row = $courseRes->fetch_assoc()) {
        $courses[] = $row;
        $courseId = isset($row['id']) ? (int)$row['id'] : 0;
        if ($courseId > 0) {
            $courseCodeById[$courseId] = strtoupper(trim((string)($row['code'] ?? '')));
        }
    }
}

$departments = [];
$defaultDepartmentId = 0;
if ($hasSectionDepartment) {
    $deptSql = "SELECT id, code, name FROM departments" . ($hasDeptDeletedAt ? " WHERE deleted_at IS NULL" : "") . " ORDER BY name ASC";
    $deptRes = $conn->query($deptSql);
    if ($deptRes) {
        while ($row = $deptRes->fetch_assoc()) {
            $departments[] = $row;
            if ($defaultDepartmentId <= 0 && isset($row['id'])) {
                $defaultDepartmentId = (int)$row['id'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $range_start = strtoupper(trim((string)($_POST['range_start'] ?? '')));
    $range_end = strtoupper(trim((string)($_POST['range_end'] ?? '')));
    $course_id = (int)($_POST['course_id'] ?? 0);
    $department_id = $hasSectionDepartment ? (int)$defaultDepartmentId : 0;
    $status_text = strtolower(trim((string)($_POST['status'] ?? 'active')));
    $status_flag = ($status_text === 'inactive') ? 0 : 1;
    $selectedCourseCode = (string)($courseCodeById[$course_id] ?? '');
    $yearThreeCourses = ['HTM', 'HMT', 'BSOA', 'BSE'];

    if ($course_id <= 0 || $selectedCourseCode === '') {
        $message = 'Course is required.';
        $message_type = 'danger';
    } elseif ($hasSectionDepartment && $department_id <= 0) {
        $message = 'No department available for sections. Please create a department first.';
        $message_type = 'danger';
    } elseif (!preg_match('/^(\d+)([A-Z])$/', $range_start, $startParts) || !preg_match('/^(\d+)([A-Z])$/', $range_end, $endParts)) {
        $message = 'Section range must use format like 2A to 2Z.';
        $message_type = 'danger';
    } else {
        $startNumber = (int)$startParts[1];
        $endNumber = (int)$endParts[1];
        $startLetter = ord($startParts[2]);
        $endLetter = ord($endParts[2]);

        if ($startNumber !== $endNumber) {
            $message = 'Start and end ranges must have the same year number (example: 2A to 2Z).';
            $message_type = 'danger';
        } elseif (in_array($selectedCourseCode, $yearThreeCourses, true) && $startNumber !== 3) {
            $message = 'For ' . $selectedCourseCode . ', use year 3 range (example: 3A to 3Z).';
            $message_type = 'danger';
        } elseif ($startLetter > $endLetter) {
            $message = 'Range start must come before range end.';
            $message_type = 'danger';
        } else {
            $sectionsToCreate = [];
            for ($letter = $startLetter; $letter <= $endLetter; $letter++) {
                $suffix = $startNumber . chr($letter);
                $sectionsToCreate[] = [
                    'code' => $selectedCourseCode,
                    'name' => $suffix,
                ];
            }

            $dupSql = "SELECT id FROM sections WHERE code = ? AND name = ?";
            if ($hasSectionDeletedAt) {
                $dupSql .= " AND deleted_at IS NULL";
            }
            $dupSql .= " LIMIT 1";
            $dupStmt = $conn->prepare($dupSql);

            $createdCount = 0;
            $skippedCount = 0;
            $errorText = defined('DB_PASS') ? DB_PASS : ''; 

            foreach ($sectionsToCreate as $sectionEntry) {
                $code = (string)$sectionEntry['code'];
                $name = (string)$sectionEntry['name'];
                $exists = false;
                if ($dupStmt) {
                    $dupStmt->bind_param('ss', $code, $name);
                    $dupStmt->execute();
                    $exists = (bool)$dupStmt->get_result()->fetch_assoc();
                }

                if ($exists) {
                    $skippedCount++;
                    continue;
                }

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
                    $createdCount++;
                } else {
                    $errorText = $conn->error;
                    break;
                }
            }

            if ($dupStmt) {
                $dupStmt->close();
            }

            if ($errorText !== '') {
                $message = 'Failed to create sections: ' . $errorText;
                $message_type = 'danger';
            } elseif ($createdCount > 0 && $skippedCount === 0) {
                header('Location: sections.php');
                exit;
            } elseif ($createdCount > 0) {
                $message = $createdCount . ' section(s) created. ' . $skippedCount . ' duplicate section(s) skipped.';
                $message_type = 'warning';
            } else {
                $message = 'No new sections were created (all duplicates).';
                $message_type = 'warning';
            }
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
            <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
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
                        <label class="form-label">Course *</label>
                        <select id="courseSelect" name="course_id" class="form-select" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int)$course['id']; ?>" data-course-code="<?php echo htmlspecialchars(strtoupper((string)($course['code'] ?? ''))); ?>">
                                    <?php echo htmlspecialchars((string)($course['code'] ?: $course['name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Range Start *</label>
                        <input type="text" id="rangeStartInput" name="range_start" class="form-control text-uppercase" required>
                        <small class="form-text text-muted">Example: 2A</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Range End *</label>
                        <input type="text" id="rangeEndInput" name="range_end" class="form-control text-uppercase" required>
                        <small class="form-text text-muted">Example: 2D</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <?php if ($hasSectionDepartment): ?>
                    <input type="hidden" name="department_id" value="<?php echo (int)$defaultDepartmentId; ?>">
                <?php endif; ?>
                <div class="mt-3 create-form-actions">
                    <button type="submit" class="btn btn-primary">Create Section</button>
                    <a href="sections.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    (function () {
        const courseSelect = document.getElementById('courseSelect');
        const rangeStartInput = document.getElementById('rangeStartInput');
        const rangeEndInput = document.getElementById('rangeEndInput');
        if (!courseSelect || !rangeStartInput || !rangeEndInput) return;

        // Accept range like 2A, 2B ... 2Z (any number + letter).
        rangeStartInput.setAttribute('pattern', '[0-9]+[A-Z]');
        rangeEndInput.setAttribute('pattern', '[0-9]+[A-Z]');
        rangeStartInput.setAttribute('title', 'Use format like 2A');
        rangeEndInput.setAttribute('title', 'Use format like 2D');

        function applyCourseDefaults() {
            const selected = courseSelect.options[courseSelect.selectedIndex];
            const courseCode = selected ? (selected.getAttribute('data-course-code') || '').toUpperCase() : '';
            const yearThreeCourses = ['HTM', 'HMT', 'BSOA', 'BSE'];
            const baseYear = yearThreeCourses.includes(courseCode) ? '3' : '2';

            if (rangeStartInput.value.trim() === '') {
                rangeStartInput.value = baseYear + 'A';
            }
            if (rangeEndInput.value.trim() === '') {
                rangeEndInput.value = baseYear + 'Z';
            }
        }

        courseSelect.addEventListener('change', applyCourseDefaults);

        rangeStartInput.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });
        rangeEndInput.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });

        applyCourseDefaults();
    })();
</script>
<?php
require_once dirname(__DIR__) . '/config/db.php';
include 'includes/footer.php';
$conn->close();
?>

