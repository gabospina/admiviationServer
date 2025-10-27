<?php
// contract_get_all_contracts.php - CORRECTED
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

include_once "db_connect.php";

// Check for sessions
if (!isset($_SESSION["HeliUser"]) || !isset($_SESSION["company_id"])) {
    header('Content-Type: application/json');
    http_response_code(401); // Unauthorized
    echo json_encode(["success" => false, "message" => "Authentication required."]);
    exit;
}

//Get the Company and ensure it's an integer
$company_id = (int)$_SESSION["company_id"];
$response = [];

// FIX: Added 'WHERE c.company_id = ?' to the SQL query.
// EXPLANATION: This is the critical change. It instructs the database to ONLY select
// contracts that belong to the currently logged-in user's company.
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
                WHERE
                    c.company_id = ?
                ORDER BY
                    c.contract_name ASC"; // Changed to order by name for better UI

$stmt = $mysqli->prepare($contractQuery);
if ($stmt === false) {
    $response["success"] = false;
    $response["message"] = "Prepare failed: " . htmlspecialchars($mysqli->error);
    error_log("Prepare failed: " . $mysqli->error);
    echo json_encode($response);
    exit;
}

// FIX: Bind the company_id to the placeholder in the WHERE clause.
$stmt->bind_param("i", $company_id);

$stmt->execute();
$contractResult = $stmt->get_result();

$contracts = [];
while ($row = $contractResult->fetch_assoc()) {
    $contracts[] = $row; // Simplified the array creation
}

$response["success"] = true;
$response["contracts"] = $contracts;


header('Content-Type: application/json');
echo json_encode($response);
$stmt->close();
$mysqli->close();
?>