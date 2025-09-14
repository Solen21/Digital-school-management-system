<?php
session_start();

// 1. Check if the user is logged in and is an admin or director.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

// 2. Check if teacher ID is provided and is numeric.
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['import_message'] = "Invalid request. No teacher ID provided.";
    $_SESSION['import_message_type'] = 'danger';
    header("Location: manage_teachers.php");
    exit();
}

$teacher_id_to_delete = intval($_GET['id']);

require_once 'data/db_connect.php';
require_once 'functions.php';

mysqli_begin_transaction($conn);

try {
    // 1. Get teacher details before deletion for logging
    $sql_get_teacher = "SELECT user_id, first_name, last_name FROM teachers WHERE teacher_id = ?";
    $stmt_get = mysqli_prepare($conn, $sql_get_teacher);
    mysqli_stmt_bind_param($stmt_get, "i", $teacher_id_to_delete);
    mysqli_stmt_execute($stmt_get);
    $result_get = mysqli_stmt_get_result($stmt_get);
    $teacher_data = mysqli_fetch_assoc($result_get);
    mysqli_stmt_close($stmt_get);

    if (!$teacher_data) {
        throw new Exception("Teacher with ID {$teacher_id_to_delete} not found.");
    }

    $user_id_to_delete = $teacher_data['user_id'];
    $teacher_name = $teacher_data['first_name'] . ' ' . $teacher_data['last_name'];

    // 2. Delete related records from child tables.
    // This is important to avoid orphaned records.
    $tables_to_delete_from = [
        'subject_assignments',
        'leave_requests',
        'grades', // Grades are linked to teachers
        'attendance' // Attendance is linked to teachers
    ];

    foreach ($tables_to_delete_from as $table) {
        $sql_delete_related = "DELETE FROM {$table} WHERE teacher_id = ?";
        $stmt_delete_related = mysqli_prepare($conn, $sql_delete_related);
        mysqli_stmt_bind_param($stmt_delete_related, "i", $teacher_id_to_delete);
        if (!mysqli_stmt_execute($stmt_delete_related)) {
            throw new Exception("Failed to delete related data from {$table}.");
        }
        mysqli_stmt_close($stmt_delete_related);
    }

    // 3. Delete the teacher record itself
    $sql_delete_teacher = "DELETE FROM teachers WHERE teacher_id = ?";
    $stmt_delete_teacher = mysqli_prepare($conn, $sql_delete_teacher);
    mysqli_stmt_bind_param($stmt_delete_teacher, "i", $teacher_id_to_delete);
    if (!mysqli_stmt_execute($stmt_delete_teacher)) throw new Exception("Failed to delete teacher record.");
    mysqli_stmt_close($stmt_delete_teacher);

    // 4. Delete the associated user account
    if ($user_id_to_delete) {
        $sql_delete_user = "DELETE FROM users WHERE user_id = ?";
        $stmt_delete_user = mysqli_prepare($conn, $sql_delete_user);
        mysqli_stmt_bind_param($stmt_delete_user, "i", $user_id_to_delete);
        if (!mysqli_stmt_execute($stmt_delete_user)) throw new Exception("Failed to delete user account.");
        mysqli_stmt_close($stmt_delete_user);
    }

    // 5. Log this action
    log_activity($conn, 'delete_teacher', $teacher_id_to_delete, $teacher_name);

    // 6. If all went well, commit the transaction
    mysqli_commit($conn);
    $_SESSION['import_message'] = "Teacher '{$teacher_name}' and all associated data have been deleted successfully.";
    $_SESSION['import_message_type'] = 'success';

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['import_message'] = "Error deleting teacher: " . $e->getMessage();
    $_SESSION['import_message_type'] = 'danger';
}

mysqli_close($conn);
header("Location: manage_teachers.php");
exit();