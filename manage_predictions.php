<?php
session_start();

// 1. Check if the user is logged in and is an admin or director.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

$page_title = 'Manage Performance Predictions';

$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);

// --- Handle POST request to clear predictions ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['clear_all_predictions'])) {
    $sql_clear = "TRUNCATE TABLE performance_predictions";
    if (mysqli_query($conn, $sql_clear)) {
        log_activity($conn, 'clear_predictions', null, 'All performance predictions were cleared.');
        $_SESSION['message'] = "All performance predictions have been cleared successfully.";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Error clearing predictions: " . mysqli_error($conn);
        $_SESSION['message_type'] = 'danger';
    }
    header("Location: manage_predictions.php");
    exit();
}

// --- Fetch stats for the dashboard ---
$stats = [
    'last_run' => 'Never',
    'total_predictions' => 0,
    'high_risk' => 0,
    'medium_risk' => 0,
    'low_risk' => 0
];

// Get last run date
$result_last_run = mysqli_query($conn, "SELECT MAX(prediction_date) as last_date FROM performance_predictions");
if ($row = mysqli_fetch_assoc($result_last_run)) {
    if ($row['last_date']) {
        $stats['last_run'] = date('M j, Y', strtotime($row['last_date']));
    }
}

// Get counts
$result_counts = mysqli_query($conn, "
    SELECT 
        risk_level, 
        COUNT(prediction_id) as count 
    FROM performance_predictions 
    WHERE prediction_date = (SELECT MAX(prediction_date) FROM performance_predictions)
    GROUP BY risk_level
");
if ($result_counts) {
    while ($row = mysqli_fetch_assoc($result_counts)) {
        $stats['total_predictions'] += $row['count'];
        if ($row['risk_level'] == 'High') $stats['high_risk'] = $row['count'];
        if ($row['risk_level'] == 'Medium') $stats['medium_risk'] = $row['count'];
        if ($row['risk_level'] == 'Low') $stats['low_risk'] = $row['count'];
    }
}

mysqli_close($conn);
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-robot"></i> Manage AI Predictions</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="card text-white bg-primary"><div class="card-body text-center"><h3><?php echo $stats['last_run']; ?></h3><p class="mb-0">Last Prediction Run</p></div></div></div>
        <div class="col-md-3"><div class="card text-white bg-secondary"><div class="card-body text-center"><h3><?php echo number_format($stats['total_predictions']); ?></h3><p class="mb-0">Total Active Predictions</p></div></div></div>
        <div class="col-md-2"><div class="card text-white bg-danger"><div class="card-body text-center"><h3><?php echo number_format($stats['high_risk']); ?></h3><p class="mb-0">High Risk</p></div></div></div>
        <div class="col-md-2"><div class="card text-dark bg-warning"><div class="card-body text-center"><h3><?php echo number_format($stats['medium_risk']); ?></h3><p class="mb-0">Medium Risk</p></div></div></div>
        <div class="col-md-2"><div class="card text-white bg-success"><div class="card-body text-center"><h3><?php echo number_format($stats['low_risk']); ?></h3><p class="mb-0">Low Risk</p></div></div></div>
    </div>

    <!-- Actions Card -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-activity"></i> System Actions</h5>
        </div>
        <div class="card-body">
            <p>Use the actions below to manage the performance prediction system. Running new predictions may take several minutes depending on the amount of data.</p>
            <div class="d-flex flex-wrap gap-3">
                <a href="run_prediction_model.php" class="btn btn-lg btn-primary" onclick="return confirm('This will generate new predictions for all students and subjects. This process can be resource-intensive and may take some time. Continue?');">
                    <i class="bi bi-play-circle-fill me-2"></i>Run New Predictions
                </a>
                <a href="view_performance_predictions.php" class="btn btn-lg btn-info">
                    <i class="bi bi-eye-fill me-2"></i>View Prediction Results
                </a>
                <form action="manage_predictions.php" method="POST" class="d-inline" onsubmit="return confirm('DANGER: This will permanently delete ALL prediction data from the system. This action cannot be undone. Are you absolutely sure?');">
                    <button type="submit" name="clear_all_predictions" class="btn btn-lg btn-danger">
                        <i class="bi bi-trash3-fill me-2"></i>Clear All Predictions
                    </button>
                </form>
            </div>
        </div>
        <div class="card-footer text-muted">
            The prediction model uses historical grades, attendance, and discipline records to forecast future academic performance and identify at-risk students.
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>