<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/lib/offices.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
/** @var mysqli $conn */

require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
biotern_offices_ensure_schema($conn);

$current_role = strtolower(trim((string) (
    $_SESSION['role'] ??
    $_SESSION['user_role'] ??
    $_SESSION['account_role'] ??
    $_SESSION['user_type'] ??
    $_SESSION['type'] ??
    ''
)));
$can_edit_sensitive_hours = in_array($current_role, ['admin', 'coordinator', 'supervisor'], true);
$can_edit_hours = true;
$can_admin_reset_student_password = ($current_role === 'admin');

function biotern_users_column_exists(mysqli $conn, string $column): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}

function biotern_student_edit_ensure_profile_picture_table(mysqli $conn): bool
{
    return (bool)$conn->query("CREATE TABLE IF NOT EXISTS user_profile_pictures (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NOT NULL,
        image_mime VARCHAR(64) NOT NULL,
        image_data LONGBLOB NOT NULL,
        image_size INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_user_profile_picture (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function biotern_student_edit_save_profile_picture_blob(mysqli $conn, int $userId, string $mime, string $binary): bool
{
    if ($userId <= 0 || $mime === '' || $binary === '') {
        return false;
    }
    if (!biotern_student_edit_ensure_profile_picture_table($conn)) {
        return false;
    }

    $size = strlen($binary);
    $stmt = $conn->prepare("INSERT INTO user_profile_pictures (user_id, image_mime, image_data, image_size, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE image_mime = VALUES(image_mime), image_data = VALUES(image_data), image_size = VALUES(image_size), updated_at = NOW()");
    if (!$stmt) {
        return false;
    }

    $blob = '';
    $stmt->bind_param('isbi', $userId, $mime, $blob, $size);
    $stmt->send_long_data(2, $binary);
    $ok = $stmt->execute();
    $stmt->close();

    return (bool)$ok;
}

function biotern_student_edit_ensure_runtime_dir(string $path): bool
{
    if (is_dir($path)) {
        return true;
    }

    $writable_base = $path;
    while (!is_dir($writable_base)) {
        $next = dirname($writable_base);
        if ($next === $writable_base) {
            break;
        }
        $writable_base = $next;
    }

    if (!is_dir($writable_base) || !is_writable($writable_base)) {
        return false;
    }

    return @mkdir($path, 0755, true) || is_dir($path);
}

function biotern_student_edit_table_exists(mysqli $conn, string $table): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    $safeTable = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safeTable}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    return $exists;
}

function biotern_student_edit_column_exists(mysqli $conn, string $table, string $column): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }

    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    return $exists;
}

function biotern_student_edit_ensure_column(mysqli $conn, string $table, string $column, string $definition): bool
{
    if (!biotern_student_edit_table_exists($conn, $table)) {
        return false;
    }
    if (biotern_student_edit_column_exists($conn, $table, $column)) {
        return true;
    }

    $ok = @$conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    return $ok === true || biotern_student_edit_column_exists($conn, $table, $column);
}

function biotern_student_edit_setting(mysqli $conn, string $key, string $fallback): string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        $tableCheck = $conn->query("SHOW TABLES LIKE 'system_settings'");
        if ($tableCheck instanceof mysqli_result && $tableCheck->num_rows > 0) {
            $stmt = $conn->prepare("SELECT `key`, `value` FROM system_settings WHERE category = 'students'");
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $cache[(string)($row['key'] ?? '')] = trim((string)($row['value'] ?? ''));
                }
                $stmt->close();
            }
        }
    }

    $value = trim((string)($cache[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
}

// Ensure student-edit can run against older deployed schemas.
$student_edit_columns = [
    'profile_picture' => 'VARCHAR(255) DEFAULT NULL',
    'phone' => 'VARCHAR(50) DEFAULT NULL',
    'date_of_birth' => 'DATE DEFAULT NULL',
    'gender' => 'VARCHAR(30) DEFAULT NULL',
    'address' => 'VARCHAR(255) DEFAULT NULL',
    'emergency_contact' => 'VARCHAR(255) DEFAULT NULL',
    'department_id' => 'INT DEFAULT NULL',
    'section_id' => 'INT DEFAULT NULL',
    'internal_total_hours' => 'INT(11) DEFAULT NULL',
    'internal_total_hours_remaining' => 'INT(11) DEFAULT NULL',
    'external_total_hours' => 'INT(11) DEFAULT NULL',
    'external_total_hours_remaining' => 'INT(11) DEFAULT NULL',
    'assignment_track' => "VARCHAR(20) NOT NULL DEFAULT 'internal'",
    'external_start_allowed' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'biometric_registered' => 'TINYINT(1) NOT NULL DEFAULT 0',
    'biometric_registered_at' => 'DATETIME DEFAULT NULL',
    'supervisor_name' => 'VARCHAR(255) DEFAULT NULL',
    'coordinator_name' => 'VARCHAR(255) DEFAULT NULL',
];

foreach ($student_edit_columns as $column => $definition) {
    biotern_student_edit_ensure_column($conn, 'students', $column, $definition);
}
biotern_student_edit_ensure_column($conn, 'users', 'profile_picture', 'VARCHAR(255) DEFAULT NULL');
biotern_student_edit_ensure_column($conn, 'users', 'updated_at', 'DATETIME DEFAULT NULL');
biotern_student_edit_ensure_column($conn, 'supervisors', 'user_id', 'INT DEFAULT NULL');
biotern_student_edit_ensure_column($conn, 'supervisors', 'course_id', 'INT DEFAULT NULL');
biotern_student_edit_ensure_column($conn, 'supervisors', 'department_id', 'INT DEFAULT NULL');
biotern_student_edit_ensure_column($conn, 'coordinators', 'user_id', 'INT DEFAULT NULL');
biotern_student_edit_ensure_column($conn, 'coordinators', 'department_id', 'INT DEFAULT NULL');
biotern_student_edit_ensure_column($conn, 'internships', 'office_id', 'BIGINT UNSIGNED NULL');

$student_edit_default_internal_hours = max(0, (int)biotern_student_edit_setting($conn, 'default_internal_hours', '140'));
$student_edit_default_external_hours = max(0, (int)biotern_student_edit_setting($conn, 'default_external_hours', '250'));

// Get student ID from URL parameter
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id == 0) {
    header('Location: idnotfound-404.php?source=students-edit&id=' . urlencode($student_id));
    exit;
}

// Use project-root uploads so files resolve from both legacy and organized routes.
$project_root = dirname(__DIR__);
$uploads_dir = $project_root . '/uploads/profile_pictures';
$uploads_available = biotern_student_edit_ensure_runtime_dir($uploads_dir);
// Ensure other upload folders exist
$uploads_manual_dtr = $project_root . '/uploads/manual_dtr';
$uploads_available = biotern_student_edit_ensure_runtime_dir($uploads_manual_dtr) && $uploads_available;
$uploads_documents = $project_root . '/uploads/documents';
$uploads_available = biotern_student_edit_ensure_runtime_dir($uploads_documents) && $uploads_available;

// Fetch Student Details
$student_query = "
    SELECT 
        s.id,
        s.user_id,
        s.student_id,
        COALESCE(NULLIF(u_student.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
        s.first_name,
        s.last_name,
        s.middle_name,
        s.email,
        s.department_id,
        s.section_id,
        s.phone,
        s.date_of_birth,
        s.gender,
        s.address,
        s.emergency_contact,
        s.internal_total_hours,
        s.internal_total_hours_remaining,
        s.external_total_hours,
        s.external_total_hours_remaining,
        s.assignment_track,
        COALESCE(s.external_start_allowed, 0) AS external_start_allowed,
        s.status,
        s.biometric_registered,
        s.biometric_registered_at,
        s.created_at,
        s.updated_at,
        s.supervisor_name,
        s.coordinator_name,
        c.name as course_name,
        c.id as course_id,
        i.id as internship_id,
        i.office_id as internship_office_id,
        i.company_name as internship_company_name,
        i.supervisor_id as internship_supervisor_id,
        i.coordinator_id as internship_coordinator_id,
        sv.id as supervisor_id,
        co.id as coordinator_id
    FROM students s
    LEFT JOIN users u_student ON s.user_id = u_student.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
    LEFT JOIN supervisors sv ON (sv.user_id = i.supervisor_id OR sv.id = i.supervisor_id)
    LEFT JOIN coordinators co ON (co.user_id = i.coordinator_id OR co.id = i.coordinator_id)
    WHERE s.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($student_query);
if (!$stmt) {
    http_response_code(500);
    die('Unable to load student record. Database query could not be prepared: ' . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
}
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: idnotfound-404.php?source=students-edit&id=' . urlencode($student_id));
    exit;
}

$student = $result->fetch_assoc();
$student_internal_total_was_empty = !isset($student['internal_total_hours']) || $student['internal_total_hours'] === '' || (is_numeric($student['internal_total_hours']) && (int)$student['internal_total_hours'] <= 0);
$student_external_total_was_empty = !isset($student['external_total_hours']) || $student['external_total_hours'] === '' || (is_numeric($student['external_total_hours']) && (int)$student['external_total_hours'] <= 0);
if ($student_internal_total_was_empty) {
    $student['internal_total_hours'] = $student_edit_default_internal_hours;
}
if ($student_external_total_was_empty) {
    $student['external_total_hours'] = $student_edit_default_external_hours;
}
if (!isset($student['internal_total_hours_remaining']) || $student['internal_total_hours_remaining'] === '' || $student_internal_total_was_empty) {
    $student['internal_total_hours_remaining'] = $student_edit_default_internal_hours;
}
if (!isset($student['external_total_hours_remaining']) || $student['external_total_hours_remaining'] === '' || $student_external_total_was_empty) {
    $student['external_total_hours_remaining'] = $student_edit_default_external_hours;
}

$biometric_registered = (int)($student['biometric_registered'] ?? 0) === 1;
$biometric_finger_id = null;
$biometric_registered_at = $student['biometric_registered_at'] ?? null;
if (!empty($student['user_id']) && biotern_student_edit_table_exists($conn, 'fingerprint_user_map')) {
    $finger_stmt = $conn->prepare('SELECT finger_id, created_at, updated_at FROM fingerprint_user_map WHERE user_id = ? LIMIT 1');
    if ($finger_stmt) {
        $user_id_for_fingerprint = (int)$student['user_id'];
        $finger_stmt->bind_param('i', $user_id_for_fingerprint);
        $finger_stmt->execute();
        $finger_res = $finger_stmt->get_result();
        $finger_row = $finger_res ? $finger_res->fetch_assoc() : null;
        $finger_stmt->close();

        if ($finger_row) {
            $biometric_finger_id = (int)($finger_row['finger_id'] ?? 0);
            $biometric_registered = $biometric_finger_id > 0;
            if ($biometric_registered) {
                $biometric_registered_at = $finger_row['created_at'] ?: ($finger_row['updated_at'] ?? $biometric_registered_at);
            }
        }
    }
}

$needs_biometric_backfill = $biometric_registered
    && ((int)($student['biometric_registered'] ?? 0) !== 1 || trim((string)($student['biometric_registered_at'] ?? '')) === '');
if ($needs_biometric_backfill) {
    $backfill_registered_at = trim((string)($student['biometric_registered_at'] ?? ''));
    if ($backfill_registered_at === '') {
        $backfill_registered_at = trim((string)($biometric_registered_at ?? ''));
    }
    if ($backfill_registered_at === '') {
        $backfill_registered_at = date('Y-m-d H:i:s');
    }

    $backfill_stmt = $conn->prepare('UPDATE students SET biometric_registered = 1, biometric_registered_at = ? WHERE id = ?');
    if ($backfill_stmt) {
        $backfill_stmt->bind_param('si', $backfill_registered_at, $student_id);
        $backfill_stmt->execute();
        $backfill_stmt->close();
    }
    $student['biometric_registered'] = 1;
    $student['biometric_registered_at'] = $backfill_registered_at;
    $biometric_registered_at = $backfill_registered_at;
}

// Fetch all courses for dropdown (be tolerant of differing schema columns)
$courses = [];
$db_name = defined('DB_NAME') ? (string)DB_NAME : 'biotern_db';
$db_esc = $conn->real_escape_string($db_name);
$has_is_active = false;
$has_status_col = false;
$col_check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $db_esc . "' AND TABLE_NAME = 'courses' AND COLUMN_NAME IN ('is_active','status')");
if ($col_check && $col_check->num_rows) {
    while ($c = $col_check->fetch_assoc()) {
        if ($c['COLUMN_NAME'] === 'is_active') $has_is_active = true;
        if ($c['COLUMN_NAME'] === 'status') $has_status_col = true;
    }
}

$courses_query = "SELECT id, name FROM courses";
if ($has_is_active) {
    $courses_query .= " WHERE is_active = 1";
} elseif ($has_status_col) {
    $courses_query .= " WHERE status = 1";
}
$courses_query .= " ORDER BY name ASC";

$courses_result = $conn->query($courses_query);
if ($courses_result && $courses_result->num_rows > 0) {
    while ($row = $courses_result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Fetch departments for dropdown, including the courses where each department has sections.
$departments = [];
$departments_result = $conn->query("
    SELECT
        d.id,
        d.name,
        GROUP_CONCAT(DISTINCT s.course_id ORDER BY s.course_id SEPARATOR ',') AS course_ids
    FROM departments d
    LEFT JOIN sections s ON s.department_id = d.id
    GROUP BY d.id, d.name
    ORDER BY d.name ASC
");
if ($departments_result && $departments_result->num_rows > 0) {
    while ($row = $departments_result->fetch_assoc()) {
        $departments[] = $row;
    }
}

// Fetch sections for dropdown
$sections = [];
$sections_result = $conn->query("SELECT id, course_id, department_id, code, name FROM sections ORDER BY code ASC, name ASC");
if ($sections_result && $sections_result->num_rows > 0) {
    while ($row = $sections_result->fetch_assoc()) {
        $sections[] = $row;
    }
}

// Fetch supervisors and coordinators strictly from their own tables
$supervisors_query = "
    SELECT 
        id,
        course_id,
        department_id,
        TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS name
    FROM supervisors
    WHERE TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) <> ''
    ORDER BY name ASC
";
$supervisors_result = $conn->query($supervisors_query);
$supervisors = [];
if ($supervisors_result->num_rows > 0) {
    while ($row = $supervisors_result->fetch_assoc()) {
        $supervisors[] = [
            'id' => (int)$row['id'],
            'course_id' => (int)($row['course_id'] ?? 0),
            'department_id' => (int)($row['department_id'] ?? 0),
            'name' => $row['name']
        ];
    }
}

$officeOptions = biotern_offices_all($conn);
$officeSupervisorMap = [];
$officeMapRes = $conn->query('SELECT office_id, supervisor_id FROM supervisor_offices');
if ($officeMapRes) {
    while ($row = $officeMapRes->fetch_assoc()) {
        $officeId = (int)($row['office_id'] ?? 0);
        if ($officeId > 0) {
            $officeSupervisorMap[$officeId][] = (int)($row['supervisor_id'] ?? 0);
        }
    }
}

$coordinators_query = "
    SELECT 
        id,
        TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS name
    FROM coordinators
    WHERE TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) <> ''
    ORDER BY name ASC
";
$coordinators_result = $conn->query($coordinators_query);
$coordinators = [];
if ($coordinators_result->num_rows > 0) {
    while ($row = $coordinators_result->fetch_assoc()) {
        $coordinators[] = [
            'id' => (int)$row['id'],
            'name' => $row['name']
        ];
    }
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lock_conflict_detected = false;
    $lock_conflict_message = '';

    // Handle profile picture upload
    $profile_picture_path = $student['profile_picture'] ?? '';
    $profile_picture_uploaded = false;
    
    $cropped_profile_picture = trim((string)($_POST['profile_picture_cropped'] ?? ''));
    if ($cropped_profile_picture !== '') {
        $linked_user_id = !empty($student['user_id']) ? (int)$student['user_id'] : 0;
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024;

        if (!preg_match('#^data:(image/(?:png|jpeg|jpg|webp|gif));base64,([a-zA-Z0-9+/=\r\n]+)$#', $cropped_profile_picture, $parts)) {
            $error_message = "Invalid cropped profile picture. Please crop the image again.";
        } else {
            $mime_type = strtolower((string)$parts[1]);
            if ($mime_type === 'image/jpg') {
                $mime_type = 'image/jpeg';
            }
            $binary = base64_decode(preg_replace('/\s+/', '', (string)$parts[2]), true);
            $img_info = is_string($binary) && $binary !== '' && function_exists('getimagesizefromstring')
                ? @getimagesizefromstring($binary)
                : false;

            if (!is_string($binary) || $binary === '' || strlen($binary) > $max_file_size || !$img_info || !in_array($mime_type, $allowed_mimes, true)) {
                $error_message = "Invalid cropped profile picture. Allowed types: JPG, PNG, GIF, WEBP up to 5MB.";
            } elseif ($linked_user_id > 0) {
                if (biotern_student_edit_save_profile_picture_blob($conn, $linked_user_id, $mime_type, $binary)) {
                    $profile_picture_path = 'db-avatar';
                    $profile_picture_uploaded = true;
                } else {
                    $error_message = "Failed to save profile picture to user_profile_pictures.";
                }
            } elseif (!$uploads_available) {
                $error_message = "Profile picture uploads are currently unavailable because the upload folder is missing or not writable.";
            } else {
                $ext_map = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];
                $unique_name = 'student_' . $student_id . '_' . time() . '.' . ($ext_map[$mime_type] ?? 'jpg');
                $file_path = $uploads_dir . '/' . $unique_name;
                $destination_dir = dirname($file_path);
                $old_profile_file = $project_root . '/' . ltrim(str_replace('\\', '/', (string)$profile_picture_path), '/');
                if (!empty($profile_picture_path) && file_exists($old_profile_file)) {
                    unlink($old_profile_file);
                }

                if (!is_dir($destination_dir) && !biotern_student_edit_ensure_runtime_dir($destination_dir)) {
                    $error_message = "Failed to upload profile picture because the destination folder is not available on this deployment.";
                } elseif (@file_put_contents($file_path, $binary) !== false) {
                    $profile_picture_path = 'uploads/profile_pictures/' . $unique_name;
                    $profile_picture_uploaded = true;
                } else {
                    $error_message = "Failed to upload profile picture. Please try again.";
                }
            }
        }
    } elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $linked_user_id = !empty($student['user_id']) ? (int)$student['user_id'] : 0;

        // Validate file type and size
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        if (in_array($file_ext, $allowed_types, true) && $file_size <= $max_file_size) {
            $mime_type = '';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime_type = (string)finfo_file($finfo, $file_tmp);
                }
            }
            if ($mime_type === '' || $mime_type === 'application/octet-stream') {
                $img_info = @getimagesize($file_tmp);
                if (is_array($img_info) && !empty($img_info['mime'])) {
                    $mime_type = (string)$img_info['mime'];
                }
            }

            if ($linked_user_id > 0) {
                if (!is_uploaded_file($file_tmp)) {
                    $error_message = "Failed to upload profile picture because the uploaded file was not detected.";
                } elseif (!in_array($mime_type, $allowed_mimes, true)) {
                    $error_message = "Invalid image file. Allowed types: JPG, JPEG, PNG, GIF, WEBP.";
                } else {
                    $binary = @file_get_contents($file_tmp);
                    if (!is_string($binary) || $binary === '') {
                        $error_message = "Failed to read uploaded profile picture.";
                    } elseif (biotern_student_edit_save_profile_picture_blob($conn, $linked_user_id, $mime_type, $binary)) {
                        $profile_picture_path = 'db-avatar';
                        $profile_picture_uploaded = true;
                    } else {
                        $error_message = "Failed to save profile picture to user_profile_pictures.";
                    }
                }
            } elseif (!$uploads_available) {
                $error_message = "Profile picture uploads are currently unavailable because the upload folder is missing or not writable.";
            } else {
                // Create unique filename
                $unique_name = 'student_' . $student_id . '_' . time() . '.' . $file_ext;
                $file_path = $uploads_dir . '/' . $unique_name;
                $destination_dir = dirname($file_path);

                // Delete old profile picture if exists
                $old_profile_file = $project_root . '/' . ltrim(str_replace('\\', '/', (string)$profile_picture_path), '/');
                if (!empty($profile_picture_path) && file_exists($old_profile_file)) {
                    unlink($old_profile_file);
                }

                // Move uploaded file
                if (!is_dir($destination_dir) && !biotern_student_edit_ensure_runtime_dir($destination_dir)) {
                    $error_message = "Failed to upload profile picture because the destination folder is not available on this deployment.";
                } elseif (!is_uploaded_file($file_tmp)) {
                    $error_message = "Failed to upload profile picture because the uploaded file was not detected.";
                } elseif (@move_uploaded_file($file_tmp, $file_path)) {
                    $profile_picture_path = 'uploads/profile_pictures/' . $unique_name;
                    $profile_picture_uploaded = true;
                } else {
                    $error_message = "Failed to upload profile picture. Please try again.";
                }
            }
        } else {
            $error_message = "Invalid file type or file size exceeds 5MB. Allowed types: JPG, JPEG, PNG, GIF, WEBP.";
        }
    }
    
    // Sanitize and validate inputs
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $date_of_birth = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';
    $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $emergency_contact = isset($_POST['emergency_contact']) ? trim($_POST['emergency_contact']) : '';
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : 0;
    $department_id = isset($_POST['department_id']) ? trim((string)$_POST['department_id']) : '';
    $section_id = isset($_POST['section_id']) ? intval($_POST['section_id']) : 0;
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $supervisor_id = isset($_POST['supervisor_id']) && $_POST['supervisor_id'] !== '' ? intval($_POST['supervisor_id']) : null;
    $office_id = isset($_POST['office_id']) && $_POST['office_id'] !== '' ? intval($_POST['office_id']) : null;
    $coordinator_id = isset($_POST['coordinator_id']) && $_POST['coordinator_id'] !== '' ? intval($_POST['coordinator_id']) : null;
    $student_id_code = isset($_POST['student_id']) ? trim($_POST['student_id']) : ($student['student_id'] ?? '');
    $internal_total_hours = isset($_POST['internal_total_hours']) && $_POST['internal_total_hours'] !== '' ? intval($_POST['internal_total_hours']) : null;
    $internal_total_hours_remaining = isset($_POST['internal_total_hours_remaining']) && $_POST['internal_total_hours_remaining'] !== '' ? intval($_POST['internal_total_hours_remaining']) : null;
    $external_total_hours = isset($_POST['external_total_hours']) && $_POST['external_total_hours'] !== '' ? intval($_POST['external_total_hours']) : null;
    $external_total_hours_remaining = isset($_POST['external_total_hours_remaining']) && $_POST['external_total_hours_remaining'] !== '' ? intval($_POST['external_total_hours_remaining']) : null;
    $assignment_track = isset($_POST['assignment_track']) ? trim($_POST['assignment_track']) : 'internal';
    $external_start_allowed = isset($_POST['external_start_allowed']) ? 1 : 0;
    $admin_reset_password = isset($_POST['admin_reset_password']) ? (string)$_POST['admin_reset_password'] : '';
    $admin_reset_password_confirm = isset($_POST['admin_reset_password_confirm']) ? (string)$_POST['admin_reset_password_confirm'] : '';
    $requested_password_reset = ($admin_reset_password !== '' || $admin_reset_password_confirm !== '');
    $original_updated_at = isset($_POST['original_updated_at']) ? trim((string)$_POST['original_updated_at']) : '';

    if (!$can_edit_sensitive_hours) {
        $student_id_code = (string)($student['student_id'] ?? '');
        $assignment_track = (string)($student['assignment_track'] ?? 'internal');
        $external_start_allowed = (int)($student['external_start_allowed'] ?? 0);
    }

    if (!in_array($assignment_track, ['internal', 'external'], true)) {
        $assignment_track = 'internal';
    }
    if ($assignment_track === 'external') {
        $external_start_allowed = 1;
    }

    $selected_supervisor_name = null;
    if ($supervisor_id !== null) {
        $sup_name_stmt = $conn->prepare("
            SELECT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS name
            FROM supervisors
            WHERE id = ?
            LIMIT 1
        ");
        if ($sup_name_stmt) {
            $sup_name_stmt->bind_param("i", $supervisor_id);
            $sup_name_stmt->execute();
            $sup_name_res = $sup_name_stmt->get_result();
            if ($sup_name_res && $sup_name_res->num_rows > 0) {
                $selected_supervisor_name = $sup_name_res->fetch_assoc()['name'];
            }
            $sup_name_stmt->close();
        }
    }
    $selectedOffice = ['id' => 0, 'name' => ''];
    if ($office_id !== null && $office_id > 0) {
        $officeStmt = $conn->prepare("SELECT id, name FROM offices WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        if ($officeStmt) {
            $officeStmt->bind_param('i', $office_id);
            $officeStmt->execute();
            $selectedOffice = $officeStmt->get_result()->fetch_assoc() ?: $selectedOffice;
            $officeStmt->close();
        }
    }
    if (($office_id === null || $office_id <= 0) && $supervisor_id !== null) {
        $supervisorOffices = biotern_offices_for_supervisor($conn, $supervisor_id);
        if (count($supervisorOffices) === 1) {
            $office_id = (int)$supervisorOffices[0]['id'];
            $selectedOffice = ['id' => $office_id, 'name' => (string)$supervisorOffices[0]['name']];
        }
    }

    $selected_coordinator_name = null;
    if ($coordinator_id !== null) {
        $coor_name_stmt = $conn->prepare("
            SELECT TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS name
            FROM coordinators
            WHERE id = ?
            LIMIT 1
        ");
        if ($coor_name_stmt) {
            $coor_name_stmt->bind_param("i", $coordinator_id);
            $coor_name_stmt->execute();
            $coor_name_res = $coor_name_stmt->get_result();
            if ($coor_name_res && $coor_name_res->num_rows > 0) {
                $selected_coordinator_name = $coor_name_res->fetch_assoc()['name'];
            }
            $coor_name_stmt->close();
        }
    }

    // Map coordinator_id / supervisor_id (which come from coordinators/supervisors tables)
    // to the users.id that internships expects (if available). Fall back to the raw id if no mapping.
    $intern_coordinator_user_id = null;
    $intern_supervisor_user_id = null;

    if ($coordinator_id !== null) {
        $map_stmt = $conn->prepare("SELECT user_id FROM coordinators WHERE id = ? LIMIT 1");
        if ($map_stmt) {
            $map_stmt->bind_param("i", $coordinator_id);
            $map_stmt->execute();
            $map_res = $map_stmt->get_result();
            if ($map_res && $map_res->num_rows > 0) {
                $tmp = $map_res->fetch_assoc();
                $intern_coordinator_user_id = !empty($tmp['user_id']) ? (int)$tmp['user_id'] : null;
            }
            $map_stmt->close();
        }
        if ($intern_coordinator_user_id === null) {
            // If no mapping, assume coordinator_id may already be a users.id
            $intern_coordinator_user_id = $coordinator_id;
        }
    }

    if ($supervisor_id !== null) {
        $map_stmt = $conn->prepare("SELECT user_id FROM supervisors WHERE id = ? LIMIT 1");
        if ($map_stmt) {
            $map_stmt->bind_param("i", $supervisor_id);
            $map_stmt->execute();
            $map_res = $map_stmt->get_result();
            if ($map_res && $map_res->num_rows > 0) {
                $tmp = $map_res->fetch_assoc();
                $intern_supervisor_user_id = !empty($tmp['user_id']) ? (int)$tmp['user_id'] : null;
            }
            $map_stmt->close();
        }
        if ($intern_supervisor_user_id === null) {
            $intern_supervisor_user_id = $supervisor_id;
        }
    }

    if ($original_updated_at !== '') {
        $lock_stmt = $conn->prepare("SELECT updated_at FROM students WHERE id = ? LIMIT 1");
        if ($lock_stmt) {
            $lock_stmt->bind_param("i", $student_id);
            $lock_stmt->execute();
            $lock_row = $lock_stmt->get_result()->fetch_assoc();
            $lock_stmt->close();
            $current_updated_at = (string)($lock_row['updated_at'] ?? '');
            if ($current_updated_at !== '' && $current_updated_at !== $original_updated_at) {
                $lock_conflict_detected = true;
                $lock_conflict_message = 'Note: This record was updated recently by another process/user. Your latest changes were saved.';
            }
        }
    }

    $section_course_valid = true;
    if ($course_id > 0 && $section_id > 0) {
        $section_course_check = $conn->prepare("SELECT 1 FROM sections WHERE id = ? AND course_id = ? AND (? = '' OR department_id = CAST(? AS UNSIGNED)) LIMIT 1");
        if ($section_course_check) {
            $section_course_check->bind_param("iiss", $section_id, $course_id, $department_id, $department_id);
            $section_course_check->execute();
            $section_course_valid = $section_course_check->get_result()->num_rows > 0;
            $section_course_check->close();
        }
    }

    $department_course_valid = true;
    if ($course_id > 0 && $department_id !== '') {
        $department_course_check = $conn->prepare("SELECT 1 FROM sections WHERE course_id = ? AND department_id = CAST(? AS UNSIGNED) LIMIT 1");
        if ($department_course_check) {
            $department_course_check->bind_param("is", $course_id, $department_id);
            $department_course_check->execute();
            $department_course_valid = $department_course_check->get_result()->num_rows > 0;
            $department_course_check->close();
        }
    }

    $supervisor_assignment_valid = true;
    if ($supervisor_id !== null && $supervisor_id > 0) {
        $supervisor_assignment_check = $conn->prepare("
            SELECT 1
            FROM supervisors
            WHERE id = ?
              AND (? = 0 OR course_id IS NULL OR course_id = 0 OR course_id = ?)
              AND (? = '' OR department_id IS NULL OR department_id = 0 OR department_id = CAST(? AS UNSIGNED))
            LIMIT 1
        ");
        if ($supervisor_assignment_check) {
            $supervisor_assignment_check->bind_param("iiiss", $supervisor_id, $course_id, $course_id, $department_id, $department_id);
            $supervisor_assignment_check->execute();
            $supervisor_assignment_valid = $supervisor_assignment_check->get_result()->num_rows > 0;
            $supervisor_assignment_check->close();
        }
    }

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "First Name, Last Name, and Email are required fields!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format!";
    } elseif (!$department_course_valid) {
        $error_message = "Selected department does not belong to the selected course.";
    } elseif (!$section_course_valid) {
        $error_message = "Selected section does not belong to the selected course and department.";
    } elseif (!$supervisor_assignment_valid) {
        $error_message = "Selected supervisor does not belong to the selected course and department.";
    } elseif ($requested_password_reset && !$can_admin_reset_student_password) {
        $error_message = "Only admin accounts can reset a student's account password.";
    } elseif ($requested_password_reset && empty($student['user_id'])) {
        $error_message = "This student has no linked account record, so password reset is not available.";
    } elseif ($requested_password_reset && ($admin_reset_password === '' || $admin_reset_password_confirm === '')) {
        $error_message = "Enter and confirm the new password to reset the account password.";
    } elseif ($requested_password_reset && !hash_equals($admin_reset_password, $admin_reset_password_confirm)) {
        $error_message = "New password and confirmation do not match.";
    } elseif ($requested_password_reset && (strlen($admin_reset_password) < 8 || !preg_match('/[A-Z]/', $admin_reset_password) || !preg_match('/[a-z]/', $admin_reset_password) || !preg_match('/\d/', $admin_reset_password))) {
        $error_message = "Password must be at least 8 characters and include uppercase, lowercase, and a number.";
    } else {
        // Check if email already exists (excluding current student)
        $email_check = $conn->prepare("SELECT id FROM students WHERE email = ? AND id != ?");
        $email_check->bind_param("si", $email, $student_id);
        $email_check->execute();
        $email_result = $email_check->get_result();

        if ($email_result->num_rows > 0) {
            $error_message = "Email address already exists!";
        } else {
            // Update student in database
            $update_query = "
                UPDATE students 
                SET 
                    student_id = ?,
                    first_name = ?,
                    last_name = ?,
                    middle_name = ?,
                    email = ?,
                    phone = ?,
                    date_of_birth = ?,
                    gender = ?,
                    address = ?,
                    emergency_contact = ?,
                    internal_total_hours = ?,
                    internal_total_hours_remaining = ?,
                    external_total_hours = ?,
                    external_total_hours_remaining = ?,
                    assignment_track = ?,
                    external_start_allowed = ?,
                    course_id = NULLIF(?, 0),
                    department_id = NULLIF(?, ''),
                    section_id = NULLIF(?, 0),
                    status = ?,
                    supervisor_name = ?,
                    coordinator_name = ?,
                    profile_picture = ?,
                    updated_at = NOW()
                WHERE id = ?
            ";

            $update_stmt = $conn->prepare($update_query);
            if (!$update_stmt) {
                $error_message = "Prepare failed: " . $conn->error;
            } else {
                $update_stmt->bind_param(
                    "ssssssssssiiiisiisiisssi",
                    $student_id_code,
                    $first_name,
                    $last_name,
                    $middle_name,
                    $email,
                    $phone,
                    $date_of_birth,
                    $gender,
                    $address,
                    $emergency_contact,
                    $internal_total_hours,
                    $internal_total_hours_remaining,
                    $external_total_hours,
                    $external_total_hours_remaining,
                    $assignment_track,
                    $external_start_allowed,
                    $course_id,
                    $department_id,
                    $section_id,
                    $status,
                    $selected_supervisor_name,
                    $selected_coordinator_name,
                    $profile_picture_path,
                    $student_id
                );

                if ($update_stmt->execute()) {
                    $password_reset_applied = false;
                    $password_reset_warning = '';

                    if (!empty($student['user_id'])) {
                        $user_id_for_sync = (int)$student['user_id'];
                        $display_name = trim(preg_replace('/\s+/', ' ', $first_name . ' ' . $middle_name . ' ' . $last_name));
                        $sync_user_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                        if ($sync_user_stmt) {
                            $sync_user_stmt->bind_param("ssi", $display_name, $email, $user_id_for_sync);
                            $sync_user_stmt->execute();
                            $sync_user_stmt->close();
                        }
                    }

                    if ($profile_picture_uploaded && !empty($student['user_id'])) {
                        $user_id_for_photo = (int)$student['user_id'];
                        $sync_user_photo = $conn->prepare("UPDATE users SET profile_picture = ?, updated_at = NOW() WHERE id = ?");
                        if ($sync_user_photo) {
                            $sync_user_photo->bind_param("si", $profile_picture_path, $user_id_for_photo);
                            $sync_user_photo->execute();
                            $sync_user_photo->close();
                        }
                    }

                    if ($requested_password_reset && !empty($student['user_id'])) {
                        $user_id_for_password = (int)$student['user_id'];
                        $new_hash = password_hash($admin_reset_password, PASSWORD_DEFAULT);

                        if ($new_hash === false) {
                            $password_reset_warning = ' Password reset was requested but failed to generate a secure password hash.';
                        } else {
                            $password_column = biotern_users_column_exists($conn, 'password_hash') ? 'password_hash' : 'password';
                            $reset_stmt = $conn->prepare("UPDATE users SET {$password_column} = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
                            if ($reset_stmt) {
                                $reset_stmt->bind_param('si', $new_hash, $user_id_for_password);
                                $reset_ok = $reset_stmt->execute();
                                $reset_stmt->close();

                                if ($reset_ok) {
                                    if ($password_column !== 'password' && biotern_users_column_exists($conn, 'password')) {
                                        $legacy_stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ? LIMIT 1");
                                        if ($legacy_stmt) {
                                            $legacy_stmt->bind_param('si', $new_hash, $user_id_for_password);
                                            $legacy_stmt->execute();
                                            $legacy_stmt->close();
                                        }
                                    }
                                    $password_reset_applied = true;
                                } else {
                                    $password_reset_warning = ' Password reset was requested but failed to save on the linked account.';
                                }
                            } else {
                                $password_reset_warning = ' Password reset was requested but the password update statement could not be prepared.';
                            }
                        }
                    }

                    // Keep assignment IDs in internships table as single source of truth.
                    if (!empty($student['internship_id'])) {
                        $internship_update = $conn->prepare("
                            UPDATE internships
                            SET supervisor_id = ?, coordinator_id = ?, office_id = NULLIF(?, 0), company_name = COALESCE(NULLIF(company_name, ''), NULLIF(?, '')), status = 'ongoing', updated_at = NOW()
                            WHERE id = ?
                        ");
                            if ($internship_update) {
                            $officeName = (string)($selectedOffice['name'] ?? '');
                            $officeIdForSave = (int)($office_id ?? 0);
                            $internship_update->bind_param("iiisi", $intern_supervisor_user_id, $intern_coordinator_user_id, $officeIdForSave, $officeName, $student['internship_id']);
                            $internship_update->execute();
                            $internship_update->close();
                        }
                    } else {
                        // If no internship row was linked by the ongoing join, update latest row if present.
                        $latest_internship_id = null;
                        $latest_stmt = $conn->prepare("
                            SELECT id
                            FROM internships
                            WHERE student_id = ?
                            ORDER BY (status = 'ongoing') DESC, id DESC
                            LIMIT 1
                        ");
                        if ($latest_stmt) {
                            $latest_stmt->bind_param("i", $student_id);
                            $latest_stmt->execute();
                            $latest_res = $latest_stmt->get_result();
                            if ($latest_res && $latest_res->num_rows > 0) {
                                $latest_internship_id = (int)$latest_res->fetch_assoc()['id'];
                            }
                            $latest_stmt->close();
                        }

                        if ($latest_internship_id) {
                            $internship_update = $conn->prepare("
                                UPDATE internships
                                SET supervisor_id = ?, coordinator_id = ?, office_id = NULLIF(?, 0), company_name = COALESCE(NULLIF(company_name, ''), NULLIF(?, '')), status = 'ongoing', updated_at = NOW()
                                WHERE id = ?
                            ");
                            if ($internship_update) {
                                $officeName = (string)($selectedOffice['name'] ?? '');
                                $officeIdForSave = (int)($office_id ?? 0);
                                $internship_update->bind_param("iiisi", $intern_supervisor_user_id, $intern_coordinator_user_id, $officeIdForSave, $officeName, $latest_internship_id);
                                $internship_update->execute();
                                $internship_update->close();
                            }
                        } elseif ($coordinator_id !== null) {
                            // Create a minimal internship row when none exists yet.
                            $department_id = null;
                            $dept_stmt = $conn->query("SELECT id FROM departments ORDER BY id ASC LIMIT 1");
                            if ($dept_stmt && $dept_stmt->num_rows > 0) {
                                $department_id = (int)$dept_stmt->fetch_assoc()['id'];
                            }

                            $course_for_intern = $course_id > 0 ? $course_id : (int)($student['course_id'] ?? 0);
                            if ($course_for_intern > 0 && $department_id !== null) {
                                $today = date('Y-m-d');
                                $year = (int)date('Y');
                                $school_year = $year . '-' . ($year + 1);
                                $type = ($assignment_track === 'external') ? 'external' : 'internal';
                                $required_hours = ($assignment_track === 'external')
                                    ? (int)($external_total_hours ?? 250)
                                    : (int)($internal_total_hours ?? 600);
                                if ($required_hours <= 0) {
                                    $required_hours = ($assignment_track === 'external') ? 250 : 600;
                                }
                                $remaining = ($assignment_track === 'external')
                                    ? (int)($external_total_hours_remaining ?? $required_hours)
                                    : (int)($internal_total_hours_remaining ?? $required_hours);
                                $rendered_hours = max(0, $required_hours - max(0, $remaining));
                                $completion_pct = $required_hours > 0 ? min(100, ($rendered_hours / $required_hours) * 100) : 0;

                                $insert_intern = $conn->prepare("
                                    INSERT INTO internships
                                    (student_id, course_id, department_id, coordinator_id, supervisor_id, office_id, company_name, type, start_date, status, school_year, required_hours, rendered_hours, completion_percentage, created_at, updated_at)
                                    VALUES
                                    (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, ''), ?, ?, 'ongoing', ?, ?, ?, ?, NOW(), NOW())
                                ");
                                if ($insert_intern) {
                                    $officeName = (string)($selectedOffice['name'] ?? '');
                                    $officeIdForSave = (int)($office_id ?? 0);
                                    $insert_intern->bind_param(
                                        "iiiiiissssiid",
                                        $student_id,
                                        $course_for_intern,
                                        $department_id,
                                        $intern_coordinator_user_id,
                                        $intern_supervisor_user_id,
                                        $officeIdForSave,
                                        $officeName,
                                        $type,
                                        $today,
                                        $school_year,
                                        $required_hours,
                                        $rendered_hours,
                                        $completion_pct
                                    );
                                    $insert_intern->execute();
                                    $insert_intern->close();
                                }
                            }
                        }
                    }

                    external_attendance_sync_student_hours($conn, $student_id);

                    $success_message = "Student information updated successfully!";
                    if ($password_reset_applied) {
                        $success_message .= " Linked account password was reset successfully.";
                    }
                    if ($password_reset_warning !== '') {
                        $success_message .= $password_reset_warning;
                    }
                    if ($lock_conflict_detected && $lock_conflict_message !== '') {
                        $success_message .= ' ' . $lock_conflict_message;
                    }
                    // Refresh student data
                    $stmt = $conn->prepare($student_query);
                    if ($stmt) {
                        $stmt->bind_param("i", $student_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $student = $result->fetch_assoc();
                        $stmt->close();
                    }
                } else {
                    $error_message = "Error updating student: " . $update_stmt->error;
                }

                $update_stmt->close();
            }
        }
    }
}

// Helper functions
function formatDate($date) {
    if ($date) {
        return date('Y-m-d', strtotime($date));
    }
    return '';
}

function formatDateTime($date) {
    if ($date) {
        return date('M d, Y h:i A', strtotime($date));
    }
    return 'N/A';
}

?>
<?php
$page_title = 'BioTern || Edit Student - ' . $student['first_name'] . ' ' . $student['last_name'];
$page_styles = array(
    'assets/css/layout/page_shell.css',
    'assets/css/modules/management/management-students-shared.css',
    'assets/css/modules/management/management-students-edit.css',
);
$page_scripts = array(
    'assets/js/modules/management/students-edit-runtime.js',
    'assets/js/theme-customizer-init.min.js',
);
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Edit Student</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item"><a href="students-view.php?id=<?php echo $student['id']; ?>">View</a></li>
                        <li class="breadcrumb-item">Edit</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            <a href="students-view.php?id=<?php echo $student['id']; ?>" class="btn btn-light-brand">
                                <i class="feather-arrow-left me-2"></i>
                                <span>Back to View</span>
                            </a>
                            <a href="students.php" class="btn btn-primary">
                                <i class="feather-list me-2"></i>
                                <span>Back to List</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content app-students-edit-main-content">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <div class="d-flex align-items-center justify-content-between">
                                    <h5 class="card-title mb-0">Edit Student Information</h5>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Alert Messages -->
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="feather-check-circle me-2"></i>
                                        <?php echo $success_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="feather-alert-circle me-2"></i>
                                        <?php echo $error_message; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>
                                <?php endif; ?>

                                <!-- Edit Form -->
                                <form method="POST" action="" id="editStudentForm" class="app-students-edit-form" enctype="multipart/form-data">
                                    <input type="hidden" name="original_updated_at" value="<?php echo htmlspecialchars((string)($student['updated_at'] ?? '')); ?>">
                                    <div class="edit-form-hero app-students-edit-form-hero">
                                        <div class="meta app-students-edit-form-meta">
                                            <strong><?php echo htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))); ?></strong>
                                            <span>Student profile editor</span>
                                        </div>
                                        <div class="meta app-students-edit-form-meta">
                                            <span>ID:</span> <strong><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></strong>
                                            <span class="ms-2">Email:</span> <?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                    <!-- Personal Information Section -->
                                    <div class="edit-section-card app-students-edit-section-card">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-user me-2"></i>Personal Information
                                        </h6>
                                        <p class="section-subtitle">Basic identity and contact details used across all documents and reports.</p>

                                        <div class="row">
                                            <div class="col-md-4 mb-4">
                                                <label for="first_name" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-4 mb-4">
                                                <label for="middle_name" class="form-label fw-semibold">Middle Name</label>
                                                <input type="text" class="form-control" id="middle_name" name="middle_name" 
                                                       value="<?php echo htmlspecialchars($student['middle_name'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4 mb-4">
                                                <label for="last_name" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($student['email']); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="phone" class="form-label fw-semibold">Phone Number</label>
                                                <input type="tel" class="form-control" id="phone" name="phone" 
                                                       value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="date_of_birth" class="form-label fw-semibold">Date of Birth</label>
                                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                                       value="<?php echo formatDate($student['date_of_birth']); ?>">
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="gender" class="form-label fw-semibold">Gender</label>
                                                <select class="form-control" id="gender" name="gender">
                                                    <option value="">-- Select Gender --</option>
                                                    <option value="male" <?php echo $student['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                                    <option value="female" <?php echo $student['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label for="address" class="form-label fw-semibold">Home Address</label>
                                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                                        </div>

                                        <div class="mb-4">
                                            <label for="emergency_contact" class="form-label fw-semibold">Emergency Contact Number</label>
                                            <input type="text" class="form-control" id="emergency_contact" name="emergency_contact" 
                                                   value="<?php echo htmlspecialchars($student['emergency_contact'] ?? ''); ?>"
                                                   placeholder="Phone number">
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="student_id" class="form-label fw-semibold">Student ID</label>
                                                <input type="text" class="form-control" id="student_id" name="student_id" 
                                                       <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?>
                                                       value="<?php echo htmlspecialchars($student['student_id'] ?? ''); ?>">
                                                <small class="form-text text-muted">
                                                    <?php echo $can_edit_sensitive_hours ? 'Can be edited by admins, coordinators, and supervisors' : 'Read-only for student accounts'; ?>
                                                </small>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="profile_picture" class="form-label fw-semibold">Profile Picture</label>
                                                <div class="mb-2">
                                                    <?php $student_profile_src = biotern_avatar_public_src((string)($student['profile_picture'] ?? ''), (int)($student['user_id'] ?? 0)); ?>
                                                    <?php if (!empty($student_profile_src)): ?>
                                                        <div class="mb-2" data-student-avatar-preview-wrap>
                                                            <img src="<?php echo htmlspecialchars($student_profile_src); ?>" alt="Profile" class="img-thumbnail app-thumb-150" data-student-avatar-preview>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-info mb-2 py-2 px-3" data-student-avatar-empty>No profile picture uploaded</div>
                                                        <div class="mb-2 d-none" data-student-avatar-preview-wrap>
                                                            <img src="" alt="Profile preview" class="img-thumbnail app-thumb-150" data-student-avatar-preview>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <input type="hidden" id="profile_picture_cropped" name="profile_picture_cropped" value="" data-student-avatar-cropped-input>
                                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                                       accept=".jpg,.jpeg,.png,.webp,.gif,image/*" data-student-avatar-file-input>
                                                <small class="form-text text-muted">JPG, PNG, GIF, WEBP (Max 5MB)</small>
                                            </div>
                                        </div>
                                    </div>


                                    <?php if ($can_admin_reset_student_password): ?>
                                    <div class="edit-section-card app-students-edit-section-card">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-lock me-2"></i>Account Password Reset (Admin)
                                        </h6>
                                        <p class="section-subtitle">Optional: set a new login password for this student's linked account when self-service reset is unavailable.</p>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="admin_reset_password" class="form-label fw-semibold">New Account Password</label>
                                                <input type="password" class="form-control" id="admin_reset_password" name="admin_reset_password" autocomplete="new-password" minlength="8" placeholder="Leave blank to keep current password">
                                                <small class="form-text text-muted">Minimum 8 chars, include uppercase, lowercase, and number.</small>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="admin_reset_password_confirm" class="form-label fw-semibold">Confirm New Password</label>
                                                <input type="password" class="form-control" id="admin_reset_password_confirm" name="admin_reset_password_confirm" autocomplete="new-password" minlength="8" placeholder="Re-enter new password">
                                            </div>
                                            <div class="col-12 mb-1">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="toggle_admin_reset_password">
                                                    <label class="form-check-label" for="toggle_admin_reset_password">Show password fields</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>


                                    <!-- Internship Information Section -->
                                    <div class="edit-section-card app-students-edit-section-card">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-briefcase me-2"></i>Internship Information
                                        </h6>
                                        <p class="section-subtitle">Assign who manages this student during internship deployment.</p>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="supervisor_id" class="form-label fw-semibold">Supervisor</label>
                                                <select class="form-control" id="supervisor_id" name="supervisor_id">
                                                    <option value=""></option>
                                                    <?php foreach ($supervisors as $supervisor): ?>
                                                        <option value="<?php echo (int)$supervisor['id']; ?>"
                                                            data-course-id="<?php echo (int)($supervisor['course_id'] ?? 0); ?>"
                                                            data-department-id="<?php echo (int)($supervisor['department_id'] ?? 0); ?>"
                                                            <?php echo ((int)($student['supervisor_id'] ?? 0) === (int)$supervisor['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($supervisor['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="office_id" class="form-label fw-semibold">Office</label>
                                                <select class="form-control" id="office_id" name="office_id">
                                                    <option value="">Auto from supervisor</option>
                                                    <?php foreach ($officeOptions as $office): ?>
                                                        <?php $officeSupIds = $officeSupervisorMap[(int)$office['id']] ?? []; ?>
                                                        <option value="<?php echo (int)$office['id']; ?>"
                                                            data-course-id="<?php echo (int)($office['course_id'] ?? 0); ?>"
                                                            data-department-id="<?php echo (int)($office['department_id'] ?? 0); ?>"
                                                            data-supervisor-ids="<?php echo htmlspecialchars(implode(',', $officeSupIds), ENT_QUOTES, 'UTF-8'); ?>"
                                                            <?php echo ((int)($student['internship_office_id'] ?? 0) === (int)$office['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars((string)$office['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <small class="form-text text-muted">If the supervisor has one office, this fills automatically. If they have more than one, choose the exact office here.</small>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="coordinator_id" class="form-label fw-semibold">Coordinator</label>
                                                <select class="form-control" id="coordinator_id" name="coordinator_id">
                                                    <option value=""></option>
                                                    <?php foreach ($coordinators as $coordinator): ?>
                                                        <option value="<?php echo (int)$coordinator['id']; ?>"
                                                            <?php echo ((int)($student['coordinator_id'] ?? 0) === (int)$coordinator['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($coordinator['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                    </div>

                                    <!-- Academic Information Section -->
                                    <div class="edit-section-card app-students-edit-section-card">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-book me-2"></i>Academic Information
                                        </h6>
                                        <p class="section-subtitle">Course placement, section, status, and required hour tracking.</p>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="course_id" class="form-label fw-semibold">Course</label>
                                                <select class="form-control" id="course_id" name="course_id">
                                                    <option value="0">-- Select Course --</option>
                                                    <?php foreach ($courses as $course): ?>
                                                        <option value="<?php echo $course['id']; ?>" 
                                                            <?php echo $student['course_id'] == $course['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($course['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="department_id" class="form-label fw-semibold">Department</label>
                                                <select class="form-control" id="department_id" name="department_id">
                                                    <option value="">-- Select Department --</option>
                                                    <?php foreach ($departments as $department): ?>
                                                        <option value="<?php echo htmlspecialchars((string)$department['id']); ?>"
                                                            data-course-ids="<?php echo htmlspecialchars((string)($department['course_ids'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            <?php echo ((string)($student['department_id'] ?? '') === (string)$department['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($department['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="section_id" class="form-label fw-semibold">Section</label>
                                                <select class="form-control" id="section_id" name="section_id">
                                                    <option value="0">-- Select Section --</option>
                                                    <?php foreach ($sections as $section): ?>
                                                        <option value="<?php echo (int)$section['id']; ?>" data-course-id="<?php echo (int)($section['course_id'] ?? 0); ?>" data-department-id="<?php echo (int)($section['department_id'] ?? 0); ?>"
                                                            <?php echo ((int)($student['section_id'] ?? 0) === (int)$section['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars(biotern_format_section_label((string)($section['code'] ?? ''), (string)($section['name'] ?? ''))); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="status" class="form-label fw-semibold">Status</label>
                                                <select class="form-control" id="status" name="status">
                                                    <option value="1" <?php echo $student['status'] == 1 ? 'selected' : ''; ?>>Active</option>
                                                    <option value="0" <?php echo $student['status'] == 0 ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="assignment_track" class="form-label fw-semibold">Assignment Track</label>
                                                <select class="form-control" id="assignment_track" name="assignment_track" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?>>
                                                    <option value="internal" <?php echo (($student['assignment_track'] ?? 'internal') === 'internal') ? 'selected' : ''; ?>>Internal</option>
                                                    <option value="external" <?php echo (($student['assignment_track'] ?? '') === 'external') ? 'selected' : ''; ?>>External</option>
                                                </select>
                                                <small class="form-text text-muted">Students may open External DTR, but external hours compute only when this track is External or the override is enabled.</small>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label class="form-label fw-semibold" for="external_start_allowed">External Start Override</label>
                                                <div class="form-check mt-2">
                                                    <input class="form-check-input" type="checkbox" id="external_start_allowed" name="external_start_allowed" value="1" <?php echo ((int)($student['external_start_allowed'] ?? 0) === 1 || ($student['assignment_track'] ?? 'internal') === 'external') ? 'checked' : ''; ?> <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?>>
                                                    <label class="form-check-label" for="external_start_allowed">Allow external hours to compute before internal completion</label>
                                                </div>
                                                <small class="form-text text-muted">Use this only when a higher role approves the student to start external early.</small>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-3 mb-4">
                                                <label for="internal_total_hours" class="form-label fw-semibold">Internal Total Hours</label>
                                                <input type="number" class="form-control" id="internal_total_hours" name="internal_total_hours" min="0" step="1" <?php echo $can_edit_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['internal_total_hours'] ?? '')); ?>">
                                            </div>
                                            <div class="col-md-3 mb-4">
                                                <label for="internal_total_hours_remaining" class="form-label fw-semibold">Internal Hours Remaining</label>
                                                <input type="number" class="form-control" id="internal_total_hours_remaining" name="internal_total_hours_remaining" min="0" step="1" <?php echo $can_edit_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['internal_total_hours_remaining'] ?? '')); ?>">
                                            </div>
                                            <div class="col-md-3 mb-4">
                                                <label for="external_total_hours" class="form-label fw-semibold">External Total Hours</label>
                                                <input type="number" class="form-control" id="external_total_hours" name="external_total_hours" min="0" step="1" <?php echo $can_edit_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['external_total_hours'] ?? '')); ?>">
                                            </div>
                                            <div class="col-md-3 mb-4">
                                                <label for="external_total_hours_remaining" class="form-label fw-semibold">External Hours Remaining</label>
                                                <input type="number" class="form-control" id="external_total_hours_remaining" name="external_total_hours_remaining" min="0" step="1" <?php echo $can_edit_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['external_total_hours_remaining'] ?? '')); ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold text-muted">Biometric Status</label>
                                                <div class="form-text">
                                                    <?php if ($biometric_registered): ?>
                                                        <span class="badge bg-success">
                                                            <i class="feather-check me-1"></i>Registered
                                                        </span>
                                                        <?php if ($biometric_finger_id !== null && $biometric_finger_id > 0): ?>
                                                            <small class="d-block mt-2 text-muted">
                                                                Finger ID: <?php echo htmlspecialchars((string)$biometric_finger_id); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <small class="d-block mt-2 text-muted">
                                                            Registered on: <?php echo formatDateTime($biometric_registered_at); ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="feather-alert-circle me-1"></i>Not Registered
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold text-muted">Registration Date</label>
                                                <div class="form-text">
                                                    <small class="text-muted">
                                                        Registered: <?php echo formatDateTime($student['created_at']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                    <!-- Form Actions -->
                                    <div class="d-flex gap-2 justify-content-between save-actions-bar app-students-edit-save-actions mt-3">
                                        <a href="generate_resume.php?id=<?php echo $student['id']; ?>" class="btn btn-success" target="_blank">
                                            <i class="feather-file-text me-2"></i>Generate Resume
                                        </a>
                                        <div class="d-flex gap-2">
                                            <a href="students-view.php?id=<?php echo $student['id']; ?>" class="btn btn-light-brand">
                                                <i class="feather-x me-2"></i>Cancel
                                            </a>
                                            <button type="reset" class="btn btn-outline-secondary">
                                                <i class="feather-refresh-cw me-2"></i>Reset
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="feather-save me-2"></i>Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

</div> <!-- .nxl-content -->
</main>
<div class="modal fade" id="studentAvatarCropModal" tabindex="-1" aria-hidden="true" data-student-avatar-crop-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crop Profile Picture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="avatar-crop-editor">
                    <div class="avatar-crop-canvas-wrap">
                        <canvas width="320" height="320" data-student-avatar-crop-canvas></canvas>
                    </div>
                    <div class="mt-2">
                        <label class="form-label mb-1" for="student_avatar_crop_zoom">Zoom</label>
                        <input type="range" id="student_avatar_crop_zoom" min="100" max="400" step="1" value="100" class="form-range" data-student-avatar-crop-zoom>
                    </div>
                    <p class="form-text mb-0 mt-2" data-student-avatar-crop-status>Drag the image to position the crop area.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-student-avatar-crop-reset>Reset</button>
                <button type="button" class="btn btn-primary" data-student-avatar-crop-apply>Crop and Preview</button>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var courseSelect = document.getElementById('course_id');
    var departmentSelect = document.getElementById('department_id');
    var sectionSelect = document.getElementById('section_id');

    var supervisorSelect = document.getElementById('supervisor_id');
    var officeSelect = document.getElementById('office_id');

    function csvHas(csv, value) {
        if (!csv || !value) return false;
        return csv.split(',').indexOf(String(value)) !== -1;
    }

    function refreshSelect2(select) {
        if (window.jQuery && select && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery(select).trigger('change.select2');
        }
    }

    function syncAcademicFields(forceDepartmentPick) {
        var courseId = courseSelect ? (courseSelect.value || '0') : '0';
        var firstVisibleDepartment = '';

        if (departmentSelect) {
            Array.prototype.forEach.call(departmentSelect.options, function (option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                var courseIds = option.getAttribute('data-course-ids') || '';
                var matches = courseId === '0' || csvHas(courseIds, courseId);
                option.hidden = !matches;
                option.disabled = !matches;
                if (matches && firstVisibleDepartment === '') {
                    firstVisibleDepartment = option.value;
                }
            });

            var selectedDepartment = departmentSelect.options[departmentSelect.selectedIndex];
            if (forceDepartmentPick || (selectedDepartment && selectedDepartment.hidden)) {
                departmentSelect.value = firstVisibleDepartment || '';
            }
            refreshSelect2(departmentSelect);
        }

        var departmentId = departmentSelect ? (departmentSelect.value || '') : '';
        if (sectionSelect) {
            Array.prototype.forEach.call(sectionSelect.options, function (option) {
                if (!option.value || option.value === '0') {
                    option.hidden = false;
                    return;
                }

                var optionCourse = option.getAttribute('data-course-id') || '0';
                var optionDepartment = option.getAttribute('data-department-id') || '0';
                var courseMatches = courseId === '0' || optionCourse === courseId;
                var departmentMatches = departmentId === '' || optionDepartment === departmentId;
                option.hidden = !(courseMatches && departmentMatches);
                option.disabled = option.hidden;
                if (option.hidden && option.selected) {
                    sectionSelect.value = '0';
                }
            });
            refreshSelect2(sectionSelect);
        }
    }

    function filterSupervisorsAndOffices() {
        var courseId = courseSelect ? (courseSelect.value || '0') : '0';
        var departmentId = departmentSelect ? (departmentSelect.value || '0') : '0';
        if (supervisorSelect) {
            Array.prototype.forEach.call(supervisorSelect.options, function (option) {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }
                var optionCourse = option.getAttribute('data-course-id') || '0';
                var optionDepartment = option.getAttribute('data-department-id') || '0';
                var courseMatches = courseId === '0' || optionCourse === '0' || optionCourse === courseId;
                var departmentMatches = departmentId === '0' || optionDepartment === '0' || optionDepartment === departmentId;
                option.hidden = !(courseMatches && departmentMatches);
                option.disabled = option.hidden;
                if (option.hidden && option.selected) {
                    supervisorSelect.value = '';
                }
            });
            refreshSelect2(supervisorSelect);
        }

        if (officeSelect) {
            var supervisorId = supervisorSelect ? (supervisorSelect.value || '') : '';
            var visibleOfficeValues = [];
            Array.prototype.forEach.call(officeSelect.options, function (option) {
                if (!option.value) {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }
                var officeCourse = option.getAttribute('data-course-id') || '0';
                var officeDepartment = option.getAttribute('data-department-id') || '0';
                var supervisorIds = option.getAttribute('data-supervisor-ids') || '';
                var courseMatches = courseId === '0' || officeCourse === '0' || officeCourse === courseId;
                var departmentMatches = departmentId === '0' || officeDepartment === '0' || officeDepartment === departmentId;
                var supervisorMatches = !supervisorId || csvHas(supervisorIds, supervisorId);
                option.hidden = !(courseMatches && departmentMatches && supervisorMatches);
                option.disabled = option.hidden;
                if (!option.hidden) visibleOfficeValues.push(option.value);
                if (option.hidden && option.selected) officeSelect.value = '';
            });
            if (supervisorId && visibleOfficeValues.length === 1 && !officeSelect.value) {
                officeSelect.value = visibleOfficeValues[0];
            }
            refreshSelect2(officeSelect);
        }
    }

    if (courseSelect) {
        courseSelect.addEventListener('change', function () {
            syncAcademicFields(true);
            filterSupervisorsAndOffices();
        });
    }
    if (departmentSelect) {
        departmentSelect.addEventListener('change', function () {
            syncAcademicFields(false);
            filterSupervisorsAndOffices();
        });
    }
    if (supervisorSelect) supervisorSelect.addEventListener('change', filterSupervisorsAndOffices);
    syncAcademicFields(false);
    filterSupervisorsAndOffices();

    var toggle = document.getElementById('toggle_admin_reset_password');
    if (!toggle) {
        return;
    }

    var passwordIds = ['admin_reset_password', 'admin_reset_password_confirm'];
    function applyVisibility() {
        var targetType = toggle.checked ? 'text' : 'password';
        passwordIds.forEach(function (id) {
            var input = document.getElementById(id);
            if (input) {
                input.type = targetType;
            }
        });
    }

    toggle.addEventListener('change', applyVisibility);
    applyVisibility();
});
</script>

<?php
$conn->close();
?>









