<?php
/**
 * Comprehensive solution to fix lecturer conflicts
 * This script clears existing conflicts and generates a new conflict-free timetable
 */

include 'connect.php';
require_once __DIR__ . '/ga/GeneticAlgorithm.php';
require_once __DIR__ . '/ga/DBLoader.php';

echo "<h2>Comprehensive Lecturer Conflict Fix</h2>\n";

// Include stream manager
if (file_exists(__DIR__ . '/includes/stream_manager.php')) include_once __DIR__ . '/includes/stream_manager.php';
$streamManager = getStreamManager();
$current_stream_id = $streamManager->getCurrentStreamId();

$semester = 2;
$academic_year = '2025/2026';

echo "<h3>1. Current Conflict Analysis</h3>\n";

// Check current conflicts
$query = "
    SELECT 
        lc.lecturer_id,
        l.name as lecturer_name,
        COUNT(*) as total_conflicts
    FROM (
        SELECT lc.lecturer_id, t.day_id, t.time_slot_id, COUNT(*) as conflict_count
        FROM timetable t
        LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
        WHERE lc.lecturer_id IS NOT NULL
        GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id
        HAVING COUNT(*) > 1
    ) as conflicts
    LEFT JOIN lecturer_courses lc ON conflicts.lecturer_id = lc.lecturer_id
    LEFT JOIN lecturers l ON lc.lecturer_id = l.id
    GROUP BY lc.lecturer_id, l.name
    ORDER BY total_conflicts DESC
";

$result = $conn->query($query);
$conflicts_before = [];
while ($row = $result->fetch_assoc()) {
    $conflicts_before[] = $row;
}

echo "<p>Found " . count($conflicts_before) . " lecturers with conflicts</p>\n";
if (!empty($conflicts_before)) {
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Lecturer</th><th>Total Conflicts</th></tr>\n";
    foreach ($conflicts_before as $conflict) {
        echo "<tr><td>{$conflict['lecturer_name']}</td><td>{$conflict['total_conflicts']}</td></tr>\n";
    }
    echo "</table>\n";
}

echo "<h3>2. Clearing Existing Timetable</h3>\n";

// Clear existing timetable
$delete_query = "DELETE FROM timetable WHERE semester = ? AND academic_year = ?";
$stmt = $conn->prepare($delete_query);
$stmt->bind_param("is", $semester, $academic_year);
$deleted_rows = $stmt->execute() ? $stmt->affected_rows : 0;

echo "<p>Deleted $deleted_rows timetable entries</p>\n";

echo "<h3>3. Generating New Conflict-Free Timetable</h3>\n";

// Load data
$loader = new DBLoader($conn);
$data = $loader->loadAll([
    'stream_id' => $current_stream_id,
    'semester' => $semester,
    'academic_year' => $academic_year
]);

echo "<p>Loaded data: " . count($data['class_courses']) . " class courses, " . count($data['lecturer_courses']) . " lecturer courses</p>\n";

if (empty($data['class_courses'])) {
    echo "<p style='color: red;'>❌ No class courses found for the specified parameters</p>\n";
    exit;
}

// Optimized GA parameters for conflict resolution
$gaOptions = [
    'population_size' => 200,  // Large population for diversity
    'generations' => 1000,     // Many generations for convergence
    'mutation_rate' => 0.25,   // High mutation rate to escape local optima
    'crossover_rate' => 0.8,
    'max_runtime' => 900,      // 15 minutes for thorough search
    'stream_id' => $current_stream_id,
    'semester' => $semester,
    'academic_year' => $academic_year,
    'fitness_threshold' => 0.98, // Very high threshold for feasibility
    'stagnation_limit' => 150   // More patience for convergence
];

try {
    $ga = new GeneticAlgorithm($conn, $gaOptions);
    
    echo "<p>Starting genetic algorithm with optimized parameters...</p>\n";
    echo "<ul>\n";
    echo "<li>Population size: {$gaOptions['population_size']}</li>\n";
    echo "<li>Generations: {$gaOptions['generations']}</li>\n";
    echo "<li>Mutation rate: {$gaOptions['mutation_rate']}</li>\n";
    echo "<li>Max runtime: {$gaOptions['max_runtime']} seconds</li>\n";
    echo "</ul>\n";
    
    $start_time = microtime(true);
    $results = $ga->run();
    $end_time = microtime(true);
    $runtime = $end_time - $start_time;
    
    echo "<h4>Generation Results:</h4>\n";
    echo "<p>Runtime: " . round($runtime, 2) . " seconds</p>\n";
    echo "<p>Generations completed: {$results['generations_completed']}</p>\n";
    
    if ($results['solution']) {
        $solution_fitness = $results['solution']['fitness'];
        echo "<p>Best solution fitness:</p>\n";
        echo "<ul>\n";
        echo "<li>Hard Score: {$solution_fitness['hard_score']}</li>\n";
        echo "<li>Soft Score: {$solution_fitness['soft_score']}</li>\n";
        echo "<li>Total Score: {$solution_fitness['total_score']}</li>\n";
        echo "<li>Is Feasible: " . ($solution_fitness['is_feasible'] ? 'Yes' : 'No') . "</li>\n";
        echo "</ul>\n";
        
        // Convert solution to database format
        $timetableEntries = $ga->convertToDatabaseFormat($results['solution']);
        
        // Insert into database
        $inserted_count = insertTimetableEntries($conn, $timetableEntries);
        
        if ($inserted_count > 0) {
            echo "<p style='color: green;'>✓ Successfully inserted $inserted_count timetable entries</p>\n";
            
            // Check for conflicts after generation
            $result = $conn->query($query);
            $conflicts_after = [];
            while ($row = $result->fetch_assoc()) {
                $conflicts_after[] = $row;
            }
            
            echo "<h4>Post-Generation Conflict Analysis:</h4>\n";
            echo "<p>Lecturers with conflicts after generation: " . count($conflicts_after) . "</p>\n";
            
            if (empty($conflicts_after)) {
                echo "<p style='color: green; font-size: 1.2em; font-weight: bold;'>🎉 SUCCESS! No lecturer conflicts in the new timetable!</p>\n";
            } else {
                echo "<p style='color: orange;'>⚠️ Still have conflicts with " . count($conflicts_after) . " lecturers</p>\n";
                echo "<table border='1' style='border-collapse: collapse;'>\n";
                echo "<tr><th>Lecturer</th><th>Remaining Conflicts</th></tr>\n";
                foreach ($conflicts_after as $conflict) {
                    echo "<tr><td>{$conflict['lecturer_name']}</td><td>{$conflict['total_conflicts']}</td></tr>\n";
                }
                echo "</table>\n";
            }
            
            // Calculate improvement
            $conflicts_reduced = count($conflicts_before) - count($conflicts_after);
            $improvement_percentage = count($conflicts_before) > 0 ? 
                round(($conflicts_reduced / count($conflicts_before)) * 100, 2) : 0;
            
            echo "<h4>Improvement Summary:</h4>\n";
            echo "<p>Conflicts before: " . count($conflicts_before) . "</p>\n";
            echo "<p>Conflicts after: " . count($conflicts_after) . "</p>\n";
            echo "<p>Conflicts reduced: $conflicts_reduced</p>\n";
            echo "<p>Improvement: $improvement_percentage%</p>\n";
            
        } else {
            echo "<p style='color: red;'>❌ Failed to insert timetable entries</p>\n";
        }
    } else {
        echo "<p style='color: red;'>❌ No solution generated</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error during generation: " . $e->getMessage() . "</p>\n";
}

$conn->close();

// Helper function to insert timetable entries
function insertTimetableEntries($conn, $timetableEntries) {
    $inserted_count = 0;
    
    foreach ($timetableEntries as $entry) {
        $sql = "INSERT INTO timetable (class_course_id, lecturer_course_id, day_id, time_slot_id, room_id, division_label, semester, academic_year, timetable_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        $semester_enum = $entry['semester'] == 1 ? 'first' : 'second';
        $academic_year = $entry['academic_year'] ?? '2025/2026';
        $timetable_type = 'lecture';
        
        $stmt->bind_param("iiiiiisss", 
            $entry['class_course_id'],
            $entry['lecturer_course_id'],
            $entry['day_id'],
            $entry['time_slot_id'],
            $entry['room_id'],
            $entry['division_label'],
            $semester_enum,
            $academic_year,
            $timetable_type
        );
        
        if ($stmt->execute()) {
            $inserted_count++;
        }
    }
    
    return $inserted_count;
}
?>


