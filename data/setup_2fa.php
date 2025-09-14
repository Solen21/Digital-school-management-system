<?php
session_start();

if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once 'data/db_connect.php';

use PragmaRX\Google2FA\Google2FA;

$google2fa = new Google2FA();
$message = '';
$message_type = '';
$user_id = $_SESSION['user_id'];

// Fetch current user's 2FA status
$stmt = mysqli_prepare($conn, "SELECT google2fa_secret, google2fa_enabled FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$secret_key = $user['google2fa_secret'];
$is_enabled = $user['google2fa_enabled'];

// --- Handle POST Requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'disable') {
        // Disable 2FA
        $stmt = mysqli_prepare($conn, "UPDATE users SET google2fa_enabled = 0, google2fa_secret = NULL WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $is_enabled = false;
        $secret_key = null;
        $message = "Two-Factor Authentication has been disabled.";
        $message_type = 'success';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'verify') {
        // Verify and enable 2FA
        $one_time_password = $_POST['one_time_password'];
        $is_valid = $google2fa->verifyKey($secret_key, $one_time_password);

        if ($is_valid) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET google2fa_enabled = 1 WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $is_enabled = true;
            $message = "Two-Factor Authentication has been enabled successfully!";
            $message_type = 'success';
        } else {
            $message = "Invalid verification code. Please try again.";
            $message_type = 'error';
        }
    }
}

// Generate a new secret key if one doesn't exist
if (!$is_enabled && empty($secret_key)) {
    $secret_key = $google2fa->generateSecretKey();
    $stmt = mysqli_prepare($conn, "UPDATE users SET google2fa_secret = ? WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "si", $secret_key, $user_id);
    mysqli_stmt_execute($stmt);
}

$qr_code_url = null;
if (!$is_enabled && !empty($secret_key)) {
    $qr_code_url = $google2fa->getQRCodeUrl(
        'Old Model School',
        $_SESSION['username'],
        $secret_key
    );
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Two-Factor Authentication</title>
    <link rel="stylesheet" href="add_student.php">
    <style>
        .setup-container { padding: 1.5rem; background-color: #f9fafb; border-radius: 0.5rem; border: 1px solid #e5e7eb; margin-top: 1.5rem; }
        .qr-code { margin: 1rem 0; }
    </style>
</head>
<body>
<div class="container" style="max-width: 700px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Two-Factor Authentication (2FA)</h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280;">Back to Dashboard</a>
    </div>

    <?php if ($message): ?><div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div><?php endif; ?>

    <div class="setup-container">
        <?php if ($is_enabled): ?>
            <h3>2FA is Currently Enabled</h3>
            <p>Your account is protected with Two-Factor Authentication. To disable it, click the button below.</p>
            <form action="setup_2fa.php" method="POST">
                <input type="hidden" name="action" value="disable">
                <button type="submit" class="btn" style="background-color: #dc2626;">Disable 2FA</button>
            </form>
        <?php else: ?>
            <h3>Setup 2FA</h3>
            <p><strong>Step 1:</strong> Scan the QR code below with your authenticator app (e.g., Google Authenticator, Authy).</p>
            <div class="qr-code">
                <?php
                if ($qr_code_url) {
                    echo '<img src="'.(new \Endroid\QrCode\QrCode($qr_code_url))->writeDataUri().'" alt="QR Code">';
                }
                ?>
            </div>
            <p>Or manually enter this secret key: <strong><?php echo htmlspecialchars($secret_key); ?></strong></p>

            <hr style="margin: 2rem 0;">

            <p><strong>Step 2:</strong> Enter the 6-digit code from your authenticator app to verify and enable 2FA.</p>
            <form action="setup_2fa.php" method="POST">
                <input type="hidden" name="action" value="verify">
                <div class="form-group">
                    <label for="one_time_password">Verification Code</label>
                    <input type="text" name="one_time_password" id="one_time_password" required maxlength="6" pattern="\d{6}">
                </div>
                <button type="submit" class="btn">Verify & Enable 2FA</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>