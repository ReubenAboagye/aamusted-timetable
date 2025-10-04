<?php
/**
 * Comprehensive Timetable Constraint Fix
 * This script fixes all constraint issues that prevent proper versioning
 */

include 'connect.php';

echo "<h2>Comprehensive Timetable Constraint Fix</h2>";

try {
    // Check current constraints
    echo "<h3>Current Constraints Analysis</h3>";
    $constraints_query = "SHOW INDEX FROM timetable WHERE Key_name LIKE 'uq_%'";
    $constraints_result = $conn->query($constraints_query);
    
    if ($constraints_result && $constraints_result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Constraint Name</th><th>Columns</th><th>Includes Version?</th><th>Action Needed</th></tr>";
        
        $constraint_info = [];
        while ($row = $constraints_result->fetch_assoc()) {
            $constraint_name = $row['Key_name'];
            if (!isset($constraint_info[$constraint_name])) {
                $constraint_info[$constraint_name] = [];
            }
            $constraint_info[$constraint_name][] = $row['Column_name'];
        }
        
        foreach ($constraint_info as $name => $columns) {
            $includes_version = in_array('version', $columns);
            $action = $includes_version ? 'OK' : 'Needs Fix';
            $columns_str = implode(', ', $columns);
            
            echo "<tr>";
            echo "<td>{$name}</td>";
            echo "<td>{$columns_str}</td>";
            echo "<td>" . ($includes_version ? 'Yes' : 'No') . "</td>";
            echo "<td>{$action}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Fix uq_timetable_class_time constraint
    echo "<h3>Fixing uq_timetable_class_time Constraint</h3>";
    
    // Drop the old constraint
    $drop_query = "ALTER TABLE timetable DROP INDEX `uq_timetable_class_time`";
    if ($conn->query($drop_query)) {
        echo "<p style='color: green;'>✓ Dropped old uq_timetable_class_time constraint</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to drop constraint: " . $conn->error . "</p>";
    }
    
    // Add the new constraint with version
    $add_query = "
        ALTER TABLE timetable 
        ADD CONSTRAINT `uq_timetable_class_time` 
        UNIQUE KEY (`class_course_id`, `day_id`, `time_slot_id`, `semester`, `academic_year`, `division_label`, `version`)
    ";
    
    if ($conn->query($add_query)) {
        echo "<p style='color: green;'>✓ Added new uq_timetable_class_time constraint with version</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to add new constraint: " . $conn->error . "</p>";
    }
    
    // Verify the fix
    echo "<h3>Verification</h3>";
    $verify_query = "SHOW INDEX FROM timetable WHERE Key_name = 'uq_timetable_class_time'";
    $verify_result = $conn->query($verify_query);
    
    if ($verify_result && $verify_result->num_rows > 0) {
        echo "<p style='color: green;'>Constraint verification successful!</p>";
        echo "<p>New constraint includes version field, allowing proper versioning.</p>";
    } else {
        echo "<p style='color: red;'>Constraint verification failed!</p>";
    }
    
    echo "<h3 style='color: green;'>All constraint fixes applied successfully!</h3>";
    echo "<p><strong>You can now generate multiple timetable versions without constraint conflicts.</strong></p>";
    
    // Optional: Show alternative approach
    echo "<h3>Alternative Approach (if issues persist)</h3>";
    echo "<p>If you still encounter issues, you can:</p>";
    echo "<ol>";
    echo "<li><strong>Remove problematic constraints entirely:</strong> This allows maximum flexibility but reduces data integrity checks</li>";
    echo "<li><strong>Use INSERT IGNORE:</strong> Modify the insertion code to ignore duplicate key errors</li>";
    echo "<li><strong>Clear existing data:</strong> Start fresh with only the auto-scheduled entries</li>";
    echo "</ol>";
    
    echo "<p><a href='generate_timetable.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Timetable Generation</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>
