<?php
session_start();

// 1. Check if the user is logged in and is an admin or director.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$page_title = 'Student Performance Predictions';

// --- Fetch data for filters ---
$subjects = [];
$sql_subjects = "SELECT subject_id, name, grade_level FROM subjects ORDER BY grade_level, name";
if ($result_subjects = mysqli_query($conn, $sql_subjects)) {
    while ($row = mysqli_fetch_assoc($result_subjects)) {
        $subjects[$row['grade_level']][] = $row;
    }
}

// --- Handle Filtering ---
$filter_grade = $_GET['grade_level'] ?? '';
$filter_subject = $_GET['subject_id'] ?? '';
$filter_risk = $_GET['risk_level'] ?? '';

$sql = "
    SELECT 
        pp.prediction_id,
        pp.predicted_grade,
        pp.risk_level,
        pp.risk_factors,
        pp.prediction_date,
        s.first_name,
        s.last_name,
        s.grade_level,
        sub.name AS subject_name
    FROM performance_predictions pp
    JOIN students s ON pp.student_id = s.student_id
    JOIN subjects sub ON pp.subject_id = sub.subject_id
    WHERE 1=1
";

$params = [];
$types = '';

if (!empty($filter_grade)) {
    $sql .= " AND s.grade_level = ?";
    $params[] = $filter_grade;
    $types .= 'i';
}
if (!empty($filter_subject)) {
    $sql .= " AND pp.subject_id = ?";
    $params[] = $filter_subject;
    $types .= 'i';
}
if (!empty($filter_risk)) {
    $sql .= " AND pp.risk_level = ?";
    $params[] = $filter_risk;
    $types .= 's';
}

// To get only the latest prediction for each student-subject pair
$sql .= " AND pp.prediction_date = (
            SELECT MAX(p2.prediction_date) 
            FROM performance_predictions p2 
            WHERE p2.student_id = pp.student_id AND p2.subject_id = pp.subject_id
          )";

$sql .= " ORDER BY s.grade_level, s.last_name, s.first_name, sub.name";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result_predictions = mysqli_stmt_get_result($stmt);
$predictions = mysqli_fetch_all($result_predictions, MYSQLI_ASSOC);

mysqli_stmt_close($stmt);
mysqli_close($conn);

include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-graph-up-arrow"></i> Student Performance Predictions</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="alert alert-info">
        <h5 class="alert-heading"><i class="bi bi-info-circle-fill"></i> About This Page</h5>
        <p>This page displays AI-driven predictions of student performance. Use the filters to narrow down the results. The "Risk Level" indicates the likelihood of a student underperforming based on historical data and other factors.</p>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="bi bi-filter"></i> Filter Predictions</h5>
        </div>
        <div class="card-body">
            <form action="view_performance_predictions.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label for="grade_level" class="form-label">Grade Level</label>
                    <select name="grade_level" id="grade_level" class="form-select">
                        <option value="">All Grades</option>
                        <option value="9" <?php echo ($filter_grade == '9') ? 'selected' : ''; ?>>Grade 9</option>
                        <option value="10" <?php echo ($filter_grade == '10') ? 'selected' : ''; ?>>Grade 10</option>
                        <option value="11" <?php echo ($filter_grade == '11') ? 'selected' : ''; ?>>Grade 11</option>
                        <option value="12" <?php echo ($filter_grade == '12') ? 'selected' : ''; ?>>Grade 12</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="subject_id" class="form-label">Subject</label>
                    <select name="subject_id" id="subject_id" class="form-select">
                        <option value="">All Subjects</option>
                        <?php
                        $subjects_to_display = !empty($filter_grade) ? ($subjects[$filter_grade] ?? []) : array_merge(...array_values($subjects));
                        foreach ($subjects_to_display as $subject) {
                            $selected = ($filter_subject == $subject['subject_id']) ? 'selected' : '';
                            echo "<option value='{$subject['subject_id']}' {$selected}>" . htmlspecialchars($subject['name']) . " (G{$subject['grade_level']})</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="risk_level" class="form-label">Risk Level</label>
                    <select name="risk_level" id="risk_level" class="form-select">
                        <option value="">All Levels</option>
                        <option value="Low" <?php echo ($filter_risk == 'Low') ? 'selected' : ''; ?>>Low</option>
                        <option value="Medium" <?php echo ($filter_risk == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                        <option value="High" <?php echo ($filter_risk == 'High') ? 'selected' : ''; ?>>High</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2 pt-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel-fill"></i> Filter</button>
                    <a href="export_predictions.php?<?php echo http_build_query(['grade_level' => $filter_grade, 'subject_id' => $filter_subject, 'risk_level' => $filter_risk]); ?>" class="btn btn-success w-100" title="Export current view to CSV">
                        <i class="bi bi-file-earmark-excel-fill"></i> Export
                    </a>
                    <a href="view_performance_predictions.php" class="btn btn-secondary w-100" title="Clear all filters">
                        <i class="bi bi-x-lg"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Predictions Table -->
    <div class="card">
        <div class="card-header">
            <h5><i class="bi bi-table"></i> Prediction Results (<?php echo count($predictions); ?>)</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Student</th>
                            <th>Grade</th>
                            <th>Subject</th>
                            <th>Predicted Grade</th>
                            <th>Risk Level</th>
                            <th>Risk Factors</th>
                            <th>Prediction Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($predictions)): ?>
                            <tr><td colspan="7" class="text-center">No predictions found matching your criteria.</td></tr>
                        <?php else: ?>
                            <?php foreach ($predictions as $pred): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pred['last_name'] . ', ' . $pred['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pred['grade_level']); ?></td>
                                    <td><?php echo htmlspecialchars($pred['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($pred['predicted_grade']); ?></td>
                                    <td>
                                        <?php
                                        $risk_class = '';
                                        switch ($pred['risk_level']) {
                                            case 'High': $risk_class = 'bg-danger'; break;
                                            case 'Medium': $risk_class = 'bg-warning text-dark'; break;
                                            case 'Low': $risk_class = 'bg-success'; break;
                                        }
                                        echo "<span class='badge {$risk_class}'>" . htmlspecialchars($pred['risk_level']) . "</span>";
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($pred['risk_factors']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($pred['prediction_date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
document.getElementById('grade_level').addEventListener('change', function() {
    // This is a simple client-side filter for the subject dropdown.
    // For a full solution, you might need AJAX to fetch subjects if the list is very large.
    this.form.submit();
});
</script>