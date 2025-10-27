<?php
// flight-tracker/user_new_location.php
// Saves a new location to the database.

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../db_connect.php';
// You could add your CSRF and permission helpers here for extra security

$response = ['success' => false, 'message' => 'An error occurred.'];

if (!isset($_SESSION['HeliUser'], $_SESSION['company_id'])) {
    http_response_code(401);
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit;
}

$company_id = (int)$_SESSION['company_id'];
$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name'] ?? '');
$lat = filter_var($data['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lon = filter_var($data['lon'] ?? null, FILTER_VALIDATE_FLOAT);

if (empty($name) || $lat === false || $lon === false) {
    http_response_code(400);
    $response['message'] = 'Invalid data provided. Please check all fields.';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $mysqli->prepare(
        "INSERT INTO user_locations (company_id, location_name, latitude, longitude) VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("isdd", $company_id, $name, $lat, $lon);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'New location saved successfully!';
        $response['new_location'] = [
            'id' => $stmt->insert_id,
            'location_name' => $name,
            'latitude' => $lat,
            'longitude' => $lon
        ];
    } else {
        throw new Exception('Failed to save location.');
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>