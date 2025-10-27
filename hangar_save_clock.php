<?php
session_start();
// Ensure this path is correct relative to save_clock.php
include_once "db_connect.php"; // Make sure this connects to your $mysqli object

// Enable error reporting FOR DEBUGGING ONLY - disable/log on production
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your/php-error.log'); // For production

// Set content type to JSON
header('Content-Type: application/json');

// --- Database Connection Check ---
if ($mysqli->connect_error) {
    error_log("Database Connection Error: " . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

// --- Authentication Check ---
if (!isset($_SESSION["HeliUser"]) || empty($_SESSION["HeliUser"])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}
$user_id = (int)$_SESSION["HeliUser"]; // Ensure user_id is an integer


// =========================================
// Handle GET request (Load settings)
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $mysqli->prepare("SELECT clock_name, clock_tz FROM pilot_info WHERE user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed (GET): (" . $mysqli->errno . ") " . $mysqli->error);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database prepare error (GET)']);
        exit;
    }

    $stmt->bind_param("i", $user_id);

    if (!$stmt->execute()) {
         error_log("Execute failed (GET): (" . $stmt->errno . ") " . $stmt->error);
         http_response_code(500);
         echo json_encode(['status' => 'error', 'message' => 'Database execute error (GET)']);
         $stmt->close();
         exit;
    }

    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if ($data && !empty($data['clock_tz']) && !empty($data['clock_name'])) {
        // Settings found and seem valid
        $response = [
            'status' => 'success',
            'name' => $data['clock_name'],
            'tz' => $data['clock_tz']
            // JS will calculate initial times
        ];
        echo json_encode($response);
    } else {
        // No settings found or they are incomplete, return defaults
        error_log("No valid clock settings found for user_id: $user_id. Returning defaults.");
        echo json_encode([
            'status' => 'success', // Still success from server perspective
            'name' => 'My Clock',   // Default name
            'tz' => 'UTC'        // Default timezone
        ]);
    }
    exit;
}

// =========================================
// Handle POST request (Save settings)
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $timezone = trim($_POST['timezone'] ?? '');
    $name = trim($_POST['name'] ?? '');

    // --- Input Validation ---
    if (empty($name) || empty($timezone)) {
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => 'Clock name and timezone are required.']);
        exit;
    }

    // Validate timezone identifier format (basic check)
    if (preg_match('/^[A-Za-z_\/]+$/', $timezone) !== 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid timezone format provided.']);
        exit;
    }

    // More robust timezone validation using PHP's DateTimeZone
    try {
        new DateTimeZone($timezone);
    } catch (Exception $e) {
        error_log("Invalid timezone provided by user $user_id: $timezone - Error: " . $e->getMessage());
        http_response_code(400); // Bad Request
        echo json_encode(['status' => 'error', 'message' => "Invalid timezone identifier: '{$timezone}'."]);
        exit;
    }

    // --- Database Operation ---
    // Assuming pilot_info record exists, using UPDATE. Add INSERT/UPSERT logic if needed.
    $stmt = $mysqli->prepare("UPDATE pilot_info SET clock_name = ?, clock_tz = ? WHERE user_id = ?");
     if (!$stmt) {
        error_log("Prepare failed (POST): (" . $mysqli->errno . ") " . $mysqli->error);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database prepare error (POST)']);
        exit;
    }

    $stmt->bind_param("ssi", $name, $timezone, $user_id);

    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        $stmt->close(); // Close statement after execute

        if ($affected_rows > 0) {
            // Successfully updated
            error_log("Successfully updated clock settings for user_id: $user_id to $name / $timezone");

             // Calculate current times for immediate feedback in JS (MM-DD-YYYY HH:MI:SS)
            try {
                $localDateTime = new DateTime('now', new DateTimeZone($timezone));
                $utcDateTime = new DateTime('now', new DateTimeZone('UTC'));

                echo json_encode([
                    'status' => 'success',
                    'name' => $name,
                    'tz' => $timezone,
                    'localTime' => $localDateTime->format('m-d-Y H:i:s'), // Format for JS display
                    'utcTime' => $utcDateTime->format('m-d-Y H:i:s')      // Format for JS display
                ]);
            } catch (Exception $e) {
                 // Should not happen due to validation above, but handle just in case
                 error_log("Error creating DateTime objects after save for user $user_id: " . $e->getMessage());
                 echo json_encode([
                    'status' => 'success', // Save was successful, time calculation failed
                    'name' => $name,
                    'tz' => $timezone,
                    'message' => 'Settings saved, but current time calculation failed.'
                 ]);
            }
        } else {
             // 0 rows affected - check if user exists or data was unchanged
             error_log("Clock settings update attempt for user_id: $user_id resulted in 0 affected rows. Checking user existence or if data was identical.");

             // Optional: Check if user actually exists if you suspect they might be deleted between login and save
             $checkStmt = $mysqli->prepare("SELECT 1 FROM pilot_info WHERE user_id = ?");
             $checkStmt->bind_param("i", $user_id);
             $checkStmt->execute();
             $userExists = $checkStmt->get_result()->num_rows > 0;
             $checkStmt->close();

             if (!$userExists) {
                 http_response_code(404); // Not Found
                 echo json_encode(['status' => 'error', 'message' => 'User record not found. Cannot save settings.']);
             } else {
                 // User exists, data was likely the same - treat as success
                 echo json_encode([
                    'status' => 'success',
                    'name' => $name,
                    'tz' => $timezone,
                    'message' => 'Settings are already up to date.'
                    // Might still want to return current times here too
                 ]);
             }
        }
    } else {
        // Execute failed
        $error_msg = $stmt->error;
        $errno = $stmt->errno;
        $stmt->close(); // Close statement even on error
        error_log("Database execute error (POST) for user_id $user_id: ($errno) $error_msg");
        http_response_code(500); // Internal Server Error
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error during save.' // Avoid exposing raw DB errors to client
        ]);
    }
    exit;
}

// Fallback for unexpected request methods
http_response_code(405); // Method Not Allowed
echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
?>