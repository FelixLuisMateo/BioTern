<?php
require_once '../config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_roles_page(['admin', 'coordinator', 'supervisor']);

function evaluate_column_exists(mysqli $conn, string $table, string $column): bool {
    $safeTable = $conn->real_escape_string($table);
    $safeColumn = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
    return ($res && $res->num_rows > 0);
}

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$can_evaluate = false;
$student = null;
$can_access_page = false;
$deny_reason = '';
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$current_user_role = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));

$course_internal_expr = evaluate_column_exists($conn, 'courses', 'internal_hours') ? 'COALESCE(c.internal_hours, 0)' : '0';
$course_external_expr = evaluate_column_exists($conn, 'courses', 'external_hours') ? 'COALESCE(c.external_hours, 0)' : '0';
$course_total_expr = evaluate_column_exists($conn, 'courses', 'total_ojt_hours') ? 'COALESCE(c.total_ojt_hours, 0)' : '0';

$stmt_student = $conn->prepare("SELECT s.*, {$course_internal_expr} AS course_internal_hours, {$course_external_expr} AS course_external_hours, {$course_total_expr} AS course_total_ojt_hours FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1");
if ($stmt_student) {
    $stmt_student->bind_param('i', $student_id);
    $stmt_student->execute();
    $student = $stmt_student->get_result()->fetch_assoc();
    $stmt_student->close();
}

if ($student) {
    if ($current_user_role === 'admin') {
        $can_access_page = true;
    } elseif ($current_user_role === 'supervisor') {
        $can_access_page = ((int)($student['supervisor_id'] ?? 0) === $current_user_id);
        if (!$can_access_page) {
            $deny_reason = 'You are not assigned as this student\'s supervisor.';
        }
    } elseif ($current_user_role === 'coordinator') {
        $assigned = ((int)($student['coordinator_id'] ?? 0) === $current_user_id);
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
        $can_access_page = ($assigned || $course_scoped);
        if (!$can_access_page) {
            $deny_reason = 'You are not assigned to this student or course scope.';
        }
    }
}

$hours_rendered = 0.0;
$assignment_track = strtolower(trim((string)($student['assignment_track'] ?? 'internal')));
$required_hours = 0;
if ($assignment_track === 'external') {
    $required_hours = (int)($student['course_external_hours'] ?? 0);
    if ($required_hours <= 0) {
        $required_hours = (int)($student['external_total_hours'] ?? 0);
    }
    if ($required_hours <= 0) {
        $required_hours = 250;
    }
} else {
    $required_hours = (int)($student['course_internal_hours'] ?? 0);
    if ($required_hours <= 0) {
        $required_hours = (int)($student['internal_total_hours'] ?? 0);
    }
    if ($required_hours <= 0) {
        $required_hours = (int)($student['course_total_ojt_hours'] ?? 0);
    }
    if ($required_hours <= 0) {
        $required_hours = 600;
    }
}

$stmt_hours = $conn->prepare("SELECT COALESCE(SUM(total_hours), 0) AS rendered FROM attendances WHERE student_id = ? AND (status IS NULL OR status <> 'rejected')");
if ($stmt_hours) {
    $stmt_hours->bind_param('i', $student_id);
    $stmt_hours->execute();
    $hours_row = $stmt_hours->get_result()->fetch_assoc();
    $hours_rendered = (float)($hours_row['rendered'] ?? 0);
    $stmt_hours->close();
}

$can_evaluate = ($hours_rendered >= $required_hours) && $can_access_page;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_evaluate && isset($_POST['rating'])) {
    $rating = intval($_POST['rating']);
    $comments = trim((string)($_POST['comments'] ?? ''));
    $evaluator_name = trim((string)($_SESSION['name'] ?? $_SESSION['username'] ?? 'System'));
    if ($rating >= 1 && $rating <= 5) {
        $stmt_insert = $conn->prepare("INSERT INTO evaluations (student_id, evaluator_name, evaluation_date, score, feedback, created_at, updated_at) VALUES (?, ?, CURDATE(), ?, ?, NOW(), NOW())");
        if ($stmt_insert) {
            $stmt_insert->bind_param('isis', $student_id, $evaluator_name, $rating, $comments);
            $stmt_insert->execute();
            $stmt_insert->close();
        }
    }
    header('Location: certificate.php?student_id=' . $student_id);
    exit;
}

$page_title = 'Evaluation';
include 'includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="main-content">
    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">Student Evaluation</h5></div>
        <div class="card-body">
            <?php if (!$student): ?>
                <div class="alert alert-danger mb-0">Student not found.</div>
            <?php elseif (!$can_access_page): ?>
                <div class="alert alert-danger mb-0">Access denied. <?php echo htmlspecialchars($deny_reason !== '' ? $deny_reason : 'Insufficient permission.'); ?></div>
            <?php elseif ($can_evaluate): ?>
                <form method="POST" action="" class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Rating (1-5)</label>
                        <select name="rating" class="form-select" required>
                            <option value="">Select rating</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Comments (optional)</label>
                        <textarea name="comments" rows="4" class="form-control" placeholder="Add remarks..."></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Submit Evaluation</button>
                        <a href="students-view.php?id=<?php echo (int)$student_id; ?>" class="btn btn-light">Back</a>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-warning mb-0">Evaluation is locked: rendered hours (<?php echo number_format((float)$hours_rendered, 1); ?>) are below required hours (<?php echo number_format((float)$required_hours, 1); ?>).</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div> <!-- .nxl-content -->
</main>
<?php include 'includes/footer.php'; ?>





