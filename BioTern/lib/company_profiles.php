<?php

if (!function_exists('biotern_company_profile_normalized_name')) {
    function biotern_company_profile_normalized_name(string $value): string
    {
        $value = function_exists('mb_strtolower')
            ? trim(mb_strtolower($value, 'UTF-8'))
            : trim(strtolower($value));
        $value = preg_replace('/\s+/', ' ', $value);
        return (string)$value;
    }
}

if (!function_exists('biotern_company_profile_lookup_key')) {
    function biotern_company_profile_lookup_key(string $companyName, string $companyAddress = ''): string
    {
        $value = strtolower(trim($companyName . '|' . $companyAddress));
        $value = preg_replace('/[^a-z0-9]+/', '', $value);
        return (string)$value;
    }
}

if (!function_exists('biotern_company_profile_public_src')) {
    function biotern_company_profile_public_src(?string $rawPath): string
    {
        $rawPath = trim((string)$rawPath);
        if ($rawPath === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $rawPath);
        $normalized = ltrim($normalized, '/');
        $baseDir = realpath(dirname(__DIR__));
        if ($baseDir === false) {
            return '';
        }

        $candidate = realpath($baseDir . DIRECTORY_SEPARATOR . $normalized);
        if ($candidate === false || !is_file($candidate)) {
            return '';
        }

        $baseDirNormalized = str_replace('\\', '/', $baseDir);
        $candidateNormalized = str_replace('\\', '/', $candidate);
        if (strpos($candidateNormalized, $baseDirNormalized) !== 0) {
            return '';
        }

        return ltrim(substr($candidateNormalized, strlen($baseDirNormalized)), '/');
    }
}

if (!function_exists('biotern_company_profiles_ensure_table')) {
    function biotern_company_profiles_ensure_table(mysqli $conn): bool
    {
        $created = $conn->query("CREATE TABLE IF NOT EXISTS ojt_partner_companies (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!$created) {
            return false;
        }

        $requiredColumns = [
            'company_profile_picture' => 'VARCHAR(255) DEFAULT NULL',
            'company_representative_position' => 'VARCHAR(255) DEFAULT NULL',
        ];

        foreach ($requiredColumns as $column => $definition) {
            $safeColumn = $conn->real_escape_string($column);
            $columnResult = $conn->query("SHOW COLUMNS FROM ojt_partner_companies LIKE '{$safeColumn}'");
            $hasColumn = $columnResult instanceof mysqli_result && $columnResult->num_rows > 0;
            if ($columnResult instanceof mysqli_result) {
                $columnResult->close();
            }
            if (!$hasColumn) {
                $conn->query("ALTER TABLE ojt_partner_companies ADD COLUMN `{$column}` {$definition}");
            }
        }

        return true;
    }
}

if (!function_exists('biotern_company_profile_fetch_by_name')) {
    function biotern_company_profile_fetch_by_name(mysqli $conn, string $companyName): ?array
    {
        if (!biotern_company_profiles_ensure_table($conn)) {
            return null;
        }

        $normalized = biotern_company_profile_normalized_name($companyName);
        if ($normalized === '') {
            return null;
        }

        $sql = "
            SELECT
                id,
                company_lookup_key,
                company_name,
                company_address,
                supervisor_name,
                supervisor_position,
                company_representative,
                company_representative_position,
                company_profile_picture,
                created_at,
                updated_at
            FROM ojt_partner_companies
            WHERE LOWER(TRIM(company_name)) = ?
            ORDER BY updated_at DESC, id DESC
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('s', $normalized);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        if (!$row) {
            return null;
        }

        $row['company_profile_picture_src'] = biotern_company_profile_public_src((string)($row['company_profile_picture'] ?? ''));
        return $row;
    }
}

if (!function_exists('biotern_company_profile_student_document_defaults')) {
    function biotern_company_profile_student_document_defaults(mysqli $conn, int $studentId): array
    {
        $defaults = [
            'company_name' => '',
            'company_address' => '',
            'contact_name' => '',
            'contact_position' => '',
            'partner_representative' => '',
            'partner_position' => '',
        ];

        if ($studentId <= 0) {
            return $defaults;
        }

        $tableResult = $conn->query("SHOW TABLES LIKE 'internships'");
        $hasInternships = $tableResult instanceof mysqli_result && $tableResult->num_rows > 0;
        if ($tableResult instanceof mysqli_result) {
            $tableResult->close();
        }
        if (!$hasInternships) {
            return $defaults;
        }

        $stmt = $conn->prepare("SELECT * FROM internships WHERE student_id = ? ORDER BY id DESC LIMIT 1");
        if (!$stmt) {
            return $defaults;
        }

        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $internship = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        if (!$internship) {
            return $defaults;
        }

        $companyName = trim((string)($internship['company_name'] ?? ''));
        $companyAddress = trim((string)($internship['company_address'] ?? ''));
        $supervisorName = trim((string)($internship['supervisor_name'] ?? ''));
        $supervisorPosition = trim((string)($internship['supervisor_position'] ?? $internship['position'] ?? ''));
        $companyRepresentative = '';
        $companyRepresentativePosition = '';

        if ($companyName !== '') {
            $companyProfile = biotern_company_profile_fetch_by_name($conn, $companyName);
            if ($companyProfile) {
                $profileCompanyName = trim((string)($companyProfile['company_name'] ?? ''));
                $profileCompanyAddress = trim((string)($companyProfile['company_address'] ?? ''));
                $profileSupervisorName = trim((string)($companyProfile['supervisor_name'] ?? ''));
                $profileSupervisorPosition = trim((string)($companyProfile['supervisor_position'] ?? ''));
                $profileRepresentative = trim((string)($companyProfile['company_representative'] ?? ''));
                $profileRepresentativePosition = trim((string)($companyProfile['company_representative_position'] ?? ''));

                if ($profileCompanyName !== '') {
                    $companyName = $profileCompanyName;
                }
                if ($profileCompanyAddress !== '') {
                    $companyAddress = $profileCompanyAddress;
                }
                if ($profileSupervisorName !== '') {
                    $supervisorName = $profileSupervisorName;
                }
                if ($profileSupervisorPosition !== '') {
                    $supervisorPosition = $profileSupervisorPosition;
                }

                $companyRepresentative = $profileRepresentative;
                $companyRepresentativePosition = $profileRepresentativePosition;
            }
        }

        $contactName = $companyRepresentative !== '' ? $companyRepresentative : $supervisorName;
        $contactPosition = $companyRepresentativePosition !== '' ? $companyRepresentativePosition : $supervisorPosition;

        $defaults['company_name'] = $companyName;
        $defaults['company_address'] = $companyAddress;
        $defaults['contact_name'] = $contactName;
        $defaults['contact_position'] = $contactPosition;
        $defaults['partner_representative'] = $contactName;
        $defaults['partner_position'] = $contactPosition;

        return $defaults;
    }
}

if (!function_exists('biotern_company_profile_merge_application_letter')) {
    function biotern_company_profile_merge_application_letter(mysqli $conn, int $studentId, ?array $documentRow): array
    {
        $row = is_array($documentRow) ? $documentRow : [];
        $defaults = biotern_company_profile_student_document_defaults($conn, $studentId);
        $positionValue = trim((string)($row['position'] ?? $row['posistion'] ?? ''));

        if (trim((string)($row['company_name'] ?? '')) === '' && $defaults['company_name'] !== '') {
            $row['company_name'] = $defaults['company_name'];
        }
        if (trim((string)($row['company_address'] ?? '')) === '' && $defaults['company_address'] !== '') {
            $row['company_address'] = $defaults['company_address'];
        }
        if (trim((string)($row['application_person'] ?? '')) === '' && $defaults['contact_name'] !== '') {
            $row['application_person'] = $defaults['contact_name'];
        }
        if ($positionValue === '' && $defaults['contact_position'] !== '') {
            $row['position'] = $defaults['contact_position'];
        } elseif ($positionValue !== '') {
            $row['position'] = $positionValue;
        }

        return $row;
    }
}

if (!function_exists('biotern_company_profile_merge_endorsement_letter')) {
    function biotern_company_profile_merge_endorsement_letter(mysqli $conn, int $studentId, ?array $documentRow): array
    {
        $row = is_array($documentRow) ? $documentRow : [];
        $defaults = biotern_company_profile_student_document_defaults($conn, $studentId);

        if (trim((string)($row['company_name'] ?? '')) === '' && $defaults['company_name'] !== '') {
            $row['company_name'] = $defaults['company_name'];
        }
        if (trim((string)($row['company_address'] ?? '')) === '' && $defaults['company_address'] !== '') {
            $row['company_address'] = $defaults['company_address'];
        }
        if (trim((string)($row['recipient_name'] ?? '')) === '' && $defaults['contact_name'] !== '') {
            $row['recipient_name'] = $defaults['contact_name'];
        }
        if (trim((string)($row['recipient_position'] ?? '')) === '' && $defaults['contact_position'] !== '') {
            $row['recipient_position'] = $defaults['contact_position'];
        }

        return $row;
    }
}

if (!function_exists('biotern_company_profile_merge_moa')) {
    function biotern_company_profile_merge_moa(mysqli $conn, int $studentId, ?array $documentRow): array
    {
        $row = is_array($documentRow) ? $documentRow : [];
        $defaults = biotern_company_profile_student_document_defaults($conn, $studentId);
        $positionValue = trim((string)($row['position'] ?? ''));

        if (trim((string)($row['company_name'] ?? '')) === '' && $defaults['company_name'] !== '') {
            $row['company_name'] = $defaults['company_name'];
        }
        if (trim((string)($row['company_address'] ?? '')) === '' && $defaults['company_address'] !== '') {
            $row['company_address'] = $defaults['company_address'];
        }
        if (trim((string)($row['partner_representative'] ?? '')) === '' && $defaults['partner_representative'] !== '') {
            $row['partner_representative'] = $defaults['partner_representative'];
        }
        if ($positionValue === '' && $defaults['partner_position'] !== '') {
            $row['position'] = $defaults['partner_position'];
        } elseif ($positionValue !== '') {
            $row['position'] = $positionValue;
        }

        return $row;
    }
}

if (!function_exists('biotern_company_profiles_catalog')) {
    function biotern_company_profiles_catalog(mysqli $conn): array
    {
        $companyMap = [];

        if (biotern_company_profiles_ensure_table($conn)) {
            $partnerResult = $conn->query("
                SELECT
                    id,
                    company_lookup_key,
                    company_name,
                    company_address,
                    supervisor_name,
                    supervisor_position,
                    company_representative,
                    company_representative_position,
                    company_profile_picture,
                    created_at,
                    updated_at
                FROM ojt_partner_companies
                ORDER BY company_name ASC, id DESC
            ");

            if ($partnerResult instanceof mysqli_result) {
                while ($row = $partnerResult->fetch_assoc()) {
                    $companyName = trim((string)($row['company_name'] ?? ''));
                    $key = biotern_company_profile_normalized_name($companyName);
                    if ($key === '') {
                        continue;
                    }

                    $companyMap[$key] = [
                        'key' => $key,
                        'partner_company_id' => (int)($row['id'] ?? 0),
                        'company_lookup_key' => trim((string)($row['company_lookup_key'] ?? '')),
                        'company_name' => $companyName,
                        'company_address' => trim((string)($row['company_address'] ?? '')),
                        'supervisor_name' => trim((string)($row['supervisor_name'] ?? '')),
                        'supervisor_position' => trim((string)($row['supervisor_position'] ?? '')),
                        'company_representative' => trim((string)($row['company_representative'] ?? '')),
                        'company_representative_position' => trim((string)($row['company_representative_position'] ?? '')),
                        'company_profile_picture' => trim((string)($row['company_profile_picture'] ?? '')),
                        'company_profile_picture_src' => biotern_company_profile_public_src((string)($row['company_profile_picture'] ?? '')),
                        'created_at' => trim((string)($row['created_at'] ?? '')),
                        'updated_at' => trim((string)($row['updated_at'] ?? '')),
                        'intern_count' => 0,
                        'ongoing_count' => 0,
                        'latest_activity' => trim((string)($row['updated_at'] ?? '')),
                        'has_partner_record' => true,
                    ];
                }
                $partnerResult->close();
            }
        }

        $internshipsTableResult = $conn->query("SHOW TABLES LIKE 'internships'");
        $hasInternships = $internshipsTableResult instanceof mysqli_result && $internshipsTableResult->num_rows > 0;
        if ($internshipsTableResult instanceof mysqli_result) {
            $internshipsTableResult->close();
        }

        if ($hasInternships) {
            $internshipAggregateResult = $conn->query("
                SELECT
                    LOWER(TRIM(COALESCE(i.company_name, ''))) AS company_key,
                    MAX(TRIM(COALESCE(i.company_name, ''))) AS company_name,
                    MAX(TRIM(COALESCE(i.company_address, ''))) AS company_address,
                    COUNT(*) AS intern_count,
                    SUM(CASE WHEN i.status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing_count,
                    MAX(i.updated_at) AS latest_activity
                FROM internships i
                INNER JOIN (
                    SELECT student_id, MAX(id) AS latest_id
                    FROM internships
                    WHERE deleted_at IS NULL
                    GROUP BY student_id
                ) latest ON latest.latest_id = i.id
                WHERE i.deleted_at IS NULL
                  AND TRIM(COALESCE(i.company_name, '')) <> ''
                GROUP BY LOWER(TRIM(COALESCE(i.company_name, '')))
            ");

            if ($internshipAggregateResult instanceof mysqli_result) {
                while ($row = $internshipAggregateResult->fetch_assoc()) {
                    $key = trim((string)($row['company_key'] ?? ''));
                    if ($key === '') {
                        $key = biotern_company_profile_normalized_name((string)($row['company_name'] ?? ''));
                    }
                    if ($key === '') {
                        continue;
                    }

                    if (!isset($companyMap[$key])) {
                        $companyMap[$key] = [
                            'key' => $key,
                            'partner_company_id' => 0,
                            'company_lookup_key' => '',
                            'company_name' => trim((string)($row['company_name'] ?? '')),
                            'company_address' => trim((string)($row['company_address'] ?? '')),
                            'supervisor_name' => '',
                            'supervisor_position' => '',
                            'company_representative' => '',
                            'company_representative_position' => '',
                            'company_profile_picture' => '',
                            'company_profile_picture_src' => '',
                            'created_at' => '',
                            'updated_at' => '',
                            'intern_count' => 0,
                            'ongoing_count' => 0,
                            'latest_activity' => '',
                            'has_partner_record' => false,
                        ];
                    }

                    if ($companyMap[$key]['company_name'] === '') {
                        $companyMap[$key]['company_name'] = trim((string)($row['company_name'] ?? ''));
                    }
                    if ($companyMap[$key]['company_address'] === '') {
                        $companyMap[$key]['company_address'] = trim((string)($row['company_address'] ?? ''));
                    }
                    $companyMap[$key]['intern_count'] = (int)($row['intern_count'] ?? 0);
                    $companyMap[$key]['ongoing_count'] = (int)($row['ongoing_count'] ?? 0);
                    $companyMap[$key]['latest_activity'] = trim((string)($row['latest_activity'] ?? ''));

                    if ($companyMap[$key]['updated_at'] === '' && $companyMap[$key]['latest_activity'] !== '') {
                        $companyMap[$key]['updated_at'] = $companyMap[$key]['latest_activity'];
                    }
                }
                $internshipAggregateResult->close();
            }
        }

        return array_values($companyMap);
    }
}

if (!function_exists('biotern_company_profile_with_contact_defaults')) {
    function biotern_company_profile_with_contact_defaults(array $company): array
    {
        $contactName = trim((string)($company['company_representative'] ?? ''));
        if ($contactName === '') {
            $contactName = trim((string)($company['supervisor_name'] ?? ''));
        }

        $contactPosition = trim((string)($company['company_representative_position'] ?? ''));
        if ($contactPosition === '') {
            $contactPosition = trim((string)($company['supervisor_position'] ?? ''));
        }

        $company['contact_name'] = $contactName;
        $company['contact_position'] = $contactPosition;
        $company['partner_representative'] = $contactName;
        $company['partner_position'] = $contactPosition;

        return $company;
    }
}

if (!function_exists('biotern_company_profile_find')) {
    function biotern_company_profile_find(mysqli $conn, string $companyIdentifier): ?array
    {
        $companyIdentifier = trim($companyIdentifier);
        if ($companyIdentifier === '') {
            return null;
        }

        $normalized = biotern_company_profile_normalized_name($companyIdentifier);
        foreach (biotern_company_profiles_catalog($conn) as $company) {
            $companyKey = trim((string)($company['key'] ?? ''));
            $lookupKey = trim((string)($company['company_lookup_key'] ?? ''));
            $companyName = biotern_company_profile_normalized_name((string)($company['company_name'] ?? ''));

            if ($companyKey === $normalized || $lookupKey === $companyIdentifier || $companyName === $normalized) {
                return biotern_company_profile_with_contact_defaults($company);
            }
        }

        return null;
    }
}

if (!function_exists('biotern_company_profiles_search')) {
    function biotern_company_profiles_search(mysqli $conn, string $term = '', int $limit = 20): array
    {
        $companies = biotern_company_profiles_catalog($conn);
        $needle = biotern_company_profile_normalized_name($term);

        if ($needle !== '') {
            $companies = array_values(array_filter($companies, static function (array $company) use ($needle): bool {
                $haystack = biotern_company_profile_normalized_name(implode(' ', [
                    (string)($company['company_name'] ?? ''),
                    (string)($company['company_address'] ?? ''),
                    (string)($company['supervisor_name'] ?? ''),
                    (string)($company['supervisor_position'] ?? ''),
                    (string)($company['company_representative'] ?? ''),
                    (string)($company['company_representative_position'] ?? ''),
                ]));

                return $haystack !== '' && strpos($haystack, $needle) !== false;
            }));
        }

        usort($companies, static function (array $a, array $b): int {
            $aName = trim((string)($a['company_name'] ?? ''));
            $bName = trim((string)($b['company_name'] ?? ''));
            return strcasecmp($aName, $bName);
        });

        if ($limit > 0 && count($companies) > $limit) {
            $companies = array_slice($companies, 0, $limit);
        }

        return array_map('biotern_company_profile_with_contact_defaults', $companies);
    }
}
