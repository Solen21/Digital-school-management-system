<?php
session_start();

// 1. Check if the user is logged in and is a teacher or rep.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$teacher_id = null;
$classes_taught = [];
$students_by_class = [];
$error_message = '';

// 2. Get the teacher's internal ID from their user_id
$sql_teacher_id = "SELECT teacher_id FROM teachers WHERE user_id = ? LIMIT 1";
$stmt_teacher = mysqli_prepare($conn, $sql_teacher_id);
mysqli_stmt_bind_param($stmt_teacher, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt_teacher);
$result_teacher = mysqli_stmt_get_result($stmt_teacher);
if ($teacher_row = mysqli_fetch_assoc($result_teacher)) {
    $teacher_id = $teacher_row['teacher_id'];
} else {
    $error_message = "Could not find a teacher profile associated with your account.";
}
mysqli_stmt_close($stmt_teacher);

if ($teacher_id) {
    // 3. Get all class/subject assignments for this teacher and group them
    $sql_assignments = "SELECT c.classroom_id, c.name AS classroom_name, s.name AS subject_name
                        FROM subject_assignments sa
                        JOIN classrooms c ON sa.classroom_id = c.classroom_id
                        JOIN subjects s ON sa.subject_id = s.subject_id
                        WHERE sa.teacher_id = ? ORDER BY c.name, s.name";
    $stmt_assignments = mysqli_prepare($conn, $sql_assignments);
    mysqli_stmt_bind_param($stmt_assignments, "i", $teacher_id);
    mysqli_stmt_execute($stmt_assignments);
    $result_assignments = mysqli_stmt_get_result($stmt_assignments);
    while ($row = mysqli_fetch_assoc($result_assignments)) {
        $classes_taught[$row['classroom_id']]['name'] = $row['classroom_name'];
        $classes_taught[$row['classroom_id']]['subjects'][] = $row['subject_name'];
    }
    mysqli_stmt_close($stmt_assignments);

    // 4. Get all students for the classrooms the teacher is assigned to
    if (!empty($classes_taught)) {
        $classroom_ids = array_keys($classes_taught);
        $in_clause = implode(',', array_fill(0, count($classroom_ids), '?'));
        $types = str_repeat('i', count($classroom_ids));

        $sql_students = "SELECT ca.classroom_id, s.user_id, s.first_name, s.middle_name, s.last_name
                         FROM class_assignments ca
                         JOIN students s ON ca.student_id = s.student_id
                         WHERE ca.classroom_id IN ($in_clause) AND s.status = 'active'
                         ORDER BY s.last_name, s.first_name";
        $stmt_students = mysqli_prepare($conn, $sql_students);
        mysqli_stmt_bind_param($stmt_students, $types, ...$classroom_ids);
        mysqli_stmt_execute($stmt_students);
        $result_students = mysqli_stmt_get_result($stmt_students);
        while ($student = mysqli_fetch_assoc($result_students)) {
            $students_by_class[$student['classroom_id']][] = $student;
        }
        mysqli_stmt_close($stmt_students);
    }
}

mysqli_close($conn);

$page_title = 'My Classes';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Classes</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php elseif (empty($classes_taught)): ?>
        <div class="alert alert-info">You are not currently assigned to any classes.</div>
    <?php else: ?>
        <div class="accordion" id="classesAccordion">
            <?php foreach ($classes_taught as $class_id => $class_details): ?>
                <?php $student_count = count($students_by_class[$class_id] ?? []); ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-<?php echo $class_id; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $class_id; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $class_id; ?>">
                            <span class="fw-bold fs-5 me-3"><?php echo htmlspecialchars($class_details['name']); ?></span>
                            <span class="badge bg-primary rounded-pill"><?php echo $student_count; ?> Student<?php echo $student_count !== 1 ? 's' : ''; ?></span>
                        </button>
                    </h2>
                    <div id="collapse-<?php echo $class_id; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $class_id; ?>" data-bs-parent="#classesAccordion">
                        <div class="accordion-body">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Class Details</h5>
                                </div>
                                <div class="card-body">
                                    <h6>Subjects Taught:</h6>
                                    <ul class="list-inline">
                                        <?php foreach ($class_details['subjects'] as $subject): ?>
                                            <li class="list-inline-item"><span class="badge bg-secondary"><?php echo htmlspecialchars($subject); ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <hr>
                                    <?php $current_students = $students_by_class[$class_id] ?? []; ?>
                                    <?php if (empty($current_students)): ?>
                                        <h6>Student Roster</h6>
                                        <p class="text-muted">No students are currently assigned to this class.</p>
                                    <?php else: ?>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6>Student Roster</h6>
                                            <input type="text" class="form-control form-control-sm w-50 student-roster-search" placeholder="Search roster..." data-target-table="roster-table-<?php echo $class_id; ?>">
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover mt-2" id="roster-table-<?php echo $class_id; ?>">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Full Name</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($current_students as $student): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name'])); ?></td>
                                                        <td>
                                                            <a href="view_profile.php?user_id=<?php echo $student['user_id']; ?>" class="btn btn-sm btn-info" title="View Profile"><i class="bi bi-eye-fill"></i> View Profile</a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInputs = document.querySelectorAll('.student-roster-search');

    searchInputs.forEach(input => {
        input.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const tableId = this.dataset.targetTable;
            const table = document.getElementById(tableId);
            if (!table) return;

            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const studentNameCell = row.querySelector('td:first-child');
                if (studentNameCell) {
                    const studentName = studentNameCell.textContent.toLowerCase();
                    if (studentName.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        });
    });
});
</script>
<?php include 'footer.php'; ?>