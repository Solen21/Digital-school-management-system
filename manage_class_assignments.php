<?php
session_start();

// 1. Check if the user is logged in and is an admin.
if (!isset($_SESSION["user_id"]) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

$message = '';
$message_type = '';

// --- Handle POST request to save assignments ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_assignments'])) {
    $classroom_id = $_POST['classroom_id'];
    $assigned_student_ids = $_POST['assigned_students'] ?? [];

    if (empty($classroom_id)) {
        $message = "Please select a classroom before saving.";
        $message_type = 'danger';
    } else {
        mysqli_begin_transaction($conn);
        try {
            // First, remove all existing assignments for this classroom
            $sql_delete = "DELETE FROM class_assignments WHERE classroom_id = ?";
            $stmt_delete = mysqli_prepare($conn, $sql_delete);
            mysqli_stmt_bind_param($stmt_delete, "i", $classroom_id);
            mysqli_stmt_execute($stmt_delete);
            mysqli_stmt_close($stmt_delete);

            // Now, insert the new assignments
            if (!empty($assigned_student_ids)) {
                $sql_insert = "INSERT INTO class_assignments (student_id, classroom_id) VALUES (?, ?)";
                $stmt_insert = mysqli_prepare($conn, $sql_insert);
                foreach ($assigned_student_ids as $student_id) {
                    mysqli_stmt_bind_param($stmt_insert, "ii", $student_id, $classroom_id);
                    mysqli_stmt_execute($stmt_insert);
                }
                mysqli_stmt_close($stmt_insert);
            }

            mysqli_commit($conn);
            $message = "Class assignments updated successfully.";
            $message_type = 'success'; 
            log_activity($conn, 'update_class_assignments', $classroom_id, "Updated assignments for classroom ID {$classroom_id}.");

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error updating assignments: " . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// --- Fetch data for display ---
$classrooms = mysqli_query($conn, "SELECT classroom_id, name FROM classrooms ORDER BY name ASC");

$selected_classroom_id = $_GET['classroom_id'] ?? ($_POST['classroom_id'] ?? null);
$assigned_students = [];
$unassigned_students = [];
$selected_classroom_details = null; // New variable

if ($selected_classroom_id) {
    // Get details for the selected classroom (including capacity)
    $sql_details = "SELECT name, capacity FROM classrooms WHERE classroom_id = ?";
    $stmt_details = mysqli_prepare($conn, $sql_details);
    mysqli_stmt_bind_param($stmt_details, "i", $selected_classroom_id);
    mysqli_stmt_execute($stmt_details);
    $result_details = mysqli_stmt_get_result($stmt_details);
    $selected_classroom_details = mysqli_fetch_assoc($result_details);
    mysqli_stmt_close($stmt_details);

    // Get students assigned to the selected classroom
    $sql_assigned = "SELECT s.student_id, s.first_name, s.last_name FROM students s JOIN class_assignments ca ON s.student_id = ca.student_id WHERE ca.classroom_id = ? ORDER BY s.last_name, s.first_name";
    $stmt_assigned = mysqli_prepare($conn, $sql_assigned);
    mysqli_stmt_bind_param($stmt_assigned, "i", $selected_classroom_id);
    mysqli_stmt_execute($stmt_assigned);
    $result_assigned = mysqli_stmt_get_result($stmt_assigned);
    while ($row = mysqli_fetch_assoc($result_assigned)) {
        $assigned_students[] = $row;
    }
    mysqli_stmt_close($stmt_assigned);
}

// Get all students NOT assigned to ANY classroom
$sql_unassigned = "SELECT s.student_id, s.first_name, s.last_name FROM students s WHERE s.student_id NOT IN (SELECT student_id FROM class_assignments) ORDER BY s.last_name, s.first_name";
$result_unassigned = mysqli_query($conn, $sql_unassigned);
while ($row = mysqli_fetch_assoc($result_unassigned)) {
    $unassigned_students[] = $row;
}

mysqli_close($conn);
$page_title = 'Assign Students to Classroom';
include 'header.php';
?>
<style>
    .assignment-container { display: grid; grid-template-columns: 1fr 100px 1fr; gap: 20px; align-items: start; }
    .student-list-panel { border: 1px solid var(--medium-gray); border-radius: var(--border-radius-lg); background-color: var(--white); box-shadow: var(--shadow-sm); }
    .student-list-panel .card-header { background-color: var(--light-gray); border-bottom: 1px solid var(--medium-gray); }
    .student-list { list-style-type: none; padding: 0; margin: 0; height: 500px; overflow-y: auto; }
    .student-list li { padding: 10px 15px; border-bottom: 1px solid var(--light-gray); }
    .student-list li:last-child { border-bottom: none; }
    .student-list li.draggable { cursor: grab; }
    .student-list li.dragging { opacity: 0.5; background: var(--primary-color-light); }
    .shuttle-buttons { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; padding-top: 150px; }
</style>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Assign Students to Classroom</h1>
        <a href="manage_assignments.php" class="btn btn-secondary">Back to Assignments</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Classroom Selection -->
    <div class="card bg-light mb-4">
        <div class="card-body">
            <form action="manage_class_assignments.php" method="GET" id="classroom-select-form">
                <label for="classroom_id" class="form-label">Select a Classroom to Manage</label>
                <div class="input-group">
                    <select name="classroom_id" id="classroom_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select Classroom --</option>
                        <?php mysqli_data_seek($classrooms, 0); // Reset pointer ?>
                        <?php while($classroom = mysqli_fetch_assoc($classrooms)): ?>
                            <option value="<?php echo $classroom['classroom_id']; ?>" <?php echo ($selected_classroom_id == $classroom['classroom_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($classroom['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button class="btn btn-primary" type="submit">Load</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_classroom_id): ?>
    <form action="manage_class_assignments.php" method="POST" id="assignment-form">
        <input type="hidden" name="classroom_id" value="<?php echo $selected_classroom_id; ?>">
        <input type="hidden" name="save_assignments" value="1">

        <div class="assignment-container mt-4">
            <!-- Unassigned Students Panel -->
            <div class="student-list-panel">
                <div class="card-header"><strong>Available (Unassigned) Students</strong></div>
                <ul id="unassigned-list" class="student-list">
                    <?php if(empty($unassigned_students)): ?> <li class="text-muted text-center p-4">No unassigned students.</li> <?php endif; ?>
                    <?php foreach ($unassigned_students as $student): ?>
                        <li class="draggable" draggable="true" data-id="<?php echo $student['student_id']; ?>">
                            <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Shuttle Buttons -->
            <div class="shuttle-buttons">
                <i class="bi bi-arrow-left-right fs-1 text-muted"></i>
            </div>

            <!-- Assigned Students Panel -->
            <div class="student-list-panel">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Students in this Class</strong>
                    <?php if ($selected_classroom_details): ?>
                        <?php 
                            $current_count = count($assigned_students);
                            $capacity = $selected_classroom_details['capacity'];
                            $capacity_class = ($current_count > $capacity) ? 'bg-danger' : (($current_count == $capacity) ? 'bg-warning text-dark' : 'bg-success');
                        ?>
                        <span class="badge <?php echo $capacity_class; ?>" id="capacity-indicator" title="Current students / Capacity">
                            <i class="bi bi-people-fill me-1"></i>
                            <span id="assigned-count"><?php echo $current_count; ?></span> / <?php echo $capacity; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <ul id="assigned-list" class="student-list">
                     <?php if(empty($assigned_students)): ?> <li class="text-muted text-center p-4">Drag students here.</li> <?php endif; ?>
                    <?php foreach ($assigned_students as $student): ?>
                        <li class="draggable" draggable="true" data-id="<?php echo $student['student_id']; ?>">
                            <?php echo htmlspecialchars($student['last_name'] . ', ' . $student['first_name']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <div class="text-end mt-4">
            <button type="submit" class="btn btn-primary btn-lg" onclick="prepareSubmit()">
                <i class="bi bi-save-fill me-2"></i>Save Changes
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const draggables = document.querySelectorAll('.draggable');
    const containers = document.querySelectorAll('.student-list');

    draggables.forEach(draggable => {
        draggable.addEventListener('dragstart', () => draggable.classList.add('dragging'));
        draggable.addEventListener('dragend', () => {
            draggable.classList.remove('dragging');
            updateCapacityIndicator(); // Update count after drop
        });
    });

    containers.forEach(container => {
        container.addEventListener('dragover', e => {
            e.preventDefault();
            const afterElement = getDragAfterElement(container, e.clientY);
            const dragging = document.querySelector('.dragging');
            if (afterElement == null) {
                container.appendChild(dragging);
            } else {
                container.insertBefore(dragging, afterElement);
            }
        });
    });

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.draggable:not(.dragging)')];
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function updateCapacityIndicator() {
        const assignedList = document.getElementById('assigned-list');
        const assignedCountEl = document.getElementById('assigned-count');
        const capacityIndicatorEl = document.getElementById('capacity-indicator');
        
        if (!assignedList || !assignedCountEl || !capacityIndicatorEl) return;

        const currentCount = assignedList.querySelectorAll('li.draggable').length;
        const capacity = <?php echo $selected_classroom_details['capacity'] ?? 0; ?>;

        assignedCountEl.textContent = currentCount;

        // Reset classes
        capacityIndicatorEl.classList.remove('bg-success', 'bg-warning', 'text-dark', 'bg-danger');

        if (currentCount > capacity) {
            capacityIndicatorEl.classList.add('bg-danger');
        } else if (currentCount === capacity) {
            capacityIndicatorEl.classList.add('bg-warning', 'text-dark');
        } else {
            capacityIndicatorEl.classList.add('bg-success');
        }
    }

    // Initial call in case of browser back/forward or if JS loads after elements
    updateCapacityIndicator();
});

function prepareSubmit() {
    const assignedList = document.getElementById('assigned-list');
    const form = document.getElementById('assignment-form');
    
    // Clear previous hidden inputs if any
    form.querySelectorAll('input[name="assigned_students[]"]').forEach(input => input.remove());

    // Create a hidden input for each student in the "assigned" list
    assignedList.querySelectorAll('li').forEach(item => {
        if(item.dataset.id) { // Ensure it's a student item
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'assigned_students[]';
            input.value = item.dataset.id;
            form.appendChild(input);
        }
    });
}
</script>

<?php include 'footer.php'; ?>