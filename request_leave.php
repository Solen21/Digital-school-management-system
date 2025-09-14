<?php
session_start();

// 1. Check if the user is logged in and is a teacher or rep.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['teacher', 'rep'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$message = '';
$message_type = '';
$upload_dir = 'uploads/leave_attachments/';

// Create upload directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Get the teacher's internal ID from their user_id.
$sql_teacher_id = "SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1";
$stmt_teacher_id = mysqli_prepare($conn, $sql_teacher_id);
mysqli_stmt_bind_param($stmt_teacher_id, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_teacher_id);
$result_teacher_id = mysqli_stmt_get_result($stmt_teacher_id);
if ($row = mysqli_fetch_assoc($result_teacher_id)) {
    $teacher_id = $row['teacher_id'];
} else {
    die("<h1>Error</h1><p>Could not find a teacher profile associated with your user account. <a href='dashboard.php'>Return to Dashboard</a></p>");
}
mysqli_stmt_close($stmt_teacher_id);

// --- Handle POST request to submit a new leave request ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_leave'])) {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    $attachment_path = NULL;

    if (empty($leave_type) || empty($start_date) || empty($end_date) || empty($reason)) {
        $message = "Leave Type, Start Date, End Date, and Reason are required.";
        $message_type = 'danger';
    } elseif ($end_date < $start_date) {
        $message = "End date cannot be before the start date.";
        $message_type = 'danger';
    } else {
        // Handle file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
            $file_name = uniqid() . '_' . basename($_FILES['attachment']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                $attachment_path = $target_file;
            } else {
                $message = "Error uploading attachment. Please check folder permissions.";
                $message_type = 'danger';
            }
        }

        if (empty($message)) {
            $sql = "INSERT INTO leave_requests (teacher_id, leave_type, start_date, end_date, reason, attachment_path) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isssss", $teacher_id, $leave_type, $start_date, $end_date, $reason, $attachment_path);

            if (mysqli_stmt_execute($stmt)) {
                $message = "Your leave request has been submitted successfully.";
                $message_type = 'success';
            } else {
                $message = "Error submitting request: " . mysqli_stmt_error($stmt);
                $message_type = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// --- Fetch past leave requests for this teacher ---
$past_requests = [];
$sql_past = "SELECT * FROM leave_requests WHERE teacher_id = ? ORDER BY request_date DESC";
$stmt_past = mysqli_prepare($conn, $sql_past);
mysqli_stmt_bind_param($stmt_past, "i", $teacher_id);
mysqli_stmt_execute($stmt_past);
$result_past = mysqli_stmt_get_result($stmt_past);
while ($row = mysqli_fetch_assoc($result_past)) {
    $past_requests[] = $row;
}
mysqli_stmt_close($stmt_past);

mysqli_close($conn);
$leave_types = ['Sick Leave', 'Personal Leave', 'Vacation', 'Other'];
$page_title = 'Request Leave';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Request Leave / Report Absence</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Submit a New Request</h5></div>
        <div class="card-body">
            <form action="request_leave.php" method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6"><label for="leave_type" class="form-label">Leave Type</label><select class="form-select" id="leave_type" name="leave_type" required><?php foreach($leave_types as $type) echo "<option value='{$type}'>{$type}</option>"; ?></select></div>
                    <div class="col-md-6"><label for="attachment" class="form-label">Attachment (optional)</label><input class="form-control" type="file" id="attachment" name="attachment"></div>
                    <div class="col-md-6"><label for="start_date" class="form-label">Start Date</label><input type="date" class="form-control" id="start_date" name="start_date" required></div>
                    <div class="col-md-6"><label for="end_date" class="form-label">End Date</label><input type="date" class="form-control" id="end_date" name="end_date" required></div>
                    <div class="col-12"><label for="reason" class="form-label">Reason for Absence</label><textarea class="form-control" id="reason" name="reason" rows="3" required></textarea></div>
                </div>
                <button type="submit" name="submit_leave" class="btn btn-primary mt-3">Submit Request</button>
            </form>
        </div>
    </div>

    <h3 id="leave-history">My Leave History</h3>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light"><tr><th>Requested On</th><th>Type</th><th>Period</th><th>Status</th><th>Reason</th><th>Attachment</th></tr></thead>
                <tbody>
                    <?php if (empty($past_requests)): ?>
                        <tr><td colspan="6" class="text-center text-muted">You have no past leave requests.</td></tr>
                    <?php else: ?>
                        <?php foreach ($past_requests as $req): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($req['request_date'])); ?></td>
                            <td><?php echo htmlspecialchars($req['leave_type']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($req['start_date'])) . ' to ' . date('M j, Y', strtotime($req['end_date'])); ?></td>
                            <td>
                                <?php $status_class = 'bg-secondary'; if ($req['status'] == 'Approved') $status_class = 'bg-success'; if ($req['status'] == 'Rejected') $status_class = 'bg-danger'; if ($req['status'] == 'Pending') $status_class = 'bg-warning text-dark'; ?>
                                <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($req['status']); ?></span>
                            </td>
                            <td><?php echo nl2br(htmlspecialchars($req['reason'])); ?></td>
                            <td><?php if ($req['attachment_path']): ?><a href="<?php echo htmlspecialchars($req['attachment_path']); ?>" target="_blank">View</a><?php else: ?>N/A<?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
            </div>
            <button type="submit" name="submit_leave" class="btn" style="margin-top: 1rem;">Submit Request</button>
        </form>
    </div>

    <h3>My Leave History</h3>
    <?php if (empty($past_requests)): ?>
        <p>You have no past leave requests.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>Request Date</th><th>Type</th><th>Period</th><th>Status</th><th>Reason</th></tr></thead>
        <tbody>
            <?php foreach ($past_requests as $req): ?>
            <tr>
                <td><?php echo date('M j, Y', strtotime($req['request_date'])); ?></td>
                <td><?php echo htmlspecialchars($req['leave_type']); ?></td>
                <td><?php echo date('M j, Y', strtotime($req['start_date'])) . ' - ' . date('M j, Y', strtotime($req['end_date'])); ?></td>
                <td class="status-<?php echo $req['status']; ?>"><?php echo $req['status']; ?></td>
                <td><?php echo htmlspecialchars($req['reason']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
</body>
</html>