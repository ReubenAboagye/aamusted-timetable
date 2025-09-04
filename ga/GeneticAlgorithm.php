<?php
/**
 * Complete Genetic Algorithm Implementation for Timetable Generation
 * 
 * This class provides a comprehensive genetic algorithm solution for generating
 * optimal timetables while respecting all constraints and preferences.
 */

require_once __DIR__ . '/DBLoader.php';
require_once __DIR__ . '/TimetableRepresentation.php';
require_once __DIR__ . '/FitnessEvaluator.php';
require_once __DIR__ . '/ConstraintChecker.php';

class GeneticAlgorithm {
    private $conn;
    private $data;
    private $constraints;
    private $options;
    
    // GA Parameters
    private $populationSize;
    private $generations;
    private $mutationRate;
    private $crossoverRate;
    private $elitismRate;
    private $tournamentSize;
    
    // Statistics
    private $generationStats = [];
    private $bestSolution = null;
    private $startTime;
    
    public function __construct(mysqli $conn, array $options = []) {
        $this->conn = $conn;
        $this->options = array_merge([
            'population_size' => 100,
            'generations' => 500,
            'mutation_rate' => 0.1,
            'crossover_rate' => 0.8,
            'elitism_rate' => 0.1,
            'tournament_size' => 3,
            'stream_id' => null,
            'academic_year' => null,
            'semester' => null,
            'max_runtime' => 300, // 5 minutes
            'fitness_threshold' => 0.95,
            'stagnation_limit' => 50
        ], $options);
        
        // Load data
        $loader = new DBLoader($conn);
        $this->data = $loader->loadAll($this->options);
        
        // Initialize constraints
        $this->constraints = new ConstraintChecker($this->data, $this->options);
        
        // Set GA parameters
        $this->populationSize = $this->options['population_size'];
        $this->generations = $this->options['generations'];
        $this->mutationRate = $this->options['mutation_rate'];
        $this->crossoverRate = $this->options['crossover_rate'];
        $this->elitismRate = $this->options['elitism_rate'];
        $this->tournamentSize = $this->options['tournament_size'];
    }
    
    /**
     * Run the genetic algorithm to generate an optimal timetable
     */
    public function run(): array {
        $this->startTime = microtime(true);
        
        // Initialize population
        $population = $this->initializePopulation();
        
        // Main GA loop
        $stagnationCount = 0;
        $lastBestFitness = 0;
        
        for ($generation = 0; $generation < $this->generations; $generation++) {
            // Check runtime limit
            if (microtime(true) - $this->startTime > $this->options['max_runtime']) {
                break;
            }
            
            // Evaluate population
            $evaluatedPopulation = $this->evaluatePopulation($population);
            
            // Sort by fitness (lower is better)
            usort($evaluatedPopulation, function($a, $b) {
                return $a['fitness']['total_score'] <=> $b['fitness']['total_score'];
            });
            
            // Track best solution
            $currentBest = $evaluatedPopulation[0];
            if ($this->bestSolution === null || 
                $currentBest['fitness']['total_score'] < $this->bestSolution['fitness']['total_score']) {
                $this->bestSolution = $currentBest;
            }
            
            // Check for stagnation
            if (abs($currentBest['fitness']['total_score'] - $lastBestFitness) < 0.001) {
                $stagnationCount++;
            } else {
                $stagnationCount = 0;
            }
            
            if ($stagnationCount >= $this->options['stagnation_limit']) {
                break;
            }
            
            $lastBestFitness = $currentBest['fitness']['total_score'];
            
            // Store generation statistics
            $this->generationStats[] = [
                'generation' => $generation,
                'best_fitness' => $currentBest['fitness']['total_score'],
                'avg_fitness' => $this->calculateAverageFitness($evaluatedPopulation),
                'worst_fitness' => end($evaluatedPopulation)['fitness']['total_score'],
                'hard_violations' => $currentBest['fitness']['hard_violations'],
                'soft_violations' => $currentBest['fitness']['soft_violations']
            ];
            
            // Check for convergence
            if ($currentBest['fitness']['total_score'] <= $this->options['fitness_threshold']) {
                break;
            }
            
            // Generate next generation
            $population = $this->generateNextGeneration($evaluatedPopulation);
        }
        
        return [
            'solution' => $this->bestSolution,
            'statistics' => $this->generationStats,
            'runtime' => microtime(true) - $this->startTime,
            'generations_completed' => count($this->generationStats)
        ];
    }
    
    /**
     * Initialize a random population
     */
    private function initializePopulation(): array {
        $population = [];
        
        for ($i = 0; $i < $this->populationSize; $i++) {
            $individual = TimetableRepresentation::createRandomIndividual($this->data);
            $population[] = $individual;
        }
        
        return $population;
    }
    
    /**
     * Evaluate the fitness of all individuals in the population
     */
    private function evaluatePopulation(array $population): array {
        $evaluated = [];
        
        foreach ($population as $individual) {
            $fitness = $this->constraints->evaluateFitness($individual);
            $evaluated[] = [
                'individual' => $individual,
                'fitness' => $fitness
            ];
        }
        
        return $evaluated;
    }
    
    /**
     * Generate the next generation using selection, crossover, and mutation
     */
    private function generateNextGeneration(array $evaluatedPopulation): array {
        $newPopulation = [];
        
        // Elitism: keep the best individuals
        $elitismCount = max(1, (int)($this->populationSize * $this->elitismRate));
        for ($i = 0; $i < $elitismCount; $i++) {
            $newPopulation[] = $evaluatedPopulation[$i]['individual'];
        }
        
        // Generate remaining individuals through selection, crossover, and mutation
        while (count($newPopulation) < $this->populationSize) {
            // Selection
            $parent1 = $this->tournamentSelection($evaluatedPopulation);
            $parent2 = $this->tournamentSelection($evaluatedPopulation);
            
            // Crossover
            if (mt_rand() / mt_getrandmax() < $this->crossoverRate) {
                $offspring = $this->crossover($parent1, $parent2);
            } else {
                $offspring = $parent1;
            }
            
            // Mutation
            $offspring = $this->mutate($offspring);
            
            $newPopulation[] = $offspring;
        }
        
        return $newPopulation;
    }
    
    /**
     * Tournament selection
     */
    private function tournamentSelection(array $evaluatedPopulation): array {
        $tournament = [];
        
        for ($i = 0; $i < $this->tournamentSize; $i++) {
            $tournament[] = $evaluatedPopulation[array_rand($evaluatedPopulation)];
        }
        
        // Return the best individual from the tournament
        usort($tournament, function($a, $b) {
            return $a['fitness']['total_score'] <=> $b['fitness']['total_score'];
        });
        
        return $tournament[0]['individual'];
    }
    
    /**
     * Crossover two parents to create offspring
     */
    private function crossover(array $parent1, array $parent2): array {
        $offspring = [];
        
        // Group genes by class_course_id to handle multi-slot courses
        $parent1Groups = $this->groupGenesByClassCourse($parent1);
        $parent2Groups = $this->groupGenesByClassCourse($parent2);
        
        foreach ($parent1Groups as $classCourseId => $genes1) {
            $genes2 = $parent2Groups[$classCourseId] ?? $genes1;
            
            // Uniform crossover - either take all genes from parent1 or parent2
            if (mt_rand() / mt_getrandmax() < 0.5) {
                foreach ($genes1 as $gene) {
                    $geneKey = $classCourseId . '_' . $gene['slot_index'];
                    $offspring[$geneKey] = $gene;
                }
            } else {
                foreach ($genes2 as $gene) {
                    $geneKey = $classCourseId . '_' . $gene['slot_index'];
                    $offspring[$geneKey] = $gene;
                }
            }
        }
        
        return $offspring;
    }
    
    /**
     * Group genes by class_course_id for multi-slot courses
     */
    private function groupGenesByClassCourse(array $individual): array {
        $groups = [];
        
        foreach ($individual as $geneKey => $gene) {
            $classCourseId = $gene['class_course_id'];
            if (!isset($groups[$classCourseId])) {
                $groups[$classCourseId] = [];
            }
            $groups[$classCourseId][] = $gene;
        }
        
        return $groups;
    }
    
    /**
     * Mutate an individual
     */
    private function mutate(array $individual): array {
        $mutated = $individual;
        
        // Group genes by class_course_id to handle multi-slot courses
        $geneGroups = $this->groupGenesByClassCourse($individual);
        
        foreach ($geneGroups as $classCourseId => $genes) {
            if (mt_rand() / mt_getrandmax() < $this->mutationRate) {
                try {
                    // Mutate the first gene (base position) and update all related genes
                    $baseGene = $genes[0];
                    $newBaseGene = TimetableRepresentation::createRandomGene(
                        $baseGene, 
                        $this->data['days'], 
                        $this->data['time_slots'], 
                        $this->data['rooms'],
                        [],
                        $baseGene['slot_index']
                    );
                    
                    // Update all genes for this class-course
                    foreach ($genes as $gene) {
                        $geneKey = $classCourseId . '_' . $gene['slot_index'];
                        $mutated[$geneKey] = $newBaseGene;
                    }
                } catch (Exception $e) {
                    // Log the error but continue with the original genes
                    error_log("Mutation error for class course $classCourseId: " . $e->getMessage());
                    // Keep the original genes unchanged
                }
            }
        }
        
        return $mutated;
    }
    
    /**
     * Get termination reason
     */
    private function getTerminationReason(int $generation): string {
        if (microtime(true) - $this->startTime > $this->options['max_runtime']) {
            return 'time_limit';
        }
        if (memory_get_usage(true) > 256 * 1024 * 1024) {
            return 'memory_limit';
        }
        if ($generation >= $this->generations - 1) {
            return 'generations_completed';
        }
        return 'convergence';
    }
    
    /**
     * Calculate average fitness of the population
     */
    private function calculateAverageFitness(array $evaluatedPopulation): float {
        $totalFitness = 0;
        
        foreach ($evaluatedPopulation as $individual) {
            $totalFitness += $individual['fitness']['total_score'];
        }
        
        return $totalFitness / count($evaluatedPopulation);
    }
    
    /**
     * Convert the best solution to database format
     * Enhanced to prevent duplicate entries more robustly
     */
    public function convertToDatabaseFormat(array $solution): array {
        $timetableEntries = [];
        $uniqueKeys = []; // Track unique combinations to prevent duplicates
        $processedGenes = []; // Track which genes we've already processed
        
        // Group genes by class_course_id to handle multi-slot courses
        $groupedGenes = [];
        foreach ($solution['individual'] as $geneKey => $gene) {
            $classCourseId = $gene['class_course_id'];
            if (!isset($groupedGenes[$classCourseId])) {
                $groupedGenes[$classCourseId] = [];
            }
            $groupedGenes[$classCourseId][] = $gene;
        }
        
        // Process each class-course group
        foreach ($groupedGenes as $classCourseId => $genes) {
            // Sort genes by slot_index to ensure proper order
            usort($genes, function($a, $b) {
                return $a['slot_index'] - $b['slot_index'];
            });
            
            // Check if this is a combined assignment
            $isCombined = $genes[0]['is_combined'] ?? false;
            $combinedClasses = $genes[0]['combined_classes'] ?? [];
            
            if ($isCombined && !empty($combinedClasses)) {
                // Handle combined assignment - create entries for all class divisions
                $courseDuration = $genes[0]['course_duration'] ?? 1;
                $baseTimeSlotId = $genes[0]['time_slot_id'];
                $baseDayId = $genes[0]['day_id'];
                $baseRoomId = $genes[0]['room_id'];
                
                for ($i = 0; $i < $courseDuration; $i++) {
                    $timeSlotId = $baseTimeSlotId + $i;
                    
                    // Create entry for each class division in the combination
                    foreach ($combinedClasses as $classCourse) {
                        $divisionLabel = $classCourse['division_label'] ?? $genes[0]['division_label'];
                        $actualClassCourseId = $classCourse['original_class_course_id'] ?? $classCourse['id'];
                        
                        // Create unique key to prevent duplicates
                        $uniqueKey = $actualClassCourseId . '-' . $baseDayId . '-' . $timeSlotId . '-' . $divisionLabel;
                        
                        if (!in_array($uniqueKey, $uniqueKeys)) {
                            $uniqueKeys[] = $uniqueKey;
                            
                            $timetableEntries[] = [
                                'class_course_id' => $actualClassCourseId,
                                'lecturer_course_id' => $genes[0]['lecturer_course_id'],
                                'day_id' => $baseDayId,
                                'time_slot_id' => $timeSlotId,
                                'room_id' => $baseRoomId,
                                'division_label' => $divisionLabel,
                                'semester' => $this->options['semester'],
                                'academic_year' => $this->options['academic_year'],
                                'timetable_type' => 'lecture',
                                'is_active' => 1
                            ];
                        } else {
                            // Log duplicate for debugging
                            error_log("Duplicate detected in combined assignment: " . $uniqueKey);
                        }
                    }
                }
            } else {
                // Handle individual assignment
                $courseDuration = $genes[0]['course_duration'] ?? 1;
                $baseTimeSlotId = $genes[0]['time_slot_id'];
                $baseDayId = $genes[0]['day_id'];
                $baseRoomId = $genes[0]['room_id'];
                
                for ($i = 0; $i < $courseDuration; $i++) {
                    $timeSlotId = $baseTimeSlotId + $i;
                    $divisionLabel = $genes[0]['division_label'];
                    
                    // Create unique key to prevent duplicates
                    $uniqueKey = $classCourseId . '-' . $baseDayId . '-' . $timeSlotId . '-' . $divisionLabel;
                    
                    if (!in_array($uniqueKey, $uniqueKeys)) {
                        $uniqueKeys[] = $uniqueKey;
                        
                        $timetableEntries[] = [
                            'class_course_id' => $classCourseId,
                            'lecturer_course_id' => $genes[0]['lecturer_course_id'],
                            'day_id' => $baseDayId,
                            'time_slot_id' => $timeSlotId,
                            'room_id' => $baseRoomId,
                            'division_label' => $divisionLabel,
                            'semester' => $this->options['semester'],
                            'academic_year' => $this->options['academic_year'],
                            'timetable_type' => 'lecture',
                            'is_active' => 1
                        ];
                    } else {
                        // Log duplicate for debugging
                        error_log("Duplicate detected in individual assignment: " . $uniqueKey);
                    }
                }
            }
        }
        
        // Final validation: ensure no duplicates in the final array
        $finalEntries = [];
        $finalUniqueKeys = [];
        
        foreach ($timetableEntries as $entry) {
            $key = $entry['class_course_id'] . '-' . $entry['day_id'] . '-' . $entry['time_slot_id'] . '-' . $entry['division_label'];
            if (!in_array($key, $finalUniqueKeys)) {
                $finalUniqueKeys[] = $key;
                $finalEntries[] = $entry;
            } else {
                // Log duplicate for debugging
                error_log("Final duplicate detected: " . $key . " for entry: " . json_encode($entry));
            }
        }
        
        // Additional validation: check for any remaining duplicates
        $duplicateCheck = [];
        foreach ($finalEntries as $entry) {
            $key = $entry['class_course_id'] . '-' . $entry['day_id'] . '-' . $entry['time_slot_id'] . '-' . $entry['division_label'];
            if (isset($duplicateCheck[$key])) {
                error_log("CRITICAL: Duplicate still found after filtering: " . $key);
                // Remove the duplicate entry
                continue;
            }
            $duplicateCheck[$key] = true;
        }
        
        // Return only unique entries
        $uniqueFinalEntries = [];
        $uniqueCheck = [];
        foreach ($finalEntries as $entry) {
            $key = $entry['class_course_id'] . '-' . $entry['day_id'] . '-' . $entry['time_slot_id'] . '-' . $entry['division_label'];
            if (!isset($uniqueCheck[$key])) {
                $uniqueCheck[$key] = true;
                $uniqueFinalEntries[] = $entry;
            }
        }
        
        error_log("Converted " . count($timetableEntries) . " entries to " . count($uniqueFinalEntries) . " unique entries");
        
        return $uniqueFinalEntries;
    }
    
    /**
     * Get the best solution found
     */
    public function getBestSolution(): ?array {
        return $this->bestSolution;
    }
    
    /**
     * Get generation statistics
     */
    public function getGenerationStats(): array {
        return $this->generationStats;
    }
}
?>
