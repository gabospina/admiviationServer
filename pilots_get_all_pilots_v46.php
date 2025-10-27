<?php
// pilots_get_all_pilots.php (ENHANCED VERSION)

session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false, 'data' => []];

try {
    if (!isset($_SESSION['company_id'])) {
        throw new Exception('Company ID not set in session');
    }
    $company_id = (int)$_SESSION['company_id'];

    // This query now fetches everything we need for the pilot list
    $query = "
        SELECT 
            u.id, 
            u.firstname, 
            u.lastname,
            -- Group together all craft types a pilot is qualified for
            GROUP_CONCAT(DISTINCT pct.craft_type SEPARATOR ',') as crafts,
            -- Group together all roles a pilot has
            GROUP_CONCAT(DISTINCT ur.role_name SEPARATOR ',') as positions
        FROM 
            users u
        LEFT JOIN 
            pilot_craft_type pct ON u.id = pct.pilot_id
        LEFT JOIN 
            user_has_roles uhr ON u.id = uhr.user_id
        LEFT JOIN 
            users_roles ur ON uhr.role_id = ur.id
        WHERE 
            u.company_id = ? AND u.is_active = 1
        GROUP BY 
            u.id
        ORDER BY 
            u.lastname, u.firstname
    ";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) throw new Exception('Database prepare failed: ' . $mysqli->error);
    
    $stmt->bind_param('i', $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pilots = [];
    while ($row = $result->fetch_assoc()) {
        $pilots[] = [
            'id' => $row['id'],
            'name' => $row['lastname'] . ', ' . $row['firstname'],
            'crafts' => $row['crafts'] ?? '', // e.g., "S76,S92"
            'positions' => $row['positions'] ?? '' // e.g., "Pilot,Admin Pilot"
        ];
    }
    
    $response['success'] = true;
    $response['data'] = $pilots;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>