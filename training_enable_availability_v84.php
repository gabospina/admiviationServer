<?php
// training_enable_availability.php v84
// (Adjusted to use training_availability table with date_on and date_off for ranges)

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
require_once 'db_connect.php';          // VERIFY PATH
require_once 'stats_api_response.php';  // VERIFY PATH

$apiResponse = new ApiResponse();

// --- Session & Permission Checks ---
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id']) || !isset($_SESSION['admin'])) {
    http_response_code(401);
    $apiResponse->setError("Authentication or required session data missing.")->send();
    exit;
}

$company_id = (int)$_SESSION['company_id'];
$user_id = (int)$_SESSION['HeliUser']; // For created_by
$admin_level = (int)$_SESSION['admin'];

// Access Control
if (!($admin_level > 0 && $admin_level != 2 && $admin_level != 4)) {
    http_response_code(403);
    $apiResponse->setError("Permission denied to enable training availability.")->send();
    exit;
}

$start_date_str = $_POST["start"] ?? null;
$end_date_str = $_POST["end"] ?? null;
// The 'isSingleDay' POST variable is NOT needed for this version of the script,
// as it handles ranges directly with date_on and date_off.

if (empty($start_date_str) || empty($end_date_str)) {
    http_response_code(400);
    $apiResponse->setError("Start and end dates are required.")->send();
    exit;
}
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_str) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date_str)) {
    http_response_code(400);
    $apiResponse->setError("Invalid date format. Use YYYY-MM-DD.")->send();
    exit;
}
if (strtotime($start_date_str) > strtotime($end_date_str)) {
    http_response_code(400);
    $apiResponse->setError("Start date cannot be after end date.")->send();
    exit;
}

try {
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error.", 500);
    }

    $mysqli->begin_transaction();

    // Check if this exact range (or a fully encompassing one) already exists to avoid duplicates/redundancy.
    // This is a simple check; more complex merging logic could be added if needed.
    $check_sql = "SELECT id FROM training_availability 
                  WHERE company_id = ? AND date_on <= ? AND date_off >= ?";
    $check_stmt = $mysqli->prepare($check_sql);
    if (!$check_stmt) throw new Exception("Check SQL prepare error: ".$mysqli->error);
    $check_stmt->bind_param("iss", $company_id, $start_date_str, $end_date_str); // Check if new start is within an existing range that also covers new end
    $check_stmt->execute();
    $check_result_encompassing = $check_stmt->get_result();
    $check_stmt->close();

    if ($check_result_encompassing->num_rows > 0) {
        // If an existing range already fully covers the new range, we consider it enabled.
        $apiResponse->setSuccess(true)->setMessage("Selected date range is already covered by existing availability.");
    } else {
        // More precise check for exact duplicate or if new range is subset of existing
        $check_exact_sql = "SELECT id FROM training_availability 
                            WHERE company_id = ? AND date_on = ? AND date_off = ?";
        $check_exact_stmt = $mysqli->prepare($check_exact_sql);
        if (!$check_exact_stmt) throw new Exception("Check Exact SQL prepare error: ".$mysqli->error);
        $check_exact_stmt->bind_param("iss", $company_id, $start_date_str, $end_date_str);
        $check_exact_stmt->execute();
        $check_exact_result = $check_exact_stmt->get_result();
        $check_exact_stmt->close();

        if ($check_exact_result->num_rows > 0) {
            $apiResponse->setSuccess(true)->setMessage("This exact availability period already exists.");
        } else {
            // No exact match or fully encompassing range found, so insert the new range.
            // Note: This doesn't merge adjacent/overlapping ranges yet. It just adds new distinct periods.
            $sql = "INSERT INTO training_availability (company_id, date_on, date_off, created_by) VALUES (?, ?, ?, ?)";
            $stmt = $mysqli->prepare($sql);
            if (!$stmt) {
                throw new Exception("Insert SQL prepare error: " . $mysqli->error, 500);
            }
            $stmt->bind_param("issi", $company_id, $start_date_str, $end_date_str, $user_id);

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $apiResponse->setSuccess(true)->setMessage("Training availability enabled for the selected range.");
                } else {
                    throw new Exception("Failed to enable availability, no rows affected.", 500);
                }
            } else {
                throw new Exception("SQL execute error: " . $stmt->error, 500);
            }
            $stmt->close();
        }
    }

    $mysqli->commit();

} catch (Exception $e) {
    if (isset($mysqli) && $mysqli->ping()) {
      $mysqli->rollback();
    }
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    $apiResponse->setError("Server error: " . $e->getMessage());
    error_log("Error in training_enable_availability.php: " . $e->getMessage());
}

$apiResponse->send();
?>