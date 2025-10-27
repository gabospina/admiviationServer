<?php
// contract_delete_customer.php (FINAL, CORRECTED VERSION)

if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'permissions.php';
require_once 'login_csrf_handler.php'; // ADDED

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // ADD CSRF VALIDATION HERE:
    if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // 1. Security & Permission Check
    if (!canManageAssets()) {
        throw new Exception("You do not have permission to delete customers.", 403);
    }
    
    $company_id = (int)$_SESSION['company_id'];
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    if ($customer_id <= 0) {
        throw new Exception("Invalid Customer ID provided.", 400);
    }

    // 2. Check for Existing Contracts
    $check_sql = "SELECT COUNT(*) as contract_count FROM contracts WHERE customer_id = ?";
    $check_stmt = $mysqli->prepare($check_sql);
    $check_stmt->bind_param("i", $customer_id);
    $check_stmt->execute();
    $contract_count = (int)$check_stmt->get_result()->fetch_assoc()['contract_count'];
    $check_stmt->close();

    if ($contract_count > 0) {
        throw new Exception("Cannot delete this customer because they are linked to {$contract_count} contract(s). Please reassign or delete those contracts first.", 409);
    }

    // 3. Proceed with Deletion (Corrected SQL)
    $delete_sql = "DELETE FROM customers WHERE id = ?";
    $delete_stmt = $mysqli->prepare($delete_sql);
    
    if ($delete_stmt === false) {
        throw new Exception("Database prepare statement failed for deletion.", 500);
    }

    $delete_stmt->bind_param("i", $customer_id);
    
    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = "Customer has been deleted successfully.";
            $response['new_csrf_token'] = CSRFHandler::generateToken(); // ADDED
        } else {
            throw new Exception("Customer not found or already deleted.", 404);
        }
    } else {
        throw new Exception("Database error during deletion.", 500);
    }
    $delete_stmt->close();

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
    
    // ✅ Ensure new token is provided on errors
    if (!isset($response['new_csrf_token'])) {
        $response['new_csrf_token'] = CSRFHandler::generateToken();
    }
}

echo json_encode($response);
?>