<?php
/**
 * File: send_direct_sms.php
 * Sends a custom SMS message via Twilio to a list of selected recipients (pilots or custom numbers).
 * Logs the attempts to the dedicated `direct_sms_log` table.
 * Uses modern Twilio PHP SDK (v7+) via Composer.
 */

// Use the required Twilio namespaces AT THE TOP
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Error Reporting & Output Buffering ---
error_reporting(E_ALL); // Report all errors during development
ini_set('display_errors', 0); // Don't display errors directly to user (breaks JSON)
ini_set('log_errors', 1); // Log errors to PHP error log
// Example: Set a specific log file path in php.ini or use error_log() explicitly
// ini_set('error_log', '/path/to/your/php-error.log');
ob_start(); // Start output buffering

// Set response header to JSON *before* any potential accidental output
// Note: If a fatal error occurs before this, it might still output HTML.
// The primary fix is avoiding the fatal error itself.
header('Content-Type: application/json');

// --- Initialize Response ---
// Initialize response structure early
$response = ['success' => false, 'message' => 'Initialization failed.'];
$mysqli = null; // Initialize mysqli variable

// --- Main Try Block for Critical Errors ---
try {
    // --- DEPENDENCIES ---
    // Include DB connection *after* use statements
    include_once "db_connect.php"; // Needs to establish $mysqli connection object

    // Check if DB connection was successful
    if (!$mysqli || $mysqli->connect_error) {
        // Use mysqli_connect_error() if $mysqli isn't an object yet
        throw new Exception('Database connection failed: ' . ($mysqli ? $mysqli->connect_error : mysqli_connect_error()));
    }

    // Include Twilio library using Composer autoloader *after* use statements
    // Adjust path if 'vendor' directory is not in the same directory as this script
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
         throw new Exception('Composer autoload file not found at: ' . $autoloadPath);
    }
    require_once $autoloadPath;

    // --- Twilio Credentials ---
    $twilioAccountSid = "ACe6e90a6871365a830192e0bcac69fb3a";
    // $twilioAuthToken = "3b58a7a528c1800495a8d267ce1c8042";
    $twilioAuthToken = "620d305bf6795f1cf05222be5d0e222c";
    $twilioFromNumber = "+16513272353";
    $twilioClient = new Client($twilioAccountSid, $twilioAuthToken);

    // Get user sending the message (needed for logging)
    // $user_sending_id = $_SESSION['user_id'] ?? null;
    $sending_user_id = $_SESSION['HeliUser'] ?? null; // update v55

    // --- Security Check (Basic Login check) ---
    if (!isset($_SESSION["HeliUser"]) || !$sending_user_id) {
        // It's better to throw exception here for centralized handling below
        throw new Exception('Authentication Required.', 401); // Use code for HTTP status
    }

    // --- Get Input Data ---
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE); // TRUE for associative array

    // Check for JSON decoding errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input received. Error: ' . json_last_error_msg(), 400); // Bad Request
    }

    $recipients = $input['recipients'] ?? []; // Expecting array of {id, phone, name}
    $messageBody = trim($input['message'] ?? '');

    // Validate input data
    if (empty($recipients) || !is_array($recipients)) {
        throw new Exception('No recipients provided or invalid format.', 400); // Bad Request
    }
    if (empty($messageBody)) {
        throw new Exception('Message body cannot be empty.', 400); // Bad Request
    }
    // --- End Input Data ---

    // --- Initialize Twilio Client ---
    try {
        $twilioClient = new Client($twilioAccountSid, $twilioAuthToken);
    } catch (Exception $e) { // Catch Twilio SDK config errors specifically if possible
        throw new Exception('Twilio client initialization failed: ' . $e->getMessage()); // 500 Internal Server Error
    }

    // --- Process Recipients ---
    $sent_count = 0;
    $failed_count = 0;
    $errors = []; // Store specific error messages for details

    foreach ($recipients as $index => $recipient) {
        // Use null coalescing operator for cleaner defaults (or ternary for < PHP 7.0)
        // Note: Using filter_var below already handles null/missing 'id' gracefully with the default option

        // Get raw values, provide defaults for logging/error messages if needed
        $target_user_id_raw = $recipient['id'] ?? null; // Use ?? if PHP >= 7.0
        // OR for PHP < 7.0: $target_user_id_raw = isset($recipient['id']) ? $recipient['id'] : null;
        $target_phone_raw = $recipient['phone'] ?? null; // Use ?? if PHP >= 7.0
        // OR for PHP < 7.0: $target_phone_raw = isset($recipient['phone']) ? $recipient['phone'] : null;
        $target_name = trim($recipient['name'] ?? ('Recipient #' . ($index + 1))); // Use ?? if PHP >= 7.0
        // OR for PHP < 7.0: $target_name = trim(isset($recipient['name']) ? $recipient['name'] : ('Recipient #' . ($index + 1)));

        // Filter and validate the User ID immediately
        $target_user_id = filter_var($target_user_id_raw, FILTER_VALIDATE_INT, ['options' => ['default' => null]]);

        // Initialize variables for this iteration
        $valid_phone = null;
        $send_status = false;
        $twilio_message_sid = null;
        $error_for_this_recipient = null;
        $log_status = 'Attempted'; // Default log status

        try {
            // Step 1: Validate essential raw data (Phone is crucial)
            if (empty($target_phone_raw)) {
                 // Use the potentially generated name if the original was empty
                throw new Exception('Missing phone number for recipient: ' . $target_name);
            }
            // target_user_id can be null for custom numbers, which is fine

            // Step 2: Clean and Validate Phone Number Format (Robust E.164 check)
            // (Your existing phone validation logic starting around line 127 seems good)
            // Remove all non-digit characters except '+' at the beginning
            $phone_clean = preg_replace('/[^\d]/', '', $target_phone_raw);

            // If '+' is missing, assume US/Canada (+1) for 10 or 11 digits starting with 1
            if (strpos($target_phone_raw, '+') !== 0) {
                if (strlen($phone_clean) == 10) {
                    $phone_clean = '+1' . $phone_clean;
                } elseif (strlen($phone_clean) == 11 && $phone_clean[0] == '1') {
                    $phone_clean = '+' . $phone_clean;
                } else {
                    // Cannot reliably add country code, log error and skip
                    throw new Exception('Invalid non-E.164 format (cannot determine country code)');
                }
            } else {
                 // If starts with '+', use it directly after cleaning other chars
                 $phone_clean = '+' . preg_replace('/[^\d]/', '', substr($target_phone_raw, 1));
            }


            // Step 3: Final E.164 Regex Validation
            // Ensures it starts with +, followed by 1-9, then 1 to 14 digits.
            if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone_clean)) {
                throw new Exception('Invalid E.164 format after cleaning (' . $phone_clean . ')');
            }
            $valid_phone = $phone_clean; // Phone is validated


            // Step 4: Attempt to Send via Twilio
            error_log("Attempting to send SMS via Twilio to: {$valid_phone} (Name: {$target_name})"); // Log before sending
            $messageResult = $twilioClient->messages->create(
                $valid_phone,
                [
                    'from' => $twilioFromNumber,
                    'body' => $messageBody // Use the common message body
                ]
            );

            // Step 5: Check Twilio Result
            if ($messageResult && $messageResult->sid) {
                $send_status = true;
                $twilio_message_sid = $messageResult->sid;
                $log_status = 'Sent'; // Or maybe 'Queued' based on Twilio status if available
                error_log("Direct SMS Queued to {$valid_phone} for {$target_name}. SID: {$twilio_message_sid}");
            } else {
                // This case might indicate an unexpected response structure from Twilio SDK
                throw new Exception('Twilio API call succeeded but did not return a valid Message SID.');
            }

        } catch (TwilioException $e) { // Catch specific Twilio API errors
            $error_code = $e->getCode();
            $error_for_this_recipient = "Twilio Error ({$error_code}): " . $e->getMessage();
            // Use ternary operator for PHP compatibility check / potential parse issue workaround
            $phone_for_log = isset($valid_phone) ? $valid_phone : $target_phone_raw;
            error_log("Twilio Exception for {$target_name} ({$phone_for_log}): {$error_for_this_recipient}");
            $send_status = false; // Ensure status is failed
            $log_status = 'Failed'; // Log as Failed
        } catch (Exception $e) { // Catch other errors (validation, general PHP)
            $error_for_this_recipient = "Processing Error: " . $e->getMessage();
            // Use ternary operator for PHP compatibility check / potential parse issue workaround
            $phone_for_log = isset($valid_phone) ? $valid_phone : $target_phone_raw;
            error_log("General Exception for {$target_name} ({$phone_for_log}): {$error_for_this_recipient}");
            $send_status = false; // Ensure status is failed
            $log_status = 'Failed'; // Log validation/processing errors as Failed
        }

        // Step 6: Log the attempt REGARDLESS OF SUCCESS/FAILURE inside the loop
        log_direct_sms_attempt(
            $mysqli,
            $sending_user_id,      // User who initiated send
            $target_user_id,       // Target User ID (can be null for custom numbers)
            $target_name,          // Target Name
            //(isset($valid_phone) && $valid_phone !== null) ? $valid_phone : $target_phone_raw,
            ($valid_phone ?? $target_phone_raw), // Log the validated phone if available, else raw
            $messageBody,          // The message content
            $log_status,           // Status based on outcome ('Sent', 'Failed')
            $twilio_message_sid    // Twilio SID (null if failed/not sent)
        );

        // Step 7: Update counters and store specific errors for the final response
        if ($send_status) {
            $sent_count++;
        } else {
            $failed_count++;
            // Add specific error message for this recipient to the list
            $errors[] = "{$target_name} ({$target_phone_raw}): " . ($error_for_this_recipient ?: 'Unknown send failure');
        }

    } // End foreach recipient loop

    // --- Prepare Final JSON Response ---
    // Based on counts after processing ALL recipients
    $response['success'] = ($sent_count > 0); // Consider success if at least one message sent
    if ($sent_count > 0 && $failed_count > 0) {
        $response['message'] = "Direct SMS Processed: {$sent_count} sent, {$failed_count} failed.";
    } elseif ($sent_count > 0 && $failed_count == 0) {
        $response['message'] = "Direct SMS: All {$sent_count} messages sent successfully.";
    } elseif ($sent_count == 0 && $failed_count > 0) {
        $response['success'] = false; // Explicitly false if none were sent
        $response['message'] = "All {$failed_count} direct SMS attempts failed.";
    } else { // ($sent_count == 0 && $failed_count == 0) -> likely no valid recipients processed
        $response['success'] = false;
        $response['message'] = "No recipients found with valid data to attempt sending.";
    }

    if (!empty($errors)) {
        // Limit number of errors shown in response if list is very long
        $response['details'] = array_slice(array_unique($errors), 0, 10); // Show up to 10 unique errors
    }


} catch (Exception $e) {
    // Catch critical errors outside the loop (DB connection, Auth, JSON parse, Twilio init)
    $errorCode = $e->getCode(); // Get code if set (like 401, 400)
    $errorMessage = $e->getMessage();
    error_log("Critical error in send_direct_sms.php: [Code {$errorCode}] " . $errorMessage . " - Input: " . $inputJSON);

    // Clean any partial output buffer before sending JSON error
    if (ob_get_level()) ob_end_clean(); // Clean buffer

    // Set appropriate HTTP status code
    if ($errorCode == 401) { // Authentication
        http_response_code(401);
        $response['error'] = $errorMessage;
    } elseif ($errorCode == 400) { // Bad Request (Invalid JSON, Missing data)
        http_response_code(400);
        $response['error'] = $errorMessage;
    } else { // Includes DB errors, Twilio init errors, other general Exceptions
        http_response_code(500); // Internal Server Error
        $response['error'] = 'Server error processing request. Please check server logs for details.'; // Keep error generic for client
    }

    // Ensure response format indicates failure
    $response['success'] = false;
    if (isset($response['message'])) unset($response['message']); // Remove default/success message if error occurs
    // Add error detail if not already set by specific code block above
    if (!isset($response['error'])) $response['error'] = $errorMessage;


} finally {
    // Ensure database connection is closed if it was opened
     if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->ping()) {
        $mysqli->close();
     }
}

// --- Output the final JSON response ---
// Ensure clean output buffer again just before echoing JSON
if (ob_get_level()) ob_end_clean();
// Re-set header just in case it was lost or overwritten
header('Content-Type: application/json');
// Prevent browser caching of the response
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

echo json_encode($response);
exit(); // Ensure script termination after output


// --- Helper Function for Logging Direct SMS ---
/**
 * Logs a Direct SMS attempt to the dedicated `direct_sms_log` table.
 * Assumes connection object $db is mysqli.
 */
function log_direct_sms_attempt($db, $sender_id, $target_id, $target_name, $recipient_contact, $message_body, $status, $provider_message_id = null) {
    // Basic check for valid DB connection
    if (!$db || !($db instanceof mysqli) || !$db->ping()) {
        error_log("log_direct_sms_attempt: Cannot log, invalid DB connection provided.");
        return; // Don't proceed if DB connection is bad
    }

    // Prepare statement
    $sql_log = "INSERT INTO sms_direct_log
                    (sending_user_id, target_user_id, target_pilot_name, recipient_contact,
                     message_body, status, sent_at, provider_message_id)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)";

    $stmt_log = $db->prepare($sql_log);

    if ($stmt_log) {
         try {
             // Bind parameters: i = integer, s = string
             // target_id might be null, bind_param handles null correctly for nullable columns.
             $stmt_log->bind_param("iisssss",
                 $sender_id,         // i: user_sending_id (Assuming it's always set)
                 $target_id,         // i: target_user_id (Can be null)
                 $target_name,       // s: target_pilot_name
                 $recipient_contact, // s: recipient_contact
                 $message_body,      // s: message_body
                 $status,            // s: status ('Sent', 'Failed', 'Attempted')
                 $provider_message_id // s: provider_message_id (Can be null)
             );

             if (!$stmt_log->execute()) {
                 // Log specific MySQL error if execution fails
                 error_log("Direct SMS Log Execute Error: (" . $stmt_log->errno . ") " . $stmt_log->error . " | SQL: " . $sql_log);
             }
             $stmt_log->close();
         } catch (Exception $e) {
             // Catch potential errors during bind/execute
             error_log("Exception during log_direct_sms_attempt (bind/execute): ".$e->getMessage());
             if ($stmt_log instanceof mysqli_stmt) $stmt_log->close(); // Ensure statement is closed on exception
         }
    } else {
        // Log specific MySQL error if preparation fails
        error_log("Direct SMS Log Prepare Error: (" . $db->errno . ") " . $db->error . " | SQL: " . $sql_log);
    }
}
?>