<?php
session_start();

// 1. Check if the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 2. Check if the user has an appropriate role (admin or director).
if (!in_array($_SESSION['role'], ['admin', 'director'])) {
    die("<h1>Access Denied</h1><p>You do not have permission to view this page. <a href='dashboard.php'>Return to Dashboard</a></p>");
}

require_once 'data/db_connect.php';
require_once 'functions.php';

// --- Handle POST request to delete a teacher ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_teacher'])) {
    $teacher_id_to_delete = $_POST['teacher_id'];

    mysqli_begin_transaction($conn);
    try {
        // --- SAFETY CHECK: Ensure teacher is not assigned to any subjects ---
        $sql_check_links = "SELECT COUNT(*) as assignment_count FROM subject_assignments WHERE teacher_id = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check_links);
        mysqli_stmt_bind_param($stmt_check, "i", $teacher_id_to_delete);
        mysqli_stmt_execute($stmt_check);
        $link_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check))['assignment_count'];
        mysqli_stmt_close($stmt_check);

        if ($link_count > 0) {
            throw new Exception("Cannot delete teacher. They are still assigned to {$link_count} subject(s). Please unassign them first from the 'Assign Subjects to Teachers' page.");
        }

        // Get teacher details for logging
        $sql_get_teacher = "SELECT user_id, first_name, last_name FROM teachers WHERE teacher_id = ?";
        $stmt_get = mysqli_prepare($conn, $sql_get_teacher);
        mysqli_stmt_bind_param($stmt_get, "i", $teacher_id_to_delete);
        mysqli_stmt_execute($stmt_get);
        $teacher_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
        mysqli_stmt_close($stmt_get);

        if (!$teacher_data) {
            throw new Exception("Teacher not found.");
        }

        // Delete the teacher record
        $sql_delete_teacher = "DELETE FROM teachers WHERE teacher_id = ?";
        $stmt_delete_teacher = mysqli_prepare($conn, $sql_delete_teacher);
        mysqli_stmt_bind_param($stmt_delete_teacher, "i", $teacher_id_to_delete);
        if (!mysqli_stmt_execute($stmt_delete_teacher)) {
            throw new Exception("Failed to delete teacher record.");
        }
        mysqli_stmt_close($stmt_delete_teacher);

        // Delete the associated user account
        if ($teacher_data['user_id']) {
            $sql_delete_user = "DELETE FROM users WHERE user_id = ?";
            $stmt_delete_user = mysqli_prepare($conn, $sql_delete_user);
            mysqli_stmt_bind_param($stmt_delete_user, "i", $teacher_data['user_id']);
            if (!mysqli_stmt_execute($stmt_delete_user)) {
                throw new Exception("Failed to delete user account.");
            }
            mysqli_stmt_close($stmt_delete_user);
        }

        mysqli_commit($conn);
        log_activity($conn, 'delete_teacher', $teacher_id_to_delete, "Deleted teacher: " . $teacher_data['first_name'] . ' ' . $teacher_data['last_name']);
        $_SESSION['import_message'] = "Teacher deleted successfully.";
        $_SESSION['import_message_type'] = 'success';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['import_message'] = "Error deleting teacher: " . $e->getMessage();
        $_SESSION['import_message_type'] = 'danger';
    }
    header("Location: manage_teachers.php");
    exit();
}

// --- Search and Filter Logic ---
$search_name = $_GET['search_name'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search_name)) {
    $where_clauses[] = "(t.first_name LIKE ? OR t.last_name LIKE ? OR CONCAT(t.first_name, ' ', t.last_name) LIKE ?)";
    $search_term = "%" . $search_name . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'sss';
}
if (!empty($filter_status)) {
    $where_clauses[] = "t.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

// --- Pagination Logic ---
$records_per_page = 15; // Number of teachers per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// --- Count total records for pagination ---
$sql_count = "SELECT COUNT(t.teacher_id) as total FROM teachers t";
if (!empty($where_clauses)) {
    $sql_count .= " WHERE " . implode(' AND ', $where_clauses);
}

$stmt_count = mysqli_prepare($conn, $sql_count);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $param_types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$result_count = mysqli_stmt_get_result($stmt_count);
$total_records = mysqli_fetch_assoc($result_count)['total'] ?? 0;
mysqli_stmt_close($stmt_count);

$total_pages = ceil($total_records / $records_per_page);

// --- Fetch teachers for the current page ---
$teachers = [];
$sql = "
    SELECT 
        t.teacher_id, t.user_id, t.first_name, t.middle_name, t.last_name, t.email, t.phone, t.status,
        (SELECT COUNT(*) FROM subject_assignments sa WHERE sa.teacher_id = t.teacher_id) as assignment_count
    FROM 
        teachers t
";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY t.last_name, t.first_name LIMIT ? OFFSET ?";

$params[] = $records_per_page;
$params[] = $offset;
$param_types .= 'ii';

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $teachers[] = $row;
    }
}
mysqli_stmt_close($stmt);

mysqli_close($conn);

$page_title = 'Manage Teachers';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Manage Teachers</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if (isset($_SESSION['import_message'])): // This session is used for import, delete, and other actions ?>
        <div class="alert alert-<?php echo $_SESSION['import_message_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['import_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['import_message'], $_SESSION['import_message_type']); ?>
    <?php endif; ?>

    <!-- Action Buttons and Import Section -->
    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <a href="add_teacher.php" class="btn btn-success"><i class="bi bi-person-plus-fill me-2"></i>Add New Teacher</a>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#importCollapse" aria-expanded="false" aria-controls="importCollapse"><i class="bi bi-upload me-2"></i>Import/Export</button>
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse"><i class="bi bi-funnel-fill me-2"></i>Filter & Search</button>
            </div>
        </div>
        <div class="collapse" id="importCollapse">
            <div class="card-footer">
                <div class="row align-items-center">
                    <div class="col-md-6 border-end">
                         <h5>Import Teachers from Excel</h5>
                         <p class="mb-md-0"><small>Upload an Excel (.xlsx) file with teacher data. <a href="export_teachers.php?template=true">Download the template here</a>.</small></p>
                         <form action="import_teachers.php" method="POST" enctype="multipart/form-data" class="d-flex gap-2 mt-2">
                             <input type="file" class="form-control" name="teacher_file" accept=".xlsx" required>
                             <button type="submit" class="btn btn-warning text-dark flex-shrink-0">Import</button>
                         </form>
                    </div>
                    <div class="col-md-6 ps-md-4">
                         <h5>Export Data</h5>
                         <p class="mb-md-0"><small>Download a complete list of all teachers currently in the system as an Excel file.</small></p>
                         <a href="export_teachers.php" class="btn btn-success mt-2"><i class="bi bi-file-earmark-excel-fill me-2"></i>Export All to Excel</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="collapse" id="filterCollapse">
            <div class="card-footer">
                <form action="manage_teachers.php" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5"><label for="search_name" class="form-label">Search by Name</label><input type="text" class="form-control" id="search_name" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>"></div>
                    <div class="col-md-3"><label for="filter_status" class="form-label">Status</label><select id="filter_status" name="filter_status" class="form-select"><option value="">All</option><option value="active" <?php if($filter_status == 'active') echo 'selected'; ?>>Active</option><option value="inactive" <?php if($filter_status == 'inactive') echo 'selected'; ?>>Inactive</option></select></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                    <div class="col-md-2"><a href="manage_teachers.php" class="btn btn-secondary w-100">Clear</a></div>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-person-workspace me-2"></i>Teacher List (<?php echo $total_records; ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>ID</th><th>Full Name</th><th>Email / Phone</th><th>Status</th><th>Assignments</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($teachers)): ?>
                            <tr><td colspan="6" class="text-center">No teachers found matching your criteria. <a href="manage_teachers.php">Clear filters</a>.</td></tr>
                        <?php else: ?>
                            <?php foreach ($teachers as $teacher): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($teacher['teacher_id']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['middle_name'] . ' ' . $teacher['last_name']); ?></td>
                                <td>
                                    <div><?php echo htmlspecialchars($teacher['email'] ?? 'N/A'); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($teacher['phone']); ?></small>
                                </td>
                                <td><span class="badge <?php echo $teacher['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars(ucfirst($teacher['status'])); ?></span></td>
                                <td><span class="badge bg-info text-dark"><?php echo $teacher['assignment_count']; ?></span></td>
                                <td>
                                    <form action="manage_teachers.php" method="POST" class="d-inline">
                                        <input type="hidden" name="teacher_id" value="<?php echo $teacher['teacher_id']; ?>">
                                        <a href="view_profile.php?user_id=<?php echo $teacher['user_id']; ?>" class="btn btn-sm btn-info" title="View Profile"><i class="bi bi-eye-fill"></i></a>
                                        <a href="edit_teacher.php?id=<?php echo $teacher['teacher_id']; ?>" class="btn btn-sm btn-primary" title="Edit Teacher"><i class="bi bi-pencil-fill"></i></a>
                                        <button type="submit" name="delete_teacher" 
                                                class="btn btn-sm btn-danger" 
                                                title="<?php echo $teacher['assignment_count'] > 0 ? 'Cannot delete: Teacher has active assignments.' : 'Delete Teacher'; ?>" 
                                                onclick="return confirm('Are you sure you want to permanently delete this teacher? This action cannot be undone.');"
                                                <?php if ($teacher['assignment_count'] > 0) echo 'disabled'; ?>>
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'pagination_controls.php'; ?>
</div>

<?php include 'footer.php'; ?>