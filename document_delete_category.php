<?php
// document_delete_category.php

// Start session at the VERY TOP
if (session_status() == PHP_SESSION_NONE) {
    session_start();

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

}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// --- Includes ---
require_once 'db_connect.php';
// REMOVED: // REMOVED: require_once 'login_csrf_handler.php';
require_once 'login_permissions.php';

// --- Default Response Structure ---
$response = ['success' => false, 'message' => 'An unknown error occurred.', 'new_csrf_token' => $_SESSION['csrf_token']];

// === FIX: ADD COMPANY_ID CHECK ===
if (!isset($_SESSION['company_id'])) {
    $response['message'] = 'Company information not found.';
    http_response_code(401);
    echo json_encode($response);
    exit;
}
$company_id = (int)$_SESSION['company_id'];

try {
    // 1. Check for proper request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method.", 405);
    }
    
    // 2. Security: Validate CSRF token and user permissions
    if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }
    
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

    // === FIX: ADD COMPANY_ID TO ALL QUERIES ===

    // Step A: Disassociate all documents from this category (only for this company)
    $stmtUpdate = $mysqli->prepare("UPDATE documents SET category_id = NULL WHERE category_id = ? AND company_id = ?");
    if (!$stmtUpdate) { throw new Exception("DB Prepare Error (Update Docs): " . $mysqli->error); }
    $stmtUpdate->bind_param("ii", $categoryId, $company_id);
    $stmtUpdate->execute();
    $stmtUpdate->close();

    // Step B: Delete the category (only if it belongs to this company)
    $stmtDelete = $mysqli->prepare("DELETE FROM document_categories WHERE id = ? AND company_id = ?");
    if (!$stmtDelete) { throw new Exception("DB Prepare Error (Delete Category): " . $mysqli->error); }
    $stmtDelete->bind_param("ii", $categoryId, $company_id);
    $stmtDelete->execute();

    // Check if a row was actually deleted
    if ($stmtDelete->affected_rows === 0) {
        throw new Exception("Category with ID $categoryId not found or access denied.", 404);
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
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $response['message'] = $e->getMessage();
    error_log("delete_category: Exception - " . $e->getMessage());
}

if (isset($mysqli) && $mysqli) {
    $mysqli->close();
}
echo json_encode($response);
exit;
?>