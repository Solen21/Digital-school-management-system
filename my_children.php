<?php
session_start();

// 1. Check if the user is logged in and is a guardian.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'guardian') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

$children = [];
$guardian_user_id = $_SESSION['user_id'];

// 2. Fetch all children linked to this guardian's user account.
$sql = "
    SELECT
        s.student_id,
        s.user_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.photo_path,
        s.grade_level,
        c.name as classroom_name,
        sgm.relation
    FROM guardians g
    JOIN student_guardian_map sgm ON g.guardian_id = sgm.guardian_id
    JOIN students s ON sgm.student_id = s.student_id
    LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
    LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
    WHERE g.user_id = ? AND s.status = 'active'
    ORDER BY s.first_name
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $guardian_user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $children[] = $row;
    }
}
mysqli_stmt_close($stmt);
mysqli_close($conn);

$page_title = 'My Children';
include 'header.php';
?>
<style>
    .child-card {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    .child-photo {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid var(--medium-gray);
    }
    .child-info h5 {
        margin-bottom: 0.25rem;
    }
    .child-info p {
        margin-bottom: 0.5rem;
        color: var(--gray);
    }
    .child-actions .btn {
        margin-right: 0.5rem;
    }
</style>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Children</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if (empty($children)): ?>
        <div class="alert alert-info">
            There are no student profiles linked to your account. Please contact the school administration if you believe this is an error.
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($children as $child): ?>
                <div class="list-group-item">
                    <div class="child-card">
                        <img src="<?php echo (!empty($child['photo_path']) && file_exists($child['photo_path'])) ? htmlspecialchars($child['photo_path']) : 'assets/default-avatar.png'; ?>" alt="Profile Photo" class="child-photo">
                        <div class="flex-grow-1">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1"><?php echo htmlspecialchars(trim($child['first_name'] . ' ' . $child['middle_name'] . ' ' . $child['last_name'])); ?></h5>
                                <small class="text-muted"><?php echo htmlspecialchars($child['relation']); ?></small>
                            </div>
                            <p class="mb-1">
                                Grade <?php echo htmlspecialchars($child['grade_level']); ?> | 
                                Classroom: <?php echo htmlspecialchars($child['classroom_name'] ?? 'Not Assigned'); ?>
                            </p>
                            <div class="child-actions mt-2">
                                <a href="view_profile.php?user_id=<?php echo $child['user_id']; ?>" class="btn btn-sm btn-primary"><i class="bi bi-person-badge-fill"></i> View Profile</a>
                                <a href="view_my_grades.php?student_id=<?php echo $child['student_id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-text-fill"></i> View Grades</a>
                                <a href="view_my_discipline.php?student_id=<?php echo $child['student_id']; ?>" class="btn btn-sm btn-outline-warning"><i class="bi bi-cone-striped"></i> Discipline</a>
                                <a href="parent_teacher_portal.php?student_id=<?php echo $child['student_id']; ?>" class="btn btn-sm btn-info"><i class="bi bi-robot"></i> Performance Portal</a>
                                <a href="view_my_attendance.php?student_id=<?php echo $child['student_id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar-check-fill"></i> View Attendance</a>
                                <a href="leaderboard.php" class="btn btn-sm btn-outline-success"><i class="bi bi-trophy-fill"></i> Leaderboard</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>