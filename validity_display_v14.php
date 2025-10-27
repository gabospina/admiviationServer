<?php
require_once 'db_connect.php'; // Your database connection file

header('Content-Type: application/json');

try {
    // if (!isset($_GET['pilot_id']) || !is_numeric($_GET['pilot_id'])) {
    //     throw new Exception('Invalid pilot ID');
    // }

    // $pilotId = $_GET['pilot_id'];

    // $pilotId = 64;    
    
    $stmt = $pdo->prepare("
        SELECT v.* 
        FROM validity v
        WHERE v.pilot_id = ?
    ");
    $stmt->execute([$pilotId]);
    $validityData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$validityData) {
        echo json_encode(['status' => 'success', 'data' => []]);
        exit;
    }

    // Format dates and remove unnecessary fields
    $responseData = [];
    foreach ($validityData as $field => $value) {
        if ($field === 'id' || $field === 'pilot_id' || $field === 'created_at' || $field === 'updated_at') continue;
        $responseData[$field] = $value ?: null;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $responseData
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
