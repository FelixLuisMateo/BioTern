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
$endorsement_data = [
    'recipient_name' => '',
    'recipient_position' => '',
    'company_name' => '',
    'company_address' => '',
    'students_to_endorse' => ''
];
$dau_moa_data = [
    'company_name' => '',
    'company_address' => '',
    'partner_representative' => '',
    'position' => '',
    'company_receipt' => '',
    'total_hours' => '',
    'school_representative' => '',
    'school_position' => '',
    'signed_at' => '',
    'signed_day' => '',
    'signed_month' => '',
    'signed_year' => '',
    'witness_partner' => '',
    'school_administrator' => '',
    'school_admin_position' => '',
    'notary_city' => '',
    'notary_day' => '',
    'notary_month' => '',
    'notary_year' => '',
    'notary_place' => '',
    'doc_no' => '',
    'page_no' => '',
    'book_no' => '',
    'series_no' => ''
];
$active_tab = 'profileTab';
$internship_data = null;
$attendance_summary = ['last_attendance_date' => '', 'pending_count' => 0, 'total_hours' => 0.0];
$document_completion = ['application' => false, 'endorsement' => false, 'moa' => false, 'dau_moa' => false];
$document_last_saved = ['application' => '', 'endorsement' => '', 'moa' => '', 'dau_moa' => ''];
$pipeline_stage = 'Applied';
$risk_flags = [];
$profile_timeline = [];
$audit_trail = [];
$attendance_audit_rows = [];
$workflow = [
    'application' => ['status' => 'draft', 'review_notes' => '', 'approved_by' => 0, 'approved_at' => ''],
    'endorsement' => ['status' => 'draft', 'review_notes' => '', 'approved_by' => 0, 'approved_at' => ''],
    'moa' => ['status' => 'draft', 'review_notes' => '', 'approved_by' => 0, 'approved_at' => ''],
    'dau_moa' => ['status' => 'draft', 'review_notes' => '', 'approved_by' => 0, 'approved_at' => ''],
];
$review_notes = [];
$current_user_id = intval($_SESSION['user_id'] ?? 0);
$current_role = strtolower((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? ''));
$can_approve_docs = in_array($current_role, ['admin', 'coordinator'], true);

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

function ojt_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return ($res && $res->num_rows > 0);
}

function ojt_stage_badge(string $stage): string
{
    $map = [
        'Applied' => 'bg-soft-warning text-warning',
        'Endorsed' => 'bg-soft-info text-info',
        'Accepted' => 'bg-soft-primary text-primary',
        'Ongoing' => 'bg-soft-success text-success',
        'Completed' => 'bg-soft-success text-success',
        'Dropped' => 'bg-soft-danger text-danger',
    ];
    $cls = $map[$stage] ?? 'bg-soft-secondary text-secondary';
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($stage) . '</span>';
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
    $conn->query("CREATE TABLE IF NOT EXISTS endorsement_letter (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        recipient_name VARCHAR(255) DEFAULT NULL,
        recipient_position VARCHAR(255) DEFAULT NULL,
        company_name VARCHAR(255) DEFAULT NULL,
        company_address VARCHAR(255) DEFAULT NULL,
        students_to_endorse TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $conn->query("CREATE TABLE IF NOT EXISTS dau_moa (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        company_name VARCHAR(255) DEFAULT NULL,
        company_address VARCHAR(255) DEFAULT NULL,
        partner_representative VARCHAR(255) DEFAULT NULL,
        position VARCHAR(255) DEFAULT NULL,
        company_receipt VARCHAR(255) DEFAULT NULL,
        total_hours VARCHAR(50) DEFAULT NULL,
        school_representative VARCHAR(255) DEFAULT NULL,
        school_position VARCHAR(255) DEFAULT NULL,
        signed_at VARCHAR(255) DEFAULT NULL,
        signed_day VARCHAR(20) DEFAULT NULL,
        signed_month VARCHAR(30) DEFAULT NULL,
        signed_year VARCHAR(10) DEFAULT NULL,
        witness_partner VARCHAR(255) DEFAULT NULL,
        school_administrator VARCHAR(255) DEFAULT NULL,
        school_admin_position VARCHAR(255) DEFAULT NULL,
        notary_city VARCHAR(255) DEFAULT NULL,
        notary_day VARCHAR(20) DEFAULT NULL,
        notary_month VARCHAR(30) DEFAULT NULL,
        notary_year VARCHAR(10) DEFAULT NULL,
        notary_place VARCHAR(255) DEFAULT NULL,
        doc_no VARCHAR(100) DEFAULT NULL,
        page_no VARCHAR(100) DEFAULT NULL,
        book_no VARCHAR(100) DEFAULT NULL,
        series_no VARCHAR(100) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $conn->query("CREATE TABLE IF NOT EXISTS document_workflow (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        doc_type VARCHAR(30) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'draft',
        review_notes TEXT NULL,
        approved_by INT NOT NULL DEFAULT 0,
        approved_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_doc (user_id, doc_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $conn->query("CREATE TABLE IF NOT EXISTS ojt_supervisor_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        reviewer_user_id INT NOT NULL DEFAULT 0,
        reviewer_role VARCHAR(50) NOT NULL DEFAULT '',
        note TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(student_id)
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_document_workflow'])) {
        $active_tab = trim((string)($_POST['active_tab'] ?? 'profileTab'));
        $posted_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $doc_type = trim((string)($_POST['doc_type'] ?? ''));
        $new_status = trim((string)($_POST['workflow_status'] ?? 'draft'));
        $review_note = trim((string)($_POST['workflow_note'] ?? ''));
        $allowed_doc_types = ['application', 'endorsement', 'moa', 'dau_moa'];
        $allowed_status = ['draft', 'for_review', 'approved', 'rejected'];
        if (!$can_approve_docs) {
            $flash_message = 'You are not allowed to change workflow statuses.';
            $flash_type = 'warning';
        } elseif ($posted_user_id > 0 && in_array($doc_type, $allowed_doc_types, true) && in_array($new_status, $allowed_status, true)) {
            $approved_by = 0;
            $approved_at = null;
            if ($new_status === 'approved') {
                $approved_by = $current_user_id;
                $approved_at = date('Y-m-d H:i:s');
            }
            $stmt = $conn->prepare("INSERT INTO document_workflow (user_id, doc_type, status, review_notes, approved_by, approved_at)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    review_notes = VALUES(review_notes),
                    approved_by = VALUES(approved_by),
                    approved_at = VALUES(approved_at),
                    updated_at = NOW()");
            $stmt->bind_param('isssis', $posted_user_id, $doc_type, $new_status, $review_note, $approved_by, $approved_at);
            if ($stmt->execute()) {
                $flash_message = strtoupper($doc_type) . ' workflow updated.';
                $flash_type = 'success';
            } else {
                $flash_message = 'Failed to update workflow.';
                $flash_type = 'danger';
            }
            $stmt->close();
            $view_user_id = $posted_user_id;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_review_note'])) {
        $active_tab = trim((string)($_POST['active_tab'] ?? 'profileTab'));
        $posted_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $review_note = trim((string)($_POST['review_note'] ?? ''));
        if ($posted_user_id > 0 && $review_note !== '') {
            $stmt = $conn->prepare("INSERT INTO ojt_supervisor_reviews (student_id, reviewer_user_id, reviewer_role, note) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('iiss', $posted_user_id, $current_user_id, $current_role, $review_note);
            if ($stmt->execute()) {
                $flash_message = 'Review note saved.';
                $flash_type = 'success';
            } else {
                $flash_message = 'Failed to save review note.';
                $flash_type = 'danger';
            }
            $stmt->close();
            $view_user_id = $posted_user_id;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_application_letter'])) {
        $active_tab = trim((string)($_POST['active_tab'] ?? 'applicationTab'));
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
        $active_tab = trim((string)($_POST['active_tab'] ?? 'moaTab'));
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_endorsement_letter'])) {
        $active_tab = trim((string)($_POST['active_tab'] ?? 'endorsementTab'));
        $posted_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if ($posted_user_id > 0) {
            $recipient_name = trim($_POST['recipient_name'] ?? '');
            $recipient_position = trim($_POST['recipient_position'] ?? '');
            $company_name = trim($_POST['company_name'] ?? '');
            $company_address = trim($_POST['company_address'] ?? '');
            $students_to_endorse = trim($_POST['students_to_endorse'] ?? '');

            $stmt = $conn->prepare("INSERT INTO endorsement_letter (user_id, recipient_name, recipient_position, company_name, company_address, students_to_endorse)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    recipient_name = VALUES(recipient_name),
                    recipient_position = VALUES(recipient_position),
                    company_name = VALUES(company_name),
                    company_address = VALUES(company_address),
                    students_to_endorse = VALUES(students_to_endorse),
                    updated_at = NOW()");
            $stmt->bind_param('isssss', $posted_user_id, $recipient_name, $recipient_position, $company_name, $company_address, $students_to_endorse);
            if ($stmt->execute()) {
                $flash_message = 'Endorsement autofill data saved.';
                $flash_type = 'success';
            } else {
                $flash_message = 'Failed to save Endorsement data.';
                $flash_type = 'danger';
            }
            $view_user_id = $posted_user_id;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dau_moa'])) {
        $active_tab = trim((string)($_POST['active_tab'] ?? 'commentTab'));
        $posted_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if ($posted_user_id > 0) {
            $company_name = trim($_POST['company_name'] ?? '');
            $company_address = trim($_POST['company_address'] ?? '');
            $partner_representative = trim($_POST['partner_representative'] ?? '');
            $position = trim($_POST['position'] ?? '');
            $company_receipt = trim($_POST['company_receipt'] ?? '');
            $total_hours = trim($_POST['total_hours'] ?? '');
            $school_representative = trim($_POST['school_representative'] ?? '');
            $school_position = trim($_POST['school_position'] ?? '');
            $signed_at = trim($_POST['signed_at'] ?? '');
            $signed_day = trim($_POST['signed_day'] ?? '');
            $signed_month = trim($_POST['signed_month'] ?? '');
            $signed_year = trim($_POST['signed_year'] ?? '');
            $witness_partner = trim($_POST['witness_partner'] ?? '');
            $school_administrator = trim($_POST['school_administrator'] ?? '');
            $school_admin_position = trim($_POST['school_admin_position'] ?? '');
            $notary_city = trim($_POST['notary_city'] ?? '');
            $notary_day = trim($_POST['notary_day'] ?? '');
            $notary_month = trim($_POST['notary_month'] ?? '');
            $notary_year = trim($_POST['notary_year'] ?? '');
            $notary_place = trim($_POST['notary_place'] ?? '');
            $doc_no = trim($_POST['doc_no'] ?? '');
            $page_no = trim($_POST['page_no'] ?? '');
            $book_no = trim($_POST['book_no'] ?? '');
            $series_no = trim($_POST['series_no'] ?? '');

            $stmt = $conn->prepare("INSERT INTO dau_moa (
                    user_id, company_name, company_address, partner_representative, position, company_receipt, total_hours,
                    school_representative, school_position, signed_at, signed_day, signed_month, signed_year,
                    witness_partner, school_administrator, school_admin_position, notary_city, notary_day, notary_month, notary_year, notary_place,
                    doc_no, page_no, book_no, series_no
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?
                )
                ON DUPLICATE KEY UPDATE
                    company_name = VALUES(company_name),
                    company_address = VALUES(company_address),
                    partner_representative = VALUES(partner_representative),
                    position = VALUES(position),
                    company_receipt = VALUES(company_receipt),
                    total_hours = VALUES(total_hours),
                    school_representative = VALUES(school_representative),
                    school_position = VALUES(school_position),
                    signed_at = VALUES(signed_at),
                    signed_day = VALUES(signed_day),
                    signed_month = VALUES(signed_month),
                    signed_year = VALUES(signed_year),
                    witness_partner = VALUES(witness_partner),
                    school_administrator = VALUES(school_administrator),
                    school_admin_position = VALUES(school_admin_position),
                    notary_city = VALUES(notary_city),
                    notary_day = VALUES(notary_day),
                    notary_month = VALUES(notary_month),
                    notary_year = VALUES(notary_year),
                    notary_place = VALUES(notary_place),
                    doc_no = VALUES(doc_no),
                    page_no = VALUES(page_no),
                    book_no = VALUES(book_no),
                    series_no = VALUES(series_no),
                    updated_at = NOW()");
            $types = 'i' . str_repeat('s', 24);
            $stmt->bind_param(
                $types,
                $posted_user_id, $company_name, $company_address, $partner_representative, $position, $company_receipt, $total_hours,
                $school_representative, $school_position, $signed_at, $signed_day, $signed_month, $signed_year,
                $witness_partner, $school_administrator, $school_admin_position, $notary_city, $notary_day, $notary_month, $notary_year, $notary_place,
                $doc_no, $page_no, $book_no, $series_no
            );
            if ($stmt->execute()) {
                $flash_message = 'Dau MOA autofill data saved.';
                $flash_type = 'success';
            } else {
                $flash_message = 'Failed to save Dau MOA data.';
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
        if (!$student) {
            header('Location: idnotfound-404.php?source=ojt-view&id=' . urlencode($view_user_id));
            exit;
        }
        $selected_student_id = intval($student['id'] ?? $view_user_id);
        $selected_user_id = intval($student['user_id'] ?? 0);

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
            $document_completion['application'] = true;
            $document_last_saved['application'] = (string)($row['updated_at'] ?? ($row['date'] ?? ''));
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
            $document_completion['moa'] = true;
            $document_last_saved['moa'] = (string)($moa_row['updated_at'] ?? ($moa_row['moa_date'] ?? ''));
        }

        $endorsement_row = null;
        foreach ($doc_lookup_ids as $lookup_id) {
            $stmt_endorse = $conn->prepare("SELECT * FROM endorsement_letter WHERE user_id = ? LIMIT 1");
            $stmt_endorse->bind_param('i', $lookup_id);
            $stmt_endorse->execute();
            $res_endorse = $stmt_endorse->get_result();
            $endorsement_row = $res_endorse ? $res_endorse->fetch_assoc() : null;
            if ($endorsement_row) break;
        }
        if ($endorsement_row) {
            foreach ($endorsement_data as $k => $v) {
                $endorsement_data[$k] = isset($endorsement_row[$k]) ? (string)$endorsement_row[$k] : '';
            }
            $document_completion['endorsement'] = true;
            $document_last_saved['endorsement'] = (string)($endorsement_row['updated_at'] ?? '');
        }

        $dau_row = null;
        foreach ($doc_lookup_ids as $lookup_id) {
            $stmt_dau = $conn->prepare("SELECT * FROM dau_moa WHERE user_id = ? LIMIT 1");
            $stmt_dau->bind_param('i', $lookup_id);
            $stmt_dau->execute();
            $res_dau = $stmt_dau->get_result();
            $dau_row = $res_dau ? $res_dau->fetch_assoc() : null;
            if ($dau_row) break;
        }
        if ($dau_row) {
            foreach ($dau_moa_data as $k => $v) {
                $dau_moa_data[$k] = isset($dau_row[$k]) ? (string)$dau_row[$k] : '';
            }
            $document_completion['dau_moa'] = true;
            $document_last_saved['dau_moa'] = (string)($dau_row['updated_at'] ?? '');
        } else {
            $dau_moa_data['company_name'] = $moa_data['company_name'];
            $dau_moa_data['company_address'] = $moa_data['company_address'];
            $dau_moa_data['partner_representative'] = $moa_data['partner_representative'];
            $dau_moa_data['position'] = $moa_data['position'];
            $dau_moa_data['company_receipt'] = $moa_data['company_receipt'];
            $dau_moa_data['total_hours'] = $moa_data['total_hours'];
            $dau_moa_data['school_position'] = $moa_data['school_position'];
            $dau_moa_data['school_administrator'] = $moa_data['school_administrator'];
            $dau_moa_data['school_admin_position'] = $moa_data['school_admin_position'];
            $dau_moa_data['doc_no'] = $moa_data['doc_no'];
            $dau_moa_data['page_no'] = $moa_data['page_no'];
            $dau_moa_data['book_no'] = $moa_data['book_no'];
            $dau_moa_data['series_no'] = $moa_data['series_no'];
            $dau_moa_data['signed_at'] = $moa_data['moa_address'];
            $dau_moa_data['witness_partner'] = $moa_data['witness'];
        }

        if (ojt_table_exists($conn, 'internships')) {
            $stmt_i = $conn->prepare("SELECT * FROM internships WHERE student_id = ? ORDER BY id DESC LIMIT 1");
            $stmt_i->bind_param('i', $selected_student_id);
            $stmt_i->execute();
            $internship_data = $stmt_i->get_result()->fetch_assoc();
            $stmt_i->close();
        }

        if (ojt_table_exists($conn, 'attendances')) {
            $stmt_a = $conn->prepare("
                SELECT
                    MAX(attendance_date) AS last_attendance_date,
                    SUM(CASE WHEN status = 'pending' OR status IS NULL THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN status <> 'rejected' OR status IS NULL THEN COALESCE(total_hours, 0) ELSE 0 END) AS total_hours
                FROM attendances
                WHERE student_id = ?
            ");
            $stmt_a->bind_param('i', $selected_student_id);
            $stmt_a->execute();
            $att_row = $stmt_a->get_result()->fetch_assoc();
            $stmt_a->close();
            if ($att_row) {
                $attendance_summary['last_attendance_date'] = (string)($att_row['last_attendance_date'] ?? '');
                $attendance_summary['pending_count'] = (int)($att_row['pending_count'] ?? 0);
                $attendance_summary['total_hours'] = (float)($att_row['total_hours'] ?? 0);
            }
        }

        $intern_status = strtolower((string)($internship_data['status'] ?? ''));
        $required_hours = (float)($internship_data['required_hours'] ?? 0);
        $rendered_hours = (float)($internship_data['rendered_hours'] ?? 0);
        if ($rendered_hours <= 0) {
            $rendered_hours = (float)$attendance_summary['total_hours'];
        }
        $progress_pct = $required_hours > 0 ? min(100, round(($rendered_hours / $required_hours) * 100, 1)) : 0;
        if ($intern_status === 'completed' || $progress_pct >= 100) {
            $pipeline_stage = 'Completed';
        } elseif ($intern_status === 'ongoing') {
            $pipeline_stage = 'Ongoing';
        } elseif (!empty($document_completion['moa'])) {
            $pipeline_stage = 'Accepted';
        } elseif (!empty($document_completion['endorsement'])) {
            $pipeline_stage = 'Endorsed';
        } else {
            $pipeline_stage = 'Applied';
        }
        if ($intern_status === 'dropped') {
            $pipeline_stage = 'Dropped';
        }

        if (!$document_completion['moa']) $risk_flags[] = 'Missing MOA';
        if (!$document_completion['endorsement']) $risk_flags[] = 'Missing Endorsement';
        if ($attendance_summary['pending_count'] > 0) $risk_flags[] = 'Pending attendance approvals';
        if ($intern_status === 'ongoing' && !empty($attendance_summary['last_attendance_date'])) {
            $days_since = (int)floor((time() - strtotime($attendance_summary['last_attendance_date'])) / 86400);
            if ($days_since >= 3) $risk_flags[] = 'No biometric logs for 3+ days';
        }
        if ($intern_status === 'ongoing' && $required_hours > 0 && $progress_pct < 50) {
            $risk_flags[] = 'Low internship completion progress';
        }

        $profile_timeline[] = ['label' => 'Student profile created', 'at' => (string)($student['created_at'] ?? '')];
        if (!empty($document_last_saved['application'])) $profile_timeline[] = ['label' => 'Application Letter saved', 'at' => $document_last_saved['application']];
        if (!empty($document_last_saved['endorsement'])) $profile_timeline[] = ['label' => 'Endorsement Letter saved', 'at' => $document_last_saved['endorsement']];
        if (!empty($document_last_saved['moa'])) $profile_timeline[] = ['label' => 'MOA saved', 'at' => $document_last_saved['moa']];
        if (!empty($document_last_saved['dau_moa'])) $profile_timeline[] = ['label' => 'Dau MOA saved', 'at' => $document_last_saved['dau_moa']];
        if (!empty($attendance_summary['last_attendance_date'])) $profile_timeline[] = ['label' => 'Latest biometric attendance', 'at' => $attendance_summary['last_attendance_date']];
        if (!empty($internship_data['start_date'])) $profile_timeline[] = ['label' => 'Internship started', 'at' => (string)$internship_data['start_date']];
        if (!empty($internship_data['end_date'])) $profile_timeline[] = ['label' => 'Internship end target', 'at' => (string)$internship_data['end_date']];
        usort($profile_timeline, function($a, $b) {
            return strtotime((string)$b['at']) <=> strtotime((string)$a['at']);
        });

        if (ojt_table_exists($conn, 'ojt_edit_audit')) {
            $stmt_audit = $conn->prepare("SELECT created_at, reason, changes_text FROM ojt_edit_audit WHERE student_id = ? ORDER BY created_at DESC LIMIT 6");
            $stmt_audit->bind_param('i', $selected_student_id);
            $stmt_audit->execute();
            $res_audit = $stmt_audit->get_result();
            while ($ar = $res_audit->fetch_assoc()) {
                $audit_trail[] = [
                    'type' => 'OJT Edit',
                    'created_at' => (string)($ar['created_at'] ?? ''),
                    'details' => trim((string)($ar['reason'] ?? ''))
                ];
            }
            $stmt_audit->close();
        }
        usort($audit_trail, function($a, $b) {
            return strtotime((string)$b['created_at']) <=> strtotime((string)$a['created_at']);
        });
        $audit_trail = array_slice($audit_trail, 0, 8);

        if (ojt_table_exists($conn, 'document_workflow')) {
            $stmt_wf = $conn->prepare("SELECT doc_type, status, review_notes, approved_by, approved_at FROM document_workflow WHERE user_id = ?");
            $stmt_wf->bind_param('i', $selected_student_id);
            $stmt_wf->execute();
            $res_wf = $stmt_wf->get_result();
            while ($wf = $res_wf->fetch_assoc()) {
                $dt = (string)($wf['doc_type'] ?? '');
                if (isset($workflow[$dt])) {
                    $workflow[$dt] = [
                        'status' => (string)($wf['status'] ?? 'draft'),
                        'review_notes' => (string)($wf['review_notes'] ?? ''),
                        'approved_by' => intval($wf['approved_by'] ?? 0),
                        'approved_at' => (string)($wf['approved_at'] ?? ''),
                    ];
                }
            }
            $stmt_wf->close();
            foreach (['application', 'endorsement', 'moa', 'dau_moa'] as $wf_key) {
                if (($workflow[$wf_key]['status'] ?? '') === 'rejected') {
                    $risk_flags[] = strtoupper($wf_key) . ' workflow rejected';
                }
                if (($workflow[$wf_key]['status'] ?? '') === 'for_review') {
                    $risk_flags[] = strtoupper($wf_key) . ' awaiting approval';
                }
            }
        }

        if (ojt_table_exists($conn, 'attendances')) {
            $stmt_att_audit = $conn->prepare("
                SELECT attendance_date, morning_time_in, morning_time_out, break_time_in, break_time_out, afternoon_time_in, afternoon_time_out, status, created_at, updated_at
                FROM attendances
                WHERE student_id = ?
                ORDER BY attendance_date DESC, updated_at DESC
                LIMIT 10
            ");
            $stmt_att_audit->bind_param('i', $selected_student_id);
            $stmt_att_audit->execute();
            $res_att_audit = $stmt_att_audit->get_result();
            while ($ar = $res_att_audit->fetch_assoc()) {
                $source = 'manual';
                if (ojt_table_exists($conn, 'biometric_event_queue')) {
                    $stmt_src = $conn->prepare("SELECT event_source FROM biometric_event_queue WHERE student_id = ? AND attendance_date = ? ORDER BY id DESC LIMIT 1");
                    $stmt_src->bind_param('is', $selected_student_id, $ar['attendance_date']);
                    $stmt_src->execute();
                    $src_row = $stmt_src->get_result()->fetch_assoc();
                    $stmt_src->close();
                    if (!empty($src_row['event_source'])) {
                        $source = (string)$src_row['event_source'];
                    }
                }
                $ar['event_source'] = $source;
                $attendance_audit_rows[] = $ar;
            }
            $stmt_att_audit->close();
        }

        if (ojt_table_exists($conn, 'ojt_supervisor_reviews')) {
            $stmt_rv = $conn->prepare("SELECT reviewer_role, note, created_at FROM ojt_supervisor_reviews WHERE student_id = ? ORDER BY created_at DESC LIMIT 8");
            $stmt_rv->bind_param('i', $selected_student_id);
            $stmt_rv->execute();
            $res_rv = $stmt_rv->get_result();
            while ($rv = $res_rv->fetch_assoc()) {
                $review_notes[] = $rv;
            }
            $stmt_rv->close();
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
    <style>
        body { background: #f5f7fb; }
        .card { border: 1px solid #e8edf6; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04); }
        .page-header .m-b-10 { margin-bottom: 4px !important; }
        .nav-tabs-custom-style .nav-link { font-weight: 600; }
        .list-group-item { border-color: #edf1f7; }
        .form-label { font-weight: 600; font-size: 12px; letter-spacing: 0.2px; }
    </style>
    <!--! END: Custom CSS-->
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
			<script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
			<script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
		<![endif]-->
</head>

<body>
    <?php include_once 'includes/navigation.php'; ?>
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
                            <a href="ojt-edit.php?id=<?php echo intval($selected_student_id); ?>" class="btn btn-icon btn-light-brand">
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
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#commentTab">Dau MOA</button>
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
                            <hr>
                            <div class="card card-body mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0">Internship Monitoring Overview</h5>
                                <div class="d-flex gap-2">
                                    <a href="ojt-edit.php?id=<?php echo intval($selected_student_id); ?>" class="btn btn-sm btn-outline-primary">Controlled Edit</a>
                                    <a href="students-dtr.php?id=<?php echo intval($selected_student_id); ?>" class="btn btn-sm btn-outline-success">Attendance History</a>
                                    <a href="ojt-workflow-board.php" class="btn btn-sm btn-outline-info">Workflow Board</a>
                                </div>
                            </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <div class="p-3 border rounded">
                                            <div class="text-muted fs-12">Pipeline Stage</div>
                                            <div class="mt-2"><?php echo ojt_stage_badge($pipeline_stage); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 border rounded">
                                            <div class="text-muted fs-12">Required Hours</div>
                                            <div class="fs-5 fw-semibold"><?php echo htmlspecialchars((string)($internship_data['required_hours'] ?? 0)); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 border rounded">
                                            <div class="text-muted fs-12">Rendered Hours</div>
                                            <div class="fs-5 fw-semibold"><?php echo number_format((float)$attendance_summary['total_hours'], 2); ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="p-3 border rounded">
                                            <div class="text-muted fs-12">Pending Attendance</div>
                                            <div class="fs-5 fw-semibold"><?php echo intval($attendance_summary['pending_count']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <div class="p-3 border rounded">
                                            <h6 class="fw-bold">Document Completion</h6>
                                            <div class="d-flex flex-wrap gap-2">
                                                <span class="badge <?php echo $document_completion['application'] ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?>">Application <?php echo $document_completion['application'] ? 'Ready' : 'Missing'; ?></span>
                                                <span class="badge <?php echo $document_completion['endorsement'] ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?>">Endorsement <?php echo $document_completion['endorsement'] ? 'Ready' : 'Missing'; ?></span>
                                                <span class="badge <?php echo $document_completion['moa'] ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?>">MOA <?php echo $document_completion['moa'] ? 'Ready' : 'Missing'; ?></span>
                                                <span class="badge <?php echo $document_completion['dau_moa'] ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?>">Dau MOA <?php echo $document_completion['dau_moa'] ? 'Ready' : 'Missing'; ?></span>
                                            </div>
                                            <div class="mt-2 fs-12 text-muted">
                                                Last save: Application <?php echo htmlspecialchars(format_dt($document_last_saved['application'])); ?>,
                                                Endorsement <?php echo htmlspecialchars(format_dt($document_last_saved['endorsement'])); ?>,
                                                MOA <?php echo htmlspecialchars(format_dt($document_last_saved['moa'])); ?>,
                                                Dau MOA <?php echo htmlspecialchars(format_dt($document_last_saved['dau_moa'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-3 border rounded">
                                            <h6 class="fw-bold">Risk Flags</h6>
                                            <?php if (count($risk_flags) === 0): ?>
                                                <span class="badge bg-soft-success text-success">No active risk</span>
                                            <?php else: ?>
                                                <div class="d-flex flex-column gap-2">
                                                    <?php foreach ($risk_flags as $flag): ?>
                                                        <span class="badge bg-soft-warning text-warning text-start"><?php echo htmlspecialchars($flag); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3 mt-1">
                                    <?php foreach (['application' => 'Application', 'endorsement' => 'Endorsement', 'moa' => 'MOA', 'dau_moa' => 'Dau MOA'] as $doc_key => $doc_label): ?>
                                        <div class="col-md-3">
                                            <form method="post" action="ojt-view.php?id=<?php echo intval($selected_student_id); ?>" class="p-2 border rounded">
                                                <input type="hidden" name="save_document_workflow" value="1">
                                                <input type="hidden" name="user_id" value="<?php echo intval($selected_student_id); ?>">
                                                <input type="hidden" name="doc_type" value="<?php echo htmlspecialchars($doc_key); ?>">
                                                <input type="hidden" name="active_tab" value="profileTab">
                                                <div class="fw-semibold mb-2"><?php echo htmlspecialchars($doc_label); ?> Workflow</div>
                                                <select name="workflow_status" class="form-select form-select-sm mb-2" <?php echo $can_approve_docs ? '' : 'disabled'; ?>>
                                                    <?php foreach (['draft', 'for_review', 'approved', 'rejected'] as $wf_status): ?>
                                                        <option value="<?php echo $wf_status; ?>" <?php echo (($workflow[$doc_key]['status'] ?? 'draft') === $wf_status) ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $wf_status)); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <textarea name="workflow_note" rows="2" class="form-control form-control-sm mb-2" placeholder="Review note"><?php echo htmlspecialchars((string)($workflow[$doc_key]['review_notes'] ?? '')); ?></textarea>
                                                <div class="fs-12 text-muted mb-2">Approved at: <?php echo htmlspecialchars(format_dt($workflow[$doc_key]['approved_at'] ?? '')); ?></div>
                                                <button type="submit" class="btn btn-sm btn-outline-primary w-100" <?php echo $can_approve_docs ? '' : 'disabled'; ?>>Save Workflow</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="card card-body h-100">
                                        <h6 class="fw-bold mb-3">Student Timeline</h6>
                                        <?php if (!$profile_timeline): ?>
                                            <div class="text-muted">No timeline events.</div>
                                        <?php else: ?>
                                            <ul class="list-group">
                                                <?php foreach ($profile_timeline as $evt): ?>
                                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                                        <span><?php echo htmlspecialchars($evt['label']); ?></span>
                                                        <span class="text-muted fs-12"><?php echo htmlspecialchars(format_dt($evt['at'])); ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card card-body h-100">
                                        <h6 class="fw-bold mb-3">Edit Audit Trail</h6>
                                        <?php if (!$audit_trail): ?>
                                            <div class="text-muted">No OJT edit audit entries.</div>
                                        <?php else: ?>
                                            <ul class="list-group">
                                                <?php foreach ($audit_trail as $audit): ?>
                                                    <li class="list-group-item">
                                                        <div class="d-flex justify-content-between">
                                                            <strong><?php echo htmlspecialchars($audit['type']); ?></strong>
                                                            <span class="text-muted fs-12"><?php echo htmlspecialchars(format_dt($audit['created_at'])); ?></span>
                                                        </div>
                                                        <div class="text-muted fs-12"><?php echo htmlspecialchars($audit['details']); ?></div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-lg-6">
                                    <div class="card card-body h-100">
                                        <h6 class="fw-bold mb-3">Attendance Event Audit (Latest 10)</h6>
                                        <?php if (!$attendance_audit_rows): ?>
                                            <div class="text-muted">No attendance audit records.</div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Date</th>
                                                            <th>Source</th>
                                                            <th>Status</th>
                                                            <th>Updated</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($attendance_audit_rows as $ar): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars((string)$ar['attendance_date']); ?></td>
                                                                <td><?php echo htmlspecialchars((string)$ar['event_source']); ?></td>
                                                                <td><?php echo htmlspecialchars((string)($ar['status'] ?? 'pending')); ?></td>
                                                                <td><?php echo htmlspecialchars(format_dt((string)($ar['updated_at'] ?? ''))); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card card-body h-100">
                                        <h6 class="fw-bold mb-3">Supervisor Review Notes</h6>
                                        <form method="post" action="ojt-view.php?id=<?php echo intval($selected_student_id); ?>" class="mb-3">
                                            <input type="hidden" name="save_review_note" value="1">
                                            <input type="hidden" name="user_id" value="<?php echo intval($selected_student_id); ?>">
                                            <input type="hidden" name="active_tab" value="profileTab">
                                            <textarea name="review_note" class="form-control mb-2" rows="2" placeholder="Add monitoring notes and recommendations."></textarea>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Save Note</button>
                                        </form>
                                        <?php if (!$review_notes): ?>
                                            <div class="text-muted">No review notes yet.</div>
                                        <?php else: ?>
                                            <?php foreach ($review_notes as $rv): ?>
                                                <div class="border rounded p-2 mb-2">
                                                    <div class="d-flex justify-content-between">
                                                        <strong><?php echo htmlspecialchars((string)($rv['reviewer_role'] ?? 'reviewer')); ?></strong>
                                                        <span class="text-muted fs-12"><?php echo htmlspecialchars(format_dt((string)($rv['created_at'] ?? ''))); ?></span>
                                                    </div>
                                                    <div class="fs-12"><?php echo htmlspecialchars((string)($rv['note'] ?? '')); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
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
                                    <input type="hidden" name="active_tab" value="applicationTab">

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
                            <?php if ($flash_message !== ''): ?>
                                <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?> mb-3"><?php echo htmlspecialchars($flash_message); ?></div>
                            <?php endif; ?>
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
                                    <input type="hidden" name="active_tab" value="moaTab">

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
                            <?php if ($flash_message !== ''): ?>
                                <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?> mb-3"><?php echo htmlspecialchars($flash_message); ?></div>
                            <?php endif; ?>
                            <?php if ($view_user_id <= 0): ?>
                                <div class="alert alert-warning mb-0">Open this page from OJT List using a specific student row so document data can be linked by user ID.</div>
                            <?php else: ?>
                                <div class="mb-4">
                                    <h5 class="fw-bold mb-1">Endorsement Letter</h5>
                                    <p class="text-muted mb-0">Prepare and save endorsement details before opening the printable document.</p>
                                </div>
                                <form method="post" action="ojt-view.php?id=<?php echo intval($selected_student_id); ?>">
                                    <input type="hidden" name="save_endorsement_letter" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo intval($selected_student_id); ?>">
                                    <input type="hidden" name="active_tab" value="endorsementTab">

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Recipient Name</label>
                                            <input type="text" name="recipient_name" class="form-control" value="<?php echo htmlspecialchars($endorsement_data['recipient_name']); ?>" placeholder="e.g. Mr. Mark G. Sison">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Recipient Position</label>
                                            <input type="text" name="recipient_position" class="form-control" value="<?php echo htmlspecialchars($endorsement_data['recipient_position']); ?>" placeholder="e.g. Supervisor/Manager">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Company Name</label>
                                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($endorsement_data['company_name']); ?>" placeholder="Company name">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Company Address</label>
                                            <textarea name="company_address" class="form-control" rows="2" placeholder="Company address"><?php echo htmlspecialchars($endorsement_data['company_address']); ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Students to Endorse (one per line)</label>
                                            <textarea name="students_to_endorse" class="form-control" rows="3"><?php echo htmlspecialchars($endorsement_data['students_to_endorse']); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="mt-3 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Save Endorsement Data</button>
                                        <a href="document_endorsement.php?id=<?php echo intval($selected_student_id); ?>" class="btn btn-success">Open Endorsement Letter</a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="commentTab" role="tabpanel">
                        <div class="card card-body">
                            <?php if ($flash_message !== ''): ?>
                                <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?> mb-3"><?php echo htmlspecialchars($flash_message); ?></div>
                            <?php endif; ?>
                            <?php if ($view_user_id <= 0): ?>
                                <div class="alert alert-warning mb-0">Open this page from OJT List using a specific student row so document data can be linked by user ID.</div>
                            <?php else: ?>
                                <div class="mb-4">
                                    <h5 class="fw-bold mb-1">Dau MOA</h5>
                                    <p class="text-muted mb-0">Fill, save, and review the Barangay DAU MOA details before final printing.</p>
                                </div>
                                <form method="post" action="ojt-view.php?id=<?php echo intval($selected_student_id); ?>">
                                    <input type="hidden" name="save_dau_moa" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo intval($selected_student_id); ?>">
                                    <input type="hidden" name="active_tab" value="commentTab">

                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Barangay Name</label>
                                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['company_name']); ?>" placeholder="Barangay name">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Barangay Address</label>
                                            <input type="text" name="company_address" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['company_address']); ?>" placeholder="Barangay address">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Barangay Representative</label>
                                            <input type="text" name="partner_representative" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['partner_representative']); ?>" placeholder="Barangay representative">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Barangay Representative Position</label>
                                            <input type="text" name="position" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['position']); ?>" placeholder="Position">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Barangay Receipt / Ref.</label>
                                            <input type="text" name="company_receipt" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['company_receipt']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Total Hours (Clause #10)</label>
                                            <input type="number" name="total_hours" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['total_hours']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">School Representative</label>
                                            <input type="text" name="school_representative" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['school_representative']); ?>" placeholder="e.g. Mr. Jomar G. Sangil">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">School Representative Position</label>
                                            <input type="text" name="school_position" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['school_position']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Signing Place</label>
                                            <input type="text" name="signed_at" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['signed_at']); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Sign Day</label>
                                            <input type="text" name="signed_day" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['signed_day']); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Sign Month</label>
                                            <input type="text" name="signed_month" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['signed_month']); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Sign Year</label>
                                            <input type="text" name="signed_year" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['signed_year']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Witness (Barangay)</label>
                                            <input type="text" name="witness_partner" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['witness_partner']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">School Administrator</label>
                                            <input type="text" name="school_administrator" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['school_administrator']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">School Admin Position</label>
                                            <input type="text" name="school_admin_position" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['school_admin_position']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Notary City</label>
                                            <input type="text" name="notary_city" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['notary_city']); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Ack Day</label>
                                            <input type="text" name="notary_day" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['notary_day']); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Ack Month</label>
                                            <input type="text" name="notary_month" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['notary_month']); ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Ack Year</label>
                                            <input type="text" name="notary_year" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['notary_year']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Ack Place</label>
                                            <input type="text" name="notary_place" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['notary_place']); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Doc No.</label>
                                            <input type="text" name="doc_no" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['doc_no']); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Page No.</label>
                                            <input type="text" name="page_no" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['page_no']); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Book No.</label>
                                            <input type="text" name="book_no" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['book_no']); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Series</label>
                                            <input type="text" name="series_no" class="form-control" value="<?php echo htmlspecialchars($dau_moa_data['series_no']); ?>">
                                        </div>
                                    </div>
                                    <div class="mt-3 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">Save Dau MOA Data</button>
                                        <a href="document_dau_moa.php?id=<?php echo intval($selected_student_id); ?>" class="btn btn-success">Open Dau MOA</a>
                                    </div>
                                </form>
                            <?php endif; ?>
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
            <p class="footer-meta fs-12 mb-0"><span>By: <a href="javascript:void(0);">ACT 2A</a></span> <span>Distributed by: <a href="javascript:void(0);">Group 5</a></span></p>
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
    <script>
        (function () {
            var preferredTabId = <?php echo json_encode($active_tab); ?>;
            if (window.location.hash && document.querySelector('button[data-bs-target="' + window.location.hash + '"]')) {
                preferredTabId = window.location.hash.replace('#', '');
            }
            var targetBtn = document.querySelector('button[data-bs-target="#' + preferredTabId + '"]');
            if (targetBtn && window.bootstrap && bootstrap.Tab) {
                var tab = new bootstrap.Tab(targetBtn);
                tab.show();
            }
            document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(function (btn) {
                btn.addEventListener('shown.bs.tab', function (e) {
                    var selector = e.target.getAttribute('data-bs-target');
                    if (selector) {
                        history.replaceState(null, '', selector);
                    }
                });
            });
        })();
    </script>
    <script>
        (function () {
            var root = document.documentElement;
            var darkBtn = document.querySelector('.dark-button');
            var lightBtn = document.querySelector('.light-button');
            function applyTheme(isDark) {
                root.classList.toggle('app-skin-dark', isDark);
                try {
                    localStorage.setItem('app-skin', isDark ? 'app-skin-dark' : 'app-skin-light');
                    localStorage.setItem('app_skin', isDark ? 'app-skin-dark' : 'app-skin-light');
                    localStorage.setItem('theme', isDark ? 'dark' : 'light');
                    if (isDark) localStorage.setItem('app-skin-dark', 'app-skin-dark');
                    else localStorage.removeItem('app-skin-dark');
                } catch (e) {}
                if (darkBtn && lightBtn) {
                    darkBtn.style.display = isDark ? 'none' : '';
                    lightBtn.style.display = isDark ? '' : 'none';
                }
            }
            var isDark = root.classList.contains('app-skin-dark');
            applyTheme(isDark);
            if (darkBtn) darkBtn.addEventListener('click', function (e) { e.preventDefault(); applyTheme(true); });
            if (lightBtn) lightBtn.addEventListener('click', function (e) { e.preventDefault(); applyTheme(false); });
        })();
    </script>
    <!-- Theme Customizer removed -->
</body>

</html>


