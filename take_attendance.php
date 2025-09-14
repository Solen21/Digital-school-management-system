<?php
session_start();

// 1. Check if the user is logged in and is a teacher or rep.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['teacher', 'rep'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$message = '';
$message_type = 'danger';
$page_title = 'Take Attendance';

// 2. Get the teacher's internal ID from their user_id.
$sql_teacher_id = "SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1";
$stmt_teacher = mysqli_prepare($conn, $sql_teacher_id);
mysqli_stmt_bind_param($stmt_teacher, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_teacher);
$result_teacher = mysqli_stmt_get_result($stmt_teacher);
if ($teacher_row = mysqli_fetch_assoc($result_teacher)) {
    $teacher_id = $teacher_row['teacher_id'];
} else {
    die("<h1>Error</h1><p>Could not find a teacher profile associated with your user account.</p>");
}
mysqli_stmt_close($stmt_teacher);

// 3. Handle POST request to save attendance
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_attendance'])) {
    $classroom_id = $_POST['classroom_id'];
    $subject_id = $_POST['subject_id'];
    $attendance_date = $_POST['attendance_date'];
    $attendance_data = $_POST['attendance'] ?? [];

    mysqli_begin_transaction($conn);
    try {
        $sql = "INSERT INTO attendance (student_id, classroom_id, subject_id, attendance_date, status, taken_by_teacher_id)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), taken_by_teacher_id = VALUES(taken_by_teacher_id)";
        $stmt = mysqli_prepare($conn, $sql);

        foreach ($attendance_data as $student_id => $status) {
            mysqli_stmt_bind_param($stmt, "iiissi", $student_id, $classroom_id, $subject_id, $attendance_date, $status, $teacher_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to save attendance for student ID $student_id: " . mysqli_stmt_error($stmt));
            }
        }
        mysqli_commit($conn);
        $message = "Attendance for " . htmlspecialchars($attendance_date) . " saved successfully.";
        $message_type = 'success';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "Error saving attendance: " . $e->getMessage();
    }
    mysqli_stmt_close($stmt);
}

// 4. Fetch data for the page (GET request)
$teacher_assignments = [];
$sql_assignments = "SELECT DISTINCT c.classroom_id, c.name AS classroom_name, s.subject_id, s.name AS subject_name
                    FROM subject_assignments sa
                    JOIN classrooms c ON sa.classroom_id = c.classroom_id
                    JOIN subjects s ON sa.subject_id = s.subject_id
                    WHERE sa.teacher_id = ? ORDER BY c.name, s.name";
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
$selected_date = $_GET['date'] ?? date('Y-m-d');

$students = [];
$existing_attendance = [];

if ($selected_classroom_id && $selected_subject_id && $selected_date) {
    // Get students for the selected class
    $sql_students = "SELECT s.student_id, s.first_name, s.last_name FROM class_assignments ca
                     JOIN students s ON ca.student_id = s.student_id
                     WHERE ca.classroom_id = ? AND s.status = 'active' ORDER BY s.last_name, s.first_name";
    $stmt_students = mysqli_prepare($conn, $sql_students);
    mysqli_stmt_bind_param($stmt_students, "i", $selected_classroom_id);
    mysqli_stmt_execute($stmt_students);
    $result_students = mysqli_stmt_get_result($stmt_students);
    while ($row = mysqli_fetch_assoc($result_students)) {
        $students[] = $row;
    }
    mysqli_stmt_close($stmt_students);

    // Get existing attendance records for these students
    $sql_existing = "SELECT student_id, status FROM attendance WHERE classroom_id = ? AND subject_id = ? AND attendance_date = ?";
    $stmt_existing = mysqli_prepare($conn, $sql_existing);
    mysqli_stmt_bind_param($stmt_existing, "iis", $selected_classroom_id, $selected_subject_id, $selected_date);
    mysqli_stmt_execute($stmt_existing);
    $result_existing = mysqli_stmt_get_result($stmt_existing);
    while ($row = mysqli_fetch_assoc($result_existing)) {
        $existing_attendance[$row['student_id']] = $row['status'];
    }
    mysqli_stmt_close($stmt_existing);
}

mysqli_close($conn);
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Take Attendance</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Selection Form -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <form action="take_attendance.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-4"><label for="classroom_id" class="form-label">Class</label><select name="classroom_id" id="classroom_id" class="form-select" required onchange="this.form.submit()"><option value="">-- Select Class --</option><?php $classrooms_done = []; foreach ($teacher_assignments as $a) { if (!in_array($a['classroom_id'], $classrooms_done)) { $sel = ($a['classroom_id'] == $selected_classroom_id) ? 'selected' : ''; echo "<option value='{$a['classroom_id']}' $sel>" . htmlspecialchars($a['classroom_name']) . "</option>"; $classrooms_done[] = $a['classroom_id']; } } ?></select></div>
                <div class="col-md-4"><label for="subject_id" class="form-label">Subject</label><select name="subject_id" id="subject_id" class="form-select" required onchange="this.form.submit()"><option value="">-- Select Subject --</option><?php foreach ($teacher_assignments as $a) { if ($a['classroom_id'] == $selected_classroom_id) { $sel = ($a['subject_id'] == $selected_subject_id) ? 'selected' : ''; echo "<option value='{$a['subject_id']}' $sel>" . htmlspecialchars($a['subject_name']) . "</option>"; } } ?></select></div>
                <div class="col-md-4"><label for="date" class="form-label">Date</label><input type="date" name="date" id="date" class="form-control" value="<?php echo htmlspecialchars($selected_date); ?>" required onchange="this.form.submit()"></div>
            </form>
        </div>
    </div>

    <!-- Attendance Form -->
    <?php if (!empty($students)): ?>
    <form action="take_attendance.php?classroom_id=<?php echo $selected_classroom_id; ?>&subject_id=<?php echo $selected_subject_id; ?>&date=<?php echo $selected_date; ?>" method="POST">
        <input type="hidden" name="classroom_id" value="<?php echo htmlspecialchars($selected_classroom_id); ?>">
        <input type="hidden" name="subject_id" value="<?php echo htmlspecialchars($selected_subject_id); ?>">
        <input type="hidden" name="attendance_date" value="<?php echo htmlspecialchars($selected_date); ?>">
        <input type="hidden" name="save_attendance" value="1">

        <div class="card">
            <div class="card-header"><h5 class="mb-0">Attendance Sheet for <?php echo htmlspecialchars($selected_date); ?></h5></div>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light"><tr><th>Student Name</th><th class="text-center">Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($students as $student):
                            $sid = $student['student_id'];
                            $current_status = $existing_attendance[$sid] ?? 'Present'; // Default to Present
                        ?>
                        <tr>
                            <td class="align-middle"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                            <td>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="attendance[<?php echo $sid; ?>]" id="present-<?php echo $sid; ?>" value="Present" <?php if($current_status == 'Present') echo 'checked'; ?>>
                                    <label class="btn btn-outline-success" for="present-<?php echo $sid; ?>">Present</label>

                                    <input type="radio" class="btn-check" name="attendance[<?php echo $sid; ?>]" id="absent-<?php echo $sid; ?>" value="Absent" <?php if($current_status == 'Absent') echo 'checked'; ?>>
                                    <label class="btn btn-outline-danger" for="absent-<?php echo $sid; ?>">Absent</label>

                                    <input type="radio" class="btn-check" name="attendance[<?php echo $sid; ?>]" id="late-<?php echo $sid; ?>" value="Late" <?php if($current_status == 'Late') echo 'checked'; ?>>
                                    <label class="btn btn-outline-warning" for="late-<?php echo $sid; ?>">Late</label>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="text-end mt-3">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-save-fill"></i> Save Attendance</button>
        </div>
    </form>
    <?php elseif ($selected_classroom_id && $selected_subject_id): ?>
        <div class="alert alert-warning">No students found for the selected class.</div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>