<?php
session_start();

include_once "db_connect.php";

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Function to sanitize data
function sanitizeString($str)
{
    global $mysqli;
    return $mysqli->real_escape_string(trim($str));
}

// Check if user is logged in and has company_id
if (!isset($_SESSION["HeliUser"])) {
    $response = ["success" => false, "message" => "You are not logged in properly or company ID not found."];
    echo json_encode($response);
    exit();
}

// Get session variables
$pilotId = (int)$_SESSION["HeliUser"];
// $companyId = (int)$_SESSION["company_id"];

// Get data from POST
$craftType = isset($_POST["craft_type"]) ? sanitizeString($_POST["craft_type"]) : "";
$position = isset($_POST["position"]) ? sanitizeString($_POST["position"]) : "";

if (empty($craftType) || empty($position)) {
    $response = ["success" => false, "message" => "Craft Type and Position are required."];
    echo json_encode($response);
    exit();
}

// Validate position
if ($position !== 'PIC' && $position !== 'SIC') {
    $response = ["success" => false, "message" => "Invalid position selected."];
    echo json_encode($response);
    exit();
}

// Check if the relationship already exists
$checkSql = "SELECT id FROM pilot_craft_type WHERE pilot_id = ? AND craft_type = ? AND position = ?";
$checkStmt = $mysqli->prepare($checkSql);

if (!$checkStmt) {
    $response = ["success" => false, "message" => "Failed to prepare check statement: " . $mysqli->error];
    echo json_encode($response);
    exit();
}

$checkStmt->bind_param("iss", $pilotId, $craftType, $position);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    $response = ["success" => false, "message" => "This pilot and craft type combination already exists."];
    echo json_encode($response);
    $checkStmt->close();
    exit();
}

$checkStmt->close();

// Insert the new record
$insertSql = "INSERT INTO pilot_craft_type (pilot_id, craft_type, position) VALUES (?, ?, ?)";
$insertStmt = $mysqli->prepare($insertSql);

if (!$insertStmt) {
    $response = ["success" => false, "message" => "Failed to prepare insert statement: " . $mysqli->error];
    echo json_encode($response);
    exit();
}

$insertStmt->bind_param("iss", $pilotId, $craftType, $position);

if ($insertStmt->execute()) {
    $response = ["success" => true, "message" => "Craft type and position added successfully!"];
} else {
    $response = ["success" => false, "message" => "Failed to add craft type and position: " . $insertStmt->error];
}

echo json_encode($response);
$insertStmt->close();
$mysqli->close();
?>