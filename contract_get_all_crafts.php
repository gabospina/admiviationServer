<?php
// contract_get_all_crafts.php 76 - FINAL VERSION with Custom Sorting

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'crafts' => [], 'error' => null];

try {
    if (!isset($_SESSION['company_id'])) {
        throw new Exception("Company context not set.", 401);
    }
    $companyId = (int)$_SESSION['company_id'];

    // === THIS IS THE ONLY CHANGE ===
    // The query now sorts by our new `display_order` column first.
    // `registration ASC` is used as a secondary sort for any items
    // that have the same order value (e.g., all new items with order 0).
    $sql = "SELECT id, craft_type, registration, tod, alive 
            FROM crafts 
            WHERE company_id = ? 
            ORDER BY display_order ASC, registration ASC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("DB prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $response['crafts'] = $result->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;
    
    $stmt->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code(500); // Send a proper server error status
}

echo json_encode($response);
?>