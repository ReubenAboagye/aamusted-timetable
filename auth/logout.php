<?php
include_once __DIR__ . '/../includes/auth.php';

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}

admin_logout();

$next = isset($_GET['next']) ? $_GET['next'] : 'auth/login.php';
header('Location: ' . $next);
exit;


