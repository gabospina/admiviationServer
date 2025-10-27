<?php
// schedule_get_aircraft.php - FINAL VERSION with HYBRID SORTING

header('Content-Type: application/json');
session_start();

// It's better to show errors during development
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

require_once 'db_connect.php';

try {
    if (!isset($_SESSION['company_id'])) {
        throw new Exception('Unauthorized: No company ID in session.', 401);
    }
    
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception('Database connection failed: ' . ($mysqli ? $mysqli->connect_error : 'Unknown error'));
    }

    $company_id = (int)$_SESSION['company_id'];

    // --- THIS IS THE REFACTORED QUERY ---
    // EXPLANATION:
    // 1. We now select the `contract_name` and `display_order` columns.
    // 2. The `ORDER BY` clause at the end is the most critical change.
    //    - `ISNULL(t.contract_name), t.contract_name`: This clever trick groups all aircraft with contracts first, sorted by contract name. Aircraft without a contract are pushed to the bottom.
    //    - `t.display_order`: This is our master order. Within each contract group, aircraft are sorted by the order set in "Manage Crafts".
    //    - `t.registration`: This is a final tie-breaker for any aircraft with the same order.
    $sql = "
        SELECT 
            t.id, 
            t.craft_type, 
            t.registration, 
            t.color,
            t.contract_name,
            t.display_order
        FROM (
            SELECT
                cr.id,
                cr.craft_type,
                cr.registration,
                cr.display_order,
                co.color,
                co.contract_name,
                ROW_NUMBER() OVER (PARTITION BY cr.id ORDER BY co.contract_order DESC, co.id DESC) as rn
            FROM 
                crafts AS cr
            LEFT JOIN 
                contract_crafts AS cc ON cr.id = cc.craft_id
            LEFT JOIN 
                contracts AS co ON cc.contract_id = co.id
            WHERE 
                cr.company_id = ? AND cr.alive = 1
        ) AS t
        WHERE t.rn = 1
        ORDER BY 
            ISNULL(t.contract_name), t.contract_name, t.display_order, t.registration
    ";

    $stmt = $mysqli->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement. MySQL Error: ' . $mysqli->error);
    }

    $stmt->bind_param("i", $company_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $schedule = $result->fetch_all(MYSQLI_ASSOC);
    
    $response = [
        'success' => true,
        'schedule' => $schedule
    ];

} catch (Exception $e) {
    http_response_code(500);
    error_log("schedule_get_aircraft.php ERROR: " . $e->getMessage());
    $response['error'] = 'Failed to load aircraft schedule. Please contact support.';
    $response['debug_error'] = $e->getMessage();
}

// Clean up resources
if (isset($stmt) && $stmt) $stmt->close();
if (isset($mysqli) && $mysqli) $mysqli->close();

echo json_encode($response);
exit;
?>