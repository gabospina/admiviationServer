<?php
/**
 * File: daily_manager_prepare_notifications_delete_item.php v83 (SESSION-BASED CSRF)
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

// REMOVE: require_once 'login_csrf_handler.php';
include_once "db_connect.php";

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
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

    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception('Authentication Required.', 401);
    }
    $adding_user_id = (int)$_SESSION['HeliUser'];

    $schedule_id = isset($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : 0;
    if ($schedule_id <= 0) {
        throw new Exception('Invalid Schedule ID provided.', 400);
    }

    $sql = "DELETE FROM sms_prepare_notifications WHERE schedule_id = ? AND adding_user_id = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("DB Prepare Error: " . $mysqli->error);

    $stmt->bind_param("ii", $schedule_id, $adding_user_id);

    if ($stmt->execute()) {
        // Regenerate CSRF token on success
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $response['success'] = true;
        $response['message'] = "Queue item for schedule ID {$schedule_id} processed for removal.";
        $response['new_csrf_token'] = $_SESSION['csrf_token'];
    } else {
        throw new Exception("DB Execute Error: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
    $response['new_csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    error_log("Error in prepare_notifications_delete_item.php: " . $e->getMessage());
}

echo json_encode($response);
?>