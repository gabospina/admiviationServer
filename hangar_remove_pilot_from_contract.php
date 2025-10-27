<?php
session_start();

include_once "db_connect.php";

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION["HeliUser"])) {
    $response = ["success" => false, "message" => "You are not logged in."];
    echo json_encode($response);
    exit;
}

// Get the pilot ID from the session
$pilotId = (int)$_SESSION["HeliUser"];

// Get the contract ID from the POST data
$contractId = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0;

// Validate the contract ID
if ($contractId <= 0) {
    $response = ["success" => false, "message" => "Invalid contract ID."];
    echo json_encode($response);
    exit;
}

// Prepare the SQL query to delete the contract from the pilot's profile
$sql = "DELETE FROM contract_pilots WHERE pilot_id = ? AND contract_id = ?";
$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    $response = ["success" => false, "message" => "Prepare failed: " . htmlspecialchars($mysqli->error)];
    echo json_encode($response);
    exit;
}

// Bind parameters and execute the query
$stmt->bind_param("ii", $pilotId, $contractId);

if ($stmt->execute()) {
    // Check if any rows were affected
    if ($stmt->affected_rows > 0) {
        $response = ["success" => true, "message" => "Contract removed successfully."];
    } else {
        $response = ["success" => false, "message" => "No matching contract found for this pilot."];
    }
} else {
    $response = ["success" => false, "message" => "Execute failed: " . htmlspecialchars($stmt->error)];
}

// Close the statement and connection
$stmt->close();
$mysqli->close();

// Return the response as JSON
echo json_encode($response);
?>