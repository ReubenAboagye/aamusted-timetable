<?php
/**
 * Script to clear existing timetable data and generate a new conflict-free timetable
 */

include 'connect.php';
require_once __DIR__ . '/ga/GeneticAlgorithm.php';
require_once __DIR__ . '/ga/DBLoader.php';

echo "<h2>Clearing Existing Timetable and Generating New One</h2>\n";

// Include stream manager
if (file_exists(__DIR__ . '/includes/stream_manager.php')) include_once __DIR__ . '/includes/stream_manager.php';
$streamManager = getStreamManager();
$current_stream_id = $streamManager->getCurrentStreamId();

$semester = 2; // Default to semester 2
$academic_year = '2025/2026'; // Use the actual academic year from database

echo "<h3>1. Clearing Existing Timetable Data</h3>\n";

// Check current conflicts before clearing
$query = "
    SELECT COUNT(*) as total_conflicts
    FROM (
        SELECT lc.lecturer_id, t.day_id, t.time_slot_id, COUNT(*) as conflict_count
        FROM timetable t
        LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
        WHERE lc.lecturer_id IS NOT NULL
        GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id
        HAVING COUNT(*) > 1
    ) as conflicts
";

$result = $conn->query($query);
$conflicts_before = $result->fetch_assoc()['total_conflicts'];
echo "<p>Lecturer conflicts before clearing: $conflicts_before</p>\n";

// Clear existing timetable for this semester and academic year
$delete_query = "DELETE FROM timetable WHERE semester = ? AND academic_year = ?";
$stmt = $conn->prepare($delete_query);
$stmt->bind_param("is", $semester, $academic_year);
$deleted_rows = $stmt->execute() ? $stmt->affected_rows : 0;

echo "<p>Deleted $deleted_rows timetable entries</p>\n";

echo "<h3>2. Generating New Timetable with Fixed Constraints</h3>\n";

// GA parameters for better constraint satisfaction
$gaOptions = [
    'population_size' => 100,  // Increased population for better diversity
    'generations' => 500,      // More generations for better convergence
    'mutation_rate' => 0.15,   // Higher mutation rate to escape local optima
    'crossover_rate' => 0.8,
    'max_runtime' => 600,      // 10 minutes for thorough search
    'stream_id' => $current_stream_id,
    'semester' => $semester,
    'academic_year' => $academic_year,
    'fitness_threshold' => 0.95,
    'stagnation_limit' => 100  // More patience for convergence
];

try {
    $ga = new GeneticAlgorithm($conn, $gaOptions);
    
    // Validate data before generation
    $loader = new DBLoader($conn);
    $data = $loader->loadAll($gaOptions);
    $validation = $loader->validateDataForGeneration($data);
    
    if (!$validation['valid']) {
        echo "<p style='color: red;'>❌ Cannot generate timetable: " . implode(', ', $validation['errors']) . "</p>\n";
    } else {
        echo "<p style='color: green;'>✓ Data validation passed</p>\n";
        echo "<p>Class courses: " . count($data['class_courses']) . "</p>\n";
        echo "<p>Lecturer courses: " . count($data['lecturer_courses']) . "</p>\n";
        echo "<p>Rooms: " . count($data['rooms']) . "</p>\n";
        echo "<p>Time slots: " . count($data['time_slots']) . "</p>\n";
        echo "<p>Days: " . count($data['days']) . "</p>\n";
        
        // Run genetic algorithm
        echo "<p>Starting genetic algorithm...</p>\n";
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
            
            if ($solution_fitness['is_feasible']) {
                echo "<p style='color: green;'>✓ Generated feasible solution!</p>\n";
                
                // Convert solution to database format
                $timetableEntries = $ga->convertToDatabaseFormat($results['solution']);
                
                // Insert into database
                $inserted_count = insertTimetableEntries($conn, $timetableEntries);
                
                if ($inserted_count > 0) {
                    echo "<p style='color: green;'>✓ Successfully inserted $inserted_count timetable entries</p>\n";
                    
                    // Check for conflicts after generation
                    $result = $conn->query($query);
                    $conflicts_after = $result->fetch_assoc()['total_conflicts'];
                    echo "<p>Lecturer conflicts after generation: $conflicts_after</p>\n";
                    
                    if ($conflicts_after == 0) {
                        echo "<p style='color: green;'>🎉 SUCCESS! No lecturer conflicts in the new timetable!</p>\n";
                    } else {
                        echo "<p style='color: orange;'>⚠️ Still have $conflicts_after lecturer conflicts</p>\n";
                    }
                } else {
                    echo "<p style='color: red;'>❌ Failed to insert timetable entries</p>\n";
                }
            } else {
                echo "<p style='color: orange;'>⚠️ Generated solution is not feasible (hard score: {$solution_fitness['hard_score']})</p>\n";
                
                // Still try to insert to see what conflicts remain
                $timetableEntries = $ga->convertToDatabaseFormat($results['solution']);
                $inserted_count = insertTimetableEntries($conn, $timetableEntries);
                
                if ($inserted_count > 0) {
                    echo "<p>Inserted $inserted_count entries for analysis</p>\n";
                    
                    // Check for conflicts after generation
                    $result = $conn->query($query);
                    $conflicts_after = $result->fetch_assoc()['total_conflicts'];
                    echo "<p>Lecturer conflicts after generation: $conflicts_after</p>\n";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ No solution generated</p>\n";
        }
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
