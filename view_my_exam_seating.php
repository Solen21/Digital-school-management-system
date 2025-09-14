<?php
session_start();

// 1. Check if the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$student_id = null;
$student_name = '';
$exam_assignments = [];
$error_message = '';

// 2. Determine which student's data to fetch.
if (isset($_GET['student_id']) && $_SESSION['role'] === 'guardian') {
    // Guardian is viewing a specific child's data.
    // First, verify this guardian is linked to this student.
    $guardian_user_id = $_SESSION['user_id'];
    $child_student_id = $_GET['student_id'];

    $sql_verify = "
        SELECT s.first_name, s.last_name
        FROM student_guardian_map sgm
        JOIN guardians g ON sgm.guardian_id = g.guardian_id
        JOIN students s ON sgm.student_id = s.student_id
        WHERE g.user_id = ? AND sgm.student_id = ?
    ";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $guardian_user_id, $child_student_id);
    mysqli_stmt_execute($stmt_verify);
    $result_verify = mysqli_stmt_get_result($stmt_verify);
    
    if ($row = mysqli_fetch_assoc($result_verify)) {
        $student_id = $child_student_id;
        $student_name = $row['first_name'] . ' ' . $row['last_name'];
    } else {
        $error_message = "Access Denied. You are not authorized to view this student's records.";
    }
    mysqli_stmt_close($stmt_verify);

} elseif (in_array($_SESSION['role'], ['student', 'rep'])) {
    // Student or Class Rep is viewing their own data.
    $sql_student_info = "SELECT student_id, first_name, last_name FROM students WHERE user_id = ?";
    $stmt_student_info = mysqli_prepare($conn, $sql_student_info);
    mysqli_stmt_bind_param($stmt_student_info, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt_student_info);
    $result_student_info = mysqli_stmt_get_result($stmt_student_info);
    if ($row = mysqli_fetch_assoc($result_student_info)) {
        $student_id = $row['student_id'];
        $student_name = $row['first_name'] . ' ' . $row['last_name'];
    } else {
        $error_message = "Could not find a student profile associated with your user account.";
    }
    mysqli_stmt_close($stmt_student_info);
} else {
    $error_message = "Your role is not authorized to view this page.";
}

if ($student_id && empty($error_message)) {
    // 3. Fetch all exam assignments for this student.
    $sql_assignments = "
        SELECT
            e.name AS exam_name,
            e.exam_date,
            er.name AS room_name,
            ea.seat_number
        FROM exam_assignments ea
        JOIN exams e ON ea.exam_id = e.exam_id
        JOIN exam_rooms er ON ea.room_id = er.room_id
        WHERE ea.student_id = ?
        ORDER BY e.exam_date DESC
    ";
    $stmt_assignments = mysqli_prepare($conn, $sql_assignments);
    mysqli_stmt_bind_param($stmt_assignments, "i", $student_id);
    mysqli_stmt_execute($stmt_assignments);
    $result_assignments = mysqli_stmt_get_result($stmt_assignments);
    while ($row = mysqli_fetch_assoc($result_assignments)) {
        $exam_assignments[] = $row;
    }
    mysqli_stmt_close($stmt_assignments);
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Exam Seating</title>
    <link rel="stylesheet" href="add_student.php">
    <style>
        .exam-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .exam-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; overflow: hidden; background-color: #f9fafb; }
        .exam-card-header { padding: 1rem; border-bottom: 1px solid #e5e7eb; }
        .exam-card-header h3 { margin: 0; }
        .exam-card-header small { color: #6b7280; }
        .exam-card-body { padding: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .exam-card-body dt { font-weight: 600; color: #4b5563; }
        .exam-card-body dd { margin: 0; font-size: 1.1rem; }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>My Exam Seating Arrangement</h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280;">Back to Dashboard</a>
    </div>
    
    <?php if ($_SESSION['role'] === 'guardian'): ?>
        <h2 style="font-size: 1.2rem; font-weight: 500; margin-top: 1rem;">Showing arrangements for: <?php echo htmlspecialchars($student_name); ?></h2>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="message error"><?php echo $error_message; ?></div>
    <?php elseif (empty($exam_assignments)): ?>
        <div class="message">No exam seating arrangements have been published for you yet.</div>
    <?php else: ?>
        <div class="exam-grid">
            <?php foreach ($exam_assignments as $assignment): ?>
            <div class="exam-card">
                <div class="exam-card-header">
                    <h3><?php echo htmlspecialchars($assignment['exam_name']); ?></h3>
                    <small>Date: <?php echo date('F j, Y', strtotime($assignment['exam_date'])); ?></small>
                </div>
                <dl class="exam-card-body">
                    <div>
                        <dt>Exam Room</dt>
                        <dd><?php echo htmlspecialchars($assignment['room_name']); ?></dd>
                    </div>
                    <div>
                        <dt>Seat Number</dt>
                        <dd><?php echo htmlspecialchars($assignment['seat_number']); ?></dd>
                    </div>
                </dl>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>