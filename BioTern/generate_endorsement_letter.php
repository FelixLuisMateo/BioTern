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

$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$student = ['first_name' => '', 'middle_name' => '', 'last_name' => ''];
if ($student_id > 0) {
    $stmt = $conn->prepare("SELECT first_name, middle_name, last_name FROM students WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $student_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $student = $row;
}
$full_name = trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));

$recipient = isset($_GET['recipient']) ? trim($_GET['recipient']) : '';
$position = isset($_GET['position']) ? trim($_GET['position']) : '';
$company = isset($_GET['company']) ? trim($_GET['company']) : '';
$company_address = isset($_GET['company_address']) ? trim($_GET['company_address']) : '';
$students_raw = isset($_GET['students']) ? trim($_GET['students']) : '';
$use_saved_template = isset($_GET['use_saved_template']) && $_GET['use_saved_template'] === '1';

$students = [];
if ($students_raw !== '') {
    $students = preg_split('/\r\n|\r|\n/', $students_raw);
    $students = array_values(array_filter(array_map('trim', $students), function($v){ return $v !== ''; }));
}
if (empty($students) && $full_name !== '') {
    $students[] = $full_name;
}
if (empty($students)) {
    $students[] = '__________________________';
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>BioTern || Endorsement Letter</title>
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/favicon.ico">
    <style>
        @page { size: Letter portrait; margin: 0.5in; }
        body { font-family: "Times New Roman", Times, serif; color:#111; font-size:12pt; margin:0; }
        .container { width:100%; max-width:7.5in; margin:0 auto; padding:0.4in; box-sizing:border-box; position:relative; }
        .header { position:relative; min-height:72px; text-align:center; border-bottom:1px solid #8ab0e6; padding:8px 0 6px; margin-bottom:10px; }
        .crest { position:absolute; left:6px; top:2px; width:70px; height:70px; object-fit:contain; }
        .header h2 { font-family:Calibri, Arial, sans-serif; color:#1b4f9c; font-size:14pt; margin:0; font-weight:700; }
        .header .meta, .header .tel { font-family:Calibri, Arial, sans-serif; color:#1b4f9c; font-size:10.5pt; line-height:1.2; }
        .content { font-size:12pt; line-height:1.45; }
        .content h3 { text-align:center; margin:8px 0 12px; font-size:13pt; }
        .signature { margin-top:28px; }
        .actions { margin-top:12px; }
        @media print { .no-print { display:none !important; } .container { padding:10mm; } }
    </style>
</head>
<body>
<div class="container">
    <img class="crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'">
    <div class="header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
        <div class="tel">Telefax No.: (045) 624-0215</div>
    </div>

    <div class="content" id="endorsement_doc_content">
        <h3>ENDORSEMENT LETTER</h3>
        <p><strong id="ed_recipient"><?php echo htmlspecialchars($recipient ?: '__________________________'); ?></strong><br>
        <span id="ed_position"><?php echo htmlspecialchars($position ?: '__________________________'); ?></span><br>
        <span id="ed_company"><?php echo htmlspecialchars($company ?: '__________________________'); ?></span><br>
        <span id="ed_company_address"><?php echo htmlspecialchars($company_address ?: '__________________________'); ?></span></p>

        <p>Dear Ma'am,</p>
        <p>Greetings from Clark College of Science and Technology!</p>

        <p>We are pleased to introduce our Associate in Computer Technology program, designed to promote student success by developing competencies in core Information Technology disciplines. Our curriculum emphasizes practical experience through internships and on-the-job training, fostering a strong foundation in current industry practices.</p>

        <p>In this regard, we are seeking your esteemed company's support in accommodating the following students:</p>
        <ul id="ed_students">
            <?php foreach ($students as $s): ?>
                <li><?php echo htmlspecialchars($s); ?></li>
            <?php endforeach; ?>
        </ul>

        <p>These students are required to complete 250 training hours. We believe that your organization can provide them with invaluable knowledge and skills, helping them to maximize their potential for future careers in IT.</p>
        <p>Our teacher-in-charge will coordinate with you to monitor the students' progress and performance.</p>
        <p>We look forward to a productive partnership with your organization. Thank you for your consideration and support.</p>

        <p>Sincerely,</p>
        <div class="signature">
            <p><strong>MR. JOMAR G. SANGIL</strong><br>
            <strong>ICT DEPARTMENT HEAD</strong><br>
            <strong>Clark College of Science and Technology</strong></p>
            <p style="margin-top:36px;"><strong>MR. ROSS CARVEL C. RAMIREZ</strong><br>
            <strong>HEAD OF ACADEMIC AFFAIRS</strong><br>
            <strong>Clark College of Science and Technology</strong></p>
        </div>
    </div>

    <div class="actions no-print">
        <button id="btn_print">Print / Save PDF</button>
        <button id="btn_close">Close</button>
    </div>
</div>

<script>
(function(){
    document.getElementById('btn_print').addEventListener('click', function(){ window.print(); });
    document.getElementById('btn_close').addEventListener('click', function(){
        if (window.opener && !window.opener.closed) { window.close(); return; }
        if (window.history.length > 1) { window.history.back(); return; }
        window.location.href = 'document_endorsement.php';
    });
})();

(function(){
    if (!<?php echo $use_saved_template ? 'true' : 'false'; ?>) return;
    try {
        var saved = localStorage.getItem('biotern_endorsement_template_html_v1');
        if (!saved) return;
        var temp = document.createElement('div');
        temp.innerHTML = saved;
        var content = temp.querySelector('.content') || temp;
        var out = document.getElementById('endorsement_doc_content');
        if (out && content) out.innerHTML = content.innerHTML;
    } catch (e) {}
})();
</script>
</body>
</html>
<?php $conn->close(); ?>
