<?php
// training_get_dates.php
// (Formerly get_training_dates.php)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json'); // Crucial for jQuery's dataType: "json"
require_once 'db_connect.php';          // VERIFY PATH relative to this script
// require_once 'stats_api_response.php'; // This script directly echoes JSON for JS compatibility

// --- Session & Permission Basic Check (actual permission for this action might not be needed, but auth is) ---
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    // For direct echo, send a simple error object
    echo json_encode(["error" => true, "message" => "Authentication required."]);
    exit;
}

$company_id = (int)$_SESSION['company_id'];

global $mysqli; // Assuming db_connect.php makes $mysqli global or returns it

if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    error_log("Database connection error in training_get_dates.php: " . ($mysqli->connect_error ?? "Unknown error"));
    echo json_encode(["error" => true, "message" => "Database connection error."]);
    exit;
}

try {
    // Fetch from 'training_availability' table which stores ranges with 'date_on' and 'date_off'
    // The JavaScript expects objects with 'id' and 'on' (which is our date_on).
    // We also send 'off' (date_off) which was part of the X-Editable structure.
    $sql = "SELECT id, date_on, date_off 
            FROM training_availability 
            WHERE company_id = ? 
            ORDER BY date_on ASC";
            
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }

    $stmt->bind_param("i", $company_id);

    if (!$stmt->execute()) {
        throw new Exception("SQL execute error: " . $stmt->error, 500);
    }

    $result = $stmt->get_result();
    $training_date_periods = [];
    while ($row = $result->fetch_assoc()) {
        $training_date_periods[] = [
            'id' => (int)$row['id'],
            'on' => $row['date_on'],  // JavaScript uses 'on' for the start of the period
            'off' => $row['date_off'] // JavaScript uses 'off' for the end of the period
        ];
    }
    $stmt->close();

    // The JavaScript in the initial AJAX chain (and in updateTrainingDates)
    // expects a direct JSON array of these objects.
    echo json_encode($training_date_periods);
    exit;

} catch (Exception $e) {
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    error_log("Error in training_get_dates.php: " . $e->getMessage());
    echo json_encode(["error" => true, "message" => "Server error while fetching training dates: " . $e->getMessage()]);
    exit;
}
?>