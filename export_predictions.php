<?php
session_start();

// 1. Check if the user is logged in and is an admin or director.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

// --- Handle Filtering (same logic as the view page) ---
$filter_grade = $_GET['grade_level'] ?? '';
$filter_subject = $_GET['subject_id'] ?? '';
$filter_risk = $_GET['risk_level'] ?? '';

$sql = "
    SELECT 
        pp.predicted_grade,
        pp.risk_level,
        pp.risk_factors,
        pp.prediction_date,
        s.first_name,
        s.last_name,
        s.grade_level,
        sub.name AS subject_name
    FROM performance_predictions pp
    JOIN students s ON pp.student_id = s.student_id
    JOIN subjects sub ON pp.subject_id = sub.subject_id
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($filter_grade)) {
    $sql .= " AND s.grade_level = ?";
    $params[] = $filter_grade;
    $types .= 'i';
}
if (!empty($filter_subject)) {
    $sql .= " AND pp.subject_id = ?";
    $params[] = $filter_subject;
    $types .= 'i';
}
if (!empty($filter_risk)) {
    $sql .= " AND pp.risk_level = ?";
    $params[] = $filter_risk;
    $types .= 's';
}

// To get only the latest prediction for each student-subject pair
$sql .= " AND pp.prediction_date = (
            SELECT MAX(p2.prediction_date) 
            FROM performance_predictions p2 
            WHERE p2.student_id = pp.student_id AND p2.subject_id = pp.subject_id
          )";

$sql .= " ORDER BY s.grade_level, s.last_name, s.first_name, sub.name";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result_predictions = mysqli_stmt_get_result($stmt);

// --- Generate CSV ---
$filename = "performance_predictions_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add header row
fputcsv($output, ['Student Name', 'Grade', 'Subject', 'Predicted Grade', 'Risk Level', 'Risk Factors', 'Prediction Date']);

// Add data rows
while ($row = mysqli_fetch_assoc($result_predictions)) {
    fputcsv($output, [
        $row['last_name'] . ', ' . $row['first_name'],
        $row['grade_level'],
        $row['subject_name'],
        $row['predicted_grade'],
        $row['risk_level'],
        $row['risk_factors'],
        $row['prediction_date']
    ]);
}

fclose($output);
mysqli_stmt_close($stmt);
mysqli_close($conn);
exit();
?>