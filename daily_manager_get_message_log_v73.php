<?php
/**
 * File: daily_manager_get_message_log.php (COMPOSITE ID VERSION)
 * Now creates a unique 'composite_id' for each row (e.g., 'assignment-123')
 * to be used by the delete function.
 */
if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
include_once "db_connect.php";

$response = ['success' => false, 'logs' => []];

if (!isset($_SESSION["HeliUser"])) {
    $response['error'] = 'Authentication Required.';
    http_response_code(401);
    echo json_encode($response);
    exit();
}

try {
    $filterDate = !empty($_GET['date']) ? $_GET['date'] : null;
    $searchTerm = !empty($_GET['search']) ? trim($_GET['search']) : null;

    $where_clauses = [];
    $params = [];
    $types = '';

    if ($filterDate) {
        $where_clauses[] = "DATE(sent_at) = ?";
        $params[] = $filterDate;
        $types .= 's';
    }

    if ($searchTerm) {
        $where_clauses[] = "(pilot_name LIKE ? OR craft_registration LIKE ? OR recipient_contact LIKE ? OR status LIKE ?)";
        $termWildcard = '%' . $searchTerm . '%';
        array_push($params, $termWildcard, $termWildcard, $termWildcard, $termWildcard);
        $types .= 'ssss';
    }

    $where_sql = count($where_clauses) > 0 ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    // === THE FIX: Use CONCAT() to create a unique composite ID for each row ===
    $sql_assignments = "SELECT CONCAT('assignment-', id) as composite_id, schedule_date, craft_registration, pilot_name, recipient_contact, sent_at, status, 'Assignment' as type FROM sms_notifications_log";
    $sql_direct = "SELECT CONCAT('direct-', id) as composite_id, NULL as schedule_date, 'Direct SMS' as craft_registration, target_pilot_name as pilot_name, recipient_contact, sent_at, status, 'Direct' as type FROM sms_direct_log";

    $final_sql = "SELECT * FROM (($sql_assignments) UNION ALL ($sql_direct)) as combined_logs
                  $where_sql
                  ORDER BY sent_at DESC
                  LIMIT 200";

    $stmt = $mysqli->prepare($final_sql);
    if (!$stmt) throw new Exception("Database prepare failed: " . $mysqli->error);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $response['logs'] = $result->fetch_all(MYSQLI_ASSOC);
    $response['success'] = true;

} catch (Exception $e) {
    error_log("Error in daily_manager_get_message_log.php: " . $e->getMessage());
    http_response_code(500);
    $response['error'] = 'A server error occurred while fetching the message log.';
    $response['debug_info'] = $e->getMessage();
}

echo json_encode($response);
?>