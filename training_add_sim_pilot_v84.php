<?php
// training_add_sim_pilot.php v84 (for training_sim_schedule)
if (session_status() == PHP_SESSION_NONE) {
    session_start();

// --- SESSION-BASED CSRF VALIDATION ---
$submitted_token = $_POST['form_token'] ?? '';

if (empty($submitted_token)) {
    throw new Exception("Security token missing. Please refresh the page.", 403);
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    throw new Exception("Invalid security token. Please refresh the page.", 403);
}

}
require_once 'db_connect.php';          // VERIFY PATH
require_once 'stats_api_response.php';  // VERIFY PATH
// require_once 'auth_helpers.php'; // Not strictly needed if only using admin_level check
require_once 'permissions.php'; // Include the firewall


$apiResponse = new ApiResponse();
global $mysqli; 

// Authentication check
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id']) || !isset($_SESSION['admin'])) {
    http_response_code(401);
    $apiResponse->setError("Authentication or required session data missing.")->send();
    exit;
}

$loggedInUserId = (int)$_SESSION['HeliUser'];       // This will be used for 'created_by'
$loggedInCompanyId = (int)$_SESSION['company_id']; // <<<< THIS IS THE COMPANY ID FOR THE QUERY

$admin_level = isset($_SESSION['admin']) ? intval($_SESSION['admin']) : 0;

// error_log("training_add_sim_pilot.php - Admin Level Check: " . $admin_level); // Keep for debugging if needed
if (!($admin_level > 0 && $admin_level != 2 && $admin_level != 4)) {
    // error_log("training_add_sim_pilot.php - Permission DENIED for admin level: " . $admin_level); // Keep for debugging
    http_response_code(403);
    $apiResponse->setError("Permission denied to add training events.")->send();
    exit; // <<<< ADD exit; HERE
} else {
    // error_log("training_add_sim_pilot.php - Permission GRANTED for admin level: " . $admin_level); // Keep for debugging
}

// --- Input Validation & Sanitization ---
$start_date = $_POST["start"] ?? null;
$length = isset($_POST["length"]) ? (int)$_POST["length"] : null;
$craft_type = $_POST["craft"] ?? null;
$ids_json = $_POST["ids"] ?? null; 

$tri1_id_str = $_POST["tri1"] ?? "null";
$tri2_id_str = $_POST["tri2"] ?? "null";
$tre_id_str = $_POST["tre"] ?? "null";

if (empty($start_date) || $length === null || $length <= 0 || empty($craft_type) || empty($ids_json)) {
    http_response_code(400);
    $apiResponse->setError("Missing or invalid required fields (start, length, craft, ids).")->send();
    exit;
}
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date)) {
    http_response_code(400);
    $apiResponse->setError("Invalid start date format. Use YYYY-MM-DD.")->send();
    exit;
}

$pilot_ids_from_json = json_decode($ids_json, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($pilot_ids_from_json)) {
    http_response_code(400);
    $apiResponse->setError("Invalid pilot IDs format.")->send();
    exit;
}

function sanitize_id_add_sim($id_str) { // Renamed function to be unique if needed
    return (strtolower(trim($id_str ?? '')) === "null" || empty(trim($id_str ?? ''))) ? null : (int)$id_str;
}

$tri1_id = sanitize_id_add_sim($tri1_id_str);
$tri2_id = sanitize_id_add_sim($tri2_id_str);
$tre_id = sanitize_id_add_sim($tre_id_str);

// $pilot_ids_from_json is already an array of numbers or nulls if JS was fixed
// If JS sends ["id1", "id2", null, null], then json_decode gives [id1_int, id2_int, null, null]
// So, sanitize_id_add_sim might not be strictly needed here if JS sends proper nulls in the array for JSON.stringify
// However, to be safe if JS sends "null" strings inside the array:
$pilot_id1 = sanitize_id_add_sim($pilot_ids_from_json[0] ?? null);
$pilot_id2 = sanitize_id_add_sim($pilot_ids_from_json[1] ?? null);
$pilot_id3 = sanitize_id_add_sim($pilot_ids_from_json[2] ?? null);
$pilot_id4 = sanitize_id_add_sim($pilot_ids_from_json[3] ?? null);


try {
    // --- SECURITY GUARD CLAUSE ---
    if (!canEditTrainingSchedule()) {
        throw new Exception("Permission denied to modify events.", 403);
    }
    // --- END GUARD CLAUSE ---
    // global $mysqli; // Already global from db_connect.php if included correctly
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error.", 500);
    }

    $sql = "INSERT INTO training_sim_schedule 
                (company_id, start_date, length, craft_type, 
                 tri1_id, tri2_id, tre_id, 
                 pilot_id1, pilot_id2, pilot_id3, pilot_id4, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }

    $stmt->bind_param("isisiiiiiiii", 
        $loggedInCompanyId, // <<<< CORRECTED: Use variable holding session company ID
        $start_date, 
        $length, 
        $craft_type,
        $tri1_id, 
        $tri2_id, 
        $tre_id,
        $pilot_id1, 
        $pilot_id2, 
        $pilot_id3, 
        $pilot_id4,
        $loggedInUserId // created_by
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $apiResponse->setSuccess(true)->setMessage("Training event added successfully.")->setData(['id' => $stmt->insert_id]);
        } else {
            // This case should ideally not be reached if execute() is true and no error.
            // If it is, it might mean data was identical or some other constraint prevented insert without error.
            throw new Exception("Failed to add training event, no rows affected despite successful execution.", 500);
        }
    } else {
        // Check for specific MySQL errors if needed, e.g., duplicate entry
        // if ($mysqli->errno == 1062) { ... }
        throw new Exception("SQL execute error: " . $stmt->error, 500);
    }
    $stmt->close();

} catch (Exception $e) {
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $apiResponse->setError("Server error: " . $e->getMessage());
    error_log("Error in training_add_sim_pilot.php: " . $e->getMessage() . " - Exception Trace: " . $e->getTraceAsString());
}

$apiResponse->send();
?>