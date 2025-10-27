<?php
// contracts.php (FINAL, READ-ONLY VERSION)

if (session_status() === PHP_SESSION_NONE) session_start();
// Basic authentication check: any logged-in user can see this page.
if (!isset($_SESSION['HeliUser'])) {
    header("Location: index.php"); 
    exit;
}

$page = "contracts";
include "header.php";
?>

<div class="container" style="padding-top: 90px;">
    <div class="row">
        <div class="col-12 text-center">
            <h2>Company Contracts & Customers</h2>
        </div>
    </div>

    <!-- The content will be loaded by JavaScript into these containers -->
    <div class="row" style="margin-top: 20px;">
        <div class="col-md-6">
            <h3>Customers</h3>
            <div id="customerList">
                <p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading customers...</p>
            </div>
        </div>
        <div class="col-md-6">
            <h3>Contracts</h3>
            <div id="contracts">
                 <p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading contracts...</p>
            </div>
        </div>
    </div>
</div>

<?php
// Define the single JavaScript file this page needs.
$page_scripts = [
    'contractfunctions.js' // The path from your project root.
];
include_once "footer.php"; 
?>