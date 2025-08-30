<?php
// Flash helper (session-based)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function flash_set($type, $message)
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get()
{
    if (!isset($_SESSION['_flash'])) return null;
    $f = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $f;
}

function redirect_with_flash($location, $type, $message)
{
    flash_set($type, $message);
    // Use absolute local path if relative
    if (!preg_match('/^https?:\/\//', $location) && strpos($location, '/') !== 0) {
        $location = $location;
    }
    header('Location: ' . $location);
    exit;
}


