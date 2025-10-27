<?php
include_once "db_connect.php";

$response = array();

$sql = "SELECT id, customer_name FROM customers";
$result = $mysqli->query($sql);

if ($result) {
    $customers = array();
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $response["success"] = true;
    $response["customers"] = $customers;
} else {
    $response["success"] = false;
    $response["message"] = "Error fetching customers: " . $mysqli->error;
    error_log("Error fetching customers: " . $mysqli->error);
}

echo json_encode($response);

$mysqli->close();
?>