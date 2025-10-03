<?php
// Utility: find a class that has timetable entries with division labels
include 'connect.php';

$sql = "SELECT c.id AS class_id, c.name AS class_name, COUNT(DISTINCT t.division_label) AS divisions
        FROM timetable t
        JOIN class_courses cc ON t.class_course_id = cc.id
        JOIN classes c ON cc.class_id = c.id
        WHERE t.division_label IS NOT NULL AND t.division_label <> ''
        GROUP BY c.id, c.name
        ORDER BY divisions DESC, c.name ASC
        LIMIT 1";

$res = $conn->query($sql);
if ($res && $row = $res->fetch_assoc()) {
    echo $row['class_id'] . "\t" . $row['class_name'] . "\t" . $row['divisions'] . "\n";
    exit(0);
}

// Fallback: find any class present in timetable
$res2 = $conn->query("SELECT DISTINCT c.id AS class_id, c.name AS class_name FROM timetable t JOIN class_courses cc ON t.class_course_id = cc.id JOIN classes c ON cc.class_id = c.id LIMIT 1");
if ($res2 && $row2 = $res2->fetch_assoc()) {
    echo $row2['class_id'] . "\t" . $row2['class_name'] . "\t0\n";
    exit(0);
}

// Nothing found
fwrite(STDERR, "No classes found in timetable.\n");
exit(1);
?>


