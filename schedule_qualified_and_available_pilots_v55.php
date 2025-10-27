<?php
// schedule_qualified_and_available_pilots.php (Advanced Version)

if (session_status() == PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
require_once "db_connect.php";

date_default_timezone_set('UTC');

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
    if (!isset($mysqli)) throw new Exception('Database connection failed');

    // --- 2. Get Craft and Contract Info ---
    // We need to find which contract this specific aircraft belongs to.
    $contract_info_stmt = $mysqli->prepare("
        SELECT c.craft_type, c.registration, cc.contract_id 
        FROM crafts c
        LEFT JOIN contract_crafts cc ON c.id = cc.craft_id
        WHERE c.id = ? AND c.company_id = ?
    ");
    $contract_info_stmt->bind_param("ii", $craft_id, $company_id);
    $contract_info_stmt->execute();
    $craft_result = $contract_info_stmt->get_result();
    if ($craft_result->num_rows === 0) {
        echo json_encode(['success' => true, 'pilots_pic' => [], 'pilots_sic' => []]);
        exit;
    }
    $craft_data = $craft_result->fetch_assoc();
    $contract_info_stmt->close();
    
    $craft_type = $craft_data['craft_type'];
    $registration = $craft_data['registration'];
    $contract_id = $craft_data['contract_id']; // This is the key piece of new information

    // --- 3. Build the Master Query for ALL Qualified & Available Pilots ---
    // This query now handles all your rules: craft type, contract, and availability.
    $base_query = "
        SELECT DISTINCT
            u.id,
            CONCAT(u.lastname, ', ', u.firstname) as display_name,
            pct.position
        FROM users u
        INNER JOIN pilot_craft_type pct ON u.id = pct.user_id
        WHERE
            u.company_id = ? AND u.is_active = 1
            AND pct.craft_type = ?
            AND NOT EXISTS (
                SELECT 1 FROM user_availability ua
                WHERE ua.user_id = u.id AND ? BETWEEN ua.on_date AND ua.off_date
            )
    ";

    // If the aircraft is linked to a contract, add the contract check.
    $params = [$company_id, $craft_type, $target_date];
    $types = "iss";
    if ($contract_id) {
        $base_query .= " AND EXISTS (
            SELECT 1 FROM contract_pilots cp
            WHERE cp.user_id = u.id AND cp.contract_id = ?
        )";
        $params[] = $contract_id;
        $types .= "i";
    }

    // --- 4. Add the UNION for already-scheduled pilots to prevent 'CONFLICT' ---
    $final_query = "
        ($base_query)
        UNION
        (SELECT DISTINCT
            u.id,
            CONCAT(u.lastname, ', ', u.firstname) as display_name,
            s.pos COLLATE utf8mb4_unicode_ci as position
        FROM users u
        INNER JOIN schedule s ON u.id = s.user_id
        WHERE u.company_id = ? AND s.sched_date = ? AND s.registration = ?
        )
        ORDER BY display_name
    ";
    
    // Add parameters for the UNION part
    array_push($params, $company_id, $target_date, $registration);
    $types .= "iss";

    // --- 5. Execute Query and Process Results ---
    $stmt = $mysqli->prepare($final_query);
    if (!$stmt) throw new Exception('Database prepare failed: ' . $mysqli->error);
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $all_qualified_pilots = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // --- 6. Separate Pilots into PIC and SIC lists based on Rule #4 ---
    $pilots_pic = [];
    $pilots_sic = [];

    foreach ($all_qualified_pilots as $pilot) {
        $position = strtoupper($pilot['position']);
        
        // A pilot qualified as PIC can be a PIC
        if ($position === 'PIC' || $position === 'COM') {
            $pilots_pic[] = $pilot;
        }
        
        // A pilot qualified as PIC or SIC can be an SIC
        if ($position === 'PIC' || $position === 'COM' || $position === 'SIC' || $position === 'PIL') {
            $pilots_sic[] = $pilot;
        }
    }

    // --- 7. Send final JSON response with separate lists ---
    echo json_encode([
        'success' => true,
        'pilots_pic' => $pilots_pic,
        'pilots_sic' => $pilots_sic
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in schedule_qualified_and_available_pilots.php: " . $e->getMessage());
    echo json_encode(["success" => false, "error" => "Server Error: " . $e->getMessage()]);
}
?>