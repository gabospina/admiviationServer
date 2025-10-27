<?php
// db_connect.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection parameters
$host = 'localhost';          // Usually 'localhost' for local development
$db_user = 'root';            // Your MySQL username (replace if different)
$db_pass = '';                // Your MySQL password (replace if different)
$db_name = 'heli_offshore';   // Your database name

// Create connection
$mysqli = new mysqli($host, $db_user, $db_pass, $db_name);

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
} else {
    error_log("Database connected successfully");
	// echo "<p>Database connected successfully</p>";
}

// Optional: Set the charset to UTF-8 for proper encoding
$mysqli->set_charset("utf8");
?>