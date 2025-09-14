<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$page_title = 'Manage Assignments';
include 'header.php';

$assignment_items = [
    ['href' => 'manage_class_assignments.php', 'icon' => 'bi-person-check-fill', 'title' => 'Assign Students to Classrooms', 'desc' => 'Manually assign students to their respective classrooms.', 'lottie' => 'assets/animations/user-group.json'],
    ['href' => 'move_student.php', 'icon' => 'bi-arrows-move', 'title' => 'Move Student', 'desc' => 'Transfer a student from one classroom to another.', 'lottie' => 'assets/animations/move-user.json'],
    ['href' => 'manage_subject_assignments.php', 'icon' => 'bi-book-half', 'title' => 'Assign Subjects to Teachers', 'desc' => 'Link teachers to the subjects they will teach in each class.', 'lottie' => 'assets/animations/assignments.json'],
    ['href' => 'manage_student_distribution.php', 'icon' => 'bi-shuffle', 'title' => 'Auto-Distribute Students', 'desc' => 'Automatically assign students to sections based on performance.', 'lottie' => 'assets/animations/user-group.json'],
    ['href' => 'manage_shift_rotation.php', 'icon' => 'bi-arrow-repeat', 'title' => 'Manage Weekly Shifts', 'desc' => 'Set up and manage the morning/afternoon shift rotations.', 'lottie' => 'assets/animations/schedule.json'],
    ['href' => 'manage_timetable.php', 'icon' => 'bi-table', 'title' => 'Manage Class Timetable', 'desc' => 'Create and edit the weekly schedule for each classroom.', 'lottie' => 'assets/animations/schedule.json'],
    ['href' => 'manage_exams.php', 'icon' => 'bi-pencil-square', 'title' => 'Manage Exams & Rooms', 'desc' => 'Define exams and the rooms where they will be held.', 'lottie' => 'assets/animations/grades.json'],
    ['href' => 'manage_exam_seating.php', 'icon' => 'bi-grid-3x3-gap-fill', 'title' => 'Generate Exam Seating', 'desc' => 'Automatically generate seating arrangements for exams.', 'lottie' => 'assets/animations/classroom.json']
];
?>
<style>
    .dashboard-card {
        background-color: var(--white);
        border: 1px solid var(--medium-gray);
        transition: all 0.3s ease-in-out;
    }
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
    }
    .dashboard-card .card-body {
        min-height: 180px;
    }
    .dashboard-card .card-title {
        font-weight: 600;
        color: var(--dark-gray);
    }
    .dashboard-card .card-text {
        color: var(--gray);
        font-size: 0.9rem;
    }
    .dashboard-card .dashboard-lottie {
        width: 70px;
        height: 70px;
        margin: 0 auto;
    }
    .dashboard-card a.stretched-link::after {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 1;
        pointer-events: auto;
        background-color: rgba(0,0,0,0);
    }
</style>

<div class="container">
    <h1>Manage Assignments</h1>
    <p class="lead">From here you can manage all student, teacher, and subject assignments.</p>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4 mt-3">
        <?php foreach ($assignment_items as $index => $item): ?>
            <div class="col">
                <div class="card h-100 text-center shadow-sm dashboard-card">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center">
                        <i class="<?php echo htmlspecialchars($item['icon']); ?> mb-2" style="font-size: 3rem; color: var(--primary-color);"></i>
                        <h5 class="card-title mt-2"><?php echo htmlspecialchars($item['title']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($item['desc']); ?></p>
                        <a href="<?php echo htmlspecialchars($item['href']); ?>" class="stretched-link"></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include 'footer.php'; ?>