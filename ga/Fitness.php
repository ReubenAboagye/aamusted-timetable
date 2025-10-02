<?php
/**
 * Fitness evaluator for timetable genomes.
 * Returns an array: ['hard' => int, 'soft' => int, 'score' => float]
 */
class Fitness {
    private $roomsById;

    public function __construct(array $rooms) {
        $this->roomsById = [];
        foreach ($rooms as $r) $this->roomsById[$r['id']] = $r;
    }

    public function evaluate(array $genome, array $classMap, array $lecturerCourseMap, array $options = []) {
        $hard = 0; $soft = 0;

        // fast lookups
        $roomSlot = [];
        $lecturerSlot = [];
        $classSlot = [];

        foreach ($genome as $gene) {
            // missing placement => heavy penalty
            if (empty($gene['day_id']) || empty($gene['time_slot_id']) || empty($gene['room_id'])) {
                $hard += 1000;
                continue;
            }
            $rkey = $gene['room_id'] . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
            if (isset($roomSlot[$rkey])) $hard++;
            else $roomSlot[$rkey] = true;

            $lkey = ($gene['lecturer_course_id'] ?? $gene['lecturer_id']) . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
            if (isset($lecturerSlot[$lkey])) $hard++;
            else $lecturerSlot[$lkey] = true;

            // Check if this is a combined assignment
            $isCombined = $gene['is_combined'] ?? false;
            $combinedClasses = $gene['combined_classes'] ?? [];
            
            if ($isCombined && !empty($combinedClasses)) {
                // Handle combined assignment - check conflicts for all classes
                foreach ($combinedClasses as $classCourse) {
                    $classId = $classCourse['class_id'];
                    $ckey = $classId . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
                    if (isset($classSlot[$ckey])) $hard++;
                    else $classSlot[$ckey] = true;
                }
                
                // Room capacity check for combined classes
                $totalClassSize = 0;
                foreach ($combinedClasses as $classCourse) {
                    $classId = $classCourse['class_id'];
                    $totalClassSize += $classMap[$classId]['total_capacity'] ?? 0;
                }
                $roomCap = $this->roomsById[$gene['room_id']]['capacity'] ?? 0;
                if ($roomCap < $totalClassSize) $soft += 10;
                
                // Bonus for successful combination (reduces soft penalty)
                if ($roomCap >= $totalClassSize) {
                    $soft -= 5;
                    // Additional bonus for larger combinations (more efficient room usage)
                    $combinationSize = count($combinedClasses);
                    if ($combinationSize >= 3) $soft -= 3; // Bonus for 3+ classes
                    if ($combinationSize >= 4) $soft -= 2; // Extra bonus for 4+ classes
                    
                    // Bonus for efficient room utilization
                    $utilizationRatio = $totalClassSize / $roomCap;
                    if ($utilizationRatio >= 0.8) $soft -= 2; // High utilization bonus
                    if ($utilizationRatio >= 0.9) $soft -= 1; // Very high utilization bonus
                }
            } else {
                // Handle individual assignment
                $ckey = $gene['class_id'] . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
                if (isset($classSlot[$ckey])) $hard++;
                else $classSlot[$ckey] = true;

                // room capacity soft check
                $classSize = $classMap[$gene['class_id']]['total_capacity'] ?? 0;
                $roomCap = $this->roomsById[$gene['room_id']]['capacity'] ?? 0;
                if ($roomCap < $classSize) $soft += 10;
            }
        }

        // score: lower is better; combine
        $score = $hard * 1000 + $soft;
        return ['hard' => $hard, 'soft' => $soft, 'score' => $score];
    }
}

?>


