<?php
/**
 * File: daily_manager_get_availability.php
 * Fetches all duty periods for a specific user ID, for manager use.
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

require_once 'db_connect.php';
require_once 'login_permissions.php'; // Or your permissions handler

$response = ['success' => false, 'availability' => []];

try {
    // --- Security & Validation ---
    $rolesThatCanView = ['manager', 'admin', 'manager pilot', 'admin pilot'];
    if (!isset($_SESSION["HeliUser"]) || !userHasRole($rolesThatCanView, $mysqli)) {
        throw new Exception("Permission Denied.", 403);
    }
    
    if (!isset($_GET['user_id'])) {
        throw new Exception("A User ID is required to fetch a schedule.", 400);
    }
    $user_id_to_fetch = (int)$_GET['user_id'];
    if ($user_id_to_fetch <= 0) {
        throw new Exception("Invalid User ID provided.", 400);
    }

    // --- Database Query ---
    // We select the primary key 'id' of the availability record itself, which is crucial for deleting.
    $sql = "SELECT id, on_date, off_date 
            FROM user_availability 
            WHERE user_id = ? 
            ORDER BY on_date DESC";
            
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("Database prepare failed: " . $mysqli->error);

    $stmt->bind_param("i", $user_id_to_fetch);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $response['availability'] = $result->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['error'] = $e->getMessage();
    error_log("Error in " . basename(__FILE__) . ": " . $e->getMessage());
}

echo json_encode($response);
?>