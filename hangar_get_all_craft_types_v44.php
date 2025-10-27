<?php
session_start();

include_once "db_connect.php";

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Fetch distinct craft types
$sql = "SELECT DISTINCT craft_type FROM crafts"; // Use DISTINCT to get unique craft types
$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    $response = ["success" => false, "message" => "Failed to prepare statement: " . $mysqli->error];
    echo json_encode($response);
    exit;
}

if (!$stmt->execute()) {
    $response = ["success" => false, "message" => "Failed to execute query: " . $stmt->error];
    echo json_encode($response);
    exit;
}

$result = $stmt->get_result();
$craftTypes = [];

while ($row = $result->fetch_assoc()) {
    $craftTypes[] = [
        "craft_type" => $row['craft_type']
    ];
}

$response = ["success" => true, "craftTypes" => $craftTypes];
echo json_encode($response);

$stmt->close();
$mysqli->close();
?>