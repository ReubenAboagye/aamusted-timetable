<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
include_once 'connect.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$action = $input['action'];
$response = ['success' => false, 'message' => ''];

try {
    if ($action === 'bulk_reactivate') {
        $table = $input['table'];
        $ids = $input['ids'];
        
        if (empty($ids) || !is_array($ids)) {
            throw new Exception('No records selected');
        }
        
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $sql = "UPDATE $table SET is_active = 1 WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $response = [
                'success' => true, 
                'message' => "$affected_rows records reactivated successfully!",
                'affected_rows' => $affected_rows
            ];
        } else {
            throw new Exception('Error reactivating records: ' . $stmt->error);
        }
        $stmt->close();
        
    } elseif ($action === 'reactivate') {
        $table = $input['table'];
        $id = (int)$input['id'];
        
        $sql = "UPDATE $table SET is_active = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $response = [
                'success' => true, 
                'message' => "Record reactivated successfully!",
                'affected_rows' => $affected_rows
            ];
        } else {
            throw new Exception('Error reactivating record: ' . $stmt->error);
        }
        $stmt->close();
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

$conn->close();
echo json_encode($response);
?>
