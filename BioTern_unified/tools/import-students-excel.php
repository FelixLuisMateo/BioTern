<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: auth-login-cover.php?next=tools/import-students-excel.php');
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

function students_excel_username(string $firstName, string $lastName, string $email): string
{
    $base = $email !== '' ? (string)strstr($email, '@', true) : trim($firstName . $lastName);
    $base = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)$base);
    if ($base === '') {
        $base = 'student' . time();
    }
    return substr((string)$base, 0, 255);
}

function students_excel_load_workbook(string $path, string &$errorMessage): array
{
    $errorMessage = '';
    try {
        $spreadsheet = IOFactory::load($path);
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
        UNIQUE KEY uniq_masterlist_student (school_year, student_lookup_key, section),
        KEY idx_masterlist_company (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!$mysqli->query($masterlistSql)) {
        $errorMessage = 'Failed to ensure ojt_masterlist table: ' . $mysqli->error;
        return false;
    }

    return true;
}

function students_excel_upsert_partner_company(mysqli $mysqli, array $row, string &$errorMessage): int
{
    $errorMessage = '';
    $companyName = trim((string)($row['company'] ?? ''));
    $companyAddress = trim((string)($row['address'] ?? ''));
    $supervisorName = trim((string)($row['supervisor_name'] ?? ''));
    $supervisorPosition = trim((string)($row['position'] ?? ''));
    $companyRepresentative = trim((string)($row['company_representative'] ?? ''));
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

function students_excel_import_masterlist(mysqli $mysqli, string $sheetName, array $rows, string $schoolYear, string $sourceWorkbook, array &$summary, array &$errors): void
{
    $tableError = '';
    if (!students_excel_ensure_masterlist_tables($mysqli, $tableError)) {
        $errors[] = $tableError;
        return;
    }

    $deleteStmt = $mysqli->prepare('DELETE FROM ojt_masterlist WHERE school_year = ?');
    if (!$deleteStmt) {
        $errors[] = 'Failed to prepare school-year refresh for masterlist: ' . $mysqli->error;
        return;
    }

    $deleteStmt->bind_param('s', $schoolYear);
    if (!$deleteStmt->execute()) {
        $errors[] = 'Failed to clear existing masterlist rows for school year ' . $schoolYear . ': ' . $mysqli->error;
        $deleteStmt->close();
        return;
    }
    $summary['masterlist_rows_replaced'] = (int)$deleteStmt->affected_rows;
    $deleteStmt->close();

    foreach ($rows as $index => $row) {
        $studentName = trim((string)($row['student_name'] ?? ''));
        $contactNo = trim((string)($row['contact_no'] ?? ''));
        $section = trim((string)($row['section'] ?? ''));
        $status = trim((string)($row['status'] ?? ''));
        $companyName = trim((string)($row['company'] ?? ''));
        $companyAddress = trim((string)($row['address'] ?? ''));
        $supervisorName = trim((string)($row['supervisor_name'] ?? ''));
        $supervisorPosition = trim((string)($row['position'] ?? ''));
        $companyRepresentative = trim((string)($row['company_representative'] ?? ''));

        if ($studentName === '' && $companyName === '' && $section === '') {
            continue;
        }

        if ($studentName === '') {
            $errors[] = 'Masterlist row ' . ($index + 2) . ': missing student name.';
            continue;
        }

        $studentLookupKey = students_excel_lookup_key($studentName);
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
                school_year, source_workbook, source_sheet, source_row_number, student_lookup_key, student_name,
                contact_no, section, company_id, company_name, company_address, supervisor_name, supervisor_position,
                company_representative, status, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
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
            'sssissssissssss',
            $schoolYear,
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

function students_excel_upsert_user(mysqli $mysqli, array $row, string &$errorMessage): int
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
    $passwordHash = students_excel_password((string)($row['password'] ?? ''));
    $existing = students_excel_find_user($mysqli, $email, $username);

    if ($existing) {
        $userId = (int)($existing['id'] ?? 0);
        $stmt = $mysqli->prepare("UPDATE users SET name = ?, username = ?, email = ?, password = ?, role = 'student', is_active = ?, profile_picture = ?, application_status = 'approved', updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            $errorMessage = 'Failed to prepare user update: ' . $mysqli->error;
            return 0;
        }
        $stmt->bind_param('ssssisi', $fullName, $username, $email, $passwordHash, $isActive, $profilePicture, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $errorMessage = 'Failed to update user: ' . $mysqli->error;
            return 0;
        }
        return $userId;
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
    $studentCode = trim((string)($row['student_id'] ?? ''));
    $firstName = trim((string)($row['first_name'] ?? ''));
    $lastName = trim((string)($row['last_name'] ?? ''));
    $middleName = trim((string)($row['middle_name'] ?? ''));
    $username = trim((string)($row['username'] ?? ''));
    $passwordHash = students_excel_password((string)($row['password'] ?? ''));
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
        $stmt = $mysqli->prepare("UPDATE students SET user_id = ?, course_id = ?, student_id = ?, first_name = ?, last_name = ?, middle_name = ?, username = ?, password = ?, email = ?, bio = ?, department_id = ?, section_id = ?, supervisor_name = ?, coordinator_name = ?, supervisor_id = NULLIF(?, 0), coordinator_id = NULLIF(?, 0), phone = ?, date_of_birth = NULLIF(?, ''), gender = NULLIF(?, ''), address = ?, internal_total_hours = ?, internal_total_hours_remaining = ?, external_total_hours = ?, external_total_hours_remaining = ?, emergency_contact = ?, profile_picture = ?, status = ?, school_year = ?, assignment_track = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) {
            $errorMessage = 'Failed to prepare student update: ' . $mysqli->error;
            return 0;
        }
        $stmt->bind_param('iissssssssiissiisssiiiissssi', $userId, $courseId, $studentCode, $firstName, $lastName, $middleName, $username, $passwordHash, $email, $bio, $departmentId, $sectionId, $supervisorName, $coordinatorName, $supervisorId, $coordinatorId, $phone, $dateOfBirth, $gender, $address, $internalTotalHours, $internalRemaining, $externalTotalHours, $externalRemaining, $emergencyContact, $profilePicture, $status, $schoolYear, $assignmentTrack, $studentPk);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            $errorMessage = 'Failed to update student: ' . $mysqli->error;
            return 0;
        }
        return $studentPk;
    }

    $stmt = $mysqli->prepare("INSERT INTO students (user_id, course_id, student_id, first_name, last_name, middle_name, username, password, email, bio, department_id, section_id, supervisor_name, coordinator_name, supervisor_id, coordinator_id, phone, date_of_birth, gender, address, internal_total_hours, internal_total_hours_remaining, external_total_hours, external_total_hours_remaining, emergency_contact, profile_picture, status, school_year, assignment_track, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    if (!$stmt) {
        $errorMessage = 'Failed to prepare student insert: ' . $mysqli->error;
        return 0;
    }
    $stmt->bind_param('iissssssssiissiisssiiiissss', $userId, $courseId, $studentCode, $firstName, $lastName, $middleName, $username, $passwordHash, $email, $bio, $departmentId, $sectionId, $supervisorName, $coordinatorName, $supervisorId, $coordinatorId, $phone, $dateOfBirth, $gender, $address, $internalTotalHours, $internalRemaining, $externalTotalHours, $externalRemaining, $emergencyContact, $profilePicture, $status, $schoolYear, $assignmentTrack);
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

function students_excel_import_workbook(mysqli $mysqli, string $path, string $sourceWorkbook, array &$summary, array &$errors, string &$message): bool
{
    $loadError = '';
    $sheets = students_excel_load_workbook($path, $loadError);
    if ($loadError !== '') {
        $message = $loadError;
        return false;
    }

    $studentsRows = $sheets['students'] ?? ($sheets['student_database'] ?? []);
    $documentsRows = $sheets['documents'] ?? [];
    $masterlistSheetKey = '';
    $masterlistRows = [];
    $masterlistRequiredHeaders = ['student_name', 'contact_no', 'section', 'company', 'address', 'supervisor_name', 'position', 'company_representative', 'status'];

    foreach ($sheets as $sheetKey => $rows) {
        if (students_excel_has_headers($rows, $masterlistRequiredHeaders)) {
            $masterlistSheetKey = (string)$sheetKey;
            $masterlistRows = $rows;
            break;
        }
    }

    if (!empty($masterlistRows)) {
        $summary = ['masterlist_rows_replaced' => 0, 'masterlist_rows_upserted' => 0, 'masterlist_rows_linked_to_company' => 0];
        $schoolYear = students_excel_infer_school_year($sourceWorkbook);
        students_excel_import_masterlist($mysqli, $masterlistSheetKey, $masterlistRows, $schoolYear, $sourceWorkbook, $summary, $errors);
        $message = 'Masterlist import finished for school year ' . $schoolYear . '. Previous rows replaced: ' . $summary['masterlist_rows_replaced'] . '. New rows saved: ' . $summary['masterlist_rows_upserted'] . '. Linked to company records: ' . $summary['masterlist_rows_linked_to_company'] . '.';
        if (!empty($errors)) {
            $message .= ' Some rows need review.';
        }
        return $summary['masterlist_rows_upserted'] > 0;
    }

    if (empty($studentsRows)) {
        $message = 'Workbook must contain either a masterlist sheet with columns like STUDENT NAME/COMPANY/SUPERVISOR NAME, or a sheet named Students/student_database.';
        return false;
    }

    $summary = ['users_upserted' => 0, 'students_upserted' => 0, 'documents_inserted' => 0, 'documents_updated' => 0];
    foreach ($studentsRows as $index => $row) {
        $rowError = '';
        $userId = students_excel_upsert_user($mysqli, $row, $rowError);
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
    }

    if (!empty($documentsRows)) {
        students_excel_import_documents($mysqli, $documentsRows, $summary, $errors);
    }

    $message = 'Excel import finished. Users upserted: ' . $summary['users_upserted'] . '. Students upserted: ' . $summary['students_upserted'] . '.';
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

function students_excel_masterlist_review(mysqli $mysqli, string $schoolYear): array
{
    $result = [
        'school_year' => $schoolYear,
        'totals' => ['rows' => 0, 'companies' => 0, 'sections' => 0, 'ongoing' => 0],
        'rows' => [],
        'sections' => [],
        'companies' => [],
    ];

    if ($schoolYear === '' || !students_excel_ensure_masterlist_tables($mysqli, $errorMessage)) {
        return $result;
    }

    $stmt = $mysqli->prepare("SELECT COUNT(*) AS total_rows,
            COUNT(DISTINCT COALESCE(NULLIF(company_name, ''), CONCAT('company:', company_id))) AS total_companies,
            COUNT(DISTINCT NULLIF(section, '')) AS total_sections,
            SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'ongoing' THEN 1 ELSE 0 END) AS total_ongoing
        FROM ojt_masterlist
        WHERE school_year = ?");
    if ($stmt) {
        $stmt->bind_param('s', $schoolYear);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($row)) {
            $result['totals'] = [
                'rows' => (int)($row['total_rows'] ?? 0),
                'companies' => (int)($row['total_companies'] ?? 0),
                'sections' => (int)($row['total_sections'] ?? 0),
                'ongoing' => (int)($row['total_ongoing'] ?? 0),
            ];
        }
    }

    $stmt = $mysqli->prepare("SELECT student_name, contact_no, section, company_name, company_address, supervisor_name, supervisor_position, company_representative, status
        FROM ojt_masterlist
        WHERE school_year = ?
        ORDER BY section ASC, student_name ASC
        LIMIT 40");
    if ($stmt) {
        $stmt->bind_param('s', $schoolYear);
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
        GROUP BY section
        ORDER BY row_count DESC, section ASC
        LIMIT 12");
    if ($stmt) {
        $stmt->bind_param('s', $schoolYear);
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
        GROUP BY company_name, supervisor_name, supervisor_position
        ORDER BY student_count DESC, company_name ASC
        LIMIT 12");
    if ($stmt) {
        $stmt->bind_param('s', $schoolYear);
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
                if ($overrideSchoolYear !== '') {
                    $originalName = $overrideSchoolYear;
                }
                $ok = students_excel_import_workbook($conn, $tmpName, $originalName, $summary, $errors, $message);
                $statusType = $ok ? 'success' : 'danger';
                $statusMessage = $message !== '' ? $message : ($ok ? 'Excel import completed.' : 'Excel import failed.');
                if ($overrideSchoolYear !== '') {
                    $selectedReviewYear = $overrideSchoolYear;
                } elseif (preg_match('/school year (\d{4}-\d{4})/i', $statusMessage, $matches)) {
                    $selectedReviewYear = $matches[1];
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
$masterlistReview = students_excel_masterlist_review($conn, $selectedReviewYear);

$page_title = 'Excel Student Database Import';
include dirname(__DIR__) . '/includes/header.php';
?>
<style>
.excel-import-shell{max-width:1120px;margin:0 auto}.excel-import-hero{background:linear-gradient(135deg,#195b42 0%,#2f855a 50%,#e0f3e8 100%);color:#fff;border:0}.excel-import-card{border:1px solid #e5ebf2;border-radius:1rem;box-shadow:0 18px 40px rgba(16,24,40,.06)}.excel-import-step{border:1px solid #edf1f5;border-radius:1rem;background:#fbfcfe;padding:1rem}.excel-import-badge{display:inline-flex;padding:.35rem .75rem;border-radius:999px;background:#eefbf3;color:#195b42;font-weight:700;font-size:.8rem}.excel-import-stat{border:1px solid #e6edf4;border-radius:1rem;padding:1rem;background:#fbfdff}.excel-import-stat-value{font-size:1.8rem;font-weight:800;color:#195b42;line-height:1}.excel-import-table{font-size:.92rem}.app-skin-dark .excel-import-card{background:#16202b;border-color:#253243;box-shadow:0 18px 40px rgba(0,0,0,.35)}.app-skin-dark .excel-import-card h4,.app-skin-dark .excel-import-card h5,.app-skin-dark .excel-import-card h6,.app-skin-dark .excel-import-card label,.app-skin-dark .excel-import-card p,.app-skin-dark .excel-import-card li,.app-skin-dark .excel-import-card .form-text,.app-skin-dark .excel-import-card .table{color:#eaf2fb}.app-skin-dark .excel-import-card .text-muted{color:#aab8c5!important}.app-skin-dark .excel-import-step,.app-skin-dark .excel-import-stat{background:#1b2632;border-color:#2a394b}.app-skin-dark .excel-import-badge{background:rgba(53,180,104,.14);color:#9ff0be}.app-skin-dark .excel-import-stat-value{color:#9ff0be}.app-skin-dark .form-control,.app-skin-dark .form-select{background:#0f1720;border-color:#334354;color:#edf4fb}
</style>
<div class="container-xxl py-4">
    <div class="excel-import-shell">
        <?php if ($statusType !== ''): ?><div class="alert alert-<?php echo $statusType === 'success' ? 'success' : 'danger'; ?> mb-4"><strong><?php echo $statusType === 'success' ? 'Success:' : 'Import error:'; ?></strong> <?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($statusDetails)): ?><div class="small mt-2"><?php foreach ($statusDetails as $detail): ?><div><?php echo htmlspecialchars((string)$detail, ENT_QUOTES, 'UTF-8'); ?></div><?php endforeach; ?></div><?php endif; ?></div><?php endif; ?>
        <div class="card excel-import-hero mb-4"><div class="card-body p-4 p-md-5"><span class="excel-import-badge">Separate Excel Workflow</span><h2 class="mt-3 mb-2 text-white">Import either a student workbook or the teacher OJT masterlist</h2><p class="mb-0 text-white-50">This page now supports the existing Students/Documents workbook and the teacher masterlist format so you can centralize OJT data on localhost without replacing the whole SQL database.</p></div></div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card excel-import-card"><div class="card-body p-4 p-md-5"><div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3"><div><span class="excel-import-badge">Import Workbook</span><h4 class="mt-3 mb-2">Teacher masterlist or Students/Documents workbook</h4><p class="text-muted mb-0">Upload the teacher masterlist with columns like <code>STUDENT NAME</code>, <code>COMPANY</code>, <code>SUPERVISOR NAME</code>, and <code>STATUS</code>, or use the older <code>Students</code> plus optional <code>Documents</code> workbook.</p></div><a href="import-sql.php" class="btn btn-light">Back to SQL Tools</a></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><div class="mb-3"><label for="excel_file" class="form-label fw-semibold">Upload Excel workbook</label><input class="form-control" type="file" id="excel_file" name="excel_file" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"><div class="form-text">The teacher file can be a single-sheet workbook. The older import still accepts <code>Students</code> and optional <code>Documents</code>.</div></div><div class="mb-3"><label for="school_year_override" class="form-label fw-semibold">School year override</label><input class="form-control" type="text" id="school_year_override" name="school_year_override" placeholder="e.g. 2025-2026 or 25-26"><div class="form-text">Leave blank to infer from the filename. Use this if the file name does not clearly include the school year.</div></div><div class="d-flex flex-wrap gap-2"><button type="submit" class="btn btn-primary">Import Excel Database</button><a href="/BioTern_unified/management/students-edit.php" class="btn btn-outline-primary">Review Students</a></div></form></div></div>
            </div>
            <div class="col-lg-4">
                <div class="card excel-import-card mb-4"><div class="card-body"><span class="excel-import-badge">Workbook Rules</span><div class="excel-import-step mt-3"><h6>Teacher masterlist supported</h6><p class="text-muted mb-0">Single-sheet masterlists are imported into centralized tables <code>ojt_masterlist</code> and <code>ojt_partner_companies</code> using columns like <code>STUDENT NAME</code>, <code>CONTACT NO.</code>, <code>SECTION</code>, <code>COMPANY</code>, <code>ADDRESS</code>, <code>SUPERVISOR NAME</code>, <code>POSITION</code>, <code>COMPANY REPRESENTATIVE</code>, and <code>STATUS</code>.</p></div><div class="excel-import-step mt-3"><h6>Older student workbook still works</h6><p class="text-muted mb-0">For direct account/student imports, use <code>Students</code> and optional <code>Documents</code> with the original columns.</p></div></div></div>
                <div class="card excel-import-card"><div class="card-body"><span class="excel-import-badge">Localhost Review</span><h5 class="mt-3 mb-2">Open this on localhost</h5><p class="text-muted mb-2">Review this tool locally before pushing changes.</p><div class="small"><div><code>http://localhost/BioTern/BioTern_unified/tools/import-students-excel.php</code></div></div></div></div>
            </div>
        </div>
        <div class="card excel-import-card mt-4"><div class="card-body p-4 p-md-5"><span class="excel-import-badge">Suggested Columns</span><div class="row g-4 mt-1"><div class="col-md-6"><h6>Teacher masterlist columns</h6><p class="text-muted mb-0"><code>student_name</code>, <code>contact_no</code>, <code>section</code>, <code>company</code>, <code>address</code>, <code>supervisor_name</code>, <code>position</code>, <code>company_representative</code>, <code>status</code>. The importer normalizes these and saves them into master tables for reuse by the document workflow.</p></div><div class="col-md-6"><h6>Older Students/Documents workbook</h6><p class="text-muted mb-0"><code>student_id</code>, <code>first_name</code>, <code>last_name</code>, <code>email</code>, <code>course_id</code>, plus the optional document metadata columns like <code>document_type</code>, <code>file_name</code>, and <code>file_path</code>.</p></div></div></div></div>
        <div class="card excel-import-card mt-4"><div class="card-body p-4 p-md-5"><div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4"><div><span class="excel-import-badge">Year Review</span><h4 class="mt-3 mb-1">Imported masterlist by school year</h4><p class="text-muted mb-0">Check what is currently stored before or after each import.</p></div><form method="get" class="d-flex flex-wrap gap-2 align-items-end"><div><label for="review_year" class="form-label fw-semibold mb-1">Review school year</label><select class="form-select" id="review_year" name="review_year"><?php if (empty($masterlistYearOptions)): ?><option value="">No imported year yet</option><?php else: ?><?php foreach ($masterlistYearOptions as $yearOption): ?><option value="<?php echo htmlspecialchars((string)$yearOption['school_year'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedReviewYear === (string)$yearOption['school_year'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$yearOption['school_year'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int)$yearOption['row_count']; ?> rows)</option><?php endforeach; ?><?php endif; ?></select></div><button type="submit" class="btn btn-outline-primary">Refresh Review</button></form></div><?php if ($selectedReviewYear === '' || (int)$masterlistReview['totals']['rows'] <= 0): ?><div class="alert alert-warning mb-0">No masterlist rows found yet for review. Import the teacher workbook first.</div><?php else: ?><div class="row g-3 mb-4"><div class="col-md-3"><div class="excel-import-stat"><div class="text-muted small">Rows</div><div class="excel-import-stat-value"><?php echo (int)$masterlistReview['totals']['rows']; ?></div></div></div><div class="col-md-3"><div class="excel-import-stat"><div class="text-muted small">Companies</div><div class="excel-import-stat-value"><?php echo (int)$masterlistReview['totals']['companies']; ?></div></div></div><div class="col-md-3"><div class="excel-import-stat"><div class="text-muted small">Sections</div><div class="excel-import-stat-value"><?php echo (int)$masterlistReview['totals']['sections']; ?></div></div></div><div class="col-md-3"><div class="excel-import-stat"><div class="text-muted small">Ongoing</div><div class="excel-import-stat-value"><?php echo (int)$masterlistReview['totals']['ongoing']; ?></div></div></div></div><div class="row g-4"><div class="col-lg-4"><h6>Sections</h6><div class="table-responsive"><table class="table table-sm align-middle excel-import-table"><thead><tr><th>Section</th><th class="text-end">Rows</th></tr></thead><tbody><?php foreach ($masterlistReview['sections'] as $sectionRow): ?><tr><td><?php echo htmlspecialchars((string)($sectionRow['section'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td class="text-end"><?php echo (int)($sectionRow['row_count'] ?? 0); ?></td></tr><?php endforeach; ?></tbody></table></div></div><div class="col-lg-8"><h6>Companies</h6><div class="table-responsive"><table class="table table-sm align-middle excel-import-table"><thead><tr><th>Company</th><th>Supervisor</th><th>Position</th><th class="text-end">Students</th></tr></thead><tbody><?php foreach ($masterlistReview['companies'] as $companyRow): ?><tr><td><?php echo htmlspecialchars((string)($companyRow['company_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($companyRow['supervisor_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($companyRow['supervisor_position'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td class="text-end"><?php echo (int)($companyRow['student_count'] ?? 0); ?></td></tr><?php endforeach; ?></tbody></table></div></div></div><div class="mt-4"><h6>Sample imported rows</h6><div class="table-responsive"><table class="table table-sm align-middle excel-import-table"><thead><tr><th>Student</th><th>Contact</th><th>Section</th><th>Company</th><th>Supervisor</th><th>Status</th></tr></thead><tbody><?php foreach ($masterlistReview['rows'] as $reviewRow): ?><tr><td><?php echo htmlspecialchars((string)($reviewRow['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($reviewRow['contact_no'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($reviewRow['section'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($reviewRow['company_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(trim((string)($reviewRow['supervisor_name'] ?? '') . ((string)($reviewRow['supervisor_position'] ?? '') !== '' ? ' / ' . (string)$reviewRow['supervisor_position'] : '')) ?: '-', ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string)($reviewRow['status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endforeach; ?></tbody></table></div></div><?php endif; ?></div></div>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
