<?php
session_start();

// 1. Check if the user is logged in and is a class rep.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'rep') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$student_id = null;
$classroom_id = null;
$classroom_name = '';
$class_roster = [];
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
    // 3. Get the student's classroom ID and name.
    $sql_classroom = "SELECT c.classroom_id, c.name FROM class_assignments ca JOIN classrooms c ON ca.classroom_id = c.classroom_id WHERE ca.student_id = ?";
    $stmt_classroom = mysqli_prepare($conn, $sql_classroom);
    mysqli_stmt_bind_param($stmt_classroom, "i", $student_id);
    mysqli_stmt_execute($stmt_classroom);
    $result_classroom = mysqli_stmt_get_result($stmt_classroom);
    if ($row = mysqli_fetch_assoc($result_classroom)) {
        $classroom_id = $row['classroom_id'];
        $classroom_name = $row['name'];
    } else {
        $error_message = "You are not currently assigned to a classroom.";
    }
    mysqli_stmt_close($stmt_classroom);
}

if ($classroom_id) {
    // 4. Fetch all students in that classroom.
    $sql_roster = "SELECT s.first_name, s.last_name, s.phone, s.email FROM class_assignments ca JOIN students s ON ca.student_id = s.student_id WHERE ca.classroom_id = ? ORDER BY s.last_name, s.first_name";
    $stmt_roster = mysqli_prepare($conn, $sql_roster);
    mysqli_stmt_bind_param($stmt_roster, "i", $classroom_id);
    mysqli_stmt_execute($stmt_roster);
    $result_roster = mysqli_stmt_get_result($stmt_roster);
    while ($row = mysqli_fetch_assoc($result_roster)) {
        $class_roster[] = $row;
    }
    mysqli_stmt_close($stmt_roster);
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Class Roster</title>
    <link rel="stylesheet" href="add_student.php">
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 1.5rem; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1>Class Roster for <?php echo htmlspecialchars($classroom_name); ?></h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280;">Back to Dashboard</a>
    </div>

    <?php if ($error_message): ?><div class="message error"><?php echo $error_message; ?></div>
    <?php elseif (empty($class_roster)): ?><div class="message">Could not find any classmates.</div>
    <?php else: ?>
        <table>
            <thead><tr><th>Name</th><th>Phone</th><th>Email</th></tr></thead>
            <tbody>
                <?php foreach ($class_roster as $student): ?>
                <tr><td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td><td><?php echo htmlspecialchars($student['phone']); ?></td><td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>