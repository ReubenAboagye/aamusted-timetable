<?php
/**
 * Debug script to identify lecturer conflicts in generated timetables
 */

ini_set('max_execution_time', 60);
ini_set('memory_limit', '512M');

include 'connect.php';
require_once __DIR__ . '/ga/GeneticAlgorithm.php';
require_once __DIR__ . '/ga/DBLoader.php';
require_once __DIR__ . '/ga/TimetableRepresentation.php';
require_once __DIR__ . '/ga/ConstraintChecker.php';
require_once __DIR__ . '/ga/FitnessEvaluator.php';

// Include stream manager
if (file_exists(__DIR__ . '/includes/stream_manager.php')) include_once __DIR__ . '/includes/stream_manager.php';
$streamManager = getStreamManager();
$current_stream_id = $streamManager->getCurrentStreamId();

echo "<h2>Lecturer Conflict Debugging</h2>\n";

// Check current timetable data
$semester = 2; // Default to semester 2
$stream_id = $current_stream_id;
$academic_year = '2024/2025'; // Default academic year

echo "<h3>1. Checking Current Timetable Data</h3>\n";

$timetable_query = "
    SELECT 
        t.id,
        t.class_course_id,
        t.day_id,
        t.time_slot_id,
        t.room_id,
        t.lecturer_course_id,
        t.division_label,
        cc.class_id,
        cc.course_id,
        lc.lecturer_id as assigned_lecturer_id,
        l.name as lecturer_name,
        c.name as course_name,
        cl.name as class_name,
        d.name as day_name,
        ts.start_time,
        ts.end_time,
        r.name as room_name
    FROM timetable t
    LEFT JOIN class_courses cc ON t.class_course_id = cc.id
    LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    LEFT JOIN lecturers l ON lc.lecturer_id = l.id
    LEFT JOIN courses c ON cc.course_id = c.id
    LEFT JOIN classes cl ON cc.class_id = cl.id
    LEFT JOIN days d ON t.day_id = d.id
    LEFT JOIN time_slots ts ON t.time_slot_id = ts.id
    LEFT JOIN rooms r ON t.room_id = r.id
    WHERE t.semester = ? AND t.academic_year = ?
    ORDER BY t.day_id, ts.start_time, l.name
";

$stmt = $conn->prepare($timetable_query);
$stmt->bind_param("is", $semester, $academic_year);
$stmt->execute();
$result = $stmt->get_result();
$timetable_entries = $result->fetch_all(MYSQLI_ASSOC);

echo "<p>Found " . count($timetable_entries) . " timetable entries</p>\n";

// Check for lecturer conflicts
$lecturer_slots = [];
$conflicts = [];

foreach ($timetable_entries as $entry) {
    $lecturer_id = $entry['assigned_lecturer_id'];
    if (!$lecturer_id) continue;
    
    $key = $lecturer_id . '|' . $entry['day_id'] . '|' . $entry['time_slot_id'];
    
    if (isset($lecturer_slots[$key])) {
        $conflicts[] = [
            'lecturer_id' => $lecturer_id,
            'lecturer_name' => $entry['lecturer_name'],
            'day' => $entry['day_name'],
            'time' => $entry['start_time'] . '-' . $entry['end_time'],
            'conflict1' => $lecturer_slots[$key],
            'conflict2' => $entry
        ];
    } else {
        $lecturer_slots[$key] = $entry;
    }
}

echo "<h3>2. Lecturer Conflicts Found</h3>\n";
if (empty($conflicts)) {
    echo "<p style='color: green;'>✓ No lecturer conflicts found in current timetable</p>\n";
} else {
    echo "<p style='color: red;'>✗ Found " . count($conflicts) . " lecturer conflicts:</p>\n";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr><th>Lecturer</th><th>Day</th><th>Time</th><th>Conflict 1</th><th>Conflict 2</th></tr>\n";
    
    foreach ($conflicts as $conflict) {
        echo "<tr>\n";
        echo "<td>{$conflict['lecturer_name']}</td>\n";
        echo "<td>{$conflict['day']}</td>\n";
        echo "<td>{$conflict['time']}</td>\n";
        echo "<td>{$conflict['conflict1']['class_name']} - {$conflict['conflict1']['course_name']} ({$conflict['conflict1']['room_name']})</td>\n";
        echo "<td>{$conflict['conflict2']['class_name']} - {$conflict['conflict2']['course_name']} ({$conflict['conflict2']['room_name']})</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

echo "<h3>3. Testing Constraint Checker</h3>\n";

// Load data for constraint checker
$loader = new DBLoader($conn);
$data = $loader->loadAll([
    'stream_id' => $stream_id,
    'semester' => $semester,
    'academic_year' => null
]);

$constraintChecker = new ConstraintChecker($data);

// Convert timetable entries to individual format for testing
$individual = [];
foreach ($timetable_entries as $entry) {
    $class_course_id = $entry['class_course_id'];
    $individual[$class_course_id] = [
        'class_course_id' => $class_course_id,
        'class_id' => $entry['class_id'],
        'course_id' => $entry['course_id'],
        'day_id' => $entry['day_id'],
        'time_slot_id' => $entry['time_slot_id'],
        'room_id' => $entry['room_id'],
        'lecturer_course_id' => $entry['lecturer_course_id'],
        'division_label' => $entry['division_label']
    ];
}

// Test constraint checker
$fitness = $constraintChecker->evaluateFitness($individual);

echo "<h4>Constraint Checker Results:</h4>\n";
echo "<p>Hard Score: {$fitness['hard_score']}</p>\n";
echo "<p>Soft Score: {$fitness['soft_score']}</p>\n";
echo "<p>Total Score: {$fitness['total_score']}</p>\n";
echo "<p>Is Feasible: " . ($fitness['is_feasible'] ? 'Yes' : 'No') . "</p>\n";

if (!empty($fitness['hard_violations']['lecturer_conflict'])) {
    echo "<h4>Lecturer Conflicts Detected by Constraint Checker:</h4>\n";
    echo "<ul>\n";
    foreach ($fitness['hard_violations']['lecturer_conflict'] as $violation) {
        echo "<li>Class Course ID {$violation['class_course_id']}: {$violation['message']}</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<p style='color: green;'>✓ Constraint checker found no lecturer conflicts</p>\n";
}

echo "<h3>4. Testing Genetic Algorithm Generation</h3>\n";

// Test generating a new timetable
$gaOptions = [
    'population_size' => 10,
    'generations' => 5,
    'mutation_rate' => 0.1,
    'crossover_rate' => 0.8,
    'max_runtime' => 30,
    'stream_id' => $stream_id,
    'semester' => $semester,
    'academic_year' => null
];

$ga = new GeneticAlgorithm($conn, $gaOptions);
$results = $ga->run();

echo "<h4>GA Generation Results:</h4>\n";
echo "<p>Generations completed: {$results['generations_completed']}</p>\n";
echo "<p>Runtime: " . round($results['runtime'], 2) . " seconds</p>\n";

if ($results['solution']) {
    $solution_fitness = $results['solution']['fitness'];
    echo "<p>Best solution fitness:</p>\n";
    echo "<ul>\n";
    echo "<li>Hard Score: {$solution_fitness['hard_score']}</li>\n";
    echo "<li>Soft Score: {$solution_fitness['soft_score']}</li>\n";
    echo "<li>Total Score: {$solution_fitness['total_score']}</li>\n";
    echo "<li>Is Feasible: " . ($solution_fitness['is_feasible'] ? 'Yes' : 'No') . "</li>\n";
    echo "</ul>\n";
    
    // Check for lecturer conflicts in the generated solution
    $lecturer_slots_test = [];
    $test_conflicts = [];
    
    foreach ($results['solution']['individual'] as $class_course_id => $gene) {
        $lecturer_id = $gene['lecturer_course_id'] ?? $gene['lecturer_id'];
        if (!$lecturer_id) continue;
        
        $key = $lecturer_id . '|' . $gene['day_id'] . '|' . $gene['time_slot_id'];
        
        if (isset($lecturer_slots_test[$key])) {
            $test_conflicts[] = [
                'lecturer_id' => $lecturer_id,
                'day_id' => $gene['day_id'],
                'time_slot_id' => $gene['time_slot_id'],
                'conflict1' => $lecturer_slots_test[$key],
                'conflict2' => $class_course_id
            ];
        } else {
            $lecturer_slots_test[$key] = $class_course_id;
        }
    }
    
    if (!empty($test_conflicts)) {
        echo "<p style='color: red;'>✗ Generated solution has " . count($test_conflicts) . " lecturer conflicts:</p>\n";
        foreach ($test_conflicts as $conflict) {
            echo "<p>Lecturer {$conflict['lecturer_id']} has conflicts on day {$conflict['day_id']}, time slot {$conflict['time_slot_id']} between class courses {$conflict['conflict1']} and {$conflict['conflict2']}</p>\n";
        }
    } else {
        echo "<p style='color: green;'>✓ Generated solution has no lecturer conflicts</p>\n";
    }
}

echo "<h3>5. Summary</h3>\n";
if (empty($conflicts) && empty($test_conflicts)) {
    echo "<p style='color: green;'>✓ No lecturer conflicts detected in current system</p>\n";
} else {
    echo "<p style='color: red;'>✗ Lecturer conflicts detected - constraint checking may need improvement</p>\n";
}

$conn->close();
?>
