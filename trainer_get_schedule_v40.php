<?php
// trainer_get_schedule.php
// (Refactored version of Batch 1, Script #5, with your filename)

if (session_status() == PHP_SESSION_NONE) {
    session_start(); // <<<< ADDED
}

header('Content-Type: application/json');
require_once 'db_connect.php'; // VERIFY PATH
// No ApiResponse needed for direct echo of event array for FullCalendar

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}
$company_id = (int)$_SESSION['company_id'];

$start_date_str = $_GET["start"] ?? null; // Calendar view start (YYYY-MM-DD)
$end_date_str = $_GET["end"] ?? null;   // Calendar view end (YYYY-MM-DD, exclusive for FC)

if (!$start_date_str || !$end_date_str ||
    !preg_match("/^\d{4}-\d{2}-\d{2}$/", $start_date_str) ||
    !preg_match("/^\d{4}-\d{2}-\d{2}$/", $end_date_str)) {
    http_response_code(400);
    error_log("trainer_get_schedule.php: Invalid or missing date parameters.");
    echo json_encode([]);
    exit;
}

global $mysqli;
if (!$mysqli || $mysqli->connect_error) {
    http_response_code(500);
    error_log("DB Connection Error in trainer_get_schedule.php: " . ($mysqli->connect_error ?? "Unknown"));
    echo json_encode([]);
    exit;
}

try {
    // Your trainer_schedule table has 'start_date' and 'end_date' (inclusive)
    // FullCalendar event 'end' is exclusive.
    // Query for events where the event's inclusive range [ts.start_date, ts.end_date]
    // overlaps with the calendar view's inclusive range [view_start, view_end_inclusive]
    // Overlap condition: event_start <= view_end_inclusive AND event_end >= view_start

    // The $end_date_str from FullCalendar is exclusive, so view_end_inclusive is $end_date_str - 1 day
    $view_end_inclusive_dt = new DateTime($end_date_str);
    $view_end_inclusive_dt->modify('-1 day');
    $view_end_inclusive_str = $view_end_inclusive_dt->format('Y-m-d');

    $sql = "SELECT ts.id, ts.trainer_user_id, ts.start_date, ts.end_date, ts.position, 
                   CONCAT(u.lastname, ', ', u.firstname) AS trainer_name
            FROM trainer_schedule ts
            JOIN users u ON ts.trainer_user_id = u.id AND u.company_id = ts.company_id
            WHERE ts.company_id = ? 
              AND ts.start_date <= ?  -- Event starts on or before view ends
              AND ts.end_date >= ?    -- Event ends on or after view starts
            ORDER BY ts.start_date ASC, trainer_name ASC";
            
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }
    $stmt->bind_param("iss", $company_id, $view_end_inclusive_str, $start_date_str);

    if (!$stmt->execute()) {
        throw new Exception("SQL execute error: " . $stmt->error, 500);
    }

    $result = $stmt->get_result();
    $trainer_events_for_fc = [];
    while ($row = $result->fetch_assoc()) {
        $event_start_dt = new DateTime($row['start_date']);
        $event_end_dt = new DateTime($row['end_date']); // This is inclusive end from DB
        
        $length_days = $event_start_dt->diff($event_end_dt)->days + 1;

        // For FullCalendar, 'end' must be exclusive for allDay events
        $fc_exclusive_end_dt = clone $event_end_dt;
        $fc_exclusive_end_dt->modify('+1 day');

        $title = strtoupper($row['position']) . ": " . htmlspecialchars($row['trainer_name']) . 
                 " (" . $length_days . " day" . ($length_days > 1 ? "s" : "") . ")";

        $trainer_events_for_fc[] = [
            "id"        => (int)$row['id'],
            "title"     => $title,
            "start"     => $row['start_date'],             // YYYY-MM-DD
            "end"       => $fc_exclusive_end_dt->format('Y-m-d'), // YYYY-MM-DD (exclusive)
            "allDay"    => true,
            "pilot_id"  => (int)$row['trainer_user_id'], // For JS eventRender data-pk
            "position"  => $row['position'],
            "borderColor" => "rgb(73, 191, 242)"      // Default from your JS
        ];
    }
    $stmt->close();

    echo json_encode($trainer_events_for_fc);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    error_log("Error in trainer_get_schedule.php: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
    echo json_encode(["error" => true, "message" => "Server error: " . $e->getMessage()]);
    exit;
}
?>