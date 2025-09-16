<?php
session_start();

// 1. Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 2. Get user info from session
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// 3. Define dashboard items based on user role for cleaner code
$dashboard_items = [];

switch ($role) {
    case 'admin':
        $dashboard_items = [
            ['href' => 'add_student.php', 'icon' => 'bi-person-plus-fill', 'title' => 'Register Student', 'desc' => 'Add a new student profile.', 'lottie' => 'assets/animations/add-user.json'],
            ['href' => 'add_teacher.php', 'icon' => 'bi-person-video3', 'title' => 'Register Teacher', 'desc' => 'Add a new teacher profile.', 'lottie' => 'assets/animations/add-user.json'],
            ['href' => 'manage_students.php', 'icon' => 'bi-people-fill', 'title' => 'Manage Students', 'desc' => 'View, edit, and manage all students.', 'lottie' => 'assets/animations/user-group.json'],
            ['href' => 'manage_teachers.php', 'icon' => 'bi-person-badge', 'title' => 'Manage Teachers', 'desc' => 'View, edit, and manage all teachers.', 'lottie' => 'assets/animations/user-group.json'],
            ['href' => 'manage_guardians.php', 'icon' => 'bi-shield-lock-fill', 'title' => 'Manage Guardians', 'desc' => 'Manage parent and guardian accounts.', 'lottie' => 'assets/animations/user-group-shield.json'],
            ['href' => 'manage_users.php', 'icon' => 'bi-person-gear', 'title' => 'Manage Users', 'desc' => 'Edit user roles and access.', 'lottie' => 'assets/animations/user-settings.json'],
            ['href' => 'view_activity_log.php', 'icon' => 'bi-journal-text', 'title' => 'View Activity Log', 'desc' => 'Review all system and user activities.', 'lottie' => 'assets/animations/activity-log.json'],
            ['href' => 'academic_overview.php', 'icon' => 'bi-bar-chart-line-fill', 'title' => 'Academic Overview', 'desc' => 'High-level school statistics.', 'lottie' => 'assets/animations/analytics-chart.json'],
            ['href' => 'manage_predictions.php', 'icon' => 'bi-robot', 'title' => 'AI Predictions', 'desc' => 'Manage and view AI-based student predictions.', 'lottie' => 'assets/animations/analytics-chart.json'],
            ['href' => 'view_teacher_evaluations.php', 'icon' => 'bi-person-check', 'title' => 'Teacher Evaluations', 'desc' => 'View anonymous student feedback.', 'lottie' => 'assets/animations/reports.json'],
            ['href' => 'student_growth_dashboard.php', 'icon' => 'bi-graph-up', 'title' => 'Student Growth', 'desc' => 'Visualize student progress and correlations.', 'lottie' => 'assets/animations/analytics-chart.json'],
            ['href' => 'run_gamification_awards.php', 'icon' => 'bi-award-fill', 'title' => 'Run Gamification Awards', 'desc' => 'Manually run scripts to award points.', 'lottie' => 'assets/animations/announcement.json'],
            ['href' => 'view_all_notifications.php', 'icon' => 'bi-bell', 'title' => 'All Notifications', 'desc' => 'View all notifications sent to users.', 'lottie' => 'assets/animations/activity-log.json'],
            ['href' => 'run_guardian_alerts.php', 'icon' => 'bi-exclamation-triangle-fill', 'title' => 'Run Guardian Alerts', 'desc' => 'Check for and send emergency alerts to guardians.', 'lottie' => 'assets/animations/announcement.json'],
            ['href' => 'send_notification.php', 'icon' => 'bi-send-fill', 'title' => 'Send Notification', 'desc' => 'Broadcast messages to users or groups.', 'lottie' => 'assets/animations/announcement.json'],
            ['href' => 'manage_discipline.php', 'icon' => 'bi-cone-striped', 'title' => 'Discipline Records', 'desc' => 'Manage student discipline incidents.', 'lottie' => 'assets/animations/reports.json'],
            ['href' => 'manage_assignments.php', 'icon' => 'bi-card-checklist', 'title' => 'Manage Assignments', 'desc' => 'Assign students, teachers, and subjects.', 'lottie' => 'assets/animations/assignments.json'],
            ['href' => 'manage_core_data.php', 'icon' => 'bi-bricks', 'title' => 'School Core Data', 'desc' => 'Manage classrooms, subjects, etc.', 'lottie' => 'assets/animations/database.json'],
            ['href' => 'manage_news.php', 'icon' => 'bi-newspaper', 'title' => 'Manage News', 'desc' => 'Post and edit school-wide news.', 'lottie' => 'assets/animations/news.json'],
            ['href' => 'manage_calendar.php', 'icon' => 'bi-calendar-week', 'title' => 'Manage Calendar', 'desc' => 'Set academic calendar events.', 'lottie' => 'assets/animations/calendar.json'],
            ['href' => 'manage_events.php', 'icon' => 'bi-calendar2-event-fill', 'title' => 'Manage Events', 'desc' => 'Create and manage school events.', 'lottie' => 'assets/animations/calendar.json'],
            ['href' => 'manage_leave_requests.php', 'icon' => 'bi-calendar-x', 'title' => 'Leave Requests', 'desc' => 'Approve or deny staff leave requests.', 'lottie' => 'assets/animations/leave-request.json'],
            ['href' => 'visitor_pass.php', 'icon' => 'bi-person-bounding-box', 'title' => 'Issue Visitor Pass', 'desc' => 'Generate temporary visitor passes.', 'lottie' => 'assets/animations/visitor-pass.json'],
            ['href' => 'manage_absence_excuses.php', 'icon' => 'bi-card-list', 'title' => 'Absence Excuses', 'desc' => 'Review all student absence excuses.', 'lottie' => 'assets/animations/leave-request-history.json'],
            ['href' => 'print_classroom_ids.php', 'icon' => 'bi-printer-fill', 'title' => 'Print Class IDs', 'desc' => 'Batch print ID cards for a class.', 'lottie' => 'assets/animations/print.json'],
            ['href' => 'print_staff_ids.php', 'icon' => 'bi-printer', 'title' => 'Print Staff IDs', 'desc' => 'Batch print ID cards for staff.', 'lottie' => 'assets/animations/print.json'],
        ];
        break;
    case 'teacher':
        $dashboard_items = [
            ['href' => 'my_classes.php', 'icon' => 'bi-easel-fill', 'title' => 'My Classes', 'desc' => 'View your assigned classes and students.', 'lottie' => 'assets/animations/classroom.json'],
            ['href' => 'take_attendance.php', 'icon' => 'bi-check-circle-fill', 'title' => 'Take Attendance', 'desc' => 'Mark student attendance for your classes.', 'lottie' => 'assets/animations/check-list.json'],
            ['href' => 'enter_grades.php', 'icon' => 'bi-pencil-square', 'title' => 'Enter Grades', 'desc' => 'Input and update student grades.', 'lottie' => 'assets/animations/grades.json'],
            ['href' => 'my_teacher_schedule.php', 'icon' => 'bi-table', 'title' => 'My Schedule', 'desc' => 'View your weekly teaching schedule.', 'lottie' => 'assets/animations/schedule.json'],
            ['href' => 'teacher_post_announcement.php', 'icon' => 'bi-megaphone-fill', 'title' => 'Post Announcements', 'desc' => 'Send messages to your students.', 'lottie' => 'assets/animations/announcement.json'],
            ['href' => 'request_leave.php', 'icon' => 'bi-calendar-x-fill', 'title' => 'Request Leave', 'desc' => 'Submit a leave of absence request.', 'lottie' => 'assets/animations/leave-request.json'],
            ['href' => 'manage_absence_excuses.php', 'icon' => 'bi-card-list', 'title' => 'Absence Excuses', 'desc' => 'Review student absence excuses.', 'lottie' => 'assets/animations/leave-request-history.json'],
            ['href' => 'request_leave.php#leave-history', 'icon' => 'bi-card-list', 'title' => 'My Leave Requests', 'desc' => 'View the status of your leave requests.', 'lottie' => 'assets/animations/leave-request-history.json'],
            ['href' => 'view_calendar.php', 'icon' => 'bi-calendar-event', 'title' => 'Academic Calendar', 'desc' => 'View important school dates.', 'lottie' => 'assets/animations/calendar.json'],
            ['href' => 'all_news.php', 'icon' => 'bi-newspaper', 'title' => 'School News', 'desc' => 'Read the latest school news.', 'lottie' => 'assets/animations/news.json'],
        ];
        break;
    case 'student':
    case 'rep':
        $dashboard_items = [
            ['href' => 'my_points.php', 'icon' => 'bi-star-fill', 'title' => 'My Points', 'desc' => 'View your points and badges.', 'lottie' => 'assets/animations/grades.json'],
            ['href' => 'leaderboard.php', 'icon' => 'bi-trophy-fill', 'title' => 'Leaderboard', 'desc' => 'See the school-wide rankings.', 'lottie' => 'assets/animations/analytics-chart.json'],
            ['href' => 'view_my_grades.php', 'icon' => 'bi-file-earmark-text-fill', 'title' => 'View My Grades', 'desc' => 'Check your latest scores.', 'lottie' => 'assets/animations/grades.json'],
            ['href' => 'view_my_discipline.php', 'icon' => 'bi-cone-striped', 'title' => 'Discipline Record', 'desc' => 'View your discipline history.', 'lottie' => 'assets/animations/reports.json'],
            ['href' => 'view_my_attendance.php', 'icon' => 'bi-calendar-check-fill', 'title' => 'View My Attendance', 'desc' => 'Review your attendance record.', 'lottie' => 'assets/animations/attendance.json'],
            ['href' => 'view_my_schedule.php', 'icon' => 'bi-table', 'title' => 'View Class Schedule', 'desc' => 'See your weekly timetable.', 'lottie' => 'assets/animations/schedule.json'],
            ['href' => 'view_announcements.php', 'icon' => 'bi-megaphone', 'title' => 'View Announcements', 'desc' => 'See messages from your teachers.', 'lottie' => 'assets/animations/announcement.json'],
            ['href' => 'career_guidance.php', 'icon' => 'bi-compass-fill', 'title' => 'Career Guidance', 'desc' => 'Get career recommendations based on your performance.', 'lottie' => 'assets/animations/analytics-chart.json'],
            ['href' => 'view_calendar.php', 'icon' => 'bi-calendar-event', 'title' => 'Academic Calendar', 'desc' => 'View important school dates.', 'lottie' => 'assets/animations/calendar.json'],
            ['href' => 'submit_evaluation.php', 'icon' => 'bi-star-half', 'title' => 'Evaluate Teachers', 'desc' => 'Provide anonymous feedback on your classes.', 'lottie' => 'assets/animations/grades.json'],
            ['href' => 'all_news.php', 'icon' => 'bi-newspaper', 'title' => 'School News', 'desc' => 'Read the latest school news.', 'lottie' => 'assets/animations/news.json'],
        ];
        if ($role === 'rep') {
             $dashboard_items[] = ['href' => 'post_class_announcement.php', 'icon' => 'bi-megaphone-fill', 'title' => 'Post Announcement', 'desc' => 'Post a message to your class.', 'lottie' => 'assets/animations/announcement.json'];
        }
        break;
    case 'director':
         $dashboard_items = [
             ['href' => 'academic_overview.php', 'icon' => 'bi-bar-chart-line-fill', 'title' => 'Academic Overview', 'desc' => 'High-level school statistics.', 'lottie' => 'assets/animations/analytics-chart.json'],
             ['href' => 'manage_predictions.php', 'icon' => 'bi-robot', 'title' => 'AI Predictions', 'desc' => 'Manage and view AI-based student predictions.', 'lottie' => 'assets/animations/analytics-chart.json'],
             ['href' => 'view_teacher_evaluations.php', 'icon' => 'bi-person-check', 'title' => 'Teacher Evaluations', 'desc' => 'View anonymous student feedback.', 'lottie' => 'assets/animations/reports.json'],
             ['href' => 'student_growth_dashboard.php', 'icon' => 'bi-graph-up', 'title' => 'Student Growth', 'desc' => 'Visualize student progress and correlations.', 'lottie' => 'assets/animations/analytics-chart.json'],
             ['href' => 'view_all_notifications.php', 'icon' => 'bi-bell', 'title' => 'All Notifications', 'desc' => 'View all notifications sent to users.', 'lottie' => 'assets/animations/activity-log.json'],
             ['href' => 'reports_hub.php', 'icon' => 'bi-file-pdf-fill', 'title' => 'Generate Reports', 'desc' => 'Create and export school reports.', 'lottie' => 'assets/animations/print.json'],
             ['href' => 'manage_discipline.php', 'icon' => 'bi-cone-striped', 'title' => 'Discipline Records', 'desc' => 'Manage student discipline incidents.', 'lottie' => 'assets/animations/reports.json'],
             ['href' => 'manage_absence_excuses.php', 'icon' => 'bi-card-list', 'title' => 'Absence Excuses', 'desc' => 'Review all student absence excuses.', 'lottie' => 'assets/animations/leave-request-history.json'],
             ['href' => 'manage_leave_requests.php', 'icon' => 'bi-calendar-x-fill', 'title' => 'Manage Leave', 'desc' => 'Review staff leave requests.', 'lottie' => 'assets/animations/leave-request.json'],
             ['href' => 'manage_students.php', 'icon' => 'bi-people-fill', 'title' => 'Manage Students', 'desc' => 'View, edit, and manage all students.', 'lottie' => 'assets/animations/user-group.json'],
             ['href' => 'manage_teachers.php', 'icon' => 'bi-person-badge', 'title' => 'Manage Teachers', 'desc' => 'View, edit, and manage all teachers.', 'lottie' => 'assets/animations/user-group.json'],
             ['href' => 'visitor_pass.php', 'icon' => 'bi-person-bounding-box', 'title' => 'Issue Visitor Pass', 'desc' => 'Generate temporary visitor passes.', 'lottie' => 'assets/animations/visitor-pass.json'],
         ];
        break;
    case 'guardian':
         $dashboard_items = [
             ['href' => 'my_children.php', 'icon' => 'bi-people', 'title' => "My Children's Hub", 'desc' => 'Access grades and attendance.', 'lottie' => 'assets/animations/family.json'],
             ['href' => 'all_news.php', 'icon' => 'bi-newspaper', 'title' => 'School News', 'desc' => 'Read the latest school news.', 'lottie' => 'assets/animations/news.json'],
             ['href' => 'view_calendar.php', 'icon' => 'bi-calendar-event', 'title' => 'Academic Calendar', 'desc' => 'View important school dates.', 'lottie' => 'assets/animations/calendar.json'],
         ];
        break;
}

// --- Fetch Upcoming Events for relevant roles ---
$upcoming_events = [];
if (in_array($role, ['student', 'teacher', 'rep', 'guardian'])) {
    // The db connection is included in header.php
    $sql_events = "SELECT event_id, title, event_date, location, image_path FROM events WHERE event_date >= NOW() AND status = 'published' ORDER BY event_date ASC LIMIT 5";
    if ($result_events = mysqli_query($conn, $sql_events)) {
        $upcoming_events = mysqli_fetch_all($result_events, MYSQLI_ASSOC);
    }
}

$page_title = 'Dashboard';
include 'header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    .dashboard-card i {
        font-size: 3rem;
        color: var(--primary-color);
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
    <div class="d-flex align-items-center mb-4">
        <div class="me-3">
            <div id="lottie-welcome"></div>
        </div>
        <div>
            <h1 class="mb-0">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <p class="lead text-muted mb-0">Your role: <strong><?php echo htmlspecialchars(ucfirst($role)); ?></strong></p>
        </div>
    </div>
    
    <h3 class="mt-5">Dashboard Menu</h3>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
        <?php if (!empty($dashboard_items)): ?>
            <?php foreach ($dashboard_items as $index => $item): ?>
                <div class="col">
                    <div class="card h-100 text-center shadow-sm dashboard-card">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                            <i class="<?php echo htmlspecialchars($item['icon']); ?> mb-2"></i>
                            <h5 class="card-title mt-2"><?php echo htmlspecialchars($item['title']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($item['desc']); ?></p>
                            <a href="<?php echo htmlspecialchars($item['href']); ?>" class="stretched-link"></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">No specific actions are available for your role at this time.</div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($upcoming_events)): ?>
    <div class="mt-5">
        <h3 class="mb-3">Upcoming Events</h3>
        <div class="list-group">
            <?php foreach ($upcoming_events as $event): ?>
                <a href="view_event.php?id=<?php echo $event['event_id']; ?>" class="list-group-item list-group-item-action flex-column align-items-start">
                    <div class="d-flex w-100">
                        <img src="<?php echo (!empty($event['image_path']) && file_exists($event['image_path'])) ? htmlspecialchars($event['image_path']) : 'assets/default-image.png'; ?>" alt="Event" style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; margin-right: 15px;">
                        <div class="flex-grow-1">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <small class="text-muted"><?php echo date('D, M j', strtotime($event['event_date'])); ?></small>
                            </div>
                            <p class="mb-1 text-muted">
                                <i class="bi bi-clock-fill"></i> <?php echo date('g:i A', strtotime($event['event_date'])); ?>
                                <?php if (!empty($event['location'])): ?>
                                    <span class="ms-3"><i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($event['location']); ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php include 'footer.php'; ?>
                renderer: 'svg',
                loop: true,
                autoplay: true,
                path: item.lottie
            });
        }
    });
</script>
