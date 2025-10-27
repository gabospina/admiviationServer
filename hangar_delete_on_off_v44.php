<?php
// assets/php/delete_on_off.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["HeliUser"])) {
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

include_once "db_connect.php";

$user_id = intval($_POST["id"]);
$on_date = $_POST["on"];
$off_date = $_POST["off"];

$sql = "DELETE FROM user_availability WHERE user_id = ? AND on_date = ? AND off_date = ?";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log("SQL prepare error: " . $mysqli->error);
    echo json_encode(["error" => "Database error: " .  $mysqli->error]);
    exit();
}

$stmt->bind_param("iss", $user_id, $on_date, $off_date);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["error" => "Failed to delete entry: " . $stmt->error]);
}

$stmt->close();
$mysqli->close();

?>