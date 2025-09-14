<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

$guardian_id = $_GET['id'] ?? null;

if (!$guardian_id || !is_numeric($guardian_id)) {
    die("<h1>Invalid Request</h1><p>No guardian ID provided. <a href='manage_guardians.php'>Return to Guardian List</a></p>");
}

// --- Handle POST requests for linking/unlinking ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guardian_id_post = $_POST['guardian_id'] ?? 0;
    $student_id_post = $_POST['student_id'] ?? 0;

    // Ensure the POST is for the correct guardian
    if ($guardian_id_post != $guardian_id) {
        $_SESSION['message'] = "Invalid action.";
        $_SESSION['message_type'] = 'danger';
        header("Location: manage_guardians.php");
        exit();
    }

    // Fetch names for logging
    $sql_student_name = "SELECT CONCAT(first_name, ' ', last_name) as name FROM students WHERE student_id = ?";
    $stmt_student_name = mysqli_prepare($conn, $sql_student_name);
    mysqli_stmt_bind_param($stmt_student_name, "i", $student_id_post);
    mysqli_stmt_execute($stmt_student_name);
    $student_name = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_student_name))['name'] ?? 'Unknown Student';
    mysqli_stmt_close($stmt_student_name);

    $sql_guardian_name = "SELECT name FROM guardians WHERE guardian_id = ?";
    $stmt_guardian_name = mysqli_prepare($conn, $sql_guardian_name);
    mysqli_stmt_bind_param($stmt_guardian_name, "i", $guardian_id);
    mysqli_stmt_execute($stmt_guardian_name);
    $guardian_name = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_guardian_name))['name'] ?? 'Unknown Guardian';
    mysqli_stmt_close($stmt_guardian_name);

    if (isset($_POST['link_student'])) {
        $relation = $_POST['relation'] ?? 'Guardian';
        $sql = "INSERT INTO student_guardian_map (student_id, guardian_id, relation) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "iis", $student_id_post, $guardian_id, $relation);
        if (mysqli_stmt_execute($stmt)) {
            $log_details = "Linked guardian '{$guardian_name}' to student '{$student_name}' with relation '{$relation}'.";
            log_activity($conn, 'link_student_guardian', $student_id_post, $student_name, $log_details);
            $_SESSION['message'] = "Student linked successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error linking student: " . mysqli_stmt_error($stmt);
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);
    } elseif (isset($_POST['unlink_student'])) {
        $sql = "DELETE FROM student_guardian_map WHERE student_id = ? AND guardian_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $student_id_post, $guardian_id);
        if (mysqli_stmt_execute($stmt)) {
            $log_details = "Unlinked guardian '{$guardian_name}' from student '{$student_name}'.";
            log_activity($conn, 'unlink_student_guardian', $student_id_post, $student_name, $log_details);
            $_SESSION['message'] = "Student unlinked successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error unlinking student: " . mysqli_stmt_error($stmt);
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
    header("Location: manage_guardian_links.php?id=" . $guardian_id);
    exit();
}


// --- Fetch data for display ---
// Get guardian details
$sql_guardian = "SELECT * FROM guardians WHERE guardian_id = ?";
$stmt_guardian = mysqli_prepare($conn, $sql_guardian);
mysqli_stmt_bind_param($stmt_guardian, "i", $guardian_id);
mysqli_stmt_execute($stmt_guardian);
$guardian = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_guardian));
mysqli_stmt_close($stmt_guardian);

if (!$guardian) {
    die("<h1>Error</h1><p>Guardian not found.</p>");
}

// Get currently linked students
$linked_students = [];
$sql_linked = "SELECT s.student_id, s.first_name, s.last_name, sgm.relation FROM students s JOIN student_guardian_map sgm ON s.student_id = sgm.student_id WHERE sgm.guardian_id = ?";
$stmt_linked = mysqli_prepare($conn, $sql_linked);
mysqli_stmt_bind_param($stmt_linked, "i", $guardian_id);
mysqli_stmt_execute($stmt_linked);
$result_linked = mysqli_stmt_get_result($stmt_linked);
while ($row = mysqli_fetch_assoc($result_linked)) {
    $linked_students[] = $row;
}
mysqli_stmt_close($stmt_linked);

// Get students NOT linked to this guardian
$unlinked_students = [];
$sql_unlinked = "SELECT student_id, first_name, last_name FROM students WHERE student_id NOT IN (SELECT student_id FROM student_guardian_map WHERE guardian_id = ?)";
$stmt_unlinked = mysqli_prepare($conn, $sql_unlinked);
mysqli_stmt_bind_param($stmt_unlinked, "i", $guardian_id);
mysqli_stmt_execute($stmt_unlinked);
$result_unlinked = mysqli_stmt_get_result($stmt_unlinked);
while ($row = mysqli_fetch_assoc($result_unlinked)) {
    $unlinked_students[] = $row;
}
mysqli_stmt_close($stmt_unlinked);

mysqli_close($conn);

$page_title = 'Manage Guardian Links';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Manage Links for Guardian: <?php echo htmlspecialchars($guardian['name']); ?></h1>
        <a href="manage_guardians.php" class="btn btn-secondary">Back to Guardian List</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

    <div class="row">
        <!-- Linked Students -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Linked Students</h5></div>
                <div class="card-body">
                    <?php if (empty($linked_students)): ?>
                        <p class="text-muted">This guardian is not linked to any students.</p>
                    <?php else: ?>
                        <ul class="list-group">
                            <?php foreach ($linked_students as $student): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        <small class="text-muted d-block">Relation: <?php echo htmlspecialchars($student['relation']); ?></small>
                                    </div>
                                    <form action="manage_guardian_links.php?id=<?php echo $guardian_id; ?>" method="POST" class="ms-2">
                                        <input type="hidden" name="guardian_id" value="<?php echo $guardian_id; ?>">
                                        <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                        <button type="submit" name="unlink_student" class="btn btn-sm btn-danger" title="Unlink Student"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Unlinked Students -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Link New Student</h5></div>
                <div class="card-body">
                    <?php if (empty($unlinked_students)): ?>
                        <p class="text-muted">All students are already linked to this guardian.</p>
                    <?php else: ?>
                        <form action="manage_guardian_links.php?id=<?php echo $guardian_id; ?>" method="POST">
                            <input type="hidden" name="guardian_id" value="<?php echo $guardian_id; ?>">
                            <input type="hidden" name="link_student" value="1">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Select Student</label>
                                <select name="student_id" id="student_id" class="form-select" required>
                                    <option value="">-- Choose a student --</option>
                                    <?php foreach ($unlinked_students as $student): ?>
                                        <option value="<?php echo $student['student_id']; ?>"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="relation" class="form-label">Relation to Student</label>
                                <input type="text" name="relation" id="relation" class="form-control" placeholder="e.g., Father, Mother..." required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-link-45deg me-2"></i>Link Student</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>