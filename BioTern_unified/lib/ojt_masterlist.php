<?php

function biotern_masterlist_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function biotern_masterlist_lookup_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '', $value);
    return (string)$value;
}

function biotern_masterlist_phone_digits(string $value): string
{
    return preg_replace('/\D+/', '', trim($value));
}

function biotern_masterlist_student_name_variants(array $student): array
{
    $first = trim((string)($student['first_name'] ?? ''));
    $middle = trim((string)($student['middle_name'] ?? ''));
    $last = trim((string)($student['last_name'] ?? ''));

    $variants = [];
    $partsForward = array_values(array_filter([$first, $middle, $last], static fn($v) => $v !== ''));
    $partsReverse = array_values(array_filter([$last, $first, $middle], static fn($v) => $v !== ''));

    if (!empty($partsForward)) {
        $variants[] = implode(' ', $partsForward);
    }
    if ($last !== '' || $first !== '') {
        $variants[] = trim($last . ', ' . $first . ($middle !== '' ? ' ' . $middle : ''));
    }
    if (!empty($partsReverse)) {
        $variants[] = implode(' ', $partsReverse);
    }

    $lookup = [];
    foreach ($variants as $variant) {
        $key = biotern_masterlist_lookup_key($variant);
        if ($key !== '') {
            $lookup[$key] = true;
        }
    }

    return array_keys($lookup);
}

function biotern_masterlist_fetch_for_student(mysqli $conn, array $student): array
{
    if (
        !biotern_masterlist_table_exists($conn, 'ojt_masterlist') ||
        !biotern_masterlist_table_exists($conn, 'ojt_partner_companies')
    ) {
        return [];
    }

    $schoolYear = trim((string)($student['school_year'] ?? ''));
    $section = trim((string)($student['section_name'] ?? ($student['section'] ?? '')));
    $phoneDigits = biotern_masterlist_phone_digits((string)($student['phone'] ?? ''));
    $nameKeys = biotern_masterlist_student_name_variants($student);

    $where = [];
    if ($schoolYear !== '') {
        $where[] = "m.school_year = '" . $conn->real_escape_string($schoolYear) . "'";
    }
    if ($section !== '') {
        $where[] = "m.section = '" . $conn->real_escape_string($section) . "'";
    }
    if ($phoneDigits !== '') {
        $where[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(m.contact_no,''), '-', ''), ' ', ''), '(', ''), ')', ''), '+', '') = '" . $conn->real_escape_string($phoneDigits) . "'";
    }
    if (!empty($nameKeys)) {
        $escapedKeys = array_map(static fn($key) => "'" . $conn->real_escape_string($key) . "'", $nameKeys);
        $where[] = 'm.student_lookup_key IN (' . implode(', ', $escapedKeys) . ')';
    }

    if (empty($where)) {
        return [];
    }

    $sql = "SELECT
            m.*,
            c.company_name AS company_table_name,
            c.company_address AS company_table_address,
            c.supervisor_name AS company_table_supervisor_name,
            c.supervisor_position AS company_table_supervisor_position,
            c.company_representative AS company_table_representative
        FROM ojt_masterlist m
        LEFT JOIN ojt_partner_companies c ON c.id = m.company_id
        WHERE " . implode(' OR ', $where) . "
        ORDER BY
            CASE WHEN '" . $conn->real_escape_string($schoolYear) . "' <> '' AND m.school_year = '" . $conn->real_escape_string($schoolYear) . "' THEN 0 ELSE 1 END,
            CASE WHEN '" . $conn->real_escape_string($section) . "' <> '' AND m.section = '" . $conn->real_escape_string($section) . "' THEN 0 ELSE 1 END,
            CASE WHEN '" . $conn->real_escape_string($phoneDigits) . "' <> '' AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(m.contact_no,''), '-', ''), ' ', ''), '(', ''), ')', ''), '+', '') = '" . $conn->real_escape_string($phoneDigits) . "' THEN 0 ELSE 1 END,
            m.updated_at DESC,
            m.id DESC
        LIMIT 1";

    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }

    $row = $res->fetch_assoc();
    return is_array($row) ? $row : [];
}

function biotern_masterlist_company_name(array $row): string
{
    return trim((string)($row['company_name'] ?? $row['company_table_name'] ?? ''));
}

function biotern_masterlist_company_address(array $row): string
{
    return trim((string)($row['company_address'] ?? $row['company_table_address'] ?? ''));
}

function biotern_masterlist_supervisor_name(array $row): string
{
    return trim((string)($row['supervisor_name'] ?? $row['company_table_supervisor_name'] ?? ''));
}

function biotern_masterlist_supervisor_position(array $row): string
{
    return trim((string)($row['supervisor_position'] ?? $row['position'] ?? $row['company_table_supervisor_position'] ?? ''));
}

function biotern_masterlist_representative(array $row): string
{
    return trim((string)($row['company_representative'] ?? $row['company_table_representative'] ?? ''));
}

function biotern_masterlist_application_defaults(array $masterlist): array
{
    return [
        'date' => date('Y-m-d'),
        'application_person' => biotern_masterlist_supervisor_name($masterlist),
        'position' => biotern_masterlist_supervisor_position($masterlist),
        'company_name' => biotern_masterlist_company_name($masterlist),
        'company_address' => biotern_masterlist_company_address($masterlist),
    ];
}

function biotern_masterlist_endorsement_defaults(array $masterlist, array $student): array
{
    $studentName = trim(implode(' ', array_values(array_filter([
        (string)($student['first_name'] ?? ''),
        (string)($student['middle_name'] ?? ''),
        (string)($student['last_name'] ?? ''),
    ], static fn($v) => trim($v) !== ''))));

    return [
        'recipient_name' => biotern_masterlist_supervisor_name($masterlist),
        'recipient_title' => 'auto',
        'recipient_position' => biotern_masterlist_supervisor_position($masterlist),
        'company_name' => biotern_masterlist_company_name($masterlist),
        'company_address' => biotern_masterlist_company_address($masterlist),
        'students_to_endorse' => $studentName,
        'greeting_preference' => 'either',
    ];
}

function biotern_masterlist_moa_defaults(array $masterlist, array $student): array
{
    $hours = '';
    if (!empty($student['external_total_hours'])) {
        $hours = (string)$student['external_total_hours'];
    } elseif (!empty($student['internal_total_hours'])) {
        $hours = (string)$student['internal_total_hours'];
    }
    if ($hours === '') {
        $hours = '250';
    }

    $representative = biotern_masterlist_representative($masterlist);
    if ($representative === '') {
        $representative = biotern_masterlist_supervisor_name($masterlist);
    }

    return [
        'company_name' => biotern_masterlist_company_name($masterlist),
        'company_address' => biotern_masterlist_company_address($masterlist),
        'company_receipt' => '',
        'doc_no' => '',
        'page_no' => '',
        'book_no' => '',
        'series_no' => '',
        'total_hours' => $hours,
        'moa_address' => biotern_masterlist_company_address($masterlist),
        'moa_date' => '',
        'coordinator' => trim((string)($student['coordinator_name'] ?? '')),
        'school_posistion' => '',
        'school_position' => '',
        'position' => biotern_masterlist_supervisor_position($masterlist),
        'partner_representative' => $representative,
        'school_administrator' => '',
        'school_admin_position' => '',
        'notary_address' => biotern_masterlist_company_address($masterlist),
        'witness' => $representative,
        'acknowledgement_date' => '',
        'acknowledgement_address' => biotern_masterlist_company_address($masterlist),
    ];
}

function biotern_masterlist_dau_moa_defaults(array $masterlist, array $student): array
{
    $moa = biotern_masterlist_moa_defaults($masterlist, $student);
    return [
        'company_name' => $moa['company_name'],
        'company_address' => $moa['company_address'],
        'partner_representative' => $moa['partner_representative'],
        'position' => $moa['position'],
        'company_receipt' => $moa['company_receipt'],
        'total_hours' => $moa['total_hours'],
        'school_representative' => $moa['coordinator'],
        'school_position' => $moa['school_position'],
        'signed_at' => $moa['moa_address'],
        'signed_day' => '',
        'signed_month' => '',
        'signed_year' => '',
        'witness_partner' => $moa['witness'],
        'school_administrator' => $moa['school_administrator'],
        'school_admin_position' => $moa['school_admin_position'],
        'notary_city' => $moa['notary_address'],
        'notary_day' => '',
        'notary_month' => '',
        'notary_year' => '',
        'notary_place' => $moa['acknowledgement_address'],
        'doc_no' => '',
        'page_no' => '',
        'book_no' => '',
        'series_no' => '',
    ];
}
