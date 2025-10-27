<?php
session_start();
include_once "db_connect.php";

echo "<h2>Session Verification</h2>";

// Check if session user exists
if (!isset($_SESSION["HeliUser"])) {
    die("<p style='color:red'>ERROR: \$_SESSION['HeliUser'] is not set</p>");
}

$user_id = $_SESSION["HeliUser"];
echo "<p>Session User ID: <strong>$user_id</strong></p>";

// Verify against database
$stmt = $mysqli->prepare("SELECT id, fname, lname FROM pilot_info WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<p style='color:green'>SUCCESS: Found matching user in database</p>";
    echo "<ul>";
    echo "<li>ID: ".$user['id']."</li>";
    echo "<li>Name: ".$user['fname']." ".$user['lname']."</li>";
    echo "</ul>";
} else {
    echo "<p style='color:red'>ERROR: No user found with ID $user_id in database</p>";
}

echo "<h3>Session Data</h3>";
echo "<pre>".print_r($_SESSION, true)."</pre>";

echo "<h3>Cookie Data</h3>";
echo "<pre>".print_r($_COOKIE, true)."</pre>";
?>