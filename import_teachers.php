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

if (isset($_FILES['teacher_file']) && $_FILES['teacher_file']['error'] === UPLOAD_ERR_OK) {
    $file_tmp_path = $_FILES['teacher_file']['tmp_name'];
    $file_name = $_FILES['teacher_file']['name'];
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

            $sql_teacher = "INSERT INTO teachers (user_id, first_name, middle_name, last_name, date_of_birth, gender, nationality, religion, city, wereda, kebele, phone, email, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_teacher = mysqli_prepare($conn, $sql_teacher);

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
                $hire_date_excel = $sheet->getCell('M' . $row_num)->getValue();

                // Basic validation
                if (empty($first_name) || empty($last_name) || empty($dob_excel) || empty($hire_date_excel)) {
                    $error_rows[] = $row_num;
                    continue; // Skip this row
                }

                // Convert Excel dates to PHP dates
                $date_of_birth = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($dob_excel)->format('Y-m-d');
                $hire_date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($hire_date_excel)->format('Y-m-d');

                // --- Create User ---
                mysqli_query($conn, "LOCK TABLES users WRITE");
                $result_max_id = mysqli_query($conn, "SELECT MAX(user_id) as last_id FROM users");
                $row_max_id = mysqli_fetch_assoc($result_max_id);
                $next_id = ($row_max_id['last_id'] ?? 0) + 1;
                $user_username = str_pad($next_id, 6, '0', STR_PAD_LEFT);
                mysqli_query($conn, "UNLOCK TABLES");

                $user_password = password_hash(strtolower(trim($last_name)) . '@123', PASSWORD_DEFAULT);
                $user_role = 'teacher';

                mysqli_stmt_bind_param($stmt_user, "sss", $user_username, $user_password, $user_role);
                if (!mysqli_stmt_execute($stmt_user)) {
                    throw new Exception("Failed to create user for row $row_num: " . mysqli_stmt_error($stmt_user));
                }
                $new_user_id = mysqli_insert_id($conn);

                // --- Create Teacher ---
                mysqli_stmt_bind_param($stmt_teacher, "isssssssssssss",
                    $new_user_id, $first_name, $middle_name, $last_name, $date_of_birth,
                    $gender, $nationality, $religion, $city, $wereda, $kebele, $phone, $email,
                    $hire_date
                );

                if (!mysqli_stmt_execute($stmt_teacher)) {
                    throw new Exception("Failed to create teacher for row $row_num: " . mysqli_stmt_error($stmt_teacher));
                }

                $imported_count++;
            }

            mysqli_commit($conn);
            log_activity($conn, 'import_teachers', null, null, "Imported $imported_count teachers.");
            $message = "Successfully imported $imported_count teachers.";
            if (!empty($error_rows)) {
                $message .= " The following rows had errors and were skipped: " . implode(', ', $error_rows);
            }
            $message_type = 'success';

            mysqli_stmt_close($stmt_user);
            mysqli_stmt_close($stmt_teacher);

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
header("Location: manage_teachers.php");
exit();
?>