<?php
require_once '../config/db.php';

$student_id = (int)($_GET['student_id'] ?? 0);
$doc_type = strtolower(trim((string)($_GET['doc_type'] ?? 'application')));

$allowed_templates = [
    'application' => 'document_application.php',
    'endorsement' => 'document_endorsement.php',
    'moa' => 'document_moa.php',
    'dtr' => 'document_dtr.php',
    'dau_moa' => 'document_dau_moa.php',
    'waiver' => 'document_waiver.php',
    'resume' => 'document_resume.php',
];

if ($student_id <= 0) {
    http_response_code(400);
    die('Student not specified.');
}
if (!isset($allowed_templates[$doc_type])) {
    http_response_code(400);
    die('Invalid document type.');
}

$student = null;
$stmt_student = $conn->prepare("SELECT s.*, i.coordinator_id FROM students s LEFT JOIN internships i ON i.student_id = s.id AND i.status IN ('ongoing','completed') WHERE s.id = ? LIMIT 1");
if ($stmt_student) {
    $stmt_student->bind_param('i', $student_id);
    $stmt_student->execute();
    $student = $stmt_student->get_result()->fetch_assoc();
    $stmt_student->close();
}

if (!$student) {
    http_response_code(404);
    die('Student not found.');
}

$coordinator = null;
$coordinator_id = (int)($student['coordinator_id'] ?? 0);
if ($coordinator_id > 0) {
    $stmt_coordinator = $conn->prepare("SELECT * FROM coordinators WHERE id = ? LIMIT 1");
    if ($stmt_coordinator) {
        $stmt_coordinator->bind_param('i', $coordinator_id);
        $stmt_coordinator->execute();
        $coordinator = $stmt_coordinator->get_result()->fetch_assoc();
        $stmt_coordinator->close();
    }
}

$template_file = __DIR__ . '/' . $allowed_templates[$doc_type];
if (!is_file($template_file)) {
    http_response_code(404);
    die('Template not found.');
}

ob_start();
include $template_file;
$content = ob_get_clean();

header('Content-Type: text/html; charset=utf-8');
echo $content;


