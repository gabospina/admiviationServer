<?php
// daily_manager_pilot_activate_deactivate.php - v83 FIXED VERSION

error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) { 
    session_start(); 
}

require_once 'db_connect.php';
require_once 'permissions.php';

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

    // Permission checks
    if (!canManagePilotAdmin()) {
        throw new Exception("You do not have permission to change pilot status.", 403);
    }

    if (!isset($_SESSION['company_id'], $_SESSION['HeliUser'])) {
        throw new Exception("Authentication required.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];

    // Input validation
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

    // Database update
    $new_status = ($action === 'activate') ? 1 : 0;
    
    $stmt = $mysqli->prepare("UPDATE users SET is_active = ? WHERE id = ? AND company_id = ?");
    if (!$stmt) throw new Exception("Database prepare statement failed: " . $mysqli->error, 500);

    $stmt->bind_param("iii", $new_status, $user_id, $company_id);

    if (!$stmt->execute()) throw new Exception("Database execute failed: " . $stmt->error, 500);
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Pilot not found, or status is already set.", 404);
    }
    $stmt->close();
    
    // Regenerate CSRF token for security
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $response = [
        'success' => true,
        'message' => "Pilot status updated successfully.", // ✅ CORRECT MESSAGE
        'new_csrf_token' => $_SESSION['csrf_token']
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage(), // ✅ Use 'message' not 'error'
        'new_csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32))
    ];
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

echo json_encode($response);
?>