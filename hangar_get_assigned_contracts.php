<?php
// hangar_get_assigned_contracts.php

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

    // 2. Database Query: Join with the contracts table and use the CORRECT 'user_id' column name
    $stmt = $mysqli->prepare("
        SELECT c.contract_name 
        FROM contract_pilots cp
        JOIN contracts c ON cp.contract_id = c.id
        WHERE cp.user_id = ? 
        ORDER BY c.contract_name ASC
    ");
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