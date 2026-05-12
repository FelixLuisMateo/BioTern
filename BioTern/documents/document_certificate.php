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

function certificate_person_name(array $row, string $prefix): string
{
    return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter([
        (string)($row[$prefix . '_first_name'] ?? ''),
        (string)($row[$prefix . '_middle_name'] ?? ''),
        (string)($row[$prefix . '_last_name'] ?? ''),
    ], static fn($part) => trim($part) !== ''))));
}

function certificate_display_date(?string $date): string
{
    $date = trim((string)$date);
    if ($date === '' || $date === '0000-00-00') {
        return '';
    }
    $timestamp = strtotime($date);
    return $timestamp ? date('F j, Y', $timestamp) : $date;
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
            i.start_date,
            i.end_date,
            i.required_hours,
            i.rendered_hours,
            i.completion_percentage,
            ea_stats.first_attendance_date,
            ea_stats.last_attendance_date,
            ea_stats.approved_hours,
            COALESCE(coord_i.first_name, coord_s.first_name) AS coordinator_first_name,
            COALESCE(coord_i.middle_name, coord_s.middle_name) AS coordinator_middle_name,
            COALESCE(coord_i.last_name, coord_s.last_name) AS coordinator_last_name,
            COALESCE(sup_i.first_name, sup_s.first_name) AS supervisor_first_name,
            COALESCE(sup_i.middle_name, sup_s.middle_name) AS supervisor_middle_name,
            COALESCE(sup_i.last_name, sup_s.last_name) AS supervisor_last_name,
            s.coordinator_name AS fallback_coordinator_name,
            s.supervisor_name AS fallback_supervisor_name,
            e.score,
            e.evaluation_date
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        LEFT JOIN sections sec ON sec.id = s.section_id
        LEFT JOIN internships i ON i.id = (
            SELECT i2.id FROM internships i2
            WHERE i2.student_id = s.id
            ORDER BY (i2.type = 'external') DESC, FIELD(i2.status, 'completed', 'finished', 'ongoing', 'pending', 'cancelled'), i2.id DESC
            LIMIT 1
        )
        LEFT JOIN (
            SELECT
                student_id,
                MIN(attendance_date) AS first_attendance_date,
                MAX(attendance_date) AS last_attendance_date,
                SUM(total_hours) AS approved_hours
            FROM external_attendance
            WHERE status = 'approved'
            GROUP BY student_id
        ) ea_stats ON ea_stats.student_id = s.id
        LEFT JOIN coordinators coord_i ON coord_i.user_id = i.coordinator_id
        LEFT JOIN coordinators coord_s ON coord_s.id = s.coordinator_id
        LEFT JOIN supervisors sup_i ON sup_i.user_id = i.supervisor_id
        LEFT JOIN supervisors sup_s ON sup_s.id = s.supervisor_id
        LEFT JOIN evaluations e ON e.id = (
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
        $error = 'Certificate is not available yet. Make sure the student exists and you have access to that student.';
    }
} else {
    $result = $conn->query("
        SELECT
            s.id,
            s.student_id,
            TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student_name,
            c.name AS course_name,
            i.required_hours,
            COALESCE(ea_stats.approved_hours, i.rendered_hours, 0) AS rendered_hours,
            i.completion_percentage,
            e.evaluation_date
        FROM students s
        LEFT JOIN courses c ON c.id = s.course_id
        INNER JOIN internships i ON i.id = (
            SELECT i2.id FROM internships i2
            WHERE i2.student_id = s.id
              AND i2.type = 'external'
              AND i2.status IN ('ongoing', 'completed', 'finished')
            ORDER BY FIELD(i2.status, 'completed', 'finished', 'ongoing'), i2.id DESC
            LIMIT 1
        )
        LEFT JOIN (
            SELECT student_id, SUM(total_hours) AS approved_hours
            FROM external_attendance
            WHERE status = 'approved'
            GROUP BY student_id
        ) ea_stats ON ea_stats.student_id = s.id
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
    .certificate-print-area {
        display: flex;
        justify-content: center;
    }
    .certificate-sheet {
        position: relative;
        overflow: hidden;
        width: min(100%, 980px);
        aspect-ratio: 1.414 / 1;
        min-height: 0;
        margin: 0 auto;
        padding: 36px 74px 34px;
        background:
            radial-gradient(circle at 50% 50%, rgba(255, 251, 237, 0.95) 0 42%, rgba(249, 239, 212, 0.88) 100%),
            #fff7df;
        color: #06243a;
        border: 1px solid #f3cf73;
        border-radius: 8px;
        box-shadow: 0 18px 46px rgba(15, 23, 42, .16);
        text-align: center;
        font-family: Georgia, "Times New Roman", serif;
    }
    .certificate-sheet::before,
    .certificate-sheet::after {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        z-index: 0;
    }
    .certificate-sheet::before {
        background:
            linear-gradient(166deg, #06243a 0 8.6%, transparent 8.7% 100%),
            linear-gradient(158deg, transparent 0 9.5%, #f27b0f 9.6% 11.6%, transparent 11.7% 100%),
            linear-gradient(145deg, transparent 0 11.8%, #ffc20a 11.9% 21.2%, transparent 21.3% 100%),
            linear-gradient(42deg, transparent 0 75.5%, #ffc20a 75.6% 83.2%, transparent 83.3% 100%),
            linear-gradient(42deg, transparent 0 80.8%, #06243a 80.9% 89.4%, transparent 89.5% 100%),
            linear-gradient(315deg, #06243a 0 5.8%, transparent 5.9% 100%),
            linear-gradient(58deg, transparent 0 86.4%, #f27b0f 86.5% 88.1%, transparent 88.2% 100%),
            repeating-linear-gradient(54deg, transparent 0 11px, rgba(255, 194, 10, 0.92) 11px 18px, transparent 18px 27px);
        background-size: 100% 100%, 100% 100%, 100% 100%, 100% 100%, 100% 100%, 100% 100%, 100% 100%, 158px 158px;
        background-position: 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, 0 0, right -26px top -16px;
        background-repeat: no-repeat;
    }
    .certificate-sheet::after {
        background:
            radial-gradient(circle at 86% 27%, rgba(6, 36, 58, .28) 0 1px, transparent 1.2px),
            radial-gradient(circle at 16% 55%, rgba(6, 36, 58, .2) 0 1px, transparent 1.2px);
        background-size: 7px 7px, 7px 7px;
        mask-image: radial-gradient(circle at 86% 27%, #000 0 95px, transparent 96px), radial-gradient(circle at 16% 55%, #000 0 128px, transparent 129px);
    }
    .certificate-content {
        position: relative;
        z-index: 1;
    }
    .certificate-logo {
        width: 52px;
        height: 52px;
        object-fit: contain;
        margin-bottom: 4px;
    }
    .certificate-school {
        font-family: Arial, sans-serif;
        font-size: 13px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }
    .certificate-school-subtitle {
        font-family: Arial, sans-serif;
        font-size: 10px;
        color: #526171;
        margin-top: 2px;
    }
    .certificate-title {
        position: relative;
        display: inline-block;
        margin: 12px 0 0;
        padding: 0 72px;
        font-family: Arial, sans-serif;
        font-size: clamp(42px, 6vw, 72px);
        line-height: .95;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0;
        color: #06243a;
    }
    .certificate-title::before,
    .certificate-title::after {
        content: "";
        position: absolute;
        top: 50%;
        width: 58px;
        border-top: 5px solid #06243a;
    }
    .certificate-title::before {
        left: 0;
    }
    .certificate-title::after {
        right: 0;
    }
    .certificate-title span {
        display: block;
        margin-top: 4px;
        font-size: clamp(24px, 3vw, 36px);
        color: #e77817;
    }
    .certificate-name {
        margin: 12px auto 8px;
        padding-bottom: 3px;
        max-width: 650px;
        border-bottom: 2px solid #e77817;
        font-size: clamp(42px, 6.5vw, 70px);
        font-weight: 700;
        font-style: italic;
        color: #06243a;
        line-height: 1.02;
    }
    .certificate-body {
        max-width: 620px;
        margin: 8px auto;
        font-family: Arial, sans-serif;
        font-size: 14px;
        line-height: 1.34;
    }
    .certificate-presented {
        margin-top: 10px;
        font-family: Arial, sans-serif;
        font-size: 13px;
        font-style: italic;
        color: #30475f;
    }
    .certificate-meta {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px 12px;
        max-width: 720px;
        margin: 14px auto 0;
        text-align: center;
        font-family: Arial, sans-serif;
        font-size: 11px;
        color: #27384a;
    }
    .certificate-signatures {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 126px;
        max-width: 640px;
        margin: 54px auto 0;
        font-family: Arial, sans-serif;
        font-size: 13px;
    }
    .certificate-sign-line {
        border-top: 1px solid #06243a;
        padding-top: 9px;
        font-weight: 700;
    }
    .certificate-sign-role {
        display: block;
        margin-top: 2px;
        color: #526171;
        font-weight: 500;
    }
    .certificate-seal {
        position: absolute;
        z-index: 2;
        left: 50%;
        bottom: 38px;
        width: 82px;
        height: 82px;
        transform: translateX(-50%);
        border-radius: 50%;
        background:
            radial-gradient(circle at 38% 32%, #fff2a7 0 10%, #f5c94d 24%, #d89b22 60%, #fff1a4 76%, #b97913 100%);
        box-shadow: 0 2px 0 rgba(110, 72, 12, .22), inset 0 0 0 6px rgba(255, 244, 171, .75), inset 0 0 0 9px rgba(202, 138, 4, .45);
    }
    .certificate-seal::before,
    .certificate-seal::after {
        content: "";
        position: absolute;
        top: 58px;
        width: 24px;
        height: 52px;
        background: linear-gradient(#f6c246, #d99019);
        clip-path: polygon(0 0, 100% 0, 76% 100%, 50% 78%, 24% 100%);
        z-index: -1;
    }
    .certificate-seal::before {
        left: 14px;
        transform: rotate(12deg);
    }
    .certificate-seal::after {
        right: 14px;
        transform: rotate(-12deg);
    }
    @media (max-width: 768px) {
        .certificate-sheet {
            padding: 36px 24px 42px;
        }
        .certificate-title {
            font-size: 36px;
            padding: 0 42px;
        }
        .certificate-title::before,
        .certificate-title::after {
            width: 32px;
            border-top-width: 4px;
        }
        .certificate-title span {
            font-size: 22px;
        }
        .certificate-name {
            font-size: 34px;
        }
        .certificate-meta,
        .certificate-signatures {
            grid-template-columns: 1fr;
        }
    }
    @media print {
        @page {
            size: landscape;
            margin: 10mm;
        }
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
            height: calc(100vh - 20mm);
            box-shadow: none;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
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
                                <h5 class="mb-1">External Student Certificates</h5>
                                <p class="text-muted mb-0">Choose an evaluated external OJT student, then print the CCST certificate.</p>
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
                                        <th>Hours</th>
                                        <th>Evaluation Date</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($eligibleStudents === []): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No evaluated external students available yet.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($eligibleStudents as $row): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo certificate_h($row['student_name'] ?? ''); ?></td>
                                                <td><?php echo certificate_h($row['student_id'] ?? ''); ?></td>
                                                <td><?php echo certificate_h($row['course_name'] ?? '-'); ?></td>
                                                <td><?php echo number_format((float)($row['rendered_hours'] ?? 0), 2); ?> / <?php echo number_format((float)($row['required_hours'] ?? 250), 0); ?></td>
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
                    $requiredHours = (float)($certificate['required_hours'] ?? 250);
                    if ($requiredHours <= 0) {
                        $requiredHours = 250;
                    }
                    $renderedHours = (float)($certificate['approved_hours'] ?? $certificate['rendered_hours'] ?? 0);
                    $dateFrom = certificate_display_date($certificate['first_attendance_date'] ?? $certificate['start_date'] ?? '');
                    $dateTo = certificate_display_date($certificate['last_attendance_date'] ?? $certificate['end_date'] ?? $certificate['evaluation_date'] ?? '');
                    $dateRange = $dateFrom !== '' && $dateTo !== '' ? ($dateFrom . ' to ' . $dateTo) : ($dateFrom . $dateTo);
                    $coordinatorName = certificate_person_name($certificate, 'coordinator');
                    if ($coordinatorName === '') {
                        $coordinatorName = trim((string)($certificate['fallback_coordinator_name'] ?? ''));
                    }
                    $supervisorName = certificate_person_name($certificate, 'supervisor');
                    if ($supervisorName === '') {
                        $supervisorName = trim((string)($certificate['fallback_supervisor_name'] ?? ''));
                    }
                    ?>
                    <div class="d-flex justify-content-end gap-2 mb-3 certificate-no-print">
                        <a class="btn btn-light" href="document_certificate.php">Back</a>
                        <button type="button" class="btn btn-primary" onclick="window.print()">Print Certificate</button>
                    </div>
                    <section class="certificate-print-area">
                        <div class="certificate-sheet">
                            <div class="certificate-content">
                                <img class="certificate-logo" src="assets/images/ccstlogo.png" alt="CCST Logo">
                                <div class="certificate-school">Clark College of Science and Technology (CCST)</div>
                                <div class="certificate-school-subtitle">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
                                <div class="certificate-title">Certificate <span>of Completion</span></div>
                                <div class="certificate-presented">This certificate is presented to</div>
                                <div class="certificate-name"><?php echo certificate_h($studentName); ?></div>
                                <div class="certificate-body">
                                    Congratulations on completing <strong><?php echo number_format($requiredHours, 0); ?> hours</strong>
                                    of external internship training<?php echo $dateRange !== '' ? ' from <strong>' . certificate_h($dateRange) . '</strong>' : ''; ?>.
                                    This achievement is recognized by CCST as part of the required OJT completion for
                                    <strong><?php echo certificate_h((string)($certificate['course_name'] ?? '')); ?></strong>.
                                </div>
                                <div class="certificate-meta">
                                    <div><strong>Student ID:</strong> <?php echo certificate_h($certificate['student_id'] ?? ''); ?></div>
                                    <div><strong>Section:</strong> <?php echo certificate_h($sectionLabel !== '' ? $sectionLabel : '-'); ?></div>
                                    <div><strong>Host Company:</strong> <?php echo certificate_h($certificate['company_name'] ?? '-'); ?></div>
                                    <div><strong>Rendered Hours:</strong> <?php echo number_format($renderedHours, 2); ?></div>
                                    <div><strong>Evaluation Rating:</strong> <?php echo certificate_h((int)($certificate['score'] ?? 0) > 0 ? $rating : '-'); ?></div>
                                    <div><strong>Date Issued:</strong> <?php echo certificate_h(certificate_display_date($certificate['evaluation_date'] ?? date('Y-m-d'))); ?></div>
                                </div>
                                <span class="certificate-seal" aria-hidden="true"></span>
                                <div class="certificate-signatures">
                                    <div>
                                        <div class="certificate-sign-line">
                                            <?php echo certificate_h($coordinatorName !== '' ? $coordinatorName : 'OJT Coordinator'); ?>
                                            <span class="certificate-sign-role">OJT Coordinator</span>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="certificate-sign-line">
                                            <?php echo certificate_h($supervisorName !== '' ? $supervisorName : 'Supervisor'); ?>
                                            <span class="certificate-sign-role">Supervisor</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
