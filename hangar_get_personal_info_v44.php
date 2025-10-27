<?php
// get_personal_info.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["HeliUser"])) {
    echo json_encode(["error" => "Not logged in"]);
    exit();
}

include_once "db_connect.php";

$user_id = intval($_SESSION["HeliUser"]);

$sql = "SELECT firstname, lastname, username, user_nationality, nal_license, for_license, email, phone, phonetwo FROM users WHERE id = ?";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    error_log("SQL prepare error: " . $mysqli->error);
    echo json_encode(["error" => "Database error: " . $mysqli->error]);
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    echo json_encode($user_data);
} else {
    echo json_encode(["error" => "User not found"]);
}

$stmt->close();
$mysqli->close();

?>