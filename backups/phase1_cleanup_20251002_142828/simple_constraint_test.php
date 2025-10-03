<?php
/**
 * Simple test to verify constraint checking fix
 */

include 'connect.php';
require_once __DIR__ . '/ga/TimetableRepresentation.php';

echo "<h2>Simple Constraint Test</h2>\n";

// Test the getLecturerConflictKey method directly
echo "<h3>Testing getLecturerConflictKey Method</h3>\n";

// Load lecturer courses data
$query = "SELECT id, lecturer_id, course_id FROM lecturer_courses LIMIT 5";
$result = $conn->query($query);
$lecturer_courses = [];
while ($row = $result->fetch_assoc()) {
    $lecturer_courses[] = $row;
}

echo "<p>Loaded " . count($lecturer_courses) . " lecturer courses</p>\n";

if (!empty($lecturer_courses)) {
    // Create test data
    $data = ['lecturer_courses' => $lecturer_courses];
    
    // Test gene with lecturer_course_id
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
    
    // Test with multiple genes that should conflict
    echo "<h3>Testing Conflict Detection</h3>\n";
    
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
        echo "<p style='color: green;'>✓ Conflict detection is working correctly</p>\n";
    } else {
        echo "<p style='color: red;'>❌ Conflict detection is not working correctly</p>\n";
    }
    
    // Test with different lecturers (should not conflict)
    if (count($lecturer_courses) > 1) {
        $gene3 = [
            'lecturer_course_id' => $lecturer_courses[1]['id'], // Different lecturer
            'day_id' => 1, // Same day
            'time_slot_id' => 1, // Same time slot
            'room_id' => 3,
            'class_id' => 3
        ];
        
        $conflicts2 = TimetableRepresentation::genesConflict($gene1, $gene3, $data);
        
        echo "<p>Gene 3: lecturer_course_id={$gene3['lecturer_course_id']}, day={$gene3['day_id']}, time={$gene3['time_slot_id']}</p>\n";
        echo "<p>Should conflict: No (different lecturers)</p>\n";
        echo "<p>Actually conflicts: " . ($conflicts2 ? 'Yes' : 'No') . "</p>\n";
        
        if (!$conflicts2) {
            echo "<p style='color: green;'>✓ Non-conflict detection is working correctly</p>\n";
        } else {
            echo "<p style='color: red;'>❌ Non-conflict detection is not working correctly</p>\n";
        }
    }
}

$conn->close();
?>

