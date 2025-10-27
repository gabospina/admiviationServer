<?php
/**
 * File: daily_manager_send_notifications.php
 * Sends SMS notifications via Twilio for selected schedule entries
 * and logs the attempts to the notifications_log table.
 * Uses modern Twilio PHP SDK (v7+) via Composer.
 */

// Start session if not already started
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
// Set response header to JSON
header('Content-Type: application/json');

// --- DEPENDENCIES ---
include_once "db_connect.php"; // Ensure this path is correct relative to this file

// --- CSRF VALIDATION ---
if (!CSRFHandler::validateToken($_POST['csrf_token'] ?? '')) {
        // Token validation failed - regenerate for security
        $response['new_csrf_token'] = CSRFHandler::generateToken();
    $response['error'] = 'Invalid security token. Please refresh the page.';
    http_response_code(403); // Forbidden
    echo json_encode($response);
    exit();
}

// Include Twilio library using Composer autoloader
// Make sure 'vendor/autoload.php' exists and is accessible from this script's location.
// Common paths might be '../vendor/autoload.php' or just 'vendor/autoload.php'
// Adjust the path based on your project structure.
require_once __DIR__ . 'vendor/autoload.php'; // Assumes vendor directory is in the parent directory or same directory

// Use the required Twilio namespaces
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;
include_once "db_connect.php";

// --- Twilio Credentials ---
// Credentials provided by the user
$twilioAccountSid = "ACe6e90a6871365a830192e0bcac69fb3a";
$twilioAuthToken = "3b58a7a528c1800495a8d267ce1c8042";
// Verified Twilio Phone Number provided by the user
$twilioFromNumber = "+12317972593";

// --- Initialize Response ---
$response = ['success' => false, 'new_csrf_token' => $_SESSION['csrf_token']];

// --- Security Check ---
$session_user_permissions = $_SESSION['user_permissions'] ?? [];
// Permission name defined in your 'permissions' table required to send notifications
$required_permission = 'send_sms'; // Verify this matches your permissions table entry

if (!isset($_SESSION["HeliUser"]) || !in_array($required_permission, $session_user_permissions)) {
    $response['error'] = 'Permission Denied to send notifications.';
    http_response_code(403); // Forbidden
    echo json_encode($response);
    exit();
}
// --- End Security Check ---

// --- Input Validation ---
$schedule_ids = $_POST['schedule_ids'] ?? [];

if (empty($schedule_ids) || !is_array($schedule_ids)) {
    $response['error'] = 'No schedule entries selected.';
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit();
}

// Sanitize IDs
$sanitized_ids = array_map('intval', $schedule_ids);
$sanitized_ids = array_filter($sanitized_ids, function($id) { return $id > 0; });

if (empty($sanitized_ids)) {
    $response['error'] = 'No valid schedule IDs provided.';
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit();
}

// Prepare for SQL query
$ids_placeholder = implode(',', array_fill(0, count($sanitized_ids), '?'));
$types = str_repeat('i', count($sanitized_ids));
// --- End Input Validation ---

// --- Initialize Twilio Client ---
try {
    $twilioClient = new Client($twilioAccountSid, $twilioAuthToken);
} catch (Exception $e) {
     error_log("CRITICAL: Failed to initialize Twilio client: " . $e->getMessage());
     $response['error'] = 'SMS service configuration error. Please contact administrator.';
     http_response_code(500);
     echo json_encode($response);
     exit();
}
// --- End Twilio Client Init ---

// --- Processing ---
$sent_count = 0;
$failed_count = 0;
$errors = [];

try {
    // Fetch schedule and user details for the selected IDs
    $sql = "SELECT s.id as schedule_id, s.sched_date, s.registration, s.pos, s.user_id,
                   u.firstname, u.lastname, u.phone, u.email
            FROM schedule s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id IN ($ids_placeholder)";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database Error: Prepare failed (fetch details): " . $mysqli->error);
    }

    $stmt->bind_param($types, ...$sanitized_ids);

    if (!$stmt->execute()) {
         throw new Exception("Database Error: Execute failed (fetch details): " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        // This case might indicate an issue after execute but before getting results
        throw new Exception("Database Error: Failed to get result after execute (fetch details).");
    }

    // Loop through each selected assignment
    while ($assignment = $result->fetch_assoc()) {
        $pilotFullName = 'N/A';
        $recipient_phone = null; // Phone number to send SMS to

        // Get Pilot Name and Phone
        if (!empty($assignment['user_id'])) {
            $lastName = $assignment['lastname'] ?? '';
            $firstName = $assignment['firstname'] ?? '';
            $pilotFullName = trim($lastName . (!empty($lastName) && !empty($firstName) ? ', ' : '') . $firstName);
            if (empty($pilotFullName)) { $pilotFullName = 'Pilot (ID: ' . $assignment['user_id'] . ')'; }

            // Clean and validate phone number for Twilio (E.164 format required)
            if (!empty($assignment['phone'])) {
                 $phone_raw = $assignment['phone'];
                 // Remove common formatting characters
                 $phone_clean = preg_replace('/[\s\-\(\)]+/', '', $phone_raw);
                 // Ensure it starts with '+'
                 if (substr($phone_clean, 0, 1) !== '+') {
                      // Basic North America assumption (improve if international)
                      if (strlen($phone_clean) == 10) { $phone_clean = '+1' . $phone_clean; }
                      else { $phone_clean = '+' . $phone_clean; } // Hope country code included
                 }
                 // Validate E.164 format
                 if (preg_match('/^\+[1-9]\d{1,14}$/', $phone_clean)) {
                     $recipient_phone = $phone_clean;
                 } else {
                     error_log("Invalid phone format for user_id {$assignment['user_id']}: {$phone_raw} -> {$phone_clean}");
                     if ($pilotFullName !== 'User Not Linked') {
                         $errors[] = "Invalid phone# for {$pilotFullName}";
                     }
                 }
            }
        } else {
             $pilotFullName = 'User Not Linked';
             error_log("Warning: Schedule ID {$assignment['schedule_id']} has no linked user_id.");
        }

        // --- Construct Notification Message ---
        $scheduleDateFormatted = date("D, M j, Y", strtotime($assignment['sched_date'])); // e.g., "Tue, Apr 16, 2024"
        // Customize your message here:
        $messageBody = "Schedule Notification ({$scheduleDateFormatted}): Assignment as {$assignment['pos']} on {$assignment['registration']}.";
        // $messageBody = "HeliOps Schedule for {$scheduleDateFormatted}: You are assigned as {$assignment['pos']} on {$assignment['registration']}."; // Alternative

        // --- Attempt to Send Notification via Twilio ---
        $send_status = false;
        $twilio_message_sid = null; // To store the Message SID from Twilio

        if (!empty($recipient_phone)) {
            try {
                // Use the Twilio Client to create (=send) a message
                 $messageResult = $twilioClient->messages->create(
                     $recipient_phone, // The 'to' number
                     [
                         'from' => $twilioFromNumber, // Your Twilio 'from' number
                         'body' => $messageBody       // The message text
                         // Add 'statusCallback' here if you want delivery reports
                     ]
                 );

                // Check the result from Twilio
                 if ($messageResult && $messageResult->sid) {
                    $send_status = true;
                    $twilio_message_sid = $messageResult->sid; // Store the SID
                    error_log("Twilio SMS Queued to {$recipient_phone}. SID: {$twilio_message_sid}");
                 } else {
                    // Should not happen often with v7+ as errors usually throw exceptions
                    error_log("Twilio send attempt to {$recipient_phone} did not return expected SID.");
                    $errors[] = "SMS send status unknown for {$pilotFullName}";
                 }

            } catch (TwilioException $e) { // Catch Twilio specific API errors
                 $errorMessage = $e->getMessage();
                 // Log detailed Twilio error
                 error_log("Twilio API Exception sending to {$recipient_phone}. Code: " . $e->getCode() . ". Message: " . $errorMessage);
                 // Provide a slightly more user-friendly error if possible
                 if (strpos($errorMessage, 'not a valid phone number') !== false) {
                    $errors[] = "SMS failed for {$pilotFullName}: Invalid recipient phone number.";
                 } elseif (strpos($errorMessage, 'Permission to send') !== false) {
                     $errors[] = "SMS failed for {$pilotFullName}: Sending permission error (check Twilio geo-permissions).";
                 } else {
                     $errors[] = "SMS failed for {$pilotFullName}: Twilio Error Code " . $e->getCode();
                 }
                 $send_status = false;
            } catch (Exception $e) { // Catch other potential exceptions (network etc.)
                 error_log("General Exception sending SMS to {$recipient_phone}: " . $e->getMessage());
                 $errors[] = "SMS Error for {$pilotFullName}";
                 $send_status = false;
            }
        } else {
            // No valid phone number was found or formatted for this user
            if ($pilotFullName !== 'User Not Linked') { // Only report error if a user was expected
                 $errors[] = "No valid phone number for {$pilotFullName} to send SMS.";
            }
            $send_status = false;
        }

        // --- Log the Attempt to Database ---
        log_notification_attempt(
            $mysqli,
            $assignment['schedule_id'],
            $assignment['user_id'],
            $assignment['sched_date'],
            $assignment['registration'],
            $pilotFullName,
            $recipient_phone, // Log the phone number actually used (or null)
            $send_status ? 'Sent' : 'Failed', // Log status based on outcome
            $messageBody,     // Log the message content
            $twilio_message_sid // Log the Twilio SID if available
        );

        // --- Update Counters ---
        if ($send_status) {
            $sent_count++;
        } else {
            $failed_count++;
        }

    } // End of the while loop processing each assignment
    $stmt->close();

    // --- Prepare Final JSON Response ---
    $response['success'] = $failed_count == 0 && $sent_count > 0;
    if ($sent_count == 0 && $failed_count == 0 && empty($errors)) {
         $response['message'] = "No notifications were attempted (possibly no valid phone numbers found).";
         $response['success'] = false; // Consider this not a full success
    } elseif ($sent_count == 0 && $failed_count == 0 && !empty($errors)) {
         $response['message'] = "No notifications could be attempted due to errors.";
         $response['success'] = false;
    } else {
        $response['message'] = "Notifications processed: {$sent_count} sent, {$failed_count} failed.";
    }
    // Include specific errors if any occurred
    if (!empty($errors)) {
        // Consolidate unique errors if needed, or just list them
        $response['details'] = array_unique($errors);
    }

} catch (Exception $e) {
    // Catch errors from the main processing block (e.g., DB query failures)
    error_log("Error during send_notifications.php processing: " . $e->getMessage());
    $response['error'] = 'An unexpected error occurred during processing.';
    // Avoid sending detailed DB errors to the client
    // $response['details'] = $e->getMessage();
    http_response_code(500); // Internal Server Error
} finally {
    // Ensure database connection is closed
     if (isset($mysqli) && $mysqli->ping()) {
        $mysqli->close();
     }
}

// --- Output the final JSON response ---
echo json_encode($response);


// --- Helper Function for Logging ---
/**
 * Logs a notification attempt to the database.
 * Includes provider_message_id for tracking.
 */
function log_notification_attempt($db, $schedule_id, $pilot_id, $schedule_date, $craft_registration, $pilot_name, $recipient_contact, $status, $message_content, $provider_message_id = null) {
    try {
        $sql_log = "INSERT INTO notifications_log
                        (schedule_id, pilot_id, schedule_date, craft_registration, pilot_name, recipient_contact, status, message, sent_at, provider_message_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)"; // Added provider_message_id

        $stmt_log = $db->prepare($sql_log);
        if ($stmt_log) {
             // Types: integer, integer, string(date), string, string, string, string, string, string(sid)
             $stmt_log->bind_param("iisssssss",
                 $schedule_id, $pilot_id, $schedule_date, $craft_registration, $pilot_name,
                 $recipient_contact, $status, $message_content, $provider_message_id // Bind the SID
             );
             if (!$stmt_log->execute()) {
                  error_log("Log Insert Execute Error: (" . $stmt_log->errno . ") " . $stmt_log->error);
             }
             $stmt_log->close();
        } else {
             error_log("Log Insert Prepare Error: (" . $db->errno . ") " . $db->error);
        }
    } catch(Exception $e) {
        // Catch any other exceptions during logging
        error_log("Exception during log_notification_attempt: ".$e->getMessage());
    }
}
?>