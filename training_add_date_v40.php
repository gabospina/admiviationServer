<?php
// assets/php/add_training_date.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json'); // Changed from raw print to JSON
require_once 'db_connect.php';
require_once 'stats_api_response.php';

$apiResponse = new ApiResponse();

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
    $apiResponse->setError("Permission denied to manage training availability.")->send();
    exit;
}

$start_date_str = $_POST["start"] ?? null; // From #date-start input
$end_date_str = $_POST["end"] ?? null;   // From #date-end input

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

// Ensure start_date is not after end_date
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

    // Optional: Check for overlapping existing availability periods for the same company
    // This can get complex if partial overlaps are disallowed.
    // For simplicity, we'll allow overlaps for now, or you can add stricter checks.
    // Example check (disallow if exact range or fully contained):
    /*
    $check_sql = "SELECT id FROM training_availability 
                  WHERE company_id = ? AND 
                        ((date_on <= ? AND date_off >= ?) OR -- New range is within an existing one
                         (date_on >= ? AND date_off <= ?) OR -- Existing range is within new one
                         (date_on BETWEEN ? AND ?) OR (date_off BETWEEN ? AND ?))"; 
    $check_stmt = $mysqli->prepare($check_sql);
    // Bind params: company_id, start_date_str, end_date_str, start_date_str, end_date_str, start_date_str, end_date_str, start_date_str, end_date_str
    // If $check_stmt->get_result()->num_rows > 0, then overlap exists.
    */

    $sql = "INSERT INTO training_availability (company_id, date_on, date_off, created_by) VALUES (?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }

    $stmt->bind_param("issi", $company_id, $start_date_str, $end_date_str, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Original script printed "success:ID". JS expects this if using X-Editable directly.
            // For consistency with ApiResponse:
            $apiResponse->setSuccess(true)
                        ->setMessage("Training availability period added.")
                        ->setData(['id' => $stmt->insert_id, 'on' => $start_date_str, 'off' => $end_date_str]);
        } else {
            throw new Exception("Failed to add availability period, no rows affected.", 500);
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