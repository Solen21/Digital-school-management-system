<?php
session_start();

// 1. Security Check
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    die("<h1>Access Denied</h1><p>You do not have permission to perform this action.</p>");
}

// 2. Include necessary files
require_once 'data/db_connect.php';

// 3. Get filter parameters from the URL
$filter_user_id = $_GET['filter_user_id'] ?? '';
$filter_action = $_GET['filter_action'] ?? '';
// 4. Build the SQL query based on filters (same logic as view page)
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($filter_user_id)) {
    $where_clauses[] = "al.user_id = ?";
    $params[] = $filter_user_id;
    $param_types .= 'i';
}
if (!empty($filter_action)) {
    $where_clauses[] = "al.action_type = ?";
    $params[] = $filter_action;
    $param_types .= 's';
}

$sql = "
    SELECT al.created_at, u.username, al.action_type, al.details
    FROM activity_logs al

    JOIN users u ON al.user_id = u.user_id
";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY al.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// 5. Set headers for CSV download
$filename = "activity_log_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 6. Write to output stream
$output = fopen('php://output', 'w');

// Write header row
fputcsv($output, ['Timestamp', 'User', 'Action Type', 'Details']);

// Write data rows
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
}

fclose($output);
mysqli_stmt_close($stmt);
mysqli_close($conn);
exit();