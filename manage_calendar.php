<?php
session_start();

// 1. Check if the user is logged in and is an admin or director.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';

// Use session for flash messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
} else {
    $message = '';
    $message_type = '';
}
$message = '';
$message_type = '';

// --- POST Request Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'add_event' || $action == 'update_event') {
        $title = $_POST['title'];
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : NULL;
        $type = $_POST['type'];
        $description = $_POST['description'];
        $user_id = $_SESSION['user_id'];

        if (empty($title) || empty($start_date) || empty($type)) {
            $_SESSION['message'] = "Title, Start Date, and Type are required.";
            $_SESSION['message_type'] = 'danger';
        } else {
            if ($action == 'add_event') {
                $sql = "INSERT INTO academic_calendar (title, start_date, end_date, type, description, created_by) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssssi", $title, $start_date, $end_date, $type, $description, $user_id);
            } else { // update_event
                $id = $_POST['event_id'];
                $sql = "UPDATE academic_calendar SET title = ?, start_date = ?, end_date = ?, type = ?, description = ? WHERE event_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssssi", $title, $start_date, $end_date, $type, $description, $id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = "Calendar event " . ($action == 'add_event' ? 'added' : 'updated') . " successfully.";
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = "Error: " . mysqli_stmt_error($stmt);
                $_SESSION['message_type'] = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action == 'delete_event') {
        $id = $_POST['event_id'];
        $sql = "DELETE FROM academic_calendar WHERE event_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = "Event deleted successfully.";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Error deleting event.";
            $_SESSION['message_type'] = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
    header("Location: manage_calendar.php");
    exit();
}

// --- Fetch data for display ---
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_type = $_GET['type'] ?? '';
$search_title = $_GET['title'] ?? '';

$sql_events = "SELECT * FROM academic_calendar";
$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($filter_start_date)) {
    $where_clauses[] = "start_date >= ?";
    $params[] = $filter_start_date;
    $param_types .= 's';
}
if (!empty($filter_end_date)) {
    $where_clauses[] = "start_date <= ?";
    $params[] = $filter_end_date;
    $param_types .= 's';
}
if (!empty($filter_type)) {
    $where_clauses[] = "type = ?";
    $params[] = $filter_type;
    $param_types .= 's';
}
if (!empty($search_title)) {
    $where_clauses[] = "title LIKE ?";
    $params[] = "%" . $search_title . "%";
    $param_types .= 's';
}
if (!empty($where_clauses)) { $sql_events .= " WHERE " . implode(' AND ', $where_clauses); }
$sql_events .= " ORDER BY start_date ASC";

$edit_event = null;
if (isset($_GET['edit_event_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM academic_calendar WHERE event_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['edit_event_id']);
    mysqli_stmt_execute($stmt);
    $edit_event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$stmt_events = mysqli_prepare($conn, $sql_events);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_events, $param_types, ...$params);
}
mysqli_stmt_execute($stmt_events);
$events_result = mysqli_stmt_get_result($stmt_events);

mysqli_close($conn);
$event_types = ['Holiday', 'Exam Period', 'Term Start', 'Term End', 'Event'];
$page_title = 'Manage Academic Calendar';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Academic Calendar</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-4">
            <div class="card" id="event-form-card">
                <div class="card-header"><h5><?php echo $edit_event ? 'Edit' : 'Add New'; ?> Event</h5></div>
                <div class="card-body">
                    <form action="manage_calendar.php" method="POST">
                        <input type="hidden" name="action" value="<?php echo $edit_event ? 'update_event' : 'add_event'; ?>">
                        <?php if ($edit_event): ?><input type="hidden" name="event_id" value="<?php echo $edit_event['event_id']; ?>"><?php endif; ?>
                        <div class="mb-3"><label for="title" class="form-label">Title</label><input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_event['title'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="start_date" class="form-label">Start Date</label><input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($edit_event['start_date'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="end_date" class="form-label">End Date (optional)</label><input type="date" id="end_date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($edit_event['end_date'] ?? ''); ?>"></div>
                        <div class="mb-3"><label for="type" class="form-label">Type</label><select id="type" name="type" class="form-select" required><?php foreach($event_types as $type): ?><option value="<?php echo $type; ?>" <?php echo (($edit_event['type'] ?? '') == $type) ? 'selected' : ''; ?>><?php echo $type; ?></option><?php endforeach; ?></select></div>
                        <div class="mb-3"><label for="description" class="form-label">Description</label><textarea id="description" name="description" class="form-control"><?php echo htmlspecialchars($edit_event['description'] ?? ''); ?></textarea></div>
                        <button type="submit" class="btn btn-primary"><?php echo $edit_event ? 'Update' : 'Add'; ?> Event</button>
                        <?php if ($edit_event): ?><a href="manage_calendar.php" class="btn btn-secondary ms-2">Cancel Edit</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Calendar Events</h5>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false">
                            <i class="bi bi-funnel-fill"></i> Filter
                        </button>
                    </div>
                    <div class="collapse" id="filterCollapse">
                        <form action="manage_calendar.php" method="GET" class="row g-2 align-items-end pt-3">
                            <div class="col-md-6"><label for="title_filter" class="form-label">Search by Title</label><input type="text" id="title_filter" name="title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search_title); ?>"></div>
                            <div class="col-md-6"><label for="type_filter" class="form-label">Event Type</label><select id="type_filter" name="type" class="form-select form-select-sm"><option value="">All Types</option><?php foreach($event_types as $type): ?><option value="<?php echo $type; ?>" <?php if ($filter_type == $type) echo 'selected'; ?>><?php echo $type; ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-4"><label for="start_date_filter" class="form-label">From</label><input type="date" id="start_date_filter" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_start_date); ?>"></div>
                            <div class="col-md-4"><label for="end_date_filter" class="form-label">To</label><input type="date" id="end_date_filter" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_end_date); ?>"></div>
                            <div class="col-md-4 d-flex gap-2"><button type="submit" class="btn btn-primary btn-sm w-100">Apply</button><a href="manage_calendar.php" class="btn btn-secondary btn-sm w-100">Clear</a></div>
                        </form>
                    </div>
                </div>
                <div class="card-body"><div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light"><tr><th>Date(s)</th><th>Title</th><th>Type</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($events_result)): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($row['start_date'])); ?><?php if ($row['end_date'] && $row['end_date'] != $row['start_date']) echo ' - ' . date('M j, Y', strtotime($row['end_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($row['type']); ?></span></td>
                                <td>
                                    <a href="?edit_event_id=<?php echo $row['event_id']; ?>#event-form-card" class="btn btn-sm btn-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                    <form action="manage_calendar.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                        <input type="hidden" name="action" value="delete_event"><input type="hidden" name="event_id" value="<?php echo $row['event_id']; ?>"><button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>