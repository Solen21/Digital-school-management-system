<?php
session_start();

// Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$notification_id = $_GET['id'] ?? null;
$redirect_url = $_GET['redirect'] ?? 'view_notifications.php';

if ($notification_id && is_numeric($notification_id)) {
    require_once 'data/db_connect.php';
    $user_id = $_SESSION['user_id'];

    // Mark the specific notification as read
    $sql = "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
}

// Redirect the user to the final destination
header("Location: " . $redirect_url);
exit();
?>