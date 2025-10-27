<?php
/**
 * File: daily_manager_prepare_notifications_delete_item.php
 * Deletes a single item from the sms_prepare_notifications table based on its schedule_id.
 * This is triggered when a pilot is deselected from the main schedule.
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

// ADD THIS LINE AT TOP:
require_once 'login_csrf_handler.php';

include_once "db_connect.php";

$response = ['success' => false];

try {
    // ADD CSRF VALIDATION HERE:
    if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // Security: Ensure a manager is logged in.
    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception('Authentication Required.', 401);
    }
    $adding_user_id = (int)$_SESSION['HeliUser'];

    // Get and validate the schedule_id from the POST request.
    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    if ($schedule_id <= 0) {
        throw new Exception('Invalid Schedule ID provided.', 400);
    }

    // Prepare the SQL to delete the item from the queue using schedule_id
    $sql = "DELETE FROM sms_prepare_notifications WHERE schedule_id = ? AND adding_user_id = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("DB Prepare Error: " . $mysqli->error);

    $stmt->bind_param("ii", $schedule_id, $adding_user_id);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = "Queue item for schedule ID {$schedule_id} processed for removal.";

        // ADD THIS LINE IN SUCCESS:
        $response['new_csrf_token'] = CSRFHandler::generateToken();
    } else {
        throw new Exception("DB Execute Error: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
    error_log("Error in prepare_notifications_delete_item.php: " . $e->getMessage());

    // ADD THIS LINE IN ERROR:
    $response['new_csrf_token'] = CSRFHandler::generateToken();
}

echo json_encode($response);
?>