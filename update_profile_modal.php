<?php
session_start();
header('Content-Type: application/json');

// Security Check: User must be logged in and have an appropriate role.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied']);
    exit();
}

require_once 'data/db_connect.php';

$user_id = $_POST['user_id'] ?? null;
$role = $_POST['role'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$status = $_POST['status'] ?? '';

if (empty($user_id) || !is_numeric($user_id) || empty($role)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit();
}

$table = '';
if ($role == 'Student' || $role == 'Rep') {
    $table = 'students';
} elseif ($role == 'Teacher') {
    $table = 'teachers';
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot edit this user type.']);
    exit();
}

$sql = "UPDATE {$table} SET phone = ?, email = ?, status = ? WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssi", $phone, $email, $status, $user_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database update failed.']);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);