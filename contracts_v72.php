<?php
// contracts.php (SIMPLIFIED, READ-ONLY VERSION)
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$page = "contracts";
include "header.php";
?>

<div class="container inner-sm">
    <div class="row">
        <h2 class="text-black text-center">Company Contracts & Customers</h2>
    </div>

    <!-- Search and Filter -->
    <!-- MODIFIED: Removed 'outer-top-md' class and added an inline style attribute -->
    <div class="row" style="margin-top: -25px;" id="contractFilter">
        <div class="col-sm-offset-5 col-sm-4 outer-xs">
            <form class="form-inline">
                <div class="input-group">
                    <input type="text" id="search_contracts" class="form-control" placeholder="Search by Contract Name...">
                    <span class="input-group-btn">
                        <input type="button" id="contractSearchGo" value="Search" class="btn btn-success">
                    </span>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer and Contract Display -->
    <!-- MODIFIED: Removed 'outer-top-md' class and added an inline style attribute -->
    <div class="row" style="margin-top: -30px;">
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

<!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.css" integrity="sha512-LbtS+5D/aH9K99eNPaCJVqP1K+QK8Pz2V+K63tEgzDyzz74V47LJX2l4cIEefUe1EivZtKkn0I3wTr9MVKWew==" crossorigin="anonymous" referrerpolicy="no-referrer" /> -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.1/spectrum.min.js" integrity="sha512-1aNp9qKP+hKU/VJwCtYqJP9tdZWbMDN5pEEXXoXT0pTAxZq1HHZhNBR/dtTNSrHO4U1FsFGGILbqG1O9nl8Mdg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script src="js/jquery-3.7.1.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="contractfunctions.js"></script>
<!-- <script src="daily_manager-contracts.js"></script> -->

<?php include_once "footer.php"; ?>