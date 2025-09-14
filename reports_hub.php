<?php
session_start();

// 1. Check if the user is logged in and is a director or admin.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['director', 'admin'])) {
    header("Location: login.php");
    exit();
}

$page_title = 'Reports Hub';
include 'header.php';

// Define the available reports
$report_items = [
    [
        'href' => 'export_grade_averages.php',
        'icon' => 'bi-bar-chart-line-fill',
        'title' => 'Student Grade Averages',
        'desc' => 'Export an Excel sheet of all students with their overall average grade.',
        'target' => '_blank',
        'lottie' => 'assets/animations/analytics-chart.json'
    ],
    [
        'href' => 'export_students.php',
        'icon' => 'bi-people-fill',
        'title' => 'Full Student List',
        'desc' => 'Export a complete list of all students with their detailed information.',
        'target' => '_blank',
        'lottie' => 'assets/animations/reports.json'
    ],
    [
        'href' => 'export_teachers.php',
        'icon' => 'bi-person-video3',
        'title' => 'Full Staff List',
        'desc' => 'Export a complete list of all teachers and their contact information.',
        'target' => '_blank',
        'lottie' => 'assets/animations/reports.json'
    ],
    [
        'href' => 'report_enrollment.php',
        'icon' => 'bi-pie-chart-fill',
        'title' => 'Enrollment Summary',
        'desc' => 'View a summary of student enrollment numbers across all grade levels.',
        'target' => '',
        'lottie' => 'assets/animations/analytics-chart.json'
    ],
    [
        'href' => '#',
        'icon' => 'bi-calendar-check',
        'title' => 'Attendance Report',
        'desc' => 'Generate attendance summaries for classes or the entire school (Coming Soon).',
        'target' => '',
        'disabled' => true,
        'lottie' => 'assets/animations/attendance.json'
    ],
    [
        'href' => '#',
        'icon' => 'bi-book',
        'title' => 'Teacher Workload',
        'desc' => 'View a report on the number of classes and subjects assigned to each teacher (Coming Soon).',
        'target' => '',
        'disabled' => true,
        'lottie' => 'assets/animations/assignments.json'
    ],
];

?>
<style>
    .report-card {
        background-color: var(--white);
        border: 1px solid var(--medium-gray);
        transition: all 0.3s ease-in-out;
        text-decoration: none;
        color: var(--dark-gray);
    }
    .report-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
        border-color: var(--primary-color);
        color: var(--primary-color);
    }
    .report-card.disabled {
        opacity: 0.6;
        pointer-events: none;
        background-color: var(--light-gray);
    }
    .report-card .card-body { min-height: 180px; }
    .report-card .card-title { font-weight: 600; }
    .report-card .card-text { color: var(--gray); font-size: 0.9rem; }
    .report-card .report-lottie {
        width: 70px;
        height: 70px;
        margin: 0 auto;
    }
</style>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Reports Hub</h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280;">Back to Dashboard</a>
    </div>
    <p class="lead">Generate and export key school data reports.</p>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-4 mt-3">
        <?php foreach ($report_items as $index => $item): ?>
            <div class="col">
                <a href="<?php echo htmlspecialchars($item['href']); ?>" 
                   class="card h-100 text-center shadow-sm report-card <?php echo ($item['disabled'] ?? false) ? 'disabled' : ''; ?>"
                   <?php if (!empty($item['target'])) echo 'target="' . $item['target'] . '"'; ?>>
                    <div class="card-body d-flex flex-column justify-content-center align-items-center">
                        <div class="report-lottie" id="lottie-item-<?php echo $index; ?>"></div>
                        <h5 class="card-title mt-2"><?php echo htmlspecialchars($item['title']); ?></h5>
                        <p class="card-text"><?php echo htmlspecialchars($item['desc']); ?></p>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    const reportItems = <?php echo json_encode($report_items); ?>;
    reportItems.forEach((item, index) => {
        if (item.lottie) {
            bodymovin.loadAnimation({
                container: document.getElementById(`lottie-item-${index}`),
                renderer: 'svg',
                loop: true,
                autoplay: true,
                path: item.lottie
            });
        }
    });
</script>
<?php include 'footer.php'; ?>