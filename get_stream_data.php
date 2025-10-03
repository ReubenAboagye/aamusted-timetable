<?php
require_once 'connect.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Stream ID is required']);
    exit;
}

$stream_id = (int) $_GET['id'];

try {
    // Fetch stream data
    $stmt = $conn->prepare("SELECT * FROM streams WHERE id = ?");
    $stmt->bind_param("i", $stream_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Stream not found']);
        exit;
    }
    
    $stream = $result->fetch_assoc();
    
    // Fetch selected time slots for this stream
    $time_slots_stmt = $conn->prepare("SELECT time_slot_id FROM stream_time_slots WHERE stream_id = ? AND is_active = 1");
    $time_slots_stmt->bind_param("i", $stream_id);
    $time_slots_stmt->execute();
    $time_slots_result = $time_slots_stmt->get_result();
    
    $selected_time_slots = [];
    while ($row = $time_slots_result->fetch_assoc()) {
        $selected_time_slots[] = $row['time_slot_id'];
    }
    
    echo json_encode([
        'success' => true,
        'stream' => $stream,
        'selected_time_slots' => $selected_time_slots
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
