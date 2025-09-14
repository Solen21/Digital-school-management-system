<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

$page_title = 'Auto-Distribute Students';
$distribution_preview = [];
$classroom_names = [];

// --- Handle POST request to distribute students ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['distribute_students'])) {
    $grade_level = $_POST['grade_level'];

    if (empty($grade_level)) {
        $message = "Please select a grade level.";
        $message_type = 'danger';
    } else {
        // 1. Get all unassigned students for the selected grade level, ordered by score
        $sql_students = "
            SELECT s.student_id, s.first_name, s.last_name, s.last_score
            FROM students s
            WHERE s.grade_level = ? AND s.student_id NOT IN (SELECT student_id FROM class_assignments)
            ORDER BY s.last_score DESC
        ";
        $stmt_students = mysqli_prepare($conn, $sql_students);
        mysqli_stmt_bind_param($stmt_students, "s", $grade_level);
        mysqli_stmt_execute($stmt_students);
        $result_students = mysqli_stmt_get_result($stmt_students);
        $unassigned_students = mysqli_fetch_all($result_students, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_students);

        // 2. Get available classrooms for this grade level (e.g., name contains 'Grade X')
        $classroom_pattern = "Grade " . $grade_level . "%";
        $sql_classrooms = "SELECT classroom_id, name, capacity FROM classrooms WHERE grade_level = ? ORDER BY name ASC";
        $stmt_classrooms = mysqli_prepare($conn, $sql_classrooms);
        mysqli_stmt_bind_param($stmt_classrooms, "s", $classroom_pattern);
        mysqli_stmt_execute($stmt_classrooms);
        $result_classrooms = mysqli_stmt_get_result($stmt_classrooms);
        $available_classrooms = mysqli_fetch_all($result_classrooms, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_classrooms);

        if (empty($unassigned_students)) {
            $message = "No unassigned students found for Grade $grade_level.";
            $message_type = 'warning';
        } elseif (empty($available_classrooms)) {
            $message = "No available classrooms found for Grade $grade_level. Please create classrooms with names like 'Grade $grade_level A'.";
            $message_type = 'warning';
        } else {
            // 3. Perform serpentine distribution
            $num_classrooms = count($available_classrooms);
            $class_index = 0;
            $direction = 1; // 1 for forward, -1 for backward

            foreach ($unassigned_students as $student) {
                $classroom_id = $available_classrooms[$class_index]['classroom_id'];
                $classroom_names[$classroom_id] = $available_classrooms[$class_index]['name'];
                $distribution_preview[$classroom_id][] = $student;

                // Move to the next classroom
                $class_index += $direction;

                // Reverse direction at the ends of the classroom list
                if ($class_index >= $num_classrooms || $class_index < 0) {
                    $direction *= -1;
                    $class_index += $direction;
                }
            }

            // 4. Save the distribution to the database
            mysqli_begin_transaction($conn);
            try {
                $sql_insert = "INSERT INTO class_assignments (student_id, classroom_id) VALUES (?, ?)";
                $stmt_insert = mysqli_prepare($conn, $sql_insert);

                foreach ($distribution_preview as $classroom_id => $students_in_class) {
                    foreach ($students_in_class as $student) {
                        mysqli_stmt_bind_param($stmt_insert, "ii", $student['student_id'], $classroom_id);
                        mysqli_stmt_execute($stmt_insert);
                    }
                }
                mysqli_commit($conn);
                mysqli_stmt_close($stmt_insert);
                
                log_activity($conn, 'auto_distribute_students', null, "Distributed " . count($unassigned_students) . " students for Grade {$grade_level}.");
                $message = "Students for Grade $grade_level have been successfully distributed and assigned.";
                $message_type = 'success';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "An error occurred while saving the assignments: " . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

mysqli_close($conn);
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Automatic Student Distribution</h1>
        <a href="manage_assignments.php" class="btn btn-secondary">Back to Assignments</a>
    </div>

    <div class="alert alert-info">
        <h5 class="alert-heading"><i class="bi bi-info-circle-fill"></i> How it Works</h5>
        <p>This tool automatically assigns unassigned students to available classrooms for a selected grade level. It uses a performance-based serpentine (or "snake") algorithm to ensure a fair and balanced distribution of students across all sections based on their last score.</p>
        <hr>
        <p class="mb-0"><strong>Note:</strong> Only classrooms matching the selected grade level will be used for distribution.</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-gear-fill"></i> Distribution Setup</h5>
        </div>
        <div class="card-body">
            <!-- Grade Selection Form -->
            <form action="manage_student_distribution.php" method="POST">
                <div class="row align-items-end">
                    <div class="col-md-6">
                        <label for="grade_level" class="form-label">Select Grade Level to Distribute</label>
                        <select name="grade_level" id="grade_level" class="form-select" required>
                            <option value="">-- Select Grade --</option>
                            <option value="9">Grade 9</option>
                            <option value="10">Grade 10</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <button type="submit" name="distribute_students" class="btn btn-primary w-100" onclick="return confirm('Are you sure? This will assign all unassigned students for the selected grade and cannot be easily undone.');">
                            <i class="bi bi-shuffle me-2"></i>Auto-Distribute Students
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Distribution Preview -->
    <?php if (!empty($distribution_preview)): ?>
        <h2 class="mt-5">Distribution Result</h2>
        <div class="row g-4">
            <?php foreach ($distribution_preview as $classroom_id => $students_in_class): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo htmlspecialchars($classroom_names[$classroom_id] ?? 'Unknown Class'); ?></h5>
                            <span class="badge bg-primary rounded-pill"><?php echo count($students_in_class); ?> Students</span>
                        </div>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($students_in_class as $student): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                                    <span class="badge bg-secondary"><?php echo $student['last_score']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
<?php include 'footer.php'; ?>