<?php
// contract_get_all_crafts.php - CORRECTED

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'crafts' => [], 'error' => null];

try {
    if (!isset($_SESSION['company_id'])) {
        throw new Exception("Company context not set.", 401);
    }
    $companyId = (int)$_SESSION['company_id'];

    // === THE FIX ===
    // Select all the necessary columns for EACH craft, not just the distinct types.
    // Order by type and then by registration for a clean, sorted list.
    $sql = "SELECT id, craft_type, registration, tod, alive FROM crafts WHERE company_id = ? ORDER BY craft_type ASC, registration ASC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("DB prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    // Use fetch_all to get all rows into the array
    $response['crafts'] = $result->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;
    
    $stmt->close();

} catch (Exception $e) {
    // For debugging, it's helpful to see the error message.
    // In production, you might want a more generic message.
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>