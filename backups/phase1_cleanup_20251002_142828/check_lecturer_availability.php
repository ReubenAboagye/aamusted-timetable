<?php
require_once 'connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

try {
    // Get parameters
    $stream_id = isset($_SESSION['current_stream_id']) ? (int)$_SESSION['current_stream_id'] : 1;
    $day_id = isset($_GET['day_id']) ? (int)$_GET['day_id'] : null;
    $time_slot_id = isset($_GET['time_slot_id']) ? (int)$_GET['time_slot_id'] : null;
    $class_course_id = isset($_GET['class_course_id']) ? (int)$_GET['class_course_id'] : null;
    
    if (!$day_id || !$time_slot_id || !$class_course_id) {
        throw new Exception("Missing required parameters");
    }
    
    // Get lecturer courses for this class course
    $lecturer_query = "
        SELECT 
            lc.id as lecturer_course_id, 
            l.name as lecturer_name,
            CASE 
                WHEN cc.lecturer_id = lc.lecturer_id THEN 1 
                ELSE 0 
            END as is_currently_assigned
        FROM lecturer_courses lc
        JOIN lecturers l ON lc.lecturer_id = l.id
        JOIN class_courses cc ON cc.id = ?
        WHERE lc.course_id = cc.course_id
        AND lc.is_active = 1
    ";
    
    $lecturer_stmt = $conn->prepare($lecturer_query);
    $lecturer_stmt->bind_param('i', $class_course_id);
    $lecturer_stmt->execute();
    $lecturer_result = $lecturer_stmt->get_result();
    
    $available_lecturers = [];
    while ($lecturer_row = $lecturer_result->fetch_assoc()) {
        // Check if this lecturer is available at this time slot
        $availability_query = "
            SELECT COUNT(*) as conflict_count
            FROM timetable t
            JOIN lecturer_courses lc2 ON t.lecturer_course_id = lc2.id
            WHERE lc2.lecturer_id = (
                SELECT lc3.lecturer_id 
                FROM lecturer_courses lc3 
                WHERE lc3.id = ?
            )
            AND t.day_id = ?
            AND t.time_slot_id = ?
        ";
        
        $availability_stmt = $conn->prepare($availability_query);
        $availability_stmt->bind_param('iii', $lecturer_row['lecturer_course_id'], $day_id, $time_slot_id);
        $availability_stmt->execute();
        $availability_result = $availability_stmt->get_result();
        $availability_row = $availability_result->fetch_assoc();
        
        if ($availability_row['conflict_count'] == 0) {
            $available_lecturers[] = [
                'lecturer_course_id' => (int)$lecturer_row['lecturer_course_id'],
                'lecturer_name' => $lecturer_row['lecturer_name'],
                'is_currently_assigned' => (bool)$lecturer_row['is_currently_assigned']
            ];
        }
        
        $availability_stmt->close();
    }
    
    $lecturer_stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $available_lecturers
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to check lecturer availability: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
