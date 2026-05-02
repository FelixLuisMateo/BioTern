<?php

require_once __DIR__ . '/company_profiles.php';

function biotern_ojt_masterlist_header_present(array $rows): bool
{
    if ($rows === []) {
        return false;
    }
    $headers = array_keys($rows[0]);
    $groups = [
        ['student_no', 'student_id', 'student_number'],
        ['school_year', 'sy'],
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

    foreach ($groups as $group) {
        $found = false;
        foreach ($group as $header) {
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

function biotern_ojt_masterlist_row_value(array $row, array $keys, string $default = ''): string
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

function biotern_ojt_masterlist_lookup_key(string $value): string
{
    $value = strtolower(trim($value));
    return (string)preg_replace('/[^a-z0-9]+/', '', $value);
}

function biotern_ojt_masterlist_school_year(string $value): string
{
    $value = trim($value);
    if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $value, $matches)) {
        return sprintf('%04d-%04d', (int)$matches[1], (int)$matches[2]);
    }
    if (preg_match('/^(\d{2})\s*-\s*(\d{2})$/', $value, $matches)) {
        return sprintf('%04d-%04d', 2000 + (int)$matches[1], 2000 + (int)$matches[2]);
    }
    $year = (int)date('Y');
    return sprintf('%04d-%04d', $year, $year + 1);
}

function biotern_ojt_masterlist_semester(string $value): string
{
    $compact = preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value)));
    $map = [
        '1' => '1st Semester',
        '1st' => '1st Semester',
        '1stsemester' => '1st Semester',
        'firstsemester' => '1st Semester',
        '2' => '2nd Semester',
        '2nd' => '2nd Semester',
        '2ndsemester' => '2nd Semester',
        'secondsemester' => '2nd Semester',
        'summer' => 'Summer',
    ];
    return $map[$compact] ?? ($compact !== '' ? trim($value) : 'Unspecified');
}

function biotern_ojt_masterlist_ensure(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS ojt_masterlist (
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
        company_representative_position VARCHAR(255) DEFAULT NULL,
        status VARCHAR(100) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_masterlist_student_term (school_year, semester, student_lookup_key, section)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $res = $conn->query("SHOW COLUMNS FROM ojt_masterlist LIKE 'student_no'");
    $hasStudentNo = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    if (!$hasStudentNo) {
        $conn->query("ALTER TABLE ojt_masterlist ADD COLUMN student_no VARCHAR(100) DEFAULT NULL AFTER semester");
    }

    $res = $conn->query("SHOW COLUMNS FROM ojt_masterlist LIKE 'company_representative_position'");
    $hasRepresentativePosition = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    if (!$hasRepresentativePosition) {
        $conn->query("ALTER TABLE ojt_masterlist ADD COLUMN company_representative_position VARCHAR(255) DEFAULT NULL AFTER company_representative");
    }
}

function biotern_ojt_masterlist_import_rows(mysqli $conn, array $rows, string $sourceWorkbook, string $defaultType, array &$errors = []): int
{
    biotern_ojt_masterlist_ensure($conn);
    $imported = 0;

    $stmt = $conn->prepare("INSERT INTO ojt_masterlist (
            school_year, semester, student_no, source_workbook, source_sheet, source_row_number,
            student_lookup_key, student_name, contact_no, section, company_name, company_address,
            supervisor_name, supervisor_position, company_representative, company_representative_position, status, created_at, updated_at
        ) VALUES (?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            student_no = COALESCE(NULLIF(VALUES(student_no), ''), ojt_masterlist.student_no),
            source_workbook = VALUES(source_workbook),
            source_sheet = VALUES(source_sheet),
            source_row_number = VALUES(source_row_number),
            student_name = VALUES(student_name),
            contact_no = VALUES(contact_no),
            company_name = VALUES(company_name),
            company_address = VALUES(company_address),
            supervisor_name = VALUES(supervisor_name),
            supervisor_position = VALUES(supervisor_position),
            company_representative = VALUES(company_representative),
            company_representative_position = VALUES(company_representative_position),
            status = VALUES(status),
            updated_at = NOW()");
    if (!$stmt) {
        $errors[] = 'Unable to prepare masterlist import.';
        return 0;
    }

    foreach ($rows as $index => $row) {
        $studentNo = biotern_ojt_masterlist_row_value($row, ['student_no', 'student_id', 'student_number']);
        $schoolYear = biotern_ojt_masterlist_school_year(biotern_ojt_masterlist_row_value($row, ['school_year', 'sy']));
        $semester = biotern_ojt_masterlist_semester(biotern_ojt_masterlist_row_value($row, ['semester', 'term']));
        $studentName = biotern_ojt_masterlist_row_value($row, ['student_name']);
        $contactNo = biotern_ojt_masterlist_row_value($row, ['contact_no', 'contact_number']);
        $section = biotern_ojt_masterlist_row_value($row, ['section']);
        $companyName = biotern_ojt_masterlist_row_value($row, ['company_name', 'company']);
        $companyAddress = biotern_ojt_masterlist_row_value($row, ['company_address', 'address']);
        $supervisorName = biotern_ojt_masterlist_row_value($row, ['supervisor_name']);
        $supervisorPosition = biotern_ojt_masterlist_row_value($row, ['supervisor_position', 'position']);
        $companyRepresentative = biotern_ojt_masterlist_row_value($row, ['company_representative']);
        $companyRepresentativePosition = biotern_ojt_masterlist_row_value($row, ['company_representative_position', 'representative_position']);
        $status = biotern_ojt_masterlist_row_value($row, ['status'], $defaultType === 'internal' ? 'internal' : 'external');
        $lookupKey = $studentNo !== '' ? biotern_ojt_masterlist_lookup_key($studentNo) : biotern_ojt_masterlist_lookup_key($studentName);
        if ($lookupKey === '' || $studentName === '') {
            $errors[] = 'Row ' . ($index + 2) . ' skipped: missing student number or student name.';
            continue;
        }
        $sheetName = $defaultType . '_students';
        $rowNumber = $index + 2;
        $stmt->bind_param(
            'sssssisssssssssss',
            $schoolYear,
            $semester,
            $studentNo,
            $sourceWorkbook,
            $sheetName,
            $rowNumber,
            $lookupKey,
            $studentName,
            $contactNo,
            $section,
            $companyName,
            $companyAddress,
            $supervisorName,
            $supervisorPosition,
            $companyRepresentative,
            $companyRepresentativePosition,
            $status
        );
        if ($stmt->execute()) {
            $imported++;
            if ($companyName !== '' && function_exists('biotern_company_profiles_ensure_table') && biotern_company_profiles_ensure_table($conn)) {
                $companyLookupKey = function_exists('biotern_company_profile_lookup_key')
                    ? biotern_company_profile_lookup_key($companyName, $companyAddress)
                    : preg_replace('/[^a-z0-9]+/', '', strtolower($companyName));
                $companyStmt = $conn->prepare("
                    INSERT INTO ojt_partner_companies (
                        company_lookup_key,
                        company_name,
                        company_address,
                        supervisor_name,
                        supervisor_position,
                        company_representative,
                        company_representative_position,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        company_address = COALESCE(NULLIF(VALUES(company_address), ''), ojt_partner_companies.company_address),
                        supervisor_name = COALESCE(NULLIF(VALUES(supervisor_name), ''), ojt_partner_companies.supervisor_name),
                        supervisor_position = COALESCE(NULLIF(VALUES(supervisor_position), ''), ojt_partner_companies.supervisor_position),
                        company_representative = COALESCE(NULLIF(VALUES(company_representative), ''), ojt_partner_companies.company_representative),
                        company_representative_position = COALESCE(NULLIF(VALUES(company_representative_position), ''), ojt_partner_companies.company_representative_position),
                        updated_at = NOW()
                ");
                if ($companyStmt) {
                    $companyStmt->bind_param(
                        'sssssss',
                        $companyLookupKey,
                        $companyName,
                        $companyAddress,
                        $supervisorName,
                        $supervisorPosition,
                        $companyRepresentative,
                        $companyRepresentativePosition
                    );
                    $companyStmt->execute();
                    $companyStmt->close();
                }
            }
            $trackStmt = $conn->prepare("UPDATE students SET assignment_track = ?, updated_at = NOW() WHERE TRIM(COALESCE(student_id, '')) COLLATE utf8mb4_unicode_ci = TRIM(?) COLLATE utf8mb4_unicode_ci");
            if ($trackStmt) {
                $trackStmt->bind_param('ss', $defaultType, $studentNo);
                $trackStmt->execute();
                $trackStmt->close();
            }
        } else {
            $errors[] = 'Row ' . ($index + 2) . ' failed to save.';
        }
    }

    $stmt->close();
    return $imported;
}
