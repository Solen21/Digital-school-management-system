<?php
session_start();

// 1. Check if the user is logged in and is a teacher.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$message = '';
$message_type = '';
$teacher_id = null;
$teacher_classes = [];

// 2. Get the teacher's internal ID from their user_id.
$sql_teacher_id = "SELECT teacher_id FROM teachers WHERE user_id = ?";
$stmt_teacher_id = mysqli_prepare($conn, $sql_teacher_id);
mysqli_stmt_bind_param($stmt_teacher_id, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_teacher_id);
$result_teacher_id = mysqli_stmt_get_result($stmt_teacher_id);
if ($row = mysqli_fetch_assoc($result_teacher_id)) {
    $teacher_id = $row['teacher_id'];
} else {
    die("<h1>Error</h1><p>Could not find a teacher profile associated with your user account. <a href='dashboard.php'>Return to Dashboard</a></p>");
}
mysqli_stmt_close($stmt_teacher_id);

// 3. Get all unique classrooms the teacher is assigned to.
$sql_classes = "
    SELECT DISTINCT c.classroom_id, c.name 
    FROM subject_assignments sa 
    JOIN classrooms c ON sa.classroom_id = c.classroom_id 
    WHERE sa.teacher_id = ? 
    ORDER BY c.name
";
$stmt_classes = mysqli_prepare($conn, $sql_classes);
mysqli_stmt_bind_param($stmt_classes, "i", $teacher_id);
mysqli_stmt_execute($stmt_classes);
$result_classes = mysqli_stmt_get_result($stmt_classes);
while ($row = mysqli_fetch_assoc($result_classes)) {
    $teacher_classes[] = $row;
}
mysqli_stmt_close($stmt_classes);


// --- Handle POST request to save announcement ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_announcement'])) {
    $classroom_id = $_POST['classroom_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    $user_id = $_SESSION['user_id'];

    if (empty($classroom_id) || empty($title) || empty($content)) {
        $message = "Class, title, and content are required.";
        $message_type = 'error';
    } else {
        // Verify the teacher is actually assigned to this class to prevent unauthorized posts
        $is_authorized = false;
        foreach ($teacher_classes as $class) {
            if ($class['classroom_id'] == $classroom_id) {
                $is_authorized = true;
                break;
            }
        }

        if ($is_authorized) {
            $sql = "INSERT INTO announcements (user_id, classroom_id, title, content) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "iiss", $user_id, $classroom_id, $title, $content);

            if (mysqli_stmt_execute($stmt)) {
                $message = "Announcement posted successfully.";
                $message_type = 'success';
            } else {
                $message = "Error posting announcement: " . mysqli_stmt_error($stmt);
                $message_type = 'error';
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "You are not authorized to post to this class.";
            $message_type = 'error';
        }
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
        <h1>Post a New Announcement</h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280;">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (empty($teacher_classes)): ?>
        <div class="message">You are not assigned to any classes, so you cannot post announcements.</div>
    <?php else: ?>
    <form action="teacher_post_announcement.php" method="POST">
        <input type="hidden" name="post_announcement" value="1">
        <div class="form-grid" style="grid-template-columns: 1fr;">
            <div class="form-group">
                <label for="classroom_id">Select Class</label>
                <select id="classroom_id" name="classroom_id" required>
                    <option value="">-- Select a class to post to --</option>
                    <?php foreach ($teacher_classes as $class): ?>
                        <option value="<?php echo $class['classroom_id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
    <?php endif; ?>

</div>
</body>
</html>