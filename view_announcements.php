<?php
session_start();

// 1. Check if the user is logged in and is a student or rep.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['student', 'rep'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$student_id = null;
$classroom_id = null;
$announcements = [];
$error_message = '';

// 2. Get the student's internal ID from their user_id.
$sql_student_id = "SELECT student_id FROM students WHERE user_id = ?";
$stmt_student_id = mysqli_prepare($conn, $sql_student_id);
mysqli_stmt_bind_param($stmt_student_id, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_student_id);
$result_student_id = mysqli_stmt_get_result($stmt_student_id);
if ($row = mysqli_fetch_assoc($result_student_id)) {
    $student_id = $row['student_id'];
} else {
    $error_message = "Could not find a student profile associated with your user account.";
}
mysqli_stmt_close($stmt_student_id);

if ($student_id) {
    // 3. Get the student's classroom ID.
    $sql_classroom = "SELECT classroom_id FROM class_assignments WHERE student_id = ?";
    $stmt_classroom = mysqli_prepare($conn, $sql_classroom);
    mysqli_stmt_bind_param($stmt_classroom, "i", $student_id);
    mysqli_stmt_execute($stmt_classroom);
    $result_classroom = mysqli_stmt_get_result($stmt_classroom);
    if ($row = mysqli_fetch_assoc($result_classroom)) {
        $classroom_id = $row['classroom_id'];
    } else {
        $error_message = "You are not currently assigned to a classroom, so you cannot see announcements.";
    }
    mysqli_stmt_close($stmt_classroom);
}

if ($classroom_id) {
    // 4. Fetch all announcements for that classroom, along with poster's info.
    $sql_announcements = "
        SELECT
            a.title, a.content, a.created_at, u.role AS poster_role,
            COALESCE(t.first_name, s.first_name, u.username) as poster_fname,
            COALESCE(t.last_name, s.last_name, '') as poster_lname
        FROM announcements a
        JOIN users u ON a.user_id = u.user_id
        LEFT JOIN teachers t ON u.user_id = t.user_id
        LEFT JOIN students s ON u.user_id = s.user_id
        WHERE a.classroom_id = ?
        ORDER BY a.created_at DESC
    ";
    $stmt_announcements = mysqli_prepare($conn, $sql_announcements);
    mysqli_stmt_bind_param($stmt_announcements, "i", $classroom_id);
    mysqli_stmt_execute($stmt_announcements);
    $result_announcements = mysqli_stmt_get_result($stmt_announcements);
    while ($row = mysqli_fetch_assoc($result_announcements)) {
        $announcements[] = $row;
    }
    mysqli_stmt_close($stmt_announcements);
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Announcements</title>
    <link rel="stylesheet" href="add_student.php">
    <style>
        .announcement-list { list-style: none; padding: 0; }
        .announcement-item { background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        .announcement-item h3 { margin-top: 0; }
        .announcement-meta { font-size: 0.875rem; color: #6b7280; margin-top: 1rem; border-top: 1px solid #e5e7eb; padding-top: 1rem; }
        .announcement-content { margin-top: 1rem; line-height: 1.6; }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1>Class Announcements</h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280;">Back to Dashboard</a>
    </div>

    <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div>
    <?php elseif (empty($announcements)): ?><div class="message">There are no announcements for your class at this time.</div>
    <?php else: ?>
        <ul class="announcement-list">
            <?php foreach ($announcements as $announcement): ?>
            <li class="announcement-item">
                <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                <div class="announcement-content"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></div>
                <div class="announcement-meta">
                    Posted by <?php echo htmlspecialchars(trim($announcement['poster_fname'] . ' ' . $announcement['poster_lname'])); ?>
                    (<?php echo htmlspecialchars(ucfirst($announcement['poster_role'])); ?>)
                    on <?php echo date('F j, Y, g:i a', strtotime($announcement['created_at'])); ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
</body>
</html>