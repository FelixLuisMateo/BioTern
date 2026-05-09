<?php

if (!function_exists('biotern_offices_ensure_schema')) {
    function biotern_offices_ensure_schema(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS offices (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(80) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_offices_active (is_active, deleted_at),
            KEY idx_offices_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $conn->query("CREATE TABLE IF NOT EXISTS supervisor_offices (
            supervisor_id BIGINT UNSIGNED NOT NULL,
            office_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (supervisor_id, office_id),
            KEY idx_supervisor_offices_office (office_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        biotern_offices_ensure_column($conn, 'supervisors', 'office_location', 'VARCHAR(255) DEFAULT NULL');
        biotern_offices_ensure_column($conn, 'supervisors', 'course_id', 'BIGINT UNSIGNED NULL');
        biotern_offices_ensure_column($conn, 'offices', 'course_id', 'BIGINT UNSIGNED NULL');
        biotern_offices_ensure_column($conn, 'offices', 'department_id', 'BIGINT UNSIGNED NULL');
        biotern_offices_ensure_column($conn, 'internships', 'office_id', 'BIGINT UNSIGNED NULL');

        $legacy = $conn->query("SELECT id, TRIM(office_location) AS office_name FROM supervisors WHERE TRIM(COALESCE(office_location, '')) <> ''");
        if ($legacy instanceof mysqli_result) {
            while ($row = $legacy->fetch_assoc()) {
                $officeId = biotern_offices_find_or_create($conn, (string)($row['office_name'] ?? ''));
                $supervisorId = (int)($row['id'] ?? 0);
                if ($officeId > 0 && $supervisorId > 0) {
                    $stmt = $conn->prepare("INSERT IGNORE INTO supervisor_offices (supervisor_id, office_id) VALUES (?, ?)");
                    if ($stmt) {
                        $stmt->bind_param('ii', $supervisorId, $officeId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
            $legacy->close();
        }
    }
}

if (!function_exists('biotern_offices_ensure_column')) {
    function biotern_offices_ensure_column(mysqli $conn, string $table, string $column, string $definition): void
    {
        $safeTable = $conn->real_escape_string($table);
        $safeColumn = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
        $exists = $res instanceof mysqli_result && $res->num_rows > 0;
        if ($res instanceof mysqli_result) {
            $res->close();
        }
        if (!$exists) {
            $conn->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
        }
    }
}

if (!function_exists('biotern_offices_find_or_create')) {
    function biotern_offices_find_or_create(mysqli $conn, string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }

        $stmt = $conn->prepare("SELECT id FROM offices WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND deleted_at IS NULL LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $name);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return (int)$row['id'];
            }
        }

        $code = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', $name));
        $code = trim(substr($code, 0, 80), '-');
        $insert = $conn->prepare("INSERT INTO offices (name, code, is_active) VALUES (?, ?, 1)");
        if (!$insert) {
            return 0;
        }
        $insert->bind_param('ss', $name, $code);
        $insert->execute();
        $id = (int)$insert->insert_id;
        $insert->close();
        return $id;
    }
}

if (!function_exists('biotern_offices_all')) {
    function biotern_offices_all(mysqli $conn): array
    {
        biotern_offices_ensure_schema($conn);
        $rows = [];
        $res = $conn->query("SELECT id, name, code, course_id, department_id FROM offices WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name ASC");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $res->close();
        }
        return $rows;
    }
}

if (!function_exists('biotern_offices_for_supervisor')) {
    function biotern_offices_for_supervisor(mysqli $conn, int $supervisorUserOrProfileId): array
    {
        biotern_offices_ensure_schema($conn);
        $rows = [];
        $stmt = $conn->prepare("
            SELECT DISTINCT o.id, o.name, o.code, o.course_id, o.department_id
            FROM supervisors s
            INNER JOIN supervisor_offices so ON so.supervisor_id = s.id
            INNER JOIN offices o ON o.id = so.office_id
            WHERE (s.id = ? OR s.user_id = ?) AND s.deleted_at IS NULL AND o.deleted_at IS NULL AND o.is_active = 1
            ORDER BY o.name ASC
        ");
        if ($stmt) {
            $stmt->bind_param('ii', $supervisorUserOrProfileId, $supervisorUserOrProfileId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        return $rows;
    }
}

if (!function_exists('biotern_supervisor_office_ids')) {
    function biotern_supervisor_office_ids(mysqli $conn, int $supervisorId): array
    {
        biotern_offices_ensure_schema($conn);
        $ids = [];
        $stmt = $conn->prepare("SELECT office_id FROM supervisor_offices WHERE supervisor_id = ? ORDER BY office_id ASC");
        if ($stmt) {
            $stmt->bind_param('i', $supervisorId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int)$row['office_id'];
            }
            $stmt->close();
        }
        return $ids;
    }
}

if (!function_exists('biotern_supervisor_office_names')) {
    function biotern_supervisor_office_names(mysqli $conn, int $supervisorId): array
    {
        biotern_offices_ensure_schema($conn);
        $names = [];
        $stmt = $conn->prepare("
            SELECT o.name
            FROM supervisor_offices so
            INNER JOIN offices o ON o.id = so.office_id
            WHERE so.supervisor_id = ? AND o.deleted_at IS NULL
            ORDER BY o.name ASC
        ");
        if ($stmt) {
            $stmt->bind_param('i', $supervisorId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)($row['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            $stmt->close();
        }
        return $names;
    }
}

if (!function_exists('biotern_supervisor_primary_office')) {
    function biotern_supervisor_primary_office(mysqli $conn, int $supervisorUserOrProfileId): array
    {
        biotern_offices_ensure_schema($conn);
        $stmt = $conn->prepare("
            SELECT o.id, o.name
            FROM supervisors s
            INNER JOIN supervisor_offices so ON so.supervisor_id = s.id
            INNER JOIN offices o ON o.id = so.office_id
            WHERE (s.id = ? OR s.user_id = ?) AND s.deleted_at IS NULL AND o.deleted_at IS NULL
            ORDER BY o.name ASC
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('ii', $supervisorUserOrProfileId, $supervisorUserOrProfileId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                return ['id' => (int)$row['id'], 'name' => (string)$row['name']];
            }
        }
        return ['id' => 0, 'name' => ''];
    }
}

if (!function_exists('biotern_supervisor_sync_offices')) {
    function biotern_supervisor_sync_offices(mysqli $conn, int $supervisorId, array $officeIds, string $extraOfficeName = ''): string
    {
        biotern_offices_ensure_schema($conn);
        $normalized = [];
        foreach ($officeIds as $officeId) {
            $officeId = (int)$officeId;
            if ($officeId > 0) {
                $normalized[$officeId] = $officeId;
            }
        }
        $extraId = biotern_offices_find_or_create($conn, $extraOfficeName);
        if ($extraId > 0) {
            $normalized[$extraId] = $extraId;
        }

        $del = $conn->prepare("DELETE FROM supervisor_offices WHERE supervisor_id = ?");
        if ($del) {
            $del->bind_param('i', $supervisorId);
            $del->execute();
            $del->close();
        }

        if ($normalized) {
            $ins = $conn->prepare("INSERT IGNORE INTO supervisor_offices (supervisor_id, office_id) VALUES (?, ?)");
            if ($ins) {
                foreach ($normalized as $officeId) {
                    $ins->bind_param('ii', $supervisorId, $officeId);
                    $ins->execute();
                }
                $ins->close();
            }
        }

        $names = biotern_supervisor_office_names($conn, $supervisorId);
        $officeText = implode(', ', $names);
        $up = $conn->prepare("UPDATE supervisors SET office_location = ? WHERE id = ?");
        if ($up) {
            $up->bind_param('si', $officeText, $supervisorId);
            $up->execute();
            $up->close();
        }

        return $officeText;
    }
}
