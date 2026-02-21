<?php
// Database Connection
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_role = strtolower(trim((string) (
    $_SESSION['role'] ??
    $_SESSION['user_role'] ??
    $_SESSION['account_role'] ??
    $_SESSION['user_type'] ??
    $_SESSION['type'] ??
    ''
)));
$can_edit_sensitive_hours = in_array($current_role, ['admin', 'coordinator', 'supervisor'], true);

// Ensure new student assignment/hour fields exist.
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS internal_total_hours INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS internal_total_hours_remaining INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_total_hours INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS external_total_hours_remaining INT(11) DEFAULT NULL");
$conn->query("ALTER TABLE students ADD COLUMN IF NOT EXISTS assignment_track VARCHAR(20) NOT NULL DEFAULT 'internal'");

// Get student ID from URL parameter
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id == 0) {
    die("Invalid student ID");
}

// Create uploads directory if it doesn't exist
$uploads_dir = dirname(__FILE__) . '/uploads/profile_pictures';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0755, true);
}
// Ensure other upload folders exist
$uploads_manual_dtr = dirname(__FILE__) . '/uploads/manual_dtr';
if (!is_dir($uploads_manual_dtr)) {
    mkdir($uploads_manual_dtr, 0755, true);
}
$uploads_documents = dirname(__FILE__) . '/uploads/documents';
if (!is_dir($uploads_documents)) {
    mkdir($uploads_documents, 0755, true);
}

// Fetch Student Details
$student_query = "
    SELECT 
        s.id,
        s.student_id,
        s.profile_picture,
        s.first_name,
        s.last_name,
        s.middle_name,
        s.email,
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
        s.status,
        s.biometric_registered,
        s.biometric_registered_at,
        s.created_at,
        s.supervisor_name,
        s.coordinator_name,
        c.name as course_name,
        c.id as course_id,
        i.id as internship_id,
        i.supervisor_id,
        i.coordinator_id
    FROM students s
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
    WHERE s.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Student not found");
}

$student = $result->fetch_assoc();

// Fetch all courses for dropdown
$courses_query = "SELECT id, name FROM courses WHERE is_active = 1 ORDER BY name ASC";
$courses_result = $conn->query($courses_query);
$courses = [];
if ($courses_result->num_rows > 0) {
    while ($row = $courses_result->fetch_assoc()) {
        $courses[] = $row;
    }
}

// Fetch all supervisors and coordinators for dropdowns
$supervisors_query = "SELECT DISTINCT supervisor_name FROM students WHERE supervisor_name IS NOT NULL ORDER BY supervisor_name ASC";
$supervisors_result = $conn->query($supervisors_query);
$supervisors = [];
if ($supervisors_result->num_rows > 0) {
    while ($row = $supervisors_result->fetch_assoc()) {
        $supervisors[] = ['name' => $row['supervisor_name']];
    }
}

$coordinators_query = "SELECT DISTINCT coordinator_name FROM students WHERE coordinator_name IS NOT NULL ORDER BY coordinator_name ASC";
$coordinators_result = $conn->query($coordinators_query);
$coordinators = [];
if ($coordinators_result->num_rows > 0) {
    while ($row = $coordinators_result->fetch_assoc()) {
        $coordinators[] = ['name' => $row['coordinator_name']];
    }
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle profile picture upload
    $profile_picture_path = $student['profile_picture'] ?? '';
    
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type and size
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        if (in_array($file_ext, $allowed_types) && $file_size <= $max_file_size) {
            // Create unique filename
            $unique_name = 'student_' . $student_id . '_' . time() . '.' . $file_ext;
            $file_path = $uploads_dir . '/' . $unique_name;
            
            // Delete old profile picture if exists
            if (!empty($profile_picture_path) && file_exists(dirname(__FILE__) . '/' . $profile_picture_path)) {
                unlink(dirname(__FILE__) . '/' . $profile_picture_path);
            }
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $file_path)) {
                $profile_picture_path = 'uploads/profile_pictures/' . $unique_name;
            } else {
                $error_message = "Failed to upload profile picture. Please try again.";
            }
        } else {
            $error_message = "Invalid file type or file size exceeds 5MB. Allowed types: JPG, PNG, GIF.";
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
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $supervisor_id = isset($_POST['supervisor_id']) ? trim($_POST['supervisor_id']) : '';
    $coordinator_id = isset($_POST['coordinator_id']) ? trim($_POST['coordinator_id']) : '';
    $student_id_code = isset($_POST['student_id']) ? trim($_POST['student_id']) : ($student['student_id'] ?? '');
    $internal_total_hours = isset($_POST['internal_total_hours']) && $_POST['internal_total_hours'] !== '' ? intval($_POST['internal_total_hours']) : null;
    $internal_total_hours_remaining = isset($_POST['internal_total_hours_remaining']) && $_POST['internal_total_hours_remaining'] !== '' ? intval($_POST['internal_total_hours_remaining']) : null;
    $external_total_hours = isset($_POST['external_total_hours']) && $_POST['external_total_hours'] !== '' ? intval($_POST['external_total_hours']) : null;
    $external_total_hours_remaining = isset($_POST['external_total_hours_remaining']) && $_POST['external_total_hours_remaining'] !== '' ? intval($_POST['external_total_hours_remaining']) : null;
    $assignment_track = isset($_POST['assignment_track']) ? trim($_POST['assignment_track']) : 'internal';

    if (!$can_edit_sensitive_hours) {
        $student_id_code = (string)($student['student_id'] ?? '');
        $internal_total_hours = isset($student['internal_total_hours']) ? intval($student['internal_total_hours']) : null;
        $internal_total_hours_remaining = isset($student['internal_total_hours_remaining']) ? intval($student['internal_total_hours_remaining']) : null;
        $external_total_hours = isset($student['external_total_hours']) ? intval($student['external_total_hours']) : null;
        $external_total_hours_remaining = isset($student['external_total_hours_remaining']) ? intval($student['external_total_hours_remaining']) : null;
        $assignment_track = (string)($student['assignment_track'] ?? 'internal');
    }

    if (!in_array($assignment_track, ['internal', 'external'], true)) {
        $assignment_track = 'internal';
    }

    // Validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $error_message = "First Name, Last Name, and Email are required fields!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format!";
    } elseif ($assignment_track === 'external' && ($internal_total_hours_remaining === null || $internal_total_hours_remaining > 0)) {
        $error_message = "Cannot assign student to External unless Internal is completed (Internal Total Hours Remaining must be 0).";
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
                    course_id = NULLIF(?, 0),
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
                    "ssssssssssiiiisiisssi",
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
                    $course_id,
                    $status,
                    $supervisor_id,
                    $coordinator_id,
                    $profile_picture_path,
                    $student_id
                );

                if ($update_stmt->execute()) {
                    $success_message = "âœ“ Student information updated successfully!";
                    // Refresh student data
                    $stmt = $conn->prepare($student_query);
                    $stmt->bind_param("i", $student_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $student = $result->fetch_assoc();
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

<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <title>BioTern || Edit Student - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/datepicker.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    
    <style>
        /* Select2 styling adjustments - avoid overriding position computed by plugin */
        .select2-container--default .select2-selection--single {
            height: 40px;
            padding: 6px 12px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            border: 1px solid #d3d3d3;
            background-color: #fff;
            color: #333;
        }

        .select2-container--default.select2-container--open .select2-selection--single {
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 0;
            align-items: center;
            display: flex;
        }

        /* Let Select2 calculate dropdown position. Only ensure visuals and stacking. */
        .select2-container--default .select2-dropdown {
            border: 1px solid #dddddd;
            border-radius: 0 0 4px 4px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.08);
            max-height: 200px;
            overflow-y: auto;
        }

        .select2-container {
            width: 100% !important;
            max-width: 100%;
        }

        .form-control.select2-hidden-accessible {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }

        /* Small tweak for arrow height and layout */
        .select2-container--default .select2-selection--single {
            position: relative;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 28px;
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Clear (Ã—) button styling */
        .select2-selection__clear {
            position: absolute;
            right: 36px;
            top: 50%;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 3px;
            border: 1px solid #e0e0e0;
            background: #fff;
            color: #333;
            box-shadow: none;
            font-size: 12px;
        }

        /* Truncate selected text so it doesn't overflow under icons */
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            display: inline-block;
            vertical-align: middle;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: calc(100% - 80px);
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main.nxl-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        div.nxl-content {
            flex: 1;
        }
        footer.footer {
            margin-top: auto;
        }
        
        /* Dark mode select and Select2 styling */
        select.form-control,
        select.form-select,
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            color: #333 !important;
            background-color: #ffffff !important;
        }
        
        /* Dark mode support for Select2 - using app-skin-dark class */
        html.app-skin-dark .select2-container--default .select2-selection--single,
        html.app-skin-dark .select2-container--default .select2-selection--multiple {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark .select2-container--default.select2-container--focus .select2-selection--single,
        html.app-skin-dark .select2-container--default.select2-container--focus .select2-selection--multiple {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
            border-color: #667eea !important;
        }
        
        html.app-skin-dark .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f0f0f0 !important;
        }
        
        html.app-skin-dark .select2-container--default .select2-selection__placeholder {
            color: #a0aec0 !important;
        }
        
        /* Dark mode dropdown menu */
        html.app-skin-dark .select2-container--default.select2-container--open .select2-dropdown {
            background-color: #2d3748 !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark .select2-results {
            background-color: #2d3748 !important;
        }
        
        html.app-skin-dark .select2-results__option {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
        }
        
        html.app-skin-dark .select2-results__option--highlighted[aria-selected] {
            background-color: #667eea !important;
            color: #ffffff !important;
        }
        
        html.app-skin-dark .select2-container--default {
            background-color: #2d3748 !important;
        }
        
        html.app-skin-dark select.form-control,
        html.app-skin-dark select.form-select {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
            border-color: #4a5568 !important;
        }
        
        html.app-skin-dark select.form-control option,
        html.app-skin-dark select.form-select option {
            color: #f0f0f0 !important;
            background-color: #2d3748 !important;
        }
    </style>
</head>

<body>
    <!--! Navigation !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="index.php" class="b-brand">
                    <img src="assets/images/logo-full.png" alt="" class="logo logo-lg">
                    <img src="assets/images/logo-abbr.png" alt="" class="logo logo-sm">
                </a>
            </div>
            <div class="navbar-content">
                <ul class="nxl-navbar">
                    <li class="nxl-item nxl-caption">
                        <label>Navigation</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-airplay"></i></span>
                            <span class="nxl-mtext">Home</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="index.php">Overview</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="analytics.php">Analytics</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-cast"></i></span>
                            <span class="nxl-mtext">Reports</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="reports-sales.php">Sales Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="reports-ojt.php">OJT Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="reports-project.php">Project Report</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="reports-timesheets.php">Timesheets Report</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-send"></i></span>
                            <span class="nxl-mtext">Applications</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="apps-chat.php">Chat</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-email.php">Email</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-tasks.php">Tasks</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-notes.php">Notes</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-storage.php">Storage</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="apps-calendar.php">Calendar</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-users"></i></span>
                            <span class="nxl-mtext">Students</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="students.php">Students</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-view.php">Students View</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-create.php">Students Create</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-alert-circle"></i></span>
                            <span class="nxl-mtext">Assign OJT Designation</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="ojt.php">OJT List</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="ojt-view.php">OJT View</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="ojt-create.php">OJT Create</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-layout"></i></span>
                            <span class="nxl-mtext">Widgets</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="widgets-lists.php">Lists</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-tables.php">Tables</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-charts.php">Charts</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-statistics.php">Statistics</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="widgets-miscellaneous.php">Miscellaneous</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-settings"></i></span>
                            <span class="nxl-mtext">Settings</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="settings-general.php">General</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-seo.php">SEO</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-tags.php">Tags</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-email.php">Email</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-tasks.php">Tasks</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-ojt.php">Leads</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-support.php">Support</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-students.php">Students</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="settings-miscellaneous.php">Miscellaneous</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-power"></i></span>
                            <span class="nxl-mtext">Authentication</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Login</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-login-cover.php">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Register</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-register-creative.php">Creative</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Error-404</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-404-minimal.php">Minimal</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Reset Pass</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-reset-cover.php">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Verify OTP</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-verify-cover.php">Cover</a></li>
                                </ul>
                            </li>
                            <li class="nxl-item nxl-hasmenu">
                                <a href="javascript:void(0);" class="nxl-link">
                                    <span class="nxl-mtext">Maintenance</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                </a>
                                <ul class="nxl-submenu">
                                    <li class="nxl-item"><a class="nxl-link" href="auth-maintenance-cover.php">Cover</a></li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-life-buoy"></i></span>
                            <span class="nxl-mtext">Help Center</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="#!">Support</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="help-knowledgebase.php">KnowledgeBase</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="/docs/documentations">Documentations</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!--! Header !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">
                    <div class="dropdown nxl-h-item nxl-header-search">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <i class="feather-search"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown">
                            <div class="input-group search-form">
                                <span class="input-group-text">
                                    <i class="feather-search fs-6 text-muted"></i>
                                </span>
                                <input type="text" class="form-control search-input-field" placeholder="Search....">
                                <span class="input-group-text">
                                    <button type="button" class="btn-close"></button>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="nxl-h-item d-none d-sm-flex">
                        <div class="full-screen-switcher">
                            <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
                                <i class="feather-maximize maximize"></i>
                                <i class="feather-minimize minimize"></i>
                            </a>
                        </div>
                    </div>
                    <div class="nxl-h-item dark-light-theme">
                        <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button">
                            <i class="feather-moon"></i>
                        </a>
                        <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display: none">
                            <i class="feather-sun"></i>
                        </a>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                            <i class="feather-bell"></i>
                            <span class="badge bg-danger nxl-h-badge">3</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                            <div class="d-flex justify-content-between align-items-center notifications-head">
                                <h6 class="fw-bold text-dark mb-0">Notifications</h6>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <img src="assets/images/avatar/1.png" alt="user-image" class="img-fluid user-avtar me-0">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="assets/images/avatar/1.png" alt="user-image" class="img-fluid user-avtar">
                                    <div>
                                        <h6 class="text-dark mb-0">Felix Luis Mateo</h6>
                                        <span class="fs-12 fw-medium text-muted">felixluismateo@example.com</span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-settings"></i>
                                <span>Account Settings</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="./auth-login-cover.php" class="dropdown-item">
                                <i class="feather-log-out"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!--! Main Content !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Edit Student</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
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
            <div class="main-content">
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
                                <form method="POST" action="" id="editStudentForm" enctype="multipart/form-data">
                                    <!-- Personal Information Section -->
                                    <div class="mb-5">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-user me-2"></i>Personal Information
                                        </h6>

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
                                                    <option value="other" <?php echo $student['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
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
                                                    <?php if (!empty($student['profile_picture'])): ?>
                                                        <div class="mb-2">
                                                            <img src="<?php echo htmlspecialchars($student['profile_picture']); ?>" alt="Profile" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-info mb-2 py-2 px-3">No profile picture uploaded</div>
                                                    <?php endif; ?>
                                                </div>
                                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" 
                                                       accept="image/*">
                                                <small class="form-text text-muted">JPG, PNG, GIF (Max 5MB)</small>
                                            </div>
                                        </div>
                                    </div>

                                    <hr>

                                    <!-- Internship Information Section -->
                                    <div class="mb-5">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-briefcase me-2"></i>Internship Information
                                        </h6>

                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label for="supervisor_id" class="form-label fw-semibold">Supervisor</label>
                                                <select class="form-control" id="supervisor_id" name="supervisor_id">
                                                    <option value="">-- Select Supervisor --</option>
                                                    <?php foreach ($supervisors as $supervisor): ?>
                                                        <option value="<?php echo htmlspecialchars($supervisor['name']); ?>" 
                                                            <?php echo (isset($student['supervisor_name']) && $student['supervisor_name'] == $supervisor['name']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($supervisor['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label for="coordinator_id" class="form-label fw-semibold">Coordinator</label>
                                                <select class="form-control" id="coordinator_id" name="coordinator_id">
                                                    <option value="">-- Select Coordinator --</option>
                                                    <?php foreach ($coordinators as $coordinator): ?>
                                                        <option value="<?php echo htmlspecialchars($coordinator['name']); ?>" 
                                                            <?php echo (isset($student['coordinator_name']) && $student['coordinator_name'] == $coordinator['name']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($coordinator['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                    <!-- Academic Information Section -->
                                    <div class="mb-5">
                                        <h6 class="fw-bold mb-4">
                                            <i class="feather-book me-2"></i>Academic Information
                                        </h6>

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
                                                <small class="form-text text-muted">Rule: External is allowed only when Internal Hours Remaining is 0.</small>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-3 mb-4">
                                                <label for="internal_total_hours" class="form-label fw-semibold">Internal Total Hours</label>
                                                <input type="number" class="form-control" id="internal_total_hours" name="internal_total_hours" min="0" step="1" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['internal_total_hours'] ?? '')); ?>">
                                            </div>
                                            <div class="col-md-3 mb-4">
                                                <label for="internal_total_hours_remaining" class="form-label fw-semibold">Internal Hours Remaining</label>
                                                <input type="number" class="form-control" id="internal_total_hours_remaining" name="internal_total_hours_remaining" min="0" step="1" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['internal_total_hours_remaining'] ?? '')); ?>">
                                            </div>
                                            <div class="col-md-3 mb-4">
                                                <label for="external_total_hours" class="form-label fw-semibold">External Total Hours</label>
                                                <input type="number" class="form-control" id="external_total_hours" name="external_total_hours" min="0" step="1" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['external_total_hours'] ?? '')); ?>">
                                            </div>
                                            <div class="col-md-3 mb-4">
                                                <label for="external_total_hours_remaining" class="form-label fw-semibold">External Hours Remaining</label>
                                                <input type="number" class="form-control" id="external_total_hours_remaining" name="external_total_hours_remaining" min="0" step="1" <?php echo $can_edit_sensitive_hours ? '' : 'disabled'; ?> value="<?php echo htmlspecialchars((string)($student['external_total_hours_remaining'] ?? '')); ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label fw-semibold text-muted">Biometric Status</label>
                                                <div class="form-text">
                                                    <?php if ($student['biometric_registered']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="feather-check me-1"></i>Registered
                                                        </span>
                                                        <small class="d-block mt-2 text-muted">
                                                            Registered on: <?php echo formatDateTime($student['biometric_registered_at']); ?>
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

                                    <hr>

                                    <!-- Form Actions -->
                                    <div class="d-flex gap-2 justify-content-between pt-4">
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

        <!-- Footer -->
        <footer class="footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright ©</span>
                <script>
                    document.write(new Date().getFullYear());
                </script>
            </p>
            <p><span>By: <a target="_blank" href="">ACT 2A</a> </span><span>Distributed by: <a target="_blank" href="">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </footer>
    </main>

    <!-- Scripts -->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <script src="assets/vendors/js/datepicker.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/theme-customizer-init.min.js"></script>

    <script>
        // Initialize form elements
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize select2 for dropdowns
            $('#course_id, #status, #gender, #supervisor_id, #coordinator_id').each(function() {
                $(this).select2({
                    allowClear: true,
                    width: 'resolve',
                    dropdownAutoWidth: false,
                    theme: 'default'
                });
            });

            // Initialize datepicker for date fields
            $('#date_of_birth').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true
            });

            // Form validation
            document.getElementById('editStudentForm').addEventListener('submit', function(e) {
                var firstName = document.getElementById('first_name').value.trim();
                var lastName = document.getElementById('last_name').value.trim();
                var email = document.getElementById('email').value.trim();

                if (!firstName || !lastName || !email) {
                    e.preventDefault();
                    alert('Please fill in all required fields!');
                    return false;
                }

                // Email validation
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Please enter a valid email address!');
                    return false;
                }

                return true;
            });
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>

</html>

<?php
$conn->close();
?>

