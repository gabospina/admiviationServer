<?php
// date_default_timezone_set('UTC');
date_default_timezone_set('America/Toronto');

// Enable error reporting for debugging
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Temporary permission mapping (create a proper permissions table later)
// $PERMISSION_MAP = [
//     1 => 'manage_account',
//     2 => 'edit_main_schedule',
//     3 => 'edit_training_schedule',
//     4 => 'view_schedule_tab',
//     5 => 'send_sms',
//     6 => 'change_permissions',
//     7 => 'access_pilot_attributes',
//     8 => 'edit_my_hangar'
// ];

// Database connection parameters
$host = 'localhost';          // Usually 'localhost' for local development
$db_user = 'root';            // Your MySQL username (replace if different)
$db_pass = '';                // Your MySQL password (replace if different)
$db_name = 'heli_offshore';   // Your database name

// Create connection
$mysqli = new mysqli($host, $db_user, $db_pass, $db_name);

// After establishing the connection
// $mysqli->query("SET time_zone = '+00:00'");
$mysqli->query("SET time_zone = '".date('P')."'"); // Sets same timezone as PHP

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
} else {
    error_log("Database connected successfully");
	// echo "<p>Database connected successfully</p>";
}

// Set timezone for this connection
if (!$mysqli->query("SET time_zone = '+00:00'")) {
    error_log("MySQL timezone setting failed: " . $mysqli->error);
    // Continue anyway rather than failing completely
}

// Optional: Set the charset to UTF-8 for proper encoding
$mysqli->set_charset("utf8");
?>