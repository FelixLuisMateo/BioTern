<?php
$student_name = trim((string)(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
$today = date('F d, Y');
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Waiver</title>
    <style>body{font-family:Arial,sans-serif;max-width:860px;margin:24px auto;line-height:1.5;padding:0 16px}h2{text-align:center;margin-bottom:24px}</style>
</head>
<body>
    <h2>Internship Waiver</h2>
    <p>Date: <?php echo htmlspecialchars($today); ?></p>
    <p>I, <strong><?php echo htmlspecialchars($student_name !== '' ? $student_name : '________________'); ?></strong>, voluntarily agree to participate in internship activities under the guidance of the institution and assigned supervisors.</p>
    <p>I acknowledge that I am responsible for following internship policies, attendance rules, and safety requirements.</p>
    <br><br>
    <p>Student Signature: ______________________________</p>
</body>
</html>
