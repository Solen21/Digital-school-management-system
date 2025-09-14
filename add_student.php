<?php
session_start();

// 1. Check if the user is logged in and has the correct role.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
    die("<h1>Access Denied</h1><p>You do not have permission to view this page. <a href='dashboard.php'>Return to Dashboard</a></p>");
}

require_once 'functions.php';
require_once 'data/db_connect.php';
    
    // Check if the form was submitted
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $message = '';
        $message_type = 'danger'; // Default to error
        if (!isset($conn) || !mysqli_ping($conn)) {
            require 'data/db_connect.php';
        }

        // --- Start Transaction ---
        mysqli_begin_transaction($conn);

        try {
            // --- Secure File Upload Handling ---
            $guardian_id = null;
            $guardian_choice = $_POST['guardian_choice'] ?? '';

            if ($guardian_choice === 'new') {
                // --- Create a new guardian ---
                if (empty($_POST['guardian_name']) || empty($_POST['guardian_phone'])) {
                    throw new Exception("New guardian's name and phone are required.");
                }

                // --- CHECK FOR EXISTING GUARDIAN BY PHONE TO PREVENT DUPLICATES ---
                $guardian_phone = $_POST['guardian_phone'];
                $sql_check_guardian = "SELECT guardian_id FROM guardians WHERE phone = ? LIMIT 1";
                $stmt_check_guardian = mysqli_prepare($conn, $sql_check_guardian);
                mysqli_stmt_bind_param($stmt_check_guardian, "s", $guardian_phone);
                mysqli_stmt_execute($stmt_check_guardian);
                $result_check_guardian = mysqli_stmt_get_result($stmt_check_guardian);
                
                if ($existing_guardian = mysqli_fetch_assoc($result_check_guardian)) {
                    // Guardian already exists, use their ID
                    $guardian_id = $existing_guardian['guardian_id'];
                } else {
                    // Guardian does not exist, create a new one
                    // 1a. Create user account for the new guardian
                    // We use the phone number as the password part for simplicity.
                    $guardian_user_data = create_user_account($conn, 'guardian', $_POST['guardian_phone'], 'g');
                    $new_guardian_user_id = $guardian_user_data['user_id'];

                    // 1b. Create the guardian profile linked to the new user account
                    $sql_guardian = "INSERT INTO guardians (user_id, name, phone, email) VALUES (?, ?, ?, ?)";
                    $stmt_guardian = mysqli_prepare($conn, $sql_guardian);
                    mysqli_stmt_bind_param($stmt_guardian, "isss", $new_guardian_user_id, $_POST['guardian_name'], $_POST['guardian_phone'], $_POST['guardian_email']);
                    if (!mysqli_stmt_execute($stmt_guardian)) throw new Exception("Failed to create guardian profile: " . mysqli_stmt_error($stmt_guardian));
                    $guardian_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($stmt_guardian);
                }
                mysqli_stmt_close($stmt_check_guardian);
            } elseif ($guardian_choice === 'existing') {
                $guardian_id = $_POST['existing_guardian_id'] ?? null;
                if (empty($guardian_id)) throw new Exception("An existing guardian must be selected.");
            } else {
                throw new Exception("A guardian must be selected or created for the student.");
            }

            $upload_dir = 'uploads/students/';
            if (!is_dir($upload_dir.'photos/')) { mkdir($upload_dir.'photos/', 0755, true); }
            if (!is_dir($upload_dir.'documents/')) { mkdir($upload_dir.'documents/', 0755, true); }

            $student_email = $_POST['email'];
            if (empty($student_email)) {
                $student_email = null; // Store NULL in the database if the email is empty
            }

            $photo_path = handle_file_upload('photo_path', $upload_dir.'photos/', ['image/jpeg', 'image/png', 'image/gif'], 5 * 1024 * 1024); // 5MB max
            $document_path = handle_file_upload('document_path', $upload_dir.'documents/', ['image/jpeg', 'image/png', 'application/pdf'], 10 * 1024 * 1024); // 10MB max

            // 2. Create a user account for the student using the refactored function
            $student_user_data = create_user_account($conn, 'student', $_POST['last_name']);
            $new_user_id = $student_user_data['user_id'];
            $user_username = $student_user_data['username'];
            // The hashed password is now stored in the students table for reference,
            // though the users table is the source of truth for login.
            $user_password_hashed = password_hash($student_user_data['password_plain'], PASSWORD_DEFAULT);

            // 3. Create the student record with the new user_id
            $sql_student = "INSERT INTO students (user_id, first_name, middle_name, last_name, date_of_birth, age, gender, nationality, religion, city, wereda, kebele, phone, email, emergency_contact, blood_type, grade_level, stream, last_school, last_score, last_grade, photo_path, document_path, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

           $stmt_student = mysqli_prepare($conn, $sql_student);
            $emergency_contact = !empty($_POST['emergency_contact']) ? $_POST['emergency_contact'] : null;

            mysqli_stmt_bind_param($stmt_student, "issssisssssssisssssdssss",
                $new_user_id,
                $_POST['first_name'],
                $_POST['middle_name'],
                $_POST['last_name'],
                $_POST['date_of_birth'],
                $_POST['age'],
                $_POST['gender'],
                $_POST['nationality'],
                $_POST['religion'],
                $_POST['city'],
                $_POST['wereda'],
                $_POST['kebele'],
                $_POST['phone'],
                $_POST['email'],
                $emergency_contact,
                $_POST['blood_type'],
                $_POST['grade_level'],
                $_POST['stream'],
                $_POST['last_school'],
                $_POST['last_score'],
                $_POST['last_grade'],
                $photo_path,
                $document_path,
                $user_password_hashed // Storing the hashed password
            );

            if (!mysqli_stmt_execute($stmt_student)) {
                throw new Exception("Error creating student record: " . mysqli_stmt_error($stmt_student));
            }

            $new_student_id = mysqli_insert_id($conn);

            // --- Link Student to Guardian ---
            $relation = $_POST['guardian_relation'] ?? 'Guardian';
            $sql_map = "INSERT INTO student_guardian_map (student_id, guardian_id, relation) VALUES (?, ?, ?)";
            $stmt_map = mysqli_prepare($conn, $sql_map);
            mysqli_stmt_bind_param($stmt_map, "iis", $new_student_id, $guardian_id, $relation);
            if (!mysqli_stmt_execute($stmt_map)) {
                throw new Exception("Failed to link student to guardian: " . mysqli_stmt_error($stmt_map));
            }
            mysqli_stmt_close($stmt_map);

            // --- Automatic Classroom Assignment ---
            $grade_level = $_POST['grade_level'];
            $assigned_classroom_name = '';

            // Find the best classroom (least populated, not full)
            $sql_find_class = "
                SELECT c.classroom_id, c.name, c.capacity, COUNT(ca.student_id) as student_count
                FROM classrooms c
                LEFT JOIN class_assignments ca ON c.classroom_id = ca.classroom_id
                WHERE c.grade_level = ?
                GROUP BY c.classroom_id, c.name, c.capacity
                HAVING student_count < c.capacity
                ORDER BY student_count ASC, c.name ASC
                LIMIT 1
            ";

            $stmt_find_class = mysqli_prepare($conn, $sql_find_class);
            mysqli_stmt_bind_param($stmt_find_class, "s", $grade_level);
            mysqli_stmt_execute($stmt_find_class);
            $result_class = mysqli_stmt_get_result($stmt_find_class);

            if ($classroom_to_assign = mysqli_fetch_assoc($result_class)) {
                $classroom_id_to_assign = $classroom_to_assign['classroom_id'];
                $assigned_classroom_name = $classroom_to_assign['name'];

                // Assign student to this classroom
                $sql_assign = "INSERT INTO class_assignments (student_id, classroom_id) VALUES (?, ?)";
                $stmt_assign = mysqli_prepare($conn, $sql_assign);
                mysqli_stmt_bind_param($stmt_assign, "ii", $new_student_id, $classroom_id_to_assign);
                if (!mysqli_stmt_execute($stmt_assign)) {
                    throw new Exception("Error assigning student to classroom: " . mysqli_stmt_error($stmt_assign));
                }
                mysqli_stmt_close($stmt_assign);
            }
            mysqli_stmt_close($stmt_find_class);

            mysqli_stmt_close($stmt_student);

            mysqli_commit($conn);

            $new_student_name = $_POST['first_name'] . ' ' . $_POST['last_name'];
            log_activity($conn, 'create_student', $new_student_id, $new_student_name);

            $message_type = 'success';
            $message = "Student registered successfully! Username: <strong>{$user_username}</strong>";
            if (!empty($assigned_classroom_name)) {
                $message .= "<br>Automatically assigned to classroom: <strong>" . htmlspecialchars($assigned_classroom_name) . "</strong>";
            } else {
                $message_type = 'warning';
                $message .= "<br>Could not automatically assign to a classroom. Please assign manually from the 'Manage Assignments' page.";
            }
        } catch (Exception $e) {
            // An error occurred, roll back the transaction
            mysqli_rollback($conn);
            $message = "Error: " . $e->getMessage();
            $message_type = 'danger';
        }

        $_SESSION['form_message'] = $message;
        $_SESSION['form_message_type'] = $message_type;
        header("Location: add_student.php", true, 303);
        exit();
    }


$page_title = 'Register New Student';
include 'header.php';
?>
<!-- Add Tom Select CSS -->
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css" rel="stylesheet">
<?php
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Register New Student</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php
    // Display message from redirected POST request
    // Fetch existing guardians for the dropdown
    require 'data/db_connect.php';
    $guardians = [];
    $guardian_result = mysqli_query($conn, "SELECT guardian_id, name, phone FROM guardians ORDER BY name ASC");
    if ($guardian_result) {
        $guardians = mysqli_fetch_all($guardian_result, MYSQLI_ASSOC);
    }
    mysqli_close($conn);

    if (isset($_SESSION['form_message'])) {
        echo '<div class="alert alert-'.$_SESSION['form_message_type'].' alert-dismissible fade show" role="alert">'.$_SESSION['form_message'].'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['form_message'], $_SESSION['form_message_type']);
    }
    ?>

    <form action="add_student.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Personal Details<?php // this comment was causing a header error ?></h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label for="first_name" class="form-label">First Name</label><input type="text" class="form-control" id="first_name" name="first_name" required><div class="invalid-feedback">First name is required.</div></div>
                    <div class="col-md-4"><label for="middle_name" class="form-label">Middle Name</label><input type="text" class="form-control" id="middle_name" name="middle_name" required><div class="invalid-feedback">Middle name is required.</div></div>
                    <div class="col-md-4"><label for="last_name" class="form-label">Last Name</label><input type="text" class="form-control" id="last_name" name="last_name" required><div class="invalid-feedback">Last name is required.</div></div>
                    <div class="col-md-4"><label for="date_of_birth" class="form-label">Date of Birth</label><input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required><div class="invalid-feedback">Date of birth is required.</div></div>
                    <div class="col-md-4"><label for="age" class="form-label">Age</label><input type="number" class="form-control" id="age" name="age" readonly required><div class="invalid-feedback">Age is calculated automatically.</div></div>
                    <div class="col-md-4"><label for="gender" class="form-label">Gender</label><select class="form-select" id="gender" name="gender" required><option value="male">Male</option><option value="female">Female</option></select><div class="invalid-feedback">Please select a gender.</div></div>
                    <div class="col-md-4"><label for="nationality" class="form-label">Nationality</label><input type="text" class="form-control" id="nationality" name="nationality" value="Ethiopian" required><div class="invalid-feedback">Nationality is required.</div></div>
                    <div class="col-md-4"><label for="religion" class="form-label">Religion</label><input type="text" class="form-control" id="religion" name="religion" required><div class="invalid-feedback">Religion is required.</div></div>
                    <div class="col-md-4"><label for="blood_type" class="form-label">Blood Type</label><select class="form-select" id="blood_type" name="blood_type"><option value="">Unknown</option><option value="A+">A+</option><option value="A-">A-</option><option value="B+">B+</option><option value="B-">B-</option><option value="AB+">AB+</option><option value="AB-">AB-</option><option value="O+">O+</option><option value="O-">O-</option></select></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Contact & Address</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label for="phone" class="form-label">Phone</label><input type="tel" class="form-control" id="phone" name="phone" placeholder="+251..." required><div class="invalid-feedback">Phone number is required.</div></div>
                    <div class="col-md-4"><label for="email" class="form-label">Email (Optional)</label><input type="email" class="form-control" id="email" name="email" placeholder="example@domain.com"><div class="invalid-feedback">Please enter a valid email.</div></div>
                    <div class="col-md-4"><label for="emergency_contact" class="form-label">Emergency Contact</label><input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" placeholder="+251..." required><div class="invalid-feedback">Emergency contact is required.</div></div>
                    <div class="col-md-4"><label for="city" class="form-label">City</label><input type="text" class="form-control" id="city" name="city" value="Debre Markos" required><div class="invalid-feedback">City is required.</div></div>
                    <div class="col-md-4"><label for="wereda" class="form-label">Wereda</label><input type="text" class="form-control" id="wereda" name="wereda" required><div class="invalid-feedback">Wereda is required.</div></div>
                    <div class="col-md-4"><label for="kebele" class="form-label">Kebele</label><input type="text" class="form-control" id="kebele" name="kebele" required><div class="invalid-feedback">Kebele is required.</div></div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Academic Information</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="grade_level" class="form-label">Enrollment Grade</label>
                        <select class="form-select" id="grade_level" name="grade_level" required>
                            <option value="9">9</option><option value="10">10</option><option value="11">11</option><option value="12">12</option>
                        </select>
                        <div class="invalid-feedback">Please select a grade.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="stream" class="form-label">Stream</label>
                        <select class="form-select" id="stream" name="stream" required>
                            <option value="Natural">Natural</option><option value="Social">Social</option><option value="Both" selected>Both</option>
                        </select>
                        <div class="invalid-feedback">Please select a stream.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="last_school" class="form-label">Last School Attended</label>
                        <input type="text" class="form-control" id="last_school" name="last_school" required><div class="invalid-feedback">Last school is required.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="last_grade" class="form-label">Last Grade Completed</label>
                        <input type="text" class="form-control" id="last_grade" name="last_grade" readonly required><div class="invalid-feedback">This is calculated automatically.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="last_score" class="form-label">Last Score/Average</label>
                        <input type="number" step="0.01" class="form-control" id="last_score" name="last_score" required><div class="invalid-feedback">Last score is required.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Guardian Information (Required)</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="guardian_choice" id="guardian_existing" value="existing" checked>
                        <label class="form-check-label" for="guardian_existing">Select Existing Guardian</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="guardian_choice" id="guardian_new" value="new">
                        <label class="form-check-label" for="guardian_new">Register New Guardian</label>
                    </div>
                </div>

                <div id="existing-guardian-section">
                    <label for="existing_guardian_id" class="form-label">Select Guardian</label>
                    <select id="existing_guardian_id" name="existing_guardian_id" required>
                        <option value="">-- Search for a guardian --</option>
                        <?php foreach ($guardians as $guardian): ?>
                            <option value="<?php echo $guardian['guardian_id']; ?>"><?php echo htmlspecialchars($guardian['name'] . ' (' . $guardian['phone'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="new-guardian-section" style="display: none;">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="guardian_phone" class="form-label">Guardian Phone</label>
                            <input type="tel" class="form-control" id="guardian_phone" name="guardian_phone">
                            <div id="guardian-phone-feedback" class="form-text"></div>
                        </div>
                        <div class="col-md-4"><label for="guardian_name" class="form-label">Guardian Full Name</label><input type="text" class="form-control" id="guardian_name" name="guardian_name"></div>
                        <div class="col-md-4"><label for="guardian_email" class="form-label">Guardian Email (Optional)</label><input type="email" class="form-control" id="guardian_email" name="guardian_email"></div>
                    </div>
                </div>
                <div class="mt-3 col-md-4"><label for="guardian_relation" class="form-label">Relationship to Student</label><input type="text" class="form-control" id="guardian_relation" name="guardian_relation" placeholder="e.g., Father, Mother..." required></div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Profile Picture & Documents</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="photo_path" class="form-label">Student Photo</label>
                    <input class="form-control" type="file" id="photo_path" name="photo_path" accept="image/*">
                    <div class="form-text">Upload a clear, recent photo of the student, or use the camera option below.</div>
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
                    <label for="document_path" class="form-label">Supporting Document</label>
                    <input class="form-control" type="file" id="document_path" name="document_path" accept="image/*,application/pdf">
                    <div class="form-text">E.g., Previous school transcript, birth certificate.</div>
                </div>
            </div>
        </div>

        <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-person-plus-fill"></i> Register Student</button>
        </div>
    </form>
</div>

<!-- Add Tom Select JS -->
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dobInput = document.getElementById('date_of_birth');
    const ageInput = document.getElementById('age');
    const gradeLevelInput = document.getElementById('grade_level');
    const lastGradeInput = document.getElementById('last_grade');

    function calculateAge() {
        if (dobInput.value) {
            const birthDate = new Date(dobInput.value);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            ageInput.value = age;
        }
    }

    function setLastGrade() {
        const grade = parseInt(gradeLevelInput.value, 10);
        if (!isNaN(grade)) {
            lastGradeInput.value = grade - 1;
        }
    }

    dobInput.addEventListener('change', calculateAge);
    gradeLevelInput.addEventListener('change', setLastGrade);

    // Initial calculation
    setLastGrade();

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

    // Bootstrap form validation
    // Bootstrap form validation and camera submission
    const form = document.querySelector('.needs-validation');
    form.addEventListener('submit', function (event) {
        // Handle captured photo submission
        if (capturedBlob) {
            event.preventDefault(); // Stop normal submission
            const formData = new FormData(form);
            // CRITICAL FIX: Remove the original file input from the form data
            // to prevent sending an "empty" file along with the captured blob.
            formData.delete('photo_path');
            formData.append('photo_path', capturedBlob, 'camera_photo.jpg');

            // Submit the form with FormData via fetch
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // The server will respond with a redirect (303). 
                // The 'redirect: 'follow'' option is default in browsers, so we just need to navigate to the final URL.
                window.location.href = response.url;
            }) 
            .catch(error => {
                console.error('Error submitting form:', error);
                alert('An error occurred. Please try again.');
            });
        } else {
             if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
        }
        form.classList.add('was-validated');
    }, false);

    // Guardian form toggle
    const guardianChoiceRadios = document.querySelectorAll('input[name="guardian_choice"]');
    const existingGuardianSection = document.getElementById('existing-guardian-section');
    const newGuardianSection = document.getElementById('new-guardian-section');
    const existingGuardianSelect = document.getElementById('existing_guardian_id');
    const newGuardianName = document.getElementById('guardian_name');
    const newGuardianPhone = document.getElementById('guardian_phone');

    function toggleGuardianSections() {
        if (document.getElementById('guardian_new').checked) {
            existingGuardianSection.style.display = 'none';
            newGuardianSection.style.display = 'block';
            existingGuardianSelect.required = false;
            newGuardianName.required = true;
            newGuardianPhone.required = true;
        } else {
            existingGuardianSection.style.display = 'block';
            newGuardianSection.style.display = 'none';
            existingGuardianSelect.required = true;
            newGuardianName.required = false;
            newGuardianPhone.required = false;
        }
    }
    guardianChoiceRadios.forEach(radio => radio.addEventListener('change', toggleGuardianSections));

    // Initialize Tom Select for the existing guardian dropdown
    let tomSelect = new TomSelect('#existing_guardian_id', {
        create: false,
        sortField: {
            field: "text",
            direction: "asc"
        }
    });

    // AJAX check for existing guardian by phone
    const guardianPhoneInput = document.getElementById('guardian_phone');
    const guardianNameInput = document.getElementById('guardian_name');
    const guardianEmailInput = document.getElementById('guardian_email');
    const phoneFeedback = document.getElementById('guardian-phone-feedback');
    let debounceTimer;

    guardianPhoneInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const phone = this.value.trim();
        phoneFeedback.textContent = ''; // Clear previous feedback

        if (phone.length < 9) { // Basic validation for Ethiopian numbers
            return;
        }

        debounceTimer = setTimeout(() => {
            phoneFeedback.textContent = 'Checking...';
            phoneFeedback.className = 'form-text text-muted';

            fetch('ajax_check_guardian.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'phone=' + encodeURIComponent(phone)
            })
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    phoneFeedback.textContent = 'Guardian found! Switching to "Existing Guardian".';
                    phoneFeedback.className = 'form-text text-success';
                    
                    // Pre-fill and switch
                    document.getElementById('guardian_existing').checked = true;
                    toggleGuardianSections();
                    tomSelect.setValue(data.guardian.guardian_id);

                } else {
                    phoneFeedback.textContent = 'This appears to be a new guardian.';
                    phoneFeedback.className = 'form-text text-info';
                }
            })
            .catch(error => console.error('Error:', error));
        }, 500); // Wait 500ms after user stops typing
    });
});
</script>

<?php include 'footer.php'; ?>