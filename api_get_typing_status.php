<?php
session_start();
header('Content-Type: application/json');

// 1. Security: Ensure user is logged in.
if (!isset($_SESSION["user_id"])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// 2. Validate the user ID we are checking.
$other_user_id = $_GET['with'] ?? null;
if (!$other_user_id || !is_numeric($other_user_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

require_once 'data/db_connect.php';

// 3. Check the last typed timestamp for the other user.
$sql = "SELECT last_typed_at FROM typing_status WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $other_user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);
mysqli_close($conn);

$is_typing = false;
if ($row) {
    $last_typed_time = strtotime($row['last_typed_at']);
    // Consider the user "typing" if their last activity was within the last 4 seconds.
    if ((time() - $last_typed_time) < 4) {
        $is_typing = true;
    }
}

echo json_encode(['is_typing' => $is_typing]);