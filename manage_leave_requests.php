<?php
session_start();

// 1. Security Check: Ensure the user is an admin or director.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

// Use session for flash messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
} else {
    $message = '';
    $message_type = '';
}

// --- Handle POST Request to update status ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['status']; // 'approved' or 'denied'

    if (in_array($new_status, ['approved', 'denied'])) {
        $sql = "UPDATE leave_requests SET status = ? WHERE request_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $new_status, $request_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Leave request has been " . $new_status . ".";
            $_SESSION['message_type'] = 'success';

            // --- Create Notification ---
            $sql_get_user = "SELECT t.user_id FROM teachers t JOIN leave_requests lr ON t.teacher_id = lr.teacher_id WHERE lr.request_id = ?";
            $stmt_get_user = mysqli_prepare($conn, $sql_get_user);
            mysqli_stmt_bind_param($stmt_get_user, "i", $request_id);
            mysqli_stmt_execute($stmt_get_user);
            if ($user_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get_user))) {
                $notification_message = "Your leave request has been " . $new_status . ".";
                create_notification($conn, $user_row['user_id'], $notification_message, 'my_leave_requests.php');
            }
            mysqli_stmt_close($stmt_get_user);
            log_activity($conn, 'update_leave_request', $request_id, "Set status to " . $new_status);
        } else {
            $_SESSION['message'] = "Error updating leave request status.";
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
    header("Location: manage_leave_requests.php");
    exit();
}

// --- Filtering Logic ---
$filter_status = $_GET['filter_status'] ?? 'pending'; // Default to pending

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($filter_status)) {
    $where_clauses[] = "lr.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

// --- Fetch Leave Requests ---
$leave_requests = [];
$sql = "
    SELECT lr.*, t.first_name, t.last_name
    FROM leave_requests lr
    JOIN teachers t ON lr.teacher_id = t.teacher_id
";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY lr.requested_at DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $leave_requests[] = $row;
    }
}
mysqli_stmt_close($stmt);

mysqli_close($conn);
$page_title = 'Manage Leave Requests';
include 'header.php';
?>

<div class="container">
    <h1>Manage Leave Requests</h1>
    <p class="lead">Review and respond to leave requests from teachers.</p>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Filter Form -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <form action="manage_leave_requests.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="filter_status" class="form-label">Filter by Status</label>
                    <select id="filter_status" name="filter_status" class="form-select" onchange="this.form.submit()">
                        <option value="pending" <?php if($filter_status == 'pending') echo 'selected'; ?>>Pending</option>
                        <option value="approved" <?php if($filter_status == 'approved') echo 'selected'; ?>>Approved</option>
                        <option value="denied" <?php if($filter_status == 'denied') echo 'selected'; ?>>Denied</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Leave Requests Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>Teacher</th><th>Requested On</th><th>Leave Dates</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($leave_requests)): ?><tr><td colspan="6" class="text-center">No leave requests found with this status.</td></tr>
                        <?php else: ?><?php foreach ($leave_requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($request['requested_at'])); ?></td>
                                <td><?php echo date('M j, Y', strtotime($request['start_date'])); ?> to <?php echo date('M j, Y', strtotime($request['end_date'])); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($request['reason'])); ?></td>
                                <td>
                                    <?php
                                    $status_class = 'bg-secondary';
                                    if ($request['status'] == 'approved') $status_class = 'bg-success';
                                    if ($request['status'] == 'denied') $status_class = 'bg-danger';
                                    if ($request['status'] == 'pending') $status_class = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($request['status']); ?></span>
                                </td>
                                <td>
                                    <?php if ($request['status'] == 'pending'): ?>
                                        <form action="manage_leave_requests.php" method="POST" class="d-inline">
                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>"><input type="hidden" name="status" value="approved"><button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <form action="manage_leave_requests.php" method="POST" class="d-inline">
                                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>"><input type="hidden" name="status" value="denied"><button type="submit" class="btn btn-sm btn-danger" title="Deny"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    <?php else: ?><span>No actions</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>