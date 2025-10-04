<?php
// Simple test for programs AJAX endpoint
header('Content-Type: application/json');

try {
    // Test database connection
    include 'connect.php';
    
    // Test if programs table exists
    $result = $conn->query("SHOW TABLES LIKE 'programs'");
    $table_exists = $result && $result->num_rows > 0;
    
    $response = [
        'success' => true,
        'message' => 'Database connection test',
        'data' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'database_connected' => $conn ? true : false,
            'programs_table_exists' => $table_exists,
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
