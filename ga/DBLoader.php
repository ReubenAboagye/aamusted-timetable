<?php
/**
 * Enhanced DB Loader for Genetic Algorithm
 * 
 * This class loads all required domain data for the genetic algorithm
 * with support for stream-based filtering and additional data requirements.
 */

class DBLoader {
    private $conn;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    /**
     * Load all required domain tables into arrays used by the GA.
     * Returns associative array with keys: class_courses, lecturer_courses, classes, rooms, time_slots, days, streams
     */
    public function loadAll(array $options = []): array {
        $streamId = $options['stream_id'] ?? null;
        $academicYear = $options['academic_year'] ?? null;
        $semester = $options['semester'] ?? null;
        
        return [
            'class_courses' => $this->loadClassCourses($streamId, $academicYear, $semester),
            'lecturer_courses' => $this->loadLecturerCourses($streamId),
            'classes' => $this->loadClasses($streamId),
            'courses' => $this->loadCourses($streamId),
            'lecturers' => $this->loadLecturers($streamId),
            'rooms' => $this->loadRooms($streamId),
            'time_slots' => $this->loadTimeSlots($streamId),
            'days' => $this->loadDays(),
            'streams' => $this->loadStreams(),
            'buildings' => $this->loadBuildings(),
            'room_types' => $this->loadRoomTypes(),
            'levels' => $this->loadLevels(),
            'programs' => $this->loadPrograms($streamId),
            'departments' => $this->loadDepartments($streamId)
        ];
    }

    private function fetchAll($sql) {
        $res = $this->conn->query($sql);
        $out = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) { 
                $out[] = $r; 
            }
            $res->close();
        }
        return $out;
    }

    private function loadClassCourses($streamId = null, $academicYear = null, $semester = null) {
        $sql = "SELECT cc.id, cc.class_id, cc.course_id, cc.lecturer_id, cc.semester, cc.academic_year, cc.is_active 
                FROM class_courses cc 
                JOIN classes c ON cc.class_id = c.id";
        
        $conditions = ["cc.is_active = 1"];
        
        if ($streamId) {
            $conditions[] = "c.stream_id = " . intval($streamId);
        }
        
        if ($academicYear) {
            $conditions[] = "cc.academic_year = '" . $this->conn->real_escape_string($academicYear) . "'";
        }
        
        if ($semester) {
            $conditions[] = "cc.semester = '" . $this->conn->real_escape_string($semester) . "'";
        }
        
        $sql .= " WHERE " . implode(" AND ", $conditions);
        
        return $this->fetchAll($sql);
    }

    private function loadLecturerCourses($streamId = null) {
        $sql = "SELECT lc.id, lc.lecturer_id, lc.course_id, lc.is_active 
                FROM lecturer_courses lc 
                WHERE lc.is_active = 1";
        
        return $this->fetchAll($sql);
    }

    private function loadClasses($streamId = null) {
        $sql = "SELECT id, name, code, total_capacity, divisions_count, stream_id, program_id, level_id, is_active 
                FROM classes WHERE is_active = 1";
        
        if ($streamId) {
            $sql .= " AND stream_id = " . intval($streamId);
        }
        
        return $this->fetchAll($sql);
    }

    private function loadCourses($streamId = null) {
        $sql = "SELECT id, code, name, department_id, credits, hours_per_week, is_active 
                FROM courses WHERE is_active = 1";
        
        return $this->fetchAll($sql);
    }

    private function loadLecturers($streamId = null) {
        $sql = "SELECT id, name, department_id, is_active 
                FROM lecturers WHERE is_active = 1";
        
        return $this->fetchAll($sql);
    }

    private function loadRooms($streamId = null) {
        $sql = "SELECT id, name, room_type, capacity, building_id, is_active 
                FROM rooms WHERE is_active = 1";
        
        return $this->fetchAll($sql);
    }

    private function loadTimeSlots($streamId = null) {
        if ($streamId) {
            // Try to get stream-specific time slots
            $sql = "SELECT ts.id, ts.start_time, ts.end_time, ts.duration, ts.is_break, ts.is_mandatory 
                    FROM time_slots ts 
                    JOIN stream_time_slots sts ON ts.id = sts.time_slot_id 
                    WHERE sts.stream_id = " . intval($streamId) . " AND sts.is_active = 1 
                    ORDER BY ts.start_time";
            
            $streamSlots = $this->fetchAll($sql);
            
            if (!empty($streamSlots)) {
                return $streamSlots;
            }
        }
        
        // Fallback to mandatory time slots
        return $this->fetchAll("SELECT id, start_time, end_time, duration, is_break, is_mandatory 
                               FROM time_slots WHERE is_mandatory = 1 ORDER BY start_time");
    }

    private function loadDays() {
        return $this->fetchAll("SELECT id, name, is_active FROM days WHERE is_active = 1 ORDER BY id");
    }

    private function loadStreams() {
        return $this->fetchAll("SELECT id, name, code, description, active_days, period_start, period_end, break_start, break_end, is_active FROM streams WHERE is_active = 1");
    }

    private function loadBuildings() {
        return $this->fetchAll("SELECT id, name, code, description, is_active FROM buildings WHERE is_active = 1");
    }

    private function loadRoomTypes() {
        return $this->fetchAll("SELECT id, name, description, is_active FROM room_types WHERE is_active = 1");
    }

    private function loadLevels() {
        return $this->fetchAll("SELECT id, name, code, description, is_active FROM levels WHERE is_active = 1");
    }

    private function loadPrograms($streamId = null) {
        $sql = "SELECT id, department_id, name, code, description, duration_years, is_active 
                FROM programs WHERE is_active = 1";
        
        return $this->fetchAll($sql);
    }

    private function loadDepartments($streamId = null) {
        $sql = "SELECT id, name, code, description, is_active 
                FROM departments WHERE is_active = 1";
        
        return $this->fetchAll($sql);
    }

    /**
     * Load data for a specific stream
     */
    public function loadStreamData($streamId): array {
        return $this->loadAll(['stream_id' => $streamId]);
    }

    /**
     * Load data for a specific academic period
     */
    public function loadPeriodData($academicYear, $semester, $streamId = null): array {
        return $this->loadAll([
            'academic_year' => $academicYear,
            'semester' => $semester,
            'stream_id' => $streamId
        ]);
    }

    /**
     * Get statistics about the loaded data
     */
    public function getDataStatistics(array $data): array {
        return [
            'class_courses' => count($data['class_courses']),
            'lecturer_courses' => count($data['lecturer_courses']),
            'classes' => count($data['classes']),
            'courses' => count($data['courses']),
            'lecturers' => count($data['lecturers']),
            'rooms' => count($data['rooms']),
            'time_slots' => count($data['time_slots']),
            'days' => count($data['days']),
            'streams' => count($data['streams']),
            'total_assignments_needed' => count($data['class_courses']),
            'total_time_slots_available' => count($data['time_slots']) * count($data['days']) * count($data['rooms'])
        ];
    }

    /**
     * Validate that sufficient data exists for timetable generation
     */
    public function validateDataForGeneration(array $data): array {
        $errors = [];
        $warnings = [];

        // Check minimum requirements
        if (empty($data['class_courses'])) {
            $errors[] = 'No class-course assignments found';
        }

        if (empty($data['time_slots'])) {
            $errors[] = 'No time slots available';
        }

        if (empty($data['days'])) {
            $errors[] = 'No days available';
        }

        if (empty($data['rooms'])) {
            $errors[] = 'No rooms available';
        }

        // Check for potential issues
        if (count($data['class_courses']) > count($data['time_slots']) * count($data['days']) * count($data['rooms'])) {
            $warnings[] = 'More assignments than available time slots. Timetable may not be feasible.';
        }

        if (empty($data['lecturer_courses'])) {
            $warnings[] = 'No lecturer-course assignments found. Some courses may not have lecturers.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }
}
?>

