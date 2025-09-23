<?php
/**
 * Intelligent Caching System for Timetable Generation
 * 
 * This class provides advanced caching mechanisms for fitness evaluation,
 * constraint checking, and other computationally expensive operations.
 */

class IntelligentCache {
    private $cache = [];
    private $cacheStats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'evictions' => 0
    ];
    private $maxSize = 10000; // Maximum cache entries
    private $ttl = 3600; // Time to live in seconds
    
    /**
     * Get cached value with intelligent key generation
     */
    public function get($key, $context = null) {
        $fullKey = $this->generateKey($key, $context);
        
        if (isset($this->cache[$fullKey])) {
            $entry = $this->cache[$fullKey];
            
            // Check TTL
            if (time() - $entry['timestamp'] < $this->ttl) {
                $this->cacheStats['hits']++;
                return $entry['value'];
            } else {
                // Expired entry
                unset($this->cache[$fullKey]);
                $this->cacheStats['evictions']++;
            }
        }
        
        $this->cacheStats['misses']++;
        return null;
    }
    
    /**
     * Set cached value with intelligent eviction
     */
    public function set($key, $value, $context = null) {
        $fullKey = $this->generateKey($key, $context);
        
        // Evict if cache is full
        if (count($this->cache) >= $this->maxSize) {
            $this->evictOldest();
        }
        
        $this->cache[$fullKey] = [
            'value' => $value,
            'timestamp' => time(),
            'access_count' => 0
        ];
        
        $this->cacheStats['sets']++;
    }
    
    /**
     * Get or compute value with caching
     */
    public function remember($key, $callback, $context = null) {
        $cached = $this->get($key, $context);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $value = $callback();
        $this->set($key, $value, $context);
        
        return $value;
    }
    
    /**
     * Cache fitness evaluation results
     */
    public function cacheFitnessEvaluation(array $individual, array $fitness) {
        $key = $this->generateIndividualKey($individual);
        $this->set("fitness_" . $key, $fitness);
    }
    
    /**
     * Get cached fitness evaluation
     */
    public function getCachedFitness(array $individual) {
        $key = $this->generateIndividualKey($individual);
        return $this->get("fitness_" . $key);
    }
    
    /**
     * Cache constraint check results
     */
    public function cacheConstraintCheck(array $individual, string $constraintType, array $result) {
        $key = $this->generateIndividualKey($individual);
        $this->set("constraint_{$constraintType}_" . $key, $result);
    }
    
    /**
     * Get cached constraint check
     */
    public function getCachedConstraintCheck(array $individual, string $constraintType) {
        $key = $this->generateIndividualKey($individual);
        return $this->get("constraint_{$constraintType}_" . $key);
    }
    
    /**
     * Cache conflict detection results
     */
    public function cacheConflictDetection(array $gene1, array $gene2, bool $hasConflict) {
        $key1 = $this->generateGeneKey($gene1);
        $key2 = $this->generateGeneKey($gene2);
        
        // Use consistent ordering for cache key
        $cacheKey = $key1 < $key2 ? "conflict_{$key1}_{$key2}" : "conflict_{$key2}_{$key1}";
        
        $this->set($cacheKey, $hasConflict);
    }
    
    /**
     * Get cached conflict detection
     */
    public function getCachedConflictDetection(array $gene1, array $gene2) {
        $key1 = $this->generateGeneKey($gene1);
        $key2 = $this->generateGeneKey($gene2);
        
        $cacheKey = $key1 < $key2 ? "conflict_{$key1}_{$key2}" : "conflict_{$key2}_{$key1}";
        
        return $this->get($cacheKey);
    }
    
    /**
     * Generate intelligent cache key for individual
     */
    private function generateIndividualKey(array $individual): string {
        // Use a subset of assignments for key generation to balance uniqueness and performance
        $keyData = [];
        $sampleSize = min(20, count($individual));
        $step = max(1, intval(count($individual) / $sampleSize));
        
        for ($i = 0; $i < count($individual); $i += $step) {
            $gene = $individual[$i];
            $keyData[] = $gene['class_course_id'] . ':' . 
                        ($gene['day_id'] ?? 'null') . ':' . 
                        ($gene['time_slot_id'] ?? 'null') . ':' . 
                        ($gene['room_id'] ?? 'null');
        }
        
        return md5(implode('|', $keyData));
    }
    
    /**
     * Generate cache key for gene
     */
    private function generateGeneKey(array $gene): string {
        return $gene['class_course_id'] . ':' . 
               ($gene['day_id'] ?? 'null') . ':' . 
               ($gene['time_slot_id'] ?? 'null') . ':' . 
               ($gene['room_id'] ?? 'null');
    }
    
    /**
     * Generate full cache key with context
     */
    private function generateKey($key, $context = null): string {
        if ($context !== null) {
            return $key . '_' . md5(serialize($context));
        }
        return $key;
    }
    
    /**
     * Evict oldest cache entries
     */
    private function evictOldest() {
        if (empty($this->cache)) {
            return;
        }
        
        // Find oldest entry
        $oldestKey = null;
        $oldestTime = time();
        
        foreach ($this->cache as $key => $entry) {
            if ($entry['timestamp'] < $oldestTime) {
                $oldestTime = $entry['timestamp'];
                $oldestKey = $key;
            }
        }
        
        if ($oldestKey !== null) {
            unset($this->cache[$oldestKey]);
            $this->cacheStats['evictions']++;
        }
    }
    
    /**
     * Clear cache
     */
    public function clear() {
        $this->cache = [];
        $this->cacheStats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'evictions' => 0
        ];
    }
    
    /**
     * Get cache statistics
     */
    public function getStats(): array {
        $hitRate = $this->cacheStats['hits'] + $this->cacheStats['misses'] > 0 
            ? $this->cacheStats['hits'] / ($this->cacheStats['hits'] + $this->cacheStats['misses']) 
            : 0;
        
        return [
            'entries' => count($this->cache),
            'max_size' => $this->maxSize,
            'hit_rate' => round($hitRate * 100, 2),
            'stats' => $this->cacheStats,
            'memory_usage' => memory_get_usage(true)
        ];
    }
    
    /**
     * Optimize cache by removing least accessed entries
     */
    public function optimize() {
        if (count($this->cache) < $this->maxSize * 0.8) {
            return; // No need to optimize yet
        }
        
        // Sort by access count and remove least accessed entries
        uasort($this->cache, function($a, $b) {
            return $a['access_count'] <=> $b['access_count'];
        });
        
        $removeCount = intval(count($this->cache) * 0.2); // Remove 20%
        $removed = 0;
        
        foreach ($this->cache as $key => $entry) {
            if ($removed >= $removeCount) {
                break;
            }
            
            unset($this->cache[$key]);
            $removed++;
            $this->cacheStats['evictions']++;
        }
    }
}
?>

