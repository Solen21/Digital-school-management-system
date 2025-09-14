<?php
session_start();

// 1. Security Check: User must be logged in and be an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    die("<h1>Access Denied</h1><p>You do not have permission to perform this action.</p>");
}

// 2. Include necessary files
require_once 'data/db_connect.php';
// Use an absolute path to include the Composer autoloader.
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 3. Define the headers for the Excel file
$headers = [
    'first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender',
    'nationality', 'religion', 'city', 'wereda', 'kebele', 'phone',
    'email', 'emergency_contact', 'blood_type', 'grade_level', 'stream',
    'last_school', 'last_score', 'last_grade'
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header values
$column = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($column.'1', ucwords(str_replace('_', ' ', $header)));
    $column++;
}

// 4. Check if this is a template download request
if (isset($_GET['template']) && $_GET['template'] == 'true') {
    $filename = 'student_import_template.xlsx';
} else {
    // 5. Fetch student data from the database
    $sql = "SELECT " . implode(', ', $headers) . " FROM students ORDER BY last_name, first_name";
    $result = mysqli_query($conn, $sql);

    if ($result) {
        $row_num = 2; // Start from the second row
        while ($row = mysqli_fetch_assoc($result)) {
            $column = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($column.$row_num, $row[$header]);
                $column++;
            }
            $row_num++;
        }
    }
    mysqli_close($conn);
    $filename = 'student_export_' . date('Y-m-d') . '.xlsx';
}

// 6. Set headers for file download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
// If you're serving to IE 9, then the following may be needed
header('Cache-Control: max-age=1');
// If you're serving to IE over SSL, then the following may be needed
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
header('Pragma: public'); // HTTP/1.0

// 7. Write spreadsheet to output
$writer = new Xlsx($spreadsheet);
try {
    $writer->save('php://output');
} catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
    // Log the error or handle it as needed
    die("Error writing file to output: ".$e->getMessage());
}
exit;
?>