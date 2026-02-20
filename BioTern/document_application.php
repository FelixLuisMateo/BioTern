<?php
// Documents page - provides UI to generate student documents (Application Letter etc.)

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

// Simple AJAX endpoints served by this file
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
                $text = trim($r['first_name'] . ' ' . ($r['middle_name'] ? $r['middle_name'] . ' ' : '') . $r['last_name']) . ' â€” ' . $r['student_id'];
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

    if ($action === 'get_application_letter' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $exists = $conn->query("SHOW TABLES LIKE 'application_letter'");
        if (!$exists || $exists->num_rows === 0) {
            echo json_encode(new stdClass());
            exit;
        }
        $stmt = $conn->prepare("SELECT * FROM application_letter WHERE user_id = ? LIMIT 1");
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
<html lang="zxx">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Documents</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <style>
        /* copy of students.php basic layout styles to match theme */
        html, body { height: 100%; margin: 0; padding: 0; }
        body { display:flex; flex-direction:column; min-height:100vh; }
        main.nxl-container { flex:1; display:flex; flex-direction:column; }
        div.nxl-content { flex:1; padding-bottom:24px; }
        .doc-preview { background:#fff; border:1px solid #eee; padding:24px; max-width:800px; margin-top:18px; margin-bottom:32px; position:relative; z-index:1; box-shadow:0 6px 20px rgba(0,0,0,0.04); }
        .preview-header{
            position: relative;
            min-height: 72px;
            text-align: center;
            border-bottom: 1px solid #8ab0e6;
            padding: 8px 0 6px 0;
            margin-bottom: 10px;
        }
        .preview-header .school-name{
            font-family: Calibri, Arial, sans-serif;
            font-weight: 700;
            color:#1b4f9c;
            font-size: 20px;
            line-height: 1.1;
            margin: 0;
        }
        .preview-header .school-meta{
            font-family: Calibri, Arial, sans-serif;
            color:#1b4f9c;
            font-size: 14px;
            line-height: 1.2;
            margin: 2px 0 0;
        }
        .preview-header .school-tel{
            font-family: Calibri, Arial, sans-serif;
            color:#1b4f9c;
            font-size: 14px;
            line-height: 1.2;
            margin: 2px 0 0;
        }
        /* ensure preview sits above footer visually */
        
        /* Select2 dropdown should overlay above other elements */
        .select2-container--open { z-index: 9999999 !important; }
        .select2-dropdown { z-index: 9999999 !important; }
        /* prevent theme/form-control styles from enlarging the Select2 search input */
        .select2-container .select2-search__field {
            padding: 4px !important;
            margin: 0 !important;
            height: auto !important;
            border: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }
        /* hide Select2's internal rendered selection so overlay input is the only visible text
           keeps arrow and container UI visible */
        .select2-container .select2-selection__rendered,
        .select2-container .select2-selection__placeholder {
            visibility: hidden !important;
        }
        /* overlay input placed on top of the visible Select2 box so users can type directly */
        .select2-overlay-input {
            position: absolute;
            inset: 0 40px 0 8px; /* leave room on the right for the arrow */
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
        html.app-skin-dark .doc-preview h6,
        html.app-skin-dark .doc-preview p,
        html.app-skin-dark .doc-preview div,
        html.app-skin-dark .doc-preview span,
        html.app-skin-dark .doc-preview strong {
            color: #dbe5f1 !important;
        }
        html.app-skin-dark .doc-preview .text-muted {
            color: #9fb0c6 !important;
        }

        main.nxl-container { padding-top: 90px; }
        .nxl-header { position: fixed !important; top: 0; left: 0; right: 0; z-index: 2147483647 !important; }
        .nxl-navigation { z-index: 2147483646; }
        footer.footer { margin-top: auto; }
        #btn_generate.is-disabled { opacity: .65; }
        .card .btn { position: relative; z-index: 2; }
        .file-edit-active #letter_content {
            outline: 2px dashed #3b82f6;
            outline-offset: 6px;
            background: rgba(59, 130, 246, 0.04);
        }
        #letter_content[contenteditable="true"] {
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
        }
    </style>

</head>
<body>
    <main class="nxl-container">
        <div class="nxl-content container">
            <div class="row mt-3">
                <div class="col-12">
                    <h4>Documents</h4>
                    <p class="text-muted">Select a student to auto-fill the Application Letter template. Click Generate to open a printable document.</p>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card p-3">
                        <label for="student_select" class="form-label">Search Student</label>
                        <select id="student_select" style="width:100%"></select>
                        <div class="mt-3">
                            <label class="form-label">Mr./Ms. (as to appear)</label>
                            <input id="input_name" class="form-control form-control-sm" type="text" placeholder="Recepient full name" autocomplete="off">
                        </div>
                        <div class="mt-2">
                            <label class="form-label">Position</label>
                            <input id="input_position" class="form-control form-control-sm" type="text" placeholder="Position (optional)" autocomplete="off">
                        </div>
                        <div class="mt-2">
                            <label class="form-label">Company</label>
                            <input id="input_company" class="form-control form-control-sm" type="text" placeholder="Company name" autocomplete="off">
                        </div>
                        <div class="mt-2">
                            <label class="form-label">Company Address</label>
                            <textarea id="input_company_address" class="form-control form-control-sm" rows="2" placeholder="Company address" autocomplete="off"></textarea>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <button id="btn_file_edit_application" type="button" class="btn btn-primary flex-grow-0">File Edit</button>
                            <button id="btn_generate" type="button" class="btn btn-success flex-grow-1">Generate / Print</button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="doc-preview" id="letter_preview">
                        <img class="crest-preview" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'" style="position:absolute; top:12px; left:12px; width:56px; height:56px; object-fit:contain;">
                        <div class="preview-header">
                            <p class="school-name">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</p>
                            <p class="school-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</p>
                            <p class="school-tel">Telefax No.: (045) 624-0215</p>
                        </div>
                        <div id="letter_content">
                            <p><strong>Application Approval Sheet</strong></p>
                            <p>Date: <span id="ap_date">__________</span></p>
                            <p>Mr./Ms.: <span id="ap_name">__________________________</span></p>
                            <p>Position: <span id="ap_position">__________________________</span></p>
                            <p>Name of Company: <span id="ap_company">__________________________</span></p>
                            <p>Company Address: <span id="ap_address">__________________________</span></p>

                            <p>Dear Sir or Madam:</p>
                            <p>I am <span id="ap_student">__________________________</span> student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of <strong>250 hours</strong>.</p>

                            <p>I would like to apply as a trainee in your company because I believe that the training and experience, I will acquire will broaden my knowledge about my course.</p>

                            <p>Thank you for any consideration that you may give to this letter of application.</p>

                            <p>Very truly yours,</p>

                            <p>Student Name: <span id="ap_student_name">__________________________</span></p>
                            <p>Student Home Address: <span id="ap_student_address">__________________________</span></p>
                            <p>Contact No.: <span id="ap_student_contact">__________________________</span></p>

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
            const APP_TEMPLATE_STORAGE_KEY = 'biotern_application_template_html_v1';
            const PREFILL_STUDENT_ID = <?php echo intval($prefill_student_id); ?>;
            const select = $('#student_select');
            const inputName = document.getElementById('input_name');
            const inputPosition = document.getElementById('input_position');
            const inputCompany = document.getElementById('input_company');
            const inputCompanyAddress = document.getElementById('input_company_address');
            const btnFileEdit = document.getElementById('btn_file_edit_application');
            const letterContent = document.getElementById('letter_content');
            let selectedStudentId = null;
            let isFileEditMode = false;
            let hasLoadedSavedTemplate = false;

            function saveApplicationTemplateHtml() {
                if (!letterContent) return;
                try { localStorage.setItem(APP_TEMPLATE_STORAGE_KEY, letterContent.innerHTML); } catch (err) {}
            }

            function loadApplicationTemplateHtml() {
                if (!letterContent) return false;
                try {
                    const saved = localStorage.getItem(APP_TEMPLATE_STORAGE_KEY);
                    if (!saved) return false;
                    const temp = document.createElement('div');
                    temp.innerHTML = saved;
                    const extracted = temp.querySelector('.content') || temp.querySelector('#application_doc_content');
                    if (extracted) {
                        letterContent.innerHTML = extracted.innerHTML;
                    } else {
                        const oldHeader = temp.querySelector('.header');
                        if (oldHeader) oldHeader.remove();
                        const oldCrest = temp.querySelector('.crest');
                        if (oldCrest) oldCrest.remove();
                        letterContent.innerHTML = temp.innerHTML || saved;
                    }
                    hasLoadedSavedTemplate = true;
                    return true;
                } catch (err) {
                    return false;
                }
            }

            function openApplicationEditor(e) {
                if (e && typeof e.preventDefault === 'function') e.preventDefault();
                // Always open editor with blank/default template, no student autofill carry-over.
                window.location.href = 'edit_application.php?blank=1';
                return false;
            }

            select.select2({
                placeholder: '',
                ajax: {
                    url: 'document_application.php',
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

            // create an overlay input so users can type directly in the visible box
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
                        // keep focus on the overlay so typing remains in the visible box
                    }, 0);
                }

                // forward typing into Select2
                overlay.addEventListener('input', function(){ openAndSync(); });
                overlay.addEventListener('keydown', function(e){
                    // open on printable key or backspace
                    if (e.key && (e.key.length === 1 || e.key === 'Backspace')){
                        openAndSync();
                        // let overlay keep its value; prevent default only for some keys
                    }
                });

                // when select2 closes, copy displayed selection text back to overlay
                $(document).on('select2:select select2:closing', '#student_select', function(e){
                    setTimeout(function(){
                        var txt = $('#student_select').find('option:selected').text() || '';
                        overlay.value = txt.replace(/\s+â€”\s+.*$/,'');
                    }, 0);
                });

                // focus overlay when container is clicked
                container.addEventListener('click', function(){ overlay.focus(); });
            })();

            

            // ensure the Select2 search input receives focus when dropdown opens
            select.on('select2:open', function() {
                // when Select2 opens, do not steal focus from the overlay input
                // we still leave the internal field available for accessibility
            });

            // when a student is selected, auto-fetch student details and fill only student-specific preview fields
            $('#student_select').on('select2:select', function(e){
                const id = select.val();
                if (!id) return;
                selectedStudentId = id;
                // auto-fill student info (NOT recipient/company fields)
                fetch('document_application.php?action=get_student&id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .then(data => {
                        if (!data) return;
                        const fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                        // Do NOT set inputName/inputCompany/inputPosition here â€” those are for the recipient/company
                        // Only set student-related preview fields
                        document.getElementById('ap_student').textContent = fullname;
                        document.getElementById('ap_student_name').textContent = fullname;
                        document.getElementById('ap_student_address').textContent = data.address || '__________________________';
                        document.getElementById('ap_student_contact').textContent = data.phone || '__________________________';
                        document.getElementById('ap_date').textContent = new Date().toLocaleDateString();
                        // keep recipient/company fields blank until user types
                        clearRecipientCompanyFields();
                        // then load saved application letter values from ojt-view.php data
                        loadApplicationLetterData(id);
                        // update generate link (do not include recipient if empty)
                        updatePreviewFields();
                        updateGenerateLink(id);
                    });
            });

            function loadApplicationLetterData(id){
                if (!id) return;
                fetch('document_application.php?action=get_application_letter&id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .then(data => {
                        if (!data || typeof data !== 'object') return;
                        inputName.value = (data.application_person || '').toString();
                        inputPosition.value = (data.posistion || data.position || '').toString();
                        inputCompany.value = (data.company_name || '').toString();
                        inputCompanyAddress.value = (data.company_address || '').toString();
                        if (data.date) document.getElementById('ap_date').textContent = data.date;
                        updatePreviewFields();
                        updateGenerateLink(id);
                    })
                    .catch(() => {});
            }

            function prefillByStudentId(id){
                if (!id) return;
                selectedStudentId = id;
                fetch('document_application.php?action=get_student&id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .then(data => {
                        if (!data || !data.id) return;
                        const fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                        const label = (fullname || 'Student') + ' - ' + (data.student_id || id);
                        const option = new Option(label, String(id), true, true);
                        select.append(option).trigger('change');

                        // Keep visible search text in sync for prefilled student id.
                        const overlayInput = document.querySelector('.select2-overlay-input');
                        if (overlayInput) overlayInput.value = fullname || '';

                        document.getElementById('ap_student').textContent = fullname || '__________________________';
                        document.getElementById('ap_student_name').textContent = fullname || '__________________________';
                        document.getElementById('ap_student_address').textContent = data.address || '__________________________';
                        document.getElementById('ap_student_contact').textContent = data.phone || '__________________________';
                        document.getElementById('ap_date').textContent = new Date().toLocaleDateString();

                        clearRecipientCompanyFields();
                        loadApplicationLetterData(id);
                        updatePreviewFields();
                        updateGenerateLink(id);
                    })
                    .catch(() => {});
            }

            function clearRecipientCompanyFields(){
                inputName.value = '';
                inputPosition.value = '';
                inputCompany.value = '';
                inputCompanyAddress.value = '';
                document.getElementById('ap_name').textContent = '__________________________';
                document.getElementById('ap_position').textContent = '__________________________';
                document.getElementById('ap_company').textContent = '__________________________';
                document.getElementById('ap_address').textContent = '__________________________';
            }

            function updatePreviewFields(){
                if (isFileEditMode) return;
                document.getElementById('ap_name').textContent = inputName.value || '__________________________';
                document.getElementById('ap_position').textContent = inputPosition.value || '__________________________';
                document.getElementById('ap_company').textContent = inputCompany.value || '__________________________';
                document.getElementById('ap_address').textContent = inputCompanyAddress.value || '__________________________';
            }

            function updateGenerateLink(id){
                const finalId = id || selectedStudentId || select.val();
                const gen = document.getElementById('btn_generate');
                const params = new URLSearchParams();
                if (finalId) params.set('id', finalId);
                if (inputName.value) params.set('ap_name', inputName.value);
                if (inputPosition.value) params.set('ap_position', inputPosition.value);
                if (inputCompany.value) params.set('ap_company', inputCompany.value);
                if (inputCompanyAddress.value) params.set('ap_address', inputCompanyAddress.value);
                try {
                    if (localStorage.getItem(APP_TEMPLATE_STORAGE_KEY)) {
                        params.set('use_saved_template', '1');
                    }
                } catch (err) {}
                params.set('date', new Date().toLocaleDateString());
                const url = 'generate_application_letter.php?' + params.toString();
                gen.dataset.url = url;
                return url;
            }

            inputName.addEventListener('input', function(){ updatePreviewFields(); updateGenerateLink(selectedStudentId); });
            inputPosition.addEventListener('input', function(){ updatePreviewFields(); updateGenerateLink(selectedStudentId); });
            inputCompany.addEventListener('input', function(){ updatePreviewFields(); updateGenerateLink(selectedStudentId); });
            inputCompanyAddress.addEventListener('input', function(){ updatePreviewFields(); updateGenerateLink(selectedStudentId); });

            btnFileEdit.addEventListener('click', function(e){
                openApplicationEditor(e);
            });

            // keep button reliably clickable and generate href on demand
            document.getElementById('btn_generate').addEventListener('click', function(e){
                const url = updateGenerateLink(selectedStudentId || select.val());
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

            loadApplicationTemplateHtml();
            if (!hasLoadedSavedTemplate) {
                updatePreviewFields();
                document.getElementById('ap_date').textContent = new Date().toLocaleDateString();
            }
            clearRecipientCompanyFields();
            updateGenerateLink(selectedStudentId || select.val());
            if (PREFILL_STUDENT_ID > 0) prefillByStudentId(PREFILL_STUDENT_ID);

        })();
    </script>
    <?php include 'template.php'; ?>
</body>
</html>

