<?php
require_once __DIR__ . '/../connect.php';
header('Content-Type: application/json');

try {
    $jobId = isset($_GET['job_id']) ? intval($_GET['job_id']) : null;
    if (!$jobId) throw new Exception('job_id required');

    $res = $conn->query("SELECT id, job_type, stream_id, academic_year, semester, status, progress, result, error_message, created_at, updated_at FROM jobs WHERE id = " . $jobId);
    $row = $res->fetch_assoc();
    if (!$row) throw new Exception('Job not found');

    echo json_encode(['success' => true, 'job' => $row]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>


