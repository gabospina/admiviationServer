<?php
header('Content-Type: application/json');
session_start();

error_reporting(0);
ini_set('display_errors', 0);

$response = ['success' => false, 'error' => 'An unknown error occurred.'];

require_once 'db_connect.php';

try {
    if (!isset($_SESSION['company_id'])) {
        throw new Exception('Unauthorized: No company ID in session.');
    }
    
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception('Database connection failed: ' . ($mysqli ? $mysqli->connect_error : 'Unknown error'));
    }

    $company_id = (int)$_SESSION['company_id'];

    // ==============================================================================
    // === FINAL, ROBUST SQL QUERY TO PREVENT DUPLICATES ===
    // This query uses a subquery with ROW_NUMBER() to ensure that even if a craft
    // is linked to multiple contracts, we only select ONE definitive row for it.
    // ==============================================================================
    $sql = "
        SELECT 
            t.id, 
            t.craft_type, 
            t.registration, 
            t.color
        FROM (
            SELECT
                cr.id,
                cr.craft_type,
                cr.registration,
                co.color,
                ROW_NUMBER() OVER (PARTITION BY cr.id ORDER BY co.id DESC) as rn
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
            t.craft_type, t.registration
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

    $schedule = [];
    while ($row = $result->fetch_assoc()) {
        $schedule[] = $row;
    }
    
    $response = [
        'success' => true,
        'schedule' => $schedule
    ];

} catch (Exception $e) {
    error_log("schedule_get_aircraft.php ERROR: " . $e->getMessage());
    $response['error'] = 'Failed to load aircraft schedule. Please contact support.';
    $response['debug_error'] = $e->getMessage();
}

// Clean up resources
if (isset($stmt) && $stmt) $stmt->close();
if (isset($mysqli) && $mysqli) $mysqli->close();

echo json_encode($response);
exit;