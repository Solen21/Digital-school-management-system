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
        t.first_name, t.last_name,
        COUNT(DISTINCT sa.subject_id) as subject_count,
        COUNT(DISTINCT sa.classroom_id) as classroom_count
    FROM teachers t
    LEFT JOIN subject_assignments sa ON t.teacher_id = sa.teacher_id
    WHERE t.status = 'active'
    GROUP BY t.teacher_id
    ORDER BY t.last_name, t.first_name;
";

$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $report_data[] = $row;
    }
}

mysqli_close($conn);
$page_title = 'Teacher Workload Report';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Teacher Workload Report</h1>
        <a href="reports_hub.php" class="btn btn-secondary">Back to Reports Hub</a>
    </div>

    <div class="card">
        <div class="card-body">
            <p>This report shows the number of unique subjects and classrooms assigned to each active teacher.</p>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>Teacher Name</th><th>Assigned Subjects</th><th>Assigned Classrooms</th></tr></thead>
                    <tbody><?php if (empty($report_data)): ?><tr><td colspan="3" class="text-center">No teacher data found.</td></tr><?php else: ?><?php foreach ($report_data as $row): ?><tr><td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td><td><?php echo $row['subject_count']; ?></td><td><?php echo $row['classroom_count']; ?></td></tr><?php endforeach; ?><?php endif; ?></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>