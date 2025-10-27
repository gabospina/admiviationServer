<?php
// schedule_get_my_schedule.php replace get_pilot_schedule.php
// (Formerly get_user_schedule.php)

if (session_status() == PHP_SESSION_NONE) {
    session_start(); // <<<< ADDED
}
header('Content-Type: application/json'); // <<<< ADDED
require_once 'db_connect.php';       // VERIFY PATH
// require_once 'stats_api_response.php'; // For ApiResponse if you standardize all responses

$response_data = ['assignments' => [], 'error' => null, 'success' => false];

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    $response_data['error'] = "Authentication required.";
    http_response_code(401);
    echo json_encode($response_data);
    exit;
}

$user_id = (int)$_SESSION["HeliUser"];
$company_id = (int)$_SESSION["company_id"]; // Get company_id from session

// Expecting 'start_of_week' and 'end_of_week' from JS, or calculate if only one date is sent
$week_start_date_str = $_POST["start_of_week"] ?? $_GET["start_of_week"] ?? null; // YYYY-MM-DD
$week_end_date_str = $_POST["end_of_week"] ?? $_GET["end_of_week"] ?? null;     // YYYY-MM-DD

if (!$week_start_date_str || !$week_end_date_str ||
    !preg_match("/^\d{4}-\d{2}-\d{2}$/", $week_start_date_str) ||
    !preg_match("/^\d{4}-\d{2}-\d{2}$/", $week_end_date_str) ) {
    $response_data['error'] = "Valid start and end dates for the week are required (YYYY-MM-DD).";
    http_response_code(400);
    echo json_encode($response_data);
    exit;
}

global $mysqli;
if (!$mysqli || $mysqli->connect_error) {
    $response_data['error'] = "Database connection error.";
    http_response_code(500);
    error_log("DB Error get_pilot_schedule: ". $mysqli->connect_error);
    echo json_encode($response_data);
    exit;
}

$all_assignments = [];

try {
    // 1. Fetch from 'schedule' table (flight duties)
    $sql_schedule = "SELECT id, user_id, sched_date, craft_type, registration, pos, otherPil, training_type, isExam 
                     FROM schedule 
                     WHERE user_id = ? AND company_id = ? AND sched_date BETWEEN ? AND ?";
    $stmt_schedule = $mysqli->prepare($sql_schedule);
    if(!$stmt_schedule) throw new Exception("Prepare failed (schedule): ".$mysqli->error);
    
    $stmt_schedule->bind_param("iiss", $user_id, $company_id, $week_start_date_str, $week_end_date_str);
    $stmt_schedule->execute();
    $result_schedule = $stmt_schedule->get_result();
    while ($row = $result_schedule->fetch_assoc()) {
        $all_assignments[] = [
            'type' => 'flight_duty', // Differentiate type of assignment
            'date' => $row['sched_date'],
            'title' => ($row['craft_type'] ?: 'N/A') . ($row['registration'] ? ' ('.$row['registration'].')' : '') . ' - Pos: ' . ($row['pos'] ?: 'N/A'),
            'details' => $row['otherPil'] ? 'With: ' . htmlspecialchars($row['otherPil']) : '',
            'raw' => $row // Keep raw data if needed by JS
        ];
    }
    $stmt_schedule->close();

    // 2. Fetch from 'training_sim_schedule' table (SIM training)
    // An event [event_start, event_start + length - 1 day] overlaps with week [week_start, week_end] if:
    // event_start <= week_end AND (event_start + length - 1 day) >= week_start
    $sql_sim = "SELECT id, start_date, length, craft_type, pilot_id1, pilot_id2, pilot_id3, pilot_id4, tri1_id, tri2_id, tre_id
                FROM training_sim_schedule
                WHERE company_id = ? 
                  AND start_date <= ? 
                  AND (start_date + INTERVAL (IFNULL(length,1) - 1) DAY) >= ?
                  AND (pilot_id1 = ? OR pilot_id2 = ? OR pilot_id3 = ? OR pilot_id4 = ? OR tri1_id = ? OR tri2_id = ? OR tre_id = ?)";
    $stmt_sim = $mysqli->prepare($sql_sim);
    if(!$stmt_sim) throw new Exception("Prepare failed (sim_schedule): ".$mysqli->error);

    $stmt_sim->bind_param("isssiiiiiii", 
        $company_id, 
        $week_end_date_str, $week_start_date_str,
        $user_id, $user_id, $user_id, $user_id, // as pilot
        $user_id, $user_id, $user_id            // as trainer
    );
    $stmt_sim->execute();
    $result_sim = $stmt_sim->get_result();
    while ($row = $result_sim->fetch_assoc()) {
        $user_role_in_sim = "Participant";
        if ($row['tri1_id'] == $user_id || $row['tri2_id'] == $user_id) $user_role_in_sim = "TRI";
        else if ($row['tre_id'] == $user_id) $user_role_in_sim = "TRE";
        else if ($row['pilot_id1'] == $user_id || $row['pilot_id2'] == $user_id || $row['pilot_id3'] == $user_id || $row['pilot_id4'] == $user_id) $user_role_in_sim = "Trainee";

        $sim_start_dt = new DateTime($row['start_date']);
        $sim_length = isset($row['length']) ? (int)$row['length'] : 1;
        if($sim_length < 1) $sim_length = 1;

        for ($k = 0; $k < $sim_length; $k++) {
            $current_sim_day_dt = clone $sim_start_dt;
            $current_sim_day_dt->modify("+$k days");
            $current_sim_day_str = $current_sim_day_dt->format('Y-m-d');

            // Only include days that fall within the requested week_start_date_str and week_end_date_str
            if (strtotime($current_sim_day_str) >= strtotime($week_start_date_str) && strtotime($current_sim_day_str) <= strtotime($week_end_date_str)) {
                 $all_assignments[] = [
                    'type' => 'sim_training',
                    'date' => $current_sim_day_str,
                    'title' => 'SIM: ' . ($row['craft_type'] ?: 'N/A') . ' (' . $user_role_in_sim . ')',
                    'details' => 'SIM Session',
                    'raw' => $row
                ];
            }
        }
    }
    $stmt_sim->close();
    
    // You could also fetch from trainer_schedule if those are distinct duties for "My Schedule"
    // ...

    $response_data['assignments'] = $all_assignments;
    $response_data['success'] = true;
    unset($response_data['error']);

} catch (Exception $e) {
    $response_data['error'] = "Server error fetching user schedule: " . $e->getMessage();
    http_response_code(500);
    error_log("Error get_pilot_schedule: " . $e->getMessage());
}

$mysqli->close();
echo json_encode($response_data);
?>