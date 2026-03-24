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

$page_title = 'MOA';
$base_href = '../';
$page_styles = ['assets/css/layout/page_shell.css', 'assets/css/documents/documents.css'];
$page_scripts = ['assets/js/documents/documents-page-runtime.js'];
include __DIR__ . '/../includes/header.php';
?>
<main class="nxl-container">
    <div class="nxl-content">
<div class="moa-page doc-page-root" data-page="moa" data-prefill-student-id="<?php echo intval($prefill_student_id); ?>">
        <div class="container moa-content">
            <div class="row mt-3">
                <div class="col-12">
                    <h4>Memorandum of Agreement</h4>
                    <p class="text-muted">Fill the fields below then click Generate MOA to open the printable Memorandum of Agreement.</p>
                </div>
            </div>

            <div class="row doc-workspace-row">
                <div class="col-lg-6 doc-form-pane">
                    <div class="card p-3">
                        <label for="student_select" class="form-label">Search Student</label>
                        <select id="student_select" data-placeholder="Search by name or student id" class="student-select-full"></select>

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

                <div class="col-lg-6 doc-template-pane">
                    <div class="doc-preview" id="moa_preview">
                        <div id="moa_content" class="a4-pages-stack">
                            <div class="a4-page">
                            <h5 class="text-center-inline">Memorandum of Agreement</h5>
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

                            </div>

                            <div class="a4-page">
                            <p class="mt-12">
                                This Memorandum of Agreement shall become effective upon signature of both parties and implementation will begin immediately and shall continue to be valid hereafter until written notice is given by either party thirty (30) days prior to the date of intended termination.
                            </p>
                            <p>
                                In witness whereof the parties have signed this Memorandum of Agreement at <span id="pv_signed_at">__________________</span> this <span id="pv_signed_day">_____</span> day of <span id="pv_signed_month">__________________</span>, <span id="pv_signed_year">20__</span>.
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
            </div>
        </div>

</div>
</div> <!-- .nxl-content -->
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>








