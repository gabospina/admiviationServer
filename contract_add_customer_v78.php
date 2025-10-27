<?php
// contract_add_customer.php - REFACTORED AND SECURED

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';
// It's good practice to include permission checks if you have them
// require_once 'login_permissions.php';

$response = ["success" => false, "message" => "An unknown error occurred."];

try {
    // 1. Security: Ensure the user is authenticated and has a company context.
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Authentication required. Please log in again.", 401);
    }
    // Optional: Add a role check if only certain roles can add customers
    // $rolesThatCanAdd = ['manager', 'admin'];
    // if (!userHasRole($rolesThatCanAdd, $mysqli)) {
    //     throw new Exception("You do not have permission to add new customers.", 403);
    // }

    $company_id = (int)$_SESSION['company_id'];
    
    // 2. Input Validation
    $customer_name = trim($_POST["customer_name"] ?? '');
    if (empty($customer_name)) {
        throw new Exception("Customer name cannot be empty.", 400);
    }

    // --- THIS IS THE FIX ---
    // 3. Database Query: Add the 'company_id' column to the INSERT statement.
    $sql = "INSERT INTO customers (company_id, customer_name) VALUES (?, ?)";
    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database query failed to prepare: " . $mysqli->error, 500);
    }
    
    // 4. Bind both the company_id from the session and the customer_name.
    $stmt->bind_param("is", $company_id, $customer_name);

    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Customer '{$customer_name}' added successfully.";
        $response["customer_id"] = $stmt->insert_id;
    } else {
        // Check for a duplicate entry error, if you have a unique index on (company_id, customer_name)
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
    error_log("Error in contract_add_customer.php: " . $e->getMessage());
}

if (isset($mysqli)) {
    $mysqli->close();
}

echo json_encode($response);
?>