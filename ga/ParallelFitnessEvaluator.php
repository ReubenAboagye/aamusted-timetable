<?php
/**
 * Parallel Fitness Evaluator for Genetic Algorithm
 * 
 * This class provides parallel processing capabilities for fitness evaluation
 * to significantly improve performance on multi-core systems.
 */

require_once __DIR__ . '/FitnessEvaluator.php';
require_once __DIR__ . '/IntelligentCache.php';

class ParallelFitnessEvaluator extends FitnessEvaluator {
    private $cache;
    private $maxWorkers;
    private $useParallel;
    
    public function __construct(array $data, array $options = []) {
        parent::__construct($data);
        
        $this->cache = new IntelligentCache();
        $this->maxWorkers = $options['max_workers'] ?? $this->detectOptimalWorkers();
        $this->useParallel = $options['use_parallel'] ?? true;
        
        // Disable parallel processing on Windows or if not supported
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('pcntl_fork')) {
            $this->useParallel = false;
        }
    }
    
    /**
     * Evaluate multiple individuals in parallel
     */
    public function evaluatePopulation(array $population): array {
        if (!$this->useParallel || count($population) < 4) {
            // Use sequential evaluation for small populations
            return $this->evaluatePopulationSequential($population);
        }
        
        return $this->evaluatePopulationParallel($population);
    }
    
    /**
     * Sequential evaluation fallback
     */
    private function evaluatePopulationSequential(array $population): array {
        $results = [];
        
        foreach ($population as $index => $individual) {
            $results[$index] = [
                'individual' => $individual,
                'fitness' => $this->evaluateWithCache($individual)
            ];
        }
        
        return $results;
    }
    
    /**
     * Parallel evaluation using process forking
     */
    private function evaluatePopulationParallel(array $population): array {
        $chunkSize = max(1, intval(count($population) / $this->maxWorkers));
        $chunks = array_chunk($population, $chunkSize, true);
        
        $pipes = [];
        $processes = [];
        
        // Create worker processes
        for ($i = 0; $i < min(count($chunks), $this->maxWorkers); $i++) {
            $chunk = $chunks[$i];
            
            $descriptorspec = [
                0 => ["pipe", "r"], // stdin
                1 => ["pipe", "w"], // stdout
                2 => ["pipe", "w"]  // stderr
            ];
            
            $process = proc_open('php', $descriptorspec, $pipes[$i]);
            
            if (is_resource($process)) {
                $processes[$i] = $process;
                
                // Send data to worker
                $workerData = [
                    'chunk' => $chunk,
                    'data' => $this->data,
                    'options' => $this->options ?? []
                ];
                
                fwrite($pipes[$i][0], serialize($workerData));
                fclose($pipes[$i][0]);
            }
        }
        
        // Collect results
        $results = [];
        foreach ($processes as $i => $process) {
            $output = stream_get_contents($pipes[$i][1]);
            $error = stream_get_contents($pipes[$i][2]);
            
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            
            $workerResults = unserialize($output);
            if ($workerResults !== false) {
                $results = array_merge($results, $workerResults);
            }
            
            proc_close($process);
        }
        
        return $results;
    }
    
    /**
     * Evaluate individual with caching
     */
    private function evaluateWithCache(array $individual): array {
        $cached = $this->cache->getCachedFitness($individual);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $fitness = parent::evaluate($individual);
        $this->cache->cacheFitnessEvaluation($individual, $fitness);
        
        return $fitness;
    }
    
    /**
     * Detect optimal number of workers
     */
    private function detectOptimalWorkers(): int {
        $cores = $this->getCpuCoreCount();
        
        // Use 75% of available cores to leave some for system processes
        return max(1, intval($cores * 0.75));
    }
    
    /**
     * Get CPU core count
     */
    private function getCpuCoreCount(): int {
        if (function_exists('sys_getloadavg')) {
            // Try to detect from system load
            $load = sys_getloadavg();
            if ($load[0] > 0) {
                return intval($load[0] * 2); // Rough estimate
            }
        }
        
        // Default fallback
        return 4;
    }
    
    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): array {
        return [
            'cache_stats' => $this->cache->getStats(),
            'max_workers' => $this->maxWorkers,
            'use_parallel' => $this->useParallel,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
}

/**
 * Worker script for parallel processing
 * This would be a separate file: ga/fitness_worker.php
 */
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'fitness_worker.php') {
    // This is a worker process
    $input = '';
    while (!feof(STDIN)) {
        $input .= fread(STDIN, 8192);
    }
    
    $workerData = unserialize($input);
    
    if ($workerData && isset($workerData['chunk']) && isset($workerData['data'])) {
        require_once __DIR__ . '/FitnessEvaluator.php';
        require_once __DIR__ . '/ConstraintChecker.php';
        
        $evaluator = new FitnessEvaluator($workerData['data'], $workerData['options'] ?? []);
        
        $results = [];
        foreach ($workerData['chunk'] as $index => $individual) {
            $results[$index] = [
                'individual' => $individual,
                'fitness' => $evaluator->evaluate($individual)
            ];
        }
        
        echo serialize($results);
    }
    
    exit(0);
}
?>

