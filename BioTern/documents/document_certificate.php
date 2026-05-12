<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/ops_helpers.php';
require_once dirname(__DIR__) . '/lib/section_format.php';

biotern_boot_session(isset($conn) ? $conn : null);
require_roles_page(['admin', 'coordinator', 'supervisor']);

$currentUserId = get_current_user_id_or_zero();
$currentRole = get_current_user_role();
$selectedStudentId = (int)($_GET['id'] ?? $_GET['student_id'] ?? 0);

function certificate_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function certificate_supervisor_profile_id(mysqli $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $conn->prepare('SELECT id FROM supervisors WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

function certificate_coordinator_profile_id(mysqli $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $conn->prepare('SELECT id FROM coordinators WHERE user_id = ? LIMIT 1');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return (int)($row['id'] ?? 0);
}

function certificate_scope_sql(mysqli $conn, string $role, int $userId): string
{
    if ($role === 'admin') {
        return '1 = 1';
    }

    if ($role === 'supervisor') {
        $profileId = certificate_supervisor_profile_id($conn, $userId);
        $ids = array_values(array_unique(array_filter([$userId, $profileId], static fn($id) => (int)$id > 0)));
        if ($ids === []) {
            return '1 = 0';
        }
        $idList = implode(',', array_map('intval', $ids));
        return "(s.supervisor_id IN ({$idList}) OR i.supervisor_id IN ({$idList}))";
    }

    if ($role === 'coordinator') {
        $profileId = certificate_coordinator_profile_id($conn, $userId);
        $ids = array_values(array_unique(array_filter([$userId, $profileId], static fn($id) => (int)$id > 0)));
        $parts = [];
        if ($ids !== []) {
            $idList = implode(',', array_map('intval', $ids));
            $parts[] = "(s.coordinator_id IN ({$idList}) OR i.coordinator_id IN ({$idList}))";
        }
        if (table_exists($conn, 'coordinator_courses')) {
            $parts[] = "EXISTS (
                SELECT 1 FROM coordinator_courses cc
                WHERE cc.coordinator_user_id = " . (int)$userId . "
                  AND cc.course_id = s.course_id
                LIMIT 1
            )";
        }
        return $parts !== [] ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
    }

    return '1 = 0';
}

$scopeSql = certificate_scope_sql($conn, $currentRole, $currentUserId);
$certificate = null;
$eligibleStudents = [];
$error = '';

if ($selectedStudentId > 0) {
    $stmt = $conn->prepare("
        SELECT
            s.id,
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.school_year,
            c.name AS course_name,
            sec.code AS section_code,
            sec.name AS section_name,
            i.company_name,
            i.position,
            i.required_hours,
            i.rendered_hours,
            i.completion_percentage,
            e.score,
            e.evaluation_date
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        LEFT JOIN internships i ON i.student_id = s.id AND i.status IN ('ongoing', 'completed', 'finished')
        INNER JOIN evaluations e ON e.id = (
            SELECT e2.id FROM evaluations e2
            WHERE e2.student_id = s.id
            ORDER BY e2.evaluation_date DESC, e2.id DESC
            LIMIT 1
        )
        WHERE s.id = ? AND {$scopeSql}
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $selectedStudentId);
        $stmt->execute();
        $certificate = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
    if (!$certificate) {
        $error = 'Certificate is not available yet. Make sure the student has an evaluation and you have access to that student.';
    }
} else {
    $result = $conn->query("
        SELECT
            s.id,
            s.student_id,
            TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student_name,
            c.name AS course_name,
            i.completion_percentage,
            e.evaluation_date
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN internships i ON i.student_id = s.id AND i.status IN ('ongoing', 'completed', 'finished')
        INNER JOIN evaluations e ON e.id = (
            SELECT e2.id FROM evaluations e2
            WHERE e2.student_id = s.id
            ORDER BY e2.evaluation_date DESC, e2.id DESC
            LIMIT 1
        )
        WHERE {$scopeSql}
        ORDER BY e.evaluation_date DESC, s.last_name ASC, s.first_name ASC
        LIMIT 100
    ");
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $eligibleStudents[] = $row;
        }
        $result->close();
    }
}

$page_title = 'Certificate of Completion';
$page_styles = [
    'assets/css/layout/page_shell.css',
];
include __DIR__ . '/../includes/header.php';
?>
<style>
    .certificate-picker-card,
    .certificate-print-card {
        border: 1px solid var(--bs-border-color);
        border-radius: 10px;
        background: var(--bs-card-bg);
    }
    .certificate-sheet {
        width: min(100%, 900px);
        min-height: 620px;
        margin: 0 auto;
        padding: 54px 64px;
        background: #fff;
        color: #111827;
        border: 12px double #1d4ed8;
        text-align: center;
        font-family: "Times New Roman", serif;
    }
    .certificate-logo {
        width: 78px;
        height: 78px;
        object-fit: contain;
        margin-bottom: 12px;
    }
    .certificate-school {
        font-size: 24px;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .certificate-title {
        margin: 34px 0 16px;
        font-size: 36px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .certificate-name {
        margin: 18px auto 8px;
        padding-bottom: 8px;
        max-width: 620px;
        border-bottom: 2px solid #111827;
        font-size: 34px;
        font-weight: 700;
    }
    .certificate-body {
        max-width: 700px;
        margin: 18px auto;
        font-size: 18px;
        line-height: 1.7;
    }
    .certificate-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
        max-width: 620px;
        margin: 28px auto 0;
        text-align: left;
        font-family: Arial, sans-serif;
        font-size: 13px;
    }
    .certificate-signatures {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 56px;
        max-width: 680px;
        margin: 64px auto 0;
        font-family: Arial, sans-serif;
        font-size: 13px;
    }
    .certificate-sign-line {
        border-top: 1px solid #111827;
        padding-top: 8px;
        font-weight: 700;
    }
    @media print {
        body * {
            visibility: hidden !important;
        }
        .certificate-print-area,
        .certificate-print-area * {
            visibility: visible !important;
        }
        .certificate-print-area {
            position: absolute;
            inset: 0;
            padding: 0;
            background: #fff;
        }
        .certificate-sheet {
            width: 100%;
            min-height: 100vh;
            border-color: #111827;
            box-shadow: none;
        }
        .certificate-no-print {
            display: none !important;
        }
    }
</style>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header certificate-no-print">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Certificate of Completion</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item">Documents</li>
                    <li class="breadcrumb-item">Certificate of Completion</li>
                </ul>
            </div>
        </div>

        <div class="main-content">
            <?php if ($selectedStudentId <= 0): ?>
                <div class="card certificate-picker-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Printable Certificates</h5>
                                <p class="text-muted mb-0">Choose a student with a completed evaluation, then print the certificate.</p>
                            </div>
                            <span class="badge bg-soft-primary text-primary"><?php echo count($eligibleStudents); ?> available</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Student ID</th>
                                        <th>Course</th>
                                        <th>Completion</th>
                                        <th>Evaluation Date</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($eligibleStudents === []): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No evaluated students available yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($eligibleStudents as $row): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo certificate_h($row['student_name'] ?? ''); ?></td>
                                                <td><?php echo certificate_h($row['student_id'] ?? ''); ?></td>
                                                <td><?php echo certificate_h($row['course_name'] ?? '-'); ?></td>
                                                <td><?php echo number_format((float)($row['completion_percentage'] ?? 0), 2); ?>%</td>
                                                <td><?php echo certificate_h($row['evaluation_date'] ?? ''); ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-primary" href="document_certificate.php?id=<?php echo (int)($row['id'] ?? 0); ?>">Open Certificate</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger certificate-no-print"><?php echo certificate_h($error); ?></div>
                    <a class="btn btn-light certificate-no-print" href="document_certificate.php">Back to certificates</a>
                <?php elseif ($certificate): ?>
                    <?php
                    $studentName = trim((string)($certificate['first_name'] ?? '') . ' ' . (string)($certificate['middle_name'] ?? '') . ' ' . (string)($certificate['last_name'] ?? ''));
                    $sectionLabel = biotern_format_section_label((string)($certificate['section_code'] ?? ''), (string)($certificate['section_name'] ?? ''));
                    $score = (int)($certificate['score'] ?? 0);
                    $rating = $score > 5 ? ($score . '%') : ($score . '/5');
                    ?>
                    <div class="d-flex justify-content-end gap-2 mb-3 certificate-no-print">
                        <a class="btn btn-light" href="document_certificate.php">Back</a>
                        <button type="button" class="btn btn-primary" onclick="window.print()">Print Certificate</button>
                    </div>
                    <section class="certificate-print-area">
                        <div class="certificate-sheet">
                            <img class="certificate-logo" src="assets/images/ccstlogo.png" alt="">
                            <div class="certificate-school">Clark College of Science and Technology</div>
                            <div class="text-muted" style="font-family: Arial, sans-serif; font-size: 13px;">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
                            <div class="certificate-title">Certificate of Completion</div>
                            <div class="certificate-body">This certifies that</div>
                            <div class="certificate-name"><?php echo certificate_h($studentName); ?></div>
                            <div class="certificate-body">
                                has satisfactorily completed the required internship training hours and requirements for
                                <strong><?php echo certificate_h((string)($certificate['course_name'] ?? '')); ?></strong>.
                            </div>
                            <div class="certificate-meta">
                                <div><strong>Student ID:</strong> <?php echo certificate_h($certificate['student_id'] ?? ''); ?></div>
                                <div><strong>Section:</strong> <?php echo certificate_h($sectionLabel !== '' ? $sectionLabel : '-'); ?></div>
                                <div><strong>Company:</strong> <?php echo certificate_h($certificate['company_name'] ?? '-'); ?></div>
                                <div><strong>Evaluation Rating:</strong> <?php echo certificate_h($rating); ?></div>
                                <div><strong>Rendered Hours:</strong> <?php echo number_format((float)($certificate['rendered_hours'] ?? 0), 2); ?></div>
                                <div><strong>Date Issued:</strong> <?php echo certificate_h($certificate['evaluation_date'] ?? date('Y-m-d')); ?></div>
                            </div>
                            <div class="certificate-signatures">
                                <div><div class="certificate-sign-line">OJT Coordinator</div></div>
                                <div><div class="certificate-sign-line">School Administrator</div></div>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
