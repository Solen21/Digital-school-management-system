<?php
/**
 * Reusable pagination control.
 *
 * This component generates pagination links. It assumes the following variables are set on the including page:
 * @var int $total_pages  The total number of pages.
 * @var int $current_page The current active page.
 *
 * It automatically preserves existing GET query parameters.
 */

if (isset($total_pages) && $total_pages > 1):
    $query_params = $_GET;
    unset($query_params['page']); // Remove page from query params to avoid duplication
    $query_string = http_build_query($query_params);
    if (!empty($query_string)) {
        $query_string = '&' . $query_string;
    }
?>
<nav aria-label="Page navigation" class="mt-4">
    <ul class="pagination justify-content-center">
        <?php
        // Previous button
        $prev_page = $current_page - 1;
        echo '<li class="page-item ' . ($current_page <= 1 ? 'disabled' : '') . '">';
        echo '<a class="page-link" href="?page=' . $prev_page . $query_string . '">Previous</a>';
        echo '</li>';

        // Page number links
        for ($i = 1; $i <= $total_pages; $i++) {
            echo '<li class="page-item ' . ($current_page == $i ? 'active' : '') . '">';
            echo '<a class="page-link" href="?page=' . $i . $query_string . '">' . $i . '</a>';
            echo '</li>';
        }

        // Next button
        $next_page = $current_page + 1;
        echo '<li class="page-item ' . ($current_page >= $total_pages ? 'disabled' : '') . '">';
        echo '<a class="page-link" href="?page=' . $next_page . $query_string . '">Next</a>';
        echo '</li>';
        ?>
    </ul>
</nav>
<?php endif; ?>