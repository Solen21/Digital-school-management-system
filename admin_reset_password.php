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
    header("Location: manage_users.php");
    exit();
}

$user_id_to_reset = intval($_GET['user_id']);

// 3. Prevent admin from resetting their own password via this script.
if ($user_id_to_reset === $_SESSION['user_id']) {
    $_SESSION['message'] = "You cannot reset your own password from this page. Please use the Settings page.";
    $_SESSION['message_type'] = 'warning';
    header("Location: manage_users.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

try {
    // 4. Fetch user details for logging.
    $sql_fetch = "SELECT username FROM users WHERE user_id = ?";
    $stmt_fetch = mysqli_prepare($conn, $sql_fetch);
    mysqli_stmt_bind_param($stmt_fetch, "i", $user_id_to_reset);
    mysqli_stmt_execute($stmt_fetch);
    $result = mysqli_stmt_get_result($stmt_fetch);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_fetch);

    if (!$user) {
        throw new Exception("User not found.");
    }
    $username_to_reset = $user['username'];

    // 5. Generate a new, secure temporary password.
    $new_password_plain = 'Temp' . bin2hex(random_bytes(3)); // e.g., Tempf8a3b1
    $new_password_hashed = password_hash($new_password_plain, PASSWORD_DEFAULT);

    // 6. Update the user's password in the database.
    $sql_update = "UPDATE users SET password = ? WHERE user_id = ?";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "si", $new_password_hashed, $user_id_to_reset);
    
    if (mysqli_stmt_execute($stmt_update)) {
        // 7. Log the action.
        $log_details = "Admin (" . $_SESSION['username'] . ") reset password for user (" . $username_to_reset . ").";
        log_activity($conn, 'admin_password_reset', $user_id_to_reset, $username_to_reset, $log_details);
        
        $_SESSION['message'] = "Password for user '{$username_to_reset}' has been reset to: <strong>{$new_password_plain}</strong>";
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
header("Location: manage_users.php");
exit();