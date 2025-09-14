<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$message = '';
$message_type = '';

// --- POST Request Handling for both Exam Rooms and Exams ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // --- Exam Room Actions ---
    if ($action == 'add_exam_room' || $action == 'update_exam_room') {
        $name = $_POST['room_name'];
        $capacity = $_POST['capacity'];
        
        if ($action == 'add_exam_room') {
            $sql = "INSERT INTO exam_rooms (name, capacity) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "si", $name, $capacity);
        } else { // update_exam_room
            $id = $_POST['room_id'];
            $sql = "UPDATE exam_rooms SET name = ?, capacity = ? WHERE room_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sii", $name, $capacity, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $message = "Exam Room " . ($action == 'add_exam_room' ? 'added' : 'updated') . " successfully.";
            $message_type = 'success';
        } else {
            $message = "Error: " . mysqli_stmt_error($stmt);
            $message_type = 'error';
        }
        mysqli_stmt_close($stmt);
    } elseif ($action == 'delete_exam_room') {
        $id = $_POST['room_id'];
        $sql = "DELETE FROM exam_rooms WHERE room_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Exam Room deleted successfully.";
            $message_type = 'success';
        } else {
            $message = "Error deleting room. It might be in use in assignments.";
            $message_type = 'error';
        }
        mysqli_stmt_close($stmt);
    }

    // --- Exam Actions ---
    if ($action == 'add_exam' || $action == 'update_exam') {
        $name = $_POST['exam_name'];
        $date = $_POST['exam_date'];
        $semester = $_POST['semester'];
        $type = $_POST['type'];

        if ($action == 'add_exam') {
            $sql = "INSERT INTO exams (name, exam_date, semester, type) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssss", $name, $date, $semester, $type);
        } else { // update_exam
            $id = $_POST['exam_id'];
            $sql = "UPDATE exams SET name = ?, exam_date = ?, semester = ?, type = ? WHERE exam_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssi", $name, $date, $semester, $type, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $message = "Exam " . ($action == 'add_exam' ? 'added' : 'updated') . " successfully.";
            $message_type = 'success';
        } else {
            $message = "Error: " . mysqli_stmt_error($stmt);
            $message_type = 'error';
        }
        mysqli_stmt_close($stmt);
    } elseif ($action == 'delete_exam') {
        $id = $_POST['exam_id'];
        $sql = "DELETE FROM exams WHERE exam_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Exam deleted successfully.";
            $message_type = 'success';
        } else {
            $message = "Error deleting exam. It might be in use in assignments.";
            $message_type = 'error';
        }
        mysqli_stmt_close($stmt);
    }
}

// --- Fetch data for display ---
$exam_rooms = mysqli_query($conn, "SELECT * FROM exam_rooms ORDER BY name ASC");
$exams = mysqli_query($conn, "SELECT * FROM exams ORDER BY exam_date DESC");

$edit_room = null;
if (isset($_GET['edit_room_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM exam_rooms WHERE room_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['edit_room_id']);
    mysqli_stmt_execute($stmt);
    $edit_room = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$edit_exam = null;
if (isset($_GET['edit_exam_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM exams WHERE exam_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['edit_exam_id']);
    mysqli_stmt_execute($stmt);
    $edit_exam = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
$page_title = 'Manage Exams & Rooms';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Exams & Rooms</h1>
        <a href="manage_assignments.php" class="btn btn-secondary">Back to Assignments</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Column: Forms -->
        <div class="col-lg-4">
            <!-- Exam Room Form -->
            <div class="card mb-4" id="room-form-card">
                <div class="card-header"><h5 class="mb-0"><?php echo $edit_room ? 'Edit' : 'Add New'; ?> Exam Room</h5></div>
                <div class="card-body">
                    <form action="manage_exams.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_room ? 'update_exam_room' : 'add_exam_room'; ?>">
                        <?php if ($edit_room): ?><input type="hidden" name="room_id" value="<?php echo $edit_room['room_id']; ?>"><?php endif; ?>
                        <div class="mb-3"><label for="room_name" class="form-label">Room Name</label><input type="text" class="form-control" id="room_name" name="room_name" value="<?php echo htmlspecialchars($edit_room['name'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="capacity" class="form-label">Capacity</label><input type="number" class="form-control" id="capacity" name="capacity" value="<?php echo htmlspecialchars($edit_room['capacity'] ?? ''); ?>" required></div>
                        <button type="submit" class="btn btn-primary"><?php echo $edit_room ? 'Update' : 'Add'; ?> Room</button>
                        <?php if ($edit_room): ?><a href="manage_exams.php" class="btn btn-secondary ms-2">Cancel Edit</a><?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Exam Form -->
            <div class="card" id="exam-form-card">
                <div class="card-header"><h5 class="mb-0"><?php echo $edit_exam ? 'Edit' : 'Add New'; ?> Exam</h5></div>
                <div class="card-body">
                    <form action="manage_exams.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_exam ? 'update_exam' : 'add_exam'; ?>">
                        <?php if ($edit_exam): ?><input type="hidden" name="exam_id" value="<?php echo $edit_exam['exam_id']; ?>"><?php endif; ?>
                        <div class="mb-3"><label for="exam_name" class="form-label">Exam Name</label><input type="text" class="form-control" id="exam_name" name="exam_name" value="<?php echo htmlspecialchars($edit_exam['name'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="exam_date" class="form-label">Date</label><input type="date" class="form-control" id="exam_date" name="exam_date" value="<?php echo htmlspecialchars($edit_exam['exam_date'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="semester" class="form-label">Semester</label><select class="form-select" id="semester" name="semester" required><option value="1" <?php echo (($edit_exam['semester'] ?? '') == '1') ? 'selected' : ''; ?>>1</option><option value="2" <?php echo (($edit_exam['semester'] ?? '') == '2') ? 'selected' : ''; ?>>2</option></select></div>
                        <div class="mb-3"><label for="type" class="form-label">Type</label><select class="form-select" id="type" name="type" required><option value="Midterm" <?php echo (($edit_exam['type'] ?? '') == 'Midterm') ? 'selected' : ''; ?>>Midterm</option><option value="Final" <?php echo (($edit_exam['type'] ?? '') == 'Final') ? 'selected' : ''; ?>>Final</option></select></div>
                        <button type="submit" class="btn btn-primary"><?php echo $edit_exam ? 'Update' : 'Add'; ?> Exam</button>
                        <?php if ($edit_exam): ?><a href="manage_exams.php" class="btn btn-secondary ms-2">Cancel Edit</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column: Tables -->
        <div class="col-lg-8">
            <!-- Exam Rooms Table -->
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Existing Exam Rooms</h5></div>
                <div class="card-body"><div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light"><tr><th>Name</th><th>Capacity</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php mysqli_data_seek($exam_rooms, 0); ?>
                            <?php while($row = mysqli_fetch_assoc($exam_rooms)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['capacity']); ?></td>
                                <td>
                                    <a href="?edit_room_id=<?php echo $row['room_id']; ?>#room-form-card" class="btn btn-sm btn-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                    <form action="manage_exams.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="delete_exam_room"><input type="hidden" name="room_id" value="<?php echo $row['room_id']; ?>"><button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div></div>
            </div>

            <!-- Exams Table -->
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Existing Exams</h5></div>
                <div class="card-body"><div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light"><tr><th>Name</th><th>Date</th><th>Semester</th><th>Type</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php mysqli_data_seek($exams, 0); ?>
                            <?php while($row = mysqli_fetch_assoc($exams)): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['exam_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['semester']); ?></td>
                                <td><?php echo htmlspecialchars($row['type']); ?></td>
                                <td>
                                    <a href="?edit_exam_id=<?php echo $row['exam_id']; ?>#exam-form-card" class="btn btn-sm btn-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                    <form action="manage_exams.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="delete_exam"><input type="hidden" name="exam_id" value="<?php echo $row['exam_id']; ?>"><button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>