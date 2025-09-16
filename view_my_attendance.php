<?php
session_start();

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$student_id = $_GET['student_id'] ?? null;
$viewer_user_id = $_SESSION['user_id'];
$viewer_role = $_SESSION['role'];

// If a student/rep is viewing, get their student_id automatically
if (is_null($student_id) && in_array($viewer_role, ['student', 'rep'])) {
    $sql_get_id = "SELECT student_id FROM students WHERE user_id = ? LIMIT 1";
    $stmt_get_id = mysqli_prepare($conn, $sql_get_id);
    mysqli_stmt_bind_param($stmt_get_id, "i", $viewer_user_id);
    mysqli_stmt_execute($stmt_get_id);
    $result_get_id = mysqli_stmt_get_result($stmt_get_id);
    if ($row = mysqli_fetch_assoc($result_get_id)) {
        $student_id = $row['student_id'];
    }
    mysqli_stmt_close($stmt_get_id);
}

if (empty($student_id) || !is_numeric($student_id)) {
    die("<h1>Error</h1><p>No student specified or invalid ID.</p>");
}

// 2. Authorization Check
$is_authorized = false;
if (in_array($viewer_role, ['student', 'rep'])) {
    $sql_verify = "SELECT student_id FROM students WHERE user_id = ? AND student_id = ? LIMIT 1";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $viewer_user_id, $student_id);
    mysqli_stmt_execute($stmt_verify);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify))) $is_authorized = true;
    mysqli_stmt_close($stmt_verify);
} elseif ($viewer_role === 'guardian') {
    $sql_verify = "SELECT COUNT(*) as count FROM guardians g JOIN student_guardian_map sgm ON g.guardian_id = sgm.guardian_id WHERE g.user_id = ? AND sgm.student_id = ?";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $viewer_user_id, $student_id);
    mysqli_stmt_execute($stmt_verify);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify))['count'] > 0) $is_authorized = true;
    mysqli_stmt_close($stmt_verify);
}

if (!$is_authorized) {
    die("<h1>Access Denied</h1><p>You are not authorized to view this student's attendance.</p>");
}

// 3. Fetch Student and Attendance Data
$student_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name, last_name FROM students WHERE student_id = $student_id"));
if (!$student_info) die("<h1>Error</h1><p>Student profile not found.</p>");

// Fetch stats
$stats_result = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM attendance WHERE student_id = $student_id GROUP BY status");
$stats = ['Present' => 0, 'Absent' => 0, 'Late' => 0];
while ($row = mysqli_fetch_assoc($stats_result)) {
    $stats[$row['status']] = $row['count'];
}

// Fetch filter options
$filter_subjects = mysqli_fetch_all(mysqli_query($conn, "SELECT DISTINCT s.subject_id, s.name FROM attendance a JOIN subjects s ON a.subject_id = s.subject_id WHERE a.student_id = $student_id ORDER BY s.name"), MYSQLI_ASSOC);

// Fetch detailed attendance records with filtering
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_subject_id = $_GET['subject_id'] ?? '';

$sql_attendance = "SELECT a.attendance_id, a.attendance_date, a.status, s.name as subject_name, CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                   ae.status as excuse_status
                   FROM attendance a
                   JOIN subjects s ON a.subject_id = s.subject_id 
                   LEFT JOIN teachers t ON a.teacher_id = t.teacher_id
                   LEFT JOIN absence_excuses ae ON a.attendance_id = ae.attendance_id
                   WHERE a.student_id = ?";
$params = [$student_id];
$types = 'i';

if ($filter_start_date) { $sql_attendance .= " AND a.attendance_date >= ?"; $params[] = $filter_start_date; $types .= 's'; }
if ($filter_end_date) { $sql_attendance .= " AND a.attendance_date <= ?"; $params[] = $filter_end_date; $types .= 's'; }
if ($filter_subject_id) { $sql_attendance .= " AND a.subject_id = ?"; $params[] = $filter_subject_id; $types .= 'i'; }

$sql_attendance .= " ORDER BY a.attendance_date DESC, s.name ASC";

$stmt_attendance = mysqli_prepare($conn, $sql_attendance);
mysqli_stmt_bind_param($stmt_attendance, $types, ...$params);
mysqli_stmt_execute($stmt_attendance);
$attendance_records = mysqli_fetch_all(mysqli_stmt_get_result($stmt_attendance), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_attendance);

mysqli_close($conn);

$back_link = ($viewer_role === 'guardian') ? 'my_children.php' : 'dashboard.php';
$page_title = 'View Attendance';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Attendance Report</h1>
            <p class="lead text-muted">For <?php echo htmlspecialchars($student_info['first_name'] . ' ' . $student_info['last_name']); ?></p>
        </div>
        <a href="<?php echo $back_link; ?>" class="btn btn-secondary">Back</a>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4"><div class="card text-white bg-success"><div class="card-body text-center"><h3><?php echo $stats['Present']; ?></h3><p class="mb-0">Days Present</p></div></div></div>
        <div class="col-md-4"><div class="card text-white bg-danger"><div class="card-body text-center"><h3><?php echo $stats['Absent']; ?></h3><p class="mb-0">Days Absent</p></div></div></div>
        <div class="col-md-4"><div class="card text-dark bg-warning"><div class="card-body text-center"><h3><?php echo $stats['Late']; ?></h3><p class="mb-0">Days Late</p></div></div></div>
    </div>

    <!-- Filter Form -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <form action="view_my_attendance.php" method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">
                <div class="col-md-3"><label for="start_date" class="form-label">Start Date</label><input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"></div>
                <div class="col-md-3"><label for="end_date" class="form-label">End Date</label><input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"></div>
                <div class="col-md-3"><label for="subject_id" class="form-label">Subject</label><select class="form-select" id="subject_id" name="subject_id"><option value="">All Subjects</option><?php foreach ($filter_subjects as $subject) { $sel = ($subject['subject_id'] == $filter_subject_id) ? 'selected' : ''; echo "<option value='{$subject['subject_id']}' $sel>" . htmlspecialchars($subject['name']) . "</option>"; } ?></select></div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
            </form>
        </div>
    </div>

    <!-- Detailed Attendance Table -->
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Attendance Log</h5></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Date</th><th>Subject</th><th>Status</th><th>Recorded By</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (empty($attendance_records)): ?>
                        <tr><td colspan="5" class="text-center text-muted">No attendance records found for the selected criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                            <td><?php echo htmlspecialchars($record['subject_name']); ?></td>
                            <td>
                                <?php
                                    $status_class = '';
                                    if ($record['status'] == 'Present') $status_class = 'bg-success';
                                    elseif ($record['status'] == 'Absent') $status_class = 'bg-danger';
                                    elseif ($record['status'] == 'Late') $status_class = 'bg-warning text-dark';
                                    elseif ($record['status'] == 'Excused') $status_class = 'bg-info text-dark';
                                ?>
                                <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($record['status']); ?></span>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars($record['teacher_name'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($record['status'] == 'Absent' && is_null($record['excuse_status'])): ?>
                                    <a href="submit_absence_excuse.php?attendance_id=<?php echo $record['attendance_id']; ?>" class="btn btn-sm btn-outline-primary">Submit Excuse</a>
                                <?php elseif (!is_null($record['excuse_status'])): ?>
                                    <span class="badge bg-secondary">Excuse <?php echo htmlspecialchars($record['excuse_status']); ?></span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>