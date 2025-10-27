<?php
session_start();

// --- SESSION-BASED CSRF VALIDATION ---
$submitted_token = $_POST['form_token'] ?? '';

if (empty($submitted_token)) {
    throw new Exception("Security token missing. Please refresh the page.", 403);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    throw new Exception("Invalid security token. Please refresh the page.", 403);
}

include_once "db_connect.php";
// REMOVED: // REMOVED: require_once 'login_csrf_handler.php'; // ADDED

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$response = ["success" => false, "message" => "An unknown error occurred.", 'new_csrf_token' => $_SESSION['csrf_token']];

try {
    // ADD CSRF VALIDATION HERE:
    if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // Check for login and company ID
    if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
        throw new Exception("Not logged in or company ID missing.", 401);
    }

    $companyId = (int)$_SESSION["company_id"];

    // Get data from POST request
    $contractId = isset($_POST["contract_id"]) ? (int)$_POST["contract_id"] : 0;
    $craftTypeId = isset($_POST["craft_id"]) ? (int)$_POST["craft_id"] : 0;

    // Validate input data
    if ($contractId <= 0 || $craftTypeId <= 0) {
        throw new Exception("Invalid contract or craft ID.", 400);
    }

    // Check if the contract and craft belong to the company - JOINT QUERY
    $checkSql = "SELECT c.id AS contract_id, craft.id AS craft_id
                 FROM contracts c
                 JOIN crafts craft ON craft.company_id = c.company_id
                 WHERE c.id = ? AND craft.id = ? AND c.company_id = ?";

    $checkStmt = $mysqli->prepare($checkSql);

    if (!$checkStmt) {
        throw new Exception("Prepare failed (contract/craft check): " . $mysqli->error, 500);
    }

    $checkStmt->bind_param("iii", $contractId, $craftTypeId, $companyId);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows == 0) {
        throw new Exception("Contract or craft not found or does not belong to your company.", 404);
    }
    $checkStmt->close();

    // Check existing relationships
    $checkSql = "SELECT id FROM contract_crafts WHERE contract_id = ? AND craft_id = ?";
    $checkStmt = $mysqli->prepare($checkSql);

    if (!$checkStmt) {
        throw new Exception("Failed to prepare check statement: " . $mysqli->error, 500);
    }

    $checkStmt->bind_param("ii", $contractId, $craftTypeId);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        throw new Exception("This craft and contract combination already exists.", 409);
    }
    $checkStmt->close();

    // Insert into contract_craft table
    $insertSql = "INSERT INTO contract_crafts (contract_id, craft_id) VALUES (?, ?)";
    $insertStmt = $mysqli->prepare($insertSql);

    if (!$insertStmt) {
        throw new Exception("Prepare failed (insert): " . $mysqli->error, 500);
    }

    $insertStmt->bind_param("ii", $contractId, $craftTypeId);
    if ($insertStmt->execute()) {
        // Update the crafts table
        $updateCraftSql = "UPDATE crafts SET contract_id = ? WHERE id = ?";
        $updateCraftStmt = $mysqli->prepare($updateCraftSql);

        if (!$updateCraftStmt) {
            throw new Exception("Prepare failed (update crafts): " . $mysqli->error, 500);
        }

        $updateCraftStmt->bind_param("ii", $contractId, $craftTypeId);
        if ($updateCraftStmt->execute()) {
            $response = ["success" => true, "message" => "Craft added to contract successfully!", 'new_csrf_token' => $_SESSION['csrf_token']];
            $response['new_csrf_token'] = CSRFHandler::generateToken(); // ADDED
        } else {
            throw new Exception("Craft added to contract, but failed to update crafts table: " . $updateCraftStmt->error, 500);
        }
        $updateCraftStmt->close();
    } else {
        throw new Exception("Insert failed: " . $insertStmt->error, 500);
    }

    $insertStmt->close();

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response["message"] = $e->getMessage();
     // ADDED
}

if (isset($mysqli)) {
    $mysqli->close();
}

echo json_encode($response);
?>