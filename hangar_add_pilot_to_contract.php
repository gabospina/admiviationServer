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
    $response = ["success" => false, "message" => "User not logged in."];
    echo json_encode($response);
    exit;
}

$pilot_id = (int)$_SESSION["HeliUser"]; // Pilot ID from session
$contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0; // Contract ID from POST

if ($contract_id <= 0) {
    $response = ["success" => false, "message" => "Invalid contract ID."];
    echo json_encode($response);
    exit;
}

// Check if the contract is already assigned to the pilot
$checkSql = "SELECT id FROM contract_pilots WHERE pilot_id = ? AND contract_id = ?";
$checkStmt = $mysqli->prepare($checkSql);

if (!$checkStmt) {
    $response = ["success" => false, "message" => "Prepare failed: " . htmlspecialchars($mysqli->error)];
    echo json_encode($response);
    exit;
}

$checkStmt->bind_param("ii", $pilot_id, $contract_id);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    $response = ["success" => false, "message" => "This contract is already assigned to the pilot."];
    echo json_encode($response);
    $checkStmt->close();
    exit;
}

$checkStmt->close();

// Insert the new record
$insertSql = "INSERT INTO contract_pilots (pilot_id, contract_id) VALUES (?, ?)";
$insertStmt = $mysqli->prepare($insertSql);

if (!$insertStmt) {
    $response = ["success" => false, "message" => "Prepare failed: " . htmlspecialchars($mysqli->error)];
    echo json_encode($response);
    exit;
}

$insertStmt->bind_param("ii", $pilot_id, $contract_id);

if ($insertStmt->execute()) {
    $response = ["success" => true, "message" => "Contract added successfully!"];
} else {
    $response = ["success" => false, "message" => "Failed to add contract: " . htmlspecialchars($insertStmt->error)];
}

echo json_encode($response);
$insertStmt->close();
$mysqli->close();
?>