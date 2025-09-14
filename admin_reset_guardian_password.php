<?php
session_start();

// 1. Security Check: User must be logged in and be an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. Validate input: user_id must be present and numeric.
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    $_SESSION['message'] = "Invalid request: User ID not provided.";
    $_SESSION['message_type'] = 'danger';
    header("Location: manage_guardians.php");
    exit();
}

$user_id_to_reset = intval($_GET['user_id']);

require_once 'data/db_connect.php';
require_once 'functions.php';

$guardian_id = null; // To redirect back to the correct edit page

try {
    // 4. Fetch user and guardian details for logging and redirecting.
    $sql_fetch = "SELECT u.username, g.guardian_id FROM users u JOIN guardians g ON u.user_id = g.user_id WHERE u.user_id = ?";
    $stmt_fetch = mysqli_prepare($conn, $sql_fetch);
    mysqli_stmt_bind_param($stmt_fetch, "i", $user_id_to_reset);
    mysqli_stmt_execute($stmt_fetch);
    $result = mysqli_stmt_get_result($stmt_fetch);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_fetch);

    if (!$user) {
        throw new Exception("Guardian user not found.");
    }
    $username_to_reset = $user['username'];
    $guardian_id = $user['guardian_id'];

    // 5. Generate a new, secure temporary password.
    $new_password_plain = 'Temp' . bin2hex(random_bytes(3)); // e.g., Tempf8a3b1
    $new_password_hashed = password_hash($new_password_plain, PASSWORD_DEFAULT);

    // 6. Update the user's password in the database.
    $sql_update = "UPDATE users SET password = ? WHERE user_id = ?";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "si", $new_password_hashed, $user_id_to_reset);
    
    if (mysqli_stmt_execute($stmt_update)) {
        $log_details = "Admin (" . $_SESSION['username'] . ") reset password for guardian (" . $username_to_reset . ").";
        log_activity($conn, 'guardian_password_reset', $user_id_to_reset, $username_to_reset, $log_details);
        
        $_SESSION['message'] = "Password for guardian '{$username_to_reset}' has been reset to: <strong>{$new_password_plain}</strong>";
        $_SESSION['message_type'] = 'success';
    } else {
        throw new Exception("Failed to update password.");
    }
    mysqli_stmt_close($stmt_update);

} catch (Exception $e) {
    $_SESSION['message'] = "Error: " . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

mysqli_close($conn);
header("Location: " . ($guardian_id ? "edit_guardian.php?id=" . $guardian_id : "manage_guardians.php"));
exit();