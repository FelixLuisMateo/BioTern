<?php
require_once dirname(__DIR__) . '/config/db.php';
// Database Connection
$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

require_once dirname(__DIR__) . '/lib/evaluation_unlock.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_user_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$can_manage_eval_unlock = in_array($current_user_role, ['admin', 'coordinator'], true);
$can_delete_student = in_array($current_user_role, ['admin', 'coordinator', 'supervisor'], true);
$eval_flash_message = '';
$eval_flash_type = 'success';

if (!function_exists('table_exists')) {
    function table_exists(mysqli $conn, string $table): bool {
        $safe = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
        return ($res instanceof mysqli_result) && $res->num_rows > 0;
    }
}

if (!function_exists('table_has_column')) {
    function table_has_column(mysqli $conn, string $table, string $column): bool {
        $safe_table = $conn->real_escape_string($table);
        $safe_col = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$safe_table}` LIKE '{$safe_col}'");
        return ($res instanceof mysqli_result) && $res->num_rows > 0;
    }
}

function resolve_profile_image_url(string $profilePath): ?string {
    $clean = ltrim(str_replace('\\', '/', trim($profilePath)), '/');
    if ($clean === '') {
        return null;
    }
    $rootPath = dirname(__DIR__) . '/' . $clean;
    if (!file_exists($rootPath)) {
        return null;
    }
    $mtime = @filemtime($rootPath);
    return $clean . ($mtime ? ('?v=' . $mtime) : '');
}

// Get student ID from URL parameter
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id == 0) {
    header('Location: idnotfound-404.php?source=students-view&id=' . urlencode($student_id));
    exit;
}

// Fetch Student Details
$student_query = "
    SELECT 
        s.id,
        s.user_id,
        s.student_id,
        COALESCE(NULLIF(u_student.profile_picture, ''), NULLIF(s.profile_picture, '')) AS profile_picture,
        s.first_name,
        s.last_name,
        s.middle_name,
        s.email,
        s.bio,
        s.department_id,
        s.phone,
        s.date_of_birth,
        s.gender,
        s.address,
        s.emergency_contact,
        s.status,
        s.biometric_registered,
        s.biometric_registered_at,
        s.created_at,
        s.internal_total_hours,
        s.internal_total_hours_remaining,
        s.external_total_hours,
        s.external_total_hours_remaining,
        s.assignment_track,
        s.supervisor_name,
        s.coordinator_name,
        c.name as course_name,
        c.id as course_id,
        d.name as department_name,
        sec.name as section_name,
        i.id as internship_id,
        i.supervisor_id,
        i.coordinator_id,
        i.rendered_hours,
        i.required_hours,
        i.status as internship_status
    FROM students s
    LEFT JOIN users u_student ON s.user_id = u_student.id
    LEFT JOIN courses c ON s.course_id = c.id
    LEFT JOIN departments d ON d.id = s.department_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
    WHERE s.id = ?
    LIMIT 1
";

$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: idnotfound-404.php?source=students-view&id=' . urlencode($student_id));
    exit;
}

$student = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'delete_student') {
    if (!$can_delete_student) {
        $eval_flash_type = 'danger';
        $eval_flash_message = 'You do not have permission to delete student accounts.';
    } else {
        $posted_student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        if ($posted_student_id !== $student_id) {
            $eval_flash_type = 'danger';
            $eval_flash_message = 'Invalid student deletion request.';
        } else {
            $linked_user_id = (int)($student['user_id'] ?? 0);
            $conn->begin_transaction();
            try {
                // Remove student-related child rows first where available.
                $student_cleanup = [
                    ['table' => 'attendances', 'column' => 'student_id'],
                    ['table' => 'internships', 'column' => 'student_id'],
                    ['table' => 'evaluations', 'column' => 'student_id'],
                    ['table' => 'evaluation_unlocks', 'column' => 'student_id'],
                    ['table' => 'certificates', 'column' => 'student_id'],
                    ['table' => 'hour_logs', 'column' => 'student_id'],
                    ['table' => 'manual_dtr_attachments', 'column' => 'student_id'],
                    ['table' => 'ojt_supervisor_reviews', 'column' => 'student_id'],
                    ['table' => 'ojt_edit_audit', 'column' => 'student_id'],
                ];

                foreach ($student_cleanup as $item) {
                    if (!table_exists($conn, $item['table']) || !table_has_column($conn, $item['table'], $item['column'])) {
                        continue;
                    }
                    $sql = "DELETE FROM `{$item['table']}` WHERE `{$item['column']}` = ?";
                    $stmt_cleanup = $conn->prepare($sql);
                    if ($stmt_cleanup) {
                        $stmt_cleanup->bind_param('i', $student_id);
                        $stmt_cleanup->execute();
                        $stmt_cleanup->close();
                    }
                }

                $del_student = $conn->prepare('DELETE FROM students WHERE id = ? LIMIT 1');
                if (!$del_student) {
                    throw new Exception('Failed to prepare student deletion.');
                }
                $del_student->bind_param('i', $student_id);
                if (!$del_student->execute()) {
                    $err = $del_student->error;
                    $del_student->close();
                    throw new Exception('Failed to delete student row: ' . $err);
                }
                $del_student->close();

                if ($linked_user_id > 0) {
                    $user_cleanup = [
                        ['table' => 'admin', 'columns' => ['user_id']],
                        ['table' => 'coordinators', 'columns' => ['user_id']],
                        ['table' => 'supervisors', 'columns' => ['user_id']],
                        ['table' => 'notifications', 'columns' => ['user_id']],
                        ['table' => 'login_logs', 'columns' => ['user_id']],
                        ['table' => 'application_letter', 'columns' => ['user_id']],
                        ['table' => 'endorsement_letter', 'columns' => ['user_id']],
                        ['table' => 'moa', 'columns' => ['user_id']],
                        ['table' => 'dau_moa', 'columns' => ['user_id']],
                        ['table' => 'document_workflow', 'columns' => ['user_id']],
                        ['table' => 'messages', 'columns' => ['from_user_id', 'to_user_id']],
                    ];

                    foreach ($user_cleanup as $item) {
                        if (!table_exists($conn, $item['table'])) {
                            continue;
                        }
                        $cols = $item['columns'];
                        if (count($cols) === 1) {
                            if (!table_has_column($conn, $item['table'], $cols[0])) {
                                continue;
                            }
                            $sql = "DELETE FROM `{$item['table']}` WHERE `{$cols[0]}` = ?";
                            $stmt_user_cleanup = $conn->prepare($sql);
                            if ($stmt_user_cleanup) {
                                $stmt_user_cleanup->bind_param('i', $linked_user_id);
                                $stmt_user_cleanup->execute();
                                $stmt_user_cleanup->close();
                            }
                        } else {
                            if (!table_has_column($conn, $item['table'], $cols[0]) || !table_has_column($conn, $item['table'], $cols[1])) {
                                continue;
                            }
                            $sql = "DELETE FROM `{$item['table']}` WHERE `{$cols[0]}` = ? OR `{$cols[1]}` = ?";
                            $stmt_user_cleanup = $conn->prepare($sql);
                            if ($stmt_user_cleanup) {
                                $stmt_user_cleanup->bind_param('ii', $linked_user_id, $linked_user_id);
                                $stmt_user_cleanup->execute();
                                $stmt_user_cleanup->close();
                            }
                        }
                    }

                    $del_user = $conn->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
                    if ($del_user) {
                        $del_user->bind_param('i', $linked_user_id);
                        $del_user->execute();
                        $del_user->close();
                    }
                }

                $conn->commit();
                $_SESSION['users_flash_message'] = 'Student and linked user account deleted successfully.';
                $_SESSION['users_flash_type'] = 'success';
                header('Location: students.php');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $eval_flash_type = 'danger';
                $eval_flash_message = $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eval_unlock_action'])) {
    if (!$can_manage_eval_unlock) {
        $eval_flash_type = 'danger';
        $eval_flash_message = 'You do not have permission to change evaluation unlock status.';
    } else {
        $post_student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
        if ($post_student_id !== $student_id) {
            $eval_flash_type = 'danger';
            $eval_flash_message = 'Invalid student unlock request.';
        } else {
            $action = strtolower(trim((string)($_POST['eval_unlock_action'] ?? '')));
            $unlock = ($action === 'unlock');
            $note = isset($_POST['eval_unlock_note']) ? trim((string)$_POST['eval_unlock_note']) : '';
            $override = set_evaluation_unlock_override($conn, $student_id, $unlock, $current_user_id, $note);
            if (!empty($override['ok'])) {
                $eval_flash_type = 'success';
                $eval_flash_message = $unlock ? 'Evaluation form unlocked manually.' : 'Evaluation form locked manually.';
            } else {
                $eval_flash_type = 'danger';
                $eval_flash_message = 'Unable to update evaluation unlock status.';
            }
        }
    }
}

// Check if student has attendance today (any record for today's date).
$today = date('Y-m-d');
$active_today_query = "
    SELECT COUNT(*) as count 
    FROM attendances 
    WHERE student_id = ? AND attendance_date = ?
";
$stmt_active = $conn->prepare($active_today_query);
$stmt_active->bind_param("is", $student_id, $today);
$stmt_active->execute();
$active_result = $stmt_active->get_result();
$active_row = $active_result->fetch_assoc();
$has_attendance_today = $active_row['count'] > 0 ? true : false;

// Check if student is currently clocked in (has morning_time_in but no morning_time_out)
$clocked_in_query = "
    SELECT 
        id,
        morning_time_in,
        morning_time_out,
        afternoon_time_in,
        afternoon_time_out
    FROM attendances 
    WHERE student_id = ? AND attendance_date = ?
    ORDER BY id DESC
    LIMIT 1
";
$stmt_clock = $conn->prepare($clocked_in_query);
$stmt_clock->bind_param("is", $student_id, $today);
$stmt_clock->execute();
$clock_result = $stmt_clock->get_result();
$attendance_record = $clock_result->fetch_assoc();

// Determine if student is currently clocked in
$is_clocked_in = false;
if ($attendance_record) {
    $morning_in = $attendance_record['morning_time_in'];
    $morning_out = $attendance_record['morning_time_out'];
    $afternoon_in = $attendance_record['afternoon_time_in'];
    $afternoon_out = $attendance_record['afternoon_time_out'];
    
    // Student is clocked in if:
    // - Morning clock in exists but no clock out, OR
    // - Afternoon clock in exists but no afternoon clock out
    if (($morning_in && !$morning_out) || ($afternoon_in && !$afternoon_out)) {
        $is_clocked_in = true;
    }
}

// Keep student status aligned with actual clock state (clocked-in => active, else inactive).
$live_clock_status = $is_clocked_in ? 1 : 0;
if ((int)($student['status'] ?? -1) !== $live_clock_status) {
    $status_stmt = $conn->prepare("UPDATE students SET status = ?, updated_at = NOW() WHERE id = ?");
    if ($status_stmt) {
        $status_stmt->bind_param("ii", $live_clock_status, $student_id);
        $status_stmt->execute();
        $status_stmt->close();
    }
    $student['status'] = $live_clock_status;
}

$open_clock_in_time = null;
if ($attendance_record) {
    if (!empty($attendance_record['afternoon_time_in']) && empty($attendance_record['afternoon_time_out'])) {
        $open_clock_in_time = $attendance_record['afternoon_time_in'];
    } elseif (!empty($attendance_record['morning_time_in']) && empty($attendance_record['morning_time_out'])) {
        $open_clock_in_time = $attendance_record['morning_time_in'];
    }
}

// Calculate hours remaining and completion percentage based on real attendance totals
// so timer stays consistent and does not jump back to preset values.
$sum_stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_hours), 0) AS rendered
    FROM attendances
    WHERE student_id = ? AND (status IS NULL OR status <> 'rejected')
");
$sum_stmt->bind_param("i", $student_id);
$sum_stmt->execute();
$sum_row = $sum_stmt->get_result()->fetch_assoc();
$sum_stmt->close();

$hours_rendered = isset($sum_row['rendered']) ? (float)$sum_row['rendered'] : 0.0;
if ($hours_rendered <= 0 && isset($student['rendered_hours'])) {
    $hours_rendered = (float)$student['rendered_hours'];
}

$open_session_seconds = 0;
if ($is_clocked_in && !empty($open_clock_in_time)) {
    $open_ts = strtotime($today . ' ' . $open_clock_in_time);
    if ($open_ts !== false) {
        $open_session_seconds = max(0, time() - $open_ts);
    }
}

$live_rendered_hours = $hours_rendered + ($open_session_seconds / 3600);

$internal_total_hours = isset($student['internal_total_hours']) ? intval($student['internal_total_hours']) : 600;
if ($internal_total_hours < 0) {
    $internal_total_hours = 0;
}
$external_total_hours = isset($student['external_total_hours']) ? intval($student['external_total_hours']) : 0;
if ($external_total_hours < 0) {
    $external_total_hours = 0;
}
if ($internal_total_hours <= 0) {
    // Prevent division by zero and keep dashboard usable when hours are not configured yet.
    $internal_total_hours = 600;
}

$assignment_track = strtolower((string)($student['assignment_track'] ?? 'internal'));
$stored_internal_remaining = isset($student['internal_total_hours_remaining']) && $student['internal_total_hours_remaining'] !== null
    ? (int)$student['internal_total_hours_remaining']
    : null;
$stored_external_remaining = isset($student['external_total_hours_remaining']) && $student['external_total_hours_remaining'] !== null
    ? (int)$student['external_total_hours_remaining']
    : null;

$internal_remaining_hours_live = max(0, $internal_total_hours - $live_rendered_hours);
$external_remaining_hours_live = max(0, $external_total_hours - $live_rendered_hours);
$hours_remaining = ($assignment_track === 'external') ? $external_remaining_hours_live : $internal_remaining_hours_live;
$hours_remaining_without_open = ($assignment_track === 'external')
    ? max(0, $external_total_hours - $hours_rendered)
    : max(0, $internal_total_hours - $hours_rendered);

$remaining_seconds = (int)max(0, round($hours_remaining * 3600));
$remaining_seconds_without_open = (int)max(0, round($hours_remaining_without_open * 3600));
$internal_remaining_display = max(0, (int)floor($internal_remaining_hours_live));
$external_remaining_display = max(0, (int)floor($external_remaining_hours_live));

// Keep stored remaining hours aligned with computed remaining to avoid future timer resets.
$remaining_for_storage = max(0, (int)floor($hours_remaining));
if ($assignment_track === 'external') {
    if ($stored_external_remaining === null || $stored_external_remaining !== $remaining_for_storage) {
        $upd_remaining = $conn->prepare("UPDATE students SET external_total_hours_remaining = ?, updated_at = NOW() WHERE id = ?");
        if ($upd_remaining) {
            $upd_remaining->bind_param("ii", $remaining_for_storage, $student_id);
            $upd_remaining->execute();
            $upd_remaining->close();
        }
    }
} else {
    if ($stored_internal_remaining === null || $stored_internal_remaining !== $remaining_for_storage) {
        $upd_remaining = $conn->prepare("UPDATE students SET internal_total_hours_remaining = ?, updated_at = NOW() WHERE id = ?");
        if ($upd_remaining) {
            $upd_remaining->bind_param("ii", $remaining_for_storage, $student_id);
            $upd_remaining->execute();
            $upd_remaining->close();
        }
    }
}
$internal_completed_hours = max(0, $internal_total_hours - $internal_remaining_display);
$completion_percentage = $internal_total_hours > 0
    ? ($internal_completed_hours / $internal_total_hours) * 100
    : 0;
if ($completion_percentage > 100) {
    $completion_percentage = 100;
}

$evaluation_gate_state = evaluate_and_finalize_student($conn, $student_id, 0);
$evaluation_unlock_state = get_evaluation_unlock_state($conn, $student_id);
$is_evaluation_unlocked = (bool)($evaluation_unlock_state['is_unlocked'] ?? false);

// Fetch Attendance Records for activity
$activity_query = "
    SELECT 
        att.id,
        att.attendance_date as date,
        att.morning_time_in,
        att.morning_time_out,
        att.break_time_in,
        att.break_time_out,
        att.afternoon_time_in,
        att.afternoon_time_out,
        att.total_hours,
        att.status,
        att.created_at
    FROM attendances att
    WHERE att.student_id = ?
    ORDER BY att.attendance_date DESC
    LIMIT 10
";

$stmt_activity = $conn->prepare($activity_query);
$stmt_activity->bind_param("i", $student_id);
$stmt_activity->execute();
$activity_result = $stmt_activity->get_result();
$activities = [];
while ($row = $activity_result->fetch_assoc()) {
    $activities[] = $row;
}

// Helper functions
function formatDate($date) {
    if ($date) {
        return date('M d, Y', strtotime($date));
    }
    return 'N/A';
}

function formatDateTime($date) {
    if ($date) {
        return date('M d, Y h:i A', strtotime($date));
    }
    return 'N/A';
}

function getStatusBadge($status) {
    if ($status == 1 || $status == 'ongoing') {
        return '<span class="badge bg-soft-success text-success">Active</span>';
    } elseif ($status == 'approved') {
        return '<span class="badge bg-soft-success text-success">Approved</span>';
    } elseif ($status == 'pending') {
        return '<span class="badge bg-soft-warning text-warning">Pending</span>';
    } elseif ($status == 'rejected') {
        return '<span class="badge bg-soft-danger text-danger">Rejected</span>';
    } else {
        return '<span class="badge bg-soft-danger text-danger">Inactive</span>';
    }
}

function getActivityTypeClass($status) {
    $status = strtolower($status);
    if ($status == 'approved') {
        return 'feed-item-success';
    } elseif ($status == 'pending') {
        return 'feed-item-warning';
    } elseif ($status == 'rejected') {
        return 'feed-item-danger';
    }
    return 'feed-item-info';
}

function formatTimeRange($time_in, $time_out) {
    if ($time_in && $time_out) {
        return date('h:i A', strtotime($time_in)) . ' - ' . date('h:i A', strtotime($time_out));
    }
    return '-';
}

function calculateTotalHours($morning_in, $morning_out, $break_in, $break_out, $afternoon_in, $afternoon_out) {
    $total = 0;
    
    // Morning hours
    if ($morning_in && $morning_out) {
        $morning_time = strtotime($morning_out) - strtotime($morning_in);
        $total += $morning_time / 3600;
    }
    
    // Afternoon hours
    if ($afternoon_in && $afternoon_out) {
        $afternoon_time = strtotime($afternoon_out) - strtotime($afternoon_in);
        $total += $afternoon_time / 3600;
    }
    
    // Subtract break time
    if ($break_in && $break_out) {
        $break_time = strtotime($break_out) - strtotime($break_in);
        $total -= $break_time / 3600;
    }
    
    return round(max(0, $total), 2);
}

?>

<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <script>
        (function () {
            var path = (window.location && window.location.pathname) ? window.location.pathname : '';
            var marker = '/BioTern_unified/';
            var idx = path.toLowerCase().indexOf(marker.toLowerCase());
            var base = idx >= 0 ? path.substring(0, idx + marker.length) : '/BioTern/BioTern_unified/';
            window.__bioternThemeApi = base + 'api/theme-customizer.php';
        })();
    </script>
    <title>BioTern || Student Profile - <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="/BioTern/BioTern_unified/assets/images/favicon.ico?v=20260310">
    <script src="assets/js/theme-preload-init.min.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <script>try{var s=localStorage.getItem('app-skin')||localStorage.getItem('app_skin')||localStorage.getItem('theme'); if(s&&s.indexOf('dark')!==-1)document.documentElement.classList.add('app-skin-dark');}catch(e){};</script>
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/layout-shared-overrides.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main.nxl-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        div.nxl-content {
            flex: 1;
        }
        footer.footer {
            margin-top: auto;
        }
        .profile-stats {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
            gap: 0.75rem;
            align-items: stretch;
        }
        .profile-stats .stat-card {
            min-width: 0;
            padding: 0.55rem 0.6rem !important;
            min-height: 84px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.2rem;
        }
        .profile-stats .hours-remaining-card,
        .profile-stats .completion-card {
            max-width: none;
            justify-self: stretch;
        }
        .profile-stats .stat-card h6 {
            margin-bottom: 0;
            line-height: 1.15;
        }
        .profile-stats .stat-card p {
            margin-bottom: 0;
            line-height: 1.2;
            white-space: nowrap;
        }
        #hoursRemaining {
            white-space: nowrap;
            font-size: 1.08rem;
            letter-spacing: 0.01em;
        }
        .attendance-clocked-out-alert {
            background-color: #e8f4ff !important;
            border: 1px solid #8ec5ff !important;
            color: #0a3761 !important;
        }
        .attendance-clocked-out-alert i,
        .attendance-clocked-out-alert span {
            color: #0a3761 !important;
        }
        .profile-contact-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.7rem !important;
        }
        .profile-contact-item .profile-contact-value {
            text-align: right;
            max-width: 62%;
            word-break: break-word;
        }
        
        /* Dark mode select and Select2 styling */
        select.form-control,
        select.form-select,
        .select2-container--default .select2-selection--single,
        .select2-container--default .select2-selection--multiple {
            color: #333;
            background-color: #ffffff;
        }
        
        /* Dark mode support for Select2 */
        body.dark .select2-container--default .select2-selection--single,
        body.dark .select2-container--default .select2-selection--multiple,
        body[data-bs-theme="dark"] .select2-container--default .select2-selection--single,
        body[data-bs-theme="dark"] .select2-container--default .select2-selection--multiple {
            color: #f0f0f0;
            background-color: #2d3748;
            border-color: #4a5568 !important;
        }
        
        body.dark .select2-container--default .select2-selection--single .select2-selection__rendered,
        body[data-bs-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f0f0f0;
        }
        
        /* Dark mode dropdown menu */
        body.dark .select2-container--default.select2-container--open .select2-dropdown,
        body[data-bs-theme="dark"] .select2-container--default.select2-container--open .select2-dropdown {
            background-color: #2d3748;
            border-color: #4a5568;
        }
        
        body.dark .select2-results__option,
        body[data-bs-theme="dark"] .select2-results__option {
            color: #f0f0f0;
            background-color: #2d3748;
        }
        
        body.dark .select2-results__option--highlighted[aria-selected],
        body[data-bs-theme="dark"] .select2-results__option--highlighted[aria-selected] {
            background-color: #667eea;
            color: #ffffff;
        }
        
        body.dark .select2-container--default select.form-control,
        body.dark select.form-control,
        body.dark select.form-select,
        body[data-bs-theme="dark"] select.form-control,
        body[data-bs-theme="dark"] select.form-select {
            color: #f0f0f0;
            background-color: #2d3748;
            border-color: #4a5568;
        }
        
        body.dark select.form-control option,
        body.dark select.form-select option,
        body[data-bs-theme="dark"] select.form-control option,
        body[data-bs-theme="dark"] select.form-select option {
            color: #f0f0f0;
            background-color: #2d3748;
        }

        /* Keep minimenu hover panel above header/page-header on this page */
        @media (min-width: 1025px) {
            html.minimenu .nxl-navigation:hover {
                z-index: 5000 !important;
            }

            html.minimenu .nxl-navigation:hover .navbar-wrapper,
            html.minimenu .nxl-navigation:hover .navbar-content,
            html.minimenu .nxl-navigation:hover .m-header {
                position: relative !important;
                z-index: 5001 !important;
            }
        }

        @media (max-width: 767.98px) {
            .profile-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.55rem;
            }
            .profile-stats .stat-card {
                min-height: 74px;
                padding: 0.45rem 0.5rem !important;
                gap: 0.1rem;
            }
            .profile-stats .stat-card h6 {
                font-size: 1.05rem;
                line-height: 1.1;
            }
            .profile-stats .stat-card p {
                font-size: 0.78rem;
                line-height: 1.1;
            }
            #hoursRemaining {
                font-size: 1.05rem;
            }
            .profile-contact-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.15rem;
                margin-bottom: 0.55rem !important;
            }
            .profile-contact-item .profile-contact-value {
                text-align: left;
                max-width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php
require_once dirname(__DIR__) . '/config/db.php';
include_once dirname(__DIR__) . '/includes/navigation.php'; ?>
    <!--! Header !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">
                    <div class="dropdown nxl-h-item nxl-header-search">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <i class="feather-search"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown">
                            <div class="input-group search-form">
                                <span class="input-group-text">
                                    <i class="feather-search fs-6 text-muted"></i>
                                </span>
                                <input type="text" class="form-control search-input-field" placeholder="Search....">
                                <span class="input-group-text">
                                    <button type="button" class="btn-close"></button>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="nxl-h-item d-none d-sm-flex">
                        <div class="full-screen-switcher">
                            <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
                                <i class="feather-maximize maximize"></i>
                                <i class="feather-minimize minimize"></i>
                            </a>
                        </div>
                    </div>
                    <div class="nxl-h-item dark-light-theme">
                        <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button">
                            <i class="feather-moon"></i>
                        </a>
                        <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display: none">
                            <i class="feather-sun"></i>
                        </a>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                            <i class="feather-bell"></i>
                            <span class="badge bg-danger nxl-h-badge">3</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                            <div class="d-flex justify-content-between align-items-center notifications-head">
                                <h6 class="fw-bold text-dark mb-0">Notifications</h6>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                            <img src="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((isset($_SESSION['profile_picture']) && trim((string)$_SESSION['profile_picture']) !== '' ? ltrim(str_replace('\\', '/', trim((string)$_SESSION['profile_picture'])), '/') : ('assets/images/avatar/' . (((int)($_SESSION['user_id'] ?? 0) % 5) + 1) . '.png')), ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar me-0" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((isset($_SESSION['profile_picture']) && trim((string)$_SESSION['profile_picture']) !== '' ? ltrim(str_replace('\\', '/', trim((string)$_SESSION['profile_picture'])), '/') : ('assets/images/avatar/' . (((int)($_SESSION['user_id'] ?? 0) % 5) + 1) . '.png')), ENT_QUOTES, 'UTF-8'); ?>" alt="user-image" class="img-fluid user-avtar" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                    <div>
                                        <h6 class="text-dark mb-0"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'BioTern User'), ENT_QUOTES, 'UTF-8'); ?></h6>
                                        <span class="fs-12 fw-medium text-muted"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)($_SESSION['email'] ?? 'admin@biotern.local'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-settings"></i>
                                <span>Account Settings</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="./auth-login-cover.php?logout=1" class="dropdown-item">
                                <i class="feather-log-out"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!--! Main Content !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Student Profile</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item">View</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            <a href="javascript:void(0);" class="btn btn-icon btn-light-brand successAlertMessage">
                                <i class="feather-star"></i>
                            </a>
                            <a href="javascript:void(0);" class="btn btn-icon btn-light-brand">
                                <i class="feather-eye me-2"></i>
                                <span>Follow</span>
                            </a>
                            <a href="students.php" class="btn btn-primary">
                                <i class="feather-arrow-left me-2"></i>
                                <span>Back to List</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="row">
                    <!-- Student Card Left Side -->
                    <div class="col-xxl-4 col-xl-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body">
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($eval_flash_message !== ''): ?>
                                    <div class="alert alert-<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $eval_flash_type === 'danger' ? 'danger' : 'success'; ?> mb-3">
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($eval_flash_message); ?>
                                    </div>
                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                <div class="mb-4 text-center">
                                    <div class="wd-150 ht-150 mx-auto mb-3 position-relative">
                                        <div class="avatar-image wd-150 ht-150 border border-5 border-gray-3">
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
$profile_img = resolve_profile_image_url((string)($student['profile_picture'] ?? ''));
                                            if ($profile_img !== null):
                                            ?>
                                                <img src="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($profile_img); ?>" alt="Profile" class="img-fluid">
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                                <img src="assets/images/avatar/<?php
require_once dirname(__DIR__) . '/config/db.php';
echo ($student['id'] % 5) + 1; ?>.png" alt="" class="img-fluid">
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                        </div>
                                        <div class="wd-10 ht-10 text-success rounded-circle position-absolute translate-middle" style="top: 76%; right: 10px">
                                            <i class="bi bi-patch-check-fill"></i>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <a href="javascript:void(0);" class="fs-14 fw-bold d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></a>
                                        <a href="javascript:void(0);" class="fs-12 fw-normal text-muted d-block"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['email']); ?></a>
                                    </div>
                                    <div class="fs-12 fw-normal text-muted text-center profile-stats mb-4">
                                        <div class="stat-card hours-remaining-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder mb-0" id="hoursRemaining">
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
$hours = intdiv($remaining_seconds, 3600);
                                                $mins = intdiv(($remaining_seconds % 3600), 60);
                                                $secs = $remaining_seconds % 60;
                                                echo $hours . 'h:' . str_pad((string)$mins, 2, '0', STR_PAD_LEFT) . 'm:' . str_pad((string)$secs, 2, '0', STR_PAD_LEFT) . 's';
                                                ?>
                                            </h6>
                                            <p class="fs-12 text-muted mb-0">Hours Remaining</p>
                                        </div>
                                        <div class="stat-card completion-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder mb-0" id="completionValue"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo number_format($completion_percentage, 2); ?>%</h6>
                                            <p class="fs-12 text-muted mb-0">Completion</p>
                                        </div>
                                        <div class="stat-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder" id="internalHoursValue"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo intval($internal_remaining_display); ?>/<?php
require_once dirname(__DIR__) . '/config/db.php';
echo intval($internal_total_hours); ?></h6>
                                            <p class="fs-12 text-muted mb-0">Internal Hours</p>
                                        </div>
                                        <div class="stat-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo intval($external_remaining_display); ?>/<?php
require_once dirname(__DIR__) . '/config/db.php';
echo intval($external_total_hours); ?></h6>
                                            <p class="fs-12 text-muted mb-0">External Hours</p>
                                        </div>
                                    </div>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($is_clocked_in): ?>
                                        <div class="alert alert-soft-success-message p-2 mb-3" role="alert">
                                            <i class="feather-check-circle me-2"></i>
                                            <span class="fs-12">Student is currently clocked in</span>
                                        </div>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
elseif ($has_attendance_today): ?>
                                        <div class="alert alert-soft-info-message attendance-clocked-out-alert p-2 mb-3" role="alert">
                                            <i class="feather-clock me-2"></i>
                                            <span class="fs-12 fw-bold">Student has attendance today and is currently clocked out</span>
                                        </div>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                        <div class="alert alert-soft-warning-message p-2 mb-3" role="alert">
                                            <i class="feather-alert-circle me-2"></i>
                                            <span class="fs-12">Student has no attendance today</span>
                                        </div>
                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                </div>
                                <ul class="list-unstyled mb-4">
                                    <li class="profile-contact-item mb-4">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-map-pin"></i>Location</span>
                                        <a href="javascript:void(0);" class="profile-contact-value"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['address'] ?? 'N/A'); ?></a>
                                    </li>
                                    <li class="profile-contact-item mb-4">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-phone"></i>Mobile Phone</span>
                                        <a href="javascript:void(0);" class="profile-contact-value"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></a>
                                    </li>
                                    <li class="profile-contact-item mb-0">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-mail"></i>Email</span>
                                        <a href="javascript:void(0);" class="profile-contact-value"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['email']); ?></a>
                                    </li>
                                </ul>
                                <div class="d-flex gap-2 text-center pt-4">
                                    <?php if ($can_delete_student): ?>
                                    <form method="post" class="w-50" onsubmit="return confirm('Delete this student and linked user account permanently?');">
                                        <input type="hidden" name="action" value="delete_student">
                                        <input type="hidden" name="student_id" value="<?php echo (int)$student['id']; ?>">
                                        <button type="submit" class="btn btn-light-brand w-100">
                                            <i class="feather-trash-2 me-2"></i>
                                            <span>Delete</span>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="students-edit.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $student['id']; ?>" class="w-50 btn btn-primary">
                                        <i class="feather-edit me-2"></i>
                                        <span>Edit Profile</span>
                                    </a>
                                </div>
                                <div class="d-grid gap-2 text-center pt-2">
                                    <a href="ojt-view.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $student['id']; ?>" class="btn btn-info">
                                        <i class="feather-file me-2"></i>
                                        <span>OJT Document View</span>
                                    </a>
                                    <a href="generate_resume.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $student['id']; ?>" class="btn btn-success" target="_blank">
                                        <i class="feather-file-text me-2"></i>
                                        <span>Generate Resume</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Information Right Side -->
                    <div class="col-xxl-8 col-xl-6">
                        <div class="card border-top-0">
                            <div class="card-header p-0">
                                <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="myTab" role="tablist">
                                    <li class="nav-item flex-fill border-top" role="presentation">
                                        <a href="javascript:void(0);" class="nav-link active" data-bs-toggle="tab" data-bs-target="#overviewTab" role="tab">Overview</a>
                                    </li>
                                    <li class="nav-item flex-fill border-top" role="presentation">
                                        <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#activityTab" role="tab">Attendance</a>
                                    </li>
                                    <li class="nav-item flex-fill border-top" role="presentation">
                                        <a href="javascript:void(0);" class="nav-link" data-bs-toggle="tab" data-bs-target="#evaluationTab" role="tab">Evaluation</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="tab-content">
                                <!-- Overview Tab -->
                                <div class="tab-pane fade show active p-4" id="overviewTab" role="tabpanel">
                                    <div class="profile-details mb-5">
                                        <div class="mb-4 d-flex align-items-center justify-content-between">
                                            <h5 class="fw-bold mb-0">Profile Details:</h5>
                                            <a href="students-edit.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo $student['id']; ?>" class="btn btn-sm btn-light-brand">Edit Profile</a>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Career Objective</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars(!empty($student['bio']) ? $student['bio'] : 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Student ID</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['student_id']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">First Name</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['first_name']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Middle Name</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['middle_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Last Name</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['last_name']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Course</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Department</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Section</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['section_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Internal Hours (Remaining/Total)</div>
                                                    <div class="fw-semibold" id="internalHoursDetailValue"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo intval($internal_remaining_display); ?> / <?php
require_once dirname(__DIR__) . '/config/db.php';
echo intval($internal_total_hours); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">External Hours (Remaining/Total)</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo intval($external_remaining_display); ?> / <?php
require_once dirname(__DIR__) . '/config/db.php';
echo intval($external_total_hours); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Email Address</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['email']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Mobile Number</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Date of Birth</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatDate($student['date_of_birth']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Gender</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars(ucfirst($student['gender'] ?? 'N/A')); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Supervisor</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['supervisor_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Coordinator</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['coordinator_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Home Address</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['address'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Emergency Contact</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars($student['emergency_contact'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Status</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo getStatusBadge($student['status']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Date Registered</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatDate($student['created_at']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Date Fingerprint Registered</div>
                                                    <div class="fw-semibold"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatDate($student['biometric_registered_at']); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Attendance Tab -->
                                <div class="tab-pane fade" id="activityTab" role="tabpanel">
                                    <div class="recent-activity p-4 pb-0">
                                        <div class="mb-4 pb-2 d-flex justify-content-between">
                                            <h5 class="fw-bold">Recent Attendance Records:</h5>
                                            <a href="students-dtr.php?id=<?php
require_once dirname(__DIR__) . '/config/db.php';
echo intval($student['id']); ?>" class="btn btn-sm btn-light-brand">Open DTR</a>
                                        </div>
                                        <ul class="list-unstyled activity-feed">
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if (count($activities) > 0): ?>
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($activities as $activity): ?>
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
$total_hours = !empty($activity['total_hours']) ? $activity['total_hours'] : calculateTotalHours(
                                                        $activity['morning_time_in'],
                                                        $activity['morning_time_out'],
                                                        $activity['break_time_in'],
                                                        $activity['break_time_out'],
                                                        $activity['afternoon_time_in'],
                                                        $activity['afternoon_time_out']
                                                    );
                                                    ?>
                                                    <li class="d-flex justify-content-between feed-item <?php
require_once dirname(__DIR__) . '/config/db.php';
echo getActivityTypeClass($activity['status']); ?>">
                                                        <div>
                                                            <span class="text-truncate-1-line lead_date">
                                                                Attendance for <?php
require_once dirname(__DIR__) . '/config/db.php';
echo date('M d, Y', strtotime($activity['date'])); ?>
                                                                <span class="date">[<?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatDateTime($activity['created_at']); ?>]</span>
                                                            </span>
                                                            <span class="text">
                                                                Morning: <a href="javascript:void(0);" class="fw-bold text-primary"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatTimeRange($activity['morning_time_in'], $activity['morning_time_out']); ?></a>
                                                                &nbsp;|&nbsp;
                                                                Afternoon: <a href="javascript:void(0);" class="fw-bold text-primary"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo formatTimeRange($activity['afternoon_time_in'], $activity['afternoon_time_out']); ?></a>
                                                                &nbsp;|&nbsp;
                                                                Total: <strong><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $total_hours; ?> hrs</strong>
                                                            </span>
                                                        </div>
                                                        <div class="ms-3 d-flex gap-2 align-items-center">
                                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
echo getStatusBadge($activity['status']); ?>
                                                        </div>
                                                    </li>
                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                                <li class="text-center py-4">
                                                    <p class="text-muted">No attendance records found</p>
                                                </li>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Evaluation Tab -->
                                <div class="tab-pane fade" id="evaluationTab" role="tabpanel">
                                    <div class="p-4">
                                        <div class="mb-4 d-flex align-items-center justify-content-between">
                                            <h5 class="fw-bold mb-0">Supervisor Evaluation:</h5>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($is_evaluation_unlocked): ?>
                                                <span class="badge bg-soft-success text-success">Unlocked</span>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                                <span class="badge bg-soft-warning text-warning">Locked</span>
                                            <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                        </div>

                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($is_evaluation_unlocked): ?>
                                            <div class="alert alert-soft-success-message p-4 mb-4" role="alert">
                                                <div class="d-flex">
                                                    <div class="me-3 d-none d-md-block"><i class="feather-check-circle fs-1"></i></div>
                                                    <div>
                                                        <p class="fw-bold mb-1">Evaluation form is unlocked</p>
                                                        <p class="fs-12 text-muted mb-0">Supervisor can now submit the final evaluation for this student.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                            <div class="alert alert-dismissible alert-soft-info-message p-4 mb-4" role="alert">
                                                <div class="d-flex">
                                                    <div class="me-3 d-none d-md-block"><i class="feather-info fs-1"></i></div>
                                                    <div>
                                                        <p class="fw-bold mb-1">Evaluation is still locked</p>
                                                        <p class="fs-12 text-muted mb-0">Completion requirements are not yet fully met.</p>
                                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
if (!empty($evaluation_gate_state['reasons']) && is_array($evaluation_gate_state['reasons'])): ?>
                                                            <ul class="mb-0 mt-2 ps-3">
                                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
foreach ($evaluation_gate_state['reasons'] as $reason): ?>
                                                                    <li class="fs-12 text-muted"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo htmlspecialchars((string)$reason); ?></li>
                                                                <?php
require_once dirname(__DIR__) . '/config/db.php';
endforeach; ?>
                                                            </ul>
                                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            </div>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>

                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($can_manage_eval_unlock): ?>
                                            <div class="border rounded p-3 mb-4">
                                                <p class="fw-semibold mb-2">Coordinator/Admin Override</p>
                                                <form method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                                                    <input type="hidden" name="student_id" value="<?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$student_id; ?>">
                                                    <input type="text" name="eval_unlock_note" class="form-control" style="max-width: 340px;" placeholder="Optional note for audit">
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
if ($is_evaluation_unlocked): ?>
                                                        <button type="submit" name="eval_unlock_action" value="lock" class="btn btn-outline-danger btn-sm">Lock Evaluation</button>
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
else: ?>
                                                        <button type="submit" name="eval_unlock_action" value="unlock" class="btn btn-outline-success btn-sm">Unlock Evaluation</button>
                                                    <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>
                                                </form>
                                            </div>
                                        <?php
require_once dirname(__DIR__) . '/config/db.php';
endif; ?>

                                        <div class="text-center py-5">
                                            <i class="feather-inbox fs-1 text-muted mb-3 d-block"></i>
                                            <p class="text-muted"><?php
require_once dirname(__DIR__) . '/config/db.php';
echo $is_evaluation_unlocked ? 'Waiting for supervisor submission' : 'Waiting for unlock requirements'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright �</span>
                <script>
                    document.write(new Date().getFullYear());
                </script>
            </p>
            <p class="footer-meta fs-12 mb-0"><span>By: <a href="javascript:void(0);">ACT 2A</a></span> <span>Distributed by: <a href="javascript:void(0);">Group 5</a></span></p>
            <div class="d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Help</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Terms</a>
                <a href="javascript:void(0);" class="fs-11 fw-semibold text-uppercase">Privacy</a>
            </div>
        </footer>
    </main>

    <!-- Scripts -->
    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script src="assets/vendors/js/select2-active.min.js"></script>
    <script src="assets/js/common-init.min.js"></script>
    <script src="assets/js/theme-customizer-init.min.js"></script>

    <script>
        function initializeTimer() {
            const timerElement = document.getElementById('hoursRemaining');
            if (!timerElement) return;
            const completionElement = document.getElementById('completionValue');
            const internalHoursElement = document.getElementById('internalHoursValue');
            const internalHoursDetailElement = document.getElementById('internalHoursDetailValue');
            const internalTotalHours = <?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$internal_total_hours; ?>;

            const studentId = <?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$student['id']; ?>;
            let remainingSeconds = <?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$remaining_seconds; ?>;
            const remainingSecondsWithoutOpen = <?php
require_once dirname(__DIR__) . '/config/db.php';
echo (int)$remaining_seconds_without_open; ?>;
            const isClockedIn = <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $is_clocked_in ? 'true' : 'false'; ?>;
            const openClockInRaw = <?php
require_once dirname(__DIR__) . '/config/db.php';
echo $open_clock_in_time ? json_encode($open_clock_in_time) : 'null'; ?>;
            const storageKey = 'student_timer_state_' + String(studentId);
            const nowRef = new Date();
            const todayKey = [
                nowRef.getFullYear(),
                String(nowRef.getMonth() + 1).padStart(2, '0'),
                String(nowRef.getDate()).padStart(2, '0')
            ].join('-');
            let lastSyncedHour = null;
            let syncInFlight = false;

            function formatHMS(totalSeconds) {
                const safe = Math.max(0, Math.floor(totalSeconds));
                const h = Math.floor(safe / 3600);
                const m = Math.floor((safe % 3600) / 60);
                const s = safe % 60;
                return h + 'h:' + String(m).padStart(2, '0') + 'm:' + String(s).padStart(2, '0') + 's';
            }

            function updateCompletionFromSeconds() {
                if (!completionElement || !Number.isFinite(internalTotalHours) || internalTotalHours <= 0) return;
                const remainingHoursPrecise = Math.max(0, remainingSeconds / 3600);
                const completed = Math.max(0, internalTotalHours - remainingHoursPrecise);
                let pct = (completed / internalTotalHours) * 100;
                if (pct > 100) pct = 100;
                completionElement.textContent = pct.toFixed(2) + '%';
            }

            function updateInternalHoursFromSeconds() {
                if (!internalHoursElement || !Number.isFinite(internalTotalHours) || internalTotalHours <= 0) return;
                const remainingWholeHours = Math.max(0, Math.floor(remainingSeconds / 3600));
                internalHoursElement.textContent = remainingWholeHours + '/' + internalTotalHours;
                if (internalHoursDetailElement) {
                    internalHoursDetailElement.textContent = remainingWholeHours + ' / ' + internalTotalHours;
                }
            }

            function loadState() {
                try {
                    const raw = localStorage.getItem(storageKey);
                    if (!raw) return null;
                    const parsed = JSON.parse(raw);
                    if (!parsed || typeof parsed.seconds === 'undefined') return null;
                    const sec = parseInt(parsed.seconds, 10);
                    if (!Number.isFinite(sec)) return null;
                    return {
                        seconds: Math.max(0, sec),
                        savedAt: parsed.savedAt ? parseInt(parsed.savedAt, 10) : null,
                        sessionDate: parsed.sessionDate || null,
                        clockInRaw: parsed.clockInRaw || null
                    };
                } catch (e) {
                    return null;
                }
            }

            function saveState() {
                try {
                    localStorage.setItem(storageKey, JSON.stringify({
                        seconds: Math.max(0, Math.floor(remainingSeconds)),
                        savedAt: Date.now(),
                        sessionDate: isClockedIn ? todayKey : null,
                        clockInRaw: isClockedIn ? openClockInRaw : null
                    }));
                } catch (e) {}
            }

            function syncRemainingHourToDb() {
                if (!isClockedIn) return;
                const currentHour = Math.max(0, Math.floor(remainingSeconds / 3600));
                if (lastSyncedHour === currentHour) return;
                if (syncInFlight) return;
                syncInFlight = true;

                const body = new URLSearchParams();
                body.set('student_id', String(studentId));
                body.set('remaining_hours', String(currentHour));

                fetch('update_remaining_hours.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data && data.ok) {
                        lastSyncedHour = currentHour;
                    }
                })
                .catch(function(){})
                .finally(function(){ syncInFlight = false; });
            }

            function elapsedSinceOpenClockIn() {
                if (!isClockedIn || !openClockInRaw) return 0;
                const now = new Date();
                const parts = String(openClockInRaw).split(':');
                if (parts.length < 2) return 0;
                const start = new Date(
                    now.getFullYear(),
                    now.getMonth(),
                    now.getDate(),
                    parseInt(parts[0], 10),
                    parseInt(parts[1], 10),
                    parseInt(parts[2] || '0', 10)
                );
                return Math.max(0, Math.floor((now.getTime() - start.getTime()) / 1000));
            }

            const saved = loadState();
            if (isClockedIn) {
                const elapsed = elapsedSinceOpenClockIn();
                if (elapsed > 0) {
                    // Build remaining time from a stable base and live elapsed time to avoid server timezone drift.
                    remainingSeconds = Math.max(0, remainingSecondsWithoutOpen - elapsed);
                }
            }
            if (saved) {
                // Only trust cache for the same active session; otherwise it can pin the timer to stale values.
                const sameSession =
                    isClockedIn &&
                    saved.sessionDate === todayKey &&
                    saved.clockInRaw === openClockInRaw;
                if (sameSession || !isClockedIn) {
                    remainingSeconds = Math.min(remainingSeconds, saved.seconds);
                }
            }

            function updateTimer() {
                timerElement.textContent = formatHMS(remainingSeconds);
                updateInternalHoursFromSeconds();
                updateCompletionFromSeconds();
                if (isClockedIn && remainingSeconds > 0) {
                    remainingSeconds--;
                }
                if (isClockedIn && (remainingSeconds % 10 === 0)) {
                    saveState();
                }
                // Sync DB only when timer hits exact whole hour (e.g., 116:00:00).
                if (isClockedIn && remainingSeconds > 0 && (remainingSeconds % 3600 === 0)) {
                    syncRemainingHourToDb();
                }
            }

            updateTimer();
            setInterval(updateTimer, 1000);

            // Persist value even when not clocked-in so it won't reset on next day/page load.
            saveState();
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    saveState();
                }
            });
            window.addEventListener('beforeunload', function(){
                saveState();
            });
        }

        document.addEventListener('DOMContentLoaded', initializeTimer);
    </script>
</body>

</html>

<?php
require_once dirname(__DIR__) . '/config/db.php';
$conn->close();
?>





