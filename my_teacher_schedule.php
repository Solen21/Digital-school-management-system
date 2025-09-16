<?php
session_start();

// 1. Check if the user is logged in and is a teacher.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$teacher_id = null;
$schedule_grid = [];
$error_message = '';

// 2. Get the teacher's internal ID from their user_id.
$sql_teacher_id = "SELECT teacher_id FROM teachers WHERE user_id = ?";
$stmt_teacher_id = mysqli_prepare($conn, $sql_teacher_id);
mysqli_stmt_bind_param($stmt_teacher_id, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_teacher_id);
$result_teacher_id = mysqli_stmt_get_result($stmt_teacher_id);
if ($row = mysqli_fetch_assoc($result_teacher_id)) {
    $teacher_id = $row['teacher_id'];
} else {
    $error_message = "Could not find a teacher profile associated with your user account.";
}
mysqli_stmt_close($stmt_teacher_id);

if ($teacher_id) {
    // 3. Fetch the schedule for the teacher.
    $sql_schedule = "
        SELECT
            p.day_of_week,
            p.start_time,
            p.end_time,
            p.is_break,
            s.name AS subject_name,
            c.name AS classroom_name
        FROM subject_assignments sa
        JOIN class_schedule cs ON sa.assignment_id = cs.subject_assignment_id
        JOIN schedule_periods p ON cs.period_id = p.period_id
        JOIN subjects s ON sa.subject_id = s.subject_id
        JOIN classrooms c ON sa.classroom_id = c.classroom_id
        WHERE sa.teacher_id = ?
        ORDER BY p.day_of_week, p.start_time
    ";
    $stmt_schedule = mysqli_prepare($conn, $sql_schedule);
    mysqli_stmt_bind_param($stmt_schedule, "i", $teacher_id);
    mysqli_stmt_execute($stmt_schedule);
    $result_schedule = mysqli_stmt_get_result($stmt_schedule);
    while ($row = mysqli_fetch_assoc($result_schedule)) {
        $schedule_grid[date('H:i:s', strtotime($row['start_time']))][$row['day_of_week']] = $row;
    }
    mysqli_stmt_close($stmt_schedule);
}

mysqli_close($conn);

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$page_title = 'My Teaching Schedule';
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Teaching Schedule</title>
    <link rel="stylesheet" href="add_student.php">
    <style>
        .schedule-table { table-layout: fixed; }
        .schedule-table th, .schedule-table td { text-align: center; vertical-align: middle; height: 80px; }
        .schedule-table .time-col { width: 120px; font-weight: bold; }
        .schedule-table .break-cell { background-color: var(--light-gray); font-style: italic; }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>My Teaching Schedule</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php elseif (empty($schedule_grid)): ?>
        <div class="alert alert-info">You do not have any classes scheduled yet.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-table me-2"></i>Weekly Timetable</h5></div>
            <div class="table-responsive">
                <table class="table table-bordered schedule-table">
                    <thead class="table-light">
                        <tr><th class="time-col">Time</th><?php foreach ($days_of_week as $day) echo "<th>$day</th>"; ?></tr>
                    </thead>
                    <tbody>
                        <?php ksort($schedule_grid); foreach ($schedule_grid as $time => $days): ?>
                            <tr>
                                <td class="time-col"><?php echo date('g:i A', strtotime($time)); ?></td>
                                <?php foreach ($days_of_week as $day): ?>
                                    <?php $period = $days[$day] ?? null; ?>
                                    <?php if ($period && $period['is_break']): ?><td class="break-cell">Break</td>
                                    <?php elseif ($period): ?><td><strong><?php echo htmlspecialchars($period['subject_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($period['classroom_name']); ?></small></td>
                                    <?php else: ?><td>-</td><?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>