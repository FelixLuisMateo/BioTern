<?php

function get_current_user_role(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role']) ? (string)$_SESSION['role'] : 'guest';
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
    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Forbidden: insufficient role permission.']);
        exit;
    }
}

function require_roles_page(array $allowed_roles): void
{
    $role = get_current_user_role();
    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        echo '<h3>Forbidden</h3><p>You do not have permission to access this page.</p>';
        exit;
    }
}

function table_exists(mysqli $conn, string $table_name): bool
{
    $safe = $conn->real_escape_string($table_name);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
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

    $stmt = $conn->prepare(
        "INSERT INTO notifications (user_id, title, message, type, is_read, created_at, updated_at)
         VALUES (?, ?, ?, 'attendance', 0, NOW(), NOW())"
    );
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iss', $user_id, $title, $message);
    $stmt->execute();
    $stmt->close();
}

