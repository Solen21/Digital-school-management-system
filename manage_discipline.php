<?php
session_start();

// 1. Security Check: User must be an admin or director.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

$message = '';
$message_type = '';

// --- POST Request Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'add_record' || $action == 'update_record') {
        $student_id = $_POST['student_id'];
        $incident_date = $_POST['incident_date'];
        $incident_type = $_POST['incident_type'];
        $description = $_POST['description'];
        $action_taken = $_POST['action_taken'];
        $reported_by_user_id = $_SESSION['user_id'];

        if (empty($student_id) || empty($incident_date) || empty($incident_type) || empty($description)) {
            $message = "Student, date, type, and description are required.";
            $message_type = 'danger';
        } else {
            if ($action == 'add_record') {
                $sql = "INSERT INTO discipline_records (student_id, reported_by_user_id, incident_date, incident_type, description, action_taken) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "iissss", $student_id, $reported_by_user_id, $incident_date, $incident_type, $description, $action_taken);
            } else { // update_record
                $record_id = $_POST['record_id'];
                $sql = "UPDATE discipline_records SET student_id = ?, incident_date = ?, incident_type = ?, description = ?, action_taken = ? WHERE record_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "issssi", $student_id, $incident_date, $incident_type, $description, $action_taken, $record_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $message = "Discipline record " . ($action == 'add_record' ? 'added' : 'updated') . " successfully.";
                $message_type = 'success';
                log_activity($conn, $action, mysqli_insert_id($conn) ?: $record_id, "Discipline record for student ID {$student_id}");
            } else {
                $message = "Error: " . mysqli_stmt_error($stmt);
                $message_type = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action == 'delete_record') {
        $record_id = $_POST['record_id'];
        $sql = "DELETE FROM discipline_records WHERE record_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $record_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Record deleted successfully.";
            $message_type = 'success';
            log_activity($conn, 'delete_record', $record_id, "Discipline record ID {$record_id}");
        } else {
            $message = "Error deleting record.";
            $message_type = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
}

// --- Fetch data for display ---
$students = mysqli_query($conn, "SELECT student_id, first_name, last_name FROM students ORDER BY last_name, first_name");
$incident_types = ['Tardiness', 'Uniform Violation', 'Disruption', 'Academic Misconduct', 'Property Damage', 'Other'];

$edit_record = null;
if (isset($_GET['edit_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM discipline_records WHERE record_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['edit_id']);
    mysqli_stmt_execute($stmt);
    $edit_record = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// --- Filtering Logic ---
$search_student = $_GET['search_student'] ?? '';
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';

// --- Pagination Logic ---
$records_per_page = 20; // Number of records per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// --- Count total records for pagination ---
$sql_count = "
    SELECT COUNT(dr.record_id) as total
    FROM discipline_records dr
    JOIN students s ON dr.student_id = s.student_id
    JOIN users u ON dr.reported_by_user_id = u.user_id
    LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
    LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
";

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search_student)) {
    $where_clauses[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?)";
    $search_term = "%" . $search_student . "%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $param_types .= 'sss';
}
if (!empty($filter_start_date)) {
    $where_clauses[] = "dr.incident_date >= ?";
    $params[] = $filter_start_date;
    $param_types .= 's';
}
if (!empty($filter_end_date)) {
    $where_clauses[] = "dr.incident_date <= ?";
    $params[] = $filter_end_date;
    $param_types .= 's';
}

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

$sql_records = "
    SELECT dr.*, s.first_name, s.last_name, u.username as reporter_username, c.name as classroom_name
    FROM discipline_records dr
    JOIN students s ON dr.student_id = s.student_id
    JOIN users u ON dr.reported_by_user_id = u.user_id
    LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
    LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
";

if (!empty($where_clauses)) {
    $sql_records .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql_records .= " ORDER BY dr.incident_date DESC LIMIT ? OFFSET ?";

$params[] = $records_per_page;
$params[] = $offset;
$param_types .= 'ii';

$stmt_records = mysqli_prepare($conn, $sql_records);
if (!empty($params)) { mysqli_stmt_bind_param($stmt_records, $param_types, ...$params); }
mysqli_stmt_execute($stmt_records);
$all_records = mysqli_stmt_get_result($stmt_records);

mysqli_close($conn);
$page_title = 'Manage Discipline';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Manage Discipline Records</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Add/Edit Form -->
        <div class="col-lg-4">
            <div class="card" id="record-form-card">
                <div class="card-header"><h5><?php echo $edit_record ? 'Edit' : 'Add New'; ?> Record</h5></div>
                <div class="card-body">
                    <form action="manage_discipline.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_record ? 'update_record' : 'add_record'; ?>">
                        <?php if ($edit_record): ?><input type="hidden" name="record_id" value="<?php echo $edit_record['record_id']; ?>"><?php endif; ?>
                        
                        <div class="mb-3"><label for="student_id" class="form-label">Student</label><select class="form-select" id="student_id" name="student_id" required><?php mysqli_data_seek($students, 0); while($s = mysqli_fetch_assoc($students)): ?><option value="<?php echo $s['student_id']; ?>" <?php if(($edit_record['student_id'] ?? '') == $s['student_id']) echo 'selected'; ?>><?php echo htmlspecialchars($s['last_name'].', '.$s['first_name']); ?></option><?php endwhile; ?></select></div>
                        <div class="mb-3"><label for="incident_date" class="form-label">Incident Date</label><input type="date" class="form-control" id="incident_date" name="incident_date" value="<?php echo htmlspecialchars($edit_record['incident_date'] ?? date('Y-m-d')); ?>" required></div>
                        <div class="mb-3"><label for="incident_type" class="form-label">Incident Type</label><select class="form-select" id="incident_type" name="incident_type" required><?php foreach($incident_types as $type): ?><option value="<?php echo $type; ?>" <?php if(($edit_record['incident_type'] ?? '') == $type) echo 'selected'; ?>><?php echo $type; ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($edit_record['description'] ?? ''); ?></textarea></div>
                        <div class="mb-3"><label for="action_taken" class="form-label">Action Taken</label><input type="text" class="form-control" id="action_taken" name="action_taken" value="<?php echo htmlspecialchars($edit_record['action_taken'] ?? ''); ?>"></div>
                        
                        <button type="submit" class="btn btn-primary"><?php echo $edit_record ? 'Update' : 'Add'; ?> Record</button>
                        <?php if ($edit_record): ?><a href="manage_discipline.php" class="btn btn-secondary ms-2">Cancel</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Records Table -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Records (<?php echo $total_records; ?>)</h5>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false">
                            <i class="bi bi-funnel-fill"></i> Filter
                        </button>
                    </div>
                    <div class="collapse" id="filterCollapse">
                        <form action="manage_discipline.php" method="GET" class="row g-2 align-items-end pt-3">
                            <div class="col-md-5"><label for="search_student" class="form-label">Search by Student</label><input type="text" id="search_student" name="search_student" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search_student); ?>"></div>
                            <div class="col-md-3"><label for="start_date" class="form-label">From</label><input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_start_date); ?>"></div>
                            <div class="col-md-3"><label for="end_date" class="form-label">To</label><input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_end_date); ?>"></div>
                            <div class="col-md-1 d-flex gap-2"><button type="submit" class="btn btn-primary btn-sm w-100">Go</button></div>
                            <div class="col-md-1 d-flex gap-2"><a href="manage_discipline.php" class="btn btn-secondary btn-sm w-100">Clear</a></div>
                        </form>
                    </div>
                </div>
                <div class="card-body"><div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light"><tr><th>Student</th><th>Section</th><th>Date</th><th>Type</th><th>Reported By</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (mysqli_num_rows($all_records) === 0): ?>
                                <tr><td colspan="6" class="text-center">No discipline records found matching your criteria.</td></tr>
                            <?php else: ?>
                                <?php while($record = mysqli_fetch_assoc($all_records)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['last_name'] . ', ' . $record['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($record['classroom_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($record['incident_date']); ?></td>
                                    <td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($record['incident_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($record['reporter_username']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info view-details-btn" data-bs-toggle="modal" data-bs-target="#detailsModal" data-student-name="<?php echo htmlspecialchars($record['last_name'] . ', ' . $record['first_name']); ?>" data-incident-date="<?php echo htmlspecialchars($record['incident_date']); ?>" data-incident-type="<?php echo htmlspecialchars($record['incident_type']); ?>" data-description="<?php echo htmlspecialchars($record['description']); ?>" data-action-taken="<?php echo htmlspecialchars($record['action_taken']); ?>" title="View Details"><i class="bi bi-eye-fill"></i></button>
                                        <a href="?edit_id=<?php echo $record['record_id']; ?>#record-form-card" class="btn btn-sm btn-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                        <form action="manage_discipline.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this record?');">
                                            <input type="hidden" name="action" value="delete_record"><input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>"><button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php include 'pagination_controls.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailsModalLabel">Discipline Record Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="printable-modal-content">
        <dl class="row">
          <dt class="col-sm-3">Student:</dt>
          <dd class="col-sm-9" id="modalStudentName"></dd>

          <dt class="col-sm-3">Reported By:</dt>
          <dd class="col-sm-9" id="modalReportedBy"></dd>

          <dt class="col-sm-3">Incident Date:</dt>
          <dd class="col-sm-9" id="modalIncidentDate"></dd>

          <dt class="col-sm-3">Incident Type:</dt>
          <dd class="col-sm-9" id="modalIncidentType"></dd>

          <dt class="col-sm-3">Description:</dt>
          <dd class="col-sm-9"><p id="modalDescription" style="white-space: pre-wrap;"></p></dd>

          <dt class="col-sm-3">Action Taken:</dt>
          <dd class="col-sm-9" id="modalActionTaken"></dd>
        </dl>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" id="printModalBtn"><i class="bi bi-printer-fill"></i> Print</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
<style>
@media print {
    body * {
        visibility: hidden;
    }
    #printable-modal-content, #printable-modal-content * {
        visibility: visible;
    }
    #printable-modal-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
    .modal-dialog {
        max-width: 100% !important;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var detailsModal = document.getElementById('detailsModal');
    detailsModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('modalStudentName').textContent = button.getAttribute('data-student-name');
        document.getElementById('modalReportedBy').textContent = button.closest('tr').querySelector('td:nth-child(5)').textContent;
        document.getElementById('modalIncidentDate').textContent = button.getAttribute('data-incident-date');
        document.getElementById('modalIncidentType').textContent = button.getAttribute('data-incident-type');
        document.getElementById('modalDescription').textContent = button.getAttribute('data-description');
        document.getElementById('modalActionTaken').textContent = button.getAttribute('data-action-taken') || 'N/A';
    });

    document.getElementById('printModalBtn').addEventListener('click', function() {
        window.print();
    });
});
</script>