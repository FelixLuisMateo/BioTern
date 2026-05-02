<?php

if (!function_exists('biotern_admin_activity_table_ready')) {
    function biotern_admin_activity_table_ready(mysqli $conn): bool
    {
        return (bool)$conn->query("CREATE TABLE IF NOT EXISTS admin_activity_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_user_id BIGINT UNSIGNED NULL,
            admin_name VARCHAR(255) NULL,
            admin_username VARCHAR(191) NULL,
            admin_email VARCHAR(255) NULL,
            action VARCHAR(50) NOT NULL,
            action_label VARCHAR(120) NOT NULL,
            target_type VARCHAR(120) NULL,
            target_id VARCHAR(120) NULL,
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

if (!function_exists('biotern_admin_activity_log')) {
    function biotern_admin_activity_log(mysqli $conn, string $action, string $targetType = '', ?string $targetId = null, array $details = []): void
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
        $ip = biotern_admin_activity_client_ip();
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $stmt = $conn->prepare('INSERT INTO admin_activity_logs
            (admin_user_id, admin_name, admin_username, admin_email, action, action_label, target_type, target_id, page, request_method, ip_address, user_agent, details_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmt) {
            return;
        }

        $stmt->bind_param(
            'issssssssssss',
            $adminUserId,
            $adminName,
            $adminUsername,
            $adminEmail,
            $action,
            $actionLabel,
            $targetType,
            $targetId,
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
            'notifications-feed.php',
            'notifications-actions.php',
        ];

        if (in_array($page, $skipPages, true)) {
            return;
        }

        $request = $method === 'POST' ? $_POST : $_GET;
        $loggableViewPrefixes = [
            'students',
            'applications-review',
            'attendance',
            'external-attendance',
            'fingerprint_mapping',
            'biometric-machine',
            'ojt',
            'courses',
            'departments',
            'sections',
            'companies',
            'coordinators',
            'supervisors',
            'users',
            'create_admin',
            'settings-',
            'auth-register',
            'theme-customizer',
        ];

        $isAdminWorkPage = false;
        foreach ($loggableViewPrefixes as $prefix) {
            if (strpos($page, $prefix) === 0) {
                $isAdminWorkPage = true;
                break;
            }
        }

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
            || isset($request['action'])
            || ($method === 'GET' && $isAdminWorkPage);

        if (!$shouldLog) {
            return;
        }

        $action = biotern_admin_activity_infer_action($page, $method, $request);
        $targetType = biotern_admin_activity_target_type($page);
        $targetId = null;
        foreach (['id', 'student_id', 'user_id', 'course_id', 'section_id', 'department_id', 'delete_id'] as $idKey) {
            if (isset($request[$idKey]) && trim((string)$request[$idKey]) !== '') {
                $targetId = (string)$request[$idKey];
                break;
            }
        }

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

        biotern_admin_activity_log($conn, $action, $targetType, $targetId, $details);
    }
}
