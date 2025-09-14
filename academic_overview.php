<?php
session_start();

// 1. Check if the user is logged in and is a director or admin.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['director', 'admin'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

// --- Fetch At-a-Glance Statistics ---
$stats = [
    'total_students' => 0,
    'total_teachers' => 0,
    'total_classrooms' => 0,
    'total_subjects' => 0,
];
$sql_stats = "SELECT
    (SELECT COUNT(*) FROM students WHERE status = 'active') as total_students,
    (SELECT COUNT(*) FROM teachers WHERE status = 'active') as total_teachers,
    (SELECT COUNT(*) FROM classrooms) as total_classrooms,
    (SELECT COUNT(*) FROM subjects) as total_subjects";
$stats_result = mysqli_query($conn, $sql_stats);
if ($stats_result) $stats = mysqli_fetch_assoc($stats_result);

// Define stat cards with Lottie animations
$stat_cards = [
    ['label' => 'Active Students', 'value' => $stats['total_students'], 'lottie' => 'assets/animations/user-group.json'],
    ['label' => 'Active Teachers', 'value' => $stats['total_teachers'], 'lottie' => 'assets/animations/user-group.json'],
    ['label' => 'Classrooms', 'value' => $stats['total_classrooms'], 'lottie' => 'assets/animations/classroom.json'],
    ['label' => 'Subjects', 'value' => $stats['total_subjects'], 'lottie' => 'assets/animations/books.json']
];

// --- Fetch Teacher Assignments ---
$teacher_assignments = [];
$sql_teachers = "
    SELECT 
        t.teacher_id, t.first_name, t.last_name,
        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') as subjects,
        GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') as classrooms
    FROM teachers t
    LEFT JOIN subject_assignments sa ON t.teacher_id = sa.teacher_id
    LEFT JOIN subjects s ON sa.subject_id = s.subject_id
    LEFT JOIN classrooms c ON sa.classroom_id = c.classroom_id
    WHERE t.status = 'active'
    GROUP BY t.teacher_id
    ORDER BY t.last_name, t.first_name;
";
$result_teachers = mysqli_query($conn, $sql_teachers);
if ($result_teachers) {
    while ($row = mysqli_fetch_assoc($result_teachers)) {
        $teacher_assignments[] = $row;
    }
}

// --- Fetch Classroom Assignments ---
$classroom_assignments = [];
$sql_classrooms = "
    SELECT 
        c.classroom_id, c.name, c.grade_level,
        COUNT(DISTINCT ca.student_id) as student_count,
        COUNT(DISTINCT sa.teacher_id) as teacher_count
    FROM classrooms c
    LEFT JOIN class_assignments ca ON c.classroom_id = ca.classroom_id
    LEFT JOIN subject_assignments sa ON c.classroom_id = sa.classroom_id
    GROUP BY c.classroom_id
    ORDER BY c.grade_level, c.name;
";
$result_classrooms = mysqli_query($conn, $sql_classrooms);
if ($result_classrooms) {
    while ($row = mysqli_fetch_assoc($result_classrooms)) {
        $classroom_assignments[] = $row;
    }
}

mysqli_close($conn);

$page_title = 'Academic Overview';
include 'header.php';
?>
<style>
    .stat-card { background-color: var(--white); border-left: 5px solid var(--primary-color); }
    .stat-card .stat-number { font-size: 2.5rem; font-weight: 700; color: var(--dark-gray); }
    .stat-card .stat-label { font-size: 1rem; color: var(--gray); }
    .stat-card .stat-lottie { width: 60px; height: 60px; opacity: 0.7; }
</style>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Academic Overview</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <!-- At-a-glance Stats -->
    <div class="row g-4 mb-4">
        <?php foreach ($stat_cards as $index => $card): ?>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm stat-card">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div class="text-start">
                        <div class="stat-number"><?php echo $card['value']; ?></div>
                        <div class="stat-label"><?php echo $card['label']; ?></div>
                    </div>
                    <div class="stat-lottie" id="lottie-stat-<?php echo $index; ?>"></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <!-- Teacher Supervision -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-person-video3 me-2"></i>Teacher Supervision</h5></div>
                <div class="card-body p-0"><div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Teacher</th><th>Assigned Subjects</th><th>Assigned Classrooms</th></tr></thead>
                        <tbody>
                            <?php foreach ($teacher_assignments as $teacher): ?>
                            <tr>
                                <td><a href="view_profile.php?user_id=<?php echo $teacher['teacher_id']; ?>"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></a></td>
                                <td class="text-muted" style="font-size: 0.9em;"><?php echo htmlspecialchars($teacher['subjects'] ?? 'None'); ?></td>
                                <td class="text-muted" style="font-size: 0.9em;"><?php echo htmlspecialchars($teacher['classrooms'] ?? 'None'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>

        <!-- Classroom Overview -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="bi bi-door-open-fill me-2"></i>Classroom Overview</h5></div>
                <div class="card-body p-0"><div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light"><tr><th>Classroom</th><th>Grade</th><th>Students</th><th>Teachers</th></tr></thead>
                        <tbody>
                            <?php foreach ($classroom_assignments as $classroom): ?>
                            <tr>
                                <td><a href="manage_assignments.php?classroom_id=<?php echo $classroom['classroom_id']; ?>"><?php echo htmlspecialchars($classroom['name']); ?></a></td>
                                <td><?php echo htmlspecialchars($classroom['grade_level']); ?></td>
                                <td><?php echo $classroom['student_count']; ?></td>
                                <td><?php echo $classroom['teacher_count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>
    </div>
</div>

<script>
    const statCards = <?php echo json_encode($stat_cards); ?>;
    statCards.forEach((card, index) => {
        if (card.lottie) {
            bodymovin.loadAnimation({
                container: document.getElementById(`lottie-stat-${index}`),
                renderer: 'svg',
                loop: true,
                autoplay: true,
                path: card.lottie
            });
        }
    });
</script>
<?php include 'footer.php'; ?>