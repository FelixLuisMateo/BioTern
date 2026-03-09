<?php
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

require_once dirname(__DIR__) . '/lib/evaluation_unlock.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_user_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$can_manage_eval_unlock = in_array($current_user_role, ['admin', 'coordinator'], true);
$eval_flash_message = '';
$eval_flash_type = 'success';

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

function students_view_column_exists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    return ($res && $res->num_rows > 0);
}

// Get student ID from URL parameter
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id == 0) {
    header('Location: idnotfound-404.php?source=students-view&id=' . urlencode($student_id));
    exit;
}

// Fetch Student Details
$course_internal_expr = students_view_column_exists($conn, 'courses', 'internal_hours') ? 'COALESCE(c.internal_hours, 0)' : '0';
$course_external_expr = students_view_column_exists($conn, 'courses', 'external_hours') ? 'COALESCE(c.external_hours, 0)' : '0';
$course_total_expr = students_view_column_exists($conn, 'courses', 'total_ojt_hours') ? 'COALESCE(c.total_ojt_hours, 0)' : '0';

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
        s.supervisor_id AS student_supervisor_id,
        s.coordinator_id AS student_coordinator_id,
        c.name as course_name,
        c.id as course_id,
        {$course_internal_expr} AS course_internal_hours,
        {$course_external_expr} AS course_external_hours,
        {$course_total_expr} AS course_total_ojt_hours,
        d.name as department_name,
        sec.name as section_name,
        sv_map.user_id AS student_supervisor_user_id,
        co_map.user_id AS student_coordinator_user_id,
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
    LEFT JOIN supervisors sv_map ON sv_map.id = s.supervisor_id
    LEFT JOIN coordinators co_map ON co_map.id = s.coordinator_id
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

$can_view_student = false;
if ($current_user_role === 'admin') {
    $can_view_student = true;
} elseif ($current_user_role === 'student') {
    $can_view_student = ((int)($student['user_id'] ?? 0) === $current_user_id);
} elseif ($current_user_role === 'supervisor') {
    $can_view_student = (
        (int)($student['supervisor_id'] ?? 0) === $current_user_id ||
        (int)($student['student_supervisor_user_id'] ?? 0) === $current_user_id
    );
} elseif ($current_user_role === 'coordinator') {
    $assigned = (
        (int)($student['coordinator_id'] ?? 0) === $current_user_id ||
        (int)($student['student_coordinator_user_id'] ?? 0) === $current_user_id
    );

    $course_scoped = false;
    $tbl = $conn->query("SHOW TABLES LIKE 'coordinator_courses'");
    if ($tbl && $tbl->num_rows > 0) {
        $course_id_for_scope = (int)($student['course_id'] ?? 0);
        if ($course_id_for_scope > 0) {
            $stmt_scope = $conn->prepare("SELECT id FROM coordinator_courses WHERE coordinator_user_id = ? AND course_id = ? LIMIT 1");
            if ($stmt_scope) {
                $stmt_scope->bind_param('ii', $current_user_id, $course_id_for_scope);
                $stmt_scope->execute();
                $course_scoped = (bool)$stmt_scope->get_result()->fetch_assoc();
                $stmt_scope->close();
            }
        }
    }
    $can_view_student = ($assigned || $course_scoped);
}

if (!$can_view_student) {
    header('Location: students.php?denied=1');
    exit;
}

$message_recipients = [];
$push_recipient = function (int $user_id, string $label) use (&$message_recipients): void {
    if ($user_id <= 0 || trim($label) === '') {
        return;
    }
    if (!isset($message_recipients[$user_id])) {
        $message_recipients[$user_id] = $label;
    }
};

$student_user_id = (int)($student['user_id'] ?? 0);
$supervisor_user_id = (int)($student['supervisor_id'] ?? 0);
if ($supervisor_user_id <= 0) {
    $supervisor_user_id = (int)($student['student_supervisor_user_id'] ?? 0);
}
$coordinator_user_id = (int)($student['coordinator_id'] ?? 0);
if ($coordinator_user_id <= 0) {
    $coordinator_user_id = (int)($student['student_coordinator_user_id'] ?? 0);
}

if ($current_user_role === 'student') {
    $push_recipient($coordinator_user_id, 'Coordinator');
    $push_recipient($supervisor_user_id, 'Supervisor');
} elseif ($current_user_role === 'coordinator') {
    $push_recipient($student_user_id, 'Student: ' . trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
    $push_recipient($supervisor_user_id, 'Supervisor');
} elseif ($current_user_role === 'supervisor') {
    $push_recipient($student_user_id, 'Student: ' . trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
    $push_recipient($coordinator_user_id, 'Coordinator');
} else {
    $push_recipient($student_user_id, 'Student: ' . trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
    $push_recipient($coordinator_user_id, 'Coordinator');
    $push_recipient($supervisor_user_id, 'Supervisor');
}

unset($message_recipients[$current_user_id]);
$selected_receiver_id = isset($_GET['receiver_id']) ? (int)$_GET['receiver_id'] : 0;
if ($selected_receiver_id <= 0 && !empty($message_recipients)) {
    $selected_receiver_id = (int)array_key_first($message_recipients);
}
if ($selected_receiver_id > 0) {
    $_GET['receiver_id'] = $selected_receiver_id;
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

$internal_total_hours = isset($student['internal_total_hours']) ? intval($student['internal_total_hours']) : 0;
if ($internal_total_hours <= 0) {
    $internal_total_hours = (int)($student['course_internal_hours'] ?? 0);
}
if ($internal_total_hours <= 0) {
    $internal_total_hours = (int)($student['course_total_ojt_hours'] ?? 0);
}
if ($internal_total_hours < 0) {
    $internal_total_hours = 0;
}
$external_total_hours = isset($student['external_total_hours']) ? intval($student['external_total_hours']) : 0;
if ($external_total_hours <= 0) {
    $external_total_hours = (int)($student['course_external_hours'] ?? 0);
}
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

function document_exists_for_user(mysqli $conn, string $table, array $userIds): bool {
    if (empty($userIds)) {
        return false;
    }
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return false;
    }
    static $documentTableCache = [];
    if (!array_key_exists($safeTable, $documentTableCache)) {
        $tableExists = false;
        $hasUserIdColumn = false;

        $tableStmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
        if ($tableStmt) {
            $tableStmt->bind_param('s', $safeTable);
            $tableStmt->execute();
            $tableExists = (bool)$tableStmt->get_result()->fetch_row();
            $tableStmt->close();
        }

        if ($tableExists) {
            $columnName = 'user_id';
            $columnStmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
            if ($columnStmt) {
                $columnStmt->bind_param('ss', $safeTable, $columnName);
                $columnStmt->execute();
                $hasUserIdColumn = (bool)$columnStmt->get_result()->fetch_row();
                $columnStmt->close();
            }
        }

        $documentTableCache[$safeTable] = $tableExists && $hasUserIdColumn;
    }

    if (!$documentTableCache[$safeTable]) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $types = str_repeat('i', count($userIds));
    $sql = "SELECT id FROM {$safeTable} WHERE user_id IN ({$placeholders}) LIMIT 1";
    $stmtDoc = $conn->prepare($sql);
    if (!$stmtDoc) {
        return false;
    }
    $bind = [$types];
    foreach ($userIds as $i => $uid) {
        $bind[] = &$userIds[$i];
    }
    call_user_func_array([$stmtDoc, 'bind_param'], $bind);
    $stmtDoc->execute();
    $exists = (bool)$stmtDoc->get_result()->fetch_assoc();
    $stmtDoc->close();
    return $exists;
}

$doc_lookup_ids = array_values(array_unique(array_filter([
    (int)($student['id'] ?? 0),
    (int)($student['user_id'] ?? 0)
], function ($v) { return $v > 0; })));

$doc_status = [
    'application' => document_exists_for_user($conn, 'application_letter', $doc_lookup_ids),
    'endorsement' => document_exists_for_user($conn, 'endorsement_letter', $doc_lookup_ids),
    'moa' => document_exists_for_user($conn, 'moa', $doc_lookup_ids),
    'dau_moa' => document_exists_for_user($conn, 'dau_moa', $doc_lookup_ids),
    'waiver' => document_exists_for_user($conn, 'waiver', $doc_lookup_ids),
    'resume' => document_exists_for_user($conn, 'resume', $doc_lookup_ids),
];

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
<?php
$page_title = 'BioTern || Student Profile - ' . $student['first_name'] . ' ' . $student['last_name'];
$page_styles = array('assets/css/management-students-view-page.css');
$page_scripts = array(
    'assets/js/students-view-runtime.js',
    'assets/js/theme-customizer-init.min.js',
);
include 'includes/header.php';
?>
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
            <div class="main-content app-students-view-main-content">
                <div class="row">
                    <!-- Student Card Left Side -->
                    <div class="col-xxl-4 col-xl-6">
                        <div class="card stretch stretch-full">
                            <div class="card-body">
                                <?php if ($eval_flash_message !== ''): ?>
                                    <div class="alert alert-<?php echo $eval_flash_type === 'danger' ? 'danger' : 'success'; ?> mb-3">
                                        <?php echo htmlspecialchars($eval_flash_message); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-4 text-center">
                                    <div class="wd-150 ht-150 mx-auto mb-3 position-relative">
                                        <div class="avatar-image wd-150 ht-150 border border-5 border-gray-3">
                                            <?php
                                            $profile_img = resolve_profile_image_url((string)($student['profile_picture'] ?? ''));
                                            if ($profile_img !== null):
                                            ?>
                                                <img src="<?php echo htmlspecialchars($profile_img); ?>" alt="Profile" class="img-fluid">
                                            <?php else: ?>
                                                <img src="assets/images/avatar/<?php echo ($student['id'] % 5) + 1; ?>.png" alt="" class="img-fluid">
                                            <?php endif; ?>
                                        </div>
                                        <div class="wd-10 ht-10 text-success rounded-circle position-absolute translate-middle app-status-dot-position">
                                            <i class="bi bi-patch-check-fill"></i>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <a href="javascript:void(0);" class="fs-14 fw-bold d-block"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></a>
                                        <a href="javascript:void(0);" class="fs-12 fw-normal text-muted d-block"><?php echo htmlspecialchars($student['email']); ?></a>
                                    </div>
                                    <div class="fs-12 fw-normal text-muted text-center profile-stats app-students-profile-stats mb-4">
                                        <div class="stat-card app-students-stat-card hours-remaining-card app-students-hours-remaining-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder mb-0" id="hoursRemaining">
                                                <?php
                                                $hours = intdiv($remaining_seconds, 3600);
                                                $mins = intdiv(($remaining_seconds % 3600), 60);
                                                $secs = $remaining_seconds % 60;
                                                echo $hours . 'h:' . str_pad((string)$mins, 2, '0', STR_PAD_LEFT) . 'm:' . str_pad((string)$secs, 2, '0', STR_PAD_LEFT) . 's';
                                                ?>
                                            </h6>
                                            <p class="fs-12 text-muted mb-0">Hours Remaining</p>
                                        </div>
                                        <div class="stat-card app-students-stat-card completion-card app-students-completion-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder mb-0" id="completionValue"><?php echo number_format($completion_percentage, 2); ?>%</h6>
                                            <p class="fs-12 text-muted mb-0">Completion</p>
                                        </div>
                                        <div class="stat-card app-students-stat-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder" id="internalHoursValue"><?php echo intval($internal_remaining_display); ?>/<?php echo intval($internal_total_hours); ?></h6>
                                            <p class="fs-12 text-muted mb-0">Internal Hours</p>
                                        </div>
                                        <div class="stat-card app-students-stat-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder"><?php echo intval($external_remaining_display); ?>/<?php echo intval($external_total_hours); ?></h6>
                                            <p class="fs-12 text-muted mb-0">External Hours</p>
                                        </div>
                                    </div>
                                    <?php if ($is_clocked_in): ?>
                                        <div class="alert alert-soft-success-message p-2 mb-3" role="alert">
                                            <i class="feather-check-circle me-2"></i>
                                            <span class="fs-12">Student is currently clocked in</span>
                                        </div>
                                    <?php elseif ($has_attendance_today): ?>
                                        <div class="alert alert-soft-info-message attendance-clocked-out-alert p-2 mb-3" role="alert">
                                            <i class="feather-clock me-2"></i>
                                            <span class="fs-12 fw-bold">Student has attendance today and is currently clocked out</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-soft-warning-message p-2 mb-3" role="alert">
                                            <i class="feather-alert-circle me-2"></i>
                                            <span class="fs-12">Student has no attendance today</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <ul class="list-unstyled mb-4">
                                    <li class="profile-contact-item app-students-profile-contact-item mb-4">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-map-pin"></i>Location</span>
                                        <a href="javascript:void(0);" class="profile-contact-value app-students-profile-contact-value"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></a>
                                    </li>
                                    <li class="profile-contact-item app-students-profile-contact-item mb-4">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-phone"></i>Mobile Phone</span>
                                        <a href="javascript:void(0);" class="profile-contact-value app-students-profile-contact-value"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></a>
                                    </li>
                                    <li class="profile-contact-item app-students-profile-contact-item mb-0">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-mail"></i>Email</span>
                                        <a href="javascript:void(0);" class="profile-contact-value app-students-profile-contact-value"><?php echo htmlspecialchars($student['email']); ?></a>
                                    </li>
                                </ul>
                                <div class="d-flex gap-2 text-center pt-4">
                                    <a href="javascript:void(0);" class="w-50 btn btn-light-brand">
                                        <i class="feather-trash-2 me-2"></i>
                                        <span>Delete</span>
                                    </a>
                                    <a href="students-edit.php?id=<?php echo $student['id']; ?>" class="w-50 btn btn-primary">
                                        <i class="feather-edit me-2"></i>
                                        <span>Edit Profile</span>
                                    </a>
                                </div>
                                <div class="d-grid gap-2 text-center pt-2">
                                    <a href="students-edit.php?id=<?php echo $student['id']; ?>#upload-profile-picture" class="btn btn-info">
                                        <i class="feather-image me-2"></i>
                                        <span>Upload Profile Picture</span>
                                    </a>
                                    <a href="generate_resume.php?id=<?php echo $student['id']; ?>" class="btn btn-success" target="_blank">
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
                                            <a href="students-edit.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-light-brand">Edit Profile</a>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Career Objective</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars(!empty($student['bio']) ? $student['bio'] : 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Student ID</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['student_id']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">First Name</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['first_name']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Middle Name</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['middle_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Last Name</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['last_name']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Course</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Department</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Section</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['section_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Internal Hours (Remaining/Total)</div>
                                                    <div class="fw-semibold" id="internalHoursDetailValue"><?php echo intval($internal_remaining_display); ?> / <?php echo intval($internal_total_hours); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">External Hours (Remaining/Total)</div>
                                                    <div class="fw-semibold"><?php echo intval($external_remaining_display); ?> / <?php echo intval($external_total_hours); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Email Address</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['email']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Mobile Number</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Date of Birth</div>
                                                    <div class="fw-semibold"><?php echo formatDate($student['date_of_birth']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Gender</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars(ucfirst($student['gender'] ?? 'N/A')); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Supervisor</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['supervisor_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Coordinator</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['coordinator_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Home Address</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['address'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Emergency Contact</div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($student['emergency_contact'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Status</div>
                                                    <div class="fw-semibold"><?php echo getStatusBadge($student['status']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Date Registered</div>
                                                    <div class="fw-semibold"><?php echo formatDate($student['created_at']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Date Fingerprint Registered</div>
                                                    <div class="fw-semibold"><?php echo formatDate($student['biometric_registered_at']); ?></div>
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
                                            <a href="students-dtr.php?id=<?php echo intval($student['id']); ?>" class="btn btn-sm btn-light-brand">Open DTR</a>
                                        </div>
                                        <ul class="list-unstyled activity-feed">
                                            <?php if (count($activities) > 0): ?>
                                                <?php foreach ($activities as $activity): ?>
                                                    <?php 
                                                    $total_hours = !empty($activity['total_hours']) ? $activity['total_hours'] : calculateTotalHours(
                                                        $activity['morning_time_in'],
                                                        $activity['morning_time_out'],
                                                        $activity['break_time_in'],
                                                        $activity['break_time_out'],
                                                        $activity['afternoon_time_in'],
                                                        $activity['afternoon_time_out']
                                                    );
                                                    ?>
                                                    <li class="d-flex justify-content-between feed-item <?php echo getActivityTypeClass($activity['status']); ?>">
                                                        <div>
                                                            <span class="text-truncate-1-line lead_date">
                                                                Attendance for <?php echo date('M d, Y', strtotime($activity['date'])); ?>
                                                                <span class="date">[<?php echo formatDateTime($activity['created_at']); ?>]</span>
                                                            </span>
                                                            <span class="text">
                                                                Morning: <a href="javascript:void(0);" class="fw-bold text-primary"><?php echo formatTimeRange($activity['morning_time_in'], $activity['morning_time_out']); ?></a>
                                                                &nbsp;|&nbsp;
                                                                Afternoon: <a href="javascript:void(0);" class="fw-bold text-primary"><?php echo formatTimeRange($activity['afternoon_time_in'], $activity['afternoon_time_out']); ?></a>
                                                                &nbsp;|&nbsp;
                                                                Total: <strong><?php echo $total_hours; ?> hrs</strong>
                                                            </span>
                                                        </div>
                                                        <div class="ms-3 d-flex gap-2 align-items-center">
                                                            <?php echo getStatusBadge($activity['status']); ?>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <li class="text-center py-4">
                                                    <p class="text-muted">No attendance records found</p>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Evaluation Tab -->
                                <div class="tab-pane fade" id="evaluationTab" role="tabpanel">
                                    <div class="p-4">
                                        <div class="mb-4 d-flex align-items-center justify-content-between">
                                            <h5 class="fw-bold mb-0">Supervisor Evaluation:</h5>
                                            <?php if ($is_evaluation_unlocked): ?>
                                                <span class="badge bg-soft-success text-success">Unlocked</span>
                                            <?php else: ?>
                                                <span class="badge bg-soft-warning text-warning">Locked</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($is_evaluation_unlocked): ?>
                                            <div class="alert alert-soft-success-message p-4 mb-4" role="alert">
                                                <div class="d-flex">
                                                    <div class="me-3 d-none d-md-block"><i class="feather-check-circle fs-1"></i></div>
                                                    <div>
                                                        <p class="fw-bold mb-1">Evaluation form is unlocked</p>
                                                        <p class="fs-12 text-muted mb-0">Supervisor can now submit the final evaluation for this student.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-dismissible alert-soft-info-message p-4 mb-4" role="alert">
                                                <div class="d-flex">
                                                    <div class="me-3 d-none d-md-block"><i class="feather-info fs-1"></i></div>
                                                    <div>
                                                        <p class="fw-bold mb-1">Evaluation is still locked</p>
                                                        <p class="fs-12 text-muted mb-0">Completion requirements are not yet fully met.</p>
                                                        <?php if (!empty($evaluation_gate_state['reasons']) && is_array($evaluation_gate_state['reasons'])): ?>
                                                            <ul class="mb-0 mt-2 ps-3">
                                                                <?php foreach ($evaluation_gate_state['reasons'] as $reason): ?>
                                                                    <li class="fs-12 text-muted"><?php echo htmlspecialchars((string)$reason); ?></li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        <?php endif; ?>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($can_manage_eval_unlock): ?>
                                            <div class="border rounded p-3 mb-4">
                                                <p class="fw-semibold mb-2">Coordinator/Admin Override</p>
                                                <form method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                                                    <input type="hidden" name="student_id" value="<?php echo (int)$student_id; ?>">
                                                    <input type="text" name="eval_unlock_note" class="form-control app-max-w-340" placeholder="Optional note for audit">
                                                    <?php if ($is_evaluation_unlocked): ?>
                                                        <button type="submit" name="eval_unlock_action" value="lock" class="btn btn-outline-danger btn-sm">Lock Evaluation</button>
                                                    <?php else: ?>
                                                        <button type="submit" name="eval_unlock_action" value="unlock" class="btn btn-outline-success btn-sm">Unlock Evaluation</button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        <?php endif; ?>

                                        <div class="text-center py-5">
                                            <i class="feather-inbox fs-1 text-muted mb-3 d-block"></i>
                                            <p class="text-muted"><?php echo $is_evaluation_unlocked ? 'Waiting for supervisor submission' : 'Waiting for unlock requirements'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <div class="document-generation">
        <h3>Generate Documents</h3>
        <div class="mb-2 d-flex flex-wrap gap-2">
            <span class="badge <?php echo !empty($doc_status['application']) ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?>">Application <?php echo !empty($doc_status['application']) ? 'Ready' : 'Missing'; ?></span>
            <span class="badge <?php echo !empty($doc_status['endorsement']) ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?>">Endorsement <?php echo !empty($doc_status['endorsement']) ? 'Ready' : 'Missing'; ?></span>
            <span class="badge <?php echo !empty($doc_status['moa']) ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?>">MOA <?php echo !empty($doc_status['moa']) ? 'Ready' : 'Missing'; ?></span>
            <span class="badge <?php echo !empty($doc_status['dau_moa']) ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?>">DAU MOA <?php echo !empty($doc_status['dau_moa']) ? 'Ready' : 'Missing'; ?></span>
            <span class="badge <?php echo !empty($doc_status['waiver']) ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?>">Waiver <?php echo !empty($doc_status['waiver']) ? 'Ready' : 'Missing'; ?></span>
            <span class="badge <?php echo !empty($doc_status['resume']) ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger'; ?>">Resume <?php echo !empty($doc_status['resume']) ? 'Ready' : 'Missing'; ?></span>
        </div>
        <a href="../documents/generate_document.php?student_id=<?php echo (int)$student['id']; ?>&doc_type=application" target="_blank" class="btn btn-sm btn-light">Application Letter</a>
        <a href="../documents/generate_document.php?student_id=<?php echo (int)$student['id']; ?>&doc_type=endorsement" target="_blank" class="btn btn-sm btn-light">Endorsement Letter</a>
        <a href="../documents/generate_document.php?student_id=<?php echo (int)$student['id']; ?>&doc_type=moa" target="_blank" class="btn btn-sm btn-light">MOA</a>
        <a href="../documents/generate_document.php?student_id=<?php echo (int)$student['id']; ?>&doc_type=dtr" target="_blank" class="btn btn-sm btn-light">DTR</a>
        <a href="../documents/generate_document.php?student_id=<?php echo (int)$student['id']; ?>&doc_type=waiver" target="_blank" class="btn btn-sm btn-light">Waiver</a>
        <a href="../documents/generate_document.php?student_id=<?php echo (int)$student['id']; ?>&doc_type=resume" target="_blank" class="btn btn-sm btn-light">Resume</a>
    </div>

    <?php if (!empty($message_recipients)): ?>
        <div class="messaging-box mb-3">
            <h3 class="mb-2">Messaging</h3>
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="id" value="<?php echo (int)$student['id']; ?>">
                <div class="col-12 col-md-5">
                    <label class="form-label">Send message to</label>
                    <select class="form-select" name="receiver_id">
                        <?php foreach ($message_recipients as $recipient_id => $recipient_label): ?>
                            <option value="<?php echo (int)$recipient_id; ?>" <?php echo ($selected_receiver_id === (int)$recipient_id) ? 'selected' : ''; ?>><?php echo htmlspecialchars($recipient_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-primary">Open Conversation</button>
                </div>
            </form>
        </div>
        <?php include 'messages.php'; ?>
    <?php endif; ?>

    <div class="evaluation-access">
        <h3>Completion & Evaluation</h3>
        <?php
        $student_id = (int)$student['id'];
        $hours_rendered = $hours_rendered ?? 0;
        $required_hours = ($assignment_track === 'external') ? $external_total_hours : $internal_total_hours;
        if ($required_hours <= 0) {
            $required_hours = ($assignment_track === 'external') ? 250 : 600;
        }
        if ($hours_rendered >= $required_hours) {
            echo '<a href="evaluate.php?student_id=' . $student_id . '" class="btn btn-success">Evaluate Internship</a>';
            echo '<a href="certificate.php?student_id=' . $student_id . '" class="btn btn-primary">Generate Certificate</a>';
        } else {
            echo '<p>Locked: rendered hours (' . number_format((float)$hours_rendered, 1) . ') are below required hours (' . number_format((float)$required_hours, 1) . ').</p>';
        }
        ?>
    </div>
    <div id="students-view-runtime-config"
         data-internal-total-hours="<?php echo (int)$internal_total_hours; ?>"
         data-student-id="<?php echo (int)$student['id']; ?>"
         data-remaining-seconds="<?php echo (int)$remaining_seconds; ?>"
         data-remaining-seconds-without-open="<?php echo (int)$remaining_seconds_without_open; ?>"
         data-is-clocked-in="<?php echo $is_clocked_in ? '1' : '0'; ?>"
         data-open-clock-in-raw="<?php echo htmlspecialchars((string)($open_clock_in_time ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
         hidden></div>
<?php include 'includes/footer.php'; ?>

<?php
$conn->close();
?>



