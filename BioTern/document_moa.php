<?php
// Documents page - UI to prepare Memorandum of Agreement (MOA)

$host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'biotern_db';

try {
    $conn = new mysqli($host, $db_user, $db_password, $db_name);
    if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}
$prefill_student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Simple AJAX endpoints served by this file (reuse same endpoints as application document)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    header('Content-Type: application/json');

    if ($action === 'search_students') {
        $term = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
        $sql = "SELECT id, first_name, middle_name, last_name, student_id FROM students WHERE CONCAT(first_name,' ',middle_name,' ',last_name) LIKE '%" . $term . "%' OR student_id LIKE '%" . $term . "%' ORDER BY first_name LIMIT 50";
        $res = $conn->query($sql);
        $out = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $text = trim($r['first_name'] . ' ' . ($r['middle_name'] ? $r['middle_name'] . ' ' : '') . $r['last_name']) . ' — ' . $r['student_id'];
                $out[] = ['id' => $r['id'], 'text' => $text];
            }
        }
        echo json_encode(['results' => $out]);
        exit;
    }

    if ($action === 'get_student' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
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
        $exists = $conn->query("SHOW TABLES LIKE 'moa'");
        if (!$exists || $exists->num_rows === 0) {
            echo json_encode(new stdClass());
            exit;
        }
        $stmt = $conn->prepare("SELECT * FROM moa WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();
        echo json_encode($data ?: new stdClass());
        exit;
    }

    echo json_encode(new stdClass());
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || MOA</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        .moa-main.nxl-container { display:flex; flex-direction:column; }
        .moa-content.nxl-content { flex:1; padding-bottom:24px; }
        .doc-preview { background:#fff; border:1px solid #eee; padding:24px; max-width:800px; margin-top:18px; margin-bottom:32px; position:relative; z-index:1; box-shadow:0 6px 20px rgba(0,0,0,0.04); }
        .doc-preview .text-center { padding-top:40px; }
        .select2-container--open { z-index: 9999999 !important; }
        .select2-dropdown { z-index: 9999999 !important; }
        .select2-container .select2-search__field { padding: 4px !important; margin: 0 !important; height: auto !important; border: 0 !important; box-shadow: none !important; background: transparent !important; }
        .select2-container .select2-selection__rendered, .select2-container .select2-selection__placeholder { visibility: hidden !important; }
        .select2-overlay-input { position: absolute; inset: 0 40px 0 8px; width: calc(100% - 48px); height: calc(100% - 8px); border: none; background: transparent; padding: 6px 8px; box-sizing: border-box; z-index: 99999999; font: inherit; color: inherit; }
        .select2-overlay-input:focus { outline: none; }
        /* Dark mode: make Select2 input readable */
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

        /* Dark mode: preview panel compatibility */
        html.app-skin-dark .doc-preview {
            background: #0f172a !important;
            border-color: #1b2436 !important;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        }
        html.app-skin-dark .doc-preview h5,
        html.app-skin-dark .doc-preview h6,
        html.app-skin-dark .doc-preview p,
        html.app-skin-dark .doc-preview div,
        html.app-skin-dark .doc-preview span,
        html.app-skin-dark .doc-preview li,
        html.app-skin-dark .doc-preview strong,
        html.app-skin-dark .doc-preview b {
            color: #dbe5f1 !important;
        }
        html.app-skin-dark .doc-preview .text-muted {
            color: #9fb0c6 !important;
        }
        .moa-main.nxl-container { padding-top: 90px; }
        .nxl-header { position: fixed !important; top: 0; left: 0; right: 0; z-index: 2147483647 !important; }
        .nxl-navigation { z-index: 2147483646; }
        .footer { position: static !important; }
        .nxl-container { min-height: auto !important; }
        .nxl-container .nxl-content { padding-bottom: 0 !important; }
        .footer { margin-bottom: 0 !important; }
        .file-edit-active #moa_content {
            outline: 2px dashed #3b82f6;
            outline-offset: 6px;
            background: rgba(59, 130, 246, 0.04);
        }
        #moa_content[contenteditable="true"] {
            cursor: text;
            user-select: text;
            -webkit-user-select: text;
        }
        @media (max-width: 1024px) {
            .nxl-navigation,
            .nxl-navigation.mob-navigation-active { z-index: 2147483646 !important; }
            .nxl-header { z-index: 2147483647 !important; }
            .nxl-container { position: relative; z-index: 1; }
            .doc-preview { z-index: 1 !important; }
            .select2-container--open,
            .select2-dropdown { z-index: 900 !important; }
            .nxl-navigation { z-index: 2147483648 !important; }
            .nxl-navigation .navbar-wrapper { z-index: 2147483648 !important; }
            .nxl-navigation .m-header {
                min-height: 96px;
                padding: 14px 18px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .nxl-navigation .m-header .b-brand {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .nxl-navigation .m-header .logo.logo-lg {
                display: block !important;
                width: min(84vw, 240px) !important;
                height: auto !important;
                max-height: 56px !important;
                object-fit: contain;
            }
            .nxl-navigation .m-header .logo.logo-sm {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <main class="nxl-container moa-main">
        <div class="nxl-content container moa-content">
            <div class="row mt-3">
                <div class="col-12">
                    <h4>Memorandum of Agreement</h4>
                    <p class="text-muted">Fill the fields below then click Generate MOA to open the printable Memorandum of Agreement.</p>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card p-3">

                        <div class="mt-3">
                            <label class="form-label">Company Name</label>
                            <input id="moa_partner_name" class="form-control form-control-sm" type="text" placeholder="Partner company name">
                        </div>

                        <div class="mt-2">
                            <label class="form-label">Company Address</label>
                            <textarea id="moa_partner_address" class="form-control form-control-sm" rows="2" placeholder="Partner address"></textarea>
                        </div>

                        <div class="mt-2">
                            <label class="form-label">Partner Representative</label>
                            <input id="moa_partner_rep" class="form-control form-control-sm" type="text" placeholder="Representative (e.g. Mr. Edward Docena)">
                        </div>

                        <div class="mt-2">
                            <label class="form-label">Representative Position</label>
                            <input id="moa_partner_position" class="form-control form-control-sm" type="text" placeholder="Position (e.g. CEO)">
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
                            <button id="btn_file_edit_moa" type="button" class="btn btn-primary flex-grow-0">File Edit</button>
                            <button id="btn_generate_moa" type="button" class="btn btn-success flex-grow-1">Generate MOA</button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="doc-preview" id="moa_preview">
                        <div id="moa_content">
                            <h5 style="text-align:center;">Memorandum of Agreement</h5>
                            <p>Date: <span id="moa_date">__________</span></p>
                            <p>This Memorandum of Agreement made and executed between: <strong>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</strong>a Higher Education Institution, duly organized and existing under Philippine Laws with office/business address at <strong>AUREA ST. SAMSONVILLE, DAU MABALACAT CITY PAMPANGA</strong> represented herein by <strong>MR. JOMAR G. SANGIL (IT, DEPARTMENT HEAD)</strong>, here in after referred to as the Higher Education Institution.<strong></p>
                            And<br>
                            <strong><span id="pv_partner_company_name">__________________________</span></strong> an enterprise duly organized and existing under Philippine Laws with office/ business address at <strong><span id="pv_partner_address">__________________________</span></strong>represented herein by <strong><span id="pv_partner_name">__________________________</span></strong> herein after referred to as the PARTNER COMPANY.</p>
                            <p>Company Receipt / Ref.: <span id="pv_company_receipt">__________________________</span></p>
                            Witnesseth: <br><br>
                            <p>The parties hereby bind themselves to undertake a Memorandum of Agreement for the purpose of supporting the HEI Internship for Learners under the following terms and condition</p>
                            <strong>Clark College of Science and Technology:</strong><br>
                            <ol>
                                <li>The <b>Clark College of Science and Technology</b> shall be responsible for briefing the Learners as part of the HEI’s and Job Induction Program;</li>
                                <li>The <b>Clark College of Science and Technology</b> shall provide the learner undergoing the INTERNSHIP with the basic orientation on work values, behavior, and discipline to ensure smooth cooperation with the <strong>PARTNER COMPANY</strong>.</li>
                                <li>The <b>Clark College of Science and Technology</b> shall issue and official endorsement vouching for the well-being of the Learner which shall be used by the <strong>PARTNER COMPANY</strong>. for the processing the learner’s application for INTERNSHIP;</li>
                                <li>The <b>Clark College of Science and Technology</b> shall voluntarily withdraw a Learner who of the PARTNER COMPANY can impose necessary HEI sanctions to the said learner;</li>
                                <li>The <b>Clark College of Science and Technology</b> through its Industry Coordinator shall make onsite sit/follow ups to the <strong>PARTNER COMPANY</strong> during the training period and evaluate the Learner’s progress based on the training plan and discuss training problems;</li>
                                <li>The <b>Clark College of Science and Technology</b> has the discretion to pull out the Learner if there is an apparent risk and/or exploitation on the rights of the Learner;</li>
                                <li>The <b>Clark College of Science and Technology</b> shall ensure that the Learner shall ensure that the Learner has an on-and off the campus insurance coverage within the duration of the training as part of their training fee.</li>
                                <li>The <b>Clark College of Science and Technology</b> shall ensure Learner shall be personally responsible for any and all liabilities arising from negligence in the performance of his/her duties and functions while under INTERNSHIP;</li>
                                <li>There is no employer-employee relationship between the <strong>PARTNER COMPANY</strong> and the Learner;</li>
                                <li>The duration of the program shall be equivalent to <span id="pv_total_hours">250</span> working hours unless otherwise agreed upon by the <strong>PARTNER COMPANY</strong> and the Clark College of Science and Technology;</li>
                                <li>Any violation of the foregoing covenants will warrant the cancellation of the Memorandum of Agreement by the <strong>PARTNER COMPANY</strong> within thirty (30) days upon notice to the Clark College of Science and Technology.</li>
                                <li>The <strong>PARTNER COMPANY</strong> may grant allowance to Learner in accordance with the PARTNER ENTERPISE’S existing rules and regulations;</li>
                                <li>The <strong>PARTNER COMPANY</strong> is not allowed to employ Learner within the INTERNSHIP period in order for the Learner to graduate from the program he/she is enrolled in.</li>
                            </ol>

                            <p style="margin-top:12px;">
                                This Memorandum of Agreement shall become effective upon signature of both parties and implementation will begin immediately and shall continue to be valid hereafter until written notice is given by either party thirty (30) days prior to the date of intended termination.
                            </p>
                            <p>
                                In witness whereof the parties have signed this Memorandum of Agreement at <span id="pv_signed_at">__________________</span> this <span id="pv_signed_day">_____</span> day of <span id="pv_signed_month">__________________</span>, <span id="pv_signed_year">20__</span>.
                            </p>

                            <div style="display:flex; justify-content:space-between; gap:12px; margin-top:24px;">
                                <div style="flex:1;">
                                    <p>For the PARTNER COMPANY</p>
                                    <p style="margin-top:40px;"><strong id="pv_partner_rep"></strong></p>
                                    <p id="pv_partner_position"></p>
                                </div>
                                <div style="flex:1; text-align:right;">
                                    <p style="margin-right: 60px;"><strong>For the SCHOOL</strong></p>
                                    <p style="margin-top:40px; text-align:right;"><strong id="pv_school_rep"></strong></p>
                                    <p style="margin-top:-18px; text-align:right;" id="pv_school_position"></p>
                                </div>
                            </div>

                            <p style="margin-top:24px;"><strong>SIGNED IN THE PRESENCE OF:</strong></p>

                            <div style="display:flex; justify-content:space-between; gap:12px; margin-top:16px;">
                                <div style="flex:1;">
                                    <p style="margin-top:40px;"><span id="pv_presence_partner_rep"></span></p>
                                    <p>Representative for the PARTNER COMPANY</p>
                                </div>
                                <div style="flex:1; text-align:right;">
                                    <p style="margin-top:40px; text-align:right;"><span id="pv_presence_school_admin"></span></p>
                                    <p style="margin-top:-18px; text-align:right;" id="pv_presence_school_admin_position"></p>
                                </div>
                            </div>

                            <p style="margin-top:24px;"><strong>ACKNOWLEDGEMENT</strong></p>
                            <p>
                                Before me, a Notary Public in the city <span id="pv_notary_city">__________________</span>, personally appeared <strong><u><span id="pv_notary_appeared_1">__________________</span></u></strong> known to me to be the same persons who executed the foregoing instrument and they acknowledged to me that the same is their free will and voluntary deed and that of the ENTERPRISEs herein represented. Witness my hand and seal on this <span id="pv_notary_day">_____</span> day of <span id="pv_notary_month">__________________</span> <span id="pv_notary_year">20___</span> in <span id="pv_notary_place">__________________</span>.
                            </p>

                            <p style="margin-top:16px;">Doc No. <span id="pv_doc_no">______</span>:</p>
                            <p>Page No. <span id="pv_page_no">_____</span>:</p>
                            <p>Book No. <span id="pv_book_no">_____</span>:</p>
                            <p>Series of <span id="pv_series_no">_____</span>:</p>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/vendors/js/vendors.min.js"></script>
    <script src="assets/vendors/js/select2.min.js"></script>
    <script>
        (function(){
            const MOA_TEMPLATE_STORAGE_KEY = 'biotern_moa_template_html_v1';
            const PREFILL_STUDENT_ID = <?php echo intval($prefill_student_id); ?>;
            const select = $('#student_select');
            const partnerName = document.getElementById('moa_partner_name');
            const partnerRep = document.getElementById('moa_partner_rep');
            const partnerPosition = document.getElementById('moa_partner_position');
            const partnerAddress = document.getElementById('moa_partner_address');
            const companyReceipt = document.getElementById('moa_company_receipt');
            const totalHours = document.getElementById('moa_total_hours');
            const schoolRep = document.getElementById('moa_school_rep');
            const schoolPosition = document.getElementById('moa_school_position');
            const signedAt = document.getElementById('moa_signed_at');
            const signedDay = document.getElementById('moa_signed_day');
            const signedMonth = document.getElementById('moa_signed_month');
            const signedYear = document.getElementById('moa_signed_year');
            const presencePartnerRep = document.getElementById('moa_presence_partner_rep');
            const presenceSchoolAdmin = document.getElementById('moa_presence_school_admin');
            const presenceSchoolAdminPosition = document.getElementById('moa_presence_school_admin_position');
            const notaryCity = document.getElementById('moa_notary_city');
            const notaryAppeared1 = document.getElementById('moa_notary_appeared_1');
            const notaryAppeared2 = document.getElementById('moa_notary_appeared_2');
            const notaryDay = document.getElementById('moa_notary_day');
            const notaryMonth = document.getElementById('moa_notary_month');
            const notaryYear = document.getElementById('moa_notary_year');
            const notaryPlace = document.getElementById('moa_notary_place');
            const docNo = document.getElementById('moa_doc_no');
            const pageNo = document.getElementById('moa_page_no');
            const bookNo = document.getElementById('moa_book_no');
            const seriesNo = document.getElementById('moa_series_no');
            const btnFill = document.getElementById('btn_file_edit_moa');
            const btnGenerate = document.getElementById('btn_generate_moa');
            const moaContent = document.getElementById('moa_content');
            let isFileEditMode = false;
            let hasLoadedSavedTemplate = false;

            function withOrdinalSuffix(dayValue){
                const raw = (dayValue || '').toString().trim();
                if (!raw) return raw;
                if (!/^\d+$/.test(raw)) return raw;
                const n = parseInt(raw, 10);
                if (isNaN(n)) return raw;
                const mod100 = n % 100;
                let suffix = 'th';
                if (mod100 < 11 || mod100 > 13) {
                    const mod10 = n % 10;
                    if (mod10 === 1) suffix = 'st';
                    else if (mod10 === 2) suffix = 'nd';
                    else if (mod10 === 3) suffix = 'rd';
                }
                return String(n) + suffix;
            }

            function saveMoaTemplateHtml() {
                if (!moaContent) return;
                try { localStorage.setItem(MOA_TEMPLATE_STORAGE_KEY, moaContent.innerHTML); } catch (err) {}
            }

            function loadMoaTemplateHtml() {
                if (!moaContent) return false;
                try {
                    const saved = localStorage.getItem(MOA_TEMPLATE_STORAGE_KEY);
                    if (!saved) return false;
                    moaContent.innerHTML = saved;
                    hasLoadedSavedTemplate = true;
                    return true;
                } catch (err) {
                    return false;
                }
            }

            function openMoaEditor(e) {
                if (e && typeof e.preventDefault === 'function') e.preventDefault();
                window.open('edit_moa.php', '_blank');
                return false;
            }
            document.addEventListener('click', function(e){
                const editBtn = e.target.closest('#btn_file_edit_moa');
                if (!editBtn) return;
                openMoaEditor(e);
            });

            select.select2({
                placeholder: '',
                ajax: {
                    url: 'document_moa.php',
                    dataType: 'json',
                    delay: 250,
                    data: function(params){ return { action: 'search_students', q: params.term }; },
                    processResults: function(data){ return { results: data.results }; }
                },
                minimumInputLength: 1,
                width: 'resolve',
                dropdownParent: $(document.body),
                dropdownCssClass: 'select2-dropdown'
            });

            // overlay input for select2 (same UX as application)
            (function createOverlayInput(){
                var sel = document.getElementById('student_select');
                var container = sel && sel.nextElementSibling;
                if (!container) return;
                container.style.position = container.style.position || 'relative';

                var overlay = document.createElement('input');
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

                overlay.addEventListener('input', function(){ openAndSync(); });
                overlay.addEventListener('keydown', function(e){ if (e.key && (e.key.length === 1 || e.key === 'Backspace')) openAndSync(); });

                $(document).on('select2:select select2:closing', '#student_select', function(e){ setTimeout(function(){ var txt = $('#student_select').find('option:selected').text() || ''; overlay.value = txt.replace(/\s+—\s+.*$/,''); }, 0); });
                container.addEventListener('click', function(){ overlay.focus(); });
            })();

            // fetch and fill student (optional) into preview
            $('#student_select').on('select2:select', function(e){
                const id = select.val();
                if (!id) return;
                fetch('document_moa.php?action=get_student&id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .then(data => {
                        if (!data) return;
                        const fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                        // place student name in preview if desired (not required for MOA header)
                        loadMoaData(id);
                    });
            });

            function loadMoaData(id){
                if (!id) return;
                fetch('document_moa.php?action=get_moa&id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .then(data => {
                        if (!data || typeof data !== 'object') return;
                        partnerName.value = (data.company_name || '').toString();
                        partnerAddress.value = (data.company_address || '').toString();
                        partnerRep.value = (data.partner_representative || '').toString();
                        partnerPosition.value = (data.position || '').toString();
                        schoolRep.value = (data.coordinator || '').toString();
                        schoolPosition.value = (data.school_posistion || data.school_position || '').toString();
                        signedAt.value = (data.moa_address || '').toString();
                        // Witness from MOA data should populate both witness textbox and acknowledgement appeared field.
                        presencePartnerRep.value = (data.witness || '').toString();
                        presenceSchoolAdmin.value = (data.school_administrator || '').toString();
                        presenceSchoolAdminPosition.value = (data.school_admin_position || '').toString();
                        notaryCity.value = (data.notary_address || '').toString();
                        notaryPlace.value = (data.acknowledgement_address || '').toString();
                        companyReceipt.value = (data.company_receipt || '').toString();
                        totalHours.value = (data.total_hours || '').toString();

                        if (data.moa_date) {
                            const d = new Date(data.moa_date);
                            if (!isNaN(d.getTime())) {
                                signedDay.value = String(d.getDate()).padStart(2, '0');
                                signedMonth.value = d.toLocaleString('en-US', { month: 'long' });
                                signedYear.value = String(d.getFullYear());
                            }
                        }
                        if (data.acknowledgement_date) {
                            const ad = new Date(data.acknowledgement_date);
                            if (!isNaN(ad.getTime())) {
                                notaryDay.value = String(ad.getDate()).padStart(2, '0');
                                notaryMonth.value = ad.toLocaleString('en-US', { month: 'long' });
                                notaryYear.value = String(ad.getFullYear());
                            }
                        }
                        // Witness should appear in ACKNOWLEDGEMENT (personally appeared...)
                        if (notaryAppeared1) {
                            notaryAppeared1.value = (data.witness || '').toString();
                        }

                        updatePreview();
                        updateGenerateLink();
                    })
                    .catch(() => {});
            }

            function prefillByStudentId(id){
                if (!id) return;
                fetch('document_moa.php?action=get_student&id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .then(data => {
                        if (!data || !data.id) return;
                        // select2 may not be present in this page layout; guard usage
                        if (select && select.length) {
                            const fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                            const label = (fullname || 'Student') + ' — ' + (data.student_id || id);
                            const option = new Option(label, String(id), true, true);
                            select.append(option).trigger('change');
                        }
                        loadMoaData(id);
                    })
                    .catch(() => {});
            }

            function updatePreview(){
                if (isFileEditMode) return;
                document.getElementById('pv_partner_company_name').textContent = partnerName.value || '__________________________';
                document.getElementById('pv_partner_name').textContent = partnerRep.value || '__________________________';
                document.getElementById('pv_partner_address').textContent = partnerAddress.value || '__________________________';
                document.getElementById('pv_company_receipt').textContent = companyReceipt.value || '__________________________';
                document.getElementById('pv_total_hours').textContent = (totalHours && totalHours.value) ? totalHours.value : '250';
                document.getElementById('pv_partner_rep').textContent = partnerRep.value || '__________________________';
                document.getElementById('pv_partner_position').textContent = partnerPosition.value || '______________, ';
                document.getElementById('pv_school_rep').textContent = schoolRep.value || '__________________';
                document.getElementById('pv_signed_at').textContent = signedAt.value || '__________________';
                document.getElementById('pv_signed_day').textContent = withOrdinalSuffix(signedDay.value) || '_____';
                document.getElementById('pv_signed_month').textContent = signedMonth.value || '__________________';
                document.getElementById('pv_signed_year').textContent = signedYear.value || '20__';
                document.getElementById('pv_presence_partner_rep').textContent = presencePartnerRep.value || '______________________________';
                document.getElementById('pv_presence_school_admin').textContent = presenceSchoolAdmin.value || '______________________';
                document.getElementById('pv_presence_school_admin_position').textContent = presenceSchoolAdminPosition.value || '______________________';
                document.getElementById('pv_school_position').textContent = schoolPosition.value || '__________________';
                document.getElementById('pv_notary_city').textContent = notaryCity.value || '__________________';
                const appeared1Value = (notaryAppeared1 && notaryAppeared1.value) || (presencePartnerRep && presencePartnerRep.value) || '';
                const pvNotaryAppeared1 = document.getElementById('pv_notary_appeared_1');
                if (pvNotaryAppeared1) pvNotaryAppeared1.textContent = appeared1Value || '__________________';
                const pvNotaryAppeared2 = document.getElementById('pv_notary_appeared_2');
                if (pvNotaryAppeared2) pvNotaryAppeared2.textContent = (notaryAppeared2 && notaryAppeared2.value) || '__________________';
                document.getElementById('pv_notary_day').textContent = withOrdinalSuffix(notaryDay.value) || '_____';
                document.getElementById('pv_notary_month').textContent = notaryMonth.value || '__________________';
                document.getElementById('pv_notary_year').textContent = notaryYear.value || '20___';
                document.getElementById('pv_notary_place').textContent = notaryPlace.value || '__________________';
                document.getElementById('pv_doc_no').textContent = docNo.value || '______';
                document.getElementById('pv_page_no').textContent = pageNo.value || '_____';
                document.getElementById('pv_book_no').textContent = bookNo.value || '_____';
                document.getElementById('pv_series_no').textContent = seriesNo.value || '_____';
                document.getElementById('moa_date').textContent = new Date().toLocaleDateString();
            }

            function updateGenerateLink(){
                const params = new URLSearchParams();
                if (partnerName.value) params.set('partner_name', partnerName.value);
                if (partnerRep.value) params.set('partner_rep', partnerRep.value);
                if (partnerPosition.value) params.set('partner_position', partnerPosition.value);
                if (partnerAddress.value) params.set('partner_address', partnerAddress.value);
                if (companyReceipt.value) params.set('company_receipt', companyReceipt.value);
                if (totalHours && totalHours.value) params.set('total_hours', totalHours.value);
                if (schoolRep.value) params.set('school_rep', schoolRep.value);
                if (schoolPosition && schoolPosition.value) params.set('school_position', schoolPosition.value);
                if (signedAt.value) params.set('signed_at', signedAt.value);
                if (signedDay.value) params.set('signed_day', withOrdinalSuffix(signedDay.value));
                if (signedMonth.value) params.set('signed_month', signedMonth.value);
                if (signedYear.value) params.set('signed_year', signedYear.value);
                if (presencePartnerRep.value) params.set('presence_partner_rep', presencePartnerRep.value);
                if (presenceSchoolAdmin.value) params.set('presence_school_admin', presenceSchoolAdmin.value);
                if (presenceSchoolAdminPosition && presenceSchoolAdminPosition.value) params.set('presence_school_admin_position', presenceSchoolAdminPosition.value);
                if (notaryCity.value) params.set('notary_city', notaryCity.value);
                if (notaryAppeared1 && notaryAppeared1.value) params.set('notary_appeared_1', notaryAppeared1.value);
                else if (presencePartnerRep && presencePartnerRep.value) params.set('notary_appeared_1', presencePartnerRep.value);
                if (notaryAppeared2 && notaryAppeared2.value) params.set('notary_appeared_2', notaryAppeared2.value);
                if (notaryDay.value) params.set('notary_day', withOrdinalSuffix(notaryDay.value));
                if (notaryMonth.value) params.set('notary_month', notaryMonth.value);
                if (notaryYear.value) params.set('notary_year', notaryYear.value);
                if (notaryPlace.value) params.set('notary_place', notaryPlace.value);
                if (docNo.value) params.set('doc_no', docNo.value);
                if (pageNo.value) params.set('page_no', pageNo.value);
                if (bookNo.value) params.set('book_no', bookNo.value);
                if (seriesNo.value) params.set('series_no', seriesNo.value);
                params.set('date', new Date().toLocaleDateString());
                const url = 'generate_moa.php?' + params.toString();
                btnGenerate.dataset.url = url;
                return url;
            }

            [
                partnerName, partnerRep, partnerPosition, partnerAddress, companyReceipt, totalHours, schoolRep, schoolPosition,
                signedAt, signedDay, signedMonth, signedYear,
                presencePartnerRep, presenceSchoolAdmin, presenceSchoolAdminPosition,
                notaryCity, notaryAppeared1, notaryAppeared2,
                notaryDay, notaryMonth, notaryYear, notaryPlace,
                docNo, pageNo, bookNo, seriesNo
            ].forEach(function(el){ if (!el) return; el.addEventListener('input', function(){ updatePreview(); updateGenerateLink(); }); });

            btnGenerate.addEventListener('click', function(){
                const url = updateGenerateLink();
                if (!url) return;
                window.location.href = url;
            });

            // fallback mobile sidebar toggler for pages where template markup/scripts load later
            document.addEventListener('click', function(e){
                const toggle = e.target.closest('#mobile-collapse');
                if (!toggle) return;
                e.preventDefault();

                const nav = document.querySelector('.nxl-navigation');
                if (!nav) return;

                nav.classList.toggle('mob-navigation-active');

                let overlay = document.querySelector('.nxl-md-overlay');
                if (nav.classList.contains('mob-navigation-active')) {
                    if (!overlay) {
                        overlay = document.createElement('div');
                        overlay.className = 'nxl-md-overlay';
                        document.body.appendChild(overlay);
                    }
                    overlay.onclick = function(){
                        nav.classList.remove('mob-navigation-active');
                        overlay.remove();
                    };
                } else if (overlay) {
                    overlay.remove();
                }
            });

            // initialize (always render live autofill preview)
            updatePreview();
            updateGenerateLink();
            if (PREFILL_STUDENT_ID > 0) prefillByStudentId(PREFILL_STUDENT_ID);

        })();
    </script>
    <?php include 'template.php'; ?>
</body>
</html>
