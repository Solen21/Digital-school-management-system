<?php
session_start();

// 1. Check if the user is logged in and has an appropriate role.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$message = '';
$message_type = ''; // 'success' or 'error'

// Handle POST requests for updating roles or resetting passwords
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'] ?? '';
    $user_id_to_modify = $_POST['user_id'] ?? 0;

    // Prevent admin from modifying their own account
    if ($action !== 'add_user' && $user_id_to_modify == $_SESSION['user_id']) {
        $message = "For security reasons, you cannot modify your own account from this page. Please use the Settings page.";
        $message_type = 'error';
    } else {
        if ($action === 'change_role') {
            $new_role = $_POST['new_role'];
            $allowed_roles = ['admin', 'teacher', 'student', 'director', 'rep', 'guardian'];

            if (in_array($new_role, $allowed_roles)) {
                $sql = "UPDATE users SET role = ? WHERE user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $new_role, $user_id_to_modify);
                if (mysqli_stmt_execute($stmt)) {
                    $message = "User role updated successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Error updating role: " . mysqli_stmt_error($stmt);
                    $message_type = 'error';
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "Invalid role selected.";
                $message_type = 'error';
            }
        } elseif ($action === 'reset_password') {
            // Get user details to generate new password and check role from the users table
            $sql_user_info = "SELECT username, role FROM users WHERE user_id = ?";
            $stmt_info = mysqli_prepare($conn, $sql_user_info);
            mysqli_stmt_bind_param($stmt_info, "i", $user_id_to_modify);
            mysqli_stmt_execute($stmt_info);
            $result_info = mysqli_stmt_get_result($stmt_info);
            $user_info = mysqli_fetch_assoc($result_info);
            mysqli_stmt_close($stmt_info);

            if ($user_info) {
                $new_password_plain = $user_info['username'] . '@reset';
                $new_password_hashed = password_hash($new_password_plain, PASSWORD_DEFAULT);

                mysqli_begin_transaction($conn);
                try {
                    // Update users table
                    $sql_update_user = "UPDATE users SET password = ? WHERE user_id = ?";
                    $stmt_update_user = mysqli_prepare($conn, $sql_update_user);
                    mysqli_stmt_bind_param($stmt_update_user, "si", $new_password_hashed, $user_id_to_modify);
                    if (!mysqli_stmt_execute($stmt_update_user)) {
                        throw new Exception(mysqli_stmt_error($stmt_update_user));
                    }
                    mysqli_stmt_close($stmt_update_user);

                    // If user is a student, also update the students table
                    if ($user_info['role'] === 'student') {
                        $sql_update_student = "UPDATE students SET password = ? WHERE user_id = ?";
                        $stmt_update_student = mysqli_prepare($conn, $sql_update_student);
                        mysqli_stmt_bind_param($stmt_update_student, "si", $new_password_hashed, $user_id_to_modify);
                        if (!mysqli_stmt_execute($stmt_update_student)) {
                           throw new Exception(mysqli_stmt_error($stmt_update_student));
                        }
                        mysqli_stmt_close($stmt_update_student);
                    }

                    mysqli_commit($conn);
                    $message = "Password reset successfully. New temporary password is: <strong>" . htmlspecialchars($new_password_plain) . "</strong>";
                    $message_type = 'success';

                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $message = "Error resetting password: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
        } elseif ($action === 'add_user') {
            try {
                // Generate a unique numeric username
                mysqli_query($conn, "LOCK TABLES users WRITE");
                $result = mysqli_query($conn, "SELECT MAX(user_id) as last_id FROM users");
                $row = mysqli_fetch_assoc($result);
                $next_id = ($row['last_id'] ?? 0) + 1;
                $user_username = str_pad($next_id, 6, '0', STR_PAD_LEFT);
                mysqli_query($conn, "UNLOCK TABLES");

                // Set password and role from form
                $user_password_plain = $user_username . '@123';
                $user_password_hashed = password_hash($user_password_plain, PASSWORD_DEFAULT);
                $user_role = $_POST['role'];

                $allowed_roles_to_create = ['admin', 'director', 'rep', 'guardian'];
                if (!in_array($user_role, $allowed_roles_to_create)) {
                    throw new Exception("Invalid role selected for creation.");
                }

                $sql_user = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
                $stmt_user = mysqli_prepare($conn, $sql_user);
                mysqli_stmt_bind_param($stmt_user, "sss", $user_username, $user_password_hashed, $user_role);
                if (mysqli_stmt_execute($stmt_user)) {
                    $message = "User created successfully! <br>Username: <strong>{$user_username}</strong> <br>Temporary Password: <strong>{$user_password_plain}</strong>";
                    $message_type = 'success';
                } else { throw new Exception(mysqli_stmt_error($stmt_user)); }
            } catch (Exception $e) {
                $message = "Error creating user: " . $e->getMessage();
                $message_type = 'error';
                }
            }
        } // This was the extra closing brace
}

// --- Search and Filter Logic ---
$search_username = $_GET['search_username'] ?? '';
$filter_role = $_GET['filter_role'] ?? '';

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search_username)) {
    $where_clauses[] = "username LIKE ?";
    $params[] = "%" . $search_username . "%";
    $param_types .= 's';
}
if (!empty($filter_role)) {
    $where_clauses[] = "role = ?";
    $params[] = $filter_role;
    $param_types .= 's';
}

$users = [];
$sql = "SELECT user_id, username, role, created_at FROM users";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY user_id ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
}
mysqli_stmt_close($stmt);

mysqli_close($conn);
$allowed_roles = ['admin', 'teacher', 'student', 'director', 'rep', 'guardian'];
$page_title = 'Manage Users';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Manage User Accounts</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Action Bar -->
    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus-fill me-2"></i>Create New User</button>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse"><i class="bi bi-funnel-fill me-2"></i>Filter & Search</button>
            </div>
        </div>
        <div class="collapse" id="filterCollapse">
            <div class="card-footer">
                <form action="manage_users.php" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4"><label for="search_username" class="form-label">Search by Username</label><input type="text" class="form-control" id="search_username" name="search_username" value="<?php echo htmlspecialchars($search_username); ?>"></div>
                    <div class="col-md-3"><label for="filter_role" class="form-label">Filter by Role</label><select id="filter_role" name="filter_role" class="form-select"><option value="">All Roles</option><?php foreach ($allowed_roles as $role_option): ?><option value="<?php echo $role_option; ?>" <?php if($filter_role == $role_option) echo 'selected'; ?>><?php echo ucfirst($role_option); ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-auto"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                    <div class="col-md-auto"><a href="manage_users.php" class="btn btn-secondary w-100">Clear</a></div>
                </form>
            </div>
        </div>
    </div>

    <!-- User Table -->
    <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="bi bi-person-gear me-2"></i>User Accounts (<?php echo count($users); ?>)</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>User ID</th><th>Username</th><th>Role</th><th>Created At</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="5" class="text-center">No users found matching your criteria.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($user['role'])); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['user_id'] != $_SESSION['user_id']): // Don't allow self-modification ?>
                                    <form action="manage_users.php" method="POST" class="d-inline-flex gap-2 align-items-center">
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <select name="new_role" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <?php foreach ($allowed_roles as $role_option): ?>
                                            <option value="<?php echo $role_option; ?>" <?php echo ($user['role'] == $role_option) ? 'selected' : ''; ?>><?php echo ucfirst($role_option); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <a href="admin_reset_password.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-danger" title="Reset Password" onclick="return confirm('Are you sure you want to reset the password for this user? A new temporary password will be generated.');"><i class="bi bi-key-fill"></i></a>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Current User</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addUserModalLabel">Create New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="manage_users.php" method="POST">
        <div class="modal-body">
            <input type="hidden" name="action" value="add_user">
            <p class="text-muted small">This creates a basic user account. The username and a temporary password will be generated automatically. To create a Student or Teacher with a full profile, please use their specific registration forms.</p>
            <div class="mb-3">
                <label for="role" class="form-label">Select Role for New User</label>
                <select id="role" name="role" class="form-select" required>
                    <option value="admin">Admin</option>
                    <option value="director">Director</option>
                    <option value="rep">Class Representative</option>
                    <option value="guardian">Guardian</option>
                </select>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Create User</button></div>
      </form>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>