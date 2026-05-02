<?php
require_once dirname(__DIR__) . '/config/db.php';

function app_letter_q(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function app_letter_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$studentId = (int)($_GET['id'] ?? 0);
$student = null;
$application = null;

if ($studentId > 0 && isset($conn) && $conn instanceof mysqli) {
    $stmt = $conn->prepare("
        SELECT id, first_name, middle_name, last_name, address, phone, contact_no
        FROM students
        WHERE id = ?
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }

    $exists = $conn->query("SHOW TABLES LIKE 'application_letter'");
    if ($exists instanceof mysqli_result && $exists->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT date, application_person, position, company_name, company_address
            FROM application_letter
            WHERE user_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->bind_param('i', $studentId);
            $stmt->execute();
            $application = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }
    }
}

$fullName = trim((string)(
    ($student['first_name'] ?? '') . ' ' .
    ($student['middle_name'] ?? '') . ' ' .
    ($student['last_name'] ?? '')
));
$studentAddress = trim((string)($student['address'] ?? ''));
$studentContact = trim((string)($student['contact_no'] ?? $student['phone'] ?? ''));

$printDate = app_letter_q('date', (string)($application['date'] ?? date('F j, Y')));
$apName = app_letter_q('ap_name', (string)($application['application_person'] ?? ''));
$apPosition = app_letter_q('ap_position', (string)($application['position'] ?? ''));
$apCompany = app_letter_q('ap_company', (string)($application['company_name'] ?? ''));
$apAddress = app_letter_q('ap_address', (string)($application['company_address'] ?? ''));
$apHours = app_letter_q('ap_hours', '250');
$useSavedTemplate = app_letter_q('use_saved_template') === '1';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTern || Application Letter</title>
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/favicon.ico?v=20260310">
    <link rel="stylesheet" href="../assets/css/modules/documents/generate-letter-shared.css">
    <link rel="stylesheet" href="../assets/css/modules/documents/generate-application-letter-page.css">
</head>
<body>
<main class="main-content"
    data-print-date="<?php echo app_letter_h($printDate); ?>"
    data-ap-name="<?php echo app_letter_h($apName); ?>"
    data-ap-position="<?php echo app_letter_h($apPosition); ?>"
    data-ap-company="<?php echo app_letter_h($apCompany); ?>"
    data-ap-company-address="<?php echo app_letter_h($apAddress); ?>"
    data-ap-hours="<?php echo app_letter_h($apHours); ?>"
    data-full-name="<?php echo app_letter_h($fullName); ?>"
    data-student-address="<?php echo app_letter_h($studentAddress); ?>"
    data-student-phone="<?php echo app_letter_h($studentContact); ?>"
    data-use-saved-template="<?php echo $useSavedTemplate ? '1' : '0'; ?>">
    <div class="container app-application-letter-container">
        <img class="crest app-application-letter-crest" src="../assets/images/ccstlogo.png" alt="CCST Logo" data-hide-onerror="1">
        <div class="header app-application-letter-header">
            <h2>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</h2>
            <div class="meta app-letter-meta">SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga</div>
            <div class="tel app-letter-tel">Telefax No.: (045) 624-0215</div>
        </div>

        <div class="content app-application-letter-content" id="application_doc_content">
            <h3>Application Approval Sheet</h3>
            <p>Date: <span id="ap_date" class="filled-val"><?php echo app_letter_h($printDate); ?></span></p>
            <p>Mr./Ms.: <span id="ap_name" class="filled-val"><?php echo app_letter_h($apName); ?></span></p>
            <p>Position: <span id="ap_position" class="filled-val"><?php echo app_letter_h($apPosition); ?></span></p>
            <p>Name of Company: <span id="ap_company" class="filled-val filled-val-wide"><?php echo app_letter_h($apCompany); ?></span></p>
            <p>Company Address: <span id="ap_address" class="filled-val filled-val-wide"><?php echo app_letter_h($apAddress); ?></span></p>
            <p class="mt-30">Dear Sir or Madam:</p>
            <p>I am <span id="ap_student" class="filled-val filled-val-name"><?php echo app_letter_h($fullName); ?></span>, student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of <strong><span id="ap_hours"><?php echo app_letter_h($apHours); ?></span> hours</strong>.</p>
            <p>I would like to apply as a trainee in your company because I believe that the training and experience, I will acquire will broaden my knowledge about my course.</p>
            <p>Thank you for any consideration that you may give to this letter of application.</p>
            <p class="mt-30">Very truly yours,</p>
            <p class="mt-40">Student Name: <span id="ap_student_name" class="filled-val"><?php echo app_letter_h($fullName); ?></span></p>
            <p>Student Home Address: <span id="ap_student_address" class="filled-val filled-val-wide"><?php echo app_letter_h($studentAddress); ?></span></p>
            <p>Contact No.: <span id="ap_student_contact" class="filled-val"><?php echo app_letter_h($studentContact); ?></span></p>
        </div>

        <div class="actions app-letter-actions no-print">
            <div class="tip-box app-letter-tip-box">Tip: Use A4 paper. In your print settings, uncheck "Headers and footers".</div>
            <button id="btn_print" type="button" class="action-btn app-letter-action-btn">Print</button>
            <button id="btn_close" type="button" class="action-btn app-letter-action-btn">Close</button>
        </div>
    </div>
</main>
<script src="../assets/js/global-ui-helpers.js"></script>
<script src="../assets/js/modules/documents/generate-application-letter-runtime.js"></script>
</body>
</html>
