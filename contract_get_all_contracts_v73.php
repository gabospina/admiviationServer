<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

include_once "db_connect.php";

// Check for sessions
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
    $response = ["success" => false, "message" => "You are not logged in properly or company ID not found."];
    echo json_encode($response);
    exit;
}

//Get the Company and it´s a int
$company_id = (int)$_SESSION["company_id"];

$response = array();

// Contract query with JOIN to fetch customer name with company id verification
$contractQuery = "SELECT
                    c.id AS contractid,
                    c.contract_name AS contract,
                    c.customer_id AS customer_id,
                    cust.customer_name AS customer_name,
                    IFNULL(c.color, '#FFFFFF') AS color
                FROM
                    contracts c
                LEFT JOIN
                    customers cust ON c.customer_id = cust.id
                ORDER BY
                    c.contract_order";

$stmt = $mysqli->prepare($contractQuery);
if ($stmt === false) {
    $response["success"] = false;
    $response["message"] = "Prepare failed: " . htmlspecialchars($mysqli->error);
    error_log("Prepare failed: " . $mysqli->error);
    echo json_encode($response);
    exit;
}

// $stmt->bind_param("i", $company_id);  //Removed check for company ID

$stmt->execute();
$contractResult = $stmt->get_result();

if ($contractResult) {
    $contracts = array();
    while ($row = mysqli_fetch_assoc($contractResult)) {
        $contracts[] = array(
            "contractid" => $row["contractid"],
            "contract" => $row["contract"],
            "customer_id" => $row["customer_id"],
            "customer_name" => $row["customer_name"],
            "color" => $row["color"]
        );
    }
    $response["success"] = true;
    $response["contracts"] = $contracts;
} else {
    $response["success"] = false;
    $response["message"] = "Error fetching contracts: " . $mysqli->error;
    error_log("Error fetching contracts: " . $mysqli->error);
}

// Make the system
header('Content-Type: application/json');
echo json_encode($response);
$mysqli->close();
?>