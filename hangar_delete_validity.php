<?php
// hangar_delete_validity.php

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'login_csrf_handler.php'; // Assuming you have this for security

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

try {
    // 1. Security First: Validate CSRF and session
    CSRFHandler::validateToken($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['HeliUser'])) {
        throw new Exception("Authentication required.", 401);
    }
    
    // The pilot ID should be the logged-in user to prevent them from deleting others' data.
    $pilotId = (int)$_SESSION['HeliUser'];

    // 2. Validate Input
    $validityField = trim($_POST['field'] ?? '');
    if (empty($validityField)) {
        throw new Exception("Invalid validity field specified.", 400);
    }

    // 3. Database Deletion
    // We target the specific user and the specific validity key to delete.
    $stmt = $mysqli->prepare("DELETE FROM user_licences_validity WHERE user_id = ? AND validity_key = ?");
    if (!$stmt) {
        throw new Exception("Database prepare statement failed.");
    }

    $stmt->bind_param("is", $pilotId, $validityField);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete the validity record from the database.");
    }

    // Check if any row was actually deleted
    if ($stmt->affected_rows > 0) {
        $response['success'] = true;
        $response['message'] = 'Validity record removed successfully.';
    } else {
        // This can happen if the record was already gone. We can still consider it a success.
        $response['success'] = true;
        $response['message'] = 'Validity record was not found, but is now clear.';
    }

    $stmt->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
}

echo json_encode($response);
