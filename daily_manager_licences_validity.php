<?php
// manager_get_licence_fields.php

session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';

$response = ['success' => false, 'fields' => [], 'message' => 'An unknown error occurred.'];

try {
    if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
        throw new Exception("Authentication required.", 401);
    }
    $company_id = (int)$_SESSION['company_id'];

    // Query the new table using the correct name you chose
    $sql = "SELECT id, field_key, field_label, display_order
            FROM user_company_licence_fields
            WHERE company_id = ?
            ORDER BY display_order, field_label ASC";
            
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("DB Prepare Error: " . $mysqli->error);

    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['fields'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $response['success'] = true;
    $response['message'] = 'Fields retrieved successfully.';

} catch (Exception $e) {
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>