<?php
// autocomplete.php
require_once 'db_connect.php';

header('Content-Type: application/json');

$search = isset($_GET['term']) ? $mysqli->real_escape_string($_GET['term']) : '';
$query = "SELECT CONCAT(firstname, ' ', lastname) AS fullname 
          FROM users 
          WHERE firstname LIKE '%$search%' OR lastname LIKE '%$search%'
          LIMIT 10";

$result = $mysqli->query($query);
$suggestions = [];

while ($row = $result->fetch_assoc()) {
    $suggestions[] = [
        'label' => $row['fullname'],
        'value' => $row['fullname']
    ];
}

echo json_encode($suggestions);