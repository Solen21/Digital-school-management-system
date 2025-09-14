<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once __DIR__ . '/functions.php';

$teacher_id = $_GET['id'] ?? null;
$page_title = 'Edit Teacher';

if (!$teacher_id || !is_numeric($teacher_id)) {
    die("<h1>Invalid Request</h1><p>No teacher ID provided. <a href='manage_teachers.php'>Return to Teacher List</a></p>");
}

// --- Handle POST request to update teacher ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = '';
    $message_type = 'danger';
    $teacher_id = $_POST['teacher_id']; // Get ID from hidden form field
    try {
        // --- Secure File Upload Handling ---
        $upload_dir = 'uploads/teachers/';
        $photo_path = process_file_update('photo_path', $_POST['existing_photo_path'], $upload_dir.'photos/', ['image/jpeg', 'image/png', 'image/gif'], 5 * 1024 * 1024);
        $document_path = process_file_update('document_path', $_POST['existing_document_path'], $upload_dir.'documents/', ['image/jpeg', 'image/png', 'application/pdf'], 10 * 1024 * 1024);

    // --- Update Database Record ---
    $sql = "UPDATE teachers SET 
                first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, 
                gender = ?, nationality = ?, religion = ?, city = ?, wereda = ?, kebele = ?, 
                phone = ?, email = ?, hire_date = ?, photo_path = ?, document_path = ?, status = ?
            WHERE teacher_id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssssssssssssssssi",
        $_POST['first_name'],
        $_POST['middle_name'],
        $_POST['last_name'],
        $_POST['date_of_birth'],
        $_POST['gender'],
        $_POST['nationality'],
        $_POST['religion'],
        $_POST['city'],
        $_POST['wereda'],
        $_POST['kebele'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['hire_date'],
        $photo_path,
        $document_path,
        $_POST['status'],
        $teacher_id
    );

    if (mysqli_stmt_execute($stmt)) {
        $message = "Teacher information updated successfully.";
        $teacher_name = $_POST['first_name'] . ' ' . $_POST['last_name'];
        log_activity($conn, 'edit_teacher', $teacher_id, $teacher_name);

        $message_type = 'success';
    } else {
        throw new Exception("Database error: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header("Location: edit_teacher.php?id=" . $teacher_id);
    exit();
}

// --- Fetch teacher data for the form ---
$teacher = null;
$sql_fetch = "SELECT * FROM teachers WHERE teacher_id = ?";
$stmt_fetch = mysqli_prepare($conn, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, "i", $teacher_id);
mysqli_stmt_execute($stmt_fetch);
$result = mysqli_stmt_get_result($stmt_fetch);
if ($row = mysqli_fetch_assoc($result)) {
    $teacher = $row;
} else {
    die("<h1>Error</h1><p>Teacher with ID {$teacher_id} not found. <a href='manage_teachers.php'>Return to Teacher List</a></p>");
}
mysqli_stmt_close($stmt_fetch);
mysqli_close($conn);

function e_teacher($data, $key, $default = '') {
    return htmlspecialchars($data[$key] ?? $default);
}
?>
<?php include 'header.php'; ?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Edit Teacher: <?php echo e_teacher($teacher, 'first_name') . ' ' . e_teacher($teacher, 'last_name'); ?></h1>
        <a href="manage_teachers.php" class="btn btn-secondary">Back to Teacher List</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

    <form action="edit_teacher.php?id=<?php echo $teacher_id; ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
        <input type="hidden" name="existing_photo_path" value="<?php echo e_teacher($teacher, 'photo_path'); ?>">
        <input type="hidden" name="existing_document_path" value="<?php echo e_teacher($teacher, 'document_path'); ?>">

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Personal & Contact Details</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label for="first_name" class="form-label">First Name</label><input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e_teacher($teacher, 'first_name'); ?>" required></div>
                    <div class="col-md-4"><label for="middle_name" class="form-label">Middle Name</label><input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo e_teacher($teacher, 'middle_name'); ?>" required></div>
                    <div class="col-md-4"><label for="last_name" class="form-label">Last Name</label><input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e_teacher($teacher, 'last_name'); ?>" required></div>
                    <div class="col-md-4"><label for="date_of_birth" class="form-label">Date of Birth</label><input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo e_teacher($teacher, 'date_of_birth'); ?>" required></div>
                    <div class="col-md-4"><label for="gender" class="form-label">Gender</label><select class="form-select" id="gender" name="gender" required><option value="male" <?php if(e_teacher($teacher, 'gender') == 'male') echo 'selected'; ?>>Male</option><option value="female" <?php if(e_teacher($teacher, 'gender') == 'female') echo 'selected'; ?>>Female</option></select></div>
                    <div class="col-md-4"><label for="phone" class="form-label">Phone</label><input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e_teacher($teacher, 'phone'); ?>" required></div>
                    <div class="col-md-4"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" value="<?php echo e_teacher($teacher, 'email'); ?>"></div>
                    <div class="col-md-4"><label for="nationality" class="form-label">Nationality</label><input type="text" class="form-control" id="nationality" name="nationality" value="<?php echo e_teacher($teacher, 'nationality'); ?>" required></div>
                    <div class="col-md-4"><label for="religion" class="form-label">Religion</label><input type="text" class="form-control" id="religion" name="religion" value="<?php echo e_teacher($teacher, 'religion'); ?>" required></div>
                    <div class="col-md-4"><label for="city" class="form-label">City</label><input type="text" class="form-control" id="city" name="city" value="<?php echo e_teacher($teacher, 'city'); ?>" required></div>
                    <div class="col-md-4"><label for="wereda" class="form-label">Wereda</label><input type="text" class="form-control" id="wereda" name="wereda" value="<?php echo e_teacher($teacher, 'wereda'); ?>" required></div>
                    <div class="col-md-4"><label for="kebele" class="form-label">Kebele</label><input type="text" class="form-control" id="kebele" name="kebele" value="<?php echo e_teacher($teacher, 'kebele'); ?>" required></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Employment & Status</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label for="hire_date" class="form-label">Hire Date</label><input type="date" class="form-control" id="hire_date" name="hire_date" value="<?php echo e_teacher($teacher, 'hire_date'); ?>" required></div>
                    <div class="col-md-6"><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status" required><option value="active" <?php if(e_teacher($teacher, 'status') == 'active') echo 'selected'; ?>>Active</option><option value="inactive" <?php if(e_teacher($teacher, 'status') == 'inactive') echo 'selected'; ?>>Inactive</option></select></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Profile Picture & Documents</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="photo_path" class="form-label">Teacher Photo (leave blank to keep existing)</label>
                    <input class="form-control" type="file" id="photo_path" name="photo_path" accept="image/*">
                    <?php if (!empty($teacher['photo_path']) && file_exists($teacher['photo_path'])): ?>
                        <div class="form-text mt-2">Current: <a href="<?php echo e_teacher($teacher, 'photo_path'); ?>" target="_blank">View Photo</a></div>
                    <?php else: ?>
                        <div class="form-text mt-2">No photo uploaded.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="document_path" class="form-label">Document (leave blank to keep existing)</label>
                    <input class="form-control" type="file" id="document_path" name="document_path" accept="image/*,application/pdf">
                     <?php if (!empty($teacher['document_path']) && file_exists($teacher['document_path'])): ?>
                        <div class="form-text mt-2">Current: <a href="<?php echo e_teacher($teacher, 'document_path'); ?>" target="_blank">View Document</a></div>
                    <?php else: ?>
                        <div class="form-text mt-2">No document uploaded.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle-fill"></i> Update Teacher</button>
        </div>
    </form>
</div>

<script>
// Bootstrap form validation
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script>

<?php include 'footer.php'; ?>