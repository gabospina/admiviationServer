<?php
// craft_toggle_status.php
session_start();
header('Content-Type: application/json');

require_once 'db_connect.php'; // Your database connection file

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Check for authentication and valid request method
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
    $response['message'] = "Authentication error.";
    echo json_encode($response);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = "Invalid request method.";
    echo json_encode($response);
    exit();
}

// Check if required data was sent
if (!isset($_POST['craft_id']) || !isset($_POST['new_status'])) {
    $response['message'] = "Missing required data.";
    echo json_encode($response);
    exit();
}

// Sanitize and validate input
$craft_id = (int)$_POST['craft_id'];
$new_status = (int)$_POST['new_status'];
$company_id = (int)$_SESSION['company_id'];

// The new status must be either 0 or 1
if ($new_status !== 0 && $new_status !== 1) {
    $response['message'] = "Invalid status value provided.";
    echo json_encode($response);
    exit();
}

// Prepare the database connection
if (!isset($mysqli) || $mysqli->connect_error) {
    $response['message'] = "Database connection failed.";
    echo json_encode($response);
    exit();
}

// --- SECURE DATABASE UPDATE ---
// The query updates the 'alive' column for the specific craft_id,
// but ONLY if it also belongs to the logged-in user's company.
// This is a critical security check.
$sql = "UPDATE crafts SET alive = ? WHERE id = ? AND company_id = ?";

if ($stmt = $mysqli->prepare($sql)) {
    // Bind parameters: integer, integer, integer
    $stmt->bind_param("iii", $new_status, $craft_id, $company_id);

    if ($stmt->execute()) {
        // Check if a row was actually updated
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Status updated successfully.';
        } else {
            // This can happen if the craft ID doesn't belong to the company
            $response['message'] = 'No craft found with that ID for your company, or status is already set.';
        }
    } else {
        $response['message'] = 'Database execute error: ' . $stmt->error;
    }

    $stmt->close();
} else {
    $response['message'] = 'SQL prepare error: ' . $mysqli->error;
}

$mysqli->close();

echo json_encode($response);
?>