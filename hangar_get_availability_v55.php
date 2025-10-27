<?php
// hangar_get_availability.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["HeliUser"])) {
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

include_once "db_connect.php";

$user_id = intval($_SESSION["HeliUser"]);

$sql = "SELECT id, on_date, off_date FROM user_availability WHERE user_id = ?";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log("SQL prepare error: " . $mysqli->error);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$availability = [];
while ($row = $result->fetch_assoc()) {
    $availability[] = array(
        'id' => $row['id'], // Include the id in the array
        'on_date' => $row['on_date'],
        'off_date' => $row['off_date']
    );
}

$stmt->close();
$mysqli->close();

echo json_encode($availability);
?>