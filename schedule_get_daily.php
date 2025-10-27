<?php
// get_daily_schedule.php // Renamed to schedule_get_daily.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_connect.php'; // VERIFY PATH

$response = ['success' => false, 'schedule' => [], 'legend' => [], 'error' => 'Unknown error.'];

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    $response['error'] = 'Authentication required.';
    http_response_code(401);
    echo json_encode($response);
    exit();
}
$company_id_session = (int)$_SESSION['company_id'];

$view_start_date_str = $_GET['start'] ?? date('Y-m-d'); 
$view_end_date_str = $_GET['end'] ?? date('Y-m-d', strtotime($view_start_date_str . ' +7 days')); 

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $view_start_date_str) || 
    !preg_match("/^\d{4}-\d{2}-\d{2}$/", $view_end_date_str)) {
    $response['error'] = 'Invalid date format for range.';
    http_response_code(400);
    echo json_encode($response);
    exit();
}

global $mysqli;
if (!$mysqli || $mysqli->connect_error) {
    $response['error'] = 'Database connection failed.';
    http_response_code(500);
    error_log("schedule_get_daily.php DB Error: " . ($mysqli->connect_error ?? "Unknown")); // Changed filename in log
    echo json_encode($response);
    exit();
}

try {
    $sql = "SELECT
                s.id as schedule_id, s.user_id, s.sched_date, s.craft_type, s.registration, 
                s.pos, s.otherPil, s.training_type, s.isExam,
                CONCAT(u.firstname, ' ', u.lastname) as pilot_name,
                COALESCE(co.contract_name, 'N/A') as contract_name, 
                COALESCE(co.color, '#3a87ad') as contract_color, -- Default color if no contract found
                co.id as contract_id_from_contracts 
            FROM schedule s
            INNER JOIN users u ON s.user_id = u.id 
            LEFT JOIN crafts cr ON s.registration = cr.registration AND cr.company_id = ? -- Craft must be in this company
            LEFT JOIN contract_crafts cc ON cr.id = cc.craft_id -- Link craft to its contract assignment
            LEFT JOIN contracts co ON cc.contract_id = co.id    -- Get contract details
            WHERE u.company_id = ? -- Filter by user's/session company for the scheduled user
              AND s.sched_date >= ? 
              AND s.sched_date < ?  -- FullCalendar's end date is exclusive
              AND u.is_active = 1 
            ORDER BY s.sched_date ASC, co.contract_name ASC, s.registration ASC, FIELD(s.pos, 'com', 'pil') ASC";

    $stmt = $mysqli->prepare($sql);
    if(!$stmt) {
        throw new Exception("Prepare failed for schedule_get_daily.php: ".$mysqli->error . " SQL: " . $sql); // Log SQL
    }
    // Parameters: 
    // 1. company_id (for crafts join: cr.company_id = ?)
    // 2. company_id (for users join: u.company_id = ?)
    // 3. view_start_date
    // 4. view_end_date
    $stmt->bind_param("iiss", $company_id_session, $company_id_session, $view_start_date_str, $view_end_date_str);    
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for schedule query in schedule_get_daily.php: ".$stmt->error);
    }
    $result = $stmt->get_result();

    $schedule_data = [];
    $legend_data_map = [];
    while ($row = $result->fetch_assoc()) {
        $row['schedule_id'] = (int)$row['schedule_id'];
        $row['user_id'] = (int)$row['user_id'];
        $row['pilot_name'] = htmlspecialchars($row['pilot_name'] ?? 'Unknown');
        $row['craft_type'] = htmlspecialchars($row['craft_type'] ?? '');
        $row['registration'] = htmlspecialchars($row['registration'] ?? '');
        $row['pos'] = htmlspecialchars($row['pos'] ?? '');
        $row['otherPil'] = htmlspecialchars($row['otherPil'] ?? '');
        $row['training_type'] = htmlspecialchars($row['training_type'] ?? '');
        $row['isExam'] = htmlspecialchars($row['isExam'] ?? '');
        $row['contract_name'] = htmlspecialchars($row['contract_name']); // Already coalesced to 'N/A'
        $row['contract_color'] = htmlspecialchars($row['contract_color']); // Already coalesced to default
        $row['contract_id_from_contracts'] = $row['contract_id_from_contracts'] ? (int)$row['contract_id_from_contracts'] : null;
        
        $schedule_data[] = $row;

        // Populate legend data
        if ($row['contract_name'] !== 'N/A' && !empty($row['contract_id_from_contracts']) && !isset($legend_data_map[$row['contract_id_from_contracts']])) {
             $legend_data_map[$row['contract_id_from_contracts']] = [
                'name' => $row['contract_name'],
                'color' => $row['contract_color']
            ];
        }
    }
    $stmt->close();

    $response['schedule'] = $schedule_data;
    $response['legend'] = array_values($legend_data_map);
    $response['success'] = true;
    unset($response['error']);

} catch (Exception $e) {
    error_log("Error in schedule_get_daily.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $response['error'] = 'Server error fetching company schedule. Debug: ' . $e->getMessage();
    http_response_code(500);
}

$mysqli->close();
echo json_encode($response);
?>