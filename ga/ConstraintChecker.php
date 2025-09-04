<?php
/**
 * Comprehensive Constraint Checker for Timetable Generation
 * 
 * This class evaluates timetable solutions against hard and soft constraints
 * for the genetic algorithm fitness evaluation.
 */

class ConstraintChecker {
    private $data;
    private $options;
    private $constraintWeights;
    private $cache = []; // Add caching for repeated calculations
    
    public function __construct(array $data, array $options = []) {
        $this->data = $data;
        $this->options = $options;
        
        // Define constraint weights
        $this->constraintWeights = [
            'hard' => [
                'room_conflict' => 1000,
                'lecturer_conflict' => 1000,
                'class_conflict' => 1000,
                'missing_assignment' => 1000,
                'invalid_time_slot' => 1000,
                'invalid_day' => 1000,
                'invalid_room' => 1000,
                'duplicate_assignment' => 1000
            ],
            'soft' => [
                'room_capacity' => 10,
                'lecturer_preference' => 5,
                'class_preference' => 5,
                'time_distribution' => 3,
                'room_preference' => 2,
                'day_distribution' => 2
            ]
        ];
    }
    
    /**
     * Clear cache (call this between different individuals)
     */
    private function clearCache(): void {
        $this->cache = [];
    }
    
    /**
     * Evaluate the fitness of a timetable solution
     */
    public function evaluateFitness(array $individual): array {
        $this->clearCache(); // Clear cache for new individual
        
        $hardViolations = $this->checkHardConstraints($individual);
        $softViolations = $this->checkSoftConstraints($individual);
        
        $totalHardScore = $this->calculateHardScore($hardViolations);
        $totalSoftScore = $this->calculateSoftScore($softViolations);
        
        return [
            'hard_violations' => $hardViolations,
            'soft_violations' => $softViolations,
            'hard_score' => $totalHardScore,
            'soft_score' => $totalSoftScore,
            'total_score' => $totalHardScore + $totalSoftScore,
            'is_feasible' => empty($hardViolations)
        ];
    }
    
    /**
     * Check hard constraints (must be satisfied)
     * Updated to handle combined class assignments and enhanced duplicate detection
     */
    private function checkHardConstraints(array $individual): array {
        $violations = [];
        
        // Track conflicts
        $roomSlots = [];
        $lecturerSlots = [];
        $classSlots = [];
        $classCourseTimeSlots = []; // Track unique class-course-time combinations
        $processedGenes = []; // Track processed genes to avoid double-counting
        
        foreach ($individual as $classCourseId => $gene) {
            // Check for missing assignments
            if (!$this->isValidAssignment($gene)) {
                $violations['missing_assignment'][] = [
                    'class_course_id' => $classCourseId,
                    'message' => 'Missing required assignment'
                ];
                continue;
            }
            
            // Create a unique identifier for this gene to avoid double-processing
            $geneId = $classCourseId . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'] . '|' . ($gene['slot_index'] ?? 0);
            if (isset($processedGenes[$geneId])) {
                continue; // Skip if we've already processed this gene
            }
            $processedGenes[$geneId] = true;
            
            // Check for duplicate class-course-time combinations
            $classCourseTimeKey = $classCourseId . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
            if (isset($classCourseTimeSlots[$classCourseTimeKey])) {
                $violations['duplicate_assignment'][] = [
                    'class_course_id' => $classCourseId,
                    'conflict_with' => $classCourseTimeSlots[$classCourseTimeKey],
                    'message' => 'Duplicate assignment for same class-course at same time',
                    'details' => "Gene: $classCourseId, Day: {$gene['day_id']}, Time: {$gene['time_slot_id']}"
                ];
                error_log("Duplicate assignment detected in GA: $classCourseTimeKey");
            } else {
                $classCourseTimeSlots[$classCourseTimeKey] = $classCourseId;
            }
            
            // Check if this is a combined assignment
            $isCombined = $gene['is_combined'] ?? false;
            $combinedClasses = $gene['combined_classes'] ?? [];
            
            if ($isCombined && !empty($combinedClasses)) {
                // Handle combined assignment - check conflicts for all classes
                foreach ($combinedClasses as $classCourse) {
                    $classId = $classCourse['class_id'];
                    
                    // Check class conflicts for each class in the combination
                    $classKey = $classId . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
                    if (isset($classSlots[$classKey])) {
                        $violations['class_conflict'][] = [
                            'class_course_id' => $classCourseId,
                            'class_id' => $classId,
                            'conflict_with' => $classSlots[$classKey],
                            'message' => 'Class already has a course at this time (in combined assignment)'
                        ];
                    } else {
                        $classSlots[$classKey] = $classCourseId;
                    }
                }
                
                // Check room conflicts (only once for the combined assignment)
                $roomKey = TimetableRepresentation::getRoomConflictKey($gene);
                if (isset($roomSlots[$roomKey])) {
                    $violations['room_conflict'][] = [
                        'class_course_id' => $classCourseId,
                        'conflict_with' => $roomSlots[$roomKey],
                        'message' => 'Room already occupied at this time (combined assignment)'
                    ];
                } else {
                    $roomSlots[$roomKey] = $classCourseId;
                }
                
                // Check lecturer conflicts (only once for the combined assignment)
                if ($gene['lecturer_course_id'] || $gene['lecturer_id']) {
                    $lecturerKey = TimetableRepresentation::getLecturerConflictKey($gene);
                    if (isset($lecturerSlots[$lecturerKey])) {
                        $violations['lecturer_conflict'][] = [
                            'class_course_id' => $classCourseId,
                            'conflict_with' => $lecturerSlots[$lecturerKey],
                            'message' => 'Lecturer already teaching at this time (combined assignment)'
                        ];
                    } else {
                        $lecturerSlots[$lecturerKey] = $classCourseId;
                    }
                }
            } else {
                // Handle individual assignment
                // Check room conflicts
                $roomKey = TimetableRepresentation::getRoomConflictKey($gene);
                if (isset($roomSlots[$roomKey])) {
                    $violations['room_conflict'][] = [
                        'class_course_id' => $classCourseId,
                        'conflict_with' => $roomSlots[$roomKey],
                        'message' => 'Room already occupied at this time'
                    ];
                } else {
                    $roomSlots[$roomKey] = $classCourseId;
                }
                
                // Check lecturer conflicts
                if ($gene['lecturer_course_id'] || $gene['lecturer_id']) {
                    $lecturerKey = TimetableRepresentation::getLecturerConflictKey($gene);
                    if (isset($lecturerSlots[$lecturerKey])) {
                        $violations['lecturer_conflict'][] = [
                            'class_course_id' => $classCourseId,
                            'conflict_with' => $lecturerSlots[$lecturerKey],
                            'message' => 'Lecturer already teaching at this time'
                        ];
                    } else {
                        $lecturerSlots[$lecturerKey] = $classCourseId;
                    }
                }
                
                // Check class conflicts
                $classKey = $gene['class_id'] . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
                if (isset($classSlots[$classKey])) {
                    $violations['class_conflict'][] = [
                        'class_course_id' => $classCourseId,
                        'conflict_with' => $classSlots[$classKey],
                        'message' => 'Class already has a course at this time'
                    ];
                } else {
                    $classSlots[$classKey] = $classCourseId;
                }
            }
            
            // Check validity of assignments
            if (!$this->isValidTimeSlot($gene)) {
                $violations['invalid_time_slot'][] = [
                    'class_course_id' => $classCourseId,
                    'time_slot_id' => $gene['time_slot_id'],
                    'message' => 'Invalid time slot'
                ];
            }
            
            if (!$this->isValidDay($gene)) {
                $violations['invalid_day'][] = [
                    'class_course_id' => $classCourseId,
                    'day_id' => $gene['day_id'],
                    'message' => 'Invalid day'
                ];
            }
            
            if (!$this->isValidRoom($gene)) {
                $violations['invalid_room'][] = [
                    'class_course_id' => $classCourseId,
                    'room_id' => $gene['room_id'],
                    'message' => 'Invalid room'
                ];
            }
        }
        
        return $violations;
    }
    
    /**
     * Check soft constraints (preferences)
     */
    private function checkSoftConstraints(array $individual): array {
        $violations = [];
        
        foreach ($individual as $classCourseId => $gene) {
            if (!$this->isValidAssignment($gene)) {
                continue;
            }
            
            // Check room capacity
            $roomCapacityViolation = $this->checkRoomCapacity($gene);
            if ($roomCapacityViolation) {
                $violations['room_capacity'][] = $roomCapacityViolation;
            }
            
            // Check lecturer preferences
            $lecturerPreferenceViolation = $this->checkLecturerPreferences($gene);
            if ($lecturerPreferenceViolation) {
                $violations['lecturer_preference'][] = $lecturerPreferenceViolation;
            }
            
            // Check class preferences
            $classPreferenceViolation = $this->checkClassPreferences($gene);
            if ($classPreferenceViolation) {
                $violations['class_preference'][] = $classPreferenceViolation;
            }
            
            // Check time distribution
            $timeDistributionViolation = $this->checkTimeDistribution($gene, $individual);
            if ($timeDistributionViolation) {
                $violations['time_distribution'][] = $timeDistributionViolation;
            }
            
            // Check room preferences
            $roomPreferenceViolation = $this->checkRoomPreferences($gene);
            if ($roomPreferenceViolation) {
                $violations['room_preference'][] = $roomPreferenceViolation;
            }
            
            // Check day distribution
            $dayDistributionViolation = $this->checkDayDistribution($gene, $individual);
            if ($dayDistributionViolation) {
                $violations['day_distribution'][] = $dayDistributionViolation;
            }
        }
        
        return $violations;
    }
    
    /**
     * Check if assignment is valid
     */
    private function isValidAssignment(array $gene): bool {
        return $gene['day_id'] && $gene['time_slot_id'] && $gene['room_id'];
    }
    
    /**
     * Check if time slot is valid
     */
    private function isValidTimeSlot(array $gene): bool {
        if (!isset($this->data['time_slots']) || empty($this->data['time_slots'])) {
            return false;
        }
        
        foreach ($this->data['time_slots'] as $timeSlot) {
            if ($timeSlot['id'] == $gene['time_slot_id']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if day is valid
     */
    private function isValidDay(array $gene): bool {
        if (!isset($this->data['days']) || empty($this->data['days'])) {
            return false;
        }
        
        foreach ($this->data['days'] as $day) {
            if ($day['id'] == $gene['day_id']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if room is valid
     */
    private function isValidRoom(array $gene): bool {
        if (!isset($this->data['rooms']) || empty($this->data['rooms'])) {
            return false;
        }
        
        foreach ($this->data['rooms'] as $room) {
            if ($room['id'] == $gene['room_id']) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check room capacity constraint
     * Updated to handle combined class assignments
     */
    private function checkRoomCapacity(array $gene): ?array {
        $roomId = $gene['room_id'];
        
        // Check if this is a combined assignment
        $isCombined = $gene['is_combined'] ?? false;
        $combinedClasses = $gene['combined_classes'] ?? [];
        
        if ($isCombined && !empty($combinedClasses)) {
            // Calculate total capacity for all class divisions in the combination
            $totalClassCapacity = 0;
            foreach ($combinedClasses as $classCourse) {
                // Use individual_capacity from expanded class divisions
                $totalClassCapacity += $classCourse['individual_capacity'] ?? 0;
            }
            
            // Find room capacity
            $roomCapacity = 0;
            if (isset($this->data['rooms'])) {
                foreach ($this->data['rooms'] as $room) {
                    if ($room['id'] == $roomId) {
                        $roomCapacity = $room['capacity'] ?? 0;
                        break;
                    }
                }
            }
            
            if ($roomCapacity < $totalClassCapacity) {
                return [
                    'class_course_id' => $gene['class_course_id'],
                    'total_class_capacity' => $totalClassCapacity,
                    'room_capacity' => $roomCapacity,
                    'shortage' => $totalClassCapacity - $roomCapacity,
                    'is_combined' => true,
                    'combined_classes_count' => count($combinedClasses)
                ];
            }
        } else {
            // Handle individual assignment
            $classId = $gene['class_id'];
            
            // Find class capacity from expanded class data
            $classCapacity = 0;
            if (isset($this->data['class_courses'])) {
                foreach ($this->data['class_courses'] as $classCourse) {
                    if ($classCourse['class_id'] == $classId && 
                        isset($classCourse['individual_capacity'])) {
                        $classCapacity = $classCourse['individual_capacity'];
                        break;
                    }
                }
            }
            
            // Fallback to total_capacity if individual_capacity not found
            if ($classCapacity == 0 && isset($this->data['classes'])) {
                foreach ($this->data['classes'] as $class) {
                    if ($class['id'] == $classId) {
                        $classCapacity = $class['total_capacity'] ?? 0;
                        break;
                    }
                }
            }
            
            // Find room capacity
            $roomCapacity = 0;
            if (isset($this->data['rooms'])) {
                foreach ($this->data['rooms'] as $room) {
                    if ($room['id'] == $roomId) {
                        $roomCapacity = $room['capacity'] ?? 0;
                        break;
                    }
                }
            }
            
            if ($roomCapacity < $classCapacity) {
                return [
                    'class_course_id' => $gene['class_course_id'],
                    'class_capacity' => $classCapacity,
                    'room_capacity' => $roomCapacity,
                    'shortage' => $classCapacity - $roomCapacity
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Check lecturer preferences
     */
    private function checkLecturerPreferences(array $gene): ?array {
        // This would check lecturer preferences (e.g., preferred days, times)
        // For now, return null (no preference violations)
        return null;
    }
    
    /**
     * Check class preferences
     */
    private function checkClassPreferences(array $gene): ?array {
        // This would check class preferences (e.g., preferred rooms, times)
        // For now, return null (no preference violations)
        return null;
    }
    
    /**
     * Check time distribution
     */
    private function checkTimeDistribution(array $gene, array $individual): ?array {
        // Check if class has too many courses in consecutive time slots
        $classId = $gene['class_id'];
        $dayId = $gene['day_id'];
        $timeSlotId = $gene['time_slot_id'];
        
        // Use cache key for this calculation
        $cacheKey = "time_dist_{$classId}_{$dayId}";
        if (!isset($this->cache[$cacheKey])) {
            // Pre-calculate time slots for this class on this day for O(n) complexity
            $classTimeSlots = [];
            foreach ($individual as $otherGene) {
                if ($otherGene['class_id'] == $classId && 
                    $otherGene['day_id'] == $dayId &&
                    $otherGene['day_id'] && $otherGene['time_slot_id']) {
                    $classTimeSlots[] = $otherGene['time_slot_id'];
                }
            }
            $this->cache[$cacheKey] = $classTimeSlots;
        } else {
            $classTimeSlots = $this->cache[$cacheKey];
        }
        
        // Count consecutive slots more efficiently
        $consecutiveCount = 0;
        foreach ($classTimeSlots as $slot) {
            if (abs($slot - $timeSlotId) <= 1) {
                $consecutiveCount++;
            }
        }
        
        if ($consecutiveCount > 3) { // More than 3 consecutive slots
            return [
                'class_course_id' => $gene['class_course_id'],
                'consecutive_count' => $consecutiveCount,
                'message' => 'Too many consecutive time slots'
            ];
        }
        
        return null;
    }
    
    /**
     * Check room preferences
     */
    private function checkRoomPreferences(array $gene): ?array {
        // This would check room preferences (e.g., labs for practical courses)
        // For now, return null (no preference violations)
        return null;
    }
    
    /**
     * Check day distribution
     */
    private function checkDayDistribution(array $gene, array $individual): ?array {
        // Check if class has too many courses on the same day
        $classId = $gene['class_id'];
        $dayId = $gene['day_id'];
        
        // Use cache key for this calculation
        $cacheKey = "day_dist_{$classId}";
        if (!isset($this->cache[$cacheKey])) {
            // Pre-calculate day counts for this class for O(n) complexity
            $classDayCounts = [];
            foreach ($individual as $otherGene) {
                if ($otherGene['class_id'] == $classId && 
                    $otherGene['day_id'] && $otherGene['time_slot_id']) {
                    $otherDayId = $otherGene['day_id'];
                    $classDayCounts[$otherDayId] = ($classDayCounts[$otherDayId] ?? 0) + 1;
                }
            }
            $this->cache[$cacheKey] = $classDayCounts;
        } else {
            $classDayCounts = $this->cache[$cacheKey];
        }
        
        $dayCount = $classDayCounts[$dayId] ?? 0;
        
        if ($dayCount > 4) { // More than 4 courses per day
            return [
                'class_course_id' => $gene['class_course_id'],
                'day_count' => $dayCount,
                'message' => 'Too many courses on the same day'
            ];
        }
        
        return null;
    }
    
    /**
     * Calculate hard constraint score
     */
    private function calculateHardScore(array $violations): float {
        $score = 0;
        
        foreach ($violations as $constraintType => $violationList) {
            $weight = $this->constraintWeights['hard'][$constraintType] ?? 1000;
            $score += count($violationList) * $weight;
        }
        
        return $score;
    }
    
    /**
     * Calculate soft constraint score
     */
    private function calculateSoftScore(array $violations): float {
        $score = 0;
        
        foreach ($violations as $constraintType => $violationList) {
            $weight = $this->constraintWeights['soft'][$constraintType] ?? 1;
            $score += count($violationList) * $weight;
        }
        
        return $score;
    }
    
    /**
     * Get detailed violation report
     */
    public function getViolationReport(array $individual): array {
        $hardViolations = $this->checkHardConstraints($individual);
        $softViolations = $this->checkSoftConstraints($individual);
        
        return [
            'hard_violations' => $hardViolations,
            'soft_violations' => $softViolations,
            'hard_count' => array_sum(array_map('count', $hardViolations)),
            'soft_count' => array_sum(array_map('count', $softViolations)),
            'total_violations' => array_sum(array_map('count', $hardViolations)) + 
                                array_sum(array_map('count', $softViolations))
        ];
    }
    
    /**
     * Check if solution is feasible (no hard violations)
     */
    public function isFeasible(array $individual): bool {
        $hardViolations = $this->checkHardConstraints($individual);
        return empty($hardViolations);
    }
}
?>
