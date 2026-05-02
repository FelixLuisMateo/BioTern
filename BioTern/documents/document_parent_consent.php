<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
require_once dirname(__DIR__) . '/lib/document_access.php';
require_once dirname(__DIR__) . '/lib/company_profiles.php';

biotern_boot_session(isset($conn) ? $conn : null);

if (!function_exists('parent_consent_h')) {
    function parent_consent_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('parent_consent_q')) {
    function parent_consent_q(string $key, string $fallback = ''): string
    {
        return trim((string)($_GET[$key] ?? $fallback));
    }
}

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$isStudentViewOnly = ($currentRole === 'student');
$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($isStudentViewOnly && $currentUserId > 0) {
    $studentLookupStmt = $conn->prepare('SELECT id FROM students WHERE user_id = ? LIMIT 1');
    if ($studentLookupStmt) {
        $studentLookupStmt->bind_param('i', $currentUserId);
        $studentLookupStmt->execute();
        $studentLookupRow = $studentLookupStmt->get_result()->fetch_assoc() ?: null;
        $studentLookupStmt->close();
        if ($studentLookupRow) {
            $studentId = (int)($studentLookupRow['id'] ?? 0);
        }
    }
}

$student = null;
if ($studentId > 0) {
    $access = $isStudentViewOnly ? ['allowed' => true] : documents_student_can_generate($conn, $studentId);
    if (!empty($access['allowed'])) {
        $stmt = $conn->prepare("SELECT s.*, c.name AS course_name FROM students s LEFT JOIN courses c ON c.id = s.course_id WHERE s.id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $studentId);
            $stmt->execute();
            $student = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }
    }
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = (string)$_GET['action'];

    if ($action === 'search_students') {
        $term = trim((string)($_GET['q'] ?? ''));
        $safeTerm = '%' . $term . '%';
        $gateWhere = documents_students_search_gate_sql($conn, 's');
        $sql = "SELECT s.id, s.first_name, s.middle_name, s.last_name, s.student_id, s.emergency_contact
            FROM students s
            WHERE (
                CONCAT(s.first_name, ' ', s.middle_name, ' ', s.last_name) LIKE ?
                OR s.student_id LIKE ?
            )
              AND {$gateWhere}
            ORDER BY s.first_name, s.last_name
            LIMIT 30";
        $stmt = $conn->prepare($sql);
        $results = [];
        if ($stmt) {
            $stmt->bind_param('ss', $safeTerm, $safeTerm);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)($row['first_name'] ?? '') . ' ' . (!empty($row['middle_name']) ? (string)$row['middle_name'] . ' ' : '') . (string)($row['last_name'] ?? ''));
                $studentNo = trim((string)($row['student_id'] ?? ''));
                $parentContact = trim((string)($row['emergency_contact'] ?? ''));
                $parentName = trim((string)preg_replace('/\s*\([^)]*\)\s*$/', '', $parentContact));
                $results[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => $name,
                    'parent_name' => $parentName,
                    'text' => trim($name . ($studentNo !== '' ? ' - ' . $studentNo : '')),
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
            $labelParts = [trim((string)($company['company_name'] ?? ''))];
            if (trim((string)($company['company_address'] ?? '')) !== '') {
                $labelParts[] = trim((string)($company['company_address'] ?? ''));
            }
            $results[] = [
                'id' => (string)($company['key'] ?? $company['company_lookup_key'] ?? $company['company_name'] ?? ''),
                'name' => trim((string)($company['company_name'] ?? '')),
                'address' => trim((string)($company['company_address'] ?? '')),
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

$studentName = parent_consent_q('student_name');
if ($studentName === '' && $student) {
    $studentName = trim((string)(($student['first_name'] ?? '') . ' ' . (!empty($student['middle_name']) ? ($student['middle_name'] . ' ') : '') . ($student['last_name'] ?? '')));
}

$parentName = parent_consent_q('parent_name');
if ($parentName === '' && $student) {
    $parentContact = trim((string)($student['emergency_contact'] ?? ''));
    $parentName = trim((string)preg_replace('/\s*\([^)]*\)\s*$/', '', $parentContact));
}
$companyName = parent_consent_q('company_name');
$printDate = parent_consent_q('date', date('F j, Y'));

$studentLine = $studentName !== '' ? $studentName : 'my son/daughter';
$parentLine = $parentName !== '' ? $parentName : '';
$companyClause = $companyName !== '' ? ' (' . $companyName . ')' : '';

$page_title = 'Parent Consent';
$base_href = '../';
$page_body_class = 'application-builder-page application-document-builder-page parent-consent-builder-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/documents/document-builder-shared.css',
    'assets/css/modules/documents/page-application-document-builder.css',
    'assets/css/modules/documents/template-print-isolation.css',
];

include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header dashboard-page-header page-header-with-middle">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Parent Consent</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Documents</a></li>
                    <li class="breadcrumb-item">Parent Consent</li>
                </ul>
            </div>
            <div class="page-header-middle">
                <p class="page-header-statement">Fill consent details, preview the waiver, and print a clean parent-ready copy.</p>
            </div>
            <?php ob_start(); ?>
                <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
                <a href="document_application.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>Application Letter</a>
                <a href="document_moa.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>MOA</a>
            <?php
            biotern_render_page_header_actions([
                'menu_id' => 'documentParentConsentActionsMenu',
                'items_html' => ob_get_clean(),
            ]);
            ?>
        </div>

        <div class="application-document-builder parent-consent-document-builder">
            <style>
                .parent-consent-builder-page .builder-paper {
                    background: #111317;
                }

                .parent-consent-builder-page #editor {
                    padding: 0;
                }

                .parent-consent-builder-page #editor .a4-page {
                    padding: 0.36in 0.55in 0.55in;
                    font-family: Arial, Helvetica, sans-serif !important;
                    font-size: 11px;
                    line-height: 1.28;
                    color: #111827;
                }

                .parent-consent-builder-page #editor .parent-consent-header {
                    display: grid;
                    grid-template-columns: 72px 1fr 72px;
                    align-items: center;
                    gap: 8px;
                    border-bottom: 1px solid #1f2937;
                    padding-bottom: 7px;
                    margin-bottom: 20px;
                }

                .parent-consent-builder-page #editor .parent-consent-header img {
                    width: 58px !important;
                    height: auto !important;
                    justify-self: center;
                }

                .parent-consent-builder-page #editor .parent-consent-school {
                    text-align: center;
                    color: #1e40af !important;
                    line-height: 1.18;
                    font-size: 9px;
                    font-weight: 600;
                }

                .parent-consent-builder-page #editor .parent-consent-school strong {
                    display: block;
                    font-size: 11px;
                    letter-spacing: 0.02em;
                }

                .parent-consent-builder-page #editor .parent-consent-content {
                    max-width: 6.65in;
                    margin: 0 auto;
                }

                .parent-consent-builder-page #editor .parent-consent-title {
                    text-align: center;
                    font-size: 11px;
                    margin: 0 0 24px;
                    font-weight: 700;
                    text-transform: uppercase;
                }

                .parent-consent-builder-page #editor .parent-consent-content p {
                    margin: 0 0 10px;
                    font-size: 11px !important;
                    line-height: 1.28 !important;
                    color: #111827 !important;
                }

                .parent-consent-builder-page #editor .parent-consent-sign-student {
                    width: 3.35in;
                    margin-top: 50px;
                }

                .parent-consent-builder-page #editor .parent-consent-sign-grid {
                    margin-top: 42px;
                    display: grid;
                    grid-template-columns: 1fr 1.1in;
                    gap: 34px;
                    align-items: end;
                }

                .parent-consent-builder-page #editor .parent-consent-sign-line,
                .parent-consent-builder-page #editor .parent-consent-sign-date {
                    border-top: 1px solid #1f2937;
                    padding-top: 6px;
                    font-size: 9px;
                    font-weight: 700;
                }

                .parent-consent-builder-page #editor .parent-consent-sign-name {
                    display: block;
                    min-height: 12px;
                    margin-bottom: 2px;
                    font-size: 10px;
                    font-weight: 700;
                    text-align: center;
                }

                .parent-consent-builder-page #editor .parent-consent-sign-label {
                    display: block;
                    text-align: left;
                }

                .parent-consent-builder-page #editor .parent-consent-sign-date {
                    text-align: center;
                }

                .parent-consent-builder-page .parent-consent-helper-list {
                    padding-left: 18px;
                    margin: 12px 0 0;
                    color: var(--doc-builder-muted);
                    font-size: 0.82rem;
                }

                .parent-consent-search {
                    position: relative;
                }

                .parent-consent-search-control {
                    position: relative;
                }

                .parent-consent-search-control .form-control {
                    padding-right: 42px;
                }

                .parent-consent-search-toggle {
                    position: absolute;
                    right: 7px;
                    top: 50%;
                    transform: translateY(-50%);
                    width: 30px;
                    height: 30px;
                    border: 0;
                    border-radius: 9px;
                    background: var(--doc-builder-control-bg);
                    color: var(--doc-builder-muted);
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                }

                .parent-consent-search-toggle:hover,
                .parent-consent-search-toggle:focus {
                    background: var(--doc-builder-control-hover);
                    color: var(--doc-builder-text);
                    outline: none;
                }

                .parent-consent-search-panel {
                    display: none;
                    position: absolute;
                    z-index: 30;
                    top: calc(100% + 6px);
                    left: 0;
                    right: 0;
                    max-height: 260px;
                    overflow: auto;
                    padding: 8px;
                    border: 1px solid var(--doc-builder-border);
                    border-radius: 12px;
                    background: var(--doc-builder-card-bg);
                    box-shadow: 0 18px 36px rgba(0, 0, 0, 0.22);
                }

                .parent-consent-search-panel.is-open {
                    display: block;
                }

                .parent-consent-search-message {
                    padding: 8px 10px;
                    color: var(--doc-builder-muted);
                    font-size: 12px;
                }

                .parent-consent-search-option {
                    display: block;
                    width: 100%;
                    border: 0;
                    border-radius: 9px;
                    background: transparent;
                    color: var(--doc-builder-text);
                    padding: 9px 10px;
                    text-align: left;
                    font-weight: 700;
                }

                .parent-consent-search-option:hover,
                .parent-consent-search-option:focus {
                    background: var(--doc-builder-control-hover);
                    outline: none;
                }

                @media print {
                    body.parent-consent-builder-page #editor .a4-page {
                        padding: 0.36in 0.55in 0.55in !important;
                        min-height: 11in !important;
                    }
                }
            </style>

            <div class="main-content">
                <form class="application-builder-grid" method="get" id="parentConsentForm">
                    <input type="hidden" name="id" id="parentConsentStudentId" value="<?php echo $studentId > 0 ? (int)$studentId : ''; ?>">

                    <section class="application-builder-sidebar">
                        <div class="builder-card">
                            <div class="builder-card-head">
                                <h6>Record Source</h6>
                                <p><?php echo $isStudentViewOnly ? 'Your student name is loaded from your account. Add parent details before printing.' : 'Fill the consent fields and the print preview updates instantly.'; ?></p>
                            </div>

                            <div class="builder-field">
                                <label for="student_name" class="form-label">Student Name</label>
                                <div class="parent-consent-search" data-parent-consent-student-search>
                                    <div class="parent-consent-search-control">
                                        <input id="student_name" class="form-control" type="text" name="student_name" value="<?php echo parent_consent_h($studentName); ?>" placeholder="Search student name or ID" data-preview-target="pcStudent" autocomplete="off" <?php echo $isStudentViewOnly ? 'readonly' : ''; ?>>
                                        <?php if (!$isStudentViewOnly): ?>
                                            <button class="parent-consent-search-toggle" type="button" aria-label="Open student search"><i class="feather-chevron-down"></i></button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!$isStudentViewOnly): ?>
                                        <div class="parent-consent-search-panel">
                                            <div class="parent-consent-search-message">Type at least 1 character.</div>
                                            <div class="parent-consent-search-results"></div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="builder-field">
                                <label for="parent_name" class="form-label">Parent / Guardian</label>
                                <input id="parent_name" class="form-control" type="text" name="parent_name" value="<?php echo parent_consent_h($parentName); ?>" placeholder="Parent or guardian full name" data-preview-target="pcParent">
                            </div>

                            <div class="builder-field">
                                <label for="company_name" class="form-label">Company / Training Site</label>
                                <div class="parent-consent-search" data-parent-consent-company-search>
                                    <input id="company_name" type="hidden" name="company_name" value="<?php echo parent_consent_h($companyName); ?>">
                                    <div class="parent-consent-search-control">
                                        <input id="company_search" class="form-control" type="text" value="<?php echo parent_consent_h($companyName); ?>" placeholder="Search company or training site" data-preview-target="pcCompany" autocomplete="off">
                                        <button class="parent-consent-search-toggle" type="button" aria-label="Open company search"><i class="feather-chevron-down"></i></button>
                                    </div>
                                    <div class="parent-consent-search-panel">
                                        <div class="parent-consent-search-message">Type at least 1 character.</div>
                                        <div class="parent-consent-search-results"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="builder-field">
                                <label for="date" class="form-label">Date</label>
                                <input id="date" class="form-control" type="text" name="date" value="<?php echo parent_consent_h($printDate); ?>" placeholder="May 2, 2026">
                            </div>

                            <div class="builder-editor-actions mt-4">
                                <button class="btn btn-primary" type="submit">Update Preview</button>
                                <a class="btn btn-light" href="document_parent_consent.php">Reset</a>
                            </div>
                        </div>

                        <div class="builder-card builder-card-note d-block">
                            <div class="builder-card-head">
                                <h6>Print Notes</h6>
                            </div>
                            <ul class="parent-consent-helper-list">
                                <li>The printed copy only includes the white consent sheet.</li>
                                <li>Leave company blank if the host company is not final yet.</li>
                                <li>Signatures remain blank for handwritten approval.</li>
                            </ul>
                        </div>
                    </section>

                    <section class="application-builder-canvas">
                        <div class="builder-card builder-card-editor">
                            <div class="builder-editor-head">
                                <div>
                                    <h6>Template Builder</h6>
                                    <p>Parent consent and waiver preview with the same document workspace style.</p>
                                </div>
                                <div class="builder-editor-actions">
                                    <button class="btn btn-primary" type="submit">Update Preview</button>
                                    <button class="btn btn-light" type="reset" id="parentConsentReset">Reset</button>
                                    <button class="btn btn-success" type="button" data-parent-consent-print>Print Consent</button>
                                </div>
                            </div>

                            <div class="builder-toolbar is-disabled" aria-hidden="true">
                                <button class="btn btn-light" type="button" disabled><strong>B</strong></button>
                                <button class="btn btn-light" type="button" disabled><em>I</em></button>
                                <button class="btn btn-light" type="button" disabled><u>U</u></button>
                                <button class="btn btn-light" type="button" disabled>Left</button>
                                <button class="btn btn-light" type="button" disabled>Center</button>
                                <button class="btn btn-light" type="button" disabled>Right</button>
                                <button class="btn btn-light" type="button" disabled>Justify</button>
                                <span class="builder-status-text">Template locked. Use fields on the left to update the copy.</span>
                            </div>

                            <div class="builder-status-bar">
                                <span class="builder-status-text">Preview ready.</span>
                            </div>

                            <div class="builder-paper-shell">
                                <div class="builder-paper">
                                    <div id="editor" class="builder-editor-surface is-locked" contenteditable="false" spellcheck="false">
                                        <div class="a4-pages-stack" data-a4-document="true">
                                            <div class="a4-page" data-a4-width-mm="210" data-a4-height-mm="297" style="width:210mm; min-height:297mm; box-sizing:border-box; background:#fff;">
                                                <header class="parent-consent-header">
                                                    <img src="assets/images/ccstlogo.png" alt="CCST Logo">
                                                    <div class="parent-consent-school">
                                                        <strong>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</strong>
                                                        SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga<br>
                                                        Telefax No.: (045) 624-0215
                                                    </div>
                                                    <span></span>
                                                </header>

                                                <section class="parent-consent-content">
                                                    <h2 class="parent-consent-title">Parent Consent and Waiver</h2>

                                                    <p>
                                                        I hereby give my consent for <strong id="pcStudent"><?php echo parent_consent_h($studentLine); ?></strong> to participate in the On-the-Job Training (OJT)
                                                        required by <strong>Clark College of Science and Technology (CCST)</strong> at the school and/or its partner or host company<span id="pcCompanyClause"><?php echo parent_consent_h($companyClause); ?></span>.
                                                    </p>

                                                    <p>
                                                        I understand that participation in OJT involves certain risks, including possible accidents, injuries, or health-related concerns. I voluntarily allow my
                                                        <strong>son/daughter</strong> to undergo OJT and agree not to hold Clark College of Science and Technology, its administrators, faculty, advisers,
                                                        and staff, as well as the host/partner company, liable for any accident or incident.
                                                    </p>

                                                    <p>
                                                        I acknowledge that the school and the faculty adviser will provide proper guidance and supervision, but that they cannot guarantee absolute safety at
                                                        all times during the OJT period.
                                                    </p>

                                                    <p>
                                                        With this, I express my trust that reasonable safety measures and precautions will be observed for the welfare of my
                                                        <strong>son/daughter</strong> throughout the duration of the OJT.
                                                    </p>

                                                    <p>
                                                        I confirm that I have read and understood this consent and that my signature below signifies my approval and agreement.
                                                    </p>

                                                    <div class="parent-consent-sign-student">
                                                        <div class="parent-consent-sign-line">
                                                            <span class="parent-consent-sign-name" id="pcStudentSignature"><?php echo parent_consent_h($studentName); ?></span>
                                                            <span class="parent-consent-sign-label">Signature over Printed Name of Student</span>
                                                        </div>
                                                    </div>

                                                    <div class="parent-consent-sign-grid">
                                                        <div class="parent-consent-sign-line">
                                                            <span class="parent-consent-sign-name" id="pcParentSignature"><?php echo parent_consent_h($parentLine); ?></span>
                                                            <span class="parent-consent-sign-label">Signature over Printed Name of Parent/Guardian</span>
                                                        </div>
                                                        <div class="parent-consent-sign-date">Date</div>
                                                    </div>
                                                </section>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </form>
            </div>
        </div>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var studentInput = document.getElementById('student_name');
    var studentIdInput = document.getElementById('parentConsentStudentId');
    var parentInput = document.getElementById('parent_name');
    var companyInput = document.getElementById('company_name');
    var companySearchInput = document.getElementById('company_search');
    var studentPreview = document.getElementById('pcStudent');
    var studentSignature = document.getElementById('pcStudentSignature');
    var parentSignature = document.getElementById('pcParentSignature');
    var companyClause = document.getElementById('pcCompanyClause');
    var endpoint = new URL('document_parent_consent.php', window.location.href).href;

    function syncPreview() {
        if (studentPreview && studentInput) {
            var studentName = studentInput.value.trim();
            studentPreview.textContent = studentName || 'my son/daughter';
            if (studentSignature) {
                studentSignature.textContent = studentName;
            }
        }
        if (parentSignature && parentInput) {
            parentSignature.textContent = parentInput.value.trim();
        }
        if (companyClause && companyInput) {
            var company = companyInput.value.trim();
            companyClause.textContent = company ? ' (' + company + ')' : '';
        }
    }

    [studentInput, parentInput, companyInput].forEach(function (input) {
        if (input) {
            input.addEventListener('input', syncPreview);
        }
    });

    function initDropdownSearch(config) {
        var root = document.querySelector(config.rootSelector);
        var input = config.input;
        if (!root || !input || input.readOnly) {
            return;
        }

        var panel = root.querySelector('.parent-consent-search-panel');
        var message = root.querySelector('.parent-consent-search-message');
        var results = root.querySelector('.parent-consent-search-results');
        var toggle = root.querySelector('.parent-consent-search-toggle');
        var timer = null;
        var token = 0;

        function openPanel() {
            if (panel) {
                panel.classList.add('is-open');
            }
        }

        function closePanel() {
            if (panel) {
                panel.classList.remove('is-open');
            }
        }

        function setMessage(text) {
            if (message) {
                message.textContent = text;
            }
        }

        function render(items) {
            if (!results) {
                return;
            }
            results.innerHTML = '';
            if (!items.length) {
                setMessage(config.emptyText || 'No results found.');
                return;
            }
            setMessage(config.pickText || 'Select an item.');
            items.forEach(function (item) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'parent-consent-search-option';
                button.textContent = item.text || item.name || ('Item #' + item.id);
                button.addEventListener('click', function () {
                    config.onPick(item);
                    closePanel();
                    syncPreview();
                });
                results.appendChild(button);
            });
        }

        function search(term) {
            var value = String(term || '').trim();
            if (value.length < 1) {
                if (results) {
                    results.innerHTML = '';
                }
                setMessage('Type at least 1 character.');
                return;
            }
            token += 1;
            var currentToken = token;
            setMessage('Searching...');
            fetch(endpoint + '?action=' + encodeURIComponent(config.action) + '&q=' + encodeURIComponent(value), { credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (currentToken !== token) {
                        return;
                    }
                    render(Array.isArray(data.results) ? data.results : []);
                })
                .catch(function () {
                    if (currentToken !== token) {
                        return;
                    }
                    if (results) {
                        results.innerHTML = '';
                    }
                    setMessage('Search failed. Try again.');
                });
        }

        input.addEventListener('focus', function () {
            openPanel();
            search(input.value);
        });
        input.addEventListener('input', function () {
            if (typeof config.onType === 'function') {
                config.onType(input.value);
            }
            openPanel();
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function () {
                search(input.value);
            }, 220);
        });
        if (toggle) {
            toggle.addEventListener('click', function () {
                openPanel();
                input.focus();
                search(input.value);
            });
        }
        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) {
                closePanel();
            }
        });
    }

    function initStudentSearch() {
        initDropdownSearch({
            rootSelector: '[data-parent-consent-student-search]',
            input: studentInput,
            action: 'search_students',
            emptyText: 'No students found.',
            pickText: 'Select a student.',
            onType: function () {
                if (studentIdInput) {
                    studentIdInput.value = '';
                }
            },
            onPick: function (item) {
                studentInput.value = item.name || String(item.text || '').replace(/\s*-\s*.*$/, '').trim();
                if (parentInput && item.parent_name && !parentInput.value.trim()) {
                    parentInput.value = item.parent_name;
                }
                if (studentIdInput) {
                    studentIdInput.value = item.id || '';
                }
            }
        });
    }

    function initCompanySearch() {
        initDropdownSearch({
            rootSelector: '[data-parent-consent-company-search]',
            input: companySearchInput,
            action: 'search_companies',
            emptyText: 'No companies found.',
            pickText: 'Select a company.',
            onType: function (value) {
                if (companyInput) {
                    companyInput.value = String(value || '').trim();
                }
                syncPreview();
            },
            onPick: function (item) {
                var companyName = item.name || String(item.text || '').replace(/\s*-\s*.*$/, '').trim();
                if (companySearchInput) {
                    companySearchInput.value = item.text || companyName;
                }
                if (companyInput) {
                    companyInput.value = companyName;
                }
            }
        });
    }

    document.querySelectorAll('[data-parent-consent-print]').forEach(function (button) {
        button.addEventListener('click', function () {
            syncPreview();
            window.print();
        });
    });

    var resetButton = document.getElementById('parentConsentReset');
    if (resetButton) {
        resetButton.addEventListener('click', function () {
            window.setTimeout(syncPreview, 0);
        });
    }

    initStudentSearch();
    initCompanySearch();
    syncPreview();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
