<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    http_response_code(500);
    die('Database connection is not available.');
}

$current_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($current_role, ['admin', 'coordinator'], true)) {
    header('Location: students.php');
    exit;
}

function create_student_column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function create_student_value(array $source, string $key): string
{
    return trim((string)($source[$key] ?? ''));
}

$courses = [];
$courseRes = $conn->query("SELECT id, name FROM courses ORDER BY name ASC");
if ($courseRes instanceof mysqli_result) {
    while ($row = $courseRes->fetch_assoc()) {
        $courses[] = $row;
    }
    $courseRes->close();
}

$sections = [];
$sectionRes = $conn->query("SELECT id, code, name FROM sections ORDER BY COALESCE(NULLIF(code, ''), name) ASC");
if ($sectionRes instanceof mysqli_result) {
    while ($row = $sectionRes->fetch_assoc()) {
        $row['section_label'] = biotern_format_section_label((string)($row['code'] ?? ''), (string)($row['name'] ?? ''));
        $sections[] = $row;
    }
    $sectionRes->close();
}

$errors = [];
$old = [
    'student_no' => '',
    'last_name' => '',
    'first_name' => '',
    'middle_name' => '',
    'email' => '',
    'course_id' => '',
    'section_id' => '',
    'password' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $_) {
        $old[$key] = create_student_value($_POST, $key);
    }

    $studentNo = $old['student_no'];
    $lastName = $old['last_name'];
    $firstName = $old['first_name'];
    $middleName = $old['middle_name'];
    $email = $old['email'];
    $courseId = (int)$old['course_id'];
    $sectionId = (int)$old['section_id'];
    $password = $old['password'];

    if ($studentNo === '') $errors[] = 'Student number is required.';
    if ($lastName === '') $errors[] = 'Last name is required.';
    if ($firstName === '') $errors[] = 'First name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($courseId <= 0) $errors[] = 'Course is required.';
    if ($sectionId <= 0) $errors[] = 'Section is required.';
    if ($password === '') $errors[] = 'Password is required.';

    if ($errors === []) {
        $dupStmt = $conn->prepare("SELECT 1 FROM students WHERE student_id = ? OR email = ? LIMIT 1");
        if ($dupStmt) {
            $dupStmt->bind_param('ss', $studentNo, $email);
            $dupStmt->execute();
            if ($dupStmt->get_result()->num_rows > 0) {
                $errors[] = 'A student with this student number or email already exists.';
            }
            $dupStmt->close();
        }
    }

    if ($errors === []) {
        $dupUserStmt = $conn->prepare("SELECT 1 FROM users WHERE username = ? OR email = ? LIMIT 1");
        if ($dupUserStmt) {
            $dupUserStmt->bind_param('ss', $studentNo, $email);
            $dupUserStmt->execute();
            if ($dupUserStmt->get_result()->num_rows > 0) {
                $errors[] = 'A user with this student number or email already exists.';
            }
            $dupUserStmt->close();
        }
    }

    if ($errors === []) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $fullName = trim($firstName . ' ' . $lastName);
        $conn->begin_transaction();
        try {
            $userColumns = ['name', 'username', 'email', 'password', 'role', 'is_active', 'profile_picture', 'created_at', 'updated_at'];
            $userValues = [$fullName, $studentNo, $email, $passwordHash, 'student', 1, '', null, null];
            $userTypes = 'sssssis';
            $userPlaceholders = ['?', '?', '?', '?', '?', '?', '?', 'NOW()', 'NOW()'];
            if (create_student_column_exists($conn, 'users', 'email_verified_at')) {
                $userColumns[] = 'email_verified_at';
                $userPlaceholders[] = 'NOW()';
            }
            if (create_student_column_exists($conn, 'users', 'application_status')) {
                $userColumns[] = 'application_status';
                $userValues[] = 'approved';
                $userTypes .= 's';
                $userPlaceholders[] = '?';
            }
            if (create_student_column_exists($conn, 'users', 'approved_by')) {
                $userColumns[] = 'approved_by';
                $userValues[] = (int)($_SESSION['user_id'] ?? 0);
                $userTypes .= 'i';
                $userPlaceholders[] = 'NULLIF(?, 0)';
            }
            if (create_student_column_exists($conn, 'users', 'approved_at')) {
                $userColumns[] = 'approved_at';
                $userPlaceholders[] = 'NOW()';
            }

            $bindUserValues = array_values(array_filter($userValues, static fn($value): bool => $value !== null));
            $userStmt = $conn->prepare('INSERT INTO users (`' . implode('`, `', $userColumns) . '`) VALUES (' . implode(', ', $userPlaceholders) . ')');
            if (!$userStmt) {
                throw new RuntimeException('Unable to prepare user creation.');
            }
            $userStmt->bind_param($userTypes, ...$bindUserValues);
            $userStmt->execute();
            $newUserId = (int)$conn->insert_id;
            $userStmt->close();

            $studentColumns = ['user_id', 'course_id', 'student_id', 'first_name', 'last_name', 'middle_name', 'username', 'password', 'email', 'bio', 'department_id', 'section_id', 'status', 'school_year', 'created_at', 'updated_at'];
            $studentPlaceholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', '?', 'NOW()', 'NOW()'];
            $studentValues = [$newUserId, $courseId, $studentNo, $firstName, $lastName, $middleName, $studentNo, $passwordHash, $email, '', 0, $sectionId, '1', ''];
            $studentTypes = 'iissssssssiiss';

            foreach ([
                'internal_total_hours_remaining' => 140,
                'internal_total_hours' => 140,
                'external_total_hours_remaining' => 250,
                'external_total_hours' => 250,
                'biometric_registered' => 0,
            ] as $column => $value) {
                if (create_student_column_exists($conn, 'students', $column)) {
                    $studentColumns[] = $column;
                    $studentPlaceholders[] = '?';
                    $studentValues[] = $value;
                    $studentTypes .= 'i';
                }
            }
            if (create_student_column_exists($conn, 'students', 'assignment_track')) {
                $studentColumns[] = 'assignment_track';
                $studentPlaceholders[] = '?';
                $studentValues[] = 'internal';
                $studentTypes .= 's';
            }
            if (create_student_column_exists($conn, 'students', 'application_status')) {
                $studentColumns[] = 'application_status';
                $studentPlaceholders[] = '?';
                $studentValues[] = 'approved';
                $studentTypes .= 's';
            }

            $studentStmt = $conn->prepare('INSERT INTO students (`' . implode('`, `', $studentColumns) . '`) VALUES (' . implode(', ', $studentPlaceholders) . ')');
            if (!$studentStmt) {
                throw new RuntimeException('Unable to prepare student creation.');
            }
            $studentStmt->bind_param($studentTypes, ...$studentValues);
            $studentStmt->execute();
            $studentStmt->close();

            $conn->commit();
            $_SESSION['students_flash'] = ['type' => 'success', 'message' => 'Student account created. They can sign in with the student number and password you entered.'];
            header('Location: students.php');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = 'Student could not be created. Please check the details and try again.';
        }
    }
}

$page_title = 'Create Student';
$page_body_class = 'students-page';
$page_styles = [
    'assets/css/modules/management/management-students-shared.css',
    'assets/css/modules/management/management-create-shared.css',
];
$page_scripts = ['assets/js/theme-customizer-init.min.js'];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Create Student</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                    <li class="breadcrumb-item">Create</li>
                </ul>
            </div>
        </div>

        <div class="card stretch stretch-full">
            <div class="card-header">
                <h5 class="mb-0">Student Account</h5>
            </div>
            <div class="card-body">
                <?php if ($errors !== []): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="student_no">Student No.</label>
                        <input type="text" class="form-control" id="student_no" name="student_no" value="<?php echo htmlspecialchars($old['student_no'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($old['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="first_name">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($old['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="middle_name">Middle Name</label>
                        <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($old['middle_name'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="password">Password</label>
                        <input type="text" class="form-control" id="password" name="password" value="<?php echo htmlspecialchars($old['password'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="course_id">Course</label>
                        <select class="form-select" id="course_id" name="course_id" required>
                            <option value="">Select course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo (int)$course['id']; ?>" <?php echo (int)$old['course_id'] === (int)$course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$course['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="section_id">Section</label>
                        <select class="form-select" id="section_id" name="section_id" required>
                            <option value="">Select section</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo (int)$section['id']; ?>" <?php echo (int)$old['section_id'] === (int)$section['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$section['section_label'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 create-form-actions">
                        <button type="submit" class="btn btn-primary">Create Student</button>
                        <a href="students.php" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
