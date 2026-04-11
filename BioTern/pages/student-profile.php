<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/section_format.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($currentRole !== 'student' || $currentUserId <= 0) {
    header('Location: homepage.php');
    exit;
}

$user = null;
$student = null;
$internship = null;
$recentAttendance = [];
$lastLoginAt = '';
$profileStats = [
    'approved_logs' => 0,
    'pending_logs' => 0,
    'rejected_logs' => 0,
    'total_hours' => 0.0,
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

$sectionParts = array_filter([
    biotern_format_section_code((string)($student['section_code'] ?? '')),
    trim((string)($student['section_name'] ?? '')),
]);
$studentNumber = trim((string)($student['student_id'] ?? ''));
$courseName = trim((string)($student['course_name'] ?? ''));
$departmentName = trim((string)($student['department_name'] ?? ''));
$sectionName = !empty($sectionParts) ? implode(' | ', $sectionParts) : '';
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

$page_title = 'BioTern || My Profile';
$page_styles = [
    'assets/css/homepage-student.css',
    'assets/css/student-profile.css',
];
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
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
                            <a href="profile-details.php#account-settings" class="btn btn-primary">Edit Account</a>
                            <a href="student-dtr.php" class="btn btn-outline-primary">Open My DTR</a>
                            <a href="document_application.php" class="btn btn-outline-secondary">My Documents</a>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="card student-home-hero student-profile-hero">
                    <div class="card-body">
                        <div class="student-home-hero__content student-profile-hero__content">
                            <div>
                                <span class="student-home-eyebrow">Overview</span>
                                <h2>My Profile</h2>
                                <p>Review your student identity, school assignment, account status, and latest internship information without leaving the student workspace.</p>
                            </div>
                            <div class="student-home-actions">
                                <span class="badge bg-soft-primary text-primary fs-11">
                                    <i class="feather-calendar me-1"></i> <?php echo htmlspecialchars(date('M d, Y'), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="student-profile-metric-grid">
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
                            <span class="student-metric-label">Hours Rendered</span>
                            <h3><?php echo number_format((float)($profileStats['total_hours'] ?? 0), 1); ?></h3>
                            <p>Total hours recorded across your DTR.</p>
                        </div>
                    </article>
                </div>

                <section class="card student-panel mt-4">
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

                <section class="card student-panel mt-4">
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

                <section class="card student-panel mt-4">
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
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-3">
                <section class="card student-panel">
                    <div class="card-body">
                        <span class="student-metric-label">Internship</span>
                        <h3 class="mb-3">Latest Details</h3>
                        <div class="student-detail-list student-profile-detail-list">
                            <div>
                                <span>Company</span>
                                <strong><?php echo htmlspecialchars(student_profile_value((string)($internship['company_name'] ?? ''), 'No company assigned yet'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Position</span>
                                <strong><?php echo htmlspecialchars(student_profile_value((string)($internship['position'] ?? ''), 'No position assigned yet'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Status</span>
                                <strong><?php echo htmlspecialchars(student_profile_value((string)($internship['status'] ?? ''), 'Not started'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Start Date</span>
                                <strong><?php echo htmlspecialchars(student_profile_format_date((string)($internship['start_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>End Date</span>
                                <strong><?php echo htmlspecialchars(student_profile_format_date((string)($internship['end_date'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card student-panel mt-4">
                    <div class="card-body">
                        <span class="student-metric-label">Account Status</span>
                        <h3 class="mb-3">BioTern Status</h3>
                        <div class="student-detail-list student-profile-detail-list">
                            <div>
                                <span>Biometric</span>
                                <strong><?php echo $biometricReady ? 'Registered' : 'Pending'; ?></strong>
                            </div>
                            <div>
                                <span>Biometric Date</span>
                                <strong><?php echo htmlspecialchars(student_profile_format_date((string)($student['biometric_registered_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Member Since</span>
                                <strong><?php echo htmlspecialchars($joinedDate, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div>
                                <span>Last Login</span>
                                <strong><?php echo htmlspecialchars($lastLoginText, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card student-panel mt-4">
                    <div class="card-body">
                        <span class="student-metric-label">Profile Completion</span>
                        <h3 class="mb-3">Readiness</h3>
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
                            <a href="profile-details.php#account-settings" class="btn btn-primary">Update My Details</a>
                            <a href="document_application.php" class="btn btn-outline-secondary">Review My Documents</a>
                        </div>
                    </div>
                </section>

                <section class="card student-panel mt-4">
                    <div class="card-body">
                        <span class="student-metric-label">Recent Attendance</span>
                        <h3 class="mb-3">Activity</h3>
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
        </div>
    </div>
</div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>
