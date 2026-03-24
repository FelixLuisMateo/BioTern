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
$prefill_greeting_pref = strtolower(trim((string)($_GET['greeting_pref'] ?? 'either')));
if (!in_array($prefill_greeting_pref, ['sir', 'maam', 'either'], true)) {
    $prefill_greeting_pref = 'either';
}
$prefill_recipient_title = strtolower(trim((string)($_GET['recipient_title'] ?? 'auto')));
if (!in_array($prefill_recipient_title, ['auto', 'mr', 'ms', 'none'], true)) {
    $prefill_recipient_title = 'auto';
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
$page_title = 'Endorsement Letter';
$base_href = '../';
$page_styles = ['assets/css/layout/page_shell.css', 'assets/css/modules/documents/documents.css'];
$page_scripts = ['assets/js/modules/documents/documents-page-runtime.js'];
include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div
    class="endorsement-page doc-page-root"
    data-page="endorsement"
    data-prefill-student-id="<?php echo intval($prefill_student_id); ?>"
    data-prefill-greeting-pref="<?php echo htmlspecialchars($prefill_greeting_pref, ENT_QUOTES, 'UTF-8'); ?>"
    data-prefill-recipient-title="<?php echo htmlspecialchars($prefill_recipient_title, ENT_QUOTES, 'UTF-8'); ?>"
>
    <div class="container">
    <div class="row mt-3">
        <div class="col-12">
            <h4>Endorsement Letter</h4>
            <p class="text-muted">Select student and prepare the endorsement letter.</p>
        </div>
    </div>

    <div class="row doc-workspace-row">
        <div class="col-lg-6 doc-form-pane">
            <div class="card p-3">
                <div class="mt-2">
                    <label for="student_select" class="form-label">Search Student</label>
                    <select id="student_select" class="student-select-full"></select>
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

        <div class="col-lg-6 doc-template-pane">
            <div class="doc-preview" id="letter_preview">
                <img class="crest-preview crest-preview-position js-hide-on-error" src="assets/images/auth/auth-cover-login-bg.png" alt="crest">
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
                            <img class="ross-signature js-hide-on-error" src="pages/Ross-Signature.png" alt="Ross signature">
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

</div>
</div> <!-- .nxl-content -->
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>







