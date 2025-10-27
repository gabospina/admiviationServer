<?php
// craft.php - FINAL READ-ONLY VERSION
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if(!isset($_SESSION["HeliUser"])){
  header("Location: index.php");
  exit();
}

$page = "crafts";
include_once "header.php";
?>

<div class="container inner-md">
    <div class="row">
      <h2 class="text-black text-center">Company Fleet</h2>
    </div>
    
    <!-- The new, read-only JavaScript will build the table inside here. -->
    <div id="crafts-display-container">
        <!-- The initial loading message -->
    </div>
</div>

<?php
// =========================================================================
// === DEFINE PAGE-SPECIFIC SCRIPTS (CRAFTS) - FINAL                     ===
// =========================================================================

// This page has no special stylesheets.
$page_stylesheets = []; 

// This page ONLY needs its own custom script.
$page_scripts = [
    'craftfunctions.js'
];

include_once "footer.php"; 
?>