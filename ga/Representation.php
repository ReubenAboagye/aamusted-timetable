<?php
/**
 * Representation helpers for GA chromosome and gene structures.
 */
class Representation {
    /**
     * Create an empty genome for given class_courses list.
     * Genome is associative array keyed by class_course_id -> gene
     */
    public static function emptyGenome(array $classCourses): array {
        $g = [];
        foreach ($classCourses as $cc) {
            $g[$cc['id']] = [
                'class_course_id' => (int)$cc['id'],
                'class_id' => (int)$cc['class_id'],
                'course_id' => (int)$cc['course_id'],
                'lecturer_id' => isset($cc['lecturer_id']) ? (int)$cc['lecturer_id'] : null,
                'day_id' => null,
                'time_slot_id' => null,
                'room_id' => null,
                'lecturer_course_id' => null,
                'division_label' => null,
            ];
        }
        return $g;
    }

    public static function randomGene(array $geneTemplate, array $days, array $timeSlots, array $rooms) {
        $gene = $geneTemplate;
        $gene['day_id'] = $days[array_rand($days)]['id'];
        $gene['time_slot_id'] = $timeSlots[array_rand($timeSlots)]['id'];
        $gene['room_id'] = $rooms[array_rand($rooms)]['id'];
        return $gene;
    }
}

?>


