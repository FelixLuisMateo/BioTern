<?php
declare(strict_types=1);

// Generates Internal Students Template.xlsx with ojt_internal-compatible headers.
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoloadPath)) {
    http_response_code(500);
    echo 'Missing composer autoload. Please run composer install.';
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
    'user_id',
    'last_name',
    'first_name',
    'middle_name',
    'course_id',
    'section_id',
    'email',
    'password',
    'status',
    'created_at',
    'update_at',
];

$sheet->fromArray($headers, null, 'A1', true);

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
