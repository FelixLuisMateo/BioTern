<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth-session.php';
require_once __DIR__ . '/../lib/section_format.php';
biotern_boot_session(isset($conn) ? $conn : null);

$studentId = (int)($_GET['id'] ?? 0);
if ($studentId <= 0) {
    header('Location: ojt-internal-list.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.email,
        s.school_year,
        s.semester,
        s.assignment_track,
        s.supervisor_name,
        s.coordinator_name,
        c.name AS course_name,
        COALESCE(NULLIF(sec.code, ''), sec.name, '-') AS section_name
    FROM students s
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    WHERE s.id = ?
    LIMIT 1
");
$stmt->bind_param('i', $studentId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc() ?: null;
$stmt->close();

if (!$student) {
    header('Location: ojt-internal-list.php');
    exit;
}

$internStmt = $conn->prepare("
    SELECT company_name, company_address, position, start_date, end_date, status, required_hours, rendered_hours
    FROM internships
    WHERE student_id = ? AND deleted_at IS NULL
      AND LOWER(TRIM(COALESCE(type, 'internal'))) = 'internal'
    ORDER BY updated_at DESC, id DESC
    LIMIT 1
");
$internship = null;
if ($internStmt) {
    $internStmt->bind_param('i', $studentId);
    $internStmt->execute();
    $internship = $internStmt->get_result()->fetch_assoc() ?: null;
    $internStmt->close();
}

$page_title = 'Internal Student View';
$page_body_class = 'page-ojt-internal-view';
$page_styles = ['assets/css/layout/page_shell.css'];
$base_href = '';
include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Internal Student View</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="ojt-internal-list.php">Internal Students</a></li>
                    <li class="breadcrumb-item">Profile</li>
                </ul>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-lg-5">
                <div class="card"><div class="card-body">
                    <h4 class="mb-2"><?php echo htmlspecialchars(trim((string)$student['first_name'] . ' ' . (string)$student['last_name']), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars((string)$student['student_id'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <dl class="row mb-0">
                        <dt class="col-5">Course</dt><dd class="col-7"><?php echo htmlspecialchars((string)($student['course_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-5">Section</dt><dd class="col-7"><?php echo htmlspecialchars(biotern_format_section_code((string)($student['section_name'] ?? '-')), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-5">School Year</dt><dd class="col-7"><?php echo htmlspecialchars((string)($student['school_year'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-5">Semester</dt><dd class="col-7"><?php echo htmlspecialchars((string)($student['semester'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-5">Supervisor</dt><dd class="col-7"><?php echo htmlspecialchars((string)($student['supervisor_name'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                        <dt class="col-5">Coordinator</dt><dd class="col-7"><?php echo htmlspecialchars((string)($student['coordinator_name'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                    </dl>
                </div></div>
            </div>
            <div class="col-12 col-lg-7">
                <div class="card"><div class="card-body">
                    <h5 class="mb-3">Internal OJT Information</h5>
                    <?php if ($internship): ?>
                        <dl class="row mb-0">
                            <dt class="col-4">Company</dt><dd class="col-8"><?php echo htmlspecialchars((string)($internship['company_name'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                            <dt class="col-4">Address</dt><dd class="col-8"><?php echo htmlspecialchars((string)($internship['company_address'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                            <dt class="col-4">Position</dt><dd class="col-8"><?php echo htmlspecialchars((string)($internship['position'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                            <dt class="col-4">Status</dt><dd class="col-8"><?php echo htmlspecialchars(ucfirst((string)($internship['status'] ?: 'unknown')), ENT_QUOTES, 'UTF-8'); ?></dd>
                            <dt class="col-4">Start Date</dt><dd class="col-8"><?php echo htmlspecialchars((string)($internship['start_date'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                            <dt class="col-4">End Date</dt><dd class="col-8"><?php echo htmlspecialchars((string)($internship['end_date'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>
                            <dt class="col-4">Hours</dt><dd class="col-8"><?php echo (int)($internship['rendered_hours'] ?? 0); ?> / <?php echo (int)($internship['required_hours'] ?? 0); ?></dd>
                        </dl>
                    <?php else: ?>
                        <p class="text-muted mb-0">No internal internship record is attached to this student yet.</p>
                    <?php endif; ?>
                </div></div>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
