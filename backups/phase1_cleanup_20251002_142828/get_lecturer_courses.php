<?php
header('Content-Type: application/json');
include 'connect.php';

$response = ['success' => false, 'message' => '', 'lecturers' => []];

try {
    $course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;
    $class_course_id = isset($_GET['class_course_id']) ? (int)$_GET['class_course_id'] : 0;
    
    if ($course_id <= 0 && $class_course_id <= 0) {
        throw new Exception('Invalid course ID or class course ID');
    }
    
    // If class_course_id is provided, get the course_id from it
    if ($class_course_id > 0 && $course_id <= 0) {
        $course_query = "SELECT course_id FROM class_courses WHERE id = ? AND is_active = 1";
        $course_stmt = $conn->prepare($course_query);
        $course_stmt->bind_param('i', $class_course_id);
        $course_stmt->execute();
        $course_result = $course_stmt->get_result();
        
        if ($course_result->num_rows === 0) {
            throw new Exception('Class course not found');
        }
        
        $course_row = $course_result->fetch_assoc();
        $course_id = $course_row['course_id'];
        $course_stmt->close();
    }
    
    // Get all lecturers assigned to this course
    $query = "
        SELECT 
            lc.id as lecturer_course_id,
            l.id as lecturer_id,
            l.name as lecturer_name
        FROM lecturer_courses lc
        JOIN lecturers l ON lc.lecturer_id = l.id
        WHERE lc.course_id = ? 
        AND lc.is_active = 1 
        AND l.is_active = 1
        ORDER BY l.name
    ";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare query: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $lecturers = [];
    while ($row = $result->fetch_assoc()) {
        $lecturers[] = [
            'lecturer_course_id' => $row['lecturer_course_id'],
            'lecturer_id' => $row['lecturer_id'],
            'lecturer_name' => $row['lecturer_name']
        ];
    }
    
    $stmt->close();
    
    $response['success'] = true;
    $response['lecturers'] = $lecturers;
    $response['message'] = 'Lecturers loaded successfully';
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>