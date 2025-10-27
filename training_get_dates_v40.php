<?php
// assets/php/get_training_dates.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'stats_api_response.php';

$apiResponse = new ApiResponse();

if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    $apiResponse->setError("Authentication required.")->send();
    exit;
}
$company_id = (int)$_SESSION['company_id'];

try {
    global $mysqli;
    if (!$mysqli || $mysqli->connect_error) {
        throw new Exception("Database connection error.", 500);
    }

    // The JS expects objects with 'id' and 'on' (date_on) date.
    // 'off' (date_off) is also fetched for potential future use or if X-Editable needs it.
    $sql = "SELECT id, date_on, date_off FROM training_availability WHERE company_id = ? ORDER BY date_on ASC";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("SQL prepare error: " . $mysqli->error, 500);
    }

    $stmt->bind_param("i", $company_id);

    if (!$stmt->execute()) {
        throw new Exception("SQL execute error: " . $stmt->error, 500);
    }

    $result = $stmt->get_result();
    $training_dates = [];
    while ($row = $result->fetch_assoc()) {
        // The JS specifically processes `res[i].on` to create moment objects.
        // It also uses `id` for editing/deleting.
        $training_dates[] = [
            'id' => $row['id'],
            'on' => $row['date_on'], // Corresponds to res[i].on in JS
            'off' => $row['date_off'] // Corresponds to res[i].off in JS for editable
        ];
    }
    $stmt->close();

    // The JS directly parses the JSON result.
    // So, similar to get_all_crafts, we echo the array directly.
    echo json_encode($training_dates);
    exit;

} catch (Exception $e) {
    $httpStatusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($httpStatusCode);
    echo json_encode(["error" => true, "message" => $e->getMessage()]);
}
?>