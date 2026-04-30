<?php
require_once dirname(__DIR__) . '/config/db.php';
$studentsExcelVendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($studentsExcelVendorAutoload)) {
    require_once $studentsExcelVendorAutoload;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login.php?next=tools/import-students-excel.php');
    exit;
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Forbidden: Admin access only.';
    exit;
}

function students_excel_csrf_token(): string
{
    $token = (string)($_SESSION['students_excel_import_csrf'] ?? '');
    if ($token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['students_excel_import_csrf'] = $token;
    }
    return $token;
}

function students_excel_header(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
    return trim((string)$value, '_');
}

function students_excel_sheet_key(string $value): string
{
    return students_excel_header($value);
}

function students_excel_lookup_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return (string)$value;
}

function students_excel_row_value(array $row, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row)) {
            $value = trim((string)$row[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }

    return $default;
}

function students_excel_infer_school_year(string $fileName): string
{
    if (preg_match('/\b(\d{2})\s*-\s*(\d{2})\b/', $fileName, $matches)) {
        $start = (int)$matches[1];
        $end = (int)$matches[2];
        $century = 2000;
        return sprintf('%04d-%04d', $century + $start, $century + $end);
    }

    $year = (int)date('Y');
    return sprintf('%04d-%04d', $year, $year + 1);
}

function students_excel_normalize_school_year(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $value, $matches)) {
        return sprintf('%04d-%04d', (int)$matches[1], (int)$matches[2]);
    }

    if (preg_match('/^(\d{2})\s*-\s*(\d{2})$/', $value, $matches)) {
        return sprintf('%04d-%04d', 2000 + (int)$matches[1], 2000 + (int)$matches[2]);
    }

    return '';
}

function students_excel_normalize_semester(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return '';
    }

    $compact = preg_replace('/[^a-z0-9]+/', '', $value);
    $map = [
        '1' => '1st Semester',
        '1st' => '1st Semester',
        '1stsemester' => '1st Semester',
        'first' => '1st Semester',
        'firstsemester' => '1st Semester',
        'sem1' => '1st Semester',
        'semester1' => '1st Semester',
        '2' => '2nd Semester',
        '2nd' => '2nd Semester',
        '2ndsemester' => '2nd Semester',
        'second' => '2nd Semester',
        'secondsemester' => '2nd Semester',
        'sem2' => '2nd Semester',
        'semester2' => '2nd Semester',
        'summer' => 'Summer',
        'summerterm' => 'Summer',
        'midyear' => 'Summer',
    ];

    return $map[$compact] ?? ucwords(trim(preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $value))));
}

function students_excel_infer_semester(string $fileName): string
{
    if (preg_match('/(^|[^a-z0-9])(1st|first|sem\s*1|semester\s*1)([^a-z0-9]|$)/i', $fileName)) {
        return '1st Semester';
    }
    if (preg_match('/(^|[^a-z0-9])(2nd|second|sem\s*2|semester\s*2)([^a-z0-9]|$)/i', $fileName)) {
        return '2nd Semester';
    }
    if (preg_match('/(^|[^a-z0-9])(summer|midyear)([^a-z0-9]|$)/i', $fileName)) {
        return 'Summer';
    }
    return '';
}

function students_excel_masterlist_semester_value(array $row, string $defaultSemester = ''): string
{
    foreach (['semester', 'term', 'school_term'] as $field) {
        $semester = students_excel_normalize_semester((string)($row[$field] ?? ''));
        if ($semester !== '') {
            return $semester;
        }
    }

    return students_excel_normalize_semester($defaultSemester);
}

function students_excel_index_columns(mysqli $mysqli, string $table, string $keyName): array
{
    $safeTable = str_replace('`', '``', $table);
    $safeKeyName = $mysqli->real_escape_string($keyName);
    $res = $mysqli->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeKeyName}'");
    if (!$res instanceof mysqli_result) {
        return [];
    }

    $columns = [];
    while ($row = $res->fetch_assoc()) {
        $columns[(int)($row['Seq_in_index'] ?? 0)] = (string)($row['Column_name'] ?? '');
    }
    ksort($columns);
    return array_values(array_filter($columns, static fn(string $value): bool => $value !== ''));
}

function students_excel_table_exists(mysqli $mysqli, string $table): bool
{
    $safeTable = $mysqli->real_escape_string($table);
    $res = $mysqli->query("SHOW TABLES LIKE '{$safeTable}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function students_excel_columns(mysqli $mysqli, string $table): array
{
    $safeTable = str_replace('`', '``', $table);
    $res = $mysqli->query("SHOW COLUMNS FROM `{$safeTable}`");
    if (!$res instanceof mysqli_result) {
        return [];
    }
    $columns = [];
    while ($row = $res->fetch_assoc()) {
        $name = trim((string)($row['Field'] ?? ''));
        if ($name !== '') {
            $columns[] = $name;
        }
    }
    $res->close();
    return $columns;
}

function students_excel_internship_status(string $raw): string
{
    $status = strtolower(trim($raw));
    if (in_array($status, ['ongoing', 'completed', 'dropped', 'cancelled', 'pending'], true)) {
        return $status;
    }
    if ($status === 'active') {
        return 'ongoing';
    }
    if ($status === 'done') {
        return 'completed';
    }
    return 'ongoing';
}

function students_excel_student_lookup_keys(array $student): array
{
    $firstName = trim((string)($student['first_name'] ?? ''));
    $middleName = trim((string)($student['middle_name'] ?? ''));
    $lastName = trim((string)($student['last_name'] ?? ''));
    $keys = [];

    $variants = [
        trim($firstName . ' ' . $lastName),
        trim($firstName . ' ' . $middleName . ' ' . $lastName),
        trim($lastName . ' ' . $firstName),
        trim($lastName . ' ' . $firstName . ' ' . $middleName),
        trim($lastName . ', ' . $firstName),
        trim($lastName . ', ' . $firstName . ' ' . $middleName),
    ];

    foreach ($variants as $variant) {
        $key = students_excel_lookup_key($variant);
        if ($key !== '') {
            $keys[$key] = true;
        }
    }

    return array_keys($keys);
}

function students_excel_sync_internship_from_masterlist(mysqli $mysqli, array $studentRow, array $masterlistRow, array &$summary): bool
{
    if (!students_excel_table_exists($mysqli, 'internships')) {
        return false;
    }

    $studentId = (int)($studentRow['id'] ?? 0);
    if ($studentId <= 0) {
        return false;
    }

    $studentTrack = strtolower(trim((string)($studentRow['assignment_track'] ?? 'internal')));
    if (!in_array($studentTrack, ['internal', 'external'], true)) {
        $studentTrack = 'internal';
    }
    $requiredHours = $studentTrack === 'external'
        ? (int)($studentRow['external_total_hours'] ?? 250)
        : (int)($studentRow['internal_total_hours'] ?? 140);
    if ($requiredHours < 0) {
        $requiredHours = 0;
    }

    $companyName = trim((string)($masterlistRow['company_name'] ?? ''));
    $supervisorName = trim((string)($masterlistRow['supervisor_name'] ?? ''));
    $positionName = trim((string)($masterlistRow['supervisor_position'] ?? ''));
    $status = students_excel_internship_status((string)($masterlistRow['status'] ?? 'ongoing'));
    $schoolYear = trim((string)($masterlistRow['school_year'] ?? ''));
    $semester = trim((string)($masterlistRow['semester'] ?? ''));

    $internCols = students_excel_columns($mysqli, 'internships');
    if ($internCols === []) {
        return false;
    }

    $existingStmt = $mysqli->prepare('SELECT * FROM internships WHERE student_id = ? ORDER BY id DESC LIMIT 1');
    if (!$existingStmt) {
        return false;
    }
    $existingStmt->bind_param('i', $studentId);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if ($existing) {
        $updates = [];
        $types = '';
        $values = [];

        if (in_array('type', $internCols, true) && (string)($existing['type'] ?? '') !== $studentTrack) {
            $updates[] = 'type = ?';
            $types .= 's';
            $values[] = $studentTrack;
        }
        if (in_array('required_hours', $internCols, true) && (int)($existing['required_hours'] ?? 0) <= 0 && $requiredHours > 0) {
            $updates[] = 'required_hours = ?';
            $types .= 'i';
            $values[] = $requiredHours;
        }
        if (in_array('school_year', $internCols, true) && trim((string)($existing['school_year'] ?? '')) === '' && $schoolYear !== '') {
            $updates[] = 'school_year = ?';
            $types .= 's';
            $values[] = $schoolYear;
        }
        if (in_array('semester', $internCols, true) && trim((string)($existing['semester'] ?? '')) === '' && $semester !== '') {
            $updates[] = 'semester = ?';
            $types .= 's';
            $values[] = $semester;
        }
        if (in_array('company_name', $internCols, true) && trim((string)($existing['company_name'] ?? '')) === '' && $companyName !== '') {
            $updates[] = 'company_name = ?';
            $types .= 's';
            $values[] = $companyName;
        }
        if (in_array('supervisor_name', $internCols, true) && trim((string)($existing['supervisor_name'] ?? '')) === '' && $supervisorName !== '') {
            $updates[] = 'supervisor_name = ?';
            $types .= 's';
            $values[] = $supervisorName;
        }
        if (in_array('position', $internCols, true) && trim((string)($existing['position'] ?? '')) === '' && $positionName !== '') {
            $updates[] = 'position = ?';
            $types .= 's';
            $values[] = $positionName;
        }
        if (in_array('status', $internCols, true)) {
            $currentStatus = strtolower(trim((string)($existing['status'] ?? '')));
            if (($currentStatus === '' || $currentStatus === 'pending') && $status !== '') {
                $updates[] = 'status = ?';
                $types .= 's';
                $values[] = $status;
            }
        }

        if ($updates !== []) {
            if (in_array('updated_at', $internCols, true)) {
                $updates[] = 'updated_at = NOW()';
            }
            $sql = 'UPDATE internships SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $types .= 'i';
            $values[] = (int)$existing['id'];
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) {
                return false;
            }
            students_excel_bind_dynamic($stmt, $types, $values);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok) {
                $summary['internships_synced'] = (int)($summary['internships_synced'] ?? 0) + 1;
            }
            return (bool)$ok;
        }

        return true;
    }

    $insertCols = ['student_id'];
    $insertVals = [$studentId];
    $insertTypes = 'i';
    if (in_array('status', $internCols, true)) {
        $insertCols[] = 'status';
        $insertVals[] = $status;
        $insertTypes .= 's';
    }
    if (in_array('type', $internCols, true)) {
        $insertCols[] = 'type';
        $insertVals[] = $studentTrack;
        $insertTypes .= 's';
    }
    if (in_array('required_hours', $internCols, true)) {
        $insertCols[] = 'required_hours';
        $insertVals[] = $requiredHours;
        $insertTypes .= 'i';
    }
    if (in_array('school_year', $internCols, true) && $schoolYear !== '') {
        $insertCols[] = 'school_year';
        $insertVals[] = $schoolYear;
        $insertTypes .= 's';
    }
    if (in_array('semester', $internCols, true) && $semester !== '') {
        $insertCols[] = 'semester';
        $insertVals[] = $semester;
        $insertTypes .= 's';
    }
    if (in_array('company_name', $internCols, true) && $companyName !== '') {
        $insertCols[] = 'company_name';
        $insertVals[] = $companyName;
        $insertTypes .= 's';
    }
    if (in_array('supervisor_name', $internCols, true) && $supervisorName !== '') {
        $insertCols[] = 'supervisor_name';
        $insertVals[] = $supervisorName;
        $insertTypes .= 's';
    }
    if (in_array('position', $internCols, true) && $positionName !== '') {
        $insertCols[] = 'position';
        $insertVals[] = $positionName;
        $insertTypes .= 's';
    }
    if (in_array('created_at', $internCols, true)) {
        $insertCols[] = 'created_at';
    }
    if (in_array('updated_at', $internCols, true)) {
        $insertCols[] = 'updated_at';
    }

    $placeholders = [];
    foreach ($insertCols as $colName) {
        if ($colName === 'created_at' || $colName === 'updated_at') {
            $placeholders[] = 'NOW()';
        } else {
            $placeholders[] = '?';
        }
    }

    $sql = 'INSERT INTO internships (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        return false;
    }
    students_excel_bind_dynamic($stmt, $insertTypes, $insertVals);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $summary['internships_created'] = (int)($summary['internships_created'] ?? 0) + 1;
    }
    return (bool)$ok;
}

function students_excel_sync_masterlist_to_internships(mysqli $mysqli, string $schoolYear, string $semester, array &$summary, array &$errors): void
{
    if (!students_excel_table_exists($mysqli, 'ojt_masterlist') || !students_excel_table_exists($mysqli, 'students')) {
        return;
    }

    $students = [];
    $studentSql = "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.assignment_track, s.internal_total_hours, s.external_total_hours,
            COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '') AS section_name
        FROM students s
        LEFT JOIN sections sec ON sec.id = s.section_id";
    $studentRes = $mysqli->query($studentSql);
    if ($studentRes instanceof mysqli_result) {
        while ($row = $studentRes->fetch_assoc()) {
            $students[] = $row;
        }
        $studentRes->close();
    }
    if ($students === []) {
        return;
    }

    $studentMap = [];
    foreach ($students as $student) {
        $studentNoKey = students_excel_lookup_key((string)($student['student_id'] ?? ''));
        if ($studentNoKey !== '') {
            $studentMap[$studentNoKey][] = $student;
        }
        foreach (students_excel_student_lookup_keys($student) as $key) {
            $studentMap[$key][] = $student;
        }
    }

    $masterSql = "SELECT school_year, semester, student_no, student_lookup_key, section, company_name, supervisor_name, supervisor_position, status
        FROM ojt_masterlist
        WHERE school_year = ?";
    $types = 's';
    $params = [$schoolYear];
    if ($semester !== '') {
        $masterSql .= ' AND semester = ?';
        $types .= 's';
        $params[] = $semester;
    }
    $masterSql .= ' ORDER BY id ASC';

    $stmt = $mysqli->prepare($masterSql);
    if (!$stmt) {
        $errors[] = 'Unable to prepare masterlist-to-internship sync query.';
        return;
    }
    students_excel_bind_dynamic($stmt, $types, $params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $lookupKey = students_excel_lookup_key((string)($row['student_no'] ?? ''));
        if ($lookupKey === '') {
            $lookupKey = trim((string)($row['student_lookup_key'] ?? ''));
        }
        if ($lookupKey === '' || empty($studentMap[$lookupKey])) {
            continue;
        }

        $masterSectionKey = students_excel_lookup_key((string)($row['section'] ?? ''));
        $candidates = $studentMap[$lookupKey];
        $picked = null;
        foreach ($candidates as $candidate) {
            $sectionKey = students_excel_lookup_key((string)($candidate['section_name'] ?? ''));
            if ($masterSectionKey !== '' && $sectionKey !== '' && $masterSectionKey === $sectionKey) {
                $picked = $candidate;
                break;
            }
            if ($picked === null) {
                $picked = $candidate;
            }
        }

        if ($picked === null) {
            continue;
        }

        if (!students_excel_sync_internship_from_masterlist($mysqli, $picked, $row, $summary)) {
            $errors[] = 'Unable to sync internship for masterlist student key: ' . $lookupKey;
        }
    }
    $stmt->close();
}

function students_excel_password(string $password): string
{
    $password = trim($password);
    if ($password === '') {
        $password = 'password123';
    }
    $info = password_get_info($password);
    if (($info['algo'] ?? 0) !== 0) {
        return $password;
    }
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    return $hashed !== false ? $hashed : $password;
}

function students_excel_bind_dynamic(mysqli_stmt $stmt, string $types, array &$values): bool
{
    if ($types === '') {
        return true;
    }

    $bind = [$types];
    foreach ($values as $idx => &$value) {
        $bind[] = &$value;
    }

    return call_user_func_array([$stmt, 'bind_param'], $bind);
}

function students_excel_username(string $firstName, string $lastName, string $email): string
{
    $base = $email !== '' ? (string)strstr($email, '@', true) : trim($firstName . $lastName);
    $base = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$base);
    if ($base === '') {
        $base = 'student' . time();
    }
    return substr((string)$base, 0, 255);
}

function students_excel_load_workbook(string $path, string $sourceWorkbook, string &$errorMessage): array
{
    $errorMessage = '';
    $extension = strtolower((string)pathinfo($sourceWorkbook, PATHINFO_EXTENSION));
    if ($extension === '') {
        $extension = students_excel_detect_workbook_extension($path);
    }

    if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        } catch (Throwable $e) {
            $errorMessage = 'Unable to read workbook: ' . $e->getMessage();
            return [];
        }

        $sheets = [];
        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            $rows = $worksheet->toArray('', true, true, false);
            if (empty($rows)) {
                continue;
            }
            $sheetKey = students_excel_sheet_key((string)$worksheet->getTitle());
            $headerRow = array_shift($rows);
            $headers = [];
            foreach ($headerRow as $cell) {
                $headers[] = students_excel_header((string)$cell);
            }

            $normalizedRows = [];
            foreach ($rows as $row) {
                $assoc = [];
                $hasContent = false;
                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $value = isset($row[$index]) ? trim((string)$row[$index]) : '';
                    if ($value !== '') {
                        $hasContent = true;
                    }
                    $assoc[$header] = $value;
                }
                if ($hasContent) {
                    $normalizedRows[] = $assoc;
                }
            }
            $sheets[$sheetKey] = $normalizedRows;
        }

        return $sheets;
    }

    if ($extension === 'xlsx') {
        return students_excel_load_xlsx_workbook($path, $errorMessage);
    }

    if ($extension === 'xls') {
        $errorMessage = 'Legacy .xls files require PhpSpreadsheet, which is not installed in this project. Please save the workbook as .xlsx first.';
        return [];
    }

    $errorMessage = 'Unsupported workbook format. Please upload an .xlsx file.';
    return [];
}

function students_excel_detect_workbook_extension(string $path): string
{
    if (!is_file($path)) {
        return '';
    }

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $hasWorkbookXml = is_string($zip->getFromName('xl/workbook.xml'));
            $zip->close();
            if ($hasWorkbookXml) {
                return 'xlsx';
            }
        }
    }

    $handle = @fopen($path, 'rb');
    if ($handle === false) {
        return '';
    }

    $signature = fread($handle, 8);
    fclose($handle);

    if (strncmp((string)$signature, "\xD0\xCF\x11\xE0", 4) === 0) {
        return 'xls';
    }

    return '';
}

function students_excel_load_xlsx_workbook(string $path, string &$errorMessage): array
{
    $errorMessage = '';
    if (!class_exists('ZipArchive')) {
        $errorMessage = 'Unable to read workbook: ZipArchive is not available on this PHP setup.';
        return [];
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        $errorMessage = 'Unable to open workbook archive.';
        return [];
    }

    $sharedStrings = students_excel_xlsx_shared_strings($zip);
    $sheetFiles = students_excel_xlsx_sheet_files($zip, $errorMessage);
    if ($sheetFiles === []) {
        $zip->close();
        if ($errorMessage === '') {
            $errorMessage = 'No worksheets found in workbook.';
        }
        return [];
    }

    $sheets = [];
    foreach ($sheetFiles as $sheetTitle => $sheetPath) {
        $sheetXml = $zip->getFromName($sheetPath);
        if (!is_string($sheetXml) || $sheetXml === '') {
            continue;
        }

        $worksheet = @simplexml_load_string($sheetXml);
        if (!$worksheet) {
            continue;
        }

        $worksheet->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rowNodes = $worksheet->xpath('//main:sheetData/main:row');
        if (!is_array($rowNodes) || $rowNodes === []) {
            continue;
        }

        $rows = [];
        $maxColumnIndex = -1;
        foreach ($rowNodes as $rowNode) {
            $cells = [];
            $rowNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cellNodes = $rowNode->xpath('./main:c');
            if (!is_array($cellNodes)) {
                $cellNodes = [];
            }

            foreach ($cellNodes as $cellNode) {
                $reference = (string)($cellNode['r'] ?? '');
                $columnIndex = students_excel_cell_reference_to_index($reference);
                if ($columnIndex < 0) {
                    continue;
                }
                $cells[$columnIndex] = trim(students_excel_xlsx_cell_value($cellNode, $sharedStrings));
                if ($columnIndex > $maxColumnIndex) {
                    $maxColumnIndex = $columnIndex;
                }
            }

            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        if ($rows === [] || $maxColumnIndex < 0) {
            continue;
        }

        $headerCells = array_shift($rows);
        $headers = [];
        for ($i = 0; $i <= $maxColumnIndex; $i++) {
            $headers[$i] = students_excel_header((string)($headerCells[$i] ?? ''));
        }

        $normalizedRows = [];
        foreach ($rows as $rowCells) {
            $assoc = [];
            $hasContent = false;
            for ($i = 0; $i <= $maxColumnIndex; $i++) {
                $header = $headers[$i] ?? '';
                if ($header === '') {
                    continue;
                }
                $value = trim((string)($rowCells[$i] ?? ''));
                if ($value !== '') {
                    $hasContent = true;
                }
                $assoc[$header] = $value;
            }
            if ($hasContent) {
                $normalizedRows[] = $assoc;
            }
        }

        $sheets[students_excel_sheet_key($sheetTitle)] = $normalizedRows;
    }

    $zip->close();
    if ($sheets === []) {
        $errorMessage = 'Workbook could be opened, but no readable worksheet data was found.';
    }

    return $sheets;
}

function students_excel_xlsx_shared_strings(ZipArchive $zip): array
{
    $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
    if (!is_string($sharedStringsXml) || $sharedStringsXml === '') {
        return [];
    }

    $sharedStringsDoc = @simplexml_load_string($sharedStringsXml);
    if (!$sharedStringsDoc) {
        return [];
    }

    $sharedStringsDoc->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $stringNodes = $sharedStringsDoc->xpath('//main:si');
    if (!is_array($stringNodes)) {
        return [];
    }

    $values = [];
    foreach ($stringNodes as $stringNode) {
        $stringNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $parts = $stringNode->xpath('.//main:t');
        if (!is_array($parts) || $parts === []) {
            $values[] = '';
            continue;
        }

        $text = '';
        foreach ($parts as $part) {
            $text .= (string)$part;
        }
        $values[] = $text;
    }

    return $values;
}

function students_excel_xlsx_sheet_files(ZipArchive $zip, string &$errorMessage): array
{
    $errorMessage = '';
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if (!is_string($workbookXml) || $workbookXml === '' || !is_string($relsXml) || $relsXml === '') {
        $errorMessage = 'Workbook metadata is incomplete.';
        return [];
    }

    $workbook = @simplexml_load_string($workbookXml);
    $relationships = @simplexml_load_string($relsXml);
    if (!$workbook || !$relationships) {
        $errorMessage = 'Workbook metadata could not be parsed.';
        return [];
    }

    $workbook->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $relationships->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $relationshipMap = [];
    $relationshipNodes = $relationships->xpath('//rel:Relationship');
    if (is_array($relationshipNodes)) {
        foreach ($relationshipNodes as $relationshipNode) {
            $id = (string)($relationshipNode['Id'] ?? '');
            $target = (string)($relationshipNode['Target'] ?? '');
            if ($id === '' || $target === '') {
                continue;
            }
            $relationshipMap[$id] = 'xl/' . ltrim(str_replace('\\', '/', $target), '/');
        }
    }

    $sheetFiles = [];
    $sheetNodes = $workbook->xpath('//main:sheets/main:sheet');
    if (!is_array($sheetNodes)) {
        return $sheetFiles;
    }

    foreach ($sheetNodes as $sheetNode) {
        $title = trim((string)($sheetNode['name'] ?? ''));
        $relationshipId = trim((string)($sheetNode->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? ''));
        if ($title === '' || $relationshipId === '' || !isset($relationshipMap[$relationshipId])) {
            continue;
        }
        $sheetFiles[$title] = $relationshipMap[$relationshipId];
    }

    return $sheetFiles;
}

function students_excel_cell_reference_to_index(string $reference): int
{
    if (!preg_match('/^[A-Z]+/i', $reference, $matches)) {
        return -1;
    }

    $letters = strtoupper($matches[0]);
    $index = 0;
    $length = strlen($letters);
    for ($i = 0; $i < $length; $i++) {
        $index = ($index * 26) + (ord($letters[$i]) - 64);
    }

    return $index - 1;
}

function students_excel_xlsx_cell_value(SimpleXMLElement $cellNode, array $sharedStrings): string
{
    $type = (string)($cellNode['t'] ?? '');
    $cellNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    if ($type === 'inlineStr') {
        $parts = $cellNode->xpath('./main:is//main:t');
        if (!is_array($parts) || $parts === []) {
            return '';
        }

        $text = '';
        foreach ($parts as $part) {
            $text .= (string)$part;
        }
        return $text;
    }

    $value = trim((string)($cellNode->v ?? ''));
    if ($type === 's') {
        $index = (int)$value;
        return (string)($sharedStrings[$index] ?? '');
    }
    if ($type === 'b') {
        return $value === '1' ? '1' : '0';
    }

    return $value;
}

function students_excel_has_headers(array $rows, array $requiredHeaders): bool
{
    if (empty($rows)) {
        return false;
    }

    $headers = array_keys($rows[0]);
    foreach ($requiredHeaders as $header) {
        if (!in_array($header, $headers, true)) {
            return false;
        }
    }

    return true;
}

function students_excel_has_header_groups(array $rows, array $requiredHeaderGroups): bool
{
    if (empty($rows)) {
        return false;
    }

    $headers = array_keys($rows[0]);
    foreach ($requiredHeaderGroups as $headerGroup) {
        $found = false;
        foreach ($headerGroup as $header) {
            if (in_array($header, $headers, true)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }
    }

    return true;
}

function students_excel_ensure_masterlist_tables(mysqli $mysqli, string &$errorMessage): bool
{
    $errorMessage = '';

    $companySql = "CREATE TABLE IF NOT EXISTS ojt_partner_companies (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        company_lookup_key VARCHAR(255) NOT NULL,
        company_name VARCHAR(255) NOT NULL,
        company_address TEXT DEFAULT NULL,
        supervisor_name VARCHAR(255) DEFAULT NULL,
        supervisor_position VARCHAR(255) DEFAULT NULL,
        company_representative VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_company_lookup (company_lookup_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$mysqli->query($companySql)) {
        $errorMessage = 'Failed to ensure ojt_partner_companies table: ' . $mysqli->error;
        return false;
    }

    $masterlistSql = "CREATE TABLE IF NOT EXISTS ojt_masterlist (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        school_year VARCHAR(20) NOT NULL,
        semester VARCHAR(30) NOT NULL DEFAULT '',
        student_no VARCHAR(100) DEFAULT NULL,
        source_workbook VARCHAR(255) DEFAULT NULL,
        source_sheet VARCHAR(255) DEFAULT NULL,
        source_row_number INT NOT NULL DEFAULT 0,
        student_lookup_key VARCHAR(255) NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        contact_no VARCHAR(50) DEFAULT NULL,
        section VARCHAR(100) DEFAULT NULL,
        company_id BIGINT UNSIGNED DEFAULT NULL,
        company_name VARCHAR(255) DEFAULT NULL,
        company_address TEXT DEFAULT NULL,
        supervisor_name VARCHAR(255) DEFAULT NULL,
        supervisor_position VARCHAR(255) DEFAULT NULL,
        company_representative VARCHAR(255) DEFAULT NULL,
        status VARCHAR(100) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_masterlist_student_term (school_year, semester, student_lookup_key, section),
        KEY idx_masterlist_company (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$mysqli->query($masterlistSql)) {
        $errorMessage = 'Failed to ensure ojt_masterlist table: ' . $mysqli->error;
        return false;
    }

    biotern_db_add_column_if_missing($mysqli, 'ojt_masterlist', 'semester', "semester VARCHAR(30) NOT NULL DEFAULT '' AFTER school_year");
    biotern_db_add_column_if_missing($mysqli, 'ojt_masterlist', 'student_no', "student_no VARCHAR(100) DEFAULT NULL AFTER semester");

    $legacyIndexColumns = students_excel_index_columns($mysqli, 'ojt_masterlist', 'uniq_masterlist_student');
    if ($legacyIndexColumns !== []) {
        $mysqli->query("ALTER TABLE `ojt_masterlist` DROP INDEX `uniq_masterlist_student`");
    }

    $expectedUnique = ['school_year', 'semester', 'student_lookup_key', 'section'];
    $currentUnique = students_excel_index_columns($mysqli, 'ojt_masterlist', 'uniq_masterlist_student_term');
    if ($currentUnique !== $expectedUnique) {
        if ($currentUnique !== []) {
            $mysqli->query("ALTER TABLE `ojt_masterlist` DROP INDEX `uniq_masterlist_student_term`");
        }
        if (!$mysqli->query("ALTER TABLE `ojt_masterlist` ADD UNIQUE KEY `uniq_masterlist_student_term` (`school_year`, `semester`, `student_lookup_key`, `section`)")) {
            $errorMessage = 'Failed to ensure semester-aware masterlist unique key: ' . $mysqli->error;
            return false;
        }
    }

    return true;
}

function students_excel_upsert_partner_company(mysqli $mysqli, array $row, string &$errorMessage): int
{
    $errorMessage = '';
    $companyName = students_excel_row_value($row, ['company_name', 'company']);
    $companyAddress = students_excel_row_value($row, ['company_address', 'address']);
    $supervisorName = students_excel_row_value($row, ['supervisor_name']);
    $supervisorPosition = students_excel_row_value($row, ['supervisor_position', 'position']);
    $companyRepresentative = students_excel_row_value($row, ['company_representative']);
    $lookupKey = students_excel_lookup_key($companyName . '|' . $companyAddress);

    if ($companyName === '') {
        return 0;
    }

    $stmt = $mysqli->prepare("INSERT INTO ojt_partner_companies (
            company_lookup_key, company_name, company_address, supervisor_name, supervisor_position, company_representative, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            company_name = VALUES(company_name),
            company_address = VALUES(company_address),
            supervisor_name = VALUES(supervisor_name),
            supervisor_position = VALUES(supervisor_position),
            company_representative = VALUES(company_representative),
            updated_at = NOW()");

    if (!$stmt) {
        $errorMessage = 'Failed to prepare partner company upsert: ' . $mysqli->error;
        return 0;
    }

    $stmt->bind_param('ssssss', $lookupKey, $companyName, $companyAddress, $supervisorName, $supervisorPosition, $companyRepresentative);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        $errorMessage = 'Failed to save partner company: ' . $mysqli->error;
        return 0;
    }

    $select = $mysqli->prepare('SELECT id FROM ojt_partner_companies WHERE company_lookup_key = ? LIMIT 1');
    if (!$select) {
        $errorMessage = 'Failed to load partner company id: ' . $mysqli->error;
        return 0;
    }

    $select->bind_param('s', $lookupKey);
    $select->execute();
    $company = $select->get_result()->fetch_assoc();
    $select->close();

    return (int)($company['id'] ?? 0);
}

function students_excel_import_masterlist(mysqli $mysqli, string $sheetName, array $rows, string $schoolYear, string $semester, string $sourceWorkbook, array &$summary, array &$errors): void
{
    $tableError = '';
    if (!students_excel_ensure_masterlist_tables($mysqli, $tableError)) {
        $errors[] = $tableError;
        return;
    }

    $deleteStmt = $mysqli->prepare('DELETE FROM ojt_masterlist WHERE school_year = ? AND semester = ?');
    if (!$deleteStmt) {
        $errors[] = 'Failed to prepare school-year and semester refresh for masterlist: ' . $mysqli->error;
        return;
    }

    $deleteStmt->bind_param('ss', $schoolYear, $semester);
    if (!$deleteStmt->execute()) {
        $errors[] = 'Failed to clear existing masterlist rows for ' . $schoolYear . ' / ' . $semester . ': ' . $mysqli->error;
        $deleteStmt->close();
        return;
    }
    $summary['masterlist_rows_replaced'] = (int)$deleteStmt->affected_rows;
    $deleteStmt->close();

    foreach ($rows as $index => $row) {
        $studentNo = students_excel_row_value($row, ['student_no', 'student_id', 'student_number']);
        $rowSchoolYear = students_excel_normalize_school_year(students_excel_row_value($row, ['school_year', 'sy'], $schoolYear));
        if ($rowSchoolYear === '') {
            $rowSchoolYear = $schoolYear;
        }
        $studentName = trim((string)($row['student_name'] ?? ''));
        $contactNo = students_excel_row_value($row, ['contact_no', 'contact_number']);
        $section = students_excel_row_value($row, ['section']);
        $status = students_excel_row_value($row, ['status']);
        $companyName = students_excel_row_value($row, ['company_name', 'company']);
        $companyAddress = students_excel_row_value($row, ['company_address', 'address']);
        $supervisorName = students_excel_row_value($row, ['supervisor_name']);
        $supervisorPosition = students_excel_row_value($row, ['supervisor_position', 'position']);
        $companyRepresentative = students_excel_row_value($row, ['company_representative']);
        $rowSemester = students_excel_masterlist_semester_value($row, $semester);

        if ($studentName === '' && $companyName === '' && $section === '') {
            continue;
        }

        if ($studentName === '') {
            $errors[] = 'Masterlist row ' . ($index + 2) . ': missing student name.';
            continue;
        }

        $studentLookupKey = $studentNo !== '' ? students_excel_lookup_key($studentNo) : students_excel_lookup_key($studentName);
        if ($studentLookupKey === '') {
            $errors[] = 'Masterlist row ' . ($index + 2) . ': student name could not be normalized.';
            continue;
        }

        $companyId = 0;
        if ($companyName !== '') {
            $companyError = '';
            $companyId = students_excel_upsert_partner_company($mysqli, $row, $companyError);
            if ($companyId <= 0 && $companyError !== '') {
                $errors[] = 'Masterlist row ' . ($index + 2) . ': ' . $companyError;
            }
        }

        $stmt = $mysqli->prepare("INSERT INTO ojt_masterlist (
                school_year, semester, student_no, source_workbook, source_sheet, source_row_number, student_lookup_key, student_name,
                contact_no, section, company_id, company_name, company_address, supervisor_name, supervisor_position,
                company_representative, status, created_at, updated_at
            ) VALUES (?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                semester = VALUES(semester),
                student_no = COALESCE(NULLIF(VALUES(student_no), ''), ojt_masterlist.student_no),
                source_workbook = VALUES(source_workbook),
                source_sheet = VALUES(source_sheet),
                source_row_number = VALUES(source_row_number),
                student_name = VALUES(student_name),
                contact_no = VALUES(contact_no),
                company_id = VALUES(company_id),
                company_name = VALUES(company_name),
                company_address = VALUES(company_address),
                supervisor_name = VALUES(supervisor_name),
                supervisor_position = VALUES(supervisor_position),
                company_representative = VALUES(company_representative),
                status = VALUES(status),
                updated_at = NOW()");

        if (!$stmt) {
            $errors[] = 'Masterlist row ' . ($index + 2) . ': failed to prepare save statement. ' . $mysqli->error;
            continue;
        }

        $rowNumber = $index + 2;
        $stmt->bind_param(
            'sssssissssissssss',
            $rowSchoolYear,
            $rowSemester,
            $studentNo,
            $sourceWorkbook,
            $sheetName,
            $rowNumber,
            $studentLookupKey,
            $studentName,
            $contactNo,
            $section,
            $companyId,
            $companyName,
            $companyAddress,
            $supervisorName,
            $supervisorPosition,
            $companyRepresentative,
            $status
        );

        if ($stmt->execute()) {
            $summary['masterlist_rows_upserted']++;
            if ($companyId > 0) {
                $summary['masterlist_rows_linked_to_company']++;
            }
        } else {
            $errors[] = 'Masterlist row ' . ($index + 2) . ': failed to save row.';
        }

        $stmt->close();
    }
}

function students_excel_find_user(mysqli $mysqli, string $email, string $username): ?array
{
    if ($email !== '') {
        $stmt = $mysqli->prepare('SELECT id, email, username FROM users WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    if ($username !== '') {
        $stmt = $mysqli->prepare('SELECT id, email, username FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    return null;
}

function students_excel_find_student(mysqli $mysqli, string $studentCode, string $email, int $userId = 0): ?array
{
    if ($studentCode !== '') {
        $stmt = $mysqli->prepare('SELECT id, user_id, student_id, email FROM students WHERE student_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $studentCode);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
        // When a student number is provided, it is the only authoritative lookup key.
        return null;
    }
    if ($email !== '') {
        $stmt = $mysqli->prepare('SELECT id, user_id, student_id, email FROM students WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    if ($userId > 0) {
        $stmt = $mysqli->prepare('SELECT id, user_id, student_id, email FROM students WHERE user_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (is_array($row)) {
                return $row;
            }
        }
    }
    return null;
}

function students_excel_upsert_user(mysqli $mysqli, array $row, string &$errorMessage, int $preferredUserId = 0): int
{
    $firstName = trim((string)($row['first_name'] ?? ''));
    $lastName = trim((string)($row['last_name'] ?? ''));
    $email = trim((string)($row['email'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    $fullName = trim((string)($row['name'] ?? trim($firstName . ' ' . $lastName)));
    $profilePicture = trim((string)($row['profile_picture'] ?? ''));
    $isActive = (int)((string)($row['is_active'] ?? '1') === '0' ? 0 : 1);
    if ($username === '') {
        $username = students_excel_username($firstName, $lastName, $email);
    }
    if ($fullName === '') {
        $fullName = $username;
    }
    $rawPassword = trim((string)($row['password'] ?? ''));
    $passwordHash = $rawPassword !== '' ? students_excel_password($rawPassword) : '';
    $existing = null;
    if ($preferredUserId > 0) {
        $prefStmt = $mysqli->prepare('SELECT id, email, username FROM users WHERE id = ? LIMIT 1');
        if ($prefStmt) {
            $prefStmt->bind_param('i', $preferredUserId);
            $prefStmt->execute();
            $existing = $prefStmt->get_result()->fetch_assoc();
            $prefStmt->close();
        }
    }
    if (!is_array($existing)) {
        $existing = students_excel_find_user($mysqli, $email, $username);
    }

    if ($existing) {
        $userId = (int)($existing['id'] ?? 0);
        if ($passwordHash !== '') {
            $stmt = $mysqli->prepare("UPDATE users SET name = ?, username = ?, email = ?, password = ?, role = 'student', is_active = ?, profile_picture = ?, application_status = 'approved', updated_at = NOW() WHERE id = ?");
        } else {
            $stmt = $mysqli->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = 'student', is_active = ?, profile_picture = ?, application_status = 'approved', updated_at = NOW() WHERE id = ?");
        }
        if (!$stmt) {
            $errorMessage = 'Failed to prepare user update: ' . $mysqli->error;
            return 0;
        }
        if ($passwordHash !== '') {
            $stmt->bind_param('ssssisi', $fullName, $username, $email, $passwordHash, $isActive, $profilePicture, $userId);
        } else {
            $stmt->bind_param('sssisi', $fullName, $username, $email, $isActive, $profilePicture, $userId);
        }
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $errorMessage = 'Failed to update user: ' . $mysqli->error;
            return 0;
        }
        return $userId;
    }

    if ($passwordHash === '') {
        $passwordHash = students_excel_password('');
    }

    $stmt = $mysqli->prepare("INSERT INTO users (name, username, email, password, role, is_active, application_status, profile_picture, created_at, updated_at) VALUES (?, ?, ?, ?, 'student', ?, 'approved', ?, NOW(), NOW())");
    if (!$stmt) {
        $errorMessage = 'Failed to prepare user insert: ' . $mysqli->error;
        return 0;
    }
    $stmt->bind_param('ssssis', $fullName, $username, $email, $passwordHash, $isActive, $profilePicture);
    $ok = $stmt->execute();
    $userId = $ok ? (int)$mysqli->insert_id : 0;
    $stmt->close();
    if (!$ok || $userId <= 0) {
        $errorMessage = 'Failed to insert user: ' . $mysqli->error;
        return 0;
    }
    return $userId;
}

function students_excel_upsert_student(mysqli $mysqli, array $row, int $userId, string &$errorMessage): int
{
    biotern_db_add_column_if_missing($mysqli, 'students', 'semester', "semester VARCHAR(30) DEFAULT NULL AFTER section_id");

    $studentCode = trim((string)($row['student_id'] ?? ''));
    $firstName = trim((string)($row['first_name'] ?? ''));
    $lastName = trim((string)($row['last_name'] ?? ''));
    $middleName = trim((string)($row['middle_name'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    $rawPassword = trim((string)($row['password'] ?? ''));
    $passwordHash = $rawPassword !== '' ? students_excel_password($rawPassword) : '';
    $email = trim((string)($row['email'] ?? ''));
    $bio = trim((string)($row['bio'] ?? ''));
    $departmentId = trim((string)($row['department_id'] ?? '0'));
    $sectionId = (int)($row['section_id'] ?? 0);
    $supervisorName = trim((string)($row['supervisor_name'] ?? ''));
    $coordinatorName = trim((string)($row['coordinator_name'] ?? ''));
    $supervisorId = (int)($row['supervisor_id'] ?? 0);
    $coordinatorId = (int)($row['coordinator_id'] ?? 0);
    $phone = trim((string)($row['phone'] ?? ''));
    $dateOfBirth = trim((string)($row['date_of_birth'] ?? ''));
    $gender = strtolower(trim((string)($row['gender'] ?? '')));
    $address = trim((string)($row['address'] ?? ''));
    $internalTotalHours = (int)($row['internal_total_hours'] ?? 0);
    $internalRemaining = (int)($row['internal_total_hours_remaining'] ?? $internalTotalHours);
    $externalTotalHours = (int)($row['external_total_hours'] ?? 0);
    $externalRemaining = (int)($row['external_total_hours_remaining'] ?? $externalTotalHours);
    $emergencyContact = trim((string)($row['emergency_contact'] ?? ''));
    $profilePicture = trim((string)($row['profile_picture'] ?? ''));
    $status = trim((string)($row['status'] ?? '1'));
    $schoolYear = trim((string)($row['school_year'] ?? ''));
    $semester = students_excel_normalize_semester((string)($row['semester'] ?? ''));
    $assignmentTrack = strtolower(trim((string)($row['assignment_track'] ?? 'internal')));
    $courseId = (int)($row['course_id'] ?? 0);

    if ($studentCode === '' || $firstName === '' || $lastName === '' || $email === '' || $courseId <= 0) {
        $errorMessage = 'Missing required student fields: student_id, first_name, last_name, email, course_id.';
        return 0;
    }
    if ($username === '') {
        $username = students_excel_username($firstName, $lastName, $email);
    }
    if (!in_array($gender, ['male', 'female', 'other', ''], true)) {
        $gender = '';
    }
    if (!in_array($assignmentTrack, ['internal', 'external'], true)) {
        $assignmentTrack = 'internal';
    }
    if ($schoolYear === '') {
        $schoolYear = date('Y') . '-' . (date('Y') + 1);
    }

    $existing = students_excel_find_student($mysqli, $studentCode, $email, $userId);
    if ($existing) {
        $studentPk = (int)($existing['id'] ?? 0);
        $stmt = $mysqli->prepare("UPDATE students SET user_id = ?, course_id = ?, student_id = ?, first_name = ?, last_name = ?, middle_name = ?, username = ?, password = COALESCE(NULLIF(?, ''), password), email = ?, bio = ?, department_id = ?, section_id = ?, semester = NULLIF(?, ''), supervisor_name = ?, coordinator_name = ?, supervisor_id = NULLIF(?, 0), coordinator_id = NULLIF(?, 0), phone = ?, date_of_birth = NULLIF(?, ''), gender = NULLIF(?, ''), address = ?, internal_total_hours = ?, internal_total_hours_remaining = ?, external_total_hours = ?, external_total_hours_remaining = ?, emergency_contact = ?, profile_picture = ?, status = ?, school_year = ?, assignment_track = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            $errorMessage = 'Failed to prepare student update: ' . $mysqli->error;
            return 0;
        }
        $stmt->bind_param('iisssssssssisssiissssiiiisssssi', $userId, $courseId, $studentCode, $firstName, $lastName, $middleName, $username, $passwordHash, $email, $bio, $departmentId, $sectionId, $semester, $supervisorName, $coordinatorName, $supervisorId, $coordinatorId, $phone, $dateOfBirth, $gender, $address, $internalTotalHours, $internalRemaining, $externalTotalHours, $externalRemaining, $emergencyContact, $profilePicture, $status, $schoolYear, $assignmentTrack, $studentPk);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $errorMessage = 'Failed to update student: ' . $mysqli->error;
            return 0;
        }
        return $studentPk;
    }

    if ($passwordHash === '') {
        $passwordHash = students_excel_password('');
    }

    $stmt = $mysqli->prepare("INSERT INTO students (user_id, course_id, student_id, first_name, last_name, middle_name, username, password, email, bio, department_id, section_id, semester, supervisor_name, coordinator_name, supervisor_id, coordinator_id, phone, date_of_birth, gender, address, internal_total_hours, internal_total_hours_remaining, external_total_hours, external_total_hours_remaining, emergency_contact, profile_picture, status, school_year, assignment_track, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, NULLIF(?, 0), NULLIF(?, 0), ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    if (!$stmt) {
        $errorMessage = 'Failed to prepare student insert: ' . $mysqli->error;
        return 0;
    }
    $stmt->bind_param('iisssssssssisssiissssiiiisssss', $userId, $courseId, $studentCode, $firstName, $lastName, $middleName, $username, $passwordHash, $email, $bio, $departmentId, $sectionId, $semester, $supervisorName, $coordinatorName, $supervisorId, $coordinatorId, $phone, $dateOfBirth, $gender, $address, $internalTotalHours, $internalRemaining, $externalTotalHours, $externalRemaining, $emergencyContact, $profilePicture, $status, $schoolYear, $assignmentTrack);
    $ok = $stmt->execute();
    $studentPk = $ok ? (int)$mysqli->insert_id : 0;
    $stmt->close();
    if (!$ok || $studentPk <= 0) {
        $errorMessage = 'Failed to insert student: ' . $mysqli->error;
        return 0;
    }
    return $studentPk;
}

function students_excel_import_documents(mysqli $mysqli, array $rows, array &$summary, array &$errors): void
{
    foreach ($rows as $index => $row) {
        $studentCode = trim((string)($row['student_id'] ?? ''));
        $email = trim((string)($row['email'] ?? ''));
        $documentType = trim((string)($row['document_type'] ?? ''));
        $fileName = trim((string)($row['file_name'] ?? ''));
        $filePath = trim((string)($row['file_path'] ?? ''));
        $description = trim((string)($row['description'] ?? ''));
        $fileType = trim((string)($row['file_type'] ?? ''));
        $fileSize = (int)($row['file_size'] ?? 0);

        if ($documentType === '' || ($studentCode === '' && $email === '')) {
            $errors[] = 'Documents row ' . ($index + 2) . ': missing student_id/email or document_type.';
            continue;
        }

        $student = students_excel_find_student($mysqli, $studentCode, $email);
        if (!$student) {
            $errors[] = 'Documents row ' . ($index + 2) . ': student not found.';
            continue;
        }

        $studentPk = (int)($student['id'] ?? 0);
        $existingId = 0;
        if ($fileName !== '') {
            $stmt = $mysqli->prepare('SELECT id FROM documents WHERE student_id = ? AND document_type = ? AND file_name = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('iss', $studentPk, $documentType, $fileName);
                $stmt->execute();
                $rowFound = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $existingId = (int)($rowFound['id'] ?? 0);
            }
        }

        if ($existingId > 0) {
            $stmt = $mysqli->prepare('UPDATE documents SET file_path = ?, description = ?, file_type = ?, file_size = ?, updated_at = NOW() WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('sssii', $filePath, $description, $fileType, $fileSize, $existingId);
                if ($stmt->execute()) {
                    $summary['documents_updated']++;
                } else {
                    $errors[] = 'Documents row ' . ($index + 2) . ': failed to update document.';
                }
                $stmt->close();
            }
            continue;
        }

        $stmt = $mysqli->prepare('INSERT INTO documents (student_id, document_type, file_path, file_name, uploaded_at, created_at, updated_at, file_type, file_size, description) VALUES (?, ?, ?, ?, NOW(), NOW(), NOW(), ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('issssis', $studentPk, $documentType, $filePath, $fileName, $fileType, $fileSize, $description);
            if ($stmt->execute()) {
                $summary['documents_inserted']++;
            } else {
                $errors[] = 'Documents row ' . ($index + 2) . ': failed to insert document.';
            }
            $stmt->close();
        }
    }
}

function students_excel_import_workbook(mysqli $mysqli, string $path, string $sourceWorkbook, string $semesterOverride, array &$summary, array &$errors, string &$message): bool
{
    $loadError = '';
    $sheets = students_excel_load_workbook($path, $sourceWorkbook, $loadError);
    if ($loadError !== '') {
        $message = $loadError;
        return false;
    }

    $studentsRows = $sheets['students'] ?? ($sheets['student_database'] ?? []);
    $documentsRows = $sheets['documents'] ?? [];
    $masterlistSheetKey = '';
    $masterlistRows = [];
    $masterlistRequiredHeaders = [
        ['student_name'],
        ['contact_no', 'contact_number'],
        ['section'],
        ['company_name', 'company'],
        ['company_address', 'address'],
        ['supervisor_name'],
        ['supervisor_position', 'position'],
        ['company_representative'],
        ['status'],
    ];

    foreach ($sheets as $sheetKey => $rows) {
        if (students_excel_has_header_groups($rows, $masterlistRequiredHeaders)) {
            $masterlistSheetKey = (string)$sheetKey;
            $masterlistRows = $rows;
            break;
        }
    }

    if (!empty($masterlistRows)) {
        $summary = [
            'masterlist_rows_replaced' => 0,
            'masterlist_rows_upserted' => 0,
            'masterlist_rows_linked_to_company' => 0,
            'internships_created' => 0,
            'internships_synced' => 0,
        ];
        $schoolYear = students_excel_normalize_school_year(students_excel_row_value($masterlistRows[0] ?? [], ['school_year', 'sy']));
        if ($schoolYear === '') {
            $schoolYear = students_excel_infer_school_year($sourceWorkbook);
        }
        $semester = students_excel_normalize_semester($semesterOverride);
        if ($semester === '') {
            $semester = students_excel_infer_semester($sourceWorkbook);
        }
        if ($semester === '') {
            $semester = students_excel_masterlist_semester_value($masterlistRows[0] ?? []);
        }
        if ($semester === '') {
            $semester = 'Unspecified';
        }
        students_excel_import_masterlist($mysqli, $masterlistSheetKey, $masterlistRows, $schoolYear, $semester, $sourceWorkbook, $summary, $errors);
        students_excel_sync_masterlist_to_internships($mysqli, $schoolYear, $semester, $summary, $errors);
        $message = 'Masterlist import finished for ' . $schoolYear . ' / ' . $semester . '. Previous rows replaced: ' . $summary['masterlist_rows_replaced'] . '. New rows saved: ' . $summary['masterlist_rows_upserted'] . '. Linked to company records: ' . $summary['masterlist_rows_linked_to_company'] . '.';
        $message .= ' Internship records created: ' . (int)($summary['internships_created'] ?? 0) . '. Internship records synced: ' . (int)($summary['internships_synced'] ?? 0) . '.';
        if (!empty($errors)) {
            $message .= ' Some rows need review.';
        }
        return $summary['masterlist_rows_upserted'] > 0;
    }

    if (empty($studentsRows)) {
        $message = 'Workbook must contain either a masterlist sheet with columns like STUDENT NAME/COMPANY/SUPERVISOR NAME, or a sheet named Students/student_database.';
        return false;
    }

    $summary = [
        'users_upserted' => 0,
        'students_upserted' => 0,
        'documents_inserted' => 0,
        'documents_updated' => 0,
        'internships_created' => 0,
        'internships_synced' => 0,
    ];
    $seenStudentCodes = [];
    foreach ($studentsRows as $index => $row) {
        $studentCode = trim((string)($row['student_id'] ?? ''));
        if ($studentCode !== '') {
            $dupeKey = strtolower($studentCode);
            if (isset($seenStudentCodes[$dupeKey])) {
                $errors[] = 'Students row ' . ($index + 2) . ': duplicate student_id ' . $studentCode . ' (skipped).';
                continue;
            }
            $seenStudentCodes[$dupeKey] = true;
        }

        $email = trim((string)($row['email'] ?? ''));
        $existingStudent = null;
        if ($studentCode !== '') {
            $existingStudent = students_excel_find_student($mysqli, $studentCode, $email, 0);
            if ($existingStudent) {
                $errors[] = 'Students row ' . ($index + 2) . ': duplicate student_id ' . $studentCode . ' found in database. Existing record will be updated.';
            }
        }

        $rowError = '';
        $preferredUserId = (int)($existingStudent['user_id'] ?? 0);
        $userId = students_excel_upsert_user($mysqli, $row, $rowError, $preferredUserId);
        if ($userId <= 0) {
            $errors[] = 'Students row ' . ($index + 2) . ': ' . $rowError;
            continue;
        }
        $summary['users_upserted']++;

        $studentPk = students_excel_upsert_student($mysqli, $row, $userId, $rowError);
        if ($studentPk <= 0) {
            $errors[] = 'Students row ' . ($index + 2) . ': ' . $rowError;
            continue;
        }
        $summary['students_upserted']++;

        $studentStmt = $mysqli->prepare("SELECT s.id, s.first_name, s.middle_name, s.last_name, s.assignment_track, s.internal_total_hours, s.external_total_hours,
                COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '') AS section_name
            FROM students s
            LEFT JOIN sections sec ON sec.id = s.section_id
            WHERE s.id = ? LIMIT 1");
        if ($studentStmt) {
            $studentStmt->bind_param('i', $studentPk);
            $studentStmt->execute();
            $studentRow = $studentStmt->get_result()->fetch_assoc();
            $studentStmt->close();

            if (is_array($studentRow) && students_excel_table_exists($mysqli, 'ojt_masterlist')) {
                $candidateKeys = students_excel_student_lookup_keys($studentRow);
                if ($candidateKeys !== []) {
                    $in = implode(',', array_fill(0, count($candidateKeys), '?'));
                    $masterSql = "SELECT school_year, semester, student_lookup_key, section, company_name, supervisor_name, supervisor_position, status
                        FROM ojt_masterlist
                        WHERE student_lookup_key IN ({$in})
                        ORDER BY updated_at DESC, id DESC
                        LIMIT 1";
                    $masterStmt = $mysqli->prepare($masterSql);
                    if ($masterStmt) {
                        $masterTypes = str_repeat('s', count($candidateKeys));
                        students_excel_bind_dynamic($masterStmt, $masterTypes, $candidateKeys);
                        $masterStmt->execute();
                        $masterRow = $masterStmt->get_result()->fetch_assoc();
                        $masterStmt->close();
                        if (is_array($masterRow) && !students_excel_sync_internship_from_masterlist($mysqli, $studentRow, $masterRow, $summary)) {
                            $errors[] = 'Students row ' . ($index + 2) . ': unable to sync internship from matching masterlist row.';
                        }
                    }
                }
            }
        }
    }

    if (!empty($documentsRows)) {
        students_excel_import_documents($mysqli, $documentsRows, $summary, $errors);
    }

    $message = 'Excel import finished. Users upserted: ' . $summary['users_upserted'] . '. Students upserted: ' . $summary['students_upserted'] . '.';
    $message .= ' Internship records created: ' . (int)($summary['internships_created'] ?? 0) . '. Internship records synced: ' . (int)($summary['internships_synced'] ?? 0) . '.';
    if (!empty($documentsRows)) {
        $message .= ' Documents inserted: ' . $summary['documents_inserted'] . '. Documents updated: ' . $summary['documents_updated'] . '.';
    }
    if (!empty($errors)) {
        $message .= ' Some rows need review.';
    }
    return $summary['students_upserted'] > 0;
}

function students_excel_masterlist_year_options(mysqli $mysqli): array
{
    $years = [];
    $errorMessage = '';
    if (!students_excel_ensure_masterlist_tables($mysqli, $errorMessage)) {
        return $years;
    }

    $res = $mysqli->query("SELECT school_year, COUNT(*) AS row_count FROM ojt_masterlist GROUP BY school_year ORDER BY school_year DESC");
    if (!$res) {
        return $years;
    }

    while ($row = $res->fetch_assoc()) {
        $years[] = [
            'school_year' => (string)($row['school_year'] ?? ''),
            'row_count' => (int)($row['row_count'] ?? 0),
        ];
    }

    return $years;
}

function students_excel_masterlist_semester_options(mysqli $mysqli, string $schoolYear): array
{
    $semesters = [];
    $errorMessage = '';
    if ($schoolYear === '' || !students_excel_ensure_masterlist_tables($mysqli, $errorMessage)) {
        return $semesters;
    }

    $stmt = $mysqli->prepare("SELECT semester, COUNT(*) AS row_count
        FROM ojt_masterlist
        WHERE school_year = ?
        GROUP BY semester
        ORDER BY semester ASC");
    if (!$stmt) {
        return $semesters;
    }

    $stmt->bind_param('s', $schoolYear);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $semesters[] = [
            'semester' => (string)($row['semester'] ?? ''),
            'row_count' => (int)($row['row_count'] ?? 0),
        ];
    }
    $stmt->close();

    return $semesters;
}

function students_excel_masterlist_review(mysqli $mysqli, string $schoolYear, string $semesterFilter = '', string $sectionFilter = ''): array
{
    $result = [
        'school_year' => $schoolYear,
        'selected_semester' => $semesterFilter,
        'selected_section' => $sectionFilter,
        'totals' => ['rows' => 0, 'companies' => 0, 'sections' => 0, 'ongoing' => 0, 'semesters' => 0],
        'rows' => [],
        'sections' => [],
        'companies' => [],
    ];

    $errorMessage = '';
    if ($schoolYear === '' || !students_excel_ensure_masterlist_tables($mysqli, $errorMessage)) {
        return $result;
    }

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total_rows,
            COUNT(DISTINCT COALESCE(NULLIF(company_name, ''), CONCAT('company:', company_id))) AS total_companies,
            COUNT(DISTINCT NULLIF(section, '')) AS total_sections,
            COUNT(DISTINCT COALESCE(NULLIF(semester, ''), 'Unspecified')) AS total_semesters,
            SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'ongoing' THEN 1 ELSE 0 END) AS total_ongoing
        FROM ojt_masterlist
        WHERE school_year = ?" . ($semesterFilter !== '' ? " AND semester = ?" : ""));
    if ($stmt) {
        if ($semesterFilter !== '') {
            $stmt->bind_param('ss', $schoolYear, $semesterFilter);
        } else {
            $stmt->bind_param('s', $schoolYear);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($row)) {
            $result['totals'] = [
                'rows' => (int)($row['total_rows'] ?? 0),
                'companies' => (int)($row['total_companies'] ?? 0),
                'sections' => (int)($row['total_sections'] ?? 0),
                'semesters' => (int)($row['total_semesters'] ?? 0),
                'ongoing' => (int)($row['total_ongoing'] ?? 0),
            ];
        }
    }

    $rowsSql = "SELECT student_name, contact_no, semester, section, company_name, company_address, supervisor_name, supervisor_position, company_representative, status
        FROM ojt_masterlist
        WHERE school_year = ?";
    if ($semesterFilter !== '') {
        $rowsSql .= " AND semester = ?";
    }
    if ($sectionFilter !== '') {
        $rowsSql .= " AND section = ?";
    }
    $rowsSql .= " ORDER BY semester ASC, section ASC, student_name ASC";

    $stmt = $mysqli->prepare($rowsSql);
    if ($stmt) {
        if ($semesterFilter !== '' && $sectionFilter !== '') {
            $stmt->bind_param('sss', $schoolYear, $semesterFilter, $sectionFilter);
        } elseif ($semesterFilter !== '') {
            $stmt->bind_param('ss', $schoolYear, $semesterFilter);
        } elseif ($sectionFilter !== '') {
            $stmt->bind_param('ss', $schoolYear, $sectionFilter);
        } else {
            $stmt->bind_param('s', $schoolYear);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $result['rows'][] = $row;
        }
        $stmt->close();
    }

    $stmt = $mysqli->prepare("SELECT section, COUNT(*) AS row_count
        FROM ojt_masterlist
        WHERE school_year = ?
        " . ($semesterFilter !== '' ? "AND semester = ?" : "") . "
        GROUP BY section
        ORDER BY row_count DESC, section ASC
        LIMIT 12");
    if ($stmt) {
        if ($semesterFilter !== '') {
            $stmt->bind_param('ss', $schoolYear, $semesterFilter);
        } else {
            $stmt->bind_param('s', $schoolYear);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $result['sections'][] = $row;
        }
        $stmt->close();
    }

    $stmt = $mysqli->prepare("SELECT company_name, supervisor_name, supervisor_position, COUNT(*) AS student_count
        FROM ojt_masterlist
        WHERE school_year = ?
        " . ($semesterFilter !== '' ? "AND semester = ?" : "") . "
        GROUP BY company_name, supervisor_name, supervisor_position
        ORDER BY student_count DESC, company_name ASC
        LIMIT 12");
    if ($stmt) {
        if ($semesterFilter !== '') {
            $stmt->bind_param('ss', $schoolYear, $semesterFilter);
        } else {
            $stmt->bind_param('s', $schoolYear);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $result['companies'][] = $row;
        }
        $stmt->close();
    }

    return $result;
}

$statusType = '';
$statusMessage = '';
$statusDetails = [];
$csrfToken = students_excel_csrf_token();
$selectedReviewYear = students_excel_normalize_school_year((string)($_GET['review_year'] ?? ''));
$selectedReviewSemester = students_excel_normalize_semester((string)($_GET['review_semester'] ?? ''));
$selectedReviewSection = trim((string)($_GET['review_section'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedCsrf)) {
        $statusType = 'danger';
        $statusMessage = 'Invalid security token. Refresh the page and try again.';
    } elseif (!isset($_FILES['excel_file']) || !is_array($_FILES['excel_file'])) {
        $statusType = 'danger';
        $statusMessage = 'Choose an Excel workbook first.';
    } else {
        $uploadError = (int)($_FILES['excel_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $statusType = 'danger';
            $statusMessage = 'Excel upload failed. Error code: ' . $uploadError;
        } else {
            $tmpName = (string)($_FILES['excel_file']['tmp_name'] ?? '');
            $isValidTempFile = $tmpName !== '' && (is_uploaded_file($tmpName) || is_file($tmpName));
            if (!$isValidTempFile) {
                $statusType = 'danger';
                $statusMessage = 'Uploaded Excel file is invalid.';
            } else {
                $summary = [];
                $errors = [];
                $message = '';
                $originalName = (string)($_FILES['excel_file']['name'] ?? 'uploaded-workbook.xlsx');
                $overrideSchoolYear = students_excel_normalize_school_year((string)($_POST['school_year_override'] ?? ''));
                $overrideSemester = students_excel_normalize_semester((string)($_POST['semester_override'] ?? ''));
                if ($overrideSchoolYear !== '') {
                    $originalName = $overrideSchoolYear;
                }
                $ok = students_excel_import_workbook($conn, $tmpName, $originalName, $overrideSemester, $summary, $errors, $message);
                $statusType = $ok ? 'success' : 'danger';
                $statusMessage = $message !== '' ? $message : ($ok ? 'Excel import completed.' : 'Excel import failed.');
                if ($overrideSchoolYear !== '') {
                    $selectedReviewYear = $overrideSchoolYear;
                } elseif (preg_match('/school year (\d{4}-\d{4})/i', $statusMessage, $matches)) {
                    $selectedReviewYear = $matches[1];
                }
                if ($overrideSemester !== '') {
                    $selectedReviewSemester = $overrideSemester;
                }
                foreach ($summary as $label => $value) {
                    $statusDetails[] = ucwords(str_replace('_', ' ', (string)$label)) . ': ' . (int)$value;
                }
                foreach (array_slice($errors, 0, 8) as $line) {
                    $statusDetails[] = $line;
                }
                if (count($errors) > 8) {
                    $statusDetails[] = 'Additional row issues not shown: ' . (count($errors) - 8);
                }
            }
        }
    }
}

$masterlistYearOptions = students_excel_masterlist_year_options($conn);
if ($selectedReviewYear === '' && !empty($masterlistYearOptions)) {
    $selectedReviewYear = (string)$masterlistYearOptions[0]['school_year'];
}
$masterlistSemesterOptions = students_excel_masterlist_semester_options($conn, $selectedReviewYear);
$availableSemesters = array_values(array_filter(array_map(
    static fn(array $semesterRow): string => trim((string)($semesterRow['semester'] ?? '')),
    $masterlistSemesterOptions
)));
if ($selectedReviewSemester !== '' && !in_array($selectedReviewSemester, $availableSemesters, true)) {
    $selectedReviewSemester = '';
}
if ($selectedReviewSemester === '' && count($masterlistSemesterOptions) === 1) {
    $selectedReviewSemester = trim((string)($masterlistSemesterOptions[0]['semester'] ?? ''));
}
$masterlistReview = students_excel_masterlist_review($conn, $selectedReviewYear, $selectedReviewSemester);
$availableSections = array_values(array_filter(array_map(
    static fn(array $sectionRow): string => trim((string)($sectionRow['section'] ?? '')),
    $masterlistReview['sections']
)));
if ($selectedReviewSection !== '' && !in_array($selectedReviewSection, $availableSections, true)) {
    $selectedReviewSection = '';
    $masterlistReview = students_excel_masterlist_review($conn, $selectedReviewYear, $selectedReviewSemester);
    $availableSections = array_values(array_filter(array_map(
        static fn(array $sectionRow): string => trim((string)($sectionRow['section'] ?? '')),
        $masterlistReview['sections']
    )));
}

$page_title = 'BioTern || Excel Student Database Import';
$page_body_class = 'page-transfer-tools page-excel-import';
$page_styles = [
    'assets/css/modules/pages/page-transfer-tools.css',
];
$page_scripts = [
    'assets/js/modules/pages/transfer-tools-runtime.js',
];
include dirname(__DIR__) . '/includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
    <div class="page-header">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title">
                <h5 class="m-b-10">Excel Import</h5>
            </div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="import-sql.php">Data Transfer</a></li>
                <li class="breadcrumb-item">Excel Import</li>
            </ul>
        </div>
        <div class="page-header-right ms-auto excel-import-page-header-actions">
            <div class="page-header-right-items">
                <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                    <a href="import-sql.php" class="btn btn-light-brand">
                        <i class="feather-database me-2"></i>
                        <span>Data Transfer</span>
                    </a>
                    <a href="students-edit.php" class="btn btn-outline-secondary">
                        <i class="feather-users me-2"></i>
                        <span>Review Students</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
<div class="container-xxl py-4 excel-import-page-wrap">
    <div class="excel-import-shell">
        <?php if ($statusType !== ''): ?><div class="alert alert-<?php echo $statusType === 'success' ? 'success' : 'danger'; ?> mb-4"><strong><?php echo $statusType === 'success' ? 'Success:' : 'Import error:'; ?></strong> <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($statusDetails)): ?><div class="small mt-2"><?php foreach ($statusDetails as $detail): ?><div><?php echo htmlspecialchars((string)$detail, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?></div><?php endif; ?>
        <div class="card excel-import-hero mb-4"><div class="card-body p-4 p-md-5"><span class="excel-import-badge">Separate Excel Workflow</span><h2 class="mt-3 mb-2 text-white">Import either a student workbook or the teacher OJT masterlist</h2><p class="mb-0 text-white-50">This page now supports the existing Students/Documents workbook and the teacher masterlist format so you can centralize OJT data on localhost without replacing the whole SQL database.</p><div class="excel-import-hero-actions"><a href="import-sql.php" class="btn btn-light-brand">Back to Data Transfer</a><a href="students-edit.php" class="btn btn-light">Review Students</a></div></div></div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card excel-import-card"><div class="card-body p-4 p-md-5"><div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3"><div><span class="excel-import-badge">Import Workbook</span><h4 class="mt-3 mb-2">Teacher masterlist or Students/Documents workbook</h4><p class="text-muted mb-0">Upload the teacher masterlist with columns like <code>STUDENT NAME</code>, <code>COMPANY</code>, <code>SUPERVISOR NAME</code>, and <code>STATUS</code>, or use the older <code>Students</code> plus optional <code>Documents</code> workbook.</p></div><a href="import-sql.php" class="btn btn-light">Back to SQL Tools</a></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><div class="mb-3"><label for="excel_file" class="form-label fw-semibold">Upload Excel workbook</label><input class="form-control" type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"><div class="form-text">The teacher file can be a single-sheet workbook. The older import still accepts <code>Students</code> and optional <code>Documents</code>.</div></div><div class="mb-3"><label for="school_year_override" class="form-label fw-semibold">School year override</label><input class="form-control" type="text" id="school_year_override" name="school_year_override" placeholder="e.g. 2025-2026 or 25-26"><div class="form-text">Leave blank to infer from the filename. Use this if the file name does not clearly include the school year.</div></div><div class="mb-3"><label for="semester_override" class="form-label fw-semibold">Semester override</label><select class="form-select" id="semester_override" name="semester_override"><option value="">Infer from file or sheet</option><option value="1st Semester">1st Semester</option><option value="2nd Semester">2nd Semester</option><option value="Summer">Summer</option></select><div class="form-text">Use this when the uploaded masterlist belongs to a specific semester. Imports now refresh by school year and semester together.</div></div><div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary">Import Excel Database</button><a href="students-edit.php" class="btn btn-outline-primary">Review Students</a></div></form></div></div>
            </div>
            <div class="col-lg-4">
                <div class="card excel-import-card mb-4"><div class="card-body"><span class="excel-import-badge">Workbook Rules</span><div class="excel-import-step mt-3"><h6>Teacher masterlist supported</h6><p class="text-muted mb-0">Single-sheet masterlists are imported into centralized tables <code>ojt_masterlist</code> and <code>ojt_partner_companies</code>. They do not create rows in <code>students</code>, but now automatically create/sync <code>internships</code> records when a matching student account already exists.</p></div><div class="excel-import-step mt-3"><h6>Older student workbook still works</h6><p class="text-muted mb-0">For direct account/student imports, use <code>Students</code> and optional <code>Documents</code> with the original columns. Student imports now also attempt to sync a matching masterlist row into <code>internships</code>.</p></div></div></div>
                <div class="card excel-import-card"><div class="card-body"><span class="excel-import-badge">Localhost Review</span><h5 class="mt-3 mb-2">Open this on localhost</h5><p class="text-muted mb-2">Review this tool locally before pushing changes.</p><div class="small"><div><code>http://localhost/BioTern/BioTern/import-students-excel.php</code></div></div></div></div>
            </div>
        </div>
        <div class="card excel-import-card mt-4"><div class="card-body p-4 p-md-5"><span class="excel-import-badge">Suggested Columns</span><div class="row g-4 mt-1"><div class="col-md-6"><h6>Teacher masterlist columns</h6><p class="text-muted mb-0"><code>student_name</code>, <code>contact_no</code>, <code>section</code>, <code>company</code>, <code>address</code>, <code>supervisor_name</code>, <code>position</code>, <code>company_representative</code>, <code>status</code>. The importer normalizes these and saves them into master tables for OJT document prefilling only.</p></div><div class="col-md-6"><h6>Older Students/Documents workbook</h6><p class="text-muted mb-0"><code>student_id</code>, <code>first_name</code>, <code>last_name</code>, <code>email</code>, <code>course_id</code>, plus the optional document metadata columns like <code>document_type</code>, <code>file_name</code>, and <code>file_path</code>.</p></div></div></div></div>
        <div class="card excel-import-card mt-4" data-excel-review-root data-selected-section="<?php echo htmlspecialchars($selectedReviewSection, ENT_QUOTES, 'UTF-8'); ?>"><div class="card-body p-4 p-md-5"><div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4"><div><span class="excel-import-badge">Year Review</span><h4 class="mt-3 mb-1">Imported masterlist by school year</h4><p class="text-muted mb-0">Check what is currently stored before or after each import.</p></div><form method="get" class="d-flex flex-wrap gap-2 align-items-end"><div><label for="review_year" class="form-label fw-semibold mb-1">Review school year</label><select class="form-select" id="review_year" name="review_year"><?php if (empty($masterlistYearOptions)): ?><option value="">No imported year yet</option><?php else: ?><?php foreach ($masterlistYearOptions as $yearOption): ?><option value="<?php echo htmlspecialchars((string)$yearOption['school_year'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedReviewYear === (string)$yearOption['school_year'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$yearOption['school_year'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)$yearOption['row_count']; ?> rows)</option><?php endforeach; ?><?php endif; ?></select></div><div><label for="review_semester" class="form-label fw-semibold mb-1">Semester</label><select class="form-select" id="review_semester" name="review_semester"><option value="">All semesters</option><?php foreach ($masterlistSemesterOptions as $semesterOption): ?><option value="<?php echo htmlspecialchars((string)$semesterOption['semester'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedReviewSemester === (string)$semesterOption['semester'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$semesterOption['semester'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)$semesterOption['row_count']; ?> rows)</option><?php endforeach; ?></select></div><button type="submit" class="btn btn-outline-primary">Refresh Review</button></form></div><?php if ($selectedReviewYear === '' || (int)$masterlistReview['totals']['rows'] <= 0): ?><div class="alert alert-warning mb-0">No masterlist rows found yet for review. Import the teacher workbook first.</div><?php else: ?><div class="row g-3 mb-4"><div class="col-md-3"><div class="excel-import-stat"><div class="text-muted small">Rows</div><div class="excel-import-stat-value"><?php echo (int)$masterlistReview['totals']['rows']; ?></div></div></div><div class="col-md-3"><div class="excel-import-stat"><div class="text-muted small">Companies</div><div class="excel-import-stat-value"><?php echo (int)$masterlistReview['totals']['companies']; ?></div></div></div><div class="col-md-3"><div class="excel-import-stat"><div class="text-muted small">Sections</div><div class="excel-import-stat-value"><?php echo (int)$masterlistReview['totals']['sections']; ?></div></div></div><div class="col-md-3"><div class="excel-import-stat"><div class="text-muted small">Ongoing</div><div class="excel-import-stat-value"><?php echo (int)$masterlistReview['totals']['ongoing']; ?></div></div></div></div><div class="excel-import-review-grid mb-4"><div><h6>Sections</h6><p class="text-muted excel-import-filter-note mb-3">Click a section to change only the student rows below.</p><div class="d-grid gap-2" id="excelImportSectionList"><button type="button" class="excel-import-section-link<?php echo $selectedReviewSection === '' ? ' active' : ''; ?>" data-excel-section-control data-section=""><span>All sections</span><strong><?php echo (int)$masterlistReview['totals']['rows']; ?></strong></button><?php foreach ($masterlistReview['sections'] as $sectionRow): ?><?php $sectionName = trim((string)($sectionRow['section'] ?? '')); if ($sectionName === '') { continue; } ?><button type="button" class="excel-import-section-link<?php echo $selectedReviewSection === $sectionName ? ' active' : ''; ?>" data-excel-section-control data-section="<?php echo htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8'); ?>"><span><?php echo htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8'); ?></span><strong><?php echo (int)($sectionRow['row_count'] ?? 0); ?></strong></button><?php endforeach; ?></div></div><div class="excel-import-review-table-wrap"><h6>Companies</h6><div class="table-responsive"><table class="table table-sm align-middle excel-import-table"><thead><tr><th>Company</th><th>Supervisor</th><th>Position</th><th class="text-end">Students</th></tr></thead><tbody><?php foreach ($masterlistReview['companies'] as $companyRow): ?><tr><td><?php echo htmlspecialchars((string)($companyRow['company_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($companyRow['supervisor_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($companyRow['supervisor_position'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td class="text-end"><?php echo (int)($companyRow['student_count'] ?? 0); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div><div class="mt-4"><div class="excel-import-student-toolbar mb-3"><div><h6 class="mb-1" id="excelImportRowsHeading"><?php echo $selectedReviewSection !== '' ? 'Students in ' . htmlspecialchars($selectedReviewSection, ENT_QUOTES, 'UTF-8') : 'All imported rows'; ?></h6><p class="text-muted mb-0" id="excelImportRowsSubheading"><?php echo $selectedReviewSection !== '' ? 'Showing the full student list for the selected section in ' . htmlspecialchars($selectedReviewSemester !== '' ? $selectedReviewSemester : 'the selected semester', ENT_QUOTES, 'UTF-8') . '.' : 'Showing the full imported student list for this school year' . ($selectedReviewSemester !== '' ? ' and semester.' : '.'); ?></p></div><div class="excel-import-chip-row" id="excelImportSectionChips"><button type="button" class="excel-import-chip excel-import-chip-clear<?php echo $selectedReviewSection === '' ? ' active' : ''; ?>" data-excel-section-control data-section="">Show all sections</button><?php foreach ($masterlistReview['sections'] as $sectionRow): ?><?php $sectionName = trim((string)($sectionRow['section'] ?? '')); if ($sectionName === '') { continue; } ?><button type="button" class="excel-import-chip<?php echo $selectedReviewSection === $sectionName ? ' active' : ''; ?>" data-excel-section-control data-section="<?php echo htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($sectionName, ENT_QUOTES, 'UTF-8'); ?><span><?php echo (int)($sectionRow['row_count'] ?? 0); ?></span></button><?php endforeach; ?></div></div><div class="excel-import-results-empty" id="excelImportEmptyState">No students found for this section in the selected school year and semester.</div><div class="table-responsive"><table class="table table-sm align-middle excel-import-table" id="excelImportRowsTable"><thead><tr><th>Student</th><th>Contact</th><th>Semester</th><th>Section</th><th>Company</th><th>Supervisor</th><th>Status</th></tr></thead><tbody><?php foreach ($masterlistReview['rows'] as $reviewRow): ?><?php $rowSection = trim((string)($reviewRow['section'] ?? '')); ?><tr data-section="<?php echo htmlspecialchars($rowSection, ENT_QUOTES, 'UTF-8'); ?>"><td><?php echo htmlspecialchars((string)($reviewRow['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($reviewRow['contact_no'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($reviewRow['semester'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($reviewRow['section'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($reviewRow['company_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(trim((string)($reviewRow['supervisor_name'] ?? '') . ((string)($reviewRow['supervisor_position'] ?? '') !== '' ? ' / ' . (string)$reviewRow['supervisor_position'] : '')) ?: '-', ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($reviewRow['status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?></div></div>
    </div>
</div>
</div>
</main>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>

