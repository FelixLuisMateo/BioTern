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
$letter = null;

$studentStmt = $conn->prepare("SELECT id FROM students WHERE user_id = ? OR id = ? LIMIT 1");
if ($studentStmt) {
    $studentStmt->bind_param('ii', $currentUserId, $currentUserId);
    $studentStmt->execute();
    $student = $studentStmt->get_result()->fetch_assoc() ?: null;
    $studentStmt->close();
}

if (!$student) {
    header('Location: student-documents.php');
    exit;
}

$studentId = (int)($student['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM endorsement_letter WHERE user_id = ? ORDER BY id DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $letter = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

if (!$letter) {
    header('Location: student-documents.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Endorsement Letter</title>
    <style>
        body{margin:0;padding:24px;background:#e9edf5;font-family:"Times New Roman",Times,serif;color:#111}
        .page{max-width:780px;margin:0 auto;background:#fff;padding:22px 26px 30px;box-shadow:0 10px 30px rgba(15,23,42,.12)}
        .header{display:flex;align-items:center;gap:16px;border-bottom:1px solid #999;padding-bottom:8px;margin-bottom:14px}
        .header img{width:78px;height:auto}
        .school{text-align:center;flex:1;line-height:1.35;font-size:12px}
        .school strong{display:block;font-size:15px}
        h1{text-align:center;font-size:22px;margin:10px 0 18px}
        p{margin:0 0 14px;font-size:18px;line-height:1.55}
        @media print{body{background:#fff;padding:0}.page{box-shadow:none;max-width:none;margin:0}}
    </style>
</head>
<body>
    <div class="page">
        <p style="margin:0 0 12px;text-align:right;">
            <button type="button" onclick="window.print()" style="padding:8px 14px;border:1px solid #1f3b75;background:#1f3b75;color:#fff;border-radius:6px;cursor:pointer;">Print</button>
        </p>
        <div class="header">
            <img src="../assets/images/ccstlogo.png" alt="CCST Logo">
            <div class="school">
                <strong>CLARK COLLEGE OF SCIENCE AND TECHNOLOGY</strong>
                SNS Bldg. Aurea St., Samsonville Subd., Dau, Mabalacat, Pampanga
                <br>Telefax No.: (045) 624-0215
            </div>
        </div>
        <h1>Endorsement Letter</h1>
        <p>To: <?php echo htmlspecialchars((string)($letter['recipient_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Position: <?php echo htmlspecialchars((string)($letter['recipient_position'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Company: <?php echo htmlspecialchars((string)($letter['company_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Address: <?php echo htmlspecialchars((string)($letter['company_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Dear Sir/Ma'am,</p>
        <p>This letter serves as endorsement for <?php echo htmlspecialchars((string)($letter['students_to_endorse'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> for internship placement.</p>
    </div>
</body>
</html>
