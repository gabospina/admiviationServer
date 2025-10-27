<?php
// pilots_get_all_crafts.php (CORRECTED for Contract Modal)

if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION["HeliUser"], $_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authentication required."]);
    exit;
}

require_once 'db_connect.php';
global $mysqli;

$company_id = (int)$_SESSION['company_id'];

if (!$mysqli) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error."]);
    exit;
}

try {
    // --- THIS IS THE FIX ---
    // Select all the columns we need for the display.
    $sql = "SELECT id, craft_type, registration 
            FROM crafts 
            WHERE company_id = ? AND alive = 1 AND registration IS NOT NULL
            ORDER BY registration ASC, craft_type ASC";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("SQL prepare error: " . $mysqli->error);
    
    $stmt->bind_param("i", $company_id);
    if (!$stmt->execute()) throw new Exception("SQL execute error: " . $stmt->error);
    
    // fetch_all() is a clean way to get all results into an array.
    $crafts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // The JSON response must match what the JavaScript expects ('success' and 'crafts' keys).
    echo json_encode(["success" => true, "crafts" => $crafts]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in pilots_get_all_crafts.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Server error."]);
}
?>