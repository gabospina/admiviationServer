<?php
// File: send_notifications.php (Using twilio-php-main >= 7.x)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// --- DEPENDENCIES ---
include_once "db_connect.php"; // Ensure this path is correct

// ** NEW: Include Twilio library using Composer autoloader (recommended) **
// If you installed via Composer:
require_once 'vendor/autoload.php';
// If you downloaded manually, adjust path to the new autoloader:
// require_once 'twilio-php-main/src/Twilio/autoload.php'; // Adjust path!

// Use the Twilio namespace
use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException; // Import TwilioException

// --- Twilio Credentials ---
// Using the Account SID and Auth Token provided
$twilioAccountSid = "ACe6e90a6871365a830192e0bcac69fb3a";
$twilioAuthToken = "3b58a7a528c1800495a8d267ce1c8042";
// ** IMPORTANT: Verify this is a TWILIO number you own/rented and is SMS-capable **
$twilioFromNumber = "+12317972593";

// --- Initialize Response ---
$response = ['success' => false];

// --- Security Check ---
$session_user_permissions = $_SESSION['user_permissions'] ?? [];
$required_permission = 'send_sms'; // Permission required to run this script

if (!isset($_SESSION["HeliUser"]) || !in_array($required_permission, $session_user_permissions)) {
    $response['error'] = 'Permission Denied to send notifications.';
    http_response_code(403);
    echo json_encode($response);
    exit();
}
// --- End Security Check ---

// --- Input Validation ---
$schedule_ids = $_POST['schedule_ids'] ?? [];
// ... (Input validation remains the same) ...
if (empty($schedule_ids) || !is_array($schedule_ids)) { /* error */ exit; }
$sanitized_ids = array_map('intval', $schedule_ids);
$sanitized_ids = array_filter($sanitized_ids, function($id) { return $id > 0; });
if (empty($sanitized_ids)) { /* error */ exit; }
$ids_placeholder = implode(',', array_fill(0, count($sanitized_ids), '?'));
$types = str_repeat('i', count($sanitized_ids));
// --- End Input Validation ---

// --- Initialize Twilio Client (New Syntax) ---
try {
    $twilioClient = new Client($twilioAccountSid, $twilioAuthToken);
} catch (Exception $e) { // Catch generic Exception during init
     error_log("Failed to initialize Twilio client: " . $e->getMessage());
     $response['error'] = 'SMS service configuration error.';
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
    // Fetch details (SQL remains the same)
    $sql = "SELECT s.id as schedule_id, s.sched_date, s.registration, s.pos, s.user_id,
                   u.firstname, u.lastname, u.phone, u.email
            FROM schedule s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id IN ($ids_placeholder)";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) { throw new Exception("Prepare failed (fetch details): " . $mysqli->error); }
    $stmt->bind_param($types, ...$sanitized_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) { throw new Exception("Failed to get result (fetch details): " . $stmt->error); }

    while ($assignment = $result->fetch_assoc()) {
        $pilotFullName = 'N/A';
        $recipient_phone = null;

        if (!empty($assignment['user_id'])) {
            $lastName = $assignment['lastname'] ?? '';
            $firstName = $assignment['firstname'] ?? '';
            $pilotFullName = trim($lastName . (!empty($lastName) && !empty($firstName) ? ', ' : '') . $firstName);
            if (empty($pilotFullName)) { $pilotFullName = 'Pilot (ID: ' . $assignment['user_id'] . ')'; }

            // Phone number cleaning/formatting (same as before)
            if (!empty($assignment['phone'])) {
                 $phone_raw = $assignment['phone'];
                 $phone_clean = preg_replace('/[\s\-\(\)]+/', '', $phone_raw);
                 if (substr($phone_clean, 0, 1) !== '+') {
                      if (strlen($phone_clean) == 10) { $phone_clean = '+1' . $phone_clean; }
                      else { $phone_clean = '+' . $phone_clean; }
                 }
                 if (preg_match('/^\+[1-9]\d{1,14}$/', $phone_clean)) {
                     $recipient_phone = $phone_clean;
                 } else {
                     error_log("Invalid phone format for user {$assignment['user_id']}: {$phone_raw} -> {$phone_clean}");
                     $errors[] = "Invalid phone format for {$pilotFullName}";
                 }
            }
        } else { /* handle User Not Linked */ }

        // --- Construct Notification Message ---
        $scheduleDateFormatted = date("D, M j", strtotime($assignment['sched_date']));
        $messageBody = "HeliOps Schedule for {$scheduleDateFormatted}: You are assigned as {$assignment['pos']} on {$assignment['registration']}.";

        // --- Send Notification (NEW Twilio Syntax) ---
        $send_status = false;
        $twilio_message_sid = null;

        if (!empty($recipient_phone)) {
            try {
                // ** NEW SYNTAX for sending SMS **
                 $messageResult = $twilioClient->messages->create(
                     $recipient_phone, // To number
                     [
                         'from' => $twilioFromNumber, // From Your Twilio Number
                         'body' => $messageBody
                         // Optional: 'statusCallback' => 'https://yourdomain.com/callback'
                     ]
                 );

                // Check result
                 if ($messageResult && $messageResult->sid) {
                    $send_status = true;
                    $twilio_message_sid = $messageResult->sid;
                    error_log("Twilio SMS sent successfully to {$recipient_phone}. SID: {$twilio_message_sid}");
                 } else {
                    error_log("Twilio send did not return SID for {$recipient_phone}.");
                    $errors[] = "SMS send status unknown for {$pilotFullName}";
                 }

            } catch (TwilioException $e) { // Catch Twilio specific exceptions
                 error_log("Twilio API Exception sending to {$recipient_phone}: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
                 $errors[] = "SMS failed for {$pilotFullName}: " . $e->getMessage();
                 $send_status = false;
            } catch (Exception $e) { // Catch other general exceptions
                 error_log("General Exception sending to {$recipient_phone}: " . $e->getMessage());
                 $errors[] = "SMS Error for {$pilotFullName}";
                 $send_status = false;
            }
        } else {
            // No valid phone number
            if ($pilotFullName !== 'User Not Linked') { $errors[] = "No valid phone number for {$pilotFullName}"; }
            $send_status = false;
        }

        // --- Log Notification Attempt (Function call is the same) ---
        log_notification_attempt(
            $mysqli, $assignment['schedule_id'], $assignment['user_id'],
            $assignment['sched_date'], $assignment['registration'], $pilotFullName,
            $recipient_phone, $send_status ? 'Sent' : 'Failed', $messageBody,
            $twilio_message_sid // Pass SID to logger
        );

        // --- Update Counters ---
        if ($send_status) { $sent_count++; } else { $failed_count++; }

    } // End while loop
    $stmt->close();

    // --- Final Response ---
    $response['success'] = $failed_count == 0 && $sent_count > 0;
    if ($sent_count == 0 && $failed_count == 0) { $response['message'] = "No notifications attempted."; $response['success'] = false;}
    else { $response['message'] = "Notifications processed: $sent_count sent, $failed_count failed."; }
    if (!empty($errors)) { $response['details'] = $errors; }

} catch (Exception $e) {
    error_log("Error in send_notifications.php processing loop: " . $e->getMessage());
    $response['error'] = 'An unexpected error occurred.';
    http_response_code(500);
} finally {
     if (isset($mysqli) && $mysqli->ping()) { $mysqli->close(); }
}

echo json_encode($response);


// --- Helper Function for Logging (Same as before, including provider_message_id) ---
function log_notification_attempt($db, $schedule_id, $pilot_id, $schedule_date, $craft_registration, $pilot_name, $recipient_contact, $status, $message_content, $provider_message_id = null) {
    try {
        $sql_log = "INSERT INTO notifications_log
                        (schedule_id, pilot_id, schedule_date, craft_registration, pilot_name, recipient_contact, status, message, sent_at, provider_message_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
        $stmt_log = $db->prepare($sql_log);
        if ($stmt_log) {
             $stmt_log->bind_param("iisssssss",
                 $schedule_id, $pilot_id, $schedule_date, $craft_registration, $pilot_name,
                 $recipient_contact, $status, $message_content, $provider_message_id
             );
             $stmt_log->execute();
             if ($stmt_log->error) { error_log("Log Insert Execute Error: " . $stmt_log->error); }
             $stmt_log->close();
        } else { error_log("Log Insert Prepare Error: " . $db->error); }
    } catch(Exception $e) { error_log("Exception during log_notification_attempt: ".$e->getMessage()); }
}
?>