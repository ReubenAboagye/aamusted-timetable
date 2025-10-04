<?php
// Debug AJAX programs endpoint
header('Content-Type: application/json');

try {
    include 'connect.php';
    
    // Check if programs table exists
    $result = $conn->query("SHOW TABLES LIKE 'programs'");
    $table_exists = $result && $result->num_rows > 0;
    
    if (!$table_exists) {
        throw new Exception("Programs table does not exist");
    }
    
    // Check table structure
    $result = $conn->query("DESCRIBE programs");
    $columns = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }
    }
    
    // Test the delete query
    $test_id = 1; // Use a test ID
    $sql = "UPDATE programs SET is_active = 0 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $response = [
        'success' => true,
        'message' => 'Database test successful',
        'data' => [
            'table_exists' => $table_exists,
            'columns' => $columns,
            'statement_prepared' => $stmt ? true : false,
            'post_data' => $_POST,
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => [
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'post_data' => $_POST
        ]
    ];
    
    echo json_encode($response);
}
?>
