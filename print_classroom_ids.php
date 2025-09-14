<?php
session_start();

// 1. Security Check: User must be logged in and have an appropriate role.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

// Fetch all classrooms for the dropdown
$classrooms = [];
$sql_classrooms = "SELECT classroom_id, name, grade_level FROM classrooms ORDER BY grade_level, name";
$result_classrooms = mysqli_query($conn, $sql_classrooms);
if ($result_classrooms) {
    while ($row = mysqli_fetch_assoc($result_classrooms)) {
        $classrooms[] = $row;
    }
}

$selected_classroom_id = $_GET['classroom_id'] ?? null;

mysqli_close($conn);

$page_title = 'Print Classroom ID Cards';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Print Classroom ID Cards</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <!-- Classroom Selection Form -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <form action="print_classroom_ids.php" method="GET" id="classroom-select-form">
                <label for="classroom_id" class="form-label fw-bold">Step 1: Select a Classroom</label>
                <div class="input-group">
                    <select name="classroom_id" id="classroom_id" class="form-select form-select-lg" onchange="this.form.submit()">
                        <option value="">-- Select Classroom --</option>
                        <?php foreach ($classrooms as $classroom): ?>
                            <option value="<?php echo $classroom['classroom_id']; ?>" <?php echo ($selected_classroom_id == $classroom['classroom_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($classroom['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" type="submit">Load</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_classroom_id): ?>
        <!-- Generate PDF Section -->
        <div class="card">
            <div class="card-body text-center p-5">
                <i class="bi bi-printer-fill" style="font-size: 4rem; color: var(--primary-color);"></i>
                <h3 class="card-title mt-3">Step 2: Generate PDF for Selected Class</h3>
                <p class="card-text text-muted mx-auto" style="max-width: 600px;">Click the button below to generate a PDF file containing ID cards for all students in the selected classroom. The PDF will be optimized for double-sided printing.</p>
                <a href="generate_classroom_ids_pdf.php?classroom_id=<?php echo $selected_classroom_id; ?>" class="btn btn-primary btn-lg mt-3" target="_blank">
                    <i class="bi bi-file-earmark-pdf-fill me-2"></i>Print All ID Cards for this Class
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-dashed"><div class="card-body text-center text-muted p-5"><i class="bi bi-arrow-up-circle" style="font-size: 4rem;"></i><h4 class="mt-3">Select a classroom above to begin.</h4><p>Once a class is selected, you will see the option to print ID cards.</p></div></div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>