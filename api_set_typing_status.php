<?php
session_start();

// 1. Security: Ensure user is logged in.
if (!isset($_SESSION["user_id"])) {
    http_response_code(401); // Unauthorized
    exit();
}

require_once 'data/db_connect.php';

$current_user_id = $_SESSION['user_id'];

// 2. Use INSERT ... ON DUPLICATE KEY UPDATE to efficiently set the typing status.
// This creates a record if it doesn't exist, or updates the timestamp if it does.
$sql = "INSERT INTO typing_status (user_id, last_typed_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_typed_at = NOW()";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $current_user_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
mysqli_close($conn);

echo json_encode(['success' => true]);