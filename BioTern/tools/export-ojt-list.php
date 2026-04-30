<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/section_format.php';

biotern_boot_session(isset($conn) ? $conn : null);

$role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if (!in_array($role, ['admin', 'coordinator', 'supervisor'], true)) {
    http_response_code(403);
    echo 'Forbidden.';
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    $conn = new mysqli(
        defined('DB_HOST') ? DB_HOST : 'localhost',
        defined('DB_USER') ? DB_USER : 'root',
        defined('DB_PASS') ? DB_PASS : '',
        defined('DB_NAME') ? DB_NAME : 'biotern_db',
        defined('DB_PORT') ? (int)DB_PORT : 3306
    );
    if ($conn->connect_error) {
        http_response_code(500);
        echo 'Connection failed.';
        exit;
    }
    $conn->set_charset('utf8mb4');
}

function export_ojt_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    return $exists;
}

function export_ojt_column_exists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $exists = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    return $exists;
}

function export_ojt_bind(mysqli_stmt $stmt, string $types, array &$values): void
{
    if ($types === '') {
        return;
    }
    $bind = [$types];
    foreach ($values as &$value) {
        $bind[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

$type = strtolower(trim((string)($_GET['type'] ?? 'external')));
if (!in_array($type, ['internal', 'external'], true)) {
    $type = 'external';
}

$schoolYear = trim((string)($_GET['school_year'] ?? ''));
$semester = trim((string)($_GET['semester'] ?? ''));
$courseId = (int)($_GET['course_id'] ?? 0);
$sectionId = (int)($_GET['section_id'] ?? 0);
$search = trim((string)($_GET['search'] ?? ''));
$status = strtolower(trim((string)($_GET['ojt_status'] ?? 'all')));

$hasMasterlist = export_ojt_table_exists($conn, 'ojt_masterlist');
if ($hasMasterlist && !export_ojt_column_exists($conn, 'ojt_masterlist', 'student_no')) {
    $conn->query("ALTER TABLE ojt_masterlist ADD COLUMN student_no VARCHAR(100) DEFAULT NULL AFTER semester");
}
$hasMasterlist = $hasMasterlist && export_ojt_column_exists($conn, 'ojt_masterlist', 'student_no');
$hasInternships = export_ojt_table_exists($conn, 'internships');

$masterJoin = '';
$masterSelect = "
    '' AS master_school_year,
    '' AS master_student_name,
    '' AS master_contact_no,
    '' AS master_section,
    '' AS master_company_name,
    '' AS master_company_address,
    '' AS master_supervisor_name,
    '' AS master_supervisor_position,
    '' AS master_company_representative,
    '' AS master_status
";
if ($hasMasterlist) {
    $masterSelect = "
        COALESCE(ml.school_year, '') AS master_school_year,
        COALESCE(ml.student_name, '') AS master_student_name,
        COALESCE(ml.contact_no, '') AS master_contact_no,
        COALESCE(ml.section, '') AS master_section,
        COALESCE(ml.company_name, '') AS master_company_name,
        COALESCE(ml.company_address, '') AS master_company_address,
        COALESCE(ml.supervisor_name, '') AS master_supervisor_name,
        COALESCE(ml.supervisor_position, '') AS master_supervisor_position,
        COALESCE(ml.company_representative, '') AS master_company_representative,
        COALESCE(ml.status, '') AS master_status
    ";
    $masterJoin = "
        LEFT JOIN ojt_masterlist ml ON TRIM(COALESCE(ml.student_no, '')) COLLATE utf8mb4_unicode_ci = TRIM(COALESCE(s.student_id, '')) COLLATE utf8mb4_unicode_ci
    ";
}

$internSelect = "
    '' AS internship_school_year,
    '' AS internship_company_name,
    '' AS internship_company_address,
    '' AS internship_supervisor_name,
    '' AS internship_position,
    '' AS internship_status
";
$internJoin = '';
if ($hasInternships) {
    $internHasSchoolYear = export_ojt_column_exists($conn, 'internships', 'school_year');
    $internHasCompanyName = export_ojt_column_exists($conn, 'internships', 'company_name');
    $internHasCompanyAddress = export_ojt_column_exists($conn, 'internships', 'company_address');
    $internHasSupervisorName = export_ojt_column_exists($conn, 'internships', 'supervisor_name');
    $internHasPosition = export_ojt_column_exists($conn, 'internships', 'position');
    $internHasStatus = export_ojt_column_exists($conn, 'internships', 'status');
    $internSchoolYearExpr = $internHasSchoolYear ? "COALESCE(i.school_year, '')" : "''";
    $internCompanyNameExpr = $internHasCompanyName ? "COALESCE(i.company_name, '')" : "''";
    $internCompanyAddressExpr = $internHasCompanyAddress ? "COALESCE(i.company_address, '')" : "''";
    $internSupervisorNameExpr = $internHasSupervisorName ? "COALESCE(i.supervisor_name, '')" : "''";
    $internPositionExpr = $internHasPosition ? "COALESCE(i.position, '')" : "''";
    $internStatusExpr = $internHasStatus ? "COALESCE(i.status, '')" : "''";
    $internSelect = "
        {$internSchoolYearExpr} AS internship_school_year,
        {$internCompanyNameExpr} AS internship_company_name,
        {$internCompanyAddressExpr} AS internship_company_address,
        {$internSupervisorNameExpr} AS internship_supervisor_name,
        {$internPositionExpr} AS internship_position,
        {$internStatusExpr} AS internship_status
    ";
    $typeClause = export_ojt_column_exists($conn, 'internships', 'type') ? "WHERE LOWER(COALESCE(i2.type, '{$type}')) = '{$type}'" : '';
    $internJoin = "
        LEFT JOIN (
            SELECT i2.*
            FROM internships i2
            INNER JOIN (
                SELECT student_id, MAX(id) AS latest_id
                FROM internships
                GROUP BY student_id
            ) latest_i ON latest_i.latest_id = i2.id
            {$typeClause}
        ) i ON i.student_id = s.id
    ";
}

$where = ["LOWER(TRIM(COALESCE(s.assignment_track, 'internal'))) = ?"];
$types = 's';
$params = [$type];

if ($courseId > 0) {
    $where[] = 's.course_id = ?';
    $types .= 'i';
    $params[] = $courseId;
}
if ($sectionId > 0) {
    $where[] = 's.section_id = ?';
    $types .= 'i';
    $params[] = $sectionId;
}
if ($schoolYear !== '') {
    $parts = ["TRIM(COALESCE(s.school_year, '')) = ?"];
    $types .= 's';
    $params[] = $schoolYear;
    if ($hasMasterlist) {
        $parts[] = "TRIM(COALESCE(ml.school_year, '')) = ?";
        $types .= 's';
        $params[] = $schoolYear;
    }
    if ($hasInternships && export_ojt_column_exists($conn, 'internships', 'school_year')) {
        $parts[] = "TRIM(COALESCE(i.school_year, '')) = ?";
        $types .= 's';
        $params[] = $schoolYear;
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
if ($semester !== '') {
    $parts = ["TRIM(COALESCE(s.semester, '')) = ?"];
    $types .= 's';
    $params[] = $semester;
    if ($hasMasterlist) {
        $parts[] = "TRIM(COALESCE(ml.semester, '')) = ?";
        $types .= 's';
        $params[] = $semester;
    }
    if ($hasInternships && export_ojt_column_exists($conn, 'internships', 'semester')) {
        $parts[] = "TRIM(COALESCE(i.semester, '')) = ?";
        $types .= 's';
        $params[] = $semester;
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}
if ($status !== '' && $status !== 'all') {
    $parts = [];
    if ($hasInternships && export_ojt_column_exists($conn, 'internships', 'status')) {
        $parts[] = "LOWER(COALESCE(i.status, '')) = ?";
        $types .= 's';
        $params[] = $status;
    }
    if ($hasMasterlist) {
        $parts[] = "LOWER(COALESCE(ml.status, '')) = ?";
        $types .= 's';
        $params[] = $status;
    }
    if ($parts !== []) {
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
}
if ($search !== '') {
    $needle = '%' . $search . '%';
    $parts = ['s.student_id LIKE ?', 's.first_name LIKE ?', 's.last_name LIKE ?', 's.email LIKE ?'];
    $types .= 'ssss';
    array_push($params, $needle, $needle, $needle, $needle);
    if ($hasMasterlist) {
        $parts[] = 'ml.student_name LIKE ?';
        $parts[] = 'ml.company_name LIKE ?';
        $types .= 'ss';
        array_push($params, $needle, $needle);
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}

$sql = "
    SELECT
        COALESCE(s.student_id, '') AS student_no,
        COALESCE(s.school_year, '') AS student_school_year,
        COALESCE(s.first_name, '') AS first_name,
        COALESCE(s.middle_name, '') AS middle_name,
        COALESCE(s.last_name, '') AS last_name,
        COALESCE(s.phone, '') AS phone,
        COALESCE(s.status, '') AS student_status,
        COALESCE(NULLIF(sec.code, ''), sec.name, '') AS registered_section,
        {$masterSelect},
        {$internSelect}
    FROM students s
    LEFT JOIN sections sec ON sec.id = s.section_id
    {$masterJoin}
    {$internJoin}
    WHERE " . implode(' AND ', $where) . "
    ORDER BY s.last_name ASC, s.first_name ASC, s.student_id ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo 'Failed to prepare export.';
    exit;
}
export_ojt_bind($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();

$filenameParts = [$type, 'students'];
if ($schoolYear !== '') {
    $filenameParts[] = preg_replace('/[^0-9A-Za-z-]+/', '-', $schoolYear);
}
$filenameParts[] = date('Ymd-His');
$filename = implode('-', $filenameParts) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');

$out = fopen('php://output', 'wb');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [
    'student_no',
    'school_year',
    'student_name',
    'contact_no',
    'section',
    'company_name',
    'company_address',
    'supervisor_name',
    'supervisor_position',
    'company_representative',
    'status',
]);

while ($row = $res->fetch_assoc()) {
    $registeredName = trim((string)($row['last_name'] ?? '') . ', ' . (string)($row['first_name'] ?? '') . ' ' . (string)($row['middle_name'] ?? ''));
    $schoolYearOut = trim((string)($row['master_school_year'] ?? '')) !== ''
        ? (string)$row['master_school_year']
        : (trim((string)($row['student_school_year'] ?? '')) !== '' ? (string)$row['student_school_year'] : (string)($row['internship_school_year'] ?? ''));
    fputcsv($out, [
        (string)($row['student_no'] ?? ''),
        $schoolYearOut,
        trim((string)($row['master_student_name'] ?? '')) !== '' ? (string)$row['master_student_name'] : $registeredName,
        trim((string)($row['master_contact_no'] ?? '')) !== '' ? (string)$row['master_contact_no'] : (string)($row['phone'] ?? ''),
        trim((string)($row['master_section'] ?? '')) !== '' ? (string)$row['master_section'] : biotern_format_section_code((string)($row['registered_section'] ?? '')),
        trim((string)($row['master_company_name'] ?? '')) !== '' ? (string)$row['master_company_name'] : (string)($row['internship_company_name'] ?? ''),
        trim((string)($row['master_company_address'] ?? '')) !== '' ? (string)$row['master_company_address'] : (string)($row['internship_company_address'] ?? ''),
        trim((string)($row['master_supervisor_name'] ?? '')) !== '' ? (string)$row['master_supervisor_name'] : (string)($row['internship_supervisor_name'] ?? ''),
        trim((string)($row['master_supervisor_position'] ?? '')) !== '' ? (string)$row['master_supervisor_position'] : (string)($row['internship_position'] ?? ''),
        (string)($row['master_company_representative'] ?? ''),
        trim((string)($row['master_status'] ?? '')) !== '' ? (string)$row['master_status'] : (trim((string)($row['internship_status'] ?? '')) !== '' ? (string)$row['internship_status'] : (string)($row['student_status'] ?? '')),
    ]);
}

fclose($out);
$stmt->close();
exit;
