<?php
// daily_manager_create_contract.php - ENHANCED VERSION
require_once 'db_connect.php';
header('Content-Type: application/json');
session_start();
global $mysqli;

// Security checks
if (!isset($_SESSION['HeliUser']) || !isset($_SESSION['company_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied.']);
    exit;
}

require_once 'login_csrf_handler.php';
require_once 'login_permissions.php';

// Validate CSRF token
CSRFHandler::validateToken($_POST['csrf_token'] ?? '');

// Check manager permissions
$allowed_roles = ['manager', 'admin', 'manager pilot', 'admin pilot'];
if (!userHasRole($allowed_roles, $mysqli)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Manager privileges required.']);
    exit;
}

// Input validation
$contract_name = trim($_POST['contract_name'] ?? '');
$customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
$color = trim($_POST['color'] ?? '#000000');

if (empty($contract_name)) {
    echo json_encode(['success' => false, 'message' => 'Contract name is required.']);
    exit;
}

if (!$customer_id || $customer_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Valid customer selection is required.']);
    exit;
}

// Validate color format
if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
    $color = '#000000'; // Default color
}

try {
    // Insert the contract
    $stmt = $mysqli->prepare("INSERT INTO contracts (contract_name, customer_id, color) VALUES (?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Database preparation failed: " . $mysqli->error);
    }
    
    $stmt->bind_param("sis", $contract_name, $customer_id, $color);
    
    if ($stmt->execute()) {
        $new_contract_id = $stmt->insert_id;
        $stmt->close();
        
        // Return success with the new contract ID for redirect
        echo json_encode([
            'success' => true, 
            'message' => 'Contract created successfully!', 
            'contract_id' => $new_contract_id
        ]);
    } else {
        throw new Exception("Database execution failed: " . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log("Contract creation error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>