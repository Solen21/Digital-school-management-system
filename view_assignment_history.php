<?php
session_start();

// 1. Security Check: Ensure only admin/director can access
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$student_id = $_GET['student_id'] ?? null;
$student_name = '';
$history = [];

if (!$student_id || !is_numeric($student_id)) {
    die("<h1>Invalid Request</h1><p>No student ID provided.</p>");
}

// Fetch student's name
$stmt_student = mysqli_prepare($conn, "SELECT first_name, last_name FROM students WHERE student_id = ?");
mysqli_stmt_bind_param($stmt_student, "i", $student_id);
mysqli_stmt_execute($stmt_student);
$result_student = mysqli_stmt_get_result($stmt_student);
if ($student_row = mysqli_fetch_assoc($result_student)) {
    $student_name = htmlspecialchars($student_row['first_name'] . ' ' . $student_row['last_name']);
} else {
    die("<h1>Error</h1><p>Student not found.</p>");
}
mysqli_stmt_close($stmt_student);

// Fetch assignment history
$sql_history = "
    SELECT 
        h.assigned_date, 
        h.left_date, 
        c.name as classroom_name, 
        u.username as assigned_by
    FROM class_assignment_history h
    JOIN classrooms c ON h.classroom_id = c.classroom_id
    LEFT JOIN users u ON h.assigned_by_user_id = u.user_id
    WHERE h.student_id = ?
    ORDER BY h.assigned_date DESC
";
$stmt_history = mysqli_prepare($conn, $sql_history);
mysqli_stmt_bind_param($stmt_history, "i", $student_id);
mysqli_stmt_execute($stmt_history);
$result_history = mysqli_stmt_get_result($stmt_history);
while ($row = mysqli_fetch_assoc($result_history)) {
    $history[] = $row;
}
mysqli_stmt_close($stmt_history);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment History for <?php echo $student_name; ?></title>
    <link rel="stylesheet" href="add_student.php">
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 1.5rem; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
<div class="container" style="max-width: 900px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Assignment History for <?php echo $student_name; ?></h1>
        <a href="view_profile.php?user_id=<?php echo $_GET['user_id'] ?? ''; ?>" class="btn" style="background-color: #6b7280;">Back to Profile</a>
    </div>

    <?php if (empty($history)): ?>
        <div class="message" style="margin-top: 1.5rem;">No assignment history found for this student.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Classroom</th><th>Date Assigned</th><th>Date Left</th><th>Assigned By</th></tr></thead>
            <tbody>
                <?php foreach ($history as $record): ?>
                <tr>
                    <td><?php echo htmlspecialchars($record['classroom_name']); ?></td>
                    <td><?php echo date('F j, Y, g:i a', strtotime($record['assigned_date'])); ?></td>
                    <td><?php echo $record['left_date'] ? date('F j, Y, g:i a', strtotime($record['left_date'])) : 'Current'; ?></td>
                    <td><?php echo htmlspecialchars($record['assigned_by'] ?? 'System'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>