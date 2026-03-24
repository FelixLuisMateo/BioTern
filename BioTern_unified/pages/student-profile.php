<?php
require_once dirname(__DIR__) . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($currentRole !== 'student' || $currentUserId <= 0) {
    header('Location: homepage.php');
    exit;
}

$stmt = $conn->prepare("SELECT s.*, c.name AS course_name, d.name AS department_name, sec.name AS section_name FROM students s LEFT JOIN courses c ON c.id = s.course_id LEFT JOIN departments d ON d.id = s.department_id LEFT JOIN sections sec ON sec.id = s.section_id WHERE s.user_id = ? LIMIT 1");
$stmt->bind_param('i', $currentUserId);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    header('Location: auth-login-cover.php?logout=1');
    exit;
}

$page_title = 'BioTern || My Student Profile';
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">My Profile</h5>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Student ID</label><input class="form-control" value="<?php echo htmlspecialchars((string)($student['student_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                        <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" value="<?php echo htmlspecialchars(trim((string)(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" value="<?php echo htmlspecialchars((string)($student['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" value="<?php echo htmlspecialchars((string)($student['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                        <div class="col-md-4"><label class="form-label">Course</label><input class="form-control" value="<?php echo htmlspecialchars((string)($student['course_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                        <div class="col-md-4"><label class="form-label">Department</label><input class="form-control" value="<?php echo htmlspecialchars((string)($student['department_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                        <div class="col-md-4"><label class="form-label">Section</label><input class="form-control" value="<?php echo htmlspecialchars((string)($student['section_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                        <div class="col-md-12"><label class="form-label">Address</label><textarea class="form-control" rows="2" readonly><?php echo htmlspecialchars((string)($student['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                    </div>

                    <div class="mt-4 d-flex flex-wrap gap-2">
                        <a href="attendance.php" class="btn btn-primary">View My DTR</a>
                        <a href="document_application.php" class="btn btn-outline-secondary">My Documents</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php include 'includes/footer.php'; ?>
