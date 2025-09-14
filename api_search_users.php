<?php
session_start();
header('Content-Type: application/json');

// 1. Security: Ensure user is logged in.
if (!isset($_SESSION["user_id"])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

// 2. Validate search term.
$term = $_GET['term'] ?? '';
if (strlen(trim($term)) < 2) {
    echo json_encode([]); // Return empty array if term is too short to reduce server load.
    exit();
}

require_once 'data/db_connect.php';

$current_user_id = $_SESSION['user_id'];
$search_term = '%' . $term . '%';

// 3. Fetch users matching the term, excluding the current user.
$sql = "SELECT user_id, username, role FROM users WHERE username LIKE ? AND user_id != ? LIMIT 10";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "si", $search_term, $current_user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$users = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
mysqli_close($conn);

echo json_encode($users);