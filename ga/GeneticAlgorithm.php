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
    // Optional progress callback and rate limiting
    private $progressCallback = null;
    private $progressUpdateInterval = 5; // seconds
    private $lastProgressUpdateTime = 0;
    
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
        $this->progressUpdateInterval = $this->options['progress_update_interval'] ?? 5;
    }

    /**
     * Set a progress callback to receive periodic updates during run().
     * Callback will be called with a single array argument containing keys: generation, percent, best_fitness, hard_violations, soft_violations
     */
    public function setProgressCallback(callable $cb): void {
        $this->progressCallback = $cb;
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
            
            // Periodic memory cleanup
            if ($generation % 10 === 0 && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            // Check for convergence
            if ($currentBest['fitness']['total_score'] <= $this->options['fitness_threshold']) {
                break;
            }

            // Progress callback (rate-limited)
            if ($this->progressCallback) {
                $now = microtime(true);
                if ($now - $this->lastProgressUpdateTime >= $this->progressUpdateInterval) {
                    $percent = ($generation / max(1, $this->generations)) * 100;
                    call_user_func($this->progressCallback, [
                        'generation' => $generation,
                        'percent' => round($percent, 2),
                        'best_fitness' => $currentBest['fitness']['total_score'],
                        'hard_violations' => $currentBest['fitness']['hard_violations'],
                        'soft_violations' => $currentBest['fitness']['soft_violations']
                    ]);
                    $this->lastProgressUpdateTime = $now;
                }
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
        $count = 0;
        
        foreach ($population as $individual) {
            $fitness = $this->constraints->evaluateFitness($individual);
            $evaluated[] = [
                'individual' => $individual,
                'fitness' => $fitness
            ];
            
            // Periodic memory cleanup
            $count++;
            if ($count % 10 === 0 && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
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
        
        // Group genes by class_course_id + division to handle multi-slot courses per division
        $parent1Groups = $this->groupGenesByDivision($parent1);
        $parent2Groups = $this->groupGenesByDivision($parent2);
        
        foreach ($parent1Groups as $classCourseId => $genes1) {
            $genes2 = $parent2Groups[$classCourseId] ?? $genes1;
            
            // Uniform crossover - either take all genes from parent1 or parent2
            if (mt_rand() / mt_getrandmax() < 0.5) {
                foreach ($genes1 as $gene) {
                    $divKey = $gene['division_key'] ?? ($classCourseId . '|' . ($gene['division_label'] ?? ''));
                    $geneKey = $divKey . '_' . $gene['slot_index'];
                    $offspring[$geneKey] = $gene;
                }
            } else {
                foreach ($genes2 as $gene) {
                    $divKey = $gene['division_key'] ?? ($classCourseId . '|' . ($gene['division_label'] ?? ''));
                    $geneKey = $divKey . '_' . $gene['slot_index'];
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
            if (!isset($groups[$classCourseId])) { $groups[$classCourseId] = []; }
            $groups[$classCourseId][] = $gene;
        }
        return $groups;
    }

    private function groupGenesByDivision(array $individual): array {
        $groups = [];
        foreach ($individual as $geneKey => $gene) {
            $classCourseId = $gene['class_course_id'];
            $divKey = $gene['division_key'] ?? ($classCourseId . '|' . ($gene['division_label'] ?? ''));
            if (!isset($groups[$divKey])) { $groups[$divKey] = []; }
            $groups[$divKey][] = $gene;
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
                    // Mutate only assignment (day/time/room) while preserving per-division attributes
                    $baseGene = $genes[0];
                    $newAssignment = TimetableRepresentation::cloneGeneWithNewAssignment(
                        $baseGene,
                        $this->data['days'],
                        $this->data['time_slots'],
                        $this->data['rooms']
                    );

                    foreach ($genes as $gene) {
                        $updated = $gene;
                        $updated['day_id'] = $newAssignment['day_id'];
                        $updated['time_slot_id'] = $newAssignment['time_slot_id'];
                        $updated['room_id'] = $newAssignment['room_id'];
                        if (isset($newAssignment['lecturer_course_id'])) {
                            $updated['lecturer_course_id'] = $newAssignment['lecturer_course_id'];
                        }
                        // Keep division_label untouched so divisions remain distinct
                        $geneKey = $classCourseId . '_' . $gene['slot_index'];
                        $mutated[$geneKey] = $updated;
                    }
                } catch (Exception $e) {
                    // Log the error but continue with the original genes
                    error_log("Mutation error for class course $classCourseId: " . $e->getMessage());
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
        
        // Validate solution for lecturer conflicts before conversion
        $this->validateSolutionForLecturerConflicts($solution);
        
        // Group genes by class_course_id + division to handle multi-slot courses per division
        $groupedGenes = [];
        foreach ($solution['individual'] as $geneKey => $gene) {
            $divisionKey = $gene['division_key'] ?? ($gene['class_course_id'] . '|' . ($gene['division_label'] ?? ''));
            if (!isset($groupedGenes[$divisionKey])) {
                $groupedGenes[$divisionKey] = [];
            }
            $groupedGenes[$divisionKey][] = $gene;
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
                // Force one period per course per division
                $courseDuration = 1;
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
                                'is_active' => 1,
                                'is_combined' => 1,
                                'combined_classes' => json_encode(array_column($gene['combined_classes'] ?? [], 'class_course_id'))
                            ];
                        } else {
                            // Log duplicate for debugging
                            error_log("Duplicate detected in combined assignment: " . $uniqueKey);
                        }
                    }
                }
            } else {
                // Handle individual assignment - ensure each division gene in the group is emitted
                // Force one period per course per division
                $courseDuration = 1;

                // For each gene (which may represent a division of the parent class_course), emit entries
                foreach ($genes as $gene) {
                    $baseTimeSlotId = $gene['time_slot_id'];
                    $baseDayId = $gene['day_id'];
                    $baseRoomId = $gene['room_id'];
                    $divisionLabel = $gene['division_label'] ?? '';

                    // Resolve original class_course id to use as FK
                    $actualClassCourseId = $gene['original_class_course_id'] ?? $gene['class_course_id'] ?? $classCourseId;

                    for ($i = 0; $i < $courseDuration; $i++) {
                        $timeSlotId = $baseTimeSlotId + $i;

                        // Create unique key to prevent duplicates
                        $uniqueKey = $actualClassCourseId . '-' . $baseDayId . '-' . $timeSlotId . '-' . $divisionLabel;

                        if (!in_array($uniqueKey, $uniqueKeys)) {
                            $uniqueKeys[] = $uniqueKey;

                            $timetableEntries[] = [
                                'class_course_id' => $actualClassCourseId,
                                'lecturer_course_id' => $gene['lecturer_course_id'],
                                'day_id' => $baseDayId,
                                'time_slot_id' => $timeSlotId,
                                'room_id' => $baseRoomId,
                                'division_label' => $divisionLabel,
                                'semester' => $this->options['semester'],
                                'academic_year' => $this->options['academic_year'],
                                'timetable_type' => 'lecture',
                                'is_active' => 1,
                                'is_combined' => 0,
                                'combined_classes' => null
                            ];
                        } else {
                            // Log duplicate for debugging
                            error_log("Duplicate detected in individual assignment: " . $uniqueKey);
                        }
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
    
    /**
     * Validate solution for lecturer conflicts before conversion
     */
    private function validateSolutionForLecturerConflicts(array $solution): void {
        if (!isset($solution['individual']) || !is_array($solution['individual'])) {
            return;
        }
        
        $lecturerSlots = []; // Track lecturer assignments by time slot
        $conflicts = [];
        
        foreach ($solution['individual'] as $geneKey => $gene) {
            if (!$gene['day_id'] || !$gene['time_slot_id'] || !$gene['room_id']) {
                continue;
            }
            
            // Get lecturer ID
            $lecturerId = $gene['lecturer_id'] ?? null;
            if (!$lecturerId && isset($gene['lecturer_course_id'])) {
                // Resolve lecturer ID from lecturer_course_id
                foreach ($this->data['lecturer_courses'] as $lc) {
                    if ($lc['id'] == $gene['lecturer_course_id']) {
                        $lecturerId = $lc['lecturer_id'];
                        break;
                    }
                }
            }
            
            if (!$lecturerId) {
                continue;
            }
            
            // Create lecturer slot key
            $lecturerSlotKey = $lecturerId . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
            
            if (isset($lecturerSlots[$lecturerSlotKey])) {
                $conflicts[] = [
                    'lecturer_id' => $lecturerId,
                    'day_id' => $gene['day_id'],
                    'time_slot_id' => $gene['time_slot_id'],
                    'conflicting_genes' => [$lecturerSlots[$lecturerSlotKey], $geneKey]
                ];
            } else {
                $lecturerSlots[$lecturerSlotKey] = $geneKey;
            }
        }
        
        if (!empty($conflicts)) {
            error_log("Lecturer conflicts detected in solution before conversion:");
            foreach ($conflicts as $conflict) {
                error_log("Lecturer {$conflict['lecturer_id']} has multiple classes at day {$conflict['day_id']}, time slot {$conflict['time_slot_id']}");
                error_log("Conflicting genes: " . implode(', ', $conflict['conflicting_genes']));
            }
            
            // Remove conflicting entries to prevent database insertion
            $this->removeConflictingEntries($solution, $conflicts);
        }
    }
    
    /**
     * Remove conflicting entries from solution
     */
    private function removeConflictingEntries(array &$solution, array $conflicts): void {
        $conflictingGeneKeys = [];
        
        foreach ($conflicts as $conflict) {
            $conflictingGeneKeys = array_merge($conflictingGeneKeys, $conflict['conflicting_genes']);
        }
        
        // Remove duplicate gene keys
        $conflictingGeneKeys = array_unique($conflictingGeneKeys);
        
        // Remove conflicting genes from solution
        foreach ($conflictingGeneKeys as $geneKey) {
            if (isset($solution['individual'][$geneKey])) {
                unset($solution['individual'][$geneKey]);
                error_log("Removed conflicting gene: $geneKey");
            }
        }
    }
}
?>
