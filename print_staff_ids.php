<?php
session_start();

// 1. Security Check: User must be logged in and have an appropriate role.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

$page_title = 'Print Staff ID Cards';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Print Staff ID Cards</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <div class="card bg-light">
        <div class="card-body text-center p-5">
            <i class="bi bi-printer-fill" style="font-size: 4rem; color: var(--primary-color);"></i>
            <h3 class="card-title mt-3">Generate PDF for All Staff</h3>
            <p class="card-text text-muted mx-auto" style="max-width: 600px;">
                Click the button below to generate a single PDF file containing ID cards for all staff members
                (Teachers, Admins, and Directors). The PDF will be optimized for double-sided printing.
            </p>
            <a href="generate_staff_ids_pdf.php" class="btn btn-primary btn-lg mt-3" target="_blank">
                <i class="bi bi-file-earmark-pdf-fill me-2"></i>Print All Staff ID Cards
            </a>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>