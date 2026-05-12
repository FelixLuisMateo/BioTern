<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';

biotern_boot_session(isset($conn) ? $conn : null);
require_roles_page(['admin', 'coordinator', 'supervisor']);

$currentUserId = get_current_user_id_or_zero();
$currentRole = get_current_user_role();
$students = [];
$scopeWhere = '1 = 1';
$hasEvaluations = table_exists($conn, 'evaluations');

if ($currentRole === 'coordinator') {
    $courseIds = coordinator_course_ids($conn, $currentUserId);
    $scopeWhere = empty($courseIds)
        ? '1 = 0'
        : 's.course_id IN (' . implode(',', array_map('intval', $courseIds)) . ')';
} elseif ($currentRole === 'supervisor') {
    $supervisorIds = [$currentUserId];
    $stmtSupervisor = $conn->prepare('SELECT id FROM supervisors WHERE user_id = ? LIMIT 1');
    if ($stmtSupervisor) {
        $stmtSupervisor->bind_param('i', $currentUserId);
        $stmtSupervisor->execute();
        $rowSupervisor = $stmtSupervisor->get_result()->fetch_assoc() ?: null;
        $stmtSupervisor->close();
        if (!empty($rowSupervisor['id'])) {
            $supervisorIds[] = (int)$rowSupervisor['id'];
        }
    }
    $supervisorIds = array_values(array_unique(array_filter($supervisorIds, static fn($id) => (int)$id > 0)));
    $scopeWhere = empty($supervisorIds)
        ? '1 = 0'
        : '(s.supervisor_id IN (' . implode(',', array_map('intval', $supervisorIds)) . ') OR i.supervisor_id IN (' . implode(',', array_map('intval', $supervisorIds)) . '))';
}

$result = $conn->query("
    SELECT
        s.id,
        s.student_id,
        TRIM(CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name)) AS student_name,
        COALESCE(c.name, '-') AS course_name,
        COALESCE(NULLIF(sec.code, ''), NULLIF(sec.name, ''), '-') AS section_name,
        " . ($hasEvaluations ? 'e.score, e.evaluation_date' : 'NULL AS score, NULL AS evaluation_date') . "
    FROM students s
    LEFT JOIN courses c ON c.id = s.course_id
    LEFT JOIN sections sec ON sec.id = s.section_id
    LEFT JOIN internships i ON i.id = (
        SELECT i2.id
        FROM internships i2
        WHERE i2.student_id = s.id
        ORDER BY (i2.status = 'ongoing') DESC, i2.id DESC
        LIMIT 1
    )
    " . ($hasEvaluations ? "LEFT JOIN evaluations e ON e.id = (
        SELECT e2.id
        FROM evaluations e2
        WHERE e2.student_id = s.id
        ORDER BY e2.evaluation_date DESC, e2.id DESC
        LIMIT 1
    )" : '') . "
    WHERE {$scopeWhere}
    ORDER BY s.last_name ASC, s.first_name ASC
    LIMIT 250
");
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $result->close();
}

$page_title = 'Evaluation Form';
$page_styles = ['assets/css/layout/page_shell.css'];
include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Evaluation Form</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Documents</li>
                    <li class="breadcrumb-item">Evaluation Form</li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <div class="card stretch stretch-full">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Student Evaluation Forms</h5>
                            <p class="text-muted mb-0">Open a student record to fill, save, or print the evaluation form.</p>
                        </div>
                        <span class="badge bg-soft-primary text-primary"><?php echo count($students); ?> students</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Student ID</th>
                                    <th>Course</th>
                                    <th>Section</th>
                                    <th>Latest Score</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($students === []): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No students available for evaluation.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars((string)($student['student_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($student['student_id'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($student['course_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($student['section_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo $student['score'] !== null ? htmlspecialchars((string)$student['score'], ENT_QUOTES, 'UTF-8') . '%' : '<span class="text-muted">Not rated</span>'; ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-primary" href="students-view.php?id=<?php echo (int)($student['id'] ?? 0); ?>&tab=evaluation">Open Form</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
