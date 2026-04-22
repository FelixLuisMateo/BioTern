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
