<?php
// Documents page - UI to prepare Memorandum of Agreement (MOA)
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

// Simple AJAX endpoints served by this file (reuse same endpoints as application document)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    header('Content-Type: application/json');

    if ($action === 'search_students') {
        if ($documentsIsStudentViewOnly) {
            $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, student_id FROM students WHERE id = ? LIMIT 1");
            $out = [];
            if ($stmt) {
                $stmt->bind_param('i', $prefill_student_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc() ?: null;
                $stmt->close();
                if ($row) {
                    $name = trim((string)$row['first_name'] . ' ' . (!empty($row['middle_name']) ? (string)$row['middle_name'] . ' ' : '') . (string)$row['last_name']);
                    $out[] = ['id' => (int)$row['id'], 'text' => trim($name . ' - ' . (string)$row['student_id'])];
                }
            }
            echo json_encode(['results' => $out]);
            exit;
        }
        $term = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
        $gateWhere = documents_students_search_gate_sql($conn, 's');
        $sql = "SELECT id, first_name, middle_name, last_name, student_id FROM students s WHERE (CONCAT(first_name,' ',middle_name,' ',last_name) LIKE '%" . $term . "%' OR student_id LIKE '%" . $term . "%') AND {$gateWhere} ORDER BY first_name LIMIT 50";
        $res = $conn->query($sql);
        $out = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $text = trim($r['first_name'] . ' ' . ($r['middle_name'] ? $r['middle_name'] . ' ' : '') . $r['last_name']) . ' - ' . $r['student_id'];
                $out[] = ['id' => $r['id'], 'text' => $text];
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
        if ($documentsIsStudentViewOnly && $id !== $prefill_student_id) {
            echo json_encode(['access_denied' => true, 'message' => 'Document access denied.']);
            exit;
        }
        $access = documents_student_can_generate($conn, $id);
        if (empty($access['allowed'])) {
            echo json_encode(['access_denied' => true, 'message' => (string)($access['reason'] ?? 'Document access denied.')]);
            exit;
        }
        $stmt = $conn->prepare("SELECT s.*, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();
        echo json_encode($data ?: new stdClass());
        exit;
    }

    if ($action === 'get_moa' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        if ($documentsIsStudentViewOnly && $id !== $prefill_student_id) {
            echo json_encode(['access_denied' => true, 'message' => 'Document access denied.']);
            exit;
        }
        $access = documents_student_can_generate($conn, $id);
        if (empty($access['allowed'])) {
            echo json_encode(['access_denied' => true, 'message' => (string)($access['reason'] ?? 'Document access denied.')]);
            exit;
        }
        $exists = $conn->query("SHOW TABLES LIKE 'moa'");
        if (!$exists || $exists->num_rows === 0) {
            $data = biotern_company_profile_merge_moa($conn, $id, []);
            echo json_encode($data ?: new stdClass());
            exit;
        }
        $stmt = $conn->prepare("SELECT * FROM moa WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();
        $data = biotern_company_profile_merge_moa($conn, $id, $data);
        echo json_encode($data ?: new stdClass());
        exit;
    }

    echo json_encode(new stdClass());
    exit;
}

$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/documents/') !== false) ? '../' : '';

$page_title = 'MOA';
$base_href = $asset_prefix;
$page_body_class = 'application-builder-page document-builder-page moa-builder-page';
$page_styles = [
    'assets/css/layout/page_shell.css',
    'assets/css/modules/documents/document-builder-shared.css',
    'assets/css/modules/documents/documents.css',
    'assets/css/modules/documents/page-moa-document-builder.css',
    'assets/css/modules/documents/template-print-isolation.css',
];
$page_scripts = [
    'assets/js/modules/documents/document-print-preview.js',
    'assets/js/modules/documents/moa-document-builder.js',
];
include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
        <div class="page-header dashboard-page-header">
            <div class="page-header-left d-flex align-items-center">
                <div class="page-header-title">
                    <h5 class="m-b-10">MOA</h5>
                </div>
                <ul class="breadcrumb">
                    <li class="breadcrumb-item"><a href="homepage.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="index.php">Documents</a></li>
                    <li class="breadcrumb-item">MOA</li>
                </ul>
            </div>
            <?php ob_start(); ?>
                <a href="homepage.php" class="btn btn-outline-secondary"><i class="feather-home me-1"></i>Dashboard</a>
                <a href="document_application.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>Application</a>
                <a href="document_dau_moa.php" class="btn btn-outline-primary"><i class="feather-file-text me-1"></i>DAU MOA</a>
            <?php
            biotern_render_page_header_actions([
                'menu_id' => 'documentMoaActionsMenu',
                'items_html' => ob_get_clean(),
            ]);
            ?>
        </div>
<div class="application-document-builder moa-page doc-page-root" data-page="moa" data-student-view-only="<?php echo $documentsIsStudentViewOnly ? '1' : '0'; ?>" data-prefill-student-id="<?php echo intval($prefill_student_id); ?>" data-prefill-company="<?php echo htmlspecialchars($prefill_company_key, ENT_QUOTES, 'UTF-8'); ?>">
        <style>
            .moa-fill-line {
                display: inline-block;
                min-width: 170px;
                padding: 0 4px 1px;
                border-bottom: 1px solid currentColor;
                line-height: 1.2;
            }
        </style>
        <div class="main-content">
            <div class="application-builder-grid">
                <section class="application-builder-sidebar">
                    <div class="builder-card">
                        <div class="builder-card-head">
                            <h6>Record Source</h6>
                        <p><?php echo $documentsIsStudentViewOnly ? 'Your MOA is loaded from your linked student record.' : 'Search student and company records, then the MOA preview updates instantly.'; ?></p>
                        </div>

                        <div class="builder-field">
                        <label for="student_select" class="form-label">Student Name</label>
                        <select id="student_select" data-placeholder="Search by name or student id" class="student-select-full" <?php echo $documentsIsStudentViewOnly ? 'disabled' : ''; ?>></select>
                        <small class="text-muted application-source-hint">Search and select from student records.</small>
                        </div>

                        <div class="builder-field">
                            <label for="company_select" class="form-label">Company / Training Site</label>
                            <select id="company_select" data-placeholder="Search company, address, or representative" class="company-select-full"></select>
                            <small class="text-muted application-source-hint">Pick a company to auto-fill company, representative, position, and address.</small>
                        </div>

                        <div class="application-autofill-panel">
                            <div class="application-autofill-title">Company Details</div>
                            <p>These fields update from the selected company record. You can still adjust them before printing.</p>
                        </div>

                        <div class="builder-field">
                            <label class="form-label">Company Name</label>
                            <input id="moa_partner_name" class="form-control" type="text" placeholder="Partner company name">
                        </div>

                        <div class="builder-field">
                            <label class="form-label">Company Address</label>
                            <textarea id="moa_partner_address" class="form-control" rows="2" placeholder="Partner address"></textarea>
                        </div>

                        <div class="builder-field">
                            <label class="form-label">Partner Representative</label>
                            <input id="moa_partner_rep" class="form-control" type="text" placeholder="Representative (e.g. Mr. Edward Docena)">
                        </div>

                        <div class="builder-field">
                            <label class="form-label">Representative Position</label>
                            <input id="moa_partner_position" class="form-control" type="text" placeholder="Position (e.g. CEO)">
                        </div>

                        <div class="mt-2">
                            <label class="form-label">Company Receipt / Ref.</label>
                            <input id="moa_company_receipt" class="form-control form-control-sm" type="text" placeholder="Reference / receipt no.">
                        </div>

                        <div class="mt-2">
                            <label class="form-label">Total Hours (Clause #10)</label>
                            <input id="moa_total_hours" class="form-control form-control-sm" type="number" min="1" step="1" placeholder="e.g. 250">
                        </div>

                        <div class="mt-2">
                            <label class="form-label">School Representative</label>
                            <input id="moa_school_rep" class="form-control form-control-sm" type="text" placeholder="School rep (defaults to Mr. Jomar G. Sangil)">
                        </div>

                        <div class="mt-2">
                            <label class="form-label">School Representative Position</label>
                            <input id="moa_school_position" class="form-control form-control-sm" type="text" placeholder="e.g. Head of Information Technology, CCST">
                        </div>

                        <hr class="my-3">
                        <div class="mt-2">
                            <label class="form-label">Signing Place (In witness whereof)</label>
                            <input id="moa_signed_at" class="form-control form-control-sm" type="text" placeholder="City / Municipality">
                        </div>

                        <div class="row mt-2 g-2">
                            <div class="col-4">
                                <label class="form-label">Signing Day</label>
                                <input id="moa_signed_day" class="form-control form-control-sm" type="text" placeholder="DD">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Signing Month</label>
                                <input id="moa_signed_month" class="form-control form-control-sm" type="text" placeholder="Month">
                            </div>
                            <div class="col-4">
                                <label class="form-label">Signing Year</label>
                                <input id="moa_signed_year" class="form-control form-control-sm" type="text" placeholder="YYYY">
                            </div>
                        </div>

                        <div class="row mt-2 g-2">
                            <div class="col-6">
                                <label class="form-label">Witness (Partner)</label>
                                <input id="moa_presence_partner_rep" class="form-control form-control-sm" type="text" placeholder="Witness name for partner side">
                            </div>
                        </div>
                        
                        <div class="mt-2">
                            <label class="form-label">School Administrator</label>
                            <input id="moa_presence_school_admin" class="form-control form-control-sm" type="text" placeholder="Name (defaults to Mr. Ross Carvel Ramirez)">
                        </div>

                        <div class="mt-2">
                            <label class="form-label">School Administrator Position</label>
                            <input id="moa_presence_school_admin_position" class="form-control form-control-sm" type="text" placeholder="e.g. School Administrator">
                        </div>

                        <div class="mt-2">
                            <label class="form-label">Notary City</label>
                            <input id="moa_notary_city" class="form-control form-control-sm" type="text" placeholder="City where acknowledged">
                        </div>

                        <div class="row mt-2 g-2">
                            <div class="col-3">
                                <label class="form-label">Ack Day</label>
                                <input id="moa_notary_day" class="form-control form-control-sm" type="text" placeholder="DD">
                            </div>
                            <div class="col-3">
                                <label class="form-label">Ack Month</label>
                                <input id="moa_notary_month" class="form-control form-control-sm" type="text" placeholder="Month">
                            </div>
                            <div class="col-3">
                                <label class="form-label">Ack Year</label>
                                <input id="moa_notary_year" class="form-control form-control-sm" type="text" placeholder="YYYY">
                            </div>
                            <div class="col-3">
                                <label class="form-label">Ack Place</label>
                                <input id="moa_notary_place" class="form-control form-control-sm" type="text" placeholder="Place">
                            </div>
                        </div>

                        <div class="row mt-2 g-2">
                            <div class="col-3">
                                <label class="form-label">Doc No.</label>
                                <input id="moa_doc_no" class="form-control form-control-sm" type="text" placeholder="Doc no">
                            </div>
                            <div class="col-3">
                                <label class="form-label">Page No.</label>
                                <input id="moa_page_no" class="form-control form-control-sm" type="text" placeholder="Page no">
                            </div>
                            <div class="col-3">
                                <label class="form-label">Book No.</label>
                                <input id="moa_book_no" class="form-control form-control-sm" type="text" placeholder="Book no">
                            </div>
                            <div class="col-3">
                                <label class="form-label">Series</label>
                                <input id="moa_series_no" class="form-control form-control-sm" type="text" placeholder="Year/series">
                            </div>
                        </div>

                        <div class="mt-3 d-flex gap-2">
                            <button id="btn_print_moa" type="button" class="btn btn-success flex-grow-1">Generate / Print MOA</button>
                        </div>
                    </div>
                </section>

                <section class="application-builder-canvas">
                    <div class="builder-card builder-card-editor">
                    <div class="moa-builder-controls">
                        <div class="builder-editor-head">
                            <div>
                                <h6>Template Builder</h6>
                                <p>MOA preview, editor, and print layout in one place.</p>
                            </div>
                            <div class="builder-editor-actions">
                            <button id="btn_toggle_edit" class="btn btn-light" type="button" aria-pressed="false" <?php echo $documentsIsStudentViewOnly ? 'style="display:none;"' : ''; ?>>Edit Template</button>
                            <button id="btn_save" class="btn btn-primary" type="button" <?php echo $documentsIsStudentViewOnly ? 'style="display:none;"' : ''; ?>>Save Template</button>
                            <button id="btn_reset" class="btn btn-light" type="button" <?php echo $documentsIsStudentViewOnly ? 'style="display:none;"' : ''; ?>>Reset</button>
                            <button id="btn_print" class="btn btn-success" type="button">Print MOA</button>
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
                            <label for="font_size_pt">Size</label>
                            <input id="font_size_pt" type="number" min="6" max="96" step="1" value="12" title="Double-click for custom size">
                            <button id="btn_apply_size" class="btn btn-light" type="button">Apply</button>
                            <label for="font_color">Color</label>
                            <input id="font_color" type="color" value="#000000">
                        </div>
                        <div class="builder-status-bar">
                            <span id="msg" class="builder-status-text">Template ready.</span>
                        </div>
                    </div>
                    <div class="doc-preview" id="moa_preview">
                        <div id="moa_content" class="a4-pages-stack">
                            <div class="a4-page" data-a4-width-mm="210" data-a4-height-mm="297" style="width:210mm; min-height:297mm; box-sizing:border-box; padding:0.24in 0.45in 0.4in 0.45in; background:#fff;">
                            <h5 class="text-center-inline">Memorandum of Agreement</h5>
                            <p>Date: <span id="moa_date" class="moa-fill-line">&nbsp;</span></p>
                            <p>This Memorandum of Agreement made and executed between: <strong>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</strong>, a Higher Education Institution, duly organized and existing under Philippine Laws with office/business address at <strong>AUREA ST. SAMSONVILLE, DAU MABALACAT CITY PAMPANGA</strong> represented herein by <strong>MR. JOMAR G. SANGIL (IT, DEPARTMENT HEAD)</strong>, hereinafter referred to as the Higher Education Institution.</p>
                            <p>And</p>
                            <p><strong><span id="pv_partner_company_name" class="moa-fill-line">&nbsp;</span></strong>, an enterprise duly organized and existing under Philippine Laws with office/business address at <strong><span id="pv_partner_address" class="moa-fill-line">&nbsp;</span></strong>, represented herein by <strong><span id="pv_partner_name" class="moa-fill-line">&nbsp;</span></strong>, hereinafter referred to as the Partner Company.</p>
                            <p>Company Receipt / Ref.: <span id="pv_company_receipt" class="moa-fill-line">&nbsp;</span></p>
                            Witnesseth: <br><br>
                            <p>The parties hereby bind themselves to undertake a Memorandum of Agreement for the purpose of supporting the HEI Internship for Learners under the following terms and condition</p>
                            <strong>Clark College of Science and Technology:</strong><br>
                            <ol>
                                <li>The <b>Clark College of Science and Technology</b> shall be responsible for briefing the Learners as part of the HEI's and Job Induction Program;</li>
                                <li>The <b>Clark College of Science and Technology</b> shall provide the learner undergoing the INTERNSHIP with the basic orientation on work values, behavior, and discipline to ensure smooth cooperation with the <strong>PARTNER COMPANY</strong>.</li>
                                <li>The <b>Clark College of Science and Technology</b> shall issue an official endorsement vouching for the well-being of the learner, which shall be used by the <strong>PARTNER COMPANY</strong> for processing the learner's application for INTERNSHIP;</li>
                                <li>The <b>Clark College of Science and Technology</b> shall voluntarily withdraw a Learner who of the PARTNER COMPANY can impose necessary HEI sanctions to the said learner;</li>
                                <li>The <b>Clark College of Science and Technology</b> through its Industry Coordinator shall make onsite sit/follow ups to the <strong>PARTNER COMPANY</strong> during the training period and evaluate the Learner's progress based on the training plan and discuss training problems;</li>
                                <li>The <b>Clark College of Science and Technology</b> has the discretion to pull out the Learner if there is an apparent risk and/or exploitation on the rights of the Learner;</li>
                                <li>The <b>Clark College of Science and Technology</b> shall ensure that the Learner shall ensure that the Learner has an on-and off the campus insurance coverage within the duration of the training as part of their training fee.</li>
                                <li>The <b>Clark College of Science and Technology</b> shall ensure Learner shall be personally responsible for any and all liabilities arising from negligence in the performance of his/her duties and functions while under INTERNSHIP;</li>
                                <li>There is no employer-employee relationship between the <strong>PARTNER COMPANY</strong> and the Learner;</li>
                                <li>The duration of the program shall be equivalent to <span id="pv_total_hours">250</span> working hours unless otherwise agreed upon by the <strong>PARTNER COMPANY</strong> and the Clark College of Science and Technology;</li>
                                <li>Any violation of the foregoing covenants will warrant the cancellation of the Memorandum of Agreement by the <strong>PARTNER COMPANY</strong> within thirty (30) days upon notice to the Clark College of Science and Technology.</li>
                                <li>The <strong>PARTNER COMPANY</strong> may grant allowance to the learner in accordance with the partner enterprise's existing rules and regulations;</li>
                                <li>The <strong>PARTNER COMPANY</strong> is not allowed to employ Learner within the INTERNSHIP period in order for the Learner to graduate from the program he/she is enrolled in.</li>
                            </ol>

                            </div>

                            <div class="a4-page" data-a4-width-mm="210" data-a4-height-mm="297" style="width:210mm; min-height:297mm; box-sizing:border-box; padding:0.24in 0.45in 0.4in 0.45in; background:#fff;">
                            <p class="mt-12">
                                This Memorandum of Agreement shall become effective upon signature of both parties and implementation will begin immediately and shall continue to be valid hereafter until written notice is given by either party thirty (30) days prior to the date of intended termination.
                            </p>
                            <p>
                                In witness whereof the parties have signed this Memorandum of Agreement at <span id="pv_signed_at" class="moa-fill-line">&nbsp;</span> this <span id="pv_signed_day" class="moa-fill-line">&nbsp;</span> day of <span id="pv_signed_month" class="moa-fill-line">&nbsp;</span>, <span id="pv_signed_year" class="moa-fill-line">&nbsp;</span>.
                            </p>

                            <div class="flex-between-gap12 mt-24">
                                <div class="flex-1">
                                    <p>For the PARTNER COMPANY</p>
                                    <p class="mt-40"><strong id="pv_partner_rep"></strong></p>
                                    <p id="pv_partner_position"></p>
                                </div>
                                <div class="flex-1 text-right">
                                    <p class="mr-60"><strong>For the SCHOOL</strong></p>
                                    <p class="mt-40 text-right"><strong id="pv_school_rep"></strong></p>
                                    <p class="mt-neg18 text-right" id="pv_school_position"></p>
                                </div>
                            </div>

                            <p class="mt-24"><strong>SIGNED IN THE PRESENCE OF:</strong></p>

                            <div class="flex-between-gap12 mt-16">
                                <div class="flex-1">
                                    <p class="mt-40"><span id="pv_presence_partner_rep"></span></p>
                                    <p>Representative for the PARTNER COMPANY</p>
                                </div>
                                <div class="flex-1 text-right">
                                    <p class="mt-40 text-right"><span id="pv_presence_school_admin"></span></p>
                                    <p class="mt-neg18 text-right" id="pv_presence_school_admin_position"></p>
                                </div>
                            </div>

                            <p class="mt-24"><strong>ACKNOWLEDGEMENT</strong></p>
                            <p>
                                Before me, a Notary Public in the city <span id="pv_notary_city">__________________</span>, personally appeared <strong><u><span id="pv_notary_appeared_1">__________________</span></u></strong> known to me to be the same persons who executed the foregoing instrument and they acknowledged to me that the same is their free will and voluntary deed and that of the ENTERPRISEs herein represented. Witness my hand and seal on this <span id="pv_notary_day">_____</span> day of <span id="pv_notary_month">__________________</span> <span id="pv_notary_year">20___</span> in <span id="pv_notary_place">__________________</span>.
                            </p>

                            <p class="mt-16">Doc No. <span id="pv_doc_no">______</span>:</p>
                            <p>Page No. <span id="pv_page_no">_____</span>:</p>
                            <p>Book No. <span id="pv_book_no">_____</span>:</p>
                            <p>Series of <span id="pv_series_no">_____</span>:</p>
                            </div>

                        </div>
                    </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    </div>
</main>
<?php if ($documentsIsStudentViewOnly): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.application-builder-sidebar input, .application-builder-sidebar textarea, .application-builder-sidebar select').forEach(function (node) {
        node.disabled = true;
        node.readOnly = true;
    });
});
</script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
