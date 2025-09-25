<?php
require_once 'connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    $class_course_id = isset($_GET['class_course_id']) ? (int)$_GET['class_course_id'] : null;
    
    if (!$class_course_id) {
        throw new Exception("Missing class_course_id parameter");
    }
    
    // Get currently assigned lecturer for this class course
    $query = "
        SELECT 
            l.id as lecturer_id,
            l.name as lecturer_name,
            lc.id as lecturer_course_id
        FROM class_courses cc
        LEFT JOIN lecturer_courses lc ON cc.lecturer_id = lc.lecturer_id AND lc.course_id = cc.course_id AND lc.is_active = 1
        LEFT JOIN lecturers l ON lc.lecturer_id = l.id
        WHERE cc.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    
    $stmt->bind_param('i', $class_course_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $stmt->close();
    
    if ($row && $row['lecturer_id']) {
        echo json_encode([
            'success' => true,
            'lecturer' => [
                'id' => (int)$row['lecturer_id'],
                'name' => $row['lecturer_name'],
                'lecturer_course_id' => (int)$row['lecturer_course_id']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'lecturer' => null
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to get current lecturer: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
