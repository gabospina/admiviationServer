<?php
// craft_get_fleet.php - REFACTORED to respect display_order

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'crafts' => [], 'error' => null];

try {
    // 1. Security: Ensure user and company context are set in the session
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("User not authenticated or company context is missing.", 401);
    }
    $companyId = (int)$_SESSION['company_id'];

    // 2. Database Query: Select all necessary columns for the fleet list
    // --- THIS IS THE FIX ---
    // The query now sorts by the custom `display_order` set by the manager.
    // `registration ASC` is used as a secondary sort for any items
    // that have the same order value (e.g., all new items with order 0).
    $sql = "SELECT id, craft_type, registration, alive 
            FROM crafts 
            WHERE company_id = ? 
            ORDER BY display_order ASC, registration ASC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database query failed to prepare: " . $mysqli->error);
    }

    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    
    // 3. Fetch all results into the response
    $response['crafts'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;
    $stmt->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $httpCode = $e->getCode() > 0 ? $e->getCode() : 500;
    http_response_code($httpCode);
}

echo json_encode($response);
?>