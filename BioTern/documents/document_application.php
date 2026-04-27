<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/document_access.php';
require_once dirname(__DIR__) . '/lib/company_profiles.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';
biotern_boot_session(isset($conn) ? $conn : null);

$prefill_student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$prefill_company_key = trim((string)($_GET['company'] ?? ''));
$documentsCurrentUserId = (int)($_SESSION['user_id'] ?? 0);
$documentsCurrentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
$documentsIsStudentViewOnly = ($documentsCurrentRole === 'student');

if ($documentsIsStudentViewOnly && $documentsCurrentUserId > 0) {
    $studentLookupStmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
    if ($studentLookupStmt) {
        $studentLookupStmt->bind_param('i', $documentsCurrentUserId);
        $studentLookupStmt->execute();
        $studentLookupRow = $studentLookupStmt->get_result()->fetch_assoc() ?: null;
        $studentLookupStmt->close();
        if ($studentLookupRow) {
            $prefill_student_id = (int)($studentLookupRow['id'] ?? 0);
        }
    }
}

if (isset($_GET['action'])) {
    $action = (string)$_GET['action'];
    header('Content-Type: application/json');

    if ($action === 'search_students') {
        $term = isset($_GET['q']) ? $conn->real_escape_string((string)$_GET['q']) : '';
        $gateWhere = documents_students_search_gate_sql($conn, 's');
        $sql = "SELECT id, first_name, middle_name, last_name, student_id
            FROM students s
            WHERE (
                CONCAT(first_name,' ',middle_name,' ',last_name) LIKE '%" . $term . "%'
                OR student_id LIKE '%" . $term . "%'
            )
              AND {$gateWhere}
                ORDER BY first_name
                LIMIT 50";
        $res = $conn->query($sql);
        $out = [];
        if ($res instanceof mysqli_result) {
            while ($r = $res->fetch_assoc()) {
                $text = trim((string)$r['first_name'] . ' ' . (!empty($r['middle_name']) ? $r['middle_name'] . ' ' : '') . (string)$r['last_name']);
                $out[] = [
                    'id' => (int)$r['id'],
                    'text' => trim($text . ' ' . (string)$r['student_id']),
                ];
            }
        }
        echo json_encode(['results' => $out]);
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
        $stmt = $conn->prepare("SELECT s.*, c.name AS course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            echo json_encode($data ?: new stdClass());
            exit;
        }
        echo json_encode(new stdClass());
        exit;
    }

    if ($action === 'get_application_letter' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $access = documents_student_can_generate($conn, $id);
        if (empty($access['allowed'])) {
            echo json_encode(['access_denied' => true, 'message' => (string)($access['reason'] ?? 'Document access denied.')]);
            exit;
        }
        $exists = $conn->query("SHOW TABLES LIKE 'application_letter'");
        if (!$exists instanceof mysqli_result || $exists->num_rows === 0) {
            $data = biotern_company_profile_merge_application_letter($conn, $id, []);
            echo json_encode($data ?: new stdClass());
            exit;
        }

        $stmt = $conn->prepare("SELECT * FROM application_letter WHERE user_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            $data = biotern_company_profile_merge_application_letter($conn, $id, $data);
            echo json_encode($data ?: new stdClass());
            exit;
        }
        $data = biotern_company_profile_merge_application_letter($conn, $id, []);
        echo json_encode($data ?: new stdClass());
        exit;
    }

    echo json_encode(new stdClass());
    exit;
}

$page_title = 'Application Letter';
$base_href = '../';
$page_body_class = 'application-builder-page application-document-builder-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/documents/document-builder-shared.css',
    'assets/css/modules/documents/page-application-document-builder.css',
    'assets/css/modules/documents/template-print-isolation.css',
];
$page_scripts = [
    'assets/js/modules/documents/application-document-builder.js',
];

include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header dashboard-page-header page-header-with-middle">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">Application Letter</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Documents</a></li>
                    <li class="breadcrumb-item">Application Builder</li>
                </ul>
            </div>
            <div class="page-header-middle">
                <p class="page-header-statement"><?php echo $documentsIsStudentViewOnly ? 'Preview your application document in read-only mode.' : 'Use one workspace to fill application data, preview the letter, and generate a print-ready copy.'; ?></p>
            </div>
            <?php ob_start(); ?>
                <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
                <a href="document_moa.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>MOA</a>
                <a href="document_dau_moa.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>DAU MOA</a>
            <?php
            biotern_render_page_header_actions([
                'menu_id' => 'documentApplicationActionsMenu',
                'items_html' => ob_get_clean(),
            ]);
            ?>
        </div>

        <div class="application-document-builder" data-prefill-student-id="<?php echo (int)$prefill_student_id; ?>" data-prefill-company="<?php echo htmlspecialchars($prefill_company_key, ENT_QUOTES, 'UTF-8'); ?>">
            <style>
                .app-fill-line {
                    display: inline-block;
                    min-width: 180px;
                    padding: 0 4px 1px;
                    border-bottom: 1px solid currentColor;
                    line-height: 1.2;
                }
                #ap_date.app-fill-line { min-width: 130px; }
                #ap_student_address.app-fill-line { min-width: 220px; }
            </style>
            <div class="main-content">
                <div class="application-builder-grid">
                    <section class="application-builder-sidebar">
                        <div class="builder-card">
                            <div class="builder-card-head">
                                <h6>Record Source</h6>
                                <p><?php echo $documentsIsStudentViewOnly ? 'Your document is loaded from your linked student record.' : 'Load a student, pull saved application-letter data, and keep the preview updated live.'; ?></p>
                            </div>

                            <div class="builder-field">
                                <label for="student_select" class="form-label">Search Student</label>
                                <select id="student_select" class="student-select-full" data-placeholder="Search by name or student id" <?php echo $documentsIsStudentViewOnly ? 'disabled' : ''; ?>></select>
                            </div>

                            <div class="builder-field">
                                <label for="company_select" class="form-label">Search Company</label>
                                <select id="company_select" class="company-select-full" data-placeholder="Search company, address, or representative" <?php echo $documentsIsStudentViewOnly ? 'disabled' : ''; ?>></select>
                            </div>

                            <div class="builder-field-grid">
                                <div class="builder-field">
                                    <label for="input_name" class="form-label">Recipient</label>
                                    <input id="input_name" class="form-control" type="text" placeholder="Mr./Ms. full name" autocomplete="off" <?php echo $documentsIsStudentViewOnly ? 'readonly' : ''; ?>>
                                </div>
                                <div class="builder-field">
                                    <label for="input_position" class="form-label">Position</label>
                                    <input id="input_position" class="form-control" type="text" placeholder="Recipient position" autocomplete="off" <?php echo $documentsIsStudentViewOnly ? 'readonly' : ''; ?>>
                                </div>
                            </div>

                            <div class="builder-field">
                                <label for="input_company" class="form-label">Company</label>
                                <input id="input_company" class="form-control" type="text" placeholder="Company name" autocomplete="off" <?php echo $documentsIsStudentViewOnly ? 'readonly' : ''; ?>>
                            </div>

                            <div class="builder-field">
                                <label for="input_company_address" class="form-label">Company Address</label>
                                <textarea id="input_company_address" class="form-control" rows="3" placeholder="Company address" autocomplete="off" <?php echo $documentsIsStudentViewOnly ? 'readonly' : ''; ?>></textarea>
                            </div>

                            <div class="builder-field-grid builder-field-grid-compact">
                                <div class="builder-field">
                                    <label for="input_hours" class="form-label">Required Hours</label>
                                    <input id="input_hours" class="form-control" type="text" value="250" placeholder="Hours" autocomplete="off" <?php echo $documentsIsStudentViewOnly ? 'readonly' : ''; ?>>
                                </div>
                                <div class="builder-field">
                                    <label for="builder_date" class="form-label">Letter Date</label>
                                    <input id="builder_date" class="form-control" type="text" value="<?php echo htmlspecialchars(date('F j, Y'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" <?php echo $documentsIsStudentViewOnly ? 'readonly' : ''; ?>>
                                </div>
                            </div>
                        </div>

                        <div class="builder-card builder-card-note">
                            <div class="builder-card-head">
                                <h6>Unified Flow</h6>
                            </div>
                            <ul class="builder-note-list">
                                <li>Template editing now happens in the same screen.</li>
                                <li>Printing uses the live preview on the right.</li>
                                <li>Saved template layout stays separate from student values.</li>
                            </ul>
                        </div>
                    </section>

                    <section class="application-builder-canvas">
                        <div class="builder-card builder-card-editor">
                            <div class="builder-editor-head">
                                <div>
                                    <h6>Template Builder</h6>
                                    <p><?php echo $documentsIsStudentViewOnly ? 'Application letter preview only. Editing is limited to staff accounts.' : 'Application letter preview, editor, and print layout in one place.'; ?></p>
                                </div>
                                <div class="builder-editor-actions">
                                    <?php if (!$documentsIsStudentViewOnly): ?>
                                        <button id="btn_toggle_edit" class="btn btn-light" type="button" aria-pressed="false">Edit Template</button>
                                        <button id="btn_save" class="btn btn-primary" type="button">Save Template</button>
                                        <button id="btn_reset" class="btn btn-light" type="button">Reset</button>
                                    <?php endif; ?>
                                    <button id="btn_print" class="btn btn-success" type="button">Print Letter</button>
                                </div>
                            </div>

                            <div class="builder-toolbar is-disabled" id="builder_toolbar" aria-label="Template formatting tools" aria-hidden="true" <?php echo $documentsIsStudentViewOnly ? 'style="display:none;"' : ''; ?>>
                                <button id="btn_bold" class="btn btn-light" type="button"><strong>B</strong></button>
                                <button id="btn_italic" class="btn btn-light" type="button"><em>I</em></button>
                                <button id="btn_underline" class="btn btn-light" type="button"><u>U</u></button>
                                <button id="btn_left" class="btn btn-light" type="button">Left</button>
                                <button id="btn_center" class="btn btn-light" type="button">Center</button>
                                <button id="btn_right" class="btn btn-light" type="button">Right</button>
                                <button id="btn_justify" class="btn btn-light" type="button">Justify</button>
                                <button id="btn_indent" class="btn btn-light" type="button">Indent</button>
                                <button id="btn_outdent" class="btn btn-light" type="button">Outdent</button>
                                <label for="font_color">Color</label>
                                <input id="font_color" type="color" value="#000000">
                            </div>

                            <div class="builder-status-bar">
                                <span id="msg" class="builder-status-text">Template ready.</span>
                            </div>

                            <div class="builder-paper-shell">
                                <div class="builder-paper">
                                    <div id="editor" class="builder-editor-surface is-locked" contenteditable="false" spellcheck="<?php echo $documentsIsStudentViewOnly ? 'false' : 'true'; ?>"></div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <template id="application_default_template">
                <div class="a4-pages-stack" data-a4-document="true">
                    <div class="a4-page" data-a4-width-mm="210" data-a4-height-mm="297" style="width:210mm; min-height:297mm; box-sizing:border-box; padding:0.35in 0.6in 0.55in 0.6in; background:#fff;">
                        <div class="container app-application-container">
                            <div class="header app-application-header">
                                <div class="app-application-header-inner">
                                    <img class="crest app-application-crest" src="assets/images/ccstlogo.png" alt="Clark College of Science and Technology logo" data-hide-onerror="1">
                                    <div class="app-application-header-copy">
                                        <h2 class="app-application-title">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
                                        <div class="meta app-application-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
                                        <div class="tel app-application-tel">Telefax No.: (045) 624-0215</div>
                                    </div>
                                </div>
                            </div>
                            <div class="content app-application-content">
                                <h3 class="app-application-heading">Application Approval Sheet</h3>
                                <p>Date: <span id="ap_date" class="app-fill-line">__________</span></p>
                                <p>Mr./Ms.: <span id="ap_name" class="app-fill-line">__________________________</span></p>
                                <p>Position: <span id="ap_position" class="app-fill-line">__________________________</span></p>
                                <p>Name of Company: <span id="ap_company" class="app-fill-line">__________________________</span></p>
                                <p>Company Address: <span id="ap_address" class="app-fill-line">__________________________</span></p>
                                <p class="mt-30 app-application-mt-30">Dear Sir or Madam:</p>
                                <p>I am <span id="ap_student" class="app-fill-line">__________________________</span>, student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of <strong><span id="ap_hours" class="app-fill-line">250</span> hours</strong>.</p>
                                <p>I would like to apply as a trainee in your company because I believe that the training and experience, I will acquire will broaden my knowledge about my course.</p>
                                <p>Thank you for any consideration that you may give to this letter of application.</p>
                                <p class="mt-30 app-application-mt-30">Very truly yours,</p>
                                <p class="mt-40 app-application-mt-40">Student Name: <span id="ap_student_name" class="app-fill-line">__________________________</span></p>
                                <p>Student Home Address: <span id="ap_student_address" class="app-fill-line">__________________________</span></p>
                                <p>Contact No.: <span id="ap_student_contact" class="app-fill-line">__________________________</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
