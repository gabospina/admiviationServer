<?php
/**
 * File: daily_manager_prepare_notifications_save_item.php v83 (SESSION-BASED CSRF)
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');

// REMOVE: require_once 'login_csrf_handler.php';
include_once "db_connect.php";

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

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

    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception('Authentication Required.', 401);
    }
    $adding_user_id = (int)$_SESSION['HeliUser'];
    $company_id = (int)$_SESSION['company_id']; // ✅ GET COMPANY_ID FROM SESSION

    $item = $_POST['item'] ?? null;
    if (!$item || !is_array($item)) {
        throw new Exception('Invalid data.', 400);
    }

    $schedule_id = isset($item['schedule_id']) ? (int)$item['schedule_id'] : null;
    if ($schedule_id === null || $schedule_id <= 0) {
        throw new Exception('Valid Schedule ID is required.', 400);
    }
    
    $routing = trim($item['routing'] ?? '');
    $target_user_id = (int)($item['user_id'] ?? 0);
    $target_pilot_name = trim($item['pilot_name'] ?? 'N/A');
    $target_sched_date = trim($item['sched_date'] ?? '');
    $target_registration = trim($item['registration'] ?? '');
    $target_craft_type = trim($item['craft_type'] ?? '');
    $target_position = trim($item['pos'] ?? '');

    $stmt_phone = $mysqli->prepare("SELECT phone FROM users WHERE id = ?");
    $stmt_phone->bind_param("i", $target_user_id);
    $stmt_phone->execute();
    $target_phone = $stmt_phone->get_result()->fetch_assoc()['phone'] ?? null;
    $stmt_phone->close();

    // ✅ CORRECTED COLUMN ORDER TO MATCH TABLE STRUCTURE
    $sql = "INSERT INTO sms_prepare_notifications 
                (company_id, adding_user_id, schedule_id, target_user_id, target_pilot_name, target_phone, target_sched_date, target_registration, target_position, target_craft_type, routing, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
            ON DUPLICATE KEY UPDATE 
                target_pilot_name = VALUES(target_pilot_name), 
                target_phone = VALUES(target_phone),
                routing = VALUES(routing),
                status = 'Pending'"; 

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("DB Prepare Error: ". $mysqli->error);

    // ✅ CORRECTED BIND PARAMETER ORDER
    // company_id comes FIRST, then adding_user_id, etc.
    $stmt->bind_param("iiissssssss", 
        $company_id,        // ✅ FIRST: company_id (position 2 in table)
        $adding_user_id,    // ✅ SECOND: adding_user_id (position 3 in table)
        $schedule_id,       // ✅ THIRD: schedule_id (position 4 in table)
        $target_user_id,    // ✅ FOURTH: target_user_id (position 7 in table)
        $target_pilot_name, // ✅ FIFTH: target_pilot_name (position 8 in table)
        $target_phone,      // ✅ SIXTH: target_phone (position 9 in table)
        $target_sched_date, // ✅ SEVENTH: target_sched_date (position 10 in table)
        $target_registration, // ✅ EIGHTH: target_registration (position 11 in table)
        $target_position,   // ✅ NINTH: target_position (position 12 in table)
        $target_craft_type, // ✅ TENTH: target_craft_type (position 13 in table)
        $routing            // ✅ ELEVENTH: routing (position 14 in table)
    );

    if (!$stmt->execute()) throw new Exception("DB Execute Error: " . $stmt->error);
    
    // Regenerate CSRF token on success
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $response['success'] = true;
    $response['inserted_id'] = $stmt->insert_id;
    $response['message'] = "Item added to notification queue.";
    $response['new_csrf_token'] = $_SESSION['csrf_token'];
    
} catch (Exception $e) { 
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
    $response['message'] = $e->getMessage();
    $response['new_csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
}

echo json_encode($response);
?>