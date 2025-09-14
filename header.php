<?php
if (session_status() == PHP_SESSION_NONE) {
    // Start the session only if it's not already started
    session_start();
}
$is_logged_in = isset($_SESSION["user_id"]);
$user_role = $_SESSION['role'] ?? '';
$page_title = $page_title ?? 'Old Model School'; // Use a default title if not set by the page

$profile_pic_path = 'assets/default-avatar.png'; // Default avatar
$unread_notifications = 0;

if ($is_logged_in) {
    require 'data/db_connect.php';
    $user_id = $_SESSION['user_id'];
    $table_name = '';

    switch ($user_role) {
        case 'student':
        case 'rep':
            $table_name = 'students';
            break;
        case 'teacher':
            $table_name = 'teachers';
            break;
    }

    if (!empty($table_name)) {
        $stmt_photo = mysqli_prepare($conn, "SELECT photo_path FROM $table_name WHERE user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_photo, "i", $user_id);
        mysqli_stmt_execute($stmt_photo);
        $result_photo = mysqli_stmt_get_result($stmt_photo);
        if ($photo_row = mysqli_fetch_assoc($result_photo)) {
            if (!empty($photo_row['photo_path']) && file_exists($photo_row['photo_path'])) {
                $profile_pic_path = $photo_row['photo_path'];
            }
        }
        mysqli_stmt_close($stmt_photo);
    }

    // Fetch unread notification count
    $sql_count = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt_count = mysqli_prepare($conn, $sql_count);
    mysqli_stmt_bind_param($stmt_count, "i", $user_id);
    mysqli_stmt_execute($stmt_count);
    if ($count_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))) {
        $unread_notifications = $count_row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Old Model School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Custom style for a smaller, circular profile picture in the navbar */
        .profile-pic {
            width: 36px;      /* Smaller width */
            height: 36px;     /* Smaller height */
            border-radius: 50%; /* Makes the image circular */
            object-fit: cover;  /* Ensures the image covers the area without distortion */
        }
        .unread-notification {
            background-color: var(--primary-color-light) !important;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="motion-background"><div id="lottie-bg"></div></div>
    
    <nav class="navbar navbar-expand-lg main-nav">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $is_logged_in ? 'dashboard.php' : 'login.php'; ?>">Old Model School</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-nav-collapse" aria-controls="main-nav-collapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="main-nav-collapse">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <?php if ($is_logged_in): ?>
                        <!-- Notification Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell-fill position-relative">
                                    <?php if ($unread_notifications > 0): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notification-badge">
                                            <?php echo $unread_notifications; ?>
                                            <span class="visually-hidden">unread messages</span>
                                        </span>
                                    <?php endif; ?>
                                </i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                                <li class="notification-header"><h6>Notifications</h6></li>
                                <li><div id="notification-list" class="list-group list-group-flush"></div></li>
                                <li><a class="dropdown-item text-center small text-muted py-2" href="view_notifications.php">View All Notifications</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="<?php echo htmlspecialchars($profile_pic_path); ?>" alt="Profile" class="profile-pic">
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                                <li><a class="dropdown-item" href="view_profile.php?user_id=<?php echo $_SESSION['user_id']; ?>">My ID Card</a></li>
                                <li><a class="dropdown-item" href="settings.php">Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                    <?php endif; ?>
                    <li class="nav-item ms-3">
                        <button id="theme-toggle" class="theme-toggle" title="Toggle dark mode">
                            <i class="bi bi-moon-fill"></i>
                            <i class="bi bi-sun-fill"></i>
                        </button>
                    </li>
                </ul>
        </div>
    </nav>
