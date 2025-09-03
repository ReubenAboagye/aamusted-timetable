<?php
/**
 * Stream Fixes Testing Script
 * This script tests all the implemented fixes to ensure they work correctly
 */

include 'connect.php';

// Include necessary components
if (file_exists(__DIR__ . '/includes/stream_manager.php')) {
    include_once __DIR__ . '/includes/stream_manager.php';
}

if (file_exists(__DIR__ . '/includes/conflict_detector.php')) {
    include_once __DIR__ . '/includes/conflict_detector.php';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stream Fixes Test Suite</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h1>🧪 Stream Fixes Test Suite</h1>
    <p class="text-muted">Testing all implemented stream fixes...</p>
    
    <?php
    $tests_passed = 0;
    $tests_failed = 0;
    $total_tests = 0;
    
    function runTest($test_name, $test_function) {
        global $tests_passed, $tests_failed, $total_tests;
        $total_tests++;
        
        echo "<div class='card mb-3'>";
        echo "<div class='card-header'><h5>Test: $test_name</h5></div>";
        echo "<div class='card-body'>";
        
        try {
            $result = $test_function();
            if ($result === true) {
                echo "<div class='alert alert-success'>✅ PASSED</div>";
                $tests_passed++;
            } else {
                echo "<div class='alert alert-danger'>❌ FAILED: $result</div>";
                $tests_failed++;
            }
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>❌ FAILED: " . $e->getMessage() . "</div>";
            $tests_failed++;
        }
        
        echo "</div></div>";
    }
    
    // Test 1: Stream Manager functionality
    runTest("Stream Manager Initialization", function() use ($conn) {
        if (!class_exists('StreamManager')) {
            return "StreamManager class not found";
        }
        
        $streamManager = getStreamManager();
        $currentId = $streamManager->getCurrentStreamId();
        
        if (!is_numeric($currentId) || $currentId <= 0) {
            return "Invalid current stream ID: $currentId";
        }
        
        return true;
    });
    
    // Test 2: Stream filtering functionality
    runTest("Stream Filtering Logic", function() use ($conn) {
        $streamManager = getStreamManager();
        
        // Test addStreamFilter method
        $sql = "SELECT * FROM classes";
        $filtered_sql = $streamManager->addStreamFilter($sql, 'classes');
        
        if (strpos($filtered_sql, 'stream_id') === false) {
            return "Stream filter not applied to classes table";
        }
        
        // Test courses filtering
        $sql2 = "SELECT * FROM courses";
        $filtered_sql2 = $streamManager->addStreamFilter($sql2, 'courses');
        
        if (strpos($filtered_sql2, 'stream_id') === false) {
            return "Stream filter not applied to courses table";
        }
        
        return true;
    });
    
    // Test 3: Database schema validation
    runTest("Database Schema Validation", function() use ($conn) {
        // Check if required tables exist
        $required_tables = ['streams', 'stream_time_slots', 'stream_days', 'migrations'];
        
        foreach ($required_tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if (!$result || $result->num_rows == 0) {
                return "Required table '$table' not found";
            }
        }
        
        // Check if required columns exist
        $required_columns = [
            'class_courses.stream_id',
            'timetable.stream_id', 
            'streams.period_start',
            'streams.period_end'
        ];
        
        foreach ($required_columns as $column) {
            list($table, $col) = explode('.', $column);
            $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$col'");
            if (!$result || $result->num_rows == 0) {
                return "Required column '$column' not found";
            }
        }
        
        return true;
    });
    
    // Test 4: Stream consistency validation
    runTest("Stream Consistency Validation", function() use ($conn) {
        if (!class_exists('StreamManager')) {
            return "StreamManager not available";
        }
        
        $streamManager = getStreamManager();
        
        // Test with valid same-stream assignment (get first class and course from same stream)
        $test_sql = "SELECT c.id as class_id, co.id as course_id 
                     FROM classes c 
                     JOIN courses co ON c.stream_id = co.stream_id 
                     WHERE c.is_active = 1 AND co.is_active = 1 
                     LIMIT 1";
        $result = $conn->query($test_sql);
        
        if ($result && $row = $result->fetch_assoc()) {
            $valid = $streamManager->validateClassCourseStreamConsistency($row['class_id'], $row['course_id']);
            if (!$valid) {
                return "Validation failed for same-stream class-course pair";
            }
        }
        
        return true;
    });
    
    // Test 5: Conflict detector functionality
    runTest("Conflict Detector Functionality", function() use ($conn) {
        if (!class_exists('ConflictDetector')) {
            return "ConflictDetector class not found";
        }
        
        $conflictDetector = getConflictDetector();
        
        // Test with a simple scenario (this will likely have conflicts in a populated DB)
        $conflicts = $conflictDetector->checkConflicts(1, 1, 1, 1, 1);
        
        // The function should return an array (empty or with conflicts)
        if (!is_array($conflicts)) {
            return "Conflict detector should return an array";
        }
        
        return true;
    });
    
    // Test 6: Database triggers and constraints
    runTest("Database Triggers and Constraints", function() use ($conn) {
        // Test if triggers exist
        $triggers = ['prevent_cross_stream_assignments', 'validate_room_capacity'];
        
        foreach ($triggers as $trigger) {
            $result = $conn->query("SHOW TRIGGERS LIKE '$trigger'");
            if (!$result || $result->num_rows == 0) {
                return "Trigger '$trigger' not found";
            }
        }
        
        // Test if stored procedures exist
        $procedures = ['assign_course_to_class', 'insert_timetable_entry'];
        
        foreach ($procedures as $procedure) {
            $result = $conn->query("SHOW PROCEDURE STATUS WHERE Name = '$procedure'");
            if (!$result || $result->num_rows == 0) {
                return "Stored procedure '$procedure' not found";
            }
        }
        
        return true;
    });
    
    // Test 7: Views and functions
    runTest("Views and Functions", function() use ($conn) {
        // Test if views exist
        $views = ['valid_class_course_combinations', 'timetable_view', 'timetable_conflicts', 'stream_statistics'];
        
        foreach ($views as $view) {
            $result = $conn->query("SHOW FULL TABLES WHERE Table_type = 'VIEW' AND Tables_in_timetable_system = '$view'");
            if (!$result || $result->num_rows == 0) {
                return "View '$view' not found";
            }
        }
        
        // Test if functions exist
        $functions = ['validate_stream_consistency', 'check_timetable_conflicts', 'get_stream_utilization'];
        
        foreach ($functions as $function) {
            $result = $conn->query("SHOW FUNCTION STATUS WHERE Name = '$function'");
            if (!$result || $result->num_rows == 0) {
                return "Function '$function' not found";
            }
        }
        
        return true;
    });
    
    // Test 8: Performance indexes
    runTest("Performance Indexes", function() use ($conn) {
        $required_indexes = [
            'classes' => ['idx_classes_stream_active'],
            'courses' => ['idx_courses_stream_active'],
            'lecturers' => ['idx_lecturers_stream_active'],
            'timetable' => ['idx_timetable_lookup', 'idx_timetable_stream_day_time']
        ];
        
        foreach ($required_indexes as $table => $indexes) {
            foreach ($indexes as $index) {
                $result = $conn->query("SHOW INDEX FROM $table WHERE Key_name = '$index'");
                if (!$result || $result->num_rows == 0) {
                    return "Index '$index' not found on table '$table'";
                }
            }
        }
        
        return true;
    });
    
    // Summary
    echo "<div class='card mt-4'>";
    echo "<div class='card-header'><h4>Test Results Summary</h4></div>";
    echo "<div class='card-body'>";
    
    if ($tests_failed == 0) {
        echo "<div class='alert alert-success'>";
        echo "<h5>🎉 All Tests Passed!</h5>";
        echo "<p>All $total_tests tests passed successfully. The stream fixes are working correctly.</p>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-danger'>";
        echo "<h5>⚠️ Some Tests Failed</h5>";
        echo "<p>$tests_passed out of $total_tests tests passed. $tests_failed tests failed.</p>";
        echo "<p><strong>Action Required:</strong> Review failed tests and ensure migrations were applied correctly.</p>";
        echo "</div>";
    }
    
    echo "<div class='row mt-3'>";
    echo "<div class='col-md-4'>";
    echo "<div class='card text-center'>";
    echo "<div class='card-body'>";
    echo "<h5 class='text-success'>$tests_passed</h5>";
    echo "<p class='card-text'>Tests Passed</p>";
    echo "</div></div></div>";
    
    echo "<div class='col-md-4'>";
    echo "<div class='card text-center'>";
    echo "<div class='card-body'>";
    echo "<h5 class='text-danger'>$tests_failed</h5>";
    echo "<p class='card-text'>Tests Failed</p>";
    echo "</div></div></div>";
    
    echo "<div class='col-md-4'>";
    echo "<div class='card text-center'>";
    echo "<div class='card-body'>";
    echo "<h5 class='text-info'>$total_tests</h5>";
    echo "<p class='card-text'>Total Tests</p>";
    echo "</div></div></div>";
    
    echo "</div>"; // End row
    echo "</div></div>"; // End card
    ?>
    
    <div class="mt-4">
        <a href="run_migrations.php" class="btn btn-warning">Run Migrations</a>
        <a href="validate_stream_consistency.php" class="btn btn-info">Validate Consistency</a>
        <a href="generate_timetable.php" class="btn btn-success">Test Timetable Generation</a>
        <a href="index.php" class="btn btn-secondary">Return to Dashboard</a>
    </div>
</div>
</body>
</html>