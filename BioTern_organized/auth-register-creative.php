<?php
$departmentOptions = [];
$courseOptions = [];
$sectionOptions = [];
$coordinatorOptions = [];
$supervisorOptions = [];
$courseDepartmentMap = [];
$dbHost = '127.0.0.1';
$dbUser = 'root';
$dbPass = '';
$dbName = 'biotern_db';

$departmentsConn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
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
    $departmentsConn->close();
}

$coursesConn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
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
    $coursesConn->close();
}

$relationsConn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
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
    $relationsConn->close();
}
    // If this page receives a POST, delegate processing to register_submit.php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/register_submit.php';
        // register_submit.php will redirect on success/failure
        exit;
    }
?>
<!DOCTYPE html>
<html lang="zxx">
<style>
    .progress-bar div {
        background-color: #FF4757;  /* Red - weak password */
        height: 3px;
        border-radius: 2px;
        margin-right: 4px;
    }
    
    .progress-bar div.active {
        background-color: #FFA502;  /* Orange - medium */
    }
    
    .progress-bar div.active.strong {
        background-color: #2ED573;  /* Green - strong */
    }

    .role-card {
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 25px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: #fff;
        min-height: 180px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: #1a1a1a;
    }

    .role-card:hover {
        border-color: #FF4757;
        box-shadow: 0 5px 20px rgba(255, 71, 87, 0.15);
        transform: translateY(-5px);
    }

    .role-card.selected {
        border-color: #FF4757;
        background: #fff5f5;
        box-shadow: 0 5px 20px rgba(255, 71, 87, 0.2);
    }

    /* Dark mode support using html.app-skin-dark */
    html.app-skin-dark .role-card {
        background: #001033;
        border-color: #444444;
        color: #e0e0e0;
    }

    html.app-skin-dark .role-card:hover {
        border-color: #FF4757;
        box-shadow: 0 5px 20px rgba(255, 71, 87, 0.3);
        background: #373737;
    }

    html.app-skin-dark .role-card.selected {
        background: #3d2a2a;
        border-color: #FF4757;
        box-shadow: 0 5px 20px rgba(255, 71, 87, 0.4);
    }

    html.app-skin-dark .role-card h5 {
        color: #f0f0f0;
    }

    html.app-skin-dark .role-card p {
        color: #b0b0b0;
    }

    .role-icon {
        font-size: 48px;
        margin-bottom: 12px;
        display: inline-block;
        line-height: 1;
    }

    .role-card h5 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 8px;
        color: #1a1a1a;
    }

    .role-card p {
        font-size: 13px;
        color: #6c757d;
        margin: 0;
        line-height: 1.4;
    }

    .hide-form {
        display: none;
    }

    .show-form {
        display: block;
    }

    .form-section {
        margin-top: 30px;
    }

    /* Role selector responsive/swipe styles */
    .roles-wrapper {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 10px;
        justify-content: center;
    }

    .roles-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        grid-auto-rows: 1fr;
        gap: 1rem;
        width: 100%;
    }

    /* Outer boxed container to visually group the 2x2 choices */
    .roles-container {
        border: none; /* removed thick black box */
        padding: 8px;
        display: flex;
        align-items: stretch;
        justify-content: center;
        max-width: 740px;
        width: 100%;
        background: transparent;
    }

    .roles-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        width: 100%;
    }

    .arrow-btn {
        display: none;
        min-width: 40px;
        height: 40px;
        border-radius: 50%;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        padding: 0;
        border: 1px solid #e9ecef;
        background: #fff;
        box-shadow: 0 1px 6px rgba(0,0,0,0.05);
        z-index: 10;
    }

    /* Mobile: horizontal swipe/scroll and snap */
    @media (max-width: 767px) {
        /* Collapse to a single column on small screens */
        .roles-container {
            border-width: 6px;
            padding: 6px;
            max-width: 420px;
        }
        .roles-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }
        .role-card { min-height: 110px; }

        .role-card h5 {
            font-size: 16px;
            white-space: normal;
            word-break: break-word;
        }

        .role-card p {
            font-size: 12px;
            white-space: normal;
        }

        .arrow-btn {
            display: flex;
        }
    }

    /* Slightly smaller cards for very small screens */
    @media (max-width: 420px) {
        .role-card {
            min-width: 170px;
            max-width: 220px;
            padding: 20px;
        }
        .role-icon { font-size: 34px; }
    }
</style>
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="theme_ocean">
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>BioTern || Register Minimal</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <script src="assets/js/skin-init.js"></script>
    <!--! END: Custom CSS-->
    <style>
        /* Ensure select text and option text are visible (black) */
        select.form-control {
            color: #000 !important;
            background-color: #fff !important;
        }
        select.form-control option {
            color: #000 !important;
        }

        .select2-container--default .select2-selection--single {
            height: calc(2.25rem + 2px);
            border: 1px solid #d9d9d9;
            border-radius: 6px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: calc(2.25rem + 0px);
            padding-left: 12px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: calc(2.25rem + 0px);
            right: 8px;
        }
    </style>
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
</head>

<body>
    <style>
        /* Center main card vertically and horizontally */
        .auth-minimal-wrapper {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 100vh !important;
            padding: 20px !important;
            background-color: transparent;
        }
        /* Ensure the card doesn't overflow on small screens */
        .card.p-sm-5 {
            box-sizing: border-box;
        }
    </style>
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="auth-minimal-wrapper">
        <div class="auth-minimal-inner">
            <div class="minimal-card-wrapper">
                <div class="card mb-4 mt-5 mx-2 mx-sm-0 position-relative" style="width: 130%; max-width: 1500px; margin: 40px auto;">
                    <div class="wd-50 bg-white p-2 rounded-circle shadow-lg position-absolute translate-middle top-0 start-50">
                        <img src="assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <div class="card-body p-sm-5" style="padding: 50px !important; min-height: auto;">
                        <h2 class="fs-20 fw-bolder mb-4">Register</h2>
                        <div class="mb-3">
                            <a href="index.php" class="btn btn-sm btn-outline-primary">← Back to Admin Dashboard</a>
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
                            } elseif (in_array($reg, ['student','coordinator','supervisor','admin'])) {
                                echo '<div class="alert alert-success" role="alert">Registration successful. You may now login.</div>';
                            }
                        }
                        ?>
                        
                        <!-- ROLE SELECTION SCREEN -->
                        <div id="roleSelectionScreen" class="show-form">
                            <div class="mt-5">
                                <h5 class="fs-14 fw-bold mb-4">Select role:</h5>
                                <div class="roles-wrapper">
                                    <!-- Outer box that visually contains the 2x2 grid -->
                                    <div class="roles-container">
                                        <div class="roles-grid" id="rolesRow">
                                            <div class="role-card" data-role="student" onclick="selectRole('student')" tabindex="0">
                                                <div class="role-icon">👨‍🎓</div>
                                                <h5>Student</h5>
                                                <p>Student: Register for internship</p>
                                            </div>
                                            <div class="role-card" data-role="coordinator" onclick="selectRole('coordinator')" tabindex="0">
                                                <div class="role-icon">👔</div>
                                                <h5>Coordinator</h5>
                                                <p>Coordinator: Manage student placements</p>
                                            </div>
                                            <div class="role-card" data-role="supervisor" onclick="selectRole('supervisor')" tabindex="0">
                                                <div class="role-icon">👨‍💼</div>
                                                <h5>Supervisor</h5>
                                                <p>Supervisor: Oversee workplace activities</p>
                                            </div>
                                            <div class="role-card" data-role="admin" onclick="selectRole('admin')" tabindex="0">
                                                <div class="role-icon">⚙️</div>
                                                <h5>Admin</h5>
                                                <p>Admin: System administrator</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STUDENT REGISTRATION FORM -->
                        <form id="studentForm" class="w-100 mt-4 pt-2 hide-form" action="auth-register-creative.php" method="post">
                            <input type="hidden" name="role" value="student">
                            <div class="form-section">
                                <h3 class="fs-18 fw-bold mb-3">Student Registration</h3>
                                <button type="button" class="btn btn-sm btn-outline-secondary mb-4" onclick="backToRoles()">← Back to Role Selection</button>
                            </div>
                            <!-- Personal Information Section -->
                            <div class="mb-4">
                                <h5 class="fs-14 fw-bold mb-3">Personal Information</h5>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <input type="text" name="student_id" class="form-control" placeholder="School ID Number" required>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <input type="text" name="first_name" style="padding: 12px 16px;" class="form-control" placeholder="First name" required>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <input type="text" name="middle_name" class="form-control" placeholder="Middle name">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <input type="text" name="last_name" class="form-control" placeholder="Last name" required>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <input type="text" name="address" class="form-control" placeholder="Full Home Address" required>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Academic Information Section -->
                            <div class="mb-4">
                                <h5 class="fs-14 fw-bold mb-3">Academic Information</h5>
                                <div class="row g-3">
                                    <div class="col-4 mb-2">
                                        <label class="form-label fs-12">Course</label>
                                        <select name="course_id" id="studentCourseSelect" class="form-control dynamic-course-select" data-section-target="studentSectionSelect" required>
                                            <option value="" disabled selected>Select Course</option>
                                            <?php foreach ($courseOptions as $course): ?>
                                                <option
                                                    value="<?php echo htmlspecialchars((string) $course['id']); ?>"
                                                    data-course-code="<?php echo htmlspecialchars((string) $course['code']); ?>"
                                                >
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $course['name'] !== '' && $course['code'] !== ''
                                                            ? ($course['code'] . ' - ' . $course['name'])
                                                            : ($course['name'] !== '' ? $course['name'] : $course['code'])
                                                    );
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-4 mb-2">
                                        <label class="form-label fs-12">Department</label>
                                        <select name="department_id" id="studentDepartmentSelect" class="form-control" required>
                                            <option value="" selected>Select Department</option>
                                            <?php foreach ($departmentOptions as $department): ?>
                                                <option
                                                    value="<?php echo (int)$department['id']; ?>"
                                                    data-default-label="<?php echo htmlspecialchars($department['name'] !== '' ? ($department['name'] . ' (' . $department['code'] . ')') : $department['code']); ?>"
                                                >
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $department['name'] !== ''
                                                            ? ($department['name'] . ' (' . $department['code'] . ')')
                                                            : $department['code']
                                                    );
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-4 mb-2">
                                        <label class="form-label fs-12">Section</label>
                                        <select name="section" id="studentSectionSelect" class="form-control" required>
                                            <option value="" disabled selected>Select Section</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12">Coordinator</label>
                                        <select name="coordinator_id" id="studentCoordinatorSelect" class="form-control" required>
                                            <option value="" disabled selected>Select Coordinator</option>
                                            <?php foreach ($coordinatorOptions as $coordinator): ?>
                                                <?php
                                                $fullName = (string)$coordinator['full_name'];
                                                $office = (string)$coordinator['office_location'];
                                                $defaultLabel = $fullName;
                                                $actLabel = $fullName . ' - Coordinator | Office: ' . $office;
                                                ?>
                                                <option
                                                    value="<?php echo (int)$coordinator['id']; ?>"
                                                    data-department-id="<?php echo (int)$coordinator['department_id']; ?>"
                                                    data-default-label="<?php echo htmlspecialchars($defaultLabel); ?>"
                                                    data-act-label="<?php echo htmlspecialchars($actLabel); ?>"
                                                >
                                                    <?php echo htmlspecialchars($defaultLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12">Supervisor</label>
                                        <select name="supervisor_id" id="studentSupervisorSelect" class="form-control" required>
                                            <option value="" disabled selected>Select Supervisor</option>
                                            <?php foreach ($supervisorOptions as $supervisor): ?>
                                                <?php
                                                $fullName = (string)$supervisor['full_name'];
                                                $office = (string)$supervisor['office_location'];
                                                $defaultLabel = $fullName;
                                                $actLabel = $fullName . ' - Supervisor | Office: ' . $office;
                                                ?>
                                                <option
                                                    value="<?php echo (int)$supervisor['id']; ?>"
                                                    data-department-id="<?php echo (int)$supervisor['department_id']; ?>"
                                                    data-default-label="<?php echo htmlspecialchars($defaultLabel); ?>"
                                                    data-act-label="<?php echo htmlspecialchars($actLabel); ?>"
                                                >
                                                    <?php echo htmlspecialchars($defaultLabel); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12">Internal Total Hours</label>
                                        <input type="number" name="internal_total_hours" class="form-control" placeholder="Internal Total Hours" min="0">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12">Finished Internal?</label>
                                        <select name="finished_internal" id="finishedInternalSelect" class="form-control">
                                            <option value="no" selected>No</option>
                                            <option value="yes">Yes</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12">External Total Hours</label>
                                        <input type="number" name="external_total_hours" id="externalTotalHoursInput" class="form-control" placeholder="External Total Hours" min="0" value="0">
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Information Section -->
                            <div class="mb-4">
                                <h5 class="fs-14 fw-bold mb-3">Additional Information</h5>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12">Date of Birth</label>
                                        <input type="date" name="date_of_birth" class="form-control">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12">Gender</label>
                                        <select name="gender" class="form-control">
                                            <option value="" disabled selected>Select Gender</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12">Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" placeholder="Phone Number">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12">Emergency Contact Name</label>
                                        <input type="text" name="emergency_contact" class="form-control" placeholder="Emergency Contact Name">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12">Emergency Contact Phone</label>
                                        <input type="tel" name="emergency_contact_phone" class="form-control" placeholder="Emergency Contact Phone Number">
                                    </div>
                                </div>
                            </div>

                            <!-- Account Information Section -->
                            <div class="mb-4">
                                <h5 class="fs-14 fw-bold mb-3">Account Information</h5>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <input type="text" name="username" class="form-control" placeholder="Username" required>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <input type="email" name="account_email" class="form-control" placeholder="Account Email Address" required>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-6 mb-2">
                                        <div class="input-group field">
                                            <input type="password" name="password" class="form-control password" id="studentPassword" placeholder="Password">
                                            <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="studentPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                        </div>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <div class="input-group field">
                                            <input type="password" name="confirm_password" class="form-control" id="studentConfirmPassword" placeholder="Confirm password" required>
                                            <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="studentConfirmPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" id="receiveMial" required>
                                    <label class="custom-control-label c-pointer text-muted" for="receiveMial" style="font-weight: 400 !important">Yes, I want to receive BioTern community emails</label>
                                </div>
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="termsCondition" required>
                                    <label class="custom-control-label c-pointer text-muted" for="termsCondition" style="font-weight: 400 !important">I agree to all the <a href="">Terms &amp; Conditions.</a></label>
                                </div>
                            </div>
                            <div class="mt-5 d-flex gap-2">
                                <button type="submit" class="btn btn-lg btn-primary flex-grow-1">Create Account</button>
                            </div>
                        </form>

                        <!-- COORDINATOR REGISTRATION FORM -->
                        <form id="coordinatorForm" class="w-100 mt-4 pt-2 hide-form" action="auth-register-creative.php" method="post">
                            <input type="hidden" name="role" value="coordinator">
                            <div class="form-section">
                                <h3 class="fs-18 fw-bold mb-3">Coordinator Registration</h3>
                                <button type="button" class="btn btn-sm btn-outline-secondary mb-4" onclick="backToRoles()">← Back to Role Selection</button>
                            </div>
                            <h5 class="fs-14 fw-bold mb-3">Personal Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="first_name" class="form-control" placeholder="First name" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="last_name" class="form-control" placeholder="Last name" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="email" name="email" class="form-control" placeholder="Institution Email Address" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="phone" class="form-control" placeholder="Phone Number" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 mb-2">
                                    <input type="text" name="office_location" class="form-control" placeholder="Full Office Address" required>
                                </div>
                            </div>

                            <h5 class="fs-14 fw-bold mb-3 mt-4">Department Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <label class="form-label fs-12">Department Code</label>
                                    <select name="department_code" class="form-control" required>
                                        <option value="" disabled selected>Select Department</option>
                                        <?php foreach ($departmentOptions as $department): ?>
                                            <option value="<?php echo htmlspecialchars($department['code']); ?>">
                                                <?php
                                                echo htmlspecialchars(
                                                    $department['name'] !== ''
                                                        ? ($department['name'] . ' (' . $department['code'] . ')')
                                                        : $department['code']
                                                );
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label fs-12">Position</label>
                                    <input type="text" name="position" class="form-control" placeholder="e.g., Internship Coordinator" required>
                                </div>
                            </div>

                            <h5 class="fs-14 fw-bold mb-3 mt-4">Account Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="email" name="account_email" class="form-control" placeholder="Account Email Address" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="password" class="form-control password" id="coordPassword" placeholder="Password">
                                        <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="coordPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                    </div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="confirm_password" class="form-control" id="coordConfirmPassword" placeholder="Confirm password" required>
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
                            <div class="mt-5 d-flex gap-2">
                                <button type="submit" class="btn btn-lg btn-primary flex-grow-1">Create Account</button>
                            </div>
                        </form>

                        <!-- SUPERVISOR REGISTRATION FORM -->
                        <form id="supervisorForm" class="w-100 mt-4 pt-2 hide-form" action="auth-register-creative.php" method="post">
                            <input type="hidden" name="role" value="supervisor">
                            <div class="form-section">
                                <h3 class="fs-18 fw-bold mb-3">Supervisor Registration</h3>
                                <button type="button" class="btn btn-sm btn-outline-secondary mb-4" onclick="backToRoles()">← Back to Role Selection</button>
                            </div>
                            <h5 class="fs-14 fw-bold mb-3">Personal Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="first_name" class="form-control" placeholder="First name" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="last_name" class="form-control" placeholder="Last name" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="tel" name="phone" class="form-control" placeholder="Phone Number" required>
                                </div>
                            </div>

                            <h5 class="fs-14 fw-bold mb-3 mt-4">Company Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="company_name" class="form-control" value="Clark College of Science and Technology" readonly>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="job_position" class="form-control" placeholder="Job Position" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <select name="department_code" class="form-control" required>
                                        <option value="" disabled selected>Select Department</option>
                                        <?php foreach ($departmentOptions as $department): ?>
                                            <option value="<?php echo htmlspecialchars($department['code']); ?>">
                                                <?php
                                                echo htmlspecialchars(
                                                    $department['name'] !== ''
                                                        ? ($department['name'] . ' (' . $department['code'] . ')')
                                                        : $department['code']
                                                );
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="specialization" class="form-control" placeholder="Area of Expertise" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 mb-2">
                                    <input type="text" name="company_address" class="form-control" value="AUREA ST. SAMSONVILLE, DAU MABALACAT CITY PAMPANGA" readonly>
                                </div>
                            </div>

                            <h5 class="fs-14 fw-bold mb-3 mt-4">Account Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="email" name="account_email" class="form-control" placeholder="Account Email Address" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="password" class="form-control password" id="supPassword" placeholder="Password">
                                        <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="supPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                    </div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="confirm_password" class="form-control" id="supConfirmPassword" placeholder="Confirm password" required>
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
                            <div class="mt-5 d-flex gap-2">
                                <button type="submit" class="btn btn-lg btn-primary flex-grow-1">Create Account</button>
                            </div>
                        </form>

                        <!-- ADMIN REGISTRATION FORM -->
                        <form id="adminForm" class="w-100 mt-4 pt-2 hide-form" action="auth-register-creative.php" method="post">
                            <input type="hidden" name="role" value="admin">
                            <div class="form-section">
                                <h3 class="fs-18 fw-bold mb-3">Admin Registration</h3>
                                <button type="button" class="btn btn-sm btn-outline-secondary mb-4" onclick="backToRoles()">← Back to Role Selection</button>
                            </div>
                            <h5 class="fs-14 fw-bold mb-3">Personal Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="first_name" class="form-control" placeholder="First name" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="text" name="last_name" class="form-control" placeholder="Last name" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="email" name="email" class="form-control" placeholder="Institution Email Address" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="tel" name="phone" class="form-control" placeholder="Phone Number" required>
                                </div>
                            </div>

                            <h5 class="fs-14 fw-bold mb-3 mt-4">Administrative Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <label class="form-label fs-12">Admin Level</label>
                                    <select class="form-control" name="admin_level" required>
                                        <option value="" disabled selected>Select Admin Level</option>
                                        <option value="head_admin">Head Admin</option>
                                        <option value="admin">Admin</option>
                                        <option value="staff">Staff</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label fs-12">Department Code</label>
                                    <select name="department_code" class="form-control" required>
                                        <option value="" disabled selected>Select Department</option>
                                        <?php foreach ($departmentOptions as $department): ?>
                                            <option value="<?php echo htmlspecialchars($department['code']); ?>">
                                                <?php
                                                echo htmlspecialchars(
                                                    $department['name'] !== ''
                                                        ? ($department['name'] . ' (' . $department['code'] . ')')
                                                        : $department['code']
                                                );
                                                ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Course & Section removed for admin registration (hidden fields preserved) -->
                            <input type="hidden" name="admin_course_id" value="">
                            <input type="hidden" name="admin_section" value="">
                            <div class="row g-3">
                                <div class="col-12 mb-2">
                                    <input type="text" name="admin_position" class="form-control" placeholder="Official Title/Position" required>
                                </div>
                            </div>

                            <h5 class="fs-14 fw-bold mb-3 mt-4">Account Information</h5>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                                </div>
                                <div class="col-6 mb-2">
                                    <input type="email" name="account_email" class="form-control" placeholder="Account Email Address" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="password" class="form-control password" id="adminPassword" placeholder="Password">
                                        <div class="input-group-text border-start bg-gray-2 c-pointer show-pass-toggle" data-target="adminPassword" data-bs-toggle="tooltip" title="Show/Hide Password"><i></i></div>
                                    </div>
                                </div>
                                <div class="col-6 mb-2">
                                    <div class="input-group field">
                                        <input type="password" name="confirm_password" class="form-control" id="adminConfirmPassword" placeholder="Confirm password" required>
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
                            <div class="mt-5 d-flex gap-2">
                                <button type="submit" class="btn btn-lg btn-primary flex-grow-1">Create Account</button>
                            </div>
                        </form>

                        <div id="loginLink" class="mt-5 text-muted show-form">
                            <span>Already have an account?</span>
                            <a href="auth-login-cover.php" class="fw-bold">Login</a>
                        </div>
                        <div id="loginLinkHidden" class="mt-5 text-muted hide-form">
                            <span>Already have an account?</span>
                            <a href="auth-login-cover.php" class="fw-bold">Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="assets/vendors/js/lslstrength.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <!--! END: Apps Init !-->
    <script src="assets/js/theme-customizer-init.min.js"></script>

    <script>
        let currentRole = null;

        function selectRole(role) {
            currentRole = role;
            const roleSelection = document.getElementById('roleSelectionScreen');
            const loginLink = document.getElementById('loginLink');
            const loginLinkHidden = document.getElementById('loginLinkHidden');

            const forms = {
                'student': 'studentForm',
                'coordinator': 'coordinatorForm',
                'supervisor': 'supervisorForm',
                'admin': 'adminForm'
            };

            // Hide role selection and login hint (guarded)
            if (roleSelection) {
                roleSelection.classList.add('hide-form');
                roleSelection.classList.remove('show-form');
            }
            if (loginLink) {
                loginLink.classList.add('hide-form');
                loginLink.classList.remove('show-form');
            }

            // Hide all forms then show the selected one
            Object.values(forms).forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.classList.add('hide-form');
                    form.classList.remove('show-form');
                }
            });

            const selectedForm = document.getElementById(forms[role]);
            if (selectedForm) {
                selectedForm.classList.add('show-form');
                selectedForm.classList.remove('hide-form');
                selectedForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            if (loginLinkHidden) {
                loginLinkHidden.classList.remove('hide-form');
                loginLinkHidden.classList.add('show-form');
            }
        }

        function backToRoles() {
            const roleSelection = document.getElementById('roleSelectionScreen');
            const loginLink = document.getElementById('loginLink');
            const loginLinkHidden = document.getElementById('loginLinkHidden');
            const forms = {
                'student': 'studentForm',
                'coordinator': 'coordinatorForm',
                'supervisor': 'supervisorForm',
                'admin': 'adminForm'
            };

            // Hide forms
            Object.values(forms).forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.classList.add('hide-form');
                    form.classList.remove('show-form');
                }
            });

            // Show role selection and login hint (guarded)
            if (roleSelection) {
                roleSelection.classList.remove('hide-form');
                roleSelection.classList.add('show-form');
                roleSelection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            if (loginLink) {
                loginLink.classList.remove('hide-form');
                loginLink.classList.add('show-form');
            }
            if (loginLinkHidden) {
                loginLinkHidden.classList.add('hide-form');
                loginLinkHidden.classList.remove('show-form');
            }

            currentRole = null;
        }

        // Validate password matches confirm password for all forms
        function validatePasswordMatch(e) {
            const form = e.target;
            const password = form.querySelector('input[name="password"]');
            const confirmPassword = form.querySelector('input[name="confirm_password"]');
            
            if (password && confirmPassword) {
                if (password.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check and try again.');
                    return false;
                }
            }
            return true;
        }

        // Attach validation to all forms when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const formIds = ['studentForm', 'coordinatorForm', 'supervisorForm', 'adminForm'];
            formIds.forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.addEventListener('submit', validatePasswordMatch);
                }
            });
            
            // Setup password visibility toggle
            setupPasswordToggle();
            setupStudentHoursControls();
            setupAcademicFilters();

        });

        function setupStudentHoursControls() {
            const finishedSelect = document.getElementById('finishedInternalSelect');
            const externalInput = document.getElementById('externalTotalHoursInput');
            const internalInput = document.querySelector('#studentForm input[name="internal_total_hours"]');
            if (!finishedSelect || !externalInput || !internalInput) return;

            function syncExternalField() {
                const finished = (finishedSelect.value || '').toLowerCase() === 'yes';
                externalInput.disabled = !finished;
                internalInput.disabled = finished;
                if (finished) {
                    internalInput.value = '0';
                }
                if (!finished) {
                    externalInput.value = '0';
                }
            }

            finishedSelect.addEventListener('change', syncExternalField);
            syncExternalField();
        }

        const courseDepartmentMap = <?php echo json_encode($courseDepartmentMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const sectionRecords = <?php echo json_encode($sectionOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        function getCourseAllowedDepartmentIds(courseId) {
            const bucket = courseDepartmentMap[String(courseId)] || {};
            const ids = Object.keys(bucket).map(function(id) { return String(id); });
            // Fallback: if no course->department mapping exists yet, keep all departments visible.
            if (!ids.length) {
                const deptSelect = document.getElementById('studentDepartmentSelect');
                if (!deptSelect) return [];
                return Array.prototype.slice.call(deptSelect.options)
                    .filter(function(opt, idx) { return idx > 0 && String(opt.value || '').trim() !== ''; })
                    .map(function(opt) { return String(opt.value); });
            }
            return ids;
        }

        function setSelectPlaceholder(selectEl, text) {
            if (!selectEl) return;
            selectEl.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.disabled = true;
            placeholder.selected = true;
            placeholder.textContent = text;
            selectEl.appendChild(placeholder);
        }

        function filterDepartmentOptions(courseId) {
            const deptSelect = document.getElementById('studentDepartmentSelect');
            if (!deptSelect) return [];
            const allowed = getCourseAllowedDepartmentIds(courseId);

            Array.prototype.slice.call(deptSelect.options).forEach(function(opt, index) {
                if (index === 0) return; // placeholder
                const show = allowed.includes(String(opt.value));
                opt.hidden = !show;
                opt.disabled = !show;
            });

            if (allowed.length === 1) {
                deptSelect.value = allowed[0];
            } else {
                deptSelect.value = '';
            }

            return allowed;
        }

        function filterSectionOptions(courseId, departmentId) {
            const sectionSelect = document.getElementById('studentSectionSelect');
            const courseSelect = document.getElementById('studentCourseSelect');
            if (!sectionSelect) return;
            setSelectPlaceholder(sectionSelect, 'Select Section');

            const cId = String(courseId || '');
            const dId = String(departmentId || '');
            let inserted = 0;

            sectionRecords.forEach(function(rec) {
                if (String(rec.course_id) !== cId) return;
                if (dId !== '' && String(rec.department_id) !== dId) return;

                const code = (rec.code || '').trim();
                const name = (rec.name || '').trim();
                const label = code && name ? (code + ' - ' + name) : (code || name || ('Section #' + rec.id));

                const option = document.createElement('option');
                option.value = code || String(rec.id);
                option.textContent = label;
                sectionSelect.appendChild(option);
                inserted++;
            });

            // Fallback for databases without sections rows yet.
            if (inserted === 0 && courseSelect) {
                const selectedOption = courseSelect.options[courseSelect.selectedIndex];
                const courseCode = selectedOption ? (selectedOption.getAttribute('data-course-code') || '').trim() : '';
                if (courseCode) {
                    ['A', 'B', 'C', 'D', 'E', 'F'].forEach(function(suffix) {
                        const code = courseCode + '-2' + suffix;
                        const option = document.createElement('option');
                        option.value = code;
                        option.textContent = code;
                        sectionSelect.appendChild(option);
                    });
                }
            }
        }

        function filterRoleOptionsByDept(selectId, allowedDepartmentIds, selectedDepartmentId, isAct) {
            const select = document.getElementById(selectId);
            if (!select) return;

            const selectedDept = String(selectedDepartmentId || '');
            const allowedSet = new Set((allowedDepartmentIds || []).map(function(v) { return String(v); }));

            Array.prototype.slice.call(select.options).forEach(function(opt, index) {
                if (index === 0) return; // placeholder

                const deptId = String(opt.getAttribute('data-department-id') || '');
                let show = true;
                if (selectedDept !== '') {
                    show = deptId === selectedDept;
                } else if (allowedSet.size > 0) {
                    show = allowedSet.has(deptId);
                }

                opt.hidden = !show;
                opt.disabled = !show;
                const defaultLabel = opt.getAttribute('data-default-label') || opt.textContent;
                const actLabel = opt.getAttribute('data-act-label') || defaultLabel;
                opt.textContent = isAct ? actLabel : defaultLabel;
            });

            select.value = '';
        }

        function setupAcademicFilters() {
            const courseSelect = document.getElementById('studentCourseSelect');
            const deptSelect = document.getElementById('studentDepartmentSelect');
            if (!courseSelect || !deptSelect) return;

            function applyFilters() {
                const selectedCourse = courseSelect.options[courseSelect.selectedIndex] || null;
                const courseId = selectedCourse ? selectedCourse.value : '';
                const courseCode = selectedCourse ? ((selectedCourse.getAttribute('data-course-code') || '').trim().toUpperCase()) : '';
                const isAct = courseCode === 'ACT';

                const allowedDeptIds = filterDepartmentOptions(courseId);
                const selectedDeptId = deptSelect.value || '';

                filterSectionOptions(courseId, selectedDeptId);
                filterRoleOptionsByDept('studentCoordinatorSelect', allowedDeptIds, selectedDeptId, isAct);
                filterRoleOptionsByDept('studentSupervisorSelect', allowedDeptIds, selectedDeptId, isAct);
            }

            courseSelect.addEventListener('change', applyFilters);
            deptSelect.addEventListener('change', applyFilters);
            applyFilters();
        }

        // New function to handle password visibility toggle for both password and confirm password
        function setupPasswordToggle() {
            const toggles = document.querySelectorAll('.show-pass-toggle');
            // simple inline SVGs for eye and eye-off
            const eyeSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            const eyeOffSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">\
                <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.86 21.86 0 0 1 5.06-6.94"></path>\
                <path d="M22.54 16.88A21.6 21.6 0 0 0 23 12s-4-8-11-8a10.94 10.94 0 0 0-5.94 1.94"></path>\
                <line x1="1" y1="1" x2="23" y2="23"></line>\
            </svg>';

            toggles.forEach(toggle => {
                // initialize icon if empty
                const icon = toggle.querySelector('i');
                if (icon && !icon.innerHTML.trim()) {
                    icon.innerHTML = eyeSVG;
                    toggle.setAttribute('title', 'Show password');
                    toggle.setAttribute('aria-label', 'Show password');
                }

                toggle.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const targetField = document.getElementById(targetId);
                    if (targetField) {
                        const wasPassword = targetField.type === 'password';
                        targetField.type = wasPassword ? 'text' : 'password';
                        // swap icon
                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.innerHTML = wasPassword ? eyeOffSVG : eyeSVG;
                            this.setAttribute('title', wasPassword ? 'Hide password' : 'Show password');
                            this.setAttribute('aria-label', wasPassword ? 'Hide password' : 'Show password');
                        }
                    }
                });
            });
        }

        /* Role carousel: arrow buttons + touch drag support */
        (function initRoleCarousel(){
            const row = document.getElementById('rolesRow');
            const prev = document.getElementById('rolesPrev');
            const next = document.getElementById('rolesNext');
            if (!row) return;

            const scrollBy = () => Math.round(row.clientWidth * 0.8);

            if (prev) prev.addEventListener('click', () => {
                row.scrollBy({ left: -scrollBy(), behavior: 'smooth' });
            });
            if (next) next.addEventListener('click', () => {
                row.scrollBy({ left: scrollBy(), behavior: 'smooth' });
            });

            // Click delegation: open role on card click (works even if pointer events are present)
            row.addEventListener('click', (e) => {
                const card = e.target.closest('.role-card');
                if (!card) return;
                const role = card.getAttribute('data-role');
                if (role) selectRole(role);
            });

            // Improve accessibility: keyboard arrows when focused (if scrollable)
            row.setAttribute('tabindex','0');
            row.addEventListener('keydown', (e)=>{
                if (e.key === 'ArrowRight') row.scrollBy({ left: scrollBy(), behavior: 'smooth' });
                if (e.key === 'ArrowLeft') row.scrollBy({ left: -scrollBy(), behavior: 'smooth' });
                if (e.key === 'Enter' || e.key === ' ') {
                    // if focused on a child card, trigger selection
                    const active = document.activeElement;
                    const card = active && active.classList && active.classList.contains('role-card') ? active : null;
                    if (card) {
                        const role = card.getAttribute('data-role');
                        if (role) selectRole(role);
                    }
                }
            });
        })();
    </script>
</body>

</html>




