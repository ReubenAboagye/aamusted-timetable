<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../connect.php';
header('Content-Type: application/json');

try {
    $stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : null;
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

    // Check if jobs table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'jobs'");
    if ($table_check->num_rows === 0) {
        // Jobs table doesn't exist, return empty array
        echo json_encode(['success' => true, 'jobs' => []]);
        exit;
    }

    $sql = "SELECT id, job_type, stream_id, academic_year, semester, status, progress, result, error_message, created_at, updated_at FROM jobs";
    $params = [];
    if ($stream_id) {
        $sql .= " WHERE stream_id = ?";
    }
    $sql .= " ORDER BY created_at DESC LIMIT ?";

    if ($stream_id) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $stream_id, $limit);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $limit);
    }

    if (!$stmt->execute()) throw new Exception($stmt->error);
    $res = $stmt->get_result();
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }

    echo json_encode(['success' => true, 'jobs' => $out]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

?>


