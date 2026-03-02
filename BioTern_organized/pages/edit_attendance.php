<?php
require_once __DIR__ . '/lib/ops_helpers.php';
require_once __DIR__ . '/lib/attendance_rules.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Database Connection
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

$attendance_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$attendance = null;
$student = null;

if ($attendance_id > 0) {
    $query = "
        SELECT 
            a.*,
            s.id as student_id,
            s.first_name,
            s.last_name,
            s.student_id as student_number,
            s.profile_picture
        FROM attendances a
        LEFT JOIN students s ON a.student_id = s.id
        WHERE a.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $attendance_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $attendance = $result->fetch_assoc();
        $student = [
            'id' => $attendance['student_id'],
            'name' => $attendance['first_name'] . ' ' . $attendance['last_name'],
            'number' => $attendance['student_number'],
            'picture' => $attendance['profile_picture']
        ];
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $attendance_id > 0) {
    $current_user_id = get_current_user_id_or_zero();
    $current_role = get_current_user_role();
    $can_direct_edit = in_array($current_role, ['admin', 'coordinator'], true);
    $morning_in = isset($_POST['morning_time_in']) ? $_POST['morning_time_in'] : $attendance['morning_time_in'];
    $morning_out = isset($_POST['morning_time_out']) ? $_POST['morning_time_out'] : $attendance['morning_time_out'];
    $break_in = isset($_POST['break_time_in']) ? $_POST['break_time_in'] : $attendance['break_time_in'];
    $break_out = isset($_POST['break_time_out']) ? $_POST['break_time_out'] : $attendance['break_time_out'];
    $afternoon_in = isset($_POST['afternoon_time_in']) ? $_POST['afternoon_time_in'] : $attendance['afternoon_time_in'];
    $afternoon_out = isset($_POST['afternoon_time_out']) ? $_POST['afternoon_time_out'] : $attendance['afternoon_time_out'];
    $status = isset($_POST['status']) ? $_POST['status'] : $attendance['status'];
    $remarks = isset($_POST['remarks']) ? $_POST['remarks'] : $attendance['remarks'];
    
    $candidate = [
        'morning_time_in' => $morning_in,
        'morning_time_out' => $morning_out,
        'break_time_in' => $break_in,
        'break_time_out' => $break_out,
        'afternoon_time_in' => $afternoon_in,
        'afternoon_time_out' => $afternoon_out
    ];
    $validation = attendance_validate_full_record($candidate);
    if (!$validation['ok']) {
        $error_msg = $validation['message'];
    } elseif ($can_direct_edit) {
        $before_data = $attendance;
        $update_query = "
            UPDATE attendances SET
                morning_time_in = ?,
                morning_time_out = ?,
                break_time_in = ?,
                break_time_out = ?,
                afternoon_time_in = ?,
                afternoon_time_out = ?,
                status = ?,
                remarks = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ssssssssi', $morning_in, $morning_out, $break_in, $break_out, $afternoon_in, $afternoon_out, $status, $remarks, $attendance_id);
        
        if ($stmt->execute()) {
            $success_msg = "Attendance record updated successfully!";
            insert_audit_log(
                $conn,
                $current_user_id,
                'attendance_manual_edit',
                'attendance',
                $attendance_id,
                $before_data,
                $candidate,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );

            // Refresh attendance data
            $stmt->close();
            $query = "SELECT * FROM attendances WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $attendance_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $attendance = $result->fetch_assoc();
        } else {
            $error_msg = "Error updating record: " . $stmt->error;
        }
        $stmt->close();
    } else {
        if (!table_exists($conn, 'attendance_correction_requests')) {
            $error_msg = "Correction workflow table is missing. Run db_updates_operations.sql first.";
        } else {
            $payload = json_encode([
                'morning_time_in' => $morning_in,
                'morning_time_out' => $morning_out,
                'break_time_in' => $break_in,
                'break_time_out' => $break_out,
                'afternoon_time_in' => $afternoon_in,
                'afternoon_time_out' => $afternoon_out,
                'status' => $status,
                'remarks' => $remarks
            ]);
            $reason = isset($_POST['correction_reason']) ? trim((string)$_POST['correction_reason']) : '';
            if ($reason === '') {
                $reason = 'Requested via edit attendance form';
            }
            $req_stmt = $conn->prepare("
                INSERT INTO attendance_correction_requests
                (attendance_id, requested_by, requester_role, correction_reason, requested_changes, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW())
            ");
            $req_stmt->bind_param('iisss', $attendance_id, $current_user_id, $current_role, $reason, $payload);
            if ($req_stmt->execute()) {
                $success_msg = "Correction request submitted for approval.";
                insert_audit_log(
                    $conn,
                    $current_user_id,
                    'attendance_correction_requested',
                    'attendance_correction_request',
                    (int)$req_stmt->insert_id,
                    [],
                    ['attendance_id' => $attendance_id, 'reason' => $reason],
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                );
            } else {
                $error_msg = "Error submitting correction request: " . $req_stmt->error;
            }
            $req_stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Attendance - BioTern</title>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <style>
        body { background-color: #f5f5f5; }
        .edit-container { max-width: 800px; margin: 30px auto; }
        .time-input { max-width: 150px; }
        .status-badge { font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container edit-container">
        <?php if (isset($success_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($attendance): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Edit Attendance Record</h4>
                </div>
                
                <div class="card-body">
                    <!-- Student Info -->
                    <div class="row mb-4 pb-3 border-bottom">
                        <div class="col-md-2">
                            <?php if ($student['picture'] && file_exists(__DIR__ . '/' . $student['picture'])): ?>
                                <img src="<?php echo htmlspecialchars($student['picture']); ?>" alt="Student" class="img-fluid rounded">
                            <?php else: ?>
                                <div class="bg-light rounded p-3 text-center">
                                    <div style="font-size: 2rem; color: #999;">
                                        <?php echo strtoupper(substr($student['name'], 0, 1)); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-10">
                            <h5><?php echo htmlspecialchars($student['name']); ?></h5>
                            <p class="text-muted mb-0">Student ID: <?php echo htmlspecialchars($student['number']); ?></p>
                            <p class="text-muted mb-0">Date: <strong><?php echo date('M d, Y', strtotime($attendance['attendance_date'])); ?></strong></p>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <!-- Morning Session -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Morning Time In</label>
                                <input type="time" name="morning_time_in" class="form-control time-input" value="<?php echo htmlspecialchars($attendance['morning_time_in'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Morning Time Out</label>
                                <input type="time" name="morning_time_out" class="form-control time-input" value="<?php echo htmlspecialchars($attendance['morning_time_out'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Break Session -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Break Time In</label>
                                <input type="time" name="break_time_in" class="form-control time-input" value="<?php echo htmlspecialchars($attendance['break_time_in'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Break Time Out</label>
                                <input type="time" name="break_time_out" class="form-control time-input" value="<?php echo htmlspecialchars($attendance['break_time_out'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Afternoon Session -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Afternoon Time In</label>
                                <input type="time" name="afternoon_time_in" class="form-control time-input" value="<?php echo htmlspecialchars($attendance['afternoon_time_in'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Afternoon Time Out</label>
                                <input type="time" name="afternoon_time_out" class="form-control time-input" value="<?php echo htmlspecialchars($attendance['afternoon_time_out'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Status and Remarks -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="pending" <?php echo $attendance['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $attendance['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $attendance['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Remarks</label>
                                <input type="text" name="remarks" class="form-control" value="<?php echo htmlspecialchars($attendance['remarks'] ?? ''); ?>" placeholder="Optional remarks">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Correction Reason (used when direct edit permission is missing)</label>
                                <input type="text" name="correction_reason" class="form-control" placeholder="Explain why this attendance should be corrected">
                            </div>
                        </div>
                        
                        <!-- Buttons -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="feather-save"></i> Save Changes
                                </button>
                                <a href="attendance.php" class="btn btn-secondary">
                                    <i class="feather-x"></i> Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <h5 class="text-danger">Attendance Record Not Found</h5>
                    <p class="text-muted mb-3">The attendance record you're trying to edit could not be found.</p>
                    <a href="attendance.php" class="btn btn-primary">Back to Attendance</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
</body>
</html>
