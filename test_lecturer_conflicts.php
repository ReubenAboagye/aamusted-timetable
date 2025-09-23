<?php
/**
 * Comprehensive test to verify lecturer conflict detection is working
 */

include 'connect.php';
require_once __DIR__ . '/ga/TimetableRepresentation.php';
require_once __DIR__ . '/ga/ConstraintChecker.php';
require_once __DIR__ . '/ga/DBLoader.php';

echo "<h2>Lecturer Conflict Detection Test</h2>\n";

// Test 1: Check current database for lecturer conflicts
echo "<h3>1. Current Database Lecturer Conflicts</h3>\n";

$query = "
    SELECT 
        lc.lecturer_id,
        l.name as lecturer_name,
        t.day_id,
        t.time_slot_id,
        COUNT(*) as conflict_count,
        GROUP_CONCAT(t.id) as timetable_ids
    FROM timetable t
    LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    LEFT JOIN lecturers l ON lc.lecturer_id = l.id
    WHERE lc.lecturer_id IS NOT NULL
    GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id
    HAVING COUNT(*) > 1
    ORDER BY conflict_count DESC
    LIMIT 10
";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    echo "<p style='color: red;'>❌ Found lecturer conflicts in database:</p>\n";
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Lecturer</th><th>Day</th><th>Time Slot</th><th>Conflicts</th><th>Timetable IDs</th></tr>\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>\n";
        echo "<td>{$row['lecturer_name']}</td>\n";
        echo "<td>{$row['day_id']}</td>\n";
        echo "<td>{$row['time_slot_id']}</td>\n";
        echo "<td>{$row['conflict_count']}</td>\n";
        echo "<td>{$row['timetable_ids']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p style='color: green;'>✓ No lecturer conflicts found in database</p>\n";
}

// Test 2: Test getLecturerConflictKey method
echo "<h3>2. Testing getLecturerConflictKey Method</h3>\n";

// Load sample lecturer courses
$query = "SELECT id, lecturer_id, course_id FROM lecturer_courses LIMIT 5";
$result = $conn->query($query);
$lecturer_courses = [];
while ($row = $result->fetch_assoc()) {
    $lecturer_courses[] = $row;
}

if (!empty($lecturer_courses)) {
    $data = ['lecturer_courses' => $lecturer_courses];
    
    $test_gene = [
        'lecturer_course_id' => $lecturer_courses[0]['id'],
        'day_id' => 1,
        'time_slot_id' => 1
    ];
    
    $lecturer_key = TimetableRepresentation::getLecturerConflictKey($test_gene, $data);
    $expected_lecturer_id = $lecturer_courses[0]['lecturer_id'];
    $expected_key = "{$expected_lecturer_id}|1|1";
    
    echo "<p>Test gene lecturer_course_id: {$test_gene['lecturer_course_id']}</p>\n";
    echo "<p>Expected lecturer_id: $expected_lecturer_id</p>\n";
    echo "<p>Generated lecturer key: $lecturer_key</p>\n";
    echo "<p>Expected key: $expected_key</p>\n";
    
    if ($lecturer_key === $expected_key) {
        echo "<p style='color: green;'>✓ getLecturerConflictKey is working correctly</p>\n";
    } else {
        echo "<p style='color: red;'>❌ getLecturerConflictKey is not working correctly</p>\n";
    }
}

// Test 3: Test genesConflict method
echo "<h3>3. Testing genesConflict Method</h3>\n";

if (!empty($lecturer_courses)) {
    $data = ['lecturer_courses' => $lecturer_courses];
    
    // Create two genes with the same lecturer, day, and time (should conflict)
    $gene1 = [
        'lecturer_course_id' => $lecturer_courses[0]['id'],
        'day_id' => 1,
        'time_slot_id' => 1,
        'room_id' => 1,
        'class_id' => 1
    ];
    
    $gene2 = [
        'lecturer_course_id' => $lecturer_courses[0]['id'], // Same lecturer
        'day_id' => 1, // Same day
        'time_slot_id' => 1, // Same time slot
        'room_id' => 2, // Different room
        'class_id' => 2 // Different class
    ];
    
    $conflicts = TimetableRepresentation::genesConflict($gene1, $gene2, $data);
    
    echo "<p>Gene 1: lecturer_course_id={$gene1['lecturer_course_id']}, day={$gene1['day_id']}, time={$gene1['time_slot_id']}</p>\n";
    echo "<p>Gene 2: lecturer_course_id={$gene2['lecturer_course_id']}, day={$gene2['day_id']}, time={$gene2['time_slot_id']}</p>\n";
    echo "<p>Should conflict: Yes (same lecturer, day, time)</p>\n";
    echo "<p>Actually conflicts: " . ($conflicts ? 'Yes' : 'No') . "</p>\n";
    
    if ($conflicts) {
        echo "<p style='color: green;'>✓ genesConflict is working correctly</p>\n";
    } else {
        echo "<p style='color: red;'>❌ genesConflict is not working correctly</p>\n";
    }
}

// Test 4: Test ConstraintChecker with intentional conflicts
echo "<h3>4. Testing ConstraintChecker</h3>\n";

// Load data for constraint checker
$loader = new DBLoader($conn);
$data = $loader->loadAll([
    'stream_id' => 3,
    'semester' => 2
]);

echo "<p>Loaded data: " . count($data['class_courses']) . " class courses, " . count($data['lecturer_courses']) . " lecturer courses</p>\n";

if (!empty($data['class_courses']) && !empty($data['lecturer_courses'])) {
    // Create a test individual with intentional lecturer conflicts
    $test_individual = [];
    
    // Get first few class courses and lecturer courses
    $class_courses = array_slice($data['class_courses'], 0, 3);
    $lecturer_courses = array_slice($data['lecturer_courses'], 0, 2);
    
    // Create genes that should conflict (same lecturer, same time)
    foreach ($class_courses as $i => $cc) {
        $lecturer_course = $lecturer_courses[$i % count($lecturer_courses)];
        
        $test_individual[$cc['id']] = [
            'class_course_id' => $cc['id'],
            'class_id' => $cc['class_id'],
            'course_id' => $cc['course_id'],
            'day_id' => 1, // Same day for all
            'time_slot_id' => 1, // Same time slot for all (should create conflicts)
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
        echo "<p style='color: green;'>✓ Found " . count($fitness['hard_violations']['lecturer_conflict']) . " lecturer conflicts</p>\n";
        foreach ($fitness['hard_violations']['lecturer_conflict'] as $violation) {
            echo "<p>Class Course ID {$violation['class_course_id']}: {$violation['message']}</p>\n";
        }
    } else {
        echo "<p style='color: red;'>❌ No lecturer conflicts detected - constraint checker may not be working</p>\n";
    }
} else {
    echo "<p style='color: orange;'>⚠️ Insufficient data loaded for testing</p>\n";
}

// Test 5: Test with actual timetable data
echo "<h3>5. Testing with Actual Timetable Data</h3>\n";

$query = "
    SELECT 
        t.id,
        t.class_course_id,
        t.lecturer_course_id,
        t.day_id,
        t.time_slot_id,
        t.room_id,
        t.division_label,
        cc.class_id,
        cc.course_id,
        lc.lecturer_id
    FROM timetable t
    LEFT JOIN class_courses cc ON t.class_course_id = cc.id
    LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    WHERE lc.lecturer_id IS NOT NULL
    LIMIT 10
";

$result = $conn->query($query);
$timetable_entries = [];
while ($row = $result->fetch_assoc()) {
    $timetable_entries[] = $row;
}

if (!empty($timetable_entries)) {
    echo "<p>Loaded " . count($timetable_entries) . " timetable entries for testing</p>\n";
    
    // Convert to individual format
    $individual = [];
    foreach ($timetable_entries as $entry) {
        $individual[$entry['class_course_id']] = [
            'class_course_id' => $entry['class_course_id'],
            'class_id' => $entry['class_id'],
            'course_id' => $entry['course_id'],
            'day_id' => $entry['day_id'],
            'time_slot_id' => $entry['time_slot_id'],
            'room_id' => $entry['room_id'],
            'lecturer_course_id' => $entry['lecturer_course_id'],
            'division_label' => $entry['division_label']
        ];
    }
    
    // Test constraint checker with real data
    $constraintChecker = new ConstraintChecker($data);
    $fitness = $constraintChecker->evaluateFitness($individual);
    
    echo "<h4>Real Data Constraint Checker Results:</h4>\n";
    echo "<p>Hard Score: {$fitness['hard_score']}</p>\n";
    echo "<p>Soft Score: {$fitness['soft_score']}</p>\n";
    echo "<p>Total Score: {$fitness['total_score']}</p>\n";
    echo "<p>Is Feasible: " . ($fitness['is_feasible'] ? 'Yes' : 'No') . "</p>\n";
    
    if (!empty($fitness['hard_violations']['lecturer_conflict'])) {
        echo "<h4>Lecturer Conflicts in Real Data:</h4>\n";
        echo "<p style='color: red;'>❌ Found " . count($fitness['hard_violations']['lecturer_conflict']) . " lecturer conflicts in real data</p>\n";
        foreach ($fitness['hard_violations']['lecturer_conflict'] as $violation) {
            echo "<p>Class Course ID {$violation['class_course_id']}: {$violation['message']}</p>\n";
        }
    } else {
        echo "<p style='color: green;'>✓ No lecturer conflicts detected in real data</p>\n";
    }
}

$conn->close();
?>


