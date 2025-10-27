<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include_once "db_connect.php";

header('Content-Type: application/json');

try {
    // Verify request method
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method");
    }

    // Verify user is logged in
    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception("Authentication required");
    }

    // Get and validate customer ID
    if (!isset($_POST['customer_id'])) {
        throw new Exception("Customer ID not provided");
    }

    $customer_id = intval($_POST['customer_id']);
    if ($customer_id <= 0) {
        throw new Exception("Invalid customer ID");
    }

    // Check database connection
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error");
    }

    // Prepare and execute query
    $sql = "SELECT COUNT(*) as contract_count FROM contracts WHERE customer_id = ?";
    if (!($stmt = $mysqli->prepare($sql))) {
        throw new Exception("Prepare failed: " . $mysqli->error);
    }

    if (!$stmt->bind_param("i", $customer_id)) {
        throw new Exception("Binding parameters failed: " . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Getting result set failed: " . $stmt->error);
    }

    $row = $result->fetch_assoc();
    if (!$row) {
        throw new Exception("No results returned");
    }

    $stmt->close();

    // Return success response
    echo json_encode([
        "success" => true,
        "has_contracts" => ($row['contract_count'] > 0),
        "contract_count" => $row['contract_count'],
        "message" => "Check completed successfully"
    ]);

} catch (Exception $e) {
    // Log the error
    error_log("Error in check_customer_contracts.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        "success" => false,
        "message" => "Error checking contracts: " . $e->getMessage(),
        "debug_info" => [
            "customer_id" => isset($customer_id) ? $customer_id : null,
            "session" => isset($_SESSION) ? $_SESSION : null
        ]
    ]);
}
?>