<?php
declare(strict_types=1);

// Generates Internal Students Template.xlsx with ojt_internal-compatible headers.
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    $templatePath = dirname(__DIR__) . '/assets/Internal Students Template.xlsx';
    if (is_file($templatePath)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="Internal Students Template.xlsx"');
        header('Content-Length: ' . filesize($templatePath));
        header('Cache-Control: max-age=0');
        readfile($templatePath);
        exit;
    }

    http_response_code(500);
    echo 'Missing template file.';
    exit;
}

require_once $autoloadPath;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Internal Students Template.xlsx"');
header('Cache-Control: max-age=0');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Internal Students');

$headers = [
    'student_no',
    'last_name',
    'first_name',
    'middle_name',
    'email',
    'course_id',
    'section_id',
    'password',
];

$sheet->fromArray($headers, null, 'A1', true);

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
