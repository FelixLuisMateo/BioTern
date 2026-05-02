<?php
require_once dirname(__DIR__) . '/config/db.php';
/** @var mysqli $conn */
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$currentRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($currentRole !== 'admin') {
    header('Location: homepage.php');
    exit;
}

function rr_esc($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function rr_table_exists(mysqli $conn, string $table): bool
{
    $safe = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function rr_rows(mysqli $conn, string $sql): array
{
    $rows = [];
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function rr_count(mysqli $conn, string $sql): int
{
    $res = $conn->query($sql);
    if ($res instanceof mysqli_result) {
        $row = $res->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
    return 0;
}

$requiredReportKey = isset($requiredReportKey) ? (string)$requiredReportKey : '';

$reports = [
    'student-status' => [
        'title' => 'Student Status Report',
        'statement' => 'Ongoing, finished, and not started OJT student status.',
        'columns' => ['Student No', 'Student', 'Course', 'Status'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Ongoing', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE status = 'ongoing'")],
                ['label' => 'Finished', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE status IN ('completed', 'finished')")],
                ['label' => 'Not Started', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM students s LEFT JOIN internships i ON i.student_id = s.id WHERE i.id IS NULL")],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_rows($conn, "SELECT COALESCE(s.student_id, '') AS student_no, TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, COALESCE(c.name, '-') AS course, COALESCE(i.status, 'Not Started') AS status FROM students s LEFT JOIN courses c ON c.id = s.course_id LEFT JOIN internships i ON i.id = (SELECT i2.id FROM internships i2 WHERE i2.student_id = s.id ORDER BY i2.id DESC LIMIT 1) ORDER BY status, student LIMIT 500");
        },
    ],
    'attendance-dtr' => [
        'title' => 'Attendance Report (DTR)',
        'statement' => 'Internal biometric attendance and external verified/manual attendance.',
        'columns' => ['Type', 'Student', 'Date', 'Hours', 'Status'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Internal Rows', 'value' => rr_table_exists($conn, 'attendances') ? rr_count($conn, 'SELECT COUNT(*) AS total FROM attendances') : 0],
                ['label' => 'External Rows', 'value' => rr_table_exists($conn, 'external_attendance') ? rr_count($conn, 'SELECT COUNT(*) AS total FROM external_attendance') : 0],
                ['label' => 'Pending External', 'value' => rr_table_exists($conn, 'external_attendance') ? rr_count($conn, "SELECT COUNT(*) AS total FROM external_attendance WHERE status = 'pending'") : 0],
            ];
        },
        'rows' => function (mysqli $conn): array {
            $rows = [];
            if (rr_table_exists($conn, 'attendances')) {
                $rows = array_merge($rows, rr_rows($conn, "SELECT 'Internal' AS type, TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, COALESCE(a.attendance_date, DATE(a.created_at)) AS report_date, COALESCE(a.total_hours, '') AS hours, COALESCE(a.status, '-') AS status FROM attendances a LEFT JOIN students s ON s.id = a.student_id ORDER BY a.id DESC LIMIT 250"));
            }
            if (rr_table_exists($conn, 'external_attendance')) {
                $rows = array_merge($rows, rr_rows($conn, "SELECT 'External' AS type, TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, ea.attendance_date AS report_date, ea.total_hours AS hours, COALESCE(ea.status, '-') AS status FROM external_attendance ea LEFT JOIN students s ON s.id = ea.student_id ORDER BY ea.id DESC LIMIT 250"));
            }
            return $rows;
        },
    ],
    'hours-completion' => [
        'title' => 'Hours Completion Report',
        'statement' => 'Rendered hours compared with required OJT hours.',
        'columns' => ['Student', 'Type', 'Rendered', 'Required', 'Progress'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Programs', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM internships')],
                ['label' => 'Completed', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE completion_percentage >= 100 OR status IN ('completed', 'finished')")],
                ['label' => 'In Progress', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE status = 'ongoing'")],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_rows($conn, "SELECT TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, i.type, i.rendered_hours, i.required_hours, CONCAT(ROUND(i.completion_percentage, 2), '%') AS progress FROM internships i LEFT JOIN students s ON s.id = i.student_id ORDER BY i.completion_percentage DESC, student LIMIT 500");
        },
    ],
    'section' => [
        'title' => 'Section Report',
        'statement' => 'Students grouped by section and status.',
        'columns' => ['Section ID', 'Total Students', 'Ongoing', 'Finished', 'Not Started'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Students', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM students')],
                ['label' => 'Sections', 'value' => rr_count($conn, 'SELECT COUNT(DISTINCT section_id) AS total FROM students')],
                ['label' => 'With OJT', 'value' => rr_count($conn, 'SELECT COUNT(DISTINCT student_id) AS total FROM internships')],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_rows($conn, "SELECT COALESCE(s.section_id, 0) AS section_id, COUNT(*) AS total_students, SUM(CASE WHEN i.status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing, SUM(CASE WHEN i.status IN ('completed', 'finished') THEN 1 ELSE 0 END) AS finished, SUM(CASE WHEN i.id IS NULL THEN 1 ELSE 0 END) AS not_started FROM students s LEFT JOIN internships i ON i.id = (SELECT i2.id FROM internships i2 WHERE i2.student_id = s.id ORDER BY i2.id DESC LIMIT 1) GROUP BY s.section_id ORDER BY s.section_id LIMIT 500");
        },
    ],
    'department' => [
        'title' => 'Department Report',
        'statement' => 'Students per department or office with assigned supervisors.',
        'columns' => ['Department', 'Students', 'Supervisors', 'OJT Programs'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Departments', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM departments')],
                ['label' => 'Students', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM students')],
                ['label' => 'Supervisors', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM supervisors')],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_rows($conn, "SELECT COALESCE(d.name, CONCAT('Department ', s.department_id)) AS department, COUNT(DISTINCT s.id) AS students, COUNT(DISTINCT sv.id) AS supervisors, COUNT(DISTINCT i.id) AS ojt_programs FROM students s LEFT JOIN departments d ON d.id = s.department_id LEFT JOIN supervisors sv ON sv.department_id = d.id LEFT JOIN internships i ON i.student_id = s.id GROUP BY s.department_id, d.name ORDER BY department LIMIT 500");
        },
    ],
    'company' => [
        'title' => 'Company Report (External OJT)',
        'statement' => 'External OJT companies and assigned or past trainees.',
        'columns' => ['Company', 'Trainees', 'Ongoing', 'Completed'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Companies', 'value' => rr_count($conn, "SELECT COUNT(DISTINCT company_name) AS total FROM internships WHERE type = 'external' AND company_name IS NOT NULL AND company_name <> ''")],
                ['label' => 'External Trainees', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE type = 'external'")],
                ['label' => 'Completed', 'value' => rr_count($conn, "SELECT COUNT(*) AS total FROM internships WHERE type = 'external' AND status IN ('completed', 'finished')")],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_rows($conn, "SELECT COALESCE(NULLIF(company_name, ''), 'No Company') AS company, COUNT(*) AS trainees, SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) AS ongoing, SUM(CASE WHEN status IN ('completed', 'finished') THEN 1 ELSE 0 END) AS completed FROM internships WHERE type = 'external' GROUP BY COALESCE(NULLIF(company_name, ''), 'No Company') ORDER BY company LIMIT 500");
        },
    ],
    'evaluation' => [
        'title' => 'Evaluation Report',
        'statement' => 'Internal and external student evaluation results.',
        'columns' => ['Student', 'Evaluator', 'Date', 'Score', 'Feedback'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Evaluations', 'value' => rr_table_exists($conn, 'evaluations') ? rr_count($conn, 'SELECT COUNT(*) AS total FROM evaluations WHERE deleted_at IS NULL') : 0],
                ['label' => 'Average Score', 'value' => rr_table_exists($conn, 'evaluations') ? rr_count($conn, 'SELECT ROUND(AVG(score), 0) AS total FROM evaluations WHERE deleted_at IS NULL') : 0],
                ['label' => 'Students Rated', 'value' => rr_table_exists($conn, 'evaluations') ? rr_count($conn, 'SELECT COUNT(DISTINCT student_id) AS total FROM evaluations WHERE deleted_at IS NULL') : 0],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_table_exists($conn, 'evaluations') ? rr_rows($conn, "SELECT TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, COALESCE(e.evaluator_name, '-') AS evaluator, e.evaluation_date, COALESCE(e.score, '-') AS score, COALESCE(e.feedback, '') AS feedback FROM evaluations e LEFT JOIN students s ON s.id = e.student_id WHERE e.deleted_at IS NULL ORDER BY e.evaluation_date DESC, e.id DESC LIMIT 500") : [];
        },
    ],
    'unassigned-students' => [
        'title' => 'Unassigned Students Report',
        'statement' => 'Registered students not yet assigned to OJT.',
        'columns' => ['Student No', 'Student', 'Course', 'Section ID'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Unassigned', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM students s LEFT JOIN internships i ON i.student_id = s.id WHERE i.id IS NULL')],
                ['label' => 'Total Students', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM students')],
                ['label' => 'Assigned', 'value' => rr_count($conn, 'SELECT COUNT(DISTINCT student_id) AS total FROM internships')],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_rows($conn, "SELECT COALESCE(s.student_id, '') AS student_no, TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))) AS student, COALESCE(c.name, '-') AS course, COALESCE(s.section_id, 0) AS section_id FROM students s LEFT JOIN courses c ON c.id = s.course_id LEFT JOIN internships i ON i.student_id = s.id WHERE i.id IS NULL ORDER BY student LIMIT 500");
        },
    ],
    'import-errors' => [
        'title' => 'Duplicate/Import Error Report',
        'statement' => 'Duplicate student numbers and import warning checks.',
        'columns' => ['Issue', 'Student No', 'Count', 'Details'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Duplicate IDs', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM (SELECT student_id FROM students GROUP BY student_id HAVING COUNT(*) > 1) d')],
                ['label' => 'Students', 'value' => rr_count($conn, 'SELECT COUNT(*) AS total FROM students')],
                ['label' => 'Imports', 'value' => rr_table_exists($conn, 'ojt_masterlist') ? rr_count($conn, 'SELECT COUNT(*) AS total FROM ojt_masterlist') : 0],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_rows($conn, "SELECT 'Duplicate Student Number' AS issue, COALESCE(student_id, '') AS student_no, COUNT(*) AS duplicate_count, GROUP_CONCAT(TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) SEPARATOR ', ') AS details FROM students GROUP BY student_id HAVING COUNT(*) > 1 ORDER BY duplicate_count DESC LIMIT 500");
        },
    ],
    'document' => [
        'title' => 'Document Report',
        'statement' => 'Generated documents per student and completion status.',
        'columns' => ['Student/User', 'Document Type', 'Status', 'Approved By', 'Updated'],
        'summary' => function (mysqli $conn): array {
            return [
                ['label' => 'Documents', 'value' => rr_table_exists($conn, 'document_workflow') ? rr_count($conn, 'SELECT COUNT(*) AS total FROM document_workflow') : 0],
                ['label' => 'Approved', 'value' => rr_table_exists($conn, 'document_workflow') ? rr_count($conn, "SELECT COUNT(*) AS total FROM document_workflow WHERE status = 'approved'") : 0],
                ['label' => 'Pending/Draft', 'value' => rr_table_exists($conn, 'document_workflow') ? rr_count($conn, "SELECT COUNT(*) AS total FROM document_workflow WHERE status <> 'approved'") : 0],
            ];
        },
        'rows' => function (mysqli $conn): array {
            return rr_table_exists($conn, 'document_workflow') ? rr_rows($conn, "SELECT COALESCE(u.name, u.username, CONCAT('User ', dw.user_id)) AS user_name, dw.doc_type, dw.status, COALESCE(approver.name, '-') AS approved_by_name, dw.updated_at FROM document_workflow dw LEFT JOIN users u ON u.id = dw.user_id LEFT JOIN users approver ON approver.id = dw.approved_by ORDER BY dw.updated_at DESC, dw.id DESC LIMIT 500") : [];
        },
    ],
];

if (!isset($reports[$requiredReportKey])) {
    http_response_code(404);
    exit('Report not found');
}

$report = $reports[$requiredReportKey];
$summary = is_callable($report['summary'] ?? null) ? $report['summary']($conn) : [];
$rows = is_callable($report['rows'] ?? null) ? $report['rows']($conn) : [];
$columns = (array)($report['columns'] ?? []);

$page_body_class = trim(($page_body_class ?? '') . ' reports-page required-report-page');
$page_styles = array_merge($page_styles ?? [], ['assets/css/modules/reports/reports-shell.css', 'assets/css/modules/reports/reports-required-page.css']);
$page_title = 'BioTern || ' . (string)$report['title'];
include 'includes/header.php';
?>
<main class="nxl-container">
<div class="nxl-content">
    <div class="page-header page-header-with-middle">
        <div class="page-header-left d-flex align-items-center">
            <div class="page-header-title logs-page-title"><h5 class="m-b-10"><?php echo rr_esc($report['title']); ?></h5></div>
            <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php">Reports</a></li>
                <li class="breadcrumb-item"><?php echo rr_esc($report['title']); ?></li>
            </ul>
        </div>
        <div class="page-header-middle">
            <p class="page-header-statement"><?php echo rr_esc($report['statement']); ?></p>
        </div>
        <?php ob_start(); ?>
            <a href="reports-admin-logs.php" class="btn btn-outline-primary"><i class="feather-shield me-1"></i>Admin Logs</a>
            <button type="button" class="btn btn-light-brand" onclick="window.print();"><i class="feather-printer me-1"></i>Print</button>
        <?php biotern_render_page_header_actions(['menu_id' => 'requiredReportActionsMenu', 'items_html' => ob_get_clean()]); ?>
    </div>

    <div class="main-content pb-5">
        <div class="required-report-summary">
            <?php foreach ($summary as $card): ?>
                <div class="required-report-kpi">
                    <div class="required-report-kpi-label"><?php echo rr_esc($card['label'] ?? 'Total'); ?></div>
                    <div class="required-report-kpi-value"><?php echo rr_esc($card['value'] ?? 0); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="required-report-table-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <th><?php echo rr_esc($column); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php foreach (array_keys($row) as $key): ?>
                                        <td><?php echo rr_esc($row[$key]); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="<?php echo max(1, count($columns)); ?>" class="text-center text-muted py-5">No records found for this report.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>
<?php include 'includes/footer.php'; ?>
