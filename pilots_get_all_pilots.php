<?php
// pilots_get_all_pilots.php (CORRECTED VERSION)

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'data' => [], 'error' => null];

try {
    if (!isset($_SESSION['company_id'])) {
        throw new Exception("Company context not set.", 401);
    }
    $companyId = (int)$_SESSION['company_id'];

    // =========================================================================
    // === THE CORRECTED QUERY THAT INCLUDES HIRE_DATE AND QUALIFICATIONS    ===
    // =========================================================================
    $sql = "SELECT 
                u.id, 
                CONCAT(u.firstname, ' ', u.lastname) as name,
                u.hire_date, -- <-- THIS WAS MISSING
                -- This combines all of a pilot's qualifications into one string
                GROUP_CONCAT(CONCAT(pct.craft_type, '-', LOWER(pct.position))) as qualifications -- <-- THIS WAS MISSING
            FROM users u
            LEFT JOIN pilot_craft_type pct ON u.id = pct.user_id
            WHERE u.company_id = ? AND u.is_active = 1
            GROUP BY u.id, u.firstname, u.lastname, u.hire_date
            ORDER BY name ASC";
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) throw new Exception("Database prepare failed: " . $mysqli->error);

    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $pilots = $result->fetch_all(MYSQLI_ASSOC);

    $response['data'] = $pilots;
    $response['success'] = true;
    $stmt->close();

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>