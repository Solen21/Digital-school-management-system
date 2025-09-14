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
$filter_action = $_GET['filter_action'] ?? '';
$search_user = $_GET['search_user'] ?? '';

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($filter_user_id)) {
    $where_clauses[] = "al.user_id = ?";
    $params[] = $filter_user_id;
    $param_types .= 'i';
}
if (!empty($filter_action)) {
    $where_clauses[] = "al.action_type = ?";
    $params[] = $filter_action;
    $param_types .= 's';
}
if (!empty($search_user)) {
    $where_clauses[] = "al.username LIKE ?";
    $params[] = "%" . $search_user . "%";
    $param_types .= 's';
}

// --- Pagination Logic ---
$records_per_page = 25;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

// --- Fetch Activity Logs ---
$logs = [];
$sql = "
    SELECT al.log_id, al.username, al.action_type, al.target_id, al.target_name, al.details, al.logged_at
    FROM activity_logs al
";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?"; // Add pagination to query

// --- Count total records for pagination ---
$sql_count = "SELECT COUNT(*) as total FROM activity_logs al ";
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

// --- Prepare and execute the main query ---
$stmt = mysqli_prepare($conn, $sql);

// Add pagination params to the existing params
$all_params = $params;
$all_params[] = $records_per_page;
$all_params[] = $offset;
$all_param_types = $param_types . 'ii';

mysqli_stmt_bind_param($stmt, $all_param_types, ...$all_params);

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $logs[] = $row;
    }
}
mysqli_stmt_close($stmt);

// --- Fetch data for filters ---
$users = mysqli_query($conn, "SELECT user_id, username FROM users ORDER BY username ASC");
$actions = mysqli_query($conn, "SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type ASC");

mysqli_close($conn);
$page_title = 'Activity Log';
include 'header.php';
?>

<div class="container">
    <h1>System Activity Log</h1>
    <p class="lead">Review recent activities performed by users in the system.</p>

    <!-- Filter Form -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <form action="view_activity_log.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-3"><label for="filter_user_id" class="form-label">Filter by User</label><select id="filter_user_id" name="filter_user_id" class="form-select"><option value="">All Users</option><?php while($user = mysqli_fetch_assoc($users)): ?><option value="<?php echo $user['user_id']; ?>" <?php if($filter_user_id == $user['user_id']) echo 'selected'; ?>><?php echo htmlspecialchars($user['username']); ?></option><?php endwhile; ?></select></div>
                <div class="col-md-3"><label for="filter_action" class="form-label">Filter by Action</label><select id="filter_action" name="filter_action" class="form-select"><option value="">All Actions</option><?php while($action = mysqli_fetch_assoc($actions)): ?><option value="<?php echo $action['action_type']; ?>" <?php if($filter_action == $action['action_type']) echo 'selected'; ?>><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $action['action_type']))); ?></option><?php endwhile; ?></select></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                <div class="col-md-2"><a href="view_activity_log.php" class="btn btn-secondary w-100">Clear</a></div>
                <div class="col-md-2">
                    <a href="export_activity_log.php?<?php echo http_build_query(['filter_user_id' => $filter_user_id, 'filter_action' => $filter_action]); ?>" class="btn btn-success w-100"><i class="bi bi-file-earmark-excel-fill me-1"></i> Export</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Log Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
                    <tbody><?php if (empty($logs)): ?><tr><td colspan="5" class="text-center">No activity logs found matching your criteria.</td></tr><?php else: ?><?php foreach ($logs as $log): ?><tr><td><?php echo htmlspecialchars($log['logged_at']); ?></td><td><?php echo htmlspecialchars($log['username']); ?></td><td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action_type']))); ?></span></td><td><?php echo htmlspecialchars($log['target_name'] ?? 'N/A'); ?> (ID: <?php echo htmlspecialchars($log['target_id'] ?? 'N/A'); ?>)</td><td><?php echo htmlspecialchars($log['details'] ?? ''); ?></td></tr><?php endforeach; ?><?php endif; ?></tbody>
                </table>
            </div>
            <?php include 'pagination_controls.php'; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>