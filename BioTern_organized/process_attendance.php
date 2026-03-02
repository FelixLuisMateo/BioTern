<?php
require_once __DIR__ . '/lib/ops_helpers.php';
require_once __DIR__ . '/lib/attendance_rules.php';
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Connection
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

header('Content-Type: application/json');

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]));
}

$current_user_id = get_current_user_id_or_zero();
$current_role = get_current_user_role();

// Get action and attendance IDs
$action = isset($_POST['action']) ? trim($_POST['action']) : '';
$ids = isset($_POST['id']) ? $_POST['id'] : [];
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';
$new_status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Convert single ID to array
if (!is_array($ids)) {
    $ids = !empty($ids) ? [$ids] : [];
}

$response = [
    'success' => false,
    'message' => 'Invalid request',
    'updated_count' => 0
];

try {
    switch ($action) {
        case 'approve':
            require_roles_json(['admin', 'coordinator', 'supervisor']);
            $response = approveAttendance($conn, $ids, $current_user_id);
            break;
            
        case 'reject':
            require_roles_json(['admin', 'coordinator', 'supervisor']);
            $response = rejectAttendance($conn, $ids, $remarks, $current_user_id);
            break;
            
        case 'delete':
            require_roles_json(['admin', 'coordinator']);
            $response = deleteAttendance($conn, $ids);
            break;
            
        case 'edit_status':
            require_roles_json(['admin', 'coordinator', 'supervisor']);
            $response = editStatus($conn, $ids, $new_status, $current_user_id);
            break;
        
        case 'request_correction':
            require_roles_json(['admin', 'coordinator', 'supervisor', 'student']);
            $response = requestCorrection($conn, $ids, $remarks, $current_user_id, $current_role);
            break;
        
        case 'review_correction':
            require_roles_json(['admin', 'coordinator']);
            $decision = isset($_POST['decision']) ? trim($_POST['decision']) : '';
            $response = reviewCorrection($conn, $ids, $decision, $remarks, $current_user_id);
            break;
            
        default:
            $response['message'] = 'Unknown action: ' . htmlspecialchars($action);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'updated_count' => 0
    ];
}

echo json_encode($response);
$conn->close();

/**
 * Approve attendance records
 */
function approveAttendance($conn, $ids, $current_user_id) {
    if (empty($ids)) {
        return [
            'success' => false,
            'message' => 'No attendance records selected',
            'updated_count' => 0
        ];
    }

    $id_list = implode(',', array_map('intval', $ids));
    $now = date('Y-m-d H:i:s');
    
    $update_query = "
        UPDATE attendances
        SET status = 'approved', 
            approved_by = ?,
            approved_at = ?
        WHERE id IN ($id_list)
    ";
    
    $stmt = $conn->prepare($update_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('is', $current_user_id, $now);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    notifyAttendanceOwners($conn, $ids, 'Attendance Approved', 'Your attendance entry was approved.');
    insert_audit_log(
        $conn,
        $current_user_id,
        'attendance_approve',
        'attendance',
        null,
        ['ids' => $ids],
        ['status' => 'approved', 'affected_rows' => $affected_rows],
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    
    return [
        'success' => $affected_rows > 0,
        'message' => $affected_rows > 0 ? "Successfully approved $affected_rows attendance record(s)" : "No records were updated",
        'updated_count' => $affected_rows
    ];
}

/**
 * Reject attendance records
 */
function rejectAttendance($conn, $ids, $remarks, $current_user_id) {
    if (empty($ids)) {
        return [
            'success' => false,
            'message' => 'No attendance records selected',
            'updated_count' => 0
        ];
    }

    if (empty($remarks)) {
        return [
            'success' => false,
            'message' => 'Rejection reason is required',
            'updated_count' => 0
        ];
    }

    $id_list = implode(',', array_map('intval', $ids));
    $now = date('Y-m-d H:i:s');
    
    $update_query = "
        UPDATE attendances
        SET status = 'rejected', 
            rejection_remarks = ?,
            rejected_by = ?,
            rejected_at = ?
        WHERE id IN ($id_list)
    ";
    
    $stmt = $conn->prepare($update_query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('sis', $remarks, $current_user_id, $now);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    notifyAttendanceOwners($conn, $ids, 'Attendance Rejected', 'Your attendance entry was rejected. Reason: ' . $remarks);
    insert_audit_log(
        $conn,
        $current_user_id,
        'attendance_reject',
        'attendance',
        null,
        ['ids' => $ids],
        ['status' => 'rejected', 'remarks' => $remarks, 'affected_rows' => $affected_rows],
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    
    return [
        'success' => $affected_rows > 0,
        'message' => $affected_rows > 0 ? "Successfully rejected $affected_rows attendance record(s)" : "No records were updated",
        'updated_count' => $affected_rows
    ];
}

/**
 * Delete attendance records
 */
function deleteAttendance($conn, $ids) {
    if (empty($ids)) {
        return [
            'success' => false,
            'message' => 'No attendance records selected',
            'updated_count' => 0
        ];
    }

    $id_list = implode(',', array_map('intval', $ids));
    
    $delete_query = "DELETE FROM attendances WHERE id IN ($id_list)";
    
    if (!$conn->query($delete_query)) {
        throw new Exception("Delete failed: " . $conn->error);
    }
    
    $affected_rows = $conn->affected_rows;
    insert_audit_log(
        $conn,
        get_current_user_id_or_zero(),
        'attendance_delete',
        'attendance',
        null,
        ['ids' => $ids],
        ['affected_rows' => $affected_rows],
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    
    return [
        'success' => $affected_rows > 0,
        'message' => $affected_rows > 0 ? "Successfully deleted $affected_rows attendance record(s)" : "No records were deleted",
        'updated_count' => $affected_rows
    ];
}

/**
 * Edit status of attendance records
 */
function editStatus($conn, $ids, $new_status, $current_user_id) {
    if (empty($ids)) {
        return [
            'success' => false,
            'message' => 'No attendance records selected',
            'updated_count' => 0
        ];
    }

    // Validate status
    $valid_statuses = ['pending', 'approved', 'rejected'];
    if (!in_array($new_status, $valid_statuses)) {
        return [
            'success' => false,
            'message' => 'Invalid status: ' . htmlspecialchars($new_status),
            'updated_count' => 0
        ];
    }

    $id_list = implode(',', array_map('intval', $ids));
    $now = date('Y-m-d H:i:s');
    
    // Update status and audit trail
    if ($new_status === 'approved') {
        $update_query = "
            UPDATE attendances
            SET status = ?,
                approved_by = ?,
                approved_at = ?
            WHERE id IN ($id_list)
        ";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('sis', $new_status, $current_user_id, $now);
    } elseif ($new_status === 'rejected') {
        $update_query = "
            UPDATE attendances
            SET status = ?,
                rejected_by = ?,
                rejected_at = ?
            WHERE id IN ($id_list)
        ";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('sis', $new_status, $current_user_id, $now);
    } else {
        // pending status - just update status
        $update_query = "UPDATE attendances SET status = ? WHERE id IN ($id_list)";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('s', $new_status);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    insert_audit_log(
        $conn,
        $current_user_id,
        'attendance_edit_status',
        'attendance',
        null,
        ['ids' => $ids],
        ['status' => $new_status, 'affected_rows' => $affected_rows],
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );
    
    return [
        'success' => $affected_rows > 0,
        'message' => $affected_rows > 0 ? "Successfully updated status to '$new_status' for $affected_rows record(s)" : "No records were updated",
        'updated_count' => $affected_rows
    ];
}

function requestCorrection($conn, $ids, $remarks, $current_user_id, $current_role) {
    if (empty($ids)) {
        return ['success' => false, 'message' => 'No attendance records selected', 'updated_count' => 0];
    }
    if ($remarks === '') {
        return ['success' => false, 'message' => 'Correction reason is required', 'updated_count' => 0];
    }
    if (!table_exists($conn, 'attendance_correction_requests')) {
        return ['success' => false, 'message' => 'Correction workflow table is missing. Run db_updates_operations.sql first.', 'updated_count' => 0];
    }

    $count = 0;
    foreach ($ids as $id) {
        $attendance_id = (int)$id;
        $stmt = $conn->prepare("
            INSERT INTO attendance_correction_requests
            (attendance_id, requested_by, requester_role, correction_reason, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('iiss', $attendance_id, $current_user_id, $current_role, $remarks);
        if ($stmt->execute()) {
            $count++;
        }
        $stmt->close();
    }

    insert_audit_log(
        $conn,
        $current_user_id,
        'attendance_request_correction',
        'attendance_correction_request',
        null,
        ['attendance_ids' => $ids],
        ['count' => $count, 'reason' => $remarks],
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );

    return [
        'success' => $count > 0,
        'message' => $count > 0 ? "Submitted $count correction request(s)." : 'No correction request was submitted.',
        'updated_count' => $count
    ];
}

function reviewCorrection($conn, $ids, $decision, $remarks, $current_user_id) {
    if (empty($ids)) {
        return ['success' => false, 'message' => 'No correction requests selected', 'updated_count' => 0];
    }
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return ['success' => false, 'message' => 'Decision must be approved or rejected.', 'updated_count' => 0];
    }
    if (!table_exists($conn, 'attendance_correction_requests')) {
        return ['success' => false, 'message' => 'Correction workflow table is missing. Run db_updates_operations.sql first.', 'updated_count' => 0];
    }

    $count = 0;
    foreach ($ids as $id) {
        $request_id = (int)$id;
        $stmt = $conn->prepare("
            UPDATE attendance_correction_requests
            SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_remarks = ?, updated_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        if (!$stmt) {
            continue;
        }
        $stmt->bind_param('sisi', $decision, $current_user_id, $remarks, $request_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $count++;
        }
        $stmt->close();
    }

    insert_audit_log(
        $conn,
        $current_user_id,
        'attendance_review_correction',
        'attendance_correction_request',
        null,
        ['request_ids' => $ids],
        ['decision' => $decision, 'count' => $count, 'remarks' => $remarks],
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    );

    return [
        'success' => $count > 0,
        'message' => $count > 0 ? "Reviewed $count correction request(s)." : 'No correction request was updated.',
        'updated_count' => $count
    ];
}

function notifyAttendanceOwners(mysqli $conn, array $attendance_ids, string $title, string $message): void {
    if (empty($attendance_ids)) {
        return;
    }
    $id_list = implode(',', array_map('intval', $attendance_ids));
    $q = $conn->query("
        SELECT DISTINCT s.user_id
        FROM attendances a
        INNER JOIN students s ON s.id = a.student_id
        WHERE a.id IN ($id_list) AND s.user_id IS NOT NULL
    ");
    if (!$q) {
        return;
    }
    while ($row = $q->fetch_assoc()) {
        create_notification($conn, (int)$row['user_id'], $title, $message);
    }
}
?>
