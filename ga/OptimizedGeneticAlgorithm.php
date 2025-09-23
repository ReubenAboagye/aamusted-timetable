<?php
/**
 * Optimized Genetic Algorithm Implementation
 * 
 * This class provides performance-optimized genetic algorithm parameters
 * and adaptive strategies for faster timetable generation.
 */

require_once __DIR__ . '/GeneticAlgorithm.php';

class OptimizedGeneticAlgorithm extends GeneticAlgorithm {
    
    /**
     * Adaptive parameter optimization based on problem size
     */
    public function optimizeParameters(array $data): array {
        $problemSize = count($data['class_courses']);
        $timeSlots = count($data['time_slots']) * count($data['days']);
        $rooms = count($data['rooms']);
        
        // Calculate optimal parameters based on problem complexity
        $complexity = $problemSize * log($timeSlots * $rooms);
        
        if ($complexity < 1000) {
            // Small problem - use aggressive parameters
            return [
                'population' => 30,
                'generations' => 150,
                'mutation_rate' => 0.15,
                'crossover_rate' => 0.9,
                'elitism_rate' => 0.1,
                'tournament_size' => 3,
                'stagnation_limit' => 20,
                'max_runtime' => 120
            ];
        } elseif ($complexity < 5000) {
            // Medium problem - balanced parameters
            return [
                'population' => 50,
                'generations' => 200,
                'mutation_rate' => 0.12,
                'crossover_rate' => 0.85,
                'elitism_rate' => 0.08,
                'tournament_size' => 4,
                'stagnation_limit' => 30,
                'max_runtime' => 180
            ];
        } else {
            // Large problem - conservative parameters
            return [
                'population' => 80,
                'generations' => 250,
                'mutation_rate' => 0.1,
                'crossover_rate' => 0.8,
                'elitism_rate' => 0.05,
                'tournament_size' => 5,
                'stagnation_limit' => 40,
                'max_runtime' => 300
            ];
        }
    }
    
    /**
     * Early termination strategies
     */
    protected function shouldTerminateEarly(array $generationStats, int $generation): bool {
        // Terminate if we've found a feasible solution
        if ($generationStats['best_fitness']['hard_score'] == 0) {
            return true;
        }
        
        // Terminate if no improvement for too long
        if ($generationStats['stagnation_count'] >= $this->options['stagnation_limit']) {
            return true;
        }
        
        // Terminate if we're close to optimal and improvement is minimal
        if ($generation > 50 && $generationStats['improvement_rate'] < 0.001) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Adaptive mutation rate based on diversity
     */
    protected function calculateAdaptiveMutationRate(array $population): float {
        $diversity = $this->calculatePopulationDiversity($population);
        
        // Increase mutation rate if population is too similar
        if ($diversity < 0.3) {
            return min(0.2, $this->mutationRate * 1.5);
        }
        
        // Decrease mutation rate if population is diverse
        if ($diversity > 0.7) {
            return max(0.05, $this->mutationRate * 0.8);
        }
        
        return $this->mutationRate;
    }
    
    /**
     * Calculate population diversity
     */
    private function calculatePopulationDiversity(array $population): float {
        if (count($population) < 2) return 0;
        
        $totalDifferences = 0;
        $comparisons = 0;
        
        // Sample-based diversity calculation for performance
        $sampleSize = min(10, count($population));
        $sample = array_slice($population, 0, $sampleSize);
        
        for ($i = 0; $i < count($sample); $i++) {
            for ($j = $i + 1; $j < count($sample); $j++) {
                $totalDifferences += $this->calculateIndividualDifference($sample[$i], $sample[$j]);
                $comparisons++;
            }
        }
        
        return $comparisons > 0 ? $totalDifferences / $comparisons : 0;
    }
    
    /**
     * Calculate difference between two individuals
     */
    private function calculateIndividualDifference(array $ind1, array $ind2): float {
        $differences = 0;
        $totalGenes = count($ind1);
        
        foreach ($ind1 as $key => $gene1) {
            $gene2 = $ind2[$key] ?? null;
            if (!$gene2) {
                $differences++;
                continue;
            }
            
            // Compare key assignment properties
            if ($gene1['day_id'] != $gene2['day_id'] ||
                $gene1['time_slot_id'] != $gene2['time_slot_id'] ||
                $gene1['room_id'] != $gene2['room_id']) {
                $differences++;
            }
        }
        
        return $differences / $totalGenes;
    }
}
?>

