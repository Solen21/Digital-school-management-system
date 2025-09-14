<?php
session_start();

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

// 2. Get user_id from GET parameter
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    http_response_code(400);
    die("Error: User ID not provided.");
}
$profile_user_id = intval($_GET['user_id']);

// 3. Authorization Check (same logic as view_profile.php)
$is_authorized = false;
$viewer_role = $_SESSION['role'];
$viewer_user_id = $_SESSION['user_id'];

if (in_array($viewer_role, ['admin', 'director'])) {
    $is_authorized = true;
} elseif ($viewer_user_id == $profile_user_id) {
    $is_authorized = true;
} elseif ($viewer_role === 'guardian') {
    $sql_verify = "
        SELECT COUNT(*) as count 
        FROM student_guardian_map sgm 
        JOIN guardians g ON sgm.guardian_id = g.guardian_id 
        JOIN students s ON sgm.student_id = s.student_id
        WHERE g.user_id = ? AND s.user_id = ?
    ";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $viewer_user_id, $profile_user_id);
    mysqli_stmt_execute($stmt_verify);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify))['count'] > 0) {
        $is_authorized = true;
    }
    mysqli_stmt_close($stmt_verify);
}

if (!$is_authorized) {
    http_response_code(403);
    die("Access Denied: You are not authorized to view this profile's contact card.");
}

// 4. Fetch the same comprehensive user data
$sql = "
    SELECT
        u.username, u.role,
        s.first_name as s_fname, s.middle_name as s_mname, s.last_name as s_lname, s.email as s_email, s.phone as s_phone,
        t.first_name as t_fname, t.middle_name as t_mname, t.last_name as t_lname, t.email as t_email, t.phone as t_phone,
        g.name as g_name, g.email as g_email, g.phone as g_phone
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN teachers t ON u.user_id = t.user_id
    LEFT JOIN guardians g ON u.user_id = g.user_id
    WHERE u.user_id = ?
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $profile_user_id);
mysqli_stmt_execute($stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
mysqli_close($conn);

if (!$user_data) {
    http_response_code(404);
    die("Error: User not found.");
}

// 5. Prepare vCard data based on role
$vcard = [];
$vcard['fn'] = $user_data['username']; // Full Name
$vcard['n'] = $user_data['username']; // Name (Last;First;Middle)
$vcard['email'] = '';
$vcard['tel'] = '';
$vcard['role'] = ucfirst($user_data['role']);

switch ($user_data['role']) {
    case 'student': case 'rep':
        $vcard['fn'] = trim($user_data['s_fname'] . ' ' . $user_data['s_mname'] . ' ' . $user_data['s_lname']);
        $vcard['n'] = "{$user_data['s_lname']};{$user_data['s_fname']};{$user_data['s_mname']}";
        $vcard['email'] = $user_data['s_email'];
        $vcard['tel'] = $user_data['s_phone'];
        break;
    case 'teacher':
        $vcard['fn'] = trim($user_data['t_fname'] . ' ' . $user_data['t_mname'] . ' ' . $user_data['t_lname']);
        $vcard['n'] = "{$user_data['t_lname']};{$user_data['t_fname']};{$user_data['t_mname']}";
        $vcard['email'] = $user_data['t_email'];
        $vcard['tel'] = $user_data['t_phone'];
        break;
    case 'guardian':
        $vcard['fn'] = $user_data['g_name'];
        $vcard['n'] = $user_data['g_name'];
        $vcard['email'] = $user_data['g_email'];
        $vcard['tel'] = $user_data['g_phone'];
        break;
}

// 6. Set headers and output the vCard file
$filename = preg_replace('/[^a-z0-9_]/i', '_', $vcard['fn']) . '.vcf';
header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo "BEGIN:VCARD\r\n";
echo "VERSION:3.0\r\n";
echo "FN:" . $vcard['fn'] . "\r\n";
echo "N:" . $vcard['n'] . "\r\n";
if (!empty($vcard['email'])) {
    echo "EMAIL;TYPE=INTERNET,WORK:" . $vcard['email'] . "\r\n";
}
if (!empty($vcard['tel'])) {
    echo "TEL;TYPE=WORK,VOICE:" . $vcard['tel'] . "\r\n";
}
echo "ROLE:" . $vcard['role'] . "\r\n";
echo "ORG:Old Model School\r\n";
echo "END:VCARD\r\n";

exit();