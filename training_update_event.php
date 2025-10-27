<?php
// training_update_event.php (for sim_training_schedule)
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
$user_id = (int)$_SESSION['HeliUser']; // For updated_by
$admin_level = (int)$_SESSION['admin'];

if (!($admin_level > 0 && $admin_level != 2 && $admin_level != 4)) {
    http_response_code(403);
    $apiResponse->setError("Permission denied to update training events.")->send();
    exit;
}

$event_id = isset($_POST["eventid"]) ? (int)$_POST["eventid"] : null;

// New fields from JS for date update
$start_date_str = $_POST["start"] ?? null; // From editStartDate
$length = isset($_POST["length"]) ? (int)$_POST["length"] : null; // Calculated in JS from editEndDate


$tri1_id_str = $_POST["tri1"] ?? "null";
$tri2_id_str = $_POST["tri2"] ?? "null";
$tre_id_str = $_POST["tre"] ?? "null";
$ids_json = $_POST["ids"] ?? null; // JSON string of pilot IDs

// if ($event_id === null || $event_id <= 0 || empty($start_date_str) || $length === null || $length <= 0 || empty($ids_json)) {
//     http_response_code(400);
//     $apiResponse->setError("Missing or invalid required fields (eventid, start, length, ids).")->send();
//     exit;
// }
if ($event_id === null || $event_id <= 0 || 
    empty($start_date_str) || 
    $length === null || !is_numeric($length) || (int)$length <= 0 || // Check if numeric and positive
    empty($ids_json)) {
    http_response_code(400);
    $apiResponse->setError("Missing or invalid required fields (eventid, start, valid positive length, ids).")->send();
    exit;
}
$length = (int)$length;

if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_str)) {
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

function sanitize_id_update($id_str) { // Renamed to avoid conflict if in same scope
    return (strtolower(trim($id_str ?? '')) === "null" || empty(trim($id_str ?? ''))) ? null : (int)$id_str;
}

$tri1_id = sanitize_id_update($tri1_id_str);
$tri2_id = sanitize_id_update($tri2_id_str);
$tre_id = sanitize_id_update($tre_id_str);
$pilot_id1 = sanitize_id_update($pilot_ids_from_json[0] ?? null);
$pilot_id2 = sanitize_id_update($pilot_ids_from_json[1] ?? null);
$pilot_id3 = sanitize_id_update($pilot_ids_from_json[2] ?? null);
$pilot_id4 = sanitize_id_update($pilot_ids_from_json[3] ?? null);

try {
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error.", 500);
    }

    $sql = "UPDATE training_sim_schedule SET 
                start_date = ?, length = ?,
                tri1_id = ?, tri2_id = ?, tre_id = ?, 
                pilot_id1 = ?, pilot_id2 = ?, pilot_id3 = ?, pilot_id4 = ?,
                updated_by = ?, updated_at = NOW()
            WHERE id = ? AND company_id = ?";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }

    $stmt->bind_param("siiiiiiiiiis", 
        $start_date_str, $length,
        $tri1_id, $tri2_id, $tre_id,
        $pilot_id1, $pilot_id2, $pilot_id3, $pilot_id4,
        $user_id, // updated_by
        $event_id, $company_id
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $apiResponse->setSuccess(true)->setMessage("Training event updated successfully.");
        } else {
            // Could be that no data changed, or event not found for company
            $check_stmt = $mysqli->prepare("SELECT id FROM sim_training_schedule WHERE id = ? AND company_id = ?");
            $check_stmt->bind_param("ii", $event_id, $company_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows === 0) {
                 $apiResponse->setError("Training event not found or no changes made.");
                 http_response_code(404);
            } else {
                 $apiResponse->setSuccess(true)->setMessage("No changes detected for the training event.");
            }
            $check_stmt->close();
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