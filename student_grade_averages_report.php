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
        s.first_name, s.last_name, s.grade_level,
        c.name as classroom_name,
        AVG(g.total) as average_grade
    FROM students s
    LEFT JOIN grades g ON s.student_id = g.student_id
    LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
    LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
    WHERE s.status = 'active'
    GROUP BY s.student_id
    ORDER BY s.grade_level, c.name, s.last_name;
";

$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $report_data[] = $row;
    }
}

mysqli_close($conn);
$page_title = 'Student Grade Averages Report';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Student Grade Averages</h1>
        <div>
            <a href="export_grade_averages.php" class="btn btn-success"><i class="bi bi-file-earmark-excel-fill me-2"></i>Export to Excel</a>
            <a href="reports_hub.php" class="btn btn-secondary">Back to Reports Hub</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <p>This report shows the overall grade average for each active student across all their subjects.</p>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>Student Name</th><th>Grade</th><th>Section</th><th>Overall Average</th></tr></thead>
                    <tbody><?php if (empty($report_data)): ?><tr><td colspan="4" class="text-center">No student grade data found.</td></tr><?php else: ?><?php foreach ($report_data as $row): ?><tr><td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td><td><?php echo htmlspecialchars($row['grade_level']); ?></td><td><?php echo htmlspecialchars($row['classroom_name'] ?? 'N/A'); ?></td><td><?php echo $row['average_grade'] ? number_format($row['average_grade'], 2) . '%' : 'N/A'; ?></td></tr><?php endforeach; ?><?php endif; ?></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>