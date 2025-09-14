<?php
session_start();

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$page_title = 'Compose New Message';
$preselected_receiver_id = $_GET['receiver_id'] ?? null;

// Fetch all users to populate the recipient dropdown
$users = [];
$sql_users = "SELECT user_id, username, role FROM users WHERE user_id != ? ORDER BY username ASC";
$stmt_users = mysqli_prepare($conn, $sql_users);
mysqli_stmt_bind_param($stmt_users, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_users);
$result_users = mysqli_stmt_get_result($stmt_users);
while ($row = mysqli_fetch_assoc($result_users)) {
    $users[] = $row;
}
mysqli_stmt_close($stmt_users);

mysqli_close($conn);
include 'header.php';
?>

<div class="container" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Compose New Message</h1>
        <a href="messages.php" class="btn btn-secondary">Back to Inbox</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="messages.php" method="POST">
                <input type="hidden" name="send_message" value="1">

                <div class="mb-3">
                    <label for="receiver_id" class="form-label">To:</label>
                    <select name="receiver_id" id="receiver_id" class="form-select" required <?php if ($preselected_receiver_id) echo 'disabled'; ?>>
                        <option value="">-- Select a Recipient --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php if ($preselected_receiver_id == $user['user_id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($user['username'] . ' (' . ucfirst($user['role']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="subject" class="form-label">Subject:</label>
                    <input type="text" name="subject" id="subject" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="content" class="form-label">Message:</label>
                    <textarea name="content" id="content" class="form-control" rows="10" required></textarea>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-send-fill me-2"></i>Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

```

### 4. Add Link to Dashboard

Finally, I'll add a "My Messages" link to the dashboard for easy access.

```diff