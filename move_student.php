<?php
session_start();

// 1. Security Check: Ensure only admin/director can access
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$message = '';
$message_type = '';

$selected_student_id = $_GET['student_id'] ?? null;
$student_details = null;
$available_classrooms = [];

// --- Handle POST request to move the student ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['move_student'])) {
    $student_id_to_move = $_POST['student_id'];
    $new_classroom_id = $_POST['new_classroom_id'];
    $admin_user_id = $_SESSION['user_id'];

    if (empty($student_id_to_move) || empty($new_classroom_id)) {
        $message = "Student and new classroom must be selected.";
        $message_type = 'error';
    } else {
        // Check new classroom capacity
        $sql_capacity = "SELECT c.capacity, COUNT(ca.student_id) as student_count FROM classrooms c LEFT JOIN class_assignments ca ON c.classroom_id = ca.classroom_id WHERE c.classroom_id = ? GROUP BY c.classroom_id";
        $stmt_capacity = mysqli_prepare($conn, $sql_capacity);
        mysqli_stmt_bind_param($stmt_capacity, "i", $new_classroom_id);
        mysqli_stmt_execute($stmt_capacity);
        $capacity_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_capacity));
        mysqli_stmt_close($stmt_capacity);

        if ($capacity_result && $capacity_result['student_count'] >= $capacity_result['capacity']) {
            $message = "The selected classroom is already at full capacity.";
            $message_type = 'error';
        } else {
            mysqli_begin_transaction($conn);
            try {
                // 1. End the previous assignment history record (if it exists)
                $sql_end_history = "UPDATE class_assignment_history SET left_date = NOW() WHERE student_id = ? AND left_date IS NULL";
                $stmt_end = mysqli_prepare($conn, $sql_end_history);
                mysqli_stmt_bind_param($stmt_end, "i", $student_id_to_move);
                mysqli_stmt_execute($stmt_end);
                mysqli_stmt_close($stmt_end);

                // 2. Update or insert the current assignment
                $sql_upsert_current = "
                    INSERT INTO class_assignments (student_id, classroom_id) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE classroom_id = VALUES(classroom_id)
                ";
                $stmt_upsert = mysqli_prepare($conn, $sql_upsert_current);
                mysqli_stmt_bind_param($stmt_upsert, "ii", $student_id_to_move, $new_classroom_id);
                if (!mysqli_stmt_execute($stmt_upsert)) {
                    throw new Exception(mysqli_stmt_error($stmt_upsert));
                }
                mysqli_stmt_close($stmt_upsert);

                // 3. Create a new assignment history record
                $sql_add_history = "INSERT INTO class_assignment_history (student_id, classroom_id, assigned_date, assigned_by_user_id) VALUES (?, ?, NOW(), ?)";
                $stmt_add = mysqli_prepare($conn, $sql_add_history);
                mysqli_stmt_bind_param($stmt_add, "iii", $student_id_to_move, $new_classroom_id, $admin_user_id);
                if (!mysqli_stmt_execute($stmt_add)) {
                    throw new Exception(mysqli_stmt_error($stmt_add));
                }
                mysqli_stmt_close($stmt_add);

                mysqli_commit($conn);
                $message = "Student moved successfully.";
                $message_type = 'success';
                $selected_student_id = $student_id_to_move; // To reload the page with updated info
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Error moving student: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// --- Fetch data for display ---
$all_students = mysqli_query($conn, "SELECT student_id, first_name, last_name, grade_level FROM students ORDER BY grade_level, last_name, first_name");

if ($selected_student_id) {
    // Fetch details for the selected student
    $sql_student = "
        SELECT s.student_id, s.first_name, s.last_name, s.grade_level, c.name as classroom_name, c.classroom_id as current_classroom_id
        FROM students s
        LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
        LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
        WHERE s.student_id = ?
    ";
    $stmt_student = mysqli_prepare($conn, $sql_student);
    mysqli_stmt_bind_param($stmt_student, "i", $selected_student_id);
    mysqli_stmt_execute($stmt_student);
    $student_details = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_student));
    mysqli_stmt_close($stmt_student);

    if ($student_details) {
        // Fetch available classrooms for the same grade level that are not full
        $grade_level = $student_details['grade_level'];
        $current_classroom_id = $student_details['current_classroom_id'] ?? 0;

        $sql_classrooms = "
            SELECT c.classroom_id, c.name, c.capacity, COUNT(ca.student_id) as student_count
            FROM classrooms c
            LEFT JOIN class_assignments ca ON c.classroom_id = ca.classroom_id
            WHERE c.grade_level = ? AND c.classroom_id != ?
            GROUP BY c.classroom_id
            HAVING student_count < c.capacity
            ORDER BY c.name
        ";
        $stmt_classrooms = mysqli_prepare($conn, $sql_classrooms);
        mysqli_stmt_bind_param($stmt_classrooms, "si", $grade_level, $current_classroom_id);
        mysqli_stmt_execute($stmt_classrooms);
        $result_classrooms = mysqli_stmt_get_result($stmt_classrooms);
        while ($row = mysqli_fetch_assoc($result_classrooms)) {
            $available_classrooms[] = $row;
        }
        mysqli_stmt_close($stmt_classrooms);
    }
}

mysqli_close($conn);
$page_title = 'Move Student';
include 'header.php';
?>

<div class="container" style="max-width: 800px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Move Student</h1>
        <a href="manage_assignments.php" class="btn btn-secondary">Back to Assignments</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Step 1: Student Selection -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Step 1: Select a Student</h5>
        </div>
        <div class="card-body">
            <form action="move_student.php" method="GET" id="student-select-form">
                <div class="mb-3">
                    <label for="student_id" class="form-label">Student</label>
                    <select name="student_id" id="student_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select a Student --</option>
                        <?php while($student = mysqli_fetch_assoc($all_students)): ?>
                            <option value="<?php echo $student['student_id']; ?>" <?php echo ($selected_student_id == $student['student_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name'] . ' (Grade ' . $student['grade_level'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Step 2: Classroom Selection (only shows if a student is selected) -->
    <?php if ($student_details): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Step 2: Choose New Classroom</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p class="mb-1"><strong>Moving Student:</strong> <?php echo htmlspecialchars($student_details['first_name'] . ' ' . $student_details['last_name']); ?></p>
                    <p class="mb-0"><strong>Current Classroom:</strong> <?php echo htmlspecialchars($student_details['classroom_name'] ?? 'Unassigned'); ?></p>
                </div>
                <form action="move_student.php?student_id=<?php echo $selected_student_id; ?>" method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
                    <div class="mb-3">
                        <label for="new_classroom_id" class="form-label">Move to Classroom</label>
                        <select name="new_classroom_id" id="new_classroom_id" class="form-select" required>
                            <option value="">-- Select New Classroom --</option>
                            <?php if (empty($available_classrooms)): ?>
                                <option value="" disabled>No other classrooms available for this grade level.</option>
                            <?php else: ?>
                                <?php foreach ($available_classrooms as $classroom): ?>
                                    <option value="<?php echo $classroom['classroom_id']; ?>"><?php echo htmlspecialchars($classroom['name'] . ' (' . $classroom['student_count'] . '/' . $classroom['capacity'] . ')'); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button type="submit" name="move_student" class="btn btn-primary" <?php if (empty($available_classrooms)) echo 'disabled'; ?>>
                        <i class="bi bi-arrows-move me-2"></i>Move Student
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>