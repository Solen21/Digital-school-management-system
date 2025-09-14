<?php
session_start();

// 1. Security Check: User must be an admin or director.
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION['role'], ['admin', 'director'])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'functions.php';

$message = '';
$message_type = '';

// --- POST Request Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action == 'add_event' || $action == 'update_event') {
        $title = $_POST['title'];
        $event_date = $_POST['event_date'];
        $location = $_POST['location'];
        $description = $_POST['description'];
        $image_path = $_POST['existing_image_path'] ?? null;
        $created_by = $_SESSION['user_id'];

        if (empty($title) || empty($event_date)) {
            $message = "Title and Event Date are required.";
            $message_type = 'danger';
        } else {
            if ($action == 'add_event') {
                $upload_dir = 'uploads/events/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $image_path = handle_file_upload('event_image', $upload_dir, ['image/jpeg', 'image/png', 'image/gif'], 5 * 1024 * 1024);

                $sql = "INSERT INTO events (title, event_date, location, description, created_by, image_path) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssis", $title, $event_date, $location, $description, $created_by, $image_path);
            } else { // update_event
                $event_id = $_POST['event_id'];
                $upload_dir = 'uploads/events/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $image_path = process_file_update('event_image', $_POST['existing_image_path'], $upload_dir, ['image/jpeg', 'image/png', 'image/gif'], 5 * 1024 * 1024);

                $sql = "UPDATE events SET title = ?, event_date = ?, location = ?, description = ?, image_path = ? WHERE event_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssssi", $title, $event_date, $location, $description, $image_path, $event_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $message = "Event " . ($action == 'add_event' ? 'added' : 'updated') . " successfully.";
                $message_type = 'success';
                $target_id = ($action == 'add_event') ? mysqli_insert_id($conn) : $event_id;
                log_activity($conn, $action, $target_id, "Event: {$title}");
            } else {
                $message = "Error: " . mysqli_stmt_error($stmt);
                $message_type = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    } elseif ($action == 'delete_event') {
        $event_id = $_POST['event_id'];
        // First, get the image path to delete the file
        $sql_get_image = "SELECT image_path FROM events WHERE event_id = ?";
        $stmt_get_image = mysqli_prepare($conn, $sql_get_image);
        mysqli_stmt_bind_param($stmt_get_image, "i", $event_id);
        mysqli_stmt_execute($stmt_get_image);
        $image_to_delete = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_get_image))['image_path'] ?? null;
        mysqli_stmt_close($stmt_get_image);

        $sql = "DELETE FROM events WHERE event_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $event_id);
        if (mysqli_stmt_execute($stmt)) {
            $message = "Event deleted successfully.";
            $message_type = 'success';
            log_activity($conn, 'delete_event', $event_id, "Deleted event ID {$event_id}");
            if (!empty($image_to_delete) && file_exists($image_to_delete)) {
                unlink($image_to_delete);
            }
        } else {
            $message = "Error deleting event.";
            $message_type = 'danger';
        }
        mysqli_stmt_close($stmt);
    }
}

// --- Fetch data for display ---
// --- Filtering Logic ---
$search_title = $_GET['search_title'] ?? '';
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';

// --- Pagination Logic ---
$records_per_page = 10; // Number of events per page
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// --- Count total records for pagination ---
$sql_count = "SELECT COUNT(event_id) as total FROM events e";

$where_clauses = [];
$params = [];
$param_types = '';

if (!empty($search_title)) {
    $where_clauses[] = "e.title LIKE ?";
    $params[] = "%" . $search_title . "%";
    $param_types .= 's';
}
if (!empty($filter_start_date)) {
    $where_clauses[] = "DATE(e.event_date) >= ?";
    $params[] = $filter_start_date;
    $param_types .= 's';
}
if (!empty($filter_end_date)) {
    $where_clauses[] = "DATE(e.event_date) <= ?";
    $params[] = $filter_end_date;
    $param_types .= 's';
}

if (!empty($where_clauses)) {
    $sql_count .= " WHERE " . implode(' AND ', $where_clauses);
}

$stmt_count = mysqli_prepare($conn, $sql_count);
if (!empty($params)) { mysqli_stmt_bind_param($stmt_count, $param_types, ...$params); }
mysqli_stmt_execute($stmt_count);
$total_records = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'] ?? 0;
mysqli_stmt_close($stmt_count);

$total_pages = ceil($total_records / $records_per_page);

// --- Fetch paginated events ---
$sql_events = "SELECT e.*, u.username as author_name FROM events e LEFT JOIN users u ON e.created_by = u.user_id ORDER BY event_date DESC LIMIT ?, ?";
$stmt_events = mysqli_prepare($conn, $sql_events);
if (!empty($where_clauses)) {
    $sql_events = "SELECT e.*, u.username as author_name FROM events e LEFT JOIN users u ON e.created_by = u.user_id WHERE " . implode(' AND ', $where_clauses) . " ORDER BY event_date DESC LIMIT ?, ?";
    $params[] = $records_per_page;
    $params[] = $offset;
    $param_types .= 'ii';
    $stmt_events = mysqli_prepare($conn, $sql_events);
    mysqli_stmt_bind_param($stmt_events, $param_types, ...$params);
} else {
    mysqli_stmt_bind_param($stmt_events, "ii", $records_per_page, $offset);
}
mysqli_stmt_execute($stmt_events);
$events = mysqli_stmt_get_result($stmt_events);

$edit_event = null;
if (isset($_GET['edit_id'])) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM events WHERE event_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_GET['edit_id']);
    mysqli_stmt_execute($stmt);
    $edit_event = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
$page_title = 'Manage Events';
include 'header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Manage School Events</h1>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Add/Edit Form -->
        <div class="col-lg-4">
            <div class="card" id="event-form-card">
                <div class="card-header"><h5><?php echo $edit_event ? 'Edit' : 'Add New'; ?> Event</h5></div>
                <div class="card-body">
                    <form action="manage_events.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?php echo $edit_event ? 'update_event' : 'add_event'; ?>">
                        <?php if ($edit_event): ?><input type="hidden" name="event_id" value="<?php echo $edit_event['event_id']; ?>"><?php endif; ?>
                        <input type="hidden" name="existing_image_path" value="<?php echo htmlspecialchars($edit_event['image_path'] ?? ''); ?>">
                        
                        <div class="mb-3"><label for="title" class="form-label">Event Title</label><input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($edit_event['title'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="event_date" class="form-label">Event Date & Time</label><input type="datetime-local" class="form-control" id="event_date" name="event_date" value="<?php echo htmlspecialchars($edit_event['event_date'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="location" class="form-label">Location</label><input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($edit_event['location'] ?? ''); ?>"></div>
                        <div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($edit_event['description'] ?? ''); ?></textarea></div>
                        <div class="mb-3"><label for="event_image" class="form-label">Event Image</label><input class="form-control" type="file" id="event_image" name="event_image" accept="image/*">
                        <?php if (!empty($edit_event['image_path']) && file_exists($edit_event['image_path'])): ?>
                            <div class="mt-2"><small>Current Image:</small><br><img src="<?php echo htmlspecialchars($edit_event['image_path']); ?>" alt="Event Image" style="max-width: 100px; max-height: 100px; border-radius: 5px;"></div>
                        <?php endif; ?>
                        </div>
                        
                        <button type="submit" class="btn btn-primary"><?php echo $edit_event ? 'Update' : 'Add'; ?> Event</button>
                        <?php if ($edit_event): ?><a href="manage_events.php" class="btn btn-secondary ms-2">Cancel</a><?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Events Table -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Upcoming & Past Events (<?php echo $total_records; ?>)</h5>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false">
                            <i class="bi bi-funnel-fill"></i> Filter
                        </button>
                    </div>
                    <div class="collapse" id="filterCollapse">
                        <form action="manage_events.php" method="GET" class="row g-2 align-items-end pt-3">
                            <div class="col-md-5"><label for="search_title" class="form-label">Search by Title</label><input type="text" id="search_title" name="search_title" class="form-control form-control-sm" value="<?php echo htmlspecialchars($search_title); ?>"></div>
                            <div class="col-md-3"><label for="start_date" class="form-label">From</label><input type="date" id="start_date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_start_date); ?>"></div>
                            <div class="col-md-3"><label for="end_date" class="form-label">To</label><input type="date" id="end_date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_end_date); ?>"></div>
                            <div class="col-md-1 d-flex gap-2"><button type="submit" class="btn btn-primary btn-sm w-100">Go</button></div>
                            <div class="col-md-1 d-flex gap-2"><a href="manage_events.php" class="btn btn-secondary btn-sm w-100">Clear</a></div>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Image</th>
                                    <th>Title</th>
                                    <th>Date & Time</th>
                                    <th>Location</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($total_records === 0): ?>
                                    <tr><td colspan="6" class="text-center">No events found.</td></tr>
                                <?php else: ?>
                                    <?php while($event = mysqli_fetch_assoc($events)): ?>
                                    <tr>
                                        <td><img src="<?php echo (!empty($event['image_path']) && file_exists($event['image_path'])) ? htmlspecialchars($event['image_path']) : 'assets/default-image.png'; ?>" alt="Event" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;"></td>
                                        <td><?php echo htmlspecialchars($event['title']); ?></td>
                                        <td><?php echo date('M j, Y, g:i A', strtotime($event['event_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($event['author_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <a href="?edit_id=<?php echo $event['event_id']; ?>#event-form-card" class="btn btn-sm btn-primary" title="Edit"><i class="bi bi-pencil-fill"></i></a>
                                            <form action="manage_events.php" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                                <input type="hidden" name="action" value="delete_event">
                                                <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-trash-fill"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php include 'pagination_controls.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>