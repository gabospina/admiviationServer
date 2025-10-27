<?php
// assets/php/checkAdmin.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// This script is expected by trainingfunctions.js to return a numeric admin level.
// $_SESSION["admin"] is populated from the users.admin column.

if (isset($_SESSION["admin"])) {
    // Output the raw integer value. The JS will parse it.
    echo intval($_SESSION["admin"]);
} else {
    // If no admin level is set in session (e.g., not logged in),
    // output a default level that signifies no special training management rights.
    echo 0;
}
// No exit needed; the echo is sufficient.
?>