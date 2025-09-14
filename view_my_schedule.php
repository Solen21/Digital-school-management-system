<?php
session_start();

// 1. Security Check: User must be logged in and a student/rep
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['student', 'rep'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$user_id = $_SESSION['user_id'];
$schedule_data = [];
$student_info = null;

// 2. Fetch student and classroom info
$sql_student = "
    SELECT s.student_id, s.first_name, s.last_name, c.classroom_id, c.name as classroom_name
    FROM students s
    LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
    LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
    WHERE s.user_id = ?
    LIMIT 1
";
$stmt_student = mysqli_prepare($conn, $sql_student);
mysqli_stmt_bind_param($stmt_student, "i", $user_id);
mysqli_stmt_execute($stmt_student);
$result_student = mysqli_stmt_get_result($stmt_student);
$student_info = mysqli_fetch_assoc($result_student);
mysqli_stmt_close($stmt_student);

if ($student_info && !empty($student_info['classroom_id'])) {
    $classroom_id = $student_info['classroom_id'];

    // 3. Fetch the schedule (subjects and teachers) for the student's classroom
    $sql_schedule = "
        SELECT
            sub.name as subject_name,
            sub.code as subject_code,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name
        FROM subject_assignments sa
        JOIN subjects sub ON sa.subject_id = sub.subject_id
        LEFT JOIN teachers t ON sa.teacher_id = t.teacher_id
        WHERE sa.classroom_id = ?
        ORDER BY sub.name
    ";
    $stmt_schedule = mysqli_prepare($conn, $sql_schedule);
    mysqli_stmt_bind_param($stmt_schedule, "i", $classroom_id);
    mysqli_stmt_execute($stmt_schedule);
    $result_schedule = mysqli_stmt_get_result($stmt_schedule);
    while ($row = mysqli_fetch_assoc($result_schedule)) {
        $schedule_data[] = $row;
    }
    mysqli_stmt_close($stmt_schedule);
}

mysqli_close($conn);

$page_title = 'My Class Schedule';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">My Class Schedule</h1>
            <p class="lead text-muted">
                For <?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?>
            </p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if (empty($student_info['classroom_id'])): ?>
        <div class="alert alert-warning">
            You are not currently assigned to a classroom. Please contact the administration.
        </div>
    <?php elseif (empty($schedule_data)): ?>
        <div class="alert alert-info">
            No subjects have been scheduled for your classroom (<?php echo htmlspecialchars($student_info['classroom_name']); ?>) yet.
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-door-open-fill me-2"></i>
                    Classroom: <?php echo htmlspecialchars($student_info['classroom_name']); ?>
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Subject</th>
                            <th>Subject Code</th>
                            <th>Teacher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedule_data as $item): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($item['subject_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['subject_code']); ?></td>
                            <td class="text-muted"><?php echo htmlspecialchars($item['teacher_name'] ?? 'Not Assigned'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="alert alert-info mt-4">
            <strong>Note:</strong> This list shows your subjects and teachers. For specific class times and periods, please refer to the official school timetable.
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>