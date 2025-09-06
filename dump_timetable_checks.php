<?php
// Usage: php dump_timetable_checks.php <stream_id> [class_id]
// Example: php dump_timetable_checks.php 3 18

if ($argc < 2) {
    echo "Usage: php dump_timetable_checks.php <stream_id> [class_id]\n";
    exit(1);
}
$streamId = (int)$argv[1];
$classId = isset($argv[2]) ? (int)$argv[2] : 0;

include 'connect.php';

echo "Query A: count rows per class / division for stream $streamId\n";
$sqlA = "SELECT c.id AS class_id, c.name AS class_name, COALESCE(t.division_label, '(none)') AS division, COUNT(*) AS cnt
FROM timetable t
JOIN class_courses cc ON t.class_course_id = cc.id
JOIN classes c ON cc.class_id = c.id
WHERE c.stream_id = ? 
GROUP BY c.id, division
ORDER BY c.name, division";
$stmt = $conn->prepare($sqlA);
$stmt->bind_param('i', $streamId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    echo sprintf("%s | %s | %d\n", $r['class_name'], $r['division'], $r['cnt']);
}
$stmt->close();

echo "\nQuery B: class_course -> divisions seen in timetable (stream $streamId)\n";
$sqlB = "SELECT cc.id AS class_course_id, c.id AS class_id, c.name AS class_name, cc.course_id, GROUP_CONCAT(DISTINCT t.division_label ORDER BY t.division_label) AS divisions
FROM class_courses cc
JOIN classes c ON cc.class_id = c.id
LEFT JOIN timetable t ON t.class_course_id = cc.id
WHERE c.stream_id = ?
GROUP BY cc.id, c.id
ORDER BY c.name, cc.id";
$stmt = $conn->prepare($sqlB);
$stmt->bind_param('i', $streamId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
    echo sprintf("cc:%d class:%s (id:%d) course:%d divisions:%s\n", $r['class_course_id'], $r['class_name'], $r['class_id'], $r['course_id'], ($r['divisions'] ?: '(none)'));
}
$stmt->close();

if ($classId > 0) {
    echo "\nQuery C: timetable rows for class id $classId (show division, day, period, course)\n";
    $sqlC = "SELECT d.name AS day, ts.start_time, ts.end_time, c.name AS class_name, t.division_label, co.code AS course_code, co.name AS course_name, IFNULL(l.name,'') AS lecturer, r.name AS room
    FROM timetable t
    JOIN class_courses cc ON t.class_course_id = cc.id
    JOIN classes c ON cc.class_id = c.id
    JOIN courses co ON cc.course_id = co.id
    LEFT JOIN lecturer_courses lc ON t.lecturer_course_id = lc.id
    LEFT JOIN lecturers l ON lc.lecturer_id = l.id
    JOIN days d ON t.day_id = d.id
    JOIN time_slots ts ON t.time_slot_id = ts.id
    JOIN rooms r ON t.room_id = r.id
    WHERE c.id = ?
    ORDER BY d.id, ts.start_time";
    $stmt = $conn->prepare($sqlC);
    $stmt->bind_param('i', $classId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        echo sprintf("%s | %s-%s | division:%s | %s %s | %s | %s\n", $r['day'], $r['start_time'], $r['end_time'], ($r['division_label']?:'(none)'), $r['course_code'], $r['course_name'], $r['lecturer'], $r['room']);
    }
    $stmt->close();
}

$conn->close();
?>
