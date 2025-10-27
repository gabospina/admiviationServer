<?php
// checkAdmin.php v84
if (session_status() == PHP_SESSION_NONE) {
    session_start();

    // --- SESSION-BASED CSRF VALIDATION ---
    $submitted_token = $_POST['form_token'] ?? '';

    if (empty($submitted_token)) {
        throw new Exception("Security token missing. Please refresh the page.", 403);
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if (!hash_equals($_SESSION['csrf_token'], $submitted_token)) {
        throw new Exception("Invalid security token. Please refresh the page.", 403);
    }
}

// This script is expected by trainingfunctions.js to return a numeric admin level.
if (isset($_SESSION["admin"])) {
    echo intval($_SESSION["admin"]);
} else {
    echo 0;
}
?>