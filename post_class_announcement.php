<?php
session_start();

// 1. Check if the user is logged in and is a class rep.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'rep') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$message = '';
$message_type = '';
$classroom_id = null;

// 2. Get the student's classroom ID to post the announcement to.
$sql_student_info = "SELECT ca.classroom_id FROM students s JOIN class_assignments ca ON s.student_id = ca.student_id WHERE s.user_id = ?";
$stmt_student_info = mysqli_prepare($conn, $sql_student_info);
mysqli_stmt_bind_param($stmt_student_info, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_student_info);
$result_student_info = mysqli_stmt_get_result($stmt_student_info);
if ($row = mysqli_fetch_assoc($result_student_info)) {
    $classroom_id = $row['classroom_id'];
} else {
    // This rep is not in a class, so they can't post.
    die("<h1>Error</h1><p>You are not assigned to a class, so you cannot post announcements. <a href='dashboard.php'>Return to Dashboard</a></p>");
}
mysqli_stmt_close($stmt_student_info);


// --- Handle POST request to save announcement ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_announcement'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $user_id = $_SESSION['user_id'];

    if (empty($title) || empty($content)) {
        $message = "Title and content are required.";
        $message_type = 'error';
    } else {
        $sql = "INSERT INTO announcements (user_id, classroom_id, title, content) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iiss", $user_id, $classroom_id, $title, $content);

        if (mysqli_stmt_execute($stmt)) {
            $message = "Announcement posted successfully to your class.";
            $message_type = 'success';
        } else {
            $message = "Error posting announcement: " . mysqli_stmt_error($stmt);
            $message_type = 'error';
        }
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Class Announcement</title>
    <link rel="stylesheet" href="add_student.php">
</head>
<body>
<div class="container" style="max-width: 800px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Post a New Announcement to Your Class</h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280;">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form action="post_class_announcement.php" method="POST">
        <input type="hidden" name="post_announcement" value="1">
        <div class="form-grid" style="grid-template-columns: 1fr;">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" required>
            </div>
            <div class="form-group">
                <label for="content">Content</label>
                <textarea id="content" name="content" rows="8" required style="font-family: inherit; font-size: 1rem; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem;"></textarea>
            </div>
            <div class="form-group">
                <button type="submit" class="btn">Post Announcement</button>
            </div>
        </div>
    </form>

</div>
</body>
</html>