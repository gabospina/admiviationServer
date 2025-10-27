<?php
session_start();

include_once "db_connect.php";

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Check if the user is logged in
if (!isset($_SESSION["HeliUser"])) {
    $response = ["success" => false, "message" => "User not logged in."];
    echo json_encode($response);
    exit;
}

$pilot_id = (int)$_SESSION["HeliUser"];

// Fetch contracts associated with the pilot
$sql = "SELECT c.id AS contract_id, c.contract_name, cust.customer_name
        FROM contract_pilots AS ct
        JOIN contracts AS c ON ct.contract_id = c.id
        JOIN customers AS cust ON c.customer_id = cust.id
        WHERE ct.pilot_id = ?";
$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    $response = ["success" => false, "message" => "Prepare failed: " . htmlspecialchars($mysqli->error)];
    echo json_encode($response);
    exit;
}

$stmt->bind_param("i", $pilot_id);

if (!$stmt->execute()) {
    $response = ["success" => false, "message" => "Execute failed: " . htmlspecialchars($stmt->error)];
    echo json_encode($response);
    exit;
}

$result = $stmt->get_result();
$contracts = [];

while ($row = $result->fetch_assoc()) {
    $contracts[] = [
        "id" => $row['contract_id'],
        "contract_name" => $row['contract_name'],
        "customer_name" => $row['customer_name']
    ];
}

$response = ["success" => true, "contracts" => $contracts];
echo json_encode($response);

$stmt->close();
$mysqli->close();
?>