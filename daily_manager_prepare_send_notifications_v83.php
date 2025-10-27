<?php
// daily_manager_prepare_send_notifications.php - v83 - SESSION-BASED CSRF

if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

// ADD THIS LINE AT TOP:
require_once 'login_csrf_handler.php';

// Check for Composer autoloader
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server Configuration Error: Twilio library is missing.']);
    exit();
}
require_once $autoloadPath;

use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

include_once "db_connect.php";

$response = ['success' => false];

try {
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

    if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
        throw new Exception('Permission Denied. Session is invalid.', 403);
    }
    $company_id = (int)$_SESSION["company_id"];

    // Twilio Credentials
    $twilioAccountSid = "ACe6e90a6871365a830192e0bcac69fb3a";
    $twilioAuthToken = "3b58a7a528c1800495a8d267ce1c8042";
    $twilioFromNumber = "+16513272353";
    $twilioClient = new Client($twilioAccountSid, $twilioAuthToken);

    $input = json_decode(file_get_contents('php://input'), true);
    $notifications_to_send = $input['notifications'] ?? [];
    if (empty($notifications_to_send)) {
        throw new Exception('No valid notification data received.', 400);
    }
    
    $stmt_update_queue = $mysqli->prepare("UPDATE sms_prepare_notifications SET status = ?, target_phone = ?, routing = ? WHERE id = ?");
    if ($stmt_update_queue === false) throw new Exception('DB Prepare Error (update_queue): ' . $mysqli->error);
    
    // FIX: Added 'company_id' to the INSERT statement.
    // EXPLANATION: This ensures every new log record is tagged with the correct company ID.
    $sql_log_history = "INSERT INTO sms_notifications_log 
                            (company_id, schedule_id, user_id, schedule_date, craft_registration, pilot_name, status, message, recipient_contact, sent_at, provider_message_id) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt_log_history = $mysqli->prepare($sql_log_history);
    if ($stmt_log_history === false) throw new Exception('DB Prepare Error (log_history): ' . $mysqli->error);

    $sent_count = 0;
    $failed_count = 0;

    foreach ($notifications_to_send as $item) {
        $queue_id = (int)($item['queue_id'] ?? 0);
        if ($queue_id <= 0) continue; 

        $phone = trim($item['phone']);
        $routing = trim($item['routing'] ?? '');
        
        $messageBody = "Assignment for {$item['sched_date']}: " .
                       "Craft: {$item['registration']}, " .
                       "Position: {$item['pos']}.";
        
        if (!empty($routing)) {
            $messageBody .= " Routing: {$routing}.";
        }
        
        $status = 'Failed';
        $twilio_sid = null;

        try {
            if (empty($phone)) throw new TwilioException("Phone number is empty for queue ID {$queue_id}.");
            
            $messageResult = $twilioClient->messages->create($phone, ['from' => $twilioFromNumber, 'body' => $messageBody]);
            
            if ($messageResult && $messageResult->sid) {
                $status = 'Sent';
                $twilio_sid = $messageResult->sid;
                $sent_count++;
            }
        } catch (TwilioException $e) {
            error_log("Twilio send failed for queue ID $queue_id: " . $e->getMessage());
            $failed_count++;
        }

        $stmt_update_queue->bind_param("sssi", $status, $phone, $routing, $queue_id);
        $stmt_update_queue->execute();
        
        // FIX: Added '$company_id' to the bind_param call and updated the type string.
        // The new type string is "iiisssssss" to match the added integer company_id.
        $stmt_log_history->bind_param("iiisssssss", $company_id, $item['schedule_id'], $item['user_id'], $item['sched_date'], $item['registration'], $item['pilot_name'], $status, $messageBody, $phone, $twilio_sid);
        $stmt_log_history->execute();
    }

    // Regenerate CSRF token on success
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $response['success'] = true;
    $response['message'] = "Notifications processed: {$sent_count} sent, {$failed_count} failed.";
    $response['new_csrf_token'] = $_SESSION['csrf_token'];
    
    } catch (Exception $e) {
        http_response_code(500);
        $response['message'] = $e->getMessage();
        $response['new_csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
        error_log("Send Notifications Error: " . $e->getMessage());
    }

echo json_encode($response);
?>