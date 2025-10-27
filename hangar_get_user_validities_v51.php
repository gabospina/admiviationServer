<?php
// hangar_get_validities.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'data' => null];

try {
    if (!isset($_SESSION['HeliUser'])) {
        http_response_code(401);
        throw new Exception("Authentication required.");
    }
    $pilot_id = (int)$_SESSION['HeliUser'];

    $stmt = $mysqli->prepare("SELECT * FROM validity WHERE pilot_id = ?");
    if (!$stmt) throw new Exception("DB Prepare Error: " . $mysqli->error);

    $stmt->bind_param("i", $pilot_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $response['data'] = $result->fetch_assoc();
    }
    // If no record exists, 'data' will remain null, which is correct.

    $stmt->close();
    $response['success'] = true;

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>