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
    private $indexes = [
        'roomsById' => [],
        'timeSlotsById' => [],
        'classCapacityByClassId' => [],
        'divisionCapacityByClassIdAndLabel' => []
    ];
    
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
                'duplicate_assignment' => 1000,
                'break_split' => 1000,
                'room_type_mismatch' => 1000
            ],
            'soft' => [
                'room_capacity' => 10,
                'lecturer_preference' => 5,
                'class_preference' => 5,
                'time_distribution' => 3,
                'room_preference' => 2,
                'day_distribution' => 2,
                'idle_gap' => 4,
                'early_start' => -1
            ]
        ];
        // Build static indexes for faster checks
        $this->buildIndexes();
    }
    
    /**
     * Clear cache (call this between different individuals)
     */
    private function clearCache(): void {
        $this->cache = [];
        // Force garbage collection to free memory
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Build fast indexes for rooms, time slots, and class capacities
     */
    private function buildIndexes(): void {
        // Rooms by id
        if (!empty($this->data['rooms'])) {
            foreach ($this->data['rooms'] as $room) {
                if (isset($room['id'])) {
                    $this->indexes['roomsById'][(int)$room['id']] = $room;
                }
            }
        }
        // Time slots by id
        if (!empty($this->data['time_slots'])) {
            foreach ($this->data['time_slots'] as $ts) {
                if (isset($ts['id'])) {
                    $this->indexes['timeSlotsById'][(int)$ts['id']] = $ts;
                }
            }
        }
        // Class capacity by class id (fallback) and per-division capacity index
        $byClass = [];
        $byDivision = [];
        if (!empty($this->data['class_courses'])) {
            foreach ($this->data['class_courses'] as $cc) {
                if (!isset($cc['class_id'])) { continue; }
                $cid = (int)$cc['class_id'];
                if (isset($cc['individual_capacity'])) {
                    $cap = (int)$cc['individual_capacity'];
                    $byClass[$cid] = max($byClass[$cid] ?? 0, $cap);
                    $label = isset($cc['division_label']) ? (string)$cc['division_label'] : '';
                    if ($label !== '') {
                        $byDivision[$cid][$label] = max($byDivision[$cid][$label] ?? 0, $cap);
                    }
                }
            }
        }
        if (!empty($this->data['classes'])) {
            foreach ($this->data['classes'] as $c) {
                if (!isset($c['id'])) { continue; }
                $cid = (int)$c['id'];
                $cap = (int)($c['total_capacity'] ?? 0);
                $byClass[$cid] = max($byClass[$cid] ?? 0, $cap);
            }
        }
        $this->indexes['classCapacityByClassId'] = $byClass;
        $this->indexes['divisionCapacityByClassIdAndLabel'] = $byDivision;
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
        
        $result = [
            'hard_violations' => $hardViolations,
            'soft_violations' => $softViolations,
            'hard_score' => $totalHardScore,
            'soft_score' => $totalSoftScore,
            'total_score' => $totalHardScore + $totalSoftScore,
            'is_feasible' => empty($hardViolations)
        ];
        
        // Force garbage collection after evaluation
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        return $result;
    }
    
    /**
     * Check hard constraints (must be satisfied)
     * Updated to handle combined class assignments and enhanced duplicate detection
     */
    private function checkHardConstraints(array $individual): array {
        $violations = [];
        
        // Track conflicts with limited memory usage
        $roomSlots = [];
        $lecturerSlots = [];
        $classSlots = [];
        $classCourseTimeSlots = []; // Track unique class-course-time combinations
        $processedGenes = []; // Track processed genes to avoid double-counting
        
        // Limit the size of tracking arrays to prevent memory exhaustion
        $maxTrackingSize = 10000;
        
        $geneCount = 0;
        foreach ($individual as $classCourseId => $gene) {
            $geneCount++;
            
            // Periodic memory cleanup for large individuals
            if ($geneCount % 100 === 0 && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
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
            
            // Prevent tracking arrays from growing too large
            if (count($processedGenes) < $maxTrackingSize) {
                $processedGenes[$geneId] = true;
            }
            
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
                    $divLabel = $classCourse['division_label'] ?? ($gene['division_label'] ?? '');
                    $classKey = $classId . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
                    if (!empty($divLabel)) { $classKey .= '|' . $divLabel; }
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
                    $lecturerKey = TimetableRepresentation::getLecturerConflictKey($gene, $this->data);
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
                    $lecturerKey = TimetableRepresentation::getLecturerConflictKey($gene, $this->data);
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
                
                // Check class conflicts (per-division time clash)
                $classKey = $gene['class_id'] . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
                if (!empty($gene['division_label'])) { $classKey .= '|' . $gene['division_label']; }
                if (isset($classSlots[$classKey])) {
                    $violations['class_conflict'][] = [
                        'class_course_id' => $classCourseId,
                        'conflict_with' => $classSlots[$classKey],
                        'message' => 'Class already has a course at this time'
                    ];
                } else {
                    $classSlots[$classKey] = $classCourseId;
                }

                // Hard: each course occurs once per week for a class division
                $div = (string)($gene['division_label'] ?? '');
                $repeatKey = 'once_per_week|' . $gene['class_id'] . '|' . $div . '|' . ($gene['course_id'] ?? '');
                if (isset($this->cache[$repeatKey])) {
                    $violations['duplicate_assignment'][] = [
                        'class_course_id' => $classCourseId,
                        'message' => 'Course scheduled more than once this week for this class division'
                    ];
                } else {
                    $this->cache[$repeatKey] = true;
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

            // Hard: room type must match preferred type if set
            $roomTypeMismatch = $this->checkRoomTypeMismatch($gene);
            if ($roomTypeMismatch) {
                $violations['room_type_mismatch'][] = $roomTypeMismatch;
            }

            // Hard: break should not split a lecture across consecutive slots (duration forced to 1, but keep check safe)
            $breakSplit = $this->checkBreakSplit($gene);
            if ($breakSplit) {
                $violations['break_split'][] = $breakSplit;
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

            // Precompute per-day slots once per individual to avoid O(n^2)
            if (!isset($this->cache['precomputed_day_slots'])) {
                $this->cache['precomputed_day_slots'] = $this->precomputeDaySlots($individual);
            }
            // Idle gaps for lecturer and class in the same day
            $idle = $this->checkIdleGaps($gene, $individual);
            if ($idle) {
                $violations['idle_gap'][] = $idle;
            }

            // Encourage 7:00 AM start (reward by negative penalty if starts at earliest slot)
            $early = $this->checkEarlyStart($gene);
            if ($early) {
                $violations['early_start'][] = $early;
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
                // Hard rule: do not allow scheduling in break slots
                if (!empty($timeSlot['is_break'])) { return false; }
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
        $rid = (int)$gene['room_id'];
        return isset($this->indexes['roomsById'][$rid]);
    }

    /**
     * Check if selected room's type matches preferred type for the course if configured
     */
    private function checkRoomTypeMismatch(array $gene): ?array {
        if (empty($this->data['course_room_types'])) { return null; }
        $courseId = $gene['course_id'] ?? null;
        if (!$courseId) { return null; }
        $preferred = $this->data['course_room_types'][$courseId] ?? null;
        if (!$preferred) { return null; }
        $roomType = null;
        $room = $this->indexes['roomsById'][(int)$gene['room_id']] ?? null;
        if ($room) { $roomType = strtolower((string)($room['room_type'] ?? '')); }
        if ($roomType && $roomType !== $preferred) {
            return [
                'class_course_id' => $gene['class_course_id'],
                'course_id' => $courseId,
                'room_id' => $gene['room_id'],
                'room_type' => $roomType,
                'preferred' => $preferred
            ];
        }
        return null;
    }

    /**
     * Check whether a multi-hour lecture crosses a break slot
     */
    private function checkBreakSplit(array $gene): ?array {
        $duration = (int)($gene['course_duration'] ?? 1);
        if ($duration <= 1) { return null; }
        $startId = (int)$gene['time_slot_id'];
        $slotMap = $this->indexes['timeSlotsById'];
        for ($i = 0; $i < $duration; $i++) {
            $sid = $startId + $i;
            if (!isset($slotMap[$sid])) { return ['class_course_id' => $gene['class_course_id'], 'time_slot_id' => $sid, 'message' => 'Consecutive slot missing']; }
            if (!empty($slotMap[$sid]['is_break'])) {
                return [
                    'class_course_id' => $gene['class_course_id'],
                    'time_slot_id' => $sid,
                    'message' => 'Lecture crosses a break period'
                ];
            }
        }
        return null;
    }

    /**
     * Penalize large idle gaps for lecturers and classes within a day
     */
    private function checkIdleGaps(array $gene, array $individual): ?array {
        $classId = $gene['class_id'];
        $dayId = $gene['day_id'];
        $timeSlotId = (int)$gene['time_slot_id'];
        $lecturerKey = $gene['lecturer_course_id'] ?? $gene['lecturer_id'] ?? null;
        $pre = $this->cache['precomputed_day_slots'] ?? ['class'=>[], 'lect'=>[]];
        $classSlots = $pre['class'][$classId][$dayId] ?? [];
        $lectSlots = $lecturerKey ? ($pre['lect'][$lecturerKey][$dayId] ?? []) : [];
        $gap = function(array $slots, int $id) {
            if (empty($slots)) return 0;
            $prev = null; $next = null;
            foreach ($slots as $s) {
                if ($s < $id) { $prev = $s; }
                if ($s > $id && $next === null) { $next = $s; break; }
            }
            $gap = 0;
            if ($prev !== null) { $gap = max($gap, $id - $prev - 1); }
            if ($next !== null) { $gap = max($gap, $next - $id - 1); }
            return $gap;
        };
        $classGap = $gap($classSlots, $timeSlotId);
        $lectGap = $gap($lectSlots, $timeSlotId);
        $maxGap = max($classGap, $lectGap);
        if ($maxGap >= 2) { // two or more empty slots around
            return [
                'class_course_id' => $gene['class_course_id'],
                'gap_slots' => $maxGap,
                'message' => 'Idle gap around this class/lecturer'];
        }
        return null;
    }

    /**
     * Precompute per-day time slots for classes and lecturers
     */
    private function precomputeDaySlots(array $individual): array {
        $classDaySlots = [];
        $lectDaySlots = [];
        foreach ($individual as $g) {
            $day = $g['day_id'] ?? null;
            $slot = $g['time_slot_id'] ?? null;
            if (!$day || !$slot) { continue; }
            $cid = $g['class_id'] ?? null;
            if ($cid) {
                $classDaySlots[$cid][$day][] = (int)$slot;
            }
            $lid = $g['lecturer_course_id'] ?? ($g['lecturer_id'] ?? null);
            if ($lid) {
                $lectDaySlots[$lid][$day][] = (int)$slot;
            }
        }
        foreach ($classDaySlots as $cid => $byDay) {
            foreach ($byDay as $day => $arr) { sort($classDaySlots[$cid][$day]); }
        }
        foreach ($lectDaySlots as $lid => $byDay) {
            foreach ($byDay as $day => $arr) { sort($lectDaySlots[$lid][$day]); }
        }
        return ['class' => $classDaySlots, 'lect' => $lectDaySlots];
    }

    /**
     * Reward early starts (7:00 AM) by emitting a negative-weight violation entry
     */
    private function checkEarlyStart(array $gene): ?array {
        $slotMap = $this->indexes['timeSlotsById'];
        $sid = (int)$gene['time_slot_id'];
        if (!isset($slotMap[$sid])) return null;
        $start = $slotMap[$sid]['start_time'] ?? '';
        if ($start === '07:00:00') {
            return [ 'class_course_id' => $gene['class_course_id'] ];
        }
        return null;
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
            
            // Find room capacity (indexed)
            $roomCapacity = (int)($this->indexes['roomsById'][(int)$roomId]['capacity'] ?? 0);
            
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
            
            // Class capacity: prefer per-division capacity when division_label present
            $divLabel = isset($gene['division_label']) ? (string)$gene['division_label'] : '';
            $classCapacity = 0;
            if ($divLabel !== '') {
                $classCapacity = (int)($this->indexes['divisionCapacityByClassIdAndLabel'][(int)$classId][$divLabel] ?? 0);
            }
            if ($classCapacity <= 0) {
                $classCapacity = (int)($this->indexes['classCapacityByClassId'][(int)$classId] ?? 0);
            }
            
            // Find room capacity (indexed)
            $roomCapacity = (int)($this->indexes['roomsById'][(int)$roomId]['capacity'] ?? 0);
            
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
