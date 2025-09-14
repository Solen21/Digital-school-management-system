<?php
/**
 * Establishes a connection to the database and makes the connection
 * object $conn available to the including script.
 *
 * It will terminate the script with an error message if the
 * connection fails.
 */
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "sms";

// Create connection directly to the 'sms' database
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}