<?php
/**
 * Enhanced Timetable Representation for Genetic Algorithm
 * 
 * This class handles the chromosome structure and gene manipulation
 * for the genetic algorithm timetable generation. It provides comprehensive
 * functionality for creating, validating, and manipulating timetable genes
 * that represent individual class-course assignments.
 * 
 * @package TimetableGA
 * @author Timetable System
 * @version 2.0
 * @since 1.0
 * 
 * @example
 * // Create a random individual for genetic algorithm
 * $data = [
 *     'class_courses' => [...],
 *     'days' => [...],
 *     'time_slots' => [...],
 *     'rooms' => [...],
 *     'lecturer_courses' => [...]
 * ];
 * $individual = TimetableRepresentation::createRandomIndividual($data);
 * 
 * @example
 * // Validate a gene structure
 * $isValid = TimetableRepresentation::validateGene($gene);
 * 
 * @example
 * // Check for conflicts between two genes
 * $hasConflict = TimetableRepresentation::genesConflict($gene1, $gene2, $data);
 */

/**
 * Custom exception for timetable representation errors
 */
class TimetableRepresentationException extends Exception {
    protected $context = [];
    
    public function __construct($message = "", $code = 0, Exception $previous = null, array $context = []) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }
    
    public function getContext(): array {
        return $this->context;
    }
    
    public function getFormattedMessage(): string {
        $message = $this->getMessage();
        if (!empty($this->context)) {
            $message .= "\nContext: " . json_encode($this->context, JSON_PRETTY_PRINT);
        }
        return $message;
    }
}

/**
 * Exception for data validation errors
 */
class TimetableDataValidationException extends TimetableRepresentationException {
    protected $validationErrors = [];
    
    public function __construct($message = "", $code = 0, Exception $previous = null, array $validationErrors = []) {
        parent::__construct($message, $code, $previous);
        $this->validationErrors = $validationErrors;
    }
    
    public function getValidationErrors(): array {
        return $this->validationErrors;
    }
}

/**
 * Exception for gene creation errors
 */
class TimetableGeneCreationException extends TimetableRepresentationException {
    protected $geneData = [];
    
    public function __construct($message = "", $code = 0, Exception $previous = null, array $geneData = []) {
        parent::__construct($message, $code, $previous);
        $this->geneData = $geneData;
    }
    
    public function getGeneData(): array {
        return $this->geneData;
    }
}

class TimetableRepresentation {
    
    /**
     * Create a random individual (chromosome) for the genetic algorithm
     * 
     * This method generates a complete timetable individual by creating random
     * assignments for all class-course combinations. It handles both individual
     * class divisions and combined classes where multiple divisions can share
     * the same time slot and room.
     * 
     * @param array $data The input data containing:
     *   - class_courses: Array of class-course relationships
     *   - days: Available days for scheduling
     *   - time_slots: Available time slots
     *   - rooms: Available rooms with capacity information
     *   - lecturer_courses: Lecturer-course assignments
     *   - course_room_types: Preferred room types for courses
     * 
     * @return array An array of genes representing the complete timetable individual
     * 
     * @throws Exception If required data is missing or invalid
     * 
     * @example
     * $individual = TimetableRepresentation::createRandomIndividual($data);
     * foreach ($individual as $geneKey => $gene) {
     *     echo "Gene: $geneKey, Class: {$gene['class_id']}, Course: {$gene['course_id']}\n";
     * }
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

            if (count($classCourses) > 1) {
                // Multiple classes/divisions: attempt to combine (same class divisions or different classes)
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
        
        // Group classes by lecturer to ensure same lecturer for combinations
        $lecturerGroups = [];
        foreach ($classCourses as $classCourse) {
            $lecturerId = $classCourse['lecturer_id'] ?? null;
            if ($lecturerId) {
                if (!isset($lecturerGroups[$lecturerId])) {
                    $lecturerGroups[$lecturerId] = [];
                }
                $lecturerGroups[$lecturerId][] = $classCourse;
            }
        }
        
        // Process each lecturer group separately
        foreach ($lecturerGroups as $lecturerId => $lecturerClasses) {
            if (count($lecturerClasses) > 1) {
                // Try to combine classes with same lecturer
                $combinations = self::createLecturerCombinations($lecturerClasses, $data);
                $assignments = array_merge($assignments, $combinations);
            } else {
                // Single class for this lecturer, create individual assignment
                $assignments = array_merge($assignments, self::createIndividualAssignment($lecturerClasses[0], $data));
            }
        }
        
        // Handle any remaining classes without lecturer assignments
        $unassignedClasses = array_filter($classCourses, function($classCourse) {
            return !isset($classCourse['lecturer_id']) || !$classCourse['lecturer_id'];
        });
        
        foreach ($unassignedClasses as $classCourse) {
            $assignments = array_merge($assignments, self::createIndividualAssignment($classCourse, $data));
        }
        
        return $assignments;
    }
    
    /**
     * Create combinations for classes with same lecturer
     */
    private static function createLecturerCombinations(array $lecturerClasses, array $data): array {
        $assignments = [];
        $remainingClasses = $lecturerClasses;
        
        // Sort classes by level and size for better combination opportunities
        usort($remainingClasses, function($a, $b) {
            // First sort by class level (same level classes together)
            $levelA = $a['class_name'] ?? '';
            $levelB = $b['class_name'] ?? '';
            if ($levelA !== $levelB) {
                return strcmp($levelA, $levelB);
            }
            
            // Then by size (smallest first)
            $sizeA = $a['individual_capacity'] ?? self::getClassSize($a['class_id']);
            $sizeB = $b['individual_capacity'] ?? self::getClassSize($b['class_id']);
            return $sizeA - $sizeB;
        });
        
        while (!empty($remainingClasses)) {
            $currentGroup = [];
            $currentTotal = 0;
            $processedKeys = [];
            $attendanceFactor = 0.7; // Assume 70% attendance rate
            
            // Try to form a group that can fit in available rooms
            foreach ($remainingClasses as $key => $classCourse) {
                $classSize = $classCourse['individual_capacity'] ?? self::getClassSize($classCourse['class_id']);
                
                // Check if we can add this class to the current group
                // Apply attendance factor: allow over-capacity bookings
                $newTotal = $currentTotal + $classSize;
                $effectiveCapacity = $newTotal * $attendanceFactor; // 70% of total students
                
                $suitableRooms = array_filter($data['rooms'], function($room) use ($effectiveCapacity) {
                    return ($room['capacity'] ?? 0) >= $effectiveCapacity;
                });
                
                if (!empty($suitableRooms)) {
                    $currentGroup[] = $classCourse;
                    $currentTotal = $newTotal;
                    $processedKeys[] = $key;
                }
            }
            
            // Remove processed classes from the list
            foreach ($processedKeys as $key) {
                unset($remainingClasses[$key]);
            }
            
            // Safety check to prevent infinite loop
            if (empty($currentGroup)) {
                // If no group could be formed, process remaining classes individually
                foreach ($remainingClasses as $classCourse) {
                    $assignments = array_merge($assignments, self::createIndividualAssignment($classCourse, $data));
                }
                break;
            }
            
            if (count($currentGroup) > 1) {
                // Create combined assignment for this group
                $combinedAssignment = self::createCombinedAssignmentForGroup($currentGroup, $data);
                if (!empty($combinedAssignment)) {
                    $assignments = array_merge($assignments, $combinedAssignment);
                } else {
                    // If combination fails, add individual assignments
                    foreach ($currentGroup as $classCourse) {
                        $assignments = array_merge($assignments, self::createIndividualAssignment($classCourse, $data));
                    }
                }
            } else {
                // Single class, create individual assignment
                $assignments = array_merge($assignments, self::createIndividualAssignment($currentGroup[0], $data));
            }
        }
        
        return $assignments;
    }
    
    /**
     * Create combined assignment for a group of classes
     */
    private static function createCombinedAssignmentForGroup(array $classGroup, array $data): array {
        $assignments = [];
        
        // Calculate total students for this group
        $totalStudents = 0;
        foreach ($classGroup as $classCourse) {
            $totalStudents += $classCourse['individual_capacity'] ?? self::getClassSize($classCourse['class_id']);
        }
        
        // Apply attendance factor for flexible room capacity
        $attendanceFactor = 0.7; // Assume 70% attendance rate
        $effectiveCapacity = $totalStudents * $attendanceFactor;
        
        // Find rooms that can accommodate this group (with attendance factor)
        $suitableRooms = array_filter($data['rooms'], function($room) use ($effectiveCapacity) {
            return ($room['capacity'] ?? 0) >= $effectiveCapacity;
        });
        
        if (empty($suitableRooms)) {
            return [];
        }
        
        // Use the first class course as the base for the combined assignment
        $baseClassCourse = $classGroup[0];
        $courseDuration = 1;
        
        // Create combined assignment
        for ($i = 0; $i < $courseDuration; $i++) {
            $geneKey = 'combined_' . $baseClassCourse['course_id'] . '_' . $i;
            $assignments[$geneKey] = self::createRandomGene(
                $baseClassCourse,
                $data['days'],
                $data['time_slots'],
                $suitableRooms,
                $data['lecturer_courses'],
                $i,
                true, // Mark as combined
                $classGroup // Include all classes in this combination
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
            throw new TimetableRepresentationException(
                "Days array is empty - cannot create gene without available days",
                1001,
                null,
                ['days_count' => count($days), 'class_course' => $classCourse]
            );
        }
        if (empty($timeSlots)) {
            throw new TimetableRepresentationException(
                "Time slots array is empty - cannot create gene without available time slots",
                1002,
                null,
                ['time_slots_count' => count($timeSlots), 'class_course' => $classCourse]
            );
        }
        if (empty($rooms)) {
            throw new TimetableRepresentationException(
                "Rooms array is empty - cannot create gene without available rooms",
                1003,
                null,
                ['rooms_count' => count($rooms), 'class_course' => $classCourse]
            );
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
    public static function getLecturerConflictKey(array $gene, array $data = null): string {
        $lecturerId = $gene['lecturer_id'] ?? null;
        
        // If lecturer_id is not directly available, resolve it from lecturer_course_id
        if (!$lecturerId && isset($gene['lecturer_course_id']) && $data) {
            $lecturerCourseId = $gene['lecturer_course_id'];
            foreach ($data['lecturer_courses'] as $lc) {
                if ($lc['id'] == $lecturerCourseId) {
                    $lecturerId = $lc['lecturer_id'];
                    break;
                }
            }
        }
        
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
    public static function genesConflict(array $gene1, array $gene2, array $data = null): bool {
        // Same time slot and day
        if ($gene1['day_id'] == $gene2['day_id'] && 
            $gene1['time_slot_id'] == $gene2['time_slot_id']) {
            
            // Room conflict
            if ($gene1['room_id'] == $gene2['room_id']) {
                return true;
            }
            
            // Lecturer conflict - resolve actual lecturer IDs
            $lecturer1 = $gene1['lecturer_id'] ?? null;
            $lecturer2 = $gene2['lecturer_id'] ?? null;
            
            // If lecturer_id is not directly available, resolve it from lecturer_course_id
            if (!$lecturer1 && isset($gene1['lecturer_course_id']) && $data) {
                $lecturerCourseId = $gene1['lecturer_course_id'];
                foreach ($data['lecturer_courses'] as $lc) {
                    if ($lc['id'] == $lecturerCourseId) {
                        $lecturer1 = $lc['lecturer_id'];
                        break;
                    }
                }
            }
            
            if (!$lecturer2 && isset($gene2['lecturer_course_id']) && $data) {
                $lecturerCourseId = $gene2['lecturer_course_id'];
                foreach ($data['lecturer_courses'] as $lc) {
                    if ($lc['id'] == $lecturerCourseId) {
                        $lecturer2 = $lc['lecturer_id'];
                        break;
                    }
                }
            }
            
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
    
    /**
     * Analyze timetable individual for conflicts and issues
     * 
     * This method provides comprehensive analysis of a timetable individual,
     * identifying conflicts, constraint violations, and optimization opportunities.
     * 
     * @param array $individual The timetable individual to analyze
     * @param array $data The data context for analysis
     * @return array Analysis results with conflict counts and details
     * 
     * @example
     * $analysis = TimetableRepresentation::analyzeIndividual($individual, $data);
     * echo "Total conflicts: {$analysis['total_conflicts']}\n";
     * echo "Room conflicts: {$analysis['room_conflicts']}\n";
     * echo "Lecturer conflicts: {$analysis['lecturer_conflicts']}\n";
     */
    public static function analyzeIndividual(array $individual, array $data): array {
        $analysis = [
            'total_genes' => count($individual),
            'total_conflicts' => 0,
            'room_conflicts' => 0,
            'lecturer_conflicts' => 0,
            'class_conflicts' => 0,
            'constraint_violations' => 0,
            'conflict_details' => [],
            'room_utilization' => [],
            'lecturer_workload' => [],
            'time_slot_usage' => []
        ];
        
        $genes = array_values($individual);
        $conflictKeys = [];
        
        // Check for conflicts between all gene pairs
        for ($i = 0; $i < count($genes); $i++) {
            for ($j = $i + 1; $j < count($genes); $j++) {
                if (self::genesConflict($genes[$i], $genes[$j], $data)) {
                    $analysis['total_conflicts']++;
                    
                    // Determine conflict type
                    $conflictType = self::getConflictType($genes[$i], $genes[$j], $data);
                    $analysis[$conflictType . '_conflicts']++;
                    
                    $analysis['conflict_details'][] = [
                        'gene1' => $genes[$i],
                        'gene2' => $genes[$j],
                        'type' => $conflictType,
                        'time_slot' => $genes[$i]['day_id'] . '|' . $genes[$i]['time_slot_id']
                    ];
                }
            }
        }
        
        // Analyze room utilization
        foreach ($individual as $gene) {
            $roomKey = $gene['room_id'];
            if (!isset($analysis['room_utilization'][$roomKey])) {
                $analysis['room_utilization'][$roomKey] = 0;
            }
            $analysis['room_utilization'][$roomKey]++;
        }
        
        // Analyze lecturer workload
        foreach ($individual as $gene) {
            if ($gene['lecturer_id']) {
                $lecturerKey = $gene['lecturer_id'];
                if (!isset($analysis['lecturer_workload'][$lecturerKey])) {
                    $analysis['lecturer_workload'][$lecturerKey] = 0;
                }
                $analysis['lecturer_workload'][$lecturerKey]++;
            }
        }
        
        // Analyze time slot usage
        foreach ($individual as $gene) {
            $timeKey = $gene['day_id'] . '|' . $gene['time_slot_id'];
            if (!isset($analysis['time_slot_usage'][$timeKey])) {
                $analysis['time_slot_usage'][$timeKey] = 0;
            }
            $analysis['time_slot_usage'][$timeKey]++;
        }
        
        return $analysis;
    }
    
    /**
     * Get the type of conflict between two genes
     * 
     * @param array $gene1 First gene
     * @param array $gene2 Second gene
     * @param array $data Data context
     * @return string Conflict type ('room', 'lecturer', or 'class')
     */
    private static function getConflictType(array $gene1, array $gene2, array $data): string {
        if ($gene1['day_id'] == $gene2['day_id'] && $gene1['time_slot_id'] == $gene2['time_slot_id']) {
            if ($gene1['room_id'] == $gene2['room_id']) {
                return 'room';
            }
            
            // Check lecturer conflict
            $lecturer1 = $gene1['lecturer_id'] ?? null;
            $lecturer2 = $gene2['lecturer_id'] ?? null;
            
            if (!$lecturer1 && isset($gene1['lecturer_course_id']) && $data) {
                foreach ($data['lecturer_courses'] as $lc) {
                    if ($lc['id'] == $gene1['lecturer_course_id']) {
                        $lecturer1 = $lc['lecturer_id'];
                        break;
                    }
                }
            }
            
            if (!$lecturer2 && isset($gene2['lecturer_course_id']) && $data) {
                foreach ($data['lecturer_courses'] as $lc) {
                    if ($lc['id'] == $gene2['lecturer_course_id']) {
                        $lecturer2 = $lc['lecturer_id'];
                        break;
                    }
                }
            }
            
            if ($lecturer1 && $lecturer2 && $lecturer1 == $lecturer2) {
                return 'lecturer';
            }
            
            if ($gene1['class_id'] == $gene2['class_id']) {
                return 'class';
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Get statistics about a timetable individual
     * 
     * @param array $individual The timetable individual
     * @param array $data The data context
     * @return array Statistical information
     */
    public static function getIndividualStats(array $individual, array $data): array {
        $stats = [
            'total_assignments' => count($individual),
            'unique_classes' => 0,
            'unique_courses' => 0,
            'unique_lecturers' => 0,
            'unique_rooms' => 0,
            'time_slots_used' => 0,
            'days_used' => 0,
            'room_utilization_rate' => 0,
            'lecturer_utilization_rate' => 0
        ];
        
        $classes = [];
        $courses = [];
        $lecturers = [];
        $rooms = [];
        $timeSlots = [];
        $days = [];
        
        foreach ($individual as $gene) {
            $classes[$gene['class_id']] = true;
            $courses[$gene['course_id']] = true;
            $rooms[$gene['room_id']] = true;
            $timeSlots[$gene['time_slot_id']] = true;
            $days[$gene['day_id']] = true;
            
            if ($gene['lecturer_id']) {
                $lecturers[$gene['lecturer_id']] = true;
            }
        }
        
        $stats['unique_classes'] = count($classes);
        $stats['unique_courses'] = count($courses);
        $stats['unique_lecturers'] = count($lecturers);
        $stats['unique_rooms'] = count($rooms);
        $stats['time_slots_used'] = count($timeSlots);
        $stats['days_used'] = count($days);
        
        // Calculate utilization rates
        if (!empty($data['rooms'])) {
            $stats['room_utilization_rate'] = count($rooms) / count($data['rooms']) * 100;
        }
        
        if (!empty($data['lecturer_courses'])) {
            $uniqueLecturers = array_unique(array_column($data['lecturer_courses'], 'lecturer_id'));
            $stats['lecturer_utilization_rate'] = count($lecturers) / count($uniqueLecturers) * 100;
        }
        
        return $stats;
    }
    
    /**
     * Find genes that can be optimized (moved to better time slots)
     * 
     * @param array $individual The timetable individual
     * @param array $data The data context
     * @return array Array of genes that could be optimized
     */
    public static function findOptimizableGenes(array $individual, array $data): array {
        $optimizable = [];
        
        foreach ($individual as $geneKey => $gene) {
            // Check if gene is in a break slot
            foreach ($data['time_slots'] as $timeSlot) {
                if ($timeSlot['id'] == $gene['time_slot_id'] && !empty($timeSlot['is_break'])) {
                    $optimizable[] = [
                        'gene_key' => $geneKey,
                        'gene' => $gene,
                        'reason' => 'scheduled_in_break_slot',
                        'priority' => 'high'
                    ];
                    break;
                }
            }
            
            // Check for room capacity issues
            foreach ($data['rooms'] as $room) {
                if ($room['id'] == $gene['room_id']) {
                    $classSize = $gene['course_duration'] ?? 1;
                    if ($room['capacity'] < $classSize * 10) { // Assuming 10 students per hour
                        $optimizable[] = [
                            'gene_key' => $geneKey,
                            'gene' => $gene,
                            'reason' => 'room_capacity_issue',
                            'priority' => 'medium'
                        ];
                    }
                    break;
                }
            }
        }
        
        return $optimizable;
    }
    
    /**
     * Generate a summary report for a timetable individual
     * 
     * @param array $individual The timetable individual
     * @param array $data The data context
     * @return string Human-readable summary report
     */
    public static function generateSummaryReport(array $individual, array $data): string {
        $analysis = self::analyzeIndividual($individual, $data);
        $stats = self::getIndividualStats($individual, $data);
        
        $report = "=== TIMETABLE INDIVIDUAL SUMMARY REPORT ===\n\n";
        $report .= "Basic Statistics:\n";
        $report .= "- Total assignments: {$stats['total_assignments']}\n";
        $report .= "- Unique classes: {$stats['unique_classes']}\n";
        $report .= "- Unique courses: {$stats['unique_courses']}\n";
        $report .= "- Unique lecturers: {$stats['unique_lecturers']}\n";
        $report .= "- Unique rooms: {$stats['unique_rooms']}\n";
        $report .= "- Time slots used: {$stats['time_slots_used']}\n";
        $report .= "- Days used: {$stats['days_used']}\n\n";
        
        $report .= "Conflict Analysis:\n";
        $report .= "- Total conflicts: {$analysis['total_conflicts']}\n";
        $report .= "- Room conflicts: {$analysis['room_conflicts']}\n";
        $report .= "- Lecturer conflicts: {$analysis['lecturer_conflicts']}\n";
        $report .= "- Class conflicts: {$analysis['class_conflicts']}\n\n";
        
        $report .= "Utilization Rates:\n";
        $report .= "- Room utilization: " . number_format($stats['room_utilization_rate'], 2) . "%\n";
        $report .= "- Lecturer utilization: " . number_format($stats['lecturer_utilization_rate'], 2) . "%\n\n";
        
        if ($analysis['total_conflicts'] > 0) {
            $report .= "Conflict Details:\n";
            foreach ($analysis['conflict_details'] as $i => $conflict) {
                $report .= ($i + 1) . ". {$conflict['type']} conflict at time slot {$conflict['time_slot']}\n";
            }
        }
        
        return $report;
    }
    
    /**
     * Validate data structure before creating individuals
     * 
     * @param array $data The data to validate
     * @return array Validation results with errors and warnings
     */
    public static function validateDataStructure(array $data): array {
        $validation = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        $requiredKeys = ['class_courses', 'days', 'time_slots', 'rooms', 'lecturer_courses'];
        
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key]) || !is_array($data[$key]) || empty($data[$key])) {
                $validation['errors'][] = "Missing or empty required data: $key";
                $validation['is_valid'] = false;
            }
        }
        
        // Validate class_courses structure
        if (isset($data['class_courses'])) {
            foreach ($data['class_courses'] as $i => $classCourse) {
                $requiredFields = ['id', 'class_id', 'course_id'];
                foreach ($requiredFields as $field) {
                    if (!isset($classCourse[$field])) {
                        $validation['errors'][] = "Class course at index $i missing required field: $field";
                        $validation['is_valid'] = false;
                    }
                }
            }
        }
        
        // Validate days structure
        if (isset($data['days'])) {
            foreach ($data['days'] as $i => $day) {
                if (!isset($day['id']) || !isset($day['name'])) {
                    $validation['errors'][] = "Day at index $i missing required fields: id or name";
                    $validation['is_valid'] = false;
                }
            }
        }
        
        // Validate time_slots structure
        if (isset($data['time_slots'])) {
            foreach ($data['time_slots'] as $i => $timeSlot) {
                if (!isset($timeSlot['id']) || !isset($timeSlot['start_time']) || !isset($timeSlot['end_time'])) {
                    $validation['errors'][] = "Time slot at index $i missing required fields: id, start_time, or end_time";
                    $validation['is_valid'] = false;
                }
            }
        }
        
        // Validate rooms structure
        if (isset($data['rooms'])) {
            foreach ($data['rooms'] as $i => $room) {
                if (!isset($room['id']) || !isset($room['name']) || !isset($room['capacity'])) {
                    $validation['errors'][] = "Room at index $i missing required fields: id, name, or capacity";
                    $validation['is_valid'] = false;
                }
            }
        }
        
        return $validation;
    }
    
    /**
     * Cache for frequently accessed data to improve performance
     */
    private static $cache = [];
    
    /**
     * Clear the internal cache
     */
    public static function clearCache(): void {
        self::$cache = [];
    }
    
    /**
     * Get cached data or compute and cache it
     * 
     * @param string $key Cache key
     * @param callable $computeFunction Function to compute the value if not cached
     * @return mixed Cached or computed value
     */
    private static function getCached(string $key, callable $computeFunction) {
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = $computeFunction();
        }
        return self::$cache[$key];
    }
    
    /**
     * Optimized conflict checking with caching
     * 
     * @param array $individual The timetable individual
     * @param array $data The data context
     * @return array Conflict analysis with performance metrics
     */
    public static function analyzeIndividualOptimized(array $individual, array $data): array {
        $startTime = microtime(true);
        
        // Use cached conflict keys for better performance
        $conflictKeys = self::getCached('conflict_keys_' . md5(serialize($individual)), function() use ($individual) {
            $keys = [];
            foreach ($individual as $gene) {
                $keys[] = [
                    'gene_key' => self::getGeneKey($gene),
                    'room_key' => self::getRoomConflictKey($gene),
                    'lecturer_key' => self::getLecturerConflictKey($gene),
                    'class_key' => self::getClassConflictKey($gene)
                ];
            }
            return $keys;
        });
        
        $analysis = [
            'total_genes' => count($individual),
            'total_conflicts' => 0,
            'room_conflicts' => 0,
            'lecturer_conflicts' => 0,
            'class_conflicts' => 0,
            'conflict_details' => [],
            'performance' => [
                'analysis_time' => 0,
                'cache_hits' => 0,
                'cache_misses' => 0
            ]
        ];
        
        // Check for conflicts using optimized approach
        $usedKeys = [];
        foreach ($conflictKeys as $i => $keys) {
            foreach ($usedKeys as $j => $usedKey) {
                if ($keys['gene_key'] === $usedKey['gene_key']) {
                    $analysis['total_conflicts']++;
                    
                    // Determine conflict type efficiently
                    if ($keys['room_key'] === $usedKey['room_key']) {
                        $analysis['room_conflicts']++;
                        $conflictType = 'room';
                    } elseif ($keys['lecturer_key'] === $usedKey['lecturer_key']) {
                        $analysis['lecturer_conflicts']++;
                        $conflictType = 'lecturer';
                    } elseif ($keys['class_key'] === $usedKey['class_key']) {
                        $analysis['class_conflicts']++;
                        $conflictType = 'class';
                    } else {
                        $conflictType = 'unknown';
                    }
                    
                    $analysis['conflict_details'][] = [
                        'gene1_index' => $i,
                        'gene2_index' => $j,
                        'type' => $conflictType,
                        'time_slot' => $keys['gene_key']
                    ];
                }
            }
            $usedKeys[] = $keys;
        }
        
        $analysis['performance']['analysis_time'] = microtime(true) - $startTime;
        
        return $analysis;
    }
    
    /**
     * Batch process multiple individuals for better performance
     * 
     * @param array $individuals Array of timetable individuals
     * @param array $data The data context
     * @return array Analysis results for all individuals
     */
    public static function batchAnalyzeIndividuals(array $individuals, array $data): array {
        $results = [];
        $startTime = microtime(true);
        
        foreach ($individuals as $index => $individual) {
            $results[$index] = self::analyzeIndividualOptimized($individual, $data);
        }
        
        $totalTime = microtime(true) - $startTime;
        
        return [
            'individuals' => $results,
            'summary' => [
                'total_individuals' => count($individuals),
                'total_analysis_time' => $totalTime,
                'average_time_per_individual' => $totalTime / count($individuals),
                'best_individual' => self::findBestIndividual($results),
                'worst_individual' => self::findWorstIndividual($results)
            ]
        ];
    }
    
    /**
     * Find the best individual based on conflict count
     * 
     * @param array $analysisResults Analysis results from batchAnalyzeIndividuals
     * @return int Index of the best individual
     */
    private static function findBestIndividual(array $analysisResults): int {
        $bestIndex = 0;
        $minConflicts = PHP_INT_MAX;
        
        foreach ($analysisResults as $index => $result) {
            if ($result['total_conflicts'] < $minConflicts) {
                $minConflicts = $result['total_conflicts'];
                $bestIndex = $index;
            }
        }
        
        return $bestIndex;
    }
    
    /**
     * Find the worst individual based on conflict count
     * 
     * @param array $analysisResults Analysis results from batchAnalyzeIndividuals
     * @return int Index of the worst individual
     */
    private static function findWorstIndividual(array $analysisResults): int {
        $worstIndex = 0;
        $maxConflicts = 0;
        
        foreach ($analysisResults as $index => $result) {
            if ($result['total_conflicts'] > $maxConflicts) {
                $maxConflicts = $result['total_conflicts'];
                $worstIndex = $index;
            }
        }
        
        return $worstIndex;
    }
    
    /**
     * Generate a performance report for the class
     * 
     * @return array Performance metrics and recommendations
     */
    public static function getPerformanceReport(): array {
        return [
            'cache_status' => [
                'cached_items' => count(self::$cache),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ],
            'recommendations' => [
                'Use analyzeIndividualOptimized() for better performance',
                'Use batchAnalyzeIndividuals() for multiple individuals',
                'Clear cache periodically with clearCache()',
                'Consider using smaller data sets for very large timetables'
            ],
            'optimization_tips' => [
                'Cache frequently accessed data structures',
                'Use batch processing for multiple operations',
                'Validate data structure before processing',
                'Monitor memory usage for large datasets'
            ]
        ];
    }
}
?>
