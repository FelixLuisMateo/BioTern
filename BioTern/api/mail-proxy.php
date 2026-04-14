<?php
define('BIOTERN_MAIL_PROXY', true);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/lib/mailer.php';

if (!isset($conn) || $conn->connect_errno) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database connection failed']);
    exit;
}

$settings = biotern_mail_settings($conn);
$token = trim((string)($settings['mail_http_token'] ?? ''));
$headerToken = '';

if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $headerToken = trim((string)$_SERVER['HTTP_AUTHORIZATION']);
    if (stripos($headerToken, 'Bearer ') === 0) {
        $headerToken = trim(substr($headerToken, 7));
    }
}
if ($headerToken === '' && isset($_SERVER['HTTP_X_MAIL_TOKEN'])) {
    $headerToken = trim((string)$_SERVER['HTTP_X_MAIL_TOKEN']);
}

if ($token === '' || $headerToken === '' || !hash_equals($token, $headerToken)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?? '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$to = trim((string)($payload['to'] ?? ''));
$subject = trim((string)($payload['subject'] ?? ''));
$text = (string)($payload['text'] ?? '');
$html = (string)($payload['html'] ?? '');

if ($to === '' || $subject === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

$errorRef = null;
$sent = biotern_send_mail($conn, $to, $subject, $text, $html, $errorRef);
if ($sent) {
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(500);
echo json_encode(['ok' => false, 'error' => 'Mail send failed', 'ref' => $errorRef]);
