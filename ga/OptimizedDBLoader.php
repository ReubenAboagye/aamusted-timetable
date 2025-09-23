<?php
/**
 * Optimized Database Loader with Caching and Query Optimization
 * 
 * This class provides enhanced database loading with intelligent caching,
 * query optimization, and memory-efficient data structures.
 */

class OptimizedDBLoader extends DBLoader {
    private $cache = [];
    private $queryCache = [];
    private $preparedStatements = [];
    
    /**
     * Load all data with intelligent caching and optimization
     */
    public function loadAll(array $options = []): array {
        $cacheKey = $this->generateCacheKey($options);
        
        // Check cache first
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $streamId = $options['stream_id'] ?? null;
        $academicYear = $options['academic_year'] ?? null;
        $semester = $options['semester'] ?? null;
        
        // Load data with optimized queries
        $data = [
            'class_courses' => $this->loadClassCoursesOptimized($streamId, $academicYear, $semester),
            'lecturer_courses' => $this->loadLecturerCoursesOptimized($streamId),
            'classes' => $this->loadClassesOptimized($streamId),
            'courses' => $this->loadCoursesOptimized($streamId),
            'lecturers' => $this->loadLecturersOptimized($streamId),
            'rooms' => $this->loadRoomsOptimized($streamId),
            'time_slots' => $this->loadTimeSlotsOptimized($streamId),
            'days' => $this->loadDaysOptimized($streamId),
            'streams' => $this->loadStreamsOptimized(),
            'buildings' => $this->loadBuildingsOptimized(),
            'room_types' => $this->loadRoomTypesOptimized(),
            'course_room_types' => $this->loadCourseRoomTypesOptimized(),
            'levels' => $this->loadLevelsOptimized(),
            'programs' => $this->loadProgramsOptimized($streamId),
            'departments' => $this->loadDepartmentsOptimized($streamId)
        ];
        
        // Cache the result
        $this->cache[$cacheKey] = $data;
        
        return $data;
    }
    
    /**
     * Optimized class courses loading with single query
     */
    private function loadClassCoursesOptimized($streamId = null, $academicYear = null, $semester = null) {
        $cacheKey = "class_courses_" . ($streamId ?? 'all') . "_" . ($academicYear ?? 'all') . "_" . ($semester ?? 'all');
        
        if (isset($this->queryCache[$cacheKey])) {
            return $this->queryCache[$cacheKey];
        }
        
        // Single optimized query with all necessary joins
        $sql = "SELECT 
                    cc.id, cc.class_id, cc.course_id, cc.lecturer_id, cc.semester, cc.academic_year, cc.is_active,
                    co.code as course_code, co.name as course_name, co.hours_per_week,
                    c.name as class_name, c.divisions_count, c.total_capacity, c.stream_id,
                    l.name as lecturer_name, l.id as lecturer_id,
                    rt.name as room_type_name
                FROM class_courses cc 
                JOIN classes c ON cc.class_id = c.id
                JOIN courses co ON cc.course_id = co.id
                LEFT JOIN lecturers l ON cc.lecturer_id = l.id
                LEFT JOIN course_room_types crt ON co.id = crt.course_id AND crt.is_active = 1
                LEFT JOIN room_types rt ON crt.room_type_id = rt.id
                WHERE cc.is_active = 1";
        
        $params = [];
        $types = "";
        
        if ($streamId) {
            $sql .= " AND c.stream_id = ?";
            $params[] = $streamId;
            $types .= "i";
        }
        
        if ($academicYear) {
            $sql .= " AND cc.academic_year = ?";
            $params[] = $academicYear;
            $types .= "s";
        }
        
        $sql .= " ORDER BY c.name, co.code";
        
        $stmt = $this->prepareStatement($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $results = [];
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        
        // Filter by semester if specified
        if ($semester && !empty($results)) {
            $results = array_filter($results, function($row) use ($semester) {
                return $this->isCourseInSemester($row['course_code'], $semester);
            });
        }
        
        // Expand divisions efficiently
        $expandedResults = $this->expandDivisionsOptimized($results);
        
        $this->queryCache[$cacheKey] = $expandedResults;
        return $expandedResults;
    }
    
    /**
     * Optimized division expansion with better memory management
     */
    private function expandDivisionsOptimized(array $results): array {
        $expandedResults = [];
        $classCourseMap = [];
        
        // Group by class for efficient processing
        foreach ($results as $row) {
            $classId = (int)$row['class_id'];
            if (!isset($classCourseMap[$classId])) {
                $classCourseMap[$classId] = [];
            }
            $classCourseMap[$classId][] = $row;
        }
        
        // Process each class's divisions
        foreach ($classCourseMap as $classId => $courses) {
            $firstCourse = $courses[0];
            $divisionsCount = max(1, (int)($firstCourse['divisions_count'] ?? 1));
            $totalCapacity = (int)($firstCourse['total_capacity'] ?? 0);
            
            // Calculate capacity per division
            $baseCapacity = intdiv($totalCapacity, $divisionsCount);
            $remainder = $totalCapacity % $divisionsCount;
            
            for ($i = 0; $i < $divisionsCount; $i++) {
                $divisionLabel = $this->generateDivisionLabel($i);
                $individualCapacity = $baseCapacity + ($i < $remainder ? 1 : 0);
                
                // Create division entries for all courses
                foreach ($courses as $course) {
                    $expandedRow = $course;
                    $expandedRow['division_label'] = $divisionLabel;
                    $expandedRow['individual_capacity'] = $individualCapacity;
                    $expandedRow['original_class_course_id'] = $course['id'];
                    $expandedRow['id'] = $course['id'] . '_' . $divisionLabel;
                    
                    $expandedResults[] = $expandedRow;
                }
            }
        }
        
        return $expandedResults;
    }
    
    /**
     * Optimized rooms loading with capacity and type information
     */
    private function loadRoomsOptimized($streamId = null) {
        $cacheKey = "rooms_" . ($streamId ?? 'all');
        
        if (isset($this->queryCache[$cacheKey])) {
            return $this->queryCache[$cacheKey];
        }
        
        $sql = "SELECT r.id, r.name, r.room_type, r.capacity, r.building_id, r.is_active,
                       b.name as building_name, b.code as building_code,
                       rt.name as room_type_name, rt.description as room_type_description
                FROM rooms r
                LEFT JOIN buildings b ON r.building_id = b.id
                LEFT JOIN room_types rt ON r.room_type = rt.id
                WHERE r.is_active = 1
                ORDER BY b.name, r.name";
        
        $results = $this->fetchAllOptimized($sql);
        
        $this->queryCache[$cacheKey] = $results;
        return $results;
    }
    
    /**
     * Optimized time slots loading with stream-specific filtering
     */
    private function loadTimeSlotsOptimized($streamId = null) {
        $cacheKey = "time_slots_" . ($streamId ?? 'all');
        
        if (isset($this->queryCache[$cacheKey])) {
            return $this->queryCache[$cacheKey];
        }
        
        if ($streamId) {
            // Try stream-specific time slots first
            $sql = "SELECT ts.id, ts.start_time, ts.end_time, ts.duration, ts.is_break, ts.is_mandatory,
                           sts.is_active as stream_active
                    FROM time_slots ts 
                    JOIN stream_time_slots sts ON ts.id = sts.time_slot_id 
                    WHERE sts.stream_id = ? AND sts.is_active = 1 
                    ORDER BY ts.start_time";
            
            $stmt = $this->prepareStatement($sql);
            $stmt->bind_param("i", $streamId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $results = [];
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
            
            if (!empty($results)) {
                $this->queryCache[$cacheKey] = $results;
                return $results;
            }
        }
        
        // Fallback to mandatory time slots
        $sql = "SELECT id, start_time, end_time, duration, is_break, is_mandatory 
                FROM time_slots 
                WHERE is_mandatory = 1 
                ORDER BY start_time";
        
        $results = $this->fetchAllOptimized($sql);
        
        $this->queryCache[$cacheKey] = $results;
        return $results;
    }
    
    /**
     * Optimized fetch with prepared statement caching
     */
    private function fetchAllOptimized($sql) {
        $stmt = $this->prepareStatement($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $results = [];
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
        
        return $results;
    }
    
    /**
     * Prepare statement with caching
     */
    private function prepareStatement($sql) {
        $hash = md5($sql);
        
        if (!isset($this->preparedStatements[$hash])) {
            $this->preparedStatements[$hash] = $this->conn->prepare($sql);
        }
        
        return $this->preparedStatements[$hash];
    }
    
    /**
     * Generate cache key for options
     */
    private function generateCacheKey(array $options): string {
        return md5(serialize($options));
    }
    
    /**
     * Clear all caches
     */
    public function clearCache(): void {
        $this->cache = [];
        $this->queryCache = [];
        
        // Close prepared statements
        foreach ($this->preparedStatements as $stmt) {
            $stmt->close();
        }
        $this->preparedStatements = [];
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats(): array {
        return [
            'cache_entries' => count($this->cache),
            'query_cache_entries' => count($this->queryCache),
            'prepared_statements' => count($this->preparedStatements),
            'memory_usage' => memory_get_usage(true)
        ];
    }
}
?>

