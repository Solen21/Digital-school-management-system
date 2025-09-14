<?php
session_start();

// 1. Check if the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

// --- Filtering Logic ---
$filter_category = $_GET['category'] ?? '';
$search_title = $_GET['search'] ?? '';

$sql = "
    SELECT 
        n.news_id, n.title, n.content, n.publish_date, n.image_path, u.username as author_name,
        GROUP_CONCAT(DISTINCT nc.name ORDER BY nc.name SEPARATOR ', ') as categories
    FROM news n
    JOIN users u ON n.author_id = u.user_id
    LEFT JOIN news_article_categories nac ON n.news_id = nac.news_id
    LEFT JOIN news_categories nc ON nac.category_id = nc.category_id
";

$where_clauses = ["n.status = 'published'", "n.publish_date <= NOW()"];
$params = [];
$param_types = '';

if (!empty($filter_category)) {
    $where_clauses[] = "n.news_id IN (SELECT news_id FROM news_article_categories WHERE category_id = ?)";
    $params[] = $filter_category;
    $param_types .= 'i';
}
if (!empty($search_title)) {
    $where_clauses[] = "n.title LIKE ?";
    $params[] = "%" . $search_title . "%";
    $param_types .= 's';
}

$sql .= " WHERE " . implode(' AND ', $where_clauses);
$sql .= " GROUP BY n.news_id ORDER BY n.publish_date DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$news_articles = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

// Fetch all categories for the filter dropdown
$categories_result = mysqli_query($conn, "SELECT category_id, name FROM news_categories ORDER BY name ASC");
$all_categories = mysqli_fetch_all($categories_result, MYSQLI_ASSOC);

mysqli_close($conn);

$page_title = 'All News';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">All News & Announcements</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <!-- Filter Form -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <form action="all_news.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-5"><label for="search" class="form-label">Search by Title</label><input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_title); ?>"></div>
                <div class="col-md-4"><label for="category" class="form-label">Filter by Category</label><select id="category" name="category" class="form-select"><option value="">All Categories</option><?php foreach ($all_categories as $cat): ?><option value="<?php echo $cat['category_id']; ?>" <?php if($filter_category == $cat['category_id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['name']); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            </form>
        </div>
    </div>

    <?php if (empty($news_articles)): ?>
        <div class="alert alert-info">No news articles found matching your criteria.</div>
    <?php else: ?>
        <div class="accordion" id="newsAccordion">
            <?php foreach ($news_articles as $index => $article): ?>
                <div class="accordion-item" id="news-<?php echo $article['news_id']; ?>">
                    <h2 class="accordion-header" id="heading-<?php echo $article['news_id']; ?>">
                        <button class="accordion-button <?php if ($index > 0) echo 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $article['news_id']; ?>" aria-expanded="<?php echo $index == 0 ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo $article['news_id']; ?>">
                            <span class="fw-bold me-3"><?php echo htmlspecialchars($article['title']); ?></span>
                        </button>
                    </h2>
                    <div id="collapse-<?php echo $article['news_id']; ?>" class="accordion-collapse collapse <?php if ($index == 0) echo 'show'; ?>" aria-labelledby="heading-<?php echo $article['news_id']; ?>" data-bs-parent="#newsAccordion">
                        <div class="accordion-body">
                            <div class="d-flex justify-content-between text-muted small mb-3">
                                <span>By <strong><?php echo htmlspecialchars($article['author_name']); ?></strong> on <?php echo date('F j, Y', strtotime($article['publish_date'])); ?></span>
                                <?php if (!empty($article['categories'])): ?>
                                    <span>Categories: <?php echo htmlspecialchars($article['categories']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($article['image_path']) && file_exists($article['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($article['image_path']); ?>" class="img-fluid rounded mb-3" style="max-height: 300px; width: 100%; object-fit: cover;" alt="">
                            <?php endif; ?>
                            <div><?php echo $article['content']; // Assuming content is safe HTML from TinyMCE ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>