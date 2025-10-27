<?php
// training_update_date.php v84
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
    // X-Editable expects 4xx for error or a specific error message format.
    // For simplicity, we'll send JSON error.
    $apiResponse->setError("Authentication or required session data missing.")->send();
    exit;
}

$company_id = (int)$_SESSION['company_id'];
$user_id = (int)$_SESSION['HeliUser']; // For updated_by if you add it
$admin_level = (int)$_SESSION['admin'];

if (!($admin_level > 0 && $admin_level != 2 && $admin_level != 4)) {
    http_response_code(403);
    $apiResponse->setError("Permission denied to update training availability.")->send();
    exit;
}

$pk_id = isset($_POST["pk"]) ? (int)$_POST["pk"] : null;         // Primary key of the row
$field_name = $_POST["name"] ?? null; // 'on' or 'off' (maps to date_on or date_off)
$new_value_date_str = $_POST["value"] ?? null; // The new date string

if ($pk_id === null || $pk_id <= 0 || empty($field_name) || empty($new_value_date_str)) {
    http_response_code(400); // X-Editable specific error
    header('HTTP/1.1 400 Bad Request', true, 400); // Make sure X-Editable sees it as an error
    echo "Missing required fields (pk, name, value).";
    exit;
}

if (!in_array($field_name, ['on', 'off'])) {
    http_response_code(400);
    header('HTTP/1.1 400 Bad Request', true, 400);
    echo "Invalid field name specified.";
    exit;
}
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $new_value_date_str)) {
    http_response_code(400);
    header('HTTP/1.1 400 Bad Request', true, 400);
    echo "Invalid date format. Use YYYY-MM-DD.";
    exit;
}

$db_column_name = ($field_name === 'on') ? 'date_on' : 'date_off';

try {
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error."); // Error caught below
    }

    // Before updating, ensure the change is valid (e.g., date_on not after date_off)
    $current_dates_stmt = $mysqli->prepare("SELECT date_on, date_off FROM training_availability WHERE id = ? AND company_id = ?");
    $current_dates_stmt->bind_param("ii", $pk_id, $company_id);
    $current_dates_stmt->execute();
    $current_dates_result = $current_dates_stmt->get_result();
    if ($current_dates_result->num_rows === 0) {
        throw new Exception("Record not found or not authorized.", 404);
    }
    $current_row = $current_dates_result->fetch_assoc();
    $current_dates_stmt->close();

    $date_on_to_check = ($db_column_name === 'date_on') ? $new_value_date_str : $current_row['date_on'];
    $date_off_to_check = ($db_column_name === 'date_off') ? $new_value_date_str : $current_row['date_off'];

    if (strtotime($date_on_to_check) > strtotime($date_off_to_check)) {
        throw new Exception("Start date (on) cannot be after end date (off).", 400);
    }

    // Using a dynamic column name requires careful handling; $db_column_name is whitelisted.
    $sql = "UPDATE training_availability SET `$db_column_name` = ?, updated_at = NOW() WHERE id = ? AND company_id = ?";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error);
    }

    $stmt->bind_param("sii", $new_value_date_str, $pk_id, $company_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // X-Editable typically expects a 200 OK on success, no content or the new value.
            // The original script printed the new value. We'll do that for compatibility.
            // No need for full ApiResponse success message if X-Editable handles it.
            echo htmlspecialchars($new_value_date_str); // Echo new value on success for X-Editable
            exit;
        } else {
            // No change, but still a success from X-Editable's perspective
            echo htmlspecialchars($new_value_date_str);
            exit;
        }
    } else {
        throw new Exception("SQL execute error: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode); // Set explicit HTTP error code
    header('HTTP/1.1 ' . $httpStatusCode . ' Error', true, $httpStatusCode); // X-Editable needs this
    echo $e->getMessage(); // X-Editable will display this message
    exit;
}
?>