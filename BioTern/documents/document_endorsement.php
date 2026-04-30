<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/ojt_masterlist.php';
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
$base_href = '';
include __DIR__ . '/../includes/header.php';
?>
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
    </style>
    <div class="container">
    <div class="row mt-3">
        <div class="col-12">
            <h4>Endorsement Letter</h4>
            <p class="text-muted">Select student and prepare the endorsement letter.</p>
            <div class="mb-3">
                <a id="word_template_link_endorsement" href="/document-word-templates?template_type=endorsement" class="btn btn-outline-info word-tool-link">
                    <span>Open Word Template Tool</span>
                    <small class="text-muted">Upload actual .docx template</small>
                </a>
            </div>
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
    const select = $('#student_select');
    const inputRecipient = document.getElementById('input_recipient');
    const inputPosition = document.getElementById('input_position');
    const inputCompany = document.getElementById('input_company');
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
    let selectedStudentId = prefillId > 0 ? String(prefillId) : '';

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
        const selected = select.select2('data') || [];
        const first = selected[0] || null;
        if (first && first.text) {
            return buildNameFromOptionText(first.text);
        }
        const txt = $('#student_select').find('option:selected').text() || '';
        return buildNameFromOptionText(txt);
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
        const selectedId = select.val() || selectedStudentId;
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
        const genUrl = '../pages/generate_endorsement_letter.php?' + p.toString();
        btnGenerate.href = genUrl;
        btnFileEdit.href = '../pages/edit_endorsement.php?blank=1';
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

    select.select2({
        placeholder: '',
        ajax: {
            url: 'document_endorsement.php',
            dataType: 'json',
            delay: 250,
            data: function(params){ return { action: 'search_students', q: params.term }; },
            processResults: function(data){ return { results: data.results || [] }; }
        },
        minimumInputLength: 1,
        width: 'resolve',
        dropdownParent: $(document.body),
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

        $(document).on('select2:select select2:closing', '#student_select', function(){
            setTimeout(function(){
                const txt = $('#student_select').find('option:selected').text() || '';
                overlay.value = buildNameFromOptionText(txt);
            }, 0);
        });
        container.addEventListener('click', function(){ overlay.focus(); });
    }

    select.on('select2:open', function() {
        // keep overlay input focused for direct typing behavior
    });

    select.on('select2:select', function(){
        const pickedId = String(select.val() || '');
        if (pickedId) selectedStudentId = pickedId;
        appendSelectedStudentToTextarea();
        if (pickedId) {
            fetch('document_endorsement.php?action=get_endorsement&id=' + encodeURIComponent(pickedId))
                .then(function(r){ return r.json(); })
                .then(function(saved){
                    applySavedEndorsement(saved);
                    updatePreview();
                    updateLinks();
                })
                .catch(function(){});
        }
        // Clear current selection so user can search/add another student quickly.
        select.val(null).trigger('change');
        const overlay = document.querySelector('.select2-overlay-input');
        if (overlay) overlay.value = '';
        setTimeout(function(){ if (overlay) overlay.focus(); }, 0);
        updatePreview();
        updateLinks();
    });

    select.on('select2:unselect change', function(){
        updatePreview();
        updateLinks();
    });

    [inputRecipient, inputPosition, inputCompany, inputCompanyAddress, inputStudents].forEach(el => {
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
                            selectedStudentId = String(prefillId);
                            select.append(o).trigger('change');
                        }
                        updatePreview();
                        updateLinks();
                        setTimeout(createSelectOverlay, 60);
                    });
            });
    }

    setTimeout(createSelectOverlay, 60);
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
<?php include __DIR__ . '/../includes/footer.php'; ?>




