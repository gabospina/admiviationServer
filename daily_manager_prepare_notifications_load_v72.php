<?php
/**
 * File: daily_manager_prepare_notifications_load.php
 * Loads items from the notification queue for display.
 *
 * FUNCTIONALITY CONFIRMED:
 * - Loads items only for the currently logged-in manager.
 * - Filters to show only assignments for today or future dates.
 * - Includes 'routing' and 'status' data.
 * - **Crucially, sorts the results by date to ensure proper grouping in the UI.**
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');
include_once "db_connect.php";

$response = ['success' => false, 'queue' => []];

// 1. Authenticate User
if (!isset($_SESSION["HeliUser"])) {
    $response['error'] = 'Authentication Required. Please log in again.';
    http_response_code(401);
    echo json_encode($response);
    exit();
}

$adding_user_id = (int)$_SESSION['HeliUser'];

try {
    // 2. Get the current date to filter out past assignments.
    $current_date = date('Y-m-d');
    // Show assignments from 3 days ago to future
    // $current_date = date('Y-m-d', strtotime('-3 days'));
    // Keep: WHERE target_sched_date >= ?

    // 3. The SQL Query with the essential ORDER BY clause
    // This query is correctly structured to produce the grouped output you want.
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
              AND target_sched_date >= ?
            ORDER BY
                target_sched_date ASC,      -- PRIMARY SORT: This is the key to grouping by date.
                target_registration ASC,  -- Secondary sort: For a given day, group by aircraft.
                FIELD(target_position, 'PIC', 'SIC') -- Tertiary sort: For a given aircraft, list PIC before SIC.
            ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) { throw new Exception("DB Prepare Error: " . $mysqli->error); }

    // 4. Bind parameters and execute
    $stmt->bind_param("is", $adding_user_id, $current_date);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        // The results are now guaranteed to be in the correct order.
        $response['queue'] = $result->fetch_all(MYSQLI_ASSOC);
        $response['success'] = true;
    } else {
        throw new Exception("DB Execute Error: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
     error_log("Load Queue Error: " . $e->getMessage());
     $response['error'] = 'A server error occurred while loading the notification queue.';
     http_response_code(500);
}

// 5. Send the sorted JSON response to the JavaScript function
echo json_encode($response);
?>