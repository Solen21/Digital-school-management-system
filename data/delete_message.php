<?php
session_start();

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['message_id'])) {
    $message_id = intval($_POST['message_id']);
    $user_id = $_SESSION['user_id'];
    $conversation_id = $_POST['conversation_id'] ?? null;

    // 2. Verify that the user is part of this message
    $sql_verify = "SELECT sender_id, receiver_id FROM messages WHERE message_id = ?";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "i", $message_id);
    mysqli_stmt_execute($stmt_verify);
    $result = mysqli_stmt_get_result($stmt_verify);
    $message = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_verify);

    if ($message && ($message['sender_id'] == $user_id || $message['receiver_id'] == $user_id)) {
        // 3. User is authorized, perform the soft delete
        if ($message['sender_id'] == $user_id) {
            // User is the sender, mark as sender_deleted
            $sql_delete = "UPDATE messages SET sender_deleted = 1 WHERE message_id = ?";
        } else {
            // User is the receiver, mark as receiver_deleted
            $sql_delete = "UPDATE messages SET receiver_deleted = 1 WHERE message_id = ?";
        }

        $stmt_delete = mysqli_prepare($conn, $sql_delete);
        mysqli_stmt_bind_param($stmt_delete, "i", $message_id);
        mysqli_stmt_execute($stmt_delete);
        mysqli_stmt_close($stmt_delete);
    }

    // 4. Redirect back to the conversation
    if ($conversation_id) {
        header("Location: messages.php?conversation_id=" . $conversation_id);
    } else {
        header("Location: messages.php");
    }
    exit();
}

header("Location: messages.php");
exit();