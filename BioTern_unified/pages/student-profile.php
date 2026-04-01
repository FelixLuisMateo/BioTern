<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/avatar.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

$userStmt = $conn->prepare('SELECT id, name, username, email, profile_picture, created_at FROM users WHERE id = ? LIMIT 1');
if ($userStmt) {
    $userStmt->bind_param('i', $currentUserId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc() ?: null;
    $userStmt->close();
}

$studentStmt = $conn->prepare(
    "SELECT s.id, s.student_id, s.first_name, s.last_name, s.email AS student_email, s.phone, s.address,
            s.status AS student_status, s.biometric_registered,
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

    if ($fallbackEmail !== '' || $fallbackName !== '') {
        $fallbackStudentStmt = $conn->prepare(
            "SELECT s.id, s.student_id, s.first_name, s.last_name, s.email AS student_email, s.phone, s.address,
                    s.status AS student_status, s.biometric_registered,
                    c.name AS course_name, d.name AS department_name, sec.code AS section_code, sec.name AS section_name
             FROM students s
             LEFT JOIN courses c ON c.id = s.course_id
             LEFT JOIN departments d ON d.id = s.department_id
             LEFT JOIN sections sec ON sec.id = s.section_id
             WHERE (
                    (? <> '' AND LOWER(COALESCE(s.email, '')) = LOWER(?))
                    OR
                    (? <> '' AND LOWER(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')))) = LOWER(?))
             )
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
        "SELECT attendance_date, status, total_hours
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
}

$displayName = trim((string)($user['name'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string)(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
}
if ($displayName === '') {
    $displayName = 'Student User';
}

$sectionParts = array_filter([
    trim((string)($student['section_code'] ?? '')),
    trim((string)($student['section_name'] ?? '')),
]);

$studentNumber = trim((string)($student['student_id'] ?? ''));
$courseName = trim((string)($student['course_name'] ?? ''));
$departmentName = trim((string)($student['department_name'] ?? ''));
$sectionName = !empty($sectionParts) ? implode(' | ', $sectionParts) : '';
$avatarSrc = biotern_avatar_public_src((string)($user['profile_picture'] ?? ''), $currentUserId);

$page_title = 'BioTern || My Profile';
include 'includes/header.php';
?>
<div class="main-content">
            <style>
                .profile-storage-shell .card {
                    border: 1px solid rgba(148, 163, 184, 0.16);
                    border-radius: 20px;
                    box-shadow: 0 14px 36px rgba(15, 23, 42, 0.06);
                    background: #fff;
                }

                .profile-storage-shell .card-body,
                .profile-storage-shell .card-header {
                    padding: 22px;
                }

                .profile-storage-kicker {
                    display: inline-block;
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: .08em;
                    color: #64748b;
                    margin-bottom: 8px;
                }

                .profile-storage-sidebar-card {
                    position: sticky;
                    top: 24px;
                }

                .profile-avatar {
                    width: 110px;
                    height: 110px;
                    border-radius: 28px;
                    object-fit: cover;
                    display: block;
                    margin: 0 auto 16px;
                    border: 4px solid rgba(37, 99, 235, 0.14);
                }

                .profile-storage-name {
                    margin: 0 0 6px;
                    text-align: center;
                    color: #0f172a;
                    font-size: 1.9rem;
                    font-weight: 800;
                    line-height: 1.05;
                }

                .profile-storage-role {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 12px;
                    border-radius: 999px;
                    background: rgba(37, 99, 235, 0.1);
                    color: #1d4ed8;
                    font-size: 12px;
                    font-weight: 700;
                }

                .profile-storage-center {
                    text-align: center;
                }

                .profile-storage-copy,
                .profile-storage-meta,
                .profile-storage-empty {
                    color: #64748b;
                }

                .profile-storage-actions {
                    display: grid;
                    gap: 10px;
                    margin-top: 18px;
                }

                .profile-storage-grid {
                    display: grid;
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                    gap: 14px;
                }

                .profile-storage-field {
                    padding: 16px;
                    border-radius: 16px;
                    background: #f8fafc;
                    border: 1px solid rgba(226, 232, 240, 0.95);
                }

                .profile-storage-field.full {
                    grid-column: 1 / -1;
                }

                .profile-storage-label {
                    color: #64748b;
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: .06em;
                    margin-bottom: 8px;
                }

                .profile-storage-value {
                    color: #0f172a;
                    font-weight: 600;
                }

                .profile-storage-list {
                    display: grid;
                    gap: 12px;
                }

                .profile-storage-item {
                    padding: 14px 16px;
                    border-radius: 16px;
                    background: #f8fafc;
                    border: 1px solid rgba(226, 232, 240, 0.95);
                }

                .profile-storage-item-title {
                    color: #0f172a;
                    font-weight: 700;
                    margin-bottom: 4px;
                }

                html.app-skin-dark .profile-storage-shell .card {
                    background: rgba(15, 23, 42, 0.92);
                    border-color: rgba(148, 163, 184, 0.14);
                }

                html.app-skin-dark .profile-storage-name,
                html.app-skin-dark .profile-storage-value,
                html.app-skin-dark .profile-storage-item-title {
                    color: #f8fafc;
                }

                html.app-skin-dark .profile-storage-copy,
                html.app-skin-dark .profile-storage-meta,
                html.app-skin-dark .profile-storage-empty,
                html.app-skin-dark .profile-storage-kicker,
                html.app-skin-dark .profile-storage-label {
                    color: #94a3b8;
                }

                html.app-skin-dark .profile-storage-field,
                html.app-skin-dark .profile-storage-item {
                    background: rgba(30, 41, 59, 0.7);
                    border-color: rgba(148, 163, 184, 0.14);
                }

                @media (max-width: 767.98px) {
                    .profile-storage-grid {
                        grid-template-columns: 1fr;
                    }

                    .profile-storage-sidebar-card {
                        position: static;
                    }
                }
            </style>

            <div class="profile-storage-shell">
                <div class="row g-4 align-items-start">
                    <div class="col-12 col-xl-3">
                        <section class="card profile-storage-sidebar-card">
                            <div class="card-body">
                                <div class="profile-storage-center">
                                    <span class="profile-storage-kicker">Student Profile</span>
                                    <img src="<?php echo htmlspecialchars($avatarSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile Avatar" class="profile-avatar">
                                    <h2 class="profile-storage-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h2>
                                    <span class="profile-storage-role"><i class="feather-user"></i> My Profile</span>
                                    <p class="profile-storage-copy mt-3 mb-0">Student identity, academic placement, internship details, and account information.</p>
                                </div>

                                <div class="profile-storage-actions">
                                    <a href="profile-details.php#account-settings" class="btn btn-primary">Edit Account</a>
                                    <a href="student-dtr.php" class="btn btn-outline-primary">Open My DTR</a>
                                    <a href="document_application.php" class="btn btn-outline-secondary">My Documents</a>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="col-12 col-xl-6">
                        <section class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                                    <div>
                                        <span class="profile-storage-kicker">Overview</span>
                                        <h3 class="mb-1">My Profile</h3>
                                        <div class="profile-storage-meta">Home / Student / My Profile</div>
                                    </div>
                                    <span class="badge bg-soft-primary text-primary fs-11"><i class="feather-calendar me-1"></i> <?php echo date('M d, Y'); ?></span>
                                </div>

                                <div class="profile-storage-grid">
                                    <div class="profile-storage-field">
                                        <div class="profile-storage-label">Student Number</div>
                                        <div class="profile-storage-value"><?php echo htmlspecialchars($studentNumber !== '' ? $studentNumber : 'Not yet available', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="profile-storage-field">
                                        <div class="profile-storage-label">Student Status</div>
                                        <div class="profile-storage-value"><?php echo htmlspecialchars(trim((string)($student['student_status'] ?? '')) !== '' ? (string)$student['student_status'] : 'Not yet available', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="profile-storage-field">
                                        <div class="profile-storage-label">Course</div>
                                        <div class="profile-storage-value"><?php echo htmlspecialchars($courseName !== '' ? $courseName : 'Not yet available', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="profile-storage-field">
                                        <div class="profile-storage-label">Department</div>
                                        <div class="profile-storage-value"><?php echo htmlspecialchars($departmentName !== '' ? $departmentName : 'Not yet available', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="profile-storage-field full">
                                        <div class="profile-storage-label">Section</div>
                                        <div class="profile-storage-value"><?php echo htmlspecialchars($sectionName !== '' ? $sectionName : 'Not yet assigned', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="profile-storage-field">
                                        <div class="profile-storage-label">Email</div>
                                        <div class="profile-storage-value"><?php echo htmlspecialchars(trim((string)($student['student_email'] ?? ($user['email'] ?? ''))) !== '' ? (string)($student['student_email'] ?? ($user['email'] ?? '')) : 'Not yet available', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="profile-storage-field">
                                        <div class="profile-storage-label">Phone</div>
                                        <div class="profile-storage-value"><?php echo htmlspecialchars(trim((string)($student['phone'] ?? '')) !== '' ? (string)$student['phone'] : 'Not yet available', ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <div class="profile-storage-field full">
                                        <div class="profile-storage-label">Address</div>
                                        <div class="profile-storage-value"><?php echo nl2br(htmlspecialchars(trim((string)($student['address'] ?? '')) !== '' ? (string)$student['address'] : 'Not yet available', ENT_QUOTES, 'UTF-8')); ?></div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="col-12 col-xl-3">
                        <section class="card">
                            <div class="card-body">
                                <span class="profile-storage-kicker">Internship</span>
                                <h3 class="mb-3">Latest Details</h3>
                                <div class="profile-storage-list">
                                    <div class="profile-storage-item">
                                        <div class="profile-storage-item-title"><?php echo htmlspecialchars(trim((string)($internship['company_name'] ?? '')) !== '' ? (string)$internship['company_name'] : 'No company assigned yet', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="profile-storage-meta">Company</div>
                                    </div>
                                    <div class="profile-storage-item">
                                        <div class="profile-storage-item-title"><?php echo htmlspecialchars(trim((string)($internship['position'] ?? '')) !== '' ? (string)$internship['position'] : 'No position assigned yet', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="profile-storage-meta">Position</div>
                                    </div>
                                    <div class="profile-storage-item">
                                        <div class="profile-storage-item-title"><?php echo htmlspecialchars(trim((string)($internship['status'] ?? '')) !== '' ? ucfirst((string)$internship['status']) : 'Not started', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="profile-storage-meta">Status</div>
                                    </div>
                                    <div class="profile-storage-item">
                                        <div class="profile-storage-item-title"><?php echo number_format((float)($internship['rendered_hours'] ?? 0), 0); ?> / <?php echo number_format((float)($internship['required_hours'] ?? 0), 0); ?> hrs</div>
                                        <div class="profile-storage-meta">Rendered vs Required</div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="card mt-4">
                            <div class="card-body">
                                <span class="profile-storage-kicker">Recent Attendance</span>
                                <h3 class="mb-3">Activity</h3>
                                <?php if (!empty($recentAttendance)): ?>
                                    <div class="profile-storage-list">
                                        <?php foreach ($recentAttendance as $attendance): ?>
                                            <div class="profile-storage-item">
                                                <div class="profile-storage-item-title"><?php echo htmlspecialchars((string)($attendance['attendance_date'] ?? 'Unknown date'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="profile-storage-meta">
                                                    <?php echo htmlspecialchars(ucfirst((string)($attendance['status'] ?? 'unknown')), ENT_QUOTES, 'UTF-8'); ?>
                                                    •
                                                    <?php echo number_format((float)($attendance['total_hours'] ?? 0), 2); ?> hrs
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="profile-storage-empty">No attendance entries yet.</div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
</div>
<?php include 'includes/footer.php'; ?>
