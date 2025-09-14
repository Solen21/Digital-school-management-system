<?php
session_start();
header('Content-Type: application/json');

// Basic security check: ensure user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['exists' => false, 'message' => 'Access Denied']);
    exit();
}

require_once 'data/db_connect.php';

$response = ['exists' => false];

if (isset($_POST['phone'])) {
    $phone = $_POST['phone'];

    $sql = "SELECT guardian_id, name, email FROM guardians WHERE phone = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $phone);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($guardian = mysqli_fetch_assoc($result)) {
            $response['exists'] = true;
            $response['guardian'] = $guardian;
        }
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($conn);
echo json_encode($response);

?>