<?php
session_start();

// 1. Check if the user is logged in. Redirect to login if not.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 2. Check if the user has the 'admin' role.
if ($_SESSION['role'] !== 'admin') {
    die("<h1>Access Denied</h1><p>You do not have permission to view this page. <a href='dashboard.php'>Return to Dashboard</a></p>");
}

require_once __DIR__ . '/functions.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $message = '';
    $message_type = 'danger'; // Default to error
    // Include the centralized database connection.
    require_once 'data/db_connect.php';

    // --- Start Transaction ---
    mysqli_begin_transaction($conn);

    try {
        // --- Secure File Upload Handling ---
        $upload_dir = 'uploads/teachers/';
        if (!is_dir($upload_dir.'photos/')) { mkdir($upload_dir.'photos/', 0755, true); }
        if (!is_dir($upload_dir.'documents/')) { mkdir($upload_dir.'documents/', 0755, true); }

        $photo_path = handle_file_upload('photo_path', $upload_dir.'photos/', ['image/jpeg', 'image/png', 'image/gif'], 5 * 1024 * 1024); // 5MB max
        
        // Allowed document types: images, PDF, and Word documents
        $allowed_doc_types = [
            'image/jpeg', 'image/png', 'application/pdf',
            'application/msword', // for .doc files
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // for .docx files
        ];
        $document_path = handle_file_upload('document_path', $upload_dir.'documents/', $allowed_doc_types, 10 * 1024 * 1024); // 10MB max

        // 1. Generate a unique numeric username
        mysqli_query($conn, "LOCK TABLES users WRITE");
        $result = mysqli_query($conn, "SELECT MAX(user_id) as last_id FROM users");
        $row = mysqli_fetch_assoc($result);
        $next_id = ($row['last_id'] ?? 0) + 1;
        $user_username = str_pad($next_id, 6, '0', STR_PAD_LEFT);
        mysqli_query($conn, "UNLOCK TABLES");

        // Password: 'lastname@123'
        $user_password = password_hash(strtolower(trim($_POST['last_name'])) . '@123', PASSWORD_DEFAULT);
        $user_role = 'teacher';

        // Check if a user with this username already exists (unlikely with this method, but safe)
        $check_stmt = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($check_stmt, "s", $user_username);
        mysqli_stmt_execute($check_stmt);
        if (mysqli_stmt_fetch($check_stmt)) {
            throw new Exception("Generated username '{$user_username}' already exists. Please try again.");
        }
        mysqli_stmt_close($check_stmt);

        $sql_user = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
        $stmt_user = mysqli_prepare($conn, $sql_user);
        mysqli_stmt_bind_param($stmt_user, "sss", $user_username, $user_password, $user_role);
        
        if (!mysqli_stmt_execute($stmt_user)) {
            if (mysqli_errno($conn) == 1062) {
                 throw new Exception("A user with the username '{$user_username}' already exists.");
            }
            throw new Exception("Error creating user account: " . mysqli_stmt_error($stmt_user));
        }

        $new_user_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt_user);

        // 3. Create the teacher record with the new user_id
        $sql_teacher = "INSERT INTO teachers (user_id, first_name, middle_name, last_name, date_of_birth, gender, nationality, religion, city, wereda, kebele, phone, email, hire_date, photo_path, document_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_teacher = mysqli_prepare($conn, $sql_teacher);

        mysqli_stmt_bind_param($stmt_teacher, "isssssssssssssss",
            $new_user_id,
            $_POST['first_name'],
            $_POST['middle_name'],
            $_POST['last_name'],
            $_POST['date_of_birth'],
            $_POST['gender'],
            $_POST['nationality'],
            $_POST['religion'],
            $_POST['city'],
            $_POST['wereda'],
            $_POST['kebele'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['hire_date'],
            $photo_path,
            $document_path
        );

        if (!mysqli_stmt_execute($stmt_teacher)) {
            throw new Exception("Error creating teacher record: " . mysqli_stmt_error($stmt_teacher));
        }

        mysqli_stmt_close($stmt_teacher);

        // If both inserts were successful, commit the transaction
        mysqli_commit($conn);

        $new_teacher_name = $_POST['first_name'] . ' ' . $_POST['last_name'];
        log_activity($conn, 'create_teacher', mysqli_insert_id($conn), $new_teacher_name);

        $message = "Teacher registered successfully! Username: <strong>{$user_username}</strong>";
        $message_type = 'success';

    } catch (Exception $e) {
        // An error occurred, roll back the transaction
        mysqli_rollback($conn);
        $message = "Error: " . $e->getMessage();
        $message_type = 'danger';
    }

    mysqli_close($conn);

    $_SESSION['form_message'] = $message;
    $_SESSION['form_message_type'] = $message_type;
    header("Location: add_teacher.php");
    exit();
}

$page_title = 'Register New Teacher';
include 'header.php';

?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Register New Teacher</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php
    // Display message from redirected POST request
    if (isset($_SESSION['form_message'])) {
        echo '<div class="alert alert-'.$_SESSION['form_message_type'].' alert-dismissible fade show" role="alert">'.$_SESSION['form_message'].'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['form_message'], $_SESSION['form_message_type']);
    }
    ?>

    <form action="add_teacher.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Personal & Contact Details</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label for="first_name" class="form-label">First Name</label><input type="text" class="form-control" id="first_name" name="first_name" required><div class="invalid-feedback">First name is required.</div></div>
                    <div class="col-md-4"><label for="middle_name" class="form-label">Middle Name</label><input type="text" class="form-control" id="middle_name" name="middle_name" required><div class="invalid-feedback">Middle name is required.</div></div>
                    <div class="col-md-4"><label for="last_name" class="form-label">Last Name</label><input type="text" class="form-control" id="last_name" name="last_name" required><div class="invalid-feedback">Last name is required.</div></div>
                    <div class="col-md-4"><label for="date_of_birth" class="form-label">Date of Birth</label><input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required><div class="invalid-feedback">Date of birth is required.</div></div>
                    <div class="col-md-4"><label for="gender" class="form-label">Gender</label><select class="form-select" id="gender" name="gender" required><option value="male">Male</option><option value="female">Female</option></select><div class="invalid-feedback">Please select a gender.</div></div>
                    <div class="col-md-4"><label for="phone" class="form-label">Phone</label><input type="tel" class="form-control" id="phone" name="phone" placeholder="+251..." required><div class="invalid-feedback">Phone number is required.</div></div>
                    <div class="col-md-4"><label for="email" class="form-label">Email (Optional)</label><input type="email" class="form-control" id="email" name="email" placeholder="example@domain.com"><div class="invalid-feedback">Please enter a valid email.</div></div>
                    <div class="col-md-4"><label for="nationality" class="form-label">Nationality</label><input type="text" class="form-control" id="nationality" name="nationality" value="Ethiopian" required><div class="invalid-feedback">Nationality is required.</div></div>
                    <div class="col-md-4"><label for="religion" class="form-label">Religion</label><input type="text" class="form-control" id="religion" name="religion" required><div class="invalid-feedback">Religion is required.</div></div>
                    <div class="col-md-4"><label for="city" class="form-label">City</label><input type="text" class="form-control" id="city" name="city" value="Debre Markos" required><div class="invalid-feedback">City is required.</div></div>
                    <div class="col-md-4"><label for="wereda" class="form-label">Wereda</label><input type="text" class="form-control" id="wereda" name="wereda" required><div class="invalid-feedback">Wereda is required.</div></div>
                    <div class="col-md-4"><label for="kebele" class="form-label">Kebele</label><input type="text" class="form-control" id="kebele" name="kebele" required><div class="invalid-feedback">Kebele is required.</div></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Employment Details</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label for="hire_date" class="form-label">Hire Date</label><input type="date" class="form-control" id="hire_date" name="hire_date" required><div class="invalid-feedback">Hire date is required.</div></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Profile Picture & Documents</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="photo_path" class="form-label">Teacher Photo</label>
                    <input class="form-control" type="file" id="photo_path" name="photo_path" accept="image/png, image/jpeg, image/gif">
                    <div class="form-text">Upload a clear, recent photo of the teacher, or use the camera option below.</div>
                    <div class="d-flex align-items-center mt-2">
                        <button type="button" class="btn btn-outline-secondary" id="show-camera-btn"><i class="bi bi-camera-fill"></i> Take Photo</button>
                        <div id="photo-preview-container" class="ms-3" style="display: none;">
                            <img id="photo-preview" src="" alt="Photo Preview" style="max-height: 100px; border-radius: 0.25rem;"/>
                            <button type="button" id="remove-photo-btn" class="btn-close" aria-label="Remove Photo" style="position: absolute; transform: translate(-100%, -50%); background-color: rgba(255,255,255,0.7); border-radius: 50%;"></button>
                        </div>
                    </div>
                    <div class="camera-section card mt-2" style="display: none;">
                        <div class="card-body text-center">
                            <video id="video" width="320" height="240" autoplay playsinline style="border-radius: 0.25rem;"></video>
                            <div class="mt-2">
                                <button type="button" class="btn btn-primary" id="snap-btn"><i class="bi bi-camera"></i> Snap</button>
                                <button type="button" class="btn btn-secondary" id="cancel-camera-btn">Cancel</button>
                            </div>
                            <canvas id="canvas" width="320" height="240" style="display:none;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="document_path" class="form-label">Supporting Document (e.g., CV, Credentials)</label>
                    <input class="form-control" type="file" id="document_path" name="document_path" accept="image/*,application/pdf,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    <div class="form-text">Upload any relevant supporting documents.</div>
                </div>
            </div>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-person-plus-fill"></i> Register Teacher</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Camera functionality
    const showCameraBtn = document.getElementById('show-camera-btn');
    const cameraSection = document.querySelector('.camera-section');
    const video = document.getElementById('video');
    const snapBtn = document.getElementById('snap-btn');
    const cancelBtn = document.getElementById('cancel-camera-btn');
    const canvas = document.getElementById('canvas');
    const photoInput = document.getElementById('photo_path');
    let stream;
    let capturedBlob = null;
    const photoPreviewContainer = document.getElementById('photo-preview-container');
    const photoPreview = document.getElementById('photo-preview');
    const removePhotoBtn = document.getElementById('remove-photo-btn');

    showCameraBtn.addEventListener('click', async () => {
        cameraSection.style.display = 'block';
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            video.srcObject = stream;
        } catch (err) {
            console.error("Error accessing camera: ", err);
            alert("Could not access the camera. Please check permissions.");
            cameraSection.style.display = 'none';
        }
    });

    snapBtn.addEventListener('click', () => {
        const context = canvas.getContext('2d');
        context.drawImage(video, 0, 0, 320, 240);
        canvas.toBlob(function(blob) {
            capturedBlob = blob;
            const previewUrl = URL.createObjectURL(blob);
            photoPreview.src = previewUrl;
            photoPreviewContainer.style.display = 'inline-block';
            
            // Clear the file input so the captured photo takes precedence
            photoInput.value = '';

        }, 'image/jpeg');
        stopCamera();
    });

    cancelBtn.addEventListener('click', () => {
        stopCamera();
    });

    removePhotoBtn.addEventListener('click', () => {
        capturedBlob = null;
        photoPreview.src = '';
        photoPreviewContainer.style.display = 'none';
        URL.revokeObjectURL(photoPreview.src);
    });

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
        }
        cameraSection.style.display = 'none';
    }

    // Bootstrap form validation and camera submission
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function (event) {
        if (capturedBlob) {
            event.preventDefault(); // Stop normal submission
            const formData = new FormData(form);
            // CRITICAL FIX: Remove the original file input from the form data
            // to prevent sending an "empty" file along with the captured blob.
            formData.delete('photo_path');
            formData.append('photo_path', capturedBlob, 'camera_photo.jpg');

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // The server will respond with a redirect (303). We navigate to the final URL.
                window.location.href = response.url;
            })
            .catch(error => console.error('Error submitting form:', error));
        } else {
             if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
        }
        form.classList.add('was-validated');
    }, false);
});
</script>

<?php include 'footer.php'; ?>