<?php
require_once dirname(__DIR__) . '/config/db.php';
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
$recipient_title = strtolower(trim((string)($_GET['recipient_title'] ?? 'auto')));
if (!in_array($recipient_title, ['auto', 'mr', 'ms', 'none'], true)) {
    $recipient_title = 'auto';
}
$position = isset($_GET['position']) ? trim($_GET['position']) : '';
$company = isset($_GET['company']) ? trim($_GET['company']) : '';
$company_address = isset($_GET['company_address']) ? trim($_GET['company_address']) : '';
$students_raw = isset($_GET['students']) ? trim($_GET['students']) : '';
$greeting_pref = strtolower(trim((string)($_GET['greeting_pref'] ?? 'either')));
if (!in_array($greeting_pref, ['sir', 'maam', 'either'], true)) {
    $greeting_pref = 'either';
}
$use_saved_template = false;

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

function detect_salutation(string $name, string $pref = 'either'): string
{
    if ($pref === 'sir') return 'Dear Sir,';
    if ($pref === 'maam') return "Dear Ma'am,";
    $n = strtolower(trim($name));
    if (str_starts_with($n, 'mr ') || str_starts_with($n, 'mr.') || str_starts_with($n, 'sir')) {
        return 'Dear Sir,';
    }
    if (
        str_starts_with($n, 'ms ') ||
        str_starts_with($n, 'ms.') ||
        str_starts_with($n, 'mrs ') ||
        str_starts_with($n, 'mrs.') ||
        str_starts_with($n, 'maam') ||
        str_starts_with($n, "ma'am") ||
        str_starts_with($n, 'madam')
    ) {
        return "Dear Ma'am,";
    }
    return "Dear Ma'am,";
}

function infer_title_from_name(string $name): string
{
    $n = strtolower(trim($name));
    if ($n === '') return 'none';
    if (str_starts_with($n, 'mr ') || str_starts_with($n, 'mr.') || str_starts_with($n, 'sir ')) return 'mr';
    if (
        str_starts_with($n, 'ms ') ||
        str_starts_with($n, 'ms.') ||
        str_starts_with($n, 'mrs ') ||
        str_starts_with($n, 'mrs.') ||
        str_starts_with($n, 'maam') ||
        str_starts_with($n, "ma'am") ||
        str_starts_with($n, 'madam')
    ) return 'ms';
    $first = preg_split('/\s+/', preg_replace('/[^a-z\s]/', ' ', $n))[0] ?? '';
    $male = ['jomer','jomar','jose','juan','mark','michael','john','james','daniel','paul','peter','kevin','robert','edward','ross','ramirez','sanchez','felix','ivan'];
    $female = ['anna','ana','maria','marie','jane','joy','kim','angel','diana','michelle','grace','sarah','liza','rose','patricia','christine','karen','claire'];
    if (in_array($first, $male, true)) return 'mr';
    if (in_array($first, $female, true)) return 'ms';
    return 'none';
}

if ($recipient_title === 'auto') {
    $recipient_title = infer_title_from_name($recipient);
}
$recipient_base = preg_replace('/^(mr\\.?|ms\\.?|mrs\\.?|sir|maam|ma\\\'am|madam)\\s+/i', '', $recipient);
if (!is_string($recipient_base) || $recipient_base === '') {
    $recipient_base = $recipient;
}
if ($greeting_pref === 'sir') {
    $salutation = 'Dear Sir,';
} elseif ($greeting_pref === 'maam') {
    $salutation = "Dear Ma'am,";
} elseif ($recipient_title === 'mr') {
    $salutation = 'Dear Sir,';
} elseif ($recipient_title === 'ms') {
    $salutation = "Dear Ma'am,";
} elseif ($recipient_title === 'none') {
    $salutation = "Dear Sir/Ma'am,";
} else {
    $salutation = detect_salutation($recipient, 'either');
}
$recipient_print = $recipient_base;
if ($recipient !== '') {
    if ($recipient_title === 'mr') $recipient_print = 'Mr. ' . $recipient_base;
    if ($recipient_title === 'ms') $recipient_print = 'Ms. ' . $recipient_base;
    if ($recipient_title === 'none') $recipient_print = 'Mr./Ms. ' . $recipient_base;
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
        body { font-family: "Times New Roman", Times, serif; color:#111; font-size:12pt; margin:0; background:#eceff3; }
        .container {
            width:100%;
            max-width:7.5in;
            margin:18px auto;
            padding:0.4in;
            box-sizing:border-box;
            position:relative;
            background:#fff;
            box-shadow:0 8px 28px rgba(0, 0, 0, 0.14);
        }
        .header { position:relative; min-height:56px; text-align:center; border-bottom:2px solid #1c5ab1; padding:8px 0 6px; margin-bottom:10px; }
        .crest { position:absolute; left:30px; top:30px; width:70px; height:70px; object-fit:contain; }
        .header h2 { font-family:'Times New Roman', Times, serif; color:#1b4f9c; font-size:13pt; margin:0; font-weight:600; }
        .header .meta { font-family:'Times New Roman', Times, serif; color:#1b4f9c; font-size:10.5pt; line-height:1.2; font-weight:600; }
        .header .tel { font-family:'Times New Roman', Times, serif; color:#1b4f9c; font-size:13pt; font-weight:600; margin-bottom: -21px; }
        .content { font-size:12pt; line-height:1.45; }
        .content h3 { text-align:center; margin:8px 0 12px; font-size:13pt; }
        .signature { margin-top:28px; }
        .ross-signatory { position: relative; margin-top:3px; padding-top:10px; }
        .ross-signature {
            position: absolute;
            top: -16px;
            left: 6px;
            width: 230px;
            max-width: none;
            height: auto;
            z-index: 2;
            pointer-events: none;
        }
        .ross-signatory-text { position: relative; z-index: 1; }
        .actions { margin-top:40px; padding-top:18px; border-top:2px dashed #cbd5e1; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .tip-box { flex:1 1 100%; font-size:17px; line-height:1.6; border:1px solid #dbe4f0; background:#f8fafc; padding:12px 14px; border-radius:12px; color:#334155; }
        .action-btn { min-width:104px; min-height:36px; font-size:14px; font-weight:600; padding:7px 14px; cursor:pointer; border-radius:12px; box-shadow:0 8px 18px rgba(15, 23, 42, 0.08); }
        @media print {
            body { background:#fff; }
            .no-print { display:none !important; }
            .container { margin:0; padding:10mm; background:#fff; box-shadow:none; }
        }
    </style>
</head>
<body>
<div class="container">
    <img class="crest" src="../assets/images/auth/auth-cover-login-bg.png" alt="crest" onerror="this.style.display='none'">
    <div class="header">
        <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
        <div class="meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
        <div class="tel">Telefax No.: (045) 624-0215</div>
    </div>

    <div class="content" id="endorsement_doc_content">
        <h3>ENDORSEMENT LETTER</h3>
        <p><strong id="ed_recipient"><?php echo htmlspecialchars($recipient_print ?: '__________________________'); ?></strong><br>
        <span id="ed_position"><?php echo htmlspecialchars($position ?: '__________________________'); ?></span><br>
        <span id="ed_company"><?php echo htmlspecialchars($company ?: '__________________________'); ?></span><br>
        <span id="ed_company_address"><?php echo htmlspecialchars($company_address ?: '__________________________'); ?></span></p>

        <p><?php echo htmlspecialchars($salutation); ?></p>
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
            <p>MR. JOMAR G. SANGIL<br>
            <strong>ICT DEPARTMENT HEAD</strong><br>
            <strong>Clark College of Science and Technology</strong></p>
            <div class="ross-signatory">
                <img class="ross-signature" src="Ross-Signature.png" alt="Ross signature" onerror="this.style.display='none'">
                <p class="ross-signatory-text">MR. ROSS CARVEL C. RAMIREZ<br>
                <strong>HEAD OF ACADEMIC AFFAIRS</strong><br>
                <strong>Clark College of Science and Technology</strong></p>
            </div>
        </div>
    </div>

    <div class="actions no-print">
        <div class="tip-box">Tip: Use A4 paper. In your print settings, set the margins to Top: 0, Bottom: 0 Left: 0.5, Right: 0.5, and uncheck "Headers and footers" or "Include headers and footers".</div>
        <button id="btn_print" type="button" class="action-btn">Print</button>
        <button id="btn_close" type="button" class="action-btn">Close</button>
    </div>
</div>

<script>
(function(){
    document.getElementById('btn_print').addEventListener('click', function(){ window.print(); });
    document.getElementById('btn_close').addEventListener('click', function(){
        if (window.opener && !window.opener.closed) { window.close(); return; }
        if (window.history.length > 1) { window.history.back(); return; }
        window.location.href = '../documents/document_endorsement.php';
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

