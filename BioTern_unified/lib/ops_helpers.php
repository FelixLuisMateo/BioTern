<?php
require_once dirname(__DIR__) . '/config/db.php';
function get_current_user_role(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $raw = $_SESSION['role']
        ?? $_SESSION['user_role']
        ?? $_SESSION['account_role']
        ?? $_SESSION['user_type']
        ?? $_SESSION['type']
        ?? 'guest';
    return strtolower(trim((string)$raw));
}

function get_current_user_id_or_zero(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function require_roles_json(array $allowed_roles): void
{
    $role = get_current_user_role();
    $allowed = array_map(static function ($r) {
        return strtolower(trim((string)$r));
    }, $allowed_roles);
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Forbidden: insufficient role permission.']);
        exit;
    }
}

function require_roles_page(array $allowed_roles): void
{
    $role = get_current_user_role();
    $allowed = array_map(static function ($r) {
        return strtolower(trim((string)$r));
    }, $allowed_roles);
    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        echo '<h3>Forbidden</h3><p>You do not have permission to access this page.</p>';
        exit;
    }
}

if (!function_exists('table_exists')) {
    function table_exists(mysqli $conn, string $table_name): bool
    {
        $safe = $conn->real_escape_string($table_name);
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return $res instanceof mysqli_result && $res->num_rows > 0;
    }
}

function insert_audit_log(
    mysqli $conn,
    ?int $user_id,
    string $action,
    string $entity_type,
    ?int $entity_id,
    array $before_data = [],
    array $after_data = [],
    string $ip = '',
    string $user_agent = ''
): void {
    if (!table_exists($conn, 'audit_logs')) {
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, before_data, after_data, ip_address, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
        return;
    }

    $before_json = !empty($before_data) ? json_encode($before_data) : null;
    $after_json = !empty($after_data) ? json_encode($after_data) : null;
    $entity_id_val = $entity_id;
    $user_id_val = $user_id;
    $stmt->bind_param(
        'ississss',
        $user_id_val,
        $action,
        $entity_type,
        $entity_id_val,
        $before_json,
        $after_json,
        $ip,
        $user_agent
    );
    $stmt->execute();
    $stmt->close();
}

function create_notification(mysqli $conn, int $user_id, string $title, string $message): void
{
    if (!table_exists($conn, 'notifications')) {
        return;
    }

    $has_title = false;
    $has_message = false;
    $has_type = false;
    $has_data = false;

    $col_res = $conn->query("SHOW COLUMNS FROM notifications");
    if ($col_res instanceof mysqli_result) {
        while ($col = $col_res->fetch_assoc()) {
            $field = strtolower((string)($col['Field'] ?? ''));
            if ($field === 'title') $has_title = true;
            if ($field === 'message') $has_message = true;
            if ($field === 'type') $has_type = true;
            if ($field === 'data') $has_data = true;
        }
    }

    if ($has_title && $has_message) {
        $stmt = $conn->prepare(
            "INSERT INTO notifications (user_id, title, message, is_read, created_at, updated_at)
             VALUES (?, ?, ?, 0, NOW(), NOW())"
        );
        if ($stmt) {
            $stmt->bind_param('iss', $user_id, $title, $message);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }

    if ($has_type && $has_data) {
        $payload = json_encode(['title' => $title, 'message' => $message], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            $payload = $message;
        }
        $type = 'system';
        $stmt = $conn->prepare(
            "INSERT INTO notifications (user_id, type, data, is_read, created_at, updated_at)
             VALUES (?, ?, ?, 0, NOW(), NOW())"
        );
        if ($stmt) {
            $stmt->bind_param('iss', $user_id, $type, $payload);
            $stmt->execute();
            $stmt->close();
        }
    }
}


