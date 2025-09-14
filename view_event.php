<?php
session_start();

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$event_id = $_GET['id'] ?? null;

if (!$event_id || !is_numeric($event_id)) {
    die("<h1>Invalid Request</h1><p>No event ID provided. <a href='dashboard.php'>Return to Dashboard</a></p>");
}

$sql = "SELECT e.*, u.username as author_name 
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.user_id 
        WHERE e.event_id = ? AND e.status = 'published' AND e.event_date >= NOW()";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $event_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$event = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$event) {
    die("<h1>Event Not Found</h1><p>The requested event could not be found or is no longer available. <a href='dashboard.php'>Return to Dashboard</a></p>");
}

$page_title = $event['title'];
include 'header.php';
?>

<div class="container" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0"><?php echo htmlspecialchars($event['title']); ?></h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="card">
        <?php if (!empty($event['image_path']) && file_exists($event['image_path'])): ?>
            <img src="<?php echo htmlspecialchars($event['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($event['title']); ?>" style="max-height: 400px; object-fit: cover;">
        <?php endif; ?>
        <div class="card-body">
            <div class="d-flex justify-content-between text-muted small mb-3">
                <span>
                    <i class="bi bi-calendar-event-fill"></i> <?php echo date('l, F j, Y', strtotime($event['event_date'])); ?>
                    at <?php echo date('g:i A', strtotime($event['event_date'])); ?>
                </span>
                <?php if (!empty($event['location'])): ?>
                    <span><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($event['location']); ?></span>
                <?php endif; ?>
            </div>
            <hr>
            <div class="event-description mt-4">
                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
            </div>
        </div>
        <div class="card-footer text-muted small">
            Posted by: <?php echo htmlspecialchars($event['author_name'] ?? 'School Administration'); ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>