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

// Fetch all published news articles
$news_articles = [];
$sql = "
    SELECT n.*, u.username as author_name,
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
    $where_clauses[] = "nac.category_id = ?";
    $params[] = $filter_category;
    $param_types .= 'i';
}

$sql .= " WHERE " . implode(' AND ', $where_clauses) . " GROUP BY n.news_id ORDER BY n.publish_date DESC";

$result = mysqli_query($conn, $sql);
if ($result) {
    $news_articles = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

mysqli_close($conn);

$page_title = 'School News & Announcements';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">School News & Announcements</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <!-- Category Filter -->
    <div class="mb-4">
        <form action="view_news.php" method="GET" class="d-flex gap-2">
            <select name="category" class="form-select w-auto" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php
                    $conn = mysqli_connect("localhost", "root", "", "sms");
                    $categories_result = mysqli_query($conn, "SELECT * FROM news_categories ORDER BY name ASC");
                    while($cat = mysqli_fetch_assoc($categories_result)) { echo "<option value='{$cat['category_id']}' " . ($filter_category == $cat['category_id'] ? 'selected' : '') . ">" . htmlspecialchars($cat['name']) . "</option>"; }
                ?>
            </select>
            <a href="view_news.php" class="btn btn-outline-secondary">Clear</a>
        </form>
    </div>

    <?php if (empty($news_articles)): ?>
        <div class="alert alert-info">There are no news articles to display at this time.</div>
    <?php else: ?>
        <div class="accordion" id="newsAccordion">
            <?php foreach ($news_articles as $index => $article): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-<?php echo $article['news_id']; ?>">
                        <a name="news-<?php echo $article['news_id']; ?>"></a> <!-- Anchor for dashboard links -->
                        <button class="accordion-button <?php if ($index > 0) echo 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $article['news_id']; ?>" aria-expanded="<?php echo $index == 0 ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo $article['news_id']; ?>">
                            <span class="fw-bold me-3"><?php echo htmlspecialchars($article['title']); ?></span>
                            <small class="text-muted">by <?php echo htmlspecialchars($article['author_name']); ?> on <?php echo date('M d, Y', strtotime($article['publish_date'])); ?></small>
                        </button>
                    </h2>
                    <div id="collapse-<?php echo $article['news_id']; ?>" class="accordion-collapse collapse <?php if ($index == 0) echo 'show'; ?>" aria-labelledby="heading-<?php echo $article['news_id']; ?>" data-bs-parent="#newsAccordion">
                        <div class="accordion-body">
                            <?php if (!empty($article['categories'])): ?>
                                <div class="mb-2">
                                    <?php foreach (explode(', ', $article['categories']) as $category): ?><span class="badge bg-primary me-1"><?php echo htmlspecialchars($category); ?></span><?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($article['image_path']) && file_exists($article['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($article['image_path']); ?>" class="img-fluid rounded mb-3" alt="<?php echo htmlspecialchars($article['title']); ?>">
                            <?php endif; ?>
                            <?php echo $article['content']; // Output raw HTML from TinyMCE ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>