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

function export_ojt_xml_text(string $value): string
{
    $clean = preg_replace('/[^\P{C}\t\r\n]/u', '', $value);
    return htmlspecialchars($clean !== null ? $clean : $value, ENT_XML1 | ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
}

function export_ojt_column_name(int $index): string
{
    $name = '';
    $index++;
    while ($index > 0) {
        $remainder = ($index - 1) % 26;
        $name = chr(65 + $remainder) . $name;
        $index = intdiv($index - 1, 26);
    }
    return $name;
}

function export_ojt_xlsx_row(int $rowNumber, array $values, int $style = 0): string
{
    $cells = '';
    foreach ($values as $index => $value) {
        $cellRef = export_ojt_column_name($index) . $rowNumber;
        $styleAttr = $style > 0 ? ' s="' . $style . '"' : '';
        $cells .= '<c r="' . $cellRef . '" t="inlineStr"' . $styleAttr . '><is><t>' . export_ojt_xml_text((string)$value) . '</t></is></c>';
    }
    return '<row r="' . $rowNumber . '">' . $cells . '</row>';
}

function export_ojt_xlsx_sheet_xml(array $headers, array $rows): string
{
    $lastRow = max(1, count($rows) + 1);
    $lastColumn = export_ojt_column_name(max(0, count($headers) - 1));
    $dimension = 'A1:' . $lastColumn . $lastRow;
    $sheetRows = export_ojt_xlsx_row(1, $headers, 1);
    foreach ($rows as $index => $row) {
        $sheetRows .= export_ojt_xlsx_row($index + 2, $row);
    }

    $widths = [18, 22, 22, 22, 34, 14, 14, 18, 28, 38, 16];
    $cols = '';
    foreach ($headers as $index => $_header) {
        $columnNumber = $index + 1;
        $width = $widths[$index] ?? 18;
        $cols .= '<col min="' . $columnNumber . '" max="' . $columnNumber . '" width="' . $width . '" customWidth="1"/>';
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="16.5"/>'
        . '<cols>' . $cols . '</cols>'
        . '<sheetData>' . $sheetRows . '</sheetData>'
        . '<autoFilter ref="' . $dimension . '"/>'
        . '</worksheet>';
}

function export_ojt_send_template_xlsx(string $type, array $headers, array $rows, string $filename): void
{
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo 'Unable to create Excel export: ZipArchive is not available on this PHP setup.';
        exit;
    }

    $templateName = $type === 'internal' ? 'Internal Students Template.xlsx' : 'External Students Template.xlsx';
    $templatePath = dirname(__DIR__) . '/assets/' . $templateName;
    if (!is_file($templatePath)) {
        http_response_code(500);
        echo 'Excel template not found.';
        exit;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'biotern-ojt-export-');
    if ($tmpPath === false || !copy($templatePath, $tmpPath)) {
        http_response_code(500);
        echo 'Unable to prepare Excel export.';
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpPath) !== true) {
        @unlink($tmpPath);
        http_response_code(500);
        echo 'Unable to open Excel template.';
        exit;
    }

    $zip->addFromString('xl/worksheets/sheet1.xml', export_ojt_xlsx_sheet_xml($headers, $rows));
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string)filesize($tmpPath));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($tmpPath);
    @unlink($tmpPath);
    exit;
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
if ($hasMasterlist && !export_ojt_column_exists($conn, 'ojt_masterlist', 'assignment_track')) {
    $conn->query("ALTER TABLE ojt_masterlist ADD COLUMN assignment_track VARCHAR(30) NOT NULL DEFAULT 'external' AFTER section");
    $conn->query("UPDATE ojt_masterlist SET assignment_track = 'external' WHERE TRIM(COALESCE(assignment_track, '')) = ''");
}
$hasMasterlist = $hasMasterlist && export_ojt_column_exists($conn, 'ojt_masterlist', 'student_no');
$hasInternships = export_ojt_table_exists($conn, 'internships');
$hasCompanyProfiles = export_ojt_table_exists($conn, 'ojt_partner_companies');

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
    '' AS master_company_representative_position,
    '' AS master_status
";
if ($hasMasterlist) {
    $masterRepresentativePositionExpr = export_ojt_column_exists($conn, 'ojt_masterlist', 'company_representative_position')
        ? "COALESCE(ml.company_representative_position, '')"
        : "''";
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
        {$masterRepresentativePositionExpr} AS master_company_representative_position,
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
    $latestWhere = ['deleted_at IS NULL'];
    if (export_ojt_column_exists($conn, 'internships', 'type')) {
        $latestWhere[] = "LOWER(COALESCE(type, '{$type}')) = '{$type}'";
    }
    $internJoin = "
        LEFT JOIN (
            SELECT i2.*
            FROM internships i2
            INNER JOIN (
                SELECT student_id, MAX(id) AS latest_id
                FROM internships
                WHERE " . implode(' AND ', $latestWhere) . "
                GROUP BY student_id
            ) latest_i ON latest_i.latest_id = i2.id
            WHERE i2.deleted_at IS NULL
        ) i ON i.student_id = s.id
    ";
}

$profileJoin = '';
$profileSelect = "
    '' AS profile_company_name,
    '' AS profile_company_address,
    '' AS profile_supervisor_name,
    '' AS profile_supervisor_position,
    '' AS profile_company_representative,
    '' AS profile_company_representative_position
";
if ($hasCompanyProfiles) {
    if ($hasMasterlist && $hasInternships) {
        $profileCompanySource = "COALESCE(NULLIF(ml.company_name, ''), NULLIF(i.company_name, ''), '')";
    } elseif ($hasMasterlist) {
        $profileCompanySource = "COALESCE(NULLIF(ml.company_name, ''), '')";
    } elseif ($hasInternships) {
        $profileCompanySource = "COALESCE(NULLIF(i.company_name, ''), '')";
    } else {
        $profileCompanySource = "''";
    }
    $profileSelect = "
        COALESCE(pc.company_name, '') AS profile_company_name,
        COALESCE(pc.company_address, '') AS profile_company_address,
        COALESCE(pc.supervisor_name, '') AS profile_supervisor_name,
        COALESCE(pc.supervisor_position, '') AS profile_supervisor_position,
        COALESCE(pc.company_representative, '') AS profile_company_representative,
        COALESCE(pc.company_representative_position, '') AS profile_company_representative_position
    ";
    $profileJoin = "
        LEFT JOIN ojt_partner_companies pc
          ON TRIM(COALESCE(pc.company_name, '')) COLLATE utf8mb4_unicode_ci =
             TRIM({$profileCompanySource}) COLLATE utf8mb4_unicode_ci
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
        COALESCE(s.email, '') AS email,
        COALESCE(s.course_id, 0) AS course_id,
        COALESCE(s.section_id, 0) AS section_id,
        COALESCE(s.status, '') AS student_status,
        COALESCE(NULLIF(sec.code, ''), sec.name, '') AS registered_section,
        {$masterSelect},
        {$internSelect},
        {$profileSelect}
    FROM students s
    LEFT JOIN sections sec ON sec.id = s.section_id
    {$masterJoin}
    {$internJoin}
    {$profileJoin}
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
$filename = implode('-', $filenameParts) . '.xlsx';
$headers = $type === 'internal'
    ? ['student_no', 'last_name', 'first_name', 'middle_name', 'email', 'course_id', 'section_id', 'password']
    : ['student_no', 'school_year', 'student_name', 'contact_no', 'section', 'company_name', 'company_address', 'supervisor_name', 'supervisor_position', 'company_representative', 'company_representative_position', 'status'];
$exportRows = [];
$exportedStudentNos = [];

while ($row = $res->fetch_assoc()) {
    $studentNoKey = preg_replace('/[^a-z0-9]+/', '', strtolower((string)($row['student_no'] ?? '')));
    if ($studentNoKey !== '') {
        $exportedStudentNos[$studentNoKey] = true;
    }
    if ($type === 'internal') {
        $exportRows[] = [
            (string)($row['student_no'] ?? ''),
            (string)($row['last_name'] ?? ''),
            (string)($row['first_name'] ?? ''),
            (string)($row['middle_name'] ?? ''),
            (string)($row['email'] ?? ''),
            (string)((int)($row['course_id'] ?? 0) > 0 ? (int)$row['course_id'] : ''),
            (string)((int)($row['section_id'] ?? 0) > 0 ? (int)$row['section_id'] : ''),
            '',
        ];
        continue;
    }

    $registeredName = trim((string)($row['last_name'] ?? '') . ', ' . (string)($row['first_name'] ?? '') . ' ' . (string)($row['middle_name'] ?? ''));
    $schoolYearOut = trim((string)($row['master_school_year'] ?? '')) !== ''
        ? (string)$row['master_school_year']
        : (trim((string)($row['student_school_year'] ?? '')) !== '' ? (string)$row['student_school_year'] : (string)($row['internship_school_year'] ?? ''));
    $companyNameOut = trim((string)($row['profile_company_name'] ?? '')) !== ''
        ? (string)$row['profile_company_name']
        : (trim((string)($row['master_company_name'] ?? '')) !== '' ? (string)$row['master_company_name'] : (string)($row['internship_company_name'] ?? ''));
    $companyAddressOut = trim((string)($row['profile_company_address'] ?? '')) !== ''
        ? (string)$row['profile_company_address']
        : (trim((string)($row['master_company_address'] ?? '')) !== '' ? (string)$row['master_company_address'] : (string)($row['internship_company_address'] ?? ''));
    $supervisorNameOut = trim((string)($row['profile_supervisor_name'] ?? '')) !== ''
        ? (string)$row['profile_supervisor_name']
        : (trim((string)($row['master_supervisor_name'] ?? '')) !== '' ? (string)$row['master_supervisor_name'] : (string)($row['internship_supervisor_name'] ?? ''));
    $supervisorPositionOut = trim((string)($row['profile_supervisor_position'] ?? '')) !== ''
        ? (string)$row['profile_supervisor_position']
        : (trim((string)($row['master_supervisor_position'] ?? '')) !== '' ? (string)$row['master_supervisor_position'] : (string)($row['internship_position'] ?? ''));
    $representativeOut = trim((string)($row['profile_company_representative'] ?? '')) !== ''
        ? (string)$row['profile_company_representative']
        : (string)($row['master_company_representative'] ?? '');
    $representativePositionOut = trim((string)($row['profile_company_representative_position'] ?? '')) !== ''
        ? (string)$row['profile_company_representative_position']
        : (string)($row['master_company_representative_position'] ?? '');
    $exportRows[] = [
        (string)($row['student_no'] ?? ''),
        $schoolYearOut,
        trim((string)($row['master_student_name'] ?? '')) !== '' ? (string)$row['master_student_name'] : $registeredName,
        trim((string)($row['master_contact_no'] ?? '')) !== '' ? (string)$row['master_contact_no'] : (string)($row['phone'] ?? ''),
        trim((string)($row['master_section'] ?? '')) !== '' ? (string)$row['master_section'] : biotern_format_section_code((string)($row['registered_section'] ?? '')),
        $companyNameOut,
        $companyAddressOut,
        $supervisorNameOut,
        $supervisorPositionOut,
        $representativeOut,
        $representativePositionOut,
        trim((string)($row['master_status'] ?? '')) !== '' ? (string)$row['master_status'] : (trim((string)($row['internship_status'] ?? '')) !== '' ? (string)$row['internship_status'] : (string)($row['student_status'] ?? '')),
    ];
}

$stmt->close();

if ($type === 'external' && $hasMasterlist) {
    $masterOnlyWhere = ["TRIM(COALESCE(ml.company_name, '')) <> ''", "LOWER(TRIM(COALESCE(ml.assignment_track, 'external'))) = 'external'"];
    $masterOnlyTypes = '';
    $masterOnlyParams = [];
    if ($schoolYear !== '') {
        $masterOnlyWhere[] = "TRIM(COALESCE(ml.school_year, '')) = ?";
        $masterOnlyTypes .= 's';
        $masterOnlyParams[] = $schoolYear;
    }
    if ($semester !== '') {
        $masterOnlyWhere[] = "TRIM(COALESCE(ml.semester, '')) = ?";
        $masterOnlyTypes .= 's';
        $masterOnlyParams[] = $semester;
    }
    if ($status !== '' && $status !== 'all') {
        $masterOnlyWhere[] = "LOWER(COALESCE(ml.status, '')) = ?";
        $masterOnlyTypes .= 's';
        $masterOnlyParams[] = $status;
    }
    if ($search !== '') {
        $needle = '%' . $search . '%';
        $masterOnlyWhere[] = "(ml.student_no LIKE ? OR ml.student_name LIKE ? OR ml.company_name LIKE ? OR ml.section LIKE ?)";
        $masterOnlyTypes .= 'ssss';
        array_push($masterOnlyParams, $needle, $needle, $needle, $needle);
    }

    $masterOnlySql = "
        SELECT
            ml.student_no,
            ml.school_year,
            ml.student_name,
            ml.contact_no,
            ml.section,
            ml.company_name,
            ml.company_address,
            ml.supervisor_name,
            ml.supervisor_position,
            ml.company_representative,
            " . (export_ojt_column_exists($conn, 'ojt_masterlist', 'company_representative_position') ? "COALESCE(ml.company_representative_position, '')" : "''") . " AS company_representative_position,
            ml.status
        FROM ojt_masterlist ml
        WHERE " . implode(' AND ', $masterOnlyWhere) . "
        ORDER BY ml.section ASC, ml.student_name ASC, ml.id ASC
    ";
    $masterOnlyStmt = $conn->prepare($masterOnlySql);
    if ($masterOnlyStmt) {
        export_ojt_bind($masterOnlyStmt, $masterOnlyTypes, $masterOnlyParams);
        $masterOnlyStmt->execute();
        $masterOnlyRes = $masterOnlyStmt->get_result();
        while ($row = $masterOnlyRes->fetch_assoc()) {
            $studentNoKey = preg_replace('/[^a-z0-9]+/', '', strtolower((string)($row['student_no'] ?? '')));
            if ($studentNoKey !== '' && isset($exportedStudentNos[$studentNoKey])) {
                continue;
            }
            if ($studentNoKey !== '') {
                $exportedStudentNos[$studentNoKey] = true;
            }
            $exportRows[] = [
                (string)($row['student_no'] ?? ''),
                (string)($row['school_year'] ?? ''),
                (string)($row['student_name'] ?? ''),
                (string)($row['contact_no'] ?? ''),
                (string)($row['section'] ?? ''),
                (string)($row['company_name'] ?? ''),
                (string)($row['company_address'] ?? ''),
                (string)($row['supervisor_name'] ?? ''),
                (string)($row['supervisor_position'] ?? ''),
                (string)($row['company_representative'] ?? ''),
                (string)($row['company_representative_position'] ?? ''),
                (string)($row['status'] ?? ''),
            ];
        }
        $masterOnlyStmt->close();
    }
}

export_ojt_send_template_xlsx($type, $headers, $exportRows, $filename);
