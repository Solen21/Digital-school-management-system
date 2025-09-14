<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

// If user isn't in the 2FA verification stage, or is already logged in, redirect them.
if (!isset($_SESSION['2fa_user_id']) || isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/data/db_connect.php';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["verification_code"]))) {
        $error_message = "Please enter the verification code.";
    } else {
        $verification_code = trim($_POST["verification_code"]);
        $user_id = $_SESSION['2fa_user_id'];

        // Get user's secret and other details
        $sql = "SELECT user_id, username, role, google2fa_secret FROM users WHERE user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($user && !empty($user['google2fa_secret'])) {
            $google2fa = new \PragmaRX\Google2FAQRCode\Google2FA();
            if ($google2fa->verifyKey($user['google2fa_secret'], $verification_code)) {
                // 2FA code is correct. Finalize login.
                unset($_SESSION['2fa_user_id']); // Clean up temp session
                $_SESSION["user_id"] = $user['user_id'];
                $_SESSION["username"] = $user['username'];
                $_SESSION["role"] = $user['role'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Invalid verification code. Please try again.";
            }
        } else {
            // Should not happen if session is set correctly
            $error_message = "An unexpected error occurred. Please try logging in again.";
            unset($_SESSION['2fa_user_id']);
        }
    }
}

$page_title = 'Two-Factor Verification';
include 'header.php';
?>
<style>
    .verify-container {
        max-width: 450px;
        margin: 5rem auto;
        padding: 2.5rem;
        background-color: var(--white);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-xl);
        border: 1px solid var(--medium-gray);
        text-align: center;
    }
    #lottie-2fa { width: 120px; height: 120px; margin: 0 auto 1rem; }
</style>

<div class="verify-container">
    <div id="lottie-2fa"></div>
    <h1>Two-Factor Verification</h1>
    <p class="text-muted">Please enter the 6-digit code from your authenticator app.</p>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form action="verify_2fa.php" method="POST">
        <div class="form-grid">
            <div class="form-group full-width"><label for="verification_code">Verification Code</label><input type="text" id="verification_code" name="verification_code" class="form-control text-center" required maxlength="6" pattern="\d{6}" autofocus></div>
            <div class="form-group full-width"><button type="submit" class="btn btn-primary w-100">Verify</button></div>
        </div>
        <div style="text-align: center; margin-top: 1rem;"><a href="logout.php">Cancel and go back</a></div>
    </form>
</div>

<?php include 'footer.php'; ?>
<script>
    bodymovin.loadAnimation({
        container: document.getElementById('lottie-2fa'),
        renderer: 'svg',
        loop: true,
        autoplay: true,
        path: 'assets/animations/2fa-shield.json' // You can find a suitable animation on LottieFiles
    });
</script>