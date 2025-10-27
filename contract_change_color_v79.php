<?php
// change_contract_color.php
require_once "db_connect.php";
header('Content-Type: application/json');

if (!isset($_SESSION['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$contractId = (int)$_POST['id'];
$color = $_POST['color'];

// Validate color format
if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
    echo json_encode(['success' => false, 'message' => 'Invalid color format']);
    exit;
}

$stmt = $mysqli->prepare("UPDATE contracts SET color = ? WHERE id = ? AND company_id = ?");
$stmt->bind_param("sii", $color, $contractId, $_SESSION['company_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}