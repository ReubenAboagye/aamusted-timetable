<?php
/**
 * CSRF Protection Helper Functions
 * Provides consistent CSRF token generation and validation across the application
 */

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate a new CSRF token and store it in session
     * @return string The generated CSRF token
     */
    function generateCSRFToken() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('getCSRFToken')) {
    /**
     * Get the current CSRF token from session
     * @return string The current CSRF token
     */
    function getCSRFToken() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['csrf_token'] ?? generateCSRFToken();
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Validate a CSRF token
     * @param string $token The token to validate
     * @return bool True if valid, false otherwise
     */
    function validateCSRFToken($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('requireCSRFToken')) {
    /**
     * Require a valid CSRF token or die with error
     * @param string $token The token to validate
     * @param string $errorMessage Error message to display
     */
    function requireCSRFToken($token, $errorMessage = 'CSRF token validation failed') {
        if (!validateCSRFToken($token)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                // AJAX request
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $errorMessage
                ]);
            } else {
                // Regular request
                die($errorMessage);
            }
            exit;
        }
    }
}

if (!function_exists('csrfTokenField')) {
    /**
     * Generate a hidden input field with CSRF token
     * @return string HTML input field
     */
    function csrfTokenField() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrfMetaTag')) {
    /**
     * Generate a meta tag with CSRF token for AJAX requests
     * @return string HTML meta tag
     */
    function csrfMetaTag() {
        return '<meta name="csrf-token" content="' . htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
?>