<?php
session_start();

include_once "db_connect.php";

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Helper function to sanitize data
function sanitizeString($str) {
    global $mysqli;
    return $mysqli->real_escape_string(trim($str));
}

// Check for login and company ID
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
    $response = ["success" => false, "message" => "Not logged in or company ID missing."];
    echo json_encode($response);
    exit();
}

$companyId = (int)$_SESSION["company_id"];

// Get data from POST request
$contractId = isset($_POST["contract_id"]) ? (int)$_POST["contract_id"] : 0;
$craftTypeId = isset($_POST["craft_id"]) ? (int)$_POST["craft_id"] : 0;

// Validate input data
if ($contractId <= 0 || $craftTypeId <= 0) {
    $response = ["success" => false, "message" => "Invalid contract or craft ID."];
    echo json_encode($response);
    exit();
}

// Check if the contract and craft belong to the company - JOINT QUERY
$checkSql = "SELECT c.id AS contract_id, craft.id AS craft_id
             FROM contracts c
             JOIN crafts craft ON craft.company_id = c.company_id
             WHERE c.id = ? AND craft.id = ? AND c.company_id = ?";

$checkStmt = $mysqli->prepare($checkSql);

if (!$checkStmt) {
    $response = ["success" => false, "message" => "Prepare failed (contract/craft check): " . $mysqli->error];
    echo json_encode($response);
    exit();
}

$checkStmt->bind_param("iii", $contractId, $craftTypeId, $companyId);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows == 0) {
    $response = ["success" => false, "message" => "Contract or craft not found or does not belong to your company."];
    $checkStmt->close();
    echo json_encode($response);
    exit();
}

$checkStmt->close();


//Check existing relationships
$checkSql = "SELECT id FROM contract_crafts WHERE contract_id = ? AND craft_id = ?";
$checkStmt = $mysqli->prepare($checkSql);

if (!$checkStmt) {
  $response = ["success" => false, "message" => "Failed to prepare check statement: " . $mysqli->error];
  echo json_encode($response);
  exit;
}

$checkStmt->bind_param("ii", $contractId, $craftTypeId);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    $response = ["success" => false, "message" => "This craft and contract combination already exists."];
    echo json_encode($response);
    $checkStmt->close();
    exit;
}

$checkStmt->close();

// Insert into contract_craft table
$insertSql = "INSERT INTO contract_crafts (contract_id, craft_id) VALUES (?, ?)";
$insertStmt = $mysqli->prepare($insertSql);

if (!$insertStmt) {
    $response = ["success" => false, "message" => "Prepare failed (insert): " . $mysqli->error];
    echo json_encode($response);
    exit();
}

$insertStmt->bind_param("ii", $contractId, $craftTypeId);
if ($insertStmt->execute()) {
    //Update the crafts table
    $updateCraftSql = "UPDATE crafts SET contract_id = ? WHERE id = ?";
    $updateCraftStmt = $mysqli->prepare($updateCraftSql);

    if (!$updateCraftStmt) {
        $response = ["success" => false, "message" => "Prepare failed (update crafts): " . $mysqli->error];
        echo json_encode($response);
        exit();
    }

    $updateCraftSql->bind_param("ii", $contractId, $craftTypeId);
    if ($updateCraftStmt->execute()) {
        $response = ["success" => true, "message" => "Craft added to contract successfully!"];
    } else {
        $response = ["success" => false, "message" => "Craft added to contract, but failed to update crafts table: " . $updateCraftStmt->error];
    }
    $updateCraftStmt->close();
} else {
    $response = ["success" => false, "message" => "Insert failed: " . $insertStmt->error];
}

echo json_encode($response);

$insertStmt->close();
$mysqli->close();
?>