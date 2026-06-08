<?php

require_once __DIR__ . '/company_profiles.php';
require_once __DIR__ . '/offices.php';

if (!function_exists('biotern_student_placement_table_exists')) {
    function biotern_student_placement_table_exists(mysqli $conn, string $table): bool
    {
        $safe = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        $exists = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->close();
        }
        return $exists;
    }
}

if (!function_exists('biotern_student_placement_column_exists')) {
    function biotern_student_placement_column_exists(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $safeTable = str_replace('`', '``', $table);
        $safeColumn = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        $cache[$key] = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->close();
        }
        return $cache[$key];
    }
}

if (!function_exists('biotern_student_placement_blank')) {
    function biotern_student_placement_blank(string $type): array
    {
        return [
            'type' => $type,
            'name' => '',
            'address' => '',
            'contact_name' => '',
            'contact_position' => '',
            'supervisor_name' => '',
            'supervisor_position' => '',
            'role' => '',
            'status' => '',
            'start_date' => '',
            'end_date' => '',
            'office_code' => '',
            'has_details' => false,
        ];
    }
}

if (!function_exists('biotern_student_placement_fetch_internship')) {
    function biotern_student_placement_fetch_internship(mysqli $conn, int $studentId, string $type): ?array
    {
        if ($studentId <= 0 || !biotern_student_placement_table_exists($conn, 'internships')) {
            return null;
        }

        biotern_offices_ensure_schema($conn);
        $hasDeletedAt = biotern_student_placement_column_exists($conn, 'internships', 'deleted_at');
        $hasType = biotern_student_placement_column_exists($conn, 'internships', 'type');

        $where = ['i.student_id = ?'];
        $params = [$studentId];
        $paramTypes = 'i';
        if ($hasDeletedAt) {
            $where[] = 'i.deleted_at IS NULL';
        }
        if ($hasType) {
            if ($type === 'external') {
                $where[] = "LOWER(TRIM(COALESCE(i.type, ''))) = 'external'";
            } else {
                $where[] = "(LOWER(TRIM(COALESCE(i.type, 'internal'))) = 'internal' OR TRIM(COALESCE(i.type, '')) = '')";
            }
        } elseif ($type === 'external') {
            $where[] = "TRIM(COALESCE(i.company_name, '')) <> ''";
        }

        $sql = "
            SELECT
                i.*,
                o.name AS office_name,
                o.code AS office_code,
                u_sup.name AS supervisor_user_name,
                u_coord.name AS coordinator_user_name
            FROM internships i
            LEFT JOIN offices o ON o.id = i.office_id AND o.deleted_at IS NULL
            LEFT JOIN users u_sup ON u_sup.id = i.supervisor_id
            LEFT JOIN users u_coord ON u_coord.id = i.coordinator_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.updated_at DESC, i.id DESC
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param($paramTypes, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        return is_array($row) ? $row : null;
    }
}

if (!function_exists('biotern_student_internal_placement_details')) {
    function biotern_student_internal_placement_details(mysqli $conn, int $studentId, array $student = []): array
    {
        $details = biotern_student_placement_blank('internal');
        $internship = biotern_student_placement_fetch_internship($conn, $studentId, 'internal');

        $officeName = trim((string)($internship['office_name'] ?? ''));
        $officeCode = trim((string)($internship['office_code'] ?? ''));
        if ($officeName === '') {
            $officeName = trim((string)($internship['company_name'] ?? ''));
        }

        $supervisorName = trim((string)($internship['supervisor_user_name'] ?? ''));
        if ($supervisorName === '') {
            $supervisorName = trim((string)($student['supervisor_name'] ?? ''));
        }

        if ($officeName === '') {
            $supervisorId = (int)($internship['supervisor_id'] ?? $student['student_supervisor_id'] ?? $student['supervisor_id'] ?? 0);
            if ($supervisorId > 0) {
                $primaryOffice = biotern_supervisor_primary_office($conn, $supervisorId);
                $officeName = trim((string)($primaryOffice['name'] ?? ''));
            }
        }

        $details['name'] = $officeName;
        $details['office_code'] = $officeCode;
        $details['contact_name'] = $supervisorName;
        $details['supervisor_name'] = $supervisorName;
        $details['role'] = trim((string)($internship['position'] ?? ''));
        $details['status'] = trim((string)($internship['status'] ?? ''));
        $details['start_date'] = trim((string)($internship['start_date'] ?? ''));
        $details['end_date'] = trim((string)($internship['end_date'] ?? ''));
        $details['has_details'] = $details['name'] !== '' || $details['contact_name'] !== '' || $details['office_code'] !== '';

        return $details;
    }
}

if (!function_exists('biotern_student_external_placement_details')) {
    function biotern_student_external_placement_details(mysqli $conn, int $studentId, array $student = []): array
    {
        $details = biotern_student_placement_blank('external');
        $internship = biotern_student_placement_fetch_internship($conn, $studentId, 'external');

        if (!$internship && biotern_student_placement_table_exists($conn, 'ojt_masterlist')) {
            $studentNo = trim((string)($student['student_id'] ?? ''));
            if ($studentNo !== '') {
                $stmt = $conn->prepare("
                    SELECT company_name, company_address, supervisor_name, supervisor_position, company_representative, status
                    FROM ojt_masterlist
                    WHERE TRIM(COALESCE(student_no, '')) = ?
                      AND TRIM(COALESCE(company_name, '')) <> ''
                    ORDER BY updated_at DESC, id DESC
                    LIMIT 1
                ");
                if ($stmt) {
                    $stmt->bind_param('s', $studentNo);
                    $stmt->execute();
                    $internship = $stmt->get_result()->fetch_assoc() ?: null;
                    $stmt->close();
                }
            }
        }

        $companyName = trim((string)($internship['company_name'] ?? ''));
        $companyAddress = trim((string)($internship['company_address'] ?? ''));
        $representative = trim((string)($internship['company_representative'] ?? ''));
        $representativePosition = trim((string)($internship['company_representative_position'] ?? ''));
        $supervisorName = trim((string)($internship['supervisor_name'] ?? $internship['supervisor_user_name'] ?? ''));
        $supervisorPosition = trim((string)($internship['supervisor_position'] ?? ''));

        if ($companyName !== '') {
            $profile = biotern_company_profile_fetch_by_name($conn, $companyName);
            if ($profile) {
                $profileCompanyName = trim((string)($profile['company_name'] ?? ''));
                $profileCompanyAddress = trim((string)($profile['company_address'] ?? ''));
                $profileRepresentative = trim((string)($profile['company_representative'] ?? ''));
                $profileRepresentativePosition = trim((string)($profile['company_representative_position'] ?? ''));
                $profileSupervisorName = trim((string)($profile['supervisor_name'] ?? ''));
                $profileSupervisorPosition = trim((string)($profile['supervisor_position'] ?? ''));

                if ($profileCompanyName !== '') {
                    $companyName = $profileCompanyName;
                }
                if ($profileCompanyAddress !== '') {
                    $companyAddress = $profileCompanyAddress;
                }
                if ($profileRepresentative !== '') {
                    $representative = $profileRepresentative;
                }
                if ($profileRepresentativePosition !== '') {
                    $representativePosition = $profileRepresentativePosition;
                }
                if ($profileSupervisorName !== '') {
                    $supervisorName = $profileSupervisorName;
                }
                if ($profileSupervisorPosition !== '') {
                    $supervisorPosition = $profileSupervisorPosition;
                }
            }
        }

        $details['name'] = $companyName;
        $details['address'] = $companyAddress;
        $details['contact_name'] = $representative !== '' ? $representative : $supervisorName;
        $details['contact_position'] = $representativePosition !== '' ? $representativePosition : $supervisorPosition;
        $details['supervisor_name'] = $supervisorName;
        $details['supervisor_position'] = $supervisorPosition;
        $details['role'] = trim((string)($internship['position'] ?? ''));
        $details['status'] = trim((string)($internship['status'] ?? ''));
        $details['start_date'] = trim((string)($internship['start_date'] ?? ''));
        $details['end_date'] = trim((string)($internship['end_date'] ?? ''));
        $details['has_details'] = $details['name'] !== '' || $details['address'] !== '' || $details['contact_name'] !== '';

        return $details;
    }
}

if (!function_exists('biotern_student_all_placement_details')) {
    function biotern_student_all_placement_details(mysqli $conn, int $studentId, array $student = []): array
    {
        return [
            'internal' => biotern_student_internal_placement_details($conn, $studentId, $student),
            'external' => biotern_student_external_placement_details($conn, $studentId, $student),
        ];
    }
}
