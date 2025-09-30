<?php
/**
 * API endpoint for lecturer assignment functionality
 * Handles getting unscheduled courses and saving lecturer assignments
 */

include 'connect.php';

// Set JSON header
header('Content-Type: application/json');

// Initialize response
$response = ['success' => false, 'message' => '', 'data' => []];

try {
    // Get JSON input
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Invalid request data. Raw input: ' . $raw_input);
    }
    
    $action = $input['action'];
    
    if ($action === 'get_unscheduled_courses') {
        // Validate required parameters
        if (!isset($input['stream_id']) || !isset($input['semester'])) {
            throw new Exception('Missing required parameters: stream_id and semester');
        }
        
        $stream_id = intval($input['stream_id']);
        $semester = intval($input['semester']);
        
        // Get unscheduled courses without lecturers
        $query = "
            SELECT 
                cc.id as class_course_id,
                cc.class_id,
                cc.course_id,
                c.name as class_name,
                co.code as course_code,
                co.name as course_name
            FROM class_courses cc
            JOIN classes c ON cc.class_id = c.id
            JOIN courses co ON cc.course_id = co.id
            WHERE cc.is_active = 1 
            AND c.stream_id = ?
            AND cc.lecturer_id IS NULL
            AND cc.id NOT IN (
                SELECT DISTINCT t.class_course_id 
                FROM timetable t 
                WHERE t.class_course_id IS NOT NULL
            )
            ORDER BY co.code, c.name
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $stream_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $courses = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $response['success'] = true;
        $response['message'] = "Unscheduled courses loaded successfully";
        $response['courses'] = $courses;
        
    } elseif ($action === 'get_course_lecturers') {
        // Validate required parameters
        if (!isset($input['course_id'])) {
            throw new Exception('Missing required parameter: course_id');
        }
        
        $course_id = intval($input['course_id']);
        
        // Get lecturers available for this course
        $query = "
            SELECT 
                lc.id as lecturer_course_id,
                l.name as lecturer_name,
                l.id as lecturer_id
            FROM lecturer_courses lc
            JOIN lecturers l ON lc.lecturer_id = l.id
            WHERE lc.course_id = ? 
            AND lc.is_active = 1 
            AND l.is_active = 1
            ORDER BY l.name
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $lecturers = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $response['success'] = true;
        $response['message'] = "Course lecturers loaded successfully";
        $response['lecturers'] = $lecturers;
        
    } elseif ($action === 'save_assignments') {
        // Validate required parameters
        if (!isset($input['assignments']) || !is_array($input['assignments'])) {
            throw new Exception('Missing or invalid assignments data');
        }
        
        $assignments = $input['assignments'];
        $assigned_count = 0;
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            foreach ($assignments as $assignment) {
                $class_course_id = intval($assignment['class_course_id']);
                $lecturer_course_id = intval($assignment['lecturer_course_id']);
                
                // Get lecturer_id from lecturer_course_id
                $lecturer_query = "SELECT lecturer_id FROM lecturer_courses WHERE id = ? AND is_active = 1";
                $lecturer_stmt = $conn->prepare($lecturer_query);
                $lecturer_stmt->bind_param("i", $lecturer_course_id);
                $lecturer_stmt->execute();
                $lecturer_result = $lecturer_stmt->get_result();
                
                if ($lecturer_row = $lecturer_result->fetch_assoc()) {
                    $lecturer_id = $lecturer_row['lecturer_id'];
                    
                    // Update class_courses with lecturer_id
                    $update_query = "UPDATE class_courses SET lecturer_id = ? WHERE id = ? AND is_active = 1";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("ii", $lecturer_id, $class_course_id);
                    
                    if ($update_stmt->execute()) {
                        $assigned_count++;
                    }
                    $update_stmt->close();
                }
                $lecturer_stmt->close();
            }
            
            // Commit transaction
            $conn->commit();
            
            $response['success'] = true;
            $response['message'] = "Lecturer assignments saved successfully";
            $response['assigned_count'] = $assigned_count;
            
        } catch (Exception $e) {
            // Rollback transaction
            $conn->rollback();
            throw $e;
        }
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Lecturer assignment API error: " . $e->getMessage());
}

echo json_encode($response);
?>
