<?php
header('Content-Type: application/json');
include 'connect.php';

$response = ['success' => false, 'message' => '', 'data' => ['available_courses' => [], 'assigned_courses' => []]];

try {
    $lecturer_id = isset($_GET['lecturer_id']) ? (int)$_GET['lecturer_id'] : 0;
    
    if ($lecturer_id <= 0) {
        throw new Exception('Invalid lecturer ID');
    }
    
    // Get all courses
    $courses_query = "SELECT id, name, code FROM courses WHERE is_active = 1 ORDER BY name";
    $courses_result = $conn->query($courses_query);
    
    if (!$courses_result) {
        throw new Exception('Failed to fetch courses: ' . $conn->error);
    }
    
    $all_courses = [];
    while ($row = $courses_result->fetch_assoc()) {
        $all_courses[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['code']
        ];
    }
    
    // Get courses assigned to this lecturer
    $assigned_query = "
        SELECT c.id, c.name, c.code 
        FROM courses c
        JOIN lecturer_courses lc ON c.id = lc.course_id
        WHERE lc.lecturer_id = ? 
        AND c.is_active = 1 
        AND lc.is_active = 1
        ORDER BY c.name
    ";
    
    $assigned_stmt = $conn->prepare($assigned_query);
    if (!$assigned_stmt) {
        throw new Exception('Failed to prepare assigned courses query: ' . $conn->error);
    }
    
    $assigned_stmt->bind_param('i', $lecturer_id);
    $assigned_stmt->execute();
    $assigned_result = $assigned_stmt->get_result();
    
    $assigned_courses = [];
    $assigned_course_ids = [];
    while ($row = $assigned_result->fetch_assoc()) {
        $assigned_courses[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'code' => $row['code']
        ];
        $assigned_course_ids[] = $row['id'];
    }
    
    $assigned_stmt->close();
    
    // Get available courses (all courses minus assigned courses)
    $available_courses = [];
    foreach ($all_courses as $course) {
        if (!in_array($course['id'], $assigned_course_ids)) {
            $available_courses[] = $course;
        }
    }
    
    $response['success'] = true;
    $response['data']['available_courses'] = $available_courses;
    $response['data']['assigned_courses'] = $assigned_courses;
    $response['message'] = 'Courses loaded successfully';
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
