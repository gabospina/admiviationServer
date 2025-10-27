<?php
session_start();

include_once "db_connect.php";

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json'); // Set the correct Content-Type header

// Check if the user is logged in and retrieve pilot_id from session
if (!isset($_SESSION["HeliUser"])) {
    $response = ["success" => false, "message" => "User not logged in."];
    echo json_encode($response);
    exit;
}

$pilot_id = (int)$_SESSION["HeliUser"]; // Get pilotId from session and cast to int

// Fetch craft types for the pilot
$sql = "SELECT id, craft_type, position FROM pilot_craft_type WHERE pilot_id = ?";
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

if ($result->num_rows > 0) {
    $craftTypes = [];
    while ($row = $result->fetch_assoc()) {
        $craftTypes[] = [
            "id" => $row['id'],
            "craft_type" => $row['craft_type'],
            "position" => $row['position']
        ];
    }
    $response = ["success" => true, "craftTypes" => $craftTypes];
} else {
    $response = ["success" => true, "message" => "No craft types found for this pilot.", "craftTypes" => []];
}

$stmt->close();
$mysqli->close();

echo json_encode($response);
exit;
?>