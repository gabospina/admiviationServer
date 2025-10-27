<?php
// hangar_get_assigned_contracts.php
require_once 'db_connect.php';
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['HeliUser'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}
$pilot_id = (int)$_SESSION['HeliUser'];

// Using 'contract_pilots' and 'contracts' tables
$sql = "SELECT c.contract_name 
        FROM contract_pilots cp
        JOIN contracts c ON cp.contract_id = c.id
        WHERE cp.pilot_id = ?
        ORDER BY c.contract_name";
        
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $pilot_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'data' => $data]);
?>