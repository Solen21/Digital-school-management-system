<?php
session_start();

// 1. Check if the user is logged in and is a teacher or rep.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['teacher', 'rep'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$teacher_id = null;
$message = '';
$message_type = '';
$page_title = 'Enter/Edit Grades';
// Get the teacher's internal ID from their user_id.
$sql_teacher_id = "SELECT teacher_id FROM teachers WHERE user_id = ?";
$stmt_teacher_id = mysqli_prepare($conn, $sql_teacher_id);
mysqli_stmt_bind_param($stmt_teacher_id, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_teacher_id);
$result_teacher_id = mysqli_stmt_get_result($stmt_teacher_id);
if ($row = mysqli_fetch_assoc($result_teacher_id)) {
    $teacher_id = $row['teacher_id'];
} else {
    die("<h1>Error</h1><p>Could not find a teacher profile associated with your user account.</p>");
}
mysqli_stmt_close($stmt_teacher_id);

// --- Handle POST request to save grades ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_grades'])) {
    $subject_id = $_POST['subject_id'];
    $grades_data = $_POST['grades']; // Array of [student_id => [component => value]]

    mysqli_begin_transaction($conn);
    try {
        $sql = "
            INSERT INTO grades (student_id, subject_id, teacher_id, test, assignment, activity, exercise, midterm, final, total, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                test = VALUES(test), assignment = VALUES(assignment), activity = VALUES(activity), 
                exercise = VALUES(exercise), midterm = VALUES(midterm), final = VALUES(final), 
                total = VALUES(total), updated_by = VALUES(updated_by)
        ";
        $stmt = mysqli_prepare($conn, $sql);
        $updated_by_username = $_SESSION['username'];

        foreach ($grades_data as $student_id => $components) {
            $test = floatval($components['test'] ?? 0);
            $assignment = floatval($components['assignment'] ?? 0);
            $activity = floatval($components['activity'] ?? 0);
            $exercise = floatval($components['exercise'] ?? 0);
            $midterm = floatval($components['midterm'] ?? 0);
            $final = floatval($components['final'] ?? 0);
            $total = $test + $assignment + $activity + $exercise + $midterm + $final;

            mysqli_stmt_bind_param($stmt, "iidddddddds", $student_id, $subject_id, $teacher_id, $test, $assignment, $activity, $exercise, $midterm, $final, $total, $updated_by_username);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to save grades for student ID $student_id: " . mysqli_stmt_error($stmt));
            }
        }
        
        mysqli_commit($conn);
        $message = "Grades saved successfully.";
        $message_type = 'success';

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "Error saving grades: " . $e->getMessage();
        $message_type = 'error';
    }
    mysqli_stmt_close($stmt);
}

// --- Fetch data for the page (GET request) ---
$teacher_assignments = [];
$sql_assignments = "
    SELECT DISTINCT c.classroom_id, c.name AS classroom_name, s.subject_id, s.name AS subject_name
    FROM subject_assignments sa
    JOIN classrooms c ON sa.classroom_id = c.classroom_id
    JOIN subjects s ON sa.subject_id = s.subject_id
    WHERE sa.teacher_id = ?
    ORDER BY c.name, s.name
";
$stmt_assignments = mysqli_prepare($conn, $sql_assignments);
mysqli_stmt_bind_param($stmt_assignments, "i", $teacher_id);
mysqli_stmt_execute($stmt_assignments);
$result_assignments = mysqli_stmt_get_result($stmt_assignments);
while ($row = mysqli_fetch_assoc($result_assignments)) {
    $teacher_assignments[] = $row;
}
mysqli_stmt_close($stmt_assignments);

$selected_classroom_id = $_GET['classroom_id'] ?? null;
$selected_subject_id = $_GET['subject_id'] ?? null;

$students = [];
$existing_grades = [];

if ($selected_classroom_id && $selected_subject_id) {
    // Get students for the selected class
    $sql_students = "
        SELECT s.student_id, s.first_name, s.middle_name, s.last_name
        FROM class_assignments ca
        JOIN students s ON ca.student_id = s.student_id
        WHERE ca.classroom_id = ?
        ORDER BY s.last_name, s.first_name
    ";
    $stmt_students = mysqli_prepare($conn, $sql_students);
    mysqli_stmt_bind_param($stmt_students, "i", $selected_classroom_id);
    mysqli_stmt_execute($stmt_students);
    $result_students = mysqli_stmt_get_result($stmt_students);
    while ($row = mysqli_fetch_assoc($result_students)) {
        $students[] = $row;
    }
    mysqli_stmt_close($stmt_students);

    // Get existing grades for these students and the selected subject
    $sql_existing = "SELECT * FROM grades WHERE student_id = ? AND subject_id = ?";
    $stmt_existing = mysqli_prepare($conn, $sql_existing);
    foreach ($students as $student) {
        mysqli_stmt_bind_param($stmt_existing, "ii", $student['student_id'], $selected_subject_id);
        mysqli_stmt_execute($stmt_existing);
        $result_existing = mysqli_stmt_get_result($stmt_existing);
        if ($row = mysqli_fetch_assoc($result_existing)) {
            $existing_grades[$student['student_id']] = $row;
        }
    }
    mysqli_stmt_close($stmt_existing);
}

mysqli_close($conn);
include 'header.php';
?>
<style>
    .grades-table td input { width: 100%; padding: 6px; border-radius: 4px; border: 1px solid #ccc; text-align: center; }
    .grades-table td input[readonly] { background-color: #e9ecef; cursor: not-allowed; font-weight: bold; }
    .grades-table .student-name { width: 25%; }
</style>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Enter/Edit Grades</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Step 1: Selection Form -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <form action="enter_grades.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label for="classroom_id" class="form-label">Class</label>
                    <select name="classroom_id" id="classroom_id" class="form-select" required onchange="this.form.submit()">
                        <option value="">-- Select Class --</option>
                        <?php
                        $classrooms_done = [];
                        foreach ($teacher_assignments as $assignment) {
                            if (!in_array($assignment['classroom_id'], $classrooms_done)) {
                                $selected = ($assignment['classroom_id'] == $selected_classroom_id) ? 'selected' : '';
                                echo "<option value='{$assignment['classroom_id']}' $selected>" . htmlspecialchars($assignment['classroom_name']) . "</option>";
                                $classrooms_done[] = $assignment['classroom_id'];
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="subject_id" class="form-label">Subject</label>
                    <select name="subject_id" id="subject_id" class="form-select" required onchange="this.form.submit()">
                        <option value="">-- Select Subject --</option>
                        <?php
                        foreach ($teacher_assignments as $assignment) {
                            if ($assignment['classroom_id'] == $selected_classroom_id) {
                                $selected = ($assignment['subject_id'] == $selected_subject_id) ? 'selected' : '';
                                echo "<option value='{$assignment['subject_id']}' $selected>" . htmlspecialchars($assignment['subject_name']) . "</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Step 2: Grade Entry Form -->
    <?php if (!empty($students)): ?>
    <form action="enter_grades.php?classroom_id=<?php echo $selected_classroom_id; ?>&subject_id=<?php echo $selected_subject_id; ?>" method="POST">
        <input type="hidden" name="classroom_id" value="<?php echo htmlspecialchars($selected_classroom_id); ?>">
        <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($selected_subject_id); ?>">
        <input type="hidden" name="save_grades" value="1">

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Grade Sheet</h5></div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-striped table-hover mb-0 grades-table">
                    <thead class="table-light">
                        <tr>
                            <th class="student-name">Student Name</th>
                            <th>Test</th><th>Assignment</th><th>Activity</th><th>Exercise</th><th>Midterm</th><th>Final</th><th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            $sid = $student['student_id'];
                            $grade = $existing_grades[$sid] ?? [];
                        ?>
                        <tr class="grade-row" data-studentid="<?php echo $sid; ?>">
                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td><input type="number" step="0.01" min="0" max="10" name="grades[<?php echo $sid; ?>][test]" value="<?php echo $grade['test'] ?? ''; ?>" class="form-control form-control-sm grade-input"></td>
                            <td><input type="number" step="0.01" min="0" max="10" name="grades[<?php echo $sid; ?>][assignment]" value="<?php echo $grade['assignment'] ?? ''; ?>" class="form-control form-control-sm grade-input"></td>
                            <td><input type="number" step="0.01" min="0" max="10" name="grades[<?php echo $sid; ?>][activity]" value="<?php echo $grade['activity'] ?? ''; ?>" class="form-control form-control-sm grade-input"></td>
                            <td><input type="number" step="0.01" min="0" max="10" name="grades[<?php echo $sid; ?>][exercise]" value="<?php echo $grade['exercise'] ?? ''; ?>" class="form-control form-control-sm grade-input"></td>
                            <td><input type="number" step="0.01" min="0" max="20" name="grades[<?php echo $sid; ?>][midterm]" value="<?php echo $grade['midterm'] ?? ''; ?>" class="form-control form-control-sm grade-input"></td>
                            <td><input type="number" step="0.01" min="0" max="40" name="grades[<?php echo $sid; ?>][final]" value="<?php echo $grade['final'] ?? ''; ?>" class="form-control form-control-sm grade-input"></td>
                            <td><input type="number" step="0.01" name="grades[<?php echo $sid; ?>][total]" value="<?php echo $grade['total'] ?? ''; ?>" class="form-control form-control-sm total-output" readonly></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div></div>
        </div>
        <div class="text-end mt-3">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save-fill"></i> Save All Grades</button>
        </div>
    </form>
    <?php elseif ($selected_classroom_id && $selected_subject_id): ?>
        <div class="alert alert-warning">No students found for the selected class.</div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const gradeTable = document.querySelector('table');
    if (!gradeTable) return;

    gradeTable.addEventListener('input', function(e) {
        if (e.target.classList.contains('grade-input')) {
            const row = e.target.closest('.grade-row');
            if (row) {
                updateTotal(row);
            }
        }
    });

    // Initial calculation for all rows
    document.querySelectorAll('.grade-row').forEach(updateTotal);
});

function updateTotal(row) {
    const inputs = row.querySelectorAll('.grade-input');
    let total = 0;
    inputs.forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    const totalOutput = row.querySelector('.total-output');
    if (totalOutput) {
        totalOutput.value = total.toFixed(2);
    }
}
</script>
<?php include 'footer.php'; ?>