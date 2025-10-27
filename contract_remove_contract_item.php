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

header('Content-Type: application/json');

$response = ["success" => false, "message" => "An unknown error occurred.", 'new_csrf_token' => $_SESSION['csrf_token']];

try {
    // ADD CSRF VALIDATION HERE:
    if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // Check if user is logged in
    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception("User not logged in.", 401);
    }

    $contract = $_POST["contract"] ?? '';
    $craft = $_POST["craft"] ?? '';

    if (empty($contract) || empty($craft)) {
        throw new Exception("Contract and craft parameters are required.", 400);
    }

    $sql = "DELETE FROM contracts WHERE contract_id=? AND craftid=?";
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error, 500);
    }

    $stmt->bind_param("ss", $contract, $craft);
    
    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Item removed successfully";
         // ADDED
    } else {
        throw new Exception("Delete failed: " . $stmt->error, 500);
    }

    $stmt->close();

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