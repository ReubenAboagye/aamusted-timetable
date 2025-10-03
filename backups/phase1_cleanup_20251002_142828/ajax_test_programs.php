<?php
// Simple test endpoint for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

try {
    include 'connect.php';
    
    $response = ['success' => false, 'message' => '', 'data' => null];
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'test_connection') {
        $response['success'] = true;
        $response['message'] = 'Connection successful';
        $response['data'] = ['connection' => $conn ? 'OK' : 'FAILED'];
    } elseif ($action === 'test_edit') {
        // Test edit with minimal data
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        
        if ($id <= 0) {
            throw new Exception('Invalid ID');
        }
        
        if (empty($name)) {
            throw new Exception('Name is required');
        }
        
        // Simple update test
        $sql = "UPDATE programs SET name = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("si", $name, $id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Test update successful';
        } else {
            throw new Exception("Update failed: " . $stmt->error);
        }
        $stmt->close();
    } else {
        throw new Exception("Invalid action: $action");
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Test AJAX Error: " . $e->getMessage());
}

echo json_encode($response);
?>
