<?php
// daily_manager_pilot_activate_deactivate.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'login_csrf_handler.php';
require_once 'login_permissions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // 1. CSRF validation
    if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // 2. Permission checks
    $rolesThatCanManage = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!userHasRole($rolesThatCanManage, $mysqli)) {
        throw new Exception("You do not have permission to change pilot status.", 403);
    }

    if (!isset($_SESSION['company_id'], $_SESSION['HeliUser'])) {
        throw new Exception("Authentication required.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];

    // 3. Input validation
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $action = trim($_POST['action'] ?? '');

    if (!$user_id) {
        throw new Exception("Invalid user ID provided.", 400);
    }
    if (!in_array($action, ['activate', 'deactivate'])) {
        throw new Exception("Invalid action specified.", 400);
    }
    
    if ($user_id === (int)$_SESSION['HeliUser']) {
        throw new Exception("You cannot change your own account status.", 400);
    }

    // 4. Database update
    $new_status = ($action === 'activate') ? 1 : 0;
    
    $stmt = $mysqli->prepare("UPDATE users SET is_active = ? WHERE id = ? AND company_id = ?");
    if (!$stmt) throw new Exception("Database prepare statement failed: " . $mysqli->error, 500);

    $stmt->bind_param("iii", $new_status, $user_id, $company_id);

    if (!$stmt->execute()) throw new Exception("Database execute failed: " . $stmt->error, 500);
    
    if ($stmt->affected_rows === 0) {
        // This can happen if the pilot is already in the desired state.
        // It's not a fatal error, but good to know.
        throw new Exception("Pilot not found, or status is already set.", 404);
    }
    $stmt->close();
    
    // 5. Success response with new token
    unset($_SESSION['csrf_token']);
    $response['new_csrf_token'] = generate_csrf_token();
    $response['success'] = true;
    $response['message'] = 'Pilot status updated successfully.';

} catch (Exception $e) {
    // Also send a new token on failure to keep the form usable
    unset($_SESSION['csrf_token']);
    $response['new_csrf_token'] = generate_csrf_token();
    
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>