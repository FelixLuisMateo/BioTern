<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
biotern_boot_session(isset($conn) ? $conn : null);
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    http_response_code(500);
    die('Database connection is not available.');
}
/** @var mysqli $db */
$db = $conn;
$db_name = defined('DB_NAME') ? (string)DB_NAME : 'biotern_db';

$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_user_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'));
$current_user_email = trim((string)($_SESSION['email'] ?? 'admin@biotern.local'));
$current_user_role_badge = trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
$current_profile_rel = ltrim(str_replace('\\', '/', trim((string)($_SESSION['profile_picture'] ?? ''))), '/');
$current_profile_img = 'assets/images/avatar/' . (($current_user_id > 0 ? ($current_user_id % 5) : 0) + 1) . '.png';
if ($current_profile_rel !== '' && file_exists(dirname(__DIR__) . '/' . $current_profile_rel)) {
    $current_profile_img = $current_profile_rel;
}

$current_role = strtolower(trim((string) (
    $_SESSION['role'] ??
    $_SESSION['user_role'] ??
    $_SESSION['account_role'] ??
    $_SESSION['user_type'] ??
    $_SESSION['type'] ??
    ''
)));
$is_student_user = ($current_role === 'student');
$coordinator_allowed_course_ids = $current_role === 'coordinator'
    ? coordinator_course_ids($db, $current_user_id)
    : [];
$coordinator_course_scope_sql = '';
if ($current_role === 'coordinator') {
    $coordinator_course_scope_sql = empty($coordinator_allowed_course_ids)
        ? '1 = 0'
        : 's.course_id IN (' . implode(',', array_map('intval', $coordinator_allowed_course_ids)) . ')';
}

function biotern_students_has_column(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function biotern_students_redirect_self(): void
{
    $target = 'students.php';
    $query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
    if ($query !== '') {
        $target .= '?' . $query;
    }
    header('Location: ' . $target);
    exit;
}

$db->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_start_allowed TINYINT(1) NOT NULL DEFAULT 0 AFTER assignment_track");

$studentColumns = [];
$studentColumnsRes = $db->query("SHOW COLUMNS FROM students");
if ($studentColumnsRes instanceof mysqli_result) {
    while ($columnRow = $studentColumnsRes->fetch_assoc()) {
        $studentColumns[strtolower((string)($columnRow['Field'] ?? ''))] = true;
    }
    $studentColumnsRes->close();
}

$internshipColumns = [];
$internshipColumnsRes = $db->query("SHOW COLUMNS FROM internships");
if ($internshipColumnsRes instanceof mysqli_result) {
    while ($columnRow = $internshipColumnsRes->fetch_assoc()) {
        $internshipColumns[strtolower((string)($columnRow['Field'] ?? ''))] = true;
    }
    $internshipColumnsRes->close();
}

$studentsFlash = $_SESSION['students_flash'] ?? null;
unset($_SESSION['students_flash']);

$has_application_status = false;
$col_app = $db->query("SHOW COLUMNS FROM users LIKE 'application_status'");
if ($col_app && $col_app->num_rows > 0) {
    $has_application_status = true;
}

$has_school_year_column = false;
$col_sy = $db->query("SHOW COLUMNS FROM students LIKE 'school_year'");
if ($col_sy && $col_sy->num_rows > 0) {
    $has_school_year_column = true;
}
$has_semester_column = false;
$col_sem = $db->query("SHOW COLUMNS FROM students LIKE 'semester'");
if ($col_sem && $col_sem->num_rows > 0) {
    $has_semester_column = true;
}

$has_fingerprint_user_map = false;
$map_tbl = $db->query("SHOW TABLES LIKE 'fingerprint_user_map'");
if ($map_tbl && $map_tbl->num_rows > 0) {
    $has_fingerprint_user_map = true;
}

$has_student_assistance_programs = false;
$sa_tbl = $db->query("SHOW TABLES LIKE 'student_assistance_programs'");
if ($sa_tbl && $sa_tbl->num_rows > 0) {
    $has_student_assistance_programs = true;
}

$biometric_ready_condition = "s.biometric_registered = 1";
if ($has_fingerprint_user_map) {
    $biometric_ready_condition = "(s.biometric_registered = 1 OR EXISTS (SELECT 1 FROM fingerprint_user_map fum WHERE fum.user_id = s.user_id))";
}

// Fetch Students Statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_students,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_students,
        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_students,
        SUM(CASE WHEN {$biometric_ready_condition} THEN 1 ELSE 0 END) as biometric_registered
    FROM students s
";
$stats_where = [];
if ($has_application_status) {
    $stats_query .= "
    LEFT JOIN users u ON u.id = s.user_id
    ";
    $stats_where[] = "COALESCE(u.application_status, 'approved') = 'approved'";
}
if ($coordinator_course_scope_sql !== '') {
    $stats_where[] = $coordinator_course_scope_sql;
}
if (!empty($stats_where)) {
    $stats_query .= ' WHERE ' . implode(' AND ', $stats_where);
}
$stats = [
    'total_students' => 0,
    'active_students' => 0,
    'inactive_students' => 0,
    'biometric_registered' => 0,
];
$stats_result = $db->query($stats_query);
if ($stats_result) {
    $stats_row = $stats_result->fetch_assoc();
    if (is_array($stats_row)) {
        $stats = array_merge($stats, $stats_row);
    }
}

// Prepare filter inputs
$filter_date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$filter_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$filter_department = isset($_GET['department_id']) ? intval($_GET['department_id']) : 0;
$filter_section = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$filter_school_year = isset($_GET['school_year']) ? trim((string)$_GET['school_year']) : '';
$filter_semester = isset($_GET['semester']) ? trim((string)$_GET['semester']) : '';
$filter_supervisor = isset($_GET['supervisor']) ? trim($_GET['supervisor']) : '';
$filter_coordinator = isset($_GET['coordinator']) ? trim($_GET['coordinator']) : '';
$filter_status = isset($_GET['status']) ? intval($_GET['status']) : -1;

$school_year_options = [];
$school_year_start = 2005;
$current_calendar_month = (int)date('n');
$current_calendar_year = (int)date('Y');
$current_school_year_start = $current_calendar_month >= 7 ? $current_calendar_year : ($current_calendar_year - 1);
$latest_school_year_start = max(2025, $current_school_year_start);
for ($year = $latest_school_year_start; $year >= $school_year_start; $year--) {
    $school_year_options[] = sprintf('%d-%d', $year, $year + 1);
}
$semester_options = ['1st Semester', '2nd Semester', 'Summer'];

// Fetch dropdown lists
$courses = [];
// Determine which column exists for active flag on courses to avoid schema mismatch errors
$db_esc = $db->real_escape_string($db_name);
$has_is_active = false;
$has_status_col = false;
$col_check = $db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $db_esc . "' AND TABLE_NAME = 'courses' AND COLUMN_NAME IN ('is_active','status')");
if ($col_check && $col_check->num_rows) {
    while ($c = $col_check->fetch_assoc()) {
        if ($c['COLUMN_NAME'] === 'is_active') $has_is_active = true;
        if ($c['COLUMN_NAME'] === 'status') $has_status_col = true;
    }
}

$courses_query = "SELECT id, name FROM courses";
$course_conditions = [];
if ($has_is_active) {
    $course_conditions[] = 'is_active = 1';
} elseif ($has_status_col) {
    $course_conditions[] = 'status = 1';
}
if ($current_role === 'coordinator') {
    $course_conditions[] = empty($coordinator_allowed_course_ids)
        ? '1 = 0'
        : 'id IN (' . implode(',', array_map('intval', $coordinator_allowed_course_ids)) . ')';
}
if (!empty($course_conditions)) {
    $courses_query .= ' WHERE ' . implode(' AND ', $course_conditions);
}
$courses_query .= " ORDER BY name ASC";

$courses_res = $db->query($courses_query);
if ($courses_res && $courses_res->num_rows) {
    while ($r = $courses_res->fetch_assoc()) $courses[] = $r;
}

$departments = [];
$dept_res = $db->query("SELECT id, name FROM departments ORDER BY name ASC");
if ($dept_res && $dept_res->num_rows) {
    while ($r = $dept_res->fetch_assoc()) $departments[] = $r;
}

$sections = [];
$section_res = $db->query("SELECT id, code, name, COALESCE(NULLIF(code, ''), name) AS section_label FROM sections ORDER BY section_label ASC");
if ($section_res && $section_res->num_rows) {
    while ($r = $section_res->fetch_assoc()) {
        $r['section_label'] = biotern_format_section_label((string)($r['code'] ?? ''), (string)($r['name'] ?? ''));
        $sections[] = $r;
    }
}

$supervisors = [];
$supervisorOptions = [];
$sup_res = $db->query("
    SELECT id, user_id, TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS supervisor_name, department_id
    FROM supervisors
    WHERE TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) <> ''
    ORDER BY supervisor_name ASC
");
if ($sup_res && $sup_res->num_rows) {
    while ($r = $sup_res->fetch_assoc()) {
        $supervisors[] = $r['supervisor_name'];
        $supervisorOptions[] = $r;
    }
}

$coordinators = [];
$coor_res = $db->query("
    SELECT DISTINCT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS coordinator_name
    FROM coordinators
    WHERE TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) <> ''
    ORDER BY coordinator_name ASC
");
if ($coor_res && $coor_res->num_rows) {
    while ($r = $coor_res->fetch_assoc()) $coordinators[] = $r['coordinator_name'];
}

$hasCoordinatorCourses = false;
$coordinatorCoursesTable = $db->query("SHOW TABLES LIKE 'coordinator_courses'");
if ($coordinatorCoursesTable instanceof mysqli_result && $coordinatorCoursesTable->num_rows > 0) {
    $hasCoordinatorCourses = true;
    $coordinatorCoursesTable->close();
}

$coordinatorCourseMap = [];
if ($hasCoordinatorCourses) {
    $coordinatorMapRes = $db->query("
        SELECT
            cc.course_id,
            cc.coordinator_user_id,
            c.id AS coordinator_profile_id,
            COALESCE(NULLIF(u.name, ''), TRIM(CONCAT_WS(' ', c.first_name, c.middle_name, c.last_name))) AS coordinator_name
        FROM coordinator_courses cc
        LEFT JOIN coordinators c ON c.user_id = cc.coordinator_user_id
        LEFT JOIN users u ON u.id = cc.coordinator_user_id
        ORDER BY cc.id DESC
    ");
    if ($coordinatorMapRes instanceof mysqli_result) {
        while ($row = $coordinatorMapRes->fetch_assoc()) {
            $courseId = (int)($row['course_id'] ?? 0);
            if ($courseId <= 0 || isset($coordinatorCourseMap[$courseId])) {
                continue;
            }
            $coordinatorCourseMap[$courseId] = [
                'user_id' => (int)($row['coordinator_user_id'] ?? 0),
                'profile_id' => (int)($row['coordinator_profile_id'] ?? 0),
                'name' => trim((string)($row['coordinator_name'] ?? '')),
            ];
        }
        $coordinatorMapRes->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_student_user) {
    $action = strtolower(trim((string)($_POST['student_action'] ?? '')));
    if ($action === 'assign_track') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $assignmentTrack = strtolower(trim((string)($_POST['assignment_track'] ?? 'internal')));
        $departmentId = (int)($_POST['department_id'] ?? 0);
        $supervisorProfileId = (int)($_POST['supervisor_id'] ?? 0);
        $startDate = trim((string)($_POST['start_date'] ?? date('Y-m-d')));

        if ($studentId <= 0) {
            $_SESSION['students_flash'] = ['type' => 'danger', 'message' => 'Student assignment could not be saved.'];
            biotern_students_redirect_self();
        }
        if (!in_array($assignmentTrack, ['internal', 'external'], true)) {
            $assignmentTrack = 'internal';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $startDate = date('Y-m-d');
        }

        $studentStmt = $db->prepare("
            SELECT s.id, s.course_id,
                   COALESCE(i.department_id, s.department_id) AS department_id,
                   COALESCE(i.supervisor_id, s.supervisor_id) AS supervisor_id,
                   COALESCE(i.coordinator_id, s.coordinator_id) AS coordinator_id,
                   first_name, last_name, supervisor_name, coordinator_name,
                   internal_total_hours, external_total_hours
            FROM students s
            LEFT JOIN internships i ON i.id = (
                SELECT i2.id
                FROM internships i2
                WHERE i2.student_id = s.id
                ORDER BY (i2.status = 'ongoing') DESC, i2.id DESC
                LIMIT 1
            )
            WHERE s.id = ?
            LIMIT 1
        ");
        $studentRow = null;
        if ($studentStmt) {
            $studentStmt->bind_param('i', $studentId);
            $studentStmt->execute();
            $studentRow = $studentStmt->get_result()->fetch_assoc() ?: null;
            $studentStmt->close();
        }

        if (!$studentRow) {
            $_SESSION['students_flash'] = ['type' => 'danger', 'message' => 'Student record not found.'];
            biotern_students_redirect_self();
        }

        if ($departmentId <= 0) {
            $departmentId = (int)($studentRow['department_id'] ?? 0);
        }
        if ($supervisorProfileId <= 0) {
            $supervisorProfileId = (int)($studentRow['supervisor_id'] ?? 0);
        }

        $supervisorProfile = null;
        foreach ($supervisorOptions as $option) {
            if ((int)($option['id'] ?? 0) === $supervisorProfileId) {
                $supervisorProfile = $option;
                break;
            }
        }
        if (!$supervisorProfile) {
            $_SESSION['students_flash'] = ['type' => 'danger', 'message' => 'Select a valid supervisor before assigning the student.'];
            biotern_students_redirect_self();
        }

        $courseId = (int)($studentRow['course_id'] ?? 0);
        $coordinatorInfo = $coordinatorCourseMap[$courseId] ?? ['user_id' => 0, 'profile_id' => 0, 'name' => trim((string)($studentRow['coordinator_name'] ?? ''))];
        $supervisorName = trim((string)($supervisorProfile['supervisor_name'] ?? ''));
        $coordinatorName = trim((string)($coordinatorInfo['name'] ?? ''));
        $coordinatorProfileId = (int)($coordinatorInfo['profile_id'] ?? 0);
        $requiredHours = $assignmentTrack === 'external'
            ? (int)($studentRow['external_total_hours'] ?? 0)
            : (int)($studentRow['internal_total_hours'] ?? 0);
        if ($requiredHours < 0) {
            $requiredHours = 0;
        }

        $studentSets = [];
        $studentTypes = '';
        $studentValues = [];

        if (isset($studentColumns['assignment_track'])) {
            $studentSets[] = 'assignment_track = ?';
            $studentTypes .= 's';
            $studentValues[] = $assignmentTrack;
        }
        if (isset($studentColumns['external_start_allowed']) && $assignmentTrack === 'external') {
            $studentSets[] = 'external_start_allowed = 1';
        }
        if (isset($studentColumns['department_id'])) {
            $studentSets[] = 'department_id = NULLIF(?, 0)';
            $studentTypes .= 'i';
            $studentValues[] = $departmentId;
        }
        if (isset($studentColumns['supervisor_name'])) {
            $studentSets[] = 'supervisor_name = ?';
            $studentTypes .= 's';
            $studentValues[] = $supervisorName;
        }
        if (isset($studentColumns['supervisor_id'])) {
            $studentSets[] = 'supervisor_id = NULLIF(?, 0)';
            $studentTypes .= 'i';
            $studentValues[] = $supervisorProfileId;
        }
        if (isset($studentColumns['coordinator_name'])) {
            $studentSets[] = 'coordinator_name = ?';
            $studentTypes .= 's';
            $studentValues[] = $coordinatorName;
        }
        if (isset($studentColumns['coordinator_id'])) {
            $studentSets[] = 'coordinator_id = NULLIF(?, 0)';
            $studentTypes .= 'i';
            $studentValues[] = $coordinatorProfileId;
        }
        if (isset($studentColumns['updated_at'])) {
            $studentSets[] = 'updated_at = NOW()';
        }

        $saveOk = false;
        $db->begin_transaction();
        try {
            if ($studentSets !== []) {
                $studentTypes .= 'i';
                $studentValues[] = $studentId;
                $studentSql = 'UPDATE students SET ' . implode(', ', $studentSets) . ' WHERE id = ? LIMIT 1';
                $studentUpdate = $db->prepare($studentSql);
                if (!$studentUpdate) {
                    throw new RuntimeException('Could not prepare the student assignment update.');
                }
                $studentUpdate->bind_param($studentTypes, ...$studentValues);
                if (!$studentUpdate->execute()) {
                    $studentUpdate->close();
                    throw new RuntimeException('Could not save the student assignment.');
                }
                $studentUpdate->close();
            }

            $internshipId = 0;
            $internshipLookup = $db->prepare("
                SELECT id
                FROM internships
                WHERE student_id = ?
                ORDER BY (status = 'ongoing') DESC, id DESC
                LIMIT 1
            ");
            if ($internshipLookup) {
                $internshipLookup->bind_param('i', $studentId);
                $internshipLookup->execute();
                $internshipRow = $internshipLookup->get_result()->fetch_assoc() ?: null;
                $internshipLookup->close();
                $internshipId = (int)($internshipRow['id'] ?? 0);
            }

            if ($internshipId > 0) {
                $internshipSets = [];
                $internshipTypes = '';
                $internshipValues = [];

                if (isset($internshipColumns['type'])) {
                    $internshipSets[] = 'type = ?';
                    $internshipTypes .= 's';
                    $internshipValues[] = $assignmentTrack;
                }
                if (isset($internshipColumns['course_id'])) {
                    $internshipSets[] = 'course_id = NULLIF(?, 0)';
                    $internshipTypes .= 'i';
                    $internshipValues[] = $courseId;
                }
                if (isset($internshipColumns['department_id'])) {
                    $internshipSets[] = 'department_id = NULLIF(?, 0)';
                    $internshipTypes .= 'i';
                    $internshipValues[] = $departmentId;
                }
                if (isset($internshipColumns['supervisor_id'])) {
                    $internshipSets[] = 'supervisor_id = NULLIF(?, 0)';
                    $internshipTypes .= 'i';
                    $internshipValues[] = $supervisorProfileId;
                }
                if (isset($internshipColumns['coordinator_id'])) {
                    $internshipSets[] = 'coordinator_id = NULLIF(?, 0)';
                    $internshipTypes .= 'i';
                    $internshipValues[] = $coordinatorProfileId;
                }
                if (isset($internshipColumns['start_date'])) {
                    $internshipSets[] = 'start_date = ?';
                    $internshipTypes .= 's';
                    $internshipValues[] = $startDate;
                }
                if (isset($internshipColumns['required_hours'])) {
                    $internshipSets[] = 'required_hours = ?';
                    $internshipTypes .= 'i';
                    $internshipValues[] = $requiredHours;
                }
                if (isset($internshipColumns['updated_at'])) {
                    $internshipSets[] = 'updated_at = NOW()';
                }

                if ($internshipSets !== []) {
                    $internshipTypes .= 'i';
                    $internshipValues[] = $internshipId;
                    $internshipSql = 'UPDATE internships SET ' . implode(', ', $internshipSets) . ' WHERE id = ?';
                    $internshipUpdate = $db->prepare($internshipSql);
                    if (!$internshipUpdate) {
                        throw new RuntimeException('Could not prepare the internship update.');
                    }
                    $internshipUpdate->bind_param($internshipTypes, ...$internshipValues);
                    if (!$internshipUpdate->execute()) {
                        $internshipUpdate->close();
                        throw new RuntimeException('Could not update the internship assignment.');
                    }
                    $internshipUpdate->close();
                }
            } else {
                $insertColumns = ['student_id'];
                $insertPlaceholders = ['?'];
                $insertTypes = 'i';
                $insertValues = [$studentId];

                if (isset($internshipColumns['course_id'])) {
                    $insertColumns[] = 'course_id';
                    $insertPlaceholders[] = 'NULLIF(?, 0)';
                    $insertTypes .= 'i';
                    $insertValues[] = $courseId;
                }
                if (isset($internshipColumns['department_id'])) {
                    $insertColumns[] = 'department_id';
                    $insertPlaceholders[] = 'NULLIF(?, 0)';
                    $insertTypes .= 'i';
                    $insertValues[] = $departmentId;
                }
                if (isset($internshipColumns['coordinator_id'])) {
                    $insertColumns[] = 'coordinator_id';
                    $insertPlaceholders[] = 'NULLIF(?, 0)';
                    $insertTypes .= 'i';
                    $insertValues[] = $coordinatorProfileId;
                }
                if (isset($internshipColumns['supervisor_id'])) {
                    $insertColumns[] = 'supervisor_id';
                    $insertPlaceholders[] = 'NULLIF(?, 0)';
                    $insertTypes .= 'i';
                    $insertValues[] = $supervisorProfileId;
                }
                if (isset($internshipColumns['type'])) {
                    $insertColumns[] = 'type';
                    $insertPlaceholders[] = '?';
                    $insertTypes .= 's';
                    $insertValues[] = $assignmentTrack;
                }
                if (isset($internshipColumns['start_date'])) {
                    $insertColumns[] = 'start_date';
                    $insertPlaceholders[] = '?';
                    $insertTypes .= 's';
                    $insertValues[] = $startDate;
                }
                if (isset($internshipColumns['status'])) {
                    $insertColumns[] = 'status';
                    $insertPlaceholders[] = '\'ongoing\'';
                }
                if (isset($internshipColumns['required_hours'])) {
                    $insertColumns[] = 'required_hours';
                    $insertPlaceholders[] = '?';
                    $insertTypes .= 'i';
                    $insertValues[] = $requiredHours;
                }
                if (isset($internshipColumns['rendered_hours'])) {
                    $insertColumns[] = 'rendered_hours';
                    $insertPlaceholders[] = '0';
                }
                if (isset($internshipColumns['completion_percentage'])) {
                    $insertColumns[] = 'completion_percentage';
                    $insertPlaceholders[] = '0';
                }
                if (isset($internshipColumns['created_at'])) {
                    $insertColumns[] = 'created_at';
                    $insertPlaceholders[] = 'NOW()';
                }
                if (isset($internshipColumns['updated_at'])) {
                    $insertColumns[] = 'updated_at';
                    $insertPlaceholders[] = 'NOW()';
                }

                $insertSql = 'INSERT INTO internships (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
                $internshipInsert = $db->prepare($insertSql);
                if (!$internshipInsert) {
                    throw new RuntimeException('Could not prepare the internship creation.');
                }
                if ($insertTypes !== '') {
                    $internshipInsert->bind_param($insertTypes, ...$insertValues);
                }
                if (!$internshipInsert->execute()) {
                    $internshipInsert->close();
                    throw new RuntimeException('Could not create the internship assignment.');
                }
                $internshipInsert->close();
            }

            external_attendance_sync_student_hours($db, $studentId);
            $db->commit();
            $saveOk = true;
        } catch (Throwable $e) {
            $db->rollback();
            $_SESSION['students_flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage(),
            ];
        }

        if ($saveOk) {
            $studentLabel = trim((string)($studentRow['first_name'] ?? '') . ' ' . (string)($studentRow['last_name'] ?? ''));
            $coordMessage = $coordinatorName !== '' ? (' Coordinator: ' . $coordinatorName . '.') : ' Coordinator is not yet mapped for this course.';
            $_SESSION['students_flash'] = [
                'type' => 'success',
                'message' => $studentLabel . ' is now assigned to ' . ucfirst($assignmentTrack) . '. Supervisor: ' . $supervisorName . '.' . $coordMessage,
            ];
        }

        biotern_students_redirect_self();
    }
}

// Build WHERE clauses depending on provided filters
$where = [];
if ($has_application_status) {
    $where[] = "COALESCE(u_student.application_status, 'approved') = 'approved'";
}
if ($filter_date !== '') {
    // Filter students that have attendance logs on the selected date.
    $safe_date = $db->real_escape_string($filter_date);
    $where[] = "EXISTS (
        SELECT 1 FROM attendances a_date
        WHERE a_date.student_id = s.id
          AND a_date.attendance_date = '{$safe_date}'
    )";
}
if ($filter_course > 0) {
    $where[] = "s.course_id = " . intval($filter_course);
}
if ($coordinator_course_scope_sql !== '') {
    $where[] = $coordinator_course_scope_sql;
}
if ($filter_department > 0) {
    $where[] = "i.department_id = " . intval($filter_department);
}
if ($filter_section > 0) {
    $where[] = "s.section_id = " . intval($filter_section);
}
if ($has_school_year_column && $filter_school_year !== '' && preg_match('/^\\d{4}-\\d{4}$/', $filter_school_year) && in_array($filter_school_year, $school_year_options, true)) {
    $esc_school_year = $db->real_escape_string($filter_school_year);
    $where[] = "s.school_year = '{$esc_school_year}'";
}
if ($has_semester_column && $filter_semester !== '' && in_array($filter_semester, $semester_options, true)) {
    $esc_semester = $db->real_escape_string($filter_semester);
    $where[] = "s.semester = '{$esc_semester}'";
}
if (!empty($filter_supervisor)) {
    $esc_sup = $db->real_escape_string($filter_supervisor);
    $where[] = "(
        TRIM(CONCAT_WS(' ', sup.first_name, sup.middle_name, sup.last_name)) LIKE '%{$esc_sup}%'
        OR sup_user.name LIKE '%{$esc_sup}%'
        OR s.supervisor_name LIKE '%{$esc_sup}%'
    )";
}
if (!empty($filter_coordinator)) {
    $esc_coor = $db->real_escape_string($filter_coordinator);
    $coordinator_course_filter_sql = $hasCoordinatorCourses ? " OR course_coor.coordinator_name LIKE '%{$esc_coor}%'" : '';
    $where[] = "(
        TRIM(CONCAT_WS(' ', coor.first_name, coor.middle_name, coor.last_name)) LIKE '%{$esc_coor}%'
        OR coor_user.name LIKE '%{$esc_coor}%'
        OR s.coordinator_name LIKE '%{$esc_coor}%'
        {$coordinator_course_filter_sql}
    )";
}
if ($filter_status >= 0) {
    if (intval($filter_status) === 1) {
        $where[] = "EXISTS (
            SELECT 1 FROM attendances a_live
            WHERE a_live.student_id = s.id
              AND a_live.attendance_date = CURDATE()
              AND (
                    (a_live.morning_time_in IS NOT NULL AND a_live.morning_time_out IS NULL)
                 OR (a_live.afternoon_time_in IS NOT NULL AND a_live.afternoon_time_out IS NULL)
              )
        )";
    } else {
        $where[] = "NOT EXISTS (
            SELECT 1 FROM attendances a_live
            WHERE a_live.student_id = s.id
              AND a_live.attendance_date = CURDATE()
              AND (
                    (a_live.morning_time_in IS NOT NULL AND a_live.morning_time_out IS NULL)
                 OR (a_live.afternoon_time_in IS NOT NULL AND a_live.afternoon_time_out IS NULL)
              )
        )";
    }
}

// Fetch Students with Related Information
$coordinator_course_join_sql = $hasCoordinatorCourses ? "
    LEFT JOIN (
        SELECT
            cc.course_id,
            cc.coordinator_user_id,
            ccf.id AS coordinator_profile_id,
            COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', ccf.first_name, ccf.middle_name, ccf.last_name)), ''),
                NULLIF(TRIM(ucf.name), '')
            ) AS coordinator_name
        FROM coordinator_courses cc
        INNER JOIN (
            SELECT course_id, MAX(id) AS latest_id
            FROM coordinator_courses
            GROUP BY course_id
        ) latest_cc ON latest_cc.latest_id = cc.id
        LEFT JOIN coordinators ccf ON ccf.user_id = cc.coordinator_user_id
        LEFT JOIN users ucf ON ucf.id = cc.coordinator_user_id
    ) course_coor ON course_coor.course_id = s.course_id
" : '';

$students_query = "
    SELECT 
        s.id,
        s.user_id,
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.email,
        s.phone,
        COALESCE(i.department_id, s.department_id) AS department_id,
        COALESCE(i.supervisor_id, s.supervisor_id) AS student_supervisor_ref_id,
        COALESCE(i.coordinator_id, s.coordinator_id) AS student_coordinator_ref_id,
        s.status,
        COALESCE(NULLIF(TRIM(s.assignment_track), ''), 'internal') AS assignment_track,
        " . ($has_student_assistance_programs ? "CASE WHEN EXISTS (SELECT 1 FROM student_assistance_programs sap WHERE sap.student_id = s.id AND sap.deleted_at IS NULL AND sap.status = 'active') THEN 1 ELSE 0 END AS is_sa_student," : "0 AS is_sa_student,") . "
        " . ($has_school_year_column ? "COALESCE(NULLIF(TRIM(s.school_year), ''), '-') AS school_year," : "'-' AS school_year,") . "
        " . ($has_semester_column ? "COALESCE(NULLIF(TRIM(s.semester), ''), '-') AS semester," : "'-' AS semester,") . "
        CASE
            WHEN EXISTS (
                SELECT 1 FROM attendances a_live
                WHERE a_live.student_id = s.id
                  AND a_live.attendance_date = CURDATE()
                  AND (
                        (a_live.morning_time_in IS NOT NULL AND a_live.morning_time_out IS NULL)
                     OR (a_live.afternoon_time_in IS NOT NULL AND a_live.afternoon_time_out IS NULL)
                  )
            ) THEN 1
            ELSE 0
        END as live_clock_status,
        s.biometric_registered,
        CASE
            WHEN {$biometric_ready_condition} THEN 1
            ELSE 0
        END as biometric_ready,
        s.created_at,
        COALESCE(NULLIF(u_student.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
        c.name as course_name,
        d.name as department_name,
        COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '-') AS section_name,
        c.id as course_id,
        i.supervisor_id,
        i.coordinator_id,
        COALESCE(
            NULLIF(TRIM(CONCAT_WS(' ', sup.first_name, sup.middle_name, sup.last_name)), ''),
            NULLIF(TRIM(sup_user.name), ''),
            NULLIF(NULLIF(TRIM(s.supervisor_name), ''), '-'),
            '-'
        ) AS supervisor_name,
        COALESCE(
            NULLIF(TRIM(CONCAT_WS(' ', coor.first_name, coor.middle_name, coor.last_name)), ''),
            NULLIF(TRIM(coor_user.name), ''),
            NULLIF(NULLIF(TRIM(s.coordinator_name), ''), '-'),
            " . ($hasCoordinatorCourses ? "NULLIF(TRIM(course_coor.coordinator_name), '')," : "") . "
            '-'
        ) AS coordinator_name
    FROM students s
    LEFT JOIN users u_student ON s.user_id = u_student.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN sections sec ON s.section_id = sec.id
    LEFT JOIN internships i ON i.id = (
        SELECT i_latest.id
        FROM internships i_latest
        WHERE i_latest.student_id = s.id
          AND i_latest.status = 'ongoing'
        ORDER BY i_latest.id DESC
        LIMIT 1
    )
    LEFT JOIN supervisors sup ON sup.id = COALESCE(i.supervisor_id, s.supervisor_id)
    LEFT JOIN users sup_user ON sup_user.id = sup.user_id
    LEFT JOIN coordinators coor ON coor.id = COALESCE(i.coordinator_id, s.coordinator_id)
    LEFT JOIN users coor_user ON coor_user.id = coor.user_id
    {$coordinator_course_join_sql}
    LEFT JOIN departments d ON d.id = COALESCE(i.department_id, s.department_id)
    " . (count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "") . "
    ORDER BY s.first_name ASC
    LIMIT 100
";
$students_result = $db->query($students_query);
$students = [];
if ($students_result && $students_result->num_rows > 0) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Helper function to get status badge
function getStatusBadge($status) {
    $is_active = ((int)$status === 1);
    $label = $is_active ? 'Active' : 'Inactive';
    $class = $is_active ? 'is-active' : 'is-inactive';
    return '<span class="app-students-status-pill ' . $class . '">' . $label . '</span>';
}

// Helper function to format date
function formatDate($date) {
    if ($date) {
        return date('M d, Y h:i A', strtotime($date));
    }
    return '-';
}

function resolve_profile_image_url(string $profilePath, int $userId = 0): ?string {
    $resolved = biotern_avatar_public_src($profilePath, $userId);
    if ($resolved === '') {
        return null;
    }
    return $resolved;
}

$selected_section_label = 'ALL';
if ($filter_section > 0) {
    foreach ($sections as $sec) {
        if ((int)$sec['id'] === (int)$filter_section) {
            $selected_section_label = biotern_format_section_label((string)($sec['code'] ?? ''), (string)($sec['name'] ?? ''));
            break;
        }
    }
}

$page_title = 'Students List';
$page_body_class = 'students-page mobile-bottom-nav';
$page_styles = array(
    'assets/css/modules/management/management-students-shared.css',
    'assets/css/modules/management/management-students.css'
);
$page_scripts = array(
    'assets/js/modules/management/students-page-runtime.js',
    'assets/js/theme-customizer-init.min.js',
);
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
    <section class="student-list-print-sheet app-students-print-sheet">
        <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" data-hide-onerror="1">
        <div class="header">
            <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
            <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
            <div class="tel">Telefax No.: (045) 624-0215</div>
        </div>
        <div class="print-title">STUDENT LIST</div>
        <div class="print-meta"><strong>FILTER:</strong> <span data-students-print-filter><?php echo htmlspecialchars($selected_section_label); ?></span></div>
        <table>
            <thead>
                <tr>
                    <th class="col-index">#</th>
                    <th>School ID</th>
                    <th>Student Name</th>
                    <th>Academic</th>
                    <th>Section</th>
                    <th>Mentors</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($print_students)): ?>
                    <?php foreach ($print_students as $i => $student): ?>
                        <?php
                        $print_student_name = trim((string)($student['first_name'] ?? '') . ' ' . (string)($student['middle_name'] ?? '') . ' ' . (string)($student['last_name'] ?? ''));
                        $print_academic_parts = array_filter([
                            (string)($student['course_name'] ?? 'N/A'),
                            (string)($student['school_year'] ?? ''),
                            (string)($student['semester'] ?? ''),
                        ], static function ($value) {
                            $value = trim((string)$value);
                            return $value !== '' && $value !== '-';
                        });
                        $print_section = biotern_format_section_code((string)($student['section_name'] ?? '-'));
                        $print_supervisor = trim((string)($student['supervisor_name'] ?? '-'));
                        $print_coordinator = trim((string)($student['coordinator_name'] ?? '-'));
                        ?>
                        <tr>
                            <td class="col-index"><?php echo (int)$i + 1; ?></td>
                            <td><?php echo htmlspecialchars((string)($student['student_id'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($print_student_name !== '' ? $print_student_name : '-'); ?></td>
                            <td><?php echo htmlspecialchars(!empty($print_academic_parts) ? implode(' / ', $print_academic_parts) : '-'); ?></td>
                            <td><?php echo htmlspecialchars($print_section); ?></td>
                            <td><?php echo htmlspecialchars('Supervisor: ' . ($print_supervisor !== '' ? $print_supervisor : '-') . ' | Coordinator: ' . ($print_coordinator !== '' ? $print_coordinator : '-')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td class="col-index">1</td>
                        <td colspan="5">No students found for current filter.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>


            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Students List</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item">Students List</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto app-students-header-actions">
                    <div class="app-table-header-search app-students-table-search">
                        <label class="visually-hidden" for="studentsHeaderSearchInput">Search student list</label>
                        <i class="feather-search" aria-hidden="true"></i>
                        <input type="search" id="studentsHeaderSearchInput" class="form-control" placeholder="Search students">
                    </div>
                    <a href="students.php" class="btn btn-sm btn-outline-secondary app-students-reset-btn">
                        <i class="feather-rotate-ccw me-1"></i>
                        <span>Reset</span>
                    </a>
                    <button type="button" class="btn btn-sm filter-toggle-btn mobile-actions-source-hidden" data-bs-toggle="collapse" data-bs-target="#studentsFiltersCollapse" aria-expanded="false" aria-controls="studentsFiltersCollapse">
                        <i class="feather-filter me-1"></i>
                        <span>Filters</span>
                    </button>
                    <button type="button" class="btn btn-sm btn-light-brand page-header-actions-toggle" aria-expanded="false" aria-controls="studentsActionsMenu">
                        <i class="feather-grid me-1"></i>
                        <span>Actions</span>
                    </button>
                    <div class="page-header-actions app-students-actions-panel" id="studentsActionsMenu">
                        <div class="dashboard-actions-panel">
                            <div class="dashboard-actions-meta">
                                <span class="text-muted fs-12">Quick Actions</span>
                            </div>
                            <div class="dashboard-actions-grid page-header-right-items-wrapper">
                            <button type="button" class="btn btn-light-brand" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                <i class="feather-bar-chart me-2"></i>
                                <span>Statistics</span>
                            </button>
                            <div class="dropdown">
                                <a class="btn btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside" role="button" aria-label="Export options">
                                    <i class="feather-paperclip me-2"></i>
                                    <span>Export</span>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="javascript:void(0);" class="dropdown-item js-export" data-export="pdf">
                                        <i class="bi bi-filetype-pdf me-3"></i>
                                        <span>PDF</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item js-export" data-export="csv">
                                        <i class="bi bi-filetype-csv me-3"></i>
                                        <span>CSV</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item js-export" data-export="xml">
                                        <i class="bi bi-filetype-xml me-3"></i>
                                        <span>XML</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item js-export" data-export="txt">
                                        <i class="bi bi-filetype-txt me-3"></i>
                                        <span>Text</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item js-export" data-export="excel">
                                        <i class="bi bi-filetype-exe me-3"></i>
                                        <span>Excel</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="javascript:void(0);" class="dropdown-item js-print-page">
                                        <i class="bi bi-printer me-3"></i>
                                        <span>Print</span>
                                    </a>
                                </div>
                            </div>
                            <button type="button" class="btn btn-light js-print-page">
                                <i class="feather-printer me-2"></i>
                                <span>Print List</span>
                            </button>
                            <button type="button" class="btn btn-light d-none js-print-selected" id="printSelectedStudents" aria-hidden="true">
                                <i class="feather-printer me-2"></i>
                                <span>Print Selected</span>
                            </button>
                            <a href="students-create.php" class="btn btn-primary">
                                <i class="feather-plus me-2"></i>
                                <span>Create Students</span>
                            </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (is_array($studentsFlash) && !empty($studentsFlash['message'])): ?>
                <div class="alert alert-<?php echo htmlspecialchars((string)($studentsFlash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?> mb-3">
                    <?php echo htmlspecialchars((string)$studentsFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div id="studentsFiltersCollapse" class="collapse">
                <div class="row mb-3 px-3">
                    <div class="col-12">
                        <div class="filter-panel">
                            <form method="GET" class="filter-form app-students-filter-form row g-2 align-items-end" id="studentsFilterForm">
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-school-year">School Year</label>
                                    <select id="filter-school-year" name="school_year" class="form-control" data-ui-select="custom">
                                        <option value="">-- All School Years --</option>
                                        <?php foreach ($school_year_options as $school_year): ?>
                                            <option value="<?php echo htmlspecialchars($school_year, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_school_year === $school_year ? 'selected' : ''; ?>><?php echo htmlspecialchars($school_year, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-semester">Semester</label>
                                    <select id="filter-semester" name="semester" class="form-control" data-ui-select="custom">
                                        <option value="">-- All Semesters --</option>
                                        <?php foreach ($semester_options as $semester_option): ?>
                                            <option value="<?php echo htmlspecialchars($semester_option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_semester === $semester_option ? 'selected' : ''; ?>><?php echo htmlspecialchars($semester_option, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-date">Date</label>
                                    <input id="filter-date" type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($filter_date, ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-course">Course</label>
                                    <select id="filter-course" name="course_id" class="form-control" data-ui-select="custom">
                                        <option value="0">-- All Courses --</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo (int)$course['id']; ?>" <?php echo $filter_course == $course['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($course['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-department">Department</label>
                                    <select id="filter-department" name="department_id" class="form-control" data-ui-select="custom">
                                        <option value="0">-- All Departments --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo (int)$dept['id']; ?>" <?php echo $filter_department == $dept['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-section">Section</label>
                                    <select id="filter-section" name="section_id" class="form-control" data-ui-select="custom">
                                        <option value="0">-- All Sections --</option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo (int)$section['id']; ?>" <?php echo $filter_section == $section['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($section['section_label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-supervisor">Supervisor</label>
                                    <select id="filter-supervisor" name="supervisor" class="form-control" data-ui-select="custom">
                                        <option value="">-- Any Supervisor --</option>
                                        <?php foreach ($supervisors as $sup): ?>
                                            <option value="<?php echo htmlspecialchars($sup, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_supervisor == $sup ? 'selected' : ''; ?>><?php echo htmlspecialchars($sup, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-coordinator">Coordinator</label>
                                    <select id="filter-coordinator" name="coordinator" class="form-control" data-ui-select="custom">
                                        <option value="">-- Any Coordinator --</option>
                                        <?php foreach ($coordinators as $coor): ?>
                                            <option value="<?php echo htmlspecialchars($coor, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $filter_coordinator == $coor ? 'selected' : ''; ?>><?php echo htmlspecialchars($coor, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6">
                                    <label class="form-label" for="filter-status">Status</label>
                                    <select id="filter-status" name="status" class="form-control" data-ui-select="custom">
                                        <option value="-1" <?php echo $filter_status < 0 ? 'selected' : ''; ?>>-- All Statuses --</option>
                                        <option value="1" <?php echo $filter_status === 1 ? 'selected' : ''; ?>>Active</option>
                                        <option value="0" <?php echo $filter_status === 0 ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!--! Statistics !-->
            <div id="collapseOne" class="accordion-collapse collapse page-header-collapse">
                <div class="accordion-body pb-2">
                    <div class="row">
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-users"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Total Students</span>
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['total_students'] ? $stats['total_students'] : '0'; ?></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-user-check"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Active Students</span>
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['active_students'] ? $stats['active_students'] : '0'; ?></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-user-minus"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Inactive Students</span>
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['inactive_students'] ? $stats['inactive_students'] : '0'; ?></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xxl-3 col-md-6">
                            <div class="card stretch stretch-full">
                                <div class="card-body">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text avatar-xl rounded">
                                                <i class="feather-check-circle"></i>
                                            </div>
                                            <a href="javascript:void(0);" class="fw-bold d-block">
                                                <span class="text-truncate-1-line">Biometric Registered</span>
                                                <span class="fs-24 fw-bolder d-block"><?php echo $stats['biometric_registered'] ? $stats['biometric_registered'] : '0'; ?></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content app-students-main-content">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="app-students-table-card app-data-card app-data-toolbar">
                                <div class="table-responsive students-table-wrap app-data-table-wrap">
                                    <table class="table table-hover app-students-list-table app-data-table" id="customerList">
                                        <thead>
                                            <tr>
                                                <th class="wd-30">
                                                    <div class="btn-group mb-1">
                                                        <div class="custom-control custom-checkbox ms-1">
                                                            <input type="checkbox" class="custom-control-input" id="checkAllStudent">
                                                            <label class="custom-control-label" for="checkAllStudent"></label>
                                                        </div>
                                                    </div>
                                                </th>
                                                <th>Student</th>
                                                <th>Academic</th>
                                                <th>Mentors</th>
                                                <th>Activity</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($students) > 0): ?>
                                                <?php foreach ($students as $index => $student): ?>
                                                    <?php
                                                    $student_name = trim((string)($student['first_name'] . ' ' . $student['last_name']));
                                                    $student_id_label = (string)($student['student_id'] ?? '-');
                                                    $course_name = (string)($student['course_name'] ?? 'N/A');
                                                    $department_name = trim((string)($student['department_name'] ?? ''));
                                                    $section_name = biotern_format_section_code((string)($student['section_name'] ?? '-'));
                                                    $supervisor_name = (string)($student['supervisor_name'] ?? '-');
                                                    $coordinator_name = (string)($student['coordinator_name'] ?? '-');
                                                    $last_logged = formatDate($student['created_at']);
                                                    $email_value = trim((string)($student['email'] ?? ''));
                                                    $phone_value = trim((string)($student['phone'] ?? ''));
                                                    $biometric_ready = ((int)($student['biometric_ready'] ?? 0) === 1);
                                                    $track_key = strtolower(trim((string)($student['assignment_track'] ?? 'internal')));
                                                    if (!in_array($track_key, ['internal', 'external'], true)) {
                                                        $track_key = 'internal';
                                                    }
                                                    $track_label = ucfirst($track_key);
                                                    $print_full_name = trim(implode(' ', array_filter([
                                                        (string)($student['first_name'] ?? ''),
                                                        (string)($student['middle_name'] ?? ''),
                                                        (string)($student['last_name'] ?? ''),
                                                    ], static function ($value) {
                                                        return trim((string)$value) !== '';
                                                    })));
                                                    $print_academic_label = implode(' / ', array_filter([
                                                        $course_name,
                                                        (string)($student['school_year'] ?? ''),
                                                        (string)($student['semester'] ?? ''),
                                                    ], static function ($value) {
                                                        $value = trim((string)$value);
                                                        return $value !== '' && $value !== '-';
                                                    }));
                                                    $student_dom_key = (int)$student['id'] . '_' . (int)$index;
                                                    ?>
                                                    <tr
                                                        class="single-item app-students-table-row"
                                                        data-export-name="<?php echo htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-student-id="<?php echo htmlspecialchars($student_id_label, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-course="<?php echo htmlspecialchars($course_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-section="<?php echo htmlspecialchars($section_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-supervisor="<?php echo htmlspecialchars($supervisor_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-coordinator="<?php echo htmlspecialchars($coordinator_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-last-logged="<?php echo htmlspecialchars($last_logged, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-export-status="<?php echo ((int)($student['live_clock_status'] ?? 0) === 1) ? 'Active' : 'Inactive'; ?>"
                                                        data-print-student-id="<?php echo htmlspecialchars($student_id_label, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-print-student-name="<?php echo htmlspecialchars($print_full_name !== '' ? $print_full_name : $student_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-print-academic="<?php echo htmlspecialchars($print_academic_label !== '' ? $print_academic_label : '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-print-section="<?php echo htmlspecialchars($section_name, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-print-mentors="<?php echo htmlspecialchars('Supervisor: ' . ($supervisor_name !== '' ? $supervisor_name : '-') . ' | Coordinator: ' . ($coordinator_name !== '' ? $coordinator_name : '-'), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-print-last-name="<?php echo htmlspecialchars((string)($student['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-print-first-name="<?php echo htmlspecialchars((string)($student['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-print-middle-name="<?php echo htmlspecialchars((string)($student['middle_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    >
                                                        <td>
                                                            <div class="item-checkbox ms-1">
                                                                <div class="custom-control custom-checkbox">
                                                                    <input type="checkbox" class="custom-control-input checkbox" id="checkBox_<?php echo htmlspecialchars($student_dom_key, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <label class="custom-control-label" for="checkBox_<?php echo htmlspecialchars($student_dom_key, ENT_QUOTES, 'UTF-8'); ?>"></label>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td data-label="Student">
                                                            <a href="students-view.php?id=<?php echo $student['id']; ?>" class="app-students-student-block">
                                                                <div class="avatar-image avatar-md">
                                                                    <?php
                                                                    $pp = $student['profile_picture'] ?? '';
                                                                    $pp_url = resolve_profile_image_url($pp, (int)($student['user_id'] ?? 0));
                                                                    if ($pp_url !== null) {
                                                                        echo '<img src="' . htmlspecialchars($pp_url) . '" alt="" class="img-fluid">';
                                                                    } else {
                                                                        echo '<img src="assets/images/avatar/' . (($index % 5) + 1) . '.png" alt="" class="img-fluid">';
                                                                    }
                                                                    ?>
                                                                </div>
                                                                <div class="app-students-student-copy">
                                                                    <span class="app-students-student-name"><?php echo htmlspecialchars($student_name); ?><?php if ((int)($student['is_sa_student'] ?? 0) === 1): ?> <span class="badge bg-soft-primary text-primary ms-1">SA</span><?php endif; ?></span>
                                                                    <span class="app-students-student-meta"><?php echo htmlspecialchars($student_id_label); ?></span>
                                                                </div>
                                                            </a>
                                                            <div class="collapse app-students-inline-collapse" id="studentRowDetails<?php echo htmlspecialchars($student_dom_key, ENT_QUOTES, 'UTF-8'); ?>">
                                                                <div class="app-students-inline-details">
                                                                    <div class="app-students-inline-detail-item">
                                                                        <span class="app-students-inline-detail-label">Track</span>
                                                                        <span class="app-students-section-pill"><?php echo htmlspecialchars($track_label); ?></span>
                                                                    </div>
                                                                    <div class="app-students-inline-detail-item">
                                                                        <span class="app-students-inline-detail-label">Section</span>
                                                                        <span class="app-students-section-pill"><?php echo htmlspecialchars($section_name); ?></span>
                                                                    </div>
                                                                    <div class="app-students-inline-detail-item">
                                                                        <span class="app-students-inline-detail-label">Email</span>
                                                                        <span class="app-students-inline-detail-value"><?php echo htmlspecialchars($email_value !== '' ? $email_value : '-'); ?></span>
                                                                    </div>
                                                                    <div class="app-students-inline-detail-item">
                                                                        <span class="app-students-inline-detail-label">Phone</span>
                                                                        <span class="app-students-inline-detail-value"><?php echo htmlspecialchars($phone_value !== '' ? $phone_value : '-'); ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td data-label="Academic">
                                                            <div class="app-students-cell-stack">
                                                                <span class="app-students-cell-title">Course</span>
                                                                <span class="app-students-cell-value"><?php echo htmlspecialchars($course_name); ?></span>
                                                                <span class="app-students-cell-meta"><?php echo htmlspecialchars($department_name !== '' ? $department_name : ('Section ' . $section_name)); ?></span>
                                                            </div>
                                                        </td>
                                                        <td data-label="Mentors">
                                                            <div class="app-students-cell-stack">
                                                                <span class="app-students-mentor-name">Supervisor: <?php echo htmlspecialchars($supervisor_name !== '' ? $supervisor_name : 'Not Assigned'); ?></span>
                                                                <span class="app-students-mentor-name">Coordinator: <?php echo htmlspecialchars($coordinator_name !== '' ? $coordinator_name : 'Not Assigned'); ?></span>
                                                            </div>
                                                        </td>
                                                        <td data-label="Activity">
                                                            <div class="app-students-cell-stack">
                                                                <span class="app-students-cell-title">Last Logged</span>
                                                                <span class="app-students-cell-value"><?php echo htmlspecialchars($last_logged); ?></span>
                                                                <span class="app-students-cell-meta"><?php echo $biometric_ready ? 'Biometric registered' : 'Biometric not registered'; ?></span>
                                                            </div>
                                                        </td>
                                                        <td data-label="Status">
                                                            <div class="app-students-status-block">
                                                                <?php echo getStatusBadge($student['live_clock_status']); ?>
                                                                <span class="app-students-biometric-pill <?php echo $track_key === 'external' ? 'is-ready' : 'is-missing'; ?>">
                                                                    <?php echo htmlspecialchars($track_label . ' Track'); ?>
                                                                </span>
                                                                <span class="app-students-biometric-pill <?php echo $biometric_ready ? 'is-ready' : 'is-missing'; ?>">
                                                                    <?php echo $biometric_ready ? 'Biometric Ready' : 'Biometric Missing'; ?>
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td data-label="Actions">
                                                            <div class="app-students-row-actions">
                                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#studentRowDetails<?php echo htmlspecialchars($student_dom_key, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false" aria-controls="studentRowDetails<?php echo htmlspecialchars($student_dom_key, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    Details
                                                                </button>
                                                                <?php if (!$is_student_user): ?>
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-sm btn-light app-students-menu-toggle"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#studentsActionModal"
                                                                        data-student-action-trigger
                                                                        data-student-id="<?php echo (int)$student['id']; ?>"
                                                                        data-student-name="<?php echo htmlspecialchars(trim((string)$student['first_name'] . ' ' . (string)$student['last_name']), ENT_QUOTES, 'UTF-8'); ?>"
                                                                        data-student-track="<?php echo htmlspecialchars($track_key, ENT_QUOTES, 'UTF-8'); ?>"
                                                                        data-student-department-id="<?php echo (int)($student['department_id'] ?? 0); ?>"
                                                                        data-student-supervisor-id="<?php echo (int)($student['student_supervisor_ref_id'] ?? 0); ?>"
                                                                        data-student-supervisor-name="<?php echo htmlspecialchars((string)($student['supervisor_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                        data-student-coordinator-name="<?php echo htmlspecialchars((string)($student['coordinator_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                        data-student-email="<?php echo htmlspecialchars((string)($student['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                        data-student-department-name="<?php echo htmlspecialchars((string)($student['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                    >
                                                                        <i class="feather feather-more-horizontal"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="app-students-mobile-list app-mobile-list">
                                    <?php if (count($students) > 0): ?>
                                        <?php foreach ($students as $index => $student): ?>
                                            <?php
                                            $status_raw = strtolower(trim((string)($student['live_clock_status'] ?? '')));
                                            $status_class = 'status-unknown';
                                            $track_key = strtolower(trim((string)($student['assignment_track'] ?? 'internal')));
                                            if (!in_array($track_key, ['internal', 'external'], true)) {
                                                $track_key = 'internal';
                                            }
                                            $track_label = ucfirst($track_key);
                                            if (in_array($status_raw, ['1', 'active', 'present', 'online'], true) || strpos($status_raw, 'active') !== false) {
                                                $status_class = 'status-active';
                                            } elseif (in_array($status_raw, ['0', 'inactive', 'offline', 'absent'], true) || strpos($status_raw, 'inactive') !== false) {
                                                $status_class = 'status-inactive';
                                            }
                                            ?>
                                            <details class="app-student-mobile-item app-mobile-item">
                                                <summary class="app-student-mobile-summary app-mobile-summary">
                                                    <div class="app-student-mobile-summary-main app-mobile-summary-main">
                                                        <div class="avatar-image avatar-md">
                                                            <?php
                                                            $pp = $student['profile_picture'] ?? '';
                                                            $pp_url = resolve_profile_image_url($pp, (int)($student['user_id'] ?? 0));
                                                            if ($pp_url !== null) {
                                                                echo '<img src="' . htmlspecialchars($pp_url) . '" alt="" class="img-fluid">';
                                                            } else {
                                                                echo '<img src="assets/images/avatar/' . (($index % 5) + 1) . '.png" alt="" class="img-fluid">';
                                                            }
                                                            ?>
                                                        </div>
                                                        <div class="app-student-mobile-summary-text app-mobile-summary-text">
                                                            <span class="app-student-mobile-name app-mobile-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
                                                            <span class="app-student-mobile-subtext app-mobile-subtext">ID: <?php echo htmlspecialchars((string)$student['student_id']); ?> &middot; <?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?> &middot; <?php echo htmlspecialchars($track_label); ?></span>
                                                        </div>
                                                    </div>
                                                    <span class="app-student-mobile-status-dot <?php echo htmlspecialchars($status_class); ?>" aria-hidden="true"></span>
                                                </summary>
                                                <div class="app-student-mobile-details app-mobile-details">
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Student ID</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars((string)$student['student_id']); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Course</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Section</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars($student['section_name'] ?? '-'); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Track</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars($track_label); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Supervisor</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars($student['supervisor_name'] ?? '-'); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Coordinator</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo htmlspecialchars($student['coordinator_name'] ?? '-'); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Last Logged</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo formatDate($student['created_at']); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-row app-mobile-row">
                                                        <span class="app-student-mobile-label app-mobile-label">Status</span>
                                                        <span class="app-student-mobile-value app-mobile-value"><?php echo getStatusBadge($student['live_clock_status']); ?></span>
                                                    </div>
                                                    <div class="app-student-mobile-actions">
                                                        <a href="students-view.php?id=<?php echo (int)$student['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                                        <?php if (!$is_student_user): ?>
                                                            <a href="students-edit.php?id=<?php echo (int)$student['id']; ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline-secondary btn-sm app-students-menu-toggle"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#studentsActionModal"
                                                                data-student-action-trigger
                                                                data-student-id="<?php echo (int)$student['id']; ?>"
                                                                data-student-name="<?php echo htmlspecialchars(trim((string)$student['first_name'] . ' ' . (string)$student['last_name']), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-student-track="<?php echo htmlspecialchars($track_key, ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-student-department-id="<?php echo (int)($student['department_id'] ?? 0); ?>"
                                                                data-student-supervisor-id="<?php echo (int)($student['student_supervisor_ref_id'] ?? 0); ?>"
                                                                data-student-supervisor-name="<?php echo htmlspecialchars((string)($student['supervisor_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-student-coordinator-name="<?php echo htmlspecialchars((string)($student['coordinator_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-student-email="<?php echo htmlspecialchars((string)($student['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                                data-student-department-name="<?php echo htmlspecialchars((string)($student['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            >More</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </details>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="app-students-mobile-empty text-muted">No students found.</div>
                                    <?php endif; ?>
                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div> <!-- .nxl-content -->
</main>
<?php if (!$is_student_user): ?>
<div class="modal fade biotern-popup-modal" id="studentsActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Student Actions</h5>
                    <div class="text-muted small" data-student-action-summary>Select the assignment details for this student.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" class="d-grid gap-2 mb-3" id="studentsActionAssignForm">
                    <input type="hidden" name="student_action" value="assign_track">
                    <input type="hidden" name="student_id" value="">
                    <div class="small text-muted border rounded p-2 mb-1" data-student-action-current>
                        Current assignment details will show here.
                    </div>
                    <select name="assignment_track" class="form-select" required>
                        <option value="internal">Internal</option>
                        <option value="external">External</option>
                    </select>
                    <select name="department_id" class="form-select" required>
                        <option value="0">Select department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo (int)$department['id']; ?>"><?php echo htmlspecialchars((string)$department['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="supervisor_id" class="form-select" required>
                        <option value="0">Select supervisor</option>
                        <?php foreach ($supervisorOptions as $supervisorOption): ?>
                            <option value="<?php echo (int)$supervisorOption['id']; ?>"><?php echo htmlspecialchars((string)$supervisorOption['supervisor_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required>
                    <button type="submit" class="btn btn-primary">Save Assignment</button>
                    <small class="text-muted">Coordinator follows the student's course automatically.</small>
                    <small class="text-muted" data-student-action-selected>Assigned department, supervisor, and coordinator will appear here.</small>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>








