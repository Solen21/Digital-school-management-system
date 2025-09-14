<?php
session_start();

// 1. Security Check: User must be logged in and be an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// 2. Include necessary files
require_once 'functions.php';
require_once 'data/db_connect.php';

require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$message = '';
$message_type = 'error';

if (isset($_FILES['student_file']) && $_FILES['student_file']['error'] === UPLOAD_ERR_OK) {
    $file_tmp_path = $_FILES['student_file']['tmp_name'];
    $file_name = $_FILES['student_file']['name'];
    $file_size = $_FILES['student_file']['size'];
    $file_type = $_FILES['student_file']['type'];
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    $allowed_extensions = ['xlsx'];

    if (in_array($file_extension, $allowed_extensions)) {
        mysqli_begin_transaction($conn);
        try {
            $spreadsheet = IOFactory::load($file_tmp_path);
            $sheet = $spreadsheet->getActiveSheet();
            $highest_row = $sheet->getHighestRow();
            
            $imported_count = 0;
            $error_rows = [];

            // Prepare statements
            $sql_user = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
            $stmt_user = mysqli_prepare($conn, $sql_user);

            $sql_student = "INSERT INTO students (user_id, first_name, middle_name, last_name, date_of_birth, age, gender, nationality, religion, city, wereda, kebele, phone, email, emergency_contact, blood_type, grade_level, stream, last_school, last_score, last_grade, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_student = mysqli_prepare($conn, $sql_student);

            // Loop through each row of the spreadsheet
            for ($row_num = 2; $row_num <= $highest_row; $row_num++) {
                // Read data from spreadsheet row
                $first_name = $sheet->getCell('A' . $row_num)->getValue();
                $middle_name = $sheet->getCell('B' . $row_num)->getValue();
                $last_name = $sheet->getCell('C' . $row_num)->getValue();
                $dob_excel = $sheet->getCell('D' . $row_num)->getValue();
                $gender = strtolower($sheet->getCell('E' . $row_num)->getValue());
                $nationality = $sheet->getCell('F' . $row_num)->getValue();
                $religion = $sheet->getCell('G' . $row_num)->getValue();
                $city = $sheet->getCell('H' . $row_num)->getValue();
                $wereda = $sheet->getCell('I' . $row_num)->getValue();
                $kebele = $sheet->getCell('J' . $row_num)->getValue();
                $phone = $sheet->getCell('K' . $row_num)->getValue();
                $email = $sheet->getCell('L' . $row_num)->getValue();
                $emergency_contact = $sheet->getCell('M' . $row_num)->getValue();
                $blood_type = $sheet->getCell('N' . $row_num)->getValue();
                $grade_level = $sheet->getCell('O' . $row_num)->getValue();
                $stream = $sheet->getCell('P' . $row_num)->getValue();
                $last_school = $sheet->getCell('Q' . $row_num)->getValue();
                $last_score = $sheet->getCell('R' . $row_num)->getValue();
                $last_grade = $sheet->getCell('S' . $row_num)->getValue();

                // Basic validation
                if (empty($first_name) || empty($last_name) || empty($dob_excel) || empty($grade_level)) {
                    $error_rows[] = $row_num;
                    continue; // Skip this row
                }

                // Convert Excel date to PHP date
                $date_of_birth = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dob_excel)->format('Y-m-d');
                
                // Calculate age
                $birthDate = new DateTime($date_of_birth);
                $today = new DateTime();
                $age = $today->diff($birthDate)->y;

                // --- Create User ---
                mysqli_query($conn, "LOCK TABLES users WRITE");
                $result_max_id = mysqli_query($conn, "SELECT MAX(user_id) as last_id FROM users");
                $row_max_id = mysqli_fetch_assoc($result_max_id);
                $next_id = ($row_max_id['last_id'] ?? 0) + 1;
                $user_username = str_pad($next_id, 6, '0', STR_PAD_LEFT);
                mysqli_query($conn, "UNLOCK TABLES");

                $user_password = password_hash(strtolower(trim($last_name)) . '@123', PASSWORD_DEFAULT);
                $user_role = 'student';

                mysqli_stmt_bind_param($stmt_user, "sss", $user_username, $user_password, $user_role);
                if (!mysqli_stmt_execute($stmt_user)) {
                    throw new Exception("Failed to create user for row $row_num: " . mysqli_stmt_error($stmt_user));
                }
                $new_user_id = mysqli_insert_id($conn);

                // --- Create Student ---
                mysqli_stmt_bind_param($stmt_student, "issssisssssssisssssdss",
                    $new_user_id, $first_name, $middle_name, $last_name, $date_of_birth, $age,
                    $gender, $nationality, $religion, $city, $wereda, $kebele, $phone, $email,
                    $emergency_contact, $blood_type, $grade_level, $stream, $last_school,
                    $last_score, $last_grade, $user_password
                );

                if (!mysqli_stmt_execute($stmt_student)) {
                    throw new Exception("Failed to create student for row $row_num: " . mysqli_stmt_error($stmt_student));
                }

                $imported_count++;
            }

            mysqli_commit($conn);
            log_activity($conn, 'import_students', null, null, "Imported $imported_count students.");
            $message = "Successfully imported $imported_count students.";
            if (!empty($error_rows)) {
                $message .= " The following rows had errors and were skipped: " . implode(', ', $error_rows);
            }
            $message_type = 'success';

            mysqli_stmt_close($stmt_user);
            mysqli_stmt_close($stmt_student);

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "An error occurred during import: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Invalid file type. Please upload an .xlsx file.";
        $message_type = 'error';
    }
} else {
    $message = "No file was uploaded or an error occurred during upload.";
    $message_type = 'error';
}

mysqli_close($conn);

// Redirect back with a message
$_SESSION['import_message'] = $message;
$_SESSION['import_message_type'] = $message_type;
header("Location: manage_students.php");
exit();
?>