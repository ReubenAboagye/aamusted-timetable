<?php
// API endpoint to get job events (including restart events)
require_once __DIR__ . '/../connect.php';

header('Content-Type: application/json');

try {
    $jobId = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;
    
    if (!$jobId) {
        throw new Exception('Job ID is required');
    }
    
    // Get events for the job
    $stmt = $conn->prepare("SELECT id, event_type, event_data, created_at FROM job_events WHERE job_id = ? ORDER BY created_at ASC");
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = [
            'id' => $row['id'],
            'event_type' => $row['event_type'],
            'event_data' => json_decode($row['event_data'], true),
            'created_at' => $row['created_at']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>