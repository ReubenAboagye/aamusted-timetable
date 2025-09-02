<?php
// Flash helper (session-based)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('flash_set')) {
    function flash_set($type, $message)
    {
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }
}

if (!function_exists('flash_get')) {
    function flash_get()
    {
        if (!isset($_SESSION['_flash'])) return null;
        $f = $_SESSION['_flash'];
        unset($_SESSION['_flash']);
        return $f;
    }
}

if (!function_exists('redirect_with_flash')) {
    function redirect_with_flash($location, $type, $message)
    {
        flash_set($type, $message);
        // Use absolute local path if relative
        if (!preg_match('/^https?:\/\//', $location) && strpos($location, '/') !== 0) {
            $location = $location;
        }
        // If headers already sent, render a JS-based redirect as a safe fallback
        if (!headers_sent()) {
            header('Location: ' . $location);
            exit;
        } else {
            // Attempt a safe client-side redirect while preserving the flash in session
            $escaped = htmlspecialchars($location, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Redirecting</title>';
            echo '<meta http-equiv="refresh" content="0;url=' . $escaped . '">';
            echo '<script>try{window.location.replace(' . json_encode($location) . ');}catch(e){window.location.href=' . json_encode($location) . ';}</script>';
            echo '</head><body><p>Redirecting... If you are not redirected, <a href="' . $escaped . '">click here</a>.</p></body></html>';
            exit;
        }
    }
}


