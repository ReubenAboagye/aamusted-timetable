<?php
// API endpoint to get timetable versions for a specific stream and semester
header('Content-Type: application/json');

include 'connect.php';

$stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;
$semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;

if ($stream_id <= 0 || $semester <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Convert semester number to text
$semester_text = ($semester == 1) ? 'first' : 'second';

try {
    // Get distinct versions for the specified stream and semester
    $stmt = $conn->prepare("
        SELECT DISTINCT t.version 
        FROM timetable t 
        JOIN class_courses cc ON t.class_course_id = cc.id 
        JOIN classes c ON cc.class_id = c.id 
        WHERE c.stream_id = ? AND t.semester = ? AND t.version IS NOT NULL 
        ORDER BY t.version DESC
    ");
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param('is', $stream_id, $semester_text);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $versions = [];
    while ($row = $result->fetch_assoc()) {
        $versions[] = $row['version'];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'versions' => $versions
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
