<?php
// craft_add_craft.php - FIXED CONSISTENT VERSION

// Error handling first
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

include_once "db_connect.php";
// REMOVE: require_once 'login_csrf_handler.php'; // ← Remove this conflicting handler

// DEBUG: Log what we're receiving
error_log("DEBUG craft_add_craft: POST data: " . print_r($_POST, true));
error_log("DEBUG craft_add_craft: Session CSRF token: " . ($_SESSION['csrf_token'] ?? 'NOT SET'));

// --- CONSISTENT CSRF VALIDATION (same as your other files) ---
$submitted_token = $_POST['form_token'] ?? ''; // ← CHANGED: csrf_token → form_token

// Check if token is missing
if (empty($submitted_token)) {
    error_log("DEBUG craft_add_craft: CSRF token missing in POST");
    echo json_encode([
        "success" => false, 
        "message" => "Security token missing. Please refresh the page."
    ]);
    exit();
}

// Check if session token exists
if (!isset($_SESSION['csrf_token'])) {
    error_log("DEBUG craft_add_craft: No CSRF token in session");
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate token - use session-based validation (not CSRFHandler)
if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
    error_log("DEBUG craft_add_craft: CSRF validation FAILED");
    // Send current token back (don't regenerate on failure)
    echo json_encode([
        "success" => false, 
        "message" => "Invalid security token. Please refresh the page.",
        "new_csrf_token" => $_SESSION['csrf_token'] // ← Current token, not new one
    ]);
    exit();
}

error_log("DEBUG craft_add_craft: CSRF validation PASSED");

// Rest of your existing code...
if (!isset($mysqli) || $mysqli->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection object (\$mysqli) not found or failed to connect."]);
    exit();
}

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
    echo json_encode(["success" => false, "message" => "Authentication error. Please log in again."]);
    exit();
}

$company_id = (int)$_SESSION["company_id"];
$mysqli->set_charset("utf8");

// Get and validate POST data
$craft_type = isset($_POST['craft']) ? trim($_POST['craft']) : '';
$registration = isset($_POST['registration']) ? trim($_POST['registration']) : '';
$tod = isset($_POST['tod']) ? trim($_POST['tod']) : '';
$alive = isset($_POST['alive']) ? (int)$_POST['alive'] : null;

if (empty($craft_type) || empty($registration) || empty($tod) || $alive === null) {
    echo json_encode(["success" => false, "message" => "All fields are required."]);
    exit();
}

$sql = "INSERT INTO crafts (craft_type, registration, tod, alive, company_id) VALUES (?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($sql);

if (!$stmt) {
    echo json_encode(["success" => false, "message" => "SQL Prepare Error: " . $mysqli->error]);
    exit();
}

$stmt->bind_param("sssii", $craft_type, $registration, $tod, $alive, $company_id);

if (!$stmt->execute()) {
    if ($stmt->errno == 1062) {
         echo json_encode(["success" => false, "message" => "Database Error: A craft with that registration already exists."]);
    } else {
         echo json_encode(["success" => false, "message" => "Database Execute Error: " . $stmt->error]);
    }
    $stmt->close();
    $mysqli->close();
    exit();
}

$craft_id = $mysqli->insert_id;
$stmt->close();
$mysqli->close();

// Success - regenerate token to prevent replay attacks
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo json_encode([
    "success" => true, 
    "message" => "Craft added successfully!", 
    "craft_id" => $craft_id,
    "new_csrf_token" => $_SESSION['csrf_token'] // ← Send new token on success
]);
?>