<?php
session_start();

require_once('data/db_connect.php');
require_once('tcpdf/tcpdf.php');

// 1. Security Check: User must be logged in and have an appropriate role.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    die("<h1>Access Denied</h1><p>You do not have permission to perform this action.</p>");
}

// 2. Validate POST data
if ($_SERVER["REQUEST_METHOD"] != "POST" || empty($_POST['visitor_name']) || empty($_POST['reason_for_visit']) || empty($_POST['person_to_visit'])) {
    die("<h1>Error</h1><p>Missing required visitor information.</p>");
}

$visitor_name = $_POST['visitor_name'];
$reason_for_visit = $_POST['reason_for_visit'];
$person_to_visit = $_POST['person_to_visit'];
$issue_date = new DateTime();
$issued_by = $_SESSION['user_id'];

// --- Log the issued pass to the database ---
$sql_log = "INSERT INTO visitor_passes (visitor_name, reason_for_visit, person_to_visit, issued_by_user_id) VALUES (?, ?, ?, ?)";
$stmt_log = mysqli_prepare($conn, $sql_log);
if ($stmt_log) {
    mysqli_stmt_bind_param($stmt_log, "sssi", $visitor_name, $reason_for_visit, $person_to_visit, $issued_by);
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);
}
// We don't stop PDF generation if logging fails, but you could add error handling here.
// --- End Logging ---

mysqli_close($conn);

// 3. Create PDF
class VisitorPassPDF extends TCPDF {
    public function Header() {}
    public function Footer() {}
}

// Standard CR80 ID card size
$card_width_mm = 85.6;
$card_height_mm = 53.98;

$pdf = new VisitorPassPDF('L', 'mm', array($card_width_mm, $card_height_mm), true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Old Model School');
$pdf->SetTitle('Visitor Pass - ' . $visitor_name);
$pdf->SetMargins(4, 4, 4);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

// --- Watermark ---
$watermark_img = 'assets/school_logo_watermark.png';
if (file_exists($watermark_img)) {
    $pdf->setAlpha(0.1);
    $pdf->Image($watermark_img, ($card_width_mm - 40) / 2, ($card_height_mm - 40) / 2, 40, 40, '', '', '', false, 300, '', false, false, 0);
    $pdf->setAlpha(1);
}

$logo_path = 'assets/school_logo.png';
$logo_html = file_exists($logo_path) ? '<img src="'.$logo_path.'" style="width: 10mm; height: 10mm; float: left; margin-right: 2mm;">' : '';

$html = '
<div style="border: 0.5px solid #000; width: 100%; height: 100%; position: relative; font-family: sans-serif; font-size: 7pt;">
    <div style="border-bottom: 0.5px solid #ccc; padding-bottom: 2mm; overflow: auto;">
        '.$logo_html.'
        <div style="text-align: center;">
            <h3 style="font-size: 10pt; margin: 0; padding: 0; font-weight: bold;">Old Model School</h3>
            <p style="font-size: 8pt; margin: 0; padding: 0; font-weight: bold; color: #d00;">VISITOR PASS</p>
        </div>
    </div>
    <div style="padding-top: 3mm; line-height: 1.6;">
        <p><strong>Name:</strong> '.htmlspecialchars($visitor_name).'</p>
        <p><strong>To Visit:</strong> '.htmlspecialchars($person_to_visit).'</p>
        <p><strong>Reason:</strong> '.htmlspecialchars($reason_for_visit).'</p>
    </div>
    <div style="position: absolute; bottom: 2mm; width: 77.6mm;">
        <div style="float: left; font-size: 6pt;">
            <p><strong>Date:</strong> '.$issue_date->format('Y-m-d').'</p>
            <p><strong>Valid Until:</strong> '.$issue_date->format('Y-m-d').' 5:00 PM</p>
        </div>
        <div style="float: right; text-align: right; font-size: 6pt; color: #555;">
            <p>Please return this pass upon exit.</p>
            <p>Thank you for your cooperation.</p>
        </div>
    </div>
</div>';

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Output('Visitor_Pass_'.preg_replace("/[^a-zA-Z0-9]/", "", $visitor_name).'.pdf', 'I');
?>