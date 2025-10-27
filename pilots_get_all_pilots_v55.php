<?php
// pilots_get_all_pilots.php (NEW VERSION WITH DUTY STATUS)

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'data' => [], 'error' => null];

try {
    if (!isset($_SESSION['company_id'])) {
        throw new Exception("Company context not set.", 401);
    }
    $companyId = (int)$_SESSION['company_id'];
    $today = date('Y-m-d'); // Get today's date in YYYY-MM-DD format

    // =========================================================================
    // === THE NEW, MORE POWERFUL QUERY                                      ===
    // =========================================================================
    // This query now uses a LEFT JOIN and a CASE statement to check
    // if the pilot is currently on duty.
    $sql = "SELECT 
                u.id, 
                CONCAT(u.firstname, ' ', u.lastname) as name,
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM user_availability ua 
                        WHERE ua.user_id = u.id AND ? BETWEEN ua.on_date AND ua.off_date
                    )
                    THEN 1 
                    ELSE 0 
                END as isOnDutyToday
            FROM users u
            WHERE u.company_id = ? AND u.is_active = 1
            GROUP BY u.id
            ORDER BY name ASC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("Database prepare failed: " . $mysqli->error);

    // Bind today's date and the company ID
    $stmt->bind_param("si", $today, $companyId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $pilots = [];
    while ($row = $result->fetch_assoc()) {
        // Convert the 1 or 0 to a true boolean for cleaner JavaScript
        $row['isOnDutyToday'] = (bool)$row['isOnDutyToday'];
        $pilots[] = $row;
    }

    $response['data'] = $pilots;
    $response['success'] = true;
    $stmt->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
}

echo json_encode($response);
?>