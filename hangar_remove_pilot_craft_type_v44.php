<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once "db_connect.php";

// Check if the user is logged in
if (!isset($_SESSION["HeliUser"])) {
    $response = ["success" => false, "message" => "User not logged in."];
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Function to sanitize data (prevent SQL injection and other issues)
function sanitizeString($str) {
    global $mysqli;
    return $mysqli->real_escape_string(trim($str));
}

$pilot_id = (int)$_SESSION["HeliUser"]; //Getting from sessions
$id = isset($_POST['id']) ? intval($_POST['id']) : 0; // Get ID from post

error_log("Attempting to remove craft type with ID: " . $id); // add line in php

// Use prepared statements to prevent SQL injection
$sql = "DELETE FROM pilot_craft_type WHERE id = ? AND pilot_id = ?"; //Make sure id correct and pilot id match to avoid delete any else

$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    $response = ["success" => false, "message" => "Prepare failed: " . htmlspecialchars($mysqli->error)];
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}

$stmt->bind_param("ii", $id, $pilot_id);

// Execute the statement
if ($stmt->execute()) {
    // Check if any rows were affected
    if ($stmt->affected_rows > 0) {
        $response = ["success" => true, "message" => "Craft type removed successfully."];
    } else {
        $response = ["success" => false, "message" => "Craft type not found or could not be removed."];
    }
} else {
    $response = ["success" => false, "message" => "Execute failed: " . htmlspecialchars($stmt->error)];
}

// Close statement and connection
$stmt->close();
$mysqli->close();

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response, JSON_PRETTY_PRINT);
?>