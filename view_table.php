<?php
session_start();

// 1. Check if the user is logged in. Redirect to login if not.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

// 2. Check if the user has the 'admin' role.
if ($_SESSION['role'] !== 'admin') {
    die("<h1>Access Denied</h1><p>You do not have permission to view this page. <a href='dashboard.php'>Return to Dashboard</a></p>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Table Data</title>
    <style>
        body { font-family: sans-serif; margin: 2em; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .container { max-width: 1200px; margin: auto; }
        .error { color: red; font-weight: bold; }
        .nav { margin-bottom: 20px; }
        .nav a { margin-right: 10px; text-decoration: none; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Database Table Viewer</h1>
        <a href="dashboard.php" class="btn" style="background-color: #6b7280; color: white; text-decoration: none; padding: 0.75rem 1.5rem; border-radius: 0.375rem; font-weight: 600;">Back to Dashboard</a>
    </div>

    <?php
    // Include the centralized database connection.
    require_once 'data/db_connect.php';

    // Security: Whitelist of tables that are safe to view publicly.
    // Avoid exposing sensitive tables like 'users'.
    $allowed_tables = [
        'students', 'teachers', 'guardians', 'subjects', 'classrooms',
        'class_assignments', 'subject_assignments', 'attendance', 'grades',
        'messages', 'reports', 'news', 'gallery', 'profile', 'absence_excuses',
        'grade_deadlines', 'notifications', 'announcements'
    ];

    // Get table name from URL, default to 'students' if not set
    $table_name = $_GET['table'] ?? 'students';

    echo '<div class="nav"><strong>View Table:</strong> ';
    foreach ($allowed_tables as $table) {
        echo "<a href='?table={$table}'>" . ucfirst($table) . "</a>";
    }
    echo '</div>';

    if (!in_array($table_name, $allowed_tables)) {
        die("<p class='error'>Error: Access to table '{$table_name}' is not permitted.</p>");
    }

    // Sanitize table name just in case (though whitelist is primary defense)
    $table_name_sanitized = mysqli_real_escape_string($conn, $table_name);

    echo "<h2>Contents of `{$table_name_sanitized}`</h2>";

    $sql = "SELECT * FROM `{$table_name_sanitized}`";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        // Get field names for table headers
        $fields = mysqli_fetch_fields($result);
        $headers = [];
        foreach ($fields as $field) {
            $headers[] = $field->name;
        }

        echo "<table>";
        echo "<thead><tr>";
        foreach ($headers as $header) {
            echo "<th>" . htmlspecialchars($header) . "</th>";
        }
        echo "</tr></thead>";
        echo "<tbody>";

        // Output data of each row
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            foreach ($row as $data) {
                echo "<td>" . htmlspecialchars($data ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else if ($result) {
        echo "<p>Table '{$table_name_sanitized}' is empty.</p>";
    } else {
        echo "<p class='error'>Error executing query: " . mysqli_error($conn) . "</p>";
    }

    mysqli_close($conn);
    ?>
</div>

</body>
</html>