<?php
// Get Session Types - AJAX endpoint for session types data

header('Content-Type: application/json');
include 'connect.php';

try {
    $sql = "SELECT id, name FROM session_types ORDER BY name";
    $result = $conn->query($sql);
    
    $session_types = [];
    while ($row = $result->fetch_assoc()) {
        $session_types[] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }
    
    echo json_encode($session_types);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
