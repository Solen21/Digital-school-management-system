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

// --- Handle POST request to save schedule ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_schedule'])) {
    $classroom_id = $_POST['classroom_id'] ?? null;
    $schedule_data = $_POST['schedule'] ?? []; // [period_id => subject_assignment_id]

    if (empty($classroom_id)) {
        $message = "Please select a classroom before saving.";
        $message_type = 'error';
    } else {
        mysqli_begin_transaction($conn);
        try {
            // First, remove all existing schedule entries for this classroom
            $sql_delete = "DELETE FROM class_schedule WHERE classroom_id = ?";
            $stmt_delete = mysqli_prepare($conn, $sql_delete);
            mysqli_stmt_bind_param($stmt_delete, "i", $classroom_id);
            mysqli_stmt_execute($stmt_delete);
            mysqli_stmt_close($stmt_delete);

            // Now, insert the new schedule entries
            $sql_insert = "INSERT INTO class_schedule (classroom_id, period_id, subject_assignment_id) VALUES (?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            foreach ($schedule_data as $period_id => $subject_assignment_id) {
                if (!empty($subject_assignment_id)) { // Only insert if a subject was selected
                    mysqli_stmt_bind_param($stmt_insert, "iii", $classroom_id, $period_id, $subject_assignment_id);
                    mysqli_stmt_execute($stmt_insert);
                }
            }
            mysqli_stmt_close($stmt_insert);

            mysqli_commit($conn);
            $message = "Timetable updated successfully.";
            $message_type = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error updating timetable: " . $e->getMessage();
            $message_type = 'error';
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['auto_distribute'])) {
    $classroom_id = $_POST['classroom_id'] ?? null;
    if (empty($classroom_id)) {
        $message = "Please select a classroom before auto-distributing.";
        $message_type = 'danger';
    } else {
        try {
            // --- New, Smarter Auto-Distribution Logic ---
            // 1. Get classroom's grade and shift to determine available periods
            $stmt_grade = mysqli_prepare($conn, "SELECT grade_level FROM classrooms WHERE classroom_id = ?");
            mysqli_stmt_bind_param($stmt_grade, "i", $classroom_id);
            mysqli_stmt_execute($stmt_grade);
            $grade_level = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_grade))['grade_level'];
            mysqli_stmt_close($stmt_grade);

            $current_week = date('W');
            $stmt_shift = mysqli_prepare($conn, "SELECT shift FROM weekly_shift_assignments WHERE grade_level = ? AND week_of_year = ?");
            mysqli_stmt_bind_param($stmt_shift, "ii", $grade_level, $current_week);
            mysqli_stmt_execute($stmt_shift);
            $classroom_shift = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_shift))['shift'] ?? 'Morning';
            mysqli_stmt_close($stmt_shift);
            
            $stmt_periods = mysqli_prepare($conn, "SELECT period_id FROM schedule_periods WHERE shift = ? AND is_break = 0");
            mysqli_stmt_bind_param($stmt_periods, "s", $classroom_shift);
            mysqli_stmt_execute($stmt_periods);
            $available_period_ids = array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt_periods), MYSQLI_ASSOC), 'period_id');
            mysqli_stmt_close($stmt_periods);

            // 2. Get all subject assignments for this class, including teacher_id and periods_per_week
            $sql_sa = "SELECT sa.assignment_id, sa.teacher_id, s.periods_per_week FROM subject_assignments sa JOIN subjects s ON sa.subject_id = s.subject_id WHERE sa.classroom_id = ?";
            $stmt_sa = mysqli_prepare($conn, $sql_sa);
            mysqli_stmt_bind_param($stmt_sa, "i", $classroom_id);
            mysqli_stmt_execute($stmt_sa);
            $subject_assignments_raw = mysqli_fetch_all(mysqli_stmt_get_result($stmt_sa), MYSQLI_ASSOC);
            mysqli_stmt_close($stmt_sa);

            // Create a "pool" of classes to be scheduled, expanded by periods_per_week
            $subject_pool = [];
            foreach ($subject_assignments_raw as $sa) {
                for ($i = 0; $i < $sa['periods_per_week']; $i++) {
                    $subject_pool[] = ['assignment_id' => $sa['assignment_id'], 'teacher_id' => $sa['teacher_id']];
                }
            }

            if (count($subject_pool) > count($available_period_ids)) {
                throw new Exception("The total required periods (" . count($subject_pool) . ") exceed the available slots (" . count($available_period_ids) . "). Please adjust subject periods per week.");
            }

            // 3. Get the complete existing schedule for ALL classrooms to check for teacher conflicts
            $sql_all_schedules = "SELECT cs.period_id, sa.teacher_id FROM class_schedule cs JOIN subject_assignments sa ON cs.subject_assignment_id = sa.assignment_id";
            $all_schedules_result = mysqli_query($conn, $sql_all_schedules);
            $teacher_schedule_map = []; // [period_id => [teacher_id, teacher_id, ...]]
            while ($row = mysqli_fetch_assoc($all_schedules_result)) {
                $teacher_schedule_map[$row['period_id']][] = $row['teacher_id'];
            }

            // 4. The Scheduling Algorithm
            shuffle($subject_pool); // Randomize to avoid the same subjects always getting prime slots
            $new_schedule = []; // [period_id => assignment_id]
            $unscheduled_subjects = [];

            foreach ($subject_pool as $subject_to_schedule) {
                $assignment_id = $subject_to_schedule['assignment_id'];
                $teacher_id = $subject_to_schedule['teacher_id'];
                $is_scheduled = false;

                // Find an available period for this subject's teacher
                shuffle($available_period_ids); // Shuffle periods to try different slots
                foreach ($available_period_ids as $period_id) {
                    // Check if this period is already taken in the new schedule for THIS class
                    if (isset($new_schedule[$period_id])) {
                        continue;
                    }
                    // Check if the teacher is already busy in ANY class during this period
                    if (isset($teacher_schedule_map[$period_id]) && in_array($teacher_id, $teacher_schedule_map[$period_id])) {
                        continue;
                    }

                    // Slot is free! Assign it.
                    $new_schedule[$period_id] = $assignment_id;
                    $teacher_schedule_map[$period_id][] = $teacher_id; // Mark teacher as busy for this period
                    $is_scheduled = true;
                    break; // Move to the next subject in the pool
                }

                if (!$is_scheduled) {
                    $unscheduled_subjects[] = $assignment_id;
                }
            }

            if (!empty($unscheduled_subjects)) {
                throw new Exception("Could not automatically schedule all subjects due to teacher conflicts. Please adjust assignments or try again.");
            }

            // Success! The $new_schedule is now the proposed schedule.
            $current_schedule = $new_schedule; // Set it for the view to render
            $message = "Schedule has been auto-distributed. Review the changes and click 'Save Timetable' to confirm.";
            $message_type = 'success';

        } else {
            $message = "Error: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
} else {
    // --- Normal GET request or after save ---
    $selected_classroom_id = $_GET['classroom_id'] ?? null;
    if ($selected_classroom_id) {
        // Get the current schedule for this classroom
        $sql_current = "SELECT period_id, subject_assignment_id FROM class_schedule WHERE classroom_id = ?";
        $stmt_current = mysqli_prepare($conn, $sql_current);
        mysqli_stmt_bind_param($stmt_current, "i", $selected_classroom_id);
        mysqli_stmt_execute($stmt_current);
        $result_current = mysqli_stmt_get_result($stmt_current);
        $current_schedule = []; // [period_id => subject_assignment_id]
        while ($row = mysqli_fetch_assoc($result_current)) {
            $current_schedule[$row['period_id']] = $row['subject_assignment_id'];
        }
        mysqli_stmt_close($stmt_current);
    }
}

// --- Fetch data for display ---
$classrooms = mysqli_query($conn, "SELECT classroom_id, name FROM classrooms ORDER BY name ASC");
$selected_classroom_id = $_GET['classroom_id'] ?? ($_POST['classroom_id'] ?? $selected_classroom_id ?? null);

$periods = [];
$subject_assignments = [];

if ($selected_classroom_id) {
    // Get the classroom's grade level, then find its current shift
    $stmt_grade = mysqli_prepare($conn, "SELECT grade_level FROM classrooms WHERE classroom_id = ?");
    mysqli_stmt_bind_param($stmt_grade, "i", $selected_classroom_id);
    mysqli_stmt_execute($stmt_grade);
    $grade_level = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_grade))['grade_level'];
    mysqli_stmt_close($stmt_grade);

    $current_week = date('W');
    $stmt_shift = mysqli_prepare($conn, "SELECT shift FROM weekly_shift_assignments WHERE grade_level = ? AND week_of_year = ?");
    mysqli_stmt_bind_param($stmt_shift, "ii", $grade_level, $current_week);
    mysqli_stmt_execute($stmt_shift);
    $result_shift = mysqli_stmt_get_result($stmt_shift);
    $classroom_shift = mysqli_fetch_assoc($result_shift)['shift'] ?? null;
    mysqli_stmt_close($stmt_shift);

    // Get all schedule periods for that shift
    $stmt_periods = mysqli_prepare($conn, "SELECT * FROM schedule_periods WHERE shift = ? ORDER BY day_of_week, start_time");
    mysqli_stmt_bind_param($stmt_periods, "s", $classroom_shift);
    mysqli_stmt_execute($stmt_periods);
    $result_periods = mysqli_stmt_get_result($stmt_periods);
    while ($row = mysqli_fetch_assoc($result_periods)) {
        $periods[$row['day_of_week']][] = $row;
    }
    mysqli_stmt_close($stmt_periods);

    // Get all available subject-teacher assignments for this classroom
    $sql_sa = "
        SELECT sa.assignment_id, s.name AS subject_name, t.first_name, t.last_name
        FROM subject_assignments sa
        JOIN subjects s ON sa.subject_id = s.subject_id
        JOIN teachers t ON sa.teacher_id = t.teacher_id
        WHERE sa.classroom_id = ?
        ORDER BY s.name
    ";
    $stmt_sa = mysqli_prepare($conn, $sql_sa);
    mysqli_stmt_bind_param($stmt_sa, "i", $selected_classroom_id);
    mysqli_stmt_execute($stmt_sa);
    $result_sa = mysqli_stmt_get_result($stmt_sa);
    while ($row = mysqli_fetch_assoc($result_sa)) {
        $subject_assignments[] = $row;
    }
    mysqli_stmt_close($stmt_sa);

}

mysqli_close($conn);
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Timetable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        table { table-layout: fixed; }
        th, td {  vertical-align: middle; height: 60px; }
        td select { width: 100%; font-size: 0.8rem; }
        .break { background-color: #f0f0f0; font-style: italic; }
    </style>
</head>
<body class="bg-light">
<div class="container" style="max-width: 1400px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Manage Timetable</h1>
        <a href="manage_assignments.php" class="btn" style="background-color: #6b7280;">Back to Assignments</a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>"><i class="bi <?php echo $message_type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?> me-2"></i><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <form action="manage_timetable.php" method="GET" id="classroom-select-form" class="mb-3">
                <label for="classroom_id" class="form-label">Select a Classroom to Manage Timetable</label>
                <select class="form-select" name="classroom_id" id="classroom_id" onchange="this.form.submit()">
                    <option value="">-- Select Classroom --</option>
                    <?php mysqli_data_seek($classrooms, 0); ?>
                    <?php while($classroom = mysqli_fetch_assoc($classrooms)): ?>
                    <option value="<?php echo $classroom['classroom_id']; ?>" <?php echo ($selected_classroom_id == $classroom['classroom_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($classroom['name']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if ($selected_classroom_id): ?>
    <form action="manage_timetable.php" method="POST" id="timetable-form" class="mt-4">
        <input type="hidden" name="classroom_id" value="<?php echo $selected_classroom_id; ?>">
        <input type="hidden" name="save_schedule" value="1">
        <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr class="table-primary"><th class="text-center">Time / Day</th><?php foreach ($days_of_week as $day) echo "<th class='text-center'>$day</th>"; ?></tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < 7; $i++): // Assumes max 6 periods + 1 break per shift ?>
                    <tr>
                        <?php $sample_period = $periods['Monday'][$i] ?? null; ?>
                        <th><?php echo date('h:i A', strtotime($sample_period['start_time'])) . ' - ' . date('h:i A', strtotime($sample_period['end_time'])); ?></th>
                        <?php foreach ($days_of_week as $day): ?>
                            <?php $period = $periods[$day][$i] ?? null; ?>
                            <?php if ($period && $period['is_break']): ?>
                                <td class="break">Break</td>
                            <?php elseif ($period): ?>
                                <td>
                                    <select class="form-select" name="schedule[<?php echo $period['period_id']; ?>]">
                                        <option value="">-- Empty --</option>
                                        <?php foreach ($subject_assignments as $sa): ?>
                                            <?php $is_selected = (isset($current_schedule[$period['period_id']]) && $current_schedule[$period['period_id']] == $sa['assignment_id']); ?>
                                            <option value="<?php echo $sa['assignment_id']; ?>" <?php echo $is_selected ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($sa['subject_name'] . ' - ' . $sa['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            <?php else: ?>
                                <td></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table></div>
        <div class="mt-3">
            <button type="submit" name="save_schedule" class="btn btn-success"><i class="bi bi-save me-1"></i> Save Timetable</button>
            <button type="submit" name="auto_distribute" class="btn btn-warning" formnovalidate><i class="bi bi-shuffle me-1"></i> Auto-Distribute Schedule</button>
        </div>
    </form>
    <?php endif; ?>
</div>
</body>
</html>