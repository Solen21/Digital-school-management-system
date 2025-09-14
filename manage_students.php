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

// --- Search and Filter Logic ---
$search_name = $_GET['search_name'] ?? '';
$filter_grade = $_GET['filter_grade'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search_name)) {
    $where_clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)";
    $search_term = "%" . $search_name . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= 'sss';
}
if (!empty($filter_grade)) {
    $where_clauses[] = "s.grade_level = ?";
    $params[] = $filter_grade;
    $param_types .= 's';
}
if (!empty($filter_status)) {
    $where_clauses[] = "s.status = ?";
    $params[] = $filter_status;
    $param_types .= 's';
}

// --- Pagination Logic ---
$records_per_page = 15; // Number of students per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// --- Count total records for pagination ---
$sql_count = "SELECT COUNT(s.student_id) as total FROM students s";
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

// --- Fetch students for the current page ---
$students = [];
$sql = "SELECT s.student_id, s.user_id, s.first_name, s.middle_name, s.last_name, s.email, s.phone, s.status, s.grade_level, c.name as classroom_name 
        FROM students s 
        LEFT JOIN (class_assignments ca JOIN classrooms c ON ca.classroom_id = c.classroom_id) 
        ON s.student_id = ca.student_id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY s.grade_level, c.name, s.last_name, s.first_name LIMIT ? OFFSET ?";

// Add LIMIT and OFFSET to parameters
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
        $students[] = $row;
    }
}
mysqli_stmt_close($stmt);

// --- Fetch data for the graph ---
$graph_data = [];
$sql_graph = "SELECT grade_level, COUNT(student_id) as student_count FROM students GROUP BY grade_level ORDER BY grade_level ASC";
$result_graph = mysqli_query($conn, $sql_graph);
if ($result_graph) {
    while ($row = mysqli_fetch_assoc($result_graph)) {
        $graph_data['labels'][] = 'Grade ' . $row['grade_level'];
        $graph_data['counts'][] = $row['student_count'];
    }
}

mysqli_close($conn);

$page_title = 'Manage Students';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Manage Students</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if (isset($_SESSION['import_message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['import_message_type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['import_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['import_message'], $_SESSION['import_message_type']); ?>
    <?php endif; ?>

    <!-- Action Buttons and Import Section -->
    <div class="card mb-4">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <a href="add_student.php" class="btn btn-success"><i class="bi bi-person-plus-fill me-2"></i>Add New Student</a>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#importCollapse" aria-expanded="false" aria-controls="importCollapse"><i class="bi bi-upload me-2"></i>Import/Export</button>
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse"><i class="bi bi-funnel-fill me-2"></i>Filter & Search</button>
            </div>
        </div>
        <div class="collapse" id="importCollapse">
            <div class="card-footer">
                <div class="row align-items-center">
                    <div class="col-md-6 border-end">
                         <h5>Import Students from Excel</h5>
                         <p class="mb-md-0"><small>Upload an Excel (.xlsx) file with student data. <a href="export_students.php?template=true">Download the template here</a>.</small></p>
                         <form action="import_students.php" method="POST" enctype="multipart/form-data" class="d-flex gap-2 mt-2">
                             <input type="file" class="form-control" name="student_file" accept=".xlsx" required>
                             <button type="submit" class="btn btn-warning text-dark flex-shrink-0">Import</button>
                         </form>
                    </div>
                    <div class="col-md-6 ps-md-4">
                         <h5>Export Data</h5>
                         <p class="mb-md-0"><small>Download a complete list of all students currently in the system as an Excel file.</small></p>
                         <a href="export_students.php" class="btn btn-success mt-2"><i class="bi bi-file-earmark-excel-fill me-2"></i>Export All to Excel</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="collapse" id="filterCollapse">
            <div class="card-footer">
                <form action="manage_students.php" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4"><label for="search_name" class="form-label">Search by Name</label><input type="text" class="form-control" id="search_name" name="search_name" value="<?php echo htmlspecialchars($search_name); ?>"></div>
                    <div class="col-md-2"><label for="filter_grade" class="form-label">Grade</label><select id="filter_grade" name="filter_grade" class="form-select"><option value="">All</option><option value="9" <?php if($filter_grade == '9') echo 'selected'; ?>>9</option><option value="10" <?php if($filter_grade == '10') echo 'selected'; ?>>10</option><option value="11" <?php if($filter_grade == '11') echo 'selected'; ?>>11</option><option value="12" <?php if($filter_grade == '12') echo 'selected'; ?>>12</option></select></div>
                    <div class="col-md-2"><label for="filter_status" class="form-label">Status</label><select id="filter_status" name="filter_status" class="form-select"><option value="">All</option><option value="active" <?php if($filter_status == 'active') echo 'selected'; ?>>Active</option><option value="inactive" <?php if($filter_status == 'inactive') echo 'selected'; ?>>Inactive</option></select></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                    <div class="col-md-2"><a href="manage_students.php" class="btn btn-secondary w-100">Clear</a></div>
                </form>
            </div>
        </div>
    </div>

    <!-- Main Content: Tabs for List and Graph -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list-pane" type="button" role="tab" aria-controls="list-pane" aria-selected="true">
                        <i class="bi bi-list-ul me-2"></i>Student List (<?php echo $total_records; ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="graph-tab" data-bs-toggle="tab" data-bs-target="#graph-pane" type="button" role="tab" aria-controls="graph-pane" aria-selected="false">
                        <i class="bi bi-bar-chart-fill me-2"></i>Enrollment Overview
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="myTabContent">
                <!-- Student List Tab -->
                <div class="tab-pane fade show active" id="list-pane" role="tabpanel" aria-labelledby="list-tab" tabindex="0">
                    <?php if (empty($students)): ?>
                        <div class="alert alert-info">No students found matching your criteria. <a href="manage_students.php" class="alert-link">Clear filters</a>.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-light"><tr><th>ID</th><th>Full Name</th><th>Grade</th><th>Section</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['grade_level']); ?></td>
                                        <td><?php echo htmlspecialchars($student['classroom_name'] ?? 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                        <td><span class="badge <?php echo $student['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars(ucfirst($student['status'])); ?></span></td>
                                        <td>
                                            <a href="view_profile.php?user_id=<?php echo $student['user_id']; ?>" class="btn btn-sm btn-info" title="View Profile"><i class="bi bi-eye-fill"></i></a>
                                            <a href="send_notification.php?user_id=<?php echo $student['user_id']; ?>" class="btn btn-sm btn-secondary" title="Send Notification"><i class="bi bi-bell-fill"></i></a>
                                            <a href="edit_student.php?id=<?php echo $student['student_id']; ?>" class="btn btn-sm btn-primary" title="Edit Student"><i class="bi bi-pencil-fill"></i></a>
                                            <a href="delete_student.php?id=<?php echo $student['student_id']; ?>" class="btn btn-sm btn-danger" title="Delete Student" onclick="return confirm('Are you sure? This will permanently delete the student and all their associated data.')"><i class="bi bi-trash-fill"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php include 'pagination_controls.php'; ?>
                    <?php endif; ?>
                </div>
                <!-- Enrollment Graph Tab -->
                <div class="tab-pane fade" id="graph-pane" role="tabpanel" aria-labelledby="graph-tab" tabindex="0">
                    <div class="row">
                        <div class="col-lg-10 mx-auto">
                            <canvas id="studentEnrollmentChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('studentEnrollmentChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($graph_data['labels'] ?? []); ?>,
                datasets: [{
                    label: '# of Students',
                    data: <?php echo json_encode($graph_data['counts'] ?? []); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 10 // Adjust step size as needed
                        }
                    }
                },
                responsive: true,
                plugins: { legend: { display: false } }
            }
        });
    }
});
</script>
<?php include 'footer.php'; ?>