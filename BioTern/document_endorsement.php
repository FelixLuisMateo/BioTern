<?php
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

    if ($action === 'get_student' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $conn->prepare("SELECT s.*, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        echo json_encode($row ?: new stdClass());
        exit;
    }

    if ($action === 'get_endorsement' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $exists = $conn->query("SHOW TABLES LIKE 'endorsement_letter'");
        if (!$exists || $exists->num_rows === 0) {
            echo json_encode(new stdClass());
            exit;
        }
        $stmt = $conn->prepare("SELECT * FROM endorsement_letter WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        echo json_encode($row ?: new stdClass());
        exit;
    }

    echo json_encode(new stdClass());
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Endorsement Letter</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/vendors.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2.min.css">
    <link rel="stylesheet" type="text/css" href="assets/vendors/css/select2-theme.min.css">
    <link rel="stylesheet" type="text/css" href="assets/css/theme.min.css">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        body { display:flex; flex-direction:column; min-height:100vh; }
        main.nxl-container { flex:1; display:flex; flex-direction:column; padding-top:90px; }
        div.nxl-content { flex:1; padding-bottom:24px; }
        .nxl-header { position: fixed !important; top: 0; left: 0; right: 0; z-index: 2147483647 !important; }
        .nxl-navigation { z-index: 2147483646; }
        footer.footer { margin-top: auto; }

        .doc-preview { background:#fff; border:1px solid #eee; padding:24px; max-width:800px; margin-top:18px; margin-bottom:32px; box-shadow:0 6px 20px rgba(0,0,0,.06); position:relative; z-index:1; }
        .preview-header { position:relative; min-height:72px; text-align:center; border-bottom:1px solid #8ab0e6; padding:8px 0 6px; margin-bottom:10px; }
        .preview-header .school-name { font-family:Calibri,Arial,sans-serif; color:#1b4f9c; font-size:20px; margin:0; font-weight:700; }
        .preview-header .school-meta, .preview-header .school-tel { font-family:Calibri,Arial,sans-serif; color:#1b4f9c; font-size:14px; line-height:1.2; }
        .preview-content { font-family:"Times New Roman", Times, serif; font-size:12pt; line-height:1.45; color:#2f3640; }
        .preview-content h5 { text-align:center; margin:8px 0 12px; font-weight:700; }
        .preview-content p,
        .preview-content li,
        .preview-content strong,
        .preview-content span {
            color:#2f3640;
        }
        .signature { margin-top:28px; }
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
            .nxl-navigation,
            .nxl-navigation.mob-navigation-active { z-index: 2147483648 !important; }
            .nxl-navigation .navbar-wrapper { z-index: 2147483648 !important; }
            .nxl-header { z-index: 2147483647 !important; }
            .nxl-container { position: relative; z-index: 1; }
            .doc-preview { z-index: 1 !important; }
            .select2-container--open,
            .select2-dropdown { z-index: 900 !important; }
        }
    </style>
</head>
<body>
<main class="nxl-container">
    <div class="nxl-content container">
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
                    <label class="form-label">Recipient Position</label>
                    <input id="input_position" class="form-control form-control-sm" type="text" placeholder="e.g. Supervisor/Manager">
                </div>
                <div class="mt-2">
                    <label class="form-label">Company Name</label>
                    <input id="input_company" class="form-control form-control-sm" type="text" placeholder="Company name">
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
                <img class="crest-preview" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" style="position:absolute; top:12px; left:12px; width:56px; height:56px; object-fit:contain;" onerror="this.style.display='none'">
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

                    <p>Dear Ma'am,</p>

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
                    </div>
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
    const inputRecipient = document.getElementById('input_recipient');
    const inputPosition = document.getElementById('input_position');
    const inputCompany = document.getElementById('input_company');
    const inputCompanyAddress = document.getElementById('input_company_address');
    const inputStudents = document.getElementById('input_students');
    const btnGenerate = document.getElementById('btn_generate');
    const btnFileEdit = document.getElementById('btn_file_edit');
    const prefillId = <?php echo intval($prefill_student_id); ?>;

    function sanitizeStudentLines(raw) {
        return String(raw || '')
            .split(/\r?\n/)
            .map(x => x.trim())
            .filter(Boolean);
    }

    function buildNameFromOptionText(text) {
        return String(text || '').replace(/\s*-\s*.*$/, '').trim();
    }

    function autofillStudentsFromSelected() {
        const selected = select.select2('data') || [];
        const first = selected[0] || null;
        const name = first ? buildNameFromOptionText(first.text) : '';
        if (name && !inputStudents.dataset.manualLocked) {
            inputStudents.value = name;
        } else if (!name && !inputStudents.dataset.manualLocked) {
            inputStudents.value = '';
        }
    }

    function updatePreview() {
        document.getElementById('pv_recipient').textContent = inputRecipient.value || '__________________________';
        document.getElementById('pv_position').textContent = inputPosition.value || '__________________________';
        document.getElementById('pv_company').textContent = inputCompany.value || '__________________________';
        document.getElementById('pv_company_address').textContent = inputCompanyAddress.value || '__________________________';

        const ul = document.getElementById('pv_students');
        const lines = sanitizeStudentLines(inputStudents.value);
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
        const selectedId = select.val();
        if (selectedId) {
            p.set('id', selectedId);
        } else if (prefillId > 0) {
            p.set('id', String(prefillId));
        }
        if (inputRecipient.value) p.set('recipient', inputRecipient.value);
        if (inputPosition.value) p.set('position', inputPosition.value);
        if (inputCompany.value) p.set('company', inputCompany.value);
        if (inputCompanyAddress.value) p.set('company_address', inputCompanyAddress.value);
        if (inputStudents.value) p.set('students', inputStudents.value);
        p.set('use_saved_template', '1');
        const genUrl = 'generate_endorsement_letter.php?' + p.toString();
        btnGenerate.href = genUrl;
        btnFileEdit.href = 'edit_endorsement.php?blank=1';
        return genUrl;
    }

    function applySavedEndorsement(data) {
        if (!data || typeof data !== 'object') return false;
        let changed = false;
        if (data.recipient_name) {
            inputRecipient.value = String(data.recipient_name);
            changed = true;
        }
        if (data.recipient_position) {
            inputPosition.value = String(data.recipient_position);
            changed = true;
        }
        if (data.company_name) {
            inputCompany.value = String(data.company_name);
            changed = true;
        }
        if (data.company_address) {
            inputCompanyAddress.value = String(data.company_address);
            changed = true;
        }
        if (data.students_to_endorse) {
            inputStudents.value = String(data.students_to_endorse);
            inputStudents.dataset.manualLocked = '1';
            changed = true;
        }
        return changed;
    }

    select.select2({
        placeholder: 'Search student by name or ID',
        ajax: {
            url: 'document_endorsement.php',
            dataType: 'json',
            delay: 250,
            data: function(params){ return { action: 'search_students', q: params.term }; },
            processResults: function(data){ return { results: data.results || [] }; }
        },
        minimumInputLength: 1,
        width: 'resolve',
        dropdownCssClass: 'select2-dropdown'
    });

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
        overlay.placeholder = 'Search student by name or ID';
        overlay.autocomplete = 'off';
        container.appendChild(overlay);
        overlay.addEventListener('focus', function(){
            try { select.select2('open'); } catch(e){}
            setTimeout(function(){
                var fld = document.querySelector('.select2-container--open .select2-search__field');
                if (fld) fld.focus();
            }, 30);
        });
        overlay.addEventListener('input', function(){
            const v = overlay.value || '';
            try { select.select2('open'); } catch(e){}
            setTimeout(function(){
                var fld = document.querySelector('.select2-container--open .select2-search__field');
                if (fld) {
                    fld.value = v;
                    fld.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }, 30);
        });
        $(document).on('select2:select select2:closing', '#student_select', function(){
            const txt = $('#student_select').find('option:selected').text() || '';
            overlay.value = txt;
        });
    }

    select.on('select2:open', function() {
        setTimeout(function() {
            var field = document.querySelector('.select2-container--open .select2-search__field');
            if (field) field.focus();
        }, 0);
    });

    select.on('select2:select select2:unselect change', function(){
        autofillStudentsFromSelected();
        updatePreview();
        updateLinks();
    });

    [inputRecipient, inputPosition, inputCompany, inputCompanyAddress, inputStudents].forEach(el => {
        el.addEventListener('input', function(){
            if (el === inputStudents) inputStudents.dataset.manualLocked = '1';
            updatePreview();
            updateLinks();
        });
    });

    btnFileEdit.addEventListener('click', function(e){
        e.preventDefault();
        const href = btnFileEdit.href || 'edit_endorsement.php?blank=1';
        window.location.href = href;
    });

    btnGenerate.addEventListener('click', function(e){
        e.preventDefault();
        const href = btnGenerate.href || updateLinks();
        if (!href) return;
        window.open(href, '_blank');
    });

    if (prefillId > 0) {
        fetch('document_endorsement.php?action=get_endorsement&id=' + encodeURIComponent(prefillId))
            .then(r => r.json())
            .then(saved => {
                const hasSaved = applySavedEndorsement(saved);
                if (hasSaved) {
                    updatePreview();
                    updateLinks();
                    setTimeout(createSelectOverlay, 60);
                    return;
                }
                fetch('document_endorsement.php?action=get_student&id=' + encodeURIComponent(prefillId))
                    .then(r => r.json())
                    .then(data => {
                        const full = [data.first_name, data.middle_name, data.last_name].filter(Boolean).join(' ').trim();
                        if (full) {
                            const text = full + ' - ' + (data.student_id || '');
                            const o = new Option(text, String(prefillId), true, true);
                            select.append(o).trigger('change');
                            autofillStudentsFromSelected();
                        }
                        updatePreview();
                        updateLinks();
                        setTimeout(createSelectOverlay, 60);
                    });
            });
    }

    setTimeout(createSelectOverlay, 60);
    updatePreview();
    updateLinks();
})();
</script>
<?php include 'includes/header.php';?>
<?php include 'includes/footer.php'; ?>
</body>
</html>
