<?php
// training_get_pilots.php (REFACTORED VERSION)
if (session_status() == PHP_SESSION_NONE) {
    session_start(); // <<<< ADDED
}

header('Content-Type: application/json'); // <<<< ADDED
require_once 'db_connect.php';          // VERIFY PATH

// Not using ApiResponse for direct array echo, matching other get_*.php scripts
// require_once 'stats_api_response.php';

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["error" => true, "message" => "Authentication required."]);
    exit;
}
$company_id = (int)$_SESSION['company_id']; // <<<< Using company_id

$start_date_str = $_GET["start"] ?? null;
$end_date_str = $_GET["end"] ?? null;
$craft_type_filter = $_GET["craft"] ?? null;
$current_pilots_param_str = $_GET["current"] ?? null; // e.g., "(id1,id2)" or "id1,id2"

if (!$start_date_str || !$end_date_str || !$craft_type_filter) {
    http_response_code(400);
    echo json_encode(["error" => true, "message" => "Start date, end date, and craft type are required."]);
    exit;
}
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_str) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date_str)) {
    http_response_code(400);
    echo json_encode(["error" => true, "message" => "Invalid date format. Use YYYY-MM-DD."]);
    exit;
}

$current_pilot_ids = [];
if ($current_pilots_param_str) {
    $trimmed_ids_str = trim($current_pilots_param_str, '()');
    if (!empty($trimmed_ids_str)) {
        $ids_array = explode(',', $trimmed_ids_str);
        foreach ($ids_array as $id_val) {
            if (is_numeric(trim($id_val))) {
                $current_pilot_ids[] = (int)trim($id_val);
            }
        }
    }
}

global $mysqli;
if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    error_log("DB Connect error in training_get_pilots: " . ($mysqli->connect_error ?? 'Unknown'));
    echo json_encode(["error" => true, "message" => "Database connection error."]);
    exit;
}

try {
    $available_pilots_list = [];
    $params_for_bind = [];
    $param_types = "";

    // Base query for pilots from 'users' table
    $sql = "SELECT DISTINCT u.id, u.lastname, u.firstname
            FROM users u
            INNER JOIN pilot_craft_type pct ON u.id = pct.user_id  
            WHERE u.company_id = ? AND pct.craft_type = ? AND u.is_active = 1";

    $params_for_bind[] = $company_id;
    $params_for_bind[] = $craft_type_filter;
    $param_types .= "is";

    // Availability conditions string to be built
    $availability_sql_conditions = " (
        u.id NOT IN ( 
            SELECT s_ts.tri1_id FROM training_sim_schedule s_ts WHERE s_ts.tri1_id IS NOT NULL AND s_ts.company_id = u.company_id AND s_ts.craft_type != ? AND (s_ts.start_date + INTERVAL (s_ts.length - 1) DAY) >= ? AND s_ts.start_date <= ?
            UNION ALL SELECT s_ts.tri2_id FROM training_sim_schedule s_ts WHERE s_ts.tri2_id IS NOT NULL AND s_ts.company_id = u.company_id AND s_ts.craft_type != ? AND (s_ts.start_date + INTERVAL (s_ts.length - 1) DAY) >= ? AND s_ts.start_date <= ?
            UNION ALL SELECT s_ts.tre_id FROM training_sim_schedule s_ts WHERE s_ts.tre_id IS NOT NULL AND s_ts.company_id = u.company_id AND s_ts.craft_type != ? AND (s_ts.start_date + INTERVAL (s_ts.length - 1) DAY) >= ? AND s_ts.start_date <= ?
        )
        AND u.id NOT IN ( 
            SELECT sts_p.pilot_id1 FROM training_sim_schedule sts_p WHERE sts_p.pilot_id1 IS NOT NULL AND sts_p.company_id = u.company_id AND (sts_p.start_date + INTERVAL (sts_p.length - 1) DAY) >= ? AND sts_p.start_date <= ?
            UNION ALL SELECT sts_p.pilot_id2 FROM training_sim_schedule sts_p WHERE sts_p.pilot_id2 IS NOT NULL AND sts_p.company_id = u.company_id AND (sts_p.start_date + INTERVAL (sts_p.length - 1) DAY) >= ? AND sts_p.start_date <= ?
            UNION ALL SELECT sts_p.pilot_id3 FROM training_sim_schedule sts_p WHERE sts_p.pilot_id3 IS NOT NULL AND sts_p.company_id = u.company_id AND (sts_p.start_date + INTERVAL (sts_p.length - 1) DAY) >= ? AND sts_p.start_date <= ?
            UNION ALL SELECT sts_p.pilot_id4 FROM training_sim_schedule sts_p WHERE sts_p.pilot_id4 IS NOT NULL AND sts_p.company_id = u.company_id AND (sts_p.start_date + INTERVAL (sts_p.length - 1) DAY) >= ? AND sts_p.start_date <= ?
        )
        AND u.id NOT IN ( 
            SELECT ua.user_id FROM user_availability ua WHERE ua.user_id = u.id ";
    // Add company_id check for user_availability IF the table has it
    // if (/* your_condition_to_check_if_user_availability_has_company_id */ false ) { // Replace false with actual check or assume it does/doesn't
    //    $availability_sql_conditions .= " AND ua.company_id = u.company_id ";
    // }
    $availability_sql_conditions .= " AND ua.off_date >= ? AND ua.on_date <= ? 
        )
        AND u.id NOT IN ( 
            SELECT tsched.user_id FROM training_schedule tsched WHERE tsched.company_id = u.company_id AND tsched.end_datetime >= ? AND tsched.start_datetime <= ?
        )
    )";

    // Parameters for availability conditions
    // For "NOT IN sim_training_schedule as key personnel on DIFFERENT craft" (3 sets)
    array_push($params_for_bind, $craft_type_filter, $start_date_str, $end_date_str, $craft_type_filter, $start_date_str, $end_date_str, $craft_type_filter, $start_date_str, $end_date_str);
    $param_types .= "sssssssss";
    // For "NOT IN sim_training_schedule as pilot" (4 sets)
    array_push($params_for_bind, $start_date_str, $end_date_str, $start_date_str, $end_date_str, $start_date_str, $end_date_str, $start_date_str, $end_date_str);
    $param_types .= "ssssssss";
    // For "NOT IN user_availability"
    array_push($params_for_bind, $start_date_str, $end_date_str);
    $param_types .= "ss";
    // For "NOT IN training_schedule (general duties)"
    array_push($params_for_bind, $start_date_str.' 00:00:00', $end_date_str.' 23:59:59');
    $param_types .= "ss";


    if (!empty($current_pilot_ids)) {
        $current_pilots_placeholders = rtrim(str_repeat('?,', count($current_pilot_ids)), ',');
        $sql .= " AND (u.id IN ($current_pilots_placeholders) OR $availability_sql_conditions)";
        // Add current pilot IDs to the beginning of $params_for_bind AFTER company_id and craft_type for IN clause
        $params_for_bind = array_merge(array_slice($params_for_bind, 0, 2), $current_pilot_ids, array_slice($params_for_bind, 2));
        $param_types = substr_replace($param_types, str_repeat('i', count($current_pilot_ids)), 2, 0);
    } else {
        $sql .= " AND $availability_sql_conditions";
    }
    
    $sql .= " ORDER BY u.lastname, u.firstname";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error training_get_pilots: " . $mysqli->error . " SQL: " . $sql, 500);
    }
    $stmt->bind_param($param_types, ...$params_for_bind);

    if (!$stmt->execute()) {
        throw new Exception("SQL execute error training_get_pilots: " . $stmt->error, 500);
    }
    $result = $stmt->get_result();
    $today = new DateTimeImmutable(); // Use Immutable for safety
    $ninety_days_hence = $today->add(new DateInterval('P90D'));

    while ($row = $result->fetch_assoc()) {
        $expireType = ""; 
        // if (!empty($row["sim_expiry_date"])) {
        //     try {
        //         $sim_expiry = new DateTimeImmutable($row["sim_expiry_date"]);
        //         if ($sim_expiry < $today) {
        //             $expireType = "alert-danger"; 
        //         } else if ($sim_expiry < $ninety_days_hence) {
        //             $expireType = "alert-warning"; 
        //         }
        //     } catch (Exception $dateEx) {
        //         error_log("Invalid sim_expiry_date format for user ".$row['id'].": ".$row["sim_expiry_date"]);
        //     }
        // }
        
        // The 'trainer' field is from your original script's logic. 
        // It's not clear if a pilot selected here can also be a "trainer" in this context.
        // For now, defaulting to false as they are being selected *as trainees*.
        $available_pilots_list[] = [
            "id" => (int)$row["id"],
            "name" => $row["lastname"] . ", " . $row["firstname"],
            "expired" => $expireType,
            "trainer" => false // This usually indicates if the pilot is also a trainer for this context
        ];
    }
    $stmt->close();

    echo json_encode($available_pilots_list);
    exit;

} catch (Exception $e) {
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    error_log("Error in training_get_pilots.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    echo json_encode(["error" => true, "message" => "Server error: " . $e->getMessage()]);
    exit;
}
?>