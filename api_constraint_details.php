<?php
/**
 * API endpoint for detailed constraint analysis
 * Provides specific constraint information for individual courses
 */

include 'connect.php';

// Set JSON header
header('Content-Type: application/json');

// Initialize response
$response = ['success' => false, 'message' => '', 'constraint_details' => []];

try {
    // Get JSON input
    $raw_input = file_get_contents('php://input');
    $input = json_decode($raw_input, true);
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Invalid request data. Raw input: ' . $raw_input);
    }
    
    $action = $input['action'];
    
    if ($action === 'get_constraint_details') {
        // Validate required parameters
        if (!isset($input['class_course_id']) || !isset($input['stream_id']) || !isset($input['semester'])) {
            throw new Exception('Missing required parameters: class_course_id, stream_id, and semester');
        }
        
        $class_course_id = intval($input['class_course_id']);
        $stream_id = intval($input['stream_id']);
        $semester = intval($input['semester']);
        
        // Get detailed information about the specific course
        $course_query = "
            SELECT 
                cc.id as class_course_id,
                cc.class_id,
                cc.course_id,
                cc.lecturer_id,
                c.name as class_name,
                co.code as course_code,
                co.name as course_name,
                co.hours_per_week,
                l.name as lecturer_name,
                c.total_capacity as class_size,
                CASE 
                    WHEN cc.lecturer_id IS NULL THEN 'No lecturer assigned'
                    ELSE 'Constraint conflicts'
                END as reason,
                CASE 
                    WHEN cc.lecturer_id IS NULL THEN 'This course does not have a lecturer assigned to it'
                    ELSE 'This course has scheduling conflicts or insufficient resources'
                END as details
            FROM class_courses cc
            LEFT JOIN classes c ON cc.class_id = c.id
            LEFT JOIN courses co ON cc.course_id = co.id
            LEFT JOIN lecturers l ON cc.lecturer_id = l.id
            WHERE cc.id = ? AND cc.is_active = 1
        ";
        
        $stmt = $conn->prepare($course_query);
        $stmt->bind_param("i", $class_course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $course = $result->fetch_assoc();
        $stmt->close();
        
        if (!$course) {
            throw new Exception('Course not found');
        }
        
        // If course has a lecturer, run detailed constraint analysis
        if ($course['lecturer_id']) {
            // Include the enhanced scheduling function
            include_once 'schedule_functions.php';
            
            // Run constraint analysis for this specific course
            $constraint_analysis = analyzeCourseConstraints($conn, $class_course_id, $stream_id, $semester);
            
            // Merge course info with constraint analysis
            $course = array_merge($course, $constraint_analysis);
        } else {
            // For courses without lecturers, set default values
            $course['attempts'] = 0;
            $course['suitable_rooms'] = 0;
            $course['available_slots'] = 0;
            $course['total_rooms'] = 0;
            $course['total_slots'] = 0;
        }
        
        $response['success'] = true;
        $response['message'] = "Constraint details loaded successfully";
        $response['constraint_details'] = $course;
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Constraint details API error: " . $e->getMessage());
}

echo json_encode($response);

/**
 * Analyze constraints for a specific course
 * This function runs a focused analysis on why a course can't be scheduled
 */
function analyzeCourseConstraints($conn, $class_course_id, $stream_id, $semester) {
    $analysis = [
        'attempts' => 0,
        'suitable_rooms' => 0,
        'available_slots' => 0,
        'total_rooms' => 0,
        'total_slots' => 0,
        'details' => 'This course has scheduling conflicts or insufficient resources'
    ];
    
    try {
        // Get course details
        $course_query = "
            SELECT 
                cc.id as class_course_id,
                cc.class_id,
                cc.course_id,
                cc.lecturer_id,
                c.name as class_name,
                co.code as course_code,
                co.name as course_name,
                co.hours_per_week,
                l.name as lecturer_name,
                c.total_capacity as class_size
            FROM class_courses cc
            LEFT JOIN classes c ON cc.class_id = c.id
            LEFT JOIN courses co ON cc.course_id = co.id
            LEFT JOIN lecturers l ON cc.lecturer_id = l.id
            WHERE cc.id = ? AND cc.is_active = 1
        ";
        
        $stmt = $conn->prepare($course_query);
        $stmt->bind_param("i", $class_course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $class_course = $result->fetch_assoc();
        $stmt->close();
        
        if (!$class_course) {
            return $analysis;
        }
        
        // Get lecturer course for this class course
        $lecturer_course_id = null;
        $lecturer_id = null;
        
        if ($class_course['lecturer_id']) {
            $lec_query = "SELECT id FROM lecturer_courses WHERE course_id = ? AND lecturer_id = ? AND is_active = 1 LIMIT 1";
            $lec_stmt = $conn->prepare($lec_query);
            $lec_stmt->bind_param("ii", $class_course['course_id'], $class_course['lecturer_id']);
            $lec_stmt->execute();
            $lec_result = $lec_stmt->get_result();
            if ($lec_row = $lec_result->fetch_assoc()) {
                $lecturer_course_id = $lec_row['id'];
                $lecturer_id = $class_course['lecturer_id'];
            }
            $lec_stmt->close();
        }
        
        if (!$lecturer_course_id || !$lecturer_id) {
            $analysis['details'] = 'This course does not have a lecturer assigned to it';
            return $analysis;
        }
        
        // Get available time slots and rooms for this stream
        $slots_query = "
            SELECT DISTINCT d.id as day_id, ts.id as time_slot_id, ts.start_time, ts.end_time, ts.is_break
            FROM days d
            CROSS JOIN stream_time_slots sts
            JOIN time_slots ts ON sts.time_slot_id = ts.id
            WHERE sts.stream_id = ? AND sts.is_active = 1
            ORDER BY d.id, ts.start_time
        ";
        
        $stmt = $conn->prepare($slots_query);
        $stmt->bind_param("i", $stream_id);
        $stmt->execute();
        $slots_result = $stmt->get_result();
        $available_slots = $slots_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Get available rooms with capacity and type information
        $rooms_query = "
            SELECT r.id, r.name, r.capacity, r.room_type
            FROM rooms r
            WHERE r.is_active = 1 
            ORDER BY r.capacity DESC, r.name ASC
        ";
        $rooms_result = $conn->query($rooms_query);
        $available_rooms = $rooms_result->fetch_all(MYSQLI_ASSOC);
        
        $analysis['total_rooms'] = count($available_rooms);
        $analysis['total_slots'] = count($available_slots);
        
        // Get course-room type preferences
        $room_preferences_query = "
            SELECT crt.course_id, rt.name as room_type
            FROM course_room_types crt
            JOIN room_types rt ON crt.room_type_id = rt.id
            WHERE crt.is_active = 1 AND crt.course_id = ?
        ";
        $prefs_stmt = $conn->prepare($room_preferences_query);
        $prefs_stmt->bind_param("i", $class_course['course_id']);
        $prefs_stmt->execute();
        $prefs_result = $prefs_stmt->get_result();
        $room_preferences = [];
        while ($pref = $prefs_result->fetch_assoc()) {
            $room_preferences[$pref['course_id']] = $pref['room_type'];
        }
        $prefs_stmt->close();
        
        // Filter rooms by preference and capacity
        $suitable_rooms = $available_rooms;
        
        // Apply room type preference if available
        if (isset($room_preferences[$class_course['course_id']])) {
            $preferred_type = $room_preferences[$class_course['course_id']];
            $filtered_rooms = array_filter($suitable_rooms, function($room) use ($preferred_type) {
                return strtolower($room['room_type'] ?? '') === strtolower($preferred_type);
            });
            if (!empty($filtered_rooms)) {
                $suitable_rooms = array_values($filtered_rooms);
            }
        }
        
        // Filter by capacity
        $class_size = (int)$class_course['class_size'];
        if ($class_size > 0) {
            $suitable_rooms = array_filter($suitable_rooms, function($room) use ($class_size) {
                return (int)$room['capacity'] >= $class_size;
            });
        }
        
        $analysis['suitable_rooms'] = count($suitable_rooms);
        
        // Get currently scheduled entries to check for conflicts
        $scheduled_query = "
            SELECT t.day_id, t.time_slot_id, t.room_id, t.class_course_id, t.lecturer_course_id,
                   cc.class_id, cc.course_id, lc.lecturer_id
            FROM timetable t
            JOIN class_courses cc ON t.class_course_id = cc.id
            JOIN classes c ON cc.class_id = c.id
            LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
            WHERE c.stream_id = ? AND t.semester = ?
        ";
        
        $stmt = $conn->prepare($scheduled_query);
        $stmt->bind_param("ii", $stream_id, $semester);
        $stmt->execute();
        $scheduled_result = $stmt->get_result();
        $scheduled_entries = $scheduled_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // Create comprehensive conflict maps
        $room_conflicts = [];
        $class_conflicts = [];
        $lecturer_conflicts = [];
        
        foreach ($scheduled_entries as $entry) {
            $room_key = $entry['day_id'] . '|' . $entry['time_slot_id'] . '|' . $entry['room_id'];
            $room_conflicts[$room_key] = true;
            
            $class_key = $entry['day_id'] . '|' . $entry['time_slot_id'] . '|' . $entry['class_id'];
            $class_conflicts[$class_key] = true;
            
            if ($entry['lecturer_id']) {
                $lecturer_key = $entry['day_id'] . '|' . $entry['time_slot_id'] . '|' . $entry['lecturer_id'];
                $lecturer_conflicts[$lecturer_key] = true;
            }
        }
        
        // Count attempts and analyze constraints
        $slot_attempts = 0;
        $room_attempts = 0;
        $total_attempts = 0;
        $failure_reasons = [];
        
        foreach ($available_slots as $slot) {
            // Skip break slots
            if ($slot['is_break']) {
                continue;
            }
            
            $slot_attempts++;
            
            // Try each suitable room
            foreach ($suitable_rooms as $room) {
                $room_attempts++;
                $total_attempts++;
                
                $room_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $room['id'];
                $class_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $class_course['class_id'];
                
                // Check for conflicts
                if (isset($room_conflicts[$room_key])) {
                    $failure_reasons[] = "Room {$room['name']} is already occupied at this time";
                    continue;
                }
                
                if (isset($class_conflicts[$class_key])) {
                    $failure_reasons[] = "Class {$class_course['class_name']} already has a class at this time";
                    continue;
                }
                
                // Check lecturer conflict if we have a lecturer
                if ($lecturer_id) {
                    $lecturer_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $lecturer_id;
                    if (isset($lecturer_conflicts[$lecturer_key])) {
                        $failure_reasons[] = "Lecturer {$class_course['lecturer_name']} already has a class at this time";
                        continue;
                    }
                    
                    // Additional constraint: Check if lecturer has too many classes on the same day
                    $daily_load_query = "
                        SELECT COUNT(*) as daily_count
                        FROM timetable t
                        JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
                        WHERE lc.lecturer_id = ? AND t.day_id = ? AND t.semester = ?
                    ";
                    $load_stmt = $conn->prepare($daily_load_query);
                    $load_stmt->bind_param("iii", $lecturer_id, $slot['day_id'], $semester);
                    $load_stmt->execute();
                    $load_result = $load_stmt->get_result();
                    $daily_count = $load_result->fetch_assoc()['daily_count'];
                    $load_stmt->close();
                    
                    // Limit lecturer to maximum 4 classes per day
                    if ($daily_count >= 4) {
                        $failure_reasons[] = "Lecturer {$class_course['lecturer_name']} already has maximum daily load (4 classes)";
                        continue;
                    }
                }
                
                // Additional constraint: Check if class has too many classes on the same day
                $class_daily_load_query = "
                    SELECT COUNT(*) as daily_count
                    FROM timetable t
                    JOIN class_courses cc ON t.class_course_id = cc.id
                    WHERE cc.class_id = ? AND t.day_id = ? AND t.semester = ?
                ";
                $class_load_stmt = $conn->prepare($class_daily_load_query);
                $class_load_stmt->bind_param("iii", $class_course['class_id'], $slot['day_id'], $semester);
                $class_load_stmt->execute();
                $class_load_result = $class_load_stmt->get_result();
                $class_daily_count = $class_load_result->fetch_assoc()['daily_count'];
                $class_load_stmt->close();
                
                // Limit class to maximum 3 classes per day
                if ($class_daily_count >= 3) {
                    $failure_reasons[] = "Class {$class_course['class_name']} already has maximum daily load (3 classes)";
                    continue;
                }
                
                // If we get here, this slot/room combination would work
                // But we're just analyzing, so we'll count it as a potential success
            }
        }
        
        $analysis['attempts'] = $total_attempts;
        $analysis['available_slots'] = $slot_attempts;
        
        // Analyze why it couldn't be scheduled
        $analysis_details = [];
        
        if (count($suitable_rooms) == 0) {
            $analysis_details[] = "No suitable rooms found (capacity or room type requirements not met)";
        }
        
        if ($slot_attempts == 0) {
            $analysis_details[] = "No available time slots found";
        }
        
        if (!empty($failure_reasons)) {
            $analysis_details = array_merge($analysis_details, array_unique($failure_reasons));
        }
        
        if (!empty($analysis_details)) {
            $analysis['details'] = implode('; ', $analysis_details);
        }
        
    } catch (Exception $e) {
        error_log("Error in analyzeCourseConstraints: " . $e->getMessage());
    }
    
    return $analysis;
}
?>
