<?php
/**
 * API endpoint for automatic scheduling of unscheduled courses
 * This endpoint handles the manual trigger of the automatic scheduling algorithm
 */

include 'connect.php';

// Set JSON header
header('Content-Type: application/json');

// Initialize response
$response = ['success' => false, 'message' => '', 'scheduled_count' => 0, 'remaining_unscheduled' => 0];

try {
    // Get JSON input
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    // Debug logging
    error_log("Auto-schedule API called with raw input: " . $raw_input);
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Invalid request data. Raw input: ' . $raw_input);
    }
    
    $action = $input['action'];
    
    if ($action === 'auto_schedule_unscheduled') {
        // Validate required parameters
        if (!isset($input['stream_id']) || !isset($input['semester'])) {
            throw new Exception('Missing required parameters: stream_id and semester');
        }
        
        $stream_id = intval($input['stream_id']);
        $semester = intval($input['semester']);
        
        // Get the current version for this stream/semester
        $version_query = "
            SELECT t.version
            FROM timetable t
            JOIN class_courses cc ON t.class_course_id = cc.id
            JOIN classes c ON cc.class_id = c.id
            WHERE c.stream_id = ? AND t.semester = ?
            ORDER BY t.created_at DESC
            LIMIT 1
        ";
        $semester_param = is_numeric($semester) ? (($semester == 1) ? 'first' : 'second') : $semester;
        $stmt = $conn->prepare($version_query);
        $stmt->bind_param("is", $stream_id, $semester_param);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_version = 'regular'; // Default
        if ($row = $result->fetch_assoc()) {
            $current_version = $row['version'] ?: 'regular';
        }
        $stmt->close();
        
        // Include the enhanced scheduling function
        include_once 'schedule_functions.php';
        
        // Debug logging
        error_log("About to call scheduleUnscheduledClasses with stream_id=$stream_id, semester=$semester, version=$current_version");
        
        // Run the automatic scheduling algorithm with current version
        $scheduling_result = scheduleUnscheduledClasses($conn, $stream_id, $semester, $current_version);
        
        error_log("scheduleUnscheduledClasses returned: " . print_r($scheduling_result, true));
        
        // Handle the new return format
        if (is_array($scheduling_result)) {
            $scheduled_count = $scheduling_result['scheduled_count'];
            $constraint_failures = $scheduling_result['constraint_failures'];
        } else {
            // Fallback for old format
            $scheduled_count = $scheduling_result;
            $constraint_failures = [];
        }
        
        // Get detailed information about remaining unscheduled courses
        $remaining_query = "
            SELECT 
                cc.id as class_course_id,
                cc.class_id,
                cc.course_id,
                cc.lecturer_id,
                c.name as class_name,
                co.code as course_code,
                co.name as course_name,
                l.name as lecturer_name,
                CASE 
                    WHEN cc.lecturer_id IS NULL THEN 'No lecturer assigned'
                    WHEN lc.id IS NULL THEN 'Lecturer not found in lecturer_courses'
                    ELSE 'Other constraint'
                END as reason
            FROM class_courses cc
            JOIN classes c ON cc.class_id = c.id
            JOIN courses co ON cc.course_id = co.id
            LEFT JOIN lecturers l ON cc.lecturer_id = l.id
            LEFT JOIN lecturer_courses lc ON cc.course_id = lc.course_id AND cc.lecturer_id = lc.lecturer_id AND lc.is_active = 1
            WHERE cc.is_active = 1 
            AND c.stream_id = ?
            AND cc.id NOT IN (
                SELECT DISTINCT t.class_course_id 
                FROM timetable t 
                WHERE t.class_course_id IS NOT NULL
            )
            ORDER BY reason, co.code
        ";
        
        
        $stmt = $conn->prepare($remaining_query);
        $stmt->bind_param("i", $stream_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $remaining_courses = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $remaining_count = count($remaining_courses);
        
        // Count courses by reason
        $no_lecturer_count = 0;
        $lecturer_not_found_count = 0;
        $other_constraint_count = 0;
        
        foreach ($remaining_courses as $course) {
            switch ($course['reason']) {
                case 'No lecturer assigned':
                    $no_lecturer_count++;
                    break;
                case 'Lecturer not found in lecturer_courses':
                    $lecturer_not_found_count++;
                    break;
                default:
                    $other_constraint_count++;
                    break;
            }
        }
        
        $response['success'] = true;
        $response['message'] = "Auto-scheduling completed successfully";
        $response['scheduled_count'] = $scheduled_count;
        $response['remaining_unscheduled'] = $remaining_count;
        $response['remaining_details'] = [
            'no_lecturer_assigned' => $no_lecturer_count,
            'lecturer_not_found' => $lecturer_not_found_count,
            'other_constraints' => $other_constraint_count
        ];
        $response['remaining_courses'] = $remaining_courses;
        $response['constraint_failures'] = $constraint_failures;
        
        // Log the results
        error_log("Auto-scheduling completed: $scheduled_count courses scheduled, $remaining_count remaining unscheduled");
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Auto-scheduling error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
} catch (Error $e) {
    $response['success'] = false;
    $response['message'] = "PHP Error: " . $e->getMessage();
    error_log("Auto-scheduling PHP error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}

echo json_encode($response);
?>
