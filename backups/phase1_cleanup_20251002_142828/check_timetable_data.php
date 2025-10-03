<?php
/**
 * Simple script to check timetable data and test constraint checking
 */

include 'connect.php';

echo "<h2>Timetable Data Check</h2>\n";

// Check timetable entries
$query = "SELECT COUNT(*) as count FROM timetable";
$result = $conn->query($query);
$count = $result->fetch_assoc()['count'];
echo "<p>Total timetable entries: $count</p>\n";

if ($count > 0) {
    // Show some sample entries
    $query = "SELECT t.*, l.name as lecturer_name, c.name as course_name, cl.name as class_name 
              FROM timetable t 
              LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
              LEFT JOIN lecturers l ON lc.lecturer_id = l.id
              LEFT JOIN class_courses cc ON t.class_course_id = cc.id
              LEFT JOIN courses c ON cc.course_id = c.id
              LEFT JOIN classes cl ON cc.class_id = cl.id
              LIMIT 10";
    $result = $conn->query($query);
    
    echo "<h3>Sample Entries:</h3>\n";
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>ID</th><th>Lecturer</th><th>Course</th><th>Class</th><th>Day</th><th>Time Slot</th><th>Room</th></tr>\n";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>\n";
        echo "<td>{$row['id']}</td>\n";
        echo "<td>{$row['lecturer_name']}</td>\n";
        echo "<td>{$row['course_name']}</td>\n";
        echo "<td>{$row['class_name']}</td>\n";
        echo "<td>{$row['day_id']}</td>\n";
        echo "<td>{$row['time_slot_id']}</td>\n";
        echo "<td>{$row['room_id']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // Check for lecturer conflicts
    $query = "
        SELECT 
            lc.lecturer_id,
            l.name as lecturer_name,
            t.day_id,
            t.time_slot_id,
            COUNT(*) as conflict_count
        FROM timetable t
        LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
        LEFT JOIN lecturers l ON lc.lecturer_id = l.id
        WHERE lc.lecturer_id IS NOT NULL
        GROUP BY lc.lecturer_id, t.day_id, t.time_slot_id
        HAVING COUNT(*) > 1
        ORDER BY conflict_count DESC
    ";
    
    $result = $conn->query($query);
    
    echo "<h3>Lecturer Conflicts:</h3>\n";
    if ($result->num_rows > 0) {
        echo "<p style='color: red;'>Found lecturer conflicts:</p>\n";
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Lecturer</th><th>Day</th><th>Time Slot</th><th>Count</th></tr>\n";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>\n";
            echo "<td>{$row['lecturer_name']}</td>\n";
            echo "<td>{$row['day_id']}</td>\n";
            echo "<td>{$row['time_slot_id']}</td>\n";
            echo "<td>{$row['conflict_count']}</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<p style='color: green;'>No lecturer conflicts found in database</p>\n";
    }
}

$conn->close();
?>

