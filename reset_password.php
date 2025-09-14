<?php
session_start();

require_once 'data/db_connect.php';

$token = $_GET['token'] ?? '';
$message = '';
$message_type = '';
$show_form = false;

if (empty($token)) {
    $message = "Invalid password reset link.";
    $message_type = 'error';
} else {
    // Validate the token
    $sql_check = "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "s", $token);
    mysqli_stmt_execute($stmt_check);
    $result = mysqli_stmt_get_result($stmt_check);
    $reset_request = mysqli_fetch_assoc($result);

    if ($reset_request) {
        $show_form = true;
        $email = $reset_request['email'];
    } else {
        $message = "This password reset link is invalid or has expired.";
        $message_type = 'error';
    }
    mysqli_stmt_close($stmt_check);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $post_token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Re-validate token on POST
    $sql_recheck = "SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()";
    $stmt_recheck = mysqli_prepare($conn, $sql_recheck);
    mysqli_stmt_bind_param($stmt_recheck, "s", $post_token);
    mysqli_stmt_execute($stmt_recheck);
    $result_recheck = mysqli_stmt_get_result($stmt_recheck);
    $reset_request_post = mysqli_fetch_assoc($result_recheck);
    mysqli_stmt_close($stmt_recheck);

    if (!$reset_request_post) {
        $message = "Invalid or expired token. Please request a new reset link.";
        $message_type = 'error';
        $show_form = false;
    } elseif (empty($new_password) || ($new_password !== $confirm_password)) {
        $message = "Passwords do not match or are empty.";
        $message_type = 'error';
        $show_form = true; // Keep form visible
    } else {
        $email = $reset_request_post['email'];
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Find the user_id associated with this email
        $user_id = null;
        $user_role = null;
        $sql_find_user = "
            (SELECT user_id, 'student' as role FROM students WHERE email = ?)
            UNION
            (SELECT user_id, 'teacher' as role FROM teachers WHERE email = ?)
            UNION
            (SELECT user_id, 'guardian' as role FROM guardians WHERE email = ?)
            LIMIT 1
        ";
        $stmt_find_user = mysqli_prepare($conn, $sql_find_user);
        mysqli_stmt_bind_param($stmt_find_user, "sss", $email, $email, $email);
        mysqli_stmt_execute($stmt_find_user);
        $result_user = mysqli_stmt_get_result($stmt_find_user);
        if ($user = mysqli_fetch_assoc($result_user)) {
            $user_id = $user['user_id'];
            $user_role = $user['role'];
        }
        mysqli_stmt_close($stmt_find_user);

        if ($user_id) {
            mysqli_begin_transaction($conn);
            try {
                // Update users table
                $sql_update_user = "UPDATE users SET password = ? WHERE user_id = ?";
                $stmt_update_user = mysqli_prepare($conn, $sql_update_user);
                mysqli_stmt_bind_param($stmt_update_user, "si", $new_hashed_password, $user_id);
                if (!mysqli_stmt_execute($stmt_update_user)) throw new Exception(mysqli_stmt_error($stmt_update_user));
                mysqli_stmt_close($stmt_update_user);

                // If user is a student, also update the students table
                if ($user_role === 'student') {
                    $sql_update_student = "UPDATE students SET password = ? WHERE user_id = ?";
                    $stmt_update_student = mysqli_prepare($conn, $sql_update_student);
                    mysqli_stmt_bind_param($stmt_update_student, "si", $new_hashed_password, $user_id);
                    if (!mysqli_stmt_execute($stmt_update_student)) throw new Exception(mysqli_stmt_error($stmt_update_student));
                    mysqli_stmt_close($stmt_update_student);
                }

                // Delete the token
                $sql_delete_token = "DELETE FROM password_resets WHERE email = ?";
                $stmt_delete = mysqli_prepare($conn, $sql_delete_token);
                mysqli_stmt_bind_param($stmt_delete, "s", $email);
                mysqli_stmt_execute($stmt_delete);
                mysqli_stmt_close($stmt_delete);

                mysqli_commit($conn);
                $message = "Your password has been reset successfully. You can now log in.";
                $message_type = 'success';
                $show_form = false;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "An error occurred. Please try again.";
                $message_type = 'error';
            }
        } else {
            $message = "Could not find a user account for this email.";
            $message_type = 'error';
        }
    }
}

mysqli_close($conn);
$page_title = 'Reset Password';
include 'header.php';
?>
<style>
    .login-container {
        max-width: 480px;
        margin: 5rem auto;
    }
    #lottie-reset-password {
        width: 120px;
        height: 120px;
        margin: 0 auto 1rem;
    }
</style>

<div class="login-container">
    <div class="card shadow-lg border-0">
        <div class="card-body p-5">
            <div class="text-center">
                <div id="lottie-reset-password"></div>
                <h1 class="h4 text-gray-900 mb-2">Reset Your Password</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($show_form): ?>
                <p class="text-center mb-4">Create a new password for your account.</p>
                <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="reset_password" value="1">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required data-toggle-target>
                            <span class="input-group-text" style="cursor: pointer;" data-toggle-trigger><i class="bi bi-eye-slash-fill"></i></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required data-toggle-target>
                            <span class="input-group-text" style="cursor: pointer;" data-toggle-trigger><i class="bi bi-eye-slash-fill"></i></span>
                        </div>
                    </div>
                    <div class="d-grid"><button type="submit" class="btn btn-primary btn-lg">Reset Password</button></div>
                </form>
            <?php else: ?>
                <div class="text-center mt-4"><a class="btn btn-primary" href="login.php">Back to Login</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
<script>
    bodymovin.loadAnimation({
        container: document.getElementById('lottie-reset-password'),
        renderer: 'svg',
        loop: true,
        autoplay: true,
        path: 'assets/animations/password-reset.json' // You can find a suitable animation on LottieFiles
    });
</script>