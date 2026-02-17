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
        /* give extra bottom space so previews stay above fixed footer */
        div.nxl-content { flex:1; padding-bottom:340px; }
        .doc-preview { background:#fff; border:1px solid #eee; padding:24px; max-width:800px; margin-top:18px; margin-bottom:32px; position:relative; z-index:2200; box-shadow:0 6px 20px rgba(0,0,0,0.04); }
        /* move the header text a bit lower so the crest/logo fits inside the preview */
        .doc-preview .text-center { padding-top:40px; }
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
        /* ensure page content sits below the site header/navigation */
        main.nxl-container { padding-top: 90px; }
        /* force the global header to be fixed and above everything */
        .nxl-header { position: fixed !important; top: 0; left: 0; right: 0; z-index: 2147483647 !important; }
        /* ensure navigation/sidebar sits below header visually */
        .nxl-navigation { z-index: 2147483646; }
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
                            <input id="input_name" class="form-control form-control-sm" type="text" placeholder="Student full name">
                        </div>
                        <div class="mt-2">
                            <label class="form-label">Position</label>
                            <input id="input_position" class="form-control form-control-sm" type="text" placeholder="Position (optional)">
                        </div>
                        <div class="mt-2">
                            <label class="form-label">Company</label>
                            <input id="input_company" class="form-control form-control-sm" type="text" placeholder="Company name">
                        </div>
                        <div class="mt-2">
                            <label class="form-label">Company Address</label>
                            <textarea id="input_company_address" class="form-control form-control-sm" rows="2" placeholder="Company address"></textarea>
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <button id="btn_fill" class="btn btn-primary flex-grow-0" disabled>Fill Template</button>
                            <a id="btn_generate" class="btn btn-success flex-grow-1 disabled" target="_blank">Generate / Print</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="doc-preview" id="letter_preview">
                        <img class="crest-preview" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'" style="position:absolute; top:12px; left:12px; width:86px;">
                        <div class="text-center mb-3" style="padding-top:8px;">
                            <h6 class="mt-2">CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h6>
                            <div class="small text-muted">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
                            <div>Telefax No.: (045) 624-0215</div>
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
            const select = $('#student_select');
            const inputName = document.getElementById('input_name');
            const inputPosition = document.getElementById('input_position');
            const inputCompany = document.getElementById('input_company');
            const inputCompanyAddress = document.getElementById('input_company_address');

            select.select2({
                placeholder: '',
                ajax: {
                    url: 'documents.php',
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
                        overlay.value = txt.replace(/\s+—\s+.*$/,'');
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

            // adjust top padding based on header height (header is injected by the template include at the bottom)
            function adjustTopPadding(){
                var hdr = document.querySelector('.nxl-header');
                var main = document.querySelector('main.nxl-container');
                if (hdr && main) {
                    var h = hdr.offsetHeight || 0;
                    main.style.paddingTop = (h + 8) + 'px';
                }
            }
            // run after DOM loads and when window resizes
            document.addEventListener('DOMContentLoaded', function(){ setTimeout(adjustTopPadding, 50); });
            window.addEventListener('load', adjustTopPadding);
            window.addEventListener('resize', adjustTopPadding);

            // when a student is selected, auto-fetch student details and fill only student-specific preview fields
            $('#student_select').on('select2:select', function(e){
                const id = select.val();
                if (!id) return;
                // enable fill button too
                $('#btn_fill').prop('disabled', false);
                // auto-fill student info (NOT recipient/company fields)
                fetch('documents.php?action=get_student&id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .then(data => {
                        if (!data) return;
                        const fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                        // Do NOT set inputName/inputCompany/inputPosition here — those are for the recipient/company
                        // Only set student-related preview fields
                        document.getElementById('ap_student').textContent = fullname;
                        document.getElementById('ap_student_name').textContent = fullname;
                        document.getElementById('ap_student_address').textContent = data.address || '__________________________';
                        document.getElementById('ap_student_contact').textContent = data.phone || '__________________________';
                        document.getElementById('ap_date').textContent = new Date().toLocaleDateString();
                        // update generate link (do not include recipient if empty)
                        updatePreviewFields();
                        updateGenerateLink(id);
                    });
            });

            function updatePreviewFields(){
                document.getElementById('ap_name').textContent = inputName.value || '__________________________';
                document.getElementById('ap_position').textContent = inputPosition.value || '__________________________';
                document.getElementById('ap_company').textContent = inputCompany.value || '__________________________';
                document.getElementById('ap_address').textContent = inputCompanyAddress.value || '__________________________';
            }

            function updateGenerateLink(id){
                const gen = document.getElementById('btn_generate');
                const params = new URLSearchParams();
                params.set('id', id);
                if (inputName.value) params.set('ap_name', inputName.value);
                if (inputPosition.value) params.set('ap_position', inputPosition.value);
                if (inputCompany.value) params.set('ap_company', inputCompany.value);
                if (inputCompanyAddress.value) params.set('ap_address', inputCompanyAddress.value);
                params.set('date', new Date().toLocaleDateString());
                gen.href = 'generate_application_letter.php?' + params.toString();
                gen.classList.remove('disabled');
            }

            inputName.addEventListener('input', function(){ updatePreviewFields(); if (select.val()) updateGenerateLink(select.val()); });
            inputPosition.addEventListener('input', function(){ updatePreviewFields(); if (select.val()) updateGenerateLink(select.val()); });
            inputCompany.addEventListener('input', function(){ updatePreviewFields(); if (select.val()) updateGenerateLink(select.val()); });
            inputCompanyAddress.addEventListener('input', function(){ updatePreviewFields(); if (select.val()) updateGenerateLink(select.val()); });

            $('#btn_fill').on('click', function(){
                const id = select.val();
                if (!id) return;
                // populate only student information in preview (do not touch recipient/company inputs)
                fetch('documents.php?action=get_student&id=' + encodeURIComponent(id))
                    .then(r => r.json())
                    .then(data => {
                        if (!data) return;
                        const fullname = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ');
                        inputCompany.value = '';
                        inputCompanyAddress.value = data.address || '';
                        inputPosition.value = '';
                        document.getElementById('ap_student').textContent = fullname;
                        document.getElementById('ap_student_name').textContent = fullname;
                        document.getElementById('ap_student_address').textContent = data.address || '__________________________';
                        document.getElementById('ap_student_contact').textContent = data.phone || '__________________________';
                        document.getElementById('ap_date').textContent = new Date().toLocaleDateString();
                        updatePreviewFields();
                        updateGenerateLink(id);
                    });
            });
        })();
    </script>
    <?php include 'template.php'; ?>
</body>
</html>
