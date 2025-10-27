<?php
// hangar_ajax_handler.php

// This is the ONLY file your hangar JavaScript will call for AJAX.
// It MUST be the first thing executed. No blank lines or spaces before <?php.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set the default content type to JSON for all responses from this handler.
header('Content-Type: application/json');

// --- Authentication Check ---
// We do this check once, right at the start.
// If the user isn't logged in, no actions will be processed.
if (!isset($_SESSION["HeliUser"]) || empty($_SESSION["HeliUser"])) {
    http_response_code(401); // 401 Unauthorized
    echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in again.']);
    exit();
}

// Determine which action the JavaScript wants to perform.
// We will send this in the AJAX call, e.g., 'action=load_clock' or 'action=save_clock'.
$action = $_REQUEST['action'] ?? '';

// This is a "router" that includes the correct logic file based on the 'action' parameter.
switch ($action) {
    case 'load_clock_settings':
        // The request is to load clock settings.
        include_once 'logic/hangar_save_clock_logic.php';
        break;

    case 'save_clock_settings':
        // The request is to save clock settings.
        // The same logic file can handle both GET (load) and POST (save).
        include_once 'logic/hangar_save_clock_logic.php';
        break;

    // --- Example for future use ---
    // case 'get_hangar_inventory':
    //     include_once 'logic/hangar_inventory_logic.php';
    //     break;

    default:
        // If an unknown or no action is provided, send a clear error.
        http_response_code(400); // 400 Bad Request
        echo json_encode(['success' => false, 'error' => 'Invalid or missing AJAX action.']);
        break;
}

// The included logic file is expected to handle its own exit().
// This is a fallback.
exit();
?>