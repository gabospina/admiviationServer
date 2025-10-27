<?php
// hangar_get_pilot_craft_types.php (FINAL, CORRECTED & SECURE VERSION)

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = [
    'success' => false,
    'craftTypes' => [],
    'message' => 'An unknown error occurred.'
];

try {
    // 1. Security: Check if the user is logged in
    if (!isset($_SESSION['HeliUser'])) {
        throw new Exception("User not authenticated. Please log in again.", 401);
    }
    // Use the session user ID, ensuring a user can only see their own data
    $userId = (int)$_SESSION['HeliUser'];

    // 2. Database Query: Use the corrected 'user_id' column name
    $stmt = $mysqli->prepare("SELECT id, craft_type, position FROM pilot_craft_type WHERE user_id = ? ORDER BY craft_type ASC");
    if (!$stmt) {
        // This error is for the developer, not the user
        throw new Exception("Database query failed to prepare: " . $mysqli->error);
    }
    
    $stmt->bind_param("i", $userId);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to retrieve craft types from the database.");
    }

    $result = $stmt->get_result();
    
    // 3. Fetch all results into an array
    // fetch_all() is more efficient than looping in PHP
    $craftTypes = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // 4. Prepare the successful response
    $response['success'] = true;
    $response['craftTypes'] = $craftTypes;
    // The message is useful for debugging but not shown to the user on success
    $response['message'] = 'Craft types loaded successfully.';

} catch (Exception $e) {
    // If any error occurs, set the response message and appropriate HTTP code
    $response['message'] = $e->getMessage();
    $httpCode = $e->getCode();
    if ($httpCode >= 400 && $httpCode < 600) {
        http_response_code($httpCode);
    } else {
        http_response_code(500); // Internal Server Error as a default
    }
}

echo json_encode($response);
?>