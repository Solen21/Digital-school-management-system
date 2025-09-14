<?php
session_start();

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$student_id = $_GET['student_id'] ?? null;
$viewer_user_id = $_SESSION['user_id'];
$viewer_role = $_SESSION['role'];

// If a student/rep is viewing their own grades, get their student_id automatically
if (is_null($student_id) && in_array($viewer_role, ['student', 'rep'])) {
    $sql_get_id = "SELECT student_id FROM students WHERE user_id = ? LIMIT 1";
    $stmt_get_id = mysqli_prepare($conn, $sql_get_id);
    mysqli_stmt_bind_param($stmt_get_id, "i", $viewer_user_id);
    mysqli_stmt_execute($stmt_get_id);
    $result_get_id = mysqli_stmt_get_result($stmt_get_id);
    if ($row = mysqli_fetch_assoc($result_get_id)) {
        $student_id = $row['student_id'];
    }
    mysqli_stmt_close($stmt_get_id);
}

if (empty($student_id) || !is_numeric($student_id)) {
    die("<h1>Error</h1><p>No student specified or invalid ID.</p>");
}

// 2. Authorization Check
$is_authorized = false;
if (in_array($viewer_role, ['student', 'rep'])) {
    // Check if the student_id belongs to the logged-in user
    $sql_verify = "SELECT student_id FROM students WHERE user_id = ? AND student_id = ? LIMIT 1";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $viewer_user_id, $student_id);
    mysqli_stmt_execute($stmt_verify);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify))) {
        $is_authorized = true;
    }
    mysqli_stmt_close($stmt_verify);
} elseif ($viewer_role === 'guardian') {
    // Check if the student is one of the guardian's children
    $sql_verify = "SELECT COUNT(*) as count FROM guardians g JOIN student_guardian_map sgm ON g.guardian_id = sgm.guardian_id WHERE g.user_id = ? AND sgm.student_id = ?";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $viewer_user_id, $student_id);
    mysqli_stmt_execute($stmt_verify);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify))['count'] > 0) {
        $is_authorized = true;
    }
    mysqli_stmt_close($stmt_verify);
}

if (!$is_authorized) {
    die("<h1>Access Denied</h1><p>You are not authorized to view this student's grades.</p>");
}

// 3. Fetch Student and Grade Data
$student_info = null;
$grades = [];
$overall_average = 0;

// Get student info
$sql_student = "SELECT first_name, last_name, grade_level FROM students WHERE student_id = ?";
$stmt_student = mysqli_prepare($conn, $sql_student);
mysqli_stmt_bind_param($stmt_student, "i", $student_id);
mysqli_stmt_execute($stmt_student);
$student_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_student));
mysqli_stmt_close($stmt_student);

if (!$student_info) {
    die("<h1>Error</h1><p>Student profile not found.</p>");
}

// Get grades
$sql_grades = "
    SELECT
        s.name as subject_name,
        CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
        g.test, g.assignment, g.activity, g.exercise, g.midterm, g.final, g.total
    FROM grades g
    JOIN subjects s ON g.subject_id = s.subject_id
    LEFT JOIN teachers t ON g.teacher_id = t.teacher_id
    WHERE g.student_id = ?
    ORDER BY s.name
";
$stmt_grades = mysqli_prepare($conn, $sql_grades);
mysqli_stmt_bind_param($stmt_grades, "i", $student_id);
mysqli_stmt_execute($stmt_grades);
$result_grades = mysqli_stmt_get_result($stmt_grades);
$total_sum = 0;
$subject_count = 0;
while ($row = mysqli_fetch_assoc($result_grades)) {
    $grades[] = $row;
    $total_sum += $row['total'];
    $subject_count++;
}
mysqli_stmt_close($stmt_grades);

if ($subject_count > 0) {
    $overall_average = $total_sum / $subject_count;
}

$chart_labels = array_column($grades, 'subject_name');
$chart_data = array_column($grades, 'total');

mysqli_close($conn);

$back_link = ($viewer_role === 'guardian') ? 'my_children.php' : 'dashboard.php';
$page_title = 'View Grades';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Grade Report</h1>
            <p class="lead text-muted">For <?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></p>
        </div>
        <a href="<?php echo $back_link; ?>" class="btn btn-secondary">Back</a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Performance Overview</h5></div>
                <div class="card-body">
                    <canvas id="gradesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h6 class="text-muted">Overall Average</h6>
                    <h2 class="display-4 fw-bold"><?php echo number_format($overall_average, 2); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><h5 class="mb-0">Detailed Grades</h5></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Subject</th><th>Test (10)</th><th>Assign. (10)</th><th>Activity (10)</th><th>Exercise (10)</th><th>Midterm (20)</th><th>Final (40)</th><th>Total (100)</th><th>Teacher</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($grades)): ?>
                        <tr><td colspan="9" class="text-center text-muted">No grades have been entered for this student yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($grades as $grade): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($grade['test']); ?></td>
                            <td><?php echo htmlspecialchars($grade['assignment']); ?></td>
                            <td><?php echo htmlspecialchars($grade['activity']); ?></td>
                            <td><?php echo htmlspecialchars($grade['exercise']); ?></td>
                            <td><?php echo htmlspecialchars($grade['midterm']); ?></td>
                            <td><?php echo htmlspecialchars($grade['final']); ?></td>
                            <td class="fw-bold"><?php echo htmlspecialchars($grade['total']); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($grade['teacher_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('gradesChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Total Score',
                    data: <?php echo json_encode($chart_data); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                scales: {
                    x: { beginAtZero: true, max: 100 }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>

<?php include 'footer.php'; ?>