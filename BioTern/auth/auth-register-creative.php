<?php
require_once dirname(__DIR__) . '/config/db.php';
// Handle submissions immediately to avoid rendering/query side effects before redirects.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestedRole = strtolower(trim((string)($_POST['role'] ?? '')));
    if ($requestedRole !== 'student') {
        $msg = rawurlencode('Staff accounts are created by an admin. Please contact your administrator.');
        header('Location: auth-register-creative.php?registered=error&msg=' . $msg);
        exit;
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
$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/auth/') !== false) ? '../' : '';
$route_prefix = $asset_prefix;
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
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/smacss.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/modules/auth/auth-register-creative.css">
    <!--! END: Custom CSS-->
    
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
</head>

<body>
    
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="auth-minimal-wrapper">
        <div class="auth-minimal-inner">
            <div class="minimal-card-wrapper">
                <div class="card register-auth-card mb-4 mt-5 mx-2 mx-sm-0 position-relative">
                    <div class="wd-50 bg-white p-2 rounded-circle shadow-lg position-absolute translate-middle top-0 start-50">
                        <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <div class="card-body p-sm-5">
                        <h2 class="fs-20 fw-bolder mb-4">Apply</h2>
                        <div class="mb-3">
                            <a href="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>index.php" class="btn btn-sm btn-outline-primary">&#8592; Back to Home</a>
                        </div>
                        <h4 class="fs-13 fw-bold mb-2">Manage your Internship account in one place.</h4>
                        <p class="fs-12 fw-medium text-muted">Let's get you all setup, so you can verify your personal account and begin setting up your profile.</p>
                        <?php

if (isset($_GET['registered'])) {
                            $reg = $_GET['registered'];
                            $msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
                            if ($reg === 'exists') {
                                echo '<div class="alert alert-warning" role="alert">' . ($msg ?: 'An account with that email or username already exists.') . '</div>';
                            } elseif ($reg === 'error') {
                                echo '<div class="alert alert-danger" role="alert">' . ($msg ?: 'An error occurred while registering.') . '</div>';
                            } elseif ($reg === 'pending') {
                                echo '<div class="alert alert-info" role="alert">' . ($msg ?: 'Application submitted. Please wait for approval.') . '</div>';
                            } elseif (in_array($reg, ['student','coordinator','supervisor','admin'])) {
                                echo '<div class="alert alert-success" role="alert">Registration successful. You may now login.</div>';
                            }
                        }
                        ?>
                        
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
                            <div class="mb-4 step-panel" data-step="1">
                                <h5 class="fs-14 fw-bold mb-3">Personal Information</h5>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <input type="text" name="student_id" class="form-control" placeholder="School ID Number" autocomplete="off" required pattern="^05-[0-9]{4,5}$" maxlength="8" title="Use format 05-1234 or 05-12345">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <input type="text" name="first_name" style="padding: 12px 16px;" class="form-control" placeholder="First name" autocomplete="given-name" required>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <input type="text" name="middle_name" class="form-control" placeholder="Middle name" autocomplete="additional-name">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <input type="text" name="last_name" class="form-control" placeholder="Last name" autocomplete="family-name" required>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <input type="text" name="address" class="form-control" placeholder="Full Home Address" autocomplete="street-address" required>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <input type="email" name="email" class="form-control" placeholder="Email Address" autocomplete="email" required>
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
                                    <div class="col-4 mb-2">
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
                                    <div class="col-4 mb-2">
                                        <label class="form-label fs-12" for="studentDepartmentSelect">Department</label>
                                        <select name="department_id" id="studentDepartmentSelect" class="form-control" required>
                                            <option value="" selected>Select Department</option>
                                            <option value="0">I still don't know yet (To be assigned)</option>
                                            <?php

foreach ($departmentOptions as $department): ?>
                                                <option
                                                    value="<?php

echo (int)$department['id']; ?>"
                                                    data-default-label="<?php

echo htmlspecialchars($department['name'] !== '' ? ($department['name'] . ' (' . $department['code'] . ')') : $department['code']); ?>"
                                                >
                                                    <?php

echo htmlspecialchars(
                                                        $department['name'] !== ''
                                                            ? ($department['name'] . ' (' . $department['code'] . ')')
                                                            : $department['code']
                                                    );
                                                    ?>
                                                </option>
                                            <?php

endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-4 mb-2">
                                        <label class="form-label fs-12" for="studentSectionSelect">Section</label>
                                        <select name="section" id="studentSectionSelect" class="form-control" required>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-4 mb-2">
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
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="studentCoordinatorSelect">Coordinator</label>
                                        <select name="coordinator_id" id="studentCoordinatorSelect" class="form-control" required>
                                            <option value="" disabled selected>Select Coordinator</option>
                                            <option value="0">I still don't know yet (To be assigned)</option>
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
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="studentSupervisorSelect">Supervisor</label>
                                        <select name="supervisor_id" id="studentSupervisorSelect" class="form-control" required>
                                            <option value="" disabled selected>Select Supervisor</option>
                                            <option value="0">I still don't know yet (To be assigned)</option>
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
                                        <small class="text-muted">Tip: If you are not sure yet, select "I still don't know yet (To be assigned)" and the approver can edit and assign it later.</small>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="studentInternalTotalHours">Internal Total Hours</label>
                                        <input type="number" id="studentInternalTotalHours" name="internal_total_hours" class="form-control" placeholder="Internal Total Hours" min="0" value="140" readonly>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="externalTotalHoursInput">External Total Hours</label>
                                        <input type="number" name="external_total_hours" id="externalTotalHoursInput" class="form-control" placeholder="External Total Hours" min="0" value="250" readonly>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="finishedInternalSelect">Finished Internal?</label>
                                        <select name="finished_internal" id="finishedInternalSelect" class="form-control" required>
                                            <option value="no" selected>No</option>
                                            <option value="yes">Yes</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="step-actions">
                                    <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                    <button type="button" class="btn btn-primary" data-step-action="next">Next</button>
                                </div>
                            </div>

                            <!-- Additional Information Section -->
                            <div class="mb-4 step-panel" data-step="3">
                                <h5 class="fs-14 fw-bold mb-3">Additional Information</h5>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="studentDateOfBirth">Date of Birth</label>
                                        <input type="date" id="studentDateOfBirth" name="date_of_birth" class="form-control" autocomplete="bday" required>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="studentGender">Gender</label>
                                        <select id="studentGender" name="gender" class="form-control" autocomplete="off" required>
                                            <option value="" disabled selected>Select Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="studentPhone">Phone Number</label>
                                        <input type="tel" id="studentPhone" name="phone" class="form-control" placeholder="Phone Number" autocomplete="tel" required>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="studentEmergencyContact">Emergency Contact Name</label>
                                        <input type="text" id="studentEmergencyContact" name="emergency_contact" class="form-control" placeholder="Emergency Contact Name" autocomplete="name" required>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="studentEmergencyContactPhone">Emergency Contact Phone</label>
                                        <input type="tel" id="studentEmergencyContactPhone" name="emergency_contact_phone" class="form-control" placeholder="Emergency Contact Phone Number" autocomplete="tel" required>
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
                                    <div class="col-6 mb-2">
                                        <input type="text" name="username" class="form-control" placeholder="Username" autocomplete="username" required>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <input type="email" name="account_email" class="form-control" placeholder="Account Email Address" autocomplete="email" required>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <div class="input-group field">
                                            <input type="password" name="password" class="form-control password" id="studentPassword" placeholder="Password" autocomplete="new-password" required>
                                            <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="studentPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="input-group field">
                                            <input type="password" name="confirm_password" class="form-control" id="studentConfirmPassword" placeholder="Confirm password" autocomplete="new-password" required>
                                            <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="studentConfirmPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                        </div>
                                    </div>
                                </div>
                            <div class="mt-4">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="receiveMial">
                                    <label class="custom-control-label c-pointer text-muted" for="receiveMial" style="font-weight: 400 !important">Yes, I want to receive BioTern community emails</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="termsCondition" required>
                                    <label class="custom-control-label c-pointer text-muted" for="termsCondition" style="font-weight: 400 !important">I agree to all the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms &amp; Conditions</a>.</label>
                                </div>
                            </div>
                            <div class="step-actions">
                                <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                            </div>
                        </form>

                        <!-- Terms & Conditions Modal -->
                        <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-scrollable">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="termsModalLabel">Terms &amp; Conditions</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>By submitting this application, you confirm that the information you provide is accurate and complete. Applications are subject to review and approval by the school administrator.</p>
                                        <ul>
                                            <li>Your application will be reviewed before any account is activated.</li>
                                            <li>You agree to comply with school policies and internship guidelines.</li>
                                            <li>Submitting false or misleading information may result in rejection.</li>
                                            <li>Approval or rejection decisions are final, but you may reapply if instructed.</li>
                                        </ul>
                                        <p class="mb-0">If you have questions, contact the school administrator.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- COORDINATOR REGISTRATION FORM -->
                        <form id="coordinatorForm" class="w-100 mt-4 pt-2 hide-form" action="" method="post">
                            <input type="hidden" name="role" value="coordinator">
                            <div class="form-section">
                                <h3 class="fs-18 fw-bold mb-3">Coordinator Registration</h3>
                            </div>
                            <div class="form-stepper" data-form="coordinatorForm">
                                <div class="stepper-track">
                                    <span class="step-dot" data-step="1" data-label="Personal"></span>
                                    <span class="step-dot" data-step="2" data-label="Department"></span>
                                    <span class="step-dot" data-step="3" data-label="Account"></span>
                                </div>
                                <div class="stepper-meta">
                                    <span class="stepper-label">Personal</span>
                                    <span class="stepper-count">Step 1 of 3</span>
                                </div>
                            </div>
                            <div class="mb-4 step-panel" data-step="1">
                                <h5 class="fs-14 fw-bold mb-3">Personal Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="first_name" class="form-control" placeholder="First name" autocomplete="given-name" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="last_name" class="form-control" placeholder="Last name" autocomplete="family-name" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="email" name="email" class="form-control" placeholder="Institution Email Address" autocomplete="email" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="phone" class="form-control" placeholder="Phone Number" autocomplete="tel" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 mb-2">
                                    <input type="text" name="office_location" class="form-control" placeholder="Full Office Address" autocomplete="street-address" required>
                                </div>
                            </div>

                                <div class="step-actions">
                                    <span></span>
                                    <button type="button" class="btn btn-primary" data-step-action="next">Next</button>
                                </div>
                            </div>
                            <div class="mb-4 step-panel" data-step="2">
                                <h5 class="fs-14 fw-bold mb-3 mt-4">Department Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <label class="form-label fs-12" for="coordDepartmentCode">Department Code</label>
                                    <select id="coordDepartmentCode" name="department_code" class="form-control" required>
                                        <option value="" disabled selected>Select Department</option>
                                        <?php

foreach ($departmentOptions as $department): ?>
                                            <option value="<?php

echo htmlspecialchars($department['code']); ?>">
                                                <?php

echo htmlspecialchars(
                                                    $department['name'] !== ''
                                                        ? ($department['name'] . ' (' . $department['code'] . ')')
                                                        : $department['code']
                                                );
                                                ?>
                                            </option>
                                        <?php

endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label fs-12" for="coordPosition">Position</label>
                                    <input type="text" id="coordPosition" name="position" class="form-control" placeholder="e.g., Internship Coordinator" autocomplete="organization-title" required>
                                </div>
                            </div>

                                <div class="step-actions">
                                    <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                    <button type="button" class="btn btn-primary" data-step-action="next">Next</button>
                                </div>
                            </div>
                            <div class="mb-4 step-panel" data-step="3">
                                <h5 class="fs-14 fw-bold mb-3 mt-4">Account Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="username" class="form-control" placeholder="Username" autocomplete="username" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="email" name="account_email" class="form-control" placeholder="Account Email Address" autocomplete="email" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="password" class="form-control password" id="coordPassword" placeholder="Password" autocomplete="new-password" required>
                                        <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="coordPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                    </div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="confirm_password" class="form-control" id="coordConfirmPassword" placeholder="Confirm password" autocomplete="new-password" required>
                                        <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="coordConfirmPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="receiveMail_coord" required>
                                    <label class="custom-control-label c-pointer text-muted" for="receiveMail_coord" style="font-weight: 400 !important">Yes, I want to receive BioTern community emails</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="termsCondition_coord" required>
                                    <label class="custom-control-label c-pointer text-muted" for="termsCondition_coord" style="font-weight: 400 !important">I agree to all the <a href="">Terms &amp; Conditions.</a></label>
                                </div>
                            </div>
                            <div class="step-actions">
                                <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                <button type="submit" class="btn btn-primary">Create Account</button>
                            </div>
                            </div>
                        </form>

                        <!-- SUPERVISOR REGISTRATION FORM -->
                        <form id="supervisorForm" class="w-100 mt-4 pt-2 hide-form" action="" method="post">
                            <input type="hidden" name="role" value="supervisor">
                            <div class="form-section">
                                <h3 class="fs-18 fw-bold mb-3">Supervisor Registration</h3>
                            </div>
                            <div class="form-stepper" data-form="supervisorForm">
                                <div class="stepper-track">
                                    <span class="step-dot" data-step="1" data-label="Personal"></span>
                                    <span class="step-dot" data-step="2" data-label="Company"></span>
                                    <span class="step-dot" data-step="3" data-label="Account"></span>
                                </div>
                                <div class="stepper-meta">
                                    <span class="stepper-label">Personal</span>
                                    <span class="stepper-count">Step 1 of 3</span>
                                </div>
                            </div>
                            <div class="mb-4 step-panel" data-step="1">
                                <h5 class="fs-14 fw-bold mb-3">Personal Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="first_name" class="form-control" placeholder="First name" autocomplete="given-name" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="last_name" class="form-control" placeholder="Last name" autocomplete="family-name" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="email" name="email" class="form-control" placeholder="Email Address" autocomplete="email" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="tel" name="phone" class="form-control" placeholder="Phone Number" autocomplete="tel" required>
                                </div>
                            </div>

                                <div class="step-actions">
                                    <span></span>
                                    <button type="button" class="btn btn-primary" data-step-action="next">Next</button>
                                </div>
                            </div>
                            <div class="mb-4 step-panel" data-step="2">
                                <h5 class="fs-14 fw-bold mb-3 mt-4">Company Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="company_name" class="form-control" value="Clark College of Science and Technology" autocomplete="organization" readonly>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="job_position" class="form-control" placeholder="Job Position" autocomplete="organization-title" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <select name="department_code" class="form-control" required>
                                        <option value="" disabled selected>Select Department</option>
                                        <?php

foreach ($departmentOptions as $department): ?>
                                            <option value="<?php

echo htmlspecialchars($department['code']); ?>">
                                                <?php

echo htmlspecialchars(
                                                    $department['name'] !== ''
                                                        ? ($department['name'] . ' (' . $department['code'] . ')')
                                                        : $department['code']
                                                );
                                                ?>
                                            </option>
                                        <?php

endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="office" class="form-control" placeholder="Office" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 mb-2">
                                    <input type="text" name="company_address" class="form-control" value="AUREA ST. SAMSONVILLE, DAU MABALACAT CITY PAMPANGA" autocomplete="street-address" readonly>
                                </div>
                            </div>

                                <div class="step-actions">
                                    <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                    <button type="button" class="btn btn-primary" data-step-action="next">Next</button>
                                </div>
                            </div>
                            <div class="mb-4 step-panel" data-step="3">
                                <h5 class="fs-14 fw-bold mb-3 mt-4">Account Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="username" class="form-control" placeholder="Username" autocomplete="username" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="email" name="account_email" class="form-control" placeholder="Account Email Address" autocomplete="email" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="password" class="form-control password" id="supPassword" placeholder="Password" autocomplete="new-password" required>
                                        <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="supPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                    </div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="confirm_password" class="form-control" id="supConfirmPassword" placeholder="Confirm password" autocomplete="new-password" required>
                                        <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="supConfirmPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="receiveMail_sup" required>
                                    <label class="custom-control-label c-pointer text-muted" for="receiveMail_sup" style="font-weight: 400 !important">Yes, I want to receive BioTern community emails</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="termsCondition_sup" required>
                                    <label class="custom-control-label c-pointer text-muted" for="termsCondition_sup" style="font-weight: 400 !important">I agree to all the <a href="">Terms &amp; Conditions.</a></label>
                                </div>
                            </div>
                            <div class="step-actions">
                                <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                <button type="submit" class="btn btn-primary">Create Account</button>
                            </div>
                            </div>
                        </form>

                        <!-- ADMIN REGISTRATION FORM -->
                        <form id="adminForm" class="w-100 mt-4 pt-2 hide-form" action="" method="post">
                            <input type="hidden" name="role" value="admin">
                            <div class="form-section">
                                <h3 class="fs-18 fw-bold mb-3">Admin Registration</h3>
                            </div>
                            <div class="form-stepper" data-form="adminForm">
                                <div class="stepper-track">
                                    <span class="step-dot" data-step="1" data-label="Personal"></span>
                                    <span class="step-dot" data-step="2" data-label="Admin"></span>
                                    <span class="step-dot" data-step="3" data-label="Account"></span>
                                </div>
                                <div class="stepper-meta">
                                    <span class="stepper-label">Personal</span>
                                    <span class="stepper-count">Step 1 of 3</span>
                                </div>
                            </div>
                            <div class="mb-4 step-panel" data-step="1">
                                <h5 class="fs-14 fw-bold mb-3">Personal Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="first_name" class="form-control" placeholder="First name" autocomplete="given-name" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="last_name" class="form-control" placeholder="Last name" autocomplete="family-name" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="email" name="email" class="form-control" placeholder="Institution Email Address" autocomplete="email" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="tel" name="phone" class="form-control" placeholder="Phone Number" autocomplete="tel" required>
                                </div>
                            </div>

                                <div class="step-actions">
                                    <span></span>
                                    <button type="button" class="btn btn-primary" data-step-action="next">Next</button>
                                </div>
                            </div>
                            <div class="mb-4 step-panel" data-step="2">
                                <h5 class="fs-14 fw-bold mb-3 mt-4">Administrative Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <label class="form-label fs-12" for="adminLevel">Admin Level</label>
                                    <select id="adminLevel" class="form-control" name="admin_level" required>
                                        <option value="" disabled selected>Select Admin Level</option>
                                        <option value="head_admin">Head Admin</option>
                                        <option value="admin">Admin</option>
                                        <option value="staff">Staff</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label fs-12" for="adminDepartmentCode">Department Code</label>
                                    <select id="adminDepartmentCode" name="department_code" class="form-control" required>
                                        <option value="" disabled selected>Select Department</option>
                                        <?php

foreach ($departmentOptions as $department): ?>
                                            <option value="<?php

echo htmlspecialchars($department['code']); ?>">
                                                <?php

echo htmlspecialchars(
                                                    $department['name'] !== ''
                                                        ? ($department['name'] . ' (' . $department['code'] . ')')
                                                        : $department['code']
                                                );
                                                ?>
                                            </option>
                                        <?php

endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Course & Section removed for admin registration (hidden fields preserved) -->
                            <input type="hidden" name="admin_course_id" value="">
                            <input type="hidden" name="admin_section" value="">
                            <div class="row g-3">
                                <div class="col-12 mb-2">
                                    <input type="text" name="admin_position" class="form-control" placeholder="Official Title/Position" autocomplete="organization-title" required>
                                </div>
                            </div>

                                <div class="step-actions">
                                    <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                    <button type="button" class="btn btn-primary" data-step-action="next">Next</button>
                                </div>
                            </div>
                            <div class="mb-4 step-panel" data-step="3">
                                <h5 class="fs-14 fw-bold mb-3 mt-4">Account Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="username" class="form-control" placeholder="Username" autocomplete="username" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="email" name="account_email" class="form-control" placeholder="Account Email Address" autocomplete="email" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="password" class="form-control password" id="adminPassword" placeholder="Password" autocomplete="new-password" required>
                                        <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="adminPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                    </div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="confirm_password" class="form-control" id="adminConfirmPassword" placeholder="Confirm password" autocomplete="new-password" required>
                                        <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="adminConfirmPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="receiveMail_admin" required>
                                    <label class="custom-control-label c-pointer text-muted" for="receiveMail_admin" style="font-weight: 400 !important">Yes, I want to receive BioTern community emails</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="termsCondition_admin" required>
                                    <label class="custom-control-label c-pointer text-muted" for="termsCondition_admin" style="font-weight: 400 !important">I agree to all the <a href="">Terms &amp; Conditions.</a></label>
                                </div>
                            </div>
                            <div class="step-actions">
                                <button type="button" class="btn btn-outline-secondary" data-step-action="prev">Back</button>
                                <button type="submit" class="btn btn-primary">Create Account</button>
                            </div>
                        </form>

                        <div id="loginLink" class="mt-5 text-muted show-form">
                            <span>Already have an account?</span>
                            <a href="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>auth-login-cover.php" class="fw-bold">Login</a>
                        </div>
                        <div id="loginLinkHidden" class="mt-5 text-muted hide-form">
                            <span>Already have an account?</span>
                            <a href="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>auth-login-cover.php" class="fw-bold">Login</a>
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
         data-section-records="<?php echo htmlspecialchars(json_encode($sectionOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>"></div>
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/vendors.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/select2.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/vendors/js/lslstrength.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/common-init.min.js"></script>
    <!--! END: Apps Init !-->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-customizer-init.min.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/modules/auth/auth-register-creative.js"></script>
</body>

</html>












