<?php
// Test AJAX endpoint for programs
header('Content-Type: application/json');

try {
    // Test basic functionality
    $response = [
        'success' => true,
        'message' => 'Test endpoint working',
        'data' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'post_data' => $_POST,
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => null
    ];
    
    echo json_encode($response);
}
?>
