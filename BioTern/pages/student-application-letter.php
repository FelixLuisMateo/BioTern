<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth-session.php';

biotern_boot_session(isset($conn) ? $conn : null);

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$currentRole = strtolower(trim((string)($_SESSION['role'] ?? $_SESSION['user_role'] ?? '')));
if ($currentRole !== 'student' || $currentUserId <= 0) {
    header('Location: homepage.php');
    exit;
}

$student = null;
$application = null;

$studentStmt = $conn->prepare("
    SELECT s.id, s.first_name, s.middle_name, s.last_name, s.address, s.phone, s.contact_no
    FROM students s
    WHERE s.user_id = ?
    LIMIT 1
");
if ($studentStmt) {
    $studentStmt->bind_param('i', $currentUserId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc() ?: null;
    $studentStmt->close();
}

if (!$student) {
    header('Location: student-documents.php');
    exit;
}

$applicationStmt = $conn->prepare("
    SELECT date, application_person, position, company_name, company_address
    FROM application_letter
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 1
");
if ($applicationStmt) {
    $studentId = (int)($student['id'] ?? 0);
    $applicationStmt->bind_param('i', $studentId);
    $applicationStmt->execute();
    $application = $applicationStmt->get_result()->fetch_assoc() ?: null;
    $applicationStmt->close();
}

if (!$application) {
    header('Location: student-documents.php');
    exit;
}

$studentName = trim((string)(
    ($student['first_name'] ?? '') . ' ' .
    ($student['middle_name'] ?? '') . ' ' .
    ($student['last_name'] ?? '')
));
$studentAddress = trim((string)($student['address'] ?? ''));
$studentContact = trim((string)($student['contact_no'] ?? $student['phone'] ?? ''));
$letterDateRaw = trim((string)($application['date'] ?? ''));
$letterDate = $letterDateRaw !== '' && $letterDateRaw !== '0000-00-00'
    ? date('Y-m-d', strtotime($letterDateRaw))
    : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Approval Sheet</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            background: #e9edf5;
            font-family: "Times New Roman", Times, serif;
            color: #111;
        }
        .page {
            max-width: 780px;
            margin: 0 auto;
            background: #fff;
            padding: 22px 26px 30px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        }
        .header {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 1px solid #999;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .header img {
            width: 78px;
            height: auto;
        }
        .school {
            text-align: center;
            flex: 1;
            line-height: 1.35;
            font-size: 12px;
        }
        .school strong {
            display: block;
            font-size: 15px;
        }
        h1 {
            text-align: center;
            font-size: 22px;
            margin: 10px 0 18px;
        }
        p {
            margin: 0 0 14px;
            font-size: 18px;
            line-height: 1.55;
        }
        .compact {
            margin-bottom: 10px;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .page {
                box-shadow: none;
                max-width: none;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <img src="../assets/images/ccstlogo.png" alt="CCST Logo">
            <div class="school">
                <strong>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</strong>
                SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga
                <br>
                Telefax No.: (045) 624-0215
            </div>
        </div>

        <h1>Application Approval Sheet</h1>

        <p class="compact">Date: <?php echo htmlspecialchars($letterDate, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="compact">Mr./Ms.: <?php echo htmlspecialchars((string)($application['application_person'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="compact">Position: <?php echo htmlspecialchars((string)($application['position'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="compact">Name of Company: <?php echo htmlspecialchars((string)($application['company_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="compact">Company Address: <?php echo htmlspecialchars((string)($application['company_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>

        <p>Dear Sir or Madam:</p>

        <p>I am <?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?>, student of Clark College of Science and Technology. In partial fulfillment of the requirements of this course, I am required to have an On-the-job Training ( OJT ) for a minimum of 250 hours.</p>

        <p>I would like to apply as a trainee in your company because I believe that the training and experience, I will acquire will broaden my knowledge about my course.</p>

        <p>Thank you for any consideration that you may give to this letter of application.</p>

        <p>Very truly yours,</p>

        <p class="compact">Student Name: <?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="compact">Student Home Address: <?php echo htmlspecialchars($studentAddress, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="compact">Contact No.: <?php echo htmlspecialchars($studentContact, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</body>
</html>
