<?php
include_once "db_connect.php";

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Function to sanitize data (prevent SQL injection and other issues)
function sanitizeString($str) {
    global $mysqli;
    $str = strip_tags($str);
    $str = htmlentities($str);
    $str = stripslashes($str);
    return $mysqli->real_escape_string($str);  // MOST IMPORTANT: Escape for MySQL
}

// Log POST data for debugging
error_log("POST Data: " . print_r($_POST, true));

// Get Data
$customer_name = isset($_POST["customer_name"]) ? sanitizeString($_POST["customer_name"]) : null;

// Initialize response array
$response = ["success" => false, "message" => ""];

// Check for required fields
if (empty($customer_name)) {
    $response["message"] = "Customer name cannot be empty.";
    echo json_encode($response);
    exit;
}

// Use prepared statement to prevent SQL injection
$sql = "INSERT INTO customers (customer_name) VALUES (?)";
$stmt = $mysqli->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $customer_name);

    if ($stmt->execute()) {
        $response["success"] = true;
        $response["message"] = "Customer added successfully.";
        $response["customer_id"] = $stmt->insert_id; // Include the new customer ID in the response
    } else {
        // Log the error
        error_log("Error inserting customer: " . $stmt->error);
        $response["message"] = "Database error: " . $stmt->error;
    }

    $stmt->close();
} else {
    // Log the error
    error_log("Error preparing statement: " . $mysqli->error);
    $response["message"] = "Statement preparation error: " . $mysqli->error;
}

echo json_encode($response);  // Return JSON response
$mysqli->close();
?>