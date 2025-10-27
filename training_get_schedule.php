<?php
// training_get_schedule.php (FINAL - Production Version)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'login_permissions.php';

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}
$company_id = (int)$_SESSION['company_id'];
$logged_in_user_id = (int)$_SESSION['HeliUser'];

$editorRoles = ['admin', 'manager', 'training manager', 'admin pilot', 'manager pilot', 'training manager pilot'];
$canEditSchedule = userHasRole($editorRoles, $mysqli);

$start_date_str = $_GET["start"] ?? null;
$end_date_str = $_GET["end"] ?? null;
$viewType = $_GET["viewType"] ?? 'trainee';

if (!$start_date_str || !$end_date_str) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

try {
    global $mysqli;
    $temp_sim_events = [];
    $user_ids_to_fetch = [];

    $sql_events = "SELECT ts.id, ts.start_date, ts.length, ts.craft_type, 
                          ts.tri1_id, ts.tri2_id, ts.tre_id, 
                          ts.pilot_id1, ts.pilot_id2, ts.pilot_id3, ts.pilot_id4
                   FROM training_sim_schedule ts 
                   WHERE ts.company_id = ? AND ts.start_date < ? AND (ts.start_date + INTERVAL (ts.length - 1) DAY) >= ?";

    $params = [$company_id, $end_date_str, $start_date_str];
    $types = "iss";
    
    if (!$canEditSchedule) { 
        if ($viewType == "trainee") {
            $sql_events .= " AND (ts.pilot_id1 = ? OR ts.pilot_id2 = ? OR ts.pilot_id3 = ? OR ts.pilot_id4 = ?)";
            array_push($params, $logged_in_user_id, $logged_in_user_id, $logged_in_user_id, $logged_in_user_id);
            $types .= "iiii";
        } elseif ($viewType == "trainer") {
            $sql_events .= " AND (ts.tri1_id = ? OR ts.tri2_id = ? OR ts.tre_id = ?)";
            array_push($params, $logged_in_user_id, $logged_in_user_id, $logged_in_user_id);
            $types .= "iii";
        }
    }

    $stmt_events = $mysqli->prepare($sql_events);
    if (!$stmt_events) throw new Exception("SQL prepare error (events)");
    
    $stmt_events->bind_param($types, ...$params);
    if (!$stmt_events->execute()) throw new Exception("SQL execute error (events)");
    
    $result_events = $stmt_events->get_result();
    while ($row = $result_events->fetch_assoc()) {
        $temp_sim_events[] = $row;
        foreach (['tri1_id', 'tri2_id', 'tre_id', 'pilot_id1', 'pilot_id2', 'pilot_id3', 'pilot_id4'] as $key) {
            if (!empty($row[$key])) {
                $user_ids_to_fetch[] = (int)$row[$key];
            }
        }
    }
    $stmt_events->close();
    
    $user_names_map = [];
    if (!empty($user_ids_to_fetch)) {
        $unique_ids = array_unique($user_ids_to_fetch);
        $placeholders = rtrim(str_repeat('?,', count($unique_ids)), ',');
        $sql_users = "SELECT id, firstname, lastname FROM users WHERE id IN ($placeholders)";
        $stmt_users = $mysqli->prepare($sql_users);
        $stmt_users->bind_param(str_repeat('i', count($unique_ids)), ...$unique_ids);
        $stmt_users->execute();
        $result_users = $stmt_users->get_result();
        while ($user_row = $result_users->fetch_assoc()) {
            $user_names_map[(int)$user_row['id']] = htmlspecialchars($user_row['lastname'] . ", " . $user_row['firstname']);
        }
        $stmt_users->close();
    }
    
    $fullcalendar_events = [];
foreach ($temp_sim_events as $event_data) {
    // --- Re-initialize all variables inside the loop to prevent data leakage ---
    $pilots_display_array = [];
    $trainers_str_parts = [];
    
    $getName = function($id) use ($user_names_map) {
        return ($id && isset($user_names_map[(int)$id])) ? $user_names_map[(int)$id] : null;
    };
    
    // Build Pilot String
    if ($name = $getName($event_data['pilot_id1'])) { $pilots_display_array[] = $name; }
    if ($name = $getName($event_data['pilot_id2'])) { $pilots_display_array[] = $name; }
    if ($name = $getName($event_data['pilot_id3'])) { $pilots_display_array[] = $name; }
    if ($name = $getName($event_data['pilot_id4'])) { $pilots_display_array[] = $name; }
    $pilots_str = implode('; ', $pilots_display_array);

    // Build Trainer String
    if ($name = $getName($event_data['tri1_id'])) { $trainers_str_parts[] = "TRI 1: " . $name; }
    if ($name = $getName($event_data['tri2_id'])) { $trainers_str_parts[] = "TRI 2: " . $name; }
    if ($name = $getName($event_data['tre_id']))  { $trainers_str_parts[] = "TRE: " . $name; }
    $trainers_str = implode('; ', $trainers_str_parts);

    // Calculate Exclusive End Date
    $start_dt = new DateTime($event_data['start_date']);
    $end_dt_exclusive = clone $start_dt;
    $length = max(1, (int)$event_data['length']);
    $end_dt_exclusive->add(new DateInterval('P' . $length . 'D'));

    // Construct the final event object
    $fc_event = [
        "id"       => (int)$event_data['id'],
        "editable" => $canEditSchedule,
        "startEditable" => $canEditSchedule,
        "durationEditable" => $canEditSchedule,
        "craft"    => htmlspecialchars($event_data['craft_type']),
        "start"    => $event_data['start_date'], 
        "end"      => $end_dt_exclusive->format('Y-m-d'), 
        "allDay"   => true,
        "pilotid1" => $event_data['pilot_id1'] ? (int)$event_data['pilot_id1'] : null,
        "pilotid2" => $event_data['pilot_id2'] ? (int)$event_data['pilot_id2'] : null,
        "pilotid3" => $event_data['pilot_id3'] ? (int)$event_data['pilot_id3'] : null,
        "pilotid4" => $event_data['pilot_id4'] ? (int)$event_data['pilot_id4'] : null,
        "tri1id"   => $event_data['tri1_id'] ? (int)$event_data['tri1_id'] : null,
        "tri2id"   => $event_data['tri2_id'] ? (int)$event_data['tri2_id'] : null,
        "treid"    => $event_data['tre_id'] ? (int)$event_data['tre_id'] : null,
        "pilots"   => $pilots_str,  
        "trainers" => $trainers_str 
    ];
    $fullcalendar_events[] = $fc_event;
}

echo json_encode($fullcalendar_events);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in training_get_schedule.php: " . $e->getMessage());
    echo json_encode([]);
}
?>