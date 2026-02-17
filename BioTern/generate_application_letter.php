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
if ($student_id <= 0) die('Invalid student id');

$query = "SELECT s.*, c.name as course_name FROM students s LEFT JOIN courses c ON s.course_id = c.id WHERE s.id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) die('Student not found');
$student = $result->fetch_assoc();

$full_name = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$address = $student['address'] ?? '';
$phone = $student['phone'] ?? '';
$today = date('F j, Y');

// Allow overriding some fields via query string when generating from document_application.php
$ap_name = isset($_GET['ap_name']) ? trim($_GET['ap_name']) : '';
$ap_position = isset($_GET['ap_position']) ? trim($_GET['ap_position']) : '';
$ap_company = isset($_GET['ap_company']) ? trim($_GET['ap_company']) : '';
$ap_company_address = isset($_GET['ap_address']) ? trim($_GET['ap_address']) : '';
$print_date = isset($_GET['date']) ? trim($_GET['date']) : $today;

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
    <title>BioTern || Application Letter - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <style>
                /* Use US Letter for printing and set sensible margins */
                    @page { size: Letter portrait; margin: 0.5in; }
                    html,body{ height:100%; margin:0; padding:0; }
                    /* document body uses Times New Roman per spec */
                    body{ font-family: 'Times New Roman', Times, serif; color:#111; background:#fff; font-size:11pt; }
          /* fit printable area: printable width = A4 width - page margins (210mm - 20mm = 190mm)
                            container total (max-width + left/right padding) should not exceed printable width */
                    .container{ width:100%; max-width:7.5in; margin:0 auto; padding:0.4in; box-sizing:border-box; position:relative; }
                    /* Move the centered header text down so the crest/logo has space */
                    .header{ text-align:center; border-bottom:1px solid #8ab0e6; padding-top:-101px; padding-bottom:6px; margin-bottom:10px }
                    /* logo size requested: 0.77in x 0.76in */
                    .crest{ position:absolute; top:0.22in; left:0.22in; width:0.77in; height:0.76in; object-fit:contain; }
        .header img{ max-height:70px; }
        /* Header styles as specified: Calibri (Body), blue colors and sizes */
        .header h2 { font-family: Calibri, 'Calibri', Arial, sans-serif; color: #1b4f9c; font-size:13pt; margin:6px 0 2px 0; }
        .header .meta { font-family: Calibri, 'Calibri', Arial, sans-serif; color:#1b4f9c; font-size:9pt; }
        .header .tel { font-family: Calibri, 'Calibri', Arial, sans-serif; color:#1b4f9c; font-size:11pt; }
        /* Main content font sizes: heading 12pt Times New Roman, body 11pt Times New Roman */
        h3{ font-family: 'Times New Roman', Times, serif; font-size:12pt; color:#000; margin:6px 0; }
        .content{ margin-top:8px; line-height:1.45; font-size:11pt; font-family: 'Times New Roman', Times, serif; }
        .small{ font-size:13px; }
        .signature{ margin-top:28px; }
        /* hide print-value spans on screen, show on print */
        .print-val{ display:none; }
        @media print {
            .blank-input{ display:none !important; }
            .print-val{ display:inline !important; }
            /* reduce spacing on print to better fit one page */
            .header{ padding-bottom:6px; margin-bottom:6px }
            .content{ margin-top:6px; font-size:13px }
            .signature{ margin-top:18px }
            body { background: #fff; }
            .no-print { display: none !important; }
            /* keep printable padding consistent with page margins */
            .container { margin: 0; padding: 10mm; }
            /* avoid page breaks inside main sections */
            .container, .content, .signature { page-break-inside: avoid; }
            /* show print-only crest and hide absolute one to avoid duplicates */
            .crest { display: none !important; }
            .crest-print { display: block !important; position: absolute; top: 0.22in; left: 0.22in; width: 0.77in; height:0.76in; z-index: 2300; object-fit:contain; }
            /* ensure images print with correct colors */
            img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            /* Reduce bottom spacing that might create an extra page */
            p { orphans: 3; widows: 3; }
        }
        /* Simple inputs styling for editable blanks */
        .blank-input{ border:none; border-bottom:1px solid #000; display:inline-block; min-width:120px; padding:2px 4px; font-size:11pt }
        .field-block{ margin:6px 0; }
        .actions{ margin-top:12px; }
    </style>
</head>
<body>
<div class="container">
    <!-- crest at top-left (auth-cover-login-bg.png if available) -->
    <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'">
    <div class="header">
        <!-- print-only inline crest: some print engines omit absolutely positioned images; include an inline, print-visible image to ensure the header logo appears -->
        <img class="crest-print" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'" style="display:none;" />
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga &middot;</div>
        <div class="tel">Telefax No.: (045) 624-0215</div>
    </div>

    <div class="content">
        <h3 style="text-align:center;">Application Approval Sheet</h3>
        <p class="field-block">Date: <input type="text" class="blank-input" id="fld_date" value="<?php echo htmlspecialchars($print_date); ?>"> <span class="print-val" id="pv_date"></span></p>
        <p class="field-block">Mr./Ms.: <input type="text" class="blank-input" id="fld_name" value="<?php echo htmlspecialchars($ap_name ?: ''); ?>"> <span class="print-val" id="pv_name"></span></p>
        <p class="field-block">Position: <input type="text" class="blank-input" id="fld_position" value="<?php echo htmlspecialchars($ap_position ?: ''); ?>"> <span class="print-val" id="pv_position"></span></p>
        <p class="field-block">Name of Company: <input type="text" class="blank-input" id="fld_company" value="<?php echo htmlspecialchars($ap_company ?: ''); ?>"> <span class="print-val" id="pv_company"></span></p>
        <p class="field-block">Company Address: <input type="text" class="blank-input" id="fld_company_address" value="<?php echo htmlspecialchars($ap_company_address ?: ''); ?>" style="min-width:60%;"> <span class="print-val" id="pv_company_address"></span></p>

        <p style="margin-top:30px;">Dear Sir or Madam:</p>

        <p>I am <strong><?php echo htmlspecialchars($full_name); ?></strong>, student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of <strong>250 hours</strong>.</p>

        <p>I would like to apply as a trainee in your company because I believe that the training and experience, I will acquire will broaden my knowledge about my course.</p>

        <p>Thank you for any consideration that you may give to this letter of application.</p>

        <p style="margin-top:30px;">Very truly yours,</p>

        <div class="signature">
            <p style="margin-top:40px;">Student Name: <?php echo htmlspecialchars($full_name); ?></p>
            <p>Student Home Address: <?php echo nl2br(htmlspecialchars($address)); ?></p>
            <p>Contact No.: <?php echo htmlspecialchars($phone); ?></p>
        </div>
    </div>


    <p class="small" style="margin-top:40px;">Student Signature: <span style="display:inline-block; width:260px; border-bottom:1px solid #000; margin-left:8px;"></span></p>
    <p class="small" style="margin-top:40px;">Noted by: <span style="display:inline-block; width:260px; border-bottom:1px solid #000; margin-left:8px;"></span></p>

    <div class="actions no-print" style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <div class="alert alert-info small" role="alert" style="flex:1 1 100%;">
            Tip: To remove the headers and footers (date / URL) from the PDF, uncheck "Headers and footers" or "Include headers and footers" in your browser's print dialog (Chrome/Edge: More settings → uncheck "Headers and footers").
        </div>
        <button id="btn_print" class="btn btn-primary btn-lg">Print / Save as PDF (letter)</button>
        <button id="btn_download_pdf" class="btn btn-success btn-lg">Download PDF</button>
        <button id="btn_download_html" class="btn btn-outline-secondary btn-lg">Download HTML</button>
        <button id="btn_close" class="btn btn-secondary btn-lg">Close</button>
    </div>

</div>
<script>
    // mirror input values into print-only spans and hide inputs when printing
    (function(){
        var fields = ['date','name','position','company','company_address'];
        fields.forEach(function(f){
            var inp = document.getElementById('fld_' + f);
            var pv = document.getElementById('pv_' + f);
            if (inp && pv) {
                pv.textContent = inp.value || '';
                inp.addEventListener('input', function(){ pv.textContent = inp.value; });
            }
        });

        // print button: open print dialog — note: browser headers/footers are controlled by print dialog settings
        document.getElementById('btn_print').addEventListener('click', function(){
            window.print();
        });

        // Download PDF / HTML handlers build the current URL and add a download flag
        function buildDownloadUrl(flag){
            var base = window.location.href.split('#')[0].split('?')[0];
            var params = new URLSearchParams(window.location.search);
            // preserve existing query params (id, ap_name etc.) then set download flag
            params.set(flag, '1');
            return base + '?' + params.toString();
        }

        document.getElementById('btn_download_pdf').addEventListener('click', function(){
            var url = buildDownloadUrl('download_pdf');
            // open in new tab to trigger download
            window.open(url, '_blank');
        });

        document.getElementById('btn_download_html').addEventListener('click', function(){
            var url = buildDownloadUrl('download_html');
            window.open(url, '_blank');
        });

        document.getElementById('btn_close').addEventListener('click', function(){ window.close(); });
    })();
</script>
</body>
</html>

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
