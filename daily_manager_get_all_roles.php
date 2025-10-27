<?php
// daily_manager_get_all_roles.php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

// Add permission checks here if necessary

$response = ['success' => false, 'roles' => []];

$sql = "SELECT id, role_name FROM users_roles ORDER BY id ASC"; // <-- Ensure ORDER BY id ASC is here
$result = $mysqli->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $response['roles'][] = $row;
    }
    $response['success'] = true;
}

echo json_encode($response);
?>