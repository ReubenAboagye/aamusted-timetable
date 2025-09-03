<?php
/**
 * Enhanced Fitness Evaluator for Genetic Algorithm
 * 
 * This class provides comprehensive fitness evaluation for timetable solutions
 * in the genetic algorithm, working with the new constraint checker.
 */

class FitnessEvaluator {
    private $constraintChecker;
    private $data;
    
    public function __construct(array $data) {
        $this->data = $data;
        $this->constraintChecker = new ConstraintChecker($data);
    }
    
    /**
     * Evaluate the fitness of a timetable solution
     */
    public function evaluate(array $individual): array {
        return $this->constraintChecker->evaluateFitness($individual);
    }
    
    /**
     * Get detailed fitness breakdown
     */
    public function getDetailedFitness(array $individual): array {
        $fitness = $this->evaluate($individual);
        $violationReport = $this->constraintChecker->getViolationReport($individual);
        
        return [
            'fitness' => $fitness,
            'violations' => $violationReport,
            'summary' => $this->generateFitnessSummary($fitness, $violationReport),
            'details' => $this->generateFitnessDetails($individual, $fitness)
        ];
    }
    
    /**
     * Generate fitness summary
     */
    private function generateFitnessSummary(array $fitness, array $violations): array {
        return [
            'total_score' => $fitness['total_score'],
            'hard_score' => $fitness['hard_score'],
            'soft_score' => $fitness['soft_score'],
            'is_feasible' => $fitness['is_feasible'],
            'hard_violations_count' => $violations['hard_count'],
            'soft_violations_count' => $violations['soft_count'],
            'total_violations_count' => $violations['total_violations'],
            'quality_percentage' => $this->calculateQualityPercentage($fitness)
        ];
    }
    
    /**
     * Generate detailed fitness information
     */
    private function generateFitnessDetails(array $individual, array $fitness): array {
        $details = [
            'assignment_stats' => $this->getAssignmentStats($individual),
            'conflict_analysis' => $this->getConflictAnalysis($individual),
            'distribution_analysis' => $this->getDistributionAnalysis($individual),
            'capacity_analysis' => $this->getCapacityAnalysis($individual)
        ];
        
        return $details;
    }
    
    /**
     * Get assignment statistics
     */
    private function getAssignmentStats(array $individual): array {
        $totalAssignments = count($individual);
        $validAssignments = 0;
        $invalidAssignments = 0;
        
        foreach ($individual as $gene) {
            if ($gene['day_id'] && $gene['time_slot_id'] && $gene['room_id']) {
                $validAssignments++;
            } else {
                $invalidAssignments++;
            }
        }
        
        return [
            'total' => $totalAssignments,
            'valid' => $validAssignments,
            'invalid' => $invalidAssignments,
            'completion_rate' => $validAssignments / $totalAssignments * 100
        ];
    }
    
    /**
     * Get conflict analysis
     */
    private function getConflictAnalysis(array $individual): array {
        $roomConflicts = [];
        $lecturerConflicts = [];
        $classConflicts = [];
        
        $roomSlots = [];
        $lecturerSlots = [];
        $classSlots = [];
        
        foreach ($individual as $classCourseId => $gene) {
            if (!$gene['day_id'] || !$gene['time_slot_id'] || !$gene['room_id']) {
                continue;
            }
            
            // Room conflicts
            $roomKey = TimetableRepresentation::getRoomConflictKey($gene);
            if (isset($roomSlots[$roomKey])) {
                $roomConflicts[] = [
                    'conflict_with' => $roomSlots[$roomKey],
                    'class_course_id' => $classCourseId
                ];
            } else {
                $roomSlots[$roomKey] = $classCourseId;
            }
            
            // Lecturer conflicts
            if ($gene['lecturer_course_id'] || $gene['lecturer_id']) {
                $lecturerKey = TimetableRepresentation::getLecturerConflictKey($gene);
                if (isset($lecturerSlots[$lecturerKey])) {
                    $lecturerConflicts[] = [
                        'conflict_with' => $lecturerSlots[$lecturerKey],
                        'class_course_id' => $classCourseId
                    ];
                } else {
                    $lecturerSlots[$lecturerKey] = $classCourseId;
                }
            }
            
            // Class conflicts
            $classKey = TimetableRepresentation::getClassConflictKey($gene);
            if (isset($classSlots[$classKey])) {
                $classConflicts[] = [
                    'conflict_with' => $classSlots[$classKey],
                    'class_course_id' => $classCourseId
                ];
            } else {
                $classSlots[$classKey] = $classCourseId;
            }
        }
        
        return [
            'room_conflicts' => $roomConflicts,
            'lecturer_conflicts' => $lecturerConflicts,
            'class_conflicts' => $classConflicts,
            'total_conflicts' => count($roomConflicts) + count($lecturerConflicts) + count($classConflicts)
        ];
    }
    
    /**
     * Get distribution analysis
     */
    private function getDistributionAnalysis(array $individual): array {
        $dayDistribution = [];
        $timeDistribution = [];
        $roomDistribution = [];
        
        foreach ($individual as $gene) {
            if (!$gene['day_id'] || !$gene['time_slot_id'] || !$gene['room_id']) {
                continue;
            }
            
            // Day distribution
            $dayId = $gene['day_id'];
            $dayDistribution[$dayId] = ($dayDistribution[$dayId] ?? 0) + 1;
            
            // Time distribution
            $timeSlotId = $gene['time_slot_id'];
            $timeDistribution[$timeSlotId] = ($timeDistribution[$timeSlotId] ?? 0) + 1;
            
            // Room distribution
            $roomId = $gene['room_id'];
            $roomDistribution[$roomId] = ($roomDistribution[$roomId] ?? 0) + 1;
        }
        
        return [
            'day_distribution' => $dayDistribution,
            'time_distribution' => $timeDistribution,
            'room_distribution' => $roomDistribution,
            'day_balance' => $this->calculateBalanceScore($dayDistribution),
            'time_balance' => $this->calculateBalanceScore($timeDistribution),
            'room_balance' => $this->calculateBalanceScore($roomDistribution)
        ];
    }
    
    /**
     * Get capacity analysis
     */
    private function getCapacityAnalysis(array $individual): array {
        $capacityIssues = [];
        $capacityStats = [
            'total_assignments' => 0,
            'capacity_violations' => 0,
            'total_shortage' => 0
        ];
        
        foreach ($individual as $gene) {
            if (!$gene['day_id'] || !$gene['time_slot_id'] || !$gene['room_id']) {
                continue;
            }
            
            $capacityStats['total_assignments']++;
            
            $classId = $gene['class_id'];
            $roomId = $gene['room_id'];
            
            // Find class capacity
            $classCapacity = 0;
            foreach ($this->data['classes'] as $class) {
                if ($class['id'] == $classId) {
                    $classCapacity = $class['total_capacity'] ?? 0;
                    break;
                }
            }
            
            // Find room capacity
            $roomCapacity = 0;
            foreach ($this->data['rooms'] as $room) {
                if ($room['id'] == $roomId) {
                    $roomCapacity = $room['capacity'] ?? 0;
                    break;
                }
            }
            
            if ($roomCapacity < $classCapacity) {
                $capacityStats['capacity_violations']++;
                $capacityStats['total_shortage'] += ($classCapacity - $roomCapacity);
                
                $capacityIssues[] = [
                    'class_course_id' => $gene['class_course_id'],
                    'class_capacity' => $classCapacity,
                    'room_capacity' => $roomCapacity,
                    'shortage' => $classCapacity - $roomCapacity
                ];
            }
        }
        
        return [
            'issues' => $capacityIssues,
            'stats' => $capacityStats,
            'violation_rate' => $capacityStats['total_assignments'] > 0 ? 
                $capacityStats['capacity_violations'] / $capacityStats['total_assignments'] * 100 : 0
        ];
    }
    
    /**
     * Calculate balance score for distribution
     */
    private function calculateBalanceScore(array $distribution): float {
        if (empty($distribution)) {
            return 0;
        }
        
        $values = array_values($distribution);
        $mean = array_sum($values) / count($values);
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($values);
        
        $standardDeviation = sqrt($variance);
        
        // Lower standard deviation means better balance
        return max(0, 100 - ($standardDeviation / $mean * 100));
    }
    
    /**
     * Calculate quality percentage
     */
    private function calculateQualityPercentage(array $fitness): float {
        // Normalize fitness score to 0-100 range
        // Lower fitness score is better, so we invert it
        $maxExpectedScore = 10000; // Reasonable maximum score
        $normalizedScore = min(100, max(0, (1 - $fitness['total_score'] / $maxExpectedScore) * 100));
        
        return round($normalizedScore, 2);
    }
    
    /**
     * Compare two solutions
     */
    public function compareSolutions(array $solution1, array $solution2): array {
        $fitness1 = $this->evaluate($solution1);
        $fitness2 = $this->evaluate($solution2);
        
        return [
            'solution1' => $fitness1,
            'solution2' => $fitness2,
            'comparison' => [
                'better_solution' => $fitness1['total_score'] < $fitness2['total_score'] ? 1 : 2,
                'score_difference' => abs($fitness1['total_score'] - $fitness2['total_score']),
                'hard_violations_difference' => 
                    array_sum(array_map('count', $fitness1['hard_violations'])) - 
                    array_sum(array_map('count', $fitness2['hard_violations'])),
                'soft_violations_difference' => 
                    array_sum(array_map('count', $fitness1['soft_violations'])) - 
                    array_sum(array_map('count', $fitness2['soft_violations']))
            ]
        ];
    }
    
    /**
     * Get solution quality rating
     */
    public function getQualityRating(array $individual): string {
        $fitness = $this->evaluate($individual);
        $qualityPercentage = $this->calculateQualityPercentage($fitness);
        
        if ($qualityPercentage >= 90) {
            return 'Excellent';
        } elseif ($qualityPercentage >= 80) {
            return 'Good';
        } elseif ($qualityPercentage >= 70) {
            return 'Fair';
        } elseif ($qualityPercentage >= 60) {
            return 'Poor';
        } else {
            return 'Very Poor';
        }
    }
}
?>
