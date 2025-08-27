<?php
include 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        // Get form data
        $className = $_POST['className'] ?? '';
        $departmentId = $_POST['departmentId'] ?? '';
        $level = $_POST['level'] ?? '';
        $sessionId = $_POST['sessionId'] ?? '';
        $capacity = $_POST['capacity'] ?? 30;
        $currentEnrollment = $_POST['currentEnrollment'] ?? 0;
        $maxDailyCourses = $_POST['maxDailyCourses'] ?? 3;
        $maxWeeklyHours = $_POST['maxWeeklyHours'] ?? 25;
        $preferredStartTime = $_POST['preferredStartTime'] ?? '08:00:00';
        $preferredEndTime = $_POST['preferredEndTime'] ?? '17:00:00';
        
        // Validate required fields
        if (empty($className) || empty($departmentId) || empty($level) || empty($sessionId)) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
            exit;
        }
        
        // Check if class name already exists for this department and session
        $checkSql = "SELECT id FROM classes WHERE name = ? AND department_id = ? AND session_id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("sii", $className, $departmentId, $sessionId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'A class with this name already exists in this department and session']);
            exit;
        }
        
        // Insert new class
        $insertSql = "INSERT INTO classes (name, department_id, level, session_id, capacity, current_enrollment, 
                                         max_daily_courses, max_weekly_hours, preferred_start_time, preferred_end_time, is_active) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param("sisiiiss", $className, $departmentId, $level, $sessionId, $capacity, 
                               $currentEnrollment, $maxDailyCourses, $maxWeeklyHours, $preferredStartTime, $preferredEndTime);
        
        if ($insertStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Class added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding class: ' . $conn->error]);
        }
        
        $insertStmt->close();
        $checkStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>
