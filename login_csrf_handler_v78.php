<?php
// login_csrf_handler.php - FIXED VERSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function generate_csrf_token() {
    // Always generate a new token and store it in session
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_expire'] = time() + 3600; // 1 hour expiration
    error_log("DEBUG: Generated new CSRF token: " . $_SESSION['csrf_token']);
    return $_SESSION['csrf_token'];
}

function CSRFHandler::validateToken($submitted_token) {
    // DEBUG: Log what we're checking
    error_log("DEBUG: Validating CSRF token:");
    error_log("DEBUG: - Session token: " . ($_SESSION['csrf_token'] ?? 'NOT SET'));
    error_log("DEBUG: - Submitted token: " . ($submitted_token ?? 'NOT SET'));
    error_log("DEBUG: - Token expires: " . ($_SESSION['csrf_token_expire'] ?? 'NOT SET'));
    error_log("DEBUG: - Current time: " . time());
    
    // Check if token exists in session
    if (empty($_SESSION['csrf_token'])) {
        error_log("DEBUG: CSRF validation failed - No session token");
        return false;
    }
    
    // Check if token is expired
    if (empty($_SESSION['csrf_token_expire']) || $_SESSION['csrf_token_expire'] < time()) {
        error_log("DEBUG: CSRF validation failed - Token expired");
        // Regenerate token if expired
        generate_csrf_token();
        return false;
    }
    
    // Validate the token
    $isValid = !empty($submitted_token) && hash_equals($_SESSION['csrf_token'], $submitted_token);
    error_log("DEBUG: CSRF validation result: " . ($isValid ? "VALID" : "INVALID"));
    
    return $isValid;
}

// Only generate token if it doesn't exist or is expired
if (empty($_SESSION['csrf_token']) || 
    empty($_SESSION['csrf_token_expire']) || 
    $_SESSION['csrf_token_expire'] < time()) {
    generate_csrf_token();
}
?>