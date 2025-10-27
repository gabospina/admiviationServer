<?php
require_once 'api_response.php';
include_once "db_connect.php";

// Initialize session and check authentication
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["HeliUser"])) {
    echo ApiResponse::error("Authentication required", 401);
    exit();
}

try {
    $user_id = (int)$_SESSION["HeliUser"];
    $start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-d');
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        echo ApiResponse::error("Invalid date format");
        exit();
    }

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM flight_hours 
                  WHERE user_id = ? AND date BETWEEN ? AND ?";
    $stmt = $mysqli->prepare($countQuery);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Get paginated results
    $query = "SELECT id, date, craft, aircraft, command, copilot, route, ifr, actual, hours, hour_type, created_at 
              FROM flight_hours 
              WHERE user_id = ? AND date BETWEEN ? AND ?
              ORDER BY date DESC, created_at DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("issii", $user_id, $start_date, $end_date, $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = [
            'id' => (int)$row['id'],
            'date' => $row['date'],
            'craft' => $row['craft'],
            'aircraft' => $row['aircraft'],
            'command' => $row['command'],
            'copilot' => $row['copilot'],
            'route' => $row['route'],
            'ifr' => (float)$row['ifr'],
            'actual' => (float)$row['actual'],
            'hours' => (float)$row['hours'],
            'hour_type' => $row['hour_type'],
            'created_at' => $row['created_at']
        ];
    }

    echo ApiResponse::success([
        'entries' => $entries,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ]);

} catch (Exception $e) {
    logError("Error in get_flight_hours.php: " . $e->getMessage(), [
        'user_id' => $user_id ?? null,
        'start_date' => $start_date ?? null,
        'end_date' => $end_date ?? null
    ]);
    echo ApiResponse::error("An error occurred while fetching flight hours");
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $mysqli->close();
}
?> 