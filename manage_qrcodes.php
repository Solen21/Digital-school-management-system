<?php
session_start();

// 1. Security Check: User must be logged in and be an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    die("<h1>Access Denied</h1><p>You do not have permission to view this page. <a href='dashboard.php'>Return to Dashboard</a></p>");
}

$qr_dir = 'uploads/qrcodes/';
$message = '';
$message_type = '';

// 2. Handle Deletion Requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_qr'])) {
        $qr_file_to_delete = $_POST['qr_file'];
        // Security: Ensure the file is within the intended directory and is a PNG.
        if (basename($qr_file_to_delete) == $qr_file_to_delete && substr($qr_file_to_delete, -4) === '.png') {
            $file_path = $qr_dir . $qr_file_to_delete;
            if (file_exists($file_path)) {
                if (unlink($file_path)) {
                    $message = "QR Code '" . htmlspecialchars($qr_file_to_delete) . "' deleted successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error deleting QR Code '" . htmlspecialchars($qr_file_to_delete) . "'.";
                    $message_type = 'error';
                }
            } else {
                $message = "QR Code file not found.";
                $message_type = 'error';
            }
        } else {
            $message = "Invalid file name.";
            $message_type = 'error';
        }
    } elseif (isset($_POST['delete_all'])) {
        $files = glob($qr_dir . '*.png');
        $deleted_count = 0;
        $error_count = 0;
        foreach ($files as $file) {
            if (is_file($file)) {
                if (unlink($file)) {
                    $deleted_count++;
                } else {
                    $error_count++;
                }
            }
        }
        $message = "Deleted $deleted_count QR codes.";
        if ($error_count > 0) {
            $message .= " Failed to delete $error_count files.";
            $message_type = 'error';
        } else {
            $message_type = 'success';
        }
    }
}

// 3. Scan for existing QR codes
$qr_files = glob($qr_dir . 'student_*.png');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage QR Codes</title>
    <link rel="stylesheet" href="add_student.php">
    <style>
        .qr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .qr-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; text-align: center; background-color: #f9fafb; }
        .qr-card img { width: 100px; height: 100px; margin-bottom: 0.5rem; }
        .qr-card p { margin: 0.25rem 0; font-size: 0.875rem; }
        .qr-card .delete-btn { background: none; border: none; color: #ef4444; cursor: pointer; text-decoration: underline; padding: 0.25rem; }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Manage Generated QR Codes</h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280;">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
        <form action="manage_qrcodes.php" method="POST" onsubmit="return confirm('Are you sure you want to delete ALL QR codes? This action cannot be undone.');">
            <button type="submit" name="delete_all" class="btn" style="background-color: #dc2626;">Delete All QR Codes</button>
        </form>
    </div>

    <div class="qr-grid">
        <?php $qr_files = glob($qr_dir . 'user_*.png'); if (empty($qr_files)): ?>
            <p>No QR codes found in the system.</p>
        <?php else: ?>
            <?php foreach ($qr_files as $file):
                $filename = basename($file);
                $user_id = str_replace(['user_', '.png'], '', $filename);
            ?>
            <div class="qr-card">
                <a href="view_profile.php?user_id=<?php echo htmlspecialchars($user_id); ?>" target="_blank"><img src="<?php echo htmlspecialchars($file); ?>" alt="QR Code for User <?php echo htmlspecialchars($user_id); ?>"></a>
                <p>User ID: <strong><?php echo htmlspecialchars($user_id); ?></strong></p>
                <form action="manage_qrcodes.php" method="POST" onsubmit="return confirm('Are you sure?');">
                    <input type="hidden" name="qr_file" value="<?php echo htmlspecialchars($filename); ?>">
                    <button type="submit" name="delete_qr" class="delete-btn">Delete</button>
                </form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>