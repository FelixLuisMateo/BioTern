<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ojt_masterlist.php';
require_once dirname(__DIR__) . '/lib/company_profiles.php';
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : '';
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';
$db_port = defined('DB_PORT') ? (int)DB_PORT : 3306;

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name, $db_port);
    if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

$prefill_student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$prefill_greeting_pref = strtolower(trim((string)($_GET['greeting_pref'] ?? 'either')));
if (!in_array($prefill_greeting_pref, ['sir', 'maam', 'either'], true)) {
    $prefill_greeting_pref = 'either';
}
$prefill_recipient_title = strtolower(trim((string)($_GET['recipient_title'] ?? 'auto')));
if (!in_array($prefill_recipient_title, ['auto', 'mr', 'ms', 'none'], true)) {
    $prefill_recipient_title = 'auto';
}

function endorsement_document_student(mysqli $conn, int $id): array
{
    if ($id <= 0) {
        return [];
    }

    $stmt = $conn->prepare("SELECT s.*, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1");
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : [];
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'search_students') {
        $term = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
        $sql = "SELECT id, first_name, middle_name, last_name, student_id
                FROM students
                WHERE CONCAT(first_name,' ',middle_name,' ',last_name) LIKE '%{$term}%'
                   OR student_id LIKE '%{$term}%'
                ORDER BY first_name
                LIMIT 50";
        $res = $conn->query($sql);
        $results = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $name = trim($r['first_name'] . ' ' . ($r['middle_name'] ? $r['middle_name'] . ' ' : '') . $r['last_name']);
                $results[] = ['id' => $r['id'], 'text' => $name . ' - ' . $r['student_id']];
            }
        }
        echo json_encode(['results' => $results]);
        exit;
    }

    if ($action === 'search_companies') {
        $term = trim((string)($_GET['q'] ?? ''));
        $results = [];
        foreach (biotern_company_profiles_search($conn, $term, 25) as $company) {
            $labelParts = [trim((string)($company['company_name'] ?? ''))];
            $contactText = trim((string)($company['contact_name'] ?? ''));
            if ($contactText !== '') {
                $labelParts[] = $contactText;
            } elseif (trim((string)($company['company_address'] ?? '')) !== '') {
                $labelParts[] = trim((string)($company['company_address'] ?? ''));
            }

            $results[] = [
                'id' => (string)($company['key'] ?? $company['company_lookup_key'] ?? $company['company_name'] ?? ''),
                'text' => implode(' - ', array_filter($labelParts, static function ($value): bool {
                    return trim((string)$value) !== '';
                })),
                'name' => trim((string)($company['company_name'] ?? '')),
                'address' => trim((string)($company['company_address'] ?? '')),
                'contact_name' => trim((string)($company['contact_name'] ?? $company['company_representative'] ?? $company['supervisor_name'] ?? '')),
                'contact_position' => trim((string)($company['contact_position'] ?? $company['company_representative_position'] ?? $company['supervisor_position'] ?? '')),
            ];
        }
        echo json_encode(['results' => $results]);
        exit;
    }

    if ($action === 'get_company_profile') {
        $companyIdentifier = trim((string)($_GET['company'] ?? ''));
        $company = biotern_company_profile_find($conn, $companyIdentifier);
        echo json_encode($company ?: new stdClass());
        exit;
    }

    if ($action === 'get_student' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $row = endorsement_document_student($conn, $id);
        echo json_encode($row ?: new stdClass());
        exit;
    }

    if ($action === 'get_endorsement' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $student = endorsement_document_student($conn, $id);
        $masterlist = !empty($student) ? biotern_masterlist_fetch_for_student($conn, $student) : [];
        $row = !empty($masterlist) ? biotern_masterlist_endorsement_defaults($masterlist, $student) : [];
        $exists = $conn->query("SHOW TABLES LIKE 'endorsement_letter'");
        if ($exists && $exists->num_rows > 0) {
            $stmt = $conn->prepare("SELECT * FROM endorsement_letter WHERE user_id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $saved = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (is_array($saved) && !empty($saved)) {
                    $row = array_merge($row, array_filter($saved, static fn($value) => $value !== null && $value !== ''));
                }
            }
        }
        echo json_encode($row ?: new stdClass());
        exit;
    }

    echo json_encode(new stdClass());
    exit;
}
$page_title = 'Endorsement Letter';
$base_href = '../';
$page_body_class = 'application-builder-page endorsement-builder-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/documents/document-builder-shared.css',
    'assets/css/modules/documents/page-endorsement-document-builder.css',
    'assets/css/modules/documents/template-print-isolation.css',
];
$page_scripts = [
    'assets/js/modules/documents/endorsement-document-builder.js',
];
include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header dashboard-page-header page-header-with-middle">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Endorsement Letter</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Documents</a></li>
                    <li class="breadcrumb-item">Endorsement Builder</li>
                </ul>
            </div>
            <div class="page-header-middle">
                <p class="page-header-statement">Use one workspace to select students, pull company data, and generate a print-ready endorsement letter.</p>
            </div>
            <?php ob_start(); ?>
                <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
                <a href="document_application.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>Application</a>
            <?php
            biotern_render_page_header_actions([
                'menu_id' => 'documentEndorsementActionsMenu',
                'items_html' => ob_get_clean(),
            ]);
            ?>
        </div>

        <div class="application-document-builder endorsement-page" data-prefill-student-id="<?php echo (int)$prefill_student_id; ?>" data-prefill-company="<?php echo htmlspecialchars((string)($_GET['company'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-prefill-recipient-title="<?php echo htmlspecialchars($prefill_recipient_title, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="main-content">
                <div class="application-builder-grid">
                    <section class="application-builder-sidebar">
                        <div class="builder-card">
                            <div class="builder-card-head">
                                <h6>Record Source</h6>
                                <p>Search student and company records, then the letter preview updates instantly.</p>
                            </div>

                            <div class="builder-field">
                                <label for="student_select" class="form-label">Student Name</label>
                                <select id="student_select" data-placeholder="Search by name or student id"></select>
                                <small class="text-muted application-source-hint">Search and select from student records.</small>
                            </div>

                            <div class="builder-field">
                                <label for="company_select" class="form-label">Company / Training Site</label>
                                <select id="company_select" data-placeholder="Search company, address, or representative"></select>
                                <small class="text-muted application-source-hint">Pick a company to auto-fill recipient, position, company, and address.</small>
                            </div>

                            <div class="application-autofill-panel">
                                <div class="application-autofill-title">Company Details</div>
                                <p>These fields update from the selected company record. You can still adjust them before printing.</p>
                            </div>

                            <div class="builder-field">
                                <label for="input_recipient" class="form-label">Recipient Name</label>
                                <input id="input_recipient" class="form-control" type="text" placeholder="Mr./Ms. full name" autocomplete="off">
                            </div>

                            <div class="builder-field">
                                <label class="form-label d-block mb-2">Recipient Title</label>
                                <div class="builder-inline-options">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="recipient_title" id="rt_auto" value="auto">
                                        <label class="form-check-label" for="rt_auto">Auto</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="recipient_title" id="rt_mr" value="mr">
                                        <label class="form-check-label" for="rt_mr">Mr.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="recipient_title" id="rt_ms" value="ms">
                                        <label class="form-check-label" for="rt_ms">Ms.</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="recipient_title" id="rt_none" value="none">
                                        <label class="form-check-label" for="rt_none">Mr./Ms.</label>
                                    </div>
                                </div>
                            </div>

                            <div class="builder-field">
                                <label for="input_position" class="form-label">Recipient Position</label>
                                <input id="input_position" class="form-control" type="text" placeholder="Recipient position" autocomplete="off">
                            </div>

                            <div class="builder-field">
                                <label for="input_company" class="form-label">Company</label>
                                <input id="input_company" class="form-control" type="text" placeholder="Company name" autocomplete="off">
                            </div>

                            <div class="builder-field">
                                <label for="input_company_address" class="form-label">Company Address</label>
                                <textarea id="input_company_address" class="form-control" rows="3" placeholder="Company address" autocomplete="off"></textarea>
                            </div>

                            <div class="builder-field">
                                <label for="input_students" class="form-label">Students to Endorse</label>
                                <textarea id="input_students" class="form-control" rows="4" placeholder="Lastname, Firstname M."></textarea>
                                <small class="text-muted application-source-hint">One student per line. Selecting students adds them here.</small>
                            </div>
                        </div>
                    </section>

                    <section class="application-builder-canvas">
                        <div class="builder-card builder-card-editor">
                            <div class="builder-editor-head">
                                <div>
                                    <h6>Template Builder</h6>
                                    <p>Endorsement letter preview, editor, and print layout in one place.</p>
                                </div>
                                <div class="builder-editor-actions">
                                    <button id="btn_toggle_edit" class="btn btn-light" type="button" aria-pressed="false">Edit Template</button>
                                    <button id="btn_save" class="btn btn-primary" type="button">Save Template</button>
                                    <button id="btn_reset" class="btn btn-light" type="button">Reset</button>
                                    <button id="btn_print" class="btn btn-success" type="button">Print Letter</button>
                                </div>
                            </div>

                            <div class="builder-toolbar is-disabled" id="builder_toolbar" aria-label="Template formatting tools" aria-hidden="true">
                                <button id="btn_bold" class="btn btn-light" type="button"><strong>B</strong></button>
                                <button id="btn_italic" class="btn btn-light" type="button"><em>I</em></button>
                                <button id="btn_underline" class="btn btn-light" type="button"><u>U</u></button>
                                <button id="btn_left" class="btn btn-light" type="button">Left</button>
                                <button id="btn_center" class="btn btn-light" type="button">Center</button>
                                <button id="btn_right" class="btn btn-light" type="button">Right</button>
                                <button id="btn_justify" class="btn btn-light" type="button">Justify</button>
                                <button id="btn_indent" class="btn btn-light" type="button">Indent</button>
                                <button id="btn_outdent" class="btn btn-light" type="button">Outdent</button>
                            </div>

                            <div class="builder-status-bar">
                                <span id="msg" class="builder-status-text">Template locked. Use Edit Template to change layout.</span>
                            </div>

                            <div class="builder-paper-shell">
                                <div class="builder-paper">
                                    <div id="editor" class="builder-editor-surface is-locked" contenteditable="false" spellcheck="false"></div>
                                </div>
                            </div>

                            <template id="endorsement_default_template">
                                <div class="a4-pages-stack" data-a4-document="true">
                                    <div class="a4-page" data-a4-width-mm="210" data-a4-height-mm="297" style="width:210mm; min-height:297mm; box-sizing:border-box; padding:0.55in 0.75in 0.75in; background:#fff;">
                                        <div class="endorsement-letter-template">
                                            <div class="preview-header">
                                                <img class="crest-preview crest-preview-position" src="assets/images/ccstlogo.png" alt="CCST logo" data-hide-onerror="1">
                                                <div class="preview-header-copy">
                                                    <p class="school-name">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</p>
                                                    <div class="school-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
                                                    <div class="school-tel">Telefax No.: (045) 624-0215</div>
                                                </div>
                                            </div>
                                            <div class="preview-content" id="preview_content">
                                                <h5>ENDORSEMENT LETTER</h5>
                                                <p><strong id="pv_recipient" class="endorsement-fill-line">__________________________</strong><br>
                                                <span id="pv_position" class="endorsement-fill-line">__________________________</span><br>
                                                <span id="pv_company" class="endorsement-fill-line">__________________________</span><br>
                                                <span id="pv_company_address" class="endorsement-fill-line">__________________________</span></p>

                                                <p><span id="pv_salutation">Dear Ma'am,</span></p>
                                                <p>Greetings from Clark College of Science and Technology!</p>
                                                <p>We are pleased to introduce our Associate in Computer Technology program, designed to promote student success by developing competencies in core Information Technology disciplines. Our curriculum emphasizes practical experience through internships and on-the-job training, fostering a strong foundation in current industry practices.</p>
                                                <p>In this regard, we are seeking your esteemed company's support in accommodating the following students:</p>
                                                <ul id="pv_students">
                                                    <li>__________________________</li>
                                                </ul>
                                                <p>These students are required to complete 250 training hours. We believe that your organization can provide them with invaluable knowledge and skills, helping them to maximize their potential for future careers in IT.</p>
                                                <p>Our teacher-in-charge will coordinate with you to monitor the students' progress and performance.</p>
                                                <p>We look forward to a productive partnership with your organization. Thank you for your consideration and support.</p>

                                                <p>Sincerely,</p>
                                                <div class="signature">
                                                    <p><strong>MR. JOMAR G. SANGIL</strong><br>
                                                    <strong>ICT DEPARTMENT HEAD</strong><br>
                                                    <strong>Clark College of Science and Technology</strong></p>
                                                    <div class="ross-signatory">
                                                        <img class="ross-signature" src="pages/Ross-Signature.png" alt="Ross signature" data-hide-onerror="1">
                                                        <p class="ross-signatory-text"><strong>MR. ROSS CARVEL C. RAMIREZ</strong><br>
                                                        <strong>HEAD OF ACADEMIC AFFAIRS</strong><br>
                                                        <strong>Clark College of Science and Technology</strong></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</main>

<?php if (false): ?>
<style>
        html, body { height: 100%; margin: 0; padding: 0; }
        body { display:flex; flex-direction:column; min-height:100vh; }
        main.nxl-container { flex:1; display:flex; flex-direction:column; padding-top:90px; }
        div.nxl-content { flex:1; padding-bottom:24px; }
        footer.footer { margin-top: auto; }

        .doc-preview { background:#fff; border:1px solid #eee; padding:24px; max-width:800px; margin-top:18px; margin-bottom:32px; box-shadow:0 6px 20px rgba(0,0,0,.06); position:relative; z-index:1; }
        .preview-header { position:relative; min-height:72px; text-align:center; border-bottom:1px solid #8ab0e6; padding:8px 0 6px; margin-bottom:10px; }
        .preview-header .school-name { font-family:'Times New Roman', Times, serif; color:#1b4f9c; font-size:20px; margin:0; font-weight:700; }
        .preview-header .school-meta, .preview-header .school-tel { font-family:'Times New Roman', Times, serif; color:#1b4f9c; font-size:14px; line-height:1.2; }
        .preview-content { font-family:"Times New Roman", Times, serif; font-size:12pt; line-height:1.45; color:#2f3640; }
        .preview-content h5 { text-align:center; margin:8px 0 12px; font-weight:700; }
        .preview-content p,
        .preview-content li,
        .preview-content strong,
        .preview-content span {
            color:#2f3640;
        }
        .signature { margin-top:28px; }
        .ross-signatory { position: relative; margin-top:34px; padding-top:48px; }
        .ross-signature {
            position: absolute;
            top: -26px;
            left: -6px;
            width: 300px;
            max-width: none;
            height: auto;
            z-index: 2;
            pointer-events: none;
        }
        .ross-signatory-text { position: relative; z-index: 1; }
        .card .btn { position: relative; z-index: 5; pointer-events: auto; }
        .select2-container--open { z-index: 9999999 !important; }
        .select2-dropdown { z-index: 9999999 !important; }
        .select2-container .select2-search__field {
            padding: 4px !important;
            margin: 0 !important;
            height: auto !important;
            border: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }
        .select2-container .select2-selection__rendered,
        .select2-container .select2-selection__placeholder {
            visibility: hidden !important;
        }
        .select2-overlay-input {
            position: absolute;
            inset: 0 40px 0 8px;
            width: calc(100% - 48px);
            height: calc(100% - 8px);
            border: none;
            background: transparent;
            padding: 6px 8px;
            box-sizing: border-box;
            z-index: 99999999;
            font: inherit;
            color: inherit;
        }
        .select2-overlay-input:focus { outline: none; }
        /* Keep placeholders visibly dimmer than user-entered values */
        .form-control::placeholder {
            color: #7a8699;
            opacity: 1;
        }
        html.app-skin-dark input.form-control::-webkit-input-placeholder,
        html.app-skin-dark textarea.form-control::-webkit-input-placeholder,
        body.app-skin-dark input.form-control::-webkit-input-placeholder,
        body.app-skin-dark textarea.form-control::-webkit-input-placeholder,
        .app-skin-dark input.form-control::-webkit-input-placeholder,
        .app-skin-dark textarea.form-control::-webkit-input-placeholder,
        html.app-skin-dark input.form-control::-moz-placeholder,
        html.app-skin-dark textarea.form-control::-moz-placeholder,
        body.app-skin-dark input.form-control::-moz-placeholder,
        body.app-skin-dark textarea.form-control::-moz-placeholder,
        .app-skin-dark input.form-control::-moz-placeholder,
        .app-skin-dark textarea.form-control::-moz-placeholder,
        html.app-skin-dark input.form-control:-ms-input-placeholder,
        html.app-skin-dark textarea.form-control:-ms-input-placeholder,
        body.app-skin-dark input.form-control:-ms-input-placeholder,
        body.app-skin-dark textarea.form-control:-ms-input-placeholder,
        .app-skin-dark input.form-control:-ms-input-placeholder,
        .app-skin-dark textarea.form-control:-ms-input-placeholder,
        html.app-skin-dark input.form-control::placeholder,
        html.app-skin-dark textarea.form-control::placeholder,
        body.app-skin-dark input.form-control::placeholder,
        body.app-skin-dark textarea.form-control::placeholder,
        .app-skin-dark input.form-control::placeholder,
        .app-skin-dark textarea.form-control::placeholder {
            color: #9fb0c6 !important;
            opacity: 1 !important;
            -webkit-text-fill-color: #9fb0c6 !important;
        }
        html.app-skin-dark .form-control,
        body.app-skin-dark .form-control,
        .app-skin-dark .form-control {
            color: #dbe5f1 !important;
            -webkit-text-fill-color: #dbe5f1 !important;
        }
        html.app-skin-dark .form-control:placeholder-shown,
        body.app-skin-dark .form-control:placeholder-shown,
        .app-skin-dark .form-control:placeholder-shown {
            color: #9fb0c6 !important;
            -webkit-text-fill-color: #9fb0c6 !important;
        }

        html.app-skin-dark .select2-container--default .select2-selection--single {
            background: #0f172a !important;
            border-color: #1b2436 !important;
            color: #dbe5f1 !important;
        }
        html.app-skin-dark .select2-overlay-input {
            color: #dbe5f1 !important;
        }
        html.app-skin-dark .select2-overlay-input::placeholder {
            color: #9fb0c6 !important;
        }
        html.app-skin-dark .select2-container--default.select2-container--open .select2-dropdown {
            background: #0f172a !important;
            border-color: #1b2436 !important;
        }
        html.app-skin-dark .select2-results__option {
            background: #0f172a !important;
            color: #dbe5f1 !important;
        }
        html.app-skin-dark .select2-results__option--highlighted[aria-selected] {
            background: #1f2b44 !important;
            color: #ffffff !important;
        }

        html.app-skin-dark .doc-preview {
            background: #0f172a !important;
            border-color: #1b2436 !important;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
        html.app-skin-dark .preview-content,
        html.app-skin-dark .preview-content p,
        html.app-skin-dark .preview-content li,
        html.app-skin-dark .preview-content strong,
        html.app-skin-dark .preview-content span {
            color: #dbe5f1 !important;
        }
        html.app-skin-dark .preview-header {
            border-bottom-color: #78a7e8 !important;
        }
        html.app-skin-dark .preview-header .school-name {
            color: #f3f8ff !important;
        }
        html.app-skin-dark .preview-header .school-meta,
        html.app-skin-dark .preview-header .school-tel {
            color: #d0dff7 !important;
        }

        @media (max-width: 1024px) {
            .nxl-container { position: relative; z-index: 1; }
            .doc-preview { z-index: 1 !important; }
            .select2-container--open,
            .select2-dropdown { z-index: 900 !important; }
        }
        .word-tool-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            font-weight: 600;
            border-width: 1px;
        }
        .endorsement-native-search {
            position: relative;
            width: 100%;
        }
        .endorsement-native-control {
            position: relative;
            display: flex;
            align-items: center;
        }
        .endorsement-native-input {
            padding-right: 38px !important;
        }
        .endorsement-native-toggle {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: #9fb0c6;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .endorsement-native-panel {
            display: none;
            position: absolute;
            z-index: 1000;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            max-height: 260px;
            overflow: auto;
            border: 1px solid #2f3f56;
            border-radius: 12px;
            background: #0f172a;
            box-shadow: 0 18px 36px rgba(0, 0, 0, 0.28);
            padding: 8px;
        }
        .endorsement-native-panel.is-open {
            display: block;
        }
        .endorsement-native-message {
            color: #b9c7dd;
            font-size: 12px;
            padding: 7px 9px;
        }
        .endorsement-native-option {
            display: block;
            width: 100%;
            border: 0;
            border-radius: 9px;
            background: transparent;
            color: #eef5ff;
            text-align: left;
            padding: 9px 10px;
            font-weight: 700;
        }
        .endorsement-native-option-title,
        .endorsement-native-option-subtitle {
            display: block;
            min-width: 0;
        }
        .endorsement-native-option-title {
            color: #eef5ff;
            font-weight: 800;
        }
        .endorsement-native-option-subtitle {
            margin-top: 3px;
            color: #b9c7dd;
            font-size: 12px;
            line-height: 1.3;
            font-weight: 500;
        }
        .endorsement-native-option:hover,
        .endorsement-native-option:focus {
            background: rgba(91, 124, 250, 0.2);
            outline: none;
        }
        @media print {
            body {
                background: #fff !important;
            }
            body * {
                visibility: hidden !important;
            }
            #letter_preview,
            #letter_preview * {
                visibility: visible !important;
            }
            #letter_preview {
                position: fixed !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding: 0.35in 0.55in !important;
                border: 0 !important;
                box-shadow: none !important;
                background: #fff !important;
            }
        }
    </style>
    <div class="container">
    <div class="row mt-3">
        <div class="col-12">
            <h4>Endorsement Letter</h4>
            <p class="text-muted">Select student and prepare the endorsement letter.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card p-3">
                <div class="mt-2">
                    <label for="student_select" class="form-label">Search Student</label>
                    <select id="student_select" style="width:100%"></select>
                    <small class="text-muted">Search and select student.</small>
                </div>
                <div class="mt-1">
                    <label class="form-label">Recipient Name</label>
                    <input id="input_recipient" class="form-control form-control-sm" type="text" placeholder="e.g. Mr. Mark G. Sison">
                </div>
                <div class="mt-2">
                    <label class="form-label d-block mb-2">Recipient Title</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="recipient_title" id="rt_auto" value="auto">
                        <label class="form-check-label" for="rt_auto">Auto (AI guess)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="recipient_title" id="rt_mr" value="mr">
                        <label class="form-check-label" for="rt_mr">Mr.</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="recipient_title" id="rt_ms" value="ms">
                        <label class="form-check-label" for="rt_ms">Ms.</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="recipient_title" id="rt_none" value="none">
                        <label class="form-check-label" for="rt_none">Mr./Ms.</label>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label">Recipient Position</label>
                    <input id="input_position" class="form-control form-control-sm" type="text" placeholder="e.g. Supervisor/Manager">
                </div>
                <div class="mt-2">
                    <label for="company_select" class="form-label">Company / Training Site</label>
                    <select id="company_select" style="width:100%" data-placeholder="Search company, address, or representative"></select>
                    <input id="input_company" type="hidden" value="">
                    <small class="text-muted">Pick a company to auto-fill recipient, position, company, and address.</small>
                </div>
                <div class="mt-2">
                    <label class="form-label">Company Address</label>
                    <textarea id="input_company_address" class="form-control form-control-sm" rows="2" placeholder="Company address"></textarea>
                </div>
                <div class="mt-2">
                    <label class="form-label">Students to Endorse (one per line)</label>
                    <textarea id="input_students" class="form-control form-control-sm" rows="4" placeholder="Lastname, Firstname M."></textarea>
                </div>

                <div class="mt-3 d-flex gap-2">
                    <a id="btn_file_edit" class="btn btn-primary">File Edit</a>
                    <a id="btn_generate" class="btn btn-success flex-grow-1" target="_blank">Generate / Print</a>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="doc-preview" id="letter_preview">
                <img class="crest-preview" src="../assets/images/auth/auth-cover-login-bg.png" alt="crest" style="position:absolute; top:12px; left:12px; width:56px; height:56px; object-fit:contain;" onerror="this.style.display='none'">
                <div class="preview-header">
                    <p class="school-name">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</p>
                    <div class="school-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
                    <div class="school-tel">Telefax No.: (045) 624-0215</div>
                </div>
                <div class="preview-content" id="preview_content">
                    <h5>ENDORSEMENT LETTER</h5>
                    <p><strong id="pv_recipient">__________________________</strong><br>
                    <span id="pv_position">__________________________</span><br>
                    <span id="pv_company">__________________________</span><br>
                    <span id="pv_company_address">__________________________</span></p>

                    <p><span id="pv_salutation">Dear Ma'am,</span></p>

                    <p>Greetings from Clark College of Science and Technology!</p>

                    <p>We are pleased to introduce our Associate in Computer Technology program, designed to promote student success by developing competencies in core Information Technology disciplines. Our curriculum emphasizes practical experience through internships and on-the-job training, fostering a strong foundation in current industry practices.</p>

                    <p>In this regard, we are seeking your esteemed company's support in accommodating the following students:</p>
                    <ul id="pv_students">
                        <li>__________________________</li>
                    </ul>

                    <p>These students are required to complete 250 training hours. We believe that your organization can provide them with invaluable knowledge and skills, helping them to maximize their potential for future careers in IT.</p>
                    <p>Our teacher-in-charge will coordinate with you to monitor the students' progress and performance.</p>
                    <p>We look forward to a productive partnership with your organization. Thank you for your consideration and support.</p>

                    <p>Sincerely,</p>
                    <div class="signature">
                        <p><strong>MR. JOMAR G. SANGIL</strong><br>
                        <strong>ICT DEPARTMENT HEAD</strong><br>
                        <strong>Clark College of Science and Technology</strong></p>
                        <div class="ross-signatory">
                            <img class="ross-signature" src="../pages/Ross-Signature.png" alt="Ross signature" onerror="this.style.display='none'">
                            <p class="ross-signatory-text"><strong>MR. ROSS CARVEL C. RAMIREZ</strong><br>
                            <strong>HEAD OF ACADEMIC AFFAIRS</strong><br>
                            <strong>Clark College of Science and Technology</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function() {
(function(){
    const hasSelect2 = !!(window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 === 'function');
    const select = hasSelect2 ? window.jQuery('#student_select') : null;
    const inputRecipient = document.getElementById('input_recipient');
    const inputPosition = document.getElementById('input_position');
    const inputCompany = document.getElementById('input_company');
    const companySelect = document.getElementById('company_select');
    const inputCompanyAddress = document.getElementById('input_company_address');
    const inputStudents = document.getElementById('input_students');
    const recipientTitleRadios = Array.prototype.slice.call(document.querySelectorAll('input[name="recipient_title"]'));
    const PREFILL_RECIPIENT_TITLE = <?php echo json_encode($prefill_recipient_title); ?>;
    const greetingRadios = Array.prototype.slice.call(document.querySelectorAll('input[name="greeting_preference"]'));
    const PREFILL_GREETING_PREF = <?php echo json_encode($prefill_greeting_pref); ?>;
    const btnGenerate = document.getElementById('btn_generate');
    const btnFileEdit = document.getElementById('btn_file_edit');
    const wordTemplateLink = document.getElementById('word_template_link_endorsement');
    const prefillId = <?php echo intval($prefill_student_id); ?>;
    const endorsementEndpoint = new URL('document_endorsement.php', window.location.href).href;
    let selectedStudentId = prefillId > 0 ? String(prefillId) : '';
    let selectedStudentName = '';
    let selectedCompanyKey = '';

    function sanitizeStudentLines(raw) {
        return String(raw || '')
            .split(/\r?\n/)
            .map(function(x){ return x.trim(); })
            .filter(Boolean);
    }

    function inferTitleFromName(name) {
        const n = String(name || '').trim();
        if (!n) return 'none';
        const l = n.toLowerCase();
        if (l.startsWith('mr ') || l.startsWith('mr.') || l.startsWith('sir ')) return 'mr';
        if (l.startsWith('ms ') || l.startsWith('ms.') || l.startsWith('mrs ') || l.startsWith('mrs.') || l.startsWith('maam') || l.startsWith("ma'am") || l.startsWith('madam')) return 'ms';
        // Lightweight "AI-like" heuristic by common first names; unknown => manual fallback.
        const first = l.replace(/[^a-z\s]/g, ' ').trim().split(/\s+/)[0] || '';
        const likelyMale = ['jomer','jomar','jose','juan','mark','michael','john','james','daniel','paul','peter','kevin','robert','edward','ross','ramirez','sanchez','felix','ivan'];
        const likelyFemale = ['anna','ana','maria','marie','jane','joy','kim','angel','diana','michelle','grace','sarah','liza','rose','patricia','christine','karen','claire'];
        if (likelyMale.indexOf(first) !== -1) return 'mr';
        if (likelyFemale.indexOf(first) !== -1) return 'ms';
        return 'none';
    }

    function resolveRecipientTitle() {
        const checked = recipientTitleRadios.find(function(r){ return r.checked; });
        const selected = checked ? checked.value : 'auto';
        if (selected === 'auto') return inferTitleFromName(inputRecipient.value);
        return selected;
    }

    function buildNameFromOptionText(text) {
        return String(text || '').replace(/\s*-\s*.*$/, '').trim();
    }

    function getSelectedStudentName() {
        if (selectedStudentName) {
            return selectedStudentName;
        }
        if (hasSelect2 && select) {
            const selected = select.select2('data') || [];
            const first = selected[0] || null;
            if (first && first.text) {
                return buildNameFromOptionText(first.text);
            }
            const txt = window.jQuery('#student_select').find('option:selected').text() || '';
            return buildNameFromOptionText(txt);
        }
        const nativeSelect = document.getElementById('student_select');
        const selectedOption = nativeSelect && nativeSelect.options && nativeSelect.selectedIndex >= 0 ? nativeSelect.options[nativeSelect.selectedIndex] : null;
        return selectedOption ? buildNameFromOptionText(selectedOption.text || '') : '';
    }

    function detectSalutation(name) {
        const resolvedTitle = resolveRecipientTitle();
        if (resolvedTitle === 'mr') return 'Dear Sir,';
        if (resolvedTitle === 'ms') return 'Dear Ma\'am,';
        if (resolvedTitle === 'none') return 'Dear Sir/Ma\'am,';

        const checked = greetingRadios.find(function(r){ return r.checked; });
        const pref = checked ? checked.value : 'either';
        if (pref === 'sir') return 'Dear Sir,';
        if (pref === 'maam') return 'Dear Ma\'am,';
        const n = String(name || '').toLowerCase().trim();
        if (n.startsWith('mr ') || n.startsWith('mr.') || n.startsWith('sir')) return 'Dear Sir,';
        if (n.startsWith('ms ') || n.startsWith('ms.') || n.startsWith('mrs ') || n.startsWith('mrs.') || n.startsWith('maam') || n.startsWith('ma\'am') || n.startsWith('madam')) return 'Dear Ma\'am,';
        return 'Dear Ma\'am,';
    }

    function appendSelectedStudentToTextarea() {
        const selectedName = getSelectedStudentName();
        if (!selectedName) return;
        const lines = sanitizeStudentLines(inputStudents.value);
        if (lines.indexOf(selectedName) === -1) {
            lines.push(selectedName);
            inputStudents.value = lines.join('\n');
        }
    }

    function formatRecipientName(name) {
        const n = String(name || '').trim();
        if (!n) return '__________________________';
        const rt = resolveRecipientTitle();
        if (rt === 'mr') return 'Mr. ' + n;
        if (rt === 'ms') return 'Ms. ' + n;
        if (rt === 'none') return 'Mr./Ms. ' + n;
        return n;
    }

    function updatePreview() {
        document.getElementById('pv_recipient').textContent = formatRecipientName(inputRecipient.value);
        document.getElementById('pv_position').textContent = inputPosition.value || '__________________________';
        document.getElementById('pv_company').textContent = inputCompany.value || '__________________________';
        document.getElementById('pv_company_address').textContent = inputCompanyAddress.value || '__________________________';
        document.getElementById('pv_salutation').textContent = detectSalutation(inputRecipient.value);

        const ul = document.getElementById('pv_students');
        const typed = sanitizeStudentLines(inputStudents.value);
        const selectedName = getSelectedStudentName();
        const lines = typed.length ? typed : (selectedName ? [selectedName] : []);
        ul.innerHTML = '';
        if (!lines.length) {
            const li = document.createElement('li');
            li.textContent = '__________________________';
            ul.appendChild(li);
        } else {
            lines.forEach(line => {
                const li = document.createElement('li');
                li.textContent = line;
                ul.appendChild(li);
            });
        }
    }

    function updateLinks() {
        const p = new URLSearchParams();
        const selectedId = (hasSelect2 && select ? select.val() : '') || selectedStudentId;
        if (selectedId) {
            p.set('id', String(selectedId));
        } else if (prefillId > 0) {
            p.set('id', String(prefillId));
        }
        if (inputRecipient.value) p.set('recipient', inputRecipient.value);
        const rt = recipientTitleRadios.find(function(r){ return r.checked; });
        if (rt && rt.value) p.set('recipient_title', rt.value);
        if (inputPosition.value) p.set('position', inputPosition.value);
        if (inputCompany.value) p.set('company', inputCompany.value);
        if (inputCompanyAddress.value) p.set('company_address', inputCompanyAddress.value);
        const checked = greetingRadios.find(function(r){ return r.checked; });
        if (checked && checked.value) p.set('greeting_pref', checked.value);
        p.set('print', '1');
        const typed = sanitizeStudentLines(inputStudents.value);
        const selectedName = getSelectedStudentName();
        const studentsValue = typed.length ? typed.join('\n') : selectedName;
        if (studentsValue) p.set('students', studentsValue);
        try {
            const savedTemplate = localStorage.getItem('biotern_endorsement_template_html_v1');
            if (savedTemplate && savedTemplate.trim()) {
                p.set('use_saved_template', '1');
            }
        } catch (e) {}
        const genUrl = 'generate_endorsement_letter.php?' + p.toString();
        btnGenerate.href = genUrl;
        btnFileEdit.href = 'edit_endorsement.php?blank=1';
        const templateParams = new URLSearchParams();
        templateParams.set('template_type', 'endorsement');
        if (selectedId) templateParams.set('student_id', String(selectedId));
        if (wordTemplateLink) wordTemplateLink.href = '/document-word-templates?' + templateParams.toString();
        return genUrl;
    }

    function applySavedEndorsement(data) {
        if (!data || typeof data !== 'object') return false;
        let changed = false;
        if (data.recipient_name) {
            inputRecipient.value = String(data.recipient_name);
            changed = true;
        }
        if (data.recipient_title) {
            const rt = String(data.recipient_title).toLowerCase();
            recipientTitleRadios.forEach(function(r){ r.checked = (r.value === rt); });
            changed = true;
        }
        if (data.recipient_position) {
            inputPosition.value = String(data.recipient_position);
            changed = true;
        }
        if (data.company_name) {
            inputCompany.value = String(data.company_name);
            const existingCompanyInput = document.querySelector('.endorsement-company-search .endorsement-native-input');
            if (existingCompanyInput) {
                existingCompanyInput.value = String(data.company_name);
            }
            changed = true;
        }
        if (data.company_address) {
            inputCompanyAddress.value = String(data.company_address);
            changed = true;
        }
        if (data.students_to_endorse) {
            inputStudents.value = String(data.students_to_endorse);
            changed = true;
        }
        if (data.greeting_preference) {
            const gp = String(data.greeting_preference).toLowerCase();
            greetingRadios.forEach(function(r){ r.checked = (r.value === gp); });
            changed = true;
        }
        return changed;
    }

    function applyCompanyProfile(company, rowLabel) {
        if (!company || typeof company !== 'object') return;

        const companyName = String(company.company_name || company.name || '').trim();
        selectedCompanyKey = String(company.key || company.company_lookup_key || companyName || '').trim();
        inputCompany.value = companyName;
        inputCompanyAddress.value = String(company.company_address || company.address || '').trim();

        if (company.contact_name || company.company_representative || company.supervisor_name || company.partner_representative) {
            inputRecipient.value = String(company.contact_name || company.company_representative || company.supervisor_name || company.partner_representative || '').trim();
        }
        if (company.contact_position || company.company_representative_position || company.supervisor_position || company.partner_position) {
            inputPosition.value = String(company.contact_position || company.company_representative_position || company.supervisor_position || company.partner_position || '').trim();
        }

        const companyInput = document.querySelector('.endorsement-company-search .endorsement-native-input');
        if (companyInput) {
            companyInput.value = rowLabel || companyName;
        }

        updatePreview();
        updateLinks();
    }

    function applyCompanySearchItem(item, rowLabel) {
        if (!item || typeof item !== 'object') return;
        applyCompanyProfile({
            key: item.id || '',
            company_name: item.name || '',
            company_address: item.address || '',
            contact_name: item.contact_name || '',
            contact_position: item.contact_position || ''
        }, rowLabel || item.text || item.name || '');
    }

    function loadCompanyProfile(companyIdentifier, rowLabel) {
        if (!companyIdentifier) return;
        fetch(endorsementEndpoint + '?action=get_company_profile&company=' + encodeURIComponent(companyIdentifier), { credentials: 'same-origin' })
            .then(function(response) { return response.json(); })
            .then(function(company) {
                applyCompanyProfile(company, rowLabel);
            })
            .catch(function(){});
    }

    function initCompanySearch() {
        const sel = companySelect;
        if (!sel || document.querySelector('.endorsement-company-search')) return;

        sel.style.display = 'none';
        sel.setAttribute('aria-hidden', 'true');

        const wrap = document.createElement('div');
        wrap.className = 'endorsement-native-search endorsement-company-search';
        wrap.innerHTML = [
            '<div class="endorsement-native-control">',
            '<input type="text" class="form-control form-control-sm endorsement-native-input" placeholder="Search company, address, or representative" autocomplete="off">',
            '<button type="button" class="endorsement-native-toggle" aria-label="Search companies"><i class="feather-chevron-down"></i></button>',
            '</div>',
            '<div class="endorsement-native-panel"><div class="endorsement-native-message">Type at least 1 character.</div><div class="endorsement-native-results"></div></div>'
        ].join('');
        sel.insertAdjacentElement('afterend', wrap);

        const input = wrap.querySelector('.endorsement-native-input');
        const toggle = wrap.querySelector('.endorsement-native-toggle');
        const panel = wrap.querySelector('.endorsement-native-panel');
        const message = wrap.querySelector('.endorsement-native-message');
        const results = wrap.querySelector('.endorsement-native-results');
        let timer = null;
        let token = 0;

        function openPanel() {
            panel.classList.add('is-open');
        }

        function closePanel() {
            panel.classList.remove('is-open');
        }

        function setMessage(text) {
            message.textContent = text;
        }

        function render(items) {
            results.innerHTML = '';
            if (!items.length) {
                setMessage('No companies found.');
                return;
            }
            setMessage('Select a company.');
            items.forEach(function(item) {
                const btn = document.createElement('button');
                const title = item.name || item.text || ('Company ' + item.id);
                const subtitle = [
                    item.contact_name || '',
                    item.contact_position || '',
                    item.address || ''
                ].filter(Boolean).join(' - ');
                btn.type = 'button';
                btn.className = 'endorsement-native-option';
                btn.innerHTML = '<span class="endorsement-native-option-title"></span><span class="endorsement-native-option-subtitle"></span>';
                btn.querySelector('.endorsement-native-option-title').textContent = title;
                btn.querySelector('.endorsement-native-option-subtitle').textContent = subtitle || 'Select this company';
                btn.addEventListener('click', function() {
                    const label = item.text || '';
                    const option = new Option(label, String(item.id), true, true);
                    sel.innerHTML = '';
                    sel.appendChild(option);
                    input.value = label;
                    closePanel();
                    applyCompanySearchItem(item, label);
                    loadCompanyProfile(String(item.id || ''), label);
                });
                results.appendChild(btn);
            });
        }

        function search(term) {
            const value = String(term || '').trim();
            if (value.length < 1) {
                results.innerHTML = '';
                setMessage('Type at least 1 character.');
                return;
            }
            token += 1;
            const currentToken = token;
            setMessage('Searching...');
            fetch(endorsementEndpoint + '?action=search_companies&q=' + encodeURIComponent(value), { credentials: 'same-origin' })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (currentToken !== token) return;
                    render(Array.isArray(data.results) ? data.results : []);
                })
                .catch(function() {
                    if (currentToken !== token) return;
                    results.innerHTML = '';
                    setMessage('Search failed. Try again.');
                });
        }

        input.addEventListener('focus', function() {
            openPanel();
            search(input.value);
        });
        input.addEventListener('input', function() {
            inputCompany.value = input.value.trim();
            updatePreview();
            updateLinks();
            openPanel();
            if (timer) clearTimeout(timer);
            timer = setTimeout(function() { search(input.value); }, 220);
        });
        toggle.addEventListener('click', function() {
            openPanel();
            input.focus();
            search(input.value);
        });
        document.addEventListener('click', function(event) {
            if (!wrap.contains(event.target)) closePanel();
        });
    }

    function loadPickedStudent(pickedId, pickedLabel) {
        if (!pickedId) return;
        selectedStudentId = String(pickedId);
        selectedStudentName = buildNameFromOptionText(pickedLabel || '');
        appendSelectedStudentToTextarea();
        fetch(endorsementEndpoint + '?action=get_endorsement&id=' + encodeURIComponent(pickedId), { credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(saved){
                applySavedEndorsement(saved);
                updatePreview();
                updateLinks();
            })
            .catch(function(){
                updatePreview();
                updateLinks();
            });
    }

    function initNativeStudentSearch() {
        const sel = document.getElementById('student_select');
        if (!sel || document.querySelector('.endorsement-native-search')) return;

        sel.style.display = 'none';
        sel.setAttribute('aria-hidden', 'true');

        const wrap = document.createElement('div');
        wrap.className = 'endorsement-native-search';
        wrap.innerHTML = [
            '<div class="endorsement-native-control">',
            '<input type="text" class="form-control form-control-sm endorsement-native-input" placeholder="Search by name or student id" autocomplete="off">',
            '<button type="button" class="endorsement-native-toggle" aria-label="Search students"><i class="feather-chevron-down"></i></button>',
            '</div>',
            '<div class="endorsement-native-panel"><div class="endorsement-native-message">Type at least 1 character.</div><div class="endorsement-native-results"></div></div>'
        ].join('');
        sel.insertAdjacentElement('afterend', wrap);

        const input = wrap.querySelector('.endorsement-native-input');
        const toggle = wrap.querySelector('.endorsement-native-toggle');
        const panel = wrap.querySelector('.endorsement-native-panel');
        const message = wrap.querySelector('.endorsement-native-message');
        const results = wrap.querySelector('.endorsement-native-results');
        let timer = null;
        let token = 0;

        function openPanel() {
            panel.classList.add('is-open');
        }

        function closePanel() {
            panel.classList.remove('is-open');
        }

        function setMessage(text) {
            message.textContent = text;
        }

        function render(items) {
            results.innerHTML = '';
            if (!items.length) {
                setMessage('No students found.');
                return;
            }
            setMessage('Select a student.');
            items.forEach(function(item) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'endorsement-native-option';
                btn.textContent = item.text || ('Student #' + item.id);
                btn.addEventListener('click', function() {
                    const label = item.text || '';
                    const option = new Option(label, String(item.id), true, true);
                    sel.innerHTML = '';
                    sel.appendChild(option);
                    input.value = buildNameFromOptionText(label);
                    closePanel();
                    loadPickedStudent(String(item.id || ''), label);
                });
                results.appendChild(btn);
            });
        }

        function search(term) {
            const value = String(term || '').trim();
            if (value.length < 1) {
                results.innerHTML = '';
                setMessage('Type at least 1 character.');
                return;
            }
            token += 1;
            const currentToken = token;
            setMessage('Searching...');
            fetch(endorsementEndpoint + '?action=search_students&q=' + encodeURIComponent(value), { credentials: 'same-origin' })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (currentToken !== token) return;
                    render(Array.isArray(data.results) ? data.results : []);
                })
                .catch(function() {
                    if (currentToken !== token) return;
                    results.innerHTML = '';
                    setMessage('Search failed. Try again.');
                });
        }

        input.addEventListener('focus', function() {
            openPanel();
            search(input.value);
        });
        input.addEventListener('input', function() {
            openPanel();
            if (timer) clearTimeout(timer);
            timer = setTimeout(function() { search(input.value); }, 220);
        });
        toggle.addEventListener('click', function() {
            openPanel();
            input.focus();
            search(input.value);
        });
        document.addEventListener('click', function(event) {
            if (!wrap.contains(event.target)) closePanel();
        });
    }

    if (hasSelect2 && select) {
        select.select2({
            placeholder: '',
            ajax: {
                url: endorsementEndpoint,
                dataType: 'json',
                delay: 250,
                data: function(params){ return { action: 'search_students', q: params.term }; },
                processResults: function(data){ return { results: data.results || [] }; }
            },
            minimumInputLength: 1,
            width: 'resolve',
            dropdownParent: window.jQuery(document.body),
            dropdownCssClass: 'select2-dropdown'
        });
    } else {
        initNativeStudentSearch();
    }

    function createSelectOverlay() {
        if (document.querySelector('.select2-overlay-input')) return;
        const sel = document.getElementById('student_select');
        if (!sel) return;
        const container = sel.nextElementSibling;
        if (!container || !container.classList || !container.classList.contains('select2')) return;
        container.style.position = 'relative';
        const overlay = document.createElement('input');
        overlay.type = 'text';
        overlay.className = 'select2-overlay-input';
        overlay.placeholder = sel.getAttribute('data-placeholder') || 'Search by name or student id';
        overlay.autocomplete = 'off';
        container.appendChild(overlay);

        function openAndSync(){
            try { select.select2('open'); } catch(e){}
            setTimeout(function(){
                var fld = document.querySelector('.select2-container--open .select2-search__field');
                if (!fld) return;
                fld.value = overlay.value || '';
                fld.dispatchEvent(new Event('input', { bubbles: true }));
            }, 0);
        }

        overlay.addEventListener('input', function(){
            openAndSync();
        });
        overlay.addEventListener('keydown', function(e){
            if (e.key && (e.key.length === 1 || e.key === 'Backspace')) {
                openAndSync();
            }
        });

        if (!hasSelect2 || !window.jQuery) return;
        window.jQuery(document).on('select2:select select2:closing', '#student_select', function(){
            setTimeout(function(){
                const txt = window.jQuery('#student_select').find('option:selected').text() || '';
                overlay.value = buildNameFromOptionText(txt);
            }, 0);
        });
        container.addEventListener('click', function(){ overlay.focus(); });
    }

    if (hasSelect2 && select) {
        select.on('select2:open', function() {
            // keep overlay input focused for direct typing behavior
        });

        select.on('select2:select', function(){
            const pickedId = String(select.val() || '');
            const pickedLabel = window.jQuery('#student_select').find('option:selected').text() || '';
            loadPickedStudent(pickedId, pickedLabel);
            // Clear current selection so user can search/add another student quickly.
            select.val(null).trigger('change');
            const overlay = document.querySelector('.select2-overlay-input');
            if (overlay) overlay.value = '';
            setTimeout(function(){ if (overlay) overlay.focus(); }, 0);
        });

        select.on('select2:unselect change', function(){
            updatePreview();
            updateLinks();
        });
    }

    [inputRecipient, inputPosition, inputCompanyAddress, inputStudents].forEach(el => {
        el.addEventListener('input', function(){
            updatePreview();
            updateLinks();
        });
    });
    recipientTitleRadios.forEach(function(r){
        r.addEventListener('change', function(){
            updatePreview();
            updateLinks();
        });
    });
    greetingRadios.forEach(function(r){
        r.addEventListener('change', function(){
            updatePreview();
            updateLinks();
        });
    });

    btnFileEdit.addEventListener('click', function(e){
        e.preventDefault();
        const href = btnFileEdit.href || 'pages/edit_endorsement.php?blank=1';
        window.location.href = href;
    });

    btnGenerate.addEventListener('click', function(e){
        e.preventDefault();
        updatePreview();
        updateLinks();
        window.print();
    });

    if (prefillId > 0) {
        fetch(endorsementEndpoint + '?action=get_endorsement&id=' + encodeURIComponent(prefillId), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(saved => {
                const hasSaved = applySavedEndorsement(saved);
                if (hasSaved) {
                    updatePreview();
                    updateLinks();
                    setTimeout(createSelectOverlay, 60);
                    return;
                }
                fetch(endorsementEndpoint + '?action=get_student&id=' + encodeURIComponent(prefillId), { credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(data => {
                        const full = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ').trim();
                        if (full) {
                            const text = full + ' - ' + (data.student_id || '');
                            const o = new Option(text, String(prefillId), true, true);
                            selectedStudentId = String(prefillId);
                            if (hasSelect2 && select) {
                                select.append(o).trigger('change');
                            } else {
                                const nativeSelect = document.getElementById('student_select');
                                if (nativeSelect) {
                                    nativeSelect.innerHTML = '';
                                    nativeSelect.appendChild(o);
                                }
                                selectedStudentName = full;
                            }
                        }
                        updatePreview();
                        updateLinks();
                        setTimeout(createSelectOverlay, 60);
                    });
            });
    }

    setTimeout(createSelectOverlay, 60);
    initCompanySearch();
    recipientTitleRadios.forEach(function(r){ r.checked = (r.value === PREFILL_RECIPIENT_TITLE); });
    if (!recipientTitleRadios.some(function(r){ return r.checked; }) && recipientTitleRadios.length) {
        const auto = recipientTitleRadios.find(function(r){ return r.value === 'auto'; });
        if (auto) auto.checked = true;
    }
    greetingRadios.forEach(function(r){ r.checked = (r.value === PREFILL_GREETING_PREF); });
    if (!greetingRadios.some(function(r){ return r.checked; }) && greetingRadios.length) {
        greetingRadios[0].checked = false;
        const either = greetingRadios.find(function(r){ return r.value === 'either'; });
        if (either) either.checked = true;
    }

    (function loadSavedTemplatePreview(){
        try {
            var saved = localStorage.getItem('biotern_endorsement_template_html_v1');
            if (!saved) return;
            var out = document.getElementById('preview_content');
            if (!out) return;
            var temp = document.createElement('div');
            temp.innerHTML = saved;
            var extracted = temp.querySelector('#endorsement_doc_content') || temp.querySelector('.content') || temp;
            out.innerHTML = extracted ? extracted.innerHTML : (temp.innerHTML || saved);
        } catch (err) {}
    })();

    updatePreview();
    updateLinks();
})();
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>




