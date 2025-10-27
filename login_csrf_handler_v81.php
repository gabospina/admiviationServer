<?php
// login_csrf_handler.php - PERMANENT FIX VERSION
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class CSRFHandler {
    private static $tokenKey = 'csrf_token';
    private static $expireKey = 'csrf_token_expire';
    private static $tokenLifetime = 3600; // 1 hour
    
    public static function generateToken() {
        // Only generate new token if expired or doesn't exist
        if (self::shouldRegenerateToken()) {
            $_SESSION[self::$tokenKey] = bin2hex(random_bytes(32));
            $_SESSION[self::$expireKey] = time() + self::$tokenLifetime;
        }
        return $_SESSION[self::$tokenKey];
    }
    
    public static function validateToken($submittedToken) {
        // Get current token
        $currentToken = $_SESSION[self::$tokenKey] ?? null;
        $tokenExpire = $_SESSION[self::$expireKey] ?? 0;
        
        // Check if token exists and isn't expired
        if (!$currentToken || $tokenExpire < time()) {
            return false;
        }
        
        // Validate the token
        return hash_equals($currentToken, $submittedToken);
    }
    
    public static function shouldRegenerateToken() {
        return empty($_SESSION[self::$tokenKey]) || 
               empty($_SESSION[self::$expireKey]) || 
               $_SESSION[self::$expireKey] < time();
    }
    
    // public static function getToken() {
    //     return self::generateToken();
    // }
}

// Initialize token on every page load
CSRFHandler::generateToken();
?>