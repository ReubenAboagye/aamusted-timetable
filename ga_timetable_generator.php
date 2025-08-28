<?php

class GeneticAlgorithm {
    private $classes;           // Array of classes from the 'class' table
    private $courses;           // Array of courses from the 'course' table
    private $rooms;             // Array of available rooms from the 'room' table
    private $lecturers;         // Array of lecturers with their constraints
    private $timeSlots;         // Available time slots (excluding break)
    private $days;              // Days of the week
    private $population;        // Current population of timetables
    private $populationSize;    // Size of the population
    private $constraints;       // Constraint definitions
    private $fitnessCache;      // Cache for fitness calculations
    private $progressCallback;  // Optional progress reporter callable
    private $currentFitnessScores; // Cached fitness scores per generation (aligned with $population)
    private $timeBudgetSeconds; // Optional time budget to stop early

    public function __construct($classes, $courses, $rooms, $lecturers = []) {
        $this->classes = $classes;
        $this->courses = $courses;
        $this->rooms = $rooms;
        $this->lecturers = $lecturers;
        
        // Define available time slots for teaching (1-hour slots 07:00..20:00)
        $this->timeSlots = [];
        for ($h = 7; $h < 20; $h++) {
            $this->timeSlots[] = sprintf('%02d:00-%02d:00', $h, $h + 1);
        }
        $this->days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        
        $this->population = [];
        $this->fitnessCache = [];
        $this->progressCallback = null;
        $this->currentFitnessScores = [];
        $this->timeBudgetSeconds = 0;
        
        // Initialize constraint definitions
        $this->initializeConstraints();
    }

    /**
     * Set a callback to report progress during evolution.
     * Callback signature: function(int $generation, int $totalGenerations, float $bestFitness): void
     */
    public function setProgressReporter($callback) {
        if (is_callable($callback)) {
            $this->progressCallback = $callback;
        }
    }

    /**
     * Set an optional wall-clock time budget (in seconds) for evolve().
     */
    public function setTimeBudgetSeconds($seconds) {
        $this->timeBudgetSeconds = max(0, (int)$seconds);
    }

    /** Allow external access for async chunking use-cases */
    public function setPopulation(array $population) { $this->population = $population; }
    public function getPopulation() { return $this->population; }

    /**
     * Initialize constraint definitions with weights
     */
    private function initializeConstraints() {
        $this->constraints = [
            'hard' => [
                'class_conflict' => ['weight' => 1000, 'description' => 'Class cannot have multiple courses at same time'],
                'lecturer_conflict' => ['weight' => 1000, 'description' => 'Lecturer cannot teach multiple courses at same time'],
                'room_conflict' => ['weight' => 1000, 'description' => 'Room cannot be used for multiple courses at same time'],
                'lecturer_unavailable' => ['weight' => 1000, 'description' => 'Lecturer not available at assigned time'],
                'room_capacity' => ['weight' => 800, 'description' => 'Room capacity insufficient for class size'],
                'room_type_mismatch' => ['weight' => 600, 'description' => 'Room type unsuitable for course requirements']
            ],
            'soft' => [
                'daily_overload' => ['weight' => 50, 'description' => 'More than 3 courses per day for a class'],
                'consecutive_breaks' => ['weight' => 30, 'description' => 'Long gaps between classes for same class'],
                'room_preference' => ['weight' => 20, 'description' => 'Room not preferred for course type'],
                'time_preference' => ['weight' => 15, 'description' => 'Time slot not preferred for course type'],
                'lecturer_workload' => ['weight' => 25, 'description' => 'Lecturer has too many courses in one day']
            ]
        ];
    }

    /**
     * Initialize a random population of timetables
     */
    public function initializePopulation($size) {
        $this->populationSize = $size;
        for ($i = 0; $i < $size; $i++) {
            $this->population[] = $this->createRandomTimetable();
        }
    }

    /**
     * Create a random timetable with basic constraint checking
     */
    private function createRandomTimetable() {
        $timetable = [];
        $usedSlots = []; // Track used slots to avoid immediate conflicts
        
        foreach ($this->classes as $class) {
            foreach ($this->courses as $course) {
                if ($course['class_id'] == $class['class_id']) {
                    $assignment = $this->findValidAssignment($course, $class, $usedSlots);
                    if ($assignment) {
                        $timetable[] = $assignment;
                        $this->markSlotAsUsed($assignment, $usedSlots);
                    }
                }
            }
        }
        return $timetable;
    }

    /**
     * Find a valid assignment for a course that minimizes conflicts
     */
    private function findValidAssignment($course, $class, $usedSlots) {
        $attempts = 0;
        $maxAttempts = 50;
        
        while ($attempts < $maxAttempts) {
            $timeSlot = $this->timeSlots[array_rand($this->timeSlots)];
            $day = $this->days[array_rand($this->days)];
            $room = $this->rooms[array_rand($this->rooms)];
            
            // Check if this assignment would create immediate conflicts
            if (!$this->hasImmediateConflicts($course, $class, $day, $timeSlot, $room, $usedSlots)) {
                return [
                    'class_id' => $class['class_id'],
                    'class_name' => $class['class_name'],
                    'course_id' => $course['course_id'],
                    'course_name' => $course['course_name'],
                    'lecturer_id' => $course['lecturer_id'],
                    'lecturer_name' => $course['lecturer_name'],
                    'time_slot' => $timeSlot,
                    'day' => $day,
                    'room_id' => $room['room_id'],
                    'room_name' => $room['room_name'],
                    'room_capacity' => $room['capacity'] ?? 30,
                    'class_size' => $class['class_size'] ?? 25
                ];
            }
            $attempts++;
        }
        
        // If no valid assignment found, return a random one (will be penalized by fitness function)
        $timeSlot = $this->timeSlots[array_rand($this->timeSlots)];
        $day = $this->days[array_rand($this->days)];
        $room = $this->rooms[array_rand($this->rooms)];
        
        return [
            'class_id' => $class['class_id'],
            'class_name' => $class['class_name'],
            'course_id' => $course['course_id'],
            'course_name' => $course['course_name'],
            'lecturer_id' => $course['lecturer_id'],
            'lecturer_name' => $course['lecturer_name'],
            'time_slot' => $timeSlot,
            'day' => $day,
            'room_id' => $room['room_id'],
            'room_name' => $room['room_name'],
            'room_capacity' => $room['capacity'] ?? 30,
            'class_size' => $class['class_size'] ?? 25
        ];
    }

    /**
     * Check for immediate conflicts during assignment
     */
    private function hasImmediateConflicts($course, $class, $day, $timeSlot, $room, $usedSlots) {
        $key = $day . '-' . $timeSlot;
        
        // Check class conflicts
        if (isset($usedSlots['class'][$class['class_id']][$key])) {
            return true;
        }
        
        // Check lecturer conflicts
        if (isset($usedSlots['lecturer'][$course['lecturer_id']][$key])) {
            return true;
        }
        
        // Check room conflicts
        if (isset($usedSlots['room'][$room['room_id']][$key])) {
            return true;
        }
        
        return false;
    }

    /**
     * Mark a slot as used to prevent immediate conflicts
     */
    private function markSlotAsUsed($assignment, &$usedSlots) {
        $key = $assignment['day'] . '-' . $assignment['time_slot'];
        
        if (!isset($usedSlots['class'])) $usedSlots['class'] = [];
        if (!isset($usedSlots['lecturer'])) $usedSlots['lecturer'] = [];
        if (!isset($usedSlots['room'])) $usedSlots['room'] = [];
        
        $usedSlots['class'][$assignment['class_id']][$key] = true;
        $usedSlots['lecturer'][$assignment['lecturer_id']][$key] = true;
        $usedSlots['room'][$assignment['room_id']][$key] = true;
    }

    /**
     * Enhanced fitness function with proper constraint validation
     */
    private function fitness($timetable) {
        // Check cache first
        $cacheKey = md5(serialize($timetable));
        if (isset($this->fitnessCache[$cacheKey])) {
            return $this->fitnessCache[$cacheKey];
        }
        
        $totalPenalty = 0;
        $constraintViolations = [];
        
        // Initialize tracking structures
        $schedule = [];
        $roomSchedule = [];
        $lecturerSchedule = [];
        $dailyCount = [];
        $lecturerDailyCount = [];
        
        // Check each assignment for constraint violations
        foreach ($timetable as $entry) {
            $day = $entry['day'];
            $timeSlot = $entry['time_slot'];
            $classId = $entry['class_id'];
            $lecturerId = $entry['lecturer_id'];
            $roomId = $entry['room_id'];
            
            // Hard constraints
            $this->checkHardConstraints($entry, $schedule, $roomSchedule, $lecturerSchedule, $constraintViolations, $totalPenalty);
            
            // Track daily counts
            $this->trackDailyCounts($entry, $dailyCount, $lecturerDailyCount);
        }
        
        // Check soft constraints
        $this->checkSoftConstraints($dailyCount, $lecturerDailyCount, $constraintViolations, $totalPenalty);
        
        // Calculate fitness (higher is better, so invert penalty)
        $fitness = 1 / (1 + $totalPenalty);
        
        // Cache the result
        $this->fitnessCache[$cacheKey] = $fitness;
        
        return $fitness;
    }

    /**
     * Check hard constraints that must be satisfied
     */
    private function checkHardConstraints($entry, &$schedule, &$roomSchedule, &$lecturerSchedule, &$constraintViolations, &$totalPenalty) {
        $day = $entry['day'];
        $timeSlot = $entry['time_slot'];
        $classId = $entry['class_id'];
        $lecturerId = $entry['lecturer_id'];
        $roomId = $entry['room_id'];
        
        // Class conflict check
        $classKey = $classId . '-' . $day . '-' . $timeSlot;
            if (isset($schedule[$classKey])) {
            $totalPenalty += $this->constraints['hard']['class_conflict']['weight'];
            $constraintViolations[] = $this->constraints['hard']['class_conflict']['description'];
            } else {
                $schedule[$classKey] = $entry;
            }
        
        // Lecturer conflict check
        $lecturerKey = $lecturerId . '-' . $day . '-' . $timeSlot;
            if (isset($lecturerSchedule[$lecturerKey])) {
            $totalPenalty += $this->constraints['hard']['lecturer_conflict']['weight'];
            $constraintViolations[] = $this->constraints['hard']['lecturer_conflict']['description'];
            } else {
                $lecturerSchedule[$lecturerKey] = $entry;
            }
        
        // Room conflict check
        $roomKey = $roomId . '-' . $day . '-' . $timeSlot;
            if (isset($roomSchedule[$roomKey])) {
            $totalPenalty += $this->constraints['hard']['room_conflict']['weight'];
            $constraintViolations[] = $this->constraints['hard']['room_conflict']['description'];
            } else {
                $roomSchedule[$roomKey] = $entry;
            }
        
        // Room capacity check
        if (isset($entry['room_capacity']) && isset($entry['class_size'])) {
            if ($entry['room_capacity'] < $entry['class_size']) {
                $totalPenalty += $this->constraints['hard']['room_capacity']['weight'];
                $constraintViolations[] = $this->constraints['hard']['room_capacity']['description'];
            }
        }
    }

    /**
     * Track daily counts for soft constraint checking
     */
    private function trackDailyCounts($entry, &$dailyCount, &$lecturerDailyCount) {
        $day = $entry['day'];
        $classId = $entry['class_id'];
        $lecturerId = $entry['lecturer_id'];
        
        // Class daily count
        $classDayKey = $classId . '-' . $day;
        if (!isset($dailyCount[$classDayKey])) {
            $dailyCount[$classDayKey] = 0;
        }
        $dailyCount[$classDayKey]++;
        
        // Lecturer daily count
        $lecturerDayKey = $lecturerId . '-' . $day;
        if (!isset($lecturerDailyCount[$lecturerDayKey])) {
            $lecturerDailyCount[$lecturerDayKey] = 0;
        }
        $lecturerDailyCount[$lecturerDayKey]++;
    }

    /**
     * Check soft constraints that are preferred but not required
     */
    private function checkSoftConstraints($dailyCount, $lecturerDailyCount, &$constraintViolations, &$totalPenalty) {
        // Check daily overload for classes
        foreach ($dailyCount as $count) {
            if ($count > 3) {
                $penalty = ($count - 3) * $this->constraints['soft']['daily_overload']['weight'];
                $totalPenalty += $penalty;
                $constraintViolations[] = $this->constraints['soft']['daily_overload']['description'];
            }
        }
        
        // Check lecturer workload
        foreach ($lecturerDailyCount as $count) {
            if ($count > 4) {
                $penalty = ($count - 4) * $this->constraints['soft']['lecturer_workload']['weight'];
                $totalPenalty += $penalty;
                $constraintViolations[] = $this->constraints['soft']['lecturer_workload']['description'];
            }
        }
    }

    /**
     * Tournament selection with elitism
     */
    private function selection() {
        // 80% chance of tournament selection, 20% chance of selecting from top 10%
        if (mt_rand() / mt_getrandmax() < 0.8) {
        $i = rand(0, $this->populationSize - 1);
        $j = rand(0, $this->populationSize - 1);
        $scoreI = $this->currentFitnessScores[$i] ?? $this->fitness($this->population[$i]);
        $scoreJ = $this->currentFitnessScores[$j] ?? $this->fitness($this->population[$j]);
        return ($scoreI > $scoreJ)
            ? $this->population[$i]
            : $this->population[$j];
        } else {
            // Select from top 10% of population
            $topCount = max(1, intval($this->populationSize * 0.1));
            $topIndex = rand(0, $topCount - 1);
            return $this->population[$topIndex];
        }
    }

    /**
     * Smart crossover that preserves constraint satisfaction
     */
    private function crossover($parent1, $parent2) {
        $child = [];
        $count = count($parent1);
        $crossoverPoint = rand(0, $count - 1);
        
        // Take first part from parent1
        for ($i = 0; $i < $crossoverPoint; $i++) {
            $child[] = $parent1[$i];
        }
        
        // Take second part from parent2, but check for conflicts
        for ($i = $crossoverPoint; $i < $count; $i++) {
            $entry = $parent2[$i];
            
            // Check if this entry would create conflicts with existing entries
            if (!$this->wouldCreateConflicts($entry, $child)) {
                $child[] = $entry;
            } else {
                // Try to find a valid alternative
                $alternative = $this->findAlternativeAssignment($entry, $child);
                if ($alternative) {
                    $child[] = $alternative;
                } else {
                    // If no alternative found, use parent1's entry
                    $child[] = $parent1[$i];
                }
            }
        }
        
        return $child;
    }

    /**
     * Check if adding an entry would create conflicts
     */
    private function wouldCreateConflicts($entry, $timetable) {
        foreach ($timetable as $existing) {
            if ($existing['day'] === $entry['day'] && $existing['time_slot'] === $entry['time_slot']) {
                if ($existing['class_id'] === $entry['class_id'] ||
                    $existing['lecturer_id'] === $entry['lecturer_id'] ||
                    $existing['room_id'] === $entry['room_id']) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Find an alternative assignment that doesn't conflict
     */
    private function findAlternativeAssignment($entry, $timetable) {
        $attempts = 0;
        $maxAttempts = 20;
        
        while ($attempts < $maxAttempts) {
            $newTimeSlot = $this->timeSlots[array_rand($this->timeSlots)];
            $newDay = $this->days[array_rand($this->days)];
            $newRoom = $this->rooms[array_rand($this->rooms)];
            
            $alternative = $entry;
            $alternative['time_slot'] = $newTimeSlot;
            $alternative['day'] = $newDay;
            $alternative['room_id'] = $newRoom['room_id'];
            $alternative['room_name'] = $newRoom['room_name'];
            
            if (!$this->wouldCreateConflicts($alternative, $timetable)) {
                return $alternative;
            }
            $attempts++;
        }
        
        return null;
    }

    /**
     * Smart mutation that tries to improve constraint satisfaction
     */
    private function mutation($timetable, $mutationRate = 0.1) {
        foreach ($timetable as &$entry) {
            if (mt_rand() / mt_getrandmax() < $mutationRate) {
                // Try to find a better assignment
                $betterAssignment = $this->findBetterAssignment($entry, $timetable);
                if ($betterAssignment) {
                    $entry = $betterAssignment;
                } else {
                    // Fall back to random mutation
                $entry['time_slot'] = $this->timeSlots[array_rand($this->timeSlots)];
                $entry['day'] = $this->days[array_rand($this->days)];
                $room = $this->rooms[array_rand($this->rooms)];
                $entry['room_id'] = $room['room_id'];
                $entry['room_name'] = $room['room_name'];
                }
            }
        }
        return $timetable;
    }

    /**
     * Try to find a better assignment that reduces conflicts
     */
    private function findBetterAssignment($entry, $timetable) {
        $currentConflicts = $this->countConflictsForEntry($entry, $timetable);
        
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $newTimeSlot = $this->timeSlots[array_rand($this->timeSlots)];
            $newDay = $this->days[array_rand($this->days)];
            $newRoom = $this->rooms[array_rand($this->rooms)];
            
            $newEntry = $entry;
            $newEntry['time_slot'] = $newTimeSlot;
            $newEntry['day'] = $newDay;
            $newEntry['room_id'] = $newRoom['room_id'];
            $newEntry['room_name'] = $newRoom['room_name'];
            
            $newConflicts = $this->countConflictsForEntry($newEntry, $timetable);
            
            if ($newConflicts < $currentConflicts) {
                return $newEntry;
            }
        }
        
        return null;
    }

    /**
     * Count conflicts for a specific entry
     */
    private function countConflictsForEntry($entry, $timetable) {
        $conflicts = 0;
        
        foreach ($timetable as $other) {
            if ($other === $entry) continue;
            
            if ($other['day'] === $entry['day'] && $other['time_slot'] === $entry['time_slot']) {
                if ($other['class_id'] === $entry['class_id'] ||
                    $other['lecturer_id'] === $entry['lecturer_id'] ||
                    $other['room_id'] === $entry['room_id']) {
                    $conflicts++;
                    // Early exit to save time on large populations
                    if ($conflicts >= 10) {
                        return $conflicts;
                    }
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Evolve the population with elitism
     */
    public function evolve($generations = 100) {
        $bestFitness = 0;
        $bestTimetable = null;
        $startTime = microtime(true);
        
        for ($generation = 0; $generation < $generations; $generation++) {
            // Evaluate once and sort by score descending
            $scores = [];
            $count = count($this->population);
            for ($i = 0; $i < $count; $i++) {
                $scores[$i] = $this->fitness($this->population[$i]);
            }
            // Sort scores and population together
            array_multisort($scores, SORT_DESC, $this->population);
            $this->currentFitnessScores = $scores;
            
            // Keep track of best solution
            $currentBestFitness = $scores[0] ?? 0;
            if ($currentBestFitness > $bestFitness) {
                $bestFitness = $currentBestFitness;
                $bestTimetable = $this->population[0];
            }
            
            // Elitism: keep top 10% of solutions
            $eliteCount = max(1, intval($this->populationSize * 0.1));
            $newPopulation = array_slice($this->population, 0, $eliteCount);
            
            // Generate rest of population
            while (count($newPopulation) < $this->populationSize) {
                $parent1 = $this->selection();
                $parent2 = $this->selection();
                $child = $this->crossover($parent1, $parent2);
                $child = $this->mutation($child, 0.05);
                $newPopulation[] = $child;
            }
            
            $this->population = $newPopulation;
            
            // Clear fitness cache periodically to save memory
            if ($generation % 20 === 0) {
                $this->fitnessCache = [];
            }

            // Report progress if callback is set
            if ($this->progressCallback) {
                try {
                    call_user_func($this->progressCallback, $generation + 1, $generations, $bestFitness);
                } catch (\Throwable $e) {
                    // Ignore progress callback errors
                }
            }

            // Respect optional time budget to avoid timeouts
            if ($this->timeBudgetSeconds > 0) {
                if ((microtime(true) - $startTime) >= $this->timeBudgetSeconds) {
                    break;
                }
            }
        }
        
        // Return the best timetable found during evolution
        return $bestTimetable ?: $this->population[0];
    }

    /**
     * Get constraint violation report for a timetable
     */
    public function getConstraintReport($timetable) {
        $violations = [];
        $totalPenalty = 0;
        
        // This would implement the same logic as the fitness function
        // but return detailed violation information instead of just a score
        
        return [
            'violations' => $violations,
            'total_penalty' => $totalPenalty,
            'fitness_score' => $this->fitness($timetable)
        ];
    }
}
?>
