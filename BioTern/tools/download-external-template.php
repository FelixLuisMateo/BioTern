<?php
declare(strict_types=1);

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    $templatePath = dirname(__DIR__) . '/assets/External Students Template.xlsx';
    if (is_file($templatePath)) {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="External Students Template.xlsx"');
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
header('Content-Disposition: attachment; filename="External Students Template.xlsx"');
header('Cache-Control: max-age=0');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('External Students');

$headers = [
    'student_no',
    'school_year',
    'student_name',
    'contact_no',
    'section',
    'company_name',
    'company_address',
    'supervisor_name',
    'supervisor_position',
    'company_representative',
    'status',
];

$sheet->fromArray($headers, null, 'A1', true);

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
