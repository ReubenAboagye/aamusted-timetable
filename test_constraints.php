<?php
/**
 * Test script for the improved constraint satisfaction system
 * This demonstrates how the new GA handles constraints
 */

include 'connect.php';
include 'ga_timetable_generator.php';

echo "<h2>Testing Improved Constraint Satisfaction System</h2>";

// Sample data for testing
$testClasses = [
    ['class_id' => 1, 'class_name' => 'Computer Science Year 1', 'class_size' => 30],
    ['class_id' => 2, 'class_name' => 'Computer Science Year 2', 'class_size' => 25],
    ['class_id' => 3, 'class_name' => 'Mathematics Year 1', 'class_size' => 35]
];

$testCourses = [
    ['course_id' => 1, 'course_name' => 'Programming Fundamentals', 'class_id' => 1, 'lecturer_id' => 1, 'lecturer_name' => 'Dr. Smith'],
    ['course_id' => 2, 'course_name' => 'Data Structures', 'class_id' => 2, 'lecturer_id' => 2, 'lecturer_name' => 'Dr. Johnson'],
    ['course_id' => 3, 'course_name' => 'Calculus', 'class_id' => 3, 'lecturer_id' => 3, 'lecturer_name' => 'Dr. Brown'],
    ['course_id' => 4, 'course_name' => 'Database Systems', 'class_id' => 2, 'lecturer_id' => 1, 'lecturer_name' => 'Dr. Smith'],
    ['course_id' => 5, 'course_name' => 'Linear Algebra', 'class_id' => 3, 'lecturer_id' => 2, 'lecturer_name' => 'Dr. Johnson']
];

$testRooms = [
    ['room_id' => 1, 'room_name' => 'Lab A', 'capacity' => 30, 'room_type' => 'computer_lab'],
    ['room_id' => 2, 'room_name' => 'Lecture Hall 1', 'capacity' => 50, 'room_type' => 'lecture_hall'],
    ['room_id' => 3, 'room_name' => 'Classroom 101', 'capacity' => 25, 'room_type' => 'classroom'],
    ['room_id' => 4, 'room_name' => 'Seminar Room', 'capacity' => 20, 'room_type' => 'seminar']
];

$testLecturers = [
    ['lecturer_id' => 1, 'lecturer_name' => 'Dr. Smith', 'max_daily_courses' => 3, 'preferred_times' => 'morning'],
    ['lecturer_id' => 2, 'lecturer_name' => 'Dr. Johnson', 'max_daily_courses' => 4, 'preferred_times' => 'afternoon'],
    ['lecturer_id' => 3, 'lecturer_name' => 'Dr. Brown', 'max_daily_courses' => 2, 'preferred_times' => 'morning']
];

echo "<h3>Test Data:</h3>";
echo "<p><strong>Classes:</strong> " . count($testClasses) . "</p>";
echo "<p><strong>Courses:</strong> " . count($testCourses) . "</p>";
echo "<p><strong>Rooms:</strong> " . count($testRooms) . "</p>";
echo "<p><strong>Lecturers:</strong> " . count($testLecturers) . "</p>";

// Test constraint validation
echo "<h3>Testing Constraint Validation:</h3>";

// Test 1: Check for class conflicts
echo "<h4>Test 1: Class Conflict Detection</h4>";
$conflictTest = [
    ['class_id' => 1, 'day' => 'Monday', 'time_slot' => '07:00-10:00'],
    ['class_id' => 1, 'day' => 'Monday', 'time_slot' => '07:00-10:00'] // Same class, same time = CONFLICT
];

$ga = new GeneticAlgorithm($testClasses, $testCourses, $testRooms, $testLecturers);
$fitness = $ga->fitness($conflictTest);
echo "<p>Fitness with class conflict: " . number_format($fitness, 6) . " (should be low due to conflict)</p>";

// Test 2: Check for lecturer conflicts
echo "<h4>Test 2: Lecturer Conflict Detection</h4>";
$lecturerConflictTest = [
    ['lecturer_id' => 1, 'day' => 'Monday', 'time_slot' => '07:00-10:00'],
    ['lecturer_id' => 1, 'day' => 'Monday', 'time_slot' => '07:00-10:00'] // Same lecturer, same time = CONFLICT
];

$fitness = $ga->fitness($lecturerConflictTest);
echo "<p>Fitness with lecturer conflict: " . number_format($fitness, 6) . " (should be low due to conflict)</p>";

// Test 3: Check for room conflicts
echo "<h4>Test 3: Room Conflict Detection</h4>";
$roomConflictTest = [
    ['room_id' => 1, 'day' => 'Monday', 'time_slot' => '07:00-10:00'],
    ['room_id' => 1, 'day' => 'Monday', 'time_slot' => '07:00-10:00'] // Same room, same time = CONFLICT
];

$fitness = $ga->fitness($roomConflictTest);
echo "<p>Fitness with room conflict: " . number_format($fitness, 6) . " (should be low due to conflict)</p>";

// Test 4: Valid timetable (no conflicts)
echo "<h4>Test 4: Valid Timetable (No Conflicts)</h4>";
$validTest = [
    ['class_id' => 1, 'lecturer_id' => 1, 'room_id' => 1, 'day' => 'Monday', 'time_slot' => '07:00-10:00'],
    ['class_id' => 2, 'lecturer_id' => 2, 'room_id' => 2, 'day' => 'Monday', 'time_slot' => '10:00-13:00'],
    ['class_id' => 3, 'lecturer_id' => 3, 'room_id' => 3, 'day' => 'Monday', 'time_slot' => '14:00-17:00']
];

$fitness = $ga->fitness($validTest);
echo "<p>Fitness with no conflicts: " . number_format($fitness, 6) . " (should be high)</p>";

// Test 5: Room capacity constraint
echo "<h4>Test 5: Room Capacity Constraint</h4>";
$capacityTest = [
    ['class_id' => 1, 'lecturer_id' => 1, 'room_id' => 4, 'day' => 'Monday', 'time_slot' => '07:00-10:00', 'room_capacity' => 20, 'class_size' => 30]
];

$fitness = $ga->fitness($capacityTest);
echo "<p>Fitness with capacity violation: " . number_format($fitness, 6) . " (should be lower due to capacity issue)</p>";

echo "<h3>Constraint Weights Summary:</h3>";
echo "<p><strong>Hard Constraints (Must be satisfied):</strong></p>";
echo "<ul>";
echo "<li>Class conflicts: Weight 1000</li>";
echo "<li>Lecturer conflicts: Weight 1000</li>";
echo "<li>Room conflicts: Weight 1000</li>";
echo "<li>Room capacity: Weight 800</li>";
echo "</ul>";

echo "<p><strong>Soft Constraints (Preferred but not required):</strong></p>";
echo "<ul>";
echo "<li>Daily overload: Weight 50</li>";
echo "<li>Lecturer workload: Weight 25</li>";
echo "<li>Room preferences: Weight 20</li>";
echo "<li>Time preferences: Weight 15</li>";
echo "</ul>";

echo "<h3>How to Use:</h3>";
echo "<ol>";
echo "<li>Run the schema update script: <code>update_schema.sql</code></li>";
echo "<li>Update your form files to include the new constraint fields</li>";
echo "<li>The improved GA will automatically handle constraint satisfaction</li>";
echo "<li>Check fitness scores to see constraint violation levels</li>";
echo "</ol>";

echo "<h3>Benefits of the Improved System:</h3>";
echo "<ul>";
echo "<li><strong>Better Conflict Detection:</strong> Identifies all types of scheduling conflicts</li>";
echo "<li><strong>Smart Assignment:</strong> Tries to find valid assignments during generation</li>";
echo "<li><strong>Elitism:</strong> Preserves best solutions across generations</li>";
echo "<li><strong>Fitness Caching:</strong> Improves performance for large timetables</li>";
echo "<li><strong>Constraint Reporting:</strong> Provides detailed violation information</li>";
echo "<li><strong>Adaptive Mutation:</strong> Tries to improve solutions rather than random changes</li>";
echo "</ul>";

$conn->close();
?>
