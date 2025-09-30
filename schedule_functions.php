<?php
/**
 * Enhanced automatic scheduling algorithm for unscheduled classes
 * Extracted from generate_timetable.php for API use
 */

/**
 * Schedule unscheduled classes in available slots after GA generation
 * @param mysqli $conn Database connection
 * @param int $stream_id Stream ID
 * @param int $semester Semester (1 or 2)
 * @return int Number of additional classes scheduled
 */
function scheduleUnscheduledClasses($conn, $stream_id, $semester) {
    $additional_scheduled = 0;
    
    try {
        // Get unscheduled class courses for this stream and semester with enhanced prioritization
        $unscheduled_query = "
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
                    WHEN cc.lecturer_id IS NOT NULL THEN 'assigned'
                    ELSE 'unassigned'
                END as lecturer_status,
                (SELECT COUNT(*) FROM lecturer_courses lc WHERE lc.course_id = co.id AND lc.is_active = 1) as total_lecturers_for_course
            FROM class_courses cc
            LEFT JOIN classes c ON cc.class_id = c.id
            LEFT JOIN courses co ON cc.course_id = co.id
            LEFT JOIN lecturers l ON cc.lecturer_id = l.id
            WHERE cc.is_active = 1 
            AND c.stream_id = ?
            AND cc.id NOT IN (
                SELECT DISTINCT t.class_course_id 
                FROM timetable t 
                WHERE t.class_course_id IS NOT NULL
            )
            ORDER BY 
                CASE 
                    WHEN cc.lecturer_id IS NOT NULL THEN 1  -- Prioritize courses with assigned lecturers
                    ELSE 2 
                END,
                c.total_capacity DESC,  -- Prioritize larger classes
                co.code ASC
        ";
        
        $stmt = $conn->prepare($unscheduled_query);
        $stmt->bind_param("i", $stream_id);
        $stmt->execute();
        $unscheduled_result = $stmt->get_result();
        $unscheduled_classes = $unscheduled_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        if (empty($unscheduled_classes)) {
            return 0; // No unscheduled classes
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
        
        if (empty($available_slots) || empty($available_rooms)) {
            return 0; // No available slots or rooms
        }
        
        // Get course-room type preferences
        $room_preferences_query = "
            SELECT crt.course_id, rt.name as room_type
            FROM course_room_types crt
            JOIN room_types rt ON crt.room_type_id = rt.id
            WHERE crt.is_active = 1
        ";
        $prefs_result = $conn->query($room_preferences_query);
        $room_preferences = [];
        while ($pref = $prefs_result->fetch_assoc()) {
            $room_preferences[$pref['course_id']] = $pref['room_type'];
        }
        
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
        $room_usage = []; // Track room usage for optimization
        
        foreach ($scheduled_entries as $entry) {
            $room_key = $entry['day_id'] . '|' . $entry['time_slot_id'] . '|' . $entry['room_id'];
            $room_conflicts[$room_key] = true;
            
            $class_key = $entry['day_id'] . '|' . $entry['time_slot_id'] . '|' . $entry['class_id'];
            $class_conflicts[$class_key] = true;
            
            if ($entry['lecturer_id']) {
                $lecturer_key = $entry['day_id'] . '|' . $entry['time_slot_id'] . '|' . $entry['lecturer_id'];
                $lecturer_conflicts[$lecturer_key] = true;
            }
            
            // Track room usage for load balancing
            $room_usage[$entry['room_id']] = ($room_usage[$entry['room_id']] ?? 0) + 1;
        }
        
        // Try to schedule each unscheduled class with enhanced algorithm
        $constraint_failures = []; // Track constraint failures for detailed reporting
        
        foreach ($unscheduled_classes as $class_course) {
            $scheduled = false;
            $failure_reasons = []; // Track specific failure reasons for this course
            
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
            
            // Skip courses without assigned lecturers
            if (!$lecturer_course_id || !$lecturer_id) {
                $constraint_failures[] = [
                    'course_code' => $class_course['course_code'],
                    'course_name' => $class_course['course_name'],
                    'class_name' => $class_course['class_name'],
                    'reason' => 'No lecturer assigned',
                    'details' => 'This course does not have a lecturer assigned to it'
                ];
                error_log("Skipping course {$class_course['course_code']} - {$class_course['course_name']} for class {$class_course['class_name']}: No lecturer assigned");
                continue;
            }
            
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
            
            // Sort rooms by capacity (ascending) and usage (ascending) for load balancing
            usort($suitable_rooms, function($a, $b) use ($room_usage) {
                $usage_a = $room_usage[$a['id']] ?? 0;
                $usage_b = $room_usage[$b['id']] ?? 0;
                
                if ($usage_a === $usage_b) {
                    return $a['capacity'] - $b['capacity']; // Prefer smaller rooms if usage is equal
                }
                return $usage_a - $usage_b; // Prefer less used rooms
            });
            
            // Try each available slot
            foreach ($available_slots as $slot) {
                // Skip break slots
                if ($slot['is_break']) {
                    continue;
                }
                
                // Try each suitable room
                foreach ($suitable_rooms as $room) {
                    $room_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $room['id'];
                    $class_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $class_course['class_id'];
                    
                    // Check for conflicts
                    if (isset($room_conflicts[$room_key]) || isset($class_conflicts[$class_key])) {
                        continue;
                    }
                    
                    // Check lecturer conflict if we have a lecturer
                    if ($lecturer_id) {
                        $lecturer_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $lecturer_id;
                        if (isset($lecturer_conflicts[$lecturer_key])) {
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
                        continue;
                    }
                    
                    // Schedule this class
                    $insert_query = "
                        INSERT INTO timetable (
                            class_course_id, lecturer_course_id, day_id, time_slot_id, 
                            room_id, division_label, semester, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, '', ?, NOW(), NOW())
                    ";
                    
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bind_param("iiiiii", 
                        $class_course['class_course_id'],
                        $lecturer_course_id,
                        $slot['day_id'],
                        $slot['time_slot_id'],
                        $room['id'],
                        $semester
                    );
                    
                    if ($insert_stmt->execute()) {
                        $additional_scheduled++;
                        $scheduled = true;
                        
                        // Update conflict maps
                        $room_conflicts[$room_key] = true;
                        $class_conflicts[$class_key] = true;
                        if ($lecturer_id) {
                            $lecturer_key = $slot['day_id'] . '|' . $slot['time_slot_id'] . '|' . $lecturer_id;
                            $lecturer_conflicts[$lecturer_key] = true;
                        }
                        
                        // Update room usage for load balancing
                        $room_usage[$room['id']] = ($room_usage[$room['id']] ?? 0) + 1;
                        
                        error_log("Successfully scheduled unscheduled class: {$class_course['class_name']} - {$class_course['course_code']} in room {$room['name']} at slot {$slot['start_time']}-{$slot['end_time']} with lecturer {$class_course['lecturer_name']}");
                        break;
                    }
                    $insert_stmt->close();
                }
                
                if ($scheduled) {
                    break;
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Error in scheduleUnscheduledClasses: " . $e->getMessage());
    }
    
    return $additional_scheduled;
}
?>
