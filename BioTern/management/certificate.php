<?php
require_once '../config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

require_roles_page(['admin', 'coordinator', 'supervisor']);

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_user_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));

$student = null;
$evaluation = null;
$can_access_certificate = false;
$deny_reason = '';

$stmt_student = $conn->prepare("SELECT s.*, c.id AS course_id FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1");
if ($stmt_student) {
    $stmt_student->bind_param('i', $student_id);
    $stmt_student->execute();
    $student = $stmt_student->get_result()->fetch_assoc();
    $stmt_student->close();
}

if ($student) {
    if ($current_user_role === 'admin') {
        $can_access_certificate = true;
    } elseif ($current_user_role === 'supervisor') {
        $assignedSupervisorId = (int)($student['supervisor_id'] ?? 0);
        $supervisorProfileId = 0;
        $supervisorProfileStmt = $conn->prepare("SELECT id FROM supervisors WHERE user_id = ? LIMIT 1");
        if ($supervisorProfileStmt) {
            $supervisorProfileStmt->bind_param('i', $current_user_id);
            $supervisorProfileStmt->execute();
            $supervisorProfileRow = $supervisorProfileStmt->get_result()->fetch_assoc();
            $supervisorProfileStmt->close();
            $supervisorProfileId = (int)($supervisorProfileRow['id'] ?? 0);
        }
        $can_access_certificate = ($assignedSupervisorId > 0) && (
            $assignedSupervisorId === $current_user_id
            || ($supervisorProfileId > 0 && $assignedSupervisorId === $supervisorProfileId)
        );
        if (!$can_access_certificate) {
            $deny_reason = 'You are not assigned as this student\'s supervisor.';
        }
    } elseif ($current_user_role === 'coordinator') {
        $assignedCoordinatorId = (int)($student['coordinator_id'] ?? 0);
        $coordinatorProfileId = 0;
        $coordinatorProfileStmt = $conn->prepare("SELECT id FROM coordinators WHERE user_id = ? LIMIT 1");
        if ($coordinatorProfileStmt) {
            $coordinatorProfileStmt->bind_param('i', $current_user_id);
            $coordinatorProfileStmt->execute();
            $coordinatorProfileRow = $coordinatorProfileStmt->get_result()->fetch_assoc();
            $coordinatorProfileStmt->close();
            $coordinatorProfileId = (int)($coordinatorProfileRow['id'] ?? 0);
        }
        $assigned = ($assignedCoordinatorId > 0) && (
            $assignedCoordinatorId === $current_user_id
            || ($coordinatorProfileId > 0 && $assignedCoordinatorId === $coordinatorProfileId)
        );
        $course_scoped = false;
        $tbl = $conn->query("SHOW TABLES LIKE 'coordinator_courses'");
        if ($tbl && $tbl->num_rows > 0 && (int)($student['course_id'] ?? 0) > 0) {
            $stmt_scope = $conn->prepare("SELECT id FROM coordinator_courses WHERE coordinator_user_id = ? AND course_id = ? LIMIT 1");
            if ($stmt_scope) {
                $course_id = (int)($student['course_id'] ?? 0);
                $stmt_scope->bind_param('ii', $current_user_id, $course_id);
                $stmt_scope->execute();
                $course_scoped = (bool)$stmt_scope->get_result()->fetch_assoc();
                $stmt_scope->close();
            }
        }
        $can_access_certificate = ($assigned || $course_scoped);
        if (!$can_access_certificate) {
            $deny_reason = 'You are not assigned to this student or course scope.';
        }
    }
}

$stmt_eval = $conn->prepare("SELECT * FROM evaluations WHERE student_id = ? ORDER BY evaluation_date DESC, id DESC LIMIT 1");
if ($stmt_eval) {
    $stmt_eval->bind_param('i', $student_id);
    $stmt_eval->execute();
    $evaluation = $stmt_eval->get_result()->fetch_assoc();
    $stmt_eval->close();
}

if (!$student || !$evaluation || !$can_access_certificate) {
    $reason = !$student ? 'Student not found.' : (!$evaluation ? 'Certificate not available yet (evaluation missing).' : ('Access denied. ' . $deny_reason));
    die($reason);
}

$page_title = 'Certificate of Completion';
$page_scripts = array('assets/js/modules/management/certificate-runtime.js');
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="main-content">
    <div class="card">
        <div class="card-body text-center p-4 p-md-5">
            <h2 class="mb-3">Certificate of Completion</h2>
            <p class="mb-2">This certifies that</p>
            <h4 class="mb-3"><?php echo htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))); ?></h4>
            <p class="mb-2">has completed the internship requirements.</p>
            <p class="mb-2">Evaluation Rating: <strong><?php echo (int)($evaluation['score'] ?? 0); ?>/5</strong></p>
            <p class="text-muted mb-4">Date Issued: <?php echo htmlspecialchars((string)($evaluation['evaluation_date'] ?? '')); ?></p>
            <div class="d-flex justify-content-center gap-2">
                <button type="button" class="btn btn-primary js-print-certificate">Print Certificate</button>
                <a href="students-view.php?id=<?php echo (int)$student_id; ?>" class="btn btn-light">Back</a>
            </div>
        </div>
    </div>
</div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>





