<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

$lastHeliSelected = "";
if (isset($_COOKIE["lastHeliSelected"])) {
    $_SESSION["lastHeliSelected"] = $_COOKIE["lastHeliSelected"];
}

if (isset($_SESSION["lastHeliSelected"])) {
    $lastHeliSelected = $_SESSION["lastHeliSelected"];
}

$page = "pilots";
include_once "header.php";

?>

<!-- No need for extra <!DOCTYPE>, <html>, <head> as they are in header.php -->
<div class="container-fluid mt-4" style="padding-top: 40px;">
    <div class="row">
        <!-- Pilot List Column -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-3">Pilot List</h4>
                    <div class="search-box mb-3">
                        <input type="text" id="search_pilot" class="form-control" placeholder="Search pilots...">
                    </div>
                    <div class="row">
                        <div class="col-sm-6">
                            <label for="sortBy" class="form-label form-label-sm">Sort By:</label>
                            <select id="sortBy" class="form-select form-select-sm">
                                <option value="name">Alphabetical (A-Z)</option>
                                <option value="seniority">Seniority (by Hire Date)</option>
                                <option value="filter_pic">Filter by: PIC Qualified</option>
                                <option value="filter_sic">Filter by: SIC Qualified</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="craftTypeFilter" class="form-label form-label-sm">Filter by Craft:</label>
                            <select id="craftTypeFilter" class="form-select form-select-sm">
                                <option value="all">All Crafts</option>
                                <!-- Craft types will be loaded here by JavaScript -->
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card-body" style="padding: 0;">
                    <div id="pilots_list" class="list-group list-group-flush">
                        <div class="text-center p-5">
                            <i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading pilots...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pilot Details Column -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h2>Pilot Information</h2>
                </div>
                <div class="card-body" id="pilot_info">
                    <div class="alert alert-info" id="default_pilot_message">
                        Please select a pilot to view details
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- END OF PAGE HTML CONTENT -->

<?php
// =========================================================================
// === DEFINE PAGE-SPECIFIC SCRIPTS (THE OTHER HALF OF THE FIX)          ===
// =========================================================================
// This array tells footer.php which scripts to load for THIS PAGE ONLY.
$page_scripts = [
    'pilotfunctions.js',
];

// Now, include the footer. It will load jQuery first, then the scripts above.
include_once "footer.php"; 
?>