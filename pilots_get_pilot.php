<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

try {
    if (!isset($_POST['id'])) {
        throw new Exception('Pilot ID is required');
    }

    $pilotId = $_POST['id'];
    
    // Get basic pilot info
    $query = "SELECT u.id, u.firstname AS fname, u.lastname AS lname, u.username, 
                     u.email, u.phone, u.comandante, u.nationality,
                     u.nal_license AS ang_license, u.for_license, u.admin,
                     u.profile_picture, u.training
              FROM users u
              WHERE u.id = ?";
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $pilotId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Pilot not found');
    }

    $pilotData = $result->fetch_assoc();

    // Get validity dates
    $validityQuery = "SELECT field, value FROM validity WHERE pilot_id = ?";
    $validityStmt = $mysqli->prepare($validityQuery);
    $validityStmt->bind_param('i', $pilotId);
    $validityStmt->execute();
    $validityResult = $validityStmt->get_result();

    $validityData = [];
    while ($row = $validityResult->fetch_assoc()) {
        $validityData[$row['field']] = $row['value'];
    }

    // Get on/off duty information
    $onOffQuery = "SELECT on_date AS `on`, off_date AS `off` FROM pilot_schedule WHERE pilot_id = ?";
    $onOffStmt = $mysqli->prepare($onOffQuery);
    $onOffStmt->bind_param('i', $pilotId);
    $onOffStmt->execute();
    $onOffResult = $onOffStmt->get_result();

    $onOffData = [];
    while ($row = $onOffResult->fetch_assoc()) {
        $onOffData[] = $row;
    }

    // Prepare final response
    $response = [
        'success' => true,
        'data' => [
            'pilot' => $pilotData,
            'validity' => $validityData,
            'onOff' => $onOffData
        ]
    ];

    echo json_encode($response);
    exit();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit();
}
?>