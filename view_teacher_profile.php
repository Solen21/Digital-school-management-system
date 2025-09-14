<?php
session_start();

// 1. Check if the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 2. Check if the user has an appropriate role (admin or director).
if (!in_array($_SESSION['role'], ['admin', 'director'])) {
    die("<h1>Access Denied</h1><p>You do not have permission to view this page. <a href='dashboard.php'>Return to Dashboard</a></p>");
}

// 3. Check if teacher ID is provided.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<h1>Invalid Request</h1><p>No teacher ID provided. <a href='manage_teachers.php'>Return to Teacher List</a></p>");
}

$teacher_id = $_GET['id'];
$teacher = null;
$error_message = '';

require_once 'data/db_connect.php';

// 4. Fetch teacher data from the database.
$sql = "SELECT t.*, u.username 
        FROM teachers t 
        JOIN users u ON t.user_id = u.user_id 
        WHERE t.teacher_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $teacher_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $teacher = $row;
} else {
    $error_message = "Teacher not found.";
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Profile</title>
    <link rel="stylesheet" href="add_student.php"> <!-- Reusing styles -->
    <style>
        .profile-container { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; margin-top: 1.5rem; }
        .profile-photo-container { text-align: center; }
        .profile-photo { width: 100%; max-width: 220px; height: auto; border-radius: 0.5rem; border: 3px solid #e5e7eb; margin-bottom: 1rem; }
        .profile-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem 1.5rem; background-color: #f9fafb; padding: 1.5rem; border-radius: 0.5rem; }
        .profile-details .detail-item { padding-bottom: 0.5rem; }
        .profile-details .detail-item strong { display: block; color: #4b5563; font-size: 0.875rem; margin-bottom: 0.25rem; }
        .profile-photo-container .btn { display: block; margin-top: 1rem; }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Teacher Profile</h1>
        <a href="manage_teachers.php" class="btn" style="background-color: #6b7280;">Back to Teacher List</a>
    </div>

    <?php if ($error_message): ?>
        <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif ($teacher): ?>
        <div class="profile-container">
            <div class="profile-photo-container">
                <?php if (!empty($teacher['photo_path']) && file_exists($teacher['photo_path'])): ?>
                    <img src="<?php echo htmlspecialchars($teacher['photo_path']); ?>" alt="Teacher Photo" class="profile-photo">
                <?php else: ?>
                    <img src="assets/default-avatar.png" alt="Default Photo" class="profile-photo"> <!-- Note: Make sure this default image exists -->
                <?php endif; ?>
                
                <?php if (!empty($teacher['document_path']) && file_exists($teacher['document_path'])): ?>
                    <a href="<?php echo htmlspecialchars($teacher['document_path']); ?>" class="btn" target="_blank">View Document</a>
                <?php else: ?>
                    <p style="text-align: center; margin-top: 1rem;">No document uploaded.</p>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <div class="detail-item">
                    <strong>Full Name</strong>
                    <span><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['middle_name'] . ' ' . $teacher['last_name']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Username</strong>
                    <span><?php echo htmlspecialchars($teacher['username']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Gender</strong>
                    <span><?php echo htmlspecialchars(ucfirst($teacher['gender'])); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Date of Birth</strong>
                    <span><?php echo htmlspecialchars($teacher['date_of_birth']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Phone</strong>
                    <span><?php echo htmlspecialchars($teacher['phone']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Email</strong>
                    <span><?php echo htmlspecialchars($teacher['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Hire Date</strong>
                    <span><?php echo htmlspecialchars($teacher['hire_date']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Status</strong>
                    <span style="color: <?php echo $teacher['status'] === 'active' ? 'green' : 'red'; ?>;"><?php echo htmlspecialchars(ucfirst($teacher['status'])); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>