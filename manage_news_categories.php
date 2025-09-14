<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
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

// --- POST Request Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'add_category' || $action == 'update_category') {
        $name = trim($_POST['name']);
        if (empty($name)) {
            $_SESSION['message'] = "Category name cannot be empty.";
            $_SESSION['message_type'] = 'danger';
        } else {
            if ($action == 'add_category') {
                $sql = "INSERT INTO news_categories (name) VALUES (?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "s", $name);
            } else { // update_category
                $id = $_POST['category_id'];
                $sql = "UPDATE news_categories SET name = ? WHERE category_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $name, $id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "Category " . ($action == 'add_category' ? 'added' : 'updated') . " successfully.";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Error: " . mysqli_stmt_error($stmt);
                $_SESSION['message_type'] = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action == 'delete_category') {
        $id = $_POST['category_id'];
        $sql = "DELETE FROM news_categories WHERE category_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Category deleted successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error deleting category. It might be in use.";
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
    header("Location: manage_news_categories.php");
    exit();
}

// --- Fetch data for display ---
$categories_result = mysqli_query($conn, "SELECT * FROM news_categories ORDER BY name ASC");

$edit_category = null;
if (isset($_GET['edit_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM news_categories WHERE category_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['edit_id']);
    mysqli_stmt_execute($stmt);
    $edit_category = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
$page_title = 'Manage News Categories';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Manage News Categories</h1>
        <a href="manage_news.php" class="btn btn-secondary">Back to News</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card" id="category-form-card">
                <div class="card-header"><h5><?php echo $edit_category ? 'Edit' : 'Add New'; ?> Category</h5></div>
                <div class="card-body">
                    <form action="manage_news_categories.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_category ? 'update_category' : 'add_category'; ?>">
                        <?php if ($edit_category): ?><input type="hidden" name="category_id" value="<?php echo $edit_category['category_id']; ?>"><?php endif; ?>
                        <div class="mb-3"><label for="name" class="form-label">Category Name</label><input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_category['name'] ?? ''); ?>" required></div>
                        <button type="submit" class="btn btn-primary"><?php echo $edit_category ? 'Update' : 'Add'; ?> Category</button>
                        <?php if ($edit_category): ?><a href="manage_news_categories.php" class="btn btn-secondary ms-2">Cancel</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h5>Existing Categories</h5></div>
                <div class="card-body"><div class="table-responsive"><table class="table table-striped table-hover">
                    <thead class="table-light"><tr><th>Name</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($categories_result)): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td>
                                <a href="?edit_id=<?php echo $row['category_id']; ?>#category-form-card" class="btn btn-sm btn-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                <form action="manage_news_categories.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_id" value="<?php echo $row['category_id']; ?>"><button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-trash-fill"></i></button></form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table></div></div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>