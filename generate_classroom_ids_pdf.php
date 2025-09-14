<?php
session_start();

require_once('tcpdf/tcpdf.php');
require_once('data/db_connect.php');
require_once('phpqrcode/qrlib.php');

// 1. Security Check: User must be logged in and have an appropriate role.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    die("<h1>Access Denied</h1><p>You do not have permission to perform this action.</p>");
}

// 2. Get classroom_id from GET parameter
if (!isset($_GET['classroom_id']) || !is_numeric($_GET['classroom_id'])) {
    die("<h1>Error</h1><p>Classroom ID not provided.</p>");
}
$classroom_id = intval($_GET['classroom_id']);

// 3. Fetch all students in the given classroom
$sql = "
    SELECT s.*, u.username, c.name as classroom_name, g.name as guardian_name, g.phone as guardian_phone
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN class_assignments ca ON s.student_id = ca.student_id
    JOIN classrooms c ON ca.classroom_id = c.classroom_id
    LEFT JOIN student_guardian_map sgm ON s.student_id = sgm.student_id
    LEFT JOIN guardians g ON sgm.guardian_id = g.guardian_id
    WHERE ca.classroom_id = ?
    GROUP BY s.student_id
    ORDER BY s.last_name, s.first_name
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $classroom_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$students = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

if (empty($students)) {
    die("<h1>No Students Found</h1><p>There are no students assigned to this classroom.</p>");
}

// 4. Create PDF
class MYPDF extends TCPDF {
    // Page header
    public function Header() {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'Classroom ID Cards - Batch Print', 0, false, 'C', 0, '', 0, false, 'M', 'M');
    }
    // Page footer
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Old Model School');
$pdf->SetTitle('Classroom ID Cards');
$pdf->SetMargins(10, 15, 10);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 15);

// --- Helper function to draw a single card ---
function draw_card($pdf, $student, $is_back = false) {
    // Card dimensions and styles
    $card_width_mm = 85.6;
    $card_height_mm = 53.98;

    // --- Watermark ---
    $watermark_img = 'assets/school_logo_watermark.png';
    if (file_exists($watermark_img)) {
        $pdf->setAlpha(0.1);
        // Center the watermark on the card area
        $wmark_w = 40;
        $wmark_h = 40;
        $wmark_x = $pdf->GetX() + (($card_width_mm - $wmark_w) / 2);
        $wmark_y = $pdf->GetY() + (($card_height_mm - $wmark_h) / 2);
        $pdf->Image($watermark_img, $wmark_x, $wmark_y, $wmark_w, $wmark_h, '', '', '', false, 300, '', false, false, 0);
        $pdf->setAlpha(1);
    }

    $html = '';
    if (!$is_back) {
        // --- FRONT OF CARD ---
        $photo_path = (!empty($student['photo_path']) && file_exists($student['photo_path'])) ? $student['photo_path'] : 'assets/default-avatar.png';
        $issue_date = new DateTime($student['registered_at']);
        $expiry_date = (new DateTime($student['registered_at']))->modify('+1 year');

        // Generate QR Code if GD is available
        $qr_dir = 'uploads/qrcodes/'; // Define directory first
        $qr_code_path = $qr_dir . 'user_' . $student['user_id'] . '.png';
        if (extension_loaded('gd') && function_exists('gd_info')) {
            if (!is_dir($qr_dir)) { mkdir($qr_dir, 0755, true); }
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domain_name = $_SERVER['HTTP_HOST'];
            $qr_content = $protocol . $domain_name . dirname($_SERVER['PHP_SELF']) . '/view_profile.php?user_id=' . $student['user_id'];
            if (!file_exists($qr_code_path)) {
                QRcode::png($qr_content, $qr_code_path, QR_ECLEVEL_L, 4);
            }
        }

        $qr_html = '';
        if (file_exists($qr_code_path)) {
            $qr_html = '<img src="'.$qr_code_path.'" style="float: left;" width="15mm" height="15mm">';
        }

        $html = '
        <div style="width:'.$card_width_mm.'mm; height:'.$card_height_mm.'mm; border: 0.5px solid #999; font-family: sans-serif; font-size: 6pt; position: relative; overflow: hidden;">
            <div style="text-align: center; border-bottom: 0.5px solid #ccc; padding-bottom: 2mm;">
                <h3 style="font-size: 9pt; margin: 0; padding: 0; font-weight: bold;">Old Model School</h3>
                <p style="font-size: 7pt; margin: 0; padding: 0;">Student Identification Card</p>
            </div>
            <div style="padding-top: 2mm;">
                <img src="'.$photo_path.'" style="width: 25mm; height: 30mm; border: 0.5px solid #999; float: left;">
                <div style="float: left; padding-left: 3mm;">
                    <p><strong>ID:</strong> '.htmlspecialchars($student['username']).'</p>
                    <p><strong>Name:</strong> '.htmlspecialchars(trim($student['first_name'].' '.$student['middle_name'].' '.$student['last_name'])).'</p>
                    <p><strong>Grade & Section:</strong> '.htmlspecialchars($student['grade_level']).' ('.htmlspecialchars($student['classroom_name'] ?? 'N/A').')</p>
                    <p><strong>Gender:</strong> '.htmlspecialchars(ucfirst($student['gender'])).'</p>
                    <p><strong>Age:</strong> '.htmlspecialchars($student['age']).'</p>
                    <p><strong>Nationality:</strong> '.htmlspecialchars($student['nationality'] ?? 'N/A').'</p>
                </div>
            </div>
            <div style="position: absolute; bottom: 2mm; width: 77.6mm;">
                '.$qr_html.'
                <div style="float: right; text-align: right; font-size: 5pt;">
                    <p>Issued: '.$issue_date->format('Y-m-d').'</p>
                    <p>Expires: '.$expiry_date->format('Y-m-d').'</p>
                </div>
            </div>
        </div>';
    } else {
        // --- BACK OF CARD ---
        $emergency_contact_name = $student['guardian_name'] ?? 'Emergency Contact';
        $emergency_contact_phone = $student['guardian_phone'] ?? $student['emergency_contact'];

        $html = '
        <div style="width:'.$card_width_mm.'mm; height:'.$card_height_mm.'mm; border: 0.5px solid #999; font-family: sans-serif; font-size: 6pt; position: relative; overflow: hidden; padding-top: 5mm;">
            <div style="text-align: center; font-weight: bold; font-size: 8pt; margin-bottom: 4mm;">IN CASE OF EMERGENCY</div>
            <div style="font-size: 7pt; line-height: 1.6;">
                <p><strong>Name:</strong> '.htmlspecialchars($emergency_contact_name).'</p>
                <p><strong>Phone:</strong> '.htmlspecialchars($emergency_contact_phone).'</p>
            </div>
            <div style="position: absolute; bottom: 3mm; width: 77.6mm; text-align: center; font-size: 6pt; color: #555;">
                If found, please return to:<br>
                <strong>Old Model School</strong><br>
                123 Education Way, Debre Markos, Ethiopia<br>
                Phone: +251 58 775 0000
            </div>
        </div>';
    }
    $pdf->writeHTMLCell($card_width_mm, $card_height_mm, '', '', $html, 0, 0, false, true, 'L', true);
}

// --- RENDER PDF ---
$cards_per_page = 10;
$card_margin_x = 5;
$card_margin_y = 5;
$card_width_mm = 85.6;
$card_height_mm = 53.98;

// --- Render Fronts ---
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 15, 'ID Cards - Front Side', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 8);
$x_start = $pdf->GetX();
$y_start = $pdf->GetY();
$x = $x_start;
$y = $y_start;
foreach ($students as $i => $student) {
    if (($i > 0) && ($i % $cards_per_page == 0)) {
        $pdf->AddPage();
        $x = $x_start;
        $y = $y_start;
    }
    $pdf->SetXY($x, $y);
    draw_card($pdf, $student, false);
    $x += $card_width_mm + $card_margin_x;
    if (($i + 1) % 2 == 0) { // 2 cards per row
        $x = $x_start;
        $y += $card_height_mm + $card_margin_y;
    }
}

// --- Render Backs ---
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 15, 'ID Cards - Back Side', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 8);
$x = $x_start;
$y = $y_start;
foreach ($students as $i => $student) {
    if (($i > 0) && ($i % $cards_per_page == 0)) {
        $pdf->AddPage();
        $x = $x_start;
        $y = $y_start;
    }
    $pdf->SetXY($x, $y);
    draw_card($pdf, $student, true);
    $x += $card_width_mm + $card_margin_x;
    if (($i + 1) % 2 == 0) { // 2 cards per row
        $x = $x_start;
        $y += $card_height_mm + $card_margin_y;
    }
}

mysqli_close($conn);
$pdf->Output('Classroom_IDs.pdf', 'I');
?>