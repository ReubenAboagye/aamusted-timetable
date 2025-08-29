<?php
header('Content-Type: application/json');
include 'connect.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action']) || $input['action'] !== 'bulk_assign') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$class_ids = $input['class_ids'] ?? [];
$course_ids = $input['course_ids'] ?? [];

if (empty($class_ids) || empty($course_ids)) {
    echo json_encode(['success' => false, 'message' => 'No classes or courses selected']);
    exit;
}

// Get active semester IDs
$semester1_id = null;
$semester2_id = null;

$sessions_query = "SELECT id, semester_number FROM sessions WHERE is_active = 1 ORDER BY semester_number";
$sessions_result = $conn->query($sessions_query);

if ($sessions_result) {
    while ($session = $sessions_result->fetch_assoc()) {
        if ($session['semester_number'] == 1) {
            $semester1_id = $session['id'];
        } elseif ($session['semester_number'] == 2) {
            $semester2_id = $session['id'];
        }
    }
}

if (!$semester1_id || !$semester2_id) {
    echo json_encode(['success' => false, 'message' => 'Active semesters not found']);
    exit;
}

$conn->begin_transaction();

try {
    // Assign courses to semester 1
    $stmt = $conn->prepare("INSERT IGNORE INTO class_courses (class_id, course_id, session_id) VALUES (?, ?, ?)");
    
    foreach ($class_ids as $class_id) {
        foreach ($course_ids as $course_id) {
            // Assign to semester 1
            $stmt->bind_param('iii', $class_id, $course_id, $semester1_id);
            $stmt->execute();
            
            // Assign to semester 2
            $stmt->bind_param('iii', $class_id, $course_id, $semester2_id);
            $stmt->execute();
        }
    }
    
    $stmt->close();
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Courses assigned successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>
