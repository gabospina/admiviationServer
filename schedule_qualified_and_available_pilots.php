<?php
/**
 * File: schedule_qualified_and_available_pilots.php (NEW COMPREHENSIVE VERSION)
 * Fetches pilots who are qualified by:
 * 1. Aircraft Type
 * 2. Position (PIC/SIC)
 * 3. Contract Assignment
 * 4. On-Duty Availability for a specific date
 * Returns separate, pre-filtered lists for PIC and SIC dropdowns.
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
require_once "db_connect.php";

$response = [
    'success' => false,
    'users_pic' => [], // Renamed for clarity
    'users_sic' => []  // Renamed for clarity
];

try {
    // --- 1. Validate Inputs ---
    if (!isset($_SESSION['company_id'], $_GET['date'], $_GET['craft_id'])) {
        throw new Exception('Missing required parameters.', 400);
    }
    $company_id = (int)$_SESSION['company_id'];
    $target_date = $_GET['date'];
    $craft_id = (int)$_GET['craft_id'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) {
        throw new Exception('Invalid date format.', 400);
    }

    // --- 2. Get Aircraft's Type and Contract ID ---
    $stmt_craft = $mysqli->prepare("
        SELECT cr.craft_type, cc.contract_id 
        FROM crafts cr
        LEFT JOIN contract_crafts cc ON cr.id = cc.craft_id
        WHERE cr.id = ? AND cr.company_id = ?
    ");
    $stmt_craft->bind_param("ii", $craft_id, $company_id);
    $stmt_craft->execute();
    $craft_info = $stmt_craft->get_result()->fetch_assoc();
    $stmt_craft->close();

    if (!$craft_info) {
        // If craft doesn't exist, return empty lists immediately.
        $response['success'] = true;
        echo json_encode($response);
        exit;
    }
    $craft_type = $craft_info['craft_type'];
    $contract_id = $craft_info['contract_id']; // This can be NULL if the craft has no contract

    // --- 3. Build and Execute the Master Query ---
    // This single query joins all tables to enforce all rules at once.
    $sql = "SELECT DISTINCT
                u.id,
                CONCAT(u.lastname, ', ', u.firstname) as display_name,
                pct.position
            FROM users u
            -- Rule 1 & 2: Must be qualified for the aircraft type and a position
            INNER JOIN pilot_craft_type pct ON u.id = pct.user_id AND pct.craft_type = ?
            -- Rule 4: Must be ON DUTY for the target date
            INNER JOIN user_availability ua ON u.id = ua.user_id AND ? BETWEEN ua.on_date AND ua.off_date
            WHERE
                u.company_id = ? AND u.is_active = 1";

    $params = [$craft_type, $target_date, $company_id];
    $types = "ssi";

    // Rule 3: If the aircraft is assigned to a contract, the pilot must also be.
    if ($contract_id) {
        $sql .= " AND EXISTS (SELECT 1 FROM contract_pilots cp WHERE cp.user_id = u.id AND cp.contract_id = ?)";
        $params[] = $contract_id;
        $types .= "i";
    }
    
    $sql .= " ORDER BY display_name ASC";

    $stmt_pilots = $mysqli->prepare($sql);
    if (!$stmt_pilots) throw new Exception("DB Prepare Error: " . $mysqli->error);
    
    $stmt_pilots->bind_param($types, ...$params);
    $stmt_pilots->execute();
    $all_qualified_pilots = $stmt_pilots->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pilots->close();

    // --- 4. Process Results into PIC and SIC lists ---
    // Rule: A PIC can also fly as an SIC, but an SIC cannot fly as a PIC.
    foreach ($all_qualified_pilots as $pilot) {
        $position = strtoupper($pilot['position']);
        
        // A pilot qualified as PIC can be added to the PIC list.
        if ($position === 'PIC') {
            $response['users_pic'][] = $pilot;
        }
        
        // A pilot qualified as either PIC or SIC can be added to the SIC list.
        if ($position === 'PIC' || $position === 'SIC') {
            $response['users_sic'][] = $pilot;
        }
    }

    $response['success'] = true;

} catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    error_log("Error in " . basename(__FILE__) . ": " . $e->getMessage());
    $response['error'] = "Server Error: Could not load pilot list.";
}

echo json_encode($response);
?>