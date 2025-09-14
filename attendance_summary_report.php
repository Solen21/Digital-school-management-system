<?php
session_start();

// 1. Security Check
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['director', 'admin'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$report_data = [];
$sql = "
    SELECT
        c.name as classroom_name,
        COUNT(a.attendance_id) as total_records,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count
    FROM attendance a
    JOIN classrooms c ON a.classroom_id = c.classroom_id
    GROUP BY a.classroom_id
    ORDER BY c.name;
";

$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $report_data[] = $row;
    }
}

mysqli_close($conn);
$page_title = 'Attendance Summary Report';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Attendance Summary Report</h1>
        <a href="reports_hub.php" class="btn btn-secondary">Back to Reports Hub</a>
    </div>

    <div class="card">
        <div class="card-body">
            <p>This report shows the overall attendance percentage for each classroom based on recorded data.</p>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>Classroom</th><th>Total Days Recorded</th><th>Present Days</th><th>Attendance Percentage</th></tr></thead>
                    <tbody>
                        <?php if (empty($report_data)): ?><tr><td colspan="4" class="text-center">No attendance data found.</td></tr>
                        <?php else: ?><?php foreach ($report_data as $row): ?><?php $percentage = ($row['total_records'] > 0) ? ($row['present_count'] / $row['total_records']) * 100 : 0; ?><tr><td><?php echo htmlspecialchars($row['classroom_name']); ?></td><td><?php echo $row['total_records']; ?></td><td><?php echo $row['present_count']; ?></td><td><?php echo number_format($percentage, 2); ?>%</td></tr><?php endforeach; ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>