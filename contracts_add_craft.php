<?php
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

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// REMOVED: // REMOVED: require_once 'login_csrf_handler.php';
include_once "db_connect.php";

$response = ['success' => false, 'message' => 'An unknown error occurred.', 'new_csrf_token' => $_SESSION['csrf_token']];

try {
    // ADD CSRF VALIDATION HERE:
    if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }

    // Check if the user is logged in and has the company_id
    if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
        throw new Exception("You are not logged in properly or company ID not found.", 401);
    }

    $user_id = (int)$_SESSION["HeliUser"];
    $company_id = (int)$_SESSION["company_id"];

    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error, 500);
    }

    $mysqli->set_charset("utf8");

    // Get the POST data
    $craft = isset($_POST['craft']) ? trim($_POST['craft']) : null;
    $registration = isset($_POST['registration']) ? trim($_POST['registration']) : null;
    $tod = isset($_POST['tod']) ? trim($_POST['tod']) : null;
    $alive = isset($_POST['alive']) ? (int)$_POST['alive'] : null;

    // Validate input
    if (empty($craft) || empty($registration) || empty($tod) || $alive === null) {
        throw new Exception("All fields are required.", 400);
    }

    // Prepare the SQL query
    $sql = "INSERT INTO crafts (craft_type, registration, tod, alive, company_id) VALUES (?, ?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);

    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $mysqli->error, 500);
    }

    $stmt->bind_param("sssii", $craft, $registration, $tod, $alive, $company_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error, 500);
    }

    // Get the ID of the newly inserted craft
    $craft_id = $mysqli->insert_id;
    $stmt->close();
    $mysqli->close();

    $response['success'] = true;
    $response['message'] = "Craft added successfully!";
    $response['craft_id'] = $craft_id;
    } catch (Exception $e) {
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
    }

echo json_encode($response);
?>