<?php
/**
 * File: hangar_get_availability.php (FINAL, ROBUST VERSION)
 * Fetches all duty periods for the currently logged-in pilot.
 * This version uses a standard JSON response format for better error handling
 * and consistency with other scripts.
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set headers and initialize the standard response structure
header('Content-Type: application/json');
$response = ['success' => false, 'data' => []];

try {
    // --- Security: Ensure a user is logged in ---
    if (!isset($_SESSION["HeliUser"])) {
        // Use an Exception for clean, centralized error handling
        throw new Exception("Authentication Required.", 401);
    }

    // Include database connection inside the try block
    require_once 'db_connect.php';

    // Get the user ID directly from their own session
    $user_id = (int)$_SESSION["HeliUser"];

    // --- Database Query ---
    // The query is the same as yours, but ordering by on_date is better for display
    $sql = "SELECT id, on_date, off_date 
            FROM user_availability 
            WHERE user_id = ? 
            ORDER BY on_date ASC"; // Order by start date
            
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        // Throwing an exception gives a more detailed error log
        throw new Exception("Database prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Database execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    // Populate the 'data' key of the response array
    $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
    // Set success to true only after data is successfully fetched
    $response['success'] = true;

    $stmt->close();
    $mysqli->close();

} catch (Exception $e) {
    // This block catches any error (authentication, DB connection, query failure)
    // and formats a proper error response.
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
    // Log the specific error for your records
    error_log("Error in " . basename(__FILE__) . ": " . $e->getMessage());
}

// Always output the final $response array as JSON
echo json_encode($response);
?>