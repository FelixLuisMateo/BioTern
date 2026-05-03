<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/document_access.php';
require_once dirname(__DIR__) . '/lib/company_profiles.php';

biotern_boot_session(isset($conn) ? $conn : null);

if (!function_exists('student_perf_h')) {
    function student_perf_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = (string)$_GET['action'];

    if ($action === 'search_students') {
        $term = trim((string)($_GET['q'] ?? ''));
        $safeTerm = '%' . $term . '%';
        $gateWhere = documents_students_search_gate_sql($conn, 's');
        $sql = "SELECT s.id, s.student_id, s.first_name, s.middle_name, s.last_name
            FROM students s
            WHERE (
                CONCAT(s.first_name, ' ', s.middle_name, ' ', s.last_name) LIKE ?
                OR s.student_id LIKE ?
            )
              AND {$gateWhere}
            ORDER BY s.first_name, s.last_name
            LIMIT 20";
        $stmt = $conn->prepare($sql);
        $results = [];
        if ($stmt) {
            $stmt->bind_param('ss', $safeTerm, $safeTerm);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)($row['first_name'] ?? '') . ' ' . (!empty($row['middle_name']) ? (string)$row['middle_name'] . ' ' : '') . (string)($row['last_name'] ?? ''));
                $studentNo = trim((string)($row['student_id'] ?? ''));
                $results[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => $name,
                    'text' => trim($name . ($studentNo !== '' ? ' - ' . $studentNo : '')),
                ];
            }
            $stmt->close();
        }
        echo json_encode(['results' => $results]);
        exit;
    }

    if ($action === 'search_appraisers') {
        $term = trim((string)($_GET['q'] ?? ''));
        $safeTerm = '%' . $term . '%';
        $results = [];
        $sql = "SELECT id, first_name, middle_name, last_name, email
            FROM supervisors
            WHERE is_active = 1
              AND (
                CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?
                OR email LIKE ?
              )
            ORDER BY first_name, last_name
            LIMIT 20";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('ss', $safeTerm, $safeTerm);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)($row['first_name'] ?? '') . ' ' . (!empty($row['middle_name']) ? (string)$row['middle_name'] . ' ' : '') . (string)($row['last_name'] ?? ''));
                $email = trim((string)($row['email'] ?? ''));
                $results[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => $name,
                    'text' => trim($name . ($email !== '' ? ' - ' . $email : '')),
                ];
            }
            $stmt->close();
        }
        echo json_encode(['results' => $results]);
        exit;
    }

    if ($action === 'search_companies') {
        $term = trim((string)($_GET['q'] ?? ''));
        $results = [];
        foreach (biotern_company_profiles_search($conn, $term, 25) as $company) {
            $companyName = trim((string)($company['company_name'] ?? ''));
            $address = trim((string)($company['company_address'] ?? ''));
            $labelParts = [$companyName];
            if ($address !== '') {
                $labelParts[] = $address;
            }
            $results[] = [
                'id' => (string)($company['key'] ?? $company['company_lookup_key'] ?? $companyName),
                'name' => $companyName,
                'text' => implode(' - ', array_filter($labelParts, static function ($value): bool {
                    return trim((string)$value) !== '';
                })),
            ];
        }
        echo json_encode(['results' => $results]);
        exit;
    }

    echo json_encode(new stdClass());
    exit;
}

$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$assetPrefix = (strpos($scriptName, '/documents/') !== false) ? '../' : '';

$page_title = 'Student Performance Evaluation - Internal';
$base_href = $assetPrefix;
$page_body_class = 'application-builder-page student-performance-internal-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/documents/document-builder-shared.css',
    'assets/css/modules/documents/page-application-document-builder.css',
    'assets/css/modules/documents/template-print-isolation.css',
];

include __DIR__ . '/../includes/header.php';

$factors = [
    ['section' => 'A. Work Performance', 'items' => [
        ['Knowledge of Work (able to grasp as)', '10%'],
        ['Quantity of work (can cope with the work demand of additional unexpected work load in a limited time)', '10%'],
        ['Quality of work (performs as assigned job effectively as possible)', '10%'],
        ['Attendance (follows assigned work schedule)', '10%'],
        ['Punctuality (reports to work assignment time)', '10%'],
    ]],
    ['section' => 'B. Personality Traits', 'items' => [
        ['Physical appearance (personally well-groomed and always wears appropriate dress)', '5%'],
        ['Attitude towards work (always shows enthusiasm and interest)', '5%'],
        ['Courtesy (observe rule and regulation of establishment)', '5%'],
        ['Conduct (observe rule and regulation of establishment)', '5%'],
        ['Perseverance and industriousness (show work over and above what is assigned)', '5%'],
        ['Drive and leadership (initiative and aggressiveness)', '5%'],
        ['Mental maturity (effective and calm under pressure)', '5%'],
        ['Sociability (can work harmoniously with other employees)', '5%'],
        ['Adaptability (adjust to be at ease to use or operate office equipment)', '5%'],
        ['Possession of traits necessary for employment in this kind of work', '5%'],
    ]],
];
?>
<style>
    .student-performance-internal-page .main-content {
        padding-top: 20px;
    }

    .student-performance-internal-page .performance-builder-grid {
        align-items: start;
    }

    .student-performance-internal-page .performance-field {
        margin-top: 13px;
    }

    .student-performance-internal-page .performance-field label {
        display: block;
        color: var(--doc-builder-text);
        font-size: 0.78rem;
        font-weight: 800;
        margin-bottom: 6px;
    }

    .student-performance-internal-page .performance-field input,
    .student-performance-internal-page .performance-field textarea {
        width: 100%;
        border: 1px solid var(--doc-builder-border);
        border-radius: 10px;
        background: var(--doc-builder-input-bg);
        color: var(--doc-builder-text);
        padding: 10px 12px;
        outline: none;
    }

    .student-performance-internal-page .lookup-results {
        display: none;
        margin-top: 6px;
        border: 1px solid rgba(96, 165, 250, 0.35);
        border-radius: 12px;
        background: #0f172a;
        overflow: hidden;
        max-height: 210px;
        overflow-y: auto;
    }

    .student-performance-internal-page .lookup-result {
        width: 100%;
        border: 0;
        border-bottom: 1px solid rgba(148, 163, 184, 0.12);
        background: transparent;
        color: #e5edff;
        text-align: left;
        padding: 9px 11px;
        font-size: 0.86rem;
    }

    .student-performance-internal-page .performance-actions {
        display: flex;
        gap: 8px;
        margin-top: 16px;
    }

    .student-performance-internal-page .performance-actions .btn {
        flex: 1;
        border: 0;
        border-radius: 10px;
        padding: 10px 12px;
        font-weight: 800;
    }

    .student-performance-internal-page .performance-preview-wrap {
        overflow: auto;
        padding: 0;
    }

    .student-performance-internal-page .performance-pages {
        display: flex;
        flex-direction: column;
        gap: 18px;
        align-items: center;
    }

    .student-performance-internal-page .performance-page {
        width: 210mm;
        min-height: 297mm;
        padding: 0.32in 0.55in;
        background: #fff;
        color: #000;
        box-shadow: 0 16px 36px rgba(0, 0, 0, 0.24);
        box-sizing: border-box;
        font-family: Arial, sans-serif;
        font-size: 10pt;
    }

    .performance-doc-header {
        display: grid;
        grid-template-columns: 0.72in minmax(0, 1fr);
        align-items: center;
        border-bottom: 2px solid #111;
        padding-bottom: 8px;
        margin-bottom: 14px;
    }

    .performance-doc-header img {
        width: 58px;
        height: auto;
    }

    .performance-school-copy {
        text-align: center;
        line-height: 1.15;
        padding-right: 0.5in;
    }

    .performance-school-copy strong {
        display: block;
        font-size: 11pt;
        letter-spacing: 0.02em;
    }

    .performance-school-copy span {
        display: block;
        font-size: 7.5pt;
    }

    .performance-title {
        text-align: center;
        font-size: 10pt;
        font-weight: 800;
        line-height: 1.25;
        margin: 4px 0 18px;
        text-transform: uppercase;
    }

    .performance-lines {
        width: 58%;
        margin: 0 0 22px 0.45in;
        display: grid;
        grid-template-columns: 1.45in 1fr;
        gap: 8px 12px;
        font-size: 9pt;
    }

    .performance-line-value {
        min-height: 16px;
        border-bottom: 1px solid #111;
        text-align: center;
        font-weight: 700;
    }

    .performance-scale {
        display: grid;
        grid-template-columns: 1.25in minmax(0, 1fr);
        gap: 4px 12px;
        width: 55%;
        margin: 12px 0 18px 1.05in;
        font-size: 8.5pt;
    }

    .performance-section-title {
        margin: 10px 0 5px;
        font-weight: 800;
        font-size: 9pt;
    }

    .performance-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.8pt;
    }

    .performance-table th,
    .performance-table td {
        border: 1px solid #333;
        padding: 4px 6px;
        vertical-align: top;
    }

    .performance-table th {
        text-align: center;
        font-weight: 800;
    }

    .performance-table .number-cell {
        width: 0.35in;
        text-align: center;
    }

    .performance-table .rating-cell,
    .performance-table .max-cell {
        width: 1.1in;
        text-align: center;
    }

    .performance-recommendation {
        margin-top: 20px;
        font-size: 9pt;
    }

    .performance-recommendation-lines {
        margin-top: 12px;
        width: 74%;
    }

    .performance-recommendation-lines div {
        border-bottom: 1px solid #111;
        height: 20px;
    }

    .performance-signature {
        margin-top: 48px;
        margin-left: auto;
        width: 2.45in;
        text-align: center;
        font-size: 8.5pt;
    }

    .performance-signature::before {
        content: "";
        display: block;
        border-top: 1px solid #111;
        margin-bottom: 5px;
    }

    @media (max-width: 1100px) {
        .student-performance-internal-page .performance-builder-grid {
            grid-template-columns: 1fr;
        }
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 0;
        }

        body.student-performance-internal-page {
            background: #fff !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        body.student-performance-internal-page .nxl-navigation,
        body.student-performance-internal-page .nxl-header,
        body.student-performance-internal-page .page-header,
        body.student-performance-internal-page .performance-card,
        body.student-performance-internal-page .builder-card-head {
            display: none !important;
        }

        body.student-performance-internal-page .nxl-container,
        body.student-performance-internal-page .nxl-content,
        body.student-performance-internal-page .performance-builder-grid,
        body.student-performance-internal-page .performance-preview-wrap,
        body.student-performance-internal-page .performance-pages {
            display: block !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 210mm !important;
            max-width: 210mm !important;
            background: #fff !important;
        }

        body.student-performance-internal-page .performance-page {
            width: 210mm !important;
            min-height: 297mm !important;
            height: 297mm !important;
            margin: 0 !important;
            padding: 0.32in 0.55in !important;
            box-shadow: none !important;
            page-break-after: always !important;
            break-after: page !important;
            overflow: hidden !important;
        }

        body.student-performance-internal-page .performance-page:last-child {
            page-break-after: auto !important;
            break-after: auto !important;
        }
    }
</style>

<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header dashboard-page-header page-header-with-middle">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Student Performance Evaluation</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Documents</a></li>
                    <li class="breadcrumb-item">Internal Evaluation</li>
                </ul>
            </div>
            <div class="page-header-middle">
                <p class="page-header-statement">Prepare and print the internal supervisor evaluation form.</p>
            </div>
            <?php ob_start(); ?>
                <a href="document_application.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>Application</a>
                <a href="document_parent_consent.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>Parent Consent</a>
            <?php
            biotern_render_page_header_actions([
                'menu_id' => 'documentPerformanceActionsMenu',
                'items_html' => ob_get_clean(),
            ]);
            ?>
        </div>

        <div class="application-document-builder doc-page-root performance-document-builder">
            <div class="main-content">
                <div class="application-builder-grid performance-builder-grid">
                    <section class="application-builder-sidebar">
                        <div class="builder-card performance-card">
                            <div class="builder-card-head">
                                <h6>Record Source</h6>
                                <p>Search a student or type the details manually. The print preview updates instantly.</p>
                            </div>

                            <div class="performance-field">
                                <label for="studentSearch">Search Student</label>
                                <input id="studentSearch" type="search" placeholder="Search by name or student id" autocomplete="off">
                                <div id="studentSearchResults" class="lookup-results"></div>
                                <small>Pick a student to fill the name field.</small>
                            </div>

                            <div class="performance-field">
                                <label for="studentNameInput">Name</label>
                                <input id="studentNameInput" data-preview-target="perfStudentName" type="text" placeholder="Student full name">
                            </div>

                            <div class="performance-field">
                                <label for="appraisedByInput">Appraised By</label>
                                <input id="appraisedByInput" data-preview-target="perfAppraisedBy" type="search" placeholder="Search supervisor / evaluator" autocomplete="off">
                                <div id="appraiserSearchResults" class="lookup-results"></div>
                            </div>

                            <div class="performance-field">
                                <label for="periodInput">Period of Appraisal</label>
                                <input id="periodInput" data-preview-target="perfPeriod" type="text" placeholder="e.g. April 1 - May 30, 2026">
                            </div>

                            <div class="performance-field">
                                <label for="companyInput">Company</label>
                                <input id="companyInput" data-preview-target="perfCompany" type="search" placeholder="Search company / office" autocomplete="off">
                                <div id="companySearchResults" class="lookup-results"></div>
                            </div>

                            <div class="performance-actions">
                                <button class="btn btn-light" type="button" id="resetPerformanceForm">Reset</button>
                                <button class="btn btn-success" type="button" id="printPerformanceForm">Print</button>
                            </div>
                        </div>
                    </section>

                    <section class="application-builder-canvas">
                        <div class="builder-card builder-card-editor">
                            <div class="builder-editor-head">
                                <div>
                                    <h6>Template Builder</h6>
                                    <p>Internal evaluation preview and print layout in one place.</p>
                                </div>
                                <div class="builder-editor-actions">
                                    <button class="btn btn-light" type="button" id="resetPerformanceFormTop">Reset</button>
                                    <button class="btn btn-success" type="button" id="printPerformanceFormTop">Print Evaluation</button>
                                </div>
                            </div>
                            <div class="builder-status-bar">
                                <span class="builder-status-text">Preview ready.</span>
                            </div>
                            <div class="doc-preview performance-preview-wrap">
                                <div class="performance-pages" id="performancePages">
                    <article class="performance-page">
                        <header class="performance-doc-header">
                            <img src="assets/images/ccstlogo.png" alt="CCST Logo">
                            <div class="performance-school-copy">
                                <strong>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</strong>
                                <span>Formerly Clark International College of Science &amp; Technology</span>
                                <span>SNS Bldg., Aurea St., Samsonville Subd., Dau, Mabalacat City, Pampanga</span>
                                <span>Telefax No.: (045) 624-0215</span>
                            </div>
                        </header>

                        <div class="performance-title">
                            Student's Performance Evaluation<br>
                            Internal
                        </div>

                        <div class="performance-lines">
                            <span>Name</span><span class="performance-line-value" id="perfStudentName">&nbsp;</span>
                            <span>Appraised By</span><span class="performance-line-value" id="perfAppraisedBy">&nbsp;</span>
                            <span>Period of Appraisal</span><span class="performance-line-value" id="perfPeriod">&nbsp;</span>
                            <span>Company</span><span class="performance-line-value" id="perfCompany">&nbsp;</span>
                        </div>

                        <p>The Purpose of this evaluation is to provide an objective measure of student's performance during the Supervised Field Training.</p>

                        <p>Rating Scale Equivalent:</p>
                        <div class="performance-scale">
                            <span>80 - 100</span><span>Very Good Performance</span>
                            <span>70 - 79</span><span>Good Performance</span>
                            <span>57 - 69</span><span>Satisfactory Performance</span>
                            <span>45 - 56</span><span>Unsatisfactory Performance</span>
                        </div>

                        <div class="performance-section-title">Part I: Ability and Application</div>
                        <table class="performance-table">
                            <thead>
                                <tr>
                                    <th colspan="2">Job Factors</th>
                                    <th class="max-cell">Maximum Rating<br>To Be Given</th>
                                    <th class="rating-cell">Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="4"><strong>A. Work Performance</strong></td></tr>
                                <?php foreach ($factors[0]['items'] as $index => $item): ?>
                                    <tr>
                                        <td class="number-cell"><?php echo $index + 1; ?>.</td>
                                        <td><?php echo student_perf_h($item[0]); ?></td>
                                        <td class="max-cell"><?php echo student_perf_h($item[1]); ?></td>
                                        <td class="rating-cell">&nbsp;</td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr><td colspan="4"><strong>B. Personality Traits</strong></td></tr>
                                <?php foreach (array_slice($factors[1]['items'], 0, 2) as $index => $item): ?>
                                    <tr>
                                        <td class="number-cell"><?php echo $index + 1; ?>.</td>
                                        <td><?php echo student_perf_h($item[0]); ?></td>
                                        <td class="max-cell"><?php echo student_perf_h($item[1]); ?></td>
                                        <td class="rating-cell">&nbsp;</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </article>

                    <article class="performance-page">
                        <header class="performance-doc-header">
                            <img src="assets/images/ccstlogo.png" alt="CCST Logo">
                            <div class="performance-school-copy">
                                <strong>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</strong>
                                <span>Formerly Clark International College of Science &amp; Technology</span>
                                <span>SNS Bldg., Aurea St., Samsonville Subd., Dau, Mabalacat City, Pampanga</span>
                                <span>Telefax No.: (045) 624-0215</span>
                            </div>
                        </header>

                        <table class="performance-table">
                            <tbody>
                                <?php foreach (array_slice($factors[1]['items'], 2) as $index => $item): ?>
                                    <tr>
                                        <td class="number-cell"><?php echo $index + 3; ?>.</td>
                                        <td><?php echo student_perf_h($item[0]); ?></td>
                                        <td class="max-cell"><?php echo student_perf_h($item[1]); ?></td>
                                        <td class="rating-cell">&nbsp;</td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <td colspan="2"><strong>Total Rating</strong></td>
                                    <td class="max-cell"><strong>100%</strong></td>
                                    <td class="rating-cell">&nbsp;</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="performance-recommendation">
                            Recommendation for the trainee's Further Growth:
                            <div class="performance-recommendation-lines">
                                <div></div>
                                <div></div>
                                <div></div>
                                <div></div>
                            </div>
                        </div>

                        <div class="performance-signature">Trainee's Supervisor Signature</div>
                    </article>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
(function () {
    var endpoint = new URL('document_student_performance_internal.php', window.location.href).href;
    var studentSearchInput = document.getElementById('studentSearch');
    var appraiserInput = document.getElementById('appraisedByInput');
    var companyInput = document.getElementById('companyInput');
    var fields = Array.prototype.slice.call(document.querySelectorAll('[data-preview-target]'));
    var resetButton = document.getElementById('resetPerformanceForm');
    var resetButtonTop = document.getElementById('resetPerformanceFormTop');
    var printButton = document.getElementById('printPerformanceForm');
    var printButtonTop = document.getElementById('printPerformanceFormTop');

    function setPreviewValue(targetId, value) {
        var target = document.getElementById(targetId);
        if (!target) {
            return;
        }
        target.textContent = value && String(value).trim() !== '' ? value : '\u00A0';
    }

    fields.forEach(function (field) {
        field.addEventListener('input', function () {
            setPreviewValue(field.getAttribute('data-preview-target'), field.value);
        });
    });

    function setupLookup(config) {
        var input = document.getElementById(config.inputId);
        var resultsBox = document.getElementById(config.resultsId);
        var searchTimer = null;
        if (!input || !resultsBox) {
            return;
        }

        function hideResults() {
            resultsBox.style.display = 'none';
            resultsBox.innerHTML = '';
        }

        function renderResults(results) {
            resultsBox.innerHTML = '';
            if (!results.length) {
                hideResults();
                return;
            }
            results.forEach(function (item) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'lookup-result';
                button.textContent = item.text || item.name || '';
                button.addEventListener('click', function () {
                    config.onSelect(item);
                    hideResults();
                });
                resultsBox.appendChild(button);
            });
            resultsBox.style.display = 'block';
        }

        input.addEventListener('input', function () {
            clearTimeout(searchTimer);
            var q = input.value.trim();
            if (q.length < 2) {
                hideResults();
                return;
            }
            searchTimer = setTimeout(function () {
                fetch(endpoint + '?action=' + encodeURIComponent(config.action) + '&q=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) { renderResults(data.results || []); })
                    .catch(hideResults);
            }, 180);
        });

        input.addEventListener('blur', function () {
            setTimeout(hideResults, 180);
        });

        input.addEventListener('focus', function () {
            if (resultsBox.children.length > 0) {
                resultsBox.style.display = 'block';
            }
        });
    }

    setupLookup({
        inputId: 'studentSearch',
        resultsId: 'studentSearchResults',
        action: 'search_students',
        onSelect: function (item) {
            var nameField = document.getElementById('studentNameInput');
            if (nameField) {
                nameField.value = item.name || '';
                setPreviewValue('perfStudentName', nameField.value);
            }
            if (studentSearchInput) {
                studentSearchInput.value = item.text || item.name || '';
            }
        }
    });

    setupLookup({
        inputId: 'appraisedByInput',
        resultsId: 'appraiserSearchResults',
        action: 'search_appraisers',
        onSelect: function (item) {
            if (appraiserInput) {
                appraiserInput.value = item.name || item.text || '';
                setPreviewValue('perfAppraisedBy', appraiserInput.value);
            }
        }
    });

    setupLookup({
        inputId: 'companyInput',
        resultsId: 'companySearchResults',
        action: 'search_companies',
        onSelect: function (item) {
            if (companyInput) {
                companyInput.value = item.name || item.text || '';
                setPreviewValue('perfCompany', companyInput.value);
            }
        }
    });

    document.addEventListener('click', function (event) {
        Array.prototype.slice.call(document.querySelectorAll('.lookup-results')).forEach(function (box) {
            if (!box.parentNode || box.parentNode.contains(event.target)) {
                return;
            }
            box.style.display = 'none';
        });
    });

    function resetLookupBoxes() {
        Array.prototype.slice.call(document.querySelectorAll('.lookup-results')).forEach(function (box) {
            box.style.display = 'none';
            box.innerHTML = '';
        });
    }

    function resetForm() {
            fields.forEach(function (field) {
                field.value = '';
                setPreviewValue(field.getAttribute('data-preview-target'), '');
            });
            if (studentSearchInput) {
                studentSearchInput.value = '';
            }
            resetLookupBoxes();
    }

    if (resetButton) {
        resetButton.addEventListener('click', resetForm);
    }

    if (resetButtonTop) {
        resetButtonTop.addEventListener('click', resetForm);
    }

    function printForm() {
            window.print();
    }

    if (printButton) {
        printButton.addEventListener('click', printForm);
    }

    if (printButtonTop) {
        printButtonTop.addEventListener('click', printForm);
    }
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
