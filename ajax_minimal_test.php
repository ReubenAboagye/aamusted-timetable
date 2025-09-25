<?php
// Minimal test for edit functionality
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
    
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $department_id = (int)($_POST['department_id'] ?? 0);
        $code = trim($_POST['code'] ?? '');
        $duration = (int)($_POST['duration'] ?? 0);
        
        // Simple update without complex validation
        $sql = "UPDATE programs SET name = ?, department_id = ?, code = ?, duration_years = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("sisii", $name, $department_id, $code, $duration, $id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Program updated successfully!';
        } else {
            throw new Exception("Update failed: " . $stmt->error);
        }
        $stmt->close();
    } else {
        throw new Exception("Invalid action: $action");
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Minimal AJAX Error: " . $e->getMessage());
}

echo json_encode($response);
?>
