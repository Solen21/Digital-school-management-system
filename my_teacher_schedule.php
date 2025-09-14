<?php
session_start();

// 1. Check if the user is logged in and is a teacher.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$teacher_id = null;
$schedule = [];
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
        $schedule[date('h:i A', strtotime($row['start_time']))][$row['day_of_week']] = $row;
    }
    mysqli_stmt_close($stmt_schedule);
}

mysqli_close($conn);

$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Teaching Schedule</title>
    <link rel="stylesheet" href="add_student.php">
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 1.5rem; table-layout: fixed; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; vertical-align: middle; height: 70px; }
        th { background-color: #f2f2f2; }
        .break { background-color: #f0f0f0; font-style: italic; }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1>My Teaching Schedule</h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280;">Back to Dashboard</a>
    </div>

    <?php if ($error_message): ?>
        <div class="message error"><?php echo $error_message; ?></div>
    <?php elseif (empty($schedule)): ?>
        <div class="message">You do not have any classes scheduled yet.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Time</th><?php foreach ($days_of_week as $day) echo "<th>$day</th>"; ?></tr></thead>
            <tbody>
            <?php
                // Get all possible periods to build the table rows
                $conn_temp = mysqli_connect("localhost", "root", "", "sms");
                $result_periods = mysqli_query($conn_temp, "SELECT DISTINCT start_time, end_time FROM schedule_periods ORDER BY start_time");
                $periods_by_time = mysqli_fetch_all($result_periods, MYSQLI_ASSOC);
                mysqli_close($conn_temp);
            ?>
            <?php foreach ($periods_by_time as $time_slot): ?>
                <tr>
                    <th><?php echo date('h:i A', strtotime($time_slot['start_time'])); ?></th>
                    <?php foreach ($days_of_week as $day): ?>
                        <?php $period_info = $schedule[date('h:i A', strtotime($time_slot['start_time']))][$day] ?? null; ?>
                        <?php if ($period_info && $period_info['is_break']): ?><td class="break">Break</td>
                        <?php elseif ($period_info): ?><td><?php echo htmlspecialchars($period_info['subject_name']); ?><br><small><?php echo htmlspecialchars($period_info['classroom_name']); ?></small></td>
                        <?php else: ?><td>-</td><?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>