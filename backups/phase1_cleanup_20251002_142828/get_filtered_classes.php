<?php
header('Content-Type: application/json');

include 'connect.php';

$stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;

if ($stream_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid stream ID',
        'classes' => []
    ]);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id, name FROM classes WHERE is_active = 1 AND stream_id = ? ORDER BY name");
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $stream_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $classes = [];
    while ($row = $result->fetch_assoc()) {
        $classes[] = [
            'id' => (int)$row['id'],
            'name' => $row['name']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Classes loaded successfully',
        'classes' => $classes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'classes' => []
    ]);
}

$conn->close();
?>