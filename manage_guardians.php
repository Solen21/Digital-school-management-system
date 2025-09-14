<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
$page_title = 'Manage Guardians';
require_once 'functions.php';
// --- Handle POST request to add a new guardian ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_guardian'])) {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    if (empty($name) || empty($phone)) {
        $_SESSION['message'] = "Guardian name and phone number are required.";
        $_SESSION['message_type'] = 'danger';
    } else {
        mysqli_begin_transaction($conn);
        try {
            // 1. Create a user account for the guardian using the centralized function
            $guardian_user_data = create_user_account($conn, 'guardian', $phone, 'g');
            $new_user_id = $guardian_user_data['user_id'];
            $username = $guardian_user_data['username'];
            $password_plain = $guardian_user_data['password_plain'];

            // 2. Create the guardian profile
            $sql_guardian = "INSERT INTO guardians (user_id, name, phone, email) VALUES (?, ?, ?, ?)";
            $stmt_guardian = mysqli_prepare($conn, $sql_guardian);
            mysqli_stmt_bind_param($stmt_guardian, "isss", $new_user_id, $name, $phone, $email);
            if (!mysqli_stmt_execute($stmt_guardian)) {
                throw new Exception("Failed to create guardian profile: " . mysqli_stmt_error($stmt_guardian));
            }
            mysqli_stmt_close($stmt_guardian);

            mysqli_commit($conn);
            $_SESSION['message'] = "Guardian added successfully. Username: <strong>$username</strong>, Password: <strong>$password_plain</strong>";
            $_SESSION['message_type'] = 'success';

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['message'] = "Error: " . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
    }
    header("Location: manage_guardians.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_guardian'])) {
    $guardian_id_to_delete = $_POST['guardian_id'];

    mysqli_begin_transaction($conn);
    try {
        // --- SAFETY CHECK: Ensure guardian is not linked to any students ---
        $sql_check_links = "SELECT COUNT(*) as student_count FROM student_guardian_map WHERE guardian_id = ?";
        $stmt_check = mysqli_prepare($conn, $sql_check_links);
        mysqli_stmt_bind_param($stmt_check, "i", $guardian_id_to_delete);
        mysqli_stmt_execute($stmt_check);
        $result_check = mysqli_stmt_get_result($stmt_check);
        $link_count = mysqli_fetch_assoc($result_check)['student_count'];
        mysqli_stmt_close($stmt_check);

        if ($link_count > 0) {
            throw new Exception("Cannot delete guardian. They are still linked to {$link_count} student(s). Please reassign the student(s) to another guardian first.");
        }
        // --- END SAFETY CHECK ---

        // Get guardian details for logging
        $sql_get_guardian = "SELECT user_id, name FROM guardians WHERE guardian_id = ?";
        $stmt_get = mysqli_prepare($conn, $sql_get_guardian);
        mysqli_stmt_bind_param($stmt_get, "i", $guardian_id_to_delete);
        mysqli_stmt_execute($stmt_get);
        $guardian_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get));
        mysqli_stmt_close($stmt_get);

        if (!$guardian_data) {
            throw new Exception("Guardian not found.");
        }

        // The check above ensures no links exist. Now we can safely delete.
        // Delete the guardian record. If the DB has ON DELETE CASCADE for the user_id, the user will also be deleted.
        $sql_delete_guardian = "DELETE FROM guardians WHERE guardian_id = ?";
        $stmt_delete_guardian = mysqli_prepare($conn, $sql_delete_guardian);
        mysqli_stmt_bind_param($stmt_delete_guardian, "i", $guardian_id_to_delete);
        if (!mysqli_stmt_execute($stmt_delete_guardian)) {
            throw new Exception("Failed to delete guardian record.");
        }
        mysqli_stmt_close($stmt_delete_guardian);

        // Also delete the associated user account explicitly to be safe.
        // The `users` table's `user_id` is the foreign key in the `guardians` table.
        if ($guardian_data['user_id']) {
            $sql_delete_user = "DELETE FROM users WHERE user_id = ?";
            $stmt_delete_user = mysqli_prepare($conn, $sql_delete_user);
            mysqli_stmt_bind_param($stmt_delete_user, "i", $guardian_data['user_id']);
            if (!mysqli_stmt_execute($stmt_delete_user)) {
                throw new Exception("Failed to delete user account.");
            }
            mysqli_stmt_close($stmt_delete_user);
        }

        mysqli_commit($conn);
        log_activity($conn, 'delete_guardian', $guardian_id_to_delete, "Deleted guardian: " . $guardian_data['name']);
        $_SESSION['message'] = "Guardian deleted successfully.";
        $_SESSION['message_type'] = 'success';

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = "Error deleting guardian: " . $e->getMessage();
        $_SESSION['message_type'] = 'danger';
    }
    header("Location: manage_guardians.php");
    exit();
}

// --- Filtering & Pagination ---
$search_query = $_GET['search'] ?? '';
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search_query)) {
    $where_clauses[] = "(g.name LIKE ? OR g.phone LIKE ? OR g.email LIKE ? OR u.username LIKE ?)";
    $search_term = "%" . $search_query . "%";
    array_push($params, $search_term, $search_term, $search_term, $search_term);
    $param_types .= 'ssss';
}

$guardians = [];
$sql_guardians = "SELECT g.*, u.username FROM guardians g LEFT JOIN users u ON g.user_id = u.user_id ORDER BY g.name ASC";

// --- Count total records for pagination ---
$sql_count = "SELECT COUNT(g.guardian_id) as total FROM guardians g LEFT JOIN users u ON g.user_id = u.user_id";
if (!empty($where_clauses)) { $sql_count .= " WHERE " . implode(' AND ', $where_clauses); }
$stmt_count = mysqli_prepare($conn, $sql_count);
if (!empty($params)) { mysqli_stmt_bind_param($stmt_count, $param_types, ...$params); }
mysqli_stmt_execute($stmt_count);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'] ?? 0;
mysqli_stmt_close($stmt_count);

$records_per_page = 15;
$total_pages = ceil($total_records / $records_per_page);
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// --- Fetch paginated guardians ---
$sql_guardians = "SELECT g.*, u.username FROM guardians g LEFT JOIN users u ON g.user_id = u.user_id";
if (!empty($where_clauses)) { $sql_guardians .= " WHERE " . implode(' AND ', $where_clauses); }
$sql_guardians .= " ORDER BY g.name ASC LIMIT ? OFFSET ?";

$stmt_guardians = mysqli_prepare($conn, $sql_guardians);
$current_params = array_merge($params, [$records_per_page, $offset]);
$current_param_types = $param_types . 'ii';
mysqli_stmt_bind_param($stmt_guardians, $current_param_types, ...$current_params);
mysqli_stmt_execute($stmt_guardians);
$result_guardians = mysqli_stmt_get_result($stmt_guardians);

if ($result_guardians) {
    while ($row = mysqli_fetch_assoc($result_guardians)) {
        $guardians[$row['guardian_id']] = $row;
        $guardians[$row['guardian_id']]['students'] = []; // Initialize student array
    }
}
mysqli_stmt_close($stmt_guardians);

// Fetch all student links in one query to avoid N+1 problem
$sql_links = "SELECT sgm.guardian_id, s.first_name, s.last_name FROM student_guardian_map sgm JOIN students s ON sgm.student_id = s.student_id";
$result_links = mysqli_query($conn, $sql_links);
if ($result_links) {
    while ($link = mysqli_fetch_assoc($result_links)) {
        if (isset($guardians[$link['guardian_id']])) {
            $guardians[$link['guardian_id']]['students'][] = $link['first_name'] . ' ' . $link['last_name'];
        }
    }
}

mysqli_close($conn);
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Manage Guardians</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

    <div class="row g-4">
        <!-- Add Guardian Form -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Add New Guardian</h5></div>
                <div class="card-body">
                    <form action="manage_guardians.php" method="POST">
                        <input type="hidden" name="add_guardian" value="1">
                        <div class="mb-3"><label for="name" class="form-label">Full Name</label><input type="text" class="form-control" id="name" name="name" required></div>
                        <div class="mb-3"><label for="phone" class="form-label">Phone Number</label><input type="tel" class="form-control" id="phone" name="phone" required></div>
                        <div class="mb-3"><label for="email" class="form-label">Email (Optional)</label><input type="email" class="form-control" id="email" name="email"></div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-circle-fill me-2"></i>Add Guardian</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Existing Guardians List -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">Existing Guardians</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Phone / Email</th>
                                    <th>Linked Students</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($guardians)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No guardians found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($guardians as $guardian): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($guardian['name']); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($guardian['username'] ?? 'N/A'); ?></span></td>
                                            <td>
                                                <div><?php echo htmlspecialchars($guardian['phone']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($guardian['email'] ?? ''); ?></small>
                                            </td>
                                            <td>
                                                <?php if (!empty($guardian['students'])): ?>
                                                    <ul class="list-unstyled mb-0" style="font-size: 0.9em;">
                                                        <?php foreach ($guardian['students'] as $student_name): ?>
                                                            <li><?php echo htmlspecialchars($student_name); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <span class="text-muted fst-italic">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form action="manage_guardians.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="guardian_id" value="<?php echo $guardian['guardian_id']; ?>">
                                                    <a href="edit_guardian.php?id=<?php echo $guardian['guardian_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                                    <button type="submit" name="delete_guardian" 
                                                            class="btn btn-sm btn-outline-danger" 
                                                            title="<?php echo empty($guardian['students']) ? 'Delete Guardian' : 'Cannot delete: Guardian is linked to students.'; ?>" 
                                                            onclick="return confirm('Are you sure you want to delete this guardian? This action cannot be undone.');"
                                                            <?php if (!empty($guardian['students'])) echo 'disabled'; ?>>
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
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>