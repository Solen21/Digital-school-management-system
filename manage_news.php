<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'config.php'; // Include configuration file

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

    if ($action == 'add_news' || $action == 'update_news') {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $status = $_POST['status'];
        $categories = $_POST['categories'] ?? [];
        $author_id = $_SESSION['user_id'];
        $author_name = $_SESSION['username'];

        if ($action == 'add_news') {
            $sql = "INSERT INTO news (title, content, status, author_id, author_name) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssis", $title, $content, $status, $author_id, $author_name);
        } else { // update_news
            $id = $_POST['news_id'];
            $sql = "UPDATE news SET title = ?, content = ?, status = ? WHERE news_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssi", $title, $content, $status, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $news_id = ($action == 'add_news') ? mysqli_insert_id($conn) : $_POST['news_id'];

            // --- Handle Category Assignments ---
            // First, delete existing category links for this article
            $sql_delete_cats = "DELETE FROM news_article_categories WHERE news_id = ?";
            $stmt_delete_cats = mysqli_prepare($conn, $sql_delete_cats);
            mysqli_stmt_bind_param($stmt_delete_cats, "i", $news_id);
            mysqli_stmt_execute($stmt_delete_cats);
            mysqli_stmt_close($stmt_delete_cats);

            // Then, insert new ones
            if (!empty($categories)) {
                $sql_insert_cat = "INSERT INTO news_article_categories (news_id, category_id) VALUES (?, ?)";
                $stmt_insert_cat = mysqli_prepare($conn, $sql_insert_cat);
                foreach ($categories as $category_id) {
                    mysqli_stmt_bind_param($stmt_insert_cat, "ii", $news_id, $category_id);
                    mysqli_stmt_execute($stmt_insert_cat);
                }
                mysqli_stmt_close($stmt_insert_cat);
            }

            $_SESSION['message'] = "News article " . ($action == 'add_news' ? 'added' : 'updated') . " successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error: " . mysqli_stmt_error($stmt);
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);

    } elseif ($action == 'delete_news') {
        $id = $_POST['news_id'];
        $sql = "DELETE FROM news WHERE news_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "News article deleted successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error deleting news article.";
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
    header("Location: manage_news.php");
    exit();
}

// --- Fetch data for display ---
$filter_status = $_GET['filter_status'] ?? '';
$search_title = $_GET['search_title'] ?? '';

$sql_news = "SELECT * FROM news";
$sql_news = "
    SELECT n.*, GROUP_CONCAT(nac.category_id) as category_ids
    FROM news n
    LEFT JOIN news_article_categories nac ON n.news_id = nac.news_id
";
$where_clauses = []; // Reset for main query
$params = [];
$param_types = '';


if (!empty($filter_status)) {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}
if (!empty($search_title)) {
    $where_clauses[] = "title LIKE ?";
    $params[] = "%" . $search_title . "%";
    $param_types .= 's';
}
if (!empty($where_clauses)) { $sql_news .= " WHERE " . implode(' AND ', $where_clauses); }
$sql_news .= " GROUP BY n.news_id";
$sql_news .= " ORDER BY created_at DESC";

$edit_article = null;
if (isset($_GET['edit_news_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM news WHERE news_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['edit_news_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $edit_article = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // Fetch categories for the article being edited
    $edit_article['categories'] = [];
    // Check if the category table exists before querying to prevent fatal errors
    $table_exists_result = mysqli_query($conn, "SHOW TABLES LIKE 'news_article_categories'");
    if (mysqli_num_rows($table_exists_result) > 0) {
        $stmt_cats = mysqli_prepare($conn, "SELECT category_id FROM news_article_categories WHERE news_id = ?");
        mysqli_stmt_bind_param($stmt_cats, "i", $_GET['edit_news_id']);
        mysqli_stmt_execute($stmt_cats);
        $cat_result = mysqli_stmt_get_result($stmt_cats);
        while($cat_row = mysqli_fetch_assoc($cat_result)) { 
            $edit_article['categories'][] = $cat_row['category_id']; 
        }
        mysqli_stmt_close($stmt_cats);
    }
}

$stmt_news = mysqli_prepare($conn, $sql_news);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_news, $param_types, ...$params);
}
mysqli_stmt_execute($stmt_news);
$result_news = mysqli_stmt_get_result($stmt_news);
$news_articles = [];
while ($row = mysqli_fetch_assoc($result_news)) { $news_articles[] = $row; }
mysqli_stmt_close($stmt_news);

// Fetch all categories for the dropdown
$categories_result = mysqli_query($conn, "SELECT category_id, name FROM news_categories ORDER BY name ASC");

mysqli_close($conn);
$page_title = 'Manage News';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Manage News & Announcements</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Add/Edit Form Column -->
        <div class="col-lg-5">
            <div class="card" id="news-form-card">
                <div class="card-header"><h5><?php echo $edit_article ? 'Edit' : 'Add New'; ?> Article</h5></div>
                <div class="card-body">
                    <form action="manage_news.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_article ? 'update_news' : 'add_news'; ?>">
                        <?php if ($edit_article): ?><input type="hidden" name="news_id" value="<?php echo $edit_article['news_id']; ?>"><?php endif; ?>
                        <div class="mb-3"><label for="title" class="form-label">Title</label><input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_article['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="mb-3"><label for="content" class="form-label">Content</label><textarea id="content" name="content" class="form-control" rows="10"><?php echo htmlspecialchars($edit_article['content'] ?? ''); ?></textarea></div>
                        <div class="mb-3">
                            <label for="categories" class="form-label">Categories</label>
                            <select id="categories" name="categories[]" class="form-select" multiple size="5">
                                <?php mysqli_data_seek($categories_result, 0); while($cat = mysqli_fetch_assoc($categories_result)): ?>
                                    <option value="<?php echo $cat['category_id']; ?>" <?php if(isset($edit_article['categories']) && in_array($cat['category_id'], $edit_article['categories'])) echo 'selected'; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3"><label for="status" class="form-label">Status</label><select id="status" name="status" class="form-select" required><option value="draft" <?php echo (($edit_article['status'] ?? '') == 'draft') ? 'selected' : ''; ?>>Draft</option><option value="published" <?php echo (($edit_article['status'] ?? '') == 'published') ? 'selected' : ''; ?>>Published</option></select></div>
                        <button type="submit" class="btn btn-primary"><?php echo $edit_article ? 'Update' : 'Add'; ?> Article</button>
                        <?php if ($edit_article): ?><a href="manage_news.php" class="btn btn-secondary ms-2">Cancel Edit</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- News List Column -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Existing Articles</h5>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false">
                            <i class="bi bi-funnel-fill"></i> Filter
                        </button>
                    </div>
                    <div class="collapse" id="filterCollapse">
                        <form action="manage_news.php" method="GET" class="row g-2 align-items-end pt-3"><div class="col-md-6"><label for="search_title" class="form-label">Search by Title</label><input type="text" id="search_title" name="search_title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search_title); ?>"></div><div class="col-md-3"><label for="filter_status" class="form-label">Status</label><select id="filter_status" name="filter_status" class="form-select form-select-sm"><option value="">All</option><option value="published" <?php if ($filter_status == 'published') echo 'selected'; ?>>Published</option><option value="draft" <?php if ($filter_status == 'draft') echo 'selected'; ?>>Draft</option></select></div><div class="col-md-3 d-flex gap-2"><button type="submit" class="btn btn-primary btn-sm w-100">Apply</button><a href="manage_news.php" class="btn btn-secondary btn-sm w-100">Clear</a></div></form>
                    </div>
                </div>
                <div class="card-body"><div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light"><tr><th>Title</th><th>Author</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach ($news_articles as $article): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($article['title']); ?></td>
                                <td><?php echo htmlspecialchars($article['author_name']); ?></td>
                                <td><span class="badge <?php echo $article['status'] == 'published' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo ucfirst($article['status']); ?></span></td>
                                <td><?php echo date('Y-m-d', strtotime($article['created_at'])); ?></td>
                                <td>
                                    <a href="?edit_news_id=<?php echo $article['news_id']; ?>#news-form-card" class="btn btn-sm btn-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                    <form action="manage_news.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this article?');">
                                        <input type="hidden" name="action" value="delete_news"><input type="hidden" name="news_id" value="<?php echo $article['news_id']; ?>"><button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>