<?php
// Generates a real Excel file for Internal Students Template using PhpSpreadsheet
require_once dirname(__DIR__) . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Internal Students Template.xlsx"');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$headers = [
    'student_no', 'user_id', 'last_name', 'first_name', 'middle_name',
    'course_id', 'section_id', 'email', 'password', 'status', 'created_at', 'update_at'
];
$sheet->fromArray($headers, NULL, 'A1');
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
