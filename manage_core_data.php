<?php
session_start();

// 1. Check if the user is logged in and has an appropriate role.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

// Use session for flash messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
} else {
    $message = '';
    $message_type = '';
}

// --- POST Request Handling for both Classrooms and Subjects ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $redirect_tab = 'classrooms'; // Default tab

    // --- Classroom Actions ---
    if ($action == 'add_classroom' || $action == 'update_classroom') {
        $name = $_POST['classroom_name'];
        $grade_level = $_POST['grade_level'];
        $capacity = $_POST['capacity'];
        $resources = $_POST['resources'];
        
        if ($action == 'add_classroom') {
            $sql = "INSERT INTO classrooms (name, grade_level, capacity, resources) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "siis", $name, $grade_level, $capacity, $resources);
        } else { // update_classroom
            $id = $_POST['classroom_id'];
            $sql = "UPDATE classrooms SET name = ?, grade_level = ?, capacity = ?, resources = ? WHERE classroom_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "siisi", $name, $grade_level, $capacity, $resources, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Classroom " . ($action == 'add_classroom' ? 'added' : 'updated') . " successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error: " . mysqli_stmt_error($stmt);
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);
    } elseif ($action == 'delete_classroom') {
        $id = $_POST['classroom_id'];
        $sql = "DELETE FROM classrooms WHERE classroom_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Classroom deleted successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error deleting classroom. It might be in use in assignments.";
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);
    }

    // --- Subject Actions ---
    elseif ($action == 'add_subject' || $action == 'update_subject') {
        $name = $_POST['subject_name'];
        $code = $_POST['subject_code'];
        $periods = $_POST['periods_per_week'];
        $grade_level = $_POST['grade_level'];
        $stream = $_POST['stream'];
        $description = $_POST['description'];
        $redirect_tab = 'subjects';

        if ($action == 'add_subject') {
            $sql = "INSERT INTO subjects (name, code, periods_per_week, grade_level, stream, description) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssisss", $name, $code, $periods, $grade_level, $stream, $description);
        } else { // update_subject
            $id = $_POST['subject_id'];
            $sql = "UPDATE subjects SET name = ?, code = ?, periods_per_week = ?, grade_level = ?, stream = ?, description = ? WHERE subject_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssisssi", $name, $code, $periods, $grade_level, $stream, $description, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Subject " . ($action == 'add_subject' ? 'added' : 'updated') . " successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error: " . mysqli_stmt_error($stmt);
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);
    } elseif ($action == 'delete_subject') {
        $id = $_POST['subject_id'];
        $sql = "DELETE FROM subjects WHERE subject_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        $redirect_tab = 'subjects';
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Subject deleted successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error deleting subject. It might be in use in assignments.";
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
    header("Location: manage_core_data.php?tab=" . $redirect_tab);
    exit();
}

// --- Fetch data for display ---
$classrooms_result = mysqli_query($conn, "SELECT * FROM classrooms ORDER BY grade_level, name ASC");
$subjects_result = mysqli_query($conn, "SELECT * FROM subjects ORDER BY name ASC");

$active_tab = $_GET['tab'] ?? 'classrooms';

// Data for edit forms
$edit_classroom = null;
if (isset($_GET['edit_classroom_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM classrooms WHERE classroom_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['edit_classroom_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_classroom = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    $active_tab = 'classrooms';
}

$edit_subject = null;
if (isset($_GET['edit_subject_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM subjects WHERE subject_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['edit_subject_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_subject = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    $active_tab = 'subjects';
}

mysqli_close($conn);
$page_title = 'Manage Core Data';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Manage Core Data</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mt-4" id="coreDataTabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link <?php if($active_tab == 'classrooms') echo 'active'; ?>" id="classrooms-tab" data-bs-toggle="tab" data-bs-target="#classrooms-pane" type="button" role="tab">Classrooms</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link <?php if($active_tab == 'subjects') echo 'active'; ?>" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects-pane" type="button" role="tab">Subjects</button></li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="coreDataTabsContent">
        <!-- Classrooms Pane -->
        <div class="tab-pane fade <?php if ($active_tab == 'classrooms') echo 'show active'; ?>" id="classrooms-pane" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="card" id="classroom-form-card"><div class="card-header"><h5><?php echo $edit_classroom ? 'Edit' : 'Add New'; ?> Classroom</h5></div><div class="card-body">
                        <form action="manage_core_data.php" method="POST">
                            <input type="hidden" name="action" value="<?php echo $edit_classroom ? 'update_classroom' : 'add_classroom'; ?>">
                            <?php if ($edit_classroom): ?><input type="hidden" name="classroom_id" value="<?php echo $edit_classroom['classroom_id']; ?>"><?php endif; ?>
                            <div class="mb-3"><label for="classroom_name" class="form-label">Name</label><input type="text" id="classroom_name" name="classroom_name" class="form-control" value="<?php echo htmlspecialchars($edit_classroom['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
                            <div class="mb-3"><label for="grade_level_class" class="form-label">Grade Level</label><select id="grade_level_class" name="grade_level" class="form-select" required><option value="">-- Select Grade --</option><?php for ($i = 9; $i <= 12; $i++): ?><option value="<?php echo $i; ?>" <?php echo (($edit_classroom['grade_level'] ?? '') == $i) ? 'selected' : ''; ?>><?php echo "Grade " . $i; ?></option><?php endfor; ?></select></div>
                            <div class="mb-3"><label for="capacity" class="form-label">Capacity</label><input type="number" id="capacity" name="capacity" class="form-control" value="<?php echo htmlspecialchars($edit_classroom['capacity'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
                            <div class="mb-3"><label for="resources" class="form-label">Resources</label><textarea id="resources" name="resources" class="form-control"><?php echo htmlspecialchars($edit_classroom['resources'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                            <button type="submit" class="btn btn-primary"><?php echo $edit_classroom ? 'Update' : 'Add'; ?> Classroom</button>
                            <?php if ($edit_classroom): ?><a href="manage_core_data.php?tab=classrooms" class="btn btn-secondary ms-2">Cancel</a><?php endif; ?>
                        </form>
                    </div></div>
                </div>
                <div class="col-md-8">
                    <div class="card"><div class="card-header"><h5>Existing Classrooms</h5></div><div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover">
                        <table class="table table-striped table-hover">
                            <thead class="table-light"><tr><th>Name</th><th>Grade</th><th>Capacity</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($classrooms_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td><td><?php echo htmlspecialchars($row['grade_level']); ?></td><td><?php echo htmlspecialchars($row['capacity']); ?></td>
                                    <td>
                                        <a href="?tab=classrooms&edit_classroom_id=<?php echo $row['classroom_id']; ?>#classroom-form-card" class="btn btn-sm btn-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                        <form action="manage_core_data.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure? This may affect existing assignments.');">
                                            <input type="hidden" name="action" value="delete_classroom"><input type="hidden" name="classroom_id" value="<?php echo $row['classroom_id']; ?>"><button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div></div></div>
                </div>
            </div>
        </div>

        <!-- Subjects Pane -->
        <div class="tab-pane fade <?php if($active_tab == 'subjects') echo 'show active'; ?>" id="subjects-pane" role="tabpanel">
            <div class="row mt-4">
                <div class="col-md-4"><div class="card" id="subject-form-card">
                    <div class="card" id="subject-form-card"><div class="card-header"><h5><?php echo $edit_subject ? 'Edit' : 'Add New'; ?> Subject</h5></div><div class="card-body">
                        <form action="manage_core_data.php" method="POST">
                            <input type="hidden" name="action" value="<?php echo $edit_subject ? 'update_subject' : 'add_subject'; ?>">
                            <?php if ($edit_subject): ?><input type="hidden" name="subject_id" value="<?php echo $edit_subject['subject_id']; ?>"><?php endif; ?>
                            <div class="mb-3"><label for="subject_name" class="form-label">Name</label><input type="text" id="subject_name" name="subject_name" class="form-control" value="<?php echo htmlspecialchars($edit_subject['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
                            <div class="mb-3"><label for="subject_code" class="form-label">Code</label><input type="text" id="subject_code" name="subject_code" class="form-control" value="<?php echo htmlspecialchars($edit_subject['code'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                            <div class="mb-3"><label for="periods_per_week" class="form-label">Periods per Week</label><input type="number" id="periods_per_week" name="periods_per_week" class="form-control" value="<?php echo htmlspecialchars($edit_subject['periods_per_week'] ?? '1'); ?>" required min="1"></div>
                            <div class="mb-3"><label for="grade_level_subject" class="form-label">Grade Level</label><select id="grade_level_subject" name="grade_level" class="form-select"><option value="">All</option><?php for ($i=9; $i<=12; $i++): ?><option value="<?php echo $i; ?>" <?php echo (($edit_subject['grade_level'] ?? '') == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option><?php endfor; ?></select></div>
                            <div class="mb-3"><label for="stream" class="form-label">Stream</label><select id="stream" name="stream" class="form-select"><option value="Both" <?php echo (($edit_subject['stream'] ?? '') == 'Both') ? 'selected' : ''; ?>>Both</option><option value="Natural" <?php echo (($edit_subject['stream'] ?? '') == 'Natural') ? 'selected' : ''; ?>>Natural</option><option value="Social" <?php echo (($edit_subject['stream'] ?? '') == 'Social') ? 'selected' : ''; ?>>Social</option></select></div>
                            <div class="mb-3"><label for="description" class="form-label">Description</label><textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($edit_subject['description'] ?? ''); ?></textarea></div>
                            <button type="submit" class="btn btn-primary"><?php echo $edit_subject ? 'Update' : 'Add'; ?> Subject</button>
                            <?php if ($edit_subject): ?><a href="manage_core_data.php?tab=subjects" class="btn btn-secondary ms-2">Cancel</a><?php endif; ?>
                        </form>
                    </div></div></div>
                </div>
                <div class="col-md-8">
                    <div class="card"><div class="card-header"><h5>Existing Subjects</h5></div><div class="card-body"><div class="table-responsive">
                        <table class="table table-striped table-hover"><table class="table table-striped table-hover">
                            <thead class="table-light"><tr><th>Name</th><th>Code</th><th>Periods/Wk</th><th>Grade</th><th>Stream</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_assoc($subjects_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td><td><?php echo htmlspecialchars($row['code']); ?></td><td><?php echo htmlspecialchars($row['periods_per_week']); ?></td><td><?php echo htmlspecialchars($row['grade_level'] ?? 'All'); ?></td><td><?php echo htmlspecialchars($row['stream']); ?></td>
                                    <td>
                                        <a href="?tab=subjects&edit_subject_id=<?php echo $row['subject_id']; ?>#subject-form-card" class="btn btn-sm btn-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                        <form action="manage_core_data.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure? This may affect existing assignments.');">
                                            <input type="hidden" name="action" value="delete_subject"><input type="hidden" name="subject_id" value="<?php echo $row['subject_id']; ?>"><button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>