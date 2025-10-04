<?php
// Simple test to debug the AJAX issue
header('Content-Type: application/json');

// Test if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

echo json_encode([
    'is_ajax' => $is_ajax,
    'headers' => getallheaders(),
    'post_data' => $_POST,
    'method' => $_SERVER['REQUEST_METHOD'],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>