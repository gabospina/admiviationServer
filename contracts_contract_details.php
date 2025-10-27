<?php
include_once "db_connect.php"; // Include your database connection file

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get the contract ID from the URL
$contract_id = isset($_GET['contract_id']) ? intval($_GET['contract_id']) : 0;

// Check if the contract ID is valid
if ($contract_id <= 0) {
    die("Invalid contract ID."); // Handle invalid contract IDs appropriately
}

// SQL query to retrieve the contract name, customer name, craft types, registrations, and pilot full names
$sql = "SELECT
        contracts.contract_name,
        customers.customer_name,
        c.craft_type,
        c.registration,
        COALESCE(CONCAT(u.firstname, ' ', u.lastname), 'N/A') AS pilot_full_name
    FROM
        contracts
    INNER JOIN
        customers ON contracts.customer_id = customers.id
    JOIN
        contract_crafts AS cc ON contracts.id = cc.contract_id
    JOIN
        crafts AS c ON cc.craft_type_id = c.id
    LEFT JOIN
        contract_pilots AS cp ON cc.craft_type_id = cp.craft_type_id AND contracts.id = cp.contract_id
    LEFT JOIN
        users AS u ON cp.user_id = u.id
    WHERE
        contracts.id = ?";

$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($mysqli->error));
}

$stmt->bind_param("i", $contract_id);

if (!$stmt->execute()) {
    die("Execute failed: " . htmlspecialchars($stmt->error));
}

$result = $stmt->get_result();
$contract_name = "";
$customer_name = "";
if ($result->num_rows > 0) {
    $firstRow = $result->fetch_assoc(); // Fetch the first row to get contract and customer names
    $contract_name = htmlspecialchars($firstRow['contract_name']);
    $customer_name = htmlspecialchars($firstRow['customer_name']);

    // Reset the result pointer to the beginning
    $result->data_seek(0);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Contract Details</title>
    <!-- Add Bootstrap CSS or your preferred styling -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h1>Contract Details: <?php echo $contract_name; ?> - <?php echo $customer_name; ?></h1>
        <a href="contracts.php" class="btn btn-primary">Back to Contracts</a>
        <?php
        if ($result->num_rows > 0) {
            echo "<table class='table table-bordered'>";
            echo "<thead><tr><th>Craft Type</th><th>Registration</th><th>Pilot Full Name</th></tr></thead>";
            echo "<tbody>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['craft_type']) . "</td>";
                echo "<td>" . htmlspecialchars($row['registration']) . "</td>";
                echo "<td>" . htmlspecialchars($row['pilot_full_name']) . "</td>"; // No need for ?? 'N/A'
                echo "</tr>";
            }
            echo "</tbody></table>";
        } else {
            echo "<p>No details found for this contract.</p>";
        }

        $stmt->close();
        $mysqli->close();
        ?>
    </div>
</body>
</html>