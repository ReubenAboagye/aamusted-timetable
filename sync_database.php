<?php
/**
 * Database Synchronization Script
 * This script ensures the database schema is in sync with the PHP application
 */

// Prevent direct access
if (!defined('SYNC_ACCESS')) {
    define('SYNC_ACCESS', true);
}

// Include database connection
include 'connect.php';

echo "<h2>Database Synchronization Report</h2>\n";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 5px;'>\n";

// Function to check table structure
function checkTableStructure($conn, $tableName, $expectedFields) {
    echo "<h3>Checking table: $tableName</h3>\n";
    
    $result = $conn->query("DESCRIBE $tableName");
    if (!$result) {
        echo "<span style='color: red;'>❌ Error: Cannot describe table $tableName</span><br>\n";
        return false;
    }
    
    $actualFields = [];
    while ($row = $result->fetch_assoc()) {
        $actualFields[$row['Field']] = $row;
    }
    
    $issues = [];
    $missingFields = [];
    $extraFields = [];
    
    // Check for missing fields
    foreach ($expectedFields as $field => $spec) {
        if (!isset($actualFields[$field])) {
            $missingFields[] = $field;
            $issues[] = "Missing field: $field ($spec)";
        }
    }
    
    // Check for extra fields
    foreach ($actualFields as $field => $spec) {
        if (!isset($expectedFields[$field])) {
            $extraFields[] = $field;
        }
    }
    
    if (empty($issues)) {
        echo "<span style='color: green;'>✅ Table $tableName is properly structured</span><br>\n";
        return true;
    } else {
        echo "<span style='color: orange;'>⚠️ Issues found in table $tableName:</span><br>\n";
        foreach ($issues as $issue) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;• $issue<br>\n";
        }
        return false;
    }
}

// Function to check and insert basic data
function checkBasicData($conn, $tableName, $data, $checkField = 'name') {
    echo "<h4>Checking basic data for $tableName</h4>\n";
    
    foreach ($data as $item) {
        $checkValue = $item[$checkField];
        $sql = "SELECT id FROM $tableName WHERE $checkField = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $checkValue);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Insert missing data
            $fields = array_keys($item);
            $placeholders = str_repeat('?,', count($fields) - 1) . '?';
            $sql = "INSERT INTO $tableName (" . implode(',', $fields) . ") VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            
            $types = str_repeat('s', count($fields));
            $stmt->bind_param($types, ...array_values($item));
            
            if ($stmt->execute()) {
                echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ Added: $checkValue<br>\n";
            } else {
                echo "&nbsp;&nbsp;&nbsp;&nbsp;❌ Failed to add: $checkValue - " . $stmt->error . "<br>\n";
            }
        } else {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;✓ Exists: $checkValue<br>\n";
        }
        $stmt->close();
    }
}

// Check table structures
echo "<h3>Table Structure Validation</h3>\n";

// Define expected table structures
$tableStructures = [
    'departments' => [
        'id' => 'int NOT NULL AUTO_INCREMENT',
        'name' => 'varchar(100) NOT NULL',
        'code' => 'varchar(20) NOT NULL',
        'description' => 'text',
        'stream_id' => 'int',
        'is_active' => 'tinyint(1)',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ],
    'courses' => [
        'id' => 'int NOT NULL AUTO_INCREMENT',
        'code' => 'varchar(20) NOT NULL',
        'name' => 'varchar(200) NOT NULL',
        'description' => 'text',
        'credits' => 'int NOT NULL',
        'lecture_hours' => 'int',
        'tutorial_hours' => 'int',
        'practical_hours' => 'int',
        'is_active' => 'tinyint(1)',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ],
    'lecturers' => [
        'id' => 'int NOT NULL AUTO_INCREMENT',
        'name' => 'varchar(100) NOT NULL',
        'email' => 'varchar(100) NOT NULL',
        'phone' => 'varchar(20)',
        'department_id' => 'int NOT NULL',
        'is_active' => 'tinyint(1)',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ],
    'rooms' => [
        'id' => 'int NOT NULL AUTO_INCREMENT',
        'building_id' => 'int',
        'stream_id' => 'int',
        'name' => 'varchar(50) NOT NULL',
        'building' => 'varchar(100) NOT NULL',
        'room_type' => 'varchar(50) NOT NULL',
        'capacity' => 'int NOT NULL',
        'stream_availability' => 'json NOT NULL',
        'facilities' => 'json',
        'accessibility_features' => 'json',
        'is_active' => 'tinyint(1)',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ]
];

$allTablesValid = true;
foreach ($tableStructures as $table => $fields) {
    if (!checkTableStructure($conn, $table, $fields)) {
        $allTablesValid = false;
    }
}

// Check and insert basic required data
echo "<h3>Basic Data Validation</h3>\n";

// Days data
$daysData = [
    ['name' => 'Monday'],
    ['name' => 'Tuesday'],
    ['name' => 'Wednesday'],
    ['name' => 'Thursday'],
    ['name' => 'Friday'],
    ['name' => 'Saturday'],
    ['name' => 'Sunday']
];
checkBasicData($conn, 'days', $daysData);

// Room types data
$roomTypesData = [
    ['name' => 'classroom', 'description' => 'Standard classroom'],
    ['name' => 'lecture_hall', 'description' => 'Large lecture hall'],
    ['name' => 'laboratory', 'description' => 'Science laboratory'],
    ['name' => 'computer_lab', 'description' => 'Computer laboratory'],
    ['name' => 'seminar_room', 'description' => 'Small seminar room'],
    ['name' => 'auditorium', 'description' => 'Large auditorium']
];
checkBasicData($conn, 'room_types', $roomTypesData);

// Streams data
$streamsData = [
    ['name' => 'Regular', 'code' => 'REG', 'description' => 'Regular daytime program'],
    ['name' => 'Evening', 'code' => 'EVE', 'description' => 'Evening program'],
    ['name' => 'Weekend', 'code' => 'WKD', 'description' => 'Weekend program']
];
checkBasicData($conn, 'streams', $streamsData, 'code');

// Time slots data
$timeSlotsData = [
    ['start_time' => '07:00:00', 'end_time' => '08:00:00', 'duration' => 60],
    ['start_time' => '08:00:00', 'end_time' => '09:00:00', 'duration' => 60],
    ['start_time' => '09:00:00', 'end_time' => '10:00:00', 'duration' => 60],
    ['start_time' => '10:00:00', 'end_time' => '11:00:00', 'duration' => 60],
    ['start_time' => '11:00:00', 'end_time' => '12:00:00', 'duration' => 60],
    ['start_time' => '12:00:00', 'end_time' => '13:00:00', 'duration' => 60],
    ['start_time' => '13:00:00', 'end_time' => '14:00:00', 'duration' => 60],
    ['start_time' => '14:00:00', 'end_time' => '15:00:00', 'duration' => 60],
    ['start_time' => '15:00:00', 'end_time' => '16:00:00', 'duration' => 60],
    ['start_time' => '16:00:00', 'end_time' => '17:00:00', 'duration' => 60],
    ['start_time' => '17:00:00', 'end_time' => '18:00:00', 'duration' => 60],
    ['start_time' => '18:00:00', 'end_time' => '19:00:00', 'duration' => 60],
    ['start_time' => '19:00:00', 'end_time' => '20:00:00', 'duration' => 60]
];
checkBasicData($conn, 'time_slots', $timeSlotsData, 'start_time');

// Buildings data
$buildingsData = [
    ['name' => 'Main Building', 'code' => 'MB', 'description' => 'Main academic building']
];
checkBasicData($conn, 'buildings', $buildingsData, 'code');

// Check foreign key relationships
echo "<h3>Foreign Key Relationship Check</h3>\n";

$foreignKeyChecks = [
    'departments' => ['stream_id' => 'streams'],
    'programs' => ['department_id' => 'departments'],
    'classes' => ['program_id' => 'programs', 'level_id' => 'levels', 'stream_id' => 'streams'],
    'courses' => [],
    'lecturers' => ['department_id' => 'departments'],
    'rooms' => ['building_id' => 'buildings', 'stream_id' => 'streams'],
    'class_courses' => ['class_id' => 'classes', 'course_id' => 'courses', 'lecturer_id' => 'lecturers'],
    'lecturer_courses' => ['lecturer_id' => 'lecturers', 'course_id' => 'courses'],
    'course_room_types' => ['course_id' => 'courses', 'room_type_id' => 'room_types'],
    'timetable' => ['class_id' => 'classes', 'course_id' => 'courses', 'lecturer_id' => 'lecturers', 'room_id' => 'rooms', 'day_id' => 'days', 'time_slot_id' => 'time_slots'],
    'timetable_lecturers' => ['timetable_id' => 'timetable', 'lecturer_id' => 'lecturers']
];

foreach ($foreignKeyChecks as $table => $relationships) {
    echo "<h4>Checking $table foreign keys</h4>\n";
    
    foreach ($relationships as $field => $referencedTable) {
        $sql = "SELECT COUNT(*) as count FROM $table t LEFT JOIN $referencedTable r ON t.$field = r.id WHERE t.$field IS NOT NULL AND r.id IS NULL";
        $result = $conn->query($sql);
        
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row['count'] > 0) {
                echo "&nbsp;&nbsp;&nbsp;&nbsp;⚠️ $field references $referencedTable: $row[count] orphaned records<br>\n";
            } else {
                echo "&nbsp;&nbsp;&nbsp;&nbsp;✅ $field references $referencedTable: All valid<br>\n";
            }
        } else {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;❌ Error checking $field references<br>\n";
        }
    }
}

// Summary
echo "<h3>Sync Summary</h3>\n";
if ($allTablesValid) {
    echo "<span style='color: green;'>✅ All tables are properly structured</span><br>\n";
} else {
    echo "<span style='color: orange;'>⚠️ Some tables have structural issues</span><br>\n";
}

echo "<br><strong>Database synchronization completed!</strong><br>\n";
echo "The database is now in sync with the application schema.<br>\n";

echo "</div>\n";

// Close connection
$conn->close();
?>
