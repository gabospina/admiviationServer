<?php
// trainers_get_tre.php (FINAL - Correct On-Duty Logic)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}
$company_id = (int)$_SESSION['company_id'];
$start_date_str = $_GET["start"] ?? null;
$end_date_str = $_GET["end"] ?? null;
$craft_type_filter = $_GET["craft"] ?? null;

if (!$start_date_str || !$end_date_str || !$craft_type_filter) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

try {
    global $mysqli;
    $available_tres = [];

    // --- FINAL CORRECTED QUERY ---
    $sql = "SELECT DISTINCT u.id, u.lastname, u.firstname 
            FROM users u
            INNER JOIN pilot_craft_type pct ON u.id = pct.user_id
            INNER JOIN user_availability ua ON u.id = ua.user_id
            WHERE u.company_id = ?
              AND u.is_active = 1
              AND pct.craft_type = ?
              AND pct.is_tre = 1
              AND ua.on_date <= ? AND ua.off_date >= ?
              AND u.id NOT IN (
                  SELECT ts.trainer_user_id FROM trainer_schedule ts 
                  WHERE ts.company_id = u.company_id AND ts.start_date <= ? AND ts.end_date >= ?
              )
            ORDER BY u.lastname, u.firstname";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error);
    }
    
    $stmt->bind_param("isssss", 
        $company_id, 
        $craft_type_filter,
        $start_date_str, $end_date_str,      // for user_availability check
        $end_date_str, $start_date_str       // for trainer_schedule conflict
    );
    
    if (!$stmt->execute()) {
        throw new Exception("SQL execute error: " . $stmt->error);
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $available_tres[] = ["id" => (int)$row['id'], "name" => $row['lastname'] . ", " . $row['firstname']];
    }
    $stmt->close();

    echo json_encode($available_tres);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in trainers_get_tre.php: " . $e->getMessage());
    echo json_encode([]);
}
?>