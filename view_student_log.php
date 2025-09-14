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
$logs = [];

if (!$student_id || !is_numeric($student_id)) {
    die("<h1>Invalid Request</h1><p>No student ID provided.</p>");
}

// Fetch student's name
$stmt_student = mysqli_prepare($conn, "SELECT first_name, last_name, user_id FROM students WHERE student_id = ?");
mysqli_stmt_bind_param($stmt_student, "i", $student_id);
mysqli_stmt_execute($stmt_student);
$result_student = mysqli_stmt_get_result($stmt_student);
if ($student_row = mysqli_fetch_assoc($result_student)) {
    $student_name = htmlspecialchars($student_row['first_name'] . ' ' . $student_row['last_name']);
    $profile_user_id = $student_row['user_id'];
} else {
    die("<h1>Error</h1><p>Student not found.</p>");
}
mysqli_stmt_close($stmt_student);

// Fetch change logs
$sql_logs = "
    SELECT 
        l.field_changed, 
        l.old_value, 
        l.new_value, 
        l.changed_at,
        u.username as changed_by
    FROM student_profile_logs l
    LEFT JOIN users u ON l.changed_by_user_id = u.user_id
    WHERE l.student_id = ?
    ORDER BY l.changed_at DESC
";
$stmt_logs = mysqli_prepare($conn, $sql_logs);
mysqli_stmt_bind_param($stmt_logs, "i", $student_id);
mysqli_stmt_execute($stmt_logs);
$result_logs = mysqli_stmt_get_result($stmt_logs);
while ($row = mysqli_fetch_assoc($result_logs)) {
    $logs[] = $row;
}
mysqli_stmt_close($stmt_logs);

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Change Log for <?php echo $student_name; ?></title>
    <link rel="stylesheet" href="add_student.php">
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 1.5rem; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
<div class="container" style="max-width: 1000px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Profile Change Log for <?php echo $student_name; ?></h1>
        <a href="view_profile.php?user_id=<?php echo $profile_user_id; ?>" class="btn" style="background-color: #6b7280;">Back to Profile</a>
    </div>

    <?php if (empty($logs)): ?>
        <div class="message" style="margin-top: 1.5rem;">No changes have been logged for this student's profile.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Date</th><th>Field Changed</th><th>Old Value</th><th>New Value</th><th>Changed By</th></tr></thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr><td><?php echo date('F j, Y, g:i a', strtotime($log['changed_at'])); ?></td><td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['field_changed']))); ?></td><td><?php echo htmlspecialchars($log['old_value']); ?></td><td><?php echo htmlspecialchars($log['new_value']); ?></td><td><?php echo htmlspecialchars($log['changed_by'] ?? 'System'); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>