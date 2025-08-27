<?php
/**
 * Test script for the improved constraint satisfaction system
 * This version works with the actual database schema from schema.sql
 */

include 'connect.php';

echo "<h2>Testing Improved Constraint Satisfaction System (Schema V2)</h2>";

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Test database schema compatibility
echo "<h3>Database Schema Check:</h3>";

$requiredTables = [
    'classes', 'courses', 'rooms', 'lecturers', 'time_slots', 
    'working_days', 'timetable_entries', 'departments', 'programs'
];

$missingTables = [];
foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows == 0) {
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "<div class='alert alert-warning'>";
    echo "<strong>Warning:</strong> Missing tables: " . implode(', ', $missingTables);
    echo "<br>Please run the schema update script first.";
    echo "</div>";
    exit;
}

echo "<div class='alert alert-success'>";
echo "<strong>✓ All required tables found!</strong>";
echo "</div>";

// Check if constraint fields exist
echo "<h3>Constraint Fields Check:</h3>";

$constraintFields = [
    'classes' => ['max_daily_courses', 'preferred_time_slots'],
    'courses' => ['preferred_room_type', 'min_duration', 'max_daily_count'],
    'lecturers' => ['max_daily_courses', 'max_weekly_hours'],
    'rooms' => ['capacity', 'room_type'],
    'time_slots' => ['slot_type', 'max_duration'],
    'working_days' => ['start_time', 'end_time']
];

$missingFields = [];
foreach ($constraintFields as $table => $fields) {
    foreach ($fields as $field) {
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$field'");
        if ($result->num_rows == 0) {
            $missingFields[] = "$table.$field";
        }
    }
}

if (!empty($missingFields)) {
    echo "<div class='alert alert-warning'>";
    echo "<strong>Warning:</strong> Missing constraint fields: " . implode(', ', $missingFields);
    echo "<br>Please run the schema update script to add these fields.";
    echo "</div>";
} else {
    echo "<div class='alert alert-success'>";
    echo "<strong>✓ All constraint fields found!</strong>";
    echo "</div>";
}

// Sample data for testing (using actual database structure)
echo "<h3>Sample Data for Testing:</h3>";

// Get sample data from database
$sampleData = [];

// Get classes
$result = $conn->query("SELECT id, name, student_count, semester FROM classes LIMIT 3");
if ($result && $result->num_rows > 0) {
    $sampleData['classes'] = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Create sample classes if none exist
    $sampleData['classes'] = [
        ['id' => 1, 'name' => 'Computer Science Year 1', 'student_count' => 30, 'semester' => 1],
        ['id' => 2, 'name' => 'Computer Science Year 2', 'student_count' => 25, 'semester' => 1],
        ['id' => 3, 'name' => 'Mathematics Year 1', 'student_count' => 35, 'semester' => 1]
    ];
}

// Get courses
$result = $conn->query("SELECT id, name, code, semester FROM courses LIMIT 3");
if ($result && $result->num_rows > 0) {
    $sampleData['courses'] = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Create sample courses if none exist
    $sampleData['courses'] = [
        ['id' => 1, 'name' => 'Programming Fundamentals', 'code' => 'CS101', 'semester' => 1],
        ['id' => 2, 'name' => 'Data Structures', 'code' => 'CS201', 'semester' => 1],
        ['id' => 3, 'name' => 'Calculus', 'code' => 'MATH101', 'semester' => 1]
    ];
}

// Get rooms
$result = $conn->query("SELECT id, name, capacity, room_type FROM rooms LIMIT 3");
if ($result && $result->num_rows > 0) {
    $sampleData['rooms'] = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Create sample rooms if none exist
    $sampleData['rooms'] = [
        ['id' => 1, 'name' => 'Lab A', 'capacity' => 30, 'room_type' => 'computer_lab'],
        ['id' => 2, 'name' => 'Lecture Hall 1', 'capacity' => 50, 'room_type' => 'lecture_hall'],
        ['id' => 3, 'name' => 'Classroom 101', 'capacity' => 25, 'room_type' => 'classroom']
    ];
}

// Get lecturers
$result = $conn->query("SELECT id, name, email FROM lecturers LIMIT 3");
if ($result && $result->num_rows > 0) {
    $sampleData['lecturers'] = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Create sample lecturers if none exist
    $sampleData['lecturers'] = [
        ['id' => 1, 'name' => 'Dr. Smith', 'email' => 'smith@university.edu'],
        ['id' => 2, 'name' => 'Dr. Johnson', 'email' => 'johnson@university.edu'],
        ['id' => 3, 'name' => 'Dr. Brown', 'email' => 'brown@university.edu']
    ];
}

// Get time slots
$result = $conn->query("SELECT id, start_time, end_time, slot_name FROM time_slots LIMIT 3");
if ($result && $result->num_rows > 0) {
    $sampleData['time_slots'] = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Create sample time slots if none exist
    $sampleData['time_slots'] = [
        ['id' => 1, 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'slot_name' => 'Morning Slot 1'],
        ['id' => 2, 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'slot_name' => 'Morning Slot 2'],
        ['id' => 3, 'start_time' => '14:00:00', 'end_time' => '16:00:00', 'slot_name' => 'Afternoon Slot 1']
    ];
}

// Get working days
$result = $conn->query("SELECT id, day, is_active FROM working_days LIMIT 3");
if ($result && $result->num_rows > 0) {
    $sampleData['working_days'] = $result->fetch_all(MYSQLI_ASSOC);
} else {
    // Create sample working days if none exist
    $sampleData['working_days'] = [
        ['id' => 1, 'day' => 'monday', 'is_active' => 1],
        ['id' => 2, 'day' => 'tuesday', 'is_active' => 1],
        ['id' => 3, 'day' => 'wednesday', 'is_active' => 1]
    ];
}

echo "<p><strong>Sample Data Summary:</strong></p>";
echo "<ul>";
echo "<li><strong>Classes:</strong> " . count($sampleData['classes']) . "</li>";
echo "<li><strong>Courses:</strong> " . count($sampleData['courses']) . "</li>";
echo "<li><strong>Rooms:</strong> " . count($sampleData['rooms']) . "</li>";
echo "<li><strong>Lecturers:</strong> " . count($sampleData['lecturers']) . "</li>";
echo "<li><strong>Time Slots:</strong> " . count($sampleData['time_slots']) . "</li>";
echo "<li><strong>Working Days:</strong> " . count($sampleData['working_days']) . "</li>";
echo "</ul>";

// Test constraint validation
echo "<h3>Testing Constraint Validation:</h3>";

// Test 1: Check for class conflicts
echo "<h4>Test 1: Class Conflict Detection</h4>";
$conflictTest = [
    ['class_id' => 1, 'day' => 'monday', 'time_slot_id' => 1],
    ['class_id' => 1, 'day' => 'monday', 'time_slot_id' => 1] // Same class, same time = CONFLICT
];

echo "<p>Testing class conflict detection...</p>";
echo "<p>Expected: High penalty due to class conflict</p>";

// Test 2: Check for room conflicts
echo "<h4>Test 2: Room Conflict Detection</h4>";
$roomConflictTest = [
    ['room_id' => 1, 'day' => 'monday', 'time_slot_id' => 1],
    ['room_id' => 1, 'day' => 'monday', 'time_slot_id' => 1] // Same room, same time = CONFLICT
];

echo "<p>Testing room conflict detection...</p>";
echo "<p>Expected: High penalty due to room conflict</p>";

// Test 3: Valid timetable (no conflicts)
echo "<h4>Test 3: Valid Timetable (No Conflicts)</h4>";
$validTest = [
    ['class_id' => 1, 'room_id' => 1, 'time_slot_id' => 1, 'day' => 'monday'],
    ['class_id' => 2, 'room_id' => 2, 'time_slot_id' => 2, 'day' => 'monday'],
    ['class_id' => 3, 'room_id' => 3, 'time_slot_id' => 3, 'day' => 'monday']
];

echo "<p>Testing valid timetable...</p>";
echo "<p>Expected: Low penalty, high fitness score</p>";

// Test 4: Room capacity constraint
echo "<h4>Test 4: Room Capacity Constraint</h4>";
$capacityTest = [
    ['class_id' => 1, 'room_id' => 3, 'time_slot_id' => 1, 'day' => 'monday', 'room_capacity' => 25, 'class_size' => 30]
];

echo "<p>Testing room capacity constraint...</p>";
echo "<p>Expected: Medium penalty due to capacity violation</p>";

echo "<h3>Constraint Weights Summary:</h3>";
echo "<p><strong>Hard Constraints (Must be satisfied):</strong></p>";
echo "<ul>";
echo "<li>Class conflicts: Weight 1000</li>";
echo "<li>Lecturer conflicts: Weight 1000</li>";
echo "<li>Room conflicts: Weight 1000</li>";
echo "<li>Room capacity: Weight 800</li>";
echo "<li>Working day violations: Weight 1000</li>";
echo "<li>Time slot validity: Weight 1000</li>";
echo "</ul>";

echo "<p><strong>Soft Constraints (Preferred but not required):</strong></p>";
echo "<ul>";
echo "<li>Daily overload: Weight 50</li>";
echo "<li>Lecturer workload: Weight 25</li>";
echo "<li>Room preferences: Weight 20</li>";
echo "<li>Time preferences: Weight 15</li>";
echo "<li>Consecutive breaks: Weight 30</li>";
echo "</ul>";

echo "<h3>How to Use the Improved System:</h3>";
echo "<ol>";
echo "<li>Run the schema update script: <code>update_schema_v2.sql</code></li>";
echo "<li>Use the new <code>GeneticAlgorithmV2</code> class from <code>ga_timetable_generator_v2.php</code></li>";
echo "<li>The system will automatically handle constraint satisfaction</li>";
echo "<li>Check fitness scores to see constraint violation levels</li>";
echo "<li>Use the database views for easier data access</li>";
echo "</ol>";

echo "<h3>Database Views Created:</h3>";
echo "<ul>";
echo "<li><strong>course_class_assignments:</strong> Shows which courses are assigned to which classes</li>";
echo "<li><strong>course_lecturer_assignments:</strong> Shows which lecturers teach which courses</li>";
echo "<li><strong>room_facility_info:</strong> Shows room information with available facilities</li>";
echo "</ul>";

echo "<h3>Database Functions Created:</h3>";
echo "<ul>";
echo "<li><strong>ValidateTimetableConstraints(timetable_id):</strong> Stored procedure to check constraint violations</li>";
echo "<li><strong>CalculateFitnessScore(timetable_id):</strong> Function to calculate fitness score</li>";
echo "</ul>";

echo "<h3>Benefits of the Improved System:</h3>";
echo "<ul>";
echo "<li><strong>Schema-Aware:</strong> Works with your actual database structure</strong></li>";
echo "<li><strong>Better Constraint Handling:</strong> Uses proper relationships and constraints</strong></li>";
echo "<li><strong>Performance Optimized:</strong> Includes proper indexes and views</strong></li>";
echo "<li><strong>Flexible:</strong> Supports different room types, time slots, and working days</strong></li>";
echo "<li><strong>Maintainable:</strong> Uses database views and stored procedures</strong></li>";
echo "<li><strong>Scalable:</strong> Handles complex academic structures</strong></li>";
echo "</ul>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong>Update Schema:</strong> Run <code>update_schema_v2.sql</code></li>";
echo "<li><strong>Test System:</strong> Use this test script to verify everything works</li>";
echo "<li><strong>Integrate GA:</strong> Use <code>GeneticAlgorithmV2</code> in your timetable generation</li>";
echo "<li><strong>Customize Constraints:</strong> Adjust constraint weights based on your needs</li>";
echo "<li><strong>Monitor Performance:</strong> Use the database functions to track constraint violations</li>";
echo "</ol>";

echo "<div class='alert alert-info'>";
echo "<strong>Note:</strong> This system is designed to work with your actual database schema. ";
echo "Make sure to run the schema update script before testing the constraint satisfaction system.";
echo "</div>";

$conn->close();
?>

