<?php
session_start();

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$user_id = $_SESSION['user_id'];

// --- Pagination Logic ---
$records_per_page = 15;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// --- Count total records for pagination ---
$sql_count = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
$stmt_count = mysqli_prepare($conn, $sql_count);
mysqli_stmt_bind_param($stmt_count, "i", $user_id);
mysqli_stmt_execute($stmt_count);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'] ?? 0;
mysqli_stmt_close($stmt_count);
$total_pages = ceil($total_records / $records_per_page);

// --- Fetch notifications for the current page ---
$notifications = [];
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iii", $user_id, $records_per_page, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}
mysqli_stmt_close($stmt);

// Mark all notifications as read when the page is viewed
$sql_mark_all_read = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
$stmt_mark_all = mysqli_prepare($conn, $sql_mark_all_read);
mysqli_stmt_bind_param($stmt_mark_all, "i", $user_id);
mysqli_stmt_execute($stmt_mark_all);
mysqli_stmt_close($stmt_mark_all);

mysqli_close($conn);

$page_title = 'All Notifications';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">All Notifications</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="card">
        <div class="list-group list-group-flush">
            <?php if (empty($notifications)): ?>
                <div class="list-group-item text-center text-muted p-5">
                    <i class="bi bi-bell-slash-fill" style="font-size: 4rem;"></i>
                    <h4 class="mt-3">You have no notifications.</h4>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notif): ?>
                    <a href="<?php echo htmlspecialchars($notif['link'] ?? '#'); ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <p class="mb-1"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <small class="text-muted"><?php echo date('M j, Y, g:i a', strtotime($notif['created_at'])); ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination Controls -->
    <?php include 'pagination_controls.php'; ?>
</div>

<?php include 'footer.php'; ?>