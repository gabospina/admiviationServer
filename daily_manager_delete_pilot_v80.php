<?php
// daily_manager_delete_pilot.php - FIXED VERSION

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ADD ERROR HANDLING AT THE TOP
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'login_csrf_handler.php'; // Your class-based CSRF handler
require_once 'login_permissions.php';
require_once 'permissions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // --- 1. Security & Input Validation ---
    // FIX: Use canManagePilotAdmin() instead of canEditTrainingSchedule()
    if (!canManagePilotAdmin()) {
        throw new Exception("Permission denied to delete users.", 403);
    }
    
    // FIX: Use your class-based CSRF validation
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (!CSRFHandler::validateToken($submitted_token)) {
        throw new Exception("Invalid security token. Please refresh the page.", 403);
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

    // --- 2. Verification Step ---
    $stmt_check = $mysqli->prepare("SELECT is_active FROM users WHERE id = ? AND company_id = ?");
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

    // --- 3. Database Transaction ---
    $mysqli->begin_transaction();
    
    // Temporarily disable foreign key checks
    $mysqli->query("SET FOREIGN_KEY_CHECKS=0;");

    try {
        // --- 4. Process Core Dependencies ---
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
            } else {
                error_log("Warning: Could not prepare statement for essential dependency table: {$table}");
            }
        }
        
        // --- 5. Final Deletion ---
        $stmt_user = $mysqli->prepare("DELETE FROM `users` WHERE `id` = ?");
        $stmt_user->bind_param("i", $pilot_id_to_delete);
        $stmt_user->execute();
        
        if ($stmt_user->affected_rows > 0) {
            $mysqli->commit(); 
            $response['success'] = true;
            $response['message'] = "Pilot has been permanently deleted.";
        } else {
            throw new Exception("Final deletion step failed. The user was not deleted from the main table.", 500);
        }
        $stmt_user->close();

    } catch (Exception $e) {
        $mysqli->rollback();
        throw $e;
    } finally {
        // Always re-enable foreign key checks
        $mysqli->query("SET FOREIGN_KEY_CHECKS=1;");
    }

} catch (Exception $e) {
    // Clean any output buffer
    if (ob_get_length()) ob_clean();
    
    $response['message'] = "Operation failed: " . $e->getMessage(); 
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
}

// FIX: Use generateToken instead of getToken
$new_csrf_token = CSRFHandler::generateToken();
$response['new_csrf_token'] = $new_csrf_token;

// Ensure no extra output
if (ob_get_length()) ob_clean();
echo json_encode($response);
exit;
?>