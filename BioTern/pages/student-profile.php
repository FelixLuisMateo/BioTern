<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/lib/external_attendance.php';
require_once dirname(__DIR__) . '/lib/company_profiles.php';
require_once dirname(__DIR__) . '/lib/attendance_workflow.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
external_attendance_ensure_schema($conn);

function student_profile_value(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    return $value !== '' ? $value : $fallback;
}

function student_profile_format_date(?string $value, string $fallback = 'Not yet available'): string
{
    $value = trim((string)$value);
    if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return $fallback;
    }

    $timestamp = strtotime($value);
    return $timestamp !== false ? date('M d, Y', $timestamp) : $fallback;
}

function student_profile_table_exists(mysqli $conn, string $table): bool
{
    $escaped = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$escaped}'");
    $exists = ($res instanceof mysqli_result) && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    return $exists;
}

function student_profile_column_exists(mysqli $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safeTable = str_replace('`', '``', $table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $cache[$key] = ($res instanceof mysqli_result) && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->close();
    }
    return $cache[$key];
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($currentRole !== 'student' || $currentUserId <= 0) {
    header('Location: homepage.php');
    exit;
}

$user = null;
$student = null;
$internship = null;
$companyProfile = null;
$studentEvaluation = null;
$recentAttendance = [];
$lastLoginAt = '';
$lastBiometricClockIn = '';
$profileStats = [
    'approved_logs' => 0,
    'pending_logs' => 0,
    'rejected_logs' => 0,
    'total_hours' => 0.0,
];
$externalProfileStats = [
    'approved_logs' => 0,
    'pending_logs' => 0,
    'rejected_logs' => 0,
    'total_hours' => 0.0,
];
$openSession = [
    'is_open' => false,
    'clocked_in_now' => false,
    'requires_correction' => false,
    'elapsed_preview_seconds' => 0,
    'cutoff_time' => null,
];

$userStmt = $conn->prepare('SELECT id, name, username, email, profile_picture, created_at FROM users WHERE id = ? LIMIT 1');
if ($userStmt) {
    $userStmt->bind_param('i', $currentUserId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc() ?: null;
    $userStmt->close();
}

$studentStmt = $conn->prepare(
    "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.email AS student_email, s.phone, s.address,
            s.date_of_birth, s.gender, s.emergency_contact,
            s.status AS student_status, s.biometric_registered, s.biometric_registered_at,
            s.assignment_track, s.internal_total_hours, s.internal_total_hours_remaining,
            s.external_total_hours, s.external_total_hours_remaining,
            c.name AS course_name, d.name AS department_name, sec.code AS section_code, sec.name AS section_name
     FROM students s
     LEFT JOIN courses c ON c.id = s.course_id
     LEFT JOIN departments d ON d.id = s.department_id
     LEFT JOIN sections sec ON sec.id = s.section_id
     WHERE s.user_id = ?
     LIMIT 1"
);
if ($studentStmt) {
    $studentStmt->bind_param('i', $currentUserId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc() ?: null;
    $studentStmt->close();
}

if (!$student && $user) {
    $fallbackEmail = trim((string)($user['email'] ?? ''));
    $fallbackName = trim((string)($user['name'] ?? ''));
    $fallbackStudentStmt = $conn->prepare(
        "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name, s.email AS student_email, s.phone, s.address,
                s.date_of_birth, s.gender, s.emergency_contact,
                s.status AS student_status, s.biometric_registered, s.biometric_registered_at,
                s.assignment_track, s.internal_total_hours, s.internal_total_hours_remaining,
                s.external_total_hours, s.external_total_hours_remaining,
                c.name AS course_name, d.name AS department_name, sec.code AS section_code, sec.name AS section_name
         FROM students s
         LEFT JOIN courses c ON c.id = s.course_id
         LEFT JOIN departments d ON d.id = s.department_id
         LEFT JOIN sections sec ON sec.id = s.section_id
         WHERE ((? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?))
             OR (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?)))
         ORDER BY
            CASE
                WHEN (? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?)) THEN 0
                WHEN (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?)) THEN 1
                ELSE 2
            END,
            s.id DESC
         LIMIT 1"
    );

    if ($fallbackStudentStmt) {
        $fallbackStudentStmt->bind_param(
            'ssssssss',
            $fallbackEmail,
            $fallbackEmail,
            $fallbackName,
            $fallbackName,
            $fallbackEmail,
            $fallbackEmail,
            $fallbackName,
            $fallbackName
        );
        $fallbackStudentStmt->execute();
        $student = $fallbackStudentStmt->get_result()->fetch_assoc() ?: null;
        $fallbackStudentStmt->close();
    }
}

if ($student) {
    $studentId = (int)($student['id'] ?? 0);

    $internshipStmt = $conn->prepare(
        "SELECT company_name, position, start_date, end_date, status, required_hours, rendered_hours, completion_percentage
         FROM internships
         WHERE student_id = ? AND deleted_at IS NULL
         ORDER BY updated_at DESC, id DESC
         LIMIT 1"
    );
    if ($internshipStmt) {
        $internshipStmt->bind_param('i', $studentId);
        $internshipStmt->execute();
        $internship = $internshipStmt->get_result()->fetch_assoc() ?: null;
        $internshipStmt->close();
    }

    $internshipCompanyName = trim((string)($internship['company_name'] ?? ''));
    if ($internshipCompanyName !== '') {
        $companyProfile = biotern_company_profile_fetch_by_name($conn, $internshipCompanyName);
        if ($companyProfile) {
            $profileCompanyName = trim((string)($companyProfile['company_name'] ?? ''));
            $profileCompanyAddress = trim((string)($companyProfile['company_address'] ?? ''));
            $profileRepresentative = trim((string)($companyProfile['company_representative'] ?? ''));
            $profileRepresentativePosition = trim((string)($companyProfile['company_representative_position'] ?? ''));
            $profileSupervisor = trim((string)($companyProfile['supervisor_name'] ?? ''));
            $profileSupervisorPosition = trim((string)($companyProfile['supervisor_position'] ?? ''));

            if ($profileCompanyName !== '') {
                $internship['company_name'] = $profileCompanyName;
            }
            if ($profileCompanyAddress !== '') {
                $internship['company_address'] = $profileCompanyAddress;
            }
            if ($profileRepresentative !== '') {
                $internship['company_representative'] = $profileRepresentative;
            }
            if ($profileRepresentativePosition !== '') {
                $internship['company_representative_position'] = $profileRepresentativePosition;
            }
            if ($profileSupervisor !== '') {
                $internship['company_supervisor_name'] = $profileSupervisor;
            }
            if ($profileSupervisorPosition !== '') {
                $internship['company_supervisor_position'] = $profileSupervisorPosition;
            }
        }
    }

    $recentStmt = $conn->prepare(
        "SELECT attendance_date, status, total_hours, source
         FROM attendances
         WHERE student_id = ?
         ORDER BY attendance_date DESC, id DESC
         LIMIT 5"
    );
    if ($recentStmt) {
        $recentStmt->bind_param('i', $studentId);
        $recentStmt->execute();
        $recentResult = $recentStmt->get_result();
        while ($recentResult && ($row = $recentResult->fetch_assoc())) {
            $recentAttendance[] = $row;
        }
        $recentStmt->close();
    }

    $lastBiometricClockInStmt = $conn->prepare(
        "SELECT MAX(clock_value) AS last_clocked_in
         FROM (
            SELECT TIMESTAMP(attendance_date, morning_time_in) AS clock_value
            FROM attendances
            WHERE student_id = ? AND morning_time_in IS NOT NULL AND morning_time_in <> ''
            UNION ALL
            SELECT TIMESTAMP(attendance_date, afternoon_time_in) AS clock_value
            FROM attendances
            WHERE student_id = ? AND afternoon_time_in IS NOT NULL AND afternoon_time_in <> ''
         ) biometric_clock_ins"
    );
    if ($lastBiometricClockInStmt) {
        $lastBiometricClockInStmt->bind_param('ii', $studentId, $studentId);
        $lastBiometricClockInStmt->execute();
        $lastBiometricClockInRow = $lastBiometricClockInStmt->get_result()->fetch_assoc() ?: null;
        $lastBiometricClockIn = trim((string)($lastBiometricClockInRow['last_clocked_in'] ?? ''));
        $lastBiometricClockInStmt->close();
    }

    $summaryStmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(total_hours), 0) AS total_hours,
            SUM(CASE WHEN LOWER(COALESCE(status, 'pending')) = 'approved' THEN 1 ELSE 0 END) AS approved_logs,
            SUM(CASE WHEN LOWER(COALESCE(status, 'pending')) = 'pending' THEN 1 ELSE 0 END) AS pending_logs,
            SUM(CASE WHEN LOWER(COALESCE(status, 'pending')) = 'rejected' THEN 1 ELSE 0 END) AS rejected_logs
         FROM attendances
         WHERE student_id = ?"
    );
    if ($summaryStmt) {
        $summaryStmt->bind_param('i', $studentId);
        $summaryStmt->execute();
        $profileStats = $summaryStmt->get_result()->fetch_assoc() ?: $profileStats;
        $summaryStmt->close();
    }

    $externalSummaryStmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN LOWER(COALESCE(status, 'pending')) <> 'rejected' THEN total_hours ELSE 0 END), 0) AS total_hours,
            SUM(CASE WHEN LOWER(COALESCE(status, 'pending')) = 'approved' THEN 1 ELSE 0 END) AS approved_logs,
            SUM(CASE WHEN LOWER(COALESCE(status, 'pending')) = 'pending' THEN 1 ELSE 0 END) AS pending_logs,
            SUM(CASE WHEN LOWER(COALESCE(status, 'pending')) = 'rejected' THEN 1 ELSE 0 END) AS rejected_logs
         FROM external_attendance
         WHERE student_id = ?"
    );
    if ($externalSummaryStmt) {
        $externalSummaryStmt->bind_param('i', $studentId);
        $externalSummaryStmt->execute();
        $externalProfileStats = $externalSummaryStmt->get_result()->fetch_assoc() ?: $externalProfileStats;
        $externalSummaryStmt->close();
    }

    if (student_profile_table_exists($conn, 'evaluations')) {
        $deletedFilter = student_profile_column_exists($conn, 'evaluations', 'deleted_at')
            ? ' AND deleted_at IS NULL'
            : '';
        $evaluationStmt = $conn->prepare("
            SELECT evaluator_name, evaluation_date, score, feedback
            FROM evaluations
            WHERE student_id = ? {$deletedFilter}
            ORDER BY evaluation_date DESC, id DESC
            LIMIT 1
        ");
        if ($evaluationStmt) {
            $evaluationStmt->bind_param('i', $studentId);
            $evaluationStmt->execute();
            $studentEvaluation = $evaluationStmt->get_result()->fetch_assoc() ?: null;
            $evaluationStmt->close();
        }
    }
}

$lastLoginStmt = $conn->prepare('SELECT created_at FROM login_logs WHERE user_id = ? AND status = ? ORDER BY created_at DESC LIMIT 1');
if ($lastLoginStmt) {
    $successStatus = 'success';
    $lastLoginStmt->bind_param('is', $currentUserId, $successStatus);
    $lastLoginStmt->execute();
    $lastLoginRow = $lastLoginStmt->get_result()->fetch_assoc() ?: null;
    $lastLoginAt = trim((string)($lastLoginRow['created_at'] ?? ''));
    $lastLoginStmt->close();
}

$displayName = trim((string)($user['name'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string)(
        ($student['first_name'] ?? '') . ' ' .
        ($student['middle_name'] ?? '') . ' ' .
        ($student['last_name'] ?? '')
    ));
}
if ($displayName === '') {
    $displayName = 'Student User';
}

$studentNumber = trim((string)($student['student_id'] ?? ''));
$courseName = trim((string)($student['course_name'] ?? ''));
$departmentName = trim((string)($student['department_name'] ?? ''));
$sectionName = biotern_format_section_label(
    (string)($student['section_code'] ?? ''),
    (string)($student['section_name'] ?? '')
);
$assignmentTrack = strtolower(trim((string)($student['assignment_track'] ?? 'internal')));
if (!in_array($assignmentTrack, ['internal', 'external'], true)) {
    $assignmentTrack = 'internal';
}
$studentHasExternalAccess = ($assignmentTrack === 'external');
if (!empty($student['id']) && $assignmentTrack === 'internal') {
    $today = date('Y-m-d');
    $openAttendanceStmt = $conn->prepare(
        "SELECT a.id, a.student_id, a.attendance_date, a.status, a.remarks,
                a.morning_time_in, a.morning_time_out, a.afternoon_time_in, a.afternoon_time_out,
                sec.attendance_session, sec.schedule_time_in, sec.schedule_time_out, sec.late_after_time, sec.weekly_schedule_json
         FROM attendances a
         LEFT JOIN students s2 ON a.student_id = s2.id
         LEFT JOIN sections sec ON s2.section_id = sec.id
         WHERE a.student_id = ? AND a.attendance_date = ?
         ORDER BY a.id DESC
         LIMIT 1"
    );
    if ($openAttendanceStmt) {
        $studentId = (int)$student['id'];
        $openAttendanceStmt->bind_param('is', $studentId, $today);
        $openAttendanceStmt->execute();
        $openAttendance = $openAttendanceStmt->get_result()->fetch_assoc() ?: null;
        $openAttendanceStmt->close();
        if ($openAttendance) {
            $openSession = attendance_workflow_mark_incomplete_if_needed($conn, $openAttendance);
        }
    }
}
$studentStatus = student_profile_value((string)($student['student_status'] ?? ''), 'Not yet available');
$contactEmail = trim((string)($student['student_email'] ?? ($user['email'] ?? '')));
$contactPhone = trim((string)($student['phone'] ?? ''));
$contactAddress = trim((string)($student['address'] ?? ''));
$birthDate = trim((string)($student['date_of_birth'] ?? ''));
$gender = trim((string)($student['gender'] ?? ''));
$emergencyContact = trim((string)($student['emergency_contact'] ?? ''));
$genderDisplay = $gender !== '' ? ucwords(strtolower($gender)) : 'Not yet available';
$avatarSrc = biotern_avatar_public_src((string)($user['profile_picture'] ?? ''), $currentUserId);
$completionPercentage = min(100, max(0, (float)($internship['completion_percentage'] ?? 0)));
$renderedHours = (float)($internship['rendered_hours'] ?? 0);
$requiredHours = (float)($internship['required_hours'] ?? 0);
$biometricReady = !empty($student['biometric_registered']);
$studentStatusRaw = trim((string)($student['student_status'] ?? ''));
$studentStatusDisplay = match (strtolower($studentStatusRaw)) {
    '1', 'true', 'active', 'approved' => 'Active',
    '0', 'false', 'inactive', 'rejected' => 'Inactive',
    'pending' => 'Pending',
    default => $studentStatus,
};
$joinedDate = student_profile_format_date((string)($user['created_at'] ?? ''));
$lastLoginText = student_profile_format_date($lastLoginAt, 'No login record yet');
$lastBiometricClockInText = student_profile_format_date($lastBiometricClockIn, 'No biometric clock-in yet');
$evaluationScore = $studentEvaluation ? (int)($studentEvaluation['score'] ?? 0) : null;
$evaluationDateText = $studentEvaluation
    ? student_profile_format_date((string)($studentEvaluation['evaluation_date'] ?? ''), 'No date saved')
    : 'Not rated yet';
$evaluationEvaluator = $studentEvaluation
    ? student_profile_value((string)($studentEvaluation['evaluator_name'] ?? ''), 'Supervisor')
    : 'Waiting for supervisor';
$completionChecks = [
    $studentNumber !== '',
    $courseName !== '',
    $contactEmail !== '',
    $contactPhone !== '',
    $contactAddress !== '',
    $birthDate !== '',
    $gender !== '',
    $emergencyContact !== '',
];
$profileCompletion = (int)round((array_sum(array_map(static fn($value) => $value ? 1 : 0, $completionChecks)) / count($completionChecks)) * 100);
$internalRenderedHours = (float)($profileStats['total_hours'] ?? 0);
$externalRenderedHours = (float)($externalProfileStats['total_hours'] ?? 0);
$internalTotalHours = max(0.0, (float)($student['internal_total_hours'] ?? 0));
$externalTotalHours = max(0.0, (float)($student['external_total_hours'] ?? 0));
if ($studentHasExternalAccess && $externalTotalHours <= 0) {
    $externalTotalHours = 250.0;
}
$storedInternalRemaining = isset($student['internal_total_hours_remaining']) && $student['internal_total_hours_remaining'] !== null
    ? max(0.0, (float)$student['internal_total_hours_remaining'])
    : null;
$storedExternalRemaining = isset($student['external_total_hours_remaining']) && $student['external_total_hours_remaining'] !== null
    ? max(0.0, (float)$student['external_total_hours_remaining'])
    : null;
$internalRemainingHours = $storedInternalRemaining !== null
    ? $storedInternalRemaining
    : max(0.0, $internalTotalHours - $internalRenderedHours);
$externalRemainingHours = $storedExternalRemaining !== null
    ? $storedExternalRemaining
    : max(0.0, $externalTotalHours - $externalRenderedHours);
if ($internalRenderedHours > 0) {
    $internalRemainingHours = max(0.0, $internalTotalHours - $internalRenderedHours);
}
if ($externalRenderedHours > 0) {
    $externalRemainingHours = max(0.0, $externalTotalHours - $externalRenderedHours);
}
$activeRemainingHours = $assignmentTrack === 'external' ? $externalRemainingHours : $internalRemainingHours;
$activeRemainingSeconds = (int)max(0, round($activeRemainingHours * 3600));
$timerPreviewSeconds = $activeRemainingSeconds;
if ($assignmentTrack === 'internal' && !empty($openSession['is_open'])) {
    $timerPreviewSeconds = max(0, $activeRemainingSeconds - (int)($openSession['elapsed_preview_seconds'] ?? 0));
}
$timerHours = intdiv($timerPreviewSeconds, 3600);
$timerMinutes = intdiv($timerPreviewSeconds % 3600, 60);
$timerSeconds = $timerPreviewSeconds % 60;
$timerDisplay = $timerHours . 'h:' . str_pad((string)$timerMinutes, 2, '0', STR_PAD_LEFT) . 'm:' . str_pad((string)$timerSeconds, 2, '0', STR_PAD_LEFT) . 's';
$timerHeading = $assignmentTrack === 'external' ? 'Remaining External Hours' : 'Remaining Internal Hours';
$timerNote = 'Hours remaining on record.';
if ($assignmentTrack === 'internal' && !empty($openSession['requires_correction'])) {
    $timerNote = 'Last captured time is held. That day stays void until the missing clock-out is reported and approved.';
} elseif ($assignmentTrack === 'internal' && !empty($openSession['clocked_in_now'])) {
    $timerNote = 'Live preview while you are still clocked in.';
}

$page_title = 'BioTern || My Profile';
$page_styles = [
    'assets/css/homepage-student.css',
    'assets/css/student-profile.css',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header dashboard-page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">My Profile</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">My Profile</li>
                </ul>
            </div>
            <div class="page-header-right ms-auto">
                <span class="badge bg-soft-primary text-primary fs-11">
                    <i class="feather-calendar me-1"></i> <?php echo htmlspecialchars(date('M d, Y'), ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>
        </div>
        <div class="main-content">
            <div class="student-home-shell student-profile-shell">
        <div class="row g-4 align-items-start">
            <div class="col-12 col-xl-3">
                <section class="card student-panel student-profile-sidebar">
                    <div class="card-body">
                        <span class="student-metric-label">Student Profile</span>
                        <div class="student-profile-center">
                            <img src="<?php echo htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile Avatar" class="student-profile-avatar">
                            <h2 class="student-profile-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h2>
                            <span class="student-profile-role"><i class="feather-user me-1"></i> Student Profile</span>
                            <p class="student-profile-copy">Review your account details, school information, internship progress, and recent attendance activity here.</p>
                        </div>

                        <div class="student-home-meta student-profile-chip-row">
                            <span><i class="feather-hash me-1"></i><?php echo htmlspecialchars($studentNumber !== '' ? $studentNumber : 'No student number', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><i class="feather-book-open me-1"></i><?php echo htmlspecialchars($courseName !== '' ? $courseName : 'No course yet', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <div class="student-profile-progress-card">
                            <div class="student-progress-row">
                                <span>Internship completion</span>
                                <strong><?php echo number_format($completionPercentage, 0); ?>%</strong>
                            </div>
                            <div class="progress student-profile-progress">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo number_format($completionPercentage, 2, '.', ''); ?>%;" aria-valuenow="<?php echo number_format($completionPercentage, 0); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small><?php echo number_format($renderedHours, 0); ?> of <?php echo number_format($requiredHours, 0); ?> required hours rendered.</small>
                        </div>

                        <div class="student-profile-actions">
                            <a href="account-settings.php#overview" class="btn btn-primary">Account Settings</a>
                            <a href="student-internal-dtr.php" class="btn btn-outline-primary">Internal DTR</a>
                            <?php if ($studentHasExternalAccess): ?>
                            <a href="external-biometric.php" class="btn btn-outline-primary">External DTR</a>
                            <?php endif; ?>
                            <a href="student-internal-dtr.php#manual-dtr" class="btn btn-outline-secondary">Manual Internal DTR</a>
                            <a href="student-documents.php" class="btn btn-outline-secondary">My Documents</a>
                        </div>

                        <div class="mt-4">
                            <span class="student-metric-label">Profile Completion</span>
                            <h3 class="mb-3">Readiness</h3>
                        </div>
                        <div class="student-profile-progress-card student-profile-progress-card--compact mt-0">
                            <div class="student-progress-row">
                                <span>Profile details filled</span>
                                <strong><?php echo $profileCompletion; ?>%</strong>
                            </div>
                            <div class="progress student-profile-progress">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $profileCompletion; ?>%;" aria-valuenow="<?php echo $profileCompletion; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small>Keep your account details complete so printed documents and student records stay accurate.</small>
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <a href="account-settings.php#overview" class="btn btn-primary">Update My Details</a>
                            <a href="student-documents.php" class="btn btn-outline-secondary">Review My Documents</a>
                        </div>

                        <div class="mt-4">
                            <span class="student-metric-label">Recent Attendance</span>
                            <h3 class="mb-3">Activity</h3>
                        </div>
                        <?php if (!empty($recentAttendance)): ?>
                            <div class="student-attendance-list">
                                <?php foreach ($recentAttendance as $attendance): ?>
                                    <?php $attendanceStatus = ucfirst(trim((string)($attendance['status'] ?? 'pending'))); ?>
                                    <div class="student-attendance-item">
                                        <div>
                                            <strong><?php echo htmlspecialchars(student_profile_format_date((string)($attendance['attendance_date'] ?? ''), 'Unknown date'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span><?php echo htmlspecialchars($attendanceStatus . ' | ' . ucfirst((string)($attendance['source'] ?? 'manual')), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <b><?php echo number_format((float)($attendance['total_hours'] ?? 0), 2); ?> hrs</b>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="student-empty-state">No attendance entries yet.</div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <div class="student-profile-metric-grid">
                    <div class="student-profile-timer-row mb-3">
                        <div class="student-profile-timer-card student-profile-timer-card--primary">
                            <span class="student-metric-label"><?php echo htmlspecialchars($timerHeading, ENT_QUOTES, 'UTF-8'); ?></span>
                            <div class="student-profile-timer-value" id="studentProfileTimerValue"><?php echo htmlspecialchars($timerDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                            <p class="student-profile-timer-note mb-0"><?php echo htmlspecialchars($timerNote, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                        <div class="student-profile-timer-card">
                            <span class="student-metric-label">Internal Hours Logged</span>
                            <div class="student-profile-timer-value">
                                <?php echo number_format($internalRenderedHours, 2); ?> / <?php echo number_format($internalTotalHours, 0); ?> hrs
                            </div>
                        </div>
                        <?php if ($studentHasExternalAccess): ?>
                        <div class="student-profile-timer-card">
                            <span class="student-metric-label">External Hours Logged</span>
                            <div class="student-profile-timer-value">
                                <?php echo number_format($externalRenderedHours, 2); ?> / <?php echo number_format($externalTotalHours, 0); ?> hrs
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <article class="card student-metric-card">
                        <div class="card-body">
                            <span class="student-metric-label">Approved Logs</span>
                            <h3><?php echo (int)($profileStats['approved_logs'] ?? 0); ?></h3>
                            <p>Attendance records already approved.</p>
                        </div>
                    </article>
                    <article class="card student-metric-card">
                        <div class="card-body">
                            <span class="student-metric-label">Pending Logs</span>
                            <h3><?php echo (int)($profileStats['pending_logs'] ?? 0); ?></h3>
                            <p>Entries still waiting for review.</p>
                        </div>
                    </article>
                    <article class="card student-metric-card">
                        <div class="card-body">
                            <span class="student-metric-label">Remaining Internal Hours</span>
                            <h3><?php echo number_format($internalRemainingHours, 1); ?></h3>
                            <p>Internal hours still needed before completion.</p>
                        </div>
                    </article>
                    <article class="card student-metric-card student-profile-evaluation-card">
                        <div class="card-body">
                            <span class="student-metric-label">Evaluation Score</span>
                            <?php if ($evaluationScore !== null): ?>
                                <h3><?php echo (int)$evaluationScore; ?>%</h3>
                                <p>Rated by <?php echo htmlspecialchars($evaluationEvaluator, ENT_QUOTES, 'UTF-8'); ?> on <?php echo htmlspecialchars($evaluationDateText, ENT_QUOTES, 'UTF-8'); ?>.</p>
                            <?php else: ?>
                                <h3>Pending</h3>
                                <p>Your supervisor has not submitted the final evaluation yet.</p>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php if ($studentHasExternalAccess): ?>
                    <article class="card student-metric-card">
                        <div class="card-body">
                            <span class="student-metric-label">Remaining External Hours</span>
                            <h3><?php echo number_format($externalRemainingHours, 1); ?></h3>
                            <p>External hours still needed before completion.</p>
                        </div>
                    </article>
                    <?php endif; ?>
                </div>

                <div class="row g-3 student-profile-info-row mt-1">
                    <div class="col-12 col-lg-6">
                        <section class="card student-panel h-100">
                            <div class="card-body">
                                <div class="student-profile-section-head">
                                    <div>
                                        <span class="student-metric-label">Academic Info</span>
                                        <h3 class="mb-1">Student Information</h3>
                                    </div>
                                    <div class="student-profile-section-copy">Your main school record linked to this account.</div>
                                </div>
                                <div class="student-profile-field-grid">
                                    <div class="student-profile-field">
                                        <span class="student-profile-field-label">Student Number</span>
                                        <strong><?php echo htmlspecialchars(student_profile_value($studentNumber, 'Not yet available'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="student-profile-field">
                                        <span class="student-profile-field-label">Student Status</span>
                                        <strong><?php echo htmlspecialchars($studentStatusDisplay, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="student-profile-field">
                                        <span class="student-profile-field-label">Course</span>
                                        <strong><?php echo htmlspecialchars(student_profile_value($courseName, 'Not yet available'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="student-profile-field">
                                        <span class="student-profile-field-label">Department</span>
                                        <strong><?php echo htmlspecialchars(student_profile_value($departmentName, 'Not yet available'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="student-profile-field student-profile-field--full">
                                        <span class="student-profile-field-label">Section</span>
                                        <strong><?php echo htmlspecialchars(student_profile_value($sectionName, 'Not yet assigned'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="col-12 col-lg-6">
                        <section class="card student-panel h-100">
                            <div class="card-body">
                                <div class="student-profile-section-head">
                                    <div>
                                        <span class="student-metric-label">Contact</span>
                                        <h3 class="mb-1">Account And Contact</h3>
                                    </div>
                                    <div class="student-profile-section-copy">Main contact details saved in BioTern.</div>
                                </div>
                                <div class="student-profile-field-grid">
                                    <div class="student-profile-field">
                                        <span class="student-profile-field-label">Email</span>
                                        <strong><?php echo htmlspecialchars(student_profile_value($contactEmail, 'Not yet available'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="student-profile-field">
                                        <span class="student-profile-field-label">Phone</span>
                                        <strong><?php echo htmlspecialchars(student_profile_value($contactPhone, 'Not yet available'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="student-profile-field student-profile-field--full">
                                        <span class="student-profile-field-label">Address</span>
                                        <strong><?php echo nl2br(htmlspecialchars(student_profile_value($contactAddress, 'Not yet available'), ENT_QUOTES, 'UTF-8')); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>

                <section class="card student-panel mt-4 student-profile-personal-card">
                    <div class="card-body">
                        <div class="student-profile-section-head">
                            <div>
                                <span class="student-metric-label">Personal</span>
                                <h3 class="mb-1">Personal Details</h3>
                            </div>
                            <div class="student-profile-section-copy">Additional profile details saved in your student record.</div>
                        </div>
                        <div class="student-profile-field-grid">
                            <div class="student-profile-field">
                                <span class="student-profile-field-label">Birth Date</span>
                                <strong><?php echo htmlspecialchars(student_profile_format_date($birthDate), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div class="student-profile-field">
                                <span class="student-profile-field-label">Gender</span>
                                <strong><?php echo htmlspecialchars($genderDisplay, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div class="student-profile-field student-profile-field--full">
                                <span class="student-profile-field-label">Emergency Contact</span>
                                <strong><?php echo htmlspecialchars(student_profile_value($emergencyContact, 'Not yet available'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        </div>
                        <div class="d-grid gap-2 d-md-flex mt-3">
                            <a href="profile-details.php#student-personal-edit" class="btn btn-primary">Edit Personal Details</a>
                            <a href="account-settings.php#overview" class="btn btn-outline-secondary">Change Profile Picture</a>
                        </div>
                    </div>
                </section>

                <section class="card student-panel mt-4 student-profile-card-horizontal">
                    <div class="card-body">
                        <div class="student-profile-section-head">
                            <div>
                                <span class="student-metric-label">Internship</span>
                                <h3 class="mb-1">Student Profile Card</h3>
                            </div>
                        </div>
                        <div class="student-profile-facts student-profile-facts--horizontal">
                            <article class="student-profile-fact"><span>Company</span><strong><?php echo htmlspecialchars(student_profile_value((string)($internship['company_name'] ?? ''), 'No company assigned yet'), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>Company Address</span><strong><?php echo htmlspecialchars(student_profile_value((string)($internship['company_address'] ?? ''), 'No company address saved yet'), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>Representative</span><strong><?php echo htmlspecialchars(student_profile_value((string)($internship['company_representative'] ?? ''), student_profile_value((string)($internship['company_supervisor_name'] ?? ''), 'Not provided')), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>Representative Position</span><strong><?php echo htmlspecialchars(student_profile_value((string)($internship['company_representative_position'] ?? ''), student_profile_value((string)($internship['company_supervisor_position'] ?? ''), 'Not provided')), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>Position</span><strong><?php echo htmlspecialchars(student_profile_value((string)($internship['position'] ?? ''), 'No position assigned yet'), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>Track</span><strong><?php echo htmlspecialchars(ucfirst($assignmentTrack), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>Status</span><strong><?php echo htmlspecialchars(student_profile_value((string)($internship['status'] ?? ''), 'Not started'), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>Start Date</span><strong><?php echo htmlspecialchars(student_profile_format_date((string)($internship['start_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>End Date</span><strong><?php echo htmlspecialchars(student_profile_format_date((string)($internship['end_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>Remaining Internal Hours</span><strong><?php echo number_format($internalRemainingHours, 2); ?> / <?php echo number_format($internalTotalHours, 0); ?></strong></article>
                            <?php if ($studentHasExternalAccess): ?>
                            <article class="student-profile-fact"><span>Remaining External Hours</span><strong><?php echo number_format($externalRemainingHours, 2); ?> / <?php echo number_format($externalTotalHours, 0); ?></strong></article>
                            <?php endif; ?>
                            <article class="student-profile-fact"><span>Biometric</span><strong><?php echo $biometricReady ? 'Registered' : 'Pending'; ?></strong></article>
                            <article class="student-profile-fact"><span>Biometric Date</span><strong><?php echo htmlspecialchars(student_profile_format_date((string)($student['biometric_registered_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>First Date Joined</span><strong><?php echo htmlspecialchars($joinedDate, ENT_QUOTES, 'UTF-8'); ?></strong></article>
                            <article class="student-profile-fact"><span>Last Biometric Clock In</span><strong><?php echo htmlspecialchars($lastBiometricClockInText, ENT_QUOTES, 'UTF-8'); ?></strong></article>
                        </div>
                    </div>
                </section>
            </div>

        </div>
    </div>
</div>
    </div>
</main>
<?php if ($assignmentTrack === 'internal'): ?>
<script>
(function () {
    var timerElement = document.getElementById('studentProfileTimerValue');
    if (!timerElement) {
        return;
    }

    var remainingSeconds = <?php echo (int)$timerPreviewSeconds; ?>;
    var isClockedIn = <?php echo !empty($openSession['clocked_in_now']) ? 'true' : 'false'; ?>;
    var requiresCorrection = <?php echo !empty($openSession['requires_correction']) ? 'true' : 'false'; ?>;

    function formatHms(totalSeconds) {
        var safe = Math.max(0, Math.floor(totalSeconds));
        var hours = Math.floor(safe / 3600);
        var minutes = Math.floor((safe % 3600) / 60);
        var seconds = safe % 60;
        return hours + 'h:' + String(minutes).padStart(2, '0') + 'm:' + String(seconds).padStart(2, '0') + 's';
    }

    function tick() {
        timerElement.textContent = formatHms(remainingSeconds);
        if (isClockedIn && !requiresCorrection && remainingSeconds > 0) {
            remainingSeconds--;
        }
    }

    tick();
    if (isClockedIn && !requiresCorrection) {
        window.setInterval(tick, 1000);
    }
})();
</script>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
