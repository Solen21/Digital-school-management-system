<?php
$page_title = 'Search Results';
include 'header.php';

// Ensure user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$query = $_GET['q'] ?? '';
$search_term = '%' . $query . '%';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$sort = $_GET['sort'] ?? 'relevance';
$results_per_page = 15;
$offset = ($page - 1) * $results_per_page;

$results = [];
$total_results = 0;

if (!empty(trim($query))) {
    // --- Count total results for pagination ---
    $sql_count = "
        SELECT SUM(total) as total_count FROM (
            (SELECT COUNT(*) as total FROM students WHERE CONCAT(first_name, ' ', last_name) LIKE ? OR first_name LIKE ? OR last_name LIKE ?)
            UNION ALL
            (SELECT COUNT(*) as total FROM teachers WHERE CONCAT(first_name, ' ', last_name) LIKE ? OR first_name LIKE ? OR last_name LIKE ?)
            UNION ALL
            (SELECT COUNT(*) as total FROM subjects WHERE name LIKE ? OR code LIKE ?)
        ) as counts
    ";
    $stmt_count = mysqli_prepare($conn, $sql_count);
    mysqli_stmt_bind_param($stmt_count, "ssssssss", $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term);
    mysqli_stmt_execute($stmt_count);
    $count_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count));
    $total_results = $count_result['total_count'] ?? 0;
    mysqli_stmt_close($stmt_count);

    $total_pages = ceil($total_results / $results_per_page);

    // --- Build Order By Clause ---
    $sort_options = [
        'relevance' => 'CASE WHEN type = \'student\' THEN 1 WHEN type = \'teacher\' THEN 2 ELSE 3 END, name ASC',
        'name_asc' => 'name ASC',
        'name_desc' => 'name DESC',
        'type_asc' => 'type ASC, name ASC'
    ];
    $order_by_clause = $sort_options[$sort] ?? $sort_options['relevance'];

    // --- Fetch paginated results ---
    $sql_search = "
        (SELECT user_id as id, CONCAT(first_name, ' ', last_name) as name, grade_level as detail, 'student' as type FROM students WHERE CONCAT(first_name, ' ', last_name) LIKE ? OR first_name LIKE ? OR last_name LIKE ?)
        UNION ALL
        (SELECT user_id as id, CONCAT(first_name, ' ', last_name) as name, 'Teacher' as detail, 'teacher' as type FROM teachers WHERE CONCAT(first_name, ' ', last_name) LIKE ? OR first_name LIKE ? OR last_name LIKE ?)
        UNION ALL
        (SELECT subject_id as id, name, code as detail, 'subject' as type FROM subjects WHERE name LIKE ? OR code LIKE ?)
        ORDER BY ".$order_by_clause."
        LIMIT ? OFFSET ?
    ";
    $stmt_search = mysqli_prepare($conn, $sql_search);
    mysqli_stmt_bind_param($stmt_search, "ssssssssii", $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $search_term, $results_per_page, $offset);
    mysqli_stmt_execute($stmt_search);
    $result = mysqli_stmt_get_result($stmt_search);
    while ($row = mysqli_fetch_assoc($result)) {
        $results[] = $row;
    }
    mysqli_stmt_close($stmt_search);
}

mysqli_close($conn);
?>

<div class="container">
    <h1 class="mb-3">Search Results for "<?php echo htmlspecialchars($query); ?>"</h1>

    <?php if (empty(trim($query))): ?>
        <div class="alert alert-info">Please enter a search term.</div>
    <?php else:
        if (empty($results)) {
            echo '<div class="alert alert-warning">No results found.</div>';
        } else {
            $sort_labels = [
                'relevance' => 'Sort by Relevance',
                'name_asc' => 'Sort by Name (A-Z)',
                'name_desc' => 'Sort by Name (Z-A)',
                'type_asc' => 'Sort by Type'
            ];
    ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">Showing <?php echo count($results); ?> of <?php echo $total_results; ?> results.</p>
            <form action="search_results.php" method="GET" class="d-flex align-items-center">
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                <label for="sort" class="form-label me-2 mb-0">Sort:</label>
                <select name="sort" id="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($sort_labels as $key => $label): ?><option value="<?php echo $key; ?>" <?php if ($sort === $key) echo 'selected'; ?>><?php echo $label; ?></option><?php endforeach; ?>
                </select>
            </form>
        </div>
        <ul class="list-group">
            <?php foreach ($results as $item): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-secondary me-2"><?php echo htmlspecialchars(ucfirst($item['type'])); ?></span>
                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                        <small class="text-muted ms-2">(<?php echo htmlspecialchars($item['detail']); ?>)</small>
                    </div>
                    <?php if (in_array($item['type'], ['student', 'teacher'])): ?>
                        <div>
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#quickViewModal" data-user-id="<?php echo $item['id']; ?>">Quick View</button>
                            <a href="view_profile.php?user_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary">Full Profile</a>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
        <?php
            $query_params = ['q' => $query, 'sort' => $sort];
        ?>
        <nav aria-label="Search results navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $page - 1])); ?>">Previous</a>
                </li>
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php if($page == $i) {echo 'active'; } ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php if($page >= $total_pages) { echo 'disabled'; } ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($query_params, ['page' => $page + 1])); ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    <?php
        }
    <?php endif; ?>
</div>

<!-- Quick View Modal -->
<div class="modal fade" id="quickViewModal" tabindex="-1" aria-labelledby="quickViewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="quickViewModalLabel">Profile Quick View</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="quickViewModalBody">
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
      </div>
      <div class="modal-footer" id="quickViewModalFooter">
        <!-- Buttons will be injected here by JavaScript -->
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>