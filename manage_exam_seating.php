<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$message = '';
$message_type = '';
$seating_preview = [];
$stats = ['total_students' => 0, 'total_capacity' => 0];

// --- Handle POST request to generate seating plan ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_seating'])) {
    $exam_id = $_POST['exam_id'];
    $grade_a = $_POST['grade_a'];
    $grade_b = $_POST['grade_b'];
    $criteria = $_POST['criteria']; // 'last_score' for now

    if (empty($exam_id) || empty($grade_a) || empty($grade_b) || $grade_a == $grade_b) {
        $message = "Please select a valid exam and two different grade levels.";
        $message_type = 'error';
    } else {
        // 1. Get all students from both grades, sorted by criteria
        $sql_students = "SELECT student_id, first_name, last_name, grade_level, last_score FROM students WHERE grade_level IN (?, ?)";
        $stmt_students = mysqli_prepare($conn, $sql_students);
        mysqli_stmt_bind_param($stmt_students, "ss", $grade_a, $grade_b);
        mysqli_stmt_execute($stmt_students);
        $result_students = mysqli_stmt_get_result($stmt_students);
        
        $students_grade_a = [];
        $students_grade_b = [];
        while ($row = mysqli_fetch_assoc($result_students)) {
            if ($row['grade_level'] == $grade_a) $students_grade_a[] = $row;
            else $students_grade_b[] = $row;
        }
        mysqli_stmt_close($stmt_students);

        // 2. Get all available exam rooms and total capacity
        $exam_rooms_result = mysqli_query($conn, "SELECT room_id, name, capacity FROM exam_rooms ORDER BY name ASC");
        $exam_rooms = mysqli_fetch_all($exam_rooms_result, MYSQLI_ASSOC);
        $stats['total_capacity'] = array_sum(array_column($exam_rooms, 'capacity'));
        $stats['total_students'] = count($students_grade_a) + count($students_grade_b);

        if ($stats['total_students'] > $stats['total_capacity']) {
            $message = "Error: Not enough seats! Required: {$stats['total_students']}, Available: {$stats['total_capacity']}. Please add more exam rooms.";
            $message_type = 'error';
        } elseif (empty($exam_rooms) || empty($students_grade_a) || empty($students_grade_b)) {
            $message = "No students or exam rooms found for the selected criteria.";
            $message_type = 'error';
        } else {
            // 3. Interleave students for fair distribution
            $interleaved_students = [];
            $max_count = max(count($students_grade_a), count($students_grade_b));
            for ($i = 0; $i < $max_count; $i++) {
                if (isset($students_grade_a[$i])) $interleaved_students[] = $students_grade_a[$i];
                if (isset($students_grade_b[$i])) $interleaved_students[] = $students_grade_b[$i];
            }

            // 4. Distribute students into rooms
            $student_index = 0;
            foreach ($exam_rooms as $room) {
                for ($seat = 1; $seat <= $room['capacity']; $seat++) {
                    if (isset($interleaved_students[$student_index])) {
                        $student = $interleaved_students[$student_index];
                        $seating_preview[$room['room_id']][] = [
                            'student_id' => $student['student_id'],
                            'name' => $student['last_name'] . ', ' . $student['first_name'],
                            'grade' => $student['grade_level'],
                            'seat' => $seat
                        ];
                        $student_index++;
                    } else {
                        break; // No more students to assign
                    }
                }
            }

            // 5. Save the generated plan to the database
            mysqli_begin_transaction($conn);
            try {
                // Clear any previous assignments for this exam
                $sql_delete = "DELETE FROM exam_assignments WHERE exam_id = ?";
                $stmt_delete = mysqli_prepare($conn, $sql_delete);
                mysqli_stmt_bind_param($stmt_delete, "i", $exam_id);
                mysqli_stmt_execute($stmt_delete);
                mysqli_stmt_close($stmt_delete);

                // Insert the new seating plan
                $sql_insert = "INSERT INTO exam_assignments (exam_id, student_id, room_id, seat_number) VALUES (?, ?, ?, ?)";
                $stmt_insert = mysqli_prepare($conn, $sql_insert);
                foreach ($seating_preview as $room_id => $assigned_students) {
                    foreach ($assigned_students as $student) {
                        mysqli_stmt_bind_param($stmt_insert, "iiis", $exam_id, $student['student_id'], $room_id, $student['seat']);
                        mysqli_stmt_execute($stmt_insert);
                    }
                }
                mysqli_stmt_close($stmt_insert);
                mysqli_commit($conn);
                $message = "Exam seating plan generated and saved successfully!";
                $message_type = 'success';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = "Database error: " . $e->getMessage();
                $message_type = 'error';
                $seating_preview = []; // Clear preview on error
            }
        }
    }
}

// --- Fetch data for forms ---
$exams = mysqli_query($conn, "SELECT exam_id, name FROM exams ORDER BY exam_date DESC");
mysqli_close($conn);
$grade_levels = [9, 10, 11, 12];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Exam Seating</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 1200px; }
        .setup-form { display: flex; gap: 1rem; align-items: flex-end; margin-top: 1.5rem; background-color: #f9fafb; padding: 1.5rem; border-radius: 0.5rem; }
        .seating-preview { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .room-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; }
        .room-card h4 { margin: 0; padding: 0.75rem 1rem; background-color: #f3f4f6; border-bottom: 1px solid #e5e7eb; }
        .room-card table { width: 100%; border-collapse: collapse; }
        .room-card th, .room-card td { padding: 0.5rem 1rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .room-card tr:last-child td { border-bottom: none; }
    </style>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-layout-split me-2"></i> Generate Exam Seating Plan</h4>
            <a href="manage_assignments.php" class="btn btn-outline-light btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to Assignments</a>
        </div>
        <div class="card-body">

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>"><i class="bi <?php echo $message_type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?> me-2"></i><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Setup Form -->
            <form action="manage_exam_seating.php" method="POST" class="mt-4">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Select Exam</label>
                        <select class="form-select" name="exam_id" required>
                            <option value="">-- Select Exam --</option>
                            <?php while($exam = mysqli_fetch_assoc($exams)): ?>
                            <option value="<?php echo $exam['exam_id']; ?>"><?php echo htmlspecialchars($exam['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Mix Grade</label>
                        <select class="form-select" name="grade_a" required>
                            <?php foreach($grade_levels as $gl): ?><option value="<?php echo $gl; ?>">Grade <?php echo $gl; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">With Grade</label>
                        <select class="form-select" name="grade_b" required>
                            <?php foreach($grade_levels as $gl): ?><option value="<?php echo $gl; ?>" <?php if($gl==11) echo 'selected'; ?>>Grade <?php echo $gl; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sort By</label>
                    <select class="form-select" name="criteria" required>
                        <option value="last_score">Initial Score</option>
                        <option value="semester_avg" disabled>Semester Average (coming soon)</option>
                    </select>
                </div>
                <button type="submit" name="generate_seating" class="btn btn-primary"><i class="bi bi-gear me-1"></i> Generate & Save</button>
            </form>

            <!-- Seating Plan Preview -->
            <?php if (!empty($seating_preview)): ?>
        <hr style="margin-top: 2rem;">
        <h2>Generated Seating Plan</h2>
        <div class="seating-preview">
            <?php
            $conn = mysqli_connect("localhost", "root", "", "sms");
            $room_ids_str = implode(',', array_keys($seating_preview));
            $rooms_result = mysqli_query($conn, "SELECT room_id, name FROM exam_rooms WHERE room_id IN ($room_ids_str)");
            $room_names = array_column(mysqli_fetch_all($rooms_result, MYSQLI_ASSOC), 'name', 'room_id');
            mysqli_close($conn);
            ?>
            <?php foreach ($seating_preview as $room_id => $assigned_students): ?>
                <div class="room-card">
                    <h4><?php echo htmlspecialchars($room_names[$room_id] ?? 'Unknown Room'); ?></h4>
                    <table>
                        <thead><tr><th>Seat</th><th>Student Name</th><th>Grade</th></tr></thead>
                        <tbody>
                            <?php foreach ($assigned_students as $student): ?>
                            <tr>
                                <td><?php echo $student['seat']; ?></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['grade']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>