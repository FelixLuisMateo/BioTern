<?php
// Generate printable Application Letter for a student
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

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$student = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'address' => '',
    'phone' => ''
];

if ($student_id > 0) {
    $query = "SELECT s.*, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $student = $result->fetch_assoc();
    }
}

$full_name = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$address = $student['address'] ?? '';
$phone = $student['phone'] ?? '';
$today = date('F j, Y');

// Allow overriding some fields via query string when generating from document_application.php
$ap_name = isset($_GET['ap_name']) ? trim($_GET['ap_name']) : '';
$ap_position = isset($_GET['ap_position']) ? trim($_GET['ap_position']) : '';
$ap_company = isset($_GET['ap_company']) ? trim($_GET['ap_company']) : '';
$ap_company_address = isset($_GET['ap_address']) ? trim($_GET['ap_address']) : '';
$ap_hours = isset($_GET['ap_hours']) ? trim($_GET['ap_hours']) : '250';
$print_date = isset($_GET['date']) ? trim($_GET['date']) : $today;
$use_saved_template = isset($_GET['use_saved_template']) && $_GET['use_saved_template'] === '1';

// do NOT default recipient name to student; leave blank unless provided

// download flags: start output buffering when a download is requested
$do_download_pdf = isset($_GET['download_pdf']);
$do_download_html = isset($_GET['download_html']);
if ($do_download_pdf || $do_download_html) ob_start();

$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/pages/') !== false) ? '../' : '';
$page_title = 'BioTern || Application Letter - ' . (trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?: 'Preview');
$base_href = $asset_prefix;
$page_styles = [
    'assets/css/generate-application-letter-page.css',
];
$page_scripts = [
    'assets/js/generate-application-letter-runtime.js',
];

include __DIR__ . '/../includes/header.php';

?>
<div class="main-content">
<div class="container app-application-letter-container">
    <!-- crest at top-left (auth-cover-login-bg.png if available) -->
    <img class="crest app-application-letter-crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" data-hide-onerror="1">
    <div class="header app-application-letter-header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta app-application-letter-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
        <div class="tel app-application-letter-tel">Telefax No.: (045) 624-0215</div>
    </div>

    <div class="content app-application-letter-content" id="application_doc_content">
        <h3 class="app-sheet-title app-application-letter-sheet-title">Application Approval Sheet</h3>
        <p class="field-block app-application-letter-field-block">Date: <span class="filled-val app-application-letter-filled-val" id="ap_date"><?php echo htmlspecialchars($print_date); ?></span></p>
        <p class="field-block app-application-letter-field-block">Mr./Ms.: <span class="filled-val app-application-letter-filled-val" id="ap_name"><?php echo htmlspecialchars($ap_name ?: ''); ?></span></p>
        <p class="field-block app-application-letter-field-block">Position: <span class="filled-val app-application-letter-filled-val" id="ap_position"><?php echo htmlspecialchars($ap_position ?: ''); ?></span></p>
        <p class="field-block app-application-letter-field-block">Name of Company: <span class="filled-val app-application-letter-filled-val filled-val-wide app-application-letter-filled-val-wide" id="ap_company"><?php echo htmlspecialchars($ap_company ?: ''); ?></span></p>
        <p class="field-block app-application-letter-field-block">Company Address: <span class="filled-val app-application-letter-filled-val filled-val-wide app-application-letter-filled-val-wide" id="ap_address"><?php echo htmlspecialchars($ap_company_address ?: ''); ?></span></p>

        <p class="mt-30 app-application-letter-mt-30">Dear Sir or Madam:</p>

        <p>I am <span class="filled-val app-application-letter-filled-val filled-val-name app-application-letter-filled-val-name"><?php echo htmlspecialchars($full_name); ?></span>, student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of <strong><span id="ap_hours"><?php echo htmlspecialchars($ap_hours); ?></span> hours</strong>.</p>

        <p>I would like to apply as a trainee in your company because I believe that the training and experience, I will acquire will broaden my knowledge about my course.</p>

        <p>Thank you for any consideration that you may give to this letter of application.</p>

        <p class="mt-30 app-application-letter-mt-30">Very truly yours,</p>

        <div class="signature app-application-letter-signature">
            <p class="mt-40 app-application-letter-mt-40">Student Name: <span class="filled-val app-application-letter-filled-val"><?php echo htmlspecialchars($full_name); ?></span></p>
            <p>Student Home Address: <span class="filled-val app-application-letter-filled-val filled-val-wide app-application-letter-filled-val-wide"><?php echo nl2br(htmlspecialchars($address)); ?></span></p>
            <p>Contact No.: <span class="filled-val app-application-letter-filled-val"><?php echo htmlspecialchars($phone); ?></span></p>
        </div>
    </div>


    <p class="small app-application-letter-small mt-40 app-application-letter-mt-40">Student Signature: <span class="approval-signature-line app-application-letter-approval-signature-line"></span></p>
    <p class="small app-application-letter-small mt-40 app-application-letter-mt-40">Noted by: <span class="approval-signature-line app-application-letter-approval-signature-line"></span></p>

    <div class="actions app-application-letter-actions no-print app-application-letter-no-print actions-inline-layout app-application-letter-actions-inline-layout">
        <div class="tip-box app-application-letter-tip-box" role="alert">
            Tip: Use A4 paper. In your print settings, set the margins to Top: 0, Bottom: 0, Left: 0.5, Right: 0.5, and uncheck "Headers and footers" or "Include headers and footers".
        </div>
        <button id="btn_print" type="button" class="btn btn-primary btn-lg action-btn app-application-letter-action-btn">Print</button>
        <button id="btn_close" type="button" class="btn btn-secondary btn-lg action-btn app-application-letter-action-btn">Close</button>
    </div>

</div>
<script>
    (function () {
        var cfg = <?php echo json_encode([
            'printDate' => (string)$print_date,
            'apName' => (string)$ap_name,
            'apPosition' => (string)$ap_position,
            'apCompany' => (string)$ap_company,
            'apCompanyAddress' => (string)$ap_company_address,
            'apHours' => (string)$ap_hours,
            'fullName' => (string)$full_name,
            'studentAddress' => (string)$address,
            'studentPhone' => (string)$phone,
            'useSavedTemplate' => $use_saved_template ? '1' : '0',
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        if (!document.body || !cfg) return;
        Object.keys(cfg).forEach(function (key) {
            document.body.dataset[key] = String(cfg[key] == null ? '' : cfg[key]);
        });
    })();
</script>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
// If a download was requested, capture the generated output and return it as an attachment.
if (isset($do_download_html) && $do_download_html) {
    $html = ob_get_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="application_letter_' . $student_id . '.html"');
    echo $html;
    $conn->close();
    exit;
}

if (isset($do_download_pdf) && $do_download_pdf) {
    $html = ob_get_clean();
    // If Dompdf is available, render PDF; otherwise fallback to HTML attachment
    if (class_exists('Dompdf\\Dompdf')) {
        // instantiate Dompdf (fully-qualified namespace)
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="application_letter_' . $student_id . '.pdf"');
        echo $dompdf->output();
        $conn->close();
        exit;
    } else {
        // fallback: return HTML file and inform user via filename
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="application_letter_' . $student_id . '.html"');
        echo $html;
        $conn->close();
        exit;
    }
}

// normal page close
$conn->close();
?>
