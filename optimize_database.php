<?php
/**
 * Database Optimization Script for Timetable Generation
 * 
 * This script creates optimized indexes and performs database maintenance
 * to improve query performance for timetable generation.
 */

include 'connect.php';

class DatabaseOptimizer {
    private $conn;
    
    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }
    
    /**
     * Create optimized indexes for timetable generation
     */
    public function createOptimizedIndexes(): array {
        $results = [];
        
        // Indexes for class_courses table
        $indexes = [
            // Primary performance indexes
            "CREATE INDEX IF NOT EXISTS idx_class_courses_stream_semester ON class_courses(class_id, semester, academic_year, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_class_courses_course_active ON class_courses(course_id, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_class_courses_lecturer ON class_courses(lecturer_id, is_active)",
            
            // Indexes for classes table
            "CREATE INDEX IF NOT EXISTS idx_classes_stream_active ON classes(stream_id, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_classes_program_level ON classes(program_id, level_id, is_active)",
            
            // Indexes for timetable table
            "CREATE INDEX IF NOT EXISTS idx_timetable_stream_semester ON timetable(semester, academic_year)",
            "CREATE INDEX IF NOT EXISTS idx_timetable_day_time ON timetable(day_id, time_slot_id)",
            "CREATE INDEX IF NOT EXISTS idx_timetable_room ON timetable(room_id)",
            "CREATE INDEX IF NOT EXISTS idx_timetable_class_course ON timetable(class_course_id)",
            "CREATE INDEX IF NOT EXISTS idx_timetable_lecturer ON timetable(lecturer_course_id)",
            
            // Composite indexes for common queries
            "CREATE INDEX IF NOT EXISTS idx_timetable_composite ON timetable(day_id, time_slot_id, room_id, semester)",
            "CREATE INDEX IF NOT EXISTS idx_timetable_class_semester ON timetable(class_course_id, semester, academic_year)",
            
            // Indexes for rooms table
            "CREATE INDEX IF NOT EXISTS idx_rooms_building_active ON rooms(building_id, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_rooms_type_capacity ON rooms(room_type, capacity, is_active)",
            
            // Indexes for time_slots table
            "CREATE INDEX IF NOT EXISTS idx_time_slots_mandatory ON time_slots(is_mandatory, is_break)",
            "CREATE INDEX IF NOT EXISTS idx_time_slots_start_time ON time_slots(start_time)",
            
            // Indexes for lecturer_courses table
            "CREATE INDEX IF NOT EXISTS idx_lecturer_courses_course ON lecturer_courses(course_id, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_lecturer_courses_lecturer ON lecturer_courses(lecturer_id, is_active)",
            
            // Indexes for stream_time_slots table
            "CREATE INDEX IF NOT EXISTS idx_stream_time_slots_stream ON stream_time_slots(stream_id, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_stream_time_slots_time_slot ON stream_time_slots(time_slot_id, is_active)",
            
            // Indexes for course_room_types table
            "CREATE INDEX IF NOT EXISTS idx_course_room_types_course ON course_room_types(course_id, is_active)",
            "CREATE INDEX IF NOT EXISTS idx_course_room_types_room_type ON course_room_types(room_type_id, is_active)"
        ];
        
        foreach ($indexes as $indexSql) {
            try {
                if ($this->conn->query($indexSql)) {
                    $results[] = "SUCCESS: " . $indexSql;
                } else {
                    $results[] = "ERROR: " . $this->conn->error . " - " . $indexSql;
                }
            } catch (Exception $e) {
                $results[] = "EXCEPTION: " . $e->getMessage() . " - " . $indexSql;
            }
        }
        
        return $results;
    }
    
    /**
     * Analyze table statistics for query optimization
     */
    public function analyzeTables(): array {
        $tables = [
            'class_courses', 'classes', 'courses', 'lecturers', 'rooms',
            'time_slots', 'days', 'timetable', 'lecturer_courses',
            'stream_time_slots', 'course_room_types', 'buildings',
            'room_types', 'streams', 'programs', 'departments', 'levels'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            try {
                $sql = "ANALYZE TABLE `$table`";
                if ($this->conn->query($sql)) {
                    $results[] = "ANALYZED: $table";
                } else {
                    $results[] = "ERROR analyzing $table: " . $this->conn->error;
                }
            } catch (Exception $e) {
                $results[] = "EXCEPTION analyzing $table: " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Optimize table storage
     */
    public function optimizeTables(): array {
        $tables = [
            'class_courses', 'classes', 'courses', 'lecturers', 'rooms',
            'time_slots', 'days', 'timetable', 'lecturer_courses',
            'stream_time_slots', 'course_room_types', 'buildings',
            'room_types', 'streams', 'programs', 'departments', 'levels'
        ];
        
        $results = [];
        
        foreach ($tables as $table) {
            try {
                $sql = "OPTIMIZE TABLE `$table`";
                if ($this->conn->query($sql)) {
                    $results[] = "OPTIMIZED: $table";
                } else {
                    $results[] = "ERROR optimizing $table: " . $this->conn->error;
                }
            } catch (Exception $e) {
                $results[] = "EXCEPTION optimizing $table: " . $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Get database performance statistics
     */
    public function getPerformanceStats(): array {
        $stats = [];
        
        // Get table sizes
        $sql = "SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size_MB',
                    table_rows
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC";
        
        $result = $this->conn->query($sql);
        if ($result) {
            $stats['table_sizes'] = [];
            while ($row = $result->fetch_assoc()) {
                $stats['table_sizes'][] = $row;
            }
        }
        
        // Get index usage statistics
        $sql = "SELECT 
                    table_name,
                    index_name,
                    cardinality
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE()
                ORDER BY table_name, cardinality DESC";
        
        $result = $this->conn->query($sql);
        if ($result) {
            $stats['index_usage'] = [];
            while ($row = $result->fetch_assoc()) {
                $stats['index_usage'][] = $row;
            }
        }
        
        // Get slow query log status
        $sql = "SHOW VARIABLES LIKE 'slow_query_log'";
        $result = $this->conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $stats['slow_query_log'] = $row['Value'];
        }
        
        return $stats;
    }
    
    /**
     * Check for missing indexes on frequently queried columns
     */
    public function checkMissingIndexes(): array {
        $recommendations = [];
        
        // Check for missing indexes on foreign key columns
        $foreignKeyChecks = [
            "SELECT COUNT(*) as count FROM class_courses WHERE class_id IS NOT NULL" => "idx_class_courses_class_id",
            "SELECT COUNT(*) as count FROM class_courses WHERE course_id IS NOT NULL" => "idx_class_courses_course_id",
            "SELECT COUNT(*) as count FROM timetable WHERE day_id IS NOT NULL" => "idx_timetable_day_id",
            "SELECT COUNT(*) as count FROM timetable WHERE time_slot_id IS NOT NULL" => "idx_timetable_time_slot_id",
            "SELECT COUNT(*) as count FROM timetable WHERE room_id IS NOT NULL" => "idx_timetable_room_id"
        ];
        
        foreach ($foreignKeyChecks as $query => $suggestedIndex) {
            $result = $this->conn->query($query);
            if ($result && $row = $result->fetch_assoc()) {
                if ($row['count'] > 100) { // Only recommend for tables with significant data
                    $recommendations[] = "Consider adding index: $suggestedIndex (affects {$row['count']} rows)";
                }
            }
        }
        
        return $recommendations;
    }
}

// Run optimization if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $optimizer = new DatabaseOptimizer($conn);
    
    echo "<h2>Database Optimization Results</h2>\n";
    
    echo "<h3>Creating Indexes</h3>\n";
    $indexResults = $optimizer->createOptimizedIndexes();
    foreach ($indexResults as $result) {
        echo "<p>$result</p>\n";
    }
    
    echo "<h3>Analyzing Tables</h3>\n";
    $analyzeResults = $optimizer->analyzeTables();
    foreach ($analyzeResults as $result) {
        echo "<p>$result</p>\n";
    }
    
    echo "<h3>Optimizing Tables</h3>\n";
    $optimizeResults = $optimizer->optimizeTables();
    foreach ($optimizeResults as $result) {
        echo "<p>$result</p>\n";
    }
    
    echo "<h3>Performance Statistics</h3>\n";
    $stats = $optimizer->getPerformanceStats();
    echo "<pre>" . print_r($stats, true) . "</pre>\n";
    
    echo "<h3>Missing Index Recommendations</h3>\n";
    $recommendations = $optimizer->checkMissingIndexes();
    foreach ($recommendations as $recommendation) {
        echo "<p>$recommendation</p>\n";
    }
}
?>

