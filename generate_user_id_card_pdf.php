<?php
session_start();

require_once('tcpdf/tcpdf.php');
require_once('data/db_connect.php');
require_once('phpqrcode/qrlib.php');

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    die("<h1>Access Denied</h1><p>Please log in to view this page.</p>");
}

// 2. Get user_id from GET parameter
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    die("<h1>Error</h1><p>User ID not provided.</p>");
}
$profile_user_id = intval($_GET['user_id']);

// 3. Authorization Check (simplified for this script, more complex logic is in view_profile.php)
$is_authorized = false;
if (in_array($_SESSION['role'], ['admin', 'director']) || ($_SESSION['user_id'] == $profile_user_id)) {
    $is_authorized = true;
}
if (!$is_authorized) {
    die("<h1>Access Denied</h1><p>You are not authorized to print this ID card.</p>");
}

// 4. Fetch user data from the database.
$sql = "
    SELECT
        u.user_id, u.username, u.role,
        s.student_id, s.first_name as s_fname, s.middle_name as s_mname, s.last_name as s_lname, s.age as s_age, s.gender as s_gender, s.grade_level, s.blood_type, s.photo_path as s_photo, s.registered_at as s_reg_at, s.emergency_contact as s_emergency, s.nationality as s_nationality,
        t.first_name as t_fname, t.middle_name as t_mname, t.last_name as t_lname, t.gender as t_gender, t.photo_path as t_photo, t.hire_date, t.phone as t_phone, t.nationality as t_nationality,
        g.name as g_name, g.phone as g_phone,
        c.name as classroom_name,
        guardian.name as guardian_for_student_name, guardian.phone as guardian_for_student_phone
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN teachers t ON u.user_id = t.user_id
    LEFT JOIN guardians g ON u.user_id = g.user_id
    LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
    LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
    LEFT JOIN student_guardian_map sgm ON s.student_id = sgm.student_id
    LEFT JOIN guardians as guardian ON sgm.guardian_id = guardian.guardian_id
    WHERE u.user_id = ?
    GROUP BY u.user_id
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $profile_user_id);
mysqli_stmt_execute($stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user_data) {
    die("<h1>Error</h1><p>User not found.</p>");
}

// 5. Process data for the ID card based on role
$id_card_data = [];
$emergency_contact_name = 'School Office';
$emergency_contact_phone = '+251 58 775 0000';

switch ($user_data['role']) {
    case 'student':
    case 'rep':
        $id_card_data['name'] = trim($user_data['s_fname'] . ' ' . $user_data['s_mname'] . ' ' . $user_data['s_lname']);
        $id_card_data['photo'] = $user_data['s_photo'];
        $id_card_data['details'] = [
            'Grade & Section' => $user_data['grade_level'] . ' (' . ($user_data['classroom_name'] ?? 'N/A') . ')',
            'Age' => $user_data['s_age'],
            'Gender' => ucfirst($user_data['s_gender']),
            'Nationality' => $user_data['s_nationality'] ?? 'N/A'
        ];
        $id_card_data['issue_date'] = new DateTime($user_data['s_reg_at']);
        $emergency_contact_name = $user_data['guardian_for_student_name'] ?? 'Emergency Contact';
        $emergency_contact_phone = $user_data['guardian_for_student_phone'] ?? $user_data['s_emergency'];
        break;
    case 'teacher':
        $id_card_data['name'] = trim($user_data['t_fname'] . ' ' . $user_data['t_mname'] . ' ' . $user_data['t_lname']);
        $id_card_data['photo'] = $user_data['t_photo'];
        $id_card_data['details'] = [
            'Role' => 'Teacher',
            'Gender' => ucfirst($user_data['t_gender']),
            'Nationality' => $user_data['t_nationality'] ?? 'N/A'
        ];
        $id_card_data['issue_date'] = new DateTime($user_data['hire_date']);
        $emergency_contact_name = 'School Office';
        $emergency_contact_phone = $user_data['t_phone'];
        break;
    default: // admin, director, guardian
        $id_card_data['name'] = $user_data['g_name'] ?? $user_data['username'];
        $id_card_data['photo'] = null;
        $id_card_data['details'] = ['Role' => ucfirst($user_data['role'])];
        $id_card_data['issue_date'] = new DateTime();
        $emergency_contact_phone = $user_data['g_phone'] ?? 'School Office';
        break;
}

// 6. Generate QR Code
$qr_dir = 'uploads/qrcodes/'; // Define directory first
$qr_code_path = null;
if (extension_loaded('gd') && function_exists('gd_info')) { // Check if GD extension is available
    $qr_code_path = $qr_dir . 'user_' . $profile_user_id . '.png';
    if (!is_dir($qr_dir)) { mkdir($qr_dir, 0755, true); } // Create directory if it doesn't exist
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain_name = $_SERVER['HTTP_HOST'];
    $qr_content = $protocol . $domain_name . dirname($_SERVER['PHP_SELF']) . '/view_profile.php?user_id=' . $profile_user_id;
    QRcode::png($qr_content, $qr_code_path, QR_ECLEVEL_L, 4);
}


mysqli_close($conn);

// 7. Create PDF
class IDCardPDF extends TCPDF {
    public function Header() {}
    public function Footer() {}
}

$card_width_mm = 85.6;
$card_height_mm = 53.98;
$pdf = new IDCardPDF('L', 'mm', array($card_width_mm, $card_height_mm), true, 'UTF-8', false);
$pdf->SetFont('helvetica', '', 10);
$pdf->SetCreator(PDF_CREATOR); // Standard credit card size
$pdf->SetAuthor('Old Model School');
$pdf->SetTitle('ID Card - ' . $id_card_data['name']);
$pdf->SetMargins(4, 4, 4);
$pdf->SetAutoPageBreak(false);

// --- FRONT OF CARD ---
$pdf->AddPage();

// Set a background color for the card
$pdf->SetFillColor(245, 247, 250); // A light grey-blue
$pdf->Rect(0, 0, $card_width_mm, $card_height_mm, 'F');

$watermark_img = 'assets/school_logo_watermark.png';
if (file_exists($watermark_img)) {
    $pdf->setAlpha(0.1);
    // Center the watermark on the card. The page IS the card in this script.
    $pdf->Image($watermark_img, ($card_width_mm - 40) / 2, ($card_height_mm - 40) / 2, 40, 40, '', '', '', false, 300, '', false, false, 0);
    $pdf->setAlpha(1);
}

$photo_path = (!empty($id_card_data['photo']) && file_exists($id_card_data['photo'])) ? $id_card_data['photo'] : 'assets/default-avatar.png';
$issue_date = $id_card_data['issue_date'];
$expiry_date = (clone $issue_date)->modify('+1 year');

$details_html = '';

$role_badge_color = '#6c757d'; // Gray for general roles
if($user_data['role'] == 'student' || $user_data['role'] == 'rep') $role_badge_color = '#0d6efd'; // Blue for students
if($user_data['role'] == 'teacher') $role_badge_color = '#198754'; // Green for teachers

$qr_html = '';
if (file_exists($qr_code_path)) {
    $qr_html = '<img src="'.$qr_code_path.'" style="width:18mm; height:18mm;">';
}

$html_front = '
<style>
    .card-container { width: 100%; height: 100%; font-family: sans-serif; position: relative; }
    .header-bar { background-color: #003366; color: white; text-align: center; padding: 2mm; font-size: 8pt; font-weight: bold; }
    .photo-col { text-align: center; }
    .photo { width: 25mm; height: 30mm; border: 1mm solid white; border-radius: 2mm; box-shadow: 0 0 5px rgba(0,0,0,0.5); }
    .name { font-size: 11pt; font-weight: bold; color: #003366; margin: 0; padding: 0; }
    .role-badge { background-color: '.$role_badge_color.'; color: white; font-size: 6pt; padding: 0.5mm 1.5mm; border-radius: 2mm; text-transform: uppercase; }
    .details-table { font-size: 7pt; line-height: 1.4; }
    .details-table td.label { font-weight: bold; color: #555; width: 25%; }
    .details-table td.value { color: #000; width: 75%; }
    .footer { position: absolute; bottom: 1mm; width: 77.6mm; font-size: 5.5pt; color: #555; }
</style>
<div class="card-container">
    <div class="header-bar">OLD MODEL SCHOOL</div>
    <table style="width:100%; margin-top: 3mm;">
        <tr>
            <td width="35%" class="photo-col">
                <img src="'.$photo_path.'" class="photo"><br>
                <span class="role-badge">'.htmlspecialchars(ucfirst($user_data['role'])).'</span>
            </td>
            <td width="65%">
                <div class="name">'.htmlspecialchars($id_card_data['name']).'</div>
                <hr style="color: #ccc;">
                <table class="details-table">
                    <tr><td class="label">ID Number:</td><td class="value">'.htmlspecialchars($user_data['username']).'</td></tr>
                    <tr><td class="label">Grade:</td><td class="value">'.htmlspecialchars($user_data['grade_level'] ?? 'N/A').'</td></tr>
                    <tr><td class="label">Section:</td><td class="value">'.htmlspecialchars($user_data['classroom_name'] ?? 'N/A').'</td></tr>
                    <tr><td class="label">Age & Sex:</td><td class="value">'.htmlspecialchars($user_data['s_age'] ?? 'N/A').' / '.htmlspecialchars(ucfirst($user_data['s_gender'] ?? 'N/A')).'</td></tr>
                </table>
            </td>
        </tr>
    </table>
    <div class="footer">
        <table style="width:100%;"><tr><td style="width: 60%; font-size: 5pt;">Issued: '.$issue_date->format('Y-m-d').' &nbsp; &nbsp; Expires: '.$expiry_date->format('Y-m-d').'</td><td style="width: 40%; text-align:right;">'.$qr_html.'</td></tr></table>
    </div>
</div>';
$pdf->writeHTML($html_front, true, false, true, false, '');

// --- BACK OF CARD ---
$pdf->AddPage();

// Set a background color for the card
$pdf->SetFillColor(245, 247, 250); // A light grey-blue
$pdf->Rect(0, 0, $card_width_mm, $card_height_mm, 'F');

if (file_exists($watermark_img)) {
    $pdf->setAlpha(0.1);
    // Center the watermark on the card. The page IS the card in this script.
    $pdf->Image($watermark_img, ($card_width_mm - 40) / 2, ($card_height_mm - 40) / 2, 40, 40, '', '', '', false, 300, '', false, false, 0);
    $pdf->setAlpha(1);
}

$html_back = '
<style>
    .id-card-back { width: 100%; height: 100%; position: relative; font-family: sans-serif; font-size: 7pt; padding-top: 5mm; }
    .back-header { text-align: center; font-weight: bold; font-size: 9pt; margin-bottom: 4mm; color: #003366; }
    .back-content { line-height: 1.6; }
    .back-content p { margin: 0; padding: 0; }
    .back-content strong { font-weight: bold; }
    .back-footer { position: absolute; bottom: 3mm; width: 77.6mm; text-align: center; font-size: 6.5pt; color: #555; line-height: 1.3; }
</style>
<div class="id-card-back">
    <div class="back-header">IN CASE OF EMERGENCY</div>
    <div class="back-content">
        <p><strong>Contact Name:</strong> '.htmlspecialchars($emergency_contact_name).'</p>
        <p><strong>Contact Phone:</strong> '.htmlspecialchars($emergency_contact_phone).'</p>
    </div>
    <div class="back-footer">
        This card is the property of Old Model School. If found, please return to:<br>
        <strong>Old Model School</strong><br>
        123 Education Way, Debre Markos, Ethiopia<br>
        Phone: +251 58 775 0000
    </div>
</div>';
$pdf->writeHTML($html_back, true, false, true, false, '');

$pdf->Output('ID_Card_'.$profile_user_id.'.pdf', 'I');
?>