<?php
// API endpoint to get job status
require_once __DIR__ . '/../connect.php';

header('Content-Type: application/json');

try {
    $jobId = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;
    
    if (!$jobId) {
        throw new Exception('Job ID is required');
    }
    
    // Get job status
    $stmt = $conn->prepare("SELECT id, status, progress, result, error, created_at, updated_at FROM jobs WHERE id = ?");
    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'job' => $row
        ]);
    } else {
        throw new Exception('Job not found');
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>