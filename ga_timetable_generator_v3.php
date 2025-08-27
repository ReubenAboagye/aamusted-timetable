<?php

class GeneticAlgorithmV3 {
    private $classes;           // Array of classes from the 'classes' table
    private $courses;           // Array of courses from the 'courses' table
    private $rooms;             // Array of available rooms from the 'rooms' table
    private $lecturers;         // Array of lecturers with their constraints
    private $timeSlots;         // Available time slots from the 'time_slots' table
    private $workingDays;       // Working days from the 'working_days' table
    private $sessions;          // Available sessions (Regular, Evening, Weekend, etc.)
    private $population;        // Current population of timetables
    private $populationSize;    // Size of the population
    private $constraints;       // Constraint definitions
    private $fitnessCache;      // Cache for fitness calculations
    private $sessionConstraints; // Session-specific constraint definitions

    public function __construct($classes, $courses, $rooms, $lecturers, $timeSlots, $workingDays, $sessions) {
        $this->classes = $classes;
        $this->courses = $courses;
        $this->rooms = $rooms;
        $this->lecturers = $lecturers;
        $this->timeSlots = $timeSlots;
        $this->workingDays = $workingDays;
        $this->sessions = $sessions;
        
        $this->population = [];
        $this->fitnessCache = [];
        
        // Initialize constraint definitions
        $this->initializeConstraints();
        $this->initializeSessionConstraints();
    }

    /**
     * Initialize constraint definitions with weights
     */
    private function initializeConstraints() {
        $this->constraints = [
            'hard' => [
                'class_conflict' => ['weight' => 1000, 'description' => 'Class cannot have multiple courses at same time'],
                'lecturer_conflict' => ['weight' => 1000, 'description' => 'Lecturer cannot teach multiple courses at same time'],
                'room_conflict' => ['weight' => 1000, 'description' => 'Room cannot be used for multiple courses at same time'],
                'room_capacity' => ['weight' => 800, 'description' => 'Room capacity insufficient for class size'],
                'room_type_mismatch' => ['weight' => 600, 'description' => 'Room type unsuitable for course requirements'],
                'time_slot_invalid' => ['weight' => 1000, 'description' => 'Time slot not available for the session'],
                'working_day_violation' => ['weight' => 1000, 'description' => 'Course scheduled on non-working day'],
                'session_violation' => ['weight' => 1000, 'description' => 'Course scheduled in inappropriate session'],
                'cross_session_conflict' => ['weight' => 1200, 'description' => 'Conflict between different sessions']
            ],
            'soft' => [
                'daily_overload' => ['weight' => 50, 'description' => 'More than 3 courses per day for a class'],
                'lecturer_workload' => ['weight' => 25, 'description' => 'Lecturer has too many courses in one day'],
                'room_preference' => ['weight' => 20, 'description' => 'Room not preferred for course type'],
                'time_preference' => ['weight' => 15, 'description' => 'Time slot not preferred for course type'],
                'consecutive_breaks' => ['weight' => 30, 'description' => 'Long gaps between classes for same class'],
                'session_preference' => ['weight' => 40, 'description' => 'Course not in preferred session type'],
                'lecturer_session_overload' => ['weight' => 60, 'description' => 'Lecturer teaching in too many different sessions']
            ]
        ];
    }

    /**
     * Initialize session-specific constraint definitions
     */
    private function initializeSessionConstraints() {
        $this->sessionConstraints = [
            'regular' => [
                'hours' => ['start' => '08:00', 'end' => '18:00'],
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'max_daily_courses' => 4,
                'max_weekly_hours' => 30,
                'preferred_room_types' => ['lecture_hall', 'classroom', 'laboratory'],
                'break_patterns' => ['12:00-13:00', '15:00-15:15']
            ],
            'evening' => [
                'hours' => ['start' => '18:00', 'end' => '22:00'],
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'max_daily_courses' => 2,
                'max_weekly_hours' => 15,
                'preferred_room_types' => ['lecture_hall', 'classroom'],
                'break_patterns' => ['19:30-19:45'],
                'intensive_courses' => true
            ],
            'weekend' => [
                'hours' => ['start' => '09:00', 'end' => '17:00'],
                'days' => ['saturday', 'sunday'],
                'max_daily_courses' => 3,
                'max_weekly_hours' => 12,
                'preferred_room_types' => ['lecture_hall', 'classroom', 'seminar_room'],
                'break_patterns' => ['12:00-13:00'],
                'intensive_courses' => true
            ],
            'sandwich' => [
                'hours' => ['start' => '08:00', 'end' => '18:00'],
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'max_daily_courses' => 2,
                'max_weekly_hours' => 20,
                'preferred_room_types' => ['laboratory', 'computer_lab', 'seminar_room'],
                'break_patterns' => ['12:00-13:00'],
                'practical_focus' => true,
                'industry_partnerships' => true
            ],
            'distance' => [
                'hours' => ['start' => '09:00', 'end' => '21:00'],
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                'max_daily_courses' => 1,
                'max_weekly_hours' => 10,
                'preferred_room_types' => ['computer_lab', 'seminar_room'],
                'break_patterns' => [],
                'online_components' => true,
                'flexible_scheduling' => true
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
     * Create a random timetable with session-aware constraint checking
     */
    private function createRandomTimetable() {
        $timetable = [];
        $usedSlots = []; // Track used slots to avoid immediate conflicts
        
        // Group courses by session type
        $coursesBySession = $this->groupCoursesBySession();
        
        foreach ($this->sessions as $session) {
            if (!$session['is_active']) continue;
            
            $sessionCourses = $coursesBySession[$session['session_type']] ?? [];
            $sessionTimetable = $this->createSessionTimetable($session, $sessionCourses, $usedSlots);
            $timetable = array_merge($timetable, $sessionTimetable);
        }
        
        return $timetable;
    }

    /**
     * Group courses by their preferred session types
     */
    private function groupCoursesBySession() {
        $grouped = [];
        
        foreach ($this->courses as $course) {
            $sessionAvailability = json_decode($course['session_availability'] ?? '[]', true);
            
            if (empty($sessionAvailability)) {
                // Default to regular session if no preference specified
                $grouped['regular'][] = $course;
            } else {
                foreach ($sessionAvailability as $sessionType) {
                    $grouped[$sessionType][] = $course;
                }
            }
        }
        
        return $grouped;
    }

    /**
     * Create timetable for a specific session
     */
    private function createSessionTimetable($session, $courses, &$usedSlots) {
        $sessionTimetable = [];
        $sessionConstraints = $this->sessionConstraints[$session['session_type']] ?? [];
        
        foreach ($this->classes as $class) {
            // Check if class can participate in this session
            if (!$this->canClassParticipateInSession($class, $session)) {
                continue;
            }
            
            foreach ($courses as $course) {
                // Check if this course is assigned to this class
                if ($this->isCourseAssignedToClass($course['id'], $class['id'])) {
                    $assignment = $this->findValidSessionAssignment($course, $class, $session, $usedSlots);
                    if ($assignment) {
                        $sessionTimetable[] = $assignment;
                        $this->markSlotAsUsed($assignment, $usedSlots);
                    }
                }
            }
        }
        
        return $sessionTimetable;
    }

    /**
     * Check if a class can participate in a specific session
     */
    private function canClassParticipateInSession($class, $session) {
        $sessionPreferences = json_decode($class['session_preferences'] ?? '[]', true);
        
        if (empty($sessionPreferences)) {
            // Default: all classes can participate in regular sessions
            return $session['session_type'] === 'regular';
        }
        
        return in_array($session['session_type'], $sessionPreferences);
    }

    /**
     * Find a valid assignment for a course within a specific session
     */
    private function findValidSessionAssignment($course, $class, $session, $usedSlots) {
        $attempts = 0;
        $maxAttempts = 50;
        $sessionConstraints = $this->sessionConstraints[$session['session_type']] ?? [];
        
        while ($attempts < $maxAttempts) {
            $timeSlot = $this->findValidTimeSlotForSession($session, $sessionConstraints);
            $day = $this->findValidDayForSession($session, $sessionConstraints);
            $room = $this->findValidRoomForSession($course, $session, $sessionConstraints);
            
            if (!$timeSlot || !$day || !$room) {
                $attempts++;
                continue;
            }
            
            // Check if this assignment would create immediate conflicts
            if (!$this->hasImmediateConflicts($course, $class, $day, $timeSlot, $room, $usedSlots, $session)) {
                return [
                    'class_id' => $class['id'],
                    'class_name' => $class['name'],
                    'course_id' => $course['id'],
                    'course_name' => $course['name'],
                    'room_id' => $room['id'],
                    'room_name' => $room['name'],
                    'time_slot_id' => $timeSlot['id'],
                    'time_slot' => $timeSlot['start_time'] . '-' . $timeSlot['end_time'],
                    'day' => $day['day'],
                    'room_capacity' => $room['capacity'],
                    'room_type' => $room['room_type'],
                    'class_size' => $class['student_count'],
                    'lecturer_ids' => $this->getCourseLecturers($course['id']),
                    'session_id' => $session['id'],
                    'session_type' => $session['session_type']
                ];
            }
            $attempts++;
        }
        
        // If no valid assignment found, return a random one (will be penalized by fitness function)
        return $this->createFallbackAssignment($course, $class, $session, $usedSlots);
    }

    /**
     * Find a valid time slot for a specific session
     */
    private function findValidTimeSlotForSession($session, $sessionConstraints) {
        $validSlots = [];
        
        foreach ($this->timeSlots as $slot) {
            if ($slot['session_id'] == $session['id']) {
                // Check if slot is session-specific
                if ($slot['session_specific']) {
                    $restrictions = json_decode($slot['session_restrictions'] ?? '[]', true);
                    if (!in_array($session['session_type'], $restrictions)) {
                        continue;
                    }
                }
                
                // Check if slot fits session hours
                $slotStart = strtotime($slot['start_time']);
                $sessionStart = strtotime($sessionConstraints['hours']['start'] ?? '08:00');
                $sessionEnd = strtotime($sessionConstraints['hours']['end'] ?? '18:00');
                
                if ($slotStart >= $sessionStart && $slotStart < $sessionEnd) {
                    $validSlots[] = $slot;
                }
            }
        }
        
        return !empty($validSlots) ? $validSlots[array_rand($validSlots)] : null;
    }

    /**
     * Find a valid day for a specific session
     */
    private function findValidDayForSession($session, $sessionConstraints) {
        $validDays = [];
        
        foreach ($this->workingDays as $workingDay) {
            if ($workingDay['session_id'] == $session['id'] && $workingDay['is_active']) {
                $sessionDays = $sessionConstraints['days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
                
                if (in_array($workingDay['day'], $sessionDays)) {
                    $validDays[] = $workingDay;
                }
            }
        }
        
        return !empty($validDays) ? $validDays[array_rand($validDays)] : null;
    }

    /**
     * Find a valid room for a course within a session
     */
    private function findValidRoomForSession($course, $session, $sessionConstraints) {
        $validRooms = [];
        $preferredRoomTypes = $sessionConstraints['preferred_room_types'] ?? ['lecture_hall', 'classroom'];
        
        foreach ($this->rooms as $room) {
            // Check if room is available for this session
            $sessionAvailability = json_decode($room['session_availability'] ?? '[]', true);
            if (!empty($sessionAvailability) && !in_array($session['session_type'], $sessionAvailability)) {
                continue;
            }
            
            // Check if room type is preferred for this session
            if (in_array($room['room_type'], $preferredRoomTypes)) {
                $validRooms[] = $room;
            }
        }
        
        return !empty($validRooms) ? $validRooms[array_rand($validRooms)] : $this->rooms[array_rand($this->rooms)];
    }

    /**
     * Create a fallback assignment when no valid assignment is found
     */
    private function createFallbackAssignment($course, $class, $session, $usedSlots) {
        $timeSlot = $this->timeSlots[array_rand($this->timeSlots)];
        $day = $this->workingDays[array_rand($this->workingDays)];
        $room = $this->rooms[array_rand($this->rooms)];
        
        return [
            'class_id' => $class['id'],
            'class_name' => $class['name'],
            'course_id' => $course['id'],
            'course_name' => $course['name'],
            'room_id' => $room['id'],
            'room_name' => $room['name'],
            'time_slot_id' => $timeSlot['id'],
            'time_slot' => $timeSlot['start_time'] . '-' . $timeSlot['end_time'],
            'day' => $day['day'],
            'room_capacity' => $room['capacity'],
            'room_type' => $room['room_type'],
            'class_size' => $class['student_count'],
            'lecturer_ids' => $this->getCourseLecturers($course['id']),
            'session_id' => $session['id'],
            'session_type' => $session['session_type']
        ];
    }

    /**
     * Check for immediate conflicts during assignment (including session awareness)
     */
    private function hasImmediateConflicts($course, $class, $day, $timeSlot, $room, $usedSlots, $session) {
        $key = $day['day'] . '-' . $timeSlot['id'];
        
        // Check class conflicts (including cross-session)
        if (isset($usedSlots['class'][$class['id']][$key])) {
            return true;
        }
        
        // Check room conflicts (including cross-session)
        if (isset($usedSlots['room'][$room['id']][$key])) {
            return true;
        }
        
        // Check lecturer conflicts (including cross-session)
        $lecturerIds = $this->getCourseLecturers($course['id']);
        foreach ($lecturerIds as $lecturerId) {
            if (isset($usedSlots['lecturer'][$lecturerId][$key])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Mark a slot as used to prevent immediate conflicts
     */
    private function markSlotAsUsed($assignment, &$usedSlots) {
        $key = $assignment['day'] . '-' . $assignment['time_slot_id'];
        
        if (!isset($usedSlots['class'])) $usedSlots['class'] = [];
        if (!isset($usedSlots['room'])) $usedSlots['room'] = [];
        if (!isset($usedSlots['lecturer'])) $usedSlots['lecturer'] = [];
        
        $usedSlots['class'][$assignment['class_id']][$key] = true;
        $usedSlots['room'][$assignment['room_id']][$key] = true;
        
        // Mark lecturer slots as used
        foreach ($assignment['lecturer_ids'] as $lecturerId) {
            $usedSlots['lecturer'][$lecturerId][$key] = true;
        }
    }

    /**
     * Enhanced fitness function with session-aware constraint validation
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
        $sessionCounts = [];
        
        // Check each assignment for constraint violations
        foreach ($timetable as $entry) {
            $day = $entry['day'];
            $timeSlotId = $entry['time_slot_id'];
            $classId = $entry['class_id'];
            $roomId = $entry['room_id'];
            $lecturerIds = $entry['lecturer_ids'];
            $sessionType = $entry['session_type'];
            
            // Hard constraints
            $this->checkHardConstraints($entry, $schedule, $roomSchedule, $lecturerSchedule, $constraintViolations, $totalPenalty);
            
            // Track daily counts
            $this->trackDailyCounts($entry, $dailyCount, $lecturerDailyCount);
            
            // Track session counts
            $this->trackSessionCounts($entry, $sessionCounts);
        }
        
        // Check soft constraints
        $this->checkSoftConstraints($dailyCount, $lecturerDailyCount, $sessionCounts, $constraintViolations, $totalPenalty);
        
        // Check cross-session conflicts
        $this->checkCrossSessionConflicts($timetable, $constraintViolations, $totalPenalty);
        
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
        $timeSlotId = $entry['time_slot_id'];
        $classId = $entry['class_id'];
        $roomId = $entry['room_id'];
        $lecturerIds = $entry['lecturer_ids'];
        $sessionType = $entry['session_type'];
        
        // Class conflict check
        $classKey = $classId . '-' . $day . '-' . $timeSlotId;
        if (isset($schedule[$classKey])) {
            $totalPenalty += $this->constraints['hard']['class_conflict']['weight'];
            $constraintViolations[] = $this->constraints['hard']['class_conflict']['description'];
        } else {
            $schedule[$classKey] = $entry;
        }
        
        // Room conflict check
        $roomKey = $roomId . '-' . $day . '-' . $timeSlotId;
        if (isset($roomSchedule[$roomKey])) {
            $totalPenalty += $this->constraints['hard']['room_conflict']['weight'];
            $constraintViolations[] = $this->constraints['hard']['room_conflict']['description'];
        } else {
            $roomSchedule[$roomKey] = $entry;
        }
        
        // Lecturer conflict check
        foreach ($lecturerIds as $lecturerId) {
            $lecturerKey = $lecturerId . '-' . $day . '-' . $timeSlotId;
            if (isset($lecturerSchedule[$lecturerKey])) {
                $totalPenalty += $this->constraints['hard']['lecturer_conflict']['weight'];
                $constraintViolations[] = $this->constraints['hard']['lecturer_conflict']['description'];
            } else {
                $lecturerSchedule[$lecturerKey] = $entry;
            }
        }
        
        // Room capacity check
        if (isset($entry['room_capacity']) && isset($entry['class_size'])) {
            if ($entry['room_capacity'] < $entry['class_size']) {
                $totalPenalty += $this->constraints['hard']['room_capacity']['weight'];
                $constraintViolations[] = $this->constraints['hard']['room_capacity']['description'];
            }
        }
        
        // Working day check
        $isWorkingDay = false;
        foreach ($this->workingDays as $workingDay) {
            if ($workingDay['day'] === $day && $workingDay['is_active']) {
                $isWorkingDay = true;
                break;
            }
        }
        if (!$isWorkingDay) {
            $totalPenalty += $this->constraints['hard']['working_day_violation']['weight'];
            $constraintViolations[] = $this->constraints['hard']['working_day_violation']['description'];
        }
        
        // Time slot validity check
        $isValidTimeSlot = false;
        foreach ($this->timeSlots as $timeSlot) {
            if ($timeSlot['id'] == $timeSlotId && $timeSlot['session_id'] == $entry['session_id'] && !$timeSlot['is_break']) {
                $isValidTimeSlot = true;
                break;
            }
        }
        if (!$isValidTimeSlot) {
            $totalPenalty += $this->constraints['hard']['time_slot_invalid']['weight'];
            $constraintViolations[] = $this->constraints['hard']['time_slot_invalid']['description'];
        }
        
        // Session-specific constraint check
        $this->checkSessionSpecificConstraints($entry, $constraintViolations, $totalPenalty);
    }

    /**
     * Check session-specific constraints
     */
    private function checkSessionSpecificConstraints($entry, &$constraintViolations, &$totalPenalty) {
        $sessionType = $entry['session_type'];
        $sessionConstraints = $this->sessionConstraints[$sessionType] ?? [];
        
        if (empty($sessionConstraints)) return;
        
        // Check if course duration fits session constraints
        $courseDuration = $this->getCourseDuration($entry['course_id']);
        $maxDuration = $sessionConstraints['max_daily_courses'] ?? 4;
        
        if ($courseDuration > $maxDuration) {
            $totalPenalty += $this->constraints['hard']['session_violation']['weight'];
            $constraintViolations[] = "Course duration exceeds session limit for $sessionType";
        }
        
        // Check if room type is appropriate for session
        $preferredRoomTypes = $sessionConstraints['preferred_room_types'] ?? [];
        if (!empty($preferredRoomTypes) && !in_array($entry['room_type'], $preferredRoomTypes)) {
            $totalPenalty += $this->constraints['soft']['room_preference']['weight'];
            $constraintViolations[] = "Room type not preferred for $sessionType session";
        }
    }

    /**
     * Track daily counts for soft constraint checking
     */
    private function trackDailyCounts($entry, &$dailyCount, &$lecturerDailyCount) {
        $day = $entry['day'];
        $classId = $entry['class_id'];
        $lecturerIds = $entry['lecturer_ids'];
        
        // Class daily count
        $classDayKey = $classId . '-' . $day;
        if (!isset($dailyCount[$classDayKey])) {
            $dailyCount[$classDayKey] = 0;
        }
        $dailyCount[$classDayKey]++;
        
        // Lecturer daily count
        foreach ($lecturerIds as $lecturerId) {
            $lecturerDayKey = $lecturerId . '-' . $day;
            if (!isset($lecturerDailyCount[$lecturerDayKey])) {
                $lecturerDailyCount[$lecturerDayKey] = 0;
            }
            $lecturerDailyCount[$lecturerDayKey]++;
        }
    }

    /**
     * Track session counts for lecturers
     */
    private function trackSessionCounts($entry, &$sessionCounts) {
        $lecturerIds = $entry['lecturer_ids'];
        $sessionType = $entry['session_type'];
        
        foreach ($lecturerIds as $lecturerId) {
            if (!isset($sessionCounts[$lecturerId])) {
                $sessionCounts[$lecturerId] = [];
            }
            if (!isset($sessionCounts[$lecturerId][$sessionType])) {
                $sessionCounts[$lecturerId][$sessionType] = 0;
            }
            $sessionCounts[$lecturerId][$sessionType]++;
        }
    }

    /**
     * Check soft constraints that are preferred but not required
     */
    private function checkSoftConstraints($dailyCount, $lecturerDailyCount, $sessionCounts, &$constraintViolations, &$totalPenalty) {
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
        
        // Check lecturer session overload
        foreach ($sessionCounts as $lecturerId => $sessions) {
            $totalSessions = count($sessions);
            if ($totalSessions > 3) {
                $penalty = ($totalSessions - 3) * $this->constraints['soft']['lecturer_session_overload']['weight'];
                $totalPenalty += $penalty;
                $constraintViolations[] = $this->constraints['soft']['lecturer_session_overload']['description'];
            }
        }
    }

    /**
     * Check for conflicts between different sessions
     */
    private function checkCrossSessionConflicts($timetable, &$constraintViolations, &$totalPenalty) {
        $sessionGroups = [];
        
        // Group entries by session
        foreach ($timetable as $entry) {
            $sessionType = $entry['session_type'];
            if (!isset($sessionGroups[$sessionType])) {
                $sessionGroups[$sessionType] = [];
            }
            $sessionGroups[$sessionType][] = $entry;
        }
        
        // Check conflicts between different sessions
        $sessionTypes = array_keys($sessionGroups);
        for ($i = 0; $i < count($sessionTypes); $i++) {
            for ($j = $i + 1; $j < count($sessionTypes); $j++) {
                $conflicts = $this->countCrossSessionConflicts($sessionGroups[$sessionTypes[$i]], $sessionGroups[$sessionTypes[$j]]);
                if ($conflicts > 0) {
                    $totalPenalty += $conflicts * $this->constraints['hard']['cross_session_conflict']['weight'];
                    $constraintViolations[] = "Cross-session conflicts between {$sessionTypes[$i]} and {$sessionTypes[$j]}";
                }
            }
        }
    }

    /**
     * Count conflicts between two session groups
     */
    private function countCrossSessionConflicts($session1Entries, $session2Entries) {
        $conflicts = 0;
        
        foreach ($session1Entries as $entry1) {
            foreach ($session2Entries as $entry2) {
                // Check for class conflicts across sessions
                if ($entry1['class_id'] == $entry2['class_id'] && 
                    $entry1['day'] == $entry2['day'] && 
                    $entry1['time_slot_id'] == $entry2['time_slot_id']) {
                    $conflicts++;
                }
                
                // Check for room conflicts across sessions
                if ($entry1['room_id'] == $entry2['room_id'] && 
                    $entry1['day'] == $entry2['day'] && 
                    $entry1['time_slot_id'] == $entry2['time_slot_id']) {
                    $conflicts++;
                }
                
                // Check for lecturer conflicts across sessions
                foreach ($entry1['lecturer_ids'] as $lecturerId1) {
                    foreach ($entry2['lecturer_ids'] as $lecturerId2) {
                        if ($lecturerId1 == $lecturerId2 && 
                            $entry1['day'] == $entry2['day'] && 
                            $entry1['time_slot_id'] == $entry2['time_slot_id']) {
                            $conflicts++;
                        }
                    }
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Get course duration
     */
    private function getCourseDuration($courseId) {
        foreach ($this->courses as $course) {
            if ($course['id'] == $courseId) {
                return $course['min_duration'] ?? 3;
            }
        }
        return 3; // Default duration
    }

    /**
     * Check if a course is assigned to a specific class
     */
    private function isCourseAssignedToClass($courseId, $classId) {
        // This would need to check the class_course_assignments table
        // For now, we'll assume all courses are available to all classes
        // You can implement proper checking based on your data
        return true;
    }

    /**
     * Get lecturer IDs for a course
     */
    private function getCourseLecturers($courseId) {
        // This would query the course_lecturers table
        // For now, return empty array - implement based on your data
        return [];
    }

    /**
     * Tournament selection with elitism
     */
    private function selection() {
        // 80% chance of tournament selection, 20% chance of selecting from top 10%
        if (mt_rand() / mt_getrandmax() < 0.8) {
            $i = rand(0, $this->populationSize - 1);
            $j = rand(0, $this->populationSize - 1);
            return ($this->fitness($this->population[$i]) > $this->fitness($this->population[$j]))
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
            if ($existing['day'] === $entry['day'] && $existing['time_slot_id'] === $entry['time_slot_id']) {
                if ($existing['class_id'] === $entry['class_id'] ||
                    $existing['room_id'] === $entry['room_id']) {
                    return true;
                }
                
                // Check lecturer conflicts
                foreach ($entry['lecturer_ids'] as $lecturerId) {
                    foreach ($existing['lecturer_ids'] as $existingLecturerId) {
                        if ($lecturerId === $existingLecturerId) {
                            return true;
                        }
                    }
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
            $newDay = $this->workingDays[array_rand($this->workingDays)];
            $newRoom = $this->rooms[array_rand($this->rooms)];
            
            $alternative = $entry;
            $alternative['time_slot_id'] = $newTimeSlot['id'];
            $alternative['time_slot'] = $newTimeSlot['start_time'] . '-' . $newTimeSlot['end_time'];
            $alternative['day'] = $newDay['day'];
            $alternative['room_id'] = $newRoom['id'];
            $alternative['room_name'] = $newRoom['name'];
            
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
                    $newTimeSlot = $this->timeSlots[array_rand($this->timeSlots)];
                    $newDay = $this->workingDays[array_rand($this->workingDays)];
                    $newRoom = $this->rooms[array_rand($this->rooms)];
                    
                    $entry['time_slot_id'] = $newTimeSlot['id'];
                    $entry['time_slot'] = $newTimeSlot['start_time'] . '-' . $newTimeSlot['end_time'];
                    $entry['day'] = $newDay['day'];
                    $entry['room_id'] = $newRoom['id'];
                    $entry['room_name'] = $newRoom['name'];
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
            $newDay = $this->workingDays[array_rand($this->workingDays)];
            $newRoom = $this->rooms[array_rand($this->rooms)];
            
            $newEntry = $entry;
            $newEntry['time_slot_id'] = $newTimeSlot['id'];
            $newEntry['time_slot'] = $newTimeSlot['start_time'] . '-' . $newTimeSlot['end_time'];
            $newEntry['day'] = $newDay['day'];
            $newEntry['room_id'] = $newRoom['id'];
            $newEntry['room_name'] = $newRoom['name'];
            
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
            
            if ($other['day'] === $entry['day'] && $other['time_slot_id'] === $entry['time_slot_id']) {
                if ($other['class_id'] === $entry['class_id'] ||
                    $other['room_id'] === $entry['room_id']) {
                    $conflicts++;
                }
                
                // Check lecturer conflicts
                foreach ($entry['lecturer_ids'] as $lecturerId) {
                    foreach ($other['lecturer_ids'] as $otherLecturerId) {
                        if ($lecturerId === $otherLecturerId) {
                            $conflicts++;
                        }
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
        
        for ($generation = 0; $generation < $generations; $generation++) {
            // Sort population by fitness (best first)
            usort($this->population, function($a, $b) {
                return $this->fitness($b) <=> $this->fitness($a);
            });
            
            // Keep track of best solution
            $currentBestFitness = $this->fitness($this->population[0]);
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
                $child = $this->mutation($child);
                $newPopulation[] = $child;
            }
            
            $this->population = $newPopulation;
            
            // Clear fitness cache periodically to save memory
            if ($generation % 20 === 0) {
                $this->fitnessCache = [];
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

    /**
     * Get session-specific statistics
     */
    public function getSessionStatistics($timetable) {
        $stats = [];
        
        foreach ($this->sessions as $session) {
            $sessionEntries = array_filter($timetable, function($entry) use ($session) {
                return $entry['session_id'] == $session['id'];
            });
            
            $stats[$session['session_type']] = [
                'session_name' => $session['name'],
                'total_courses' => count($sessionEntries),
                'total_classes' => count(array_unique(array_column($sessionEntries, 'class_id'))),
                'total_rooms' => count(array_unique(array_column($sessionEntries, 'room_id'))),
                'fitness_score' => $this->fitness($sessionEntries)
            ];
        }
        
        return $stats;
    }
}
?>

