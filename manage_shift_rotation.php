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

// --- Handle POST request to generate shifts ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_shifts'])) {
    $num_weeks = intval($_POST['num_weeks']);
    if ($num_weeks > 0 && $num_weeks <= 52) { // Safety cap
        mysqli_begin_transaction($conn);
        try {
            // Find the last generated week to continue from there
            $last_week_result = mysqli_query($conn, "SELECT year, week_of_year FROM weekly_shift_assignments ORDER BY year DESC, week_of_year DESC LIMIT 1");
            if (mysqli_num_rows($last_week_result) > 0) {
                $last_week_row = mysqli_fetch_assoc($last_week_result);
                $start_year = $last_week_row['year'];
                $start_week = $last_week_row['week_of_year'];

                // Get the shift assignments for that last week to determine the starting pattern
                $sql_last_shifts = "SELECT grade_level, shift FROM weekly_shift_assignments WHERE year = ? AND week_of_year = ? ORDER BY grade_level ASC";
                $stmt_last = mysqli_prepare($conn, $sql_last_shifts);
                mysqli_stmt_bind_param($stmt_last, "ii", $start_year, $start_week);
                mysqli_stmt_execute($stmt_last);
                $result_last = mysqli_stmt_get_result($stmt_last);
                $last_shifts = [];
                while($row = mysqli_fetch_assoc($result_last)) {
                    $last_shifts[$row['grade_level']] = $row['shift'];
                }
            } else {
                // No shifts generated yet, start from this year and week 1 with a default pattern
                $start_year = date('Y');
                $start_week = 0; // Will be incremented to 1 in the loop
                $last_shifts = [9 => 'Morning', 10 => 'Afternoon', 11 => 'Morning', 12 => 'Afternoon']; // Default starting pattern
            }

            $sql_insert = "INSERT INTO weekly_shift_assignments (year, week_of_year, grade_level, shift) VALUES (?, ?, ?, ?)";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);

            $current_week = $start_week;
            $current_year = $start_year;
            $current_shifts = $last_shifts;

            for ($i = 0; $i < $num_weeks; $i++) {
                $current_week++;
                if ($current_week > 52) {
                    $current_week = 1;
                    $current_year++;
                }

                // Flip the shifts for the new week
                $next_shifts = [];
                foreach ($current_shifts as $grade => $shift) {
                    $next_shifts[$grade] = ($shift == 'Morning') ? 'Afternoon' : 'Morning';
                }
                $current_shifts = $next_shifts;

                // Insert the new week's assignments
                foreach ($current_shifts as $grade => $shift) {
                    mysqli_stmt_bind_param($stmt_insert, "iiis", $current_year, $current_week, $grade, $shift);
                    mysqli_stmt_execute($stmt_insert);
                }
            }

            mysqli_commit($conn);
            $message = "$num_weeks week(s) of shift rotations have been generated successfully.";
            $message_type = 'success';

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error generating shifts: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Please enter a valid number of weeks (1-52).";
        $message_type = 'error';
    }
}

// --- Fetch current week's shifts for display ---
$current_year = date('Y');
$current_week_num = date('W');
$sql_current_shifts = "SELECT grade_level, shift FROM weekly_shift_assignments WHERE year = ? AND week_of_year = ?";
$stmt_display = mysqli_prepare($conn, $sql_current_shifts);
mysqli_stmt_bind_param($stmt_display, "ii", $current_year, $current_week_num);
mysqli_stmt_execute($stmt_display);
$result_display = mysqli_stmt_get_result($stmt_display);
$shifts_today = ['Morning' => [], 'Afternoon' => []];
while($row = mysqli_fetch_assoc($result_display)) {
    $shifts_today[$row['shift']][] = "Grade " . $row['grade_level'];
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <title>Manage Shift Rotation</title>

    <!-- Custom styles -->
    <style>
        .info-box { background-color: #f3f4f6; border-radius: 0.375rem; padding: 1.5rem; margin-top: 1.5rem; }
        .shift-display { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem; }
        .shift-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; }
        .shift-card h4 { margin-top: 0; }
    </style>
</head>
<body class="bg-light">

<div class="container py-5" style="max-width: 800px;">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="bi bi-arrow-clockwise me-2"></i> Manage Weekly Shift Rotation</h4>
            <a href="manage_assignments.php" class="btn btn-outline-light btn-sm"><i class="bi bi-chevron-left me-1"></i> Back to Assignments</a>
        </div>
        <div class="card-body">

            <div class="alert alert-info" role="alert">
                <i class="bi bi-info-circle me-2"></i> This system automatically rotates shifts weekly. Two grade levels are paired in the morning shift, and two are in the afternoon. The pairs switch shifts every week.
            </div>

            <h5 class="mt-4"><i class="bi bi-calendar-week me-2"></i> Current Week (Week <?php echo $current_week_num; ?>, <?php echo $current_year; ?>)</h5>
            <div class="shift-display">
                <div class="shift-card">
                    <h4><i class="bi bi-sunrise me-1"></i> Morning Shift</h4>
                    <p><?php echo !empty($shifts_today['Morning']) ? implode(' & ', $shifts_today['Morning']) : '<span class="text-muted">Not set for this week.</span>'; ?></p>
                </div>
                <div class="shift-card">
                    <h4><i class="bi bi-sunset me-1"></i> Afternoon Shift</h4>
                    <p><?php echo !empty($shifts_today['Afternoon']) ? implode(' & ', $shifts_today['Afternoon']) : '<span class="text-muted">Not set for this week.</span>'; ?></p>
                </div>
            </div>

            <hr class="my-4">
            <h4><i class="bi bi-gear me-2"></i> Generate Future Shifts</h4>
            <p>Generate the rotating schedule for the upcoming weeks. The system will continue from the last generated week.</p>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>"><i class="bi <?php echo $message_type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'; ?> me-2"></i><?php echo $message; ?></div>
            <?php endif; ?>

            <form action="manage_shift_rotation.php" method="POST">
                <div class="mb-3">
                    <label for="num_weeks" class="form-label">Number of Weeks to Generate</label>
                    <input type="number" class="form-control" name="num_weeks" id="num_weeks" value="4" min="1" max="52" required>
                </div>
                <button type="submit" name="generate_shifts" class="btn btn-success"><i class="bi bi-plus-circle me-2"></i> Generate Shifts</button>
            </form>

        </div>
    </div>
</div>
</body>
</html>