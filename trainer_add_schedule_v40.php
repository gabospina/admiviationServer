<?php
// add_trainer_schedule.php
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
$created_by_user_id = (int)$_SESSION['HeliUser'];
$admin_level = (int)$_SESSION['admin'];

// Access Control
if (!($admin_level > 0 && $admin_level != 2 && $admin_level != 4)) {
    http_response_code(403);
    $apiResponse->setError("Permission denied to add trainer schedules.")->send();
    exit;
}

$start_date_str = $_POST["start"] ?? null;
$length_days = isset($_POST["length"]) ? (int)$_POST["length"] : null; // Length in days
$trainer_pilot_id = isset($_POST["pilot"]) ? (int)$_POST["pilot"] : null; // This is the user_id of the trainer
$position = $_POST["position"] ?? null; // 'tri', 'tre', 'tri/tre'

if (empty($start_date_str) || $length_days === null || $length_days <= 0 || 
    $trainer_pilot_id === null || $trainer_pilot_id <= 0 || empty($position)) {
    http_response_code(400);
    $apiResponse->setError("Missing or invalid required fields (start, length, pilot, position).")->send();
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

// Calculate end_date from start_date and length
try {
    $start_date_obj = new DateTime($start_date_str);
    $end_date_obj = clone $start_date_obj;
    $end_date_obj->add(new DateInterval('P' . ($length_days - 1) . 'D')); // Length is inclusive
    $end_date_for_db = $end_date_obj->format('Y-m-d');
} catch (Exception $e) {
    http_response_code(400);
    $apiResponse->setError("Invalid date or length calculation error: " . $e->getMessage())->send();
    exit;
}


try {
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error.", 500);
    }

    // Check if trainer belongs to the same company (important for security)
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


    $sql = "INSERT INTO trainer_schedule 
                (company_id, trainer_user_id, start_date, end_date, position, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }

    $stmt->bind_param("iisssi", 
        $company_id, $trainer_pilot_id, $start_date_str, $end_date_for_db, $position, $created_by_user_id
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $apiResponse->setSuccess(true)->setMessage("Trainer schedule added successfully.")->setData(['id' => $stmt->insert_id]);
        } else {
            throw new Exception("Failed to add trainer schedule, no rows affected.", 500);
        }
    } else {
        // Check for unique constraint violations or other DB errors
        if ($mysqli->errno == 1062) { // Duplicate entry
             throw new Exception("This trainer schedule might already exist or conflict.", 409);
        }
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