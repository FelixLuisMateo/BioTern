<?php
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

$conn = null;
$view_user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$selected_student_id = $view_user_id;
$selected_user_id = 0;
$flash_message = '';
$flash_type = 'success';
$student = null;
$app_letter = [
    'date' => '',
    'application_person' => '',
    'position' => '',
    'company_name' => '',
    'company_address' => ''
];
$moa_data = [
    'company_name' => '',
    'company_address' => '',
    'company_receipt' => '',
    'doc_no' => '',
    'page_no' => '',
    'book_no' => '',
    'series_no' => '',
    'total_hours' => '',
    'moa_address' => '',
    'moa_date' => '',
    'coordinator' => '',
    'school_position' => '',
    'position' => '',
    'partner_representative' => '',
    'school_administrator' => '',
    'school_admin_position' => '',
    'notary_address' => '',
    'witness' => '',
    'acknowledgement_date' => '',
    'acknowledgement_address' => ''
];

function display_text($value, $fallback = '-')
{
    $text = trim((string)$value);
    return $text !== '' ? $text : $fallback;
}

function format_dt($value)
{
    if (!$value) return '-';
    $ts = strtotime((string)$value);
    if (!$ts) return '-';
    return date('M d, Y h:i A', $ts);
}

function status_badge_html($status)
{
    $raw = trim((string)$status);
    if ($raw === '1' || strcasecmp($raw, 'active') === 0 || strcasecmp($raw, 'ongoing') === 0) {
        return '<span class="badge bg-soft-success text-success">Active</span>';
    }
    if ($raw === '0' || strcasecmp($raw, 'inactive') === 0) {
        return '<span class="badge bg-soft-danger text-danger">Inactive</span>';
    }
    return '<span class="badge bg-soft-primary text-primary">' . htmlspecialchars($raw !== '' ? ucfirst($raw) : 'Unknown') . '</span>';
}

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }

    $conn->query("CREATE TABLE IF NOT EXISTS application_letter (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        date DATE DEFAULT NULL,
        application_person VARCHAR(255) DEFAULT NULL,
        position VARCHAR(255) DEFAULT NULL,
        company_name VARCHAR(255) DEFAULT NULL,
        company_address VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $conn->query("CREATE TABLE IF NOT EXISTS moa (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        company_name VARCHAR(255) NOT NULL,
        company_address VARCHAR(255) NOT NULL,
        company_receipt VARCHAR(255) NOT NULL,
        doc_no VARCHAR(100) NOT NULL,
        page_no VARCHAR(100) NOT NULL,
        book_no VARCHAR(100) NOT NULL,
        series_no VARCHAR(100) NOT NULL,
        total_hours VARCHAR(50) NOT NULL,
        moa_address VARCHAR(255) NOT NULL,
        moa_date DATE DEFAULT NULL,
        coordinator VARCHAR(255) NOT NULL,
        school_posistion VARCHAR(255) NOT NULL,
        position VARCHAR(255) NOT NULL,
        partner_representative VARCHAR(255) NOT NULL,
        school_administrator VARCHAR(255) NOT NULL,
        school_admin_position VARCHAR(255) NOT NULL,
        notary_address VARCHAR(255) NOT NULL,
        witness VARCHAR(255) NOT NULL,
        acknowledgement_date DATE DEFAULT NULL,
        acknowledgement_address VARCHAR(255) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    // Ensure acknowledgement_date is DATE even if previously created as VARCHAR.
    $ack_col = $conn->query("SHOW COLUMNS FROM moa LIKE 'acknowledgement_date'");
    if ($ack_col && $ack_col->num_rows > 0) {
        $ack_meta = $ack_col->fetch_assoc();
        if (isset($ack_meta['Type']) && stripos($ack_meta['Type'], 'date') === false) {
            $conn->query("ALTER TABLE moa MODIFY acknowledgement_date DATE DEFAULT NULL");
        }
    }
    $hours_col = $conn->query("SHOW COLUMNS FROM moa LIKE 'total_hours'");
    if (!$hours_col || $hours_col->num_rows === 0) {
        $conn->query("ALTER TABLE moa ADD COLUMN total_hours VARCHAR(50) NOT NULL AFTER company_receipt");
    }
    $doc_col = $conn->query("SHOW COLUMNS FROM moa LIKE 'doc_no'");
    if (!$doc_col || $doc_col->num_rows === 0) {
        $conn->query("ALTER TABLE moa ADD COLUMN doc_no VARCHAR(100) NOT NULL AFTER company_receipt");
    }
    $page_col = $conn->query("SHOW COLUMNS FROM moa LIKE 'page_no'");
    if (!$page_col || $page_col->num_rows === 0) {
        $conn->query("ALTER TABLE moa ADD COLUMN page_no VARCHAR(100) NOT NULL AFTER doc_no");
    }
    $book_col = $conn->query("SHOW COLUMNS FROM moa LIKE 'book_no'");
    if (!$book_col || $book_col->num_rows === 0) {
        $conn->query("ALTER TABLE moa ADD COLUMN book_no VARCHAR(100) NOT NULL AFTER page_no");
    }
    $series_col = $conn->query("SHOW COLUMNS FROM moa LIKE 'series_no'");
    if (!$series_col || $series_col->num_rows === 0) {
        $conn->query("ALTER TABLE moa ADD COLUMN series_no VARCHAR(100) NOT NULL AFTER book_no");
    }
    $school_pos_col = $conn->query("SHOW COLUMNS FROM moa LIKE 'school_posistion'");
    if (!$school_pos_col || $school_pos_col->num_rows === 0) {
        $conn->query("ALTER TABLE moa ADD COLUMN school_posistion VARCHAR(255) NOT NULL AFTER coordinator");
    }
    $school_admin_pos_col = $conn->query("SHOW COLUMNS FROM moa LIKE 'school_admin_position'");
    if (!$school_admin_pos_col || $school_admin_pos_col->num_rows === 0) {
        $conn->query("ALTER TABLE moa ADD COLUMN school_admin_position VARCHAR(255) NOT NULL AFTER school_administrator");
    }

    $res_col = $conn->query("SHOW COLUMNS FROM application_letter LIKE 'company_address'");
    if (!$res_col || $res_col->num_rows === 0) {
        $conn->query("ALTER TABLE application_letter ADD COLUMN company_address VARCHAR(255) DEFAULT NULL");
    }
    $res_type = $conn->query("SHOW COLUMNS FROM application_letter LIKE 'company_name'");
    if ($res_type && $res_type->num_rows > 0) {
        $col = $res_type->fetch_assoc();
        if (isset($col['Type']) && stripos($col['Type'], 'int') !== false) {
            $conn->query("ALTER TABLE application_letter MODIFY company_name VARCHAR(255) DEFAULT NULL");
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_application_letter'])) {
        $posted_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if ($posted_user_id > 0) {
            // Keep compatible with schemas where `application_letter.date` is NOT NULL.
            $date_val = isset($_POST['date']) && $_POST['date'] !== '' ? $_POST['date'] : date('Y-m-d');
            $person_val = trim($_POST['application_person'] ?? '');
            $position_val = trim($_POST['position'] ?? '');
            $company_name_val = trim($_POST['company_name'] ?? '');
            $company_address_val = trim($_POST['company_address'] ?? '');

            $stmt = $conn->prepare("INSERT INTO application_letter (user_id, date, application_person, position, company_name, company_address)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    date = VALUES(date),
                    application_person = VALUES(application_person),
                    position = VALUES(position),
                    company_name = VALUES(company_name),
                    company_address = VALUES(company_address)");
            $stmt->bind_param('isssss', $posted_user_id, $date_val, $person_val, $position_val, $company_name_val, $company_address_val);
            if ($stmt->execute()) {
                $flash_message = 'Application Letter autofill data saved.';
                $flash_type = 'success';
            } else {
                $flash_message = 'Failed to save Application Letter data.';
                $flash_type = 'danger';
            }
            $view_user_id = $posted_user_id;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_moa'])) {
        $posted_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if ($posted_user_id > 0) {
            $company_name = trim($_POST['company_name'] ?? '');
            $company_address = trim($_POST['company_address'] ?? '');
            $company_receipt = trim($_POST['company_receipt'] ?? '');
            $doc_no = trim($_POST['doc_no'] ?? '');
            $page_no = trim($_POST['page_no'] ?? '');
            $book_no = trim($_POST['book_no'] ?? '');
            $series_no = trim($_POST['series_no'] ?? '');
            $total_hours = trim($_POST['total_hours'] ?? '');
            $moa_address = trim($_POST['moa_address'] ?? '');
            $moa_date = isset($_POST['moa_date']) && $_POST['moa_date'] !== '' ? $_POST['moa_date'] : null;
            $coordinator = trim($_POST['coordinator'] ?? '');
            $school_posistion = trim($_POST['school_position'] ?? ($_POST['school_posistion'] ?? ''));
            $position = trim($_POST['position'] ?? '');
            $partner_representative = trim($_POST['partner_representative'] ?? '');
            $school_administrator = trim($_POST['school_administrator'] ?? '');
            $school_admin_position = trim($_POST['school_admin_position'] ?? '');
            $notary_address = trim($_POST['notary_address'] ?? '');
            $witness = trim($_POST['witness'] ?? '');
            $acknowledgement_date = isset($_POST['acknowledgement_date']) && $_POST['acknowledgement_date'] !== '' ? $_POST['acknowledgement_date'] : null;
            $acknowledgement_address = trim($_POST['acknowledgement_address'] ?? '');

            $stmt = $conn->prepare("INSERT INTO moa (user_id, company_name, company_address, company_receipt, doc_no, page_no, book_no, series_no, total_hours, moa_address, moa_date, coordinator, school_posistion, position, partner_representative, school_administrator, school_admin_position, notary_address, witness, acknowledgement_date, acknowledgement_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    company_name = VALUES(company_name),
                    company_address = VALUES(company_address),
                    company_receipt = VALUES(company_receipt),
                    doc_no = VALUES(doc_no),
                    page_no = VALUES(page_no),
                    book_no = VALUES(book_no),
                    series_no = VALUES(series_no),
                    total_hours = VALUES(total_hours),
                    moa_address = VALUES(moa_address),
                    moa_date = VALUES(moa_date),
                    coordinator = VALUES(coordinator),
                    school_posistion = VALUES(school_posistion),
                    position = VALUES(position),
                    partner_representative = VALUES(partner_representative),
                    school_administrator = VALUES(school_administrator),
                    school_admin_position = VALUES(school_admin_position),
                    notary_address = VALUES(notary_address),
                    witness = VALUES(witness),
                    acknowledgement_date = VALUES(acknowledgement_date),
                    acknowledgement_address = VALUES(acknowledgement_address)");
            $types = 'i' . str_repeat('s', 20);
            $stmt->bind_param(
                $types,
                $posted_user_id,
                $company_name,
                $company_address,
                $company_receipt,
                $doc_no,
                $page_no,
                $book_no,
                $series_no,
                $total_hours,
                $moa_address,
                $moa_date,
                $coordinator,
                $school_posistion,
                $position,
                $partner_representative,
                $school_administrator,
                $school_admin_position,
                $notary_address,
                $witness,
                $acknowledgement_date,
                $acknowledgement_address
            );
            if ($stmt->execute()) {
                $flash_message = 'MOA autofill data saved.';
                $flash_type = 'success';
            } else {
                $flash_message = 'Failed to save MOA data.';
                $flash_type = 'danger';
            }
            $view_user_id = $posted_user_id;
        }
    }

    if ($view_user_id > 0) {
        // Resolve student robustly across varying schemas/data imports.
        $student_cols = [];
        $col_res = $conn->query("SHOW COLUMNS FROM students");
        if ($col_res) {
            while ($col = $col_res->fetch_assoc()) {
                $student_cols[] = $col['Field'] ?? '';
            }
        }

        $candidate_wheres = [];
        $candidate_wheres[] = "s.id = " . intval($view_user_id);
        if (in_array('user_id', $student_cols, true)) {
            $candidate_wheres[] = "s.user_id = " . intval($view_user_id);
        }
        if (in_array('student_id', $student_cols, true)) {
            $candidate_wheres[] = "s.student_id = '" . $conn->real_escape_string((string)$view_user_id) . "'";
        }
        $candidate_sql = implode(' OR ', array_unique($candidate_wheres));
        // Priority order is critical:
        // 1) exact students.id match, 2) user_id match, 3) student_id string match.
        $student_sql = "SELECT s.*, c.name AS course_name
            FROM students s
            LEFT JOIN courses c ON s.course_id = c.id
            WHERE (" . $candidate_sql . ")
            ORDER BY
                CASE
                    WHEN s.id = " . intval($view_user_id) . " THEN 1
                    " . (in_array('user_id', $student_cols, true) ? "WHEN s.user_id = " . intval($view_user_id) . " THEN 2" : "") . "
                    " . (in_array('student_id', $student_cols, true) ? "WHEN s.student_id = '" . $conn->real_escape_string((string)$view_user_id) . "' THEN 3" : "") . "
                    ELSE 99
                END
            LIMIT 1";
        $res_student = $conn->query($student_sql);
        $student = $res_student ? $res_student->fetch_assoc() : null;
        if ($student) {
            $selected_student_id = intval($student['id'] ?? $view_user_id);
            $selected_user_id = intval($student['user_id'] ?? 0);
        }

        $doc_lookup_ids = array_values(array_unique(array_filter([
            $selected_student_id,
            $selected_user_id,
            $view_user_id
        ], function ($v) { return intval($v) > 0; })));

        $row = null;
        foreach ($doc_lookup_ids as $lookup_id) {
            $stmt_app = $conn->prepare("SELECT * FROM application_letter WHERE user_id = ? LIMIT 1");
            $stmt_app->bind_param('i', $lookup_id);
            $stmt_app->execute();
            $res_app = $stmt_app->get_result();
            $row = $res_app ? $res_app->fetch_assoc() : null;
            if ($row) break;
        }
        if ($row) {
            $app_letter['date'] = $row['date'] ?? '';
            $app_letter['application_person'] = $row['application_person'] ?? '';
            $app_letter['position'] = $row['position'] ?? '';
            $app_letter['company_name'] = isset($row['company_name']) ? (string)$row['company_name'] : '';
            $app_letter['company_address'] = $row['company_address'] ?? '';
        }

        $moa_row = null;
        foreach ($doc_lookup_ids as $lookup_id) {
            $stmt_moa = $conn->prepare("SELECT * FROM moa WHERE user_id = ? LIMIT 1");
            $stmt_moa->bind_param('i', $lookup_id);
            $stmt_moa->execute();
            $res_moa = $stmt_moa->get_result();
            $moa_row = $res_moa ? $res_moa->fetch_assoc() : null;
            if ($moa_row) break;
        }
        if ($moa_row) {
            if (!isset($moa_row['school_position']) || $moa_row['school_position'] === '' || $moa_row['school_position'] === null) {
                $moa_row['school_position'] = $moa_row['school_posistion'] ?? '';
            }
            foreach ($moa_data as $k => $v) {
                $moa_data[$k] = isset($moa_row[$k]) ? (string)$moa_row[$k] : '';
            }
        }
    }
} catch (Exception $e) {
    $flash_message = 'Database error: ' . $e->getMessage();
    $flash_type = 'danger';
}
?><!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>BioTern || OJT View</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <!--! END: Favicon-->
    <script>
        (function(){
            try{
                var s = localStorage.getItem('app-skin-dark') || localStorage.getItem('app-skin') || localStorage.getItem('app_skin') || localStorage.getItem('theme');
                if (s && (s.indexOf && s.indexOf('dark') !== -1 || s === 'app-skin-dark')) {
                    document.documentElement.classList.add('app-skin-dark');
                }
            }catch(e){}
        })();
    </script>
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <script>try{var s=localStorage.getItem('app-skin')||localStorage.getItem('app_skin')||localStorage.getItem('theme'); if(s&&s.indexOf('dark')!==-1)document.documentElement.classList.add('app-skin-dark');}catch(e){};</script>
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
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
    <!--! [Start] Navigation Manu !-->
    <!--! ================================================================ !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="index.php" class="b-brand">
                    <!-- ========   change your logo hear   ============ -->
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
                            <li class="nxl-item"><a class="nxl-link" href="settings-ojt.php">OJT</a></li>
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
    <!--! ================================================================ !-->
    <!--! [End]  Navigation Manu !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! [Start] Header !-->
    <!--! ================================================================ !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <!--! [Start] Header Left !-->
            <div class="header-left d-flex align-items-center gap-4">
                <!--! [Start] nxl-head-mobile-toggler !-->
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <!--! [Start] nxl-head-mobile-toggler !-->
                <!--! [Start] nxl-navigation-toggle !-->
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
                <!--! [End] nxl-navigation-toggle !-->
            </div>
            <!--! [End] Header Left !-->
            <!--! [Start] Header Right !-->
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
                            <div class="dropdown-divider mt-0"></div>
                            <!--! search coding for database !-->
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
                        <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <i class="feather-clock"></i>
                            <span class="badge bg-success nxl-h-badge">2</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-timesheets-menu">
                            <div class="d-flex justify-content-between align-items-center timesheets-head">
                                <h6 class="fw-bold text-dark mb-0">Timesheets</h6>
                                <a href="javascript:void(0);" class="fs-11 text-success text-end ms-auto" data-bs-toggle="tooltip" title="Upcomming Timers">
                                    <i class="feather-clock"></i>
                                    <span>3 Upcomming</span>
                                </a>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-column timesheets-body">
                                <i class="feather-clock fs-1 mb-4"></i>
                                <p class="text-muted">No started timers found yes!</p>
                                <a href="javascript:void(0);" class="btn btn-sm btn-primary">Started Timer</a>
                            </div>
                            <div class="text-center timesheets-footer">
                                <a href="javascript:void(0);" class="fs-13 fw-semibold text-dark">Alls Timesheets</a>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                            <i class="feather-bell"></i>
                            <span class="badge bg-danger nxl-h-badge">3</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                            <div class="d-flex justify-content-between align-items-center notifications-head">
                                <h6 class="fw-bold text-dark mb-0">Notifications</h6>
                                <a href="javascript:void(0);" class="fs-11 text-success text-end ms-auto" data-bs-toggle="tooltip" title="Make as Read">
                                    <i class="feather-check"></i>
                                    <span>Make as Read</span>
                                </a>
                            </div>
                            <div class="notifications-item">
                                <img src="assets/images/avatar/2.png" alt="" class="rounded me-3 border">
                                <div class="notifications-desc">
                                    <a href="javascript:void(0);" class="font-body text-truncate-2-line"> <span class="fw-semibold text-dark">Malanie Hanvey</span> We should talk about that at lunch!</a>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="notifications-date text-muted border-bottom border-bottom-dashed">2 minutes ago</div>
                                        <div class="d-flex align-items-center float-end gap-2">
                                            <a href="javascript:void(0);" class="d-block wd-8 ht-8 rounded-circle bg-gray-300" data-bs-toggle="tooltip" title="Make as Read"></a>
                                            <a href="javascript:void(0);" class="text-danger" data-bs-toggle="tooltip" title="Remove">
                                                <i class="feather-x fs-12"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
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
                                        <h6 class="text-dark mb-0">Felix Luis Mateo <span class="badge bg-soft-success text-success ms-1">PRO</span></h6>
                                        <span class="fs-12 fw-medium text-muted">felixluismateo@example.com</span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown">
                                <a href="javascript:void(0);" class="dropdown-item" data-bs-toggle="dropdown">
                                    <span class="hstack">
                                        <i class="wd-10 ht-10 border border-2 border-gray-1 bg-success rounded-circle me-2"></i>
                                        <span>Active</span>
                                    </span>
                                    <i class="feather-chevron-right ms-auto me-0"></i>
                                </a>
                                <div class="dropdown-menu">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="wd-10 ht-10 border border-2 border-gray-1 bg-warning rounded-circle me-2"></i>
                                            <span>Always</span>
                                        </span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <span class="hstack">
                                            <i class="wd-10 ht-10 border border-2 border-gray-1 bg-success rounded-circle me-2"></i>
                                            <span>Active</span>
                                        </span>
                                    </a>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>

                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-user"></i>
                                <span>Profile Details</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-activity"></i>
                                <span>Activity Feed</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-bell"></i>
                                <span>Notifications</span>
                            </a>
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
            <!--! [End] Header Right !-->
        </div>
    </header>
    <!--! ================================================================ !-->
    <!--! [End] Header !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- [ page-header ] start -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">OJT</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item">View</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex d-md-none">
                            <a href="javascript:void(0)" class="page-header-right-close-toggle">
                                <i class="feather-arrow-left me-2"></i>
                                <span>Back</span>
                            </a>
                        </div>
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            <a href="javascript:void(0);" class="btn btn-icon btn-light-brand">
                                <i class="feather-printer"></i>
                            </a>
                            <a href="ojt-create.php" class="btn btn-icon btn-light-brand">
                                <i class="feather-edit"></i>
                            </a>
                            <div class="dropdown">
                                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-more-horizontal"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-user-x me-3"></i>
                                        <span>Make as Lost</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-delete me-3"></i>
                                        <span>Make as Junk</span>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a href="javascript:void(0);" class="dropdown-item">
                                        <i class="feather-trash-2 me-3"></i>
                                        <span>Delete as Lead</span>
                                    </a>
                                </div>
                            </div>
                            <a href="javascript:void(0);" class="btn btn-primary successAlertMessage">
                                <i class="feather-plus me-2"></i>
                                <span>Make as Student</span>
                            </a>
                        </div>
                    </div>
                    <div class="d-md-none d-flex align-items-center">
                        <a href="javascript:void(0)" class="page-header-right-open-toggle">
                            <i class="feather-align-right fs-20"></i>
                        </a>
                    </div>
                </div>
            </div>
            <!-- [ page-header ] end -->
            <div class="bg-white py-3 border-bottom rounded-0 p-md-0 mb-0">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="nav-tabs-wrapper page-content-left-sidebar-wrapper">
                        <ul class="nav nav-tabs nav-tabs-custom-style" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profileTab">Profile</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#applicationTab">Application Letter</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#moaTab">MOA</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#endorsementTab">Endorsement Letter</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#commentTab">Comments</button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- [ Main Content ] start -->
            <div class="main-content">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="profileTab" role="tabpanel">
                        <?php if (!$student): ?>
                            <div class="card card-body">
                                <div class="alert alert-warning mb-0">No student found for this ID.</div>
                            </div>
                        <?php else: ?>
                            <?php
                            $full_name = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
                            $profile_picture = trim((string)($student['profile_picture'] ?? ''));
                            $profile_img_src = 'assets/images/avatar/1.png';
                            if ($profile_picture !== '' && file_exists(__DIR__ . '/' . $profile_picture)) {
                                $profile_img_src = $profile_picture . '?v=' . filemtime(__DIR__ . '/' . $profile_picture);
                            }
                            ?>
                            <div class="card card-body lead-info">
                                <div class="mb-4 d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0">
                                        <span class="d-block mb-2">Student Information :</span>
                                        <span class="fs-12 fw-normal text-muted d-block">Live data from students table</span>
                                    </h5>
                                    <a href="students-view.php?id=<?php echo intval($student['id']); ?>" class="btn btn-sm btn-light-brand">Open Student View</a>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Profile</div>
                                    <div class="col-lg-10"><img src="<?php echo htmlspecialchars($profile_img_src); ?>" alt="profile" class="img-fluid rounded-circle" style="width:56px;height:56px;object-fit:cover;"></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Name</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($full_name)); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Student ID</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['student_id'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Course</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['course_name'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Email</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['email'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Phone</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['phone'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Gender</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text(isset($student['gender']) ? ucfirst((string)$student['gender']) : '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Date of Birth</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['date_of_birth'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-0">
                                    <div class="col-lg-2 fw-medium">Address</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['address'] ?? '')); ?></div>
                                </div>
                            </div>
                            <hr>
                            <div class="card card-body general-info">
                                <div class="mb-4 d-flex align-items-center justify-content-between">
                                    <h5 class="fw-bold mb-0">
                                        <span class="d-block mb-2">General Information :</span>
                                        <span class="fs-12 fw-normal text-muted d-block">Live data from students table</span>
                                    </h5>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Status</div>
                                    <div class="col-lg-10"><?php echo status_badge_html($student['status'] ?? ''); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Supervisor</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['supervisor_name'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Coordinator</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['coordinator_name'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Emergency Contact</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['emergency_contact'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Total Hours</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['internal_total_hours'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Hours Remaining</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(display_text($student['internal_total_hours_remaining'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Biometric Registered</div>
                                    <div class="col-lg-10"><?php echo !empty($student['biometric_registered']) ? 'Yes' : 'No'; ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Biometric Registered At</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(format_dt($student['biometric_registered_at'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-lg-2 fw-medium">Created At</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(format_dt($student['created_at'] ?? '')); ?></div>
                                </div>
                                <div class="row mb-0">
                                    <div class="col-lg-2 fw-medium">Updated At</div>
                                    <div class="col-lg-10"><?php echo htmlspecialchars(format_dt($student['updated_at'] ?? '')); ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade" id="applicationTab" role="tabpanel">
                        <div class="card card-body">
                            <?php if ($flash_message !== ''): ?>
                                <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?> mb-3"><?php echo htmlspecialchars($flash_message); ?></div>
                            <?php endif; ?>

                            <?php if ($view_user_id <= 0): ?>
                                <div class="alert alert-warning mb-0">Open this page from OJT List using a specific student row so document data can be linked by user ID.</div>
                            <?php else: ?>
                                <div class="mb-4">
                                    <h5 class="fw-bold mb-1">Application Letter Autofill</h5>
                                    <p class="text-muted mb-0">Saved data here will be used by <code>document_application.php</code> when this student is selected.</p>
                                </div>

                                <div class="mb-3">
                                    <strong>Student:</strong>
                                    <?php
                                    $student_name = $student ? trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) : 'Unknown';
                                    echo htmlspecialchars($student_name);
                                    ?>
                                    <span class="text-muted">(ID: <?php echo intval($view_user_id); ?>)</span>
                                </div>

                                <form method="post" action="ojt-view.php?id=<?php echo intval($selected_student_id); ?>">
                                    <input type="hidden" name="save_application_letter" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo intval($selected_student_id); ?>">

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Date</label>
                                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($app_letter['date']); ?>">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">Mr./Ms. (as to appear)</label>
                                            <input type="text" name="application_person" class="form-control" value="<?php echo htmlspecialchars($app_letter['application_person']); ?>" placeholder="Recipient full name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Position</label>
                                            <input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($app_letter['position']); ?>" placeholder="Position">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Company Name</label>
                                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($app_letter['company_name']); ?>" placeholder="Company name">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Company Address</label>
                                            <textarea name="company_address" class="form-control" rows="2" placeholder="Company address"><?php echo htmlspecialchars($app_letter['company_address']); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="mt-3 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Save Application Data</button>
                                        <a href="document_application.php?id=<?php echo intval($selected_student_id); ?>" class="btn btn-success">Open Application Letter</a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="moaTab" role="tabpanel">
                        <div class="card card-body">
                            <?php if ($view_user_id <= 0): ?>
                                <div class="alert alert-warning mb-0">Open this page from OJT List using a specific student row so document data can be linked by user ID.</div>
                            <?php else: ?>
                                <div class="mb-4">
                                    <h5 class="fw-bold mb-1">MOA Autofill</h5>
                                    <p class="text-muted mb-0">Saved data here will be used by <code>document_moa.php</code> when this student is selected.</p>
                                </div>
                                <form method="post" action="ojt-view.php?id=<?php echo intval($selected_student_id); ?>">
                                    <input type="hidden" name="save_moa" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo intval($selected_student_id); ?>">

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Company Name</label>
                                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($moa_data['company_name']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Company Address</label>
                                            <input type="text" name="company_address" class="form-control" value="<?php echo htmlspecialchars($moa_data['company_address']); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Partner Representative</label>
                                            <input type="text" name="partner_representative" class="form-control" value="<?php echo htmlspecialchars($moa_data['partner_representative']); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Partner Representative Position</label>
                                            <input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($moa_data['position']); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Coordinator / School Rep</label>
                                            <input type="text" name="coordinator" class="form-control" value="<?php echo htmlspecialchars($moa_data['coordinator']); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Coordinator / School Rep Position</label>
                                            <input type="text" name="school_position" class="form-control" value="<?php echo htmlspecialchars($moa_data['school_position']); ?>">
                                        </div>



                                        
                                        <div class="col-md-4">
                                            <label class="form-label">MOA Date</label>
                                            <input type="date" name="moa_date" class="form-control" value="<?php echo htmlspecialchars($moa_data['moa_date']); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">MOA Address / Signing Place</label>
                                            <input type="text" name="moa_address" class="form-control" value="<?php echo htmlspecialchars($moa_data['moa_address']); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">School Administrator</label>
                                            <input type="text" name="school_administrator" class="form-control" value="<?php echo htmlspecialchars($moa_data['school_administrator']); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">School Administrator Position</label>
                                            <input type="text" name="school_admin_position" class="form-control" value="<?php echo htmlspecialchars($moa_data['school_admin_position']); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Witness</label>
                                            <input type="text" name="witness" class="form-control" value="<?php echo htmlspecialchars($moa_data['witness']); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Notary Address/City</label>
                                            <input type="text" name="notary_address" class="form-control" value="<?php echo htmlspecialchars($moa_data['notary_address']); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Acknowledgement Date</label>
                                            <input type="date" name="acknowledgement_date" class="form-control" value="<?php echo htmlspecialchars($moa_data['acknowledgement_date']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Acknowledgement Address</label>
                                            <input type="text" name="acknowledgement_address" class="form-control" value="<?php echo htmlspecialchars($moa_data['acknowledgement_address']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Company Receipt / Ref.</label>
                                            <input type="text" name="company_receipt" class="form-control" value="<?php echo htmlspecialchars($moa_data['company_receipt']); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Doc No.</label>
                                            <input type="text" name="doc_no" class="form-control" value="<?php echo htmlspecialchars($moa_data['doc_no']); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Page No.</label>
                                            <input type="text" name="page_no" class="form-control" value="<?php echo htmlspecialchars($moa_data['page_no']); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Book No.</label>
                                            <input type="text" name="book_no" class="form-control" value="<?php echo htmlspecialchars($moa_data['book_no']); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Series of</label>
                                            <input type="text" name="series_no" class="form-control" value="<?php echo htmlspecialchars($moa_data['series_no']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Total Hours (Clause #10)</label>
                                            <input type="number" name="total_hours" class="form-control" min="1" step="1" value="<?php echo htmlspecialchars($moa_data['total_hours']); ?>">
                                        </div>
                                    </div>

                                    <div class="mt-3 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Save MOA Data</button>
                                        <a href="document_moa.php?id=<?php echo intval($selected_student_id); ?>" class="btn btn-success">Open MOA</a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="endorsementTab" role="tabpanel">
                        <div class="card card-body">
                            <div class="d-flex align-items-center justify-content-center" style="height: calc(100vh - 315px)">
                                <div class="text-center">
                                    <h2 class="fs-16 fw-semibold">No notes yet!</h2>
                                    <p class="fs-12 text-muted">There is no notes create yet.</p>
                                    <a href="javascript:void(0);" class="avatar-text bg-soft-primary text-primary mx-auto" data-bs-toggle="tooltip" title="Create Notes">
                                        <i class="feather-plus"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="commentTab" role="tabpanel">
                        <div class="card card-body">
                            <div class="d-flex align-items-center justify-content-center" style="height: calc(100vh - 315px)">
                                <div class="text-center">
                                    <h2 class="fs-16 fw-semibold">No comments yet!</h2>
                                    <p class="fs-12 text-muted">There is no comments posted yet.</p>
                                    <a href="javascript:void(0);" class="avatar-text bg-soft-primary text-primary mx-auto" data-bs-toggle="tooltip" title="Add Comments">
                                        <i class="feather-plus"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ Main Content ] end -->
        </div>
        <!-- [ Footer ] start -->
        <footer class="footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright ©</span>
                <script>
                    document.write(new Date().getFullYear());
                </script>
            </p>
            <p><span>By: <a target="_blank" href="" target="_blank">ACT 2A</a> </span><span>Distributed by: <a target="_blank" href="" target="_blank">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </footer>
        <!-- [ Footer ] end -->
    </main>
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Theme Customizer !-->
    <!--! ================================================================ !-->
    <div class="theme-customizer">
        <div class="customizer-handle">
            <a href="javascript:void(0);" class="cutomizer-open-trigger bg-primary">
                <i class="feather-settings"></i>
            </a>
        </div>
        <div class="customizer-sidebar-wrapper">
            <div class="customizer-sidebar-header px-4 ht-80 border-bottom d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Theme Settings</h5>
                <a href="javascript:void(0);" class="cutomizer-close-trigger d-flex">
                    <i class="feather-x"></i>
                </a>
            </div>
            <div class="customizer-sidebar-body position-relative p-4" data-scrollbar-target="#psScrollbarInit">
                <!--! BEGIN: [Navigation] !-->
                <div class="position-relative px-3 pb-3 pt-4 mt-3 mb-5 border border-gray-2 theme-options-set">
                    <label class="py-1 px-2 fs-8 fw-bold text-uppercase text-muted text-spacing-2 bg-white border border-gray-2 position-absolute rounded-2 options-label" style="top: -12px">Navigation</label>
                    <div class="row g-2 theme-options-items app-navigation" id="appNavigationList">
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-navigation-light" name="app-navigation" value="1" data-app-navigation="app-navigation-light" checked>
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-navigation-light">Light</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-navigation-dark" name="app-navigation" value="2" data-app-navigation="app-navigation-dark">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-navigation-dark">Dark</label>
                        </div>
                    </div>
                </div>
                <!--! END: [Navigation] !-->
                <!--! BEGIN: [Header] !-->
                <div class="position-relative px-3 pb-3 pt-4 mt-3 mb-5 border border-gray-2 theme-options-set mt-5">
                    <label class="py-1 px-2 fs-8 fw-bold text-uppercase text-muted text-spacing-2 bg-white border border-gray-2 position-absolute rounded-2 options-label" style="top: -12px">Header</label>
                    <div class="row g-2 theme-options-items app-header" id="appHeaderList">
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-header-light" name="app-header" value="1" data-app-header="app-header-light" checked>
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-header-light">Light</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-header-dark" name="app-header" value="2" data-app-header="app-header-dark">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-header-dark">Dark</label>
                        </div>
                    </div>
                </div>
                <!--! END: [Header] !-->
                <!--! BEGIN: [Skins] !-->
                <div class="position-relative px-3 pb-3 pt-4 mt-3 mb-5 border border-gray-2 theme-options-set">
                    <label class="py-1 px-2 fs-8 fw-bold text-uppercase text-muted text-spacing-2 bg-white border border-gray-2 position-absolute rounded-2 options-label" style="top: -12px">Skins</label>
                    <div class="row g-2 theme-options-items app-skin" id="appSkinList">
                        <div class="col-6 text-center position-relative single-option light-button active">
                            <input type="radio" class="btn-check" id="app-skin-light" name="app-skin" value="1" data-app-skin="app-skin-light">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-skin-light">Light</label>
                        </div>
                        <div class="col-6 text-center position-relative single-option dark-button">
                            <input type="radio" class="btn-check" id="app-skin-dark" name="app-skin" value="2" data-app-skin="app-skin-dark">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-skin-dark">Dark</label>
                        </div>
                    </div>
                </div>
                <!--! END: [Skins] !-->
                <!--! BEGIN: [Typography] !-->
                <div class="position-relative px-3 pb-3 pt-4 mt-3 mb-0 border border-gray-2 theme-options-set">
                    <label class="py-1 px-2 fs-8 fw-bold text-uppercase text-muted text-spacing-2 bg-white border border-gray-2 position-absolute rounded-2 options-label" style="top: -12px">Typography</label>
                    <div class="row g-2 theme-options-items font-family" id="fontFamilyList">
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-lato" name="font-family" value="1" data-font-family="app-font-family-lato">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-lato">Lato</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-rubik" name="font-family" value="2" data-font-family="app-font-family-rubik">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-rubik">Rubik</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-inter" name="font-family" value="3" data-font-family="app-font-family-inter" checked>
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-inter">Inter</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-cinzel" name="font-family" value="4" data-font-family="app-font-family-cinzel">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-cinzel">Cinzel</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-nunito" name="font-family" value="6" data-font-family="app-font-family-nunito">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-nunito">Nunito</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-roboto" name="font-family" value="7" data-font-family="app-font-family-roboto">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-roboto">Roboto</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-ubuntu" name="font-family" value="8" data-font-family="app-font-family-ubuntu">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-ubuntu">Ubuntu</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-poppins" name="font-family" value="9" data-font-family="app-font-family-poppins">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-poppins">Poppins</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-raleway" name="font-family" value="10" data-font-family="app-font-family-raleway">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-raleway">Raleway</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-system-ui" name="font-family" value="11" data-font-family="app-font-family-system-ui">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-system-ui">System UI</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-noto-sans" name="font-family" value="12" data-font-family="app-font-family-noto-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-noto-sans">Noto Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-fira-sans" name="font-family" value="13" data-font-family="app-font-family-fira-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-fira-sans">Fira Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-work-sans" name="font-family" value="14" data-font-family="app-font-family-work-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-work-sans">Work Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-open-sans" name="font-family" value="15" data-font-family="app-font-family-open-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-open-sans">Open Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-maven-pro" name="font-family" value="16" data-font-family="app-font-family-maven-pro">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-maven-pro">Maven Pro</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-quicksand" name="font-family" value="17" data-font-family="app-font-family-quicksand">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-quicksand">Quicksand</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-montserrat" name="font-family" value="18" data-font-family="app-font-family-montserrat">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-montserrat">Montserrat</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-josefin-sans" name="font-family" value="19" data-font-family="app-font-family-josefin-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-josefin-sans">Josefin Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-ibm-plex-sans" name="font-family" value="20" data-font-family="app-font-family-ibm-plex-sans">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-ibm-plex-sans">IBM Plex Sans</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-source-sans-pro" name="font-family" value="5" data-font-family="app-font-family-source-sans-pro">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-source-sans-pro">Source Sans Pro</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-montserrat-alt" name="font-family" value="21" data-font-family="app-font-family-montserrat-alt">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-montserrat-alt">Montserrat Alt</label>
                        </div>
                        <div class="col-6 text-center single-option">
                            <input type="radio" class="btn-check" id="app-font-family-roboto-slab" name="font-family" value="22" data-font-family="app-font-family-roboto-slab">
                            <label class="py-2 fs-9 fw-bold text-dark text-uppercase text-spacing-1 border border-gray-2 w-100 h-100 c-pointer position-relative options-label" for="app-font-family-roboto-slab">Roboto Slab</label>
                        </div>
                    </div>
                </div>
                <!--! END: [Typography] !-->
            </div>
            <div class="customizer-sidebar-footer px-4 ht-60 border-top d-flex align-items-center gap-2">
                <div class="flex-fill w-50">
                    <a href="javascript:void(0);" class="btn btn-danger" data-style="reset-all-common-style">Reset</a>
                </div>
                <div class="flex-fill w-50">
                    <a href="https://www.themewagon.com/themes/BioTern-admin" target="_blank" class="btn btn-primary">Download</a>
                </div>
            </div>
        </div>
    </div>

    <!--! ================================================================ !-->
    <!--! [End] Theme Customizer !-->
    <!--! ================================================================ !-->
    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/leads-view-init.min.js"></script>
    <!--! END: Apps Init !-->
    <!--! BEGIN: Theme Customizer  !-->
    <script src="assets/js/theme-customizer-init.min.js"></script>
    <!--! END: Theme Customizer !-->
</body>

</html>


