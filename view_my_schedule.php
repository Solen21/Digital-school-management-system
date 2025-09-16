<?php
session_start();

// 1. Security Check: User must be logged in and a student/rep
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['student', 'rep'])) {
    // Allow guardians to view via GET param
    if (!isset($_GET['student_id']) || $_SESSION['role'] !== 'guardian') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$user_id = $_SESSION['user_id'];
$schedule_grid = [];
$student_info = null;
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

// 2. Determine which student's schedule to view
$student_id_to_view = null;
if ($_SESSION['role'] === 'guardian' && isset($_GET['student_id'])) {
    // Guardian viewing a child's schedule. Verify access.
    $child_student_id = intval($_GET['student_id']);
    $sql_verify = "SELECT COUNT(*) as count FROM student_guardian_map sgm JOIN guardians g ON sgm.guardian_id = g.guardian_id WHERE g.user_id = ? AND sgm.student_id = ?";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $user_id, $child_student_id);
    mysqli_stmt_execute($stmt_verify);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify))['count'] > 0) {
        $student_id_to_view = $child_student_id;
    }
} else {
    // Student viewing their own schedule
    $student_id_to_view = $_SESSION['student_id'] ?? null;
}

if ($student_id_to_view) {
    // Fetch student and classroom info
    $sql_student = "
        SELECT s.student_id, s.first_name, s.last_name, c.classroom_id, c.name as classroom_name, c.grade_level
        FROM students s
        LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
        LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
        WHERE s.student_id = ? LIMIT 1
    ";
    $stmt_student = mysqli_prepare($conn, $sql_student);
    mysqli_stmt_bind_param($stmt_student, "i", $student_id_to_view);
    mysqli_stmt_execute($stmt_student);
    $student_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_student));
    mysqli_stmt_close($stmt_student);
}

if ($student_info && !empty($student_info['classroom_id'])) {    
    // 3. Get the classroom's shift for the current week
    $current_week = date('W');
    $stmt_shift = mysqli_prepare($conn, "SELECT shift FROM weekly_shift_assignments WHERE grade_level = ? AND week_of_year = ?");
    mysqli_stmt_bind_param($stmt_shift, "ii", $student_info['grade_level'], $current_week);
    mysqli_stmt_execute($stmt_shift);
    $classroom_shift = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_shift))['shift'] ?? 'Morning';
    mysqli_stmt_close($stmt_shift);

    // 4. Fetch the full schedule grid for the classroom
    $sql_grid = "
        SELECT
            p.day_of_week, p.start_time, p.end_time, p.is_break,
            sub.name as subject_name,
            CONCAT(t.first_name, ' ', t.last_name) as teacher_name
        FROM class_schedule cs
        JOIN schedule_periods p ON cs.period_id = p.period_id
        JOIN subject_assignments sa ON cs.subject_assignment_id = sa.assignment_id
        JOIN subjects sub ON sa.subject_id = sub.subject_id
        JOIN teachers t ON sa.teacher_id = t.teacher_id
        WHERE cs.classroom_id = ? AND p.shift = ?
        ORDER BY p.start_time, p.day_of_week
    ";
    $stmt_grid = mysqli_prepare($conn, $sql_grid);
    mysqli_stmt_bind_param($stmt_grid, "is", $student_info['classroom_id'], $classroom_shift);
    mysqli_stmt_execute($stmt_grid);
    $result_grid = mysqli_stmt_get_result($stmt_grid);
    while ($row = mysqli_fetch_assoc($result_grid)) {
        $schedule_grid[$row['start_time']][$row['day_of_week']] = $row;
    }
    mysqli_stmt_close($stmt_grid);
}

mysqli_close($conn);

$page_title = 'My Class Schedule';
include 'header.php';
?>
<style>
    .schedule-table { table-layout: fixed; }
    .schedule-table th, .schedule-table td { text-align: center; vertical-align: middle; height: 80px; }
    .schedule-table .time-col { width: 120px; font-weight: bold; }
    .schedule-table .break-cell { background-color: var(--light-gray); font-style: italic; }
</style>

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