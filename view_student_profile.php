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

// 3. Check if student ID is provided.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("<h1>Invalid Request</h1><p>No student ID provided. <a href='manage_students.php'>Return to Student List</a></p>");
}

$student_id = $_GET['id'];
$student = null;
$error_message = '';

require_once 'data/db_connect.php';

// 4. Fetch student data from the database.
$sql = "SELECT s.*, u.username, c.name as classroom_name
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
        LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
        WHERE s.student_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($result)) {
    $student = $row;
} else {
    $error_message = "Student not found.";
}

mysqli_stmt_close($stmt);

// 5. Fetch assigned guardians for this student
$guardians = [];
if ($student) {
    $sql_guardians = "SELECT g.name, g.phone, g.email, sgm.relation FROM guardians g JOIN student_guardian_map sgm ON g.guardian_id = sgm.guardian_id WHERE sgm.student_id = ?";
    $stmt_guardians = mysqli_prepare($conn, $sql_guardians);
    mysqli_stmt_bind_param($stmt_guardians, "i", $student_id);
    mysqli_stmt_execute($stmt_guardians);
    $result_guardians = mysqli_stmt_get_result($stmt_guardians);
    while ($row = mysqli_fetch_assoc($result_guardians)) {
        $guardians[] = $row;
    }
    mysqli_stmt_close($stmt_guardians);
}

// 6. Generate QR Code
$qr_code_path = null;
if ($student) {
    require_once('phpqrcode/qrlib.php');
    $qr_dir = 'uploads/qrcodes/';
    if (!is_dir($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }
    // Generate a URL to the student's profile
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain_name = $_SERVER['HTTP_HOST'];
    $qr_content = $protocol . $domain_name . dirname($_SERVER['PHP_SELF']) . '/view_student_profile.php?id=' . $student_id;
    $qr_code_path = $qr_dir . 'student_' . $student_id . '.png';
    QRcode::png($qr_content, $qr_code_path, QR_ECLEVEL_L, 4);
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link rel="stylesheet" href="add_student.php"> <!-- Reusing styles -->
    <style>
        .profile-container { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; margin-top: 1.5rem; }
        .profile-photo-container { text-align: center; }
        .profile-photo { width: 100%; max-width: 220px; height: auto; border-radius: 0.5rem; border: 3px solid #e5e7eb; margin-bottom: 1rem; }
        .profile-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem 1.5rem; background-color: #f9fafb; padding: 1.5rem; border-radius: 0.5rem; }
        .profile-details .detail-item { padding-bottom: 0.5rem; }
        .profile-details .detail-item strong { display: block; color: #4b5563; font-size: 0.875rem; margin-bottom: 0.25rem; }
        .section-header { font-size: 1.2rem; font-weight: 600; margin-top: 2.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background-color: #f9fafb; font-weight: 600; }
        .profile-photo-container .btn { display: block; margin-top: 1rem; }

        /* ID Card Styles */
        .id-card-section { margin-top: 2.5rem; }
        .id-card { width: 350px; border: 1px solid #ccc; border-radius: 10px; padding: 15px; font-family: sans-serif; background: #fff; box-shadow: 0 4px 8px rgba(0,0,0,0.1); margin-bottom: 1rem; }
        .id-card-header { text-align: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 10px; }
        .id-card-header h3 { margin: 0; font-size: 1.2rem; color: #333; }
        .id-card-header p { margin: 5px 0 0; color: #555; font-size: 0.9rem; }
        .id-card-body { display: flex; gap: 15px; }
        .id-card-photo { width: 100px; height: 120px; object-fit: cover; border: 2px solid #ddd; border-radius: 5px; }
        .id-card-details { font-size: 0.8rem; flex-grow: 1; }
        .id-card-details p { margin: 0 0 6px; }
        .id-card-details strong { display: inline-block; width: 80px; color: #555; }
        .id-card-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px; }
        .id-card-qr { width: 60px; height: 60px; }
        .id-card-dates { text-align: right; font-size: 0.7rem; color: #777; }
        .id-card-actions { margin-top: 1rem; }

        @media print {
            body * { visibility: hidden; }
            .id-card-section, .id-card-section * { visibility: visible; }
            .id-card-section { position: absolute; left: 0; top: 0; }
        }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Student Profile</h1>
        <a href="manage_students.php" class="btn" style="background-color: #6b7280;">Back to Student List</a>
    </div>

    <?php if ($error_message): ?>
        <div class="message error"><?php echo htmlspecialchars($error_message); ?></div>
    <?php elseif ($student): ?>
        <div class="profile-container">
            <div class="profile-photo-container">
                <?php if (!empty($student['photo_path']) && file_exists($student['photo_path'])): ?>
                    <img src="<?php echo htmlspecialchars($student['photo_path']); ?>" alt="Student Photo" class="profile-photo">
                <?php else: ?>
                    <img src="assets/default-avatar.png" alt="Default Photo" class="profile-photo"> <!-- Note: Make sure this default image exists -->
                <?php endif; ?>
                
                <?php if (!empty($student['document_path']) && file_exists($student['document_path'])): ?>
                    <a href="<?php echo htmlspecialchars($student['document_path']); ?>" class="btn" target="_blank">View Document</a>
                <?php else: ?>
                    <p style="text-align: center; margin-top: 1rem;">No document uploaded.</p>
                <?php endif; ?>
            </div>
            <div class="profile-details">
                <div class="detail-item">
                    <strong>Full Name</strong>
                    <span><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Username</strong>
                    <span><?php echo htmlspecialchars($student['username']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Grade Level</strong>
                    <span><?php echo htmlspecialchars($student['grade_level']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Stream</strong>
                    <span><?php echo htmlspecialchars($student['stream']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Gender</strong>
                    <span><?php echo htmlspecialchars(ucfirst($student['gender'])); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Blood Type</strong>
                    <span><?php echo htmlspecialchars($student['blood_type'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Date of Birth (Age)</strong>
                    <span><?php echo htmlspecialchars($student['date_of_birth']); ?> (<?php echo htmlspecialchars($student['age']); ?>)</span>
                </div>
                <div class="detail-item">
                    <strong>Phone</strong>
                    <span><?php echo htmlspecialchars($student['phone']); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Email</strong>
                    <span><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <strong>Status</strong>
                    <span style="color: <?php echo $student['status'] === 'active' ? 'green' : 'red'; ?>;"><?php echo htmlspecialchars(ucfirst($student['status'])); ?></span>
                </div>
            </div>
        </div>

        <div class="section-header">
            Assigned Guardians
        </div>
        <?php if (empty($guardians)): ?>
            <div class="message">No guardians are linked to this student. <a href="manage_guardian_links.php?id=<?php echo $student_id; ?>">Link a guardian now.</a></div>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Name</th><th>Relation</th><th>Phone</th><th>Email</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($guardians as $guardian): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($guardian['name']); ?></td>
                        <td><?php echo htmlspecialchars($guardian['relation']); ?></td>
                        <td><?php echo htmlspecialchars($guardian['phone']); ?></td>
                        <td><?php echo htmlspecialchars($guardian['email'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ID Card Section -->
    <?php if ($student): ?>
    <div class="id-card-section">
        <h2 class="section-header">Student ID Card</h2>
        <div class="id-card" id="student-id-card">
            <div class="id-card-header">
                <h3>Old Model School</h3>
                <p>Student Identification Card</p>
            </div>
            <div class="id-card-body">
                <?php if (!empty($student['photo_path']) && file_exists($student['photo_path'])): ?>
                    <img src="<?php echo htmlspecialchars($student['photo_path']); ?>" alt="Student Photo" class="id-card-photo">
                <?php else: ?>
                    <img src="assets/default-avatar.png" alt="Default Photo" class="id-card-photo">
                <?php endif; ?>
                <div class="id-card-details">
                    <p><strong>ID:</strong> <?php echo htmlspecialchars($student['username']); ?></p>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($student['first_name'].' '.$student['last_name']); ?></p>
                    <p><strong>Grade:</strong> <?php echo htmlspecialchars($student['grade_level']); ?> (<?php echo htmlspecialchars($student['classroom_name'] ?? 'N/A'); ?>)</p>
                    <p><strong>Sex:</strong> <?php echo htmlspecialchars(ucfirst($student['gender'])); ?></p>
                    <p><strong>Age:</strong> <?php echo htmlspecialchars($student['age']); ?></p>
                    <p><strong>Blood:</strong> <?php echo htmlspecialchars($student['blood_type'] ?? 'N/A'); ?></p>
                </div>
            </div>
            <div class="id-card-footer">
                <?php if ($qr_code_path && file_exists($qr_code_path)): ?>
                    <img src="<?php echo htmlspecialchars($qr_code_path); ?>" alt="QR Code" class="id-card-qr">
                <?php endif; ?>
                <div class="id-card-dates">
                    <?php
                        $issue_date = new DateTime($student['registered_at']);
                        $expiry_date = new DateTime($student['registered_at']);
                        $expiry_date->modify('+1 year');
                    ?>
                    <p>Issued: <?php echo $issue_date->format('Y-m-d'); ?></p>
                    <p>Expires: <?php echo $expiry_date->format('Y-m-d'); ?></p>
                </div>
            </div>
        </div>
        <div class="id-card-actions"><a href="generate_id_card_pdf.php?id=<?php echo $student_id; ?>" class="btn" target="_blank">Print ID Card</a></div>
    </div>
    <?php endif; ?>
</div>
</body>
</html>