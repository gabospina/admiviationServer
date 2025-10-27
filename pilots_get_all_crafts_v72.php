<?php
// pilots_get_all_crafts.php (CORRECTED TO PREVENT DUPLICATES)

error_reporting(0);
ini_set('display_errors', 0);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Authentication required."]);
    exit;
}

require_once 'db_connect.php';
global $mysqli;

$company_id = (int)$_SESSION['company_id'];

if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error."]);
    exit;
}

try {
    // This script is specifically for the filter dropdown on pilots.php.
    // The other logic for modals or different pages can be in other files or handled with parameters.
    
    // =========================================================================
    // === THE FIX IS HERE: Use SELECT DISTINCT to get unique craft types    ===
    // =========================================================================
    $sql = "SELECT DISTINCT craft_type 
            FROM crafts 
            WHERE company_id = ? AND alive = 1 AND craft_type IS NOT NULL AND craft_type != ''
            ORDER BY craft_type ASC";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("SQL prepare error: " . $mysqli->error);
    
    $stmt->bind_param("i", $company_id);
    if (!$stmt->execute()) throw new Exception("SQL execute error: " . $stmt->error);
    
    $crafts = [];
    $result = $stmt->get_result();
    
    // The JavaScript expects an array of objects, where each object has a 'craft_type' property.
    while ($row = $result->fetch_assoc()) {
        $crafts[] = [
            'craft_type' => $row['craft_type']
        ];
    }
    $stmt->close();
    
    // The JSON response structure must match what the JavaScript expects ('success' and 'crafts' keys)
    echo json_encode(["success" => true, "crafts" => $crafts]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in pilots_get_all_crafts.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
    exit;
}
?>