<?php
// hangar_get_assigned_crafts.php

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'data' => [], 'error' => 'An unknown error occurred.'];

try {
    // 1. Security: Ensure user is logged in
    if (!isset($_SESSION['HeliUser'])) {
        throw new Exception("User not authenticated.", 401);
    }
    $userId = (int)$_SESSION['HeliUser'];

    // 2. Database Query: Use the CORRECT 'user_id' column name
    $stmt = $mysqli->prepare("SELECT craft_type, position FROM pilot_craft_type WHERE user_id = ? ORDER BY craft_type ASC");
    if (!$stmt) {
        throw new Exception("Database query failed to prepare.");
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // 3. Fetch all results
    $response['data'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $response['success'] = true;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
}

echo json_encode($response);
?>