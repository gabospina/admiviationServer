<?php
// stats_add_experience.php v83 (Modified for detailed initial experience)

require_once 'stats_api_response.php';
require_once 'db_connect.php';

if (session_status() == PHP_SESSION_NONE) { session_start();

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

$response = new ApiResponse();
header('Content-Type: application/json');
$stmt = null;

try {
    // --- Authentication & Get User/Company ID ---
    if (!isset($_SESSION["HeliUser"])) { throw new Exception("Authentication required.", 401); }
    $userId = (int)$_SESSION["HeliUser"];
    if (!isset($_SESSION['company_id']) || !filter_var($_SESSION['company_id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        throw new Exception("Valid Company ID not found in session.", 400);
    }
    $company_id = (int)$_SESSION['company_id'];

    // --- Validate Input (from JS: craft_type, pic_ifr_hours, pic_vfr_hours, etc.) ---
    $required_fields = [
        'craft_type', 
        'pic_ifr_hours', 'pic_vfr_hours', 'pic_night_hours',
        'sic_ifr_hours', 'sic_vfr_hours', 'sic_night_hours'
    ];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) { // Check if set, allow 0 but not missing/empty string for hours
            if (strpos($field, '_hours') !== false && $_POST[$field] === '') {
                 throw new Exception("Hour field '$field' cannot be empty, use 0 if none.", 400);
            } elseif (strpos($field, '_hours') === false && empty($_POST[$field])) {
                 throw new Exception("Missing required field: " . $field, 400);
            } elseif (!isset($_POST[$field])) { // For hour fields that are not just empty string
                 throw new Exception("Missing required field: " . $field, 400);
            }
        }
    }

    $craft_type = trim(filter_input(INPUT_POST, 'craft_type', FILTER_SANITIZE_STRING));
    if (empty($craft_type)) { throw new Exception("Craft Type cannot be empty.", 400); }

    // Sanitize and validate all hour inputs
    $initial_pic_ifr_hours   = filter_input(INPUT_POST, 'pic_ifr_hours', FILTER_VALIDATE_FLOAT);
    $initial_pic_vfr_hours   = filter_input(INPUT_POST, 'pic_vfr_hours', FILTER_VALIDATE_FLOAT);
    $initial_pic_night_hours = filter_input(INPUT_POST, 'pic_night_hours', FILTER_VALIDATE_FLOAT);
    $initial_sic_ifr_hours   = filter_input(INPUT_POST, 'sic_ifr_hours', FILTER_VALIDATE_FLOAT);
    $initial_sic_vfr_hours   = filter_input(INPUT_POST, 'sic_vfr_hours', FILTER_VALIDATE_FLOAT);
    $initial_sic_night_hours = filter_input(INPUT_POST, 'sic_night_hours', FILTER_VALIDATE_FLOAT);

    // Check if any filter failed or value is negative
    if ($initial_pic_ifr_hours === false   || $initial_pic_ifr_hours < 0)   throw new Exception("Invalid PIC IFR Hours.", 400);
    if ($initial_pic_vfr_hours === false   || $initial_pic_vfr_hours < 0)   throw new Exception("Invalid PIC VFR Hours.", 400);
    if ($initial_pic_night_hours === false || $initial_pic_night_hours < 0) throw new Exception("Invalid PIC Night Hours.", 400);
    if ($initial_sic_ifr_hours === false   || $initial_sic_ifr_hours < 0)   throw new Exception("Invalid SIC IFR Hours.", 400);
    if ($initial_sic_vfr_hours === false   || $initial_sic_vfr_hours < 0)   throw new Exception("Invalid SIC VFR Hours.", 400);
    if ($initial_sic_night_hours === false || $initial_sic_night_hours < 0) throw new Exception("Invalid SIC Night Hours.", 400);

    // Validate night hours against respective IFR+VFR totals for PIC and SIC
    if ($initial_pic_night_hours > ($initial_pic_ifr_hours + $initial_pic_vfr_hours)) {
        throw new Exception("Initial PIC Night hours cannot exceed sum of PIC IFR and PIC VFR hours.", 400);
    }
    if ($initial_sic_night_hours > ($initial_sic_ifr_hours + $initial_sic_vfr_hours)) {
        throw new Exception("Initial SIC Night hours cannot exceed sum of SIC IFR and SIC VFR hours.", 400);
    }
    
    // --- Database Interaction ---
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) { throw new Exception("DB connection error.", 500); }

    // UPSERT query for pilot_initial_experience
    // Adds the submitted hours to any existing hours for that user/company/craft combo
    $sql = "INSERT INTO pilot_initial_experience 
                (user_id, company_id, craft_type, 
                 initial_pic_ifr_hours, initial_pic_vfr_hours, initial_pic_night_hours,
                 initial_sic_ifr_hours, initial_sic_vfr_hours, initial_sic_night_hours)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                initial_pic_ifr_hours   = initial_pic_ifr_hours   + VALUES(initial_pic_ifr_hours),
                initial_pic_vfr_hours   = initial_pic_vfr_hours   + VALUES(initial_pic_vfr_hours),
                initial_pic_night_hours = initial_pic_night_hours + VALUES(initial_pic_night_hours),
                initial_sic_ifr_hours   = initial_sic_ifr_hours   + VALUES(initial_sic_ifr_hours),
                initial_sic_vfr_hours   = initial_sic_vfr_hours   + VALUES(initial_sic_vfr_hours),
                initial_sic_night_hours = initial_sic_night_hours + VALUES(initial_sic_night_hours)
            "; 
            
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) { throw new Exception("Prepare failed: ".$mysqli->error, 500); }

    // Bind parameters: iisdddddd (2 integers, 6 doubles)
    if (!$stmt->bind_param("iisdddddd", 
            $userId, $company_id, $craft_type, 
            $initial_pic_ifr_hours, $initial_pic_vfr_hours, $initial_pic_night_hours,
            $initial_sic_ifr_hours, $initial_sic_vfr_hours, $initial_sic_night_hours
        )) {
         throw new Exception("Bind Param failed: ".$stmt->error, 500);
    }
    
    if (!$stmt->execute()) { throw new Exception("Execute failed: ".$stmt->error, 500); }

    $response->setSuccess(true)->setMessage("Initial experience for " . htmlspecialchars($craft_type) . " updated successfully.");

} catch (Exception $e) {
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
    http_response_code($httpStatusCode);
    $response->setSuccess(false)->setError($e->getMessage());
    error_log("Error in " . basename(__FILE__) . " on line " . $e->getLine() . ": " . $e->getMessage() . " Context: " . json_encode($_POST));
} finally {
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    // if (isset($mysqli) && ...) { $mysqli->close(); }
}

$response->send();
exit;
?>