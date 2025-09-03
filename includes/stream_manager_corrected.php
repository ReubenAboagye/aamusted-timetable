<?php
/**
 * CORRECTED Stream Manager - Only classes are stream-specific
 * All other entities (courses, lecturers, rooms, departments) are GLOBAL
 */

if (!class_exists('StreamManager')) {
class StreamManager {
    private $conn;
    private $current_stream_id;
    private $current_stream_name;
    private $current_stream_settings;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->initializeStream();
    }
    
    /**
     * Initialize the current stream based on session or default
     */
    private function initializeStream() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session for current stream
        if (isset($_SESSION['current_stream_id'])) {
            $this->current_stream_id = $_SESSION['current_stream_id'];
        } else {
            // Get the currently active stream from database
            $active_stream_sql = "SELECT id FROM streams WHERE is_current = 1 LIMIT 1";
            $active_result = $this->conn->query($active_stream_sql);
            if ($active_result && $active_result->num_rows > 0) {
                $active_row = $active_result->fetch_assoc();
                $this->current_stream_id = $active_row['id'];
            } else {
                // Fallback to first active stream
                $fallback_sql = "SELECT id FROM streams WHERE is_active = 1 ORDER BY id LIMIT 1";
                $fallback_result = $this->conn->query($fallback_sql);
                if ($fallback_result && $fallback_result->num_rows > 0) {
                    $fallback_row = $fallback_result->fetch_assoc();
                    $this->current_stream_id = $fallback_row['id'];
                } else {
                    $this->current_stream_id = 1; // Ultimate fallback
                }
            }
            $_SESSION['current_stream_id'] = $this->current_stream_id;
        }
        
        $this->loadStreamDetails();
    }
    
    /**
     * Load current stream details and settings
     */
    private function loadStreamDetails() {
        $sql = "SELECT name, code, period_start, period_end, break_start, break_end, 
                       active_days, max_daily_hours, max_weekly_hours 
                FROM streams WHERE id = ? AND is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->current_stream_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $this->current_stream_name = $row['name'];
            $this->current_stream_settings = [
                'code' => $row['code'],
                'period_start' => $row['period_start'],
                'period_end' => $row['period_end'],
                'break_start' => $row['break_start'],
                'break_end' => $row['break_end'],
                'active_days' => json_decode($row['active_days'], true) ?: [],
                'max_daily_hours' => $row['max_daily_hours'],
                'max_weekly_hours' => $row['max_weekly_hours']
            ];
        } else {
            $this->current_stream_name = "Unknown";
            $this->current_stream_settings = [];
        }
        $stmt->close();
    }
    
    /**
     * Get current stream ID
     */
    public function getCurrentStreamId() {
        return $this->current_stream_id;
    }
    
    /**
     * Get current stream name
     */
    public function getCurrentStreamName() {
        return $this->current_stream_name;
    }
    
    /**
     * Get current stream settings
     */
    public function getCurrentStreamSettings() {
        return $this->current_stream_settings;
    }
    
    /**
     * Set current stream
     */
    public function setCurrentStream($stream_id) {
        if (is_numeric($stream_id)) {
            $this->current_stream_id = $stream_id;
            $_SESSION['current_stream_id'] = $stream_id;
            $this->loadStreamDetails();
            return true;
        }
        return false;
    }
    
    /**
     * CORRECTED: Add stream filter ONLY to classes table
     * All other tables are global and should NOT be filtered by stream
     */
    public function addStreamFilter($sql, $table_alias = '') {
        $alias = $table_alias ? $table_alias . '.' : '';
        $aliasTrim = rtrim($table_alias, '.');
        
        // ONLY filter classes table - everything else is global
        $classes_aliases = ['c', 'classes', 'cl'];
        
        if (in_array(strtolower($aliasTrim), $classes_aliases)) {
            if (strpos($sql, 'WHERE') !== false) {
                $sql .= " AND {$alias}stream_id = " . $this->current_stream_id;
            } else {
                $sql .= " WHERE {$alias}stream_id = " . $this->current_stream_id;
            }
        }
        
        return $sql;
    }
    
    /**
     * Get stream filter condition for classes only
     */
    public function getStreamFilterCondition($table_alias = '') {
        $aliasTrim = rtrim($table_alias, '.');
        $classes_aliases = ['c', 'classes', 'cl'];
        
        if (in_array(strtolower($aliasTrim), $classes_aliases)) {
            $alias = $table_alias ? $table_alias . '.' : '';
            return "{$alias}stream_id = " . $this->current_stream_id;
        }
        return '';
    }
    
    /**
     * Get classes for current stream
     */
    public function getCurrentStreamClasses($additional_conditions = '') {
        $sql = "SELECT c.*, d.name as department_name, p.name as program_name, l.name as level_name
                FROM classes c
                JOIN departments d ON c.department_id = d.id
                JOIN programs p ON c.program_id = p.id  
                JOIN levels l ON c.level_id = l.id
                WHERE c.stream_id = ? AND c.is_active = 1";
        
        if (!empty($additional_conditions)) {
            $sql .= " AND ($additional_conditions)";
        }
        
        $sql .= " ORDER BY d.name, p.name, l.numeric_value, c.name";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->current_stream_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get time slots available for current stream
     */
    public function getCurrentStreamTimeSlots() {
        $sql = "SELECT ts.*, sts.is_active as stream_active
                FROM time_slots ts
                JOIN stream_time_slots sts ON ts.id = sts.time_slot_id
                WHERE sts.stream_id = ? AND sts.is_active = 1 AND ts.is_active = 1
                ORDER BY ts.start_time";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->current_stream_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get days available for current stream
     */
    public function getCurrentStreamDays() {
        $sql = "SELECT d.*, sd.is_active as stream_active
                FROM days d
                JOIN stream_days sd ON d.id = sd.day_id
                WHERE sd.stream_id = ? AND sd.is_active = 1 AND d.is_active = 1
                ORDER BY d.sort_order";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->current_stream_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Professional validation for class-course assignments (department-oriented)
     */
    public function validateClassCourseAssignment($class_id, $course_id) {
        $sql = "SELECT 
                    c.department_id as class_dept,
                    c.level_id as class_level,
                    c.program_id as class_program,
                    c.name as class_name,
                    co.department_id as course_dept,
                    co.level_id as course_level,
                    co.course_name,
                    co.course_type,
                    co.prerequisites,
                    d1.name as class_dept_name,
                    d2.name as course_dept_name
                FROM classes c
                JOIN departments d1 ON c.department_id = d1.id
                CROSS JOIN courses co
                JOIN departments d2 ON co.department_id = d2.id
                WHERE c.id = ? AND co.id = ? AND c.is_active = 1 AND co.is_active = 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $class_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'valid' => false,
                'errors' => ['Class or course not found or inactive'],
                'warnings' => []
            ];
        }
        
        $data = $result->fetch_assoc();
        $stmt->close();
        
        $errors = [];
        $warnings = [];
        
        // Professional validation rules:
        
        // 1. CRITICAL: Level must match exactly
        if ($data['class_level'] !== $data['course_level']) {
            $errors[] = "Level mismatch: Class is at level {$data['class_level']}, Course is at level {$data['course_level']}";
        }
        
        // 2. PREFERRED: Same department (but allow cross-departmental for electives)
        if ($data['class_dept'] !== $data['course_dept']) {
            if ($data['course_type'] === 'core') {
                $errors[] = "Core course from different department: Class from {$data['class_dept_name']}, Course from {$data['course_dept_name']}";
            } else {
                $warnings[] = "Cross-departmental assignment: Class from {$data['class_dept_name']}, Course from {$data['course_dept_name']}";
            }
        }
        
        // 3. Check if course is already assigned to this class
        $existing_sql = "SELECT id FROM class_courses WHERE class_id = ? AND course_id = ? AND is_active = 1";
        $existing_stmt = $this->conn->prepare($existing_sql);
        $existing_stmt->bind_param("ii", $class_id, $course_id);
        $existing_stmt->execute();
        $existing_result = $existing_stmt->get_result();
        
        if ($existing_result->num_rows > 0) {
            $warnings[] = "Course is already assigned to this class";
        }
        $existing_stmt->close();
        
        // 4. Check prerequisites (simplified)
        if (!empty($data['prerequisites'])) {
            $prereqs = json_decode($data['prerequisites'], true);
            if (!empty($prereqs)) {
                $warnings[] = "Course has prerequisites that should be verified";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'data' => $data
        ];
    }
    
    /**
     * Get recommended courses for a class (department-oriented)
     */
    public function getRecommendedCoursesForClass($class_id) {
        $sql = "SELECT 
                    co.id,
                    co.course_code,
                    co.course_name,
                    co.credits,
                    co.course_type,
                    d.name as department_name,
                    
                    -- Recommendation score
                    (
                        CASE WHEN c.department_id = co.department_id THEN 10 ELSE 0 END +
                        CASE WHEN c.level_id = co.level_id THEN 10 ELSE -10 END +
                        CASE WHEN co.course_type = 'core' THEN 8 ELSE 0 END +
                        CASE WHEN co.course_type = 'elective' THEN 3 ELSE 0 END +
                        CASE WHEN co.course_type = 'practical' THEN 5 ELSE 0 END
                    ) as recommendation_score,
                    
                    -- Status
                    CASE 
                        WHEN cc.id IS NOT NULL THEN 'assigned'
                        WHEN c.department_id = co.department_id AND c.level_id = co.level_id THEN 'recommended'
                        WHEN c.level_id = co.level_id THEN 'possible'
                        ELSE 'not_suitable'
                    END as assignment_status
                    
                FROM classes c
                CROSS JOIN courses co
                JOIN departments d ON co.department_id = d.id
                LEFT JOIN class_courses cc ON c.id = cc.class_id AND co.id = cc.course_id AND cc.is_active = 1
                WHERE c.id = ? AND c.is_active = 1 AND co.is_active = 1
                ORDER BY recommendation_score DESC, co.course_code";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get all available streams
     */
    public function getAllStreams() {
        $sql = "SELECT id, name, code, description, period_start, period_end, is_current 
                FROM streams WHERE is_active = 1 ORDER BY name";
        $result = $this->conn->query($sql);
        $streams = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $streams[] = $row;
            }
        }
        
        return $streams;
    }
    
    /**
     * Check if current stream period allows a specific time
     */
    public function isTimeInStreamPeriod($time) {
        $settings = $this->current_stream_settings;
        if (empty($settings)) return true;
        
        $check_time = is_string($time) ? $time : $time->format('H:i:s');
        
        return ($check_time >= $settings['period_start'] && 
                $check_time <= $settings['period_end'] &&
                !($check_time >= $settings['break_start'] && $check_time <= $settings['break_end']));
    }
    
    /**
     * Check if current stream allows a specific day
     */
    public function isDayInStreamSchedule($day_name) {
        $settings = $this->current_stream_settings;
        if (empty($settings['active_days'])) return true;
        
        return in_array(strtolower($day_name), array_map('strtolower', $settings['active_days']));
    }
    
    /**
     * Get stream selector HTML
     */
    public function getStreamSelector() {
        $streams = $this->getAllStreams();
        $html = '<div class="stream-selector">';
        $html .= '<label for="streamSelect" class="form-label">Current Stream:</label>';
        $html .= '<select id="streamSelect" class="form-select form-select-sm" onchange="changeStream(this.value)">';
        
        foreach ($streams as $stream) {
            $selected = ($stream['id'] == $this->current_stream_id) ? 'selected' : '';
            $period = $stream['period_start'] . ' - ' . $stream['period_end'];
            $html .= '<option value="' . $stream['id'] . '" ' . $selected . '>' . 
                     htmlspecialchars($stream['name']) . ' (' . $period . ')</option>';
        }
        
        $html .= '</select>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get stream badge HTML with period info
     */
    public function getStreamBadge() {
        $settings = $this->current_stream_settings;
        $period_info = '';
        if (!empty($settings)) {
            $period_info = ' (' . $settings['period_start'] . '-' . $settings['period_end'] . ')';
        }
        return '<span class="badge bg-primary">' . 
               htmlspecialchars($this->current_stream_name . $period_info) . '</span>';
    }
    
    /**
     * Get stream statistics
     */
    public function getStreamStatistics($stream_id = null) {
        $stream_id = $stream_id ?: $this->current_stream_id;
        
        $sql = "SELECT 
                    s.name as stream_name,
                    s.code as stream_code,
                    COUNT(DISTINCT c.id) as total_classes,
                    COUNT(DISTINCT cc.course_id) as assigned_courses,
                    COUNT(DISTINCT t.id) as timetable_entries,
                    SUM(c.current_enrollment) as total_enrollment,
                    AVG(c.current_enrollment) as avg_enrollment_per_class
                FROM streams s
                LEFT JOIN classes c ON s.id = c.stream_id AND c.is_active = 1
                LEFT JOIN class_courses cc ON c.id = cc.class_id AND cc.is_active = 1
                LEFT JOIN timetable t ON cc.id = t.class_course_id
                WHERE s.id = ? AND s.is_active = 1
                GROUP BY s.id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $stream_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
}
}

/**
 * Global function to get stream manager instance
 */
if (!function_exists('getStreamManager')) {
function getStreamManager() {
    global $conn;
    static $streamManager = null;
    
    if ($streamManager === null) {
        $streamManager = new StreamManager($conn);
    }
    
    return $streamManager;
}
}

/**
 * CORRECTED: Helper function to add stream filter (only for classes)
 */
if (!function_exists('addStreamFilter')) {
function addStreamFilter($sql, $table_alias = '') {
    $streamManager = getStreamManager();
    return $streamManager->addStreamFilter($sql, $table_alias);
}
}

/**
 * Helper function to get stream filter condition (only for classes)
 */
if (!function_exists('getStreamFilterCondition')) {
function getStreamFilterCondition($table_alias = '') {
    $streamManager = getStreamManager();
    return $streamManager->getStreamFilterCondition($table_alias);
}
}

/**
 * Professional class-course assignment function
 */
if (!function_exists('assignCourseToClassProfessional')) {
function assignCourseToClassProfessional($class_id, $course_id, $semester = 'first', $academic_year = '2024/2025', $assigned_by = null) {
    global $conn;
    
    $streamManager = getStreamManager();
    $validation = $streamManager->validateClassCourseAssignment($class_id, $course_id);
    
    if (!$validation['valid']) {
        return [
            'success' => false,
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings']
        ];
    }
    
    // Use stored procedure for safe assignment
    $stmt = $conn->prepare("CALL assign_course_to_class_professional(?, ?, ?, ?, ?, ?, @result, @warnings)");
    $assignment_reason = "Professional assignment based on department and level compatibility";
    
    $stmt->bind_param("isssss", $class_id, $course_id, $semester, $academic_year, $assigned_by, $assignment_reason);
    
    if ($stmt->execute()) {
        // Get results
        $result_query = $conn->query("SELECT @result as result, @warnings as warnings");
        $result_data = $result_query->fetch_assoc();
        
        $stmt->close();
        
        return [
            'success' => strpos($result_data['result'], 'SUCCESS') === 0,
            'result' => $result_data['result'],
            'warnings' => json_decode($result_data['warnings'], true) ?: []
        ];
    } else {
        $stmt->close();
        return [
            'success' => false,
            'errors' => ['Database error: ' . $conn->error]
        ];
    }
}
}
?>
