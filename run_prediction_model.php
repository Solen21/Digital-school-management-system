<?php
session_start();

// 1. Check if the user is logged in and is an admin or director.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

set_time_limit(300); // Allow script to run for up to 5 minutes

/**
 * In a real-world application, this script would:
 * 1. Collect all relevant student data (grades, attendance, discipline, etc.).
 * 2. Format it into a dataset (e.g., CSV or JSON).
 * 3. Execute a Python script (or call an API) with this data.
 * 4. The Python script would use a trained model (e.g., from scikit-learn, TensorFlow) to generate predictions.
 * 5. This PHP script would then receive the predictions and insert them into the `performance_predictions` table.
 *
 * For this simulation, we will generate some plausible random data.
 */

mysqli_begin_transaction($conn);

try {
    // Get all active students and their subjects
    $sql = "
        SELECT s.student_id, sa.subject_id
        FROM students s
        JOIN class_assignments ca ON s.student_id = ca.student_id
        JOIN subject_assignments sa ON ca.classroom_id = sa.classroom_id
        WHERE s.status = 'active'
    ";
    $result = mysqli_query($conn, $sql);
    $student_subjects = mysqli_fetch_all($result, MYSQLI_ASSOC);

    if (empty($student_subjects)) {
        throw new Exception("No active students with assigned subjects found.");
    }

    $sql_insert = "INSERT INTO performance_predictions (student_id, subject_id, predicted_grade, risk_level, risk_factors, prediction_date) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    $prediction_date = date('Y-m-d');

    foreach ($student_subjects as $ss) {
        // --- SIMULATED PREDICTION LOGIC ---
        $predicted_grade = rand(5500, 9800) / 100; // Random grade between 55.00 and 98.00
        $risk_factors_options = ['Low Attendance', 'Poor Past Grades', 'Discipline Issues', 'Missed Assignments'];
        
        if ($predicted_grade < 65) {
            $risk_level = 'High';
            $risk_factors = $risk_factors_options[array_rand($risk_factors_options)];
        } elseif ($predicted_grade < 75) {
            $risk_level = 'Medium';
            $risk_factors = 'Slightly low past scores';
        } else {
            $risk_level = 'Low';
            $risk_factors = 'None';
        }
        // --- END SIMULATION ---

        mysqli_stmt_bind_param($stmt_insert, "iidsss", $ss['student_id'], $ss['subject_id'], $predicted_grade, $risk_level, $risk_factors, $prediction_date);
        mysqli_stmt_execute($stmt_insert);
    }

    mysqli_commit($conn);
    log_activity($conn, 'run_predictions', null, 'Generated new performance predictions for '.count($student_subjects).' student-subject pairs.');
    $_SESSION['message'] = "Successfully generated " . count($student_subjects) . " new performance predictions.";
    $_SESSION['message_type'] = 'success';

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['message'] = "An error occurred: " . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

mysqli_close($conn);
header("Location: manage_predictions.php");
exit();
?>