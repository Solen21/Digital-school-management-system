<?php
$host = "localhost";
$user = "root";   // change if needed
$pass = "";       // your MySQL password
$dbname     = "sms";

// Connect to MySQL server
$conn = mysqli_connect($host, $user, $pass);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Create Database
$sql = "CREATE DATABASE IF NOT EXISTS videocall_app";
if (mysqli_query($conn, $sql)) {
    echo "✅ Database created successfully.<br>";
} else {
    echo "❌ Error creating database: " . mysqli_error($conn) . "<br>";
}

// Select database
mysqli_select_db($conn, "videocall_app");

// Create Tables
$tables = [];

// users table
$tables[] = "CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// rooms table
$tables[] = "CREATE TABLE IF NOT EXISTS rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(50) UNIQUE NOT NULL,
    host_id INT NOT NULL,
    room_type ENUM('video','audio','meeting') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (host_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

// participants table
$tables[] = "CREATE TABLE IF NOT EXISTS participants (
    participant_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('host','member') DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

// messages table
$tables[] = "CREATE TABLE IF NOT EXISTS messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('text','file') DEFAULT 'text',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

// call_logs table
$tables[] = "CREATE TABLE IF NOT EXISTS call_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE
)";

// Run all table queries
foreach ($tables as $query) {
    if (mysqli_query($conn, $query)) {
        echo "✅ Table created successfully.<br>";
    } else {
        echo "❌ Error creating table: " . mysqli_error($conn) . "<br>";
    }
}

mysqli_close($conn);
?>
