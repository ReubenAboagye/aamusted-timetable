<?php
/**
 * Test script to verify the constraint checking fix
 */

include 'connect.php';
require_once __DIR__ . '/ga/GeneticAlgorithm.php';
require_once __DIR__ . '/ga/DBLoader.php';
require_once __DIR__ . '/ga/TimetableRepresentation.php';
require_once __DIR__ . '/ga/ConstraintChecker.php';
require_once __DIR__ . '/ga/FitnessEvaluator.php';

echo "<h2>Testing Constraint Fix</h2>\n";

// Test 1: Check existing timetable data with correct academic year
echo "<h3>1. Checking Existing Timetable Data</h3>\n";

$query = "SELECT DISTINCT academic_year FROM timetable LIMIT 5";
$result = $conn->query($query);
$academic_years = [];
while ($row = $result->fetch_assoc()) {
    $academic_years[] = $row['academic_year'];
}

echo "<p>Available academic years: " . implode(', ', $academic_years) . "</p>\n";

if (!empty($academic_years)) {
    $academic_year = $academic_years[0];
    echo "<p>Using academic year: $academic_year</p>\n";
    
    // Check for lecturer conflicts with correct academic year
    $query = "
        SELECT 
            lc.lecturer_id,
            l.name as lecturer_name,
            t.day_id,
            t.time_slot_id,
            COUNT(*) as conflict_count
        FROM timetable t
        LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
        LEFT JOIN lecturers l ON lc.lecturer_id = l.id
        WHERE lc.lecturer_id IS NOT NULL AND t.academic_year = ?
        GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id
        HAVING COUNT(*) > 1
        ORDER BY conflict_count DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $academic_year);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<h4>Lecturer Conflicts in Database:</h4>\n";
    if ($result->num_rows > 0) {
        echo "<p style='color: red;'>Found lecturer conflicts:</p>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Lecturer</th><th>Day</th><th>Time Slot</th><th>Count</th></tr>\n";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>\n";
            echo "<td>{$row['lecturer_name']}</td>\n";
            echo "<td>{$row['day_id']}</td>\n";
            echo "<td>{$row['time_slot_id']}</td>\n";
            echo "<td>{$row['conflict_count']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p style='color: green;'>No lecturer conflicts found in database</p>\n";
    }
}

// Test 2: Test constraint checker with sample data
echo "<h3>2. Testing Constraint Checker with Sample Data</h3>\n";

// Load data
$loader = new DBLoader($conn);
$data = $loader->loadAll([
    'stream_id' => 3,
    'semester' => 2,
    'academic_year' => $academic_year ?? '2024/2025'
]);

echo "<p>Loaded data: " . count($data['class_courses']) . " class courses, " . count($data['lecturer_courses']) . " lecturer courses</p>\n";

// Create a test individual with intentional lecturer conflicts
$test_individual = [];

// Get some sample class courses and lecturer courses
$class_courses = array_slice($data['class_courses'], 0, 5);
$lecturer_courses = array_slice($data['lecturer_courses'], 0, 3);

foreach ($class_courses as $i => $cc) {
    $lecturer_course = $lecturer_courses[$i % count($lecturer_courses)];
    
    $test_individual[$cc['id']] = [
        'class_course_id' => $cc['id'],
        'class_id' => $cc['class_id'],
        'course_id' => $cc['course_id'],
        'day_id' => 1, // Same day for all
        'time_slot_id' => 1, // Same time slot for all (this should create conflicts)
        'room_id' => 1 + $i, // Different rooms
        'lecturer_course_id' => $lecturer_course['id'],
        'division_label' => null
    ];
}

echo "<p>Created test individual with " . count($test_individual) . " genes</p>\n";

// Test constraint checker
$constraintChecker = new ConstraintChecker($data);
$fitness = $constraintChecker->evaluateFitness($test_individual);

echo "<h4>Constraint Checker Results:</h4>\n";
echo "<p>Hard Score: {$fitness['hard_score']}</p>\n";
echo "<p>Soft Score: {$fitness['soft_score']}</p>\n";
echo "<p>Total Score: {$fitness['total_score']}</p>\n";
echo "<p>Is Feasible: " . ($fitness['is_feasible'] ? 'Yes' : 'No') . "</p>\n";

if (!empty($fitness['hard_violations']['lecturer_conflict'])) {
    echo "<h4>Lecturer Conflicts Detected:</h4>\n";
    echo "<p style='color: red;'>Found " . count($fitness['hard_violations']['lecturer_conflict']) . " lecturer conflicts</p>\n";
    foreach ($fitness['hard_violations']['lecturer_conflict'] as $violation) {
        echo "<p>Class Course ID {$violation['class_course_id']}: {$violation['message']}</p>\n";
    }
} else {
    echo "<p style='color: red;'>❌ No lecturer conflicts detected - this indicates the fix is not working</p>\n";
}

// Test 3: Test the getLecturerConflictKey method directly
echo "<h3>3. Testing getLecturerConflictKey Method</h3>\n";

$test_gene = $test_individual[array_key_first($test_individual)];
$lecturer_key = TimetableRepresentation::getLecturerConflictKey($test_gene, $data);

echo "<p>Test gene lecturer_course_id: {$test_gene['lecturer_course_id']}</p>\n";
echo "<p>Generated lecturer key: $lecturer_key</p>\n";

// Find the actual lecturer ID
$lecturer_id = null;
foreach ($data['lecturer_courses'] as $lc) {
    if ($lc['id'] == $test_gene['lecturer_course_id']) {
        $lecturer_id = $lc['lecturer_id'];
        break;
    }
}

echo "<p>Resolved lecturer_id: $lecturer_id</p>\n";
echo "<p>Expected key format: {$lecturer_id}|1|1</p>\n";

if ($lecturer_key === "{$lecturer_id}|1|1") {
    echo "<p style='color: green;'>✓ getLecturerConflictKey is working correctly</p>\n";
} else {
    echo "<p style='color: red;'>❌ getLecturerConflictKey is not working correctly</p>\n";
}

$conn->close();
?>




