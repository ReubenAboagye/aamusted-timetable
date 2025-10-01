<?php
/**
 * Remove Problematic Constraints (Alternative Approach)
 * This script removes constraints that prevent versioning, allowing maximum flexibility
 */

include 'connect.php';

echo "<h2>Remove Problematic Constraints (Alternative Approach)</h2>";
echo "<p style='color: orange;'><strong>Warning:</strong> This approach removes data integrity constraints. Use only if the constraint fixes don't work.</p>";

if (isset($_POST['confirm_remove'])) {
    try {
        echo "<h3>Removing Problematic Constraints</h3>";
        
        // List of constraints to remove
        $constraints_to_remove = [
            'uq_timetable_class_time',
            'uq_timetable_class_course_time',
            'uq_timetable_same_version'
        ];
        
        foreach ($constraints_to_remove as $constraint) {
            $drop_query = "ALTER TABLE timetable DROP INDEX `{$constraint}`";
            if ($conn->query($drop_query)) {
                echo "<p style='color: green;'>✓ Removed constraint: {$constraint}</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Constraint {$constraint} may not exist: " . $conn->error . "</p>";
            }
        }
        
        echo "<h3 style='color: green;'>✅ All problematic constraints removed!</h3>";
        echo "<p><strong>You can now generate timetable versions without constraint conflicts.</strong></p>";
        echo "<p><em>Note: This removes some data integrity checks. Make sure your application logic handles duplicates appropriately.</em></p>";
        
        echo "<p><a href='generate_timetable.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Timetable Generation</a></p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<h3>Current Constraints</h3>";
    
    $constraints_query = "SHOW INDEX FROM timetable WHERE Key_name LIKE 'uq_%'";
    $constraints_result = $conn->query($constraints_query);
    
    if ($constraints_result && $constraints_result->num_rows > 0) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Constraint Name</th><th>Columns</th><th>Will Be Removed</th></tr>";
        
        $constraint_info = [];
        while ($row = $constraints_result->fetch_assoc()) {
            $constraint_name = $row['Key_name'];
            if (!isset($constraint_info[$constraint_name])) {
                $constraint_info[$constraint_name] = [];
            }
            $constraint_info[$constraint_name][] = $row['Column_name'];
        }
        
        foreach ($constraint_info as $name => $columns) {
            $will_remove = in_array($name, ['uq_timetable_class_time', 'uq_timetable_class_course_time', 'uq_timetable_same_version']);
            $columns_str = implode(', ', $columns);
            
            echo "<tr>";
            echo "<td>{$name}</td>";
            echo "<td>{$columns_str}</td>";
            echo "<td>" . ($will_remove ? '❌ Yes' : '✅ Keep') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>Confirmation Required</h3>";
    echo "<p>This will remove the following constraints:</p>";
    echo "<ul>";
    echo "<li><strong>uq_timetable_class_time</strong> - Prevents same class-course-time across versions</li>";
    echo "<li><strong>uq_timetable_class_course_time</strong> - Prevents same class-course-time across versions</li>";
    echo "<li><strong>uq_timetable_same_version</strong> - May prevent room conflicts across versions</li>";
    echo "</ul>";
    
    echo "<form method='POST'>";
    echo "<p><input type='checkbox' name='confirm_remove' value='1' required> I understand this removes data integrity constraints</p>";
    echo "<p><button type='submit' style='background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Remove Constraints</button></p>";
    echo "</form>";
    
    echo "<p><a href='fix_all_constraints.php' style='color: #007bff;'>← Back to Constraint Fix Approach</a></p>";
}

$conn->close();
?>
