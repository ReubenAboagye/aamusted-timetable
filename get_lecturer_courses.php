<?php
header('Content-Type: application/json');
include 'connect.php';

$response = ['success' => false, 'data' => null, 'error' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    $lecturer_id = isset($_GET['lecturer_id']) ? (int)$_GET['lecturer_id'] : 0;
    
    if ($lecturer_id <= 0) {
        throw new Exception('Invalid lecturer ID');
    }

    // Get all available courses
    $available_courses_query = "SELECT c.id, c.code, c.name 
                               FROM courses c 
                               WHERE c.is_active = 1 
                               ORDER BY c.code";
    
    $available_courses = $conn->query($available_courses_query);
    if (!$available_courses) {
        throw new Exception('Failed to fetch available courses: ' . $conn->error);
    }

    $available_courses_data = [];
    while ($course = $available_courses->fetch_assoc()) {
        $available_courses_data[] = [
            'id' => $course['id'],
            'code' => $course['code'],
            'name' => $course['name']
        ];
    }

    // Get assigned courses for this lecturer
    $assigned_courses_query = "SELECT c.id, c.code, c.name 
                              FROM courses c 
                              INNER JOIN lecturer_courses lc ON c.id = lc.course_id 
                              WHERE lc.lecturer_id = ? AND c.is_active = 1 
                              ORDER BY c.code";
    
    $stmt = $conn->prepare($assigned_courses_query);
    if (!$stmt) {
        throw new Exception('Failed to prepare assigned courses query: ' . $conn->error);
    }

    $stmt->bind_param('i', $lecturer_id);
    $stmt->execute();
    $assigned_courses_result = $stmt->get_result();
    $stmt->close();

    $assigned_courses_data = [];
    while ($course = $assigned_courses_result->fetch_assoc()) {
        $assigned_courses_data[] = [
            'id' => $course['id'],
            'code' => $course['code'],
            'name' => $course['name']
        ];
    }

    // Remove assigned courses from available courses
    $assigned_course_ids = array_column($assigned_courses_data, 'id');
    $available_courses_data = array_filter($available_courses_data, function($course) use ($assigned_course_ids) {
        return !in_array($course['id'], $assigned_course_ids);
    });

    $response['success'] = true;
    $response['data'] = [
        'available_courses' => array_values($available_courses_data),
        'assigned_courses' => $assigned_courses_data
    ];

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
} catch (Error $e) {
    $response['error'] = 'System error: ' . $e->getMessage();
}

echo json_encode($response);
?>
