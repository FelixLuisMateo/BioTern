<?php

if (!function_exists('biotern_admin_activity_column_exists')) {
    function biotern_admin_activity_column_exists(mysqli $conn, string $table, string $column): bool
    {
        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return (int)($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('biotern_admin_activity_table_exists')) {
    function biotern_admin_activity_table_exists(mysqli $conn, string $table): bool
    {
        $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return (int)($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('biotern_admin_activity_table_ready')) {
    function biotern_admin_activity_table_ready(mysqli $conn): bool
    {
        $created = (bool)$conn->query("CREATE TABLE IF NOT EXISTS admin_activity_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_user_id BIGINT UNSIGNED NULL,
            admin_name VARCHAR(255) NULL,
            admin_username VARCHAR(191) NULL,
            admin_email VARCHAR(255) NULL,
            action VARCHAR(50) NOT NULL,
            action_label VARCHAR(120) NOT NULL,
            target_type VARCHAR(120) NULL,
            target_id VARCHAR(120) NULL,
            target_name VARCHAR(255) NULL,
            activity_comment VARCHAR(500) NULL,
            page VARCHAR(191) NULL,
            request_method VARCHAR(12) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            details_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_activity_user_created (admin_user_id, created_at),
            INDEX idx_admin_activity_action_created (action, created_at),
            INDEX idx_admin_activity_page_created (page, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!$created) {
            return false;
        }

        if (!biotern_admin_activity_column_exists($conn, 'admin_activity_logs', 'target_name')) {
            $conn->query('ALTER TABLE admin_activity_logs ADD COLUMN target_name VARCHAR(255) NULL AFTER target_id');
        }
        if (!biotern_admin_activity_column_exists($conn, 'admin_activity_logs', 'activity_comment')) {
            $conn->query('ALTER TABLE admin_activity_logs ADD COLUMN activity_comment VARCHAR(500) NULL AFTER target_name');
        }

        return true;
    }
}

if (!function_exists('biotern_admin_activity_clean_details')) {
    function biotern_admin_activity_clean_details(array $source): array
    {
        $blocked = ['password', 'pass', 'confirm_password', 'password_confirm', 'csrf', 'csrf_token', 'token', 'otp', 'code'];
        $clean = [];

        foreach ($source as $key => $value) {
            $keyString = (string)$key;
            $lowerKey = strtolower($keyString);
            $hide = false;
            foreach ($blocked as $blockedKey) {
                if (strpos($lowerKey, $blockedKey) !== false) {
                    $hide = true;
                    break;
                }
            }

            if ($hide) {
                $clean[$keyString] = '[hidden]';
                continue;
            }

            if (is_array($value)) {
                $clean[$keyString] = biotern_admin_activity_clean_details($value);
                continue;
            }

            $stringValue = trim((string)$value);
            if (strlen($stringValue) > 500) {
                $stringValue = substr($stringValue, 0, 500) . '...';
            }
            $clean[$keyString] = $stringValue;
        }

        return $clean;
    }
}

if (!function_exists('biotern_admin_activity_client_ip')) {
    function biotern_admin_activity_client_ip(): string
    {
        $candidates = [
            (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
            (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim(explode(',', $candidate)[0] ?? '');
            if ($candidate !== '') {
                return substr($candidate, 0, 45);
            }
        }

        return '';
    }
}

if (!function_exists('biotern_admin_activity_page')) {
    function biotern_admin_activity_page(): string
    {
        if (!empty($_GET['file'])) {
            return strtolower(basename((string)$_GET['file']));
        }

        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        return strtolower(basename((string)$path));
    }
}

if (!function_exists('biotern_admin_activity_target_type')) {
    function biotern_admin_activity_target_type(string $page): string
    {
        if (strtolower(trim($page)) === 'process_attendance.php') {
            return 'attendance';
        }

        $name = preg_replace('/\.php$/i', '', $page);
        $name = preg_replace('/-(create|edit|view|list)$/i', '', (string)$name);
        $name = str_replace(['reports-', 'import-', 'export-'], '', (string)$name);
        return trim(str_replace('-', ' ', (string)$name));
    }
}

if (!function_exists('biotern_admin_activity_infer_action')) {
    function biotern_admin_activity_infer_action(string $page, string $method, array $request): string
    {
        $page = strtolower($page);
        $method = strtoupper($method);
        $rawAction = strtolower(trim((string)($request['action'] ?? $request['bulk_action'] ?? $request['form_action'] ?? '')));

        if ($rawAction !== '') {
            if (strpos($rawAction, 'delete') !== false || strpos($rawAction, 'remove') !== false) {
                return 'delete';
            }
            if (strpos($rawAction, 'approve') !== false || strpos($rawAction, 'accept') !== false) {
                return 'approve';
            }
            if (strpos($rawAction, 'reject') !== false || strpos($rawAction, 'deny') !== false) {
                return 'reject';
            }
            if (strpos($rawAction, 'archive') !== false) {
                return 'archive';
            }
            if (strpos($rawAction, 'restore') !== false || strpos($rawAction, 'reactivate') !== false) {
                return 'restore';
            }
            if (strpos($rawAction, 'import') !== false || strpos($rawAction, 'upload') !== false) {
                return 'import';
            }
            if (strpos($rawAction, 'export') !== false || strpos($rawAction, 'download') !== false) {
                return 'export';
            }
            if (strpos($rawAction, 'edit') !== false || strpos($rawAction, 'update') !== false || strpos($rawAction, 'save') !== false) {
                return 'edit';
            }
            if (strpos($rawAction, 'create') !== false || strpos($rawAction, 'add') !== false || strpos($rawAction, 'insert') !== false) {
                return 'create';
            }
            return preg_replace('/[^a-z0-9_ -]/', '', $rawAction) ?: 'action';
        }

        if (strpos($page, 'export') !== false || strpos($page, 'download') !== false || isset($request['export']) || isset($request['download'])) {
            return 'export';
        }
        if (isset($request['delete']) || isset($request['remove']) || isset($request['delete_id']) || isset($request['archive'])) {
            return isset($request['archive']) ? 'archive' : 'delete';
        }
        if (isset($request['approve'])) {
            return 'approve';
        }
        if (isset($request['reject'])) {
            return 'reject';
        }
        if ($method !== 'POST') {
            return 'view';
        }
        if (strpos($page, 'import') !== false) {
            return 'import';
        }
        if (strpos($page, 'create') !== false) {
            return 'create';
        }
        if (strpos($page, 'edit') !== false || isset($request['edit']) || isset($request['update'])) {
            return 'edit';
        }
        if (isset($request['delete']) || isset($request['remove']) || isset($request['delete_id'])) {
            return 'delete';
        }

        return 'update';
    }
}

if (!function_exists('biotern_admin_activity_action_label')) {
    function biotern_admin_activity_action_label(string $action): string
    {
        $labels = [
            'create' => 'Created',
            'add' => 'Added',
            'edit' => 'Edited',
            'update' => 'Updated',
            'delete' => 'Deleted',
            'import' => 'Imported',
            'export' => 'Exported',
            'view' => 'Viewed',
            'approve' => 'Approved',
            'reject' => 'Rejected',
            'archive' => 'Archived',
            'restore' => 'Restored',
        ];

        return $labels[$action] ?? ucwords(str_replace(['_', '-'], ' ', $action));
    }
}

if (!function_exists('biotern_admin_activity_compact_text')) {
    function biotern_admin_activity_compact_text(string $value, int $limit = 255): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($value === '') {
            return '';
        }

        if (strlen($value) > $limit) {
            return substr($value, 0, max(0, $limit - 3)) . '...';
        }

        return $value;
    }
}

if (!function_exists('biotern_admin_activity_flat_value')) {
    function biotern_admin_activity_flat_value($value): string
    {
        if (is_array($value)) {
            $parts = [];
            array_walk_recursive($value, static function ($item) use (&$parts): void {
                $item = trim((string)$item);
                if ($item !== '') {
                    $parts[] = $item;
                }
            });

            return biotern_admin_activity_compact_text(implode(', ', array_unique($parts)), 120);
        }

        return biotern_admin_activity_compact_text((string)$value, 120);
    }
}

if (!function_exists('biotern_admin_activity_request_value')) {
    function biotern_admin_activity_request_value(array $request, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($request[$key])) {
                $value = biotern_admin_activity_flat_value($request[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}

if (!function_exists('biotern_admin_activity_name_from_request')) {
    function biotern_admin_activity_name_from_request(array $request): string
    {
        $first = biotern_admin_activity_request_value($request, ['first_name', 'firstname', 'fname']);
        $middle = biotern_admin_activity_request_value($request, ['middle_name', 'middlename', 'mname']);
        $last = biotern_admin_activity_request_value($request, ['last_name', 'lastname', 'lname']);
        $fullName = biotern_admin_activity_compact_text(trim($first . ' ' . $middle . ' ' . $last));
        if ($fullName !== '') {
            return $fullName;
        }

        return biotern_admin_activity_request_value($request, [
            'name',
            'full_name',
            'student_name',
            'company_name',
            'course_name',
            'department_name',
            'section_name',
            'title',
            'username',
            'email',
        ]);
    }
}

if (!function_exists('biotern_admin_activity_row_label')) {
    function biotern_admin_activity_row_label(array $row): string
    {
        $nameParts = [];
        foreach (['last_name', 'first_name', 'middle_name'] as $nameKey) {
            $value = trim((string)($row[$nameKey] ?? ''));
            if ($value !== '') {
                $nameParts[] = $value;
            }
        }

        if ($nameParts) {
            $name = implode($nameParts[0] === ($row['last_name'] ?? null) ? ', ' : ' ', $nameParts);
            $studentNo = trim((string)($row['student_id'] ?? $row['student_no'] ?? $row['student_number'] ?? ''));
            return biotern_admin_activity_compact_text($studentNo !== '' ? $studentNo . ' - ' . $name : $name);
        }

        foreach (['name', 'full_name', 'company_name', 'course_name', 'department_name', 'section_name', 'title', 'username', 'email', 'code', 'acronym'] as $key) {
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '') {
                $code = trim((string)($row['code'] ?? $row['acronym'] ?? ''));
                if ($code !== '' && !in_array($key, ['code', 'acronym'], true) && stripos($value, $code) === false) {
                    return biotern_admin_activity_compact_text($code . ' - ' . $value);
                }
                return biotern_admin_activity_compact_text($value);
            }
        }

        return '';
    }
}

if (!function_exists('biotern_admin_activity_lookup_row_label')) {
    function biotern_admin_activity_lookup_row_label(mysqli $conn, string $table, string $idColumn, string $targetId): string
    {
        if ($targetId === '' || !biotern_admin_activity_table_exists($conn, $table) || !biotern_admin_activity_column_exists($conn, $table, $idColumn)) {
            return '';
        }

        $sql = 'SELECT * FROM `' . $table . '` WHERE `' . $idColumn . '` = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return '';
        }
        $stmt->bind_param('s', $targetId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        return $row ? biotern_admin_activity_row_label($row) : '';
    }
}

if (!function_exists('biotern_admin_activity_lookup_target_name')) {
    function biotern_admin_activity_lookup_target_name(mysqli $conn, string $page, string $targetType, ?string $targetId, array $request): string
    {
        $targetId = trim((string)$targetId);
        $page = strtolower($page);
        $targetType = strtolower($targetType);

        if ($targetId !== '' && strcasecmp($targetId, 'Array') === 0) {
            $targetId = '';
        }
        if ($targetId !== '' && preg_match('/^\d+(?:\s*,\s*\d+)+$/', $targetId)) {
            $label = (strpos($page, 'attendance') !== false || strpos($targetType, 'attendance') !== false) ? 'Attendance records' : 'Records';
            return $label . ': ' . $targetId;
        }

        $candidates = [];
        if (strpos($page, 'attendance') !== false || strpos($targetType, 'attendance') !== false) {
            $candidates[] = ['attendances', 'id'];
            $candidates[] = ['external_attendance', 'id'];
        }
        if (strpos($page, 'student') !== false || strpos($targetType, 'student') !== false) {
            $candidates[] = ['students', 'id'];
            $candidates[] = ['students', 'student_id'];
        }
        if (strpos($page, 'coordinator') !== false || strpos($targetType, 'coordinator') !== false) {
            $candidates[] = ['coordinators', 'id'];
            $candidates[] = ['users', 'id'];
        }
        if (strpos($page, 'supervisor') !== false || strpos($targetType, 'supervisor') !== false) {
            $candidates[] = ['supervisors', 'id'];
            $candidates[] = ['users', 'id'];
        }
        if (strpos($page, 'user') !== false || strpos($page, 'auth-register') !== false || strpos($page, 'create_admin') !== false || strpos($targetType, 'user') !== false) {
            $candidates[] = ['users', 'id'];
        }
        if (strpos($page, 'course') !== false || strpos($targetType, 'course') !== false) {
            $candidates[] = ['courses', 'id'];
            $candidates[] = ['courses', 'course_id'];
        }
        if (strpos($page, 'department') !== false || strpos($targetType, 'department') !== false) {
            $candidates[] = ['departments', 'id'];
            $candidates[] = ['departments', 'department_id'];
        }
        if (strpos($page, 'section') !== false || strpos($targetType, 'section') !== false) {
            $candidates[] = ['sections', 'id'];
            $candidates[] = ['sections', 'section_id'];
        }
        if (strpos($page, 'compan') !== false || strpos($targetType, 'compan') !== false) {
            $candidates[] = ['ojt_partner_companies', 'id'];
            $candidates[] = ['companies', 'id'];
        }
        if (strpos($page, 'ojt') !== false || strpos($page, 'intern') !== false || strpos($targetType, 'ojt') !== false || strpos($targetType, 'intern') !== false) {
            $candidates[] = ['internships', 'id'];
            $candidates[] = ['ojt_assignments', 'id'];
        }

        if ($targetId !== '') {
            foreach ($candidates as $candidate) {
                $label = biotern_admin_activity_lookup_row_label($conn, $candidate[0], $candidate[1], $targetId);
                if ($label !== '') {
                    return $label;
                }
            }
        }

        return biotern_admin_activity_compact_text(biotern_admin_activity_name_from_request($request));
    }
}

if (!function_exists('biotern_admin_activity_build_comment')) {
    function biotern_admin_activity_build_comment(string $adminName, string $actionLabel, string $targetType, ?string $targetName, ?string $targetId, string $page): string
    {
        $subject = trim($targetName ?? '');
        $targetId = trim((string)$targetId);
        if (strcasecmp($subject, 'Array') === 0) {
            $subject = '';
        }
        if (strcasecmp($targetId, 'Array') === 0) {
            $targetId = '';
        }
        if ($subject === '' && $targetId !== '') {
            $subject = '#' . $targetId;
        }

        $targetType = trim($targetType);
        $actionWord = strtolower(trim($actionLabel));
        $adminName = trim($adminName) !== '' ? trim($adminName) : 'Admin';

        if ($subject !== '' && $targetType !== '') {
            return biotern_admin_activity_compact_text($adminName . ' ' . $actionWord . ' ' . $targetType . ': ' . $subject . '.', 500);
        }
        if ($subject !== '') {
            return biotern_admin_activity_compact_text($adminName . ' ' . $actionWord . ' ' . $subject . '.', 500);
        }
        if ($targetType !== '') {
            return biotern_admin_activity_compact_text($adminName . ' ' . $actionWord . ' ' . $targetType . ' on ' . $page . '.', 500);
        }

        return biotern_admin_activity_compact_text($adminName . ' ' . $actionWord . ' ' . $page . '.', 500);
    }
}

if (!function_exists('biotern_admin_activity_log')) {
    function biotern_admin_activity_log(mysqli $conn, string $action, string $targetType = '', ?string $targetId = null, array $details = [], ?string $targetName = null, ?string $activityComment = null): void
    {
        if (!biotern_admin_activity_table_ready($conn)) {
            return;
        }

        $adminUserId = (int)($_SESSION['user_id'] ?? 0);
        $adminName = trim((string)($_SESSION['name'] ?? ''));
        $adminUsername = trim((string)($_SESSION['username'] ?? ''));
        $adminEmail = trim((string)($_SESSION['email'] ?? ''));

        if ($adminUserId > 0 && ($adminName === '' || $adminUsername === '' || $adminEmail === '')) {
            $stmtUser = $conn->prepare('SELECT name, username, email FROM users WHERE id = ? LIMIT 1');
            if ($stmtUser) {
                $stmtUser->bind_param('i', $adminUserId);
                $stmtUser->execute();
                $row = $stmtUser->get_result()->fetch_assoc() ?: [];
                $stmtUser->close();
                $adminName = $adminName !== '' ? $adminName : trim((string)($row['name'] ?? ''));
                $adminUsername = $adminUsername !== '' ? $adminUsername : trim((string)($row['username'] ?? ''));
                $adminEmail = $adminEmail !== '' ? $adminEmail : trim((string)($row['email'] ?? ''));
            }
        }

        if ($adminName === '') {
            $adminName = $adminUsername !== '' ? $adminUsername : 'Admin';
        }

        $page = biotern_admin_activity_page();
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $action = strtolower(trim($action));
        $actionLabel = biotern_admin_activity_action_label($action);
        $targetType = trim($targetType);
        $targetId = $targetId !== null ? trim($targetId) : null;
        $targetName = $targetName !== null ? biotern_admin_activity_compact_text($targetName) : null;
        $activityComment = $activityComment !== null && trim($activityComment) !== ''
            ? biotern_admin_activity_compact_text($activityComment, 500)
            : biotern_admin_activity_build_comment($adminName, $actionLabel, $targetType, $targetName, $targetId, $page);
        $ip = biotern_admin_activity_client_ip();
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $stmt = $conn->prepare('INSERT INTO admin_activity_logs
            (admin_user_id, admin_name, admin_username, admin_email, action, action_label, target_type, target_id, target_name, activity_comment, page, request_method, ip_address, user_agent, details_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmt) {
            return;
        }

        $stmt->bind_param(
            'issssssssssssss',
            $adminUserId,
            $adminName,
            $adminUsername,
            $adminEmail,
            $action,
            $actionLabel,
            $targetType,
            $targetId,
            $targetName,
            $activityComment,
            $page,
            $method,
            $ip,
            $userAgent,
            $detailsJson
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('biotern_admin_activity_auto_log')) {
    function biotern_admin_activity_auto_log($conn = null): void
    {
        static $logged = false;
        if ($logged) {
            return;
        }
        $logged = true;

        if (!($conn instanceof mysqli) || $conn->connect_errno) {
            return;
        }

        $role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
        if ($role !== 'admin') {
            return;
        }

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $page = biotern_admin_activity_page();
        $skipPages = [
            'auth-login.php',
            'auth-two-factor.php',
            'reports-admin-logs.php',
            'theme-customizer.php',
            'notifications-feed.php',
            'notifications-actions.php',
        ];

        if (in_array($page, $skipPages, true)) {
            return;
        }

        $request = $method === 'POST' ? $_POST : $_GET;
        $shouldLog = $method === 'POST'
            || strpos($page, 'export') !== false
            || strpos($page, 'download') !== false
            || isset($request['delete'])
            || isset($request['delete_id'])
            || isset($request['remove'])
            || isset($request['archive'])
            || isset($request['approve'])
            || isset($request['reject'])
            || isset($request['export'])
            || isset($request['download'])
            || isset($request['action']);

        if (!$shouldLog) {
            return;
        }

        $action = biotern_admin_activity_infer_action($page, $method, $request);
        $importantActions = ['create', 'add', 'edit', 'update', 'delete', 'import', 'export', 'approve', 'reject', 'archive', 'restore'];
        if (!in_array($action, $importantActions, true)) {
            return;
        }

        $targetType = biotern_admin_activity_target_type($page);
        $targetId = null;
        foreach (['id', 'student_id', 'user_id', 'course_id', 'section_id', 'department_id', 'coordinator_id', 'supervisor_id', 'company_id', 'internship_id', 'ojt_id', 'delete_id'] as $idKey) {
            if (!isset($request[$idKey])) {
                continue;
            }

            $rawTargetId = biotern_admin_activity_flat_value($request[$idKey]);

            if (trim((string)$rawTargetId) !== '') {
                $targetId = (string)$rawTargetId;
                break;
            }
        }
        $targetName = biotern_admin_activity_lookup_target_name($conn, $page, $targetType, $targetId, $request);

        $details = [
            'query' => biotern_admin_activity_clean_details($_GET),
            'form' => $method === 'POST' ? biotern_admin_activity_clean_details($_POST) : [],
            'files' => [],
        ];

        foreach ($_FILES as $key => $file) {
            if (is_array($file) && isset($file['name'])) {
                $details['files'][(string)$key] = is_array($file['name']) ? array_values($file['name']) : (string)$file['name'];
            }
        }

        biotern_admin_activity_log($conn, $action, $targetType, $targetId, $details, $targetName);
    }
}
