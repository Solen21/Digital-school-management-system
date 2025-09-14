<?php
session_start();

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'data/db_connect.php';
    require_once __DIR__ . '/email_functions.php';
    require_once __DIR__ . '/vendor/autoload.php';

    $email = $_POST['email'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = 'error';
    } else {
        // Check if this email exists for any user type
        $user_id = null;
        $sql_find_user = "
            (SELECT user_id FROM students WHERE email = ?)
            UNION
            (SELECT user_id FROM teachers WHERE email = ?)
            UNION
            (SELECT user_id FROM guardians WHERE email = ?)
            LIMIT 1
        ";
        $stmt_find_user = mysqli_prepare($conn, $sql_find_user);
        mysqli_stmt_bind_param($stmt_find_user, "sss", $email, $email, $email);
        mysqli_stmt_execute($stmt_find_user);
        $result = mysqli_stmt_get_result($stmt_find_user);

        if ($user = mysqli_fetch_assoc($result)) {
            // User found, generate a token
            $token = bin2hex(random_bytes(32));
            $expires = new DateTime('now');
            $expires->add(new DateInterval('PT1H')); // Token expires in 1 hour
            $expires_str = $expires->format('Y-m-d H:i:s');

            // Store token in the database
            $sql_insert_token = "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert_token);
            mysqli_stmt_bind_param($stmt_insert, "sss", $email, $token, $expires_str);
            mysqli_stmt_execute($stmt_insert);

            // --- Email Sending Logic ---
            // IMPORTANT: You need to configure an email sending library like PHPMailer.
            // This is a placeholder for the email logic.
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domain_name = $_SERVER['HTTP_HOST'];
            $reset_link = $protocol . $domain_name . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;

            $email_subject = "Password Reset Request";
            $email_body = "
                <p>Hello,</p>
                <p>You requested a password reset for your account. Please click the link below to set a new password. This link is valid for one hour.</p>
                <p><a href='{$reset_link}'>{$reset_link}</a></p>
                <p>If you did not request a password reset, please ignore this email.</p>
            ";
            
            if (sendEmail($email, $email_subject, $email_body)) {
                $message = "A password reset link has been sent to your email address.";
                $message_type = 'success';
            } else {
                $message = "The email could not be sent. Please contact an administrator. For testing, here is the link: <br><a href='{$reset_link}'>{$reset_link}</a>";
                $message_type = 'error';
            }

        } else {
            // To prevent user enumeration, show the same message whether the email exists or not.
            $message = "If an account with that email exists, a password reset link has been sent.";
            $message_type = 'success';
        }
        mysqli_close($conn);
    }
}
$page_title = 'Forgot Password';
include 'header.php';
?>

<div class="login-container">
    <h1>Forgot Password</h1>
    <p>Enter your email address and we will send you a link to reset your password.</p>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo $message; // The link is displayed here for testing ?></div>
    <?php endif; ?>

    <form action="forgot_password.php" method="POST">
        <div class="form-grid">
            <div class="form-group"><label for="email">Email Address</label><input type="email" id="email" name="email" required></div>
            <div class="form-group full-width"><button type="submit" class="btn">Send Reset Link</button></div>
        </div>
        <div style="text-align: center; margin-top: 1rem;"><a href="login.php">Back to Login</a></div>
    </form>
</div>
<?php include 'footer.php'; ?>