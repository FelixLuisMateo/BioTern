<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$ops_helpers = dirname(__DIR__) . '/lib/ops_helpers.php';
if (file_exists($ops_helpers)) {
    require_once $ops_helpers;
    if (function_exists('require_roles_page')) {
        require_roles_page(['admin', 'coordinator', 'supervisor']);
    }
}

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

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function get_columns(mysqli $conn, string $table): array {
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = strtolower((string)$row['Field']);
        }
    }
    return $cols;
}

function has_col(array $cols, string $name): bool {
    return in_array(strtolower($name), $cols, true);
}

$intern_cols = get_columns($conn, 'internships');
$student_cols = get_columns($conn, 'students');
$course_cols = get_columns($conn, 'courses');
$dept_cols = get_columns($conn, 'departments');

$has_intern_deleted_at = has_col($intern_cols, 'deleted_at');
$has_student_deleted_at = has_col($student_cols, 'deleted_at');
$has_course_deleted_at = has_col($course_cols, 'deleted_at');
$has_dept_deleted_at = has_col($dept_cols, 'deleted_at');

$courses = [];
$course_sql = "SELECT id, code, name FROM courses" . ($has_course_deleted_at ? " WHERE deleted_at IS NULL" : "") . " ORDER BY name ASC";
$course_res = $conn->query($course_sql);
if ($course_res) {
    while ($row = $course_res->fetch_assoc()) {
        $courses[] = $row;
    }
}

$departments = [];
$dept_sql = "SELECT id, code, name FROM departments" . ($has_dept_deleted_at ? " WHERE deleted_at IS NULL" : "") . " ORDER BY name ASC";
$dept_res = $conn->query($dept_sql);
if ($dept_res) {
    while ($row = $dept_res->fetch_assoc()) {
        $departments[] = $row;
    }
}

$students = [];
$student_fields = ['id', 'student_id', 'first_name', 'last_name'];
if (has_col($student_cols, 'course_id')) $student_fields[] = 'course_id';
if (has_col($student_cols, 'department_id')) $student_fields[] = 'department_id';
if (has_col($student_cols, 'section_id')) $student_fields[] = 'section_id';
$student_sql = "SELECT " . implode(', ', $student_fields) . " FROM students";
if ($has_student_deleted_at) {
    $student_sql .= " WHERE deleted_at IS NULL";
}
$student_sql .= " ORDER BY first_name ASC, last_name ASC";
$student_res = $conn->query($student_sql);
if ($student_res) {
    while ($row = $student_res->fetch_assoc()) {
        $students[] = $row;
    }
}

$supervisors = [];
$sup_sql = "SELECT id, first_name, middle_name, last_name FROM supervisors ORDER BY first_name ASC, last_name ASC";
$sup_res = $conn->query($sup_sql);
if ($sup_res) {
    while ($row = $sup_res->fetch_assoc()) {
        $full = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $row['full_name'] = $full !== '' ? $full : ('Supervisor #' . (int)$row['id']);
        $supervisors[] = $row;
    }
}

$coordinators = [];
$coor_sql = "SELECT id, first_name, middle_name, last_name FROM coordinators ORDER BY first_name ASC, last_name ASC";
$coor_res = $conn->query($coor_sql);
if ($coor_res) {
    while ($row = $coor_res->fetch_assoc()) {
        $full = trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $row['full_name'] = $full !== '' ? $full : ('Coordinator #' . (int)$row['id']);
        $coordinators[] = $row;
    }
}

$sections = [];
if ($conn->query("SHOW TABLES LIKE 'sections'")->num_rows > 0) {
    $section_sql = "SELECT id, code, name, course_id, department_id FROM sections";
    if ($conn->query("SHOW COLUMNS FROM sections LIKE 'deleted_at'")->num_rows > 0) {
        $section_sql .= " WHERE deleted_at IS NULL";
    }
    $section_sql .= " ORDER BY code ASC, name ASC";
    $section_res = $conn->query($section_sql);
    if ($section_res) {
        while ($row = $section_res->fetch_assoc()) {
            $sections[] = $row;
        }
    }
}

$form = [
    'student_id' => (int)($_POST['student_id'] ?? 0),
    'course_id' => (int)($_POST['course_id'] ?? 0),
    'department_id' => (int)($_POST['department_id'] ?? 0),
    'section_id' => (int)($_POST['section_id'] ?? 0),
    'supervisor_id' => (int)($_POST['supervisor_id'] ?? 0),
    'coordinator_id' => (int)($_POST['coordinator_id'] ?? 0),
    'type' => trim((string)($_POST['type'] ?? 'internal')),
    'status' => trim((string)($_POST['status'] ?? 'ongoing')),
    'school_year' => trim((string)($_POST['school_year'] ?? (date('Y') . '-' . (date('Y') + 1)))),
    'required_hours' => (int)($_POST['required_hours'] ?? 250),
    'rendered_hours' => (int)($_POST['rendered_hours'] ?? 0),
    'start_date' => trim((string)($_POST['start_date'] ?? date('Y-m-d'))),
    'end_date' => trim((string)($_POST['end_date'] ?? '')),
    'company_name' => trim((string)($_POST['company_name'] ?? '')),
    'company_address' => trim((string)($_POST['company_address'] ?? '')),
    'contact_person' => trim((string)($_POST['contact_person'] ?? '')),
    'contact_number' => trim((string)($_POST['contact_number'] ?? '')),
    'remarks' => trim((string)($_POST['remarks'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    if ($form['student_id'] <= 0) $errors[] = 'Student is required.';
    if ($form['course_id'] <= 0 && has_col($intern_cols, 'course_id')) $errors[] = 'Course is required.';
    if ($form['department_id'] <= 0 && has_col($intern_cols, 'department_id')) $errors[] = 'Department is required.';
    if ($form['supervisor_id'] <= 0 && has_col($intern_cols, 'supervisor_id')) $errors[] = 'Supervisor is required.';
    if ($form['coordinator_id'] <= 0 && has_col($intern_cols, 'coordinator_id')) $errors[] = 'Coordinator is required.';
    if ($form['required_hours'] < 0) $errors[] = 'Required hours cannot be negative.';
    if ($form['rendered_hours'] < 0) $errors[] = 'Rendered hours cannot be negative.';
    if ($form['end_date'] !== '' && $form['start_date'] !== '' && strtotime($form['end_date']) < strtotime($form['start_date'])) {
        $errors[] = 'End date cannot be earlier than start date.';
    }

    $valid_status = ['ongoing', 'completed', 'dropped', 'paused'];
    if (!in_array($form['status'], $valid_status, true)) {
        $form['status'] = 'ongoing';
    }
    $valid_type = ['internal', 'external'];
    if (!in_array($form['type'], $valid_type, true)) {
        $form['type'] = 'internal';
    }

    if (empty($errors)) {
        $dup_sql = "SELECT id FROM internships WHERE student_id = ? AND status = 'ongoing'";
        if ($has_intern_deleted_at) {
            $dup_sql .= " AND deleted_at IS NULL";
        }
        $dup_sql .= " LIMIT 1";
        $dup_stmt = $conn->prepare($dup_sql);
        if ($dup_stmt) {
            $dup_stmt->bind_param('i', $form['student_id']);
            $dup_stmt->execute();
            $dup = $dup_stmt->get_result()->fetch_assoc();
            $dup_stmt->close();
            if ($dup && $form['status'] === 'ongoing') {
                $errors[] = 'This student already has an ongoing internship. Edit it instead.';
            }
        }
    }

    if (empty($errors)) {
        $completion = 0.0;
        if ($form['required_hours'] > 0) {
            $completion = round(($form['rendered_hours'] / $form['required_hours']) * 100, 2);
            if ($completion < 0) $completion = 0;
            if ($completion > 100) $completion = 100;
        }

        $insert_cols = [];
        $insert_vals = [];
        $types = '';
        $binds = [];

        $add = function (string $col, string $type, $val) use (&$insert_cols, &$insert_vals, &$types, &$binds) {
            $insert_cols[] = $col;
            $insert_vals[] = '?';
            $types .= $type;
            $binds[] = $val;
        };
        $add_now = function (string $col) use (&$insert_cols, &$insert_vals) {
            $insert_cols[] = $col;
            $insert_vals[] = 'NOW()';
        };

        if (has_col($intern_cols, 'student_id')) $add('student_id', 'i', $form['student_id']);
        if (has_col($intern_cols, 'course_id')) $add('course_id', 'i', $form['course_id']);
        if (has_col($intern_cols, 'department_id')) $add('department_id', 'i', $form['department_id']);
        if (has_col($intern_cols, 'coordinator_id')) $add('coordinator_id', 'i', $form['coordinator_id']);
        if (has_col($intern_cols, 'supervisor_id')) $add('supervisor_id', 'i', $form['supervisor_id']);
        if (has_col($intern_cols, 'type')) $add('type', 's', $form['type']);
        if (has_col($intern_cols, 'status')) $add('status', 's', $form['status']);
        if (has_col($intern_cols, 'school_year')) $add('school_year', 's', $form['school_year']);
        if (has_col($intern_cols, 'required_hours')) $add('required_hours', 'i', $form['required_hours']);
        if (has_col($intern_cols, 'rendered_hours')) $add('rendered_hours', 'i', $form['rendered_hours']);
        if (has_col($intern_cols, 'completion_percentage')) $add('completion_percentage', 'd', $completion);
        if (has_col($intern_cols, 'start_date')) $add('start_date', 's', $form['start_date']);
        if (has_col($intern_cols, 'end_date')) $add('end_date', 's', ($form['end_date'] !== '' ? $form['end_date'] : null));
        if (has_col($intern_cols, 'company_name')) $add('company_name', 's', $form['company_name']);
        if (has_col($intern_cols, 'company_address')) $add('company_address', 's', $form['company_address']);
        if (has_col($intern_cols, 'contact_person')) $add('contact_person', 's', $form['contact_person']);
        if (has_col($intern_cols, 'contact_number')) $add('contact_number', 's', $form['contact_number']);
        if (has_col($intern_cols, 'remarks')) $add('remarks', 's', $form['remarks']);
        if (has_col($intern_cols, 'created_at')) $add_now('created_at');
        if (has_col($intern_cols, 'updated_at')) $add_now('updated_at');

        $sql = "INSERT INTO internships (" . implode(', ', $insert_cols) . ") VALUES (" . implode(', ', $insert_vals) . ")";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $message = 'Failed to prepare internship insert: ' . $conn->error;
            $message_type = 'danger';
        } else {
            if ($types !== '') {
                $stmt->bind_param($types, ...$binds);
            }
            if ($stmt->execute()) {
                // Optional sync back to student record to keep roster filters consistent.
                $student_updates = [];
                $student_types = '';
                $student_binds = [];
                if (has_col($student_cols, 'course_id') && $form['course_id'] > 0) {
                    $student_updates[] = 'course_id = ?';
                    $student_types .= 'i';
                    $student_binds[] = $form['course_id'];
                }
                if (has_col($student_cols, 'department_id') && $form['department_id'] > 0) {
                    $student_updates[] = 'department_id = ?';
                    $student_types .= 'i';
                    $student_binds[] = $form['department_id'];
                }
                if (has_col($student_cols, 'section_id') && $form['section_id'] > 0) {
                    $student_updates[] = 'section_id = ?';
                    $student_types .= 'i';
                    $student_binds[] = $form['section_id'];
                }
                if (has_col($student_cols, 'assignment_track')) {
                    $student_updates[] = 'assignment_track = ?';
                    $student_types .= 's';
                    $student_binds[] = $form['type'];
                }
                if (has_col($student_cols, 'internal_total_hours') && $form['type'] === 'internal') {
                    $student_updates[] = 'internal_total_hours = ?';
                    $student_types .= 'i';
                    $student_binds[] = $form['required_hours'];
                }
                if (has_col($student_cols, 'external_total_hours') && $form['type'] === 'external') {
                    $student_updates[] = 'external_total_hours = ?';
                    $student_types .= 'i';
                    $student_binds[] = $form['required_hours'];
                }
                if (has_col($student_cols, 'internal_total_hours_remaining') && $form['type'] === 'internal') {
                    $student_updates[] = 'internal_total_hours_remaining = ?';
                    $student_types .= 'i';
                    $student_binds[] = max(0, $form['required_hours'] - $form['rendered_hours']);
                }
                if (has_col($student_cols, 'external_total_hours_remaining') && $form['type'] === 'external') {
                    $student_updates[] = 'external_total_hours_remaining = ?';
                    $student_types .= 'i';
                    $student_binds[] = max(0, $form['required_hours'] - $form['rendered_hours']);
                }
                if (has_col($student_cols, 'updated_at')) {
                    $student_updates[] = 'updated_at = NOW()';
                }

                if (!empty($student_updates)) {
                    $stu_sql = "UPDATE students SET " . implode(', ', $student_updates) . " WHERE id = ?";
                    $student_types .= 'i';
                    $student_binds[] = $form['student_id'];
                    $stu_stmt = $conn->prepare($stu_sql);
                    if ($stu_stmt) {
                        if ($student_types !== '') {
                            $stu_stmt->bind_param($student_types, ...$student_binds);
                        }
                        $stu_stmt->execute();
                        $stu_stmt->close();
                    }
                }

                $stmt->close();
                header('Location: ojt.php');
                exit;
            } else {
                $message = 'Failed to create OJT assignment: ' . $stmt->error;
                $message_type = 'danger';
                $stmt->close();
            }
        }
    } else {
        $message = implode(' ', $errors);
        $message_type = 'warning';
    }
}

$page_title = 'Create OJT Assignment';
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
            <h5 class="m-b-10">Create OJT Assignment</h5>
        </div>
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
            <li class="breadcrumb-item"><a href="ojt.php">OJT</a></li>
            <li class="breadcrumb-item">Create</li>
        </ul>
    </div>
    <div class="page-header-right ms-auto">
        <a href="ojt.php" class="btn btn-outline-secondary">Back to List</a>
    </div>
</div>

<div class="main-content">
    <div class="card stretch stretch-full">
        <div class="card-header">
            <h5 class="card-title mb-0">Detailed OJT Registration</h5>
        </div>
        <div class="card-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo h($message_type); ?>" role="alert"><?php echo h($message); ?></div>
            <?php endif; ?>
            <form method="post" action="" class="row g-3">
                <div class="col-12"><h6 class="fw-bold mb-1">Student & Academic Assignment</h6></div>
                <div class="col-md-6">
                    <label class="form-label">Student *</label>
                    <select name="student_id" id="student_id" class="form-select" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $st): ?>
                            <option
                                value="<?php echo (int)$st['id']; ?>"
                                data-course-id="<?php echo (int)($st['course_id'] ?? 0); ?>"
                                data-department-id="<?php echo (int)($st['department_id'] ?? 0); ?>"
                                data-section-id="<?php echo (int)($st['section_id'] ?? 0); ?>"
                                <?php echo $form['student_id'] === (int)$st['id'] ? 'selected' : ''; ?>
                            >
                                <?php echo h(($st['student_id'] ?? 'N/A') . ' - ' . ($st['first_name'] ?? '') . ' ' . ($st['last_name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Course *</label>
                    <select name="course_id" id="course_id" class="form-select" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $form['course_id'] === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo h(($c['code'] ?: $c['name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department *</label>
                    <select name="department_id" id="department_id" class="form-select" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo $form['department_id'] === (int)$d['id'] ? 'selected' : ''; ?>>
                                <?php echo h(($d['code'] ?: $d['name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Section</label>
                    <select name="section_id" id="section_id" class="form-select">
                        <option value="">Optional</option>
                        <?php foreach ($sections as $s): ?>
                            <?php $label = trim((string)($s['code'] ?? '')) !== '' ? (string)$s['code'] : (string)($s['name'] ?? ''); ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo $form['section_id'] === (int)$s['id'] ? 'selected' : ''; ?>>
                                <?php echo h($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Supervisor *</label>
                    <select name="supervisor_id" class="form-select" required>
                        <option value="">Select Supervisor</option>
                        <?php foreach ($supervisors as $s): ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo $form['supervisor_id'] === (int)$s['id'] ? 'selected' : ''; ?>>
                                <?php echo h($s['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Coordinator *</label>
                    <select name="coordinator_id" class="form-select" required>
                        <option value="">Select Coordinator</option>
                        <?php foreach ($coordinators as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $form['coordinator_id'] === (int)$c['id'] ? 'selected' : ''; ?>>
                                <?php echo h($c['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 mt-2"><h6 class="fw-bold mb-1">Internship Details</h6></div>
                <div class="col-md-2">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="internal" <?php echo $form['type'] === 'internal' ? 'selected' : ''; ?>>Internal</option>
                        <option value="external" <?php echo $form['type'] === 'external' ? 'selected' : ''; ?>>External</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="ongoing" <?php echo $form['status'] === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo $form['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="paused" <?php echo $form['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                        <option value="dropped" <?php echo $form['status'] === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">School Year</label>
                    <input type="text" name="school_year" class="form-control" value="<?php echo h($form['school_year']); ?>" placeholder="2026-2027">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Required Hours</label>
                    <input type="number" min="0" name="required_hours" class="form-control" value="<?php echo (int)$form['required_hours']; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Rendered Hours</label>
                    <input type="number" min="0" name="rendered_hours" class="form-control" value="<?php echo (int)$form['rendered_hours']; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo h($form['start_date']); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo h($form['end_date']); ?>">
                </div>

                <div class="col-12 mt-2"><h6 class="fw-bold mb-1">Company & Contact</h6></div>
                <div class="col-md-4">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" class="form-control" value="<?php echo h($form['company_name']); ?>" placeholder="Company/Agency name">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Company Address</label>
                    <input type="text" name="company_address" class="form-control" value="<?php echo h($form['company_address']); ?>" placeholder="Complete address">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?php echo h($form['contact_person']); ?>" placeholder="Supervisor/HR">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact_number" class="form-control" value="<?php echo h($form['contact_number']); ?>" placeholder="+63...">
                </div>
                <div class="col-md-9">
                    <label class="form-label">Remarks / Notes</label>
                    <input type="text" name="remarks" class="form-control" value="<?php echo h($form['remarks']); ?>" placeholder="Optional notes">
                </div>

                <div class="col-12 mt-3 create-form-actions">
                    <button type="submit" class="btn btn-primary">Create OJT Assignment</button>
                    <a href="ojt.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var studentSelect = document.getElementById('student_id');
    var courseSelect = document.getElementById('course_id');
    var departmentSelect = document.getElementById('department_id');
    var sectionSelect = document.getElementById('section_id');

    function syncFromStudent() {
        if (!studentSelect) return;
        var opt = studentSelect.options[studentSelect.selectedIndex];
        if (!opt) return;
        var c = opt.getAttribute('data-course-id') || '';
        var d = opt.getAttribute('data-department-id') || '';
        var s = opt.getAttribute('data-section-id') || '';
        if (c && courseSelect) courseSelect.value = c;
        if (d && departmentSelect) departmentSelect.value = d;
        if (s && sectionSelect) sectionSelect.value = s;
    }

    if (studentSelect) {
        studentSelect.addEventListener('change', syncFromStudent);
    }
});
</script>
<?php $conn->close(); ?>
