<?php
session_start();

// 1. Security Check
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

$message = '';
$message_type = '';

// Pre-fill from GET request
$prefill_target_type = '';
$prefill_user_id = '';
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $prefill_target_type = 'specific_user';
    $prefill_user_id = $_GET['user_id'];
}

// Fetch data for dropdowns
$users = mysqli_query($conn, "SELECT user_id, username, role FROM users ORDER BY username ASC");
$roles = ['student', 'teacher', 'guardian', 'rep', 'director', 'admin'];

// --- Handle POST Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $target_type = $_POST['target_type'] ?? '';
    $notification_message = $_POST['message'] ?? '';
    $notification_link = !empty($_POST['link']) ? $_POST['link'] : null;

    if (empty($target_type) || empty($notification_message)) {
        $message = "Target and message fields are required.";
        $message_type = 'danger';
    } else {
        $target_user_ids = [];

        switch ($target_type) {
            case 'all_users':
                $result = mysqli_query($conn, "SELECT user_id FROM users");
                while ($row = mysqli_fetch_assoc($result)) {
                    $target_user_ids[] = $row['user_id'];
                }
                break;
            case 'by_role':
                $target_role = $_POST['target_role'] ?? '';
                if (!empty($target_role)) {
                    $stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE role = ?");
                    mysqli_stmt_bind_param($stmt, "s", $target_role);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);
                    while ($row = mysqli_fetch_assoc($result)) {
                        $target_user_ids[] = $row['user_id'];
                    }
                    mysqli_stmt_close($stmt);
                }
                break;
            case 'specific_user':
                $target_user = $_POST['target_user_id'] ?? '';
                if (!empty($target_user)) {
                    $target_user_ids[] = $target_user;
                }
                break;
        }

        if (!empty($target_user_ids)) {
            mysqli_begin_transaction($conn);
            try {
                foreach ($target_user_ids as $user_id) {
                    create_notification($conn, $user_id, $notification_message, $notification_link);
                }
                mysqli_commit($conn);
                $message = "Notification sent successfully to " . count($target_user_ids) . " user(s).";
                $message_type = 'success';
                log_activity($conn, 'send_notification', null, "Sent to " . count($target_user_ids) . " users: " . $notification_message);
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "An error occurred while sending notifications.";
                $message_type = 'danger';
            }
        } else {
            $message = "No target users found for the selected criteria.";
            $message_type = 'danger';
        }
    }
}

$page_title = 'Send Notification';
include 'header.php';
?>

<div class="container">
    <h1>Send Notification</h1>
    <p class="lead">Broadcast a message to all users, a specific group, or an individual.
        <?php if ($prefill_user_id): ?>
            <a href="send_notification.php" class="btn btn-sm btn-outline-secondary ms-2">Clear Selection</a>
        <?php endif; ?>
    </p>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form action="send_notification.php" method="POST" id="notification-form">
                <div class="mb-3">
                    <label for="target_type" class="form-label">Select Target Audience</label>
                    <select id="target_type" name="target_type" class="form-select" required>
                        <option value="">-- Choose Target --</option>
                        <option value="all_users" <?php if ($prefill_target_type == 'all_users') echo 'selected'; ?>>All Users</option>
                        <option value="by_role" <?php if ($prefill_target_type == 'by_role') echo 'selected'; ?>>A Specific Role</option>
                        <option value="specific_user" <?php if ($prefill_target_type == 'specific_user') echo 'selected'; ?>>A Specific User</option>
                    </select>
                </div>
                <div id="role-select-container" class="mb-3" style="display: none;"><label for="target_role" class="form-label">Select Role</label><select id="target_role" name="target_role" class="form-select"><?php foreach ($roles as $role): ?><option value="<?php echo $role; ?>"><?php echo ucfirst($role); ?></option><?php endforeach; ?></select></div>
                <div id="user-select-container" class="mb-3" style="display: none;">
                    <label for="target_user_id" class="form-label">Select User</label>
                    <select id="target_user_id" name="target_user_id" class="form-select">
                        <option value="">-- Choose User --</option>
                        <?php mysqli_data_seek($users, 0); while($user = mysqli_fetch_assoc($users)): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php if ($prefill_user_id == $user['user_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($user['username'] . ' (' . ucfirst($user['role']) . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3"><label for="message" class="form-label">Notification Message</label><textarea id="message" name="message" class="form-control" rows="4" required></textarea></div>
                <div class="mb-3"><label for="link" class="form-label">Optional Link (e.g., view_news.php)</label><input type="text" id="link" name="link" class="form-control" placeholder="Leave blank for no link"></div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-send-fill me-2"></i>Send Notification</button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const targetType = document.getElementById('target_type');
    const roleContainer = document.getElementById('role-select-container');
    const userContainer = document.getElementById('user-select-container');

    function toggleTargetOptions() {
        roleContainer.style.display = 'none';
        userContainer.style.display = 'none';

        if (targetType.value === 'by_role') {
            roleContainer.style.display = 'block';
        } else if (targetType.value === 'specific_user') {
            userContainer.style.display = 'block';
        }
    }

    targetType.addEventListener('change', toggleTargetOptions);

    // Trigger on page load to handle pre-filled values
    toggleTargetOptions();
});
</script>

<?php include 'footer.php'; ?>