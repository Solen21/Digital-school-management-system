<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

$message = '';
$message_type = '';

// --- Handle POST request to save assignments ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_assignments'])) {
    $classroom_id = $_POST['classroom_id'];
    $assignments = $_POST['assignments'] ?? []; // [subject_id => teacher_id]

    if (empty($classroom_id)) {
        $message = "Please select a classroom before saving.";
        $message_type = 'danger';
    } else {
        mysqli_begin_transaction($conn);
        try {
            // First, remove all existing assignments for this classroom
            $sql_delete = "DELETE FROM subject_assignments WHERE classroom_id = ?";
            $stmt_delete = mysqli_prepare($conn, $sql_delete);
            mysqli_stmt_bind_param($stmt_delete, "i", $classroom_id);
            mysqli_stmt_execute($stmt_delete);
            mysqli_stmt_close($stmt_delete);

            // Now, insert the new assignments where a teacher is selected
            $sql_insert = "INSERT INTO subject_assignments (subject_id, classroom_id, teacher_id) VALUES (?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            foreach ($assignments as $subject_id => $teacher_id) {
                if (!empty($teacher_id)) { // Only insert if a teacher was selected
                    mysqli_stmt_bind_param($stmt_insert, "iii", $subject_id, $classroom_id, $teacher_id);
                    mysqli_stmt_execute($stmt_insert);
                }
            }
            mysqli_stmt_close($stmt_insert);

            mysqli_commit($conn);
            $message = "Subject assignments updated successfully.";
            $message_type = 'success'; 
            log_activity($conn, 'update_subject_assignments', $classroom_id, "Updated subject assignments for classroom ID {$classroom_id}.");
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error updating assignments: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// --- Fetch data for display ---
$classrooms = mysqli_query($conn, "SELECT classroom_id, name FROM classrooms ORDER BY name ASC");
$subjects = mysqli_query($conn, "SELECT subject_id, name, code FROM subjects ORDER BY name ASC");
$teachers = mysqli_query($conn, "SELECT teacher_id, first_name, last_name FROM teachers ORDER BY last_name, first_name ASC");

$selected_classroom_id = $_GET['classroom_id'] ?? ($_POST['classroom_id'] ?? null);
$current_assignments = []; // [subject_id => teacher_id]

if ($selected_classroom_id) {
    // Get current assignments for the selected classroom
    $sql_current = "SELECT subject_id, teacher_id FROM subject_assignments WHERE classroom_id = ?";
    $stmt_current = mysqli_prepare($conn, $sql_current);
    mysqli_stmt_bind_param($stmt_current, "i", $selected_classroom_id);
    mysqli_stmt_execute($stmt_current);
    $result_current = mysqli_stmt_get_result($stmt_current);
    while ($row = mysqli_fetch_assoc($result_current)) {
        $current_assignments[$row['subject_id']] = $row['teacher_id'];
    }
    mysqli_stmt_close($stmt_current);
}

mysqli_close($conn);
$page_title = 'Assign Subjects to Teachers';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Assign Subjects to Teachers</h1>
        <a href="manage_assignments.php" class="btn btn-secondary">Back to Assignments</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Classroom Selection -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <form action="manage_subject_assignments.php" method="GET" id="classroom-select-form">
                <label for="classroom_id" class="form-label">Select a Classroom to Manage Assignments</label>
                <div class="input-group">
                    <select name="classroom_id" id="classroom_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select Classroom --</option>
                        <?php mysqli_data_seek($classrooms, 0); ?>
                        <?php while($classroom = mysqli_fetch_assoc($classrooms)): ?>
                            <option value="<?php echo $classroom['classroom_id']; ?>" <?php echo ($selected_classroom_id == $classroom['classroom_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($classroom['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button class="btn btn-primary" type="submit">Load</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_classroom_id): ?>
    <form action="manage_subject_assignments.php" method="POST">
        <input type="hidden" name="classroom_id" value="<?php echo $selected_classroom_id; ?>">
        <input type="hidden" name="save_assignments" value="1">

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Subject Assignments</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Subject</th>
                                <th>Assigned Teacher</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php mysqli_data_seek($subjects, 0); ?>
                            <?php while($subject = mysqli_fetch_assoc($subjects)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['name']); ?> <small class="text-muted">(<?php echo htmlspecialchars($subject['code']); ?>)</small></td>
                                <td>
                                    <select name="assignments[<?php echo $subject['subject_id']; ?>]" class="form-select">
                                        <option value="">-- Unassigned --</option>
                                        <?php mysqli_data_seek($teachers, 0); ?>
                                        <?php while($teacher = mysqli_fetch_assoc($teachers)): ?>
                                            <?php $is_selected = (isset($current_assignments[$subject['subject_id']]) && $current_assignments[$subject['subject_id']] == $teacher['teacher_id']); ?>
                                            <option value="<?php echo $teacher['teacher_id']; ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="text-end mt-4">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save-fill me-2"></i>Save Assignments</button>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>