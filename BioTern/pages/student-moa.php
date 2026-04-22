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
$doc = null;
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
$stmt = $conn->prepare("SELECT * FROM moa WHERE user_id = ? ORDER BY id DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}
if (!$doc) {
    header('Location: student-documents.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MOA</title>
    <style>
        body{margin:0;padding:24px;background:#e9edf5;font-family:"Times New Roman",Times,serif;color:#111}
        .page{max-width:820px;margin:0 auto;background:#fff;padding:22px 26px 30px;box-shadow:0 10px 30px rgba(15,23,42,.12)}
        h1{text-align:center;font-size:22px;margin:6px 0 18px}
        p{margin:0 0 12px;font-size:18px;line-height:1.45}
        @media print{body{background:#fff;padding:0}.page{box-shadow:none;max-width:none;margin:0}}
    </style>
</head>
<body>
    <div class="page">
        <p style="margin:0 0 12px;text-align:right;">
            <button type="button" onclick="window.print()" style="padding:8px 14px;border:1px solid #1f3b75;background:#1f3b75;color:#fff;border-radius:6px;cursor:pointer;">Print</button>
        </p>
        <h1>Memorandum of Agreement (MOA)</h1>
        <p>Company Name: <?php echo htmlspecialchars((string)($doc['company_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Company Address: <?php echo htmlspecialchars((string)($doc['company_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Partner Representative: <?php echo htmlspecialchars((string)($doc['partner_representative'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Position: <?php echo htmlspecialchars((string)($doc['position'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Total Hours: <?php echo htmlspecialchars((string)($doc['total_hours'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Coordinator: <?php echo htmlspecialchars((string)($doc['coordinator'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Witness: <?php echo htmlspecialchars((string)($doc['witness'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</body>
</html>
