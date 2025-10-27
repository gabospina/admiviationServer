<?php
// stats_log_book_entry_add.php - v83 FINAL FIXED VERSION

require_once 'stats_api_response.php';
require_once 'db_connect.php';

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
$apiResponse = new ApiResponse();
$stmt = null;

try {
    // --- 1. Authentication & Authorization ---
    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception("Authentication required.", 401);
    }
    $loggedInUserId = (int)$_SESSION["HeliUser"];
    $loggedInUserName = $_SESSION["username"] ?? "Pilot";
    
    // Get company_id from session
    if (!isset($_SESSION['company_id']) || !filter_var($_SESSION['company_id'], FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]])) {
        throw new Exception("Valid Company ID not found in session.", 400);
    }
    $company_id = (int)$_SESSION['company_id'];

    // --- 2. Define and Get Required Inputs ---
    $requiredFields = [
        'date' => "Date",
        'craft_type' => "Craft Type",
        'registration' => "Registration",
        'PIC' => "Pilot in Command",
    ];
    
    $input = [];
    foreach ($requiredFields as $fieldKey => $fieldName) {
        if (empty(trim($_POST[$fieldKey] ?? ''))) {
            throw new Exception("Error: The '$fieldName' field is required.", 400);
        }
        $input[$fieldKey] = trim($_POST[$fieldKey]);
    }

    // --- 3. Get Optional & Numeric Inputs ---
    $input['SIC'] = trim($_POST['SIC'] ?? '');
    $input['route'] = trim($_POST['route'] ?? '');
    $input['ifr'] = filter_input(INPUT_POST, 'ifr', FILTER_VALIDATE_FLOAT);
    $input['vfr'] = filter_input(INPUT_POST, 'vfr', FILTER_VALIDATE_FLOAT);
    $input['hours'] = filter_input(INPUT_POST, 'hours', FILTER_VALIDATE_FLOAT);
    $input['night_time'] = filter_input(INPUT_POST, 'night_time', FILTER_VALIDATE_FLOAT);
    
    // Default to 0.0 if not provided or invalid
    if ($input['ifr'] === null || $input['ifr'] === false) $input['ifr'] = 0.0;
    if ($input['vfr'] === null || $input['vfr'] === false) $input['vfr'] = 0.0;
    if ($input['hours'] === null || $input['hours'] === false) $input['hours'] = 0.0;
    if ($input['night_time'] === null || $input['night_time'] === false) $input['night_time'] = 0.0;

    // --- 4. Custom Validation Logic ---
    if ($input['ifr'] <= 0 && $input['vfr'] <= 0) {
        throw new Exception("Validation Error: You must enter a value for IFR, VFR, or both.", 400);
    }

    $calculatedHours = $input['ifr'] + $input['vfr'];
    if (abs($input['hours'] - $calculatedHours) > 0.01) {
        throw new Exception("Validation Error: Total Hours must be the sum of IFR and VFR.", 400);
    }
    if ($calculatedHours <= 0) {
        throw new Exception("Validation Error: Total Flight Time must be greater than zero.", 400);
    }

    if ($input['night_time'] > $calculatedHours) {
        throw new Exception("Validation Error: Night Time cannot exceed Total Hours.", 400);
    }

    // Date validation
    $dateObject = DateTime::createFromFormat('Y-m-d', $input['date']);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $input['date']) {
        throw new Exception("Invalid date format. Expected YYYY-MM-DD.", 400);
    }
    $db_date = $input['date'];
    
    // --- 5. Determine Crew Roles and IDs ---
    $pic_user_id_to_store = null;
    $sic_user_id_to_store = null;
    $pic_name_to_store = $input['PIC'];
    $sic_name_to_store = $input['SIC'];
    $hour_type = ($input['night_time'] > 0) ? 'night' : 'day';

    $crew_role = $_POST['crew_role'] ?? 'PIC';
    
    if ($crew_role === 'PIC') {
        $pic_user_id_to_store = $loggedInUserId;
        if (empty($pic_name_to_store)) {
             $pic_name_to_store = $loggedInUserName;
        }
    } elseif ($crew_role === 'SIC') {
        $sic_user_id_to_store = $loggedInUserId;
        if (empty($sic_name_to_store)) {
             $sic_name_to_store = $loggedInUserName;
        }
    }
    
    // --- 6. Prepare and Execute SQL INSERT ---
    // ✅ CORRECTED: Match exact table column order
    $query = "INSERT INTO pilot_log_book
                    (company_id, date, craft_type, registration, PIC, SIC, 
                     pic_user_id, sic_user_id, route, ifr, vfr, hours, 
                     night_time, hour_type, user_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) { throw new Exception("SQL Prepare failed: " . $mysqli->error, 500); }

    // ✅ CORRECTED: Parameter order matches table columns
    $stmt->bind_param("isssssiisddddsi",
        $company_id,        // company_id (int)
        $db_date,           // date (string)
        $input['craft_type'], // craft_type (string)
        $input['registration'], // registration (string)
        $pic_name_to_store, // PIC (string)
        $sic_name_to_store, // SIC (string)
        $pic_user_id_to_store, // pic_user_id (int)
        $sic_user_id_to_store, // sic_user_id (int)
        $input['route'],    // route (string)
        $input['ifr'],      // ifr (double)
        $input['vfr'],      // vfr (double)
        $calculatedHours,   // hours (double)
        $input['night_time'], // night_time (double)
        $hour_type,         // hour_type (string)
        $loggedInUserId     // user_id (int) - ✅ ADDED AT THE END
    );

    if (!$stmt->execute()) {
        if ($stmt->errno == 1062) {
             throw new Exception("This flight log might already exist.", 409);
        } else {
             throw new Exception("SQL Execute failed: " . $stmt->error, 500);
        }
    }
    
    $apiResponse->setSuccess(true)->setMessage("Flight hours added successfully.");

} catch (Exception $e) {
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 400;
    http_response_code($httpStatusCode);
    $apiResponse->setSuccess(false)->setError($e->getMessage());
} finally {
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
}

$apiResponse->send();
exit;
?>