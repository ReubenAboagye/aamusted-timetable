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
        $excludeScheduled = $options['exclude_scheduled'] ?? false;
        
        return [
            'class_courses' => $this->loadClassCourses($streamId, $academicYear, $semester, $excludeScheduled),
            'lecturer_courses' => $this->loadLecturerCourses($streamId),
            'classes' => $this->loadClasses($streamId),
            'courses' => $this->loadCourses($streamId),
            'lecturers' => $this->loadLecturers($streamId),
            'rooms' => $this->loadRooms($streamId),
            'time_slots' => $this->loadTimeSlots($streamId),
            'days' => $this->loadDays($streamId),
            'streams' => $this->loadStreams(),
            'buildings' => $this->loadBuildings(),
            'room_types' => $this->loadRoomTypes(),
            // Map of course_id => normalized room_type string (e.g., 'lecture_hall')
            'course_room_types' => $this->loadCourseRoomTypes(),
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

    private function loadClassCourses($streamId = null, $academicYear = null, $semester = null, $excludeScheduled = false) {
        $sql = "SELECT cc.id, cc.class_id, cc.course_id, cc.lecturer_id, cc.semester, cc.academic_year, cc.is_active, 
                       co.code as course_code, co.name as course_name, co.hours_per_week,
                       c.name as class_name, c.divisions_count, c.total_capacity
                FROM class_courses cc 
                JOIN classes c ON cc.class_id = c.id
                JOIN courses co ON cc.course_id = co.id";
        
        $conditions = ["cc.is_active = 1"];
        
        if ($streamId) {
            $conditions[] = "c.stream_id = " . intval($streamId);
        }
        
        if ($academicYear) {
            $conditions[] = "cc.academic_year = '" . $this->conn->real_escape_string($academicYear) . "'";
        }
        
        // Exclude already scheduled courses if requested
        if ($excludeScheduled && $semester) {
            $semesterParam = is_numeric($semester) ? (($semester == 1) ? 'first' : 'second') : $semester;
            $conditions[] = "cc.id NOT IN (
                SELECT DISTINCT t.class_course_id 
                FROM timetable t 
                WHERE t.semester = '" . $this->conn->real_escape_string($semesterParam) . "'
                AND t.is_active = 1
            )";
        }
        
        $sql .= " WHERE " . implode(" AND ", $conditions);
        
        $results = $this->fetchAll($sql);
        
        // Filter by semester if specified (based on course code)
        if ($semester && !empty($results)) {
            $originalCount = count($results);
            $results = array_filter($results, function($row) use ($semester) {
                return $this->isCourseInSemester($row['course_code'], $semester);
            });
            
            // If all courses were filtered out, provide helpful error message
            if (empty($results)) {
                $availableSemesters = [];
                foreach ($this->fetchAll($sql) as $row) {
                    $courseCode = $row['course_code'];
                    if (preg_match('/(\d{3})/', $courseCode, $matches)) {
                        $threeDigit = $matches[1];
                        $secondDigit = (int)substr($threeDigit, 1, 1);
                        $courseSemester = ($secondDigit % 2 === 1) ? 1 : 2;
                        $availableSemesters[$courseSemester] = true;
                    }
                }
                
                $availableSemesterList = array_keys($availableSemesters);
                if (!empty($availableSemesterList)) {
                    throw new Exception("No courses found for semester $semester. Available semesters: " . implode(', ', $availableSemesterList) . ". Please select the correct semester.");
                } else {
                    throw new Exception("No courses found for semester $semester. Please check your course codes and semester assignments.");
                }
            }
        }
        
        // Expand class divisions into individual class assignments
        $expandedResults = [];
        foreach ($results as $row) {
            $divisionsCount = max(1, (int)($row['divisions_count'] ?? 1));
            
            for ($i = 0; $i < $divisionsCount; $i++) {
                // Generate division label (A, B, C, ..., Z, AA, AB, etc.)
                $divisionLabel = $this->generateDivisionLabel($i);
                
                // Calculate individual class capacity
                $totalCapacity = (int)($row['total_capacity'] ?? 0);
                $baseCapacity = intdiv($totalCapacity, $divisionsCount);
                $remainder = $totalCapacity % $divisionsCount;
                $individualCapacity = $baseCapacity + ($i < $remainder ? 1 : 0);
                
                $expandedRow = $row;
                $expandedRow['division_label'] = $divisionLabel;
                $expandedRow['individual_capacity'] = $individualCapacity;
                $expandedRow['original_class_course_id'] = $row['id']; // Keep reference to original
                $expandedRow['id'] = $row['id'] . '_' . $divisionLabel; // Unique ID for each division
                
                $expandedResults[] = $expandedRow;
            }
        }

        // Duplicate courses across all divisions of a class
        $classToCourses = [];
        $expandedWithCourses = [];
        
        // Group original courses by parent class
        foreach ($expandedResults as $row) {
            $parentClassId = (int)($row['class_id'] ?? 0);
            $divisionLabel = $row['division_label'] ?? '';
            
            if ($divisionLabel === '') { // Original non-division row
                if (!isset($classToCourses[$parentClassId])) {
                    $classToCourses[$parentClassId] = [];
                }
                $classToCourses[$parentClassId][] = $row;
            }
        }

        // For each expanded division, duplicate all courses from its parent class
        foreach ($expandedResults as $row) {
            $parentClassId = (int)($row['class_id'] ?? 0);
            $divisionLabel = $row['division_label'] ?? '';
            
            if ($divisionLabel !== '' && isset($classToCourses[$parentClassId])) {
                // This is a division - duplicate all courses from parent class
                foreach ($classToCourses[$parentClassId] as $parentCourse) {
                    $dup = $row;
                    $dup['course_id'] = $parentCourse['course_id'];
                    $dup['course_code'] = $parentCourse['course_code'];
                    $dup['course_name'] = $parentCourse['course_name'];
                    $dup['hours_per_week'] = $parentCourse['hours_per_week'];
                    $dup['lecturer_id'] = $parentCourse['lecturer_id'];
                    $dup['semester'] = $parentCourse['semester'];
                    $dup['academic_year'] = $parentCourse['academic_year'];
                    $dup['id'] = $parentCourse['id'] . '_' . $divisionLabel; // Unique per course+division
                    $expandedWithCourses[] = $dup;
                }
            } else {
                // This is the original non-division row
                $expandedWithCourses[] = $row;
            }
        }
        
        return $expandedWithCourses;
    }
    
    /**
     * Generate division label for class divisions
     * @param int $index The division index (0-based)
     * @return string The division label (A, B, C, ..., Z, AA, AB, etc.)
     */
    private function generateDivisionLabel($index) {
        $label = '';
        $n = $index;
        while (true) {
            $label = chr(65 + ($n % 26)) . $label;
            $n = intdiv($n, 26) - 1;
            if ($n < 0) break;
        }
        return $label;
    }

    /**
     * Check if a course belongs to the specified semester based on course code
     * @param string $courseCode The course code
     * @param int $semester 1 for first semester, 2 for second semester
     * @return bool True if course belongs to the semester
     */
    private function isCourseInSemester($courseCode, $semester) {
        // Extract 3-digit number from course code
        if (preg_match('/(\d{3})/', $courseCode, $matches)) {
            $threeDigit = $matches[1];
            $secondDigit = (int)substr($threeDigit, 1, 1);
            
            if ($semester == 1) {
                // First semester: second digit is odd (1,3,5,7,9)
                return $secondDigit % 2 === 1;
            } else {
                // Second semester: second digit is even (2,4,6,8,0)
                return $secondDigit % 2 === 0;
            }
        }
        
        // If no 3-digit pattern found, include in both semesters
        return true;
    }

    private function loadLecturerCourses($streamId = null) {
        $sql = "SELECT lc.id, lc.lecturer_id, lc.course_id, lc.is_active 
                FROM lecturer_courses lc 
                JOIN courses c ON lc.course_id = c.id
                WHERE lc.is_active = 1 AND c.is_active = 1";
        
        if ($streamId) {
            $sql .= " AND c.stream_id = " . intval($streamId);
        }
        
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
        $sql = "SELECT id, code, name, department_id, credits, hours_per_week, is_active, stream_id 
                FROM courses WHERE is_active = 1";
        
        if ($streamId) {
            $sql .= " AND stream_id = " . intval($streamId);
        }
        
        return $this->fetchAll($sql);
    }

    private function loadLecturers($streamId = null) {
        $sql = "SELECT id, name, department_id, is_active, stream_id 
                FROM lecturers WHERE is_active = 1";
        
        if ($streamId) {
            $sql .= " AND stream_id = " . intval($streamId);
        }
        
        return $this->fetchAll($sql);
    }

    private function loadRooms($streamId = null) {
        $sql = "SELECT id, name, room_type, capacity, building_id, is_active, stream_id 
                FROM rooms WHERE is_active = 1";
        
        if ($streamId) {
            $sql .= " AND stream_id = " . intval($streamId);
        }
        
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

    private function loadDays($streamId = null) {
        if ($streamId) {
            // Get stream-specific active days
            $streamSql = "SELECT active_days FROM streams WHERE id = ?";
            $stmt = $this->conn->prepare($streamSql);
            $stmt->bind_param("i", $streamId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($streamRow = $result->fetch_assoc()) {
                $activeDaysJson = $streamRow['active_days'];
                if (!empty($activeDaysJson)) {
                    $activeDaysArray = json_decode($activeDaysJson, true);
                    if (is_array($activeDaysArray) && count($activeDaysArray) > 0) {
                        // Validate day names exist in database
                        $validDays = [];
                        foreach ($activeDaysArray as $dayName) {
                            $checkSql = "SELECT id FROM days WHERE name = ? AND is_active = 1";
                            $checkStmt = $this->conn->prepare($checkSql);
                            $checkStmt->bind_param("s", $dayName);
                            $checkStmt->execute();
                            if ($checkStmt->get_result()->num_rows > 0) {
                                $validDays[] = $dayName;
                            }
                            $checkStmt->close();
                        }
                        
                        if (!empty($validDays)) {
                            // Use stream-specific days
                            $placeholders = str_repeat('?,', count($validDays) - 1) . '?';
                            $sql = "SELECT id, name, is_active FROM days WHERE is_active = 1 AND name IN ($placeholders) ORDER BY id";
                            $stmt = $this->conn->prepare($sql);
                            $stmt->bind_param(str_repeat('s', count($validDays)), ...$validDays);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $days = [];
                            while ($row = $result->fetch_assoc()) {
                                $days[] = $row;
                            }
                            $stmt->close();
                            error_log("GA DBLoader using stream-specific days for stream $streamId: " . implode(', ', $validDays));
                            return $days;
                        }
                    }
                }
            }
            $stmt->close();
        }
        
        // Fallback to all active days
        if ($streamId) {
            error_log("GA DBLoader falling back to all days for stream $streamId");
        }
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

    /**
     * Load preferred room types for courses as a map of course_id => normalized room_type string
     */
    private function loadCourseRoomTypes(): array {
        $map = [];
        $sql = "SELECT crt.course_id, rt.name AS room_type_name
                FROM course_room_types crt
                JOIN room_types rt ON rt.id = crt.room_type_id
                WHERE crt.is_active = 1";
        $rows = $this->fetchAll($sql);
        foreach ($rows as $row) {
            $name = isset($row['room_type_name']) ? (string)$row['room_type_name'] : '';
            if ($name !== '') {
                $normalized = strtolower(str_replace(' ', '_', $name));
                $map[(int)$row['course_id']] = $normalized;
            }
        }
        return $map;
    }

    private function loadLevels() {
        return $this->fetchAll("SELECT id, name, code, description, is_active FROM levels WHERE is_active = 1");
    }

    private function loadPrograms($streamId = null) {
        $sql = "SELECT id, department_id, name, code, description, duration_years, is_active, stream_id 
                FROM programs WHERE is_active = 1";
        
        if ($streamId) {
            $sql .= " AND stream_id = " . intval($streamId);
        }
        
        return $this->fetchAll($sql);
    }

    private function loadDepartments($streamId = null) {
        $sql = "SELECT id, name, code, is_active 
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

