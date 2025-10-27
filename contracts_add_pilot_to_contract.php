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

// Enable error reporting
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

    // Check if the user is logged in
    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception("User not logged in.", 401);
    }

    $user_id = (int)$_SESSION["HeliUser"]; // Pilot ID from session
    $contract_id = isset($_POST['contract_id']) ? intval($_POST['contract_id']) : 0; // Contract ID from POST

    if ($contract_id <= 0) {
        throw new Exception("Invalid contract ID.", 400);
    }

    // Check if the contract is already assigned to the pilot
    $checkSql = "SELECT id FROM contract_pilots WHERE user_id = ? AND contract_id = ?";
    $checkStmt = $mysqli->prepare($checkSql);

    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $mysqli->error, 500);
    }

    $checkStmt->bind_param("ii", $user_id, $contract_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        throw new Exception("This contract is already assigned to the pilot.", 409);
    }
    $checkStmt->close();

    // Insert the new record
    $insertSql = "INSERT INTO contract_pilots (user_id, contract_id) VALUES (?, ?)";
    $insertStmt = $mysqli->prepare($insertSql);

    if (!$insertStmt) {
        throw new Exception("Prepare failed: " . $mysqli->error, 500);
    }

    $insertStmt->bind_param("ii", $user_id, $contract_id);

    if ($insertStmt->execute()) {
        $response = ["success" => true, "message" => "Contract added successfully!", 'new_csrf_token' => $_SESSION['csrf_token']];
        $response['new_csrf_token'] = CSRFHandler::generateToken(); // ADDED
    } else {
        throw new Exception("Failed to add contract: " . $insertStmt->error, 500);
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