<?php
session_start();

// 1. Security Check: User must be logged in and have an appropriate role.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

// --- Search and Filter Logic ---
$search_name = $_GET['search_name'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search_name)) {
    $where_clauses[] = "vp.visitor_name LIKE ?";
    $params[] = "%" . $search_name . "%";
    $param_types .= 's';
}
if (!empty($start_date)) {
    $where_clauses[] = "vp.issued_at >= ?";
    $params[] = $start_date . ' 00:00:00';
    $param_types .= 's';
}
if (!empty($end_date)) {
    $where_clauses[] = "vp.issued_at <= ?";
    $params[] = $end_date . ' 23:59:59';
    $param_types .= 's';
}

$visitor_logs = [];
$sql = "
    SELECT 
        vp.pass_id, vp.visitor_name, vp.reason_for_visit, vp.person_to_visit, vp.issued_at,
        u.username as issuer_username
    FROM visitor_passes vp
    LEFT JOIN users u ON vp.issued_by_user_id = u.user_id ";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY vp.issued_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    $visitor_logs = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Pass Log</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 1.5rem; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: var(--light-gray); }
        .filter-form { display: flex; gap: 1rem; align-items: flex-end; background-color: var(--light-gray); padding: 1rem; border-radius: 0.5rem; margin-top: 1.5rem; }
    </style>
</head>
<body>
<button id="theme-toggle" class="theme-toggle" title="Toggle dark mode">
    <i class="bi bi-moon-fill"></i>
    <i class="bi bi-sun-fill"></i>
</button>
<div class="container" style="max-width: 1200px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Visitor Pass Log</h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280;">Back to Dashboard</a>
    </div>

    <form action="view_visitor_log.php" method="GET" class="filter-form">
        <div class="form-group"><label for="search_name">Visitor Name</label><input type="text" name="search_name" id="search_name" value="<?php echo htmlspecialchars($search_name); ?>"></div>
        <div class="form-group"><label for="start_date">From Date</label><input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>"></div>
        <div class="form-group"><label for="end_date">To Date</label><input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>"></div>
        <button type="submit" class="btn">Filter</button>
        <a href="view_visitor_log.php" class="btn" style="background-color: #9ca3af;">Clear</a>
    </form>

    <?php if (empty($visitor_logs)): ?>
        <div class="message" style="margin-top: 1.5rem;">No visitor passes have been logged yet.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Visitor Name</th><th>Reason for Visit</th><th>Person to Visit</th><th>Issued At</th><th>Issued By</th></tr></thead>
            <tbody>
                <?php foreach ($visitor_logs as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['visitor_name']); ?></td><td><?php echo htmlspecialchars($log['reason_for_visit']); ?></td><td><?php echo htmlspecialchars($log['person_to_visit']); ?></td><td><?php echo date('M j, Y, g:i a', strtotime($log['issued_at'])); ?></td><td><?php echo htmlspecialchars($log['issuer_username'] ?? 'N/A'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<script src="assets/theme-toggle.js"></script>
</body>
</html>