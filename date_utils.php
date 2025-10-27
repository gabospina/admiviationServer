<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Convert French format (dd/mm/yyyy) to database format (yyyy-mm-dd)
function frenchToDbDate($frenchDate) {
    $parts = explode('/', $frenchDate);
    if (count($parts) === 3) {
        return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
    }
    return $frenchDate; // fallback if already in correct format
}

// Convert database format (yyyy-mm-dd) to French format (dd/mm/yyyy)
function dbToFrenchDate($dbDate) {
    $parts = explode('-', $dbDate);
    if (count($parts) === 3) {
        return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
    }
    return $dbDate; // fallback
}
?>