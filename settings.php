<?php
session_start();

// 1. Check if the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$message = '';
$message_type = 'danger'; // Default to error

// 2. Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    // --- Validation ---
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $message = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 6) {
        $message = "New password must be at least 6 characters long.";
    } else {
        // --- Process Password Change ---
        try {
            // Get current hashed password from DB
            $sql_fetch = "SELECT password FROM users WHERE user_id = ?";
            $stmt_fetch = mysqli_prepare($conn, $sql_fetch);
            mysqli_stmt_bind_param($stmt_fetch, "i", $user_id);
            mysqli_stmt_execute($stmt_fetch);
            $result = mysqli_stmt_get_result($stmt_fetch);
            $user = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt_fetch);

            if ($user && password_verify($current_password, $user['password'])) {
                // Current password is correct, proceed to update
                $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

                mysqli_begin_transaction($conn);

                // Update users table
                $sql_update_user = "UPDATE users SET password = ? WHERE user_id = ?";
                $stmt_update_user = mysqli_prepare($conn, $sql_update_user);
                mysqli_stmt_bind_param($stmt_update_user, "si", $new_password_hashed, $user_id);
                if (!mysqli_stmt_execute($stmt_update_user)) {
                    throw new Exception("Failed to update main user password.");
                }
                mysqli_stmt_close($stmt_update_user);

                // If user is a student, also update the students table
                if ($user_role === 'student' || $user_role === 'rep') {
                    $sql_update_student = "UPDATE students SET password = ? WHERE user_id = ?";
                    $stmt_update_student = mysqli_prepare($conn, $sql_update_student);
                    mysqli_stmt_bind_param($stmt_update_student, "si", $new_password_hashed, $user_id);
                    if (!mysqli_stmt_execute($stmt_update_student)) {
                        throw new Exception("Failed to update student profile password.");
                    }
                    mysqli_stmt_close($stmt_update_student);
                }

                mysqli_commit($conn);
                $message = "Password changed successfully.";
                $message_type = 'success';

            } else {
                $message = "Incorrect current password.";
            }
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "An error occurred: " . $e->getMessage();
        }
    }
}

mysqli_close($conn);
$page_title = 'Settings';
include 'header.php';
?>
<style>
    #lottie-settings {
        width: 120px;
        height: 120px;
        margin: 0 auto 1rem;
    }
</style>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="text-center mb-4">
                <div id="lottie-settings"></div>
                <h1 class="h3">Settings</h1>
            </div>

            <div class="d-flex justify-content-end mb-3">
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form action="settings.php" method="POST">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required data-toggle-target>
                                <span class="input-group-text" style="cursor: pointer;" data-toggle-trigger><i class="bi bi-eye-slash-fill"></i></span>
                            </div>
                        </div>
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
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
bodymovin.loadAnimation({
    container: document.getElementById('lottie-settings'),
    renderer: 'svg',
    loop: true,
    autoplay: true,
    path: 'assets/animations/settings-gear.json' // A suitable animation for settings
});
</script>
<?php include 'footer.php'; ?>