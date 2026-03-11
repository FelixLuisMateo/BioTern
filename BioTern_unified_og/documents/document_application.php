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
                $text = trim($r['first_name'] . ' ' . ($r['middle_name'] ? $r['middle_name'] . ' ' : '') . $r['last_name']) . '  ' . $r['student_id'];
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

$page_title = 'Documents';
$base_href = '../';
$page_styles = ['assets/css/documents-page.css'];
$page_scripts = ['assets/js/documents-page-runtime.js'];
include __DIR__ . '/../includes/header.php';
?>
<div class="app-page doc-page-root" data-page="application" data-prefill-student-id="<?php echo intval($prefill_student_id); ?>">

    <div class="container">
            <div class="row mt-1">
                <div class="col-12">
                    <h4>Documents</h4>
                    <p class="text-muted">Select a student to auto-fill the Application Letter template. Click Generate to open a printable document.</p>
                </div>
            </div>

            <div class="row doc-workspace-row">
                <div class="col-lg-6 doc-form-pane">
                    <div class="card p-3">
                        <label for="student_select" class="form-label">Search Student</label>
                        <select id="student_select" class="student-select-full"></select>
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
                        <div class="mt-2">
                            <label class="form-label">Hours</label>
                            <input id="input_hours" class="form-control form-control-sm" type="text" value="250" placeholder="Required OJT hours" autocomplete="off">
                        </div>
                        <div class="mt-3 d-flex gap-2">
                            <button id="btn_file_edit_application" type="button" class="btn btn-primary flex-grow-0">File Edit</button>
                            <button id="btn_generate" type="button" class="btn btn-success flex-grow-1">Generate / Print</button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 doc-template-pane">
                    <div class="doc-preview" id="letter_preview">
                        <img class="crest-preview crest-preview-position js-hide-on-error" src="assets/images/auth/auth-cover-login-bg.png" alt="crest">
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
                            <p>I am <span id="ap_student">__________________________</span> student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of <strong><span id="ap_hours">250</span> hours</strong>.</p>

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

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>


