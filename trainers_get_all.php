<?php
// trainer_get_all.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(["error" => true, "message" => "Authentication required."]);
    exit;
}
$company_id = (int)$_SESSION['company_id'];

$start_date_str = $_GET["start"] ?? null; // Optional: for availability checking
$end_date_str = $_GET["end"] ?? null;     // Optional: for availability checking

// Date validation if provided
if (($start_date_str && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_str)) || 
    ($end_date_str && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date_str))) {
    http_response_code(400);
    echo json_encode(["error" => true, "message" => "Invalid date format. Use YYYY-MM-DD."]);
    exit;
}


$tri_role_id = 3;
$tre_role_id = 6;
$trainer_role_ids = [$tri_role_id, $tre_role_id];
$role_placeholders = rtrim(str_repeat('?,', count($trainer_role_ids)), ',');

try {
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error.", 500);
    }

    $all_available_trainers = [];

    // Base query for users with TRI or TRE roles
    $sql = "SELECT DISTINCT u.id, u.lastname, u.firstname,
                   (SELECT GROUP_CONCAT(uhr_in.role_id) FROM user_has_roles uhr_in WHERE uhr_in.user_id = u.id AND uhr_in.company_id = u.company_id AND uhr_in.role_id IN ($role_placeholders)) as assigned_trainer_roles,
                   v.sim_expiry_date
            FROM users u
            INNER JOIN user_has_roles uhr ON u.id = uhr.user_id AND uhr.company_id = u.company_id
            LEFT JOIN validity v ON u.id = v.pilot_id AND v.company_id = u.company_id 
            WHERE u.company_id = ?
              AND u.is_active = 1
              AND uhr.role_id IN ($role_placeholders)";

    $params_for_bind = array_merge($trainer_role_ids, [$company_id], $trainer_role_ids);
    $param_types_base = str_repeat('i', count($trainer_role_ids)) . "i" . str_repeat('i', count($trainer_role_ids));


    // Add availability checks if start_date and end_date are provided
    if ($start_date_str && $end_date_str) {
        $sql .= " AND u.id NOT IN ( 
                      SELECT ua.user_id FROM user_availability ua WHERE ua.company_id = u.company_id AND ua.off_date >= ? AND ua.on_date <= ?
                  )
                  AND u.id NOT IN (
                      SELECT tsched.user_id FROM training_schedule tsched WHERE tsched.company_id = u.company_id AND tsched.end_datetime >= ? AND tsched.start_datetime <= ?
                  )";
        // Note: More detailed training_sim_schedule and trainer_schedule conflict checks could be added here too for stricter availability
        array_push($params_for_bind, $start_date_str, $end_date_str, $start_date_str.' 00:00:00', $end_date_str.' 23:59:59');
        $param_types_base .= "ssss";
    }
    
    $sql .= " ORDER BY u.lastname, u.firstname";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }

    $stmt->bind_param($param_types_base, ...$params_for_bind);


    if (!$stmt->execute()) {
        throw new Exception("SQL execute error: " . $stmt->error, 500);
    }
    $result = $stmt->get_result();
    $today = new DateTime();
    $ninety_days_hence = (new DateTime())->add(new DateInterval('P90D'));

    while ($row = $result->fetch_assoc()) {
        $expireType = "";
        if ($row["sim_expiry_date"] != null) {
            $sim_expiry = new DateTime($row["sim_expiry_date"]);
            if ($sim_expiry < $today) $expireType = "alert-danger";
            else if ($sim_expiry < $ninety_days_hence) $expireType = "alert-warning";
        }

        // Determine training_level for data-pos: 2 if TRE (can do both), 1 if only TRI
        $training_level_for_js_pos = 1; // Default to TRI
        $user_actual_roles = explode(',', $row['assigned_trainer_roles']);
        if (in_array((string)$tre_role_id, $user_actual_roles)) {
            $training_level_for_js_pos = 2; 
        }

        $all_available_trainers[] = [
            "id" => $row['id'],
            "name" => $row['lastname'] . ", " . $row['firstname'],
            "expired" => $expireType,
            "training_level" => $training_level_for_js_pos // For data-pos in JS
        ];
    }
    $stmt->close();

    echo json_encode($all_available_trainers);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in get_all_trainers.php: " . $e->getMessage(). " SQL: " . ($sql ?? 'N/A'));
    echo json_encode(["error" => true, "message" => "Server error: " . $e->getMessage()]);
}
?>