<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function log_activity($conn, $action_type, $target_id = null, $details = '') {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $sql = "INSERT INTO activity_logs (user_id, action_type, target_id, details) VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "isis", $user_id, $action_type, $target_id, $details);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function create_notification($conn, $user_id, $message, $link = null) {
    $sql = "INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $user_id, $message, $link);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function handle_file_upload($file_key, $upload_subdir, $allowed_mime_types, $max_size) {
    if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
        if ($_FILES[$file_key]['size'] > $max_size) {
            throw new Exception(ucfirst(str_replace('_', ' ', $file_key)) . " file is too large.");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($_FILES[$file_key]['tmp_name']);
        if (!in_array($mime_type, $allowed_mime_types)) {
            throw new Exception("Invalid file type for " . ucfirst(str_replace('_', ' ', $file_key)) . ".");
        }

        $file_extension = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
        $safe_filename = uniqid('', true) . '.' . preg_replace('/[^a-zA-Z0-9-_\.]/', '', $file_extension);
        
        if (!is_dir($upload_subdir)) {
            mkdir($upload_subdir, 0755, true);
        }
        $target_file = $upload_subdir . $safe_filename;

        if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $target_file)) { return $target_file; }
        throw new Exception("Failed to move uploaded " . ucfirst(str_replace('_', ' ', $file_key)) . " file.");
    }
    return null;
}

/**
 * Processes a file update, handling the upload of a new file
 * and deleting the old one if successful.
 *
 * @param string $file_key The key in the $_FILES array (e.g., 'photo_path').
 * @param string $existing_path The path to the current file, from the database.
 * @param string $upload_subdir The directory to upload the new file to.
 * @param array $allowed_mime_types An array of allowed MIME types.
 * @param int $max_size The maximum file size in bytes.
 * @return string The new file path to be saved, or the existing path if no new file was uploaded.
 * @throws Exception on upload error.
 */
function process_file_update($file_key, $existing_path, $upload_subdir, $allowed_mime_types, $max_size) {
    $new_file_path = handle_file_upload($file_key, $upload_subdir, $allowed_mime_types, $max_size);
    if ($new_file_path) {
        if (!empty($existing_path) && file_exists($existing_path)) { unlink($existing_path); }
        return $new_file_path;
    }
    return $existing_path;
}