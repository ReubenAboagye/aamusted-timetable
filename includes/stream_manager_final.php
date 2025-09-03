<?php
/**
 * FINAL Stream Manager - Based on your actual DB Schema
 * Correctly implements: Only CLASSES are stream-specific, everything else is GLOBAL
 * Professional implementation with department-oriented validation
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
     * Initialize the current stream based on session or database
     */
    private function initializeStream() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check session for current stream
        if (isset($_SESSION['current_stream_id']) && is_numeric($_SESSION['current_stream_id'])) {
            $this->current_stream_id = (int)$_SESSION['current_stream_id'];
        } else {
            // Get the currently active stream from database
            $active_stream_sql = "SELECT id FROM streams WHERE is_current = 1 AND is_active = 1 LIMIT 1";
            $active_result = $this->conn->query($active_stream_sql);
            
            if ($active_result && $active_result->num_rows > 0) {
                $active_row = $active_result->fetch_assoc();
                $this->current_stream_id = (int)$active_row['id'];
            } else {
                // Fallback to first active stream
                $fallback_sql = "SELECT id FROM streams WHERE is_active = 1 ORDER BY sort_order, id LIMIT 1";
                $fallback_result = $this->conn->query($fallback_sql);
                
                if ($fallback_result && $fallback_result->num_rows > 0) {
                    $fallback_row = $fallback_result->fetch_assoc();
                    $this->current_stream_id = (int)$fallback_row['id'];
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
        $sql = "SELECT name, code, description, period_start, period_end, break_start, break_end, 
                       active_days, max_daily_hours, max_weekly_hours, color_code
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
                'description' => $row['description'],
                'period_start' => $row['period_start'],
                'period_end' => $row['period_end'],
                'break_start' => $row['break_start'],
                'break_end' => $row['break_end'],
                'active_days' => json_decode($row['active_days'], true) ?: [],
                'max_daily_hours' => (int)$row['max_daily_hours'],
                'max_weekly_hours' => (int)$row['max_weekly_hours'],
                'color_code' => $row['color_code']
            ];
        } else {
            $this->current_stream_name = "Unknown Stream";
            $this->current_stream_settings = [];
        }
        $stmt->close();
    }
    
    // ========================================================================
    // CORE STREAM METHODS
    // ========================================================================
    
    public function getCurrentStreamId() {
        return $this->current_stream_id;
    }
    
    public function getCurrentStreamName() {
        return $this->current_stream_name;
    }
    
    public function getCurrentStreamSettings() {
        return $this->current_stream_settings;
    }
    
    public function setCurrentStream($stream_id) {
        if (is_numeric($stream_id)) {
            // Verify stream exists and is active
            $verify_sql = "SELECT id FROM streams WHERE id = ? AND is_active = 1";
            $verify_stmt = $this->conn->prepare($verify_sql);
            $verify_stmt->bind_param("i", $stream_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows > 0) {
                $this->current_stream_id = (int)$stream_id;
                $_SESSION['current_stream_id'] = $this->current_stream_id;
                $this->loadStreamDetails();
                $verify_stmt->close();
                return true;
            }
            $verify_stmt->close();
        }
        return false;
    }
    
    // ========================================================================
    // CORRECTED FILTERING LOGIC - ONLY CLASSES ARE STREAM-SPECIFIC
    // ========================================================================
    
    /**
     * CORRECTED: Add stream filter ONLY to classes table
     * All other tables (courses, lecturers, rooms, departments, programs) are GLOBAL
     */
    public function addStreamFilter($sql, $table_alias = '') {
        $alias = $table_alias ? $table_alias . '.' : '';
        $aliasTrim = rtrim($table_alias, '.');
        
        // ONLY filter classes table - everything else is global and shared across streams
        $classes_aliases = ['c', 'classes', 'cl', 'class'];
        
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
        $classes_aliases = ['c', 'classes', 'cl', 'class'];
        
        if (in_array(strtolower($aliasTrim), $classes_aliases)) {
            $alias = $table_alias ? $table_alias . '.' : '';
            return "{$alias}stream_id = " . $this->current_stream_id;
        }
        return '';
    }
    
    // ========================================================================
    // PROFESSIONAL CLASS-COURSE ASSIGNMENT VALIDATION
    // ========================================================================
    
    /**
     * Professional validation for class-course assignments (department-oriented)
     */
    public function validateClassCourseAssignment($class_id, $course_id) {
        $sql = "SELECT 
                    c.id as class_id,
                    c.name as class_name,
                    c.level_id as class_level_id,
                    p.department_id as class_department_id,
                    d1.name as class_department_name,
                    l1.name as class_level_name,
                    
                    co.id as course_id,
                    co.name as course_name,
                    co.level_id as course_level_id,
                    co.department_id as course_department_id,
                    co.course_type,
                    d2.name as course_department_name,
                    l2.name as course_level_name
                    
                FROM classes c
                JOIN programs p ON c.program_id = p.id
                JOIN departments d1 ON p.department_id = d1.id
                JOIN levels l1 ON c.level_id = l1.id
                CROSS JOIN courses co
                JOIN departments d2 ON co.department_id = d2.id
                JOIN levels l2 ON co.level_id = l2.id
                WHERE c.id = ? AND co.id = ? AND c.is_active = 1 AND co.is_active = 1";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $class_id, $course_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return [
                'valid' => false,
                'errors' => ['Class or course not found or inactive'],
                'warnings' => [],
                'quality_score' => 0
            ];
        }
        
        $data = $result->fetch_assoc();
        $stmt->close();
        
        $errors = [];
        $warnings = [];
        $quality_score = 0;
        
        // PROFESSIONAL VALIDATION RULES:
        
        // 1. CRITICAL: Level must match exactly (academic integrity)
        if ($data['class_level_id'] !== $data['course_level_id']) {
            $errors[] = "Level mismatch: Class '{$data['class_name']}' is {$data['class_level_name']}, Course '{$data['course_name']}' is {$data['course_level_name']}";
        } else {
            $quality_score += 25; // Major points for level match
        }
        
        // 2. PROFESSIONAL: Department alignment (program-based)
        if ($data['class_department_id'] === $data['course_department_id']) {
            $quality_score += 20; // Points for same department
        } else {
            if ($data['course_type'] === 'core') {
                $errors[] = "Core course '{$data['course_name']}' from {$data['course_department_name']} cannot be assigned to class from {$data['class_department_name']}";
            } else {
                $quality_score += 5; // Some points for cross-departmental electives
                $warnings[] = "Cross-departmental {$data['course_type']} assignment: {$data['class_department_name']} class taking {$data['course_department_name']} course";
            }
        }
        
        // 3. Course type scoring
        switch ($data['course_type']) {
            case 'core':
                $quality_score += 15;
                break;
            case 'elective':
                $quality_score += 8;
                break;
            case 'practical':
                $quality_score += 12;
                break;
            case 'project':
                $quality_score += 10;
                break;
        }
        
        // 4. Check if already assigned
        $existing_sql = "SELECT id, assignment_type, assigned_by FROM class_courses 
                        WHERE class_id = ? AND course_id = ? AND is_active = 1";
        $existing_stmt = $this->conn->prepare($existing_sql);
        $existing_stmt->bind_param("ii", $class_id, $course_id);
        $existing_stmt->execute();
        $existing_result = $existing_stmt->get_result();
        
        if ($existing_result->num_rows > 0) {
            $existing_data = $existing_result->fetch_assoc();
            $warnings[] = "Course already assigned to this class (Type: {$existing_data['assignment_type']}, By: {$existing_data['assigned_by']})";
        }
        $existing_stmt->close();
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'quality_score' => $quality_score,
            'data' => $data
        ];
    }
    
    // ========================================================================
    // DATA ACCESS METHODS
    // ========================================================================
    
    /**
     * Get classes for current stream with full context
     */
    public function getCurrentStreamClasses($filters = []) {
        $sql = "SELECT 
                    c.*,
                    p.name as program_name,
                    p.code as program_code,
                    d.name as department_name,
                    d.code as department_code,
                    l.name as level_name,
                    l.numeric_value as level_number,
                    s.name as stream_name,
                    s.code as stream_code,
                    
                    -- Assignment statistics
                    (SELECT COUNT(*) FROM class_courses cc WHERE cc.class_id = c.id AND cc.is_active = 1) as assigned_courses_count,
                    (SELECT COUNT(*) FROM timetable t WHERE t.class_id = c.id) as scheduled_sessions_count
                    
                FROM classes c
                LEFT JOIN programs p ON c.program_id = p.id
                LEFT JOIN departments d ON p.department_id = d.id
                LEFT JOIN levels l ON c.level_id = l.id
                LEFT JOIN streams s ON c.stream_id = s.id
                WHERE c.stream_id = ? AND c.is_active = 1";
        
        $params = [$this->current_stream_id];
        $types = 'i';
        
        // Apply filters
        if (!empty($filters['department_id'])) {
            $sql .= " AND p.department_id = ?";
            $params[] = $filters['department_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['program_id'])) {
            $sql .= " AND c.program_id = ?";
            $params[] = $filters['program_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['level_id'])) {
            $sql .= " AND c.level_id = ?";
            $params[] = $filters['level_id'];
            $types .= 'i';
        }
        
        if (!empty($filters['search_name'])) {
            $sql .= " AND c.name LIKE ?";
            $params[] = "%{$filters['search_name']}%";
            $types .= 's';
        }
        
        $sql .= " ORDER BY d.name, p.name, l.numeric_value, c.name";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get ALL courses (global) with compatibility scoring for a specific class
     */
    public function getCoursesWithCompatibility($class_id = null) {
        $sql = "SELECT 
                    co.*,
                    d.name as department_name,
                    d.code as department_code,
                    l.name as level_name,
                    l.numeric_value as level_number";
        
        // Add compatibility scoring if class_id provided
        if ($class_id) {
            $sql .= ",
                    -- Compatibility with specific class
                    (SELECT p.department_id FROM classes c JOIN programs p ON c.program_id = p.id WHERE c.id = ?) as class_dept_id,
                    (SELECT c.level_id FROM classes c WHERE c.id = ?) as class_level_id,
                    
                    -- Professional compatibility score
                    CASE 
                        WHEN (SELECT p.department_id FROM classes c JOIN programs p ON c.program_id = p.id WHERE c.id = ?) = co.department_id 
                             AND (SELECT c.level_id FROM classes c WHERE c.id = ?) = co.level_id 
                             AND co.course_type = 'core' THEN 50
                        WHEN (SELECT p.department_id FROM classes c JOIN programs p ON c.program_id = p.id WHERE c.id = ?) = co.department_id 
                             AND (SELECT c.level_id FROM classes c WHERE c.id = ?) = co.level_id THEN 40
                        WHEN (SELECT c.level_id FROM classes c WHERE c.id = ?) = co.level_id 
                             AND co.course_type IN ('elective', 'practical') THEN 25
                        WHEN (SELECT c.level_id FROM classes c WHERE c.id = ?) = co.level_id THEN 15
                        ELSE 0
                    END as compatibility_score,
                    
                    -- Assignment status
                    CASE 
                        WHEN cc.id IS NOT NULL AND cc.is_active = 1 THEN 'assigned'
                        ELSE 'available'
                    END as assignment_status";
        }
        
        $sql .= "
                FROM courses co
                LEFT JOIN departments d ON co.department_id = d.id
                LEFT JOIN levels l ON co.level_id = l.id";
        
        if ($class_id) {
            $sql .= " LEFT JOIN class_courses cc ON co.id = cc.course_id AND cc.class_id = ?";
        }
        
        $sql .= " WHERE co.is_active = 1";
        
        // Apply filters if provided
        $params = [];
        $types = '';
        
        if ($class_id) {
            $params = array_fill(0, 8, $class_id); // For the 8 subqueries
            $params[] = $class_id; // For the LEFT JOIN
            $types = str_repeat('i', 9);
        }
        
        $sql .= " ORDER BY d.name, l.numeric_value, co.code";
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get available time slots for current stream
     */
    public function getStreamTimeSlots() {
        $sql = "SELECT ts.*, sts.priority
                FROM time_slots ts
                JOIN stream_time_slots sts ON ts.id = sts.time_slot_id
                WHERE sts.stream_id = ? AND sts.is_active = 1 AND ts.is_active = 1
                ORDER BY sts.priority, ts.start_time";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->current_stream_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Get available days for current stream
     */
    public function getStreamDays() {
        $sql = "SELECT d.*
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
     * Get all available streams
     */
    public function getAllStreams() {
        $sql = "SELECT id, name, code, description, period_start, period_end, 
                       color_code, is_current, sort_order
                FROM streams WHERE is_active = 1 ORDER BY sort_order, name";
        $result = $this->conn->query($sql);
        $streams = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $streams[] = $row;
            }
        }
        
        return $streams;
    }
    
    // ========================================================================
    // PROFESSIONAL ASSIGNMENT METHODS
    // ========================================================================
    
    /**
     * Get recommended courses for a class based on professional criteria
     */
    public function getRecommendedCoursesForClass($class_id) {
        $sql = "SELECT 
                    recommendation_status,
                    recommendation_score,
                    course_id,
                    course_code,
                    course_name,
                    course_type,
                    credits,
                    class_department,
                    course_department,
                    department_match,
                    level_match,
                    assigned_by,
                    current_quality_score
                FROM course_assignment_recommendations 
                WHERE class_id = ?
                ORDER BY recommendation_score DESC, course_code";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        return $stmt->get_result();
    }
    
    /**
     * Professional assignment with full validation
     */
    public function assignCourseToClass($class_id, $course_id, $lecturer_id = null, $semester = 'first', $academic_year = '2024/2025', $assigned_by = null) {
        // First validate the assignment
        $validation = $this->validateClassCourseAssignment($class_id, $course_id);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings']
            ];
        }
        
        // Use stored procedure for safe assignment
        $stmt = $this->conn->prepare("CALL assign_course_to_class_professional(?, ?, ?, ?, ?, ?, @result, @quality_score)");
        $assigned_by = $assigned_by ?: ($_SESSION['user_name'] ?? 'System');
        
        $stmt->bind_param("iiisss", $class_id, $course_id, $lecturer_id, $semester, $academic_year, $assigned_by);
        
        if ($stmt->execute()) {
            // Get results
            $result_query = $this->conn->query("SELECT @result as result, @quality_score as quality_score");
            $result_data = $result_query->fetch_assoc();
            
            $stmt->close();
            
            $success = strpos($result_data['result'], 'SUCCESS') === 0;
            
            return [
                'success' => $success,
                'message' => $result_data['result'],
                'quality_score' => (int)$result_data['quality_score'],
                'warnings' => $validation['warnings']
            ];
        } else {
            $stmt->close();
            return [
                'success' => false,
                'errors' => ['Database error: ' . $this->conn->error]
            ];
        }
    }
    
    // ========================================================================
    // UTILITY METHODS
    // ========================================================================
    
    /**
     * Check if time is within current stream period
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
     * Get stream statistics
     */
    public function getStreamStatistics($stream_id = null) {
        $stream_id = $stream_id ?: $this->current_stream_id;
        
        $sql = "SELECT * FROM stream_utilization WHERE stream_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $stream_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get stream selector HTML with enhanced styling
     */
    public function getStreamSelector() {
        $streams = $this->getAllStreams();
        $current_settings = $this->current_stream_settings;
        
        $html = '<div class="stream-selector d-flex align-items-center gap-2">';
        $html .= '<label for="streamSelect" class="form-label mb-0 fw-bold">Stream:</label>';
        $html .= '<select id="streamSelect" class="form-select form-select-sm" style="width: auto;" onchange="changeStream(this.value)">';
        
        foreach ($streams as $stream) {
            $selected = ($stream['id'] == $this->current_stream_id) ? 'selected' : '';
            $period = $stream['period_start'] . ' - ' . $stream['period_end'];
            $style = $stream['color_code'] ? "data-color='{$stream['color_code']}'" : '';
            
            $html .= "<option value='{$stream['id']}' {$selected} {$style}>";
            $html .= htmlspecialchars($stream['name']) . " ({$period})";
            $html .= "</option>";
        }
        
        $html .= '</select>';
        
        // Add stream badge
        $color = $current_settings['color_code'] ?? '#007bff';
        $html .= "<span class='badge ms-2' style='background-color: {$color};'>";
        $html .= htmlspecialchars($this->current_stream_name);
        if (!empty($current_settings['period_start'])) {
            $html .= "<br><small>{$current_settings['period_start']} - {$current_settings['period_end']}</small>";
        }
        $html .= "</span>";
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get assignment quality report for current stream
     */
    public function getAssignmentQualityReport() {
        $sql = "SELECT * FROM assignment_quality_monitor 
                WHERE stream_name = (SELECT name FROM streams WHERE id = ?)
                ORDER BY quality_score DESC, assignment_date DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $this->current_stream_id);
        $stmt->execute();
        return $stmt->get_result();
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
 * Helper function to add stream filter (CORRECTED - only for classes)
 */
if (!function_exists('addStreamFilter')) {
function addStreamFilter($sql, $table_alias = '') {
    $streamManager = getStreamManager();
    return $streamManager->addStreamFilter($sql, $table_alias);
}
}

/**
 * Professional assignment helper function
 */
if (!function_exists('assignCourseToClassProfessional')) {
function assignCourseToClassProfessional($class_id, $course_id, $lecturer_id = null, $semester = 'first', $academic_year = '2024/2025', $assigned_by = null) {
    $streamManager = getStreamManager();
    return $streamManager->assignCourseToClass($class_id, $course_id, $lecturer_id, $semester, $academic_year, $assigned_by);
}
}

/**
 * Get courses with professional compatibility scoring
 */
if (!function_exists('getCoursesWithCompatibility')) {
function getCoursesWithCompatibility($class_id = null) {
    $streamManager = getStreamManager();
    return $streamManager->getCoursesWithCompatibility($class_id);
}
}

/**
 * Validate class-course assignment professionally
 */
if (!function_exists('validateClassCourseAssignmentProfessional')) {
function validateClassCourseAssignmentProfessional($class_id, $course_id) {
    $streamManager = getStreamManager();
    return $streamManager->validateClassCourseAssignment($class_id, $course_id);
}
}
?>