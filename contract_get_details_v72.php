<?php
// contract_get_details.php
// Fetches all details for a single contract, including assigned crafts and pilots.

if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';

$response = ['success' => false, 'error' => 'An error occurred.'];

try {
    if (!isset($_SESSION['HeliUser'], $_GET['contract_id'])) {
        throw new Exception("Authentication or Contract ID is missing.", 400);
    }
    $contract_id = (int)$_GET['contract_id'];

    $contract_data = [];

    // 1. Get basic contract details
    $stmt = $mysqli->prepare("SELECT contract_name, customer_id, color FROM contracts WHERE id = ?");
    $stmt->bind_param("i", $contract_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception("Contract not found.", 404);
    $contract_data['details'] = $result->fetch_assoc();
    $stmt->close();

    // 2. Get assigned craft IDs
    $stmt = $mysqli->prepare("SELECT craft_id FROM contract_crafts WHERE contract_id = ?");
    $stmt->bind_param("i", $contract_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $craft_ids = [];
    while ($row = $result->fetch_assoc()) {
        $craft_ids[] = $row['craft_id'];
    }
    $contract_data['assigned_craft_ids'] = $craft_ids;
    $stmt->close();
    
    // 3. Get assigned pilot IDs
    $stmt = $mysqli->prepare("SELECT user_id FROM contract_pilots WHERE contract_id = ?");
    $stmt->bind_param("i", $contract_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $pilot_ids = [];
    while ($row = $result->fetch_assoc()) {
        $pilot_ids[] = $row['user_id'];
    }
    $contract_data['assigned_pilot_ids'] = $pilot_ids;
    $stmt->close();

    $response['success'] = true;
    $response['data'] = $contract_data;

} catch (Exception $e) {
    http_response_code(500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>