<?php
// Add these at the very top
ini_set('display_errors', 0);
error_reporting(0);
session_start();
header('Content-Type: application/json');

require_once 'db_connect.php';

try {
    $query = "SELECT id, firstname AS fname, lastname AS lname 
              FROM users 
              WHERE company_id = ? 
              ORDER BY lastname, firstname";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $mysqli->error);
    }
    
    $company_id = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : 132;
    $stmt->bind_param('i', $company_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }
    
    $pilots = [];
    while ($row = $result->fetch_assoc()) {
        $pilots[] = [
            'id' => $row['id'],
            'fname' => $row['fname'],
            'lname' => $row['lname'],
            'name' => $row['lname'] . ', ' . $row['fname']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $pilots
    ]);
    exit();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit();
}
?>