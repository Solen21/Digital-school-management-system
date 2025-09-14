<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once __DIR__ . '/functions.php';

$student_id = $_GET['id'] ?? null;

if (!$student_id || !is_numeric($student_id)) {
    die("<h1>Invalid Request</h1><p>No student ID provided. <a href='manage_students.php'>Return to Student List</a></p>");
}

// --- Handle POST request to update student ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = '';
    $message_type = 'danger';
    try {
        $student_id = $_POST['student_id']; // Get ID from hidden form field

        // --- Fetch old data for logging ---
        $sql_old_data = "SELECT * FROM students WHERE student_id = ?";
        $stmt_old = mysqli_prepare($conn, $sql_old_data);
        mysqli_stmt_bind_param($stmt_old, "i", $student_id);
        mysqli_stmt_execute($stmt_old);
        $old_student_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_old));
        mysqli_stmt_close($stmt_old);

        // --- Secure File Upload Handling ---
        $upload_dir = 'uploads/students/';
        $photo_path = process_file_update('photo_path', $_POST['existing_photo_path'], $upload_dir.'photos/', ['image/jpeg', 'image/png', 'image/gif'], 5 * 1024 * 1024);
        $document_path = process_file_update('document_path', $_POST['existing_document_path'], $upload_dir.'documents/', ['image/jpeg', 'image/png', 'application/pdf'], 10 * 1024 * 1024);

        // --- Update Database Record ---
        $sql = "UPDATE students SET 
                    first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, age = ?,
                    gender = ?, nationality = ?, religion = ?, city = ?, wereda = ?, kebele = ?, 
                    phone = ?, email = ?, emergency_contact = ?, blood_type = ?, grade_level = ?, stream = ?,
                    last_school = ?, last_score = ?, last_grade = ?, photo_path = ?, document_path = ?, status = ?
                WHERE student_id = ?";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssisssssssissssssssssi",
            $_POST['first_name'], $_POST['middle_name'], $_POST['last_name'], $_POST['date_of_birth'], $_POST['age'],
            $_POST['gender'], $_POST['nationality'], $_POST['religion'], $_POST['city'], $_POST['wereda'], $_POST['kebele'],
            $_POST['phone'], $_POST['email'], $_POST['emergency_contact'], $_POST['blood_type'], $_POST['grade_level'], $_POST['stream'],
            $_POST['last_school'], $_POST['last_score'], $_POST['last_grade'], $photo_path, $document_path, $_POST['status'],
            $student_id
        );

        if (mysqli_stmt_execute($stmt)) {
            $message = "Student information updated successfully.";
            $message_type = 'success';

            $student_name = $_POST['first_name'] . ' ' . $_POST['last_name'];
            log_activity($conn, 'edit_student', $student_id, $student_name);

            // --- LOG CHANGES ---
            $new_student_data = $_POST;
            $new_student_data['photo_path'] = $photo_path;
            $new_student_data['document_path'] = $document_path;
            
            $log_stmt = mysqli_prepare($conn, "INSERT INTO student_profile_logs (student_id, changed_by_user_id, field_changed, old_value, new_value) VALUES (?, ?, ?, ?, ?)");
            $changed_by = $_SESSION['user_id'];
            
            // Define which fields to log to avoid logging sensitive or irrelevant data
            $loggable_fields = ['first_name', 'middle_name', 'last_name', 'date_of_birth', 'gender', 'phone', 'email', 'emergency_contact', 'grade_level', 'stream', 'status'];

            foreach ($loggable_fields as $field) {
                if (isset($new_student_data[$field]) && isset($old_student_data[$field]) && $new_student_data[$field] != $old_student_data[$field]) {
                    mysqli_stmt_bind_param($log_stmt, "iisss", $student_id, $changed_by, $field, $old_student_data[$field], $new_student_data[$field]);
                    mysqli_stmt_execute($log_stmt);
                }
            }
            mysqli_stmt_close($log_stmt);
            // --- END LOG CHANGES ---
        } else {
            throw new Exception("Database error: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $message_type;
    header("Location: edit_student.php?id=" . $student_id);
    exit();
}

// --- Fetch student data for the form ---
$student = null;
$sql_fetch = "SELECT * FROM students WHERE student_id = ?";
$stmt_fetch = mysqli_prepare($conn, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, "i", $student_id);
mysqli_stmt_execute($stmt_fetch);
$result = mysqli_stmt_get_result($stmt_fetch);
if ($row = mysqli_fetch_assoc($result)) {
    $student = $row;
} else {
    die("<h1>Error</h1><p>Student with ID {$student_id} not found. <a href='manage_students.php'>Return to Student List</a></p>");
}
mysqli_stmt_close($stmt_fetch);

// --- Fetch student profile change logs ---
$profile_logs = [];
$sql_logs = "
    SELECT spl.*, u.username as changed_by_username
    FROM student_profile_logs spl
    JOIN users u ON spl.changed_by_user_id = u.user_id
    WHERE spl.student_id = ?
    ORDER BY spl.change_timestamp DESC
";
$stmt_logs = mysqli_prepare($conn, $sql_logs);
mysqli_stmt_bind_param($stmt_logs, "i", $student_id);
mysqli_stmt_execute($stmt_logs);
$result_logs = mysqli_stmt_get_result($stmt_logs);
while ($log_row = mysqli_fetch_assoc($result_logs)) { $profile_logs[] = $log_row; }
mysqli_stmt_close($stmt_logs);
mysqli_close($conn);

function e_student($data, $key, $default = '') {
    return htmlspecialchars($data[$key] ?? $default);
}
$page_title = 'Edit Student';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Edit Student: <?php echo e_student($student, 'first_name') . ' ' . e_student($student, 'last_name'); ?></h1>
        <a href="manage_students.php" class="btn btn-secondary">Back to Student List</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

    <form action="edit_student.php?id=<?php echo $student_id; ?>" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
        <input type="hidden" name="existing_photo_path" value="<?php echo e_student($student, 'photo_path'); ?>">
        <input type="hidden" name="existing_document_path" value="<?php echo e_student($student, 'document_path'); ?>">

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Personal Details</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label for="first_name" class="form-label">First Name</label><input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo e_student($student, 'first_name'); ?>" required></div>
                    <div class="col-md-4"><label for="middle_name" class="form-label">Middle Name</label><input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo e_student($student, 'middle_name'); ?>" required></div>
                    <div class="col-md-4"><label for="last_name" class="form-label">Last Name</label><input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo e_student($student, 'last_name'); ?>" required></div>
                    <div class="col-md-4"><label for="date_of_birth" class="form-label">Date of Birth</label><input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo e_student($student, 'date_of_birth'); ?>" required></div>
                    <div class="col-md-4"><label for="age" class="form-label">Age</label><input type="number" class="form-control" id="age" name="age" value="<?php echo e_student($student, 'age'); ?>" readonly required></div>
                    <div class="col-md-4"><label for="gender" class="form-label">Gender</label><select class="form-select" id="gender" name="gender" required><option value="male" <?php if(e_student($student, 'gender') == 'male') echo 'selected'; ?>>Male</option><option value="female" <?php if(e_student($student, 'gender') == 'female') echo 'selected'; ?>>Female</option></select></div>
                    <div class="col-md-4"><label for="nationality" class="form-label">Nationality</label><input type="text" class="form-control" id="nationality" name="nationality" value="<?php echo e_student($student, 'nationality'); ?>" required></div>
                    <div class="col-md-4"><label for="religion" class="form-label">Religion</label><input type="text" class="form-control" id="religion" name="religion" value="<?php echo e_student($student, 'religion'); ?>" required></div>
                    <div class="col-md-4"><label for="blood_type" class="form-label">Blood Type</label><select class="form-select" id="blood_type" name="blood_type"><option value="" <?php if(e_student($student, 'blood_type') == '') echo 'selected'; ?>>Unknown</option><option value="A+" <?php if(e_student($student, 'blood_type') == 'A+') echo 'selected'; ?>>A+</option><option value="A-" <?php if(e_student($student, 'blood_type') == 'A-') echo 'selected'; ?>>A-</option><option value="B+" <?php if(e_student($student, 'blood_type') == 'B+') echo 'selected'; ?>>B+</option><option value="B-" <?php if(e_student($student, 'blood_type') == 'B-') echo 'selected'; ?>>B-</option><option value="AB+" <?php if(e_student($student, 'blood_type') == 'AB+') echo 'selected'; ?>>AB+</option><option value="AB-" <?php if(e_student($student, 'blood_type') == 'AB-') echo 'selected'; ?>>AB-</option><option value="O+" <?php if(e_student($student, 'blood_type') == 'O+') echo 'selected'; ?>>O+</option><option value="O-" <?php if(e_student($student, 'blood_type') == 'O-') echo 'selected'; ?>>O-</option></select></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Contact & Address</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label for="phone" class="form-label">Phone</label><input type="tel" class="form-control" id="phone" name="phone" value="<?php echo e_student($student, 'phone'); ?>" required></div>
                    <div class="col-md-4"><label for="email" class="form-label">Email</label><input type="email" class="form-control" id="email" name="email" value="<?php echo e_student($student, 'email'); ?>"></div>
                    <div class="col-md-4"><label for="emergency_contact" class="form-label">Emergency Contact</label><input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" value="<?php echo e_student($student, 'emergency_contact'); ?>" required></div>
                    <div class="col-md-4"><label for="city" class="form-label">City</label><input type="text" class="form-control" id="city" name="city" value="<?php echo e_student($student, 'city'); ?>" required></div>
                    <div class="col-md-4"><label for="wereda" class="form-label">Wereda</label><input type="text" class="form-control" id="wereda" name="wereda" value="<?php echo e_student($student, 'wereda'); ?>" required></div>
                    <div class="col-md-4"><label for="kebele" class="form-label">Kebele</label><input type="text" class="form-control" id="kebele" name="kebele" value="<?php echo e_student($student, 'kebele'); ?>" required></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Academic Information</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label for="grade_level" class="form-label">Grade Level</label><select class="form-select" id="grade_level" name="grade_level" required><?php foreach (['9', '10', '11', '12'] as $grade): ?><option value="<?php echo $grade; ?>" <?php if(e_student($student, 'grade_level') == $grade) echo 'selected'; ?>><?php echo $grade; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label for="stream" class="form-label">Stream</label><select class="form-select" id="stream" name="stream" required><?php foreach (['Natural', 'Social', 'Both'] as $stream): ?><option value="<?php echo $stream; ?>" <?php if(e_student($student, 'stream') == $stream) echo 'selected'; ?>><?php echo $stream; ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><label for="status" class="form-label">Status</label><select class="form-select" id="status" name="status" required><option value="active" <?php if(e_student($student, 'status') == 'active') echo 'selected'; ?>>Active</option><option value="inactive" <?php if(e_student($student, 'status') == 'inactive') echo 'selected'; ?>>Inactive</option></select></div>
                    <div class="col-md-4"><label for="last_school" class="form-label">Last School Attended</label><input type="text" class="form-control" id="last_school" name="last_school" value="<?php echo e_student($student, 'last_school'); ?>" required></div>
                    <div class="col-md-4"><label for="last_grade" class="form-label">Last Grade Completed</label><input type="text" class="form-control" id="last_grade" name="last_grade" value="<?php echo e_student($student, 'last_grade'); ?>" readonly required></div>
                    <div class="col-md-4"><label for="last_score" class="form-label">Last Score/Average</label><input type="number" step="0.01" class="form-control" id="last_score" name="last_score" value="<?php echo e_student($student, 'last_score'); ?>" required></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Profile Picture & Documents</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="photo_path" class="form-label">Student Photo (leave blank to keep existing)</label>
                    <input class="form-control" type="file" id="photo_path" name="photo_path" accept="image/*">
                    <?php if (!empty($student['photo_path']) && file_exists($student['photo_path'])): ?>
                        <div class="form-text mt-2">Current: <a href="<?php echo e_student($student, 'photo_path'); ?>" target="_blank">View Photo</a></div>
                    <?php else: ?>
                        <div class="form-text mt-2">No photo uploaded.</div>
                    <?php endif; ?>
                </div>
                <div class="mb-3">
                    <label for="document_path" class="form-label">Document (leave blank to keep existing)</label>
                    <input class="form-control" type="file" id="document_path" name="document_path" accept="image/*,application/pdf">
                     <?php if (!empty($student['document_path']) && file_exists($student['document_path'])): ?>
                        <div class="form-text mt-2">Current: <a href="<?php echo e_student($student, 'document_path'); ?>" target="_blank">View Document</a></div>
                    <?php else: ?>
                        <div class="form-text mt-2">No document uploaded.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle-fill"></i> Update Student</button>
        </div>
    </form>

    <!-- Profile Change History -->
    <div class="card mt-5">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Profile Change History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date of Change</th>
                            <th>Field Changed</th>
                            <th>Old Value</th>
                            <th>New Value</th>
                            <th>Changed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($profile_logs)): ?>
                            <tr><td colspan="5" class="text-center text-muted">No changes have been logged for this student.</td></tr>
                        <?php else: ?>
                            <?php foreach ($profile_logs as $log): ?>
                                <tr>
                                    <td><?php echo date('M j, Y, g:i A', strtotime($log['change_timestamp'])); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['field_changed']))); ?></span></td>
                                    <td><?php echo htmlspecialchars($log['old_value']); ?></td>
                                    <td><?php echo htmlspecialchars($log['new_value']); ?></td>
                                    <td><?php echo htmlspecialchars($log['changed_by_username']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dobInput = document.getElementById('date_of_birth');
    const ageInput = document.getElementById('age');
    const gradeLevelInput = document.getElementById('grade_level');
    const lastGradeInput = document.getElementById('last_grade');

    function calculateAge() {
        if (dobInput.value) {
            const birthDate = new Date(dobInput.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            ageInput.value = age;
        }
    }

    function setLastGrade() {
        const grade = parseInt(gradeLevelInput.value, 10);
        if (!isNaN(grade)) {
            lastGradeInput.value = grade - 1;
        }
    }

    dobInput.addEventListener('change', calculateAge);
    gradeLevelInput.addEventListener('change', setLastGrade);

    // Initial calculation on page load
    calculateAge();
    setLastGrade();

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
            </div>
            }
            form.classList.add('was-validated')
          }, false)
        })
    })()
});

<?php include 'footer.php'; ?>