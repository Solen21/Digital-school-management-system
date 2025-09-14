<?php
session_start();
header('Content-Type: application/json');

// Security Check: User must be logged in and an admin/director
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit();
}

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid User ID']);
    exit();
}

require_once 'data/db_connect.php';

$user_id = intval($_GET['user_id']);
$details = [];

// Fetch user role first
$stmt_role = mysqli_prepare($conn, "SELECT role FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($stmt_role, "i", $user_id);
mysqli_stmt_execute($stmt_role);
$result_role = mysqli_stmt_get_result($stmt_role);
$user = mysqli_fetch_assoc($result_role);
mysqli_stmt_close($stmt_role);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit();
}

$details['role'] = ucfirst($user['role']);

if (in_array($user['role'], ['student', 'rep'])) {
    $sql = "
        SELECT s.first_name, s.middle_name, s.last_name, s.email, s.phone, s.status, s.grade_level, c.name as classroom_name, g.name as guardian_name, s.user_id
        FROM students s
        LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
        LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
        LEFT JOIN student_guardian_map sgm ON s.student_id = sgm.student_id
        LEFT JOIN guardians g ON sgm.guardian_id = g.guardian_id
        WHERE s.user_id = ?
        GROUP BY s.student_id
    ";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    if ($data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
        $details['name'] = trim($data['first_name'] . ' ' . $data['middle_name'] . ' ' . $data['last_name']);
        $details['editable'] = [
            'phone' => $data['phone'],
            'email' => $data['email'] ?? '',
            'status' => $data['status']
        ];
        $details['readonly'] = [
            'Grade' => $data['grade_level'],
            'Section' => $data['classroom_name'] ?? 'Unassigned',
            'Guardian' => $data['guardian_name'] ?? 'N/A'
        ];
    }
    mysqli_stmt_close($stmt);
} elseif ($user['role'] === 'teacher') {
    $sql = "SELECT first_name, middle_name, last_name, email, phone, status, hire_date, user_id FROM teachers WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    if ($data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
        $details['name'] = trim($data['first_name'] . ' ' . $data['middle_name'] . ' ' . $data['last_name']);
        $details['editable'] = [
            'phone' => $data['phone'],
            'email' => $data['email'] ?? '',
            'status' => $data['status']
        ];
        $details['readonly'] = [
            'Hire Date' => date('M j, Y', strtotime($data['hire_date']))
        ];
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);

echo json_encode($details);