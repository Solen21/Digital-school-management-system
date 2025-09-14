<?php
session_start();

// 1. Security Check
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['director', 'admin'])) {
    die("<h1>Access Denied</h1><p>You do not have permission to perform this action.</p>");
}

// 2. Include necessary files
require_once 'data/db_connect.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 3. Define the headers
$headers = ['Student Name', 'Grade', 'Section', 'Overall Average'];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set header values
$column = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($column.'1', $header);
    $column++;
}

// 4. Fetch data from the database
$sql = "
    SELECT
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        s.grade_level,
        c.name as classroom_name,
        AVG(g.total) as average_grade
    FROM students s
    LEFT JOIN grades g ON s.student_id = g.student_id
    LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
    LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
    WHERE s.status = 'active'
    GROUP BY s.student_id
    ORDER BY s.grade_level, c.name, s.last_name;
";
$result = mysqli_query($conn, $sql);

if ($result) {
    $row_num = 2;
    while ($row = mysqli_fetch_assoc($result)) {
        $sheet->setCellValue('A'.$row_num, $row['student_name']);
        $sheet->setCellValue('B'.$row_num, $row['grade_level']);
        $sheet->setCellValue('C'.$row_num, $row['classroom_name'] ?? 'N/A');
        $sheet->setCellValue('D'.$row_num, $row['average_grade'] ? number_format($row['average_grade'], 2) : 'N/A');
        $row_num++;
    }
}
mysqli_close($conn);

// 5. Set headers for file download
$filename = 'student_grade_averages_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// 6. Write spreadsheet to output
$writer = new Xlsx($spreadsheet);
try {
    $writer->save('php://output');
} catch (\PhpOffice\PhpSpreadsheet\Writer\Exception $e) {
    die("Error writing file to output: ".$e->getMessage());
}
exit;
?>