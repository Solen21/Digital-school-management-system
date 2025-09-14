<?php
session_start();

// 1. Check if the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$events = [];
$sql = "SELECT * FROM academic_calendar ORDER BY start_date ASC";
$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $events[] = $row;
    }
}

mysqli_close($conn);
$page_title = 'Academic Calendar';
include 'header.php';

$event_colors = [
    'Holiday' => 'bg-danger text-white',
    'Exam Period' => 'bg-warning text-dark',
    'Term Start' => 'bg-success text-white',
    'Term End' => 'bg-primary text-white',
    'Event' => 'bg-info text-dark',
];
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Academic Calendar</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="card">
        <div class="list-group list-group-flush">
            <?php if (empty($events)): ?>
                <div class="list-group-item">
                    <p class="text-muted text-center my-3">The academic calendar has not been set yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h5 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h5>
                        <small class="text-muted">
                            <?php echo date('M j, Y', strtotime($event['start_date'])); ?>
                            <?php if ($event['end_date'] && ($event['start_date'] != $event['end_date'])) echo ' - ' . date('M j, Y', strtotime($event['end_date'])); ?>
                        </small>
                    </div>
                    <p class="mb-1"><?php echo htmlspecialchars($event['description'] ?? ''); ?></p>
                    <small><span class="badge <?php echo $event_colors[$event['type']] ?? 'bg-secondary'; ?>"><?php echo htmlspecialchars($event['type']); ?></span></small>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>