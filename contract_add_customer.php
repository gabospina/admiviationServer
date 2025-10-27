<?php
// contract_add_customer.php - FINAL ROBUST VERSION
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) session_start();

// ======================================================================
// === THIS IS THE FIX: Only start a session if one is not already active ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ======================================================================

// ======================================================================
// === DIAGNOSTIC LOGGING: Record what the server receives ===
error_log("AJAX SCRIPT >>> Session ID: [" . session_id() . "] --- Session Token: [" . ($_SESSION['csrf_token'] ?? 'NOT SET') . "]");
error_log("AJAX SCRIPT >>> Posted Token: [" . ($_POST['form_token'] ?? 'NOT POSTED') . "]");
// ======================================================================

header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ["success" => false, "message" => "An unknown error occurred."];

try {
    // === FIX: Check for the new field name 'form_token' ===
    if (!isset($_POST['form_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['form_token'])) {
        // $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $response['new_csrf_token'] = $_SESSION['csrf_token'];
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }
    
    // === CSRF VALIDATION - FIXED VERSION ===
    if (!isset($_POST['form_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['form_token'] ?? '')) {
        // DO NOT regenerate token on failure - this causes the race condition
        $response['new_csrf_token'] = $_SESSION['csrf_token']; // Send current token back
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // 1. Security & Authentication
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Authentication required. Please log in again.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];
    
    // 2. Input Validation
    $customer_name = trim($_POST["customer_name"] ?? '');
    if (empty($customer_name)) {
        throw new Exception("Customer name cannot be empty.", 400);
    }

    // 3. Database Query
    $sql = "INSERT INTO customers (company_id, customer_name) VALUES (?, ?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database query failed to prepare: " . $mysqli->error, 500);
    }
    
    $stmt->bind_param("is", $company_id, $customer_name);

    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Customer '{$customer_name}' added successfully.";
        $response["customer_id"] = $stmt->insert_id;
        
        // Generate a new token for the next form submission to prevent replay attacks
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $response['new_csrf_token'] = $_SESSION['csrf_token'];
    } else {
        // Handle potential duplicate entry
        if ($mysqli->errno === 1062) {
             throw new Exception("A customer with this name already exists for your company.", 409);
        }
        throw new Exception("Database error: Failed to insert customer.", 500);
    }
    $stmt->close();

} catch (Exception $e) {
    $response["message"] = $e->getMessage();
    $httpCode = $e->getCode() > 0 ? $e->getCode() : 500;
    http_response_code($httpCode);
    
    // If a new token hasn't been set yet on an error, set one now
    // if (!isset($response['new_csrf_token'])) {
    //     $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $response['new_csrf_token'] = $_SESSION['csrf_token'];
    }
    
//     error_log("Error in contract_add_customer.php: " . $e->getMessage());
// }

if (isset($mysqli)) {
    $mysqli->close();
}

echo json_encode($response);
?>