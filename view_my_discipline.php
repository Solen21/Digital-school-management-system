<?php
session_start();

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$student_id = null;
$student_name = '';
$records = [];
$error_message = '';
$viewer_role = $_SESSION['role'];

// 2. Determine which student's data to fetch.
if (isset($_GET['student_id']) && $viewer_role === 'guardian') {
    // Guardian is viewing a specific child's data. Verify access.
    $child_student_id = $_GET['student_id'];
    $sql_verify = "SELECT s.student_id, s.first_name, s.last_name FROM student_guardian_map sgm JOIN guardians g ON sgm.guardian_id = g.guardian_id JOIN students s ON sgm.student_id = s.student_id WHERE g.user_id = ? AND sgm.student_id = ?";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $_SESSION['user_id'], $child_student_id);
    mysqli_stmt_execute($stmt_verify);
    if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify))) {
        $student_id = $row['student_id'];
        $student_name = $row['first_name'] . ' ' . $row['last_name'];
    } else {
        $error_message = "Access Denied. You are not authorized to view this student's records.";
    }
} elseif (in_array($viewer_role, ['student', 'rep'])) {
    // Student is viewing their own data.
    $sql_student = "SELECT student_id, first_name, last_name FROM students WHERE user_id = ?";
    $stmt_student = mysqli_prepare($conn, $sql_student);
    mysqli_stmt_bind_param($stmt_student, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt_student);
    if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_student))) {
        $student_id = $row['student_id'];
        $student_name = $row['first_name'] . ' ' . $row['last_name'];
    } else {
        $error_message = "Could not find a student profile associated with your account.";
    }
} else {
    $error_message = "Your role is not authorized to view this page.";
}

if ($student_id && empty($error_message)) {
    // 3. Fetch all discipline records for this student.
    $sql_records = "SELECT * FROM discipline_records WHERE student_id = ? ORDER BY incident_date DESC";
    $stmt_records = mysqli_prepare($conn, $sql_records);
    mysqli_stmt_bind_param($stmt_records, "i", $student_id);
    mysqli_stmt_execute($stmt_records);
    $result_records = mysqli_stmt_get_result($stmt_records);
    while ($row = mysqli_fetch_assoc($result_records)) {
        $records[] = $row;
    }
    mysqli_stmt_close($stmt_records);
}

mysqli_close($conn);
$page_title = 'My Discipline Records';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Discipline Records</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <?php if ($viewer_role === 'guardian'): ?>
        <h2 class="h5 text-muted mb-4">Showing records for: <?php echo htmlspecialchars($student_name); ?></h2>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php elseif (empty($records)): ?>
        <div class="alert alert-success">No discipline records found.</div>
    <?php else: ?>
        <?php foreach ($records as $record): ?>
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="mb-0"><?php echo htmlspecialchars($record['incident_type']); ?></h5>
                    <small class="text-muted"><?php echo date('F j, Y', strtotime($record['incident_date'])); ?></small>
                </div>
                <div class="card-body">
                    <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($record['description'])); ?></p>
                    <p class="mb-0"><strong>Action Taken:</strong> <?php echo htmlspecialchars($record['action_taken'] ?: 'N/A'); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>