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
$use_saved_template = isset($_GET['use_saved_template']) && $_GET['use_saved_template'] === '1';

$students = [];
if ($students_raw !== '') {
    $students = preg_split('/\r\n|\r|\n/', $students_raw);
    $students = array_values(array_filter(array_map('trim', $students), function ($v) {
        return $v !== '';
    }));
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
    $male = ['jomer', 'jomar', 'jose', 'juan', 'mark', 'michael', 'john', 'james', 'daniel', 'paul', 'peter', 'kevin', 'robert', 'edward', 'ross', 'ramirez', 'sanchez', 'felix', 'ivan'];
    $female = ['anna', 'ana', 'maria', 'marie', 'jane', 'joy', 'kim', 'angel', 'diana', 'michelle', 'grace', 'sarah', 'liza', 'rose', 'patricia', 'christine', 'karen', 'claire'];
    if (in_array($first, $male, true)) return 'mr';
    if (in_array($first, $female, true)) return 'ms';
    return 'none';
}

if ($recipient_title === 'auto') {
    $recipient_title = infer_title_from_name($recipient);
}
$recipient_base = preg_replace('/^(mr\\.?|ms\\.?|mrs\\.?|sir|maam|ma\\x27am|madam)\\s+/i', '', $recipient);
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

$script_name = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$asset_prefix = (strpos($script_name, '/pages/') !== false) ? '../' : '';
$page_title = 'BioTern || Endorsement Letter';
$base_href = $asset_prefix;
$page_body_class = 'app-generate-page';
$page_styles = [
    'assets/css/generate-shell-clean.css',
    'assets/css/generate-letter-shared.css',
    'assets/css/generate-endorsement-letter-page.css',
];
$page_scripts = [
    'assets/js/generate-endorsement-letter-runtime.js',
];

include __DIR__ . '/../includes/header.php';
?>
<div class="main-content" data-use-saved-template="<?php echo $use_saved_template ? '1' : '0'; ?>">
    <div class="container app-endorsement-letter-container">
        <img class="crest app-endorsement-letter-crest" src="assets/images/auth/auth-cover-login-bg.png" alt="crest" data-hide-onerror="1">
        <div class="header app-endorsement-letter-header">
            <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
            <div class="meta app-endorsement-letter-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
            <div class="tel app-endorsement-letter-tel">Telefax No.: (045) 624-0215</div>
        </div>

        <div class="content app-endorsement-letter-content" id="endorsement_doc_content">
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
            <div class="signature app-endorsement-letter-signature">
                <p>MR. JOMAR G. SANGIL<br>
                    <strong>ICT DEPARTMENT HEAD</strong><br>
                    <strong>Clark College of Science and Technology</strong></p>
                <div class="ross-signatory app-endorsement-letter-ross-signatory">
                    <img class="ross-signature app-endorsement-letter-ross-signature" src="pages/Ross-Signature.png" alt="Ross signature" data-hide-onerror="1">
                    <p class="ross-signatory-text app-endorsement-letter-ross-signatory-text">MR. ROSS CARVEL C. RAMIREZ<br>
                        <strong>HEAD OF ACADEMIC AFFAIRS</strong><br>
                        <strong>Clark College of Science and Technology</strong></p>
                </div>
            </div>
        </div>

        <div class="actions app-endorsement-letter-actions no-print app-endorsement-letter-no-print">
            <div class="tip-box app-endorsement-letter-tip-box">Tip: Use A4 paper. In your print settings, set the margins to Top: 0, Bottom: 0 Left: 0.5, Right: 0.5, and uncheck "Headers and footers" or "Include headers and footers".</div>
            <button id="btn_print" type="button" class="action-btn app-endorsement-letter-action-btn">Print</button>
            <button id="btn_close" type="button" class="action-btn app-endorsement-letter-action-btn">Close</button>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php $conn->close(); ?>

