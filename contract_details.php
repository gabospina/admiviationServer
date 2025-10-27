<?php
// contract_details.php - CORRECTED & SECURED

if (session_status() === PHP_SESSION_NONE) session_start();
include_once "db_connect.php";

// Add a full authentication and session check for security
if (!isset($_SESSION['HeliUser']) || !isset($_SESSION['company_id'])) {
    header("Location: index.php"); 
    exit;
}

// Get the logged-in company's ID from the session
$company_id = (int)$_SESSION['company_id'];

// Get and validate the contract ID from the URL.
$contract_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($contract_id <= 0) {
    die("Invalid Contract ID provided.");
}

// --- QUERY 1: Get the basic contract and customer details ---
$contract_name = "";
$customer_name = "";

// FIX: Added 'AND ct.company_id = ?' to the WHERE clause.
// EXPLANATION: This prevents a user from one company from viewing another company's
// contract details by simply changing the 'id' in the URL. It's a critical security fix.
$stmt_contract = $mysqli->prepare(
    "SELECT ct.contract_name, cust.customer_name 
     FROM contracts ct 
     JOIN customers cust ON ct.customer_id = cust.id 
     WHERE ct.id = ? AND ct.company_id = ?"
);
if ($stmt_contract) {
    // FIX: Bind both the contract ID and the company ID to the query.
    $stmt_contract->bind_param("ii", $contract_id, $company_id);
    $stmt_contract->execute();
    $result_contract = $stmt_contract->get_result();
    if ($contract = $result_contract->fetch_assoc()) {
        $contract_name = htmlspecialchars($contract['contract_name']);
        $customer_name = htmlspecialchars($contract['customer_name']);
    }
    $stmt_contract->close();
}
// If the contract name is still empty, it means either the contract doesn't exist
// OR it doesn't belong to this company. In either case, deny access.
if (empty($contract_name)) {
    die("Contract not found or you do not have permission to view it.");
}


// --- QUERY 2: Get all crafts assigned to this contract (This query is safe as it relies on the validated $contract_id) ---
$assigned_crafts = [];
$stmt_crafts = $mysqli->prepare(
    "SELECT c.craft_type, c.registration 
     FROM crafts c 
     JOIN contract_crafts cc ON c.id = cc.craft_id 
     WHERE cc.contract_id = ?"
);
if ($stmt_crafts) {
    $stmt_crafts->bind_param("i", $contract_id);
    $stmt_crafts->execute();
    $assigned_crafts = $stmt_crafts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_crafts->close();
}


// --- QUERY 3: Get all pilots assigned to this contract (Also safe) ---
$assigned_pilots = [];
$stmt_pilots = $mysqli->prepare(
    "SELECT CONCAT(u.firstname, ' ', u.lastname) AS pilot_full_name 
     FROM users u 
     JOIN contract_pilots cp ON u.id = cp.user_id 
     WHERE cp.contract_id = ?"
);
if ($stmt_pilots) {
    $stmt_pilots->bind_param("i", $contract_id);
    $stmt_pilots->execute();
    $assigned_pilots = $stmt_pilots->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pilots->close();
}

$mysqli->close();
?>

<!DOCTYPE html>
<!-- The rest of your HTML code remains unchanged -->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contract Details Report</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body { padding: 20px; background-color: #f4f7f6; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .details-section { margin-top: 30px; }
        .print-button { margin-left: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>Contract: <?php echo $contract_name; ?></h1>
                <h3 class="text-muted">Customer: <?php echo $customer_name; ?></h3>
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-info print-button"><i class="fa fa-print"></i> Print Report</button>
            </div>
        </div>
        
        <div class="row details-section">
            <div class="col-md-6">
                <h4>Assigned Crafts</h4>
                <?php if (!empty($assigned_crafts)): ?>
                    <ul class="list-group">
                        <?php foreach ($assigned_crafts as $craft): ?>
                            <li class="list-group-item">
                                <?php echo htmlspecialchars($craft['registration']); ?> 
                                <small class="text-muted">(<?php echo htmlspecialchars($craft['craft_type']); ?>)</small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="alert alert-info">No crafts have been assigned to this contract.</div>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <h4>Assigned Pilots</h4>
                <?php if (!empty($assigned_pilots)): ?>
                    <ul class="list-group">
                        <?php foreach ($assigned_pilots as $pilot): ?>
                            <li class="list-group-item"><?php echo htmlspecialchars($pilot['pilot_full_name']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="alert alert-info">No pilots have been assigned to this contract.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>