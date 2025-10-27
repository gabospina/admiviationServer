<?php
// training_remove.php v84
// (Formerly remove_training.php - for training_sim_schedule table)

// Suppress direct error output for AJAX JSON response
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
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

}

header('Content-Type: application/json');
require_once 'db_connect.php';          // VERIFY PATH
require_once 'stats_api_response.php';  // VERIFY PATH

$apiResponse = new ApiResponse();

// --- Session & Permission Checks ---
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id']) || !isset($_SESSION['admin'])) {
    http_response_code(401);
    $apiResponse->setError("Authentication or required session data missing.")->send();
    exit;
}

$company_id = (int)$_SESSION['company_id'];
$admin_level = (int)$_SESSION['admin'];

// Access Control (example using admin_level)
// You can replace this with userHasPermission($mysqli, $_SESSION['HeliUser'], 'delete_training_event', $company_id)
if (!($admin_level > 0 && $admin_level != 2 && $admin_level != 4)) {
    http_response_code(403);
    $apiResponse->setError("Permission denied to remove training events.")->send();
    exit;
}

$event_id_to_remove = isset($_POST["id"]) ? (int)$_POST["id"] : null;

if ($event_id_to_remove === null || $event_id_to_remove <= 0) {
    http_response_code(400);
    $apiResponse->setError("Invalid event ID provided for deletion.")->send();
    exit;
}

global $mysqli;
if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    $apiResponse->setError("Database connection error.")->send();
    error_log("DB Connect error in training_remove.php: " . ($mysqli->connect_error ?? 'Unknown'));
    exit;
}

try {
    // Ensure you are deleting from the correct table: training_sim_schedule
    $sql = "DELETE FROM training_sim_schedule WHERE id = ? AND company_id = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }

    $stmt->bind_param("ii", $event_id_to_remove, $company_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $apiResponse->setSuccess(true)->setMessage("Training event removed successfully.");
        } else {
            // No rows affected - could be ID not found for that company, or already deleted
            http_response_code(404); // Or 200 with a specific message
            $apiResponse->setSuccess(false)->setMessage("Training event not found or already removed for your company.");
        }
    } else {
        throw new Exception("SQL execute error: " . $stmt->error, 500);
    }
    $stmt->close();

} catch (Exception $e) {
    // Log the detailed error to server logs
    error_log("Error in training_remove.php: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $apiResponse->setError("Server error while removing training event."); // User-friendly message
}

$apiResponse->send(); // <<<< THIS SENDS A PROPER JSON RESPONSE
?>