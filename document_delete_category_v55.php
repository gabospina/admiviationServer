<?php
// document_delete_category.php

session_start();

ini_set('display_errors', 0); // Production: Hide errors from user
ini_set('log_errors', 1);    // Log errors to file
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- Includes ---
require_once 'db_connect.php';
require_once 'login_csrf_handler.php'; // For token validation
require_once 'login_permissions.php';  // For permission checks

// --- Default Response Structure ---
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    // 1. Check for proper request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.", 405);
    }
    
    // 2. Security: Validate CSRF token and user permissions
    CSRFHandler::validateToken($_POST['csrf_token'] ?? '');
    
    // Define which roles can manage categories. This is a critical security step.
    $rolesThatCanManage = ['training_manager pilot', 'manager pilot', 'manager', 'admin', 'admin pilot'];
    if (!userHasRole($rolesThatCanManage, $mysqli)) {
        throw new Exception("You do not have permission to delete categories.", 403);
    }
    
    // 3. Validate the incoming category ID
    $categoryId = isset($_POST['categoryId']) ? filter_var($_POST['categoryId'], FILTER_VALIDATE_INT) : null;
    if (!$categoryId) {
        throw new Exception('Invalid or missing category ID.', 400);
    }

    // --- Database Operations within a Transaction ---
    $mysqli->begin_transaction();

    // Step A: Disassociate all documents from this category
    $stmtUpdate = $mysqli->prepare("UPDATE documents SET category_id = NULL WHERE category_id = ?");
    if (!$stmtUpdate) { throw new Exception("DB Prepare Error (Update Docs): " . $mysqli->error); }
    $stmtUpdate->bind_param("i", $categoryId);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    // Step B: Delete the now-empty category
    $stmtDelete = $mysqli->prepare("DELETE FROM document_categories WHERE id = ?");
    if (!$stmtDelete) { throw new Exception("DB Prepare Error (Delete Category): " . $mysqli->error); }
    $stmtDelete->bind_param("i", $categoryId);
    $stmtDelete->execute();

    // Check if a row was actually deleted
    if ($stmtDelete->affected_rows === 0) {
        throw new Exception("Category with ID $categoryId not found.", 404);
    }
    $stmtDelete->close();

    // If both operations succeed, commit the transaction
    $mysqli->commit();

    $response['success'] = true;
    $response['message'] = 'Category deleted successfully. Documents are now uncategorized.';

} catch (Exception $e) {
    // If anything fails, roll back the transaction
    if ($mysqli->in_transaction) {
        $mysqli->rollback();
    }
    // Set response details from the exception
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $response['message'] = $e->getMessage();
    error_log("delete_category: Exception - " . $e->getMessage());
}

// Close connection and send response
if (isset($mysqli) && $mysqli) {
    $mysqli->close();
}
echo json_encode($response);
exit;
?>