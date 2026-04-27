<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */

require_once dirname(__DIR__) . '/lib/evaluation_unlock.php';
require_once dirname(__DIR__) . '/lib/attendance_workflow.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/lib/company_profiles.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_user_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$can_manage_eval_unlock = in_array($current_user_role, ['admin', 'coordinator'], true);
$eval_flash_message = '';
$eval_flash_type = 'success';

function resolve_profile_image_url(string $profilePath, int $userId = 0): ?string {
    $resolved = biotern_avatar_public_src($profilePath, $userId);
    if ($resolved === '') {
        return null;
    }
    return $resolved;
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
        sec.code as section_code,
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
$student_latest_internship = null;
$student_company_profile = null;

function internship_column_exists(mysqli $conn, string $column): bool
{
    static $cache = [];
    if (array_key_exists($column, $cache)) {
        return $cache[$column];
    }

    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM internships LIKE '{$safeColumn}'");
    $cache[$column] = ($res instanceof mysqli_result) && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }

    return $cache[$column];
}

function student_internship_start_date(mysqli $conn, int $studentId, string $type): ?string
{
    if ($studentId <= 0 || !internship_column_exists($conn, 'start_date')) {
        return null;
    }

    $sql = "SELECT MIN(start_date) AS start_date FROM internships WHERE student_id = ? AND start_date IS NOT NULL AND start_date <> ''";
    if (internship_column_exists($conn, 'type')) {
        $sql .= " AND LOWER(TRIM(COALESCE(type, 'internal'))) = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $studentId, $type);
    } else {
        if ($type === 'external') {
            return null;
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $studentId);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    $value = trim((string)($row['start_date'] ?? ''));
    return $value !== '' ? $value : null;
}

$student['internal_start_date'] = null;
$student['external_start_date'] = null;
$internalAttendanceStartStmt = $conn->prepare("SELECT MIN(attendance_date) AS start_date FROM attendances WHERE student_id = ?");
if ($internalAttendanceStartStmt) {
    $internalAttendanceStartStmt->bind_param('i', $student_id);
    $internalAttendanceStartStmt->execute();
    $internalAttendanceStartRow = $internalAttendanceStartStmt->get_result()->fetch_assoc() ?: null;
    $internalAttendanceStartStmt->close();
    $internalAttendanceStartValue = trim((string)($internalAttendanceStartRow['start_date'] ?? ''));
    if ($internalAttendanceStartValue !== '') {
        $student['internal_start_date'] = $internalAttendanceStartValue;
    }
}
external_attendance_ensure_schema($conn);
$externalAttendanceStartStmt = $conn->prepare("SELECT MIN(attendance_date) AS start_date FROM external_attendance WHERE student_id = ?");
if ($externalAttendanceStartStmt) {
    $externalAttendanceStartStmt->bind_param('i', $student_id);
    $externalAttendanceStartStmt->execute();
    $externalAttendanceStartRow = $externalAttendanceStartStmt->get_result()->fetch_assoc() ?: null;
    $externalAttendanceStartStmt->close();
    $externalAttendanceStartValue = trim((string)($externalAttendanceStartRow['start_date'] ?? ''));
    if ($externalAttendanceStartValue !== '') {
        $student['external_start_date'] = $externalAttendanceStartValue;
    }
}
if (empty($student['internal_start_date'])) {
    $student['internal_start_date'] = student_internship_start_date($conn, $student_id, 'internal');
}
if (empty($student['external_start_date'])) {
    $student['external_start_date'] = student_internship_start_date($conn, $student_id, 'external');
}

$latestInternshipStmt = $conn->prepare("
    SELECT company_name, company_address, position, status, start_date, end_date
    FROM internships
    WHERE student_id = ? AND deleted_at IS NULL
    ORDER BY updated_at DESC, id DESC
    LIMIT 1
");
if ($latestInternshipStmt) {
    $latestInternshipStmt->bind_param('i', $student_id);
    $latestInternshipStmt->execute();
    $student_latest_internship = $latestInternshipStmt->get_result()->fetch_assoc() ?: null;
    $latestInternshipStmt->close();
}

$latestCompanyName = trim((string)($student_latest_internship['company_name'] ?? ''));
if ($latestCompanyName !== '') {
    $student_company_profile = biotern_company_profile_fetch_by_name($conn, $latestCompanyName);
    if ($student_company_profile) {
        if (trim((string)($student_company_profile['company_name'] ?? '')) !== '') {
            $student_latest_internship['company_name'] = trim((string)$student_company_profile['company_name']);
        }
        if (trim((string)($student_company_profile['company_address'] ?? '')) !== '') {
            $student_latest_internship['company_address'] = trim((string)$student_company_profile['company_address']);
        }
        if (trim((string)($student_company_profile['company_representative'] ?? '')) !== '') {
            $student_latest_internship['company_representative'] = trim((string)$student_company_profile['company_representative']);
        }
        if (trim((string)($student_company_profile['company_representative_position'] ?? '')) !== '') {
            $student_latest_internship['company_representative_position'] = trim((string)$student_company_profile['company_representative_position']);
        }
        if (trim((string)($student_company_profile['supervisor_name'] ?? '')) !== '') {
            $student_latest_internship['supervisor_name'] = trim((string)$student_company_profile['supervisor_name']);
        }
        if (trim((string)($student_company_profile['supervisor_position'] ?? '')) !== '') {
            $student_latest_internship['supervisor_position'] = trim((string)$student_company_profile['supervisor_position']);
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
        a.id,
        a.student_id,
        a.attendance_date,
        a.status,
        a.remarks,
        a.morning_time_in,
        a.morning_time_out,
        a.afternoon_time_in,
        a.afternoon_time_out,
        sec.attendance_session,
        sec.schedule_time_in,
        sec.schedule_time_out,
        sec.late_after_time,
        sec.weekly_schedule_json
    FROM attendances a
    LEFT JOIN students s2 ON a.student_id = s2.id
    LEFT JOIN sections sec ON s2.section_id = sec.id
    WHERE a.student_id = ? AND a.attendance_date = ?
    ORDER BY a.id DESC
    LIMIT 1
";
$stmt_clock = $conn->prepare($clocked_in_query);
$stmt_clock->bind_param("is", $student_id, $today);
$stmt_clock->execute();
$clock_result = $stmt_clock->get_result();
$attendance_record = $clock_result->fetch_assoc();

$open_session = $attendance_record ? attendance_workflow_mark_incomplete_if_needed($conn, $attendance_record) : ['clocked_in_now' => false, 'is_open' => false, 'elapsed_preview_seconds' => 0, 'cutoff_time' => null];
$is_clocked_in = !empty($open_session['clocked_in_now']);

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
if ($attendance_record && !empty($open_session['in_time'])) {
    $open_clock_in_time = (string)$open_session['in_time'];
}

// Calculate hours per track so internal/external totals stay isolated after track changes.
$sum_stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_hours), 0) AS rendered
    FROM attendances
    WHERE student_id = ? AND (status IS NULL OR status <> 'rejected')
");
$sum_stmt->bind_param("i", $student_id);
$sum_stmt->execute();
$sum_row = $sum_stmt->get_result()->fetch_assoc();
$sum_stmt->close();

external_attendance_ensure_schema($conn);
$external_sum_stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_hours), 0) AS rendered
    FROM external_attendance
    WHERE student_id = ? AND status <> 'rejected'
");
$external_hours_rendered = 0.0;
if ($external_sum_stmt) {
    $external_sum_stmt->bind_param("i", $student_id);
    $external_sum_stmt->execute();
    $external_sum_row = $external_sum_stmt->get_result()->fetch_assoc();
    $external_hours_rendered = isset($external_sum_row['rendered']) ? (float)$external_sum_row['rendered'] : 0.0;
    $external_sum_stmt->close();
}

$internal_hours_rendered = isset($sum_row['rendered']) ? (float)$sum_row['rendered'] : 0.0;
if ($internal_hours_rendered <= 0 && isset($student['rendered_hours']) && strtolower(trim((string)($student['assignment_track'] ?? 'internal'))) !== 'external') {
    $internal_hours_rendered = (float)$student['rendered_hours'];
}

$open_session_seconds = ($attendance_record && !empty($open_session['is_open']))
    ? (int)($open_session['elapsed_preview_seconds'] ?? 0)
    : 0;

$internal_total_hours = isset($student['internal_total_hours']) ? intval($student['internal_total_hours']) : 140;
if ($internal_total_hours < 0) {
    $internal_total_hours = 0;
}
$external_total_hours = isset($student['external_total_hours']) ? intval($student['external_total_hours']) : 0;
if ($external_total_hours < 0) {
    $external_total_hours = 0;
}
if ($external_total_hours <= 0) {
    $external_total_hours = 250;
}
if ($internal_total_hours <= 0) {
    // Prevent division by zero and keep dashboard usable when hours are not configured yet.
    $internal_total_hours = 140;
}

$assignment_track = strtolower((string)($student['assignment_track'] ?? 'internal'));
$stored_internal_remaining = isset($student['internal_total_hours_remaining']) && $student['internal_total_hours_remaining'] !== null
    ? (int)$student['internal_total_hours_remaining']
    : null;
$stored_external_remaining = isset($student['external_total_hours_remaining']) && $student['external_total_hours_remaining'] !== null
    ? (int)$student['external_total_hours_remaining']
    : null;

$internal_remaining_hours_live = max(0, $internal_total_hours - $internal_hours_rendered);
$external_remaining_hours_live = max(0, $external_total_hours - $external_hours_rendered);
$internal_remaining_hours_effective = $stored_internal_remaining !== null
    ? max(0, $stored_internal_remaining)
    : $internal_remaining_hours_live;
$external_remaining_hours_effective = $stored_external_remaining !== null
    ? max(0, $stored_external_remaining)
    : $external_remaining_hours_live;
if ($internal_hours_rendered > 0 && $internal_remaining_hours_effective <= 0 && $internal_remaining_hours_live > 0) {
    $internal_remaining_hours_effective = $internal_remaining_hours_live;
}
if ($external_hours_rendered > 0 && $external_remaining_hours_effective <= 0 && $external_remaining_hours_live > 0) {
    $external_remaining_hours_effective = $external_remaining_hours_live;
}
if ($assignment_track === 'internal' && $internal_remaining_hours_effective >= $internal_total_hours && $internal_hours_rendered > 0) {
    $internal_remaining_hours_effective = $internal_remaining_hours_live;
}
if ($assignment_track === 'external' && $external_remaining_hours_effective >= $external_total_hours && $external_hours_rendered > 0) {
    $external_remaining_hours_effective = $external_remaining_hours_live;
}
$hours_remaining = ($assignment_track === 'external') ? $external_remaining_hours_effective : $internal_remaining_hours_effective;
$hours_remaining_without_open = ($assignment_track === 'external')
    ? $external_remaining_hours_effective
    : $internal_remaining_hours_effective;
$hours_rendered = ($assignment_track === 'external') ? $external_hours_rendered : $internal_hours_rendered;

$remaining_seconds = (int)max(0, round($hours_remaining_without_open * 3600));
$remaining_seconds_without_open = (int)max(0, round($hours_remaining_without_open * 3600));
$preview_remaining_seconds = (int)max(0, $remaining_seconds_without_open - $open_session_seconds);
$internal_remaining_display = max(0, (int)floor($internal_remaining_hours_effective));
$external_remaining_display = max(0, (int)floor($external_remaining_hours_effective));
$internal_completed_hours = max(0, $internal_total_hours - $internal_remaining_display);
$external_completed_hours = max(0, $external_total_hours - $external_remaining_display);
$active_completed_hours = ($assignment_track === 'external') ? $external_completed_hours : $internal_completed_hours;
$active_total_hours = ($assignment_track === 'external') ? $external_total_hours : $internal_total_hours;
$completion_percentage = $active_total_hours > 0
    ? ($active_completed_hours / $active_total_hours) * 100
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

<?php
$page_title = 'BioTern || Student Profile - ' . $student['first_name'] . ' ' . $student['last_name'];
$page_styles = array(
    'assets/css/layout/page_shell.css',
    'assets/css/modules/management/management-students-shared.css',
    'assets/css/modules/management/management-students-view.css'
);
$page_scripts = array(
    'assets/js/modules/management/students-view-runtime.js',
    'assets/js/theme-customizer-init.min.js',
);
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
            <!-- Page Header -->
            <div class="page-header app-students-view-page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Student Profile</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="students.php">Students</a></li>
                        <li class="breadcrumb-item">View</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="d-flex align-items-center gap-2">
                        <a href="javascript:void(0);" class="btn btn-icon btn-light-brand successAlertMessage">
                            <i class="feather-star"></i>
                        </a>
                        <a href="javascript:void(0);" class="btn btn-icon btn-light-brand">
                            <i class="feather-eye me-2"></i>
                            <span>Follow</span>
                        </a>
                        <a href="students.php" class="btn btn-outline-secondary">
                            <i class="feather-arrow-left me-2"></i>
                            <span>Back to List</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content app-students-view-main-content">
                <div class="row app-students-view-layout-row">
                    <!-- Student Card Left Side -->
                    <div class="col-xxl-4 col-xl-6">
                        <div class="card stretch stretch-full app-students-view-profile-card">
                            <div class="card-body">
                                <?php
if ($eval_flash_message !== ''): ?>
                                    <div class="alert alert-<?php
echo $eval_flash_type === 'danger' ? 'danger' : 'success'; ?> mb-3">
                                        <?php
echo htmlspecialchars($eval_flash_message); ?>
                                    </div>
                                <?php
endif; ?>
                                <div class="mb-4 text-center">
                                    <div class="wd-150 ht-150 mx-auto mb-3 position-relative">
                                        <div class="avatar-image wd-150 ht-150 border border-5 border-gray-3">
                                            <?php
$profile_img = resolve_profile_image_url((string)($student['profile_picture'] ?? ''), (int)($student['user_id'] ?? 0));
                                            if ($profile_img !== null):
                                            ?>
                                                <img src="<?php
echo htmlspecialchars($profile_img); ?>" alt="Profile" class="img-fluid">
                                            <?php
else: ?>
                                                <img src="assets/images/avatar/<?php
echo ($student['id'] % 5) + 1; ?>.png" alt="" class="img-fluid">
                                            <?php
endif; ?>
                                        </div>
                                        <div class="wd-10 ht-10 text-success rounded-circle position-absolute translate-middle app-status-dot-position">
                                            <i class="bi bi-patch-check-fill"></i>
                                        </div>
                                    </div>
                                    <div class="mb-4">
                                        <a href="javascript:void(0);" class="fs-14 fw-bold d-block"><?php
echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></a>
                                        <a href="javascript:void(0);" class="fs-12 fw-normal text-muted d-block"><?php
echo htmlspecialchars($student['email']); ?></a>
                                    </div>
                                    <div class="fs-12 fw-normal text-muted text-center profile-stats mb-4">
                                        <div class="stat-card hours-remaining-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder mb-0" id="hoursRemaining">
                                                <?php
                                                $hours = intdiv($preview_remaining_seconds, 3600);
                                                $mins = intdiv(($preview_remaining_seconds % 3600), 60);
                                                $secs = $preview_remaining_seconds % 60;
                                                echo $hours . 'h:' . str_pad((string)$mins, 2, '0', STR_PAD_LEFT) . 'm:' . str_pad((string)$secs, 2, '0', STR_PAD_LEFT) . 's';
                                                ?>
                                            </h6>
                                            <p class="fs-12 text-muted mb-0">
                                                <?php if (!empty($open_session['requires_correction'])): ?>
                                                    Frozen until manual clock-out is approved
                                                <?php elseif ($is_clocked_in): ?>
                                                    Live preview only until clock-out
                                                <?php else: ?>
                                                    Hours Remaining
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="stat-card completion-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder mb-0" id="completionValue"><?php
echo number_format($completion_percentage, 2); ?>%</h6>
                                            <p class="fs-12 text-muted mb-0">Completion</p>
                                        </div>
                                        <div class="stat-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder" id="internalHoursValue"><?php
echo intval($internal_remaining_display); ?>/<?php
echo intval($internal_total_hours); ?></h6>
                                            <p class="fs-12 text-muted mb-0">Internal Hours</p>
                                        </div>
                                        <div class="stat-card py-3 px-4 rounded-1 border border-dashed border-gray-5">
                                            <h6 class="fs-15 fw-bolder"><?php
echo intval($external_remaining_display); ?>/<?php
echo intval($external_total_hours); ?></h6>
                                            <p class="fs-12 text-muted mb-0">External Hours</p>
                                        </div>
                                    </div>
                                    <?php
if ($is_clocked_in): ?>
                                        <div class="alert alert-soft-success-message p-2 mb-3" role="alert">
                                            <i class="feather-check-circle me-2"></i>
                                            <span class="fs-12">Student is currently clocked in</span>
                                        </div>
                                    <?php
elseif ($has_attendance_today): ?>
                                        <div class="alert attendance-clocked-out-alert p-2 mb-3" role="alert">
                                            <i class="feather-clock me-2"></i>
                                            <span class="fs-12 fw-bold">Student has attendance today and is currently clocked out</span>
                                        </div>
                                    <?php
else: ?>
                                        <div class="alert alert-soft-warning-message p-2 mb-3" role="alert">
                                            <i class="feather-alert-circle me-2"></i>
                                            <span class="fs-12">Student has no attendance today</span>
                                        </div>
                                    <?php
endif; ?>
                                </div>
                                <ul class="list-unstyled mb-4">
                                    <li class="profile-contact-item mb-4">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-map-pin"></i>Location</span>
                                        <a href="javascript:void(0);" class="profile-contact-value"><?php
echo htmlspecialchars($student['address'] ?? 'N/A'); ?></a>
                                    </li>
                                    <li class="profile-contact-item mb-4">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-phone"></i>Mobile Phone</span>
                                        <a href="javascript:void(0);" class="profile-contact-value"><?php
echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></a>
                                    </li>
                                    <li class="profile-contact-item mb-0">
                                        <span class="text-muted fw-medium hstack gap-3"><i class="feather-mail"></i>Email</span>
                                        <a href="javascript:void(0);" class="profile-contact-value"><?php
echo htmlspecialchars($student['email']); ?></a>
                                    </li>
                                </ul>
                                <div class="d-flex gap-2 text-center pt-4">
                                    <a href="javascript:void(0);" class="w-50 btn btn-light-brand">
                                        <i class="feather-trash-2 me-2"></i>
                                        <span>Delete</span>
                                    </a>
                                    <a href="students-edit.php?id=<?php
echo $student['id']; ?>" class="w-50 btn btn-outline-secondary">
                                        <i class="feather-edit me-2"></i>
                                        <span>Edit Profile</span>
                                    </a>
                                </div>
                                <div class="d-grid gap-2 text-center pt-2">
                                    <a href="ojt-view.php?id=<?php
echo $student['id']; ?>" class="btn btn-outline-secondary">
                                        <i class="feather-file me-2"></i>
                                        <span>OJT Document View</span>
                                    </a>
                                    <a href="generate_resume.php?id=<?php
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
                        <div class="card border-top-0 app-students-view-detail-card">
                            <div class="card-header p-0 app-students-view-tabs-shell">
                                <ul class="nav nav-tabs w-100 text-center app-students-view-tabs" id="myTab" role="tablist">
                                    <li class="nav-item app-students-view-tab-item" role="presentation">
                                        <a href="javascript:void(0);" class="nav-link active app-students-view-tab-link" data-bs-toggle="tab" data-bs-target="#overviewTab" role="tab">Overview</a>
                                    </li>
                                    <li class="nav-item app-students-view-tab-item" role="presentation">
                                        <a href="javascript:void(0);" class="nav-link app-students-view-tab-link" data-bs-toggle="tab" data-bs-target="#activityTab" role="tab">Attendance</a>
                                    </li>
                                    <li class="nav-item app-students-view-tab-item" role="presentation">
                                        <a href="javascript:void(0);" class="nav-link app-students-view-tab-link" data-bs-toggle="tab" data-bs-target="#evaluationTab" role="tab">Evaluation</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="tab-content">
                                <!-- Overview Tab -->
                                <div class="tab-pane fade show active app-students-view-tab-pane" id="overviewTab" role="tabpanel">
                                    <div class="profile-details app-students-view-profile-details mb-4">
                                        <div class="mb-4 d-flex align-items-center justify-content-between">
                                            <h5 class="fw-bold mb-0">Profile Details:</h5>
                                            <a href="students-edit.php?id=<?php
echo $student['id']; ?>" class="btn btn-sm btn-light-brand">Edit Profile</a>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Career Objective</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars(!empty($student['bio']) ? $student['bio'] : 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Student ID</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['student_id']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">First Name</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['first_name']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Middle Name</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['middle_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Last Name</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['last_name']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Course</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Department</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['department_name'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Section</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars(biotern_format_section_label((string)($student['section_code'] ?? ''), (string)($student['section_name'] ?? 'N/A'))); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Current Company</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars(trim((string)($student_latest_internship['company_name'] ?? '')) !== '' ? (string)$student_latest_internship['company_name'] : 'No company linked yet'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Company Contact</div>
                                                    <div class="fw-semibold"><?php
$studentCompanyContact = trim((string)($student_latest_internship['company_representative'] ?? ''));
if ($studentCompanyContact === '') {
    $studentCompanyContact = trim((string)($student_latest_internship['supervisor_name'] ?? ''));
}
echo htmlspecialchars($studentCompanyContact !== '' ? $studentCompanyContact : 'Not provided'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Company Address</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars(trim((string)($student_latest_internship['company_address'] ?? '')) !== '' ? (string)$student_latest_internship['company_address'] : 'No company address saved yet'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Internal Hours (Remaining/Total)</div>
                                                    <div class="fw-semibold" id="internalHoursDetailValue"><?php
echo intval($internal_remaining_display); ?> / <?php
echo intval($internal_total_hours); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">External Hours (Remaining/Total)</div>
                                                    <div class="fw-semibold"><?php
echo intval($external_remaining_display); ?> / <?php
echo intval($external_total_hours); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Internal Start Date</div>
                                                    <div class="fw-semibold"><?php
echo formatDate($student['internal_start_date'] ?? null); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">External Start Date</div>
                                                    <div class="fw-semibold"><?php
echo formatDate($student['external_start_date'] ?? null); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Email Address</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['email']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Mobile Number</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['phone'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Date of Birth</div>
                                                    <div class="fw-semibold"><?php
echo formatDate($student['date_of_birth']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Gender</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars(ucfirst($student['gender'] ?? 'N/A')); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Supervisor</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['supervisor_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Coordinator</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['coordinator_name'] ?? 'Not Assigned'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Home Address</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['address'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Emergency Contact</div>
                                                    <div class="fw-semibold"><?php
echo htmlspecialchars($student['emergency_contact'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Status</div>
                                                    <div class="fw-semibold"><?php
echo getStatusBadge($student['status']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Date Registered</div>
                                                    <div class="fw-semibold"><?php
echo formatDate($student['created_at']); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="p-3 border rounded">
                                                    <div class="small text-muted mb-1">Date Fingerprint Registered</div>
                                                    <div class="fw-semibold"><?php
echo formatDate($student['biometric_registered_at']); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Attendance Tab -->
                                <div class="tab-pane fade app-students-view-tab-pane" id="activityTab" role="tabpanel">
                                    <div class="recent-activity app-students-view-recent-activity">
                                        <div class="mb-4 pb-2 d-flex justify-content-between">
                                            <h5 class="fw-bold">Recent Attendance Records:</h5>
                                            <div class="d-flex gap-2">
                                                <a href="students-internal-dtr.php?id=<?php
echo intval($student['id']); ?>" class="btn btn-sm btn-light-brand">Open Internal DTR</a>
                                                <a href="students-external-dtr.php?id=<?php
echo intval($student['id']); ?>" class="btn btn-sm btn-outline-secondary">Open External DTR</a>
                                            </div>
                                        </div>
                                        <ul class="list-unstyled activity-feed">
                                            <?php
if (count($activities) > 0): ?>
                                                <?php
foreach ($activities as $activity): ?>
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
                                                    <li class="d-flex justify-content-between feed-item <?php
echo getActivityTypeClass($activity['status']); ?>">
                                                        <div>
                                                            <span class="text-truncate-1-line lead_date">
                                                                Attendance for <?php
echo date('M d, Y', strtotime($activity['date'])); ?>
                                                                <span class="date">[<?php
echo formatDateTime($activity['created_at']); ?>]</span>
                                                            </span>
                                                            <span class="text">
                                                                Morning: <a href="javascript:void(0);" class="fw-bold"><?php
echo formatTimeRange($activity['morning_time_in'], $activity['morning_time_out']); ?></a>
                                                                &nbsp;|&nbsp;
                                                                Afternoon: <a href="javascript:void(0);" class="fw-bold"><?php
echo formatTimeRange($activity['afternoon_time_in'], $activity['afternoon_time_out']); ?></a>
                                                                &nbsp;|&nbsp;
                                                                Total: <strong><?php
echo $total_hours; ?> hrs</strong>
                                                            </span>
                                                        </div>
                                                        <div class="ms-3 d-flex gap-2 align-items-center">
                                                            <?php
echo getStatusBadge($activity['status']); ?>
                                                        </div>
                                                    </li>
                                                <?php
endforeach; ?>
                                            <?php
else: ?>
                                                <li class="text-center py-4">
                                                    <p class="text-muted">No attendance records found</p>
                                                </li>
                                            <?php
endif; ?>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Evaluation Tab -->
                                <div class="tab-pane fade app-students-view-tab-pane" id="evaluationTab" role="tabpanel">
                                    <div class="app-students-view-evaluation-wrap">
                                        <div class="mb-4 d-flex align-items-center justify-content-between">
                                            <h5 class="fw-bold mb-0">Supervisor Evaluation:</h5>
                                            <?php
if ($is_evaluation_unlocked): ?>
                                                <span class="badge bg-soft-success text-success">Unlocked</span>
                                            <?php
else: ?>
                                                <span class="badge bg-soft-warning text-warning">Locked</span>
                                            <?php
endif; ?>
                                        </div>

                                        <?php
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
else: ?>
                                            <div class="alert alert-dismissible app-students-view-neutral-alert p-4 mb-4" role="alert">
                                                <div class="d-flex">
                                                    <div class="me-3 d-none d-md-block"><i class="feather-info fs-1"></i></div>
                                                    <div>
                                                        <p class="fw-bold mb-1">Evaluation is still locked</p>
                                                        <p class="fs-12 text-muted mb-0">Completion requirements are not yet fully met.</p>
                                                        <?php
if (!empty($evaluation_gate_state['reasons']) && is_array($evaluation_gate_state['reasons'])): ?>
                                                            <ul class="mb-0 mt-2 ps-3">
                                                                <?php
foreach ($evaluation_gate_state['reasons'] as $reason): ?>
                                                                    <li class="fs-12 text-muted"><?php
echo htmlspecialchars((string)$reason); ?></li>
                                                                <?php
endforeach; ?>
                                                            </ul>
                                                        <?php
endif; ?>
                                                    </div>
                                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                </div>
                                            </div>
                                        <?php
endif; ?>

                                        <?php
if ($can_manage_eval_unlock): ?>
                                            <div class="border rounded p-3 mb-4">
                                                <p class="fw-semibold mb-2">Coordinator/Admin Override</p>
                                                <form method="POST" class="d-flex flex-wrap gap-2 align-items-center">
                                                    <input type="hidden" name="student_id" value="<?php
echo (int)$student_id; ?>">
                                                    <input type="text" name="eval_unlock_note" class="form-control app-max-w-340" placeholder="Optional note for audit">
                                                    <?php
if ($is_evaluation_unlocked): ?>
                                                        <button type="submit" name="eval_unlock_action" value="lock" class="btn btn-outline-danger btn-sm">Lock Evaluation</button>
                                                    <?php
else: ?>
                                                        <button type="submit" name="eval_unlock_action" value="unlock" class="btn btn-outline-success btn-sm">Unlock Evaluation</button>
                                                    <?php
endif; ?>
                                                </form>
                                            </div>
                                        <?php
endif; ?>

                                        <div class="text-center py-5">
                                            <i class="feather-inbox fs-1 text-muted mb-3 d-block"></i>
                                            <p class="text-muted"><?php
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

            <div id="students-view-runtime-config"
         data-internal-total-hours="<?php echo (int)$internal_total_hours; ?>"
         data-external-total-hours="<?php echo (int)$external_total_hours; ?>"
         data-active-track="<?php echo htmlspecialchars($assignment_track, ENT_QUOTES, 'UTF-8'); ?>"
         data-active-total-hours="<?php echo (int)$active_total_hours; ?>"
         data-student-id="<?php echo (int)$student['id']; ?>"
         data-remaining-seconds="<?php echo (int)$preview_remaining_seconds; ?>"
         data-remaining-seconds-without-open="<?php echo (int)$remaining_seconds_without_open; ?>"
         data-is-clocked-in="<?php echo $is_clocked_in ? '1' : '0'; ?>"
          data-open-clock-in-raw="<?php echo htmlspecialchars((string)($open_clock_in_time ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
         data-session-cutoff-raw="<?php echo htmlspecialchars((string)($open_session['cutoff_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
          hidden></div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>

<?php
$conn->close();
?>













