<?php
include 'connect.php';

echo "<h2>Regenerating Timetable with Individual Class Divisions</h2>";

// Step 1: Clear current timetable data
echo "<h3>Step 1: Clearing Current Timetable Data</h3>";

$clear_sql = "DELETE FROM timetable WHERE semester = 'second'";
$clear_result = $conn->query($clear_sql);

if ($clear_result) {
    $affected_rows = $conn->affected_rows;
    echo "<p>✓ Cleared $affected_rows timetable entries</p>";
} else {
    echo "<p>✗ Error clearing timetable: " . $conn->error . "</p>";
    exit;
}

// Step 2: Check if genetic algorithm files exist
echo "<h3>Step 2: Checking Genetic Algorithm Files</h3>";

$required_files = [
    'ga/DBLoader.php',
    'ga/TimetableRepresentation.php', 
    'ga/ConstraintChecker.php',
    'ga/GeneticAlgorithm.php'
];

$missing_files = [];
foreach ($required_files as $file) {
    if (!file_exists($file)) {
        $missing_files[] = $file;
    }
}

if (!empty($missing_files)) {
    echo "<p>✗ Missing required files:</p>";
    echo "<ul>";
    foreach ($missing_files as $file) {
        echo "<li>$file</li>";
    }
    echo "</ul>";
    exit;
} else {
    echo "<p>✓ All required genetic algorithm files found</p>";
}

// Step 3: Include genetic algorithm files
echo "<h3>Step 3: Loading Genetic Algorithm</h3>";

require_once 'ga/DBLoader.php';
require_once 'ga/TimetableRepresentation.php';
require_once 'ga/ConstraintChecker.php';
require_once 'ga/GeneticAlgorithm.php';

echo "<p>✓ Genetic algorithm files loaded</p>";

// Include stream manager to pick the currently selected stream from application headers
if (file_exists(__DIR__ . '/includes/stream_manager.php')) {
    require_once __DIR__ . '/includes/stream_manager.php';
    $streamManager = getStreamManager();
    $stream_id = $streamManager->getCurrentStreamId();
} else {
    // Fallback: use stream id 1 if stream manager is not available
    $stream_id = 1;
}
echo "<p>Using stream id: " . htmlspecialchars($stream_id) . "</p>";
// Determine academic year from stream manager if available
if (isset($streamManager) && method_exists($streamManager, 'getCurrentAcademicYear')) {
    $academic_year = $streamManager->getCurrentAcademicYear();
} else {
    // Compute a default academic year
    $m = (int)date('n');
    $y = (int)date('Y');
    $academic_year = ($m >= 8) ? ($y . '/' . ($y + 1)) : (($y - 1) . '/' . $y);
}
echo "<p>Using academic year: " . htmlspecialchars($academic_year) . "</p>";

// Step 4: Generate new timetable
echo "<h3>Step 4: Generating New Timetable</h3>";

try {
    // Data will be loaded by the genetic algorithm internally
    echo "<p>✓ Data loading will be handled by genetic algorithm</p>";
    
    // Initialize genetic algorithm
    $gaOptions = [
        'population_size' => 50,
        'generations' => 100,
        'mutation_rate' => 0.1,
        'crossover_rate' => 0.8,
        'stream_id' => $stream_id,
        'semester' => 2,  // Second semester
        'academic_year' => $academic_year
    ];
    
    $geneticAlgorithm = new GeneticAlgorithm($conn, $gaOptions);
    
    echo "<p>✓ Genetic algorithm initialized</p>";
    
    // Run the algorithm
    echo "<p>Running genetic algorithm...</p>";
    $results = $geneticAlgorithm->run();
    
    echo "<p>✓ Genetic algorithm completed</p>";
    
    // Convert to database format and save
    $databaseData = $geneticAlgorithm->convertToDatabaseFormat($results['solution']);
    
    echo "<p>✓ Solution converted to database format</p>";
    
    // Save to database
    $saved_count = 0;
    
    // Deduplicate entries before insertion
    $unique_entries = [];
    foreach ($databaseData as $entry) {
        $key = $entry['class_course_id'] . '-' . $entry['day_id'] . '-' . $entry['time_slot_id'] . '-' . ($entry['division_label'] ?? 'NULL');
        if (!isset($unique_entries[$key])) {
            $unique_entries[$key] = $entry;
        }
    }
    
    echo "<p>Total entries generated: " . count($databaseData) . "</p>";
    echo "<p>Unique entries after deduplication: " . count($unique_entries) . "</p>";
    
    foreach ($unique_entries as $entry) {
        // Ensure academic_year is populated
        $academic_year = $entry['academic_year'] ?? null;
        if (empty($academic_year)) {
            $m = (int)date('n');
            $y = (int)date('Y');
            $academic_year = ($m >= 8) ? ($y . '/' . ($y + 1)) : (($y - 1) . '/' . $y);
        }

        $sql = "INSERT IGNORE INTO timetable (
            class_course_id, lecturer_course_id, day_id, time_slot_id, 
            room_id, division_label, semester, academic_year, 
            timetable_type, is_active, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = $conn->prepare($sql);

        $class_course_id = $entry['class_course_id'];
        $lecturer_course_id = $entry['lecturer_course_id'];
        $day_id = $entry['day_id'];
        $time_slot_id = $entry['time_slot_id'];
        $room_id = $entry['room_id'];
        $division_label = $entry['division_label'];
        $semester = $entry['semester'];
        $timetable_type = $entry['timetable_type'] ?? 'lecture';
        $is_active = $entry['is_active'] ?? 1;

        $stmt->bind_param(
            "iiiiissssi",
            $class_course_id,
            $lecturer_course_id,
            $day_id,
            $time_slot_id,
            $room_id,
            $division_label,
            $semester,
            $academic_year,
            $timetable_type,
            $is_active
        );
        
        if ($stmt->execute()) {
            $saved_count++;
        }
    }
    
    echo "<p>✓ Saved $saved_count timetable entries to database</p>";
    
    // Verify the results
    echo "<h3>Step 5: Verifying Results</h3>";
    
    $verify_sql = "SELECT t.division_label, c.name as class_name, co.code as course_code, COUNT(*) as count
                   FROM timetable t 
                   JOIN class_courses cc ON t.class_course_id = cc.id 
                   JOIN classes c ON cc.class_id = c.id 
                   JOIN courses co ON cc.course_id = co.id 
                   WHERE t.semester = 'second' AND t.academic_year = '2025/2026'
                   GROUP BY t.division_label, c.name, co.code
                   ORDER BY c.name, t.division_label";
    
    $verify_result = $conn->query($verify_sql);
    
    if ($verify_result) {
        echo "<table border='1'>";
        echo "<tr><th>Class Name</th><th>Division Label</th><th>Course Code</th><th>Count</th></tr>";
        
        while ($row = $verify_result->fetch_assoc()) {
            $display_class = $row['class_name'];
            if ($row['division_label']) {
                $display_class .= ' ' . $row['division_label'];
            }
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($display_class) . "</td>";
            echo "<td>" . ($row['division_label'] ? htmlspecialchars($row['division_label']) : 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['course_code']) . "</td>";
            echo "<td>" . $row['count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<div style='background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>✓ Success!</h4>";
    echo "<p>The timetable has been regenerated with individual class divisions.</p>";
    echo "<p>You should now see individual classes like 'MAN 100A', 'MAN 100B', etc. in the timetable.</p>";
    echo "<p><a href='generate_timetable.php' class='btn btn-primary'>View Generated Timetable</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>✗ Error</h4>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

?>
