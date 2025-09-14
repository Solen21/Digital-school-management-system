<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

// 2. Check if student ID is provided and is numeric.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['import_message'] = "Invalid request. No student ID provided.";
    $_SESSION['import_message_type'] = 'danger';
    header("Location: manage_students.php");
    exit();
}

$student_id_to_delete = intval($_GET['id']);

require_once 'data/db_connect.php';
require_once 'functions.php';

mysqli_begin_transaction($conn);

try {
    // 1. Get student details before deletion for logging
    $sql_get_student = "SELECT user_id, first_name, last_name FROM students WHERE student_id = ?";
    $stmt_get = mysqli_prepare($conn, $sql_get_student);
    mysqli_stmt_bind_param($stmt_get, "i", $student_id_to_delete);
    mysqli_stmt_execute($stmt_get);
    $result_get = mysqli_stmt_get_result($stmt_get);
    $student_data = mysqli_fetch_assoc($result_get);
    mysqli_stmt_close($stmt_get);

    if (!$student_data) {
        throw new Exception("Student with ID {$student_id_to_delete} not found.");
    }

    $user_id_to_delete = $student_data['user_id'];
    $student_name = $student_data['first_name'] . ' ' . $student_data['last_name'];

    // 2. Delete related records from child tables to maintain data integrity.
    // The order is important if foreign keys are enforced.
    $tables_to_delete_from = [
        'student_profile_logs',
        'student_guardian_map',
        'grades',
        'attendance',
        'class_assignments',
        'exam_assignments'
    ];

    foreach ($tables_to_delete_from as $table) {
        $sql_delete_related = "DELETE FROM {$table} WHERE student_id = ?";
        $stmt_delete_related = mysqli_prepare($conn, $sql_delete_related);
        mysqli_stmt_bind_param($stmt_delete_related, "i", $student_id_to_delete);
        if (!mysqli_stmt_execute($stmt_delete_related)) {
            throw new Exception("Failed to delete related data from {$table}.");
        }
        mysqli_stmt_close($stmt_delete_related);
    }

    // 3. Delete the student record itself
    $sql_delete_student = "DELETE FROM students WHERE student_id = ?";
    $stmt_delete_student = mysqli_prepare($conn, $sql_delete_student);
    mysqli_stmt_bind_param($stmt_delete_student, "i", $student_id_to_delete);
    if (!mysqli_stmt_execute($stmt_delete_student)) {
        throw new Exception("Failed to delete student record.");
    }
    mysqli_stmt_close($stmt_delete_student);

    // 4. Delete the associated user account
    if ($user_id_to_delete) {
        $sql_delete_user = "DELETE FROM users WHERE user_id = ?";
        $stmt_delete_user = mysqli_prepare($conn, $sql_delete_user);
        mysqli_stmt_bind_param($stmt_delete_user, "i", $user_id_to_delete);
        if (!mysqli_stmt_execute($stmt_delete_user)) {
            throw new Exception("Failed to delete user account.");
        }
        mysqli_stmt_close($stmt_delete_user);
    }

    // 5. Log this action
    log_activity($conn, 'delete_student', $student_id_to_delete, $student_name);

    // 6. If all went well, commit the transaction
    mysqli_commit($conn);
    $_SESSION['import_message'] = "Student '{$student_name}' and all associated data have been deleted successfully.";
    $_SESSION['import_message_type'] = 'success';

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['import_message'] = "Error deleting student: " . $e->getMessage();
    $_SESSION['import_message_type'] = 'danger';
}

mysqli_close($conn);
header("Location: manage_students.php");
exit();