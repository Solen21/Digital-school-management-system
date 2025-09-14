<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once 'data/db_connect.php';

$user_id = $_SESSION['user_id'];
$notifications = [];

$sql = "SELECT notification_id, message, link, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 7";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}

mysqli_close($conn);
echo json_encode($notifications);