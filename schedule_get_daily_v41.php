<?php
// get_daily_schedule.php

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
$company_id = (int)$_SESSION['company_id'];

// FullCalendar sends 'start' and 'end' for the view range (YYYY-MM-DD format)
$view_start_date_str = $_GET['start'] ?? date('Y-m-d', strtotime('monday this week')); 
$view_end_date_str = $_GET['end'] ?? date('Y-m-d', strtotime('sunday this week +1 day')); // FC end is exclusive

// Basic validation (FullCalendar usually sends valid dates)
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
    error_log("get_daily_schedule.php DB Error: " . ($mysqli->connect_error ?? "Unknown"));
    echo json_encode($response);
    exit();
}

try {
    // Fetch from 'schedule' table.
    // Your 'schedule' table has user_id, sched_date, craft_type, registration, pos, otherPil, training_type, isExam
    // We need to join with 'users' to get pilot_name.
    // We can LEFT JOIN with 'crafts' and then 'contracts' if contract_color is desired.
    
    $sql = "SELECT
                s.id as schedule_id, s.user_id, s.sched_date, s.craft_type, s.registration, 
                s.pos, s.otherPil, s.training_type, s.isExam,
                CONCAT(u.firstname, ' ', u.lastname) as pilot_name,
                COALESCE(co.contract_name, 'N/A') as contract_name, 
                COALESCE(co.color, '#3a87ad') as contract_color 
            FROM schedule s
            INNER JOIN users u ON s.user_id = u.id 
            LEFT JOIN crafts cr ON s.registration = cr.registration AND cr.company_id = u.company_id 
            LEFT JOIN contracts co ON cr.contract_id = co.id -- Assuming contracts table and crafts.contract_id
            WHERE u.company_id = ? -- Filter by user's company (implicitly schedule's company)
              AND s.sched_date >= ? 
              AND s.sched_date < ? -- FullCalendar's end date is exclusive
              AND u.is_active = 1 -- Assuming users table has is_active
            ORDER BY s.sched_date ASC, s.registration ASC, FIELD(s.pos, 'com', 'pil') ASC";
            // Note: schedule table itself should ideally have a company_id column if entries can span multiple companies.
            // If not, filtering by u.company_id assumes schedule entries are tied to the user's company.

    $stmt = $mysqli->prepare($sql);
    if(!$stmt) {
        throw new Exception("Prepare failed for schedule query: ".$mysqli->error);
    }

    // Params: company_id (for users join), view_start_date, view_end_date
    $stmt->bind_param("iss", $company_id, $view_start_date_str, $view_end_date_str);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed for schedule query: ".$stmt->error);
    }
    $result = $stmt->get_result();

    $schedule_data = [];
    $legend_data_map = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure all necessary fields are present for the JS map function
        $row['schedule_id'] = (int)$row['schedule_id'];
        $row['user_id'] = (int)$row['user_id'];
        // HTML escape strings that will be displayed
        $row['pilot_name'] = htmlspecialchars($row['pilot_name'] ?? 'Unknown');
        $row['craft_type'] = htmlspecialchars($row['craft_type'] ?? '');
        $row['registration'] = htmlspecialchars($row['registration'] ?? '');
        $row['pos'] = htmlspecialchars($row['pos'] ?? '');
        $row['otherPil'] = htmlspecialchars($row['otherPil'] ?? '');
        $row['training_type'] = htmlspecialchars($row['training_type'] ?? '');
        $row['isExam'] = htmlspecialchars($row['isExam'] ?? '');
        $row['contract_name'] = htmlspecialchars($row['contract_name']);
        $row['contract_color'] = htmlspecialchars($row['contract_color']);

        $schedule_data[] = $row;

        if ($row['contract_name'] !== 'N/A' && !isset($legend_data_map[$row['contract_name']])) {
             $legend_data_map[$row['contract_name']] = [
                'name' => $row['contract_name'],
                'color' => $row['contract_color']
            ];
        }
    }
    $stmt->close();

    $response['schedule'] = $schedule_data;
    $response['legend'] = array_values($legend_data_map); // For legend display if needed
    $response['success'] = true;
    unset($response['error']);

} catch (Exception $e) {
    error_log("Error in get_daily_schedule.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $response['error'] = 'Server error fetching company schedule. ' . $e->getMessage();
    http_response_code(500);
}

$mysqli->close();
echo json_encode($response);
?>