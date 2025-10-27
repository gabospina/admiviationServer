<?php
// assets/php/update_trainer_schedule.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'stats_api_response.php';

$apiResponse = new ApiResponse();

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id']) || !isset($_SESSION['admin'])) {
    http_response_code(401);
    $apiResponse->setError("Authentication or required session data missing.")->send();
    exit;
}

$company_id = (int)$_SESSION['company_id'];
// $updated_by_user_id = (int)$_SESSION['HeliUser']; // Can add updated_by if table has it
$admin_level = (int)$_SESSION['admin'];

if (!($admin_level > 0 && $admin_level != 2 && $admin_level != 4)) {
    http_response_code(403);
    $apiResponse->setError("Permission denied to update trainer schedules.")->send();
    exit;
}

$event_id = isset($_POST["eventid"]) ? (int)$_POST["eventid"] : null;
$trainer_pilot_id = isset($_POST["pilot"]) ? (int)$_POST["pilot"] : null;
$position = $_POST["position"] ?? null;
$start_date_str = $_POST["start"] ?? null; // This is the new start date from 'changeTrainerStart'
$length_days = isset($_POST["length"]) ? (int)$_POST["length"] : null; // This is the new length from JS calculation

if ($event_id === null || $event_id <= 0 || $trainer_pilot_id === null || $trainer_pilot_id <= 0 ||
    empty($position) || empty($start_date_str) || $length_days === null || $length_days <= 0) {
    http_response_code(400);
    $apiResponse->setError("Missing or invalid required fields (eventid, pilot, position, start, length).")->send();
    exit;
}
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_str)) {
    http_response_code(400);
    $apiResponse->setError("Invalid start date format. Use YYYY-MM-DD.")->send();
    exit;
}
if (!in_array($position, ['tri', 'tre', 'tri/tre'])) {
    http_response_code(400);
    $apiResponse->setError("Invalid position specified.")->send();
    exit;
}

// Calculate new end_date
try {
    $start_date_obj = new DateTime($start_date_str);
    $end_date_obj = clone $start_date_obj;
    $end_date_obj->add(new DateInterval('P' . ($length_days - 1) . 'D'));
    $end_date_for_db = $end_date_obj->format('Y-m-d');
} catch (Exception $e) {
    http_response_code(400);
    $apiResponse->setError("Invalid date or length for end date calculation: " . $e->getMessage())->send();
    exit;
}

try {
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error.", 500);
    }
    
    // Optional: Check if new trainer_pilot_id belongs to the company
    $check_user_stmt = $mysqli->prepare("SELECT id FROM users WHERE id = ? AND company_id = ?");
    $check_user_stmt->bind_param("ii", $trainer_pilot_id, $company_id);
    $check_user_stmt->execute();
    $check_result = $check_user_stmt->get_result();
    if ($check_result->num_rows === 0) {
        http_response_code(400);
        $apiResponse->setError("Selected trainer does not belong to your company or does not exist.")->send();
        exit;
    }
    $check_user_stmt->close();

    $sql = "UPDATE trainer_schedule SET 
                trainer_user_id = ?, 
                position = ?, 
                start_date = ?, 
                end_date = ?,
                updated_at = NOW() -- Assuming you have an updated_at column
            WHERE id = ? AND company_id = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }

    $stmt->bind_param("isssii", 
        $trainer_pilot_id, $position, $start_date_str, $end_date_for_db,
        $event_id, $company_id
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $apiResponse->setSuccess(true)->setMessage("Trainer schedule updated successfully.");
        } else {
            $check_stmt_exist = $mysqli->prepare("SELECT id FROM trainer_schedule WHERE id = ? AND company_id = ?");
            $check_stmt_exist->bind_param("ii", $event_id, $company_id);
            $check_stmt_exist->execute();
            if ($check_stmt_exist->get_result()->num_rows === 0) {
                $apiResponse->setError("Trainer schedule entry not found for your company.");
                http_response_code(404);
            } else {
                $apiResponse->setSuccess(true)->setMessage("No changes detected for the trainer schedule.");
            }
            $check_stmt_exist->close();
        }
    } else {
        throw new Exception("SQL execute error: " . $stmt->error, 500);
    }
    $stmt->close();

} catch (Exception $e) {
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $apiResponse->setError("Server error: " . $e->getMessage());
}

$apiResponse->send();
?>