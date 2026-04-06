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
$dbHost = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';
$dbName = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$dbPort = defined('DB_PORT') ? (int)DB_PORT : 3306;

$departmentsConn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
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

$coursesConn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
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

$relationsConn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);
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

    .step-panel {
        display: none;
    }

    .step-panel.active {
        display: block;
    }

    .form-stepper {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin: 8px 0 18px;
    }

    .stepper-track {
        display: flex;
        gap: 8px;
    }

    .step-dot {
        flex: 1 1 0;
        height: 6px;
        border-radius: 999px;
        background: rgba(148, 163, 184, 0.2);
        position: relative;
        overflow: hidden;
    }

    .step-dot::after {
        content: "";
        position: absolute;
        inset: 0;
        background: rgba(59, 130, 246, 0.4);
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 200ms ease;
    }

    .step-dot.active::after,
    .step-dot.done::after {
        transform: scaleX(1);
        background: rgba(59, 130, 246, 0.8);
    }

    .stepper-meta {
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 12px;
        color: #cbd5f5;
    }

    .step-actions {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-top: 18px;
    }

    .step-actions .btn {
        min-width: 140px;
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
        grid-template-columns: 1fr;
        gap: 12px;
        width: 100%;
        justify-items: center;
    }

    .role-card {
        max-width: 260px;
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
    <title>BioTern || Apply</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/favicon.ico?v=20260310">
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
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/theme.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/css/datepicker-global.css">
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/skin-init.js"></script>
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

        /* Dark mode form field readability */
        html.app-skin-dark input.form-control,
        html.app-skin-dark textarea.form-control,
        html.app-skin-dark .form-control[type="text"],
        html.app-skin-dark .form-control[type="email"],
        html.app-skin-dark .form-control[type="password"],
        html.app-skin-dark .form-control[type="number"],
        html.app-skin-dark .form-control[type="date"],
        html.app-skin-dark .form-control[type="time"],
        html.app-skin-dark .form-control[type="search"],
        html.app-skin-dark .form-control[type="tel"],
        html.app-skin-dark .form-control[type="url"] {
            color: #ffffff !important;
            -webkit-text-fill-color: #ffffff !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }

        html.app-skin-dark input.form-control::placeholder,
        html.app-skin-dark textarea.form-control::placeholder {
            color: #d1dcf0 !important;
            opacity: 1 !important;
        }

        html.app-skin-dark select.form-control,
        html.app-skin-dark select.form-control option {
            color: #ffffff !important;
            background-color: #0f172a !important;
            border-color: #4a5568 !important;
        }

        /* Keep browser suggestion/autofill values readable in dark mode */
        html.app-skin-dark input.form-control:-webkit-autofill,
        html.app-skin-dark input.form-control:-webkit-autofill:hover,
        html.app-skin-dark input.form-control:-webkit-autofill:focus,
        html.app-skin-dark textarea.form-control:-webkit-autofill,
        html.app-skin-dark textarea.form-control:-webkit-autofill:hover,
        html.app-skin-dark textarea.form-control:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff !important;
            caret-color: #ffffff !important;
            box-shadow: 0 0 0 1000px #0f172a inset !important;
            -webkit-box-shadow: 0 0 0 1000px #0f172a inset !important;
            border-color: #4a5568 !important;
            transition: background-color 9999s ease-out 0s;
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
                        <img src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/images/logo-abbr.png" alt="" class="img-fluid">
                    </div>
                    <div class="card-body p-sm-5" style="padding: 50px !important; min-height: auto;">
                        <h2 class="fs-20 fw-bolder mb-4">Apply</h2>
                        <div class="mb-3">
                            <a href="<?php echo htmlspecialchars($route_prefix, ENT_QUOTES, 'UTF-8'); ?>index.php" class="btn btn-sm btn-outline-primary">&#8592; Back to Home</a>
                        </div>
                        <h4 class="fs-13 fw-bold mb-2">Manage your Internship account in one place.</h4>
                        <p class="fs-12 fw-medium text-muted">Let's get you all setup, so you can verify your personal account and begin setting up your profile.</p>
                        <?php
require_once dirname(__DIR__) . '/config/db.php';
if (isset($_GET['registered'])) {
                            $reg = $_GET['registered'];
                            $msg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
                            if ($reg === 'exists') {
                                echo '<div class="alert alert-warning" role="alert">' . ($msg ?: 'An account with that email already exists.') . '</div>';
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
                                        <input type="text" name="student_id" class="form-control" placeholder="School ID Number" autocomplete="off" required pattern="^[A-Za-z0-9][A-Za-z0-9-]{3,19}$" maxlength="20" title="Use letters, numbers, or hyphens only">
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
                                    <div class="col-12 mb-2">
                                        <input type="text" name="address" class="form-control" placeholder="Full Home Address" autocomplete="street-address" required>
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
require_once dirname(__DIR__) . '/config/db.php';
foreach ($courseOptions as $course): ?>
                                                <option
                                                    value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string) $course['id']); ?>"
                                                    data-course-code="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string) $course['code']); ?>"
                                                >
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars(
                                                        $course['name'] !== '' && $course['code'] !== ''
                                                            ? ($course['code'] . ' - ' . $course['name'])
                                                            : ($course['name'] !== '' ? $course['name'] : $course['code'])
                                                    );
                                                    ?>
                                                </option>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-4 mb-2">
                                        <label class="form-label fs-12" for="studentDepartmentSelect">Department</label>
                                        <select name="department_id" id="studentDepartmentSelect" class="form-control" required>
                                            <option value="" selected>Select Department</option>
                                            <option value="0">I still don't know yet (To be assigned)</option>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($departmentOptions as $department): ?>
                                                <option
                                                    value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$department['id']; ?>"
                                                    data-default-label="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($department['name'] !== '' ? ($department['name'] . ' (' . $department['code'] . ')') : $department['code']); ?>"
                                                >
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars(
                                                        $department['name'] !== ''
                                                            ? ($department['name'] . ' (' . $department['code'] . ')')
                                                            : $department['code']
                                                    );
                                                    ?>
                                                </option>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
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
require_once dirname(__DIR__) . '/config/db.php';
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
                                    <div class="col-4 mb-2">
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
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="studentCoordinatorSelect">Coordinator</label>
                                        <select name="coordinator_id" id="studentCoordinatorSelect" class="form-control" required>
                                            <option value="" disabled selected>Select Coordinator</option>
                                            <option value="0">I still don't know yet (To be assigned)</option>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($coordinatorOptions as $coordinator): ?>
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
$fullName = (string)$coordinator['full_name'];
                                                $office = (string)$coordinator['office_location'];
                                                $defaultLabel = $fullName;
                                                $actLabel = $fullName . ' - Coordinator | Office: ' . $office;
                                                ?>
                                                <option
                                                    value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$coordinator['id']; ?>"
                                                    data-department-id="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$coordinator['department_id']; ?>"
                                                    data-default-label="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($defaultLabel); ?>"
                                                    data-act-label="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($actLabel); ?>"
                                                >
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($defaultLabel); ?>
                                                </option>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label class="form-label fs-12" for="studentSupervisorSelect">Supervisor</label>
                                        <select name="supervisor_id" id="studentSupervisorSelect" class="form-control" required>
                                            <option value="" disabled selected>Select Supervisor</option>
                                            <option value="0">I still don't know yet (To be assigned)</option>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($supervisorOptions as $supervisor): ?>
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
$fullName = (string)$supervisor['full_name'];
                                                $office = (string)$supervisor['office_location'];
                                                $defaultLabel = $fullName;
                                                $actLabel = $fullName . ' - Supervisor | Office: ' . $office;
                                                ?>
                                                <option
                                                    value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$supervisor['id']; ?>"
                                                    data-department-id="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$supervisor['department_id']; ?>"
                                                    data-default-label="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($defaultLabel); ?>"
                                                    data-act-label="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($actLabel); ?>"
                                                >
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($defaultLabel); ?>
                                                </option>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
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
                                    <div class="col-12 mb-2">
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
require_once dirname(__DIR__) . '/config/db.php';
foreach ($departmentOptions as $department): ?>
                                            <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($department['code']); ?>">
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars(
                                                    $department['name'] !== ''
                                                        ? ($department['name'] . ' (' . $department['code'] . ')')
                                                        : $department['code']
                                                );
                                                ?>
                                            </option>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
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
                                <div class="col-12 mb-2">
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
require_once dirname(__DIR__) . '/config/db.php';
foreach ($departmentOptions as $department): ?>
                                            <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($department['code']); ?>">
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars(
                                                    $department['name'] !== ''
                                                        ? ($department['name'] . ' (' . $department['code'] . ')')
                                                        : $department['code']
                                                );
                                                ?>
                                            </option>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
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
                                <div class="col-12 mb-2">
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
require_once dirname(__DIR__) . '/config/db.php';
foreach ($departmentOptions as $department): ?>
                                            <option value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($department['code']); ?>">
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars(
                                                    $department['name'] !== ''
                                                        ? ($department['name'] . ' (' . $department['code'] . ')')
                                                        : $department['code']
                                                );
                                                ?>
                                            </option>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
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
                                <div class="col-12 mb-2">
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
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/global-datepicker-init.js"></script>
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/common-init.min.js"></script>
    <!--! END: Apps Init !-->
    <script src="<?php echo htmlspecialchars($asset_prefix, ENT_QUOTES, 'UTF-8'); ?>assets/js/theme-customizer-init.min.js"></script>

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
                initFormStepper(selectedForm.id);
                if (typeof selectedForm._showStep === 'function') {
                    selectedForm._showStep(1);
                }
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

        function initFormStepper(formId) {
            const form = document.getElementById(formId);
            if (!form || form.dataset.stepperInited === '1') return;

            const panels = Array.prototype.slice.call(form.querySelectorAll('.step-panel'));
            if (!panels.length) return;
            const stepper = form.querySelector('.form-stepper');
            const dots = stepper ? Array.prototype.slice.call(stepper.querySelectorAll('.step-dot')) : [];
            const total = panels.length;
            let current = 1;

            function showStep(step) {
                current = Math.min(Math.max(step, 1), total);
                panels.forEach(panel => {
                    const panelStep = Number(panel.dataset.step || 0);
                    panel.classList.toggle('active', panelStep === current);
                });
                dots.forEach(dot => {
                    const dotStep = Number(dot.dataset.step || 0);
                    dot.classList.toggle('active', dotStep === current);
                    dot.classList.toggle('done', dotStep < current);
                });
                if (stepper) {
                    const labelEl = stepper.querySelector('.stepper-label');
                    const countEl = stepper.querySelector('.stepper-count');
                    if (labelEl) {
                        const activeDot = stepper.querySelector('.step-dot[data-step="' + current + '"]');
                        labelEl.textContent = activeDot && activeDot.dataset.label ? activeDot.dataset.label : ('Step ' + current);
                    }
                    if (countEl) {
                        countEl.textContent = 'Step ' + current + ' of ' + total;
                    }
                }
            }

            form.querySelectorAll('[data-step-action]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const action = btn.getAttribute('data-step-action');
                    if (action === 'next') {
                        const activePanel = panels.find(panel => panel.classList.contains('active'));
                        if (activePanel) {
                            const requiredFields = Array.prototype.slice.call(activePanel.querySelectorAll('input, select, textarea'));

                            const markInvalid = (field, msg) => {
                                field.classList.add('is-invalid');
                                const group = field.closest('.input-group');
                                const anchor = group || field;
                                let feedback = anchor.parentElement ? anchor.parentElement.querySelector('.invalid-feedback') : null;
                                if (!feedback) {
                                    feedback = document.createElement('div');
                                    feedback.className = 'invalid-feedback d-block';
                                    if (anchor.parentElement) anchor.parentElement.appendChild(feedback);
                                }
                                feedback.textContent = msg;
                            };

                            const clearInvalid = (field) => {
                                field.classList.remove('is-invalid');
                                const group = field.closest('.input-group');
                                const anchor = group || field;
                                const feedback = anchor.parentElement ? anchor.parentElement.querySelector('.invalid-feedback') : null;
                                if (feedback) feedback.remove();
                            };

                            let hasInvalid = false;
                            for (let i = 0; i < requiredFields.length; i++) {
                                const field = requiredFields[i];
                                if (field.disabled || !field.required) continue;
                                if (!field.checkValidity()) {
                                    hasInvalid = true;
                                    let msg = 'Please check this field.';
                                    if (field.validity) {
                                        if (field.validity.valueMissing) {
                                            msg = field.tagName === 'SELECT' ? 'Please select an item in the list.' : 'This field is required.';
                                        } else if (field.validity.typeMismatch) {
                                            msg = 'Please enter a valid value.';
                                        } else if (field.validity.patternMismatch) {
                                            msg = field.getAttribute('title') || 'Invalid format.';
                                        } else if (field.validity.tooShort || field.validity.tooLong) {
                                            msg = field.validationMessage || 'Please check the required length.';
                                        } else {
                                            msg = field.validationMessage || msg;
                                        }
                                    }
                                    markInvalid(field, msg);
                                } else {
                                    clearInvalid(field);
                                }
                            }
                            if (hasInvalid) return;
                        }
                        showStep(current + 1);
                    } else if (action === 'prev') {
                        showStep(current - 1);
                    }
                });
            });

            form._showStep = showStep;
            form.dataset.stepperInited = '1';
            showStep(1);

            form.addEventListener('input', function (e) {
                const field = e.target;
                if (!field || !field.classList || !field.classList.contains('is-invalid')) return;
                field.classList.remove('is-invalid');
                const group = field.closest('.input-group');
                const anchor = group || field;
                const feedback = anchor.parentElement ? anchor.parentElement.querySelector('.invalid-feedback') : null;
                if (feedback) feedback.remove();
            });
            form.addEventListener('change', function (e) {
                const field = e.target;
                if (!field || !field.classList || !field.classList.contains('is-invalid')) return;
                field.classList.remove('is-invalid');
                const group = field.closest('.input-group');
                const anchor = group || field;
                const feedback = anchor.parentElement ? anchor.parentElement.querySelector('.invalid-feedback') : null;
                if (feedback) feedback.remove();
            });
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
            initFormStepper('studentForm');
            initFormStepper('coordinatorForm');
            initFormStepper('supervisorForm');
            initFormStepper('adminForm');

            const studentIdInput = document.querySelector('#studentForm input[name="student_id"]');
            if (studentIdInput) {
                const studentIdPattern = /^[A-Za-z0-9][A-Za-z0-9-]{3,19}$/;

                studentIdInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\s+/g, '');
                    if (this.value === '' || studentIdPattern.test(this.value)) {
                        this.setCustomValidity('');
                    } else {
                        this.setCustomValidity('Use 4-20 letters, numbers, or hyphens only');
                    }
                });

                studentIdInput.addEventListener('blur', function() {
                    this.value = this.value.trim();
                });
            }

            const requestedRole = new URLSearchParams(window.location.search).get('role');
            if (requestedRole && requestedRole.toLowerCase() === 'student') {
                selectRole('student');
            }

            const params = new URLSearchParams(window.location.search);
            if (params.get('registered')) {
                const studentForm = document.getElementById('studentForm');
                if (studentForm) {
                    studentForm.reset();
                    setupAcademicFilters();
                    if (typeof studentForm._showStep === 'function') {
                        studentForm._showStep(1);
                    }
                }
            }

        });

        function setupStudentHoursControls() {
            const finishedSelect = document.getElementById('finishedInternalSelect');
            const externalInput = document.getElementById('externalTotalHoursInput');
            const internalInput = document.querySelector('#studentForm input[name="internal_total_hours"]');
            if (!finishedSelect || !externalInput || !internalInput) return;

            function syncExternalField() {
                if ((internalInput.value || '').trim() === '') {
                    internalInput.value = '140';
                }
                if ((externalInput.value || '').trim() === '') {
                    externalInput.value = '250';
                }
                internalInput.disabled = false;
                externalInput.disabled = false;
            }

            finishedSelect.addEventListener('change', syncExternalField);
            syncExternalField();
        }

        const courseDepartmentMap = <?php
require_once dirname(__DIR__) . '/config/db.php';
echo json_encode($courseDepartmentMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const sectionRecords = <?php
require_once dirname(__DIR__) . '/config/db.php';
echo json_encode($sectionOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

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
            const selectedBefore = deptSelect.value;
            const allowedDepartmentIds = getCourseAllowedDepartmentIds(courseId);
            const allowedSet = new Set(allowedDepartmentIds.map(function(id) { return String(id); }));
            const allDepartmentIds = [];

            Array.prototype.slice.call(deptSelect.options).forEach(function(opt, index) {
                if (index === 0) return; // placeholder
                if (String(opt.value) === '0') {
                    opt.hidden = false;
                    opt.disabled = false;
                    return;
                }
                const deptId = String(opt.value);
                const show = allowedSet.size === 0 ? true : allowedSet.has(deptId);
                opt.hidden = !show;
                opt.disabled = !show;
                if (show) {
                    allDepartmentIds.push(deptId);
                }
            });

            if (selectedBefore !== '' && allDepartmentIds.indexOf(String(selectedBefore)) !== -1) {
                deptSelect.value = selectedBefore;
            } else if (allDepartmentIds.length === 1) {
                deptSelect.value = allDepartmentIds[0];
            } else {
                deptSelect.value = '';
            }

            return allDepartmentIds;
        }

        function filterSectionOptions(courseId, departmentId) {
            const sectionSelect = document.getElementById('studentSectionSelect');
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '';
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = 'Select Section';
            placeholderOption.selected = true;
            sectionSelect.appendChild(placeholderOption);

            const cId = String(courseId || '');
            const dId = String(departmentId || '');
            let inserted = 0;

            sectionRecords.forEach(function(rec) {
                const matchesCourse = (cId === '') || (String(rec.course_id) === cId);
                const matchesDept = (dId === '' || dId === '0') || (String(rec.department_id) === dId);
                if (!matchesCourse || !matchesDept) return;

                const code = (rec.code || '').trim();
                const name = (rec.name || '').trim();
                const formattedCode = code.replace(/\s*-\s*/g, ' - ');
                const formattedName = name.replace(/\s*-\s*/g, ' - ');
                const label = code && name
                    ? (code.toLowerCase() === name.toLowerCase()
                        ? formattedCode
                        : (formattedCode + ' - ' + formattedName))
                    : (formattedCode || formattedName || ('Section #' + rec.id));

                const option = document.createElement('option');
                option.value = code || String(rec.id);
                option.textContent = label;
                sectionSelect.appendChild(option);
                inserted++;
            });

            if (inserted === 0) {
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.disabled = true;
                emptyOption.textContent = 'No sections found in database';
                sectionSelect.appendChild(emptyOption);
            }
        }

        function filterRoleOptionsByDept(selectId, allowedDepartmentIds, selectedDepartmentId, isAct) {
            const select = document.getElementById(selectId);
            if (!select) return;

            const selectedDept = String(selectedDepartmentId || '');
            const allowedSet = new Set((allowedDepartmentIds || []).map(function(v) { return String(v); }));

            Array.prototype.slice.call(select.options).forEach(function(opt, index) {
                if (index === 0) return; // placeholder
                if (String(opt.value) === '0') {
                    opt.hidden = false;
                    opt.disabled = false;
                    return;
                }

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

            if (courseSelect.dataset.academicBound !== '1') {
                courseSelect.addEventListener('change', applyFilters);
                deptSelect.addEventListener('change', applyFilters);
                courseSelect.dataset.academicBound = '1';
            }
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









