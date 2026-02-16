<?php
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

// Get current logged-in user ID (assuming session is set up)
// Default to 1 if not set, you should replace this with actual session/auth logic
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

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
            $response = approveAttendance($conn, $ids, $current_user_id);
            break;
            
        case 'reject':
            $response = rejectAttendance($conn, $ids, $remarks, $current_user_id);
            break;
            
        case 'delete':
            $response = deleteAttendance($conn, $ids);
            break;
            
        case 'edit_status':
            $response = editStatus($conn, $ids, $new_status, $current_user_id);
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
    
    return [
        'success' => $affected_rows > 0,
        'message' => $affected_rows > 0 ? "Successfully updated status to '$new_status' for $affected_rows record(s)" : "No records were updated",
        'updated_count' => $affected_rows
    ];
}
?>
