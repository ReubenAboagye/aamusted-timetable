<?php
/**
 * Enhanced Timetable Representation for Genetic Algorithm
 * 
 * This class handles the chromosome structure and gene manipulation
 * for the genetic algorithm timetable generation.
 */

class TimetableRepresentation {
    
    /**
     * Create a random individual (chromosome) for the genetic algorithm
     * Updated to ensure every class division appears for each course it offers
     */
    public static function createRandomIndividual(array $data): array {
        $individual = [];
        
        // Group class courses by course_id to identify combinable classes
        // Now each division (ECO 100A, ECO 100B, etc.) is treated as an individual class
        $courseGroups = [];
        foreach ($data['class_courses'] as $classCourse) {
            $courseId = $classCourse['course_id'];
            if (!isset($courseGroups[$courseId])) {
                $courseGroups[$courseId] = [];
            }
            $courseGroups[$courseId][] = $classCourse;
        }
        
        // Process each course group
        foreach ($courseGroups as $courseId => $classCourses) {
            // Check if these are divisions of the same class
            $parentClassId = null;
            $isSameClass = true;
            foreach ($classCourses as $cc) {
                if ($parentClassId === null) {
                    $parentClassId = $cc['class_id'] ?? null;
                } elseif ($cc['class_id'] !== $parentClassId) {
                    $isSameClass = false;
                    break;
                }
            }

            if (count($classCourses) > 1 && $isSameClass) {
                // Divisions of same class: schedule independently, do not combine
                foreach ($classCourses as $classCourse) {
                    $individual = array_merge($individual, self::createIndividualAssignment($classCourse, $data));
                }
            } elseif (count($classCourses) > 1) {
                // Different classes: attempt to combine
                $combinedAssignment = self::createCombinedAssignment($classCourses, $data);
                if ($combinedAssignment) {
                    $individual = array_merge($individual, $combinedAssignment);
                } else {
                    // If combination is not possible, create individual assignments
                    foreach ($classCourses as $classCourse) {
                        $individual = array_merge($individual, self::createIndividualAssignment($classCourse, $data));
                    }
                }
            } else {
                // Single class division offering this course
                $classCourse = $classCourses[0];
                $individual = array_merge($individual, self::createIndividualAssignment($classCourse, $data));
            }
        }
        
        return $individual;
    }
    
    /**
     * Create assignment for a single class-course
     */
    private static function createIndividualAssignment(array $classCourse, array $data): array {
        $assignments = [];
        // Business rule: each course occurs once per week per class division => single slot
        $courseDuration = 1;
        
        // Filter rooms by preferred room type if available
        $roomsForCourse = $data['rooms'];
        if (!empty($data['course_room_types']) && isset($classCourse['course_id'])) {
            $preferredType = $data['course_room_types'][$classCourse['course_id']] ?? null;
            if ($preferredType) {
                $filtered = array_values(array_filter($roomsForCourse, function($room) use ($preferredType) {
                    return isset($room['room_type']) && strtolower((string)$room['room_type']) === $preferredType;
                }));
                if (!empty($filtered)) {
                    $roomsForCourse = $filtered;
                }
            }
        }
        
        // Create a single gene per class-course per division for the week
        for ($i = 0; $i < $courseDuration; $i++) {
            $geneKey = $classCourse['id'] . '_' . $i; // Unique key for each slot
            $assignments[$geneKey] = self::createRandomGene(
                $classCourse,
                $data['days'],
                $data['time_slots'],
                $roomsForCourse,
                $data['lecturer_courses'],
                $i // Slot index within the course
            );
        }
        
        return $assignments;
    }
    
    /**
     * Create combined assignment for multiple class divisions offering the same course
     */
    private static function createCombinedAssignment(array $classCourses, array $data): array {
        $assignments = [];
        
        // Check if combination is feasible based on room capacity
        $totalStudents = 0;
        foreach ($classCourses as $classCourse) {
            // Use individual_capacity from expanded class divisions
            $totalStudents += $classCourse['individual_capacity'] ?? self::getClassSize($classCourse['class_id']);
        }
        
        // Find rooms that can accommodate all students
        $suitableRooms = array_filter($data['rooms'], function($room) use ($totalStudents) {
            return ($room['capacity'] ?? 0) >= $totalStudents;
        });
        
        // Further filter by preferred room type if available for this course
        if (!empty($data['course_room_types'])) {
            $baseCourseId = $classCourses[0]['course_id'] ?? null;
            if ($baseCourseId) {
                $preferredType = $data['course_room_types'][$baseCourseId] ?? null;
                if ($preferredType) {
                    $filtered = array_values(array_filter($suitableRooms, function($room) use ($preferredType) {
                        return isset($room['room_type']) && strtolower((string)$room['room_type']) === $preferredType;
                    }));
                    if (!empty($filtered)) {
                        $suitableRooms = $filtered;
                    }
                }
            }
        }
        
        if (empty($suitableRooms)) {
            // No suitable room found for combination
            return [];
        }
        
        // Use the first class course as the base for the combined assignment
        $baseClassCourse = $classCourses[0];
        // Enforce once-per-week per course per division: always one period
        $courseDuration = 1;
        
        // Create combined assignment
        for ($i = 0; $i < $courseDuration; $i++) {
            $geneKey = 'combined_' . $baseClassCourse['course_id'] . '_' . $i;
            $assignments[$geneKey] = self::createRandomGene(
                $baseClassCourse,
                $data['days'],
                $data['time_slots'],
                $suitableRooms, // Use only suitable rooms
                $data['lecturer_courses'],
                $i,
                true, // Mark as combined
                $classCourses // Include all class divisions in this combination
            );
        }
        
        return $assignments;
    }
    
    /**
     * Create a random gene (timetable slot) for a class-course
     */
    public static function createRandomGene(
        array $classCourse, 
        array $days, 
        array $timeSlots, 
        array $rooms, 
        array $lecturerCourses = [],
        int $slotIndex = 0,
        bool $isCombined = false,
        array $combinedClasses = []
    ): array {
        // Validate input arrays
        if (empty($days)) {
            throw new Exception("Days array is empty");
        }
        if (empty($timeSlots)) {
            throw new Exception("Time slots array is empty");
        }
        if (empty($rooms)) {
            throw new Exception("Rooms array is empty");
        }
        
        // Find appropriate lecturer course for this class course
        $lecturerCourseId = self::findLecturerCourseId($classCourse, $lecturerCourses);
        
        // Use division label from expanded class data
        $divisionLabel = $classCourse['division_label'] ?? self::generateDivisionLabel($classCourse);
        
        // Get random day with proper error checking
        $randomDayIndex = array_rand($days);
        $randomDay = $days[$randomDayIndex];
        if (!isset($randomDay['id'])) {
            throw new Exception("Day at index $randomDayIndex missing 'id' key: " . print_r($randomDay, true));
        }
        
        // Get random time slot with proper error checking
        // For consecutive slots, ensure we don't cross break slots
        $courseDuration = $classCourse['hours_per_week'] ?? 1;
        $maxStartIndex = count($timeSlots) - $courseDuration;
        if ($maxStartIndex < 0) {
            throw new Exception("Not enough time slots available for course duration $courseDuration");
        }
        
        // Build list of valid start indices where none of the consecutive slots are break slots
        $validStartIndices = [];
        for ($idx = 0; $idx <= $maxStartIndex; $idx++) {
            $ok = true;
            for ($k = 0; $k < $courseDuration; $k++) {
                $slot = $timeSlots[$idx + $k];
                if (!empty($slot['is_break'])) { $ok = false; break; }
            }
            if ($ok) { $validStartIndices[] = $idx; }
        }
        if (empty($validStartIndices)) {
            // Fallback: if no continuous non-break window exists, allow any non-break start
            $nonBreakStarts = [];
            for ($idx = 0; $idx <= $maxStartIndex; $idx++) {
                if (empty($timeSlots[$idx]['is_break'])) { $nonBreakStarts[] = $idx; }
            }
            $validStartIndices = !empty($nonBreakStarts) ? $nonBreakStarts : range(0, $maxStartIndex);
        }
        
        if ($slotIndex === 0) {
            // Choose a valid start index
            $randomTimeSlotIndex = $validStartIndices[array_rand($validStartIndices)];
        } else {
            // Subsequent slots are not used directly in final conversion; keep random to maintain diversity
            $randomTimeSlotIndex = array_rand($timeSlots);
        }
        
        $randomTimeSlot = $timeSlots[$randomTimeSlotIndex];
        if (!isset($randomTimeSlot['id'])) {
            throw new Exception("Time slot at index $randomTimeSlotIndex missing 'id' key: " . print_r($randomTimeSlot, true));
        }
        
        // Get random room with proper error checking
        $randomRoomIndex = array_rand($rooms);
        $randomRoom = $rooms[$randomRoomIndex];
        if (!isset($randomRoom['id'])) {
            throw new Exception("Room at index $randomRoomIndex missing 'id' key: " . print_r($randomRoom, true));
        }
        
        // Validate class course has required fields (handle both database records and genes)
        if (!isset($classCourse['id']) && !isset($classCourse['class_course_id'])) {
            throw new Exception("Class course missing both 'id' and 'class_course_id' keys: " . print_r($classCourse, true));
        }
        if (!isset($classCourse['class_id'])) {
            throw new Exception("Class course missing 'class_id' key: " . print_r($classCourse, true));
        }
        if (!isset($classCourse['course_id'])) {
            throw new Exception("Class course missing 'course_id' key: " . print_r($classCourse, true));
        }
        
        return [
            // Preserve DB class_course id for FK while keeping a unique division key
            'class_course_id' => (int)($classCourse['original_class_course_id'] ?? $classCourse['class_course_id'] ?? (is_numeric($classCourse['id'] ?? null) ? $classCourse['id'] : 0)),
            'division_key' => (string)($classCourse['id'] ?? ($classCourse['class_course_id'] ?? '')),
            'class_id' => (int)$classCourse['class_id'],
            'course_id' => (int)$classCourse['course_id'],
            'lecturer_id' => isset($classCourse['lecturer_id']) ? (int)$classCourse['lecturer_id'] : null,
            'lecturer_course_id' => $lecturerCourseId,
            'day_id' => $randomDay['id'],
            'time_slot_id' => $randomTimeSlot['id'],
            'room_id' => $randomRoom['id'],
            'division_label' => $divisionLabel,
            'semester' => $classCourse['semester'] ?? 'first',
            'academic_year' => $classCourse['academic_year'] ?? null,
            'slot_index' => $slotIndex,
            'course_duration' => $courseDuration,
            'is_combined' => $isCombined,
            'combined_classes' => $combinedClasses // Store combined classes
        ];
    }
    
    /**
     * Find an appropriate lecturer course for a class course
     */
    private static function findLecturerCourseId(array $classCourse, array $lecturerCourses): int {
        // If class course already has a lecturer_id, find matching lecturer course
        if (isset($classCourse['lecturer_id']) && $classCourse['lecturer_id']) {
            foreach ($lecturerCourses as $lecturerCourse) {
                if (isset($lecturerCourse['id']) && $lecturerCourse['lecturer_id'] == $classCourse['lecturer_id'] && 
                    $lecturerCourse['course_id'] == $classCourse['course_id']) {
                    return (int)$lecturerCourse['id'];
                }
            }
        }
        
        // Otherwise, find any lecturer course for this course
        foreach ($lecturerCourses as $lecturerCourse) {
            if (isset($lecturerCourse['id']) && $lecturerCourse['course_id'] == $classCourse['course_id']) {
                return (int)$lecturerCourse['id'];
            }
        }
        
        // If no lecturer course found, return the first available one or a default value
        if (!empty($lecturerCourses)) {
            return (int)$lecturerCourses[0]['id'];
        }
        
        // Return a default value (this should not happen in practice)
        return 1;
    }
    
    /**
     * Generate division label for classes with multiple divisions
     */
    private static function generateDivisionLabel($classCourse): ?string {
        // If classCourse is an array, try to extract division info
        if (is_array($classCourse)) {
            // Check if division_label is already set
            if (isset($classCourse['division_label'])) {
                return $classCourse['division_label'];
            }
            
            // Check if divisions_count is available
            if (isset($classCourse['divisions_count']) && $classCourse['divisions_count'] > 1) {
                // This would need more context to generate the specific division
                // For now, return null and let the DBLoader handle it
                return null;
            }
        }
        
        // Default: no division
        return null;
    }
    
    /**
     * Create an empty genome template
     */
    public static function createEmptyGenome(array $classCourses): array {
        $genome = [];
        
        foreach ($classCourses as $classCourse) {
            $genome[$classCourse['id']] = [
                'class_course_id' => (int)$classCourse['id'],
                'class_id' => (int)$classCourse['class_id'],
                'course_id' => (int)$classCourse['course_id'],
                'lecturer_id' => isset($classCourse['lecturer_id']) ? (int)$classCourse['lecturer_id'] : null,
                'lecturer_course_id' => null,
                'day_id' => null,
                'time_slot_id' => null,
                'room_id' => null,
                'division_label' => null,
                'semester' => $classCourse['semester'] ?? 'first',
                'academic_year' => $classCourse['academic_year'] ?? null
            ];
        }
        
        return $genome;
    }
    
    /**
     * Validate a gene structure
     */
    public static function validateGene(array $gene): bool {
        $requiredFields = [
            'class_course_id', 'class_id', 'course_id', 
            'day_id', 'time_slot_id', 'room_id'
        ];
        
        foreach ($requiredFields as $field) {
            if (!isset($gene[$field]) || $gene[$field] === null) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Clone a gene with new time/room assignment
     */
    public static function cloneGeneWithNewAssignment(
        array $gene, 
        array $days, 
        array $timeSlots, 
        array $rooms
    ): array {
        // Validate input arrays
        if (empty($days)) {
            throw new Exception("Days array is empty");
        }
        if (empty($timeSlots)) {
            throw new Exception("Time slots array is empty");
        }
        if (empty($rooms)) {
            throw new Exception("Rooms array is empty");
        }
        
        $newGene = $gene;
        
        // Get random day with proper error checking
        $randomDayIndex = array_rand($days);
        $randomDay = $days[$randomDayIndex];
        if (!isset($randomDay['id'])) {
            throw new Exception("Day at index $randomDayIndex missing 'id' key: " . print_r($randomDay, true));
        }
        
        // Get random time slot with proper error checking (avoid break slots)
        $nonBreakSlots = array_values(array_filter($timeSlots, function($s){ return empty($s['is_break']); }));
        $pool = !empty($nonBreakSlots) ? $nonBreakSlots : $timeSlots;
        $randomTimeSlotIndex = array_rand($pool);
        $randomTimeSlot = $pool[$randomTimeSlotIndex];
        if (!isset($randomTimeSlot['id'])) {
            throw new Exception("Time slot at index $randomTimeSlotIndex missing 'id' key: " . print_r($randomTimeSlot, true));
        }
        
        // Get random room with proper error checking
        $randomRoomIndex = array_rand($rooms);
        $randomRoom = $rooms[$randomRoomIndex];
        if (!isset($randomRoom['id'])) {
            throw new Exception("Room at index $randomRoomIndex missing 'id' key: " . print_r($randomRoom, true));
        }
        
        $newGene['day_id'] = $randomDay['id'];
        $newGene['time_slot_id'] = $randomTimeSlot['id'];
        $newGene['room_id'] = $randomRoom['id'];
        
        return $newGene;
    }
    
    /**
     * Get gene key for conflict checking
     */
    public static function getGeneKey(array $gene): string {
        return $gene['day_id'] . '|' . $gene['time_slot_id'];
    }
    
    /**
     * Get room conflict key
     */
    public static function getRoomConflictKey(array $gene): string {
        return $gene['room_id'] . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
    }
    
    /**
     * Get lecturer conflict key
     */
    public static function getLecturerConflictKey(array $gene): string {
        $lecturerId = $gene['lecturer_course_id'] ?? $gene['lecturer_id'];
        return $lecturerId . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
    }
    
    /**
     * Get class conflict key
     */
    public static function getClassConflictKey(array $gene): string {
        $key = $gene['class_id'] . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
        if ($gene['division_label']) {
            $key .= '|' . $gene['division_label'];
        }
        return $key;
    }
    
    /**
     * Check if two genes conflict
     */
    public static function genesConflict(array $gene1, array $gene2): bool {
        // Same time slot and day
        if ($gene1['day_id'] == $gene2['day_id'] && 
            $gene1['time_slot_id'] == $gene2['time_slot_id']) {
            
            // Room conflict
            if ($gene1['room_id'] == $gene2['room_id']) {
                return true;
            }
            
            // Lecturer conflict
            $lecturer1 = $gene1['lecturer_course_id'] ?? $gene1['lecturer_id'];
            $lecturer2 = $gene2['lecturer_course_id'] ?? $gene2['lecturer_id'];
            if ($lecturer1 && $lecturer2 && $lecturer1 == $lecturer2) {
                return true;
            }
            
            // Class conflict (same class cannot have multiple courses at same time)
            if ($gene1['class_id'] == $gene2['class_id']) {
                $div1 = isset($gene1['division_label']) ? (string)$gene1['division_label'] : '';
                $div2 = isset($gene2['division_label']) ? (string)$gene2['division_label'] : '';
                // If both have no division label, they conflict
                if ($div1 === '' && $div2 === '') {
                    return true;
                }
                // If both have division labels and they're equal, they conflict
                if ($div1 !== '' && $div2 !== '' && $div1 === $div2) {
                    return true;
                }
                // If division labels differ, they are different divisions and do not conflict
            }
        }
        
        return false;
    }
    
    /**
     * Get gene information for debugging
     */
    public static function getGeneInfo(array $gene, array $data): array {
        $info = [
            'class_course_id' => $gene['class_course_id'],
            'day_id' => $gene['day_id'],
            'time_slot_id' => $gene['time_slot_id'],
            'room_id' => $gene['room_id']
        ];
        
        // Add readable names if available
        foreach ($data['days'] as $day) {
            if ($day['id'] == $gene['day_id']) {
                $info['day_name'] = $day['name'];
                break;
            }
        }
        
        foreach ($data['time_slots'] as $timeSlot) {
            if ($timeSlot['id'] == $gene['time_slot_id']) {
                $info['time_slot'] = $timeSlot['start_time'] . '-' . $timeSlot['end_time'];
                break;
            }
        }
        
        foreach ($data['rooms'] as $room) {
            if ($room['id'] == $gene['room_id']) {
                $info['room_name'] = $room['name'];
                break;
            }
        }
        
        return $info;
    }

    /**
     * Get the size (number of students) for a class division
     */
    private static function getClassSize($classId) {
        // For expanded class divisions, the individual_capacity is already calculated
        // This method is called with the class_id from the expanded class course data
        // The individual_capacity should be available in the class course data
        return 50; // Default fallback - this should be overridden by individual_capacity
    }
}
?>
