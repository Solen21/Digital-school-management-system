<?php
session_start();

require_once('tcpdf/tcpdf.php');
require_once('data/db_connect.php');

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    die("<h1>Access Denied</h1><p>Please log in to view this page.</p>");
}

// 2. Get student_id from GET parameter
if (!isset($_GET['student_id'])) {
    die("<h1>Error</h1><p>Student ID not provided.</p>");
}
$student_id = intval($_GET['student_id']);

// 3. Authorization Check
$is_authorized = false;
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if (in_array($user_role, ['admin', 'director', 'teacher'])) {
    $is_authorized = true; // Staff can view any report
} elseif (in_array($user_role, ['student', 'rep'])) {
    $sql_check = "SELECT student_id FROM students WHERE user_id = ?";
    $stmt_check = mysqli_prepare($conn, $sql_check);
    mysqli_stmt_bind_param($stmt_check, "i", $user_id);
    mysqli_stmt_execute($stmt_check);
    $result_check = mysqli_stmt_get_result($stmt_check);
    if ($row = mysqli_fetch_assoc($result_check)) {
        if ($row['student_id'] == $student_id) {
            $is_authorized = true;
        }
    }
    mysqli_stmt_close($stmt_check);
} elseif ($user_role === 'guardian') {
    $sql_verify = "SELECT COUNT(*) as count FROM student_guardian_map sgm JOIN guardians g ON sgm.guardian_id = g.guardian_id WHERE g.user_id = ? AND sgm.student_id = ?";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $user_id, $student_id);
    mysqli_stmt_execute($stmt_verify);
    $result_verify = mysqli_stmt_get_result($stmt_verify);
    if (mysqli_fetch_assoc($result_verify)['count'] > 0) {
        $is_authorized = true;
    }
    mysqli_stmt_close($stmt_verify);
}

if (!$is_authorized) {
    die("<h1>Access Denied</h1><p>You are not authorized to view this student's report.</p>");
}

// 4. Fetch Data
// Fetch student details
$sql_student = "SELECT s.*, c.name as classroom_name FROM students s LEFT JOIN class_assignments ca ON s.student_id = ca.student_id LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id WHERE s.student_id = ?";
$stmt_student = mysqli_prepare($conn, $sql_student);
mysqli_stmt_bind_param($stmt_student, "i", $student_id);
mysqli_stmt_execute($stmt_student);
$student_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_student));
mysqli_stmt_close($stmt_student);

if (!$student_data) {
    die("<h1>Error</h1><p>Student with ID {$student_id} not found.</p>");
}

// Fetch grades
$sql_grades = "SELECT g.*, s.name AS subject_name FROM grades g JOIN subjects s ON g.subject_id = s.subject_id WHERE g.student_id = ? ORDER BY s.name ASC";
$stmt_grades = mysqli_prepare($conn, $sql_grades);
mysqli_stmt_bind_param($stmt_grades, "i", $student_id);
mysqli_stmt_execute($stmt_grades);
$result_grades = mysqli_stmt_get_result($stmt_grades);
$grades = [];
$total_sum = 0;
$grade_count = 0;
while ($row = mysqli_fetch_assoc($result_grades)) {
    $grades[] = $row;
    $total_sum += $row['total'];
    $grade_count++;
}
$overall_average = ($grade_count > 0) ? ($total_sum / $grade_count) : 0;
mysqli_stmt_close($stmt_grades);
mysqli_close($conn);

// 5. Create PDF
class MYPDF extends TCPDF {
    public function Header() {
        // School Seal - assuming the seal is stored in 'images/school_seal.png'
        // The '@' will suppress errors if the file doesn't exist.
        $image_file = 'images/school_seal.png';
        if (@file_exists($image_file)) {
            $this->Image($image_file, 15, 10, 25, 25, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }

        // School Name and Report Title
        $this->SetY(12);
        $this->SetX(45); // Start text after the image
        $this->SetFont('helvetica', 'B', 18);
        $this->Cell(0, 10, 'Old Model School', 0, true, 'L');
        $this->SetX(45);
        $this->SetFont('helvetica', '', 12);
        $this->Cell(0, 10, 'Student Grade Report', 0, true, 'L');

        // Header line
        $this->Line(15, 38, $this->getPageWidth() - 15, 38);
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Old Model School');
$pdf->SetTitle('Grade Report for ' . $student_data['first_name']);
$pdf->SetMargins(PDF_MARGIN_LEFT, 40, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->AddPage();

$student_info_html = '
    <h3>Student Information</h3>
    <table cellpadding="5" border="0">
        <tr><td width="25%"><b>Name:</b></td><td width="75%">' . htmlspecialchars($student_data['first_name'] . ' ' . $student_data['middle_name'] . ' ' . $student_data['last_name']) . '</td></tr>
        <tr><td><b>Grade Level:</b></td><td>' . htmlspecialchars($student_data['grade_level']) . '</td></tr>
        <tr><td><b>Classroom:</b></td><td>' . htmlspecialchars($student_data['classroom_name'] ?? 'N/A') . '</td></tr>
        <tr><td><b>Report Date:</b></td><td>' . date('F j, Y') . '</td></tr>
    </table>';
$pdf->writeHTML($student_info_html, true, false, true, false, '');

$grades_html = '
    <h3>Detailed Grade Report</h3>
    <table cellpadding="5" border="1" style="border-color: #cccccc;">
        <tr style="background-color:#f2f2f2; font-weight:bold;">
            <th width="30%">Subject</th><th width="10%" align="center">Test</th><th width="10%" align="center">Assign.</th><th width="10%" align="center">Activity</th><th width="10%" align="center">Exercise</th><th width="10%" align="center">Midterm</th><th width_old="10%" align="center">Final</th><th width="10%" align="center">Total</th>
        </tr>';

if (empty($grades)) {
    $grades_html .= '<tr><td colspan="8" align="center">No grades have been entered yet.</td></tr>';
} else {
    foreach ($grades as $grade) {
        $grades_html .= '<tr>
            <td>' . htmlspecialchars($grade['subject_name']) . '</td>
            <td align="center">' . htmlspecialchars($grade['test']) . '</td>
            <td align="center">' . htmlspecialchars($grade['assignment']) . '</td>
            <td align="center">' . htmlspecialchars($grade['activity']) . '</td>
            <td align="center">' . htmlspecialchars($grade['exercise']) . '</td>
            <td align="center">' . htmlspecialchars($grade['midterm']) . '</td>
            <td align="center">' . htmlspecialchars($grade['final']) . '</td>
            <td align="center"><b>' . htmlspecialchars($grade['total']) . '</b></td>
        </tr>';
    }
}
$grades_html .= '</table>';
$pdf->writeHTML($grades_html, true, false, true, false, '');

$summary_html = '<div style="text-align:right; margin-top: 20px;"><font size="12"><b>Overall Average: ' . number_format($overall_average, 2) . '%</b></font></div>';
$pdf->writeHTML($summary_html, true, false, true, false, '');

$pdf->Ln(25); // Add vertical space

// Signature Line
$line_y = $pdf->GetY();
$pdf->Line(120, $line_y, $pdf->getPageWidth() - 15, $line_y);
$pdf->Ln(2);
$pdf->SetFont('helvetica', 'I', 10);
$pdf->Cell(0, 10, "School Director's Signature", 0, false, 'R');

$pdf->Output('grade_report_' . $student_id . '.pdf', 'I');
?>