<?php
require_once dirname(__DIR__) . '/config/db.php';
// Generate printable Application Letter for a student
$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$db_user = defined('DB_USER') ? DB_USER : 'root';
$db_password = defined('DB_PASS') ? DB_PASS : ''; 
$db_name = defined('DB_NAME') ? DB_NAME : 'biotern_db';

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

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>BioTern || Application Letter - <?php echo htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) ?: 'Preview'); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="/BioTern/BioTern_unified/assets/images/favicon.ico?v=20260310">
    <style>
                /* Use US Letter for printing and set sensible margins */
                    @page { size: Letter portrait; margin: 0.5in; }
                    html,body{ height:100%; margin:0; padding:0; }
                    /* document body uses Times New Roman per spec */
                    body{ font-family: 'Times New Roman', Times, serif; color:#111; background:#eceff3; font-size:13pt; }
          /* fit printable area: printable width = A4 width - page margins (210mm - 20mm = 190mm)
                            container total (max-width + left/right padding) should not exceed printable width */
                    .container{
                        width:100%;
                        max-width:7.5in;
                        margin:18px auto;
                        padding:0.4in;
                        box-sizing:border-box;
                        position:relative;
                        background:#fff;
                        box-shadow:0 8px 28px rgba(0, 0, 0, 0.14);
                    }
                    .header{
                        position: relative;
                        min-height: 60px;
                        text-align:center;
                        border-bottom:2px solid #1c5ab1;
                        padding: 0 0 0.04in 0;
                        margin-bottom:10px;
                    }
                    /* logo size requested: 0.77in x 0.76in */
                    .crest{ position:absolute; top:0.24in; left:0.12in; width:0.82in; height:0.80in; object-fit:contain; }
        /* Header styles as specified: Calibri (Body), blue colors and sizes */
        .header h2 { font-family: 'Times New Roman', Times, serif; color: #1b4f9c; font-size:14pt; margin:4px 0 2px 0; }
        .header .meta { font-family: 'Times New Roman', Times, serif; color:#1b4f9c; font-size:10pt; font-weight:700; }
        .header .tel { font-family: 'Times New Roman', Times, serif; color:#1b4f9c; font-size:12pt; font-weight:700; }
        /* Main content font sizes: heading 12pt Times New Roman, body 11pt Times New Roman */
        h3{ font-family: 'Times New Roman', Times, serif; font-size:11pt; color:#000; margin:6px 0; text-align:center; }
        #application_doc_content h3{
            text-align:center !important;
            width:100%;
            display:block;
            margin-left:auto;
            margin-right:auto;
        }
        .content{ margin-top:8px; line-height:1.45; font-size:14pt; font-family: 'Times New Roman', Times, serif; }
        .small{ font-size:15px; }
        .signature{ margin-top:28px; }
        .filled-val{
            display:inline-block;
            border-bottom:1px solid #000;
            min-width:180px;
            padding:0 2px;
            line-height:1.1;
            vertical-align:baseline;
        }
        .filled-val-wide{ min-width:240px; }
        .filled-val-name{ min-width:170px; }
        @media print {
            /* reduce spacing on print to better fit one page */
            .header{ padding-bottom:6px; margin-bottom:6px }
            .content{ margin-top:16px; font-size:15px }
            .signature{ margin-top:18px }
            body { background: #fff; }
            .no-print { display: none !important; }
            /* keep printable padding consistent with page margins */
            .container { margin: 0; padding: 10mm; background:#fff; box-shadow:none; }
            /* avoid page breaks inside main sections */
            .container, .content, .signature { page-break-inside: avoid; }
            .crest { display: block !important; }
            /* ensure images print with correct colors */
            img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            /* Reduce bottom spacing that might create an extra page */
            p { orphans: 3; widows: 3; }
        }
        .field-block{ margin:6px 0; }
        .actions{
            margin-top:40px;
            padding-top:18px;
            border-top:2px dashed #cbd5e1;
        }
        .tip-box{
            flex:1 1 100%;
            font-size:17px;
            line-height:1.6;
            border:1px solid #dbe4f0;
            background:#f8fafc;
            padding:12px 14px;
            border-radius:12px;
            color:#334155;
        }
        .action-btn{
            min-width:104px;
            min-height:36px;
            font-size:14px;
            font-weight:600;
            padding:7px 14px;
            border-radius:12px;
            box-shadow:0 8px 18px rgba(15, 23, 42, 0.08);
        }
    </style>
</head>
<body>
<div class="container">
    <!-- crest at top-left (auth-cover-login-bg.png if available) -->
    <img class="crest" src="../assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'">
    <div class="header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
        <div class="tel">Telefax No.: (045) 624-0215</div>
    </div>

    <div class="content" id="application_doc_content">
        <h3 style="text-align:center;">Application Approval Sheet</h3>
        <p class="field-block">Date: <span class="filled-val" id="ap_date"><?php echo htmlspecialchars($print_date); ?></span></p>
        <p class="field-block">Mr./Ms.: <span class="filled-val" id="ap_name"><?php echo htmlspecialchars($ap_name ?: ''); ?></span></p>
        <p class="field-block">Position: <span class="filled-val" id="ap_position"><?php echo htmlspecialchars($ap_position ?: ''); ?></span></p>
        <p class="field-block">Name of Company: <span class="filled-val filled-val-wide" id="ap_company"><?php echo htmlspecialchars($ap_company ?: ''); ?></span></p>
        <p class="field-block">Company Address: <span class="filled-val filled-val-wide" id="ap_address"><?php echo htmlspecialchars($ap_company_address ?: ''); ?></span></p>

        <p style="margin-top:30px;">Dear Sir or Madam:</p>

        <p>I am <span class="filled-val filled-val-name"><?php echo htmlspecialchars($full_name); ?></span>, student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of <strong><span id="ap_hours"><?php echo htmlspecialchars($ap_hours); ?></span> hours</strong>.</p>

        <p>I would like to apply as a trainee in your company because I believe that the training and experience, I will acquire will broaden my knowledge about my course.</p>

        <p>Thank you for any consideration that you may give to this letter of application.</p>

        <p style="margin-top:30px;">Very truly yours,</p>

        <div class="signature">
            <p style="margin-top:40px;">Student Name: <span class="filled-val"><?php echo htmlspecialchars($full_name); ?></span></p>
            <p>Student Home Address: <span class="filled-val filled-val-wide"><?php echo nl2br(htmlspecialchars($address)); ?></span></p>
            <p>Contact No.: <span class="filled-val"><?php echo htmlspecialchars($phone); ?></span></p>
        </div>
    </div>


    <p class="small" style="margin-top:40px;">Student Signature: <span style="display:inline-block; width:260px; border-bottom:1px solid #000; margin-left:8px;"></span></p>
    <p class="small" style="margin-top:40px;">Noted by: <span style="display:inline-block; width:260px; border-bottom:1px solid #000; margin-left:8px;"></span></p>

    <div class="actions no-print" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <div class="tip-box" role="alert">
            Tip: Use A4 paper. In your print settings, set the margins to Top: 0, Bottom: 0, Left: 0.5, Right: 0.5, and uncheck "Headers and footers" or "Include headers and footers".
        </div>
        <button id="btn_print" type="button" class="btn btn-primary btn-lg action-btn" onclick="window.print(); return false;">Print</button>
        <button id="btn_close" type="button" class="btn btn-secondary btn-lg action-btn" onclick="window.location.href='../documents/document_application.php'; return false;">Close</button>
    </div>

</div>
<script>
    (function(){
        function ensurePrintableHoursSpan(value) {
            var doc = document.getElementById('application_doc_content');
            if (!doc) return null;

            var existing = doc.querySelector('#ap_hours');
            if (existing) {
                existing.textContent = value || existing.textContent || '250';
                return existing;
            }

            var paragraphs = doc.querySelectorAll('p');
            paragraphs.forEach(function(p){
                if (existing) return;
                var text = (p.textContent || '').replace(/\s+/g, ' ').trim();
                if (text.indexOf('I am ') !== 0) return;
                if (text.indexOf('minimum of') === -1 || text.indexOf('hours') === -1) return;
                p.innerHTML = p.innerHTML.replace(
                    /minimum of\s*<strong>[\s\S]*?hours<\/strong>/i,
                    'minimum of <strong><span id="ap_hours">' + String(value || '250').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span> hours</strong>'
                );
                existing = doc.querySelector('#ap_hours');
            });

            return existing;
        }

        function setText(id, value) {
            var el = document.getElementById(id);
            if (!el) return;
            el.textContent = value || '';
        }

        function applyRuntimeValues() {
            setText('ap_date', <?php echo json_encode($print_date); ?>);
            setText('ap_name', <?php echo json_encode($ap_name); ?>);
            setText('ap_position', <?php echo json_encode($ap_position); ?>);
            setText('ap_company', <?php echo json_encode($ap_company); ?>);
            setText('ap_address', <?php echo json_encode($ap_company_address); ?>);
            ensurePrintableHoursSpan(<?php echo json_encode($ap_hours); ?>);
            setText('ap_student', <?php echo json_encode($full_name); ?>);
            setText('ap_student_name', <?php echo json_encode($full_name); ?>);
            setText('ap_student_address', <?php echo json_encode($address); ?>);
            setText('ap_student_contact', <?php echo json_encode($phone); ?>);
        }

        // Apply once for default template content.
        applyRuntimeValues();
        // Expose for later call after saved-template injection.
        window.__applyApplicationRuntimeValues = applyRuntimeValues;
    })();

    (function(){
        function escHtml(s) {
            return String(s || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function underlineLabelValue(label, extraClass) {
            var doc = document.getElementById('application_doc_content');
            if (!doc) return;
            var nodes = doc.querySelectorAll('p');
            nodes.forEach(function(p){
                if (p.querySelector('input, textarea, select')) return;
                var text = (p.textContent || '').replace(/\s+/g, ' ').trim();
                if (text.indexOf(label) !== 0) return;
                var value = text.slice(label.length).trim();
                var cls = 'filled-val' + (extraClass ? (' ' + extraClass) : '');
                p.innerHTML = label + ' <span class="' + cls + '">' + escHtml(value) + '</span>';
            });
        }

        function underlineIAmLine() {
            var doc = document.getElementById('application_doc_content');
            if (!doc) return;
            var nodes = doc.querySelectorAll('p');
            nodes.forEach(function(p){
                var html = (p.innerHTML || '').trim();
                if (html.indexOf('I am') !== 0) return;
                if (html.indexOf('filled-val-name') !== -1) return;
                p.innerHTML = html.replace(
                    /^I am\s*(.*?)\s*,\s*student of/i,
                    'I am <span class="filled-val filled-val-name">$1</span>, student of'
                );
            });
        }

        function forceApplicationUnderlines() {
            underlineLabelValue('Date:');
            underlineLabelValue('Mr./Ms.:');
            underlineLabelValue('Position:');
            underlineLabelValue('Name of Company:', 'pv-wide');
            underlineLabelValue('Company Address:', 'pv-wide');
            underlineLabelValue('Student Name:');
            underlineLabelValue('Student Home Address:', 'filled-val-wide');
            underlineLabelValue('Contact No.:');
            underlineIAmLine();
        }

        forceApplicationUnderlines();
        window.__forceApplicationUnderlines = forceApplicationUnderlines;
    })();

    // button actions
    (function(){
        ensurePrintableHoursSpan(<?php echo json_encode($ap_hours); ?>);

        // print button: open print dialog — note: browser headers/footers are controlled by print dialog settings
        var printButton = document.getElementById('btn_print');
        if (printButton) {
            printButton.addEventListener('click', function(e){
                e.preventDefault();
                window.print();
            });
        }

        var closeButton = document.getElementById('btn_close');
        if (closeButton) {
            closeButton.addEventListener('click', function(e){
                e.preventDefault();
                if (window.opener && !window.opener.closed) {
                    window.close();
                    return;
                }
                if (window.history.length > 1) {
                    window.history.back();
                    return;
                }
                window.location.href = '../documents/document_application.php';
            });
        }
    })();

    (function(){
        if (!<?php echo $use_saved_template ? 'true' : 'false'; ?>) return;
        try {
            var saved = localStorage.getItem('biotern_application_template_html_v1');
            var doc = document.getElementById('application_doc_content');
            var pageCrest = document.querySelector('.crest');
            if (saved && doc) {
                var temp = document.createElement('div');
                temp.innerHTML = saved;
                var extracted = temp.querySelector('.content');
                var savedCrest = temp.querySelector('.crest');
                if (savedCrest && pageCrest) {
                    var style = savedCrest.style || {};
                    if (style.left) pageCrest.style.left = style.left;
                    if (style.top) pageCrest.style.top = style.top;
                    if (style.width) pageCrest.style.width = style.width;
                    if (style.height) pageCrest.style.height = style.height;
                }
                if (!extracted) extracted = temp.querySelector('#application_doc_content');
                if (extracted) {
                    doc.innerHTML = extracted.innerHTML;
                } else {
                    var oldHeader = temp.querySelector('.header');
                    if (oldHeader) oldHeader.remove();
                    var oldCrest = temp.querySelector('.crest');
                    if (oldCrest) oldCrest.remove();
                    doc.innerHTML = temp.innerHTML || saved;
                }
                if (typeof window.__applyApplicationRuntimeValues === 'function') {
                    window.__applyApplicationRuntimeValues();
                }
                if (typeof window.__forceApplicationUnderlines === 'function') {
                    window.__forceApplicationUnderlines();
                }
                var title = doc.querySelector('h3');
                if (title) title.style.textAlign = 'center';
            }
        } catch (err) {}
    })();
</script>
</body>
</html>

<?php
require_once dirname(__DIR__) . '/config/db.php';
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


