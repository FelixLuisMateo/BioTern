<?php
require_once dirname(__DIR__) . '/config/db.php';
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/auth/') !== false) ? '../' : '';
$route_prefix = $asset_prefix;
$self_register_url = $route_prefix . 'auth-register.php';
// Handle submissions immediately to avoid rendering/query side effects before redirects.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestedRole = strtolower(trim((string)($_POST['role'] ?? '')));
    // This registration page is student-only. If a stale/incorrect role payload is posted,
    // normalize it to student so the application can still be submitted.
    if ($requestedRole !== 'student') {
        $_POST['role'] = 'student';
    }
    require_once dirname(__DIR__) . '/api/register_submit.php';
    exit;
}

$departmentOptions = [];
$courseOptions = [];
$sectionOptions = [];
$coordinatorOptions = [];
$supervisorOptions = [];
$courseDepartmentMap = [];
$register_toast = null;

if ($conn && $conn->connect_errno === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS student_applications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT UNSIGNED NULL,
        username VARCHAR(120) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        student_id VARCHAR(80) NULL,
        first_name VARCHAR(120) NOT NULL,
        middle_name VARCHAR(120) NULL,
        last_name VARCHAR(120) NOT NULL,
        course_id INT NULL,
        department_id INT NULL,
        section_id INT NULL,
        section_code_snapshot VARCHAR(80) NULL,
        section_name_snapshot VARCHAR(120) NULL,
        semester VARCHAR(30) NULL,
        school_year VARCHAR(16) NULL,
        address VARCHAR(255) NULL,
        phone VARCHAR(50) NULL,
        date_of_birth DATE NULL,
        gender VARCHAR(30) NULL,
        supervisor_id INT NULL,
        supervisor_name VARCHAR(255) NULL,
        coordinator_id INT NULL,
        coordinator_name VARCHAR(255) NULL,
        internal_total_hours INT NULL,
        external_total_hours INT NULL,
        assignment_track VARCHAR(20) NOT NULL DEFAULT 'internal',
        emergency_contact VARCHAR(255) NULL,
        emergency_contact_phone VARCHAR(50) NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME NULL,
        reviewed_by INT NULL,
        approval_notes VARCHAR(255) NULL,
        disciplinary_remark VARCHAR(255) NULL,
        created_student_user_id INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_student_app_user_id (user_id),
        UNIQUE KEY uq_student_app_email (email),
        KEY idx_student_app_status (status),
        KEY idx_student_app_submitted (submitted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$departmentsConn = $conn;
if ($departmentsConn && $departmentsConn->connect_errno === 0) {
    $departmentQuery = "SELECT id, code, name FROM departments ORDER BY name ASC";
    $hasIsActive = $departmentsConn->query("SHOW COLUMNS FROM departments LIKE 'is_active'");
    if ($hasIsActive && $hasIsActive->num_rows > 0) {
        $departmentQuery = "SELECT id, code, name FROM departments WHERE is_active = 1 ORDER BY name ASC";
    }

    $departmentResult = $departmentsConn->query($departmentQuery);
    if ($departmentResult) {
        while ($departmentRow = $departmentResult->fetch_assoc()) {
            $id = isset($departmentRow['id']) ? (int)$departmentRow['id'] : 0;
            $code = isset($departmentRow['code']) ? trim((string)$departmentRow['code']) : '';
            $name = isset($departmentRow['name']) ? trim((string)$departmentRow['name']) : '';
            if ($id > 0) {
                $departmentOptions[] = [
                    'id' => $id,
                    'code' => $code,
                    'name' => $name
                ];
            }
        }
    }
}

$coursesConn = $conn;
if ($coursesConn && $coursesConn->connect_errno === 0) {
    $courseQuery = "SELECT id, code, name FROM courses ORDER BY name ASC";
    $hasDeletedAt = $coursesConn->query("SHOW COLUMNS FROM courses LIKE 'deleted_at'");
    if ($hasDeletedAt && $hasDeletedAt->num_rows > 0) {
        $courseQuery = "SELECT id, code, name FROM courses WHERE deleted_at IS NULL ORDER BY name ASC";
    }

    $courseResult = $coursesConn->query($courseQuery);
    if ($courseResult) {
        while ($courseRow = $courseResult->fetch_assoc()) {
            $id = isset($courseRow['id']) ? (int) $courseRow['id'] : 0;
            $code = isset($courseRow['code']) ? trim((string) $courseRow['code']) : '';
            $name = isset($courseRow['name']) ? trim((string) $courseRow['name']) : '';
            if ($id > 0) {
                $courseOptions[] = [
                    'id' => $id,
                    'code' => $code,
                    'name' => $name
                ];
            }
        }
    }
}

$relationsConn = $conn;
if ($relationsConn && $relationsConn->connect_errno === 0) {
    $sectionQuery = "SELECT id, course_id, department_id, code, name FROM sections WHERE 1=1";
    $hasSectionDeletedAt = $relationsConn->query("SHOW COLUMNS FROM sections LIKE 'deleted_at'");
    if ($hasSectionDeletedAt && $hasSectionDeletedAt->num_rows > 0) {
        $sectionQuery .= " AND deleted_at IS NULL";
    }
    $hasSectionActive = $relationsConn->query("SHOW COLUMNS FROM sections LIKE 'is_active'");
    if ($hasSectionActive && $hasSectionActive->num_rows > 0) {
        $sectionQuery .= " AND is_active = 1";
    }
    $sectionQuery .= " ORDER BY course_id ASC, code ASC, name ASC";
    $sectionResult = $relationsConn->query($sectionQuery);
    if ($sectionResult) {
        while ($row = $sectionResult->fetch_assoc()) {
            $id = isset($row['id']) ? (int)$row['id'] : 0;
            $courseId = isset($row['course_id']) ? (int)$row['course_id'] : 0;
            $departmentId = isset($row['department_id']) ? (int)$row['department_id'] : 0;
            $code = isset($row['code']) ? trim((string)$row['code']) : '';
            $name = isset($row['name']) ? trim((string)$row['name']) : '';
            if ($id > 0 && $courseId > 0) {
                $sectionOptions[] = [
                    'id' => $id,
                    'course_id' => $courseId,
                    'department_id' => $departmentId,
                    'code' => $code,
                    'name' => $name
                ];
                if ($departmentId > 0) {
                    if (!isset($courseDepartmentMap[$courseId])) {
                        $courseDepartmentMap[$courseId] = [];
                    }
                    $courseDepartmentMap[$courseId][$departmentId] = true;
                }
            }
        }
    }

    $coordinatorQuery = "
        SELECT
            c.id,
            c.department_id,
            CONCAT(c.first_name, ' ', c.last_name) AS full_name,
            COALESCE(NULLIF(TRIM(c.office_location), ''), 'N/A') AS office_location
        FROM coordinators c
        WHERE c.is_active = 1
    ";
    $hasCoordinatorDeletedAt = $relationsConn->query("SHOW COLUMNS FROM coordinators LIKE 'deleted_at'");
    if ($hasCoordinatorDeletedAt && $hasCoordinatorDeletedAt->num_rows > 0) {
        $coordinatorQuery .= " AND c.deleted_at IS NULL";
    }
    $coordinatorQuery .= " ORDER BY c.first_name ASC, c.last_name ASC";
    $coordinatorResult = $relationsConn->query($coordinatorQuery);
    if ($coordinatorResult) {
        while ($row = $coordinatorResult->fetch_assoc()) {
            $id = isset($row['id']) ? (int)$row['id'] : 0;
            if ($id > 0) {
                $coordinatorOptions[] = [
                    'id' => $id,
                    'department_id' => isset($row['department_id']) ? (int)$row['department_id'] : 0,
                    'full_name' => isset($row['full_name']) ? trim((string)$row['full_name']) : '',
                    'office_location' => isset($row['office_location']) ? trim((string)$row['office_location']) : 'N/A'
                ];
            }
        }
    }

    $supervisorQuery = "
        SELECT
            s.id,
            s.department_id,
            CONCAT(s.first_name, ' ', s.last_name) AS full_name,
            COALESCE(NULLIF(TRIM(d.name), ''), 'N/A') AS office_location
        FROM supervisors s
        LEFT JOIN departments d ON d.id = s.department_id
        WHERE s.is_active = 1
    ";
    $hasSupervisorDeletedAt = $relationsConn->query("SHOW COLUMNS FROM supervisors LIKE 'deleted_at'");
    if ($hasSupervisorDeletedAt && $hasSupervisorDeletedAt->num_rows > 0) {
        $supervisorQuery .= " AND s.deleted_at IS NULL";
    }
    $supervisorQuery .= " ORDER BY s.first_name ASC, s.last_name ASC";
    $supervisorResult = $relationsConn->query($supervisorQuery);
    if ($supervisorResult) {
        while ($row = $supervisorResult->fetch_assoc()) {
            $id = isset($row['id']) ? (int)$row['id'] : 0;
            if ($id > 0) {
                $supervisorOptions[] = [
                    'id' => $id,
                    'department_id' => isset($row['department_id']) ? (int)$row['department_id'] : 0,
                    'full_name' => isset($row['full_name']) ? trim((string)$row['full_name']) : '',
                    'office_location' => isset($row['office_location']) ? trim((string)$row['office_location']) : 'N/A'
                ];
            }
        }
    }
}

if (isset($_GET['registered'])) {
    $reg = (string)$_GET['registered'];
    $msg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';

    if ($reg === 'exists') {
        $register_toast = [
            'type' => 'warning',
            'message' => ($msg !== '' ? $msg : 'An account with that email or username already exists.')
        ];
    } elseif ($reg === 'error') {
        $register_toast = [
            'type' => 'error',
            'message' => ($msg !== '' ? $msg : 'An error occurred while registering.')
        ];
    } elseif ($reg === 'pending') {
        $register_toast = [
            'type' => 'info',
            'message' => ($msg !== '' ? $msg : 'Application received. Please wait for approval from an administrator, coordinator, or supervisor.')
        ];
    } elseif ($reg === 'student') {
        $register_toast = [
            'type' => 'info',
            'message' => 'Application received. Please wait for approval from an administrator, coordinator, or supervisor.'
        ];
    } elseif (in_array($reg, ['coordinator', 'supervisor', 'admin'], true)) {
        $register_toast = [
            'type' => 'success',
            'message' => 'Registration successful. You may now log in.'
        ];
    }
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
    <meta name="author" content="theme_ocean">
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>BioTern || Apply</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico?v=20260310">
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/modules/shared/theme-state-core.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-preload-init.min.js"></script>
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/css/datepicker.min.css">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/smacss.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/modules/app-ui-select-dropdown.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/modules/app-ui-datepicker.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/state/notification-skin.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/modules/auth/auth-register-creative.css">
    <!--! END: Custom CSS-->
    
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
</head>

<body class="auth-register-page">
    <div class="login-bg-watermark register-bg-watermark" aria-hidden="true"></div>
    
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="auth-minimal-wrapper">
        <div class="auth-minimal-inner">
            <div class="minimal-card-wrapper">
                <div class="card register-auth-card mb-4 position-relative">
                    <div class="register-floating-logos position-absolute translate-middle top-0 start-50" aria-label="BioTern and school partnership logos">
                        <div class="register-brand-partnership">
                            <span class="register-logo-badge register-logo-badge--biotern">
                                <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png" alt="BioTern logo" class="img-fluid">
                            </span>
                            <span class="register-partner-divider">x</span>
                            <span class="register-logo-badge register-logo-badge--school">
                                <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/ccstlogo.png" alt="School logo" class="img-fluid">
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-sm-5">
                        <div class="register-hero">
                            <h2 class="fs-20 fw-bolder mb-4">Apply</h2>
                            <div class="mb-3">
                                <a href="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>index.php" class="btn btn-sm btn-outline-primary">&#8592; Back to Home</a>
                            </div>
                            <h4 class="fs-13 fw-bold mb-2">Manage your Internship account in one place.</h4>
                            <p class="fs-12 fw-medium text-muted">Let's get you all setup, so you can verify your personal account and begin setting up your profile.</p>
                        </div>

                        <!-- STUDENT REGISTRATION FORM -->
                        <form id="studentForm" class="w-100 mt-4 pt-2 show-form" action="" method="post" autocomplete="off" novalidate>
                            <input type="hidden" name="role" value="student">
                            <div class="form-section">
                                <h3 class="fs-18 fw-bold mb-3">Student Application</h3>
                            </div>
                            <div class="form-stepper" data-form="studentForm">
                                <div class="stepper-track">
                                    <span class="step-dot" data-step="1" data-label="Personal"></span>
                                    <span class="step-dot" data-step="2" data-label="Academic"></span>
                                    <span class="step-dot" data-step="3" data-label="Additional"></span>
                                    <span class="step-dot" data-step="4" data-label="Account"></span>
                                </div>
                                <div class="stepper-meta">
                                    <span class="stepper-label">Personal</span>
                                    <span class="stepper-count">Step 1 of 4</span>
                                </div>
                            </div>
                            <!-- Personal Information Section -->
                            <div class="mb-4 step-panel register-identity-fields" data-step="1">
                                <h5 class="fs-14 fw-bold mb-3">Personal Information</h5>
                                <div class="row g-3 register-field-grid register-field-grid-halves register-identity-grid">
                                    <div class="col-12 col-md-6 mb-2">
                                        <input type="text" name="student_id" class="form-control register-field-input" placeholder="School ID Number" autocomplete="off" required pattern="^05-[0-9]{4,5}$" maxlength="8" title="Use format 05-1234 or 05-12345" value="05-">
                                    </div>
                                    <div class="col-12 col-md-6 mb-2">
                                        <input type="text" name="first_name" class="form-control register-field-input" placeholder="First name" autocomplete="given-name" required>
                                    </div>
                                    <div class="col-12 col-md-6 mb-2">
                                        <input type="text" name="middle_name" class="form-control register-field-input" placeholder="Middle name" autocomplete="additional-name">
                                    </div>
                                    <div class="col-12 col-md-6 mb-2">
                                        <input type="text" name="last_name" class="form-control register-field-input" placeholder="Last name" autocomplete="family-name" required>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 mb-2">
                                        <label class="form-label fs-12 mb-1" for="studentStreetAddress">Current Address</label>
                                        <input type="text" id="studentStreetAddress" class="form-control" placeholder="Street / House No. (optional)" autocomplete="street-address" data-no-floating="1">
                                        <input type="hidden" name="address" id="studentAddress">
                                    </div>
                                </div>
                                <div class="row g-3 student-location-row">
                                    <div class="col-12 col-lg-4 col-md-6 mb-2 register-select-stack">
                                        <label class="form-label fs-12 mb-1" for="studentProvinceSelect">Province</label>
                                        <select id="studentProvinceSelect" class="form-control" required>
                                            <option value="" selected disabled>Select Province</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-lg-4 col-md-6 mb-2 register-select-stack">
                                        <label class="form-label fs-12 mb-1" for="studentCitySelect">City / Municipality</label>
                                        <select id="studentCitySelect" class="form-control" required disabled>
                                            <option value="" selected disabled>Select City / Municipality</option>
                                        </select>
                                    </div>
                                    <div class="col-12 col-lg-4 col-md-6 mb-2 register-select-stack">
                                        <label class="form-label fs-12 mb-1" for="studentBarangaySelect">Barangay</label>
                                        <select id="studentBarangaySelect" class="form-control" required disabled>
                                            <option value="" selected disabled>Select Barangay</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="step-actions">
                                    <span></span>
                                    <button type="button" class="btn btn-primary" data-step-action="next">Next</button>
                                </div>
                            </div>

                            <!-- Academic Information Section -->
                            <div class="mb-4 step-panel" data-step="2">
                                <h5 class="fs-14 fw-bold mb-3">Academic Information</h5>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6 mb-2">
                                        <label class="form-label fs-12" for="studentCourseSelect">Course</label>
                                        <select name="course_id" id="studentCourseSelect" class="form-control dynamic-course-select" data-section-target="studentSectionSelect" required>
                                            <option value="" disabled selected>Select Course</option>
                                            <?php

foreach ($courseOptions as $course): ?>
                                                <option
                                                    value="<?php

echo htmlspecialchars((string) $course['id']); ?>"
                                                    data-course-code="<?php

echo htmlspecialchars((string) $course['code']); ?>"
                                                >
                                                    <?php

echo htmlspecialchars(
                                                        $course['name'] !== '' && $course['code'] !== ''
                                                            ? ($course['code'] . ' - ' . $course['name'])
                                                            : ($course['name'] !== '' ? $course['name'] : $course['code'])
                                                    );
                                                    ?>
                                                </option>
                                            <?php

endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 mb-2">
                                        <label class="form-label fs-12" for="studentSectionSelect">Section</label>
                                        <select name="section" id="studentSectionSelect" class="form-control" required>
                                        </select>
                                    </div>
                                </div>
                                <input type="hidden" name="department_id" id="studentDepartmentSelect" value="">
                                <div class="row g-3">
                                    <div class="col-12 col-md-4 mb-2">
                                        <label class="form-label fs-12" for="studentSchoolYear">School Year</label>
                                        <select name="school_year" id="studentSchoolYear" class="form-control" required>
                                            <?php

$currentYear = (int)date('Y');
$startYear = 2005;
for ($y = $currentYear; $y >= $startYear; $y--):
    $label = $y . '-' . ($y + 1);
?>
                                                <option value="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $y === $currentYear ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-4 mb-2">
                                        <label class="form-label fs-12" for="studentSemester">Semester</label>
                                        <select name="semester" id="studentSemester" class="form-control" required>
                                            <option value="" disabled selected>Select Semester</option>
                                            <option value="1st Semester">1st Semester</option>
                                            <option value="2nd Semester">2nd Semester</option>
                                            <option value="Summer">Summer</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6 mb-2">
                                        <label class="form-label fs-12" for="studentCoordinatorSelect">Coordinator</label>
                                        <select name="coordinator_id" id="studentCoordinatorSelect" class="form-control" required>
                                            <option value="" disabled selected>Select Coordinator</option>
                                            <option value="0">To be assigned</option>
                                            <?php

foreach ($coordinatorOptions as $coordinator): ?>
                                                <?php

$fullName = (string)$coordinator['full_name'];
                                                $office = (string)$coordinator['office_location'];
                                                $defaultLabel = $fullName;
                                                $actLabel = $fullName . ' - Coordinator | Office: ' . $office;
                                                ?>
                                                <option
                                                    value="<?php

echo (int)$coordinator['id']; ?>"
                                                    data-department-id="<?php

echo (int)$coordinator['department_id']; ?>"
                                                    data-default-label="<?php

echo htmlspecialchars($defaultLabel); ?>"
                                                    data-act-label="<?php

echo htmlspecialchars($actLabel); ?>"
                                                >
                                                    <?php

echo htmlspecialchars($defaultLabel); ?>
                                                </option>
                                            <?php

endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6 mb-2">
                                        <label class="form-label fs-12" for="studentSupervisorSelect">Supervisor</label>
                                        <select name="supervisor_id" id="studentSupervisorSelect" class="form-control" required>
                                            <option value="" disabled selected>Select Supervisor</option>
                                            <option value="0">To be assigned</option>
                                            <?php

foreach ($supervisorOptions as $supervisor): ?>
                                                <?php

$fullName = (string)$supervisor['full_name'];
                                                $office = (string)$supervisor['office_location'];
                                                $defaultLabel = $fullName;
                                                $actLabel = $fullName . ' - Supervisor | Office: ' . $office;
                                                ?>
                                                <option
                                                    value="<?php

echo (int)$supervisor['id']; ?>"
                                                    data-department-id="<?php

echo (int)$supervisor['department_id']; ?>"
                                                    data-default-label="<?php

echo htmlspecialchars($defaultLabel); ?>"
                                                    data-act-label="<?php

echo htmlspecialchars($actLabel); ?>"
                                                >
                                                    <?php

echo htmlspecialchars($defaultLabel); ?>
                                                </option>
                                            <?php

endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 mb-2">
                                        <small class="text-muted">Tip: If you are not sure yet, select "To be assigned" and the approver can update it later.</small>
                                    </div>
                                </div>
                                <input type="hidden" id="studentInternalTotalHours" name="internal_total_hours" value="140">
                                <input type="hidden" name="external_total_hours" id="externalTotalHoursInput" value="250">
                                <input type="hidden" name="finished_internal" id="finishedInternalSelect" value="no">
                                <div class="step-actions">
                                    <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                    <button type="button" class="btn btn-primary" data-step-action="next">Next</button>
                                </div>
                            </div>

                            <!-- Additional Information Section -->
                            <div class="mb-4 step-panel" data-step="3">
                                <h5 class="fs-14 fw-bold mb-3">Additional Information</h5>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6 mb-2">
                                        <label class="form-label fs-12" for="studentDateOfBirth">Date of Birth</label>
                                        <input type="date" id="studentDateOfBirth" name="date_of_birth" class="form-control" autocomplete="bday" data-no-floating="1" required>
                                    </div>
                                    <div class="col-12 col-md-6 mb-2">
                                        <label class="form-label fs-12" for="studentGender">Gender</label>
                                        <select id="studentGender" name="gender" class="form-control" autocomplete="off" required>
                                            <option value="" disabled selected>Select Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6 mb-2">
                                        <label class="form-label fs-12" for="studentPhone">Phone Number</label>
                                        <input type="tel" id="studentPhone" name="phone" class="form-control" placeholder="Phone Number" autocomplete="tel" data-no-floating="1" required>
                                    </div>
                                    <div class="col-12 col-md-6 mb-2">
                                        <label class="form-label fs-12" for="studentEmergencyContact">Emergency Contact Name</label>
                                        <input type="text" id="studentEmergencyContact" name="emergency_contact" class="form-control" placeholder="Emergency Contact Name" autocomplete="name" data-no-floating="1" required>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label class="form-label fs-12" for="studentEmergencyContactPhone">Emergency Contact Phone</label>
                                        <input type="tel" id="studentEmergencyContactPhone" name="emergency_contact_phone" class="form-control" placeholder="Emergency Contact Phone Number" autocomplete="tel" data-no-floating="1" required>
                                    </div>
                                </div>
                                <div class="step-actions">
                                    <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                    <button type="button" class="btn btn-primary" data-step-action="next">Next</button>
                                </div>
                            </div>

                            <!-- Account Information Section -->
                            <div class="mb-4 step-panel" data-step="4">
                                <h5 class="fs-14 fw-bold mb-3">Account Information</h5>
                                <div class="row g-3">
                                    <div class="col-12 mb-2">
                                        <input type="email" name="account_email" class="form-control" placeholder="Account Email Address" autocomplete="email" required>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6 mb-2">
                                        <div class="input-group field">
                                            <input type="password" name="password" class="form-control password" id="studentPassword" placeholder="Password" autocomplete="new-password" required>
                                            <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="studentPassword" aria-label="Show password"><i></i></div>
                                        </div>
                                        <div class="password-strength-indicator" id="studentPasswordStrength" aria-live="polite">
                                            <div class="password-strength-bars" aria-hidden="true">
                                                <span></span>
                                                <span></span>
                                                <span></span>
                                                <span></span>
                                            </div>
                                            <div class="password-strength-text">Password strength: Not entered</div>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 mb-2">
                                        <div class="input-group field">
                                            <input type="password" name="confirm_password" class="form-control" id="studentConfirmPassword" placeholder="Confirm password" autocomplete="new-password" required>
                                            <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="studentConfirmPassword" aria-label="Show password"><i></i></div>
                                        </div>
                                    </div>
                                </div>
                            <div class="register-account-options mt-4">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="receiveMial">
                                    <label class="custom-control-label c-pointer text-muted" for="receiveMial" style="font-weight: 400 !important">Yes, I want to receive BioTern community emails</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="termsCondition" required>
                                    <label class="custom-control-label c-pointer text-muted" for="termsCondition" style="font-weight: 400 !important">I have read and agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms &amp; Conditions</a>.</label>
                                </div>
                            </div>
                            <div class="step-actions">
                                <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                <button type="submit" class="btn btn-primary" id="studentApplyBtn">Apply</button>
                            </div>
                            </div>
                        </form>

                        <!-- Terms & Conditions Modal -->
                        <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable">
                                <div class="modal-content register-terms-modal">
                                    <div class="modal-header">
                                        <div>
                                            <h5 class="modal-title" id="termsModalLabel">BioTern Student Application Terms</h5>
                                            <p class="register-terms-subtitle mb-0">Please review these terms before submitting your application.</p>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body register-terms-body">
                                        <div class="register-terms-section">
                                            <h6>1. Accurate Information</h6>
                                            <p>You confirm that the information you submit is complete, truthful, and belongs to you. Inaccurate, incomplete, or misleading details may cause your application to be delayed, rejected, or cancelled.</p>
                                        </div>
                                        <div class="register-terms-section">
                                            <h6>2. Review and Approval</h6>
                                            <p>Your application will remain pending until it is reviewed by an authorized BioTern approver such as an administrator, coordinator, or supervisor. Access is not guaranteed until approval is completed.</p>
                                        </div>
                                        <div class="register-terms-section">
                                            <h6>3. Academic and Internship Details</h6>
                                            <p>Your submitted course, section, coordinator, supervisor, and related internship details may be verified or updated by school staff when needed to match official records or placement decisions.</p>
                                        </div>
                                        <div class="register-terms-section">
                                            <h6>4. Biometric and Attendance Use</h6>
                                            <p>If your application is approved, your account may be linked to attendance, fingerprint, and internship monitoring records used for school operations, supervision, reporting, and required documentation.</p>
                                        </div>
                                        <div class="register-terms-section">
                                            <h6>5. Student Responsibility</h6>
                                            <p>You are responsible for protecting your login details and for using the platform appropriately. Abuse, impersonation, or misuse of the system may lead to account restrictions or disciplinary action.</p>
                                        </div>
                                        <div class="register-terms-note">
                                            If you have questions about your application, account approval, or biometric processing, contact the school administrator before submitting.
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="studentReviewModal" tabindex="-1" aria-labelledby="studentReviewModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="studentReviewModalLabel">Final Review</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body" id="studentReviewContent"></div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Edit Details</button>
                                        <button type="button" class="btn btn-primary" id="studentConfirmSubmitBtn">Confirm and Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="loginLink" class="mt-5 text-muted show-form">
                            <span>Already have an account?</span>
                            <a href="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>auth-login.php" class="fw-bold">Login</a>
                        </div>
                        <div id="loginLinkHidden" class="mt-5 text-muted hide-form">
                            <span>Already have an account?</span>
                            <a href="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>auth-login.php" class="fw-bold">Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->
    <!--! ================================================================ !-->
    <div id="registerData"
         class="d-none"
         data-course-map="<?php echo htmlspecialchars(json_encode($courseDepartmentMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
         data-section-records="<?php echo htmlspecialchars(json_encode($sectionOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"
         data-departments="<?php echo htmlspecialchars(json_encode($departmentOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"></div>
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/vendors.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/select2.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/datepicker.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/lslstrength.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/common-init.min.js"></script>
    <!--! END: Apps Init !-->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-customizer-init.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/modules/shared/unified-date-picker.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/modules/shared/custom-select-dropdown.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/modules/auth/auth-register-creative.js"></script>
    <?php if ($register_toast !== null): ?>
    <script>
    (function () {
        var payload = <?php echo json_encode($register_toast, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        if (!payload || !payload.message) {
            return;
        }

        var variantMap = {
            success: 'success',
            info: 'info',
            warning: 'warning',
            danger: 'error',
            error: 'error'
        };
        var iconMap = {
            success: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 7 9 18l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            info: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 10v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 7h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            warning: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M15 9 9 15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="m9 9 6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
        };

        var variant = variantMap[payload.type] || 'info';
        var root = document.body || document.documentElement;
        if (!root) {
            return;
        }

        var toast = document.createElement('div');
        toast.id = 'authRegisterToast';
        toast.className = 'app-theme-toast-static app-theme-toast-static--' + variant;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');

        var iconWrap = document.createElement('span');
        iconWrap.className = 'app-theme-toast-static-icon';

        var iconEl = document.createElement('span');
        iconEl.className = 'app-theme-toast-static-icon-glyph';
        iconEl.setAttribute('aria-hidden', 'true');
        iconEl.innerHTML = iconMap[variant] || iconMap.info;
        iconWrap.appendChild(iconEl);

        var textWrap = document.createElement('span');
        textWrap.className = 'app-theme-toast-static-text';
        textWrap.textContent = String(payload.message);

        toast.appendChild(iconWrap);
        toast.appendChild(textWrap);
        root.appendChild(toast);

        window.setTimeout(function () {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5200);
    })();
    </script>
    <?php endif; ?>
</body>

</html>












