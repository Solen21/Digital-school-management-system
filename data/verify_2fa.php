<?php
session_start();

if (!isset($_SESSION['2fa_user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'data/db_connect.php';

use PragmaRX\Google2FA\Google2FA;

$google2fa = new Google2FA();
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['2fa_user_id'];
    $one_time_password = $_POST['one_time_password'];

    // Fetch user's secret key
    $stmt = mysqli_prepare($conn, "SELECT username, role, google2fa_secret, student_id FROM users u LEFT JOIN students s ON u.user_id = s.user_id WHERE u.user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($user) {
        $is_valid = $google2fa->verifyKey($user['google2fa_secret'], $one_time_password);

        if ($is_valid) {
            // 2FA is correct, complete the login process
            unset($_SESSION['2fa_user_id']);
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            if ($user['role'] == 'student' && $user['student_id']) {
                $_SESSION['student_id'] = $user['student_id'];
            }
            header("Location: dashboard.php");
            exit();
        } else {
            $error_message = "Invalid verification code. Please try again.";
        }
    } else {
        // Should not happen if session is set correctly
        $error_message = "An error occurred. Please try logging in again.";
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication</title>
    <link rel="stylesheet" href="add_student.php">
    <style>
        .container { max-width: 450px; margin-top: 5em; }
        .form-grid { grid-template-columns: 1fr; }
    </style>
</head>
<body>
<div class="container">
    <h1>Two-Factor Authentication</h1>
    <p>Please enter the code from your authenticator app to complete your login.</p>

    <?php if (!empty($error_message)): ?>
        <div class="message error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="verify_2fa.php" method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label for="one_time_password">6-Digit Code</label>
                <input type="text" id="one_time_password" name="one_time_password" required maxlength="6" pattern="\d{6}" autofocus>
            </div>
            <div class="form-group full-width">
                <button type="submit" class="btn">Verify</button>
            </div>
        </div>
        <div style="text-align: center; margin-top: 1rem;"><a href="logout.php">Cancel and go back</a></div>
    </form>
</div>
</body>
</html>