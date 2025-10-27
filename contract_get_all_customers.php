<?php
// contract_get_all_customers.php - REFACTORED AND SECURED

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'customers' => []];

try {
    // 1. Security: Ensure user is authenticated and has a company context.
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Authentication required.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];

    // --- THIS IS THE FIX ---
    // 2. Database Query: Add the critical "WHERE company_id = ?" clause.
    // This ensures that we only select customers belonging to the logged-in company.
    $sql = "SELECT id, customer_name FROM customers WHERE company_id = ? ORDER BY customer_name ASC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database query failed to prepare: " . $mysqli->error);
    }

    // 3. Bind the company_id from the session to the query.
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    
    // 4. Fetch all results into the response.
    $response['customers'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;
    $stmt->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $httpCode = $e->getCode() > 0 ? $e->getCode() : 500;
    http_response_code($httpCode);
    // Log the detailed error for the administrator
    error_log("Error in contract_get_all_customers.php: " . $e->getMessage());
}

if (isset($mysqli)) {
    $mysqli->close();
}

echo json_encode($response);
?>