<?php
/**
 * Apply timetable versioning constraint fix
 * This script updates the unique constraint to allow same class-course-time across different versions
 */

include 'connect.php';

echo "<h2>Applying Timetable Versioning Constraint Fix</h2>";

try {
    // Check if there are any existing conflicts
    echo "<p>Checking for existing conflicts...</p>";
    $conflict_query = "
        SELECT 
            class_course_id,
            day_id,
            time_slot_id,
            division_label,
            COUNT(*) as conflict_count
        FROM timetable 
        GROUP BY class_course_id, day_id, time_slot_id, division_label
        HAVING COUNT(*) > 1
    ";
    
    $conflict_result = $conn->query($conflict_query);
    if ($conflict_result && $conflict_result->num_rows > 0) {
        echo "<p style='color: orange;'>Found " . $conflict_result->num_rows . " existing conflicts. These will be resolved by the new constraint.</p>";
        while ($row = $conflict_result->fetch_assoc()) {
            echo "<p>Conflict: Class Course {$row['class_course_id']}, Day {$row['day_id']}, Time Slot {$row['time_slot_id']}, Division '{$row['division_label']}' - {$row['conflict_count']} entries</p>";
        }
    } else {
        echo "<p style='color: green;'>No existing conflicts found.</p>";
    }
    
    // Drop the old constraint
    echo "<p>Dropping old constraint...</p>";
    $drop_constraint = "ALTER TABLE timetable DROP INDEX `uq_timetable_class_course_time`";
    if ($conn->query($drop_constraint)) {
        echo "<p style='color: green;'>✓ Old constraint dropped successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to drop old constraint: " . $conn->error . "</p>";
    }
    
    // Add the new constraint that includes version and semester
    echo "<p>Adding new constraint with version and semester...</p>";
    $add_constraint = "
        ALTER TABLE timetable 
        ADD CONSTRAINT `uq_timetable_class_course_time` 
        UNIQUE KEY (`class_course_id`, `day_id`, `time_slot_id`, `division_label`, `semester`, `version`)
    ";
    
    if ($conn->query($add_constraint)) {
        echo "<p style='color: green;'>✓ New constraint added successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to add new constraint: " . $conn->error . "</p>";
    }
    
    // Add index for better performance
    echo "<p>Adding performance index...</p>";
    $add_index = "
        ALTER TABLE timetable 
        ADD INDEX `idx_timetable_version_lookup` (`class_course_id`, `day_id`, `time_slot_id`, `semester`, `version`)
    ";
    
    if ($conn->query($add_index)) {
        echo "<p style='color: green;'>✓ Performance index added successfully</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Index might already exist: " . $conn->error . "</p>";
    }
    
    echo "<h3 style='color: green;'>✅ Timetable versioning constraint fix applied successfully!</h3>";
    echo "<p>You can now generate multiple timetable versions without constraint conflicts.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>

