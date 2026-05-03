<?php
declare(strict_types=1);

$templatePath = dirname(__DIR__) . '/assets/Internal Students Template.xlsx';
if (!is_file($templatePath)) {
    http_response_code(404);
    echo 'Missing internal OJT template file.';
    exit;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Internal Students Template.xlsx"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($templatePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
header('X-Content-Type-Options: nosniff');
readfile($templatePath);
exit;
