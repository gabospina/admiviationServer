<?php
// daily_manager_create_contract.php - ENHANCED VERSION
require_once 'db_connect.php';
header('Content-Type: application/json');
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

global $mysqli;

error_log("=== CONTRACT CREATION REQUEST ===");
error_log("POST Data: " . print_r($_POST, true));
error_log("SESSION Data: " . print_r($_SESSION, true));

// Security checks
if (!isset($_SESSION['HeliUser']) || !isset($_SESSION['company_id'])) {
    error_log("Security check failed: No session user or company_id");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied.']);
    exit;
}

// REMOVED: // REMOVED: require_once 'login_csrf_handler.php';
require_once 'login_permissions.php';

// Validate CSRF token
// REMOVED: CSRFHandler::validateToken

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