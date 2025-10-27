<?php
// craft_add_craft.php
session_start();
// --- ADD CSRF PROTECTION ---
require_once 'login_csrf_handler.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once "db_connect.php";

header('Content-Type: application/json');

// --- CSRF VALIDATION ---
if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(["success" => false, "message" => "Invalid security token. Please refresh the page."]);
    exit();
}

// === THE FIX IS HERE ===
// We now check for the `$mysqli` variable directly, because that is
// the variable created by your db_connect.php file.
// We no longer use or reference `$conn`.
if (!isset($mysqli) || $mysqli->connect_error) {
    // Use a more specific error message for clarity
    echo json_encode(["success" => false, "message" => "Database connection object (\$mysqli) not found or failed to connect."]);
    exit();
}

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
    echo json_encode(["success" => false, "message" => "Authentication error. Please log in again."]);
    exit();
}

$company_id = (int)$_SESSION["company_id"];

$mysqli->set_charset("utf8");

// Get and validate POST data
$craft_type = isset($_POST['craft']) ? trim($_POST['craft']) : '';
$registration = isset($_POST['registration']) ? trim($_POST['registration']) : '';
$tod = isset($_POST['tod']) ? trim($_POST['tod']) : '';
$alive = isset($_POST['alive']) ? (int)$_POST['alive'] : null;

if (empty($craft_type) || empty($registration) || empty($tod) || $alive === null) {
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit();
}

$sql = "INSERT INTO crafts (craft_type, registration, tod, alive, company_id) VALUES (?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "SQL Prepare Error: " . $mysqli->error]);
    exit();
}

$stmt->bind_param("sssii", $craft_type, $registration, $tod, $alive, $company_id);

if (!$stmt->execute()) {
    if ($stmt->errno == 1062) {
         echo json_encode(["success" => false, "message" => "Database Error: A craft with that registration already exists."]);
    } else {
         echo json_encode(["success" => false, "message" => "Database Execute Error: " . $stmt->error]);
    }
    $stmt->close();
    $mysqli->close();
    exit();
}

$craft_id = $mysqli->insert_id;

$stmt->close();
$mysqli->close();

echo json_encode(["success" => true, "message" => "Craft added successfully!", "craft_id" => $craft_id]);
?>