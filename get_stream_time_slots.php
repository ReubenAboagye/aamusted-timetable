<?php
require_once 'connect.php';

header('Content-Type: application/json');

try {
    // Get the current stream ID from session or default to 1
    $stream_id = isset($_SESSION['current_stream_id']) ? (int)$_SESSION['current_stream_id'] : 1;
    
    // Query to get time slots for the current stream
    $query = "
        SELECT ts.id, ts.start_time, ts.end_time, ts.duration, ts.is_break, ts.is_mandatory
        FROM time_slots ts
        LEFT JOIN stream_time_slots sts ON ts.id = sts.time_slot_id AND sts.stream_id = ? AND sts.is_active = 1
        WHERE ts.is_mandatory = 1 OR sts.stream_id IS NOT NULL
        ORDER BY ts.start_time
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $stream_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $time_slots = [];
    while ($row = $result->fetch_assoc()) {
        $time_slots[] = [
            'id' => (int)$row['id'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'duration' => (int)$row['duration'],
            'is_break' => (bool)$row['is_break'],
            'is_mandatory' => (bool)$row['is_mandatory']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $time_slots
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch time slots: ' . $e->getMessage()
    ]);
}

$conn->close();
?>