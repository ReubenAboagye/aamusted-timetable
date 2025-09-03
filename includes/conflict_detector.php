<?php
/**
 * Conflict Detector - Enhanced conflict detection for timetable generation
 */

if (!class_exists('ConflictDetector')) {
class ConflictDetector {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Check all types of conflicts for a timetable slot
     */
    public function checkConflicts($class_course_id, $lecturer_course_id, $day_id, $time_slot_id, $room_id, $division_label = null) {
        $conflicts = [];
        
        // Get related IDs
        $class_id = $this->getClassIdFromClassCourse($class_course_id);
        $course_id = $this->getCourseIdFromClassCourse($class_course_id);
        $lecturer_id = $this->getLecturerIdFromLecturerCourse($lecturer_course_id);
        
        // Check lecturer conflicts
        $lecturer_conflicts = $this->checkLecturerConflicts($lecturer_id, $day_id, $time_slot_id);
        if (!empty($lecturer_conflicts)) {
            $conflicts['lecturer'] = $lecturer_conflicts;
        }
        
        // Check room conflicts
        $room_conflicts = $this->checkRoomConflicts($room_id, $day_id, $time_slot_id);
        if (!empty($room_conflicts)) {
            $conflicts['room'] = $room_conflicts;
        }
        
        // Check class conflicts
        $class_conflicts = $this->checkClassConflicts($class_id, $day_id, $time_slot_id, $division_label);
        if (!empty($class_conflicts)) {
            $conflicts['class'] = $class_conflicts;
        }
        
        // Check room capacity vs class enrollment
        $capacity_conflicts = $this->checkRoomCapacity($room_id, $class_id);
        if (!empty($capacity_conflicts)) {
            $conflicts['capacity'] = $capacity_conflicts;
        }
        
        // Check stream consistency
        $stream_conflicts = $this->checkStreamConsistency($class_course_id, $lecturer_course_id, $room_id);
        if (!empty($stream_conflicts)) {
            $conflicts['stream'] = $stream_conflicts;
        }
        
        return $conflicts;
    }
    
    /**
     * Check if lecturer is already scheduled at this time
     */
    private function checkLecturerConflicts($lecturer_id, $day_id, $time_slot_id) {
        $sql = "SELECT t.id, cc.class_id, ct.name as class_name, co.course_code, co.name as course_name
                FROM timetable t 
                JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id 
                JOIN class_courses cc ON t.class_course_id = cc.id
                JOIN class_offerings cof ON cc.class_id = cof.id
                JOIN class_templates ct ON cof.template_id = ct.id
                JOIN courses co ON cc.course_id = co.id
                WHERE lc.lecturer_id = ? AND t.day_id = ? AND t.time_slot_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iii', $lecturer_id, $day_id, $time_slot_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $conflicts = [];
        while ($row = $result->fetch_assoc()) {
            $conflicts[] = [
                'type' => 'lecturer_busy',
                'message' => "Lecturer already teaching {$row['course_code']} to {$row['class_name']}",
                'details' => $row
            ];
        }
        
        $stmt->close();
        return $conflicts;
    }
    
    /**
     * Check if room is already occupied
     */
    private function checkRoomConflicts($room_id, $day_id, $time_slot_id) {
        $sql = "SELECT t.id, cc.class_id, ct.name as class_name, co.course_code, r.name as room_name
                FROM timetable t 
                JOIN class_courses cc ON t.class_course_id = cc.id
                JOIN class_offerings cof ON cc.class_id = cof.id
                JOIN class_templates ct ON cof.template_id = ct.id
                JOIN courses co ON cc.course_id = co.id
                JOIN rooms r ON t.room_id = r.id
                WHERE t.room_id = ? AND t.day_id = ? AND t.time_slot_id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iii', $room_id, $day_id, $time_slot_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $conflicts = [];
        while ($row = $result->fetch_assoc()) {
            $conflicts[] = [
                'type' => 'room_occupied',
                'message' => "Room {$row['room_name']} already occupied by {$row['class_name']} for {$row['course_code']}",
                'details' => $row
            ];
        }
        
        $stmt->close();
        return $conflicts;
    }
    
    /**
     * Check if class is already scheduled at this time
     */
    private function checkClassConflicts($class_id, $day_id, $time_slot_id, $division_label = null) {
        $sql = "SELECT t.id, co.course_code, co.name as course_name, r.name as room_name, t.division_label
                FROM timetable t 
                JOIN class_courses cc ON t.class_course_id = cc.id
                JOIN class_offerings cof ON cc.class_id = cof.id
                JOIN courses co ON cc.course_id = co.id
                JOIN rooms r ON t.room_id = r.id
                WHERE cc.class_id = ? AND t.day_id = ? AND t.time_slot_id = ?";
        
        $params = [$class_id, $day_id, $time_slot_id];
        $types = 'iii';
        
        if ($division_label !== null) {
            $sql .= " AND t.division_label = ?";
            $params[] = $division_label;
            $types .= 's';
        }
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $conflicts = [];
        while ($row = $result->fetch_assoc()) {
            $division_info = $row['division_label'] ? " (Division: {$row['division_label']})" : "";
            $conflicts[] = [
                'type' => 'class_busy',
                'message' => "Class already scheduled for {$row['course_code']} in {$row['room_name']}{$division_info}",
                'details' => $row
            ];
        }
        
        $stmt->close();
        return $conflicts;
    }
    
    /**
     * Check if room capacity is sufficient for class enrollment
     */
    private function checkRoomCapacity($room_id, $class_id) {
        $sql = "SELECT r.capacity, r.name as room_name, cof.current_enrollment, ct.name as class_name
                FROM rooms r
                JOIN class_offerings cof ON cof.id = ?
                JOIN class_templates ct ON cof.template_id = ct.id
                WHERE r.id = ?";

        // Note: params order matches cof.id then r.id
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $class_id, $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $conflicts = [];
        if ($row = $result->fetch_assoc()) {
            if ($row['current_enrollment'] > $row['capacity']) {
                $conflicts[] = [
                    'type' => 'capacity_exceeded',
                    'message' => "Room {$row['room_name']} (capacity: {$row['capacity']}) cannot accommodate {$row['class_name']} (enrollment: {$row['current_enrollment']})",
                    'details' => $row
                ];
            }
        }
        
        $stmt->close();
        return $conflicts;
    }
    
    /**
     * Check stream consistency across all entities
     */
    private function checkStreamConsistency($class_course_id, $lecturer_course_id, $room_id) {
        $sql = "SELECT 
                    cof.stream_id as class_stream,
                    co.stream_id as course_stream,
                    l.stream_id as lecturer_stream,
                    r.stream_id as room_stream,
                    ct.name as class_name,
                    co.code as course_code,
                    l.name as lecturer_name,
                    r.name as room_name
                FROM class_courses cc
                JOIN class_offerings cof ON cc.class_id = cof.id
                JOIN class_templates ct ON cof.template_id = ct.id
                JOIN courses co ON cc.course_id = co.id
                JOIN lecturer_courses lc ON lc.id = ?
                JOIN lecturers l ON lc.lecturer_id = l.id
                JOIN rooms r ON r.id = ?
                WHERE cc.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iii', $lecturer_course_id, $room_id, $class_course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $conflicts = [];
        if ($row = $result->fetch_assoc()) {
            $streams = [
                'class' => $row['class_stream'],
                'course' => $row['course_stream'],
                'lecturer' => $row['lecturer_stream'],
                'room' => $row['room_stream']
            ];
            
            $unique_streams = array_unique(array_values($streams));
            
            if (count($unique_streams) > 1) {
                $conflicts[] = [
                    'type' => 'stream_mismatch',
                    'message' => "Stream mismatch: Class({$streams['class']}), Course({$streams['course']}), Lecturer({$streams['lecturer']}), Room({$streams['room']})",
                    'details' => [
                        'streams' => $streams,
                        'entities' => [
                            'class' => $row['class_name'],
                            'course' => $row['course_code'],
                            'lecturer' => $row['lecturer_name'],
                            'room' => $row['room_name']
                        ]
                    ]
                ];
            }
        }
        
        $stmt->close();
        return $conflicts;
    }
    
    /**
     * Helper methods to get IDs from foreign key relationships
     */
    private function getClassIdFromClassCourse($class_course_id) {
        $stmt = $this->conn->prepare("SELECT class_id FROM class_courses WHERE id = ?");
        $stmt->bind_param('i', $class_course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $class_id = $result->fetch_assoc()['class_id'] ?? null;
        $stmt->close();
        return $class_id;
    }
    
    private function getCourseIdFromClassCourse($class_course_id) {
        $stmt = $this->conn->prepare("SELECT course_id FROM class_courses WHERE id = ?");
        $stmt->bind_param('i', $class_course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $course_id = $result->fetch_assoc()['course_id'] ?? null;
        $stmt->close();
        return $course_id;
    }
    
    private function getLecturerIdFromLecturerCourse($lecturer_course_id) {
        $stmt = $this->conn->prepare("SELECT lecturer_id FROM lecturer_courses WHERE id = ?");
        $stmt->bind_param('i', $lecturer_course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $lecturer_id = $result->fetch_assoc()['lecturer_id'] ?? null;
        $stmt->close();
        return $lecturer_id;
    }
    
    /**
     * Get detailed conflict report
     */
    public function getConflictReport($conflicts) {
        if (empty($conflicts)) {
            return "No conflicts detected.";
        }
        
        $report = "Conflicts detected:\n";
        foreach ($conflicts as $type => $type_conflicts) {
            $report .= "\n{$type} conflicts:\n";
            foreach ($type_conflicts as $conflict) {
                $report .= "- " . $conflict['message'] . "\n";
            }
        }
        
        return $report;
    }
    
    /**
     * Check if assignment is safe (no conflicts)
     */
    public function isSafeAssignment($class_course_id, $lecturer_course_id, $day_id, $time_slot_id, $room_id, $division_label = null) {
        $conflicts = $this->checkConflicts($class_course_id, $lecturer_course_id, $day_id, $time_slot_id, $room_id, $division_label);
        return empty($conflicts);
    }
}
}

/**
 * Global function to get conflict detector instance
 */
if (!function_exists('getConflictDetector')) {
function getConflictDetector() {
    global $conn;
    static $conflictDetector = null;
    
    if ($conflictDetector === null) {
        $conflictDetector = new ConflictDetector($conn);
    }
    
    return $conflictDetector;
}
}
?>