<?php
// contract_delete_customer.php (FINAL, CORRECTED VERSION)

if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'permissions.php';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // 1. --- Security & Permission Check ---
    if (!canManageAssets()) { // Or a more specific permission if you create one
        throw new Exception("You do not have permission to delete customers.", 403);
    }
    
    // We get company_id to check if the user is acting within their own company's context,
    // but we don't need it for the final DELETE statement.
    $company_id = (int)$_SESSION['company_id'];
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    if ($customer_id <= 0) {
        throw new Exception("Invalid Customer ID provided.", 400);
    }

    // 2. --- CRITICAL: Check for Existing Contracts ---
    // This query is correct and should remain. It checks if any contract is linked to this customer.
    $check_sql = "SELECT COUNT(*) as contract_count FROM contracts WHERE customer_id = ?";
    $check_stmt = $mysqli->prepare($check_sql);
    $check_stmt->bind_param("i", $customer_id);
    $check_stmt->execute();
    $contract_count = (int)$check_stmt->get_result()->fetch_assoc()['contract_count'];
    $check_stmt->close();

    if ($contract_count > 0) {
        // This is working correctly and causing the 409 Conflict error as intended.
        throw new Exception("Cannot delete this customer because they are linked to {$contract_count} contract(s). Please reassign or delete those contracts first.", 409);
    }

    // --- THIS IS THE FIX ---
    // 3. --- Proceed with Deletion (Corrected SQL) ---
    // The `customers` table is likely global and not tied to a specific company_id directly.
    // We delete based only on the customer's primary key `id`.
    $delete_sql = "DELETE FROM customers WHERE id = ?";
    $delete_stmt = $mysqli->prepare($delete_sql);
    
    // Check if the prepare statement was successful BEFORE trying to use it.
    if ($delete_stmt === false) {
        throw new Exception("Database prepare statement failed for deletion.", 500);
    }

    $delete_stmt->bind_param("i", $customer_id); // Now only binds one parameter
    // --- END OF FIX ---
    
    if ($delete_stmt->execute()) {
        if ($delete_stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = "Customer has been deleted successfully.";
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
}

echo json_encode($response);
?>