<?php
session_start();

// 1. Security Check: Ensure the user is an admin or director.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

// --- Filtering Logic ---
$filter_user_id = $_GET['filter_user_id'] ?? '';
$search_message = $_GET['search_message'] ?? '';

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($filter_user_id)) {
    $where_clauses[] = "n.user_id = ?";
    $params[] = $filter_user_id;
    $param_types .= 'i';
}
if (!empty($search_message)) {
    $where_clauses[] = "n.message LIKE ?";
    $params[] = "%" . $search_message . "%";
    $param_types .= 's';
}

// --- Pagination Logic ---
$records_per_page = 25;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

// --- Count total records for pagination ---
$sql_count = "SELECT COUNT(*) as total FROM notifications n ";
if (!empty($where_clauses)) {
    $sql_count .= " WHERE " . implode(' AND ', $where_clauses);
}
$stmt_count = mysqli_prepare($conn, $sql_count);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $param_types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'];
$total_pages = ceil($total_records / $records_per_page);
mysqli_stmt_close($stmt_count);

// --- Fetch Notifications ---
$notifications = [];
$sql = "
    SELECT n.notification_id, n.message, n.link, n.is_read, n.created_at, u.username
    FROM notifications n
    JOIN users u ON n.user_id = u.user_id
";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";

$all_params = array_merge($params, [$records_per_page, $offset]);
$all_param_types = $param_types . 'ii';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $all_param_types, ...$all_params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
}
mysqli_stmt_close($stmt);

// --- Fetch data for filters ---
$users = mysqli_query($conn, "SELECT user_id, username FROM users ORDER BY username ASC");

mysqli_close($conn);
$page_title = 'All Notifications Log';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">All User Notifications</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <!-- Filter Form -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <form action="view_all_notifications.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4"><label for="search_message" class="form-label">Search Message</label><input type="text" class="form-control" id="search_message" name="search_message" value="<?php echo htmlspecialchars($search_message); ?>"></div>
                <div class="col-md-4"><label for="filter_user_id" class="form-label">Filter by User</label><select id="filter_user_id" name="filter_user_id" class="form-select"><option value="">All Users</option><?php while($user = mysqli_fetch_assoc($users)): ?><option value="<?php echo $user['user_id']; ?>" <?php if($filter_user_id == $user['user_id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['username']); ?></option><?php endwhile; ?></select></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                <div class="col-md-2"><a href="view_all_notifications.php" class="btn btn-secondary w-100">Clear</a></div>
            </form>
        </div>
    </div>

    <!-- Log Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>Timestamp</th><th>User</th><th>Message</th><th>Link</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr><td colspan="5" class="text-center">No notifications found matching your criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($notif['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($notif['username']); ?></td>
                                <td><?php echo htmlspecialchars($notif['message']); ?></td>
                                <td><?php echo $notif['link'] ? '<a href="'.htmlspecialchars($notif['link']).'">Go to</a>' : 'N/A'; ?></td>
                                <td>
                                    <?php if ($notif['is_read']): ?>
                                        <span class="badge bg-secondary">Read</span>
                                    <?php else: ?>
                                        <span class="badge bg-primary">Unread</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php include 'pagination_controls.php'; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>