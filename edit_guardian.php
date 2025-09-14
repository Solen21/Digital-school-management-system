<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$guardian_id = $_GET['id'] ?? null;

if (!$guardian_id || !is_numeric($guardian_id)) {
    die("<h1>Invalid Request</h1><p>No guardian ID provided. <a href='manage_guardians.php'>Return to Guardian List</a></p>");
}

// --- Handle POST request to update guardian ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = '';
    $message_type = 'danger';
    $guardian_id = $_POST['guardian_id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    if (empty($name) || empty($phone)) {
        $message = "Guardian name and phone number are required.";
        $message_type = 'danger';
    } else {
        $sql = "UPDATE guardians SET name = ?, phone = ?, email = ? WHERE guardian_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $name, $phone, $email, $guardian_id);

        if (mysqli_stmt_execute($stmt)) {
            $message = "Guardian information updated successfully. <a href='manage_guardians.php'>Return to list.</a>";
            $message_type = 'success'; // This will be a Bootstrap class
        } else {
            $message = "Error updating record: " . mysqli_stmt_error($stmt);
            $message_type = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header("Location: edit_guardian.php?id=" . $guardian_id);
    exit();
}

// --- Fetch guardian data for the form ---
$guardian = null;
$sql_fetch = "
    SELECT g.*, u.username 
    FROM guardians g 
    LEFT JOIN users u ON g.user_id = u.user_id
    WHERE g.guardian_id = ?
";
$stmt_fetch = mysqli_prepare($conn, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, "i", $guardian_id);
mysqli_stmt_execute($stmt_fetch);
$result = mysqli_stmt_get_result($stmt_fetch);
if ($row = mysqli_fetch_assoc($result)) {
    $guardian = $row;
} else {
    die("<h1>Error</h1><p>Guardian with ID {$guardian_id} not found. <a href='manage_guardians.php'>Return to Guardian List</a></p>");
}
mysqli_stmt_close($stmt_fetch);
mysqli_close($conn);

function e_guardian($data, $key, $default = '') {
    return htmlspecialchars($data[$key] ?? $default);
}
$page_title = 'Edit Guardian';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Edit Guardian: <?php echo e_guardian($guardian, 'name'); ?></h1>
        <a href="manage_guardians.php" class="btn btn-secondary">Back to Guardians</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Guardian Details</h5>
            <?php if (isset($guardian['username'])): ?>
                <span class="badge bg-info text-dark">Username: <?php echo e_guardian($guardian, 'username'); ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form action="edit_guardian.php?id=<?php echo $guardian_id; ?>" method="POST">
                <input type="hidden" name="guardian_id" value="<?php echo $guardian_id; ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" value="<?php echo e_guardian($guardian, 'name'); ?>" required>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e_guardian($guardian, 'phone'); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email (Optional)</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo e_guardian($guardian, 'email'); ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle-fill me-2"></i>Update Details</button>
                <?php if (isset($guardian['user_id'])): ?>
                <a href="admin_reset_guardian_password.php?user_id=<?php echo $guardian['user_id']; ?>" class="btn btn-warning" onclick="return confirm('Are you sure you want to reset the password for this guardian? A new temporary password will be generated.');"><i class="bi bi-key-fill me-2"></i>Reset Password</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>