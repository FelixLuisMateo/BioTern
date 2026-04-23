<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/document_access.php';
require_once dirname(__DIR__) . '/lib/company_profiles.php';

$prefill_student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$prefill_company_key = trim((string)($_GET['company'] ?? ''));
$prefill_recipient_title = strtolower(trim((string)($_GET['recipient_title'] ?? 'auto')));
if (!in_array($prefill_recipient_title, ['auto', 'mr', 'ms', 'none'], true)) {
    $prefill_recipient_title = 'auto';
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'search_students') {
        $term = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
        $gateWhere = documents_students_search_gate_sql($conn, 's');
        $sql = "SELECT id, first_name, middle_name, last_name, student_id
            FROM students s
            WHERE (
                CONCAT(first_name,' ',middle_name,' ',last_name) LIKE '%{$term}%'
                OR student_id LIKE '%{$term}%'
            )
              AND {$gateWhere}
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
                'id' => (string)($company['key'] ?? ''),
                'text' => implode(' - ', array_filter($labelParts, static function ($value): bool {
                    return trim((string)$value) !== '';
                })),
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
        $access = documents_student_can_generate($conn, $id);
        if (empty($access['allowed'])) {
            echo json_encode(['access_denied' => true, 'message' => (string)($access['reason'] ?? 'Document access denied.')]);
            exit;
        }
        $stmt = $conn->prepare("SELECT s.*, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        echo json_encode($row ?: new stdClass());
        exit;
    }

    if ($action === 'get_endorsement' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $access = documents_student_can_generate($conn, $id);
        if (empty($access['allowed'])) {
            echo json_encode(['access_denied' => true, 'message' => (string)($access['reason'] ?? 'Document access denied.')]);
            exit;
        }
        $exists = $conn->query("SHOW TABLES LIKE 'endorsement_letter'");
        if (!$exists || $exists->num_rows === 0) {
            $row = biotern_company_profile_merge_endorsement_letter($conn, $id, []);
            echo json_encode($row ?: new stdClass());
            exit;
        }
        $stmt = $conn->prepare("SELECT * FROM endorsement_letter WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $row = biotern_company_profile_merge_endorsement_letter($conn, $id, $row);
        echo json_encode($row ?: new stdClass());
        exit;
    }

    echo json_encode(new stdClass());
    exit;
}
$page_title = 'Endorsement Letter';
$base_href = '../';
$page_body_class = 'application-builder-page document-builder-page endorsement-builder-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/documents/document-builder-shared.css',
    'assets/css/modules/documents/page-application-document-builder.css',
    'assets/css/modules/documents/page-endorsement-document-builder.css',
    'assets/css/modules/documents/template-print-isolation.css',
];
$page_scripts = ['assets/js/modules/documents/endorsement-document-builder.js'];
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
                <p class="page-header-statement">Use one workspace to edit the template, load data, and print the endorsement letter.</p>
            </div>
            <?php ob_start(); ?>
                <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
                <a href="document_application.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>Application</a>
                <a href="document_moa.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>MOA</a>
            <?php
            biotern_render_page_header_actions([
                'menu_id' => 'documentEndorsementActionsMenu',
                'items_html' => ob_get_clean(),
            ]);
            ?>
        </div>
        <div
            class="application-document-builder endorsement-page"
            data-prefill-student-id="<?php echo intval($prefill_student_id); ?>"
            data-prefill-company="<?php echo htmlspecialchars($prefill_company_key, ENT_QUOTES, 'UTF-8'); ?>"
            data-prefill-recipient-title="<?php echo htmlspecialchars($prefill_recipient_title, ENT_QUOTES, 'UTF-8'); ?>"
        >
            <div class="main-content">
                <div class="application-builder-grid">
                    <section class="application-builder-sidebar">
                        <div class="builder-card">
                            <div class="builder-card-head">
                                <h6>Record Source</h6>
                                <p>Select a student, load saved endorsement data, and prepare final values for print.</p>
                            </div>

                            <div class="builder-field">
                                <label for="student_select" class="form-label">Search Student</label>
                                <select id="student_select" class="student-select-full" data-placeholder="Search by name or student id"></select>
                            </div>

                            <div class="builder-field">
                                <label for="company_select" class="form-label">Search Company</label>
                                <select id="company_select" class="company-select-full" data-placeholder="Search company, address, or representative"></select>
                            </div>

                            <div class="builder-field">
                                <label class="form-label">Recipient Name</label>
                                <input id="input_recipient" class="form-control" type="text" placeholder="e.g. Mark G. Sison" autocomplete="off">
                            </div>

                            <div class="builder-field">
                                <label class="form-label d-block mb-2">Recipient Title</label>
                                <div class="builder-inline-options">
                                    <label class="form-check"><input class="form-check-input" type="radio" name="recipient_title" id="rt_auto" value="auto"><span class="form-check-label">Auto</span></label>
                                    <label class="form-check"><input class="form-check-input" type="radio" name="recipient_title" id="rt_mr" value="mr"><span class="form-check-label">Mr.</span></label>
                                    <label class="form-check"><input class="form-check-input" type="radio" name="recipient_title" id="rt_ms" value="ms"><span class="form-check-label">Ms.</span></label>
                                    <label class="form-check"><input class="form-check-input" type="radio" name="recipient_title" id="rt_none" value="none"><span class="form-check-label">Mr./Ms.</span></label>
                                </div>
                            </div>

                            <div class="builder-field-grid">
                                <div class="builder-field">
                                    <label class="form-label">Recipient Position</label>
                                    <input id="input_position" class="form-control" type="text" placeholder="Supervisor/Manager" autocomplete="off">
                                </div>
                                <div class="builder-field">
                                    <label class="form-label">Company Name</label>
                                    <input id="input_company" class="form-control" type="text" placeholder="Company name" autocomplete="off">
                                </div>
                            </div>

                            <div class="builder-field">
                                <label class="form-label">Company Address</label>
                                <textarea id="input_company_address" class="form-control" rows="2" placeholder="Company address" autocomplete="off"></textarea>
                            </div>

                            <div class="builder-field">
                                <label class="form-label">Students to Endorse (one per line)</label>
                                <textarea id="input_students" class="form-control" rows="5" placeholder="Lastname, Firstname M."></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="application-builder-canvas">
                        <div class="builder-card builder-card-editor">
                            <div class="builder-editor-head">
                                <div>
                                    <h6>Template Builder</h6>
                                    <p>Edit, save, and print endorsement letters directly from this page.</p>
                                </div>
                                <div class="builder-editor-actions">
                                    <button id="btn_toggle_edit" class="btn btn-light" type="button" aria-pressed="false">Edit Template</button>
                                    <button id="btn_save" class="btn btn-primary" type="button">Save Template</button>
                                    <button id="btn_reset" class="btn btn-light" type="button">Reset</button>
                                    <button id="btn_print" class="btn btn-success" type="button">Generate / Print</button>
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
                                <label for="font_size_pt">Size</label>
                                <input id="font_size_pt" type="number" min="6" max="96" step="1" value="12" title="Double-click for custom size">
                                <button id="btn_apply_size" class="btn btn-light" type="button">Apply</button>
                                <label for="font_color">Color</label>
                                <input id="font_color" type="color" value="#000000">
                            </div>

                            <div class="builder-status-bar">
                                <span id="msg" class="builder-status-text">Template ready.</span>
                            </div>

                            <div class="builder-paper-shell">
                                <div class="builder-paper">
                                    <div id="editor" class="builder-editor-surface is-locked" contenteditable="false" spellcheck="true"></div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <template id="endorsement_default_template">
                    <div class="a4-pages-stack" data-a4-document="true">
                        <div class="a4-page" data-a4-width-mm="210" data-a4-height-mm="297" style="width:210mm; min-height:297mm; box-sizing:border-box; padding:0.55in 0.9in 0.85in 0.9in; background:#fff;">
                            <div class="endorsement-letter-template">
                                <div class="preview-header">
                                    <img class="crest-preview" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" style="position:absolute; top:12px; left:12px; width:56px; height:56px; object-fit:contain;" onerror="this.style.display='none'">
                                    <div class="preview-header-copy">
                                        <p class="school-name">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</p>
                                        <div class="school-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
                                        <div class="school-tel">Telefax No.: (045) 624-0215</div>
                                    </div>
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
                                            <img class="ross-signature" src="pages/Ross-Signature.png" alt="Ross signature" onerror="this.style.display='none'">
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
        </div>
    </div> <!-- .nxl-content -->
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>







