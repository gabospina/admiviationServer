<?php
// daily_manager_delete_pilot.php - v83 - FIXED VERSION

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
    
    // Security & Input Validation
    if (!canManagePilotAdmin()) {
        throw new Exception("Permission denied to delete users.", 403);
    }
    
    if (!isset($_SESSION['company_id'], $_SESSION['HeliUser'])) {
        throw new Exception("Authentication required.", 401);
    }
    
    $company_id = (int)$_SESSION['company_id'];
    $manager_user_id = (int)$_SESSION['HeliUser'];
    $pilot_id_to_delete = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    if (!$pilot_id_to_delete) {
        throw new Exception("Invalid Pilot ID provided.", 400);
    }
    if ($pilot_id_to_delete === $manager_user_id) {
        throw new Exception("You cannot delete your own account.", 403);
    }

    // Verification Step
    $stmt_check = $mysqli->prepare("SELECT firstname, lastname, is_active FROM users WHERE id = ? AND company_id = ?");
    $stmt_check->bind_param("ii", $pilot_id_to_delete, $company_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Pilot not found in your company.", 404);
    }
    $user_data = $result->fetch_assoc();
    $stmt_check->close();
    
    if ((int)$user_data['is_active'] !== 0) {
        throw new Exception("Pilot must be set to Inactive before they can be permanently deleted.", 400);
    }

    // Database Transaction
    $mysqli->begin_transaction();
    
    // Temporarily disable foreign key checks
    $mysqli->query("SET FOREIGN_KEY_CHECKS=0;");

    // Process Core Dependencies
    $essential_dependencies = [
        'user_has_roles',
        'contract_pilots', 
        'pilot_craft_type',
        'user_availability',
        'schedule'
    ];

    foreach ($essential_dependencies as $table) {
        $stmt = $mysqli->prepare("DELETE FROM `{$table}` WHERE `user_id` = ?");
        if ($stmt) {
            $stmt->bind_param("i", $pilot_id_to_delete);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Final Deletion
    $stmt_user = $mysqli->prepare("DELETE FROM `users` WHERE `id` = ?");
    $stmt_user->bind_param("i", $pilot_id_to_delete);
    $stmt_user->execute();
    
    if ($stmt_user->affected_rows > 0) {
        $mysqli->commit();
        
        // Regenerate CSRF token for security
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $response = [
            'success' => true,
            'message' => "Pilot {$user_data['firstname']} {$user_data['lastname']} has been permanently deleted.", // ✅ CORRECT MESSAGE
            'new_csrf_token' => $_SESSION['csrf_token']
        ];
    } else {
        throw new Exception("Final deletion step failed. The user was not deleted from the main table.", 500);
    }
    $stmt_user->close();

} catch (Exception $e) {
    if (isset($mysqli)) {
        $mysqli->rollback();
    }
    
    $response = [
        'success' => false,
        'message' => $e->getMessage(), // ✅ Use 'message' not 'error'
        'new_csrf_token' => $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32))
    ];
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

// Re-enable foreign key checks
if (isset($mysqli)) {
    $mysqli->query("SET FOREIGN_KEY_CHECKS=1;");
}

echo json_encode($response);
?>