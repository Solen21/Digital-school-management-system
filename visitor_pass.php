<?php
session_start();

// 1. Security Check: User must be logged in and have an appropriate role.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

$page_title = 'Issue Visitor Pass';
include 'header.php';
?>

<div class="container" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Issue Temporary Visitor Pass</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill me-2"></i>
        Fill out the form below to generate a temporary ID pass for a visitor. The pass will be valid for today only.
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Visitor Details</h5></div>
        <div class="card-body">
            <form action="generate_visitor_pass_pdf.php" method="POST" target="_blank">
                <div class="mb-3"><label for="visitor_name" class="form-label">Visitor's Full Name</label><input type="text" class="form-control" id="visitor_name" name="visitor_name" required></div>
                <div class="mb-3"><label for="reason_for_visit" class="form-label">Reason for Visit</label><input type="text" class="form-control" id="reason_for_visit" name="reason_for_visit" required></div>
                <div class="mb-3"><label for="person_to_visit" class="form-label">Person/Office to Visit</label><input type="text" class="form-control" id="person_to_visit" name="person_to_visit" required></div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-printer-fill me-2"></i>Generate & Print Pass
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>