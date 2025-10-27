<?php
header('Content-Type: application/json');

require_once 'stats_api_response.php';
require_once 'db_connect.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    // 1. Authentication
    if (!isset($_SESSION["HeliUser"])) {
        throw new Exception("Authentication required", 401);
    }
    $userId = (int)$_SESSION["HeliUser"];

    // 2. Database connection
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection failed", 500);
    }

    // 3. Get initial experience
    $initial = [];
    $stmt = $mysqli->prepare("SELECT craft_type, 
            SUM(initial_pic_ifr_hours + initial_pic_vfr_hours) as pic,
            SUM(initial_sic_ifr_hours + initial_sic_vfr_hours) as sic,
            SUM(initial_pic_ifr_hours + initial_sic_ifr_hours) as ifr,
            SUM(initial_pic_vfr_hours + initial_sic_vfr_hours) as vfr,
            SUM(initial_pic_night_hours + initial_sic_night_hours) as night,
            SUM(initial_pic_ifr_hours + initial_pic_vfr_hours + 
                initial_sic_ifr_hours + initial_sic_vfr_hours) as total
            FROM pilot_initial_experience 
            WHERE user_id = ?
            GROUP BY craft_type");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $mysqli->error, 500);
    }
    
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error, 500);
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $initial[$row['craft_type']] = [
            'PIC' => (float)$row['pic'],
            'SIC' => (float)$row['sic'],
            'IFR' => (float)$row['ifr'],
            'VFR' => (float)$row['vfr'],
            'Night' => (float)$row['night'],
            'Total' => (float)$row['total']
        ];
    }
    $stmt->close();

    // 4. Get logged hours
    $logged = [];
    $stmt = $mysqli->prepare("SELECT craft_type,
            SUM(CASE WHEN pic_user_id = ? THEN hours ELSE 0 END) as pic,
            SUM(CASE WHEN sic_user_id = ? THEN hours ELSE 0 END) as sic,
            SUM(ifr) as ifr, 
            SUM(vfr) as vfr,
            SUM(night_time) as night, 
            SUM(hours) as total
            FROM pilot_log_book 
            WHERE user_id = ?
            GROUP BY craft_type");
    
    $stmt->bind_param("iii", $userId, $userId, $userId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error, 500);
    }
    
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $logged[$row['craft_type']] = [
            'PIC' => (float)$row['pic'],
            'SIC' => (float)$row['sic'],
            'IFR' => (float)$row['ifr'],
            'VFR' => (float)$row['vfr'],
            'Night' => (float)$row['night'],
            'Total' => (float)$row['total']
        ];
    }
    $stmt->close();

    // 5. Combine results
    $result = [];
    $allCrafts = array_unique(array_merge(array_keys($initial), array_keys($logged)));
    foreach ($allCrafts as $craft) {
        $result[$craft] = [
            'PIC' => ($initial[$craft]['PIC'] ?? 0) + ($logged[$craft]['PIC'] ?? 0),
            'SIC' => ($initial[$craft]['SIC'] ?? 0) + ($logged[$craft]['SIC'] ?? 0),
            'IFR' => ($initial[$craft]['IFR'] ?? 0) + ($logged[$craft]['IFR'] ?? 0),
            'VFR' => ($initial[$craft]['VFR'] ?? 0) + ($logged[$craft]['VFR'] ?? 0),
            'Night' => ($initial[$craft]['Night'] ?? 0) + ($logged[$craft]['Night'] ?? 0),
            'Total' => ($initial[$craft]['Total'] ?? 0) + ($logged[$craft]['Total'] ?? 0)
        ];
    }

    $response = [
        'success' => true,
        'data' => $result
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code($e->getCode() >= 400 ? $e->getCode() : 500);
}

echo json_encode($response);
exit;