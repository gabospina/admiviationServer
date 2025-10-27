<?php
/**
 * File: daily_manager_get_message_log.php - CORRECTED & SECURED
 */
if (session_status() == PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
include_once "db_connect.php";

$response = ['success' => false, 'logs' => []];

// FIX: Added check for company_id in the session.
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
    $response['error'] = 'Authentication Required.';
    http_response_code(401);
    echo json_encode($response);
    exit();
}

// Get the company ID from the session. This is our primary security filter.
$company_id = (int)$_SESSION['company_id'];

try {
    $filterDate = !empty($_GET['date']) ? $_GET['date'] : null;
    $searchTerm = !empty($_GET['search']) ? trim($_GET['search']) : null;

    $where_clauses = [];
    $params = [];
    $types = '';

    // FIX: Add the company_id as the first and mandatory filter.
    // EXPLANATION: This ensures that no matter what other filters are applied,
    // the query will ONLY ever return results for the logged-in company.
    $where_clauses[] = "company_id = ?";
    $params[] = $company_id;
    $types .= 'i';

    // Add optional user-supplied filters
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

    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

    // FIX: Modified the subqueries to include the company_id column in their SELECT list.
    // EXPLANATION: For the outer `WHERE company_id = ?` clause to work on the combined results,
    // the company_id column must be present in both parts of the UNION.
    $sql_assignments = "SELECT company_id, CONCAT('assignment-', id) as composite_id, schedule_date, craft_registration, pilot_name, recipient_contact, sent_at, status, 'Assignment' as type FROM sms_notifications_log";
    $sql_direct = "SELECT company_id, CONCAT('direct-', id) as composite_id, NULL as schedule_date, 'Direct SMS' as craft_registration, target_pilot_name as pilot_name, recipient_contact, sent_at, status, 'Direct' as type FROM sms_direct_log";

    $final_sql = "SELECT * FROM (($sql_assignments) UNION ALL ($sql_direct)) as combined_logs
                  $where_sql
                  ORDER BY sent_at DESC
                  LIMIT 200";

    $stmt = $mysqli->prepare($final_sql);
    if (!$stmt) throw new Exception("Database prepare failed: " . $mysqli->error);

    // The bind_param call now correctly includes the company_id
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