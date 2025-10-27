<?php
/**
 * File: daily_manager_prepare_notifications_load.php v72 (UPDATED FOR TODAY/TOMORROW SECTIONS)
 * Now returns separate arrays for today and tomorrow's assignments
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
include_once "db_connect.php";

$response = ['success' => false, 'today_queue' => [], 'tomorrow_queue' => []];

// 1. Authenticate User
if (!isset($_SESSION["HeliUser"])) {
    $response['error'] = 'Authentication Required. Please log in again.';
    http_response_code(401);
    echo json_encode($response);
    exit();
}

$adding_user_id = (int)$_SESSION['HeliUser'];

try {
    // 2. Calculate today and tomorrow dates
    $today_date = date('Y-m-d');
    $tomorrow_date = date('Y-m-d', strtotime('+1 day'));

    // 3. The SQL Query - get both today and tomorrow
    $sql = "SELECT
                id as queue_id,
                schedule_id,
                target_user_id as user_id,
                target_pilot_name as pilot_name,
                target_phone as phone,
                target_sched_date as sched_date,
                target_registration as registration,
                target_position as pos,
                target_craft_type as craft_type,
                routing,
                status
            FROM sms_prepare_notifications
            WHERE adding_user_id = ?
              AND (target_sched_date = ? OR target_sched_date = ?)
            ORDER BY 
                target_sched_date ASC,
                target_registration ASC,
                FIELD(target_position, 'PIC', 'SIC')";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) { 
        throw new Exception("DB Prepare Error: " . $mysqli->error); 
    }

    // 4. Bind parameters and execute
    $stmt->bind_param("iss", $adding_user_id, $today_date, $tomorrow_date);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $all_queue_items = $result->fetch_all(MYSQLI_ASSOC);
        
        // 5. Separate into today and tomorrow arrays
        foreach ($all_queue_items as $item) {
            if ($item['sched_date'] === $today_date) {
                $response['today_queue'][] = $item;
            } elseif ($item['sched_date'] === $tomorrow_date) {
                $response['tomorrow_queue'][] = $item;
            }
        }
        
        $response['success'] = true;
        $response['today_date'] = $today_date;
        $response['tomorrow_date'] = $tomorrow_date;
        
    } else {
        throw new Exception("DB Execute Error: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
     error_log("Load Queue Error: " . $e->getMessage());
     $response['error'] = 'A server error occurred while loading the notification queue.';
     http_response_code(500);
}

echo json_encode($response);
?>