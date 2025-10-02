<?php
include 'connect.php';

// Check for combined classes in the database
$query = "
    SELECT t.*, 
           l.name as lecturer_name,
           c.name as course_name,
           cl.name as class_name,
           d.name as day_name,
           ts.start_time, ts.end_time,
           r.name as room_name, r.capacity
    FROM timetable t
    JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    JOIN lecturers l ON lc.lecturer_id = l.id
    JOIN class_courses cc ON t.class_course_id = cc.id
    JOIN classes cl ON cc.class_id = cl.id
    JOIN courses c ON cc.course_id = c.id
    JOIN days d ON t.day_id = d.id
    JOIN time_slots ts ON t.time_slot_id = ts.id
    JOIN rooms r ON t.room_id = r.id
    WHERE t.is_combined = 1
    ORDER BY t.day_id, ts.start_time, l.name, cl.name
";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    echo "COMBINED CLASSES FOUND:\n";
    echo "========================\n";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo sprintf(
            "Day: %s, Time: %s-%s\n",
            $row['day_name'],
            $row['start_time'],
            $row['end_time']
        );
        echo sprintf(
            "Lecturer: %s\n",
            $row['lecturer_name']
        );
        echo sprintf(
            "Course: %s\n",
            $row['course_name']
        );
        echo sprintf(
            "Class: %s\n",
            $row['class_name']
        );
        echo sprintf(
            "Room: %s (Capacity: %d)\n",
            $row['room_name'],
            $row['capacity']
        );
        if (!empty($row['combined_classes'])) {
            echo sprintf(
                "Combined Classes: %s\n",
                $row['combined_classes']
            );
        }
        echo "---\n";
    }
} else {
    echo "No combined classes found in the database.\n";
}

// Also check for potential combinations (same lecturer, same time, same course)
echo "\nPOTENTIAL COMBINATION OPPORTUNITIES:\n";
echo "=====================================\n";

$potential_query = "
    SELECT 
        l.name as lecturer_name,
        c.name as course_name,
        d.name as day_name,
        ts.start_time, ts.end_time,
        r.name as room_name, r.capacity,
        COUNT(*) as class_count,
        GROUP_CONCAT(cl.name ORDER BY cl.name SEPARATOR ', ') as classes
    FROM timetable t
    JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    JOIN lecturers l ON lc.lecturer_id = l.id
    JOIN class_courses cc ON t.class_course_id = cc.id
    JOIN classes cl ON cc.class_id = cl.id
    JOIN courses c ON cc.course_id = c.id
    JOIN days d ON t.day_id = d.id
    JOIN time_slots ts ON t.time_slot_id = ts.id
    JOIN rooms r ON t.room_id = r.id
    WHERE t.is_combined = 0
    GROUP BY l.id, c.id, d.id, ts.id, r.id
    HAVING COUNT(*) > 1
    ORDER BY l.name, c.name, d.name, ts.start_time
";

$potential_result = mysqli_query($conn, $potential_query);

if ($potential_result && mysqli_num_rows($potential_result) > 0) {
    while ($row = mysqli_fetch_assoc($potential_result)) {
        echo sprintf(
            "Lecturer: %s, Course: %s\n",
            $row['lecturer_name'],
            $row['course_name']
        );
        echo sprintf(
            "Day: %s, Time: %s-%s\n",
            $row['day_name'],
            $row['start_time'],
            $row['end_time']
        );
        echo sprintf(
            "Room: %s (Capacity: %d)\n",
            $row['room_name'],
            $row['capacity']
        );
        echo sprintf(
            "Classes: %s (%d classes)\n",
            $row['classes'],
            $row['class_count']
        );
        echo "---\n";
    }
} else {
    echo "No potential combination opportunities found.\n";
}

mysqli_close($conn);
?>
