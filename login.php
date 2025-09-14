<?php
// Start the session at the very beginning
session_start();

// If the user is already logged in, redirect them to the dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'data/db_connect.php';

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = "Username and password are required.";
    } else {
        // Prepare a statement to prevent SQL injection
        $sql = "SELECT user_id, username, password, role, google2fa_secret FROM users WHERE username = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            // Verify the hashed password
            if (password_verify($password, $user['password'])) {
                // Password is correct. Check for 2FA.
                if (in_array($user['role'], ['admin', 'director']) && $user['google2fa_enabled']) {
                    // 2FA is enabled, redirect to verification page
                    $_SESSION['2fa_user_id'] = $user['user_id']; // Store user_id temporarily
                    header("Location: verify_2fa.php");
                    exit();
                } else {
                    // Normal login for other users or admins without 2FA
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    // If the user is a student, get their student_id
                    if (in_array($user['role'], ['student', 'rep'])) {
                        $sql_student = "SELECT student_id FROM students WHERE user_id = ?";
                        $stmt_student = mysqli_prepare($conn, $sql_student);
                        mysqli_stmt_bind_param($stmt_student, "i", $user['user_id']);
                        mysqli_stmt_execute($stmt_student);
                        if ($student_user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_student))) {
                            $_SESSION['student_id'] = $student_user['student_id'];
                        }
                        mysqli_stmt_close($stmt_student);
                    }
                    // Redirect to the dashboard
                    header("Location: dashboard.php");
                    exit();
                }
            } else {
                $error_message = "Invalid username or password.";
            }
        } else {
            $error_message = "Invalid username or password.";
        }
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
    }
}

$page_title = 'Login';
include 'header.php'; // Use the consistent header
?>
<style>
    .login-container {
        max-width: 480px;
        margin: 5rem auto;
    }
    #lottie-login {
        width: 120px;
        height: 120px;
        margin: 0 auto 1rem;
    }
</style>

<div class="login-container">
    <div class="card shadow-lg border-0">
        <div class="card-body p-5">
            <div class="text-center">
                <div id="lottie-login"></div>
                <h1 class="h4 text-gray-900 mb-2">Welcome Back!</h1>
                <p class="mb-4">Please enter your credentials to access your dashboard.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger text-center"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required>
                        <span class="input-group-text"><i class="bi bi-eye-slash-fill" id="togglePassword" style="cursor: pointer;"></i></span>
                    </div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">Login</button>
                </div>
            </form>
            <hr>
            <div class="text-center">
                <a class="small" href="forgot_password.php">Forgot your password?</a>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; // Use the consistent footer ?>
<script>
    bodymovin.loadAnimation({
        container: document.getElementById('lottie-login'),
        renderer: 'svg',
        loop: true,
        autoplay: true,
        path: 'assets/animations/login-security.json' // A new, relevant animation
    });

    const togglePassword = document.querySelector('#togglePassword');
    if (togglePassword) {
        togglePassword.addEventListener('click', function () {
            const password = document.querySelector('#password');
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            // toggle the icon
            this.classList.toggle('bi-eye-slash-fill');
            this.classList.toggle('bi-eye-fill');
        });
    }
</script>