<?php
// get_all_crafts.php
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
$distinct_types_only = isset($_GET['distinct']) && $_GET['distinct'] === 'true';
$for_contract_modal = isset($_GET['for_contract_modal']) && $_GET['for_contract_modal'] === 'true';

if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database connection error."]);
    exit;
}

try {
    if ($for_contract_modal) {
        // Format specifically for contract modal dropdown
        $sql = "SELECT id, CONCAT(craft_type, ' - ', registration) AS display_text 
                FROM crafts 
                WHERE company_id = ? AND alive = 1 
                ORDER BY craft_type, registration";
        
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new Exception("SQL prepare error: " . $mysqli->error);
        
        $stmt->bind_param("i", $company_id);
        if (!$stmt->execute()) throw new Exception("SQL execute error: " . $stmt->error);
        
        $crafts = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $crafts[] = [
                'id' => (int)$row['id'],
                'text' => $row['display_text']
            ];
        }
        $stmt->close();
        
        echo json_encode(["success" => true, "data" => $crafts]);
        
    } elseif ($distinct_types_only) {
        // For trainingfunctions.js (craftAndColors and simple dropdowns)
        $sql = "SELECT DISTINCT craft_type FROM crafts 
                WHERE company_id = ? AND alive = 1 
                ORDER BY craft_type ASC";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new Exception("SQL prepare error: " . $mysqli->error);

        $stmt->bind_param("i", $company_id);
        if (!$stmt->execute()) throw new Exception("SQL execute error: " . $stmt->error);
        
        $craft_type_strings = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $craft_type_strings[] = $row['craft_type'];
        }
        $stmt->close();
        echo json_encode($craft_type_strings); // Direct array of strings
        
    } else {
        // Default full details response for craftfunctions.js
        $sql = "SELECT id, craft_type, registration, tod, alive 
                FROM crafts 
                WHERE company_id = ? AND alive = 1 
                ORDER BY craft_type ASC, registration ASC";

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new Exception("SQL prepare error: " . $mysqli->error);
        
        $stmt->bind_param("i", $company_id);
        if (!$stmt->execute()) throw new Exception("SQL execute error: " . $stmt->error);
        
        $crafts = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $crafts[] = [
                'id' => (int)$row['id'],
                'craft_type' => $row['craft_type'],
                'registration' => $row['registration'],
                'tod' => $row['tod'],
                'alive' => (int)$row['alive']
            ];
        }
        $stmt->close();
        
        echo json_encode(["success" => true, "crafts" => $crafts]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_all_crafts.php: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
    exit;
}
?>