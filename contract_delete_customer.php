<?php
// contract_delete_customer.php - SESSION DEBUG VERSION
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'permissions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// CRITICAL DEBUG INFO
error_log("=== SESSION DEBUG ===");
error_log("Session ID: " . session_id());
error_log("Session Name: " . session_name());
error_log("All Session Data: " . print_r($_SESSION, true));
error_log("Posted form_token: " . ($_POST['form_token'] ?? 'NOT POSTED'));

try {
    // Check if we have a session at all
    if (empty($_SESSION)) {
        error_log("ERROR: Session is completely empty!");
        throw new Exception("Session lost. Please refresh the page and login again.", 403);
    }

    // Check if session token exists
    if (!isset($_SESSION['csrf_token'])) {
        error_log("WARNING: Generating new CSRF token because session token missing");
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Check if form_token was posted
    if (!isset($_POST['form_token'])) {
        error_log("ERROR: No form_token in POST data!");
        $response['new_csrf_token'] = $_SESSION['csrf_token'];
        throw new Exception("Security token missing from request.", 403);
    }

    // Validate CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['form_token'])) {
        error_log("ERROR: Token mismatch!");
        error_log("Session token: " . $_SESSION['csrf_token']);
        error_log("Posted token: " . $_POST['form_token']);
        $response['new_csrf_token'] = $_SESSION['csrf_token'];
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    error_log("SUCCESS: CSRF token validated");

    // Rest of your business logic...
    if (!canManageAssets()) {
        throw new Exception("You do not have permission to delete customers.", 403);
    }
    
    $company_id = (int)$_SESSION['company_id'];
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    if ($customer_id <= 0) {
        throw new Exception("Invalid Customer ID provided.", 400);
    }

    // Check for Existing Contracts
    $check_sql = "SELECT COUNT(*) as contract_count FROM contracts WHERE customer_id = ?";
    $check_stmt = $mysqli->prepare($check_sql);
    $check_stmt->bind_param("i", $customer_id);
    $check_stmt->execute();
    $contract_count = (int)$check_stmt->get_result()->fetch_assoc()['contract_count'];
    $check_stmt->close();

    if ($contract_count > 0) {
        throw new Exception("Cannot delete this customer because they are linked to {$contract_count} contract(s). Please reassign or delete those contracts first.", 409);
    }

    // Proceed with Deletion
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
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $response['new_csrf_token'] = $_SESSION['csrf_token'];
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
    $response['new_csrf_token'] = $_SESSION['csrf_token'];
}

error_log("Final response: " . json_encode($response));
echo json_encode($response);
?>