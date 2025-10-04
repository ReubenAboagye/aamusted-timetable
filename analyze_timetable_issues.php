<?php
include 'connect.php';

echo "<h1>Timetable System Analysis Report</h1>\n";
echo "<p>This report identifies potential issues that could prevent timetable generation.</p>\n";

$issues = [];
$warnings = [];
$info = [];

// Test 1: Database Connection
echo "<h2>1. Database Connection</h2>\n";
if ($conn->connect_error) {
    $issues[] = "Database connection failed: " . $conn->connect_error;
    echo "<p style='color: red;'>Database connection failed: " . $conn->connect_error . "</p>\n";
} else {
    echo "<p style='color: green;'>Database connection successful</p>\n";
    $info[] = "Database connection is working";
}

// Test 2: Required Tables
echo "<h2>2. Required Tables Check</h2>\n";
$required_tables = [
    'class_courses', 'lecturer_courses', 'classes', 'courses', 
    'lecturers', 'rooms', 'time_slots', 'days', 'streams', 'timetable'
];

foreach ($required_tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>Table '$table' exists</p>\n";
    } else {
        $issues[] = "Required table '$table' is missing";
        echo "<p style='color: red;'>Table '$table' is missing</p>\n";
    }
}

// Test 3: Data Availability
echo "<h2>3. Data Availability Check</h2>\n";
$data_tables = [
    'class_courses' => 'is_active = 1',
    'lecturer_courses' => 'is_active = 1', 
    'classes' => 'is_active = 1',
    'courses' => 'is_active = 1',
    'lecturers' => 'is_active = 1',
    'rooms' => 'is_active = 1',
    'time_slots' => 'is_mandatory = 1', // time_slots doesn't have is_active
    'days' => 'is_active = 1',
    'streams' => 'is_active = 1'
];

foreach ($data_tables as $table => $condition) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table WHERE $condition");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        if ($count > 0) {
            echo "<p style='color: green;'>$table: $count records</p>\n";
        } else {
            $warnings[] = "Table '$table' has no records matching condition: $condition";
            echo "<p style='color: orange;'>$table: $count records (no data)</p>\n";
        }
    } else {
        $issues[] = "Cannot query table '$table'";
        echo "<p style='color: red;'>Cannot query table '$table': " . $conn->error . "</p>\n";
    }
}

// Test 4: Timetable Table Schema
echo "<h2>4. Timetable Table Schema</h2>\n";
$result = $conn->query("SHOW COLUMNS FROM timetable");
if ($result) {
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $required_columns = ['class_course_id', 'lecturer_course_id', 'day_id', 'time_slot_id', 'room_id'];
    $missing_columns = array_diff($required_columns, $columns);
    
    if (empty($missing_columns)) {
        echo "<p style='color: green;'>Timetable table has all required columns</p>\n";
    } else {
        $issues[] = "Timetable table missing columns: " . implode(', ', $missing_columns);
        echo "<p style='color: red;'>Timetable table missing columns: " . implode(', ', $missing_columns) . "</p>\n";
    }
    
    // Check for old schema
    if (in_array('class_id', $columns) && !in_array('class_course_id', $columns)) {
        $warnings[] = "Timetable table uses old schema (class_id instead of class_course_id)";
        echo "<p style='color: orange;'>Timetable table uses old schema (class_id instead of class_course_id)</p>\n";
    }
} else {
    $issues[] = "Cannot check timetable table schema";
    echo "<p style='color: red;'>Cannot check timetable table schema: " . $conn->error . "</p>\n";
}

// Test 5: Stream Configuration
echo "<h2>5. Stream Configuration</h2>\n";
$result = $conn->query("SELECT COUNT(*) as count FROM streams WHERE is_active = 1");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    if ($count > 0) {
        echo "<p style='color: green;'>$count active streams found</p>\n";
    } else {
        $warnings[] = "No active streams found";
        echo "<p style='color: orange;'>No active streams found</p>\n";
    }
} else {
    $issues[] = "Cannot check streams";
    echo "<p style='color: red;'>Cannot check streams: " . $conn->error . "</p>\n";
}

// Test 6: Class-Course Assignments
echo "<h2>6. Class-Course Assignments</h2>\n";
$result = $conn->query("SELECT COUNT(*) as count FROM class_courses WHERE is_active = 1");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    if ($count > 0) {
        echo "<p style='color: green;'>$count class-course assignments found</p>\n";
        
        // Check for semester assignments
        $result2 = $conn->query("SELECT COUNT(*) as count FROM class_courses WHERE is_active = 1 AND (semester IS NULL OR semester = '')");
        if ($result2) {
            $null_count = $result2->fetch_assoc()['count'];
            if ($null_count > 0) {
                $warnings[] = "$null_count class-course assignments have no semester specified";
                echo "<p style='color: orange;'>$null_count class-course assignments have no semester specified</p>\n";
            }
        }
    } else {
        $issues[] = "No class-course assignments found";
        echo "<p style='color: red;'>No class-course assignments found</p>\n";
    }
} else {
    $issues[] = "Cannot check class-course assignments";
    echo "<p style='color: red;'>Cannot check class-course assignments: " . $conn->error . "</p>\n";
}

// Test 7: Lecturer-Course Assignments
echo "<h2>7. Lecturer-Course Assignments</h2>\n";
$result = $conn->query("SELECT COUNT(*) as count FROM lecturer_courses WHERE is_active = 1");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    if ($count > 0) {
        echo "<p style='color: green;'>$count lecturer-course assignments found</p>\n";
    } else {
        $warnings[] = "No lecturer-course assignments found";
        echo "<p style='color: orange;'>No lecturer-course assignments found</p>\n";
    }
} else {
    $issues[] = "Cannot check lecturer-course assignments";
    echo "<p style='color: red;'>Cannot check lecturer-course assignments: " . $conn->error . "</p>\n";
}

// Test 8: Time Slots and Days
echo "<h2>8. Time Slots and Days</h2>\n";
$result = $conn->query("SELECT COUNT(*) as count FROM time_slots WHERE is_mandatory = 1");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    if ($count > 0) {
        echo "<p style='color: green;'>$count mandatory time slots found</p>\n";
    } else {
        $warnings[] = "No mandatory time slots found";
        echo "<p style='color: orange;'>No mandatory time slots found</p>\n";
    }
} else {
    $issues[] = "Cannot check time slots";
    echo "<p style='color: red;'>Cannot check time slots: " . $conn->error . "</p>\n";
}

$result = $conn->query("SELECT COUNT(*) as count FROM days WHERE is_active = 1");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    if ($count > 0) {
        echo "<p style='color: green;'>$count active days found</p>\n";
    } else {
        $warnings[] = "No active days found";
        echo "<p style='color: orange;'>No active days found</p>\n";
    }
} else {
    $issues[] = "Cannot check days";
    echo "<p style='color: red;'>Cannot check days: " . $conn->error . "</p>\n";
}

// Test 9: Room Availability
echo "<h2>9. Room Availability</h2>\n";
$result = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE is_active = 1");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    if ($count > 0) {
        echo "<p style='color: green;'>$count active rooms found</p>\n";
    } else {
        $warnings[] = "No active rooms found";
        echo "<p style='color: orange;'>No active rooms found</p>\n";
    }
} else {
    $issues[] = "Cannot check rooms";
    echo "<p style='color: red;'>Cannot check rooms: " . $conn->error . "</p>\n";
}

// Test 10: Genetic Algorithm Components
echo "<h2>10. Genetic Algorithm Components</h2>\n";
$ga_files = [
    'ga/GeneticAlgorithm.php',
    'ga/DBLoader.php', 
    'ga/TimetableRepresentation.php',
    'ga/ConstraintChecker.php',
    'ga/FitnessEvaluator.php'
];

foreach ($ga_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>$file exists</p>\n";
    } else {
        $issues[] = "Required GA file missing: $file";
        echo "<p style='color: red;'>Required GA file missing: $file</p>\n";
    }
}

// Test 11: Stream Manager
echo "<h2>11. Stream Manager</h2>\n";
if (file_exists('includes/stream_manager.php')) {
    echo "<p style='color: green;'>Stream manager exists</p>\n";
} else {
    $issues[] = "Stream manager missing";
    echo "<p style='color: red;'>Stream manager missing</p>\n";
}

// Test 12: Flash Helper
echo "<h2>12. Flash Helper</h2>\n";
if (file_exists('includes/flash.php')) {
    echo "<p style='color: green;'>Flash helper exists</p>\n";
} else {
    $warnings[] = "Flash helper missing";
    echo "<p style='color: orange;'>Flash helper missing</p>\n";
}

// Summary
echo "<h2>Summary</h2>\n";
echo "<div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px;'>\n";
echo "<h3>Issues Found (" . count($issues) . ")</h3>\n";
if (empty($issues)) {
    echo "<p style='color: green;'>No critical issues found!</p>\n";
} else {
    echo "<ul>\n";
    foreach ($issues as $issue) {
        echo "<li style='color: red;'>$issue</li>\n";
    }
    echo "</ul>\n";
}

echo "<h3>Warnings (" . count($warnings) . ")</h3>\n";
if (empty($warnings)) {
    echo "<p style='color: green;'>No warnings</p>\n";
} else {
    echo "<ul>\n";
    foreach ($warnings as $warning) {
        echo "<li style='color: orange;'>$warning</li>\n";
    }
    echo "</ul>\n";
}

echo "<h3>Recommendations</h3>\n";
if (empty($issues)) {
    echo "<p style='color: green;'>Your system appears ready for timetable generation!</p>\n";
    echo "<p>You can proceed to generate timetables. However, consider addressing the warnings above for optimal performance.</p>\n";
} else {
    echo "<p style='color: red;'>Please fix the critical issues above before attempting timetable generation.</p>\n";
    echo "<p>Key steps to resolve:</p>\n";
    echo "<ol>\n";
    if (in_array("Database connection failed", $issues)) {
        echo "<li>Check your database connection settings in connect.php</li>\n";
    }
    if (array_filter($issues, function($i) { return strpos($i, 'Table') !== false; })) {
        echo "<li>Run the database setup script or import the schema</li>\n";
    }
    if (array_filter($issues, function($i) { return strpos($i, 'GA file') !== false; })) {
        echo "<li>Ensure all genetic algorithm components are present</li>\n";
    }
    echo "</ol>\n";
}
echo "</div>\n";

// Test timetable generation readiness
echo "<h2>13. Timetable Generation Test</h2>\n";
if (empty($issues)) {
    echo "<p>Testing timetable generation components...</p>\n";
    
    try {
        // Test DBLoader
        require_once __DIR__ . '/ga/DBLoader.php';
        $loader = new DBLoader($conn);
        echo "<p style='color: green;'>DBLoader initialized successfully</p>\n";
        
        // Test data loading
        $data = $loader->loadAll(['stream_id' => 1, 'academic_year' => '2025/2026', 'semester' => 1]);
        echo "<p style='color: green;'>Data loading successful</p>\n";
        
        // Test validation
        $validation = $loader->validateDataForGeneration($data);
        if ($validation['valid']) {
            echo "<p style='color: green;'>Data validation passed</p>\n";
        } else {
            echo "<p style='color: orange;'>Data validation warnings:</p>\n";
            echo "<ul>\n";
            foreach ($validation['warnings'] as $warning) {
                echo "<li style='color: orange;'>$warning</li>\n";
            }
            echo "</ul>\n";
        }
        
    } catch (Exception $e) {
        $issues[] = "Timetable generation test failed: " . $e->getMessage();
        echo "<p style='color: red;'>Timetable generation test failed: " . $e->getMessage() . "</p>\n";
    }
} else {
    echo "<p style='color: red;'>Skipping timetable generation test due to critical issues</p>\n";
}

echo "<hr>\n";
echo "<p><strong>Analysis completed.</strong> " . count($issues) . " issues, " . count($warnings) . " warnings found.</p>\n";
?>
