<?php
// trainer_remove.php
// (Formerly remove_trainer.php)

// Suppress direct error output for AJAX JSON response
error_reporting(0);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
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

// Access Control (example using admin_level, can be replaced/augmented with userHasPermission)
if (!($admin_level > 0 && $admin_level != 2 && $admin_level != 4)) {
    http_response_code(403);
    $apiResponse->setError("Permission denied to remove trainer schedules.")->send();
    exit;
}

$schedule_id_to_remove = isset($_POST["id"]) ? (int)$_POST["id"] : null;

if ($schedule_id_to_remove === null || $schedule_id_to_remove <= 0) {
    http_response_code(400);
    $apiResponse->setError("Invalid trainer schedule ID provided for deletion.")->send();
    exit;
}

global $mysqli;
if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    $apiResponse->setError("Database connection error.")->send();
    error_log("DB Connect error in trainer_remove.php: " . ($mysqli->connect_error ?? 'Unknown'));
    exit;
}

try {
    $sql = "DELETE FROM trainer_schedule WHERE id = ? AND company_id = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }

    $stmt->bind_param("ii", $schedule_id_to_remove, $company_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $apiResponse->setSuccess(true)->setMessage("Trainer assignment removed successfully.");
        } else {
            // No rows affected - could be ID not found for that company, or already deleted
            http_response_code(404); // Or 200 with a specific message
            $apiResponse->setSuccess(false)->setMessage("Trainer assignment not found or already removed for your company.");
        }
    } else {
        throw new Exception("SQL execute error: " . $stmt->error, 500);
    }
    $stmt->close();

} catch (Exception $e) {
    // Log the detailed error to server logs
    error_log("Error in trainer_remove.php: " . $e->getMessage() . " - Trace: " . $e->getTraceAsString());
    
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $apiResponse->setError("Server error while removing trainer assignment."); // User-friendly message
}

$apiResponse->send();
?>