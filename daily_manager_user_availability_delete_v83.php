<?php
/**
 * File: daily_manager_user_availability_delete.php v83 - (SESSION-BASED CSRF)
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'login_permissions.php';
// REMOVE: require_once 'login_csrf_handler.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // --- SESSION-BASED CSRF VALIDATION ---
    $submitted_token = $_POST['form_token'] ?? ''; // Changed from csrf_token
    
    if (empty($submitted_token)) {
        throw new Exception("Security token missing. Please refresh the page.", 403);
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // --- Security & Validation ---
    $rolesThatCanDelete = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!isset($_SESSION["HeliUser"]) || !userHasRole($rolesThatCanDelete, $mysqli)) {
        throw new Exception("Permission Denied.", 403);
    }
    
    $company_id = (int)$_SESSION['company_id'];
    $availability_id = isset($_POST['availability_id']) ? (int)$_POST['availability_id'] : 0;
    
    if ($availability_id <= 0) {
        throw new Exception("Invalid availability record ID provided.", 400);
    }

    // --- Database Delete ---
    $sql = "DELETE FROM user_availability WHERE id = ? AND company_id = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("Database prepare failed: " . $mysqli->error);
    
    $stmt->bind_param("ii", $availability_id, $company_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Regenerate CSRF token on success
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $response['success'] = true;
            $response['message'] = 'Duty period deleted successfully.';
            $response['new_csrf_token'] = $_SESSION['csrf_token'];
        } else {
            throw new Exception("Record not found or you do not have permission to delete it.", 404);
        }
    } else {
        throw new Exception("Failed to delete duty period: " . $stmt->error);
    }

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage(); // Use 'message' not 'error'
    $response['new_csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    error_log("Error in " . basename(__FILE__) . ": " . $e->getMessage());
}

echo json_encode($response);
?>